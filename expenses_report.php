<?php
// expenses_report.php - تقرير الربح الصافي وحركة المصروفات (النسخة المصححة)

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
require_once 'config.php'; 

// التحقق من صلاحية المدير فقط
check_access('admin');

$net_profit_data = [];
$error_message = null;

// تحديد الفترة الافتراضية للتقرير (مثلاً: آخر 30 يوماً)
$end_date = date('Y-m-d 23:59:59');
$start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = date('Y-m-d 00:00:00', strtotime($_GET['start_date']));
    $end_date = date('Y-m-d 23:59:59', strtotime($_GET['end_date']));
}

try {
    // 1. حساب إجمالي المبيعات، إجمالي التكلفة، وإجمالي الأرباح الإجمالية (Gross Profit)
    // نستخدم جدول 'sales' الفعلي الذي يحتوي على total_amount و total_cost
    $sql_sales = "SELECT 
                    SUM(total_amount) AS total_revenue,
                    SUM(total_cost) AS total_cogs,
                    SUM(total_amount - total_cost) AS gross_profit
                  FROM sales 
                  WHERE sale_date BETWEEN ? AND ? AND status = 'completed'"; // 💡 نضمن أن تكون المبيعات مكتملة
    
    $stmt_sales = $conn->prepare($sql_sales);
    if (!$stmt_sales) {
         throw new Exception("فشل في تحضير استعلام المبيعات: " . $conn->error);
    }
    $stmt_sales->bind_param("ss", $start_date, $end_date);
    $stmt_sales->execute();
    $result_sales = $stmt_sales->get_result()->fetch_assoc();
    $stmt_sales->close();

    $total_revenue = (float)($result_sales['total_revenue'] ?? 0);
    $total_cogs = (float)($result_sales['total_cogs'] ?? 0);
    $gross_profit = (float)($result_sales['gross_profit'] ?? 0);

    // 2. حساب إجمالي المصروفات التشغيلية (Operating Expenses)
    $sql_expenses = "SELECT 
                       SUM(amount) AS total_expenses
                     FROM expenditures 
                     WHERE expense_date BETWEEN ? AND ?";
                     
    $stmt_expenses = $conn->prepare($sql_expenses);
    if (!$stmt_expenses) {
         throw new Exception("فشل في تحضير استعلام المصروفات: " . $conn->error);
    }
    $stmt_expenses->bind_param("ss", $start_date, $end_date);
    $stmt_expenses->execute();
    $result_expenses = $stmt_expenses->get_result()->fetch_assoc();
    $stmt_expenses->close();
    
    $total_expenses = (float)($result_expenses['total_expenses'] ?? 0);
    
    // 3. حساب الربح الصافي (Net Profit = Gross Profit - Total Expenses)
    $net_profit = $gross_profit - $total_expenses;

    $net_profit_data = [
        'total_revenue' => $total_revenue,
        'total_cogs' => $total_cogs,
        'gross_profit' => $gross_profit,
        'total_expenses' => $total_expenses,
        'net_profit' => $net_profit,
        'start_date' => date('Y-m-d', strtotime($start_date)),
        'end_date' => date('Y-m-d', strtotime($end_date)),
    ];

} catch (Exception $e) {
    $error_message = "فشل في جلب البيانات المالية: " . $e->getMessage();
}

// 🛑 تم حذف $conn->close() من هنا.
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تقرير الربح الصافي - <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'النظام'; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* CSS المنسوخ من تنسيق لوحة التحكم */
body { font-family: Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
.header-bar { background-color: #343a40; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
.header-bar a { color: #ffc107; text-decoration: none; font-weight: bold; margin-left: 20px; }
.header-bar a:hover { color: white; }
.container { padding: 20px; }
h1 { color: #6f42c1; text-align: center; margin-bottom: 30px; }

.card { background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
.card-header { font-size: 1.2em; font-weight: bold; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid; }

/* ألوان البطاقات */
.net-profit-card { border-top: 5px solid #28a745; }
.net-profit-card .card-header { border-bottom-color: #28a745; color: #28a745; }

.expenses-detail-card { border-top: 5px solid #dc3545; }
.expenses-detail-card .card-header { border-bottom-color: #dc3545; color: #dc3545; }

/* تنسيق جدول البيان المالي */
.financial-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 1.1em; }
.financial-table th, .financial-table td { padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right; }
.financial-table th { background-color: #f8f9fa; color: #495057; font-weight: bold; }

.total-row td { background-color: #e2f0fd; font-weight: bold; border-top: 2px solid #007bff; }
.net-profit-row td { background-color: #d4edda; color: #155724; font-size: 1.4em; font-weight: bold; border-top: 3px solid #28a745; }
.net-profit-negative td { background-color: #f8d7da; color: #721c24; }

.value-col { width: 30%; text-align: left !important; font-weight: bold; }

/* تنسيق فلترة التاريخ */
.filter-form { display: flex; align-items: flex-end; gap: 15px; background-color: #ffffff; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.filter-form label { font-weight: bold; color: #495057; }
.filter-form input[type="date"], .filter-form button { padding: 10px; border-radius: 5px; border: 1px solid #ced4da; }
.filter-form button { background-color: #007bff; color: white; cursor: pointer; border: none; transition: background-color 0.3s; }
.filter-form button:hover { background-color: #0056b3; }

/* تفاصيل المصروفات (في البطاقة الجانبية) */
.expense-category-list { list-style: none; padding: 0; }
.expense-category-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e9ecef; }
.expense-category-list .amount { font-weight: bold; color: #dc3545; }
.expense-category-list .category { color: #343a40; }

@media (max-width: 768px) {
.row { flex-direction: column; }
.col-md-8, .col-md-4 { max-width: 100%; flex: 0 0 100%; }
}
</style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>

<div class="container">
<h1><span style="color: #6f42c1;">📈</span> تقرير الربح الصافي والمصروفات <span style="color: #6f42c1;">💰</span></h1>

<?php if ($error_message): ?>
<div class="card" style="color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; text-align: center;">
<p><?php echo $error_message; ?></p>
</div>
<?php endif; ?>

<form class="filter-form" method="GET">
<div style="flex-grow: 1;">
<label for="start_date">من تاريخ:</label>
<input type="date" id="start_date" name="start_date" 
 value="<?php echo date('Y-m-d', strtotime($start_date)); ?>" required>
</div>
<div style="flex-grow: 1;">
<label for="end_date">إلى تاريخ:</label>
<input type="date" id="end_date" name="end_date" 
 value="<?php echo date('Y-m-d', strtotime($end_date)); ?>" required>
</div>
<button type="submit"><i class="fas fa-filter"></i> تصفية التقرير</button>
</form>

<div class="card" style="text-align: center; background-color: #e9ecef;">
<p style="font-size: 1.1em; font-weight: bold; color: #343a40;">
التقرير المعروض للفترة: 
<span style="color: #007bff;"><?php echo date('Y/m/d', strtotime($start_date)); ?></span>
إلى 
<span style="color: #007bff;"><?php echo date('Y/m/d', strtotime($end_date)); ?></span>
</p>
</div>

<div class="row">

<div class="col-md-8" style="padding-left: 0; padding-right: 10px;">
<div class="card net-profit-card">
<div class="card-header">
<i class="fas fa-file-invoice-dollar"></i> بيان الدخل (Income Statement)
</div>

<table class="financial-table">
<thead>
<tr>
<th>البند</th>
<th class="value-col">المبلغ (ج.س)</th>
</tr>
</thead>
<tbody>
<tr>
<td>إجمالي الإيرادات (المبيعات)</td>
<td class="value-col"><?php echo number_format($net_profit_data['total_revenue'], 2); ?></td>
</tr>
<tr>
<td>تكلفة البضاعة المباعة (COGS)</td>
<td class="value-col" style="color: #dc3545;">(<?php echo number_format($net_profit_data['total_cogs'], 2); ?>)</td>
</tr>
<tr class="total-row">
<td>**الربح الإجمالي (Gross Profit)**</td>
<td class="value-col"><?php echo number_format($net_profit_data['gross_profit'], 2); ?></td>
</tr>
<tr>
<td>المصروفات التشغيلية الكلية</td>
<td class="value-col" style="color: #dc3545;">(<?php echo number_format($net_profit_data['total_expenses'], 2); ?>)</td>
</tr>
<?php 
$net_profit_class = ($net_profit_data['net_profit'] >= 0) ? 'net-profit-row' : 'net-profit-row net-profit-negative';
$net_profit_display = ($net_profit_data['net_profit'] >= 0) ? number_format($net_profit_data['net_profit'], 2) : '(' . number_format(abs($net_profit_data['net_profit']), 2) . ')';
?>
<tr class="<?php echo $net_profit_class; ?>">
<td>**الربح الصافي (Net Profit)**</td>
<td class="value-col"><?php echo $net_profit_display; ?></td>
</tr>
</tbody>
</table>
</div>
</div>

<div class="col-md-4" style="padding-right: 0; padding-left: 10px;">
 <div class="card expenses-detail-card">
<div class="card-header">
<i class="fas fa-clipboard-list"></i> تفاصيل المصروفات بالفئة
</div>
<div class="card-body">
<?php 
// جلب المصروفات المصنفة لهذه الفترة
$sql_category_expenses = "SELECT category, SUM(amount) AS category_total
FROM expenditures 
WHERE expense_date BETWEEN ? AND ?
GROUP BY category ORDER BY category_total DESC";

$stmt_cat = $conn->prepare($sql_category_expenses);
// 🛑 هنا لم يعد $conn مغلقاً
if ($stmt_cat) {
    $stmt_cat->bind_param("ss", $start_date, $end_date);
    $stmt_cat->execute();
    $result_cat = $stmt_cat->get_result();

    if ($result_cat->num_rows > 0):
    ?>
    <ul class="expense-category-list">
        <?php while ($row = $result_cat->fetch_assoc()): ?>
            <li>
                <span class="category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($row['category']); ?></span>
                <span class="amount"><?php echo number_format($row['category_total'], 2); ?> ج.س</span>
            </li>
        <?php endwhile; $stmt_cat->close(); ?>
    </ul>
    <?php else: ?>
        <p style="text-align: center; color: #6c757d;">لا توجد مصروفات مسجلة في هذه الفترة.</p>
    <?php endif; 
} else {
    echo '<p class="text-danger" style="text-align: center;">خطأ في تحضير استعلام المصروفات المصنفة.</p>';
}
?>
</div>
</div>
</div>
</div>
<a href="logout.php" class="logout-link">🚪 تسجيل الخروج</a>
</div>

</body>
</html>
<?php 
// 🛑 تم نقل إغلاق الاتصال إلى نهاية الملف بالكامل
if (isset($conn)) {
    $conn->close();
}
?>