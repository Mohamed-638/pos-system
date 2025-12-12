<?php
// manage_users.php - عرض وإدارة المستخدمين (للمدير فقط)
session_start();
require_once 'db_connect.php'; 
require_once 'auth_check.php';
// تأكد من وجود ملف config.php وجلبه لاسم المطعم
require_once 'config.php'; 

// التحقق من صلاحية المدير
check_access('admin'); 

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';

// --------------------------------------------------------
// 1. جلب قائمة المستخدمين
// --------------------------------------------------------
// 🟢 تم التحديث: إضافة created_at مرة أخرى بعد التأكد من وجوده في قاعدة البيانات
$sql_users = "SELECT u.user_id, u.username, u.role, u.full_name, u.created_at, b.name AS branch_name 
          FROM users u 
          LEFT JOIN branches b ON u.branch_id = b.branch_id 
          ORDER BY u.user_id DESC";
              
$result_users = $conn->query($sql_users);

$users = [];
if ($result_users) {
    while($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    $message = "❌ حدث خطأ في جلب بيانات المستخدمين: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة المستخدمين - <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'النظام'; ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 30px auto; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h2 { border-bottom: 3px solid #17a2b8; padding-bottom: 10px; color: #333; display: flex; justify-content: space-between; align-items: center; }
        
        /* روابط التنقل والأزرار */
        .nav-links { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .nav-links a { text-decoration: none; padding: 10px 15px; border-radius: 5px; font-weight: bold; transition: background-color 0.2s; margin-left: 10px; }
        .add-link { background-color: #17a2b8; color: white; }
        .add-link:hover { background-color: #138496; }
        .back-link { background-color: #6c757d; color: white; }
        .back-link:hover { background-color: #5a6268; }

        /* رسائل النظام */
        .message-box { padding: 15px; border-radius: 4px; text-align: center; margin-bottom: 20px; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* تنسيق الجدول */
        .user-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; text-align: center; }
        .user-table th, .user-table td { border: 1px solid #ddd; padding: 12px; }
        .user-table th { background-color: #17a2b8; color: white; }
        .user-table tr:nth-child(even) { background-color: #f9f9f9; }
        .user-table tr:hover { background-color: #f1f1f1; }
        
        /* الأزرار داخل الجدول */
        .action-btn { padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; margin: 2px; transition: opacity 0.2s; }
        .edit-btn { background-color: #ffc107; color: #333; }
        .delete-btn { background-color: #dc3545; color: white; }
        
        /* الأدوار */
        .role-admin { color: #dc3545; font-weight: bold; }
        .role-cashier { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
        
        <h2>
            🧑‍💻 إدارة المستخدمين والموظفين
        </h2>

        <div class="nav-links">
            <a href="dashboard.php" class="back-link">🔙 لوحة التحكم</a>
            <a href="add_user.php" class="add-link">➕ إضافة مستخدم جديد</a> 
        </div>


        <?php if ($message): 
            $class = (strpos($message, '❌') !== false || strpos($message, 'خطأ') !== false) ? 'error' : 'success';
        ?>
            <div class="message-box <?php echo $class; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($users)): ?>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>اسم المستخدم</th>
                        <th>الاسم الكامل</th>
                        <th>الفرع</th>
                        <th>الدور</th>
                        <th>تاريخ التسجيل</th> <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php 
                        $role_class = $user['role'] === 'admin' ? 'role-admin' : 'role-cashier';
                        $role_text = $user['role'] === 'admin' ? 'مدير' : 'كاشير';
                    ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td style="font-weight: bold;"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['branch_name'] ?? '-'); ?></td>
                        <td><span class="<?php echo $role_class; ?>"><?php echo $role_text; ?></span></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td> <td>
                            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="action-btn edit-btn">تعديل ✍️</a>
                            
                            <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                حذف 🗑️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 30px; background-color: #fff3cd; border: 1px dashed #ffeeba;">لا توجد حسابات مستخدمين مسجلة حالياً في النظام.</p>
        <?php endif; ?>

    </div>
    <script>
        // دالة JavaScript لتأكيد عملية الحذف
        function confirmDelete(id, username) {
            // التحقق لمنع حذف المستخدم الذي يقوم بتسجيل الدخول حاليًا
            // ملاحظة: قد تحتاج إلى تمرير user_id للمستخدم الحالي من PHP إذا أردت تطبيق هذا القيد
            
            if (confirm("هل أنت متأكد من حذف المستخدم '" + username + "' (ID: " + id + ")؟")) {
                // سيتم إنشاء ملف delete_user.php لمعالجة الحذف
                window.location.href = 'delete_user.php?id=' + id;
            }
        }
    </script>
</body>
</html>