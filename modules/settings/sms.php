<?php
require_once '../../includes/auth.php';
checkLogin();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access Denied";
    exit();
}

require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Create SMS settings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `sms_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `provider` varchar(50) DEFAULT 'twilio',
    `api_key` varchar(255) DEFAULT NULL,
    `api_secret` varchar(255) DEFAULT NULL,
    `sender_id` varchar(50) DEFAULT NULL,
    `enabled` tinyint(1) DEFAULT 0,
    `sale_notification` tinyint(1) DEFAULT 0,
    `debt_reminder` tinyint(1) DEFAULT 0,
    `low_stock_alert` tinyint(1) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");

// Insert default if not exists
$check = $conn->query("SELECT id FROM sms_settings LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO sms_settings (provider, enabled) VALUES ('twilio', 0)");
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'save';
    
    if ($action == 'save') {
        $provider = sanitizeInput($_POST['provider']);
        $api_key = sanitizeInput($_POST['api_key']);
        $api_secret = sanitizeInput($_POST['api_secret']);
        $sender_id = sanitizeInput($_POST['sender_id']);
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sale_notification = isset($_POST['sale_notification']) ? 1 : 0;
        $debt_reminder = isset($_POST['debt_reminder']) ? 1 : 0;
        $low_stock_alert = isset($_POST['low_stock_alert']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE sms_settings SET provider = ?, api_key = ?, api_secret = ?, sender_id = ?, enabled = ?, sale_notification = ?, debt_reminder = ?, low_stock_alert = ? WHERE id = 1");
        $stmt->bind_param("ssssiiii", $provider, $api_key, $api_secret, $sender_id, $enabled, $sale_notification, $debt_reminder, $low_stock_alert);
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>SMS settings saved successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error saving settings.</div>";
        }
    } elseif ($action == 'test') {
        // Test SMS (simulation)
        $phone = sanitizeInput($_POST['test_phone']);
        $message = "<div class='alert alert-info'><i class='bi bi-send me-2'></i>Test SMS would be sent to: <strong>$phone</strong><br><small>Note: Actual SMS sending requires API integration.</small></div>";
    }
}

// Fetch Settings
$settings = $conn->query("SELECT * FROM sms_settings WHERE id = 1")->fetch_assoc();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="bi bi-chat-dots me-2"></i> SMS Settings</h2>
        <p class="text-muted">Configure SMS notifications and alerts.</p>
    </div>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-8">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            
            <!-- Provider Settings -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">SMS Provider Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Enable SMS</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="enabled" 
                                <?php echo $settings['enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">SMS Notifications Active</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">SMS Provider</label>
                        <select name="provider" class="form-select">
                            <option value="twilio" <?php echo $settings['provider'] == 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                            <option value="africas_talking" <?php echo $settings['provider'] == 'africas_talking' ? 'selected' : ''; ?>>Africa's Talking</option>
                            <option value="nexmo" <?php echo $settings['provider'] == 'nexmo' ? 'selected' : ''; ?>>Vonage (Nexmo)</option>
                            <option value="termii" <?php echo $settings['provider'] == 'termii' ? 'selected' : ''; ?>>Termii</option>
                            <option value="custom" <?php echo $settings['provider'] == 'custom' ? 'selected' : ''; ?>>Custom API</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <input type="text" name="api_key" class="form-control" 
                            value="<?php echo $settings['api_key']; ?>" placeholder="Enter API Key">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">API Secret / Auth Token</label>
                        <input type="password" name="api_secret" class="form-control" 
                            value="<?php echo $settings['api_secret']; ?>" placeholder="Enter API Secret">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sender ID / Phone Number</label>
                        <input type="text" name="sender_id" class="form-control" 
                            value="<?php echo $settings['sender_id']; ?>" placeholder="e.g. EAMPOS or +1234567890">
                        <small class="text-muted">This appears as the sender name in SMS messages.</small>
                    </div>
                </div>
            </div>
            
            <!-- Notification Settings -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Notification Triggers</h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="sale_notification" 
                            <?php echo $settings['sale_notification'] ? 'checked' : ''; ?>>
                        <label class="form-check-label">
                            <strong>Sale Notification</strong>
                            <small class="d-block text-muted">Send SMS to customer after purchase</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="debt_reminder" 
                            <?php echo $settings['debt_reminder'] ? 'checked' : ''; ?>>
                        <label class="form-check-label">
                            <strong>Debt Reminder</strong>
                            <small class="d-block text-muted">Send SMS reminders for outstanding payments</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="low_stock_alert" 
                            <?php echo $settings['low_stock_alert'] ? 'checked' : ''; ?>>
                        <label class="form-check-label">
                            <strong>Low Stock Alert</strong>
                            <small class="d-block text-muted">Send SMS to admin when stock is low</small>
                        </label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Save Settings
            </button>
        </form>
    </div>
    
    <div class="col-md-4">
        <!-- Test SMS -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-send me-2"></i> Test SMS</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="test">
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="test_phone" class="form-control" placeholder="+1234567890" required>
                    </div>
                    <button type="submit" class="btn btn-info w-100" <?php echo !$settings['enabled'] ? 'disabled' : ''; ?>>
                        Send Test SMS
                    </button>
                </form>
                <?php if (!$settings['enabled']): ?>
                <small class="text-muted mt-2 d-block">Enable SMS to send test message.</small>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Usage Info -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Setup Guide</h5>
            </div>
            <div class="card-body">
                <ol class="mb-0 ps-3">
                    <li>Create an account with your SMS provider</li>
                    <li>Get your API credentials</li>
                    <li>Enter credentials above</li>
                    <li>Configure notification triggers</li>
                    <li>Send a test message</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
