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
    if (empty($name)) {
        $message = 'اسم الفرع مطلوب.';
    } else {
        $stmt = $conn->prepare("INSERT INTO branches (name, address, phone) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $address, $phone);
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
    <button type="submit">حفظ</button>
</form>
<p><a href="view_branches.php">عرض الفروع</a></p>
</div>
</body>
</html>
