<?php
// add_user.php - نموذج ومعالج إضافة مستخدم جديد (مدير/كاشير)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
require_once 'config.php';

// يجب أن يكون المدير مسجلاً للدخول
check_access('admin'); 

// load branches for assignment (for both GET & POST)
$branches = [];
$b_res = $conn->query("SELECT branch_id, name FROM branches ORDER BY name");
if ($b_res) {
    while ($b_row = $b_res->fetch_assoc()) {
        $branches[] = $b_row;
    }
}

$message = ''; 

// 1. التحقق مما إذا كان النموذج قد تم إرساله بطريقة POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // **أ. تصفية (Sanitization) البيانات المدخلة**
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $full_name  = trim($_POST['full_name']);
    $role       = $_POST['role']; // يجب أن تكون 'admin' أو 'cashier'
    $is_active  = isset($_POST['is_active']) ? 1 : 0; 
    // branch_id is handled further below

    $is_valid = true;
    $errors = [];

    // **ب. التحقق من صحة البيانات**
    if (empty($username)) {
        $errors[] = "اسم المستخدم مطلوب.";
        $is_valid = false;
    }
    if (strlen($password) < 6) {
        $errors[] = "يجب أن لا تقل كلمة المرور عن 6 أحرف.";
        $is_valid = false;
    }
    if (!in_array($role, ['admin', 'cashier'])) {
        $errors[] = "دور المستخدم غير صحيح.";
        $is_valid = false;
    }
    
    // التحقق من أن اسم المستخدم غير موجود مسبقاً
    if ($is_valid) {
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $errors[] = "اسم المستخدم '$username' موجود بالفعل. يرجى اختيار اسم آخر.";
            $is_valid = false;
        }
        $stmt_check->close();
    }
    // validate branch if selected
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;
    if ($is_valid && $branch_id !== null) {
        $found = false;
        foreach ($branches as $bch) {
            if ($bch['branch_id'] == $branch_id) { $found = true; break; }
        }
        if (!$found) {
            $errors[] = "الفرع المحدد غير صالح.";
            $is_valid = false;
        }
    }
    
    // تجميع رسائل الخطأ
    if (!$is_valid) {
        $message = "🚫 خطأ في الإدخال: " . implode(" ", $errors);
    }
    
    // **ج. إدراج البيانات في قاعدة البيانات**
    if ($is_valid) {
        
        // تشفير كلمة المرور
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // ملاحظة: لن ندرج created_at هنا، حيث أن قاعدة البيانات ستضيفه تلقائياً (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
            $sql = "INSERT INTO users (username, password_hash, role, full_name, is_active, branch_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // s=string, s=string, s=string, s=string, i=integer
            // s=string, s=string, s=string, s=string, i=integer, i=integer(null)
            // for branch_id we allow NULL
            $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;
            $branch_bind = $branch_id === null ? null : $branch_id;
            $stmt->bind_param("ssssii", $username, $password_hash, $role, $full_name, $is_active, $branch_bind);
            
            if ($stmt->execute()) {
                // التوجيه إلى صفحة إدارة المستخدمين مع رسالة نجاح
                header("Location: manage_users.php?message=" . urlencode("✅ تمت إضافة المستخدم **{$username}** بنجاح كـ {$role}."));
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

// 2. إغلاق الاتصال بعد الانتهاء من استخدامه
$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة مستخدم جديد - <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'النظام'; ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 500px; margin: 0 auto; padding: 25px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #17a2b8; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type=text], input[type=password], select { 
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
        input[type=submit] { background-color: #17a2b8; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        input[type=submit]:hover { background-color: #138496; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #6c757d; }
    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
        <h2>➕ إضافة مستخدم جديد</h2>
        
        <?php 
        // 3. عرض رسالة النجاح أو الفشل
        if ($message) {
            $class = (strpos($message, '✅') !== false) ? 'success' : 'error';
            echo "<div class='message $class'>{$message}</div>";
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            
            <label for="username">اسم المستخدم (لتسجيل الدخول):</label>
            <input type="text" id="username" name="username" required placeholder="مثال: ahmed_admin" value="<?php echo htmlspecialchars($username ?? ''); ?>">

            <label for="password">كلمة المرور (لا تقل عن 6 أحرف):</label>
            <input type="password" id="password" name="password" required placeholder="************">
            
            <label for="full_name">الاسم الكامل (اختياري):</label>
            <input type="text" id="full_name" name="full_name" placeholder="مثال: أحمد محمد علي" value="<?php echo htmlspecialchars($full_name ?? ''); ?>">

            <label for="role">دور المستخدم:</label>
            <select id="role" name="role" required>
                <option value="cashier" <?php echo (($role ?? '') === 'cashier') ? 'selected' : ''; ?>>كاشير (Cashier)</option>
                <option value="admin" <?php echo (($role ?? '') === 'admin') ? 'selected' : ''; ?>>مدير (Admin)</option>
            </select>
            
            <div style="margin-bottom: 15px;">
                <label for="is_active">الحالة:</label>
                <input type="checkbox" id="is_active" name="is_active" checked>
                <span>حساب نشط (يمكنه تسجيل الدخول)</span>
            </div>

            <label for="branch_id">الفرع (اختياري):</label>
            <select id="branch_id" name="branch_id">
                <option value="">-- لا يوجد (الفرع العالمي) --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['branch_id']; ?>" <?php echo (isset($branch_id) && $branch_id == $b['branch_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="submit" value="إضافة المستخدم">
            
            <a href="manage_users.php" class="back-link">العودة لقائمة المستخدمين</a>
        </form>
    </div>
</body>
</html>