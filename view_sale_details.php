<?php
// view_sale_details.php - عرض تفاصيل إيصال مبيعات محدد (مُحدَّث لعرض حالة الإلغاء وسببه)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// التحقق من صلاحية المدير
check_access('admin'); 

$sale_id = isset($_GET['sale_id']) ? filter_var($_GET['sale_id'], FILTER_SANITIZE_NUMBER_INT) : 0;
$sale_data = null;
$sale_items = [];
$message = '';

if ($sale_id <= 0) {
header("Location: sales_log_admin.php?message=" . urlencode("❌ لم يتم تحديد رقم الإيصال بشكل صحيح."));
exit();
}

try {
// 1. جلب البيانات الرئيسية للإيصال والموظف (تم تنظيف الاستعلام)
$sql_sale = "SELECT s.sale_id, s.sale_date, s.total_amount, s.payment_method, s.status, s.cancellation_reason, s.canceled_by_user_id, u.username AS cashier_name FROM sales s JOIN users u ON s.user_id = u.user_id WHERE s.sale_id = ?";

$stmt_sale = $conn->prepare($sql_sale);
$stmt_sale->bind_param("i", $sale_id);
$stmt_sale->execute();
$result_sale = $stmt_sale->get_result();

if ($result_sale->num_rows > 0) {
$sale_data = $result_sale->fetch_assoc();
        
        // جلب اسم الموظف الذي ألغى الإيصال إذا كان ملغى
        $sale_data['canceled_by_name'] = null;
        if ($sale_data['status'] === 'cancelled' && $sale_data['canceled_by_user_id']) {
            // 🟢 الاستعلام الثاني: التأكد من أنه نظيف أيضاً
            $sql_canceller = "SELECT username FROM users WHERE user_id = ?";
            $stmt_canceller = $conn->prepare($sql_canceller);
            $stmt_canceller->bind_param("i", $sale_data['canceled_by_user_id']);
            $stmt_canceller->execute();
            $result_canceller = $stmt_canceller->get_result();
            if ($result_canceller->num_rows > 0) {
                $canceller_row = $result_canceller->fetch_assoc();
                $sale_data['canceled_by_name'] = $canceller_row['username'];
            }
            $stmt_canceller->close();
        }

} else {
$message = "❌ لم يتم العثور على الإيصال رقم #{$sale_id}.";
}
$stmt_sale->close();


// 2. جلب تفاصيل المنتجات داخل الإيصال (sale_items) - يجب تنظيفه أيضاً
    $sql_items = "SELECT si.quantity, si.price, si.cost_price, p.name AS product_name FROM sale_items si JOIN products p ON si.product_id = p.product_id WHERE si.sale_id = ?";

$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $sale_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

while($row = $result_items->fetch_assoc()) {
// ... (بقية حسابات المنتجات)
$row['price_at_sale'] = $row['price'];
$row['product_cost'] = $row['cost_price'];

$row['profit_per_item'] = $row['price_at_sale'] - $row['product_cost'];
$row['total_profit'] = $row['profit_per_item'] * $row['quantity'];
$sale_items[] = $row;
}
$stmt_items->close();

} catch (Exception $e) {
$message = "❌ حدث خطأ غير متوقع: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تفاصيل الإيصال #<?php echo htmlspecialchars($sale_id); ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<style>
body { font-family: Tahoma, sans-serif; padding: 20px; background-color: #f4f4f4; }
.container { max-width: 900px; margin: 0 auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; color: #007bff; margin-top: 0; }
.sale-info { display: flex; justify-content: space-between; margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9; }
.sale-info div { flex: 1; min-width: 200px; }
.sale-info p { margin: 5px 0; }
.sale-info strong { color: #333; }

/* تنسيق الجدول */
.items-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.95em; }
.items-table th, .items-table td { border: 1px solid #ddd; padding: 12px; text-align: center; }
.items-table th { background-color: #343a40; color: white; }
.items-table tr:nth-child(even) { background-color: #f9f9f9; }
.back-link { margin-bottom: 20px; display: inline-block; color: #6c757d; text-decoration: none; font-weight: bold; }
.back-link:hover { color: #343a40; }
.profit-col { color: #28a745; font-weight: bold; }
.total-row { font-weight: bold; background-color: #e9ecef; }

.message-box { padding: 15px; border-radius: 4px; text-align: center; margin-bottom: 20px; background-color: #f8d7da; color: #721c24; }
        
        /* 🟢 تنسيق تنبيه الإلغاء */
        .cancellation-alert {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .cancellation-alert p { margin: 5px 0; }
        .cancellation-alert strong { color: #dc3545; }
        .cancelled-amount { text-decoration: line-through; color: #dc3545; font-size: 1.2em; }
</style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">

<a href="sales_log_admin.php" class="back-link">🔙 العودة لسجل المبيعات</a>

<?php if ($message): ?>
<div class="message-box"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($sale_data): ?>

<h2>📄 تفاصيل الإيصال #<?php echo htmlspecialchars($sale_data['sale_id']); ?></h2>

            <?php if ($sale_data['status'] === 'cancelled'): ?>
            <div class="cancellation-alert">
                <p>❌ **تم إلغاء هذا الإيصال**</p>
                <p><strong>سبب الإلغاء:</strong> <?php echo htmlspecialchars($sale_data['cancellation_reason'] ?? 'غير محدد'); ?></p>
                <?php if ($sale_data['canceled_by_name']): ?>
                    <p><strong>تم الإلغاء بواسطة:</strong> <?php echo htmlspecialchars($sale_data['canceled_by_name']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

<div class="sale-info">
<div>
<p><strong>التاريخ والوقت:</strong> <?php echo htmlspecialchars($sale_data['sale_date']); ?></p>
<p><strong>الموظف (الكاشير):</strong> <?php echo htmlspecialchars($sale_data['cashier_name']); ?></p>
</div>
<div>
<p><strong>طريقة الدفع:</strong> <?php echo htmlspecialchars($sale_data['payment_method']); ?></p>
<p><strong>الحالة:</strong> 
                        <?php if ($sale_data['status'] === 'cancelled'): ?>
                            <span style="color: #dc3545; font-weight: bold;">ملغى 🚫</span>
                        <?php else: ?>
                            <span style="color: #28a745; font-weight: bold;">مكتمل ✅</span>
                        <?php endif; ?>
                    </p>
<p><strong>المبلغ الإجمالي:</strong> 
                        <?php if ($sale_data['status'] === 'cancelled'): ?>
                            <span class="cancelled-amount"><?php echo number_format($sale_data['total_amount'], 2); ?> ج.س</span>
                        <?php else: ?>
                            <span style="color: #28a745; font-size: 1.2em; font-weight: bold;"><?php echo number_format($sale_data['total_amount'], 2); ?> ج.س</span>
                        <?php endif; ?>
                    </p>
</div>
</div>

<h3>المنتجات المباعة:</h3>
<?php 
$total_profit_receipt = 0;
if (!empty($sale_items)): ?>
<table class="items-table">
<thead>
<tr>
<th>المنتج</th>
<th>الكمية</th>
<th>سعر البيع للوحدة</th>
<th>سعر التكلفة للوحدة</th>
<th>إجمالي البيع</th>
<th>الربح الإجمالي 💹</th>
</tr>
</thead>
<tbody>
<?php foreach ($sale_items as $item): 
$item_total = $item['quantity'] * $item['price_at_sale'];
$total_profit_receipt += $item['total_profit'];
?>
<tr>
<td style="text-align: right;"><?php echo htmlspecialchars($item['product_name']); ?></td>
<td><?php echo (int)$item['quantity']; ?></td>
<td><?php echo number_format($item['price_at_sale'], 2); ?> ج.س</td>
<td><?php echo number_format($item['product_cost'], 2); ?> ج.س</td>
<td><?php echo number_format($item_total, 2); ?> ج.س</td>
<td class="profit-col"><?php echo number_format($item['total_profit'], 2); ?> ج.س</td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr class="total-row">
<td colspan="4" style="text-align: left;">الإجمالي المدفوع:</td>
<td><?php echo number_format($sale_data['total_amount'], 2); ?> ج.س</td>
<td></td>
</tr>
<tr class="total-row">
<td colspan="5" style="text-align: left;">صافي الربح من هذا الإيصال:</td>
<td class="profit-col"><?php echo number_format($total_profit_receipt, 2); ?> ج.س</td>
</tr>
</tfoot>
</table>
<?php else: ?>
<p>لا توجد منتجات مسجلة لهذا الإيصال.</p>
<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>