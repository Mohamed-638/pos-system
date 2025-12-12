<?php
// add_expense.php - لمعالجة وتسجيل المصروفات الجديدة (النسخة المصححة)

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// يجب أن يكون المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
http_response_code(401); // Unauthorized
echo json_encode(['status' => 'error', 'message' => 'الرجاء تسجيل الدخول أولاً.']);
exit;
}

// التحقق من الصلاحيات
check_access(['admin', 'cashier']); 

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'حدث خطأ غير متوقع.'];

$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
$response['message'] = 'طريقة الطلب غير مدعومة.';
echo json_encode($response);
exit;
}

// 1. استلام البيانات وتنظيفها باستخدام الطريقة المرنة
// استخدام ?? للتعامل مع غياب المتغير في حالة عدم إرساله
$amount = (float)($_POST['amount'] ?? 0);
$description = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
$category = htmlspecialchars($_POST['category'] ?? '', ENT_QUOTES, 'UTF-8');
$expense_date_str = $_POST['expense_date'] ?? '';

// 2. التحقق من صحة البيانات
if ($amount <= 0) { 
$response['message'] = 'المبلغ غير صالح (يجب أن يكون أكبر من صفر).';
echo json_encode($response);
exit;
}
if (empty($description) || empty($category) || empty($expense_date_str)) {
$response['message'] = 'الرجاء ملء جميع الحقول المطلوبة (الوصف والفئة والتاريخ).';
echo json_encode($response);
exit;
}

// تحويل التاريخ إلى صيغة قاعدة البيانات
$expense_date = date('Y-m-d H:i:s', strtotime($expense_date_str));


// 3. إدراج المصروف في قاعدة البيانات
try {
$sql = "INSERT INTO expenditures (expense_date, description, amount, category, user_id) 
VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

// ربط المعاملات: s=expense_date, s=description, d=amount, s=category, i=user_id
$stmt->bind_param("ssdsi", $expense_date, $description, $amount, $category, $current_user_id);

if ($stmt->execute()) {
$response['status'] = 'success';
$response['message'] = '✅ تم تسجيل المصروف بنجاح. ID: ' . $conn->insert_id;
$response['expense_id'] = $conn->insert_id;
} else {
throw new Exception("خطأ في تنفيذ الاستعلام: " . $stmt->error);
}

$stmt->close();

} catch (Exception $e) {
error_log("Error adding expense: " . $e->getMessage());
$response['message'] = 'فشل تسجيل المصروف. خطأ: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>