<?php
session_start();
require_once 'db_connect.php';
require_once 'auth_check.php';
require_once 'config.php';

check_access('admin');

$message = '';
// Load suppliers and products
$suppliers = [];
$products = [];
$res = $conn->query("SELECT supplier_id, name FROM suppliers ORDER BY name ASC");
if ($res) { while ($r = $res->fetch_assoc()) $suppliers[] = $r; $res->free(); }
$res = $conn->query("SELECT product_id, name, price FROM products WHERE active = 1 ORDER BY name ASC");
if ($res) { while ($r = $res->fetch_assoc()) $products[] = $r; $res->free(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    $qty = floatval($_POST['quantity'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $branch_id = $_SESSION['branch_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if ($supplier_id <= 0 || $product_id <= 0 || $qty <= 0) {
        $message = '🚫 بيانات الشراء ناقصة.';
    } else {
        $conn->begin_transaction();
        try {
            $total = $qty * $price;
            $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, branch_id, total_amount, user_id, status) VALUES (?, ?, ?, ?, 'received')");
            $stmt->bind_param('iidi', $supplier_id, $branch_id, $total, $user_id);
            if (!$stmt->execute()) throw new Exception($stmt->error);
            $purchase_id = $conn->insert_id;
            $stmt->close();

            $stmt_item = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, price, sub_total) VALUES (?, ?, ?, ?, ?)");
            $stmt_item->bind_param('iiddd', $purchase_id, $product_id, $qty, $price, $total);
            if (!$stmt_item->execute()) throw new Exception($stmt_item->error);
            $stmt_item->close();

            // update stock
            $stmt_up = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
            $stmt_up->bind_param('di', $qty, $product_id);
            if (!$stmt_up->execute()) throw new Exception($stmt_up->error);
            $stmt_up->close();

            $conn->commit();
            header('Location: view_purchases.php?message=' . urlencode('✅ تمت إضافة الشراء بنجاح.'));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'فشل إضافة الشراء: ' . $e->getMessage();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><title>إضافة عملية شراء</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php require_once 'includes/admin_header.php'; ?>
<div class="container">
<h2>➕ إضافة عملية شراء (استلام المخزون)</h2>
<?php if ($message): ?><div class="message error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<form method="post">
    <label>المورّد</label>
    <select name="supplier_id" required>
        <option value="">اختر مورداً</option>
        <?php foreach ($suppliers as $s): ?>
            <option value="<?php echo $s['supplier_id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <label>المنتج</label>
    <select name="product_id" required>
        <option value="">اختر منتجاً</option>
        <?php foreach ($products as $p): ?>
            <option value="<?php echo $p['product_id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <label>الكمية</label>
    <input type="number" step="0.01" name="quantity" required>
    <label>سعر الشراء للقطعة</label>
    <input type="number" step="0.01" name="price" required>
    <button type="submit">حفظ الشراء</button>
    <p><a href="view_purchases.php">مشاهدة المشتريات</a></p>
</form>
</div>
</body></html>
