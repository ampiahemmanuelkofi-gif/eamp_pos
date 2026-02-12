<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/settings_loader.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$invoice = $_POST['invoice'] ?? null;
$type = $_POST['type'] ?? null;
$contact = $_POST['contact'] ?? null;

if (!$invoice || !$type || !$contact) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Get invoice details
$db = new Database();
$conn = $db->getConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM sales WHERE invoice = ?");
$stmt->bind_param("s", $invoice);
$stmt->execute();
$result = $stmt->get_result();
$sale = $result->fetch_assoc();

if (!$sale) {
    echo json_encode(['success' => false, 'error' => 'Invoice not found']);
    exit;
}

// Generate receipt content
$receipt_content = generateReceiptContent($invoice, $sale, $conn);

if ($type === 'email') {
    $success = sendEmailReceipt($contact, $invoice, $receipt_content);
} elseif ($type === 'sms') {
    $success = sendSmsReceipt($contact, $invoice);
} else {
    $success = false;
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Receipt sent successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send receipt']);
}

function generateReceiptContent($invoice, $sale, $conn) {
    global $SYSTEM_SETTINGS;
    
    $content = "=" . str_repeat("=", 48) . "\n";
    $content .= str_pad($SYSTEM_SETTINGS['company_name'], 50, " ", STR_PAD_BOTH) . "\n";
    $content .= str_repeat("=", 50) . "\n\n";
    
    $content .= "Invoice: " . $invoice . "\n";
    $content .= "Date: " . $sale['date'] . "\n";
    $content .= "Customer: " . $sale['name'] . "\n";
    $content .= "Payment: " . $sale['payment_method'] . "\n";
    $content .= str_repeat("-", 50) . "\n\n";
    
    // Get items
    $stmt = $conn->prepare("SELECT * FROM sales_order WHERE invoice = ?");
    $stmt->bind_param("s", $invoice);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $total = $item['qty'] * $item['price'];
        $content .= $item['name'] . "\n";
        $content .= "  " . $item['qty'] . " x " . number_format($item['price'], 2) . " = " . number_format($total, 2) . "\n";
    }
    
    $content .= str_repeat("-", 50) . "\n";
    $content .= "TOTAL: " . number_format($sale['amount'], 2) . "\n";
    $content .= str_repeat("=", 50) . "\n";
    $content .= "Thank you for your business!\n";
    
    return $content;
}

function sendEmailReceipt($email, $invoice, $receipt_content) {
    global $SYSTEM_SETTINGS;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $to = $email;
    $subject = "Receipt - Invoice " . $invoice . " from " . $SYSTEM_SETTINGS['company_name'];
    $message = "Dear Customer,\n\n";
    $message .= "Thank you for your purchase!\n\n";
    $message .= $receipt_content . "\n\n";
    $message .= "View your invoice online: " . BASE_URL . "/modules/sales/invoice.php?invoice=" . $invoice . "\n\n";
    $message .= "Best regards,\n" . $SYSTEM_SETTINGS['company_name'];
    
    $headers = "From: " . $SYSTEM_SETTINGS['company_details'] . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email
    $result = @mail($to, $subject, $message, $headers);
    
    return $result;
}

function sendSmsReceipt($phone, $invoice) {
    // Check if SMS settings are configured
    global $SYSTEM_SETTINGS;
    
    // Format message
    $message = "Receipt from " . $SYSTEM_SETTINGS['company_name'] . ". Invoice: " . $invoice . ". ";
    $message .= "View: " . BASE_URL . "/modules/sales/invoice.php?invoice=" . $invoice;
    
    // TODO: Integrate with SMS provider (Twilio, AfricasTalking, etc.)
    // For now, just log it
    error_log("SMS to " . $phone . ": " . $message);
    
    // Placeholder - return true if SMS gateway is configured
    // return sendViaGateway($phone, $message);
    
    // For now, simulate success
    return true;
}
?>
