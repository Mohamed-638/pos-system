<?php
// Seed script: create admin user and a sample product & license
// Run: php seeds/seed_admin.php
require_once __DIR__ . '/../db_connect.php';

// Change these values before running locally if desired
$username = 'admin';
$password = 'admin123'; // change after first run
$full_name = 'Admin User';
$role = 'admin';
$is_active = 1;

// Verify DB connection
if ($conn->connect_error) {
    die('DB connection error: ' . $conn->connect_error . PHP_EOL);
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo "User '{$username}' already exists, skipping creation." . PHP_EOL;
} else {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (username, password_hash, role, full_name, is_active) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param('ssssi', $username, $password_hash, $role, $full_name, $is_active);
    if ($insert->execute()) {
        echo "Admin user '{$username}' created successfully with password '{$password}'. Please change it after login." . PHP_EOL;
    } else {
        echo "Failed to create admin: " . $insert->error . PHP_EOL;
    }
    $insert->close();
}
$stmt->close();

// Create default branch if not exists
$branch_name = 'Main Branch';
$stmt_branch = $conn->prepare("SELECT branch_id FROM branches WHERE name = ?");
$stmt_branch->bind_param('s', $branch_name);
$stmt_branch->execute();
$stmt_branch->bind_result($existing_branch_id);
$stmt_branch->store_result();
if ($stmt_branch->num_rows > 0) {
    $stmt_branch->fetch();
    $branch_id = $existing_branch_id;
    echo "Branch '{$branch_name}' exists (ID: {$branch_id})." . PHP_EOL;
} else {
    $stmt_insert_branch = $conn->prepare("INSERT INTO branches (name, address, phone) VALUES (?, ?, ?)");
    $default_address = 'Main Address';
    $default_phone = '0000';
    $stmt_insert_branch->bind_param('sss', $branch_name, $default_address, $default_phone);
    if ($stmt_insert_branch->execute()) {
        $branch_id = $conn->insert_id;
        echo "Branch '{$branch_name}' created (ID: {$branch_id})." . PHP_EOL;
    } else {
        echo "Failed to create branch: " . $stmt_insert_branch->error . PHP_EOL;
    }
    $stmt_insert_branch->close();
}
$stmt_branch->close();

// Update admin user to link to branch
$stmt_update_user_branch = $conn->prepare("UPDATE users SET branch_id = ? WHERE username = ?");
$stmt_update_user_branch->bind_param('is', $branch_id, $username);
$stmt_update_user_branch->execute();
$stmt_update_user_branch->close();

// Create a default license if not present
$license_key = 'LITE-YOUR-CLIENT-CODE-001';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$machine_id = sha1($host . __DIR__);
$stmt2 = $conn->prepare("SELECT license_key FROM licenses WHERE license_key = ?");
$stmt2->bind_param('s', $license_key);
$stmt2->execute();
$stmt2->store_result();
if ($stmt2->num_rows > 0) {
    echo "License '{$license_key}' already exists." . PHP_EOL;
} else {
    $insert2 = $conn->prepare("INSERT INTO licenses (license_key, machine_id) VALUES (?, ?)");
    $insert2->bind_param('ss', $license_key, $machine_id);
    if ($insert2->execute()) {
        echo "License '{$license_key}' created with machine_id: {$machine_id}." . PHP_EOL;
    } else {
        echo "Failed to create license: " . $insert2->error . PHP_EOL;
    }
    $insert2->close();
}
$stmt2->close();

// Optional: Create a sample product if not present
$prod_name = 'Sample Product';
$stmt3 = $conn->prepare("SELECT product_id FROM products WHERE name = ?");
$stmt3->bind_param('s', $prod_name);
$stmt3->execute();
$stmt3->store_result();
if ($stmt3->num_rows > 0) {
    echo "Sample product already exists." . PHP_EOL;
} else {
    $price = 10.00;
    $cost = 6.00;
    $stock = 50;
    $active = 1;
    $image_path = NULL;
    $insert3 = $conn->prepare("INSERT INTO products (name, price, cost, stock, active, image_path, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    // s=string, d=double, d=double, d=double, i=integer, s=string, i=integer
    $insert3->bind_param('sdddisi', $prod_name, $price, $cost, $stock, $active, $image_path, $branch_id);
    if ($insert3->execute()) {
        echo "Sample product created: {$prod_name}." . PHP_EOL;
    } else {
        echo "Failed to create sample product: " . $insert3->error . PHP_EOL;
    }
    $insert3->close();
}
$stmt3->close();

// Create default supplier
$supplier_name = 'Default Supplier';
$stmt_supplier = $conn->prepare("SELECT supplier_id FROM suppliers WHERE name = ?");
$stmt_supplier->bind_param('s', $supplier_name);
$stmt_supplier->execute();
$stmt_supplier->bind_result($existing_supplier_id);
$stmt_supplier->store_result();
if ($stmt_supplier->num_rows > 0) {
    $stmt_supplier->fetch();
    $supplier_id = $existing_supplier_id;
    echo "Supplier '{$supplier_name}' exists (ID: {$supplier_id})." . PHP_EOL;
} else {
    $stmt_insert_supplier = $conn->prepare("INSERT INTO suppliers (name, phone, email, address) VALUES (?, ?, ?, ?)");
    $default_phone = '0000';
    $default_email = 'supplier@example.com';
    $default_address = 'Supplier Address';
    $stmt_insert_supplier->bind_param('ssss', $supplier_name, $default_phone, $default_email, $default_address);
    if ($stmt_insert_supplier->execute()) {
        $supplier_id = $conn->insert_id;
        echo "Supplier '{$supplier_name}' created (ID: {$supplier_id})." . PHP_EOL;
    }
    $stmt_insert_supplier->close();
}
$stmt_supplier->close();

// Create a sample purchase to increase stock for the sample product
$stmt_get_prod = $conn->prepare("SELECT product_id FROM products WHERE name = ? LIMIT 1");
$stmt_get_prod->bind_param('s', $prod_name);
$stmt_get_prod->execute();
$stmt_get_prod->bind_result($product_id);
$stmt_get_prod->store_result();
if ($stmt_get_prod->num_rows > 0) {
    $stmt_get_prod->fetch();
    // Create purchase
    $purchase_total = 50.00;
    $stmt_insert_purchase = $conn->prepare("INSERT INTO purchases (supplier_id, branch_id, total_amount, user_id, status) VALUES (?, ?, ?, ?, 'received')");
    // get admin user id
    $stmt_get_admin = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt_get_admin->bind_param('s', $username);
    $stmt_get_admin->execute();
    $stmt_get_admin->bind_result($admin_user_id);
    $stmt_get_admin->fetch();
    $stmt_get_admin->close();
    $admin_user_id = $admin_user_id ?? 1;
    $stmt_insert_purchase->bind_param('iidi', $supplier_id, $branch_id, $purchase_total, $admin_user_id);
    if ($stmt_insert_purchase->execute()) {
        $purchase_id = $conn->insert_id;
        // Insert purchase item
        $qty = 10;
        $p_price = 5.00;
        $sub_total = $qty * $p_price;
        $stmt_insert_item = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, price, sub_total) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert_item->bind_param('iiddd', $purchase_id, $product_id, $qty, $p_price, $sub_total);
        if ($stmt_insert_item->execute()) {
            // Update product stock
            $stmt_update_stock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
            $stmt_update_stock->bind_param('di', $qty, $product_id);
            $stmt_update_stock->execute();
            $stmt_update_stock->close();
            echo "Sample purchase created and stock updated for product ID: {$product_id}." . PHP_EOL;
        }
        $stmt_insert_item->close();
    }
    $stmt_insert_purchase->close();
}
$stmt_get_prod->close();

$conn->close();
echo "Seeding completed." . PHP_EOL;
?>
