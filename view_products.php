<?php
// view_products.php - عرض وإدارة المنتجات (للمدير فقط)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
// تأكد من وجود ملف config.php وجلبه لاسم المطعم
require_once 'config.php'; 

// التحقق من صلاحية المدير
check_access('admin'); 

// جلب رسالة النظام من أي عملية سابقة (إضافة/تعديل/حذف)
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$branch_filter = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;

$branches = [];
$branches_res = $conn->query("SELECT branch_id, name FROM branches ORDER BY name");
if ($branches_res) {
    while ($b = $branches_res->fetch_assoc()) {
        $branches[] = $b;
    }
    $branches_res->free();
}

// ---------------------------------------------------
// 1. جلب قائمة المنتجات
// ---------------------------------------------------
// 🛠️ تم تصحيح أسماء الأعمدة: استخدام 'stock' كـ quantity و 'active' كـ status
$sql_products = "SELECT 
                    p.product_id, 
                    p.name, 
                    p.price, 
                    p.cost, 
                    p.stock AS quantity,  /* اسم العمود الفعلي في DB هو 'stock' */
                    p.active AS status,   /* اسم العمود الفعلي في DB هو 'active' (0 أو 1) */
                    p.image_path,
                    b.name AS branch_name
                 FROM products p
                 LEFT JOIN branches b ON p.branch_id = b.branch_id
                 WHERE (? IS NULL OR p.branch_id = ?)
                 ORDER BY p.product_id DESC";
                 
$stmt_products = $conn->prepare($sql_products);
$stmt_products->bind_param("ii", $branch_filter, $branch_filter);
$stmt_products->execute();
$result_products = $stmt_products->get_result();

$products = [];
if ($result_products) {
    while($row = $result_products->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    $message = "❌ حدث خطأ في جلب بيانات المنتجات: " . $conn->error;
}
$stmt_products->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة المنتجات - <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'النظام'; ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 30px auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h2 { border-bottom: 3px solid #007bff; padding-bottom: 10px; color: #333; display: flex; justify-content: space-between; align-items: center; }
        
        /* روابط التنقل والأزرار */
        .nav-links { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .nav-links a { text-decoration: none; padding: 10px 15px; border-radius: 5px; font-weight: bold; transition: background-color 0.2s; margin-left: 10px; }
        .add-link { background-color: #28a745; color: white; }
        .add-link:hover { background-color: #1e7e34; }
        .back-link { background-color: #6c757d; color: white; }
        .back-link:hover { background-color: #5a6268; }

        /* رسائل النظام */
        .message-box { padding: 15px; border-radius: 4px; text-align: center; margin-bottom: 20px; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* تنسيق الجدول */
        .product-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; text-align: center; }
        .product-table th, .product-table td { border: 1px solid #ddd; padding: 12px; }
        .product-table th { background-color: #007bff; color: white; }
        .product-table tr:nth-child(even) { background-color: #f9f9f9; }
        .product-table tr:hover { background-color: #f1f1f1; }
        
        /* الأزرار داخل الجدول */
        .action-btn { padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; margin: 2px; transition: opacity 0.2s; }
        .edit-btn { background-color: #ffc107; color: #333; }
        .delete-btn { background-color: #dc3545; color: white; }
        
        /* الصورة المصغرة */
        .product-thumb { max-width: 50px; height: auto; border-radius: 4px; border: 1px solid #eee; }
        
        /* حالة المنتج */
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }

        /* تنبيه المخزون */
        .low-stock { background-color: #fff3cd; color: #856404; font-weight: bold; }
    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
        
        <h2>
            📦 إدارة المنتجات والمخزون
        </h2>

        <div class="nav-links">
            <a href="dashboard.php" class="back-link">🔙 لوحة التحكم</a>
            <a href="add_product.php" class="add-link">➕ إضافة منتج جديد</a>
        </div>

        <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
            <label for="branch_id" style="font-weight: bold;">الفرع:</label>
            <select id="branch_id" name="branch_id">
                <option value="">كل الفروع</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['branch_id']; ?>" <?php echo ($branch_filter === (int)$branch['branch_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="action-btn" style="background-color: #007bff; color: white;">تصفية</button>
        </form>


        <?php if ($message): 
            $class = (strpos($message, '❌') !== false || strpos($message, 'خطأ') !== false) ? 'error' : 'success';
        ?>
            <div class="message-box <?php echo $class; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($products)): ?>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>الصورة</th>
                        <th>المنتج</th>
                        <th>الفرع</th>
                        <th>سعر البيع (ج.س)</th>
                        <th>سعر التكلفة (ج.س)</th>
                        <th>المخزون</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <?php 
                        // يتم جلب الحالة كـ 'status' بقيمة 0 أو 1 بسبب إعادة التسمية في SQL
                        $is_active = (int)$product['status'] === 1;
                        
                        $stock_class = '';
                        // يتم استخدام 'quantity' (وهي في الأصل 'stock')
                        if ($product['quantity'] < 5 && $is_active) {
                            $stock_class = 'low-stock';
                        }
                        
                        // إعداد عرض الصورة
                        $image_path = $product['image_path'];
                        $image_tag = 'لا توجد صورة';

                        if (!empty($image_path)) {
                            // المسار المخزن في قاعدة البيانات هو المسار النسبي الصحيح (مثل images/products/...)
                            $image_url_for_display = htmlspecialchars($image_path);
                            $image_tag = "<img src='{$image_url_for_display}' alt='" . htmlspecialchars($product['name']) . "' class='product-thumb'
                                        onerror=\"this.onerror=null;this.src='images/default_product.png';\">";
                        }

                        $status_text = $is_active ? '<span class="status-active">متاح ✅</span>' : '<span class="status-inactive">متوقف 🛑</span>';
                    ?>
                    <tr class="<?php echo $stock_class; ?>">
                        <td><?php echo $product['product_id']; ?></td>
                        <td><?php echo $image_tag; ?></td>
                        <td style="text-align: right; font-weight: bold;"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['branch_name'] ?? '-'); ?></td>
                        <td><?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo number_format($product['cost'], 2); ?></td>
                        <td>
                            <?php echo (int)$product['quantity']; ?>
                            <?php if ($stock_class === 'low-stock'): ?>
                                (نفاد وشيك!)
                            <?php endif; ?>
                        </td>
                        <td><?php echo $status_text; ?></td>
                        <td>
                            <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="action-btn edit-btn">تعديل ✍️</a>
                            <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $product['product_id']; ?>)">
                                حذف 🗑️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 30px; background-color: #ffe0e6; border: 1px dashed #dc3545;">لا توجد منتجات مسجلة حالياً في النظام.</p>
        <?php endif; ?>

    </div>
    <script>
        // دالة JavaScript لتأكيد عملية الحذف
        function confirmDelete(id) {
            if (confirm("هل أنت متأكد من حذف المنتج رقم " + id + "؟ سيتم حذفه نهائياً وحذف صورته.")) {
                // التوجيه إلى ملف معالجة الحذف المنفصل
                window.location.href = 'delete_product.php?id=' + id;
            }
        }
    </script>
</body>
</html>
