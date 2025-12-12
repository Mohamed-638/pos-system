<?php
// config.php - إعدادات المشروع ومفتاح الترخيص والإعدادات العامة

// --- إعدادات الترخيص (Lite Version) ---
if (!defined('INSTALLED_LICENSE_KEY')) {
    define('INSTALLED_LICENSE_KEY', 'LITE-YOUR-CLIENT-CODE-001'); // استخدم المفتاح الفعلي هنا
}

// --- إعدادات المطعم/النظام (تستخدم في POS والفواتير) ---

// اسم المنشأة
if (!defined('RESTAURANT_NAME')) {
    define('RESTAURANT_NAME', 'كافيتريا '); 
}

// مسار الشعار (يستخدم في الواجهة الرئيسية)
if (!defined('RESTAURANT_LOGO_URL')) {
    define('RESTAURANT_LOGO_URL', 'images/logo.jpg'); 
}

// عنوان المنشأة (مطلوب في الفاتورة الآن)
if (!defined('RESTAURANT_ADDRESS')) {
    define('RESTAURANT_ADDRESS', 'الخرطوم، شارع القصر، مبنى 101');
}

// رقم الهاتف
if (!defined('RESTAURANT_PHONE')) {
    define('RESTAURANT_PHONE', '0555-123456'); 
}

// رسالة تذييل الفاتورة
if (!defined('RESTAURANT_FOOTER_MESSAGE')) {
    define('RESTAURANT_FOOTER_MESSAGE', 'نتطلع لخدمتكم مجدداً! شكراً لزيارتكم.');
}

// ... يمكنك إضافة المزيد من الإعدادات هنا لاحقاً (مثل معدل الضريبة، إلخ)

?>