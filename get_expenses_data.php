<?php
// get_expenses_data.php - لجلب قائمة المصروفات (للعرض في الواجهة الإدارية)

require_once 'db_connect.php'; 

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'حدث خطأ.', 'data' => []];

$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
// 1. جلب المصروفات بترتيب تنازلي حسب التاريخ (الأحدث أولاً)
$sql = "SELECT 
expense_id, 
DATE_FORMAT(expense_date, '%Y-%m-%d %H:%i') AS date_formatted, 
description, 
amount, 
category 
FROM 
expenditures 
ORDER BY 
expense_date DESC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
while ($row = $result->fetch_assoc()) {
$expenses[] = [
'expense_id' => (int)$row['expense_id'],
'date_formatted' => $row['date_formatted'],
'description' => htmlspecialchars($row['description']),
'amount' => (float)$row['amount'],
'category' => htmlspecialchars($row['category'])
];
}

$stmt->close();

$response['status'] = 'success';
$response['message'] = 'تم جلب البيانات بنجاح.';
$response['data'] = $expenses;

} catch (Exception $e) {
error_log("Error fetching expense data: " . $e->getMessage());
$response['message'] = 'فشل فني في جلب المصروفات.';
}

$conn->close();
echo json_encode($response);
?>