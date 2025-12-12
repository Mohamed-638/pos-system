<?php
// license_check.php - التحقق من الترخيص ومنع النسخ غير المصرح به (نسخة مُحدَّثة)

// ⚠️ تأكد من أن هذين الملفين موجودين في نفس المجلد 
require_once 'db_connect.php';
require_once 'config.php';

// *****************************************************************
// دالة لتوليد الهوية الفريدة للجهاز (Machine ID) - محسّنة ضد تغيير مسار المجلد
// *****************************************************************
function generate_machine_id() {
    // نستخدم الآن __DIR__ (المسار المطلق للمجلد الحالي) لضمان تغيير الـ ID عند تغيير اسم المجلد.
    $path_info = __DIR__; 
    
    $id_string = 
        $_SERVER['HTTP_HOST'] .          // اسم المضيف (localhost أو IP)
        $path_info .                     // 👈 المسار المطلق للمجلد الحالي (المُعدّل)
        $_SERVER['SERVER_SOFTWARE'];     // نوع الخادم (Apache/XAMPP)
    
    // استخدام SHA1 لتوليد هاش ثابت وقصير
    return sha1($id_string);
}

// *****************************************************************
// دالة التحقق من الترخيص
// *****************************************************************
function check_lite_license($conn) {
    // المفتاح المثبت حالياً في ملف config.php
    $key = INSTALLED_LICENSE_KEY;
    $current_machine_id = generate_machine_id();
    
    // جلب المفتاح المسجل من قاعدة البيانات
    // نستخدم die() هنا لتجنب عرض أخطاء PHP للمستخدم النهائي في حالة وجود مشكلة في الاتصال
    $stmt = $conn->prepare("SELECT machine_id FROM licenses WHERE license_key = ?");
    
    if ($stmt === false) {
        // يمكنك تعديل هذا السلوك ليناسب بيئتك، لكن سنسمح بالتشغيل لتجنب تعطل النظام بالكامل
        return true; 
    }
    
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // الحالة 1: المفتاح غير مسجل في قاعدة البيانات (تفعيل أولي)
        die("
            <div style='text-align: center; padding: 50px; border: 2px solid #007bff; margin: 50px auto; max-width: 600px; background-color: #e3f2fd; font-family: Tahoma, sans-serif; border-radius: 8px;'>
                <h2 style='color: #007bff;'>🛑 الترخيص غير مُفعَّل</h2>
                <p>هذا النظام يحتاج إلى تفعيل لمرة واحدة.</p>
                <p><strong>يرجى إرسال البيانات التالية إلى مسؤول التفعيل:</strong></p>
                <div style='text-align: right; direction: ltr; margin: 20px;'>
                    <p style='background-color: #fff; padding: 10px; border: 1px dashed #ccc; border-radius: 4px;'>
                        <strong>مفتاح الرخصة:</strong> <code style='font-size: 1.1em;'>". $key ."</code>
                    </p>
                    <p style='background-color: #fff; padding: 10px; border: 1px dashed #ccc; border-radius: 4px;'>
                        <strong>كود هوية الجهاز:</strong> <code style='font-size: 1.1em;'>". $current_machine_id ."</code>
                    </p>
                </div>
                <p style='color: #dc3545;'>لن يعمل النظام حتى يتم تسجيل الكود في خادم الترخيص.</p>
            </div>
        ");
    }

    $data = $result->fetch_assoc();
    $registered_id = $data['machine_id'];
    
    if ($current_machine_id !== $registered_id) {
        // الحالة 2: المفتاح صحيح لكن الهوية مختلفة (تم النسخ إلى جهاز آخر)
        die("
            <div style='text-align: center; padding: 50px; border: 2px solid red; margin: 50px auto; max-width: 600px; background-color: #ffe0e0; font-family: Tahoma, sans-serif; border-radius: 8px;'>
                <h2 style='color: #dc3545;'>🚫 خطأ الترخيص: تم اكتشاف محاولة تشغيل النظام على جهاز غير مُصرّح به.</h2>
                <p><strong>الهوية المسجلة لا تطابق الهوية الحالية.</strong></p>
                <p>الرجاء العودة إلى الجهاز الأصلي أو التواصل معنا للحصول على رخصة إضافية.</p>
            </div>
        ");
    }
    
    // الحالة 3: كل شيء سليم - السماح بالتشغيل
    return true; 
}
?>