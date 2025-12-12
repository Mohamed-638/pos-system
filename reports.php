<?php
// reports.php (النسخة الاحترافية)
require_once 'db_connect.php'; 

// =========================================================
// 1. الاستعلامات المالية الأساسية
// =========================================================
$sql_daily = "SELECT SUM(total_amount) AS daily_total FROM sales WHERE DATE(sale_date) = CURDATE()";
$result_daily = $conn->query($sql_daily);
$daily_total = ($result_daily && $row = $result_daily->fetch_assoc()) ? $row['daily_total'] : 0;

$sql_grand = "SELECT SUM(total_amount) AS grand_total FROM sales";
$result_grand = $conn->query($sql_grand);
$grand_total = ($result_grand && $row = $result_grand->fetch_assoc()) ? $row['grand_total'] : 0;

$sql_distribution = "SELECT payment_method, SUM(total_amount) AS method_total FROM sales GROUP BY payment_method";
$result_distribution = $conn->query($sql_distribution);
$distribution = [];
if ($result_distribution) {
    while($row = $result_distribution->fetch_assoc()) {
        $distribution[$row['payment_method']] = $row['method_total'];
    }
}

// =========================================================
// 2. تقرير أوقات الذروة اليومية (Hourly Peak Times)
// =========================================================
$sql_peak_time = "SELECT HOUR(sale_date) AS sale_hour, COUNT(sale_id) AS orders_count
                  FROM sales
                  WHERE DATE(sale_date) = CURDATE()
                  GROUP BY sale_hour
                  ORDER BY orders_count DESC
                  LIMIT 5";

$result_peak_time = $conn->query($sql_peak_time);
$peak_times = [];
if ($result_peak_time) {
    while($row = $result_peak_time->fetch_assoc()) {
        $formatted_hour = (new DateTime())->setTime($row['sale_hour'], 0)->format('h A');
        $peak_times[] = ['hour' => $formatted_hour, 'count' => $row['orders_count']];
    }
}

// =========================================================
// 3. تقرير المنتجات الأكثر مبيعاً (Top Selling Products)
// =========================================================
$sql_top_products = "SELECT p.name, SUM(si.quantity) AS total_quantity
                     FROM sale_items si
                     JOIN products p ON si.product_id = p.product_id
                     GROUP BY p.name
                     ORDER BY total_quantity DESC
                     LIMIT 5";

$result_top_products = $conn->query($sql_top_products);
$top_products = [];
if ($result_top_products) {
    while($row = $result_top_products->fetch_assoc()) {
        $top_products[] = ['name' => $row['name'], 'quantity' => $row['total_quantity']];
    }
}

// =========================================================
// 4. سجل جميع سجلات المبيعات (All Sales Records)
// =========================================================
$sql_all_sales = "SELECT sale_id, total_amount, payment_method, sale_date 
                  FROM sales 
                  ORDER BY sale_id DESC 
                  LIMIT 50";

$result_all_sales = $conn->query($sql_all_sales);
$all_sales_records = [];
if ($result_all_sales) {
    while($row = $result_all_sales->fetch_assoc()) {
        $all_sales_records[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تقارير نقاط البيع الاحترافية</title>
    <style>
        body { font-family: Tahoma, sans-serif; padding: 20px; background-color: #e9ecef; }
        .container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2, h3 { color: #343a40; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-top: 30px; }
        .nav-links { text-align: center; margin-bottom: 25px; }
        .nav-links a { text-decoration: none; color: #007bff; margin: 0 15px; font-weight: bold; padding: 5px 10px; border-radius: 5px; transition: background-color 0.3s; }
        .nav-links a:hover { background-color: #e9ecef; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .card h4 { margin-top: 0; color: #6c757d; font-size: 1em; }
        .amount { font-size: 2.2em; font-weight: 700; margin: 5px 0; }
        .currency { font-size: 0.7em; opacity: 0.7; }
        .daily { background-color: #e6f5ea; border: 1px solid #c3e6cb; color: #1e7e34; }
        .grand { background-color: #e0f2ff; border: 1px solid #b8daff; color: #0056b3; }
        .cash { background-color: #fff8e1; border: 1px solid #ffeeba; color: #856404; }
        .bank { background-color: #fcebeb; border: 1px solid #f5c6cb; color: #721c24; }
        .analytics-section { display: flex; gap: 20px; flex-wrap: wrap; }
        .analytics-card { flex: 1; min-width: 45%; padding: 20px; border-radius: 10px; background-color: #f8f9fa; border: 1px solid #e9ecef; }
        .analytics-card table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .analytics-card th, .analytics-card td { padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6; }
        .analytics-card th { background-color: #f1f3f5; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📈 لوحة تقارير نقاط البيع الاحترافية</h2>
        
        <div class="nav-links">
            <a href="pos_screen.php">شاشة الكاشير</a>
            <a href="view_products.php">إدارة المنتجات</a>
        </div>
        
        <h3>ملخص المبيعات</h3>
        <div class="dashboard-grid">
            
            <div class="card daily"><h4>💰 إجمالي مبيعات اليوم</h4><div class="amount"><?php echo number_format($daily_total, 2); ?><span class="currency"> ج.س</span></div></div>
            <div class="card grand"><h4>🌐 الإجمالي الكلي المسجل</h4><div class="amount"><?php echo number_format($grand_total, 2); ?><span class="currency"> ج.س</span></div></div>
            <div class="card cash"><h4>💵 المبيعات النقدية (كاش)</h4><div class="amount"><?php echo number_format($distribution['cash'] ?? 0, 2); ?><span class="currency"> ج.س</span></div></div>
            <div class="card bank"><h4>💳 المبيعات البنكية/التطبيق</h4><div class="amount"><?php echo number_format($distribution['bank_transfer'] ?? 0, 2); ?><span class="currency"> ج.س</span></div></div>
            
        </div>
        
        <h3>تحليل الأداء</h3>
        <div class="analytics-section">
            
            <div class="analytics-card">
                <h4>⏱️ أوقات الذروة اليومية (عدد الطلبات)</h4>
                <table>
                    <thead><tr><th>الساعة</th><th>عدد الطلبات</th></tr></thead>
                    <tbody>
                        <?php if (!empty($peak_times)): foreach ($peak_times as $peak): ?>
                            <tr><td><?php echo $peak['hour']; ?></td><td><?php echo $peak['count']; ?> طلب</td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" style="text-align: center;">لا توجد مبيعات اليوم.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="analytics-card">
                <h4>🛒 أكثر 5 منتجات رواجاً (بالكمية)</h4>
                <table>
                    <thead><tr><th>المنتج</th><th>الكمية المباعة</th></tr></thead>
                    <tbody>
                        <?php if (!empty($top_products)): foreach ($top_products as $product): ?>
                            <tr><td><?php echo htmlspecialchars($product['name']); ?></td><td><?php echo $product['quantity']; ?></td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" style="text-align: center;">لا توجد بيانات مبيعات بعد.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
        
        <h3 style="margin-top: 40px;">سجل جميع الإيصالات (آخر 50 فاتورة)</h3>
        
        <div class="analytics-card" style="padding: 15px; min-width: 100%;">
            <table style="width: 100%; font-size: 0.9em; text-align: right;">
                <thead>
                    <tr style="background-color: #f1f3f5;">
                        <th style="padding: 10px;">رقم الفاتورة</th>
                        <th style="padding: 10px;">التاريخ والوقت</th>
                        <th style="padding: 10px;">طريقة الدفع</th>
                        <th style="padding: 10px;">الإجمالي</th>
                        <th style="padding: 10px;">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_sales_records)): foreach ($all_sales_records as $sale):
                            $is_today = (date('Y-m-d', strtotime($sale['sale_date'])) == date('Y-m-d'));
                            $row_style = $is_today ? 'background-color: #e6f5ea; font-weight: bold;' : '';
                            ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td style="padding: 10px;">#<?php echo $sale['sale_id']; ?></td>
                                <td style="padding: 10px;"><?php echo date('Y-m-d H:i', strtotime($sale['sale_date'])); ?></td>
                                <td style="padding: 10px;"><?php echo ($sale['payment_method'] === 'cash') ? 'كاش 💵' : 'بنكي 💳'; ?></td>
                                <td style="padding: 10px;"><?php echo number_format($sale['total_amount'], 2); ?> ج.س</td>
                                <td style="padding: 10px;">
                                    <button onclick="reprintReceipt(<?php echo $sale['sale_id']; ?>)" style="background-color: #007bff; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px;">إعادة طباعة</button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 15px;">لا توجد أي إيصالات مبيعات مسجلة حتى الآن.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>
    
    <script>
        function reprintReceipt(saleId) {
            window.open('generate_receipt.php?sale_id=' + saleId, '_blank');
        }
    </script>
</body>
</html>