<?php
// admin_header.php - shared header for admin pages
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
// Simple header - no DB connection or JS, reverts to original behavior
?>
<div class="header-bar">
    <div class="logo-name">
        <img src="<?php echo RESTAURANT_LOGO_URL; ?>" alt="<?php echo RESTAURANT_NAME; ?>" style="height:30px; margin-left:8px;">
        <?php echo RESTAURANT_NAME; ?>
    </div>
    <div style="display:flex; gap:12px; align-items:center;">
        <a href="dashboard.php">لوحة التحكم</a>
        <a href="sales_log_admin.php">سجل المبيعات</a>
        <a href="view_products.php">المنتجات</a>
        <a href="view_purchases.php">المشتريات</a>
        <a href="view_branches.php">الفروع</a>
        <a href="dashboard_all_branches.php">نظرة عامة على الفروع</a>
        <a href="view_suppliers.php">المورّدين</a>
        <a href="manage_users.php">المستخدمون</a>
    </div>
    <div class="user-info">
        <span>مرحباً، <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></strong></span>
        <a href="logout.php" class="logout-btn">تسجيل الخروج 🚪</a>
    </div>
</div>
