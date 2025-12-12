<?php
// edit_product.php - نموذج ومعالج تعديل منتج موجود (النسخة المصححة)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// التحقق من صلاحية المدير
check_access('admin'); 

$message = ''; 
// 💡 المسار النسبي الذي سيتم حفظه في قاعدة البيانات وعرضه في المتصفح
$db_upload_path = 'images/products/'; 
// 💡 المسار المطلق على نظام التشغيل لاستخدام وظائف PHP (مثل unlink)
$server_root = dirname(__FILE__) . '/';
$server_upload_dir = $server_root . $db_upload_path;

$product = null; 

// ------------------------------------------
// 1. تحديد المعرف (ID) وجلب البيانات
// ------------------------------------------

$product_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;

if ($product_id <= 0 && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {
    $product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
}

if ($product_id <= 0) {
    header("Location: view_products.php?message=" . urlencode("❌ لم يتم تحديد المنتج المراد تعديله."));
    exit();
}

try {
    $stmt_fetch = $conn->prepare("SELECT product_id, name, price, cost, stock, active, image_path FROM products WHERE product_id = ?");
    $stmt_fetch->bind_param("i", $product_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    
    if ($result_fetch->num_rows === 0) {
        $conn->close();
        header("Location: view_products.php?message=" . urlencode("❌ المنتج المطلوب غير موجود."));
        exit();
    }
    
    $product = $result_fetch->fetch_assoc();
    $stmt_fetch->close();

} catch (Exception $e) {
    $message = "❌ خطأ في جلب بيانات المنتج: " . $e->getMessage();
}


// ------------------------------------------
// 2. معالجة طلب التعديل (POST Request)
// ------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && $product) {
    
    // **أ. تصفية والتحقق من البيانات**
    $updated_id = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
    $name = trim($_POST['name']);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $cost = filter_var($_POST['cost'], FILTER_VALIDATE_FLOAT); 
    $stock = filter_var($_POST['stock'], FILTER_VALIDATE_INT);
    $active = isset($_POST['active']) ? 1 : 0;
    
    // المسار الحالي المخزن في قاعدة البيانات
    $current_image_path_db = $product['image_path']; 
    $new_image_path = $current_image_path_db; // نفترض عدم وجود تغيير مبدئياً

    $is_valid = true;

    if ((int)$updated_id !== (int)$product_id || empty($name) || $price === false || $price <= 0 || $stock === false || $stock < 0 || $cost === false || $cost < 0) {
        $message = "🚫 خطأ في الإدخال: بيانات التعديل غير كاملة أو غير صالحة.";
        $is_valid = false;
    }

    // **ب. معالجة حذف الصورة الحالية**
    if ($is_valid && isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        // نستخدم المسار المطلق للحذف على نظام التشغيل
        if ($current_image_path_db && file_exists($server_root . $current_image_path_db)) {
            @unlink($server_root . $current_image_path_db); 
        }
        $new_image_path = NULL; // تعيين المسار لـ NULL في قاعدة البيانات
    }

    // **ج. معالجة تحميل صورة جديدة**
    if ($is_valid && isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $file_info = pathinfo($_FILES['product_image']['name']);
        $file_extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_extension, $allowed_extensions)) {
            $message = "🚫 خطأ في الصورة: يُسمح فقط بملفات JPG، JPEG، PNG، GIF.";
            $is_valid = false;
        } else {
            // حذف الصورة القديمة قبل رفع الجديدة (إذا كانت موجودة) باستخدام المسار المطلق
            if ($current_image_path_db && file_exists($server_root . $current_image_path_db)) {
                @unlink($server_root . $current_image_path_db);
            }
            
            // إنشاء اسم فريد للملف
            $new_file_name = time() . '-' . uniqid() . '.' . $file_extension;
            $target_file = $server_upload_dir . $new_file_name; // المسار المطلق للرفع
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                // 🚀 حفظ المسار النسبي (للعرض على الويب) في قاعدة البيانات
                $new_image_path = $db_upload_path . $new_file_name; 
            } else {
                $message = "❌ فشل رفع الملف. تحقق من صلاحيات مجلد uploads.";
                $is_valid = false;
            }
        }
    }


    // **د. تنفيذ استعلام التعديل (UPDATE Query)**
    if ($is_valid) {
        try {
            $sql = "UPDATE products SET name=?, price=?, cost=?, stock=?, active=?, image_path=? WHERE product_id=?";
            $stmt = $conn->prepare($sql);
            
            // s=string, d=double, d=double, i=integer, i=integer, s=string, i=integer
            $stmt->bind_param("sddiisi", $name, $price, $cost, $stock, $active, $new_image_path, $product_id);
            
            if ($stmt->execute()) {
                // تحديث البيانات المعروضة في النموذج بعد التعديل الناجح
                $product = [
                    'product_id' => $product_id,
                    'name' => $name,
                    'price' => $price,
                    'cost' => $cost,
                    'stock' => $stock,
                    'active' => $active,
                    'image_path' => $new_image_path,
                ];
                
                $message = "✅ تم تحديث المنتج **{$name}** بنجاح!";
            } else {
                $message = "❌ خطأ في التحديث: " . $stmt->error;
            }
            $stmt->close();

        } catch (Exception $e) {
            $message = "❌ خطأ غير متوقع: " . $e->getMessage();
        }
    }
}

// 3. إغلاق الاتصال بعد الانتهاء من استخدامه
if ($conn->connect_errno === 0) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل منتج: <?php echo htmlspecialchars($product['name'] ?? 'غير موجود'); ?></title>
    <style>
        /* ... (تنسيقات CSS بدون تغيير) ... */
        body { font-family: Tahoma, sans-serif; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; padding: 25px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type=text], input[type=number], input[type=file], select { 
            width: 100%; 
            padding: 10px; 
            margin-bottom: 15px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        input[type=checkbox] { 
            margin-left: 10px;
        }
        input[type=submit] { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        input[type=submit]:hover { background-color: #1e7e34; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-row { display: flex; gap: 20px; }
        .form-row > div { flex: 1; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #6c757d; }

        .current-image-preview { margin-bottom: 15px; text-align: center; border: 1px solid #ccc; padding: 10px; border-radius: 4px; }
        .current-image-preview img { max-width: 100%; height: auto; display: block; margin: 10px auto; border-radius: 4px; }
        .image-action { display: flex; align-items: center; justify-content: center; }

    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
        <h2>🛠️ تعديل المنتج: <?php echo htmlspecialchars($product['name'] ?? ''); ?></h2>
        
        <?php 
        // عرض رسالة النجاح أو الفشل
        if ($message) {
            $class = (strpos($message, '✅') !== false) ? 'success' : 'error';
            echo "<div class='message $class'>{$message}</div>";
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $product_id; ?>" method="post" enctype="multipart/form-data">
            
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id'] ?? ''); ?>">

            <label for="name">اسم المنتج:</label>
            <input type="text" id="name" name="name" required placeholder="اسم المنتج" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>">

            <div class="form-row">
                <div>
                    <label for="price">سعر البيع (SDG):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" required placeholder="سعر البيع" value="<?php echo htmlspecialchars($product['price'] ?? 0.00); ?>">
                </div>
                <div>
                    <label for="cost">سعر التكلفة (SDG):</label>
                    <input type="number" id="cost" name="cost" step="0.01" min="0" required placeholder="سعر التكلفة" value="<?php echo htmlspecialchars($product['cost'] ?? 0.00); ?>">
                </div>
            </div>

            <label for="stock">كمية المخزون:</label>
            <input type="number" id="stock" name="stock" min="0" required placeholder="كمية المخزون" value="<?php echo htmlspecialchars($product['stock'] ?? 0); ?>">

            <label>صورة المنتج:</label>

            <?php 
            $display_image_path = $product['image_path'] ?? '';
            ?>

            <?php if (!empty($display_image_path) && file_exists($server_root . $display_image_path)): ?>
                <div class="current-image-preview">
                    <p>الصورة الحالية:</p>
                    <img src="<?php echo htmlspecialchars($display_image_path); ?>" alt="صورة المنتج" style="max-height: 200px;">
                    <div class="image-action">
                        <input type="checkbox" id="delete_image" name="delete_image" value="1">
                        <label for="delete_image">حذف الصورة الحالية</label>
                    </div>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #888;">لا توجد صورة حالية للمنتج. </p>
            <?php endif; ?>

            <label for="product_image">تحميل صورة جديدة (ستحل محل الصورة الحالية):</label>
            <input type="file" id="product_image" name="product_image" accept="image/*">
            
            <div style="margin-bottom: 15px;">
                <label for="active">الحالة:</label>
                <input type="checkbox" id="active" name="active" <?php echo ($product['active'] ?? 1) ? 'checked' : ''; ?>>
                <span>متاح للبيع (نشط)</span>
            </div>

            <input type="submit" value="حفظ التعديلات">
            
            <a href="view_products.php" class="back-link">العودة لقائمة المنتجات</a>
        </form>
    </div>
</body>
</html>