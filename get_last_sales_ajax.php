<?php
// get_last_sales_ajax.php - لجلب آخر 10 مبيعات وعرضها في لوحة POS مع زر الإلغاء

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php'; 

// لا حاجة لـ header('Content-Type: application/json') لأننا سنُرجع محتوى HTML

if (!isset($_SESSION['user_id'])) {
    echo '<p style="color: red;">خطأ: يجب تسجيل الدخول لرؤية آخر الطلبات.</p>';
    exit();
}

$output = '';

try {
    // 1. جلب آخر 10 مبيعات: 🟢 التعديل: إضافة عمود status
    $sql = "
        SELECT 
            s.sale_id, 
            s.sale_date,  
            s.total_amount, 
            s.payment_method,
            s.status,     
            u.username 
        FROM 
            sales s
        JOIN 
            users u ON s.user_id = u.user_id
        ORDER BY 
            s.sale_id DESC
        LIMIT 10
    ";
    
    $result = $conn->query($sql);

    if ($result === false) {
          throw new Exception("SQL Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        $output .= '<ul>';
        while ($row = $result->fetch_assoc()) {
            $date = date('H:i', strtotime($row['sale_date'])); 
            $status = $row['status'];
            $sale_id = $row['sale_id'];
            $total_amount = $row['total_amount'];
            
            $status_color = ($status === 'cancelled') ? '#dc3545' : '#198754';
            $status_text  = ($status === 'cancelled') ? ' (ملغى 🚫)' : ' (مكتمل)';
            
            // 2. تحديد الزر الذي سيظهر
            $action_button = '';
            if ($status === 'completed') {
                // زر الإلغاء يظهر للإيصالات المكتملة فقط
                $action_button = "
                    <button 
                        onclick=\"confirmCancellation({$sale_id}, {$total_amount})\" 
                        style='background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; font-size: 0.9em; margin-left: 5px;'
                        title='إلغاء هذا الإيصال وإعادة المخزون'
                    >
                        إلغاء
                    </button>
                ";
            }
            
            // زر إعادة الطباعة يظهر دائماً
            $reprint_button = "
                <button 
                    onclick='reprintReceipt({$sale_id})' 
                    style='background: #007bff; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; font-size: 0.9em;'
                >
                    🖨️
                </button>
            ";

            $output .= "
                <li style='border-bottom: 1px dotted #ddd; padding: 5px 0; display: flex; justify-content: space-between; align-items: center;'>
                    <div>
                        <strong>#{$sale_id}</strong> - {$total_amount} ج.س ({$row['payment_method']})
                        <span style='font-size: 0.9em; color: {$status_color}; font-weight: bold;'>{$status_text}</span>
                        <br>
                        <span style='font-size: 0.8em; color: #666;'>{$date} | البائع: {$row['username']}</span>
                    </div>
                    <div style='display: flex; gap: 5px;'>
                        {$action_button}
                        {$reprint_button}
                    </div>
                </li>
            ";
        }
        $output .= '</ul>';
        $result->close();
    } else {
        $output .= '<p style="text-align: center; color: #aaa;">لا توجد مبيعات مسجلة حتى الآن.</p>';
    }

} catch (Exception $e) {
    error_log("Error loading last sales: " . $e->getMessage());
    $output = '<p style="color: red;">حدث خطأ في جلب بيانات المبيعات. (راجع سجل الأخطاء)</p>';
}

$conn->close();
echo $output;
?>