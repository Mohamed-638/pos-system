<?php
// get_last_sales.php

// 1. استعلام لجلب آخر 10 مبيعات
// ملاحظة: يتم استخدام $conn من الملف الرئيسي pos_screen.php
$sql = "SELECT sale_id, total_amount, payment_method, sale_date 
        FROM sales 
        ORDER BY sale_id DESC 
        LIMIT 10"; // عرض آخر 10 فواتير

$result = $conn->query($sql);

$last_sales = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $last_sales[] = $row;
    }
}
?>