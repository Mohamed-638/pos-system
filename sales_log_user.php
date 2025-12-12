<?php
// sales_log_user.php - سجل المبيعات لليوزر الحالي فقط (مُحدَّث لاستثناء المُلغاة)

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
require_once 'config.php';

// يجب أن يكون الكاشير مسجلاً للدخول
check_access('cashier'); 

$current_user_id = $_SESSION['user_id'];
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 🟢 التعديل: إضافة شرط (AND status = 'completed') لضمان عرض واحتساب المبيعات المكتملة فقط.
$where_clause = "DATE(sale_date) = '{$filter_date}' AND user_id = {$current_user_id} AND status = 'completed'";

// =========================================================
// جلب سجلات المبيعات المكتملة
// =========================================================
$sql_sales_log = "SELECT sale_id, total_amount, payment_method, sale_date 
FROM sales 
WHERE {$where_clause}
ORDER BY sale_id DESC";

$result_sales_log = $conn->query($sql_sales_log);
$sales_records = [];
if ($result_sales_log) {
while($row = $result_sales_log->fetch_assoc()) {
$sales_records[] = $row;
}
}

// 🟢 التعديل: إحصائيات إجمالي مبيعات اليوزر المكتملة للتاريخ المحدد
$sql_total_for_date = "SELECT SUM(total_amount) AS date_total FROM sales WHERE {$where_clause}";
$result_total_for_date = $conn->query($sql_total_for_date);
$date_total = ($result_total_for_date && $row = $result_total_for_date->fetch_assoc()) ? $row['date_total'] : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مبيعات <?php echo $_SESSION['full_name']; ?> - سجل اليوم</title>
    <style>
        /* التنسيقات العامة */
        body { font-family: Tahoma, sans-serif; padding: 0; background-color: #f4f4f4; margin: 0; }
        .container { max-width: 1000px; margin: 30px auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; color: #333; }

        /* تنسيق شريط العودة */
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            padding: 5px 10px;
            border: 1px solid #007bff;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .back-link:hover {
            background-color: #007bff;
            color: white;
        }

        /* تنسيق جدول المبيعات */
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .sales-table th, .sales-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: right;
        }
        .sales-table th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }
        .sales-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .sales-table tr:hover {
            background-color: #f1f1f1;
        }
        .sales-table button {
            background-color: #ffc107;
            color: #333;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .sales-table button:hover {
            background-color: #e0a800;
        }
        
        /* تنسيق الإجماليات */
        .total-summary {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 1.1em;
            text-align: center;
            border: 1px solid #c3e6cb;
        }

        /* تنسيق لوحة التصفية (Filter Panel) */
        .filter-panel {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-panel label {
            font-weight: bold;
            color: #495057;
        }
        .filter-panel input[type="date"] {
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .filter-panel button {
            padding: 8px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-panel button:hover {
            background-color: #1e7e34;
        }

    </style>
</head>
<body>
    <div class="container">
        <h2>🧾 سجل مبيعاتي اليومية المكتملة (<?php echo $_SESSION['full_name']; ?>)</h2>
        
        <a href="pos_screen.php" class="back-link">🔙 العودة لشاشة الكاشير</a>
        
        <form method="GET" action="sales_log_user.php" class="filter-panel">
            <label for="date-filter">عرض المبيعات في تاريخ:</label>
            <input type="date" id="date-filter" name="date" value="<?php echo $filter_date; ?>">
            <button type="submit">عرض</button>
        </form>
        
        <div class="total-summary">
            الإجمالي الصافي للتاريخ المحدد (<span style="font-style: italic;"><?php echo $filter_date; ?></span>): 
            <span style="font-weight: bold; font-size: 1.2em;"><?php echo number_format($date_total, 2); ?> ج.س</span>
        </div>

        <table class="sales-table">
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>التاريخ والوقت</th>
                    <th>طريقة الدفع</th>
                    <th>الإجمالي</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                    <?php if (!empty($sales_records)): foreach ($sales_records as $sale): ?>
                    <tr>
                    <td>#<?php echo $sale['sale_id']; ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($sale['sale_date'])); ?></td>
                    <td>
                       <?php 
                       $method = trim(strtolower($sale['payment_method']));
                       if (strpos($method, 'نقد') !== false || strpos($method, 'كاش') !== false || $method === 'cash'):
                       echo 'كاش (نقدي) 💵';
                       else:
                       echo 'دفع بنكي / تطبيق 💳'; // لأي شيء آخر غير نقدي
                       endif;
                       ?>
                    </td>
                    <td><?php echo number_format($sale['total_amount'], 2); ?> ج.س</td>
                    <td style="text-align: center;">
                    <button onclick="window.open('generate_receipt.php?sale_id=<?php echo $sale['sale_id']; ?>', '_blank')">إعادة طباعة</button>
                    </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc3545; font-weight: bold;">لا توجد إيصالات مكتملة مسجلة لك في هذا التاريخ.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                </div>
</body>
</html>