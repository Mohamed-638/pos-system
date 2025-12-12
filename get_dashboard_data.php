<?php
// get_dashboard_data.php - جلب بيانات لوحة التحكم (مُحدَّث لتحليل الذروة والمبيعات)

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php'; 
// check_access('admin'); // يفترض أن يتم تضمينها في auth_check.php

date_default_timezone_set('Africa/Khartoum'); 

$today = date('Y-m-d');
$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : null;

$stats = [
    'total_sales_today' => 0,
    'total_transactions_today' => 0, // الإيصالات المكتملة
    'cash_sales_today' => 0,
    'app_sales_today' => 0,
    'total_profit_today' => 0, 
    'product_count' => 0,
    'total_transactions_all' => 0, // الإيصالات الكلية لليوم
    // الإضافات الجديدة
    'top_products' => [],
    'peak_hours' => [],
];

try {

    // ---------------------------------------------------
    // أ. حساب مبيعات اليوم وتفاصيل الدفع (المكتملة فقط)
    // ---------------------------------------------------
    $sql_sales_today = "
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_sales_today,
            COUNT(sale_id) as total_transactions_today,
            SUM(CASE WHEN payment_method IN ('نقدي', 'كاش', 'نقد') THEN total_amount ELSE 0 END) as cash_sales_today,
            SUM(CASE WHEN payment_method NOT IN ('نقدي', 'كاش', 'نقد') THEN total_amount ELSE 0 END) as app_sales_today
        FROM 
            sales
        WHERE 
            DATE(sale_date) = CURDATE()
            AND status = 'completed';
    ";
    if ($branch_id) {
        $sql_sales_today = str_replace("FROM\n            sales", "FROM\n            sales s", $sql_sales_today);
        $sql_sales_today = str_replace("WHERE\n            DATE(sale_date) = CURDATE()", "WHERE\n            DATE(s.sale_date) = CURDATE() AND s.branch_id = ?", $sql_sales_today);
        $stmt = $conn->prepare($sql_sales_today);
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $result_today = $stmt->get_result();
    } else {
        $result_today = $conn->query($sql_sales_today);
    }
    if ($result_today && $row = $result_today->fetch_assoc()) {
        $stats['total_sales_today'] = (float)$row['total_sales_today'];
        $stats['total_transactions_today'] = (int)$row['total_transactions_today'];
        $stats['cash_sales_today'] = (float)$row['cash_sales_today'];
        $stats['app_sales_today'] = (float)$row['app_sales_today'];
    }

    // ---------------------------------------------------
    // ب. حساب إجمالي العمليات المنفذة (الكل) لليوم
    // ---------------------------------------------------
    $sql_transactions_all = "
        SELECT COUNT(sale_id) as total_transactions_all FROM sales WHERE DATE(sale_date) = CURDATE();
    ";
    if ($branch_id) {
        $sql_transactions_all = "SELECT COUNT(sale_id) as total_transactions_all FROM sales s WHERE DATE(s.sale_date) = CURDATE() AND s.branch_id = ?";
        $stmt = $conn->prepare($sql_transactions_all);
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $result_all = $stmt->get_result();
    } else {
        $result_all = $conn->query($sql_transactions_all);
    }
    if ($result_all && $row = $result_all->fetch_assoc()) {
        $stats['total_transactions_all'] = (int)$row['total_transactions_all'];
    }

    // ---------------------------------------------------
    // ج. حساب إجمالي الأرباح لليوم الحالي (المكتملة فقط)
    // ---------------------------------------------------
    $sql_profit = "
        SELECT 
            COALESCE(SUM( (si.price - si.cost_price) * si.quantity ), 0) as total_profit_today
        FROM 
            sale_items si
        JOIN
            sales s ON si.sale_id = s.sale_id
        WHERE 
            DATE(s.sale_date) = CURDATE()
            AND s.status = 'completed';
    ";
    if ($branch_id) {
        $sql_profit = str_replace("FROM\n            sale_items si\n        JOIN\n            sales s ON si.sale_id = s.sale_id\n        WHERE \n            DATE(s.sale_date) = CURDATE()", "FROM\n            sale_items si\n        JOIN\n            sales s ON si.sale_id = s.sale_id\n        WHERE \n            DATE(s.sale_date) = CURDATE() AND s.branch_id = ?");
        $stmt = $conn->prepare($sql_profit);
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $result_profit = $stmt->get_result();
    } else {
        $result_profit = $conn->query($sql_profit);
    }
    if ($result_profit && $row = $result_profit->fetch_assoc()) {
        $stats['total_profit_today'] = (float)$row['total_profit_today'];
    }

    // ---------------------------------------------------
    // د. حساب عدد المنتجات المسجلة
    // ---------------------------------------------------
    $sql_products = "SELECT COUNT(product_id) as product_count FROM products;";
    if ($branch_id) {
        // count products for a branch if branch_id exists
        $stmt = $conn->prepare("SELECT COUNT(product_id) as product_count FROM products WHERE branch_id = ?");
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $result_products = $stmt->get_result();
    } else {
        $result_products = $conn->query($sql_products);
    }
    if ($result_products && $row = $result_products->fetch_assoc()) {
        $stats['product_count'] = (int)$row['product_count'];
    }
    
    // ---------------------------------------------------
    // هـ. (جديد) المنتجات الأكثر مبيعاً (Top 3)
    // ---------------------------------------------------
    $sql_top_products = "
        SELECT 
            p.name AS product_name,
            SUM(sd.quantity) AS total_sold
        FROM 
            sale_items sd
        JOIN 
            products p ON sd.product_id = p.product_id
        JOIN
            sales s ON sd.sale_id = s.sale_id
        WHERE
            DATE(s.sale_date) = CURDATE() AND s.status = 'completed'
        GROUP BY 
            p.name
        ORDER BY 
            total_sold DESC
        LIMIT 3
    ";
    if ($branch_id) {
        $sql_top_products = str_replace("JOIN\n            sales s ON sd.sale_id = s.sale_id\n        WHERE\n            DATE(s.sale_date) = CURDATE() AND s.status = 'completed'", "JOIN\n            sales s ON sd.sale_id = s.sale_id\n        WHERE\n            DATE(s.sale_date) = CURDATE() AND s.status = 'completed' AND s.branch_id = ?");
        $stmt = $conn->prepare($sql_top_products);
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $result_top_products = $stmt->get_result();
    } else {
        $result_top_products = $conn->query($sql_top_products);
    }
    $top_products = [];
    if ($result_top_products) {
        while($row = $result_top_products->fetch_assoc()) {
            $top_products[] = $row;
        }
    }
    $stats['top_products'] = $top_products;

    // ---------------------------------------------------
    // و. (جديد) أوقات الذروة (Top 3 Peak Hours)
    // ---------------------------------------------------
    $sql_peak_hours = "
        SELECT
            HOUR(sale_date) AS peak_hour,
            COUNT(sale_id) AS transaction_count
        FROM
            sales
        WHERE
            DATE(sale_date) = CURDATE() AND status = 'completed'
        GROUP BY
            peak_hour
        ORDER BY
            transaction_count DESC
        LIMIT 3
    ";
    if ($branch_id) {
        $sql_peak_hours = "SELECT HOUR(sale_date) AS peak_hour, COUNT(sale_id) AS transaction_count FROM sales s WHERE DATE(s.sale_date) = CURDATE() AND s.status = 'completed' AND s.branch_id = ? GROUP BY peak_hour ORDER BY transaction_count DESC LIMIT 3";
        $stmt = $conn->prepare($sql_peak_hours);
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $result_peak_hours = $stmt->get_result();
    } else {
        $result_peak_hours = $conn->query($sql_peak_hours);
    }
    $peak_hours = [];
    if ($result_peak_hours) {
        while($row = $result_peak_hours->fetch_assoc()) {
            $peak_hours[] = $row;
        }
    }
    $stats['peak_hours'] = $peak_hours;

    // ---------------------------------------------------
    
    // إرجاع البيانات بصيغة JSON
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $stats]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("خطأ في جلب بيانات اللوحة: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'خطأ في جلب بيانات اللوحة: ' . $e->getMessage()]);
}

$conn->close();
?>