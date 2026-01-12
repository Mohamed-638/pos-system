<?php
// get_last_sales.php

// 1. استعلام لجلب آخر 10 مبيعات
// ملاحظة: يتم استخدام $conn من الملف الرئيسي pos_screen.php
$branch_id = $_SESSION['branch_id'] ?? null;
$sql = "SELECT sale_id, total_amount, payment_method, sale_date 
        FROM sales 
        WHERE (? IS NULL OR branch_id = ?)
        ORDER BY sale_id DESC 
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $branch_id, $branch_id);
$stmt->execute();
$result = $stmt->get_result();

$last_sales = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $last_sales[] = $row;
    }
}
$stmt->close();
?>
