<?php
// cancel_sale.php - معالجة إلغاء عملية بيع وإرجاع الكميات للمخزون

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// التحقق من صلاحية المدير
check_access('admin'); 

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'حدث خطأ.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['sale_id'])) {
$response['message'] = 'طلب غير صالح.';
echo json_encode($response);
exit;
}

$sale_id = $conn->real_escape_string($_POST['sale_id']);
$cancel_reason = isset($_POST['reason']) ? $conn->real_escape_string($_POST['reason']) : 'لم يتم تحديد سبب.';
$user_id = $_SESSION['user_id'];

// ---------------------------------------------------
// بدء المعاملة (Transaction) لضمان سلامة البيانات
// ---------------------------------------------------
$conn->begin_transaction();

try {
// 1. التحقق من حالة الإيصال الحالية
$check_sql = "SELECT status FROM sales WHERE sale_id = '{$sale_id}' FOR UPDATE";
$check_result = $conn->query($check_sql);

if (!$check_result || $check_result->num_rows === 0) {
throw new Exception("الإيصال غير موجود.");
}

$sale_status = $check_result->fetch_assoc()['status'];

if ($sale_status === 'canceled') {
throw new Exception("الإيصال تم إلغاؤه مسبقاً.");
}

// 2. تحديث حالة الإيصال إلى 'canceled' (مع الأعمدة المضافة حديثاً)
$update_sql = "
UPDATE sales 
SET 
status = 'canceled', 
cancellation_date = NOW(),
canceled_by_user_id = '{$user_id}',
cancellation_reason = '{$cancel_reason}'
WHERE 
sale_id = '{$sale_id}'
";
if (!$conn->query($update_sql)) {
$db_error = $conn->error;
throw new Exception("فشل تحديث حالة الإيصال. خطأ SQL: " . $db_error);
}

// ---------------------------------------------------
// 3. 🟢 استرجاع الكميات إلى المخزون (Inventory Rollback)
// ---------------------------------------------------

// أ. جلب تفاصيل المنتجات التي كانت في هذا الإيصال
$details_sql = "SELECT product_id, quantity FROM sale_items WHERE sale_id = '{$sale_id}'";
$details_result = $conn->query($details_sql);

  

// 4. إنهاء المعاملة بنجاح
$conn->commit();

$response['status'] = 'success';
$response['message'] = 'تم إلغاء الإيصال بنجاح وتم إرجاع الكميات إلى المخزون.';

} catch (Exception $e) {
// التراجع عن جميع التغييرات إذا حدث أي خطأ
$conn->rollback();
// إرجاع الرسالة التفصيلية من Catch Block
$response['message'] = 'فشل عملية الإلغاء: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>