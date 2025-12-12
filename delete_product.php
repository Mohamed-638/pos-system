<?php
// delete_product.php - معالجة حذف المنتج
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// التحقق من صلاحية المدير
check_access('admin'); 

// المسار النسبي الذي يتم تخزينه في قاعدة البيانات (للعرض على الويب)
$db_upload_path = 'images/products/';

// المسار المطلق لنظام الملفات (للحذف)
$server_root = dirname(__FILE__) . '/';
$server_upload_dir = $server_root . $db_upload_path;


$delete_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;
$message = '';

if ($delete_id > 0) {
    try {
        // 1. جلب مسار الصورة لحذفها من المجلد
        $sql_get_image = "SELECT image_path FROM products WHERE product_id = ?";
        $stmt_get = $conn->prepare($sql_get_image);
        $stmt_get->bind_param("i", $delete_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        $row_image = $result_get->fetch_assoc();
        $image_to_delete = $row_image['image_path'] ?? null;
        $stmt_get->close();

        // 2. حذف سجل المنتج من قاعدة البيانات
        $stmt_delete = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt_delete->bind_param("i", $delete_id);
        
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                // 3. حذف ملف الصورة فعلياً من المجلد
                if ($image_to_delete && file_exists($server_upload_dir . $image_to_delete)) {
                    // نستخدم @unlink لتجاهل رسائل الخطأ في حال لم يتمكن من الحذف
                    @unlink($server_upload_dir . $image_to_delete);
                }
                $message = "✅ تم حذف المنتج رقم #{$delete_id} بنجاح.";
            } else {
                $message = "⚠️ المنتج رقم #{$delete_id} غير موجود أو فشل حذفه.";
            }
        } else {
            $message = "❌ خطأ في الحذف: قد يكون المنتج مرتبطاً ببيانات مبيعات سابقة. (" . $stmt_delete->error . ")";
        }
        $stmt_delete->close();
    } catch (Exception $e) {
        $message = "❌ حدث خطأ غير متوقع أثناء الحذف: " . $e->getMessage();
    }
} else {
    $message = "❌ لم يتم تحديد رقم المنتج للحذف.";
}

$conn->close();

// إعادة التوجيه إلى صفحة العرض مع رسالة النظام
header("Location: view_products.php?message=" . urlencode($message));
exit();
?>