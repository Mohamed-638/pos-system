<?php
// edit_user.php - نموذج ومعالج تعديل مستخدم موجود (مدير/كاشير)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
require_once 'config.php';

// التحقق من صلاحية المدير
check_access('admin'); 

// load branches for selection
$branches = [];
$b_res = $conn->query("SELECT branch_id, name FROM branches ORDER BY name");
if ($b_res) {
    while ($b_row = $b_res->fetch_assoc()) {
        $branches[] = $b_row;
    }
}

$message = ''; 
$user = null; // سيتم تخزين بيانات المستخدم الذي يتم تعديله هنا
$current_user_id = $_SESSION['user_id'] ?? 0; // معرف المستخدم الحالي المسجل للدخول

// ------------------------------------------
// 1. تحديد المعرف (ID) وجلب البيانات
// ------------------------------------------

// استخلاص المعرف من الرابط (GET)
$user_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;

// إذا لم يتم تحديد ID من الرابط، نحاول استخلاصه من POST في حال حدوث خطأ
if ($user_id <= 0 && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
}

// إذا لم يتم تحديد معرف المنتج، نعود لصفحة الإدارة
if ($user_id <= 0) {
    header("Location: manage_users.php?message=" . urlencode("❌ لم يتم تحديد المستخدم المراد تعديله."));
    exit();
}

try {
    // جلب بيانات المستخدم الحالي
    $stmt_fetch = $conn->prepare("SELECT user_id, username, role, full_name, is_active, branch_id FROM users WHERE user_id = ?");
    $stmt_fetch->bind_param("i", $user_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    
    if ($result_fetch->num_rows === 0) {
        $conn->close();
        header("Location: manage_users.php?message=" . urlencode("❌ المستخدم المطلوب غير موجود."));
        exit();
    }
    
    $user = $result_fetch->fetch_assoc();
    $stmt_fetch->close();

} catch (Exception $e) {
    $message = "❌ خطأ في جلب بيانات المستخدم: " . $e->getMessage();
}

// ------------------------------------------
// 2. معالجة طلب التعديل (POST Request)
// ------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && $user) {
    
    // **أ. تصفية والتحقق من البيانات**
    $updated_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
    $username   = trim($_POST['username']);
    $full_name  = trim($_POST['full_name']);
    $role       = $_POST['role'];
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;
    $password   = $_POST['password'] ?? ''; // قد يكون فارغاً إذا لم يرغب المستخدم في التغيير
    $is_active  = isset($_POST['is_active']) ? 1 : 0;
    
    $is_valid = true;
    $errors = [];
    
    // التحقق من أن ID المرسل يطابق ID المستخدم الذي تم جلبه
    if ((int)$updated_id !== (int)$user_id) {
        $errors[] = "تضارب في معرف المستخدم.";
        $is_valid = false;
    }
    
    if (empty($username) || !in_array($role, ['admin', 'cashier'])) {
        $errors[] = "بيانات التعديل غير كاملة أو غير صالحة.";
        $is_valid = false;
    }

    // التحقق من طول كلمة المرور إذا تم إدخالها
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "يجب أن لا تقل كلمة المرور عن 6 أحرف إذا أردت تغييرها.";
        $is_valid = false;
    }

    // التحقق لمنع تغيير اسم المستخدم إلى اسم مستخدم آخر موجود بالفعل (باستثناء اسمه الحالي)
    if ($is_valid && $username !== $user['username']) {
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt_check->bind_param("si", $username, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $errors[] = "اسم المستخدم '$username' موجود بالفعل لدى مستخدم آخر.";
            $is_valid = false;
        }
        $stmt_check->close();
    }
    // validate branch if provided
    if ($is_valid && $branch_id !== null) {
        $found_branch = false;
        foreach ($branches as $bch) {
            if ($bch['branch_id'] == $branch_id) { $found_branch = true; break; }
        }
        if (!$found_branch) {
            $errors[] = "الفرع المحدد غير صالح.";
            $is_valid = false;
        }
    }
    
    // منع تعطيل حساب المدير الذي يقوم بالتعديل حالياً
    if ((int)$user_id === (int)$current_user_id && $is_active === 0) {
        $errors[] = "لا يمكن تعطيل حساب المدير الذي يقوم بتسجيل الدخول حالياً.";
        $is_valid = false;
    }

    if (!$is_valid) {
        $message = "🚫 خطأ في الإدخال: " . implode(" ", $errors);
    }

    // **ج. تنفيذ استعلام التعديل (UPDATE Query)**
    if ($is_valid) {
        
        $set_password_clause = "";
        $password_hash = null;
        // prepare default bind params (username, full_name, role, is_active, branch_id)
        $bind_params = [$username, $full_name, $role, $is_active, $branch_id];
        
        // إذا تم إدخال كلمة مرور جديدة، يتم تشفيرها وإضافتها للاستعلام
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $set_password_clause = ", password_hash = ?";
            // when password set, include password_hash after username
            $bind_params = [$username, $password_hash, $full_name, $role, $is_active, $branch_id];
        }

        // بناء استعلام التحديث
        $sql = "UPDATE users SET username=?, full_name=?, role=?, is_active=?, branch_id=? {$set_password_clause} WHERE user_id=?";
        
        // إذا تم تغيير كلمة المرور، يجب أن نغير ترتيب الحقول في الاستعلام ليتناسب مع bind_param
        if (!empty($password)) {
            $sql = "UPDATE users SET username=?, password_hash=?, full_name=?, role=?, is_active=?, branch_id=? WHERE user_id=?";
        }


        try {
            $stmt = $conn->prepare($sql);
            
            // إضافة user_id كمعامل أخير للتحديد في WHERE
            $bind_params[] = $user_id;

            // تحديد أنواع المتغيرات (Bind Types) بناءً على ما إذا كانت كلمة المرور ستتغير
            // If password present: username(s), password_hash(s), full_name(s), role(s), is_active(i), branch_id(i), user_id(i) => s s s s i i i
            // If password absent: username(s), full_name(s), role(s), is_active(i), branch_id(i), user_id(i) => s s s i i i
            $bind_types = !empty($password) ? "ssssiii" : "sssiii";
            
            // ربط المعاملات
            $stmt->bind_param($bind_types, ...$bind_params);
            
            if ($stmt->execute()) {
                // تحديث البيانات المعروضة في النموذج بعد التعديل الناجح
                $user['username'] = $username;
                $user['full_name'] = $full_name;
                $user['role'] = $role;
                $user['is_active'] = $is_active;
                $user['branch_id'] = $branch_id;
                
                $message = "✅ تم تحديث بيانات المستخدم **{$username}** بنجاح!";
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
    <title>تعديل مستخدم: <?php echo htmlspecialchars($user['username'] ?? 'غير موجود'); ?></title>
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
        input[type=submit] { background-color: #ffc107; color: #333; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; font-weight: bold; }
        input[type=submit]:hover { background-color: #e0a800; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #6c757d; }
    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
        <h2>🛠️ تعديل المستخدم: <?php echo htmlspecialchars($user['username'] ?? ''); ?></h2>
        
        <?php 
        // عرض رسالة النجاح أو الفشل
        if ($message) {
            $class = (strpos($message, '✅') !== false) ? 'success' : 'error';
            echo "<div class='message $class'>{$message}</div>";
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $user_id; ?>" method="post">
            
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id'] ?? ''); ?>">

            <label for="username">اسم المستخدم (لتسجيل الدخول):</label>
            <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">

            <label for="full_name">الاسم الكامل (اختياري):</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="الاسم الكامل">

            <label for="password">كلمة المرور الجديدة (اتركها فارغة لعدم التغيير):</label>
            <input type="password" id="password" name="password" placeholder="أدخل كلمة مرور جديدة (أو اتركها فارغة)">
            
            <label for="role">دور المستخدم:</label>
            <select id="role" name="role" required>
                <option value="cashier" <?php echo ($user['role'] ?? '') === 'cashier' ? 'selected' : ''; ?>>كاشير (Cashier)</option>
                <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>مدير (Admin)</option>
            </select>
            
            <label for="branch_id">الفرع (اختياري):</label>
            <select id="branch_id" name="branch_id">
                <option value="">-- لا يوجد (الفرع العالمي) --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['branch_id']; ?>" <?php echo (isset($user['branch_id']) && $user['branch_id'] == $b['branch_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <div style="margin-bottom: 15px;">
                <label for="is_active">الحالة:</label>
                <?php $is_disabled = ((int)$user_id === (int)$current_user_id) ? 'disabled' : ''; ?>
                
                <input type="checkbox" id="is_active" name="is_active" 
                       <?php echo (($user['is_active'] ?? 1) == 1) ? 'checked' : ''; ?>
                       <?php echo $is_disabled; ?>>
                
                <span>حساب نشط (يمكنه تسجيل الدخول)</span>
                <?php if ($is_disabled): ?>
                    <p style="color: red; font-size: 0.8em; margin: 5px 0 0 0;">(لا يمكن تعطيل حسابك الخاص كمدير.)</p>
                <?php endif; ?>
            </div>

            <input type="submit" value="حفظ التعديلات">
            
            <a href="manage_users.php" class="back-link">العودة لقائمة المستخدمين</a>
        </form>
    </div>
</body>
</html>