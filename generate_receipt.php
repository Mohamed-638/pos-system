<?php
// generate_receipt.php - لإنشاء محتوى الفاتورة الاحترافي والقصير

session_start();
require_once 'db_connect.php'; 
// 💡 يجب التأكد من وجود ملف config.php وجلبه
require_once 'config.php'; 

if (!isset($_GET['sale_id']) || !is_numeric($_GET['sale_id'])) {
    // 💡 تضمين رسالة الخطأ داخل تنسيق الطباعة لكي يظهر الخطأ
    echo '<div class="center">خطأ: لم يتم تحديد رقم الفاتورة.</div>';
    exit();
}

$sale_id = (int)$_GET['sale_id'];
$receipt_html = '';

try {
    // 1. جلب تفاصيل البيع الرئيسية (مع اسم المستخدم)
    $stmt_sale = $conn->prepare("
        SELECT 
            s.total_amount, s.payment_method, s.sale_date, u.username, s.branch_id, b.name AS branch_name, b.address AS branch_address, b.phone AS branch_phone
        FROM 
            sales s
        JOIN 
            users u ON s.user_id = u.user_id
        LEFT JOIN
            branches b ON s.branch_id = b.branch_id
        WHERE 
            s.sale_id = ?
    ");
    $stmt_sale->bind_param("i", $sale_id);
    $stmt_sale->execute();
    $result_sale = $stmt_sale->get_result();
    $sale_details = $result_sale->fetch_assoc();
    $stmt_sale->close();

    if (!$sale_details) {
        throw new Exception("الفاتورة رقم {$sale_id} غير موجودة.");
    }

    // 2. جلب تفاصيل المنتجات المباعة 
    $stmt_items = $conn->prepare("
        SELECT 
            product_name, quantity, price, sub_total, cost_price
        FROM 
            sale_items 
        WHERE 
            sale_id = ?
    ");
    $stmt_items->bind_param("i", $sale_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $stmt_items->close();

    $items = [];
    $total_cost = 0; 
    while ($row = $result_items->fetch_assoc()) {
        $items[] = $row;
        // حساب التكلفة الإجمالية للطلب (يمكن تخزينها للاستفادة منها لاحقًا إذا لزم الأمر)
        $total_cost += $row['cost_price'] * $row['quantity'];
    }
    
    // 3. بناء هيكل الإيصال (HTML)
    
    // 🚨 التعديل المُقترح: وضع الأنماط في وسم <style>
    $receipt_html .= '
    <style>
        /* أنماط أساسية لطباعة الإيصالات الحرارية */
        .receipt { width: 100%; border-collapse: collapse; }
        .receipt td, .receipt th { padding: 0; text-align: right; }
        .center { text-align: center; }
        /* إخفاء معلومات التكلفة في طباعة الإيصال إن وجدت */
        .cost-info { display: none; } 
    </style>
    ';

    $receipt_html .= '
        <div class="receipt-container" style="width: 100%; font-size: 11.5pt;"> 
            
            <div style="display: flex; align-items: center; justify-content: center; flex-direction: column; margin-bottom: 5px; gap: 4px; border-bottom: 1px dashed #000; padding-bottom: 8px;">
                <img src="' . RESTAURANT_LOGO_URL . '" alt="' . RESTAURANT_NAME . ' Logo" 
                    style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                <h3 style="margin: 0; font-size: 1.2em; line-height: 1;">' . RESTAURANT_NAME . '</h3>
                ' . (empty($sale_details['branch_name']) ? '' : '<div style="font-size: 0.95em; font-weight:bold">' . htmlspecialchars($sale_details['branch_name']) . '</div>') . '
            </div>
            
            <p style="font-size: 0.9em; margin: 5px 0 0 0;">
                رقم الفاتورة: <strong>#' . $sale_id . '</strong> <br>
                التاريخ: ' . date('Y-m-d H:i', strtotime($sale_details['sale_date'])) . ' <br>
                الكاشير: ' . htmlspecialchars($sale_details['username']) . '
            </p>
            
            <hr style="border: none; border-top: 1px dashed #000; margin: 5px 0;">

            <table class="receipt" style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                <thead>
                    <tr style="border-bottom: 1px solid #000; font-weight: bold;">
                        <th style="width: 50%; text-align: right; padding: 3px 0;">المنتج</th>
                        <th style="width: 15%; text-align: center; padding: 3px 0;">كـم</th>
                        <th style="width: 35%; text-align: left; padding: 3px 0;">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>';
                    
                    foreach ($items as $item) {
                        $receipt_html .= '
                            <tr>
                                <td style="text-align: right; padding: 2px 0;">' . htmlspecialchars($item['product_name']) . '</td>
                                <td style="text-align: center; padding: 2px 0;">' . number_format($item['quantity'], 0) . '</td>
                                <td style="text-align: left; padding: 2px 0;">' . number_format($item['sub_total'], 2) . ' ج.س</td>
                            </tr>
                            <tr style="font-size: 0.8em; opacity: 0.9;">
                                <td colspan="3" style="text-align: left; padding: 0 0 5px 0;">
                                    (' . number_format($item['price'], 2) . ' x ' . number_format($item['quantity'], 0) . ')
                                </td>
                            </tr>';
                    }

    $receipt_html .= '
                </tbody>
            </table>
            
            <hr style="border: none; border-top: 2px dashed #000; margin: 8px 0;">

            <div class="total-row" style="padding: 5px 0; font-weight: bold; font-size: 1.2em; display: flex; justify-content: space-between;">
                <span>الإجمالي الكلي:</span>
                <span>' . number_format($sale_details['total_amount'], 2) . ' ج.س</span>
            </div>
            
            <hr style="border: none; border-top: 1px dashed #000; margin: 8px 0;">

            <p style="font-size: 0.9em; text-align: center; margin-top: 5px;">طريقة الدفع: <strong>' . htmlspecialchars($sale_details['payment_method']) . '</strong></p>

            <hr style="border: none; border-top: 1px dashed #000; margin: 8px 0;">
            
                <p class="center" style="font-size: 0.8em; margin: 2px 0;">' . (!empty($sale_details['branch_address']) ? htmlspecialchars($sale_details['branch_address']) : RESTAURANT_ADDRESS) . '</p>
                <p class="center" style="font-size: 0.8em; margin: 2px 0;">هاتف: ' . (!empty($sale_details['branch_phone']) ? htmlspecialchars($sale_details['branch_phone']) : RESTAURANT_PHONE) . '</p>

            <h4 class="center" style="margin-top: 10px; margin-bottom: 5px; font-size: 0.9em;">' . RESTAURANT_FOOTER_MESSAGE . '</h4>
        </div>
    ';

} catch (Exception $e) {
    error_log("Receipt Generation Error: " . $e->getMessage());
    $receipt_html = '<div class="center" style="color: red;">تعذر توليد الفاتورة: ' . $e->getMessage() . '</div>';
}

$conn->close();
echo $receipt_html;
?>