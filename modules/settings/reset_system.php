<?php
require_once '../../includes/auth.php';
checkLogin();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    // Verify admin password
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT password FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {

            // Proceed with reset
            $tables_to_truncate = [
                'accounts',
                'accounts1',
                'cash_register',
                'categories',
                'debt_payment_history',
                'distributed_items',
                'expenses',
                'expenses1',
                'fund',
                'fund_payment',
                'fund_transactions',
                'income',
                'invoice',
                'invoice_order',
                'items_transfer',
                'products',
                'products_import',
                'products_received',
                'product_stock',
                'report_daily',
                'sales',
                'sales_order',
                'sms',
                'stock_adjustments',
                'stock_takes',
                'stock_take_items',
                'suppliers',
                'audit_logs'
            ];

            $conn->begin_transaction();
            try {
                // Disable foreign key checks if any (legacy might not have them but good practice)
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");

                foreach ($tables_to_truncate as $table) {
                    $conn->query("TRUNCATE TABLE `$table` ");
                }

                // Reset Shops (Keep Main Shop)
                $conn->query("DELETE FROM shops WHERE id > 1");
                $conn->query("ALTER TABLE shops AUTO_INCREMENT = 2");

                // Reset Bank Accounts (Keep Cash Register)
                $conn->query("DELETE FROM bank_accounts WHERE id > 1");
                $conn->query("ALTER TABLE bank_accounts AUTO_INCREMENT = 2");
                $conn->query("UPDATE bank_accounts SET balance = 0 WHERE id = 1");

                // Reset staff_list (Keep manager if exists or clear except some?)
                // Usually system reset should clear all staff except the admin who is in 'user' table
                $conn->query("TRUNCATE TABLE staff_list");

                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                $conn->commit();

                // Log the reset action if possible before session kill
                if (file_exists('../../includes/audit_helper.php')) {
                    require_once '../../includes/audit_helper.php';
                    logAction('SYSTEM_RESET', 'All transactional and business data cleared for new client.');
                }

                echo json_encode(['success' => true, 'message' => 'System reset successful. Redirecting...']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Reset failed: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect admin password.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Admin user not found.']);
    }
    exit();
}
?>