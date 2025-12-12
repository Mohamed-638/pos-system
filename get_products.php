<?php
// get_products.php
// تم التحديث لجعل مسار الصورة يتوافق مع المسار المخزن في DB (images/products/...)

try {
    // 1. الاستعلام المحدّث: جلب جميع البيانات الضرورية
    $branch_id = $_SESSION['branch_id'] ?? null;
    if ($branch_id) {
        $sql = "SELECT product_id, name, price, cost, stock, image_path 
            FROM products 
            WHERE active = 1 AND stock >= 0 AND (branch_id = ? OR branch_id IS NULL) 
            ORDER BY name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT product_id, name, price, cost, stock, image_path 
            FROM products 
            WHERE active = 1 AND stock >= 0 
            ORDER BY name ASC";
        $result = $conn->query($sql);
    }
    
    // تعريف مسار الصورة الافتراضي: (مسار نسبي)
    $default_image_url = 'images/default_product.png'; 

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // تنظيف وإعداد البيانات
            $product_id = htmlspecialchars($row['product_id']);
            $name = htmlspecialchars($row['name']);
            $price = htmlspecialchars($row['price']);
            $cost = htmlspecialchars($row['cost']); 
            $stock = (int)$row['stock'];
            
            // استخدام image_path من قاعدة البيانات إذا كان موجودًا، وإلا استخدام الافتراضي
            $db_image_path = htmlspecialchars($row['image_path']);
            
            // 🚀 التعديل الهام: استخدام المسار المخزن في DB مباشرة للعرض على المتصفح
            $image_url = !empty($db_image_path) ? $db_image_path : $default_image_url;
            
            // تحقق من المخزون
            $disabled = $stock <= 0 ? 'disabled' : '';
            $opacity = $stock <= 0 ? 'opacity: 0.5; pointer-events: none;' : ''; 
            $stock_text = $stock <= 0 ? '(نفد)' : "(متوفر: {$stock})";
            $price_display = number_format((float)$price, 2);

            // إنشاء البطاقة
            echo "<div class='product-card' style='{$opacity}' {$disabled}
                      onclick=\"addToOrder('{$product_id}', '{$name}', '{$price}', '{$cost}')\">
                      
                      <img src='{$image_url}' alt='{$name}' 
                           onerror=\"this.onerror=null;this.src='{$default_image_url}';\">
                      
                      <h4>{$name}</h4>
                      <p>{$price_display} ج.س</p>
                      <span style='font-size: 0.7em; opacity: 0.8;'>{$stock_text}</span>
                  </div>";
        }
    } else {
        echo "<p style='text-align: center; color: red; grid-column: 1 / -1;'>⚠️ لا توجد منتجات متوفرة أو المنتجات غير نشطة.</p>";
    }
    
    if (isset($result) && $result) {
        $result->free();
    }

} catch (Exception $e) {
    echo "<p style='text-align: center; color: red; grid-column: 1 / -1;'>خطأ فني في جلب المنتجات. يرجى مراجعة سجل الأخطاء.</p>";
    error_log("Error fetching products: " . $e->getMessage());
}
?>