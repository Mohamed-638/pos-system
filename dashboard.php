<?php
// dashboard.php - لوحة تحكم المدير (النسخة النهائية مع التحليلات)

session_start();
require_once 'db_connect.php';
require_once 'config.php';
require_once 'auth_check.php';

// التحقق من صلاحية المدير
check_access('admin');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم المدير - <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'النظام'; ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .header-bar { background-color: #343a40; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header-bar a { color: #ffc107; text-decoration: none; font-weight: bold; margin-left: 20px; }
        .header-bar a:hover { color: white; }
        .container { padding: 20px; }
        h1 { color: #007bff; text-align: center; margin-bottom: 30px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { font-size: 1em; color: #6c757d; margin-top: 0; margin-bottom: 10px; }
        .stat-card .value { font-size: 2.5em; font-weight: bold; }
        
        /* ألوان بطاقات الإحصائيات */
        .card-blue .value { color: #007bff; }
        .card-green .value { color: #28a745; }
        .card-orange .value { color: #fd7e14; }
        .card-red .value { color: #dc3545; }
        .card-purple .value { color: #6f42c1; }
        .card-yellow .value { color: #ffc107; }

        .loader { text-align: center; font-size: 1.2em; color: #007bff; }
        
        /* تنسيق قسم الروابط */
        .nav-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-top: 40px; }
        .nav-card { 
            background-color: #ffffff; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
            text-align: center;
            border-top: 4px solid #007bff; /* تمييز */
            transition: background-color 0.2s, box-shadow 0.2s;
        }
        .nav-card:hover { background-color: #f1f5ff; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2); }
        .nav-card a { 
            text-decoration: none; 
            color: #333; 
            font-size: 1.4em; 
            font-weight: bold;
            display: block;
        }
        .nav-card p { color: #6c757d; margin-top: 10px; }
        .icon { font-size: 2em; margin-bottom: 10px; color: #007bff; }
        .logout-link { display: block; text-align: center; margin-top: 40px; font-size: 1.1em; color: #dc3545; text-decoration: none; }
        .logout-link:hover { text-decoration: underline; }
.nav-card:hover { background-color: #f1f5ff; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2); }
        /* 🟢 تنسيق خاص للتحليلات المضافة */
        .analysis-list {
            list-style: none;
            padding: 0;
            margin: 10px 0 0 0;
            text-align: right; /* محاذاة القوائم لليمين */
        }
        .analysis-list li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #e9ecef;
            font-size: 1em;
        }
        .analysis-list li:last-child {
            border-bottom: none;
        }
        .analysis-label {
            font-weight: bold;
            color: #343a40;
            text-align: right;
        }
        .analysis-value {
            color: #007bff;
            font-weight: 600;
        }
        .analysis-value.green {
            color: #28a745;
        }

        /* 🟢 تنسيقات بطاقات المصروفات والتقارير المضافة */
.nav-card.expenses { border-top: 4px solid #dc3545; }
.nav-card.expenses .icon { color: #dc3545; }
.nav-card.report { border-top: 4px solid #28a745; }
.nav-card.report .icon { color: #28a745; }
/* ---------------------------------------------------- */
    </style>
</head>
<body>

<?php require_once 'includes/admin_header.php'; ?>

    <div class="container">
    <h1><span style="color: #6f42c1;">⚙️</span> لوحة تحكم المدير <span style="color: #6f42c1;">📊</span></h1>
    
    <div style="display:flex; justify-content: flex-end; gap: 10px; margin-bottom: 10px;">
        <form id="branch-form" onsubmit="event.preventDefault(); loadDashboardData();">
            <label for="branch_filter">فرع:</label>
            <select id="branch_filter" name="branch_filter" style="padding:6px;">
                <option value="">كل الفروع</option>
                <?php
                    $branches_res = $conn->query("SELECT branch_id, name FROM branches ORDER BY name");
                    if ($branches_res) {
                        while($b = $branches_res->fetch_assoc()) {
                            echo "<option value='{$b['branch_id']}'>{$b['name']}</option>";
                        }
                        $branches_res->free();
                    }
                ?>
            </select>
        </form>
    </div>
    <div style="margin-bottom: 20px; text-align: right;">
        <strong>لوحات الفروع:</strong>
        <?php
            $branches_res = $conn->query("SELECT branch_id, name FROM branches ORDER BY name");
            if ($branches_res) {
                while($b = $branches_res->fetch_assoc()) {
                    echo "<a href='dashboard_branch.php?branch_id={$b['branch_id']}' style='margin-left:8px; text-decoration:none; font-weight:bold;'>{$b['name']}</a>";
                }
                $branches_res->free();
            }
        ?>
    </div>
    <div class="stats-grid" id="dashboard-stats">
        <div class="loader">جاري تحميل البيانات...</div>
    </div>
    
    <div style="background-color: #e9ecef; padding: 20px; border-radius: 8px; margin-bottom: 40px;">
        <h2>📝 تقارير الأرباح</h2>
        <p>هذا القسم مخصص لعرض حركة المخزون وتفاصيل الأرباح لكل منتج.</p>
        <p>مجموع الأرباح لليوم: <strong id="profit-display" style="color: #28a745; font-size: 1.5em;">...</strong> ج.س</p>
    </div>

    <h2>📊 تحليل الذروة والمبيعات (اليوم)</h2>
    <div class="stats-grid" id="analysis-grid">
        <div class="loader">جاري تحميل تقارير التحليل...</div>
    </div>
    
    <div class="nav-grid">
<div class="nav-card">
<span class="icon">🛒</span>
<a href="pos_screen.php">شاشة نقاط البيع (الكاشير)</a>
<p>إجراء عمليات البيع اليومية.</p>
</div>

<div class="nav-card">
<span class="icon">📦</span>
<a href="view_products.php">إدارة المنتجات</a>
<p>إضافة، تعديل، وعرض المخزون.</p>
</div>

    <div class="nav-card">
        <span class="icon">🏭</span>
        <a href="view_branches.php">إدارة الفروع</a>
        <p>عرض، إضافة، وتعديل الفروع.</p>
    </div>

    <div class="nav-card">
        <span class="icon">📊</span>
        <a href="dashboard_all_branches.php">نظرة عامة على الفروع</a>
        <p>عرض إحصائيات مجمعة لكل فرع.</p>
    </div>

    <!-- Removed quick-create 'Add Branch' card as requested; admin can use branch list to add via management pages. -->

    <div class="nav-card expenses">
        <span class="icon">💸</span>
        <a href="expenses.php">تسجيل وإدارة المصروفات</a>
        <p>تسجيل المصروفات التشغيلية للمنشأة.</p>
    </div>

    <div class="nav-card report">
        <span class="icon">📉</span>
        <a href="expenses_report.php">تقرير الربح الصافي</a>
        <p>مراجعة الإيرادات والمصروفات وحساب الربح الصافي.</p>
    </div>
        <div class="nav-card">
            <span class="icon">🏭</span>
            <a href="view_suppliers.php">المورّدين والمشتريات</a>
            <p>إدارة مورّدين وعمليات الشراء وتوريد المخزون.</p>
        </div>
        <!-- Removed quick-create 'Add Supplier' card; suppliers are managed from view_suppliers.php -->

        <!-- Use Purchases management page to add purchases. -->
    <div class="nav-card">
<span class="icon">📈</span>
<a href="sales_log_admin.php">سجل المبيعات الشامل</a>
<p>مراجعة جميع الإيصالات والأرباح.</p>
</div>
    
<div class="nav-card">
<span class="icon">🧑‍💻</span>
<a href="manage_users.php">إدارة المستخدمين</a> 
<p>إضافة أو تعطيل موظفي الكاشير.</p>
</div>
</div>
    
    <a href="logout.php" class="logout-link">🚪 تسجيل الخروج</a>

</div>

<script>
document.addEventListener('DOMContentLoaded', loadDashboardData);

function loadDashboardData() {
    const statsContainer = document.getElementById('dashboard-stats');
    const analysisContainer = document.getElementById('analysis-grid'); 
    
    statsContainer.innerHTML = '<div class="loader">جاري تحميل البيانات...</div>';
    analysisContainer.innerHTML = '<div class="loader">جاري تحميل تقارير التحليل...</div>';

    const branchId = document.getElementById('branch_filter').value;
    let url = 'get_dashboard_data.php';
    if (branchId) url += '?branch_id=' + encodeURIComponent(branchId);
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('فشل جلب البيانات الإحصائية: ' + response.statusText);
            }
            return response.json();
        })
        .then(result => {
            if (result.status === 'success') {
                const data = result.data;
                
                const formatCurrency = (amount) => parseFloat(amount).toFixed(2) + ' ج.س';
                const formatNumber = (num) => parseInt(num).toLocaleString();

                // ---------------------------------------------------
                // 1. عرض الإحصائيات الرئيسية
                // ---------------------------------------------------
                statsContainer.innerHTML = `
                    <div class="stat-card card-green">
                        <h3>إجمالي مبيعات اليوم (الصافي)</h3>
                        <div class="value">${formatCurrency(data.total_sales_today)}</div>
                        <p style="font-size: 0.9em; color: #6c757d;">(${formatNumber(data.total_transactions_today)} طلب)</p>
                    </div>

                    <div class="stat-card card-blue">
                        <h3>إجمالي الأرباح لليوم</h3>
                        <div class="value">${formatCurrency(data.total_profit_today)}</div>
                        <p style="font-size: 0.9em; color: #6c757d;">(الفرق بين المبيعات والتكلفة)</p>
                    </div>
                    
                    <div class="stat-card card-orange">
                        <h3>مبيعات اليوم (نقدي)</h3>
                        <div class="value">${formatCurrency(data.cash_sales_today)}</div>
                    </div>
                    
                    <div class="stat-card card-purple">
                        <h3>مبيعات اليوم (دفع بنكي/تطبيق)</h3>
                        <div class="value">${formatCurrency(data.app_sales_today)}</div>
                    </div>

                    <div class="stat-card card-red">
                        <h3>إجمالي المنتجات المسجلة</h3>
                        <div class="value">${formatNumber(data.product_count)}</div>
                    </div>

                    <div class="stat-card card-yellow">
                        <h3>إجمالي العمليات المنفذة (الكل)</h3>
                        <div class="value">${formatNumber(data.total_transactions_all)}</div>
                    </div>
                `;

                document.getElementById('profit-display').innerText = formatCurrency(data.total_profit_today);

                // ---------------------------------------------------
                // 2. عرض التحليلات المضافة
                // ---------------------------------------------------
                
                // تجهيز المنتجات الأكثر مبيعاً
                let topProductsHTML = data.top_products.map((p, index) => 
                    `<li>
                        <span class="analysis-label">${index + 1}. ${p.product_name}</span>
                        <span class="analysis-value green">${formatNumber(p.total_sold)} حبة</span>
                    </li>`
                ).join('');

                if (data.top_products.length === 0) {
                    topProductsHTML = '<li><span style="color: #dc3545;">لا توجد مبيعات مكتملة اليوم.</span></li>';
                }

                // تجهيز أوقات الذروة
                let peakHoursHTML = data.peak_hours.map((h, index) => {
                    // تحويل الساعة (0-23) إلى تنسيق 12 ساعة مع AM/PM حسب الرغبة، أو تركها 24 ساعة
                    const hour24 = h.peak_hour;
                    const displayTime = (hour24 > 12 ? hour24 - 12 : (hour24 === 0 ? 12 : hour24)) + (hour24 >= 12 ? ' مساءً' : ' صباحاً');

                    return `
                        <li>
                            <span class="analysis-label">الساعة ${displayTime}</span>
                            <span class="analysis-value">${formatNumber(h.transaction_count)} طلب</span>
                        </li>
                    `;
                }).join('');
                
                 if (data.peak_hours.length === 0) {
                    peakHoursHTML = '<li><span style="color: #dc3545;">لا توجد طلبات مكتملة لتحديد الذروة.</span></li>';
                }

                analysisContainer.innerHTML = `
                    <div class="stat-card card-purple">
                        <h3>🏆 المنتجات الأكثر مبيعاً (الكمية)</h3>
                        <ul class="analysis-list">${topProductsHTML}</ul>
                    </div>

                    <div class="stat-card card-orange">
                        <h3>⏰ أوقات الذروة في المبيعات</h3>
                        <ul class="analysis-list">${peakHoursHTML}</ul>
                    </div>
                `;


            } else {
                statsContainer.innerHTML = '<div style="color: red; text-align: center;">خطأ في البيانات: ' + result.message + '</div>';
                analysisContainer.innerHTML = '<div style="color: red; text-align: center;">خطأ في البيانات: ' + result.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
            statsContainer.innerHTML = '<div style="color: red; text-align: center;">فشل الاتصال بالخادم. تحقق من ملف get_dashboard_data.php</div>';
            analysisContainer.innerHTML = '<div style="color: red; text-align: center;">فشل الاتصال بالخادم. تحقق من ملف get_dashboard_data.php</div>';
        });
}
</script>

</body>
</html>