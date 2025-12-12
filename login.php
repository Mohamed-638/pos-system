<?php
// login.php - صفحة تسجيل الدخول للمستخدمين
session_start();
require_once 'db_connect.php'; 
require_once 'config.php'; // 🟢 1. تم تضمين ملف الإعدادات

$error_message = '';

// التحقق مما إذا كان النموذج قد تم إرساله
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "الرجاء إدخال اسم المستخدم وكلمة المرور.";
    } else {
        // الاستعلام عن المستخدم
        $stmt = $conn->prepare("SELECT user_id, password_hash, role, full_name FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // التحقق من كلمة المرور باستخدام دالة التشفير
            if (password_verify($password, $user['password_hash'])) {
                // النجاح! إنشاء جلسة للمستخدم
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                // حفظ branch_id في الجلسة إن وجد
                $stmt_branch = $conn->prepare("SELECT branch_id FROM users WHERE user_id = ? LIMIT 1");
                $stmt_branch->bind_param('i', $user['user_id']);
                $stmt_branch->execute();
                $res_branch = $stmt_branch->get_result();
                if ($res_branch && $row_branch = $res_branch->fetch_assoc()) {
                    $_SESSION['branch_id'] = $row_branch['branch_id'];
                } else {
                    // fallback: خذ أول فرع إذا لم يتوفر
                    $res_first = $conn->query("SELECT branch_id FROM branches ORDER BY branch_id LIMIT 1");
                    if ($res_first && $rowf = $res_first->fetch_assoc()) {
                        $_SESSION['branch_id'] = $rowf['branch_id'];
                    }
                }
                if (isset($stmt_branch)) $stmt_branch->close();
                
                // توجيه المستخدم حسب دوره
                if ($user['role'] === 'admin') {
                    header('Location: dashboard.php'); // المدير يذهب للتقارير
                } else {
                    header('Location: pos_screen.php'); // الكاشير يذهب لشاشة البيع
                }
                exit();
            } else {
                $error_message = "اسم المستخدم أو كلمة المرور غير صحيحة.";
            }
        } else {
            $error_message = "اسم المستخدم أو كلمة المرور غير صحيحة.";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - نظام نقاط البيع</title>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
        h2 { color: #343a40; margin-bottom: 25px; }
        .input-group { margin-bottom: 15px; text-align: right; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .error { color: #dc3545; margin-bottom: 15px; font-weight: bold; }
        button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1.1em; transition: background-color 0.3s; }
        button:hover { background-color: #0056b3; }
        
        /* 🟢 تنسيق الشعار */
        .logo { max-width: 120px; height: auto; margin-bottom: 20px; border-radius: 5px; } 
    </style>
</head>
<body>
    <div class="login-container">
        <img src="<?php echo RESTAURANT_LOGO_URL; ?>" alt="<?php echo RESTAURANT_NAME; ?>" class="logo">
        
        <h2>👤 تسجيل الدخول للنظام</h2>
        <?php if ($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="input-group">
                <label for="username">اسم المستخدم:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="input-group">
                <label for="password">كلمة المرور:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">دخول</button>
        </form>
    </div>
</body>
</html>