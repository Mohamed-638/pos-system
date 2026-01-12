<?php
// sales_log.php - سجل الإيصالات المفصل مع خاصية التصفية
require_once 'db_connect.php'; 
require_once 'auth_check.php';


check_access('admin');

$branches = [];
$branches_res = $conn->query("SELECT branch_id, name FROM branches ORDER BY name");
if ($branches_res) {
    while ($b = $branches_res->fetch_assoc()) {
        $branches[] = $b;
    }
    $branches_res->free();
}

// تحديد التاريخ المراد عرضه (افتراضيًا: اليوم الحالي)
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$branch_filter = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;

// إعداد شرط التصفية
$where_clause = "DATE(s.sale_date) = ?";
$where_params = [$filter_date];
$where_types = "s";
if ($branch_filter) {
    $where_clause .= " AND s.branch_id = ?";
    $where_params[] = $branch_filter;
    $where_types .= "i";
}

// =========================================================
// 1. جلب سجلات المبيعات بناءً على التاريخ المحدد
// =========================================================
$sql_sales_log = "SELECT s.sale_id, s.total_amount, s.payment_method, s.sale_date, b.name AS branch_name
                  FROM sales s
                  LEFT JOIN branches b ON s.branch_id = b.branch_id
                  WHERE {$where_clause}
                  ORDER BY s.sale_id DESC";

$stmt_sales_log = $conn->prepare($sql_sales_log);
$stmt_sales_log->bind_param($where_types, ...$where_params);
$stmt_sales_log->execute();
$result_sales_log = $stmt_sales_log->get_result();
$sales_records = [];
if ($result_sales_log) {
    while($row = $result_sales_log->fetch_assoc()) {
        $sales_records[] = $row;
    }
}
$stmt_sales_log->close();

// =========================================================
// 2. إحصائيات إجمالي المبيعات للتاريخ المحدد فقط
// =========================================================
$sql_total_for_date = "SELECT SUM(total_amount) AS date_total FROM sales s WHERE {$where_clause}";
$stmt_total_for_date = $conn->prepare($sql_total_for_date);
$stmt_total_for_date->bind_param($where_types, ...$where_params);
$stmt_total_for_date->execute();
$result_total_for_date = $stmt_total_for_date->get_result();
$date_total = ($result_total_for_date && $row = $result_total_for_date->fetch_assoc()) ? $row['date_total'] : 0;
$stmt_total_for_date->close();


$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سجل الإيصالات المفصل وتصفية التاريخ</title>
    <style>
        body { font-family: Tahoma, sans-serif; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 1000px; margin: 0 auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { color: #343a40; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; }
        .nav-links { margin-bottom: 20px; }
        .nav-links a { text-decoration: none; color: #007bff; margin-left: 15px; font-weight: bold; }
        
        .filter-panel { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; background-color: #f8f8f8; }
        .filter-panel label { font-weight: bold; }
        .filter-panel input[type="date"] { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; }
        .filter-panel button { padding: 8px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }

        .sales-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .sales-table th, .sales-table td { padding: 12px; text-align: right; border-bottom: 1px solid #dee2e6; }
        .sales-table th { background-color: #e9ecef; }
        .total-summary { text-align: center; font-size: 1.5em; margin-top: 20px; padding: 10px; background-color: #fff3cd; border-radius: 5px; }
    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
    <div class="container">
        <h2>🧾 سجل الإيصالات المفصل</h2>
        
        <div class="nav-links">
            <a href="dashboard.php">العودة للوحة التقارير</a>
            <a href="pos_screen.php">شاشة الكاشير</a>
        </div>
        
        <form method="GET" action="sales_log.php" class="filter-panel">
            <label for="date-filter">تصفية حسب التاريخ:</label>
            <input type="date" id="date-filter" name="date" value="<?php echo $filter_date; ?>">
            <label for="branch-filter">الفرع:</label>
            <select id="branch-filter" name="branch_id">
                <option value="">كل الفروع</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['branch_id']; ?>" <?php echo ($branch_filter === (int)$branch['branch_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">عرض الإيصالات</button>
            <?php if ($filter_date != date('Y-m-d')): ?>
                <a href="sales_log.php" style="color: #dc3545; font-weight: bold; text-decoration: none;">عرض اليوم</a>
            <?php endif; ?>
        </form>
        
        <div class="total-summary">
            الإجمالي للتاريخ المحدد (<span style="color: #007bff;"><?php echo $filter_date; ?></span>): 
            <span style="color: #28a745; font-weight: bold;"><?php echo number_format($date_total, 2); ?> ج.س</span>
        </div>

        <table class="sales-table">
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>التاريخ والوقت</th>
                    <th>طريقة الدفع</th>
                    <th>الفرع</th>
                    <th>الإجمالي</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sales_records)): ?>
                    <?php foreach ($sales_records as $sale): ?>
                        <tr>
                            <td>#<?php echo $sale['sale_id']; ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($sale['sale_date'])); ?></td>
                            <td><?php echo ($sale['payment_method'] === 'cash') ? 'كاش 💵' : 'بنكي 💳'; ?></td>
                            <td><?php echo htmlspecialchars($sale['branch_name'] ?? '-'); ?></td>
                            <td><?php echo number_format($sale['total_amount'], 2); ?> ج.س</td>
                            <td>
                                <button onclick="reprintReceipt(<?php echo $sale['sale_id']; ?>)" 
                                        style="background-color: #007bff; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px;">
                                    إعادة طباعة
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 15px; color: #dc3545;">لا توجد إيصالات مسجلة في تاريخ **<?php echo $filter_date; ?>**.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    </div>
    
    <script>
        // نحتاج تعريف هذه الدالة هنا أيضاً
        function reprintReceipt(saleId) {
            // تفتح نافذة جديدة لعرض الإيصال وطباعته
            window.open('generate_receipt.php?sale_id=' + saleId, '_blank');
        }
    </script>
</body>
</html>
