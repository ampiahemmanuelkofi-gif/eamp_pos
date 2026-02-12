<?php
// includes/audit_helper.php

/**
 * Log a user action to the audit_logs table
 * 
 * @param string $action The action performed (e.g. 'SALE_CREATED', 'PRODUCT_UPDATED')
 * @param string|array $details Description or data related to the action
 */
function logAction($action, $details = '')
{
    global $conn;

    if (!isset($conn)) {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
    }

    $user_id = $_SESSION['username'] ?? 'SYSTEM';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (is_array($details)) {
        $details = json_encode($details);
    }

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user_id, $action, $details, $ip);
    $stmt->execute();
}
?>