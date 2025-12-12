<?php
// process_sale.php - معالجة إتمام عملية بيع وحفظ البيانات (Advanced Ready & Secure)

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// التأكد من أن المستخدم الحالي هو الكاشير أو المدير
check_access(['cashier', 'admin']); 

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'حدث خطأ غير متوقع.'];

$current_user_id = $_SESSION['user_id'];
$current_branch_id = $_SESSION['branch_id'] ?? null;

// 1. التحقق من الطلب والتأكد من استقبال بيانات POST العادية
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cart_items'])) {
    $response['message'] = 'طلب غير صالح أو سلة المبيعات فارغة.';
    echo json_encode($response);
    exit;
}

// جلب البيانات من مصفوفة POST
$cart_items_json = $_POST['cart_items'];
$payment_method = $_POST['payment_method'] ?? 'Cash';
$total_amount_received = (float)($_POST['total_amount'] ?? 0);

// فك تشفير سلة المشتريات (JSON String -> PHP Array)
$cart_items = json_decode($cart_items_json, true);

if (empty($cart_items) || $total_amount_received <= 0) {
    $response['message'] = 'سلة المبيعات فارغة أو المبلغ الإجمالي غير صحيح.';
    echo json_encode($response);
    exit;
}

// ---------------------------------------------------
// 2. بدء المعاملة (Transaction) لضمان سلامة البيانات
// ---------------------------------------------------
$conn->begin_transaction();

$calculated_total = 0;
$total_cost = 0; 
$sale_id = null;

try {
    // 3. التحقق من الكميات وحساب الإجمالي النهائي والتكلفة (نستخدم البيانات المرسلة من الواجهة: cost و price)
    foreach ($cart_items as &$item) {
        $product_id = (int)$item['id'];
        $quantity = (float)$item['quantity'];
        
        // استخدام الأسعار والتكاليف المرسلة من الواجهة (تم التحقق منها عند إضافة المنتج في الواجهة)
        $price_at_sale = (float)$item['price'];
        $cost_at_sale = (float)$item['cost']; 

        // 🚨 التحقق من المخزون في هذه المرحلة عبر استعلام SELECT FOR UPDATE
        $check_sql = "SELECT stock FROM products WHERE product_id = ? FOR UPDATE";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $product_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        
        if (!$check_result || $check_result->num_rows === 0) {
            throw new Exception("المنتج ID: {$product_id} غير موجود.");
        }
        
        $current_stock = (int)$check_result->fetch_assoc()['stock'];
        $stmt_check->close();

        if ($current_stock < $quantity) {
            throw new Exception("المخزون غير كافٍ للمنتج ID: {$product_id}. المطلوب: {$quantity}، المتوفر: {$current_stock}.");
        }
        
        // حساب الإجمالي والتكلفة
        $calculated_total += $price_at_sale * $quantity;
        $total_cost += $cost_at_sale * $quantity;
        
        // إضافة البيانات إلى العنصر استعداداً لحفظه في sale_items
        $item['price_at_sale'] = $price_at_sale;
        $item['cost_at_sale'] = $cost_at_sale;
        $item['sub_total'] = $price_at_sale * $quantity;
    }
    unset($item);

    // 4. التأكد من أن الإجمالي المحسوب يطابق الإجمالي المرسل
    if (abs($calculated_total - $total_amount_received) > 0.01) {
        throw new Exception("فشل التحقق من الإجمالي. المحسوب: " . number_format($calculated_total, 2) . "، المرسل: " . number_format($total_amount_received, 2) . ".");
    }

    // 5. إدراج عملية البيع في جدول sales (باستخدام PREPARE)
    // 🟢 تم إضافة total_cost و sale_date
    $insert_sale_sql = "
        INSERT INTO sales (total_amount, total_cost, status, payment_method, sale_date, user_id, branch_id)
        VALUES (?, ?, 'completed', ?, NOW(), ?, ?)
    ";
    
    $stmt_sale = $conn->prepare($insert_sale_sql);
    // ربط المعاملات: d=total_amount, d=total_cost, s=payment_method, i=user_id
    $stmt_sale->bind_param("ddsii", $calculated_total, $total_cost, $payment_method, $current_user_id, $current_branch_id);
    
    if (!$stmt_sale->execute()) {
        throw new Exception("فشل إدراج عملية البيع الرئيسية: " . $stmt_sale->error);
    }
    
    $sale_id = $conn->insert_id; // جلب الـ ID للإيصال الجديد
    $stmt_sale->close();

    // 6. حفظ تفاصيل الإيصال في sale_items وتحديث المخزون (باستخدام PREPARE)
    
    // استعلام إدراج تفاصيل المنتج
    $insert_item_sql = "
        INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, cost_price, sub_total)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_item = $conn->prepare($insert_item_sql);
    
    // استعلام تحديث المخزون (طرح الكمية)
    $update_stock_sql = "
        UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?
    ";
    $stmt_stock = $conn->prepare($update_stock_sql);

    foreach ($cart_items as $item) {
        $product_id = (int)$item['id'];
        $product_name = $item['name'];
        $quantity = (float)$item['quantity'];
        $price = (float)$item['price_at_sale'];
        $cost_price = (float)$item['cost_at_sale'];
        $sub_total = (float)$item['sub_total'];

        // أ. إدراج تفاصيل المنتج
        // ربط المعاملات: i, i, s, d, d, d, d
        $stmt_item->bind_param("iissddd", $sale_id, $product_id, $product_name, $quantity, $price, $cost_price, $sub_total);
        if (!$stmt_item->execute()) {
            throw new Exception("فشل إدراج تفاصيل المنتج ID: {$product_id}.");
        }

        // ب. تحديث المخزون
        // ربط المعاملات: d=الكمية المطروحة, i=ID المنتج, d=الكمية للمقارنة
        $stmt_stock->bind_param("did", $quantity, $product_id, $quantity); 
        if (!$stmt_stock->execute()) {
            throw new Exception("خطأ في تحديث المخزون للمنتج ID: {$product_id}.");
        }
        
        // التحقق من أنه تم تحديث صف واحد على الأقل (لتأكيد توفر المخزون)
        if ($stmt_stock->affected_rows === 0) {
            // بالرغم من أننا تحققنا من المخزون في الخطوة 3، هذا يضمن التزامن
            throw new Exception("المخزون غير كافٍ للمنتج ID: {$product_id}. لم يتم إتمام العملية.");
        }
    }

    $stmt_item->close();
    $stmt_stock->close();

    // 7. إنهاء المعاملة بنجاح
    $conn->commit();

    $response['status'] = 'success';
    $response['message'] = '✅ تم إتمام عملية البيع بنجاح. رقم الفاتورة: ' . $sale_id;
    $response['sale_id'] = $sale_id;

} catch (Exception $e) {
    // التراجع عن جميع التغييرات في حالة حدوث أي خطأ
    $conn->rollback();
    error_log("Sale Transaction Failed: " . $e->getMessage()); 
    $response['message'] = 'فشل عملية البيع. الرجاء المحاولة مجدداً. (التفاصيل: ' . $e->getMessage() . ')';
}

echo json_encode($response);
$conn->close();
?>