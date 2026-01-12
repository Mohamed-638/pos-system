<?php
session_start();
require_once 'db_connect.php';
require_once 'auth_check.php';
require_once 'config.php';

check_access('admin');

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$branch_filter = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;

$branches = [];
$branches_res = $conn->query("SELECT branch_id, name FROM branches ORDER BY name");
if ($branches_res) {
    while ($b = $branches_res->fetch_assoc()) {
        $branches[] = $b;
    }
    $branches_res->free();
}
$purchases = [];
$sql = "SELECT p.purchase_id, p.purchase_date, p.total_amount, p.status, s.name AS supplier_name, u.username AS created_by, b.name as branch_name
        FROM purchases p
        JOIN suppliers s ON p.supplier_id = s.supplier_id
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN branches b ON p.branch_id = b.branch_id
        WHERE (? IS NULL OR p.branch_id = ?)
        ORDER BY p.purchase_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $branch_filter, $branch_filter);
$stmt->execute();
$res = $stmt->get_result();
if ($res) { while ($row = $res->fetch_assoc()) $purchases[] = $row; $res->free(); }
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>قائمة المشتريات</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
<h2>📦 سجل المشتريات</h2>
<?php if ($message): ?><div><?php echo $message; ?></div><?php endif; ?>
<p><a href="add_purchase.php">➕ إضافة مشتريات</a></p>
<form method="GET" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
    <label for="branch_id" style="font-weight: bold;">الفرع:</label>
    <select id="branch_id" name="branch_id">
        <option value="">كل الفروع</option>
        <?php foreach ($branches as $branch): ?>
            <option value="<?php echo $branch['branch_id']; ?>" <?php echo ($branch_filter === (int)$branch['branch_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($branch['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">تصفية</button>
</form>
<?php if (!empty($purchases)): ?>
<table border="1" cellpadding="8" style="border-collapse:collapse"><thead><tr><th>ID</th><th>التاريخ</th><th>المورّد</th><th>الفرع</th><th>المبلغ</th><th>المُدار</th><th>الحالة</th></tr></thead>
<tbody><?php foreach ($purchases as $p): ?><tr><td><?php echo $p['purchase_id']; ?></td><td><?php echo $p['purchase_date']; ?></td><td><?php echo htmlspecialchars($p['supplier_name']); ?></td><td><?php echo htmlspecialchars($p['branch_name']); ?></td><td><?php echo number_format($p['total_amount'], 2); ?></td><td><?php echo htmlspecialchars($p['created_by']); ?></td><td><?php echo htmlspecialchars($p['status']); ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?><p>لا توجد مشتريات مسجلة حتى الآن.</p><?php endif; ?>
</div>
</body></html>
