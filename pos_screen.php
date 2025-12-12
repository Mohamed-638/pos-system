<?php

// pos_screen.php - شاشة الكاشير (نسخة مطورة)

session_start();

require_once 'db_connect.php';

require_once 'license_check.php';

require_once 'config.php';

require_once 'auth_check.php';



// 2. التحقق من الترخيص والصلاحية

check_lite_license($conn);



// تأكد أن الكاشير مسجل الدخول

// allow both cashier and admin to access the POS screen (admins may open it for testing)
check_access(['admin', 'cashier']);

// جلب بيانات الفرع الحالي لعرضها في الهيدر
$branch_name = null;
$branch_address = null;
$branch_phone = null;
$branch_id = $_SESSION['branch_id'] ?? null;
if ($branch_id) {
    $stmt_b = $conn->prepare("SELECT name, address, phone FROM branches WHERE branch_id = ? LIMIT 1");
    if ($stmt_b) {
        $stmt_b->bind_param('i', $branch_id);
        $stmt_b->execute();
        $res_b = $stmt_b->get_result();
        if ($res_b && $row_b = $res_b->fetch_assoc()) {
            $branch_name = $row_b['name'];
            $branch_address = $row_b['address'];
            $branch_phone = $row_b['phone'];
        }
        $stmt_b->close();
    }
}

// fallback: load first branch if none provided
if (!$branch_name) {
    $res_fb = $conn->query("SELECT name, address, phone FROM branches ORDER BY branch_id LIMIT 1");
    if ($res_fb && $rf = $res_fb->fetch_assoc()) {
        $branch_name = $rf['name'] ?? null;
        $branch_address = $rf['address'] ?? null;
        $branch_phone = $rf['phone'] ?? null;
    }
}

?>



<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <title>شاشة الكاشير (نظام نقاط البيع)</title>

    <style>

        /* التنسيقات الأساسية */

        body {

            display: flex;

            flex-direction: column;

            font-family: Tahoma, sans-serif;

            margin: 0;

            min-height: 100vh;

            background-color: #f0f0f0;

        }

       

        /* تنسيقات شريط التنقل العلوي (دون تغيير) */

        .header-bar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 10px 20px;

            background-color: #343a40;

            color: white;

            width: 100%;

            box-sizing: border-box;

        }

        .header-bar .logo-name { display: flex; align-items: center; font-size: 1.5em; font-weight: bold; }

        .header-bar img { height: 30px; margin-left: 10px; border-radius: 4px; }

        .header-bar .user-info { display: flex; align-items: center; gap: 15px; }

        .header-bar .user-info a { color: #ffc107; text-decoration: none; font-weight: bold; transition: color 0.2s; }

        .header-bar .user-info a:hover { color: #fff; }

        .header-bar .logout-btn { color: #dc3545; }



        /* تصميم الأقسام الرئيسية */

        #main-content {

            display: flex;

            flex: 1;

            width: 100%;

            padding-top: 20px;

            box-sizing: border-box;

            overflow: hidden; /* لمنع ظهور شريط تمرير غير مرغوب فيه في الواجهة الرئيسية */

        }

        #product-catalog {

            flex: 3;

            padding: 15px;

            background-color: #fff;

            overflow-y: auto;

        }

        #order-list {

            flex: 2;

            padding: 15px;

            background-color: #f7f7f7;

            border-right: 1px solid #ccc;

            overflow-y: auto;

        }

        #payment-panel {

            flex: 1.5;

            padding: 15px;

            background-color: #eee;

            overflow-y: auto;

        }

       

        /* 🆕 تنسيقات شبكة المنتجات الجديدة */

        #product-grid {

            display: grid;

            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));

            gap: 15px;

            padding-top: 10px;

        }

        .product-card {

            background-color: #007bff;

            color: white;

            border: none;

            padding: 10px; /* تقليل البادينغ ليتسع للصورة والنص */

            cursor: pointer;

            text-align: center;

            border-radius: 8px;

            font-weight: bold;

            transition: background-color 0.2s, transform 0.2s;

            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);

            overflow: hidden;

            height: 140px; /* تحديد ارتفاع ثابت للبطاقة */

        }

        .product-card:hover {

            background-color: #0056b3;

            transform: translateY(-3px);

        }

        .product-card img {

            width: 100%;

            height: 70px; /* تحديد ارتفاع الصورة */

            object-fit: cover; /* لضمان تغطية الصورة للمساحة المخصصة دون تشويه */

            border-radius: 4px;

            margin-bottom: 5px;

            border: 1px solid #fff3cd; /* إضافة حدود خفيفة للصورة */

        }

        .product-card h4 {

            margin: 0;

            font-size: 0.9em;

            overflow: hidden;

            white-space: nowrap;

            text-overflow: ellipsis;

        }

        .product-card p {

            margin: 2px 0 0 0;

            font-size: 1em;

            font-weight: bold;

            color: #ffc107; /* لون السعر المميز */

        }

       

        /* تنسيقات أخرى */

        .total-display { font-size: 2em; margin: 15px 0; font-weight: bold; color: #28a745; text-align: center; }

        .footer-panel { background-color: #ddd; padding: 10px; margin-top: 15px; border-radius: 5px; }

        .reprint-btn { background-color: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9em; }

        .reprint-btn:hover { background-color: #0056b3; }

        /* لتنسيق جدول آخر الطلبات */

        #last-orders-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }

        #last-orders-table th, #last-orders-table td { padding: 6px; text-align: right; border-bottom: 1px solid #ccc; }

    </style>

</head>

<body>



<div class="header-bar">

    <div class="logo-name">
        <img src="<?php echo RESTAURANT_LOGO_URL; ?>" alt="شعار <?php echo RESTAURANT_NAME; ?>">
        <?php echo RESTAURANT_NAME; ?>
        <?php if (!empty($branch_name)): ?>
            <span style="font-size: 0.85em; color: #f8f9fa; margin-right: 10px;">- <?php echo htmlspecialchars($branch_name); ?></span>
        <?php endif; ?>
    </div>



    <div class="user-info">

        <span>مرحباً، <strong><?php echo $_SESSION['full_name']; ?></strong> (<?php echo ($_SESSION['role'] === 'admin' ? 'مدير' : 'كاشير'); ?>)</span>

    </div>



    <div class="user-info">

        <a href="sales_log_user.php">مبيعاتي اليوم</a>

        <?php if ($_SESSION['role'] === 'admin'): ?>

            <a href="dashboard.php">لوحة المدير 🛠️</a>

        <?php endif; ?>

       

        <a href="logout.php" class="logout-btn">تسجيل الخروج 🚪</a>

    </div>

</div>

<div id="main-content">

   

    <div id="product-catalog">

        <h2>🍔 قائمة المنتجات</h2>

        <div id="product-grid">

            <?php

            // تضمين ملف جلب المنتجات (get_products.php)

            include 'get_products.php';

            ?>

        </div>

    </div>



    <div id="order-list">

        <h2>📝 الطلب الحالي</h2>

        <div id="cart-items-display">

            <p style="text-align: center; color: #666;">لم يتم اختيار أي منتج بعد.</p>

        </div>

       

        <hr style="border: none; border-top: 1px solid #ccc;">

       

        <div class="total-display">

            الإجمالي: <span id="total-amount">0.00</span> ج.س

        </div>

    </div>



    <div id="payment-panel">

        <h2>💰 إتمام الدفع</h2>

       

<label for="payment_method" style="display: block; margin-bottom: 5px;">طريقة الدفع:</label>
        <select id="payment_method" style="width: 100%; padding: 10px; margin-bottom: 20px; border-radius: 4px;" required>
            <option value="نقدي">كاش (نقدي)</option>
            <option value="تطبيق">دفع بنكي / تطبيق</option>
        </select>

       

        <button id="finalize-button" onclick="finalizeSale()" style="width: 100%; padding: 20px; background-color: #28a745; color: white; border: none; border-radius: 8px; font-size: 1.2em; cursor: pointer;">

            إتمام الطلب وطباعة الفاتورة

        </button>

        <div id="message-area" style="margin-top: 10px; text-align: center;"></div>



        <div id="last-orders-summary" class="footer-panel">

            <h3>📜 آخر 10 طلبات</h3>

            <div id="last-orders-summary-content">

                <p style="text-align: center;">جاري التحميل...</p>

            </div>

        </div>

    </div>

</div>

   

<div id="receipt-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">

    <div id="receipt-content-wrapper" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 8px;">

        <span class="close-button" onclick="closeModal()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>

       

        <h2>🧾 فاتورة مبيعات</h2>

        <div id="receipt-details">

            <p style="text-align: center;">جاري تحميل تفاصيل الفاتورة...</p>

        </div>

        <hr>

        <button onclick="printReceiptContent()" style="width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; margin-top: 10px;">

            🖨️ طباعة الإيصال

        </button>

    </div>

</div>


<script>
// استدعاء تحميل آخر المبيعات عند تحميل الصفحة لأول مرة
document.addEventListener('DOMContentLoaded', loadLastSales);

// 1. مصفوفة لتخزين المنتجات في الطلب الحالي (السلة الافتراضية)
// 💡 تم تحديث هيكل الكائن ليشمل سعر التكلفة (cost)
let currentOrder = [];


// 2. دالة لإضافة منتج للطلب أو زيادة كميته
function addToOrder(productId, productName, productPrice, productCost) {
    // 💡 ضمان أن الـ ID يتم تخزينه كرقم صحيح
    const id = parseInt(productId);
    const price = parseFloat(productPrice);
    const cost = parseFloat(productCost);

    if (isNaN(price) || isNaN(cost)) {
        console.error("Invalid product price or cost:", productPrice, productCost);
        return;
    }

    // 💡 البحث باستخدام id كرقم صحيح
    const existingItem = currentOrder.find(item => item.id === id);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        currentOrder.push({
            id: id, // استخدام ID المحول إلى رقم
            name: productName,
            price: price,
            cost: cost, 
            quantity: 1
        });
    }
    
    updateOrderDisplay();
}

// 3. دالة لحذف منتج بالكامل أو إنقاص كميته
function updateItemQuantity(productId, action) {
    const id = parseInt(productId); 
    const itemIndex = currentOrder.findIndex(item => item.id === id); 
    
    if (itemIndex > -1) {
        let item = currentOrder[itemIndex];

        if (action === 'increment') {
            item.quantity += 1;
        } else if (action === 'decrement') {
            item.quantity -= 1;
            if (item.quantity <= 0) {
                currentOrder.splice(itemIndex, 1);
            }
        } else if (action === 'remove') {
            currentOrder.splice(itemIndex, 1);
        }
    }
    updateOrderDisplay();
}


// 4. دالة لتحديث عرض قائمة الطلب وحساب الإجمالي (التعديل الرئيسي للتنسيق)
function updateOrderDisplay() {
    let total = 0;
    const displayArea = document.getElementById('cart-items-display');
    displayArea.innerHTML = '';

    if (currentOrder.length === 0) {
        displayArea.innerHTML = '<p style="text-align: center; color: #666;">لم يتم اختيار أي منتج بعد.</p>';
        document.getElementById('total-amount').innerText = '0.00';
        return;
    }
    
    currentOrder.forEach(item => {
        const subtotal = item.quantity * item.price;
        total += subtotal;
        const price_display = subtotal.toFixed(2);
        
        const itemElement = document.createElement('div');
        // 🚨 التعديل على التنسيق: استخدام Flexbox لتنظيم العناصر على صف واحد
        itemElement.style.cssText = 'display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #ccc; padding: 8px 0;';

        itemElement.innerHTML = `
            <div style="flex-grow: 1; text-align: right; padding-right: 10px;">
                <p style="margin: 0;">
                    <span style="font-weight: bold; color: #333;">${item.name}</span>
                </p>
                <p style="margin: 0; font-size: 0.9em; color: #777;">
                    ${item.quantity} x ${item.price.toFixed(2)} ج.س
                </p>
            </div>
            
            <div style="flex-shrink: 0; text-align: left; display: flex; align-items: center;">
                
                <span style="font-weight: bold; color: #007bff; margin-right: 15px; width: 60px; text-align: left;">${price_display} ج.س</span>
                
                <button onclick="updateItemQuantity(${item.id}, 'decrement')" style="padding: 3px 8px; background-color: #f8d7da; border: 1px solid #dc3545; cursor: pointer; margin-left: 5px;">-</button>
                <span style="display: inline-block; width: 30px; text-align: center; font-weight: bold;">${item.quantity}</span>
                <button onclick="updateItemQuantity(${item.id}, 'increment')" style="padding: 3px 8px; background-color: #d4edda; border: 1px solid #28a745; cursor: pointer; margin-right: 5px;">+</button>
                <button onclick="updateItemQuantity(${item.id}, 'remove')" style="padding: 3px 8px; background-color: #ffc107; border: none; cursor: pointer; margin-left: 10px;">حذف</button>
            </div>
        `;
        
        displayArea.appendChild(itemElement);
    });

    // تحديث الإجمالي الكلي
    document.getElementById('total-amount').innerText = total.toFixed(2);
}


// 5. دالة إرسال الطلب النهائي (AJAX) - 🟢 مُستخدمة الآن لـ process_sale.php
function finalizeSale() {
    const totalAmountText = document.getElementById('total-amount').innerText;
    const totalAmount = parseFloat(totalAmountText);
    const paymentMethod = document.getElementById('payment_method').value;
    const messageArea = document.getElementById('message-area');

    if (currentOrder.length === 0 || totalAmount <= 0) {
        messageArea.innerHTML = '<span style="color: red;">الطلب فارغ!</span>';
        return;
    }
    
    // قفل زر الإرسال لمنع النقر المزدوج
    document.getElementById('finalize-button').disabled = true;
    messageArea.innerHTML = 'جاري إتمام الطلب...';

    // استخدام FormData لإرسال البيانات لـ process_sale.php
    const formData = new FormData();
    formData.append('cart_items', JSON.stringify(currentOrder)); 
    formData.append('total_amount', totalAmount.toFixed(2));
    formData.append('payment_method', paymentMethod);
    
    fetch('process_sale.php', {
        method: 'POST',
        body: formData // إرسال البيانات كـ FormData
    })
    .then(response => {
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            return response.json();
        } else {
            return response.text().then(text => {
                throw new Error("لم يتم استقبال JSON. الرد: " + text);
            });
        }
    })
    .then(result => {
        document.getElementById('finalize-button').disabled = false; // فك قفل الزر
        
        if (result.status === 'success') {
            messageArea.innerHTML = `<span style="color: green; font-weight: bold;">${result.message}</span>`;
            
            // إجراءات ما بعد النجاح
            currentOrder = [];
            updateOrderDisplay();
            loadLastSales(); 
            
            // عرض الفاتورة
            loadReceiptDetails(result.sale_id);
            openModal();
            
        } else {
            messageArea.innerHTML = `<span style="color: red;">خطأ: ${result.message}</span>`;
        }
    })
    .catch(error => {
        document.getElementById('finalize-button').disabled = false; // فك قفل الزر
        messageArea.innerHTML = `<span style="color: red;">فشل الاتصال بالخادم أو خطأ غير متوقع. ${error.message}</span>`; 
        console.error('Error:', error);
    });
}


// دالة جديدة: جلب آخر الطلبات وتحديث الواجهة
function loadLastSales() {
    const container = document.getElementById('last-orders-summary-content');
    
    fetch('get_last_sales_ajax.php')
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('فشل تحميل قائمة آخر الطلبات:', error);
            container.innerHTML = '<p style="color: red;">خطأ في تحميل القائمة.</p>';
        });
}


// دوال للتحكم في النافذة المنبثقة
function openModal() { document.getElementById('receipt-modal').style.display = 'block'; }
function closeModal() {
    document.getElementById('receipt-modal').style.display = 'none';
    document.getElementById('receipt-details').innerHTML = '';
}
function printReceiptContent() {
    const receiptDetails = document.getElementById('receipt-details').innerHTML;
    const printWindow = window.open('', '', 'height=600,width=400');
    printWindow.document.write('<html><head><title>إيصال</title>');
    printWindow.document.write('<style>body { font-family: \'Courier New\', monospace; width: 80mm; margin: 0 auto; padding: 10px; font-size: 10pt; }.receipt { width: 100%; border-collapse: collapse; }.receipt th, .receipt td { text-align: right; padding: 3px 0; }.center { text-align: center; }.total-row { border-top: 1px dashed #000; border-bottom: 1px dashed #000; font-weight: bold; }@media print { body { margin: 0; padding: 0; } .no-print { display: none; } }</style>');
    printWindow.document.write('</head><body onload="window.print()">');
    printWindow.document.write(receiptDetails);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    closeModal();
}


function loadReceiptDetails(saleId) {
    const receiptDetails = document.getElementById('receipt-details');
    receiptDetails.innerHTML = '<p style="text-align: center;">جاري تحميل تفاصيل الفاتورة...</p>';
    
    fetch('generate_receipt.php?sale_id=' + saleId)
        .then(response => response.text())
        .then(html => { receiptDetails.innerHTML = html; })
        .catch(error => {
            receiptDetails.innerHTML = '<p style="color: red;">فشل تحميل تفاصيل الفاتورة.</p>';
            console.error('Error:', error);
        });
}

// دالة JavaScript لإعادة الطباعة (مستخدمة من get_last_sales_ajax.php)
function reprintReceipt(saleId) {
    loadReceiptDetails(saleId); 
    openModal(); 
}

// 🟢 الدالة الجديدة: لتأكيد الإلغاء وطلب السبب
function confirmCancellation(saleId, totalAmount) {
    let reason = prompt(`هل أنت متأكد من إلغاء الإيصال رقم ${saleId} بقيمة ${totalAmount} ج.س؟ \n\n يرجى إدخال سبب الإلغاء (إلزامي):`);

    if (reason === null || reason.trim() === "") {
        if (reason !== null) {
            alert("لا يمكن الإلغاء بدون تحديد سبب.");
        }
        return; 
    }
    
    // التوجه إلى معالج الحذف مع تمرير السبب
    const encodedReason = encodeURIComponent(reason.trim());
    window.location.href = `cancel_receipt.php?id=${saleId}&reason=${encodedReason}`;
}

</script>
</body>

</html>