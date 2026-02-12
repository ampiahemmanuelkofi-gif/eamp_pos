<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/settings_loader.php';
require_once '../../includes/audit_helper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'error' => 'No items in cart']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$items = $input['items'];
$discount = $input['discount'] ?? 0;
// Recalculate total to be safe
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['qty'];
}
$total = $subtotal - $discount;

// Generate Invoice Number
$prefix = $SYSTEM_SETTINGS['invoice_prefix'] ?? 'INV-';
$invoice = $prefix . strtoupper(uniqid());
$date = date('Y-m-d'); // Schema uses varchar for date in some places, let's use standard format
$agent = $_SESSION['username'];

// Start Transaction
$conn->begin_transaction();

try {
    // 1. Insert into Sales (Summary)
    $customer_name = $input['customer_name'] ?? 'Walk-in Customer';
    $payment_method = $input['payment_method'] ?? 'Cash';
    $payment_ref = $input['payment_ref'] ?? '';

    // Amount Paid Logic
    $amount_paid = isset($input['amount_paid']) ? (float) $input['amount_paid'] : $total;

    // Ensure amount_paid is not negative
    if ($amount_paid < 0)
        $amount_paid = 0;

    // Calculate Tax on Backend
    $tax_rate = (float) ($SYSTEM_SETTINGS['tax_rate'] ?? 0);
    $tax_enabled = (int) ($SYSTEM_SETTINGS['tax_enabled'] ?? 0);
    $taxable_amount = max(0, $total - (float) ($input['discount'] ?? 0));
    $tax_amount = ($tax_enabled == 1) ? ($taxable_amount * ($tax_rate / 100)) : 0;

    // Recalculate Total
    $total = $taxable_amount + $tax_amount;

    // Calculate Due
    $amt_due = max(0, $total - $amount_paid);

    // Update Query to include shop, mobile, address, payment_method, tax_amount, and momo_ref
    $shop = $_SESSION['shop_id'] ?? 'Main Shop';
    $customer_contact = $input['customer_contact'] ?? '';
    $customer_address = '';

    $stmt = $conn->prepare("INSERT INTO sales (invoice, amount, tax_amount, amt_due, name, address, mobile, date, agent, shop, payment_method, momo_ref) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdddssssssss", $invoice, $total, $tax_amount, $amt_due, $customer_name, $customer_address, $customer_contact, $date, $agent, $shop, $payment_method, $payment_ref);

    if (!$stmt->execute()) {
        throw new Exception("Sales insert failed: " . $stmt->error);
    }

    logAction('SALE_CREATED', "Invoice: $invoice, Total: $total");

    // 2. Process Items
    $stmt_item = $conn->prepare("INSERT INTO sales_order (invoice, code, qty, amount, name, price, factory_price, date_sold, agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_update = $conn->prepare("UPDATE products SET qty = qty - ?, sold = sold + ? WHERE id = ?");

    $total_cogs = 0;
    foreach ($items as $item) {
        // ... (existing item processing) ...
        // [Existing code from line 96-105 will be here, let's just make sure we capture COGS]

        // Lookup product for cost
        $p_query = $conn->prepare("SELECT p.code, p.price, p.factory_price, p.qty as warehouse_qty, COALESCE(ps.qty, 0) as shop_qty FROM products p LEFT JOIN product_stock ps ON p.id = ps.product_id AND ps.shop_id = ? WHERE p.id = ?");
        $p_query->bind_param("si", $shop, $item['id']);
        $p_query->execute();
        $product = $p_query->get_result()->fetch_assoc();

        $code = $product['code'];
        $price = $product['price'];
        $factory_price = $product['factory_price'] ?? 0;
        $total_cogs += ($factory_price * $item['qty']);

        // Insert / Update logic ...
        $item_real_total = $price * $item['qty'];
        $stmt_item->bind_param("ssidsisss", $invoice, $code, $item['qty'], $item_real_total, $item['name'], $price, $factory_price, $date, $agent);
        $stmt_item->execute();

        // Stock Updates...
        $qty_to_deduct = $item['qty'];
        $deduct_from_shop = min($qty_to_deduct, (int) $product['shop_qty']);
        if ($deduct_from_shop > 0) {
            $stmt_shop = $conn->prepare("UPDATE product_stock SET qty = qty - ? WHERE product_id = ? AND shop_id = ?");
            $stmt_shop->bind_param("iis", $deduct_from_shop, $item['id'], $shop);
            $stmt_shop->execute();
            $qty_to_deduct -= $deduct_from_shop;
        }
        if ($qty_to_deduct > 0) {
            $conn->query("UPDATE products SET qty = qty - $qty_to_deduct WHERE id = {$item['id']}");
        }
        $conn->query("UPDATE products SET sold = sold + {$item['qty']} WHERE id = {$item['id']}");
    }

    $profit = ($total - $tax_amount) - $total_cogs;
    $conn->commit();
    echo json_encode(['success' => true, 'invoice' => $invoice, 'profit' => $profit]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>