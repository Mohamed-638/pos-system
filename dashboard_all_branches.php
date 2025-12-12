<?php
session_start();
require_once 'db_connect.php';
require_once 'config.php';
require_once 'auth_check.php';
check_access('admin');

// Fetch basic per-branch stats (sales count and total) for overview
$sql = "SELECT b.branch_id, b.name, b.address, b.phone, COALESCE(SUM(s.total_amount), 0) AS total_sales, COUNT(s.sale_id) AS total_transactions
        FROM branches b
        LEFT JOIN sales s ON s.branch_id = b.branch_id
        GROUP BY b.branch_id
        ORDER BY b.name";
$res = $conn->query($sql);
$branches = [];
if ($res) { while($r = $res->fetch_assoc()) $branches[] = $r; $res->free(); }
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>ملخص الفروع</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
    <h2>🌍 نظرة عامة على الفروع</h2>
    <?php if (empty($branches)): ?>
        <p>لا توجد فروع مسجلة.</p>
    <?php else: ?>
        <table class="table" border="1" cellpadding="6" style="border-collapse:collapse">
            <thead><tr><th>الفرع</th><th>العنوان</th><th>الهاتف</th><th>مجموع المبيعات</th><th>عدد المعاملات</th><th>لوحة الفرع</th></tr></thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['name']); ?></td>
                        <td><?php echo htmlspecialchars($b['address']); ?></td>
                        <td><?php echo htmlspecialchars($b['phone']); ?></td>
                        <td><?php echo number_format($b['total_sales'], 2); ?> ج.س</td>
                        <td><?php echo intval($b['total_transactions']); ?></td>
                        <td><a href="dashboard_branch.php?branch_id=<?php echo intval($b['branch_id']); ?>">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>