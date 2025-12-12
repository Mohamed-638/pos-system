<?php
// add_product.php - نموذج ومعالج إضافة منتج جديد (النسخة المصححة)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';

// يجب أن يكون المدير مسجلاً للدخول
check_access('admin'); 

$message = ''; 
// 💡 المسار النسبي الذي سيتم حفظه في قاعدة البيانات وعرضه في المتصفح
$db_upload_path = 'images/products/'; 
// 💡 المسار المطلق على نظام التشغيل لاستخدام وظائف PHP (مثل move_uploaded_file)
$server_root = dirname(__FILE__) . '/';
$server_upload_dir = $server_root . $db_upload_path;

// 2. التحقق مما إذا كان النموذج قد تم إرساله بطريقة POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // **أ. تصفية (Sanitization) البيانات المدخلة**
    $name  = trim($_POST['name']);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $cost  = filter_var($_POST['cost'], FILTER_VALIDATE_FLOAT); 
    $stock = filter_var($_POST['stock'], FILTER_VALIDATE_INT);
    $active = isset($_POST['active']) ? 1 : 0; 

    $image_path = NULL; // المسار الافتراضي للصورة (للتخزين في DB)
    $current_branch_id = $_SESSION['branch_id'] ?? null;
    $is_valid = true;

    // **ب. التحقق من صحة البيانات**
    if (empty($name) || $price === false || $price <= 0 || $stock === false || $stock < 0 || $cost === false || $cost < 0) {
        $message = "🚫 خطأ في الإدخال: يرجى التأكد من صحة جميع الحقول الإلزامية.";
        $is_valid = false;
    }
    
    // **ج. معالجة تحميل الصورة**
    if ($is_valid && isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $file_info = pathinfo($_FILES['product_image']['name']);
        $file_extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_extension, $allowed_extensions)) {
            $message = "🚫 خطأ في الصورة: يُسمح فقط بملفات JPG، JPEG، PNG، GIF.";
            $is_valid = false;
        } else {
            // إنشاء اسم فريد للملف
            $new_file_name = time() . '-' . uniqid() . '.' . $file_extension;
            // المسار الكامل للملف على الخادم
            $target_file = $server_upload_dir . $new_file_name;

            // التأكد من أن المجلد موجود
            if (!is_dir($server_upload_dir)) {
                // نستخدم المسار المطلق لإنشاء المجلد
                mkdir($server_upload_dir, 0777, true); 
            }
            
            // محاولة نقل الملف باستخدام المسار المطلق ($target_file)
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                // 🚀 حفظ المسار النسبي (للعرض على الويب) في قاعدة البيانات
                $image_path = $db_upload_path . $new_file_name; 
            } else {
                $message = "❌ فشل رفع الملف. تحقق من صلاحيات مجلد uploads.";
                $is_valid = false;
            }
        }
    }

    // **د. إدراج البيانات في قاعدة البيانات (باستخدام البيانات المُعدَّة)**
    if ($is_valid) {
        try {
            $sql = "INSERT INTO products (name, price, cost, stock, active, image_path, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // s=string, d=double, d=double, i=integer, i=integer, s=string, i=integer
            $stmt->bind_param("sdddisi", $name, $price, $cost, $stock, $active, $image_path, $current_branch_id);
            
            if ($stmt->execute()) {
                header("Location: view_products.php?message=" . urlencode("✅ تمت إضافة المنتج **{$name}** بنجاح."));
                exit();
            } else {
                $message = "❌ خطأ في الإضافة: " . $stmt->error;
            }
            $stmt->close();

        } catch (Exception $e) {
            $message = "❌ خطأ غير متوقع: " . $e->getMessage();
        }
    }
}

// 3. إغلاق الاتصال بعد الانتهاء من استخدامه
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة منتج جديد</title>
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
        input[type=submit] { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        input[type=submit]:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-row { display: flex; gap: 20px; }
        .form-row > div { flex: 1; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #6c757d; }
    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
        <h2>➕ إضافة منتج جديد</h2>
        
        <?php 
        // 4. عرض رسالة النجاح أو الفشل
        if ($message) {
            $class = (strpos($message, '✅') !== false) ? 'success' : 'error';
            echo "<div class='message $class'>{$message}</div>";
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            
            <label for="name">اسم المنتج:</label>
            <input type="text" id="name" name="name" required placeholder="مثال: فطيرة بيرقر">

            <div class="form-row">
                <div>
                    <label for="price">سعر البيع (SDG):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" required placeholder="مثال: 15.50">
                </div>
                <div>
                    <label for="cost">سعر التكلفة (SDG):</label>
                    <input type="number" id="cost" name="cost" step="0.01" min="0" required placeholder="مثال: 10.00">
                </div>
            </div>

            <label for="stock">كمية المخزون:</label>
            <input type="number" id="stock" name="stock" min="0" required placeholder="مثال: 100">

            <label for="product_image">صورة المنتج (اختياري):</label>
            <input type="file" id="product_image" name="product_image" accept="image/*">
            
            <div style="margin-bottom: 15px;">
                <label for="active">الحالة:</label>
                <input type="checkbox" id="active" name="active" checked>
                <span>متاح للبيع (نشط)</span>
            </div>

            <input type="submit" value="حفظ المنتج">
            
            <a href="view_products.php" class="back-link">العودة لقائمة المنتجات</a>
        </form>
    </div>
</body>
</html>