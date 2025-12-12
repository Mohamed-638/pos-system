<?php
// logout.php - معالج تسجيل الخروج
require_once 'auth_check.php'; // تأكد من وجود ملف auth_check.php

// استدعاء دالة تسجيل الخروج لإنهاء الجلسة
logout();

// لا حاجة لأي كود آخر، لأن دالة logout() تحتوي على exit() و header()
?>