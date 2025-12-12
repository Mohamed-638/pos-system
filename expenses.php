<?php
// expenses.php - صفحة تسجيل المصروفات (بتنسيق لوحة التحكم)

session_start();
require_once 'db_connect.php'; 
require_once 'config.php'; 
require_once 'auth_check.php'; 

// يجب أن يكون المدير أو الكاشير قادرًا على تسجيل المصروفات
check_access(['admin', 'cashier']); 

// جلب فئات المصروفات الشائعة
$categories = [
    'إيجار', 'كهرباء وماء', 'رواتب', 'صيانة وتصليحات', 
    'مستلزمات مكتبية', 'مشتريات/خامات إضافية', 'نقل وشحن', 'أخرى'
];

$current_date_time = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل المصروفات - <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'النظام'; ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .header-bar { background-color: #343a40; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header-bar a { color: #ffc107; text-decoration: none; font-weight: bold; margin-left: 20px; }
        .header-bar a:hover { color: white; }
        .container { padding: 20px; }
        h1 { color: #007bff; text-align: center; margin-bottom: 30px; }
        
        /* تنسيق الأزرار والنماذج */
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            font-size: 1.2em;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #6c757d; }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; /* لضمان أن العرض 100% يشمل التبطين */
        }
        .btn-success {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-success:hover { background-color: #218838; }
        .btn-success:disabled { background-color: #9ccc9c; cursor: not-allowed; }
        
        /* تنسيق الشبكة للمحتوى */
        .row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
        .col-md-6 { flex: 0 0 50%; max-width: 50%; padding: 0 10px; }
        
        /* تنسيق عرض المصروفات الحديثة */
        .list-group { list-style: none; padding: 0; }
        .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed #e9ecef;
        }
        .expense-info { font-weight: bold; }
        .expense-amount { color: #dc3545; font-weight: bold; }
        
        /* تنسيق الرسائل */
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-info { color: #17a2b8; }

        /* لضبط عرض الأجهزة الصغيرة */
        @media (max-width: 768px) {
            .col-md-6 { flex: 0 0 100%; max-width: 100%; }
            .row { margin: 0; }
        }
    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>

<div class="container">
    <h1><span style="color: #dc3545;">💸</span> تسجيل وإدارة المصروفات <span style="color: #dc3545;">🧾</span></h1>
    
    <div class="row">
        
        <div class="col-md-6">
            <div class="card" style="border-top: 4px solid #dc3545;">
                <div class="card-header">
                    إدخال مصروف جديد
                </div>
                <form id="add-expense-form">
                    <div class="form-group">
                        <label for="expense_date">تاريخ ووقت المصروف:</label>
                        <input type="datetime-local" class="form-control" 
                               id="expense_date" name="expense_date" required 
                               value="<?php echo $current_date_time; ?>">
                    </div>

                    <div class="form-group">
                        <label for="category">فئة المصروف:</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">-- اختر الفئة --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">المبلغ (ج.س):</label>
                        <input type="number" step="0.01" class="form-control" 
                               id="amount" name="amount" required min="0.01">
                    </div>

                    <div class="form-group">
                        <label for="description">وصف/ملاحظات (لتوضيح سبب المصروف):</label>
                        <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn-success" id="submit-expense-btn">
                            تسجيل المصروف
                        </button>
                        <div id="expense-message" class="mt-3" style="margin-top: 15px;"></div>
                    </div>
                </form>
                </div>
        </div>

        <div class="col-md-6">
            <div class="card" style="border-top: 4px solid #17a2b8;">
                <div class="card-header" style="border-bottom-color: #17a2b8;">
                    آخر المصروفات المسجلة
                </div>
                <div id="last-expenses-display">
                    <div class="loader" style="text-align: center;">جاري تحميل المصروفات...</div>
                </div>
            </div>
            
            <div class="card" style="text-align: center; border-top: 4px solid #6f42c1;">
                <a href="expenses_report.php" style="text-decoration: none; font-size: 1.2em; font-weight: bold; color: #6f42c1;">
                    📊 عرض تقارير المصروفات الشهرية
                </a>
                <p style="color: #6c757d; font-size: 0.9em; margin-top: 5px;">لمراجعة المصروفات والربح الصافي.</p>
            </div>
        </div>
    </div>
    <a href="logout.php" class="logout-link">🚪 تسجيل الخروج</a>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // استدعاء دالة لتحميل آخر المصروفات عند تحميل الصفحة
    loadLastExpenses();
    
    // معالج إرسال النموذج
    document.getElementById('add-expense-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = document.getElementById('submit-expense-btn');
        const messageArea = document.getElementById('expense-message');

        submitBtn.disabled = true;
        messageArea.innerHTML = '<span class="text-info">جاري التسجيل...</span>';
        
        fetch('add_expense.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            submitBtn.disabled = false;
            
            if (result.status === 'success') {
                messageArea.innerHTML = '<span class="text-success font-weight-bold">' + result.message + '</span>';
                form.reset(); 
                document.getElementById('expense_date').value = '<?php echo $current_date_time; ?>';
                loadLastExpenses(); // تحديث القائمة بعد التسجيل بنجاح
            } else {
                messageArea.innerHTML = '<span class="text-danger font-weight-bold">' + result.message + '</span>';
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            messageArea.innerHTML = '<span class="text-danger">فشل في الاتصال بالخادم.</span>';
            console.error('Error:', error);
        });
    });
});

// دالة لجلب وعرض آخر 5 مصروفات
function loadLastExpenses() {
    const displayArea = document.getElementById('last-expenses-display');
    displayArea.innerHTML = '<div class="loader text-info">جاري تحميل المصروفات...</div>';
    
    // 💡 سنفترض وجود ملف get_expenses_data.php لجلب البيانات
    fetch('get_expenses_data.php?limit=5')
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success' && result.data.length > 0) {
                let listHTML = '<ul class="list-group">';
                result.data.forEach(expense => {
                    listHTML += `
                        <li class="list-group-item">
                            <div>
                                <span class="expense-info">${expense.description}</span>
                                <p style="font-size: 0.8em; margin: 0; color: #6c757d;">${expense.category} - ${expense.date_formatted}</p>
                            </div>
                            <span class="expense-amount">-${parseFloat(expense.amount).toFixed(2)} ج.س</span>
                        </li>
                    `;
                });
                listHTML += '</ul>';
                displayArea.innerHTML = listHTML;
            } else if (result.status === 'success' && result.data.length === 0) {
                displayArea.innerHTML = '<p style="text-align: center; color: #dc3545;">لا توجد مصروفات مسجلة بعد.</p>';
            } else {
                displayArea.innerHTML = '<p class="text-danger" style="text-align: center;">فشل جلب المصروفات: ' + result.message + '</p>';
            }
        })
        .catch(error => {
            displayArea.innerHTML = '<p class="text-danger" style="text-align: center;">خطأ في الاتصال بالخادم.</p>';
            console.error('Fetch Error:', error);
        });
}
</script>

</body>
</html>