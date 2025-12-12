<?php
session_start();
require_once 'db_connect.php';
require_once 'config.php';
require_once 'auth_check.php';
check_access('admin');

$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
if ($branch_id <= 0) {
    header('Location: dashboard.php?message=' . urlencode('يجب اختيار فرع.'));
    exit;
}

// fetch branch info
$stmt = $conn->prepare("SELECT branch_id, name, address, phone FROM branches WHERE branch_id = ?");
$stmt->bind_param('i', $branch_id);
$stmt->execute();
$res = $stmt->get_result();
$branch = $res->fetch_assoc();
$stmt->close();

// Basic stats for this branch
$stmt = $conn->prepare("SELECT COUNT(*) AS tx_count, COALESCE(SUM(total_amount), 0) AS total_sales FROM sales WHERE branch_id = ?");
$stmt->bind_param('i', $branch_id);
$stmt->execute();
$res = $stmt->get_result();
$stats = $res->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>لوحة فرع - <?php echo htmlspecialchars($branch['name'] ?? ''); ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
    <h2>📍 لوحة فرع: <?php echo htmlspecialchars($branch['name'] ?? ''); ?></h2>
    <p>العنوان: <?php echo htmlspecialchars($branch['address'] ?? '-'); ?> | هاتف: <?php echo htmlspecialchars($branch['phone'] ?? '-'); ?></p>
    <div class="stats-grid">
        <div class="stat-card card-green">
            <h3>إجمالي المبيعات</h3>
            <div class="value"><?php echo number_format($stats['total_sales'],2); ?> ج.س</div>
        </div>
        <div class="stat-card card-blue">
            <h3>عدد المعاملات</h3>
            <div class="value"><?php echo intval($stats['tx_count']); ?></div>
        </div>
    </div>
    <p><a href="dashboard_all_branches.php">◀️ العودة إلى نظرة عامة على الفروع</a></p>
</div>
</body>
</html>