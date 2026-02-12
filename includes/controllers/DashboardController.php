<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth.php';

class DashboardController
{
    public function index()
    {
        checkLogin();

        $conn = (new Database())->getConnection();
        $today = date('Y-m-d');

        // Total Sales Today
        $stmt = $conn->prepare("SELECT SUM(amount) as total FROM sales WHERE date = ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $sales_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

        // Low Stock Items
        $low_stock = 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM products WHERE qty <= alert");
        if ($result) {
            $low_stock = $result->fetch_assoc()['count'];
        }

        // Pending Debts
        $stmt = $conn->prepare("SELECT SUM(amt_due) as total FROM sales WHERE amt_due > 0");
        $stmt->execute();
        $pending_debts = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

        // License Duration
        global $SYSTEM_SETTINGS; // Loaded via header usually, but we need to ensure it's available.
        require_once __DIR__ . '/../settings_loader.php';

        $license_expiry = $SYSTEM_SETTINGS['license_expiry'] ?? 'N/A';
        $license_duration = 'Unknown';
        if ($license_expiry === 'LIFETIME') {
            $license_duration = 'Lifetime';
        } elseif ($license_expiry !== 'N/A') {
            $expiry_ts = strtotime($license_expiry);
            $diff = $expiry_ts - time();
            $days = (int) ceil($diff / (60 * 60 * 24));
            if ($days < 0) {
                $license_duration = "Expired (" . abs($days) . " days ago)";
            } elseif ($days == 0) {
                $license_duration = "Expires Today";
            } else {
                $license_duration = $days . " days remaining";
            }
        }

        // Pass variables to view
        require_once __DIR__ . '/../../modules/dashboard/view_refactored.php';
    }
}
?>