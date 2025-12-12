<?php
session_start();
require_once 'db_connect.php';
require_once 'auth_check.php';
require_once 'config.php';
check_access('admin');
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$branches = [];
$res = $conn->query("SELECT branch_id, name, address, phone FROM branches ORDER BY branch_id DESC");
if ($res) { while ($r = $res->fetch_assoc()) $branches[] = $r; $res->free(); }
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="utf-8">
	<title>الفروع</title>
	<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
<h2>🏭 إدارة الفروع</h2>
<?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
<p><a class="btn" href="add_branch.php">➕ إضافة فرع</a></p>
<?php if (!empty($branches)): ?>
<table class="table" border='1' cellpadding='6' style='border-collapse:collapse'>
<thead><tr><th>ID</th><th>الاسم</th><th>العنوان</th><th>هاتف</th></tr></thead>
<tbody><?php foreach($branches as $b): ?><tr><td><?php echo $b['branch_id']; ?></td><td><?php echo htmlspecialchars($b['name']); ?></td><td><?php echo htmlspecialchars($b['address']); ?></td><td><?php echo htmlspecialchars($b['phone']); ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?><p>لا توجد فروع مسجلة.</p><?php endif; ?>
</div>
</body>
</html>
