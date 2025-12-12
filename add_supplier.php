<?php
session_start();
require_once 'db_connect.php';
require_once 'auth_check.php';
require_once 'config.php';

check_access('admin');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if (empty($name)) {
        $message = '🚫 اسم المورّد مطلوب.';
    } else {
        $stmt = $conn->prepare("INSERT INTO suppliers (name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $name, $phone, $email, $address);
        if ($stmt->execute()) {
            header('Location: view_suppliers.php?message=' . urlencode('✅ تمت إضافة المورّد بنجاح.'));
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
    <title>إضافة مورّد</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
    <h2>➕ إضافة مورّد جديد</h2>
    <?php if ($message): ?><div class="message error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form method="post">
        <label>اسم المورّد</label>
        <input type="text" name="name" required>
        <label>الهاتف</label>
        <input type="text" name="phone">
        <label>البريد الإلكتروني</label>
        <input type="email" name="email">
        <label>العنوان</label>
        <input type="text" name="address">
        <button type="submit">حفظ</button>
    </form>
    <p><a href="view_suppliers.php">عرض المورّدين</a></p>
</div>
</body>
</html>
