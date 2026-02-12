<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$db = new Database();
$conn = $db->getConnection();

$shop = $_SESSION['shop_id'] ?? '';

$sql = "SELECT p.id, p.code, p.name, p.price, (p.qty + COALESCE(ps.qty, 0)) as qty, p.department 
        FROM products p 
        LEFT JOIN product_stock ps ON p.id = ps.product_id AND ps.shop_id = ? 
        WHERE (p.qty + COALESCE(ps.qty, 0)) > 0";
$params = [$shop];
$types = "s";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR code LIKE ? OR barcode LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if (!empty($category)) {
    $sql .= " AND department = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY name LIMIT 50";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);
?>