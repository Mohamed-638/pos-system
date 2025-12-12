<?php
// handle_product.php - معالج CRUD للمنتجات (للمدير فقط)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// التحقق من صلاحية المدير
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => '🚫 غير مصرح لك بإجراء هذه العملية.']);
    exit();
}

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'طلب غير صالح.'];
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($data['mode'])) {
    
    $mode = $data['mode'];

    // ------------------------------------------
    // 1. إضافة منتج جديد (ADD)
    // ------------------------------------------
    if ($mode === 'add' && 
        isset($data['name'], $data['price'], $data['cost'], $data['stock'], $data['active'])) {
        
        // تنظيف البيانات
        $name = trim($data['name']);
        $price = floatval($data['price']);
        $cost = floatval($data['cost']);
        $stock = intval($data['stock']);
        $active = intval($data['active']);
        
        if (empty($name) || $price <= 0 || $cost < 0 || $stock < 0) {
            $response['message'] = 'جميع حقول البيانات إلزامية ويجب أن تكون صحيحة.';
            goto end_script;
        }

        try {
            $branch_id = $_SESSION['branch_id'] ?? null;
            $stmt = $conn->prepare("INSERT INTO products (name, price, cost, stock, active, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdddii", $name, $price, $cost, $stock, $active, $branch_id); 
            
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '✅ تم إضافة المنتج بنجاح.'];
            } else {
                throw new Exception("خطأ قاعدة البيانات: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
             $response['message'] = "فشل الإضافة: " . $e->getMessage();
        }
    }
    
    // ------------------------------------------
    // 2. تعديل منتج موجود (UPDATE)
    // ------------------------------------------
    elseif ($mode === 'update' && 
            isset($data['product_id'], $data['name'], $data['price'], $data['cost'], $data['stock'], $data['active'])) {
        
        // تنظيف البيانات
        $product_id = intval($data['product_id']);
        $name = trim($data['name']);
        $price = floatval($data['price']);
        $cost = floatval($data['cost']);
        $stock = intval($data['stock']);
        $active = intval($data['active']);
        
        if ($product_id <= 0 || empty($name) || $price <= 0 || $cost < 0 || $stock < 0) {
            $response['message'] = 'بيانات التعديل غير كاملة أو غير صالحة.';
            goto end_script;
        }

        try {
            $branch_id = $_SESSION['branch_id'] ?? null;
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, cost=?, stock=?, active=?, branch_id=? WHERE product_id=?");
            $stmt->bind_param("sddiiii", $name, $price, $cost, $stock, $active, $branch_id, $product_id); 
            
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '✅ تم تحديث المنتج بنجاح.'];
            } else {
                 throw new Exception("خطأ قاعدة البيانات: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
             $response['message'] = "فشل التعديل: " . $e->getMessage();
        }
    }

    // ------------------------------------------
    // 3. حذف منتج (DELETE)
    // ------------------------------------------
    elseif ($mode === 'delete' && isset($data['product_id'])) {
        
        $product_id = intval($data['product_id']);
        
        if ($product_id <= 0) {
            $response['message'] = 'معرّف المنتج غير صالح للحذف.';
            goto end_script;
        }
        
        try {
            // ملاحظة: يجب التأكد من عدم وجود بيانات مرتبطة في جدول sale_items قبل الحذف، 
            // وإلا ستفشل العملية بسبب قيود المفاتيح الخارجية.
            
            // الحل المبدئي: سنحاول الحذف مباشرة
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id=?");
            $stmt->bind_param("i", $product_id); 
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response = ['status' => 'success', 'message' => '🗑️ تم حذف المنتج بنجاح.'];
                } else {
                    $response = ['status' => 'error', 'message' => 'لم يتم العثور على المنتج للحذف.'];
                }
            } else {
                 throw new Exception("خطأ قاعدة البيانات: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
             // قد يكون هذا الخطأ ناتجًا عن وجود مبيعات مرتبطة بالمنتج
             $response['message'] = "فشل الحذف. قد يكون المنتج مرتبطًا بمبيعات سابقة: " . $e->getMessage();
        }
    }
}

end_script:
$conn->close();
echo json_encode($response);
?>