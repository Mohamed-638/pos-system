<?php
session_start();
require_once 'db_connect.php';
require_once 'auth_check.php';
require_once 'config.php';

check_access('admin');

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$suppliers = [];
$res = $conn->query("SELECT supplier_id, name, phone, email, address FROM suppliers ORDER BY supplier_id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $suppliers[] = $row;
    }
    $res->free();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>قائمة المورّدين</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
<h2>📦 قائمة المورّدين</h2>
<?php if ($message): ?><div><?php echo $message; ?></div><?php endif; ?>
<p><a class="btn" href="add_supplier.php">➕ إضافة مورّد</a></p>
<?php if (!empty($suppliers)): ?>
<table border="1" cellpadding="8" style="border-collapse:collapse">
<thead><tr><th>ID</th><th>الاسم</th><th>هاتف</th><th>بريد</th><th>العنوان</th></tr></thead>
<tbody>
<?php foreach ($suppliers as $s): ?>
<tr><td><?php echo $s['supplier_id']; ?></td><td><?php echo htmlspecialchars($s['name']); ?></td><td><?php echo htmlspecialchars($s['phone']); ?></td><td><?php echo htmlspecialchars($s['email']); ?></td><td><?php echo htmlspecialchars($s['address']); ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><p>لا يوجد مورّدين حتى الآن.</p><?php endif; ?>
</div>
</body></html>
