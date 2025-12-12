<?php
// sales_log_admin.php - سجل المبيعات الشامل (للمدير فقط) - مُحدَّث لدعم الإلغاء

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
require_once 'config.php'; 

// التحقق من صلاحية المدير
check_access('admin'); 

// 🟢 1. جلب التاريخ المُحدد من النموذج (Query Parameter)
// استخدام تاريخ اليوم كقيمة افتراضية إذا لم يتم تحديد تاريخ
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');

// تحديد الشروط بناءً على ما إذا كان هناك تاريخ محدد
$where_clause = "";
if (!empty($filter_date)) {
    // التأكد من أن التاريخ المدخل صحيح قبل استخدامه في الاستعلام
    $where_clause = "WHERE DATE(s.sale_date) = ?";
}

// بناء الاستعلام الرئيسي (لجلب سجل المبيعات)
$sql = "SELECT 
    s.sale_id, 
    s.sale_date, 
    s.total_amount, 
    s.payment_method,
    s.status,
    u.username AS cashier_name
FROM 
    sales s
JOIN 
    users u ON s.user_id = u.user_id
{$where_clause}
ORDER BY 
    s.sale_date DESC";

$sales_log = [];

try {
    $stmt = $conn->prepare($sql);
    
    // ربط القيمة إذا كان هناك شرط WHERE
    if (!empty($filter_date)) {
        $stmt->bind_param("s", $filter_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $sales_log[] = $row;
        }
    }
    $stmt->close();

    // 🟢 2. حساب الإجمالي الكلي الصافي (مع تطبيق الفلتر)
    // حساب المبيعات المكتملة فقط
    $sql_grand_total = "SELECT SUM(total_amount) AS net_total FROM sales s {$where_clause} AND s.status = 'completed'";
    
    $stmt_grand_total = $conn->prepare($sql_grand_total);
    $net_grand_total = 0;

    // ربط القيمة للإجمالي الكلي
    if (!empty($filter_date)) {
        $stmt_grand_total->bind_param("s", $filter_date);
    }

    $stmt_grand_total->execute();
    $result_grand_total = $stmt_grand_total->get_result();
    
    if ($result_grand_total && $row = $result_grand_total->fetch_assoc()) {
        $net_grand_total = $row['net_total'] ?? 0;
    }
    $stmt_grand_total->close();


} catch (Exception $e) {
    // التعامل مع الأخطاء (مثل أخطاء قاعدة البيانات)
    error_log("SQL Error: " . $e->getMessage());
    // يمكنك إضافة رسالة تنبيه للمستخدم هنا
    $sales_log = [];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>سجل المبيعات الشامل 📈</title>
<link rel="stylesheet" href="assets/css/app.css">
<style>
body { font-family: Tahoma, sans-serif; padding: 20px; background-color: #f4f4f4; }
.container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
h2 { border-bottom: 2px solid #343a40; padding-bottom: 10px; color: #333; margin-top: 0; }

/* تنسيق الجدول */
.sales-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
.sales-table th, .sales-table td { border: 1px solid #ddd; padding: 12px; text-align: center; }
.sales-table th { background-color: #343a40; color: white; }
.sales-table tr:nth-child(even) { background-color: #f9f9f9; }
.cancelled-row { 
    background-color: #fceceb !important; 
    color: #dc3545; 
    text-decoration: line-through; 
    opacity: 0.7;
}
.total-col { font-weight: bold; color: #28a745; font-size: 1.1em; }
.cancelled-status { color: #dc3545; font-weight: bold; }
.completed-status { color: #28a745; font-weight: bold; }

.detail-btn { 
    background-color: #007bff; 
    color: white; 
    padding: 5px 10px; 
    border: none; 
    border-radius: 4px; 
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.detail-btn:hover { background-color: #0056b3; }

/* 🟢 تنسيق زر الإلغاء */
.cancel-btn { 
    background-color: #dc3545; 
    color: white; 
    padding: 5px 10px; 
    border: none; 
    border-radius: 4px; 
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9em;
    font-weight: bold;
}
.cancel-btn:hover { background-color: #c82333; }
.disabled-btn { 
    background-color: #6c757d; 
    cursor: not-allowed; 
    opacity: 0.6;
}
/* نهاية تنسيق زر الإلغاء */

.back-link { margin-bottom: 20px; display: inline-block; color: #6c757d; text-decoration: none; font-weight: bold; }
.back-link:hover { color: #343a40; }

/* تنسيق الفلتر */
.filter-form { 
    display: flex; 
    align-items: center; 
    gap: 15px; 
    margin-bottom: 20px;
    padding: 10px;
    background-color: #f9f9f9;
    border-radius: 6px;
    border: 1px solid #eee;
}
.filter-form label { font-weight: bold; color: #333; }
.filter-form input[type="date"] { 
    padding: 8px; 
    border: 1px solid #ccc; 
    border-radius: 4px;
    font-size: 1em;
}
.filter-form button {
    background-color: #28a745;
    color: white;
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.2s;
}
.filter-form button:hover {
    background-color: #1e7e34;
}
</style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
<h2>📈 سجل المبيعات الشامل (المدير) - الصافي</h2>

<a href="dashboard.php" class="back-link">🔙 العودة للوحة التحكم</a>

<form method="GET" class="filter-form">
    <label for="filter_date">تصفية حسب اليوم:</label>
    <input type="date" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
    <button type="submit">تطبيق الفلتر</button>
</form>

<?php if (!empty($sales_log)): ?>
<table class="sales-table">
<thead>
<tr>
<th>رقم الإيصال</th>
<th>التاريخ والوقت</th>
<th>الموظف (الكاشير)</th>
<th>طريقة الدفع</th>
<th>الحالة</th>
<th>المبلغ الإجمالي (ج.س)</th>
<th>التفاصيل</th>
<th>الإجراء</th> </tr>
</thead>
<tbody>
<?php 
foreach ($sales_log as $sale): 
    $row_class = ($sale['status'] === 'cancelled') ? 'cancelled-row' : '';
    $status_text = ($sale['status'] === 'cancelled') ? '<span class="cancelled-status">🚫 ملغى</span>' : '<span class="completed-status">✅ مكتمل</span>';
?>
<tr id="sale-row-<?php echo $sale['sale_id']; ?>" class="<?php echo $row_class; ?>">
<td><?php echo $sale['sale_id']; ?></td>
<td><?php echo $sale['sale_date']; ?></td>
<td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
<td><?php echo htmlspecialchars($sale['payment_method']); ?></td>
<td><?php echo $status_text; ?></td>
<td class="total-col"><?php echo number_format($sale['total_amount'], 2); ?></td>
<td>
    <a href="view_sale_details.php?sale_id=<?php echo $sale['sale_id']; ?>" class="detail-btn">عرض التفاصيل</a>
</td>
<td>
    <?php if ($sale['status'] === 'completed'): ?>
        <button class="cancel-btn" onclick="cancelSale('<?php echo $sale['sale_id']; ?>')">إلغاء 🚫</button>
    <?php else: ?>
        <button class="cancel-btn disabled-btn" disabled>ملغى</button>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
<td colspan="5" style="text-align: left; font-weight: bold; font-size: 1.1em;">
    الإجمالي الصافي للمبيعات المكتملة 
    <?php echo !empty($filter_date) ? "بتاريخ: " . htmlspecialchars($filter_date) : ""; ?>:
</td>
<td class="total-col" style="font-size: 1.2em;"><?php echo number_format($net_grand_total, 2); ?> ج.س</td>
<td colspan="2"></td> </tr>
</tfoot>
</table>
<?php else: ?>
<p style="text-align: center; color: #dc3545; padding: 30px; border: 1px dashed #dc3545; border-radius: 8px;">
    ❌ لا توجد سجلات مبيعات مكتملة 
    <?php echo !empty($filter_date) ? "في تاريخ: " . htmlspecialchars($filter_date) : ""; ?>.
</p>
<?php endif; ?>
</div>

<script>
function cancelSale(saleId) {
    // 1. تأكيد الإلغاء
    if (!confirm('هل أنت متأكد من رغبتك في إلغاء الإيصال رقم #' + saleId + '؟ سيتم إرجاع الكميات للمخزون.')) {
        return;
    }

    // 2. طلب سبب الإلغاء
    const reason = prompt('يرجى إدخال سبب إلغاء الإيصال (مطلوب):');
    if (!reason || reason.trim() === '') {
        alert('يجب إدخال سبب للإلغاء.');
        return;
    }

    const formData = new FormData();
    formData.append('sale_id', saleId);
    formData.append('reason', reason);

    // 3. إرسال طلب AJAX
    fetch('cancel_sale.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            
            // 4. تحديث الصفحة لتحديث حالة الإيصال والإجمالي الصافي (مع الاحتفاظ بالفلتر)
            window.location.href = window.location.href; 
            
        } else {
            alert('فشل الإلغاء: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال بالخادم.');
    });
}
</script>
</body>
</html>