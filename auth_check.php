<?php
// auth_check.php - التحقق من تسجيل الدخول وتحديد الصلاحيات

// ابدأ الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * دالة التحقق من الصلاحيات والوصول
 * @param string $required_role الدور المطلوب ('admin' أو 'cashier')
 */
/**
 * Check user login & permissions
 * @param string|array $required_roles - 'admin' | 'cashier' or ['admin','cashier']
 * @param int|array|null $branch_ids - optional branch id(s) to ensure the user belongs to a branch
 */
function check_access($required_roles, $branch_ids = null) {
    // 1. التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        // المستخدم غير مسجل الدخول، يتم توجيهه لصفحة الدخول
        header('Location: login.php');
        exit();
    }

    // 2. تحقق من الدور: سلسلة أو مصفوفة مقبولة
    $userRole = $_SESSION['role'] ?? null;
    $allowed = false;
    if (is_array($required_roles)) {
        $allowed = in_array($userRole, $required_roles, true);
    } else {
        $allowed = ($userRole === $required_roles);
    }
    if (!$allowed) {
        // المستخدم ليس مديراً لكنه يحاول الوصول لصفحة المدير
        die("
            <div style='text-align: center; padding: 50px; border: 2px solid red; margin: 50px; background-color: #ffe0e0; font-family: Tahoma, sans-serif;'>
                <h2>🛑 وصول غير مُصَرَّح به</h2>
                <p>صلاحياتك لا تسمح لك بالوصول إلى هذه الصفحة.</p>
                <a href='pos_screen.php'>العودة لشاشة البيع</a>
            </div>
        ");
    }
    // 3. (اختياري) قيود الفروع
    if ($branch_ids !== null) {
        $userBranch = $_SESSION['branch_id'] ?? null;
        if (is_null($userBranch) && isset($_SESSION['user_id'])) {
            // جلب من DB إذا لم تُحفظ الجلسة
            require_once 'db_connect.php';
            $stmt = $conn->prepare('SELECT branch_id FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $userBranch = $row['branch_id'];
                $_SESSION['branch_id'] = $userBranch;
            }
            $stmt->close();
        }
        if (is_array($branch_ids)) {
            if (!in_array($userBranch, $branch_ids, true)) {
                deny_access();
            }
        } else {
            if ($userBranch != $branch_ids) {
                deny_access();
            }
        }
    }
}

function deny_access() {
    die("<div style='text-align: center; padding: 50px; border: 2px solid red; margin: 50px; background-color: #ffe0e0; font-family: Tahoma, sans-serif; border-radius: 8px;'><h2>🛑 وصول غير مُصرَّح به</h2><p>صلاحياتك لا تسمح لك بالوصول إلى هذه الصفحة.</p><a href='pos_screen.php'>العودة لشاشة البيع</a></div>");
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// دالة لتسجيل الخروج
function logout() {
    session_start();
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}
?>