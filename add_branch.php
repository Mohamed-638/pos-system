<?php
session_start();
require_once 'db_connect.php';
require_once 'auth_check.php';
require_once 'config.php';

check_access('admin');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $delivery_phone = trim($_POST['delivery_phone'] ?? '');
    $working_hours = trim($_POST['working_hours'] ?? '');
    if (empty($name)) {
        $message = 'اسم الفرع مطلوب.';
    } else {
        $stmt = $conn->prepare("INSERT INTO branches (name, address, phone, delivery_phone, working_hours) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $address, $phone, $delivery_phone, $working_hours);
        if ($stmt->execute()) {
            header('Location: view_branches.php?message=' . urlencode('تمت إضافة الفرع.'));
            exit;
        } else {
            $message = 'خطأ في الحفظ: ' . $stmt->error;
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إضافة فرع</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <style> /* small local adjustments */ .form-inline { display:block; }</style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
<h2>➕ إضافة فرع</h2>
<?php if ($message): ?><div class="message error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<form method="post">
    <label>الاسم</label>
    <input name="name" required>
    <label>العنوان</label>
    <input name="address">
    <label>الهاتف</label>
    <input name="phone">
    <label>هاتف التوصيل</label>
    <input name="delivery_phone">
    <label>دوام العمل</label>
    <input name="working_hours" placeholder="مثال: 09:00 - 23:00">
    <button type="submit">حفظ</button>
</form>
<p><a href="view_branches.php">عرض الفروع</a></p>
</div>
</body>
</html>
