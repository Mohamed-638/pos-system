<?php
// delete_user.php - معالج حذف المستخدمين (للمدير فقط)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
require_once 'config.php';

// 1. التحقق من صلاحية المدير
check_access('admin'); 

// 2. استخلاص وتصفية معرف المستخدم المراد حذفه
$user_id_to_delete = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;

$message = '';
$redirect_url = 'manage_users.php';

// التحقق من وجود المعرف
if ($user_id_to_delete <= 0) {
    $message = "❌ لم يتم تحديد المستخدم المراد حذفه بشكل صحيح.";
    header("Location: {$redirect_url}?message=" . urlencode($message));
    exit();
}

// 3. فحص أمني: منع المدير من حذف حسابه الشخصي
$current_admin_id = $_SESSION['user_id'] ?? 0;

if ((int)$user_id_to_delete === (int)$current_admin_id) {
    $message = "🚫 لا يمكنك حذف حسابك الشخصي أثناء تسجيل الدخول.";
    header("Location: {$redirect_url}?message=" . urlencode($message));
    exit();
}

// 4. تنفيذ عملية الحذف
try {
    // جلب اسم المستخدم قبل حذفه لعرضه في رسالة النجاح
    $stmt_name = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt_name->bind_param("i", $user_id_to_delete);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    $user_data = $result_name->fetch_assoc();
    $username_deleted = $user_data['username'] ?? 'مستخدم غير معروف';
    $stmt_name->close();

    // استعلام الحذف الآمن باستخدام العبارات المُعدَّة
    $sql = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id_to_delete);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $message = "✅ تم حذف المستخدم **{$username_deleted}** بنجاح.";
        } else {
            // قد يحدث هذا إذا كان المستخدم موجوداً عند جلب اسمه، ثم حذفه شخص آخر
            $message = "⚠️ المستخدم ذو المعرف ID: {$user_id_to_delete} غير موجود أو تم حذفه مسبقاً.";
        }
    } else {
        $message = "❌ خطأ في عملية الحذف: " . $stmt->error;
    }
    $stmt->close();

} catch (Exception $e) {
    $message = "❌ خطأ غير متوقع في قاعدة البيانات: " . $e->getMessage();
}

// 5. إغلاق الاتصال وإعادة التوجيه
$conn->close();
header("Location: {$redirect_url}?message=" . urlencode($message));
exit();