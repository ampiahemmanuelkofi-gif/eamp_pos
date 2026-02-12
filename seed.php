<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Seeding products...\n";

$products = [
    ['code' => 'P001', 'name' => 'Coca Cola 500ml', 'price' => 5.00, 'cost' => 3.50, 'qty' => 100, 'alert' => 10, 'department' => 'Beverages', 'barcode' => '5449000000996'],
    ['code' => 'P002', 'name' => 'Pepsi 500ml', 'price' => 5.00, 'cost' => 3.50, 'qty' => 80, 'alert' => 10, 'department' => 'Beverages', 'barcode' => '4060800106518'],
    ['code' => 'P003', 'name' => 'Lays Salted Chips', 'price' => 12.00, 'cost' => 8.00, 'qty' => 50, 'alert' => 5, 'department' => 'Snacks', 'barcode' => '8901491100564'],
    ['code' => 'P004', 'name' => 'Cadbury Dairy Milk', 'price' => 15.00, 'cost' => 10.00, 'qty' => 200, 'alert' => 20, 'department' => 'Snacks', 'barcode' => '7622210287023'],
    ['code' => 'P005', 'name' => 'Bottled Water 1L', 'price' => 3.00, 'cost' => 1.50, 'qty' => 500, 'alert' => 50, 'department' => 'Beverages', 'barcode' => '1234567890123'],
    ['code' => 'P006', 'name' => 'Laptop Notebook A5', 'price' => 25.00, 'cost' => 15.00, 'qty' => 30, 'alert' => 5, 'department' => 'Stationery', 'barcode' => '9876543210987'],
    ['code' => 'P007', 'name' => 'Ballpoint Pen Blue', 'price' => 2.00, 'cost' => 0.80, 'qty' => 1000, 'alert' => 100, 'department' => 'Stationery', 'barcode' => '1122334455667'],
    ['code' => 'P008', 'name' => 'USB Cable Type-C', 'price' => 35.00, 'cost' => 15.00, 'qty' => 40, 'alert' => 5, 'department' => 'Electronics', 'barcode' => '5566778899001'],
    ['code' => 'P009', 'name' => 'Wireless Mouse', 'price' => 150.00, 'cost' => 80.00, 'qty' => 15, 'alert' => 2, 'department' => 'Electronics', 'barcode' => '6677889900112'],
    ['code' => 'P010', 'name' => 'Generic Bread', 'price' => 10.00, 'cost' => 7.00, 'qty' => 20, 'alert' => 5, 'department' => 'Bakery', 'barcode' => '7788990011223']
];

$stmt = $conn->prepare("INSERT INTO products (code, name, price, cost, qty, alert, department, barcode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($products as $p) {
    // Check if exists
    $check = $conn->query("SELECT id FROM products WHERE code = '" . $p['code'] . "'");
    if ($check->num_rows == 0) {
        $stmt->bind_param("ssddiiss", $p['code'], $p['name'], $p['price'], $p['cost'], $p['qty'], $p['alert'], $p['department'], $p['barcode']);
        if ($stmt->execute()) {
            echo "Added: " . $p['name'] . "\n";
        } else {
            echo "Error adding " . $p['name'] . ": " . $stmt->error . "\n";
        }
    } else {
        echo "Skipped (Exists): " . $p['name'] . "\n";
    }
}

echo "Done.\n";
?>