<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/constants.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (empty($query)) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$searchTerm = "%$query%";
// Fetch recent 10 sales matching the customer
$sql = "SELECT invoice, name, mobile, amount, date FROM sales 
        WHERE name LIKE ? OR mobile LIKE ? 
        ORDER BY id DESC LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'invoice' => $row['invoice'],
        'name' => htmlspecialchars($row['name']),
        'mobile' => htmlspecialchars($row['mobile']),
        'amount' => format_currency($row['amount']),
        'date' => $row['date']
    ];
}

echo json_encode(['success' => true, 'results' => $results]);
?>