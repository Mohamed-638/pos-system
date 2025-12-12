<?php
// save_sale.php - معالج حفظ عملية البيع (النسخة النهائية مع status)

session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php'; 

header('Content-Type: application/json'); 

$response = ['status' => 'error', 'message' => 'بيانات غير صالحة.'];

// التحقق من تسجيل الدخول قبل أي عملية
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => '🚫 غير مصرح لك بإجراء العملية. الرجاء تسجيل الدخول أولاً.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
// branch id for this user if any
$current_branch_id = $_SESSION['branch_id'] ?? null;


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);
    
    // التحقق من أن البيانات الأساسية للطلب موجودة وغير فارغة
    if (isset($data['order']) && is_array($data['order']) && !empty($data['order'])) {
        
        $totalAmount   = filter_var($data['total_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $paymentMethod = $data['payment_method']; 
        $orderItems    = $data['order'];

        // **3. بدء المعاملة (Transaction)**
        // لضمان الحماية من الأخطاء في منتصف العملية
        $conn->begin_transaction();
        
        try {
            // **أ. إدراج عملية البيع الرئيسية في جدول sales**
            // 🟢 التعديل: تمت إضافة عمود status بقيمة 'completed'
            $stmt_sale = $conn->prepare("INSERT INTO sales (total_amount, payment_method, status, user_id, branch_id) VALUES (?, ?, 'completed', ?, ?)");
            if ($stmt_sale === false) {
                throw new Exception("خطأ في إعداد استعلام البيع الرئيسي: " . $conn->error);
            }
            
            // d=double, s=string, i=integer
            $stmt_sale->bind_param("dsii", $totalAmount, $paymentMethod, $current_user_id, $current_branch_id);
            
            if (!$stmt_sale->execute()) {
                throw new Exception("خطأ في حفظ عملية البيع الرئيسية: " . $stmt_sale->error);
            }
            $sale_id = $conn->insert_id; 
            $stmt_sale->close();

            // **ب. إدراج تفاصيل المنتجات وتحديث المخزون**
            $stmt_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, sub_total, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            // 💡 استعلام تحديث المخزون: يطرح الكمية المباعة ويتأكد أن المخزون لا يذهب للسالب
            $stmt_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?");

            if ($stmt_item === false || $stmt_stock === false) {
                 throw new Exception("خطأ في إعداد استعلام التفاصيل/المخزون: " . $conn->error);
            }

            foreach ($orderItems as $item) {
                $productId   = (int)$item['id'];
                $quantity    = (float)$item['quantity']; 
                $price       = (float)$item['price']; 
                $costPrice   = (float)$item['cost']; 
                $productName = $item['name'];
                $subTotal    = $quantity * $price; 

                if ($quantity <= 0) continue; 

                // 1. حفظ تفاصيل المنتج في جدول sale_items
                // ربط المعاملات: i, i, s, d, d, d, d
                $stmt_item->bind_param("iissddd", $sale_id, $productId, $productName, $quantity, $price, $subTotal, $costPrice); 
                
                if (!$stmt_item->execute()) {
                    throw new Exception("خطأ في حفظ تفاصيل المنتج: " . $stmt_item->error);
                }

                // 2. تحديث كمية المخزون (طرح الكمية المباعة)
                // ربط المعاملات: d=الكمية المطروحة, i=ID المنتج, d=الكمية للمقارنة
                $stmt_stock->bind_param("did", $quantity, $productId, $quantity); 
                if (!$stmt_stock->execute()) {
                    throw new Exception("خطأ في تحديث المخزون للمنتج ID: {$productId}.");
                }
                
                // 3. التحقق من أنه تم تحديث صف واحد على الأقل (لتأكيد توفر المخزون)
                if ($stmt_stock->affected_rows === 0) {
                     // محاولة تحديث المخزون فشلت، مما يعني أن المخزون غير كافٍ.
                     throw new Exception("المخزون غير كافٍ للمنتج ID: {$productId} ({$productName}). لم يتم إتمام العملية.");
                }
            }
            
            $stmt_item->close();
            $stmt_stock->close();

            // **ج. إنهاء المعاملة بنجاح**
            $conn->commit();
            $response = ['status' => 'success', 'message' => '✅ تم تسجيل الطلب بنجاح. رقم الفاتورة: ' . $sale_id, 'sale_id' => $sale_id];

        } catch (Exception $e) {
            // **د. التراجع عند الخطأ**
            $conn->rollback();
            error_log("Sale Transaction Failed: " . $e->getMessage()); 
            $response = ['status' => 'error', 'message' => 'فشلت عملية البيع. الرجاء المحاولة مجدداً. (التفاصيل: ' . $e->getMessage() . ')'];
        }
    } else {
        $response['message'] = 'الطلب فارغ أو البيانات غير صحيحة.';
    }
}

$conn->close();
echo json_encode($response);