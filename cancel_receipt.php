<?php
// cancel_receipt.php - معالج إلغاء الإيصال وإعادة المخزون
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
require_once 'config.php';

// يمكن للكاشير أو المدير إلغاء الإيصالات (يمكنك تغيير هذه الصلاحية حسب الحاجة)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'cashier')) {
    header("Location: login.php");
    exit();
}

$message = '';
$redirect_url = 'pos_screen.php'; // عادة ما نعود إلى شاشة نقاط البيع أو صفحة المبيعات

// 1. جلب معرف الإيصال المراد إلغاؤه
$sale_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;
$cancellation_reason = isset($_GET['reason']) ? filter_var($_GET['reason'], FILTER_SANITIZE_STRING) : 'إلغاء من قبل المستخدم.';

$current_user_id = $_SESSION['user_id'];

if ($sale_id <= 0) {
    $message = "❌ لم يتم تحديد الإيصال المراد إلغاؤه.";
    header("Location: {$redirect_url}?message=" . urlencode($message));
    exit();
}

// **2. بدء المعاملة (Transaction) لضمان سلامة المخزون**
$conn->begin_transaction();

try {
    // ----------------------------------------------------
    // أ. التأكد من أن الإيصال لم يتم إلغاؤه بالفعل
    // ----------------------------------------------------
    $stmt_check = $conn->prepare("SELECT status FROM sales WHERE sale_id = ?");
    $stmt_check->bind_param("i", $sale_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $sale_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$sale_data) {
        throw new Exception("الإيصال غير موجود.");
    }
    
    if ($sale_data['status'] === 'cancelled') {
        throw new Exception("الإيصال رقم {$sale_id} ملغى مسبقاً ولا يمكن إلغاؤه مرة أخرى.");
    }

    // ----------------------------------------------------
    // ب. جلب تفاصيل منتجات الإيصال
    // ----------------------------------------------------
    $stmt_items = $conn->prepare("SELECT product_id, quantity, product_name FROM sale_items WHERE sale_id = ?");
    $stmt_items->bind_param("i", $sale_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $items_to_revert = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    if (empty($items_to_revert)) {
        // لا يوجد عناصر لإلغائها، فقط قم بتحديث حالة الطلب
        error_log("No items found for sale ID: " . $sale_id);
    }
    
    // ----------------------------------------------------
    // ج. تحديث المخزون (إعادة الكميات)
    // ----------------------------------------------------
    $stmt_stock_revert = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");

    foreach ($items_to_revert as $item) {
        $product_id = (int)$item['product_id'];
        $quantity = (float)$item['quantity'];

        // ربط المعاملات: d=الكمية المضافة, i=ID المنتج
        $stmt_stock_revert->bind_param("di", $quantity, $product_id); 
        
        if (!$stmt_stock_revert->execute()) {
            throw new Exception("فشل في إعادة المخزون للمنتج: " . htmlspecialchars($item['product_name']) . ".");
        }
        // يمكن إضافة فحص affected_rows للتأكد من وجود المنتج، لكنه اختياري هنا
    }
    $stmt_stock_revert->close();

    // ----------------------------------------------------
    // د. تحديث حالة الإيصال إلى 'cancelled'
    // ----------------------------------------------------
    $stmt_cancel = $conn->prepare("UPDATE sales SET status = 'cancelled', canceled_by_user_id = ?, cancellation_reason = ? WHERE sale_id = ?");
    
    // ربط المعاملات: i=user_id, s=reason, i=sale_id
    $stmt_cancel->bind_param("isi", $current_user_id, $cancellation_reason, $sale_id);
    
    if (!$stmt_cancel->execute()) {
        throw new Exception("فشل في تحديث حالة الإيصال في جدول sales.");
    }
    $stmt_cancel->close();

    // **هـ. إنهاء المعاملة بنجاح**
    $conn->commit();
    $message = "✅ تم إلغاء الإيصال رقم **{$sale_id}** بنجاح وتمت إعادة المنتجات إلى المخزون.";

} catch (Exception $e) {
    // **و. التراجع عند الخطأ**
    $conn->rollback();
    error_log("Receipt Cancellation Failed (Sale ID: {$sale_id}): " . $e->getMessage()); 
    $message = "❌ فشل إلغاء الإيصال! (السبب: " . $e->getMessage() . ")";
}

$conn->close();

// إعادة التوجيه إلى الصفحة الرئيسية مع رسالة
header("Location: {$redirect_url}?message=" . urlencode($message));
exit();