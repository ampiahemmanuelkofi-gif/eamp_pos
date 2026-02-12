<?php
require_once '../../includes/auth.php';
checkLogin();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access Denied";
    exit();
}

require_once __DIR__ . '/../../config/database.php';
$db = new Database();
$conn = $db->getConnection();
$message = '';

// Create preferences table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `system_preferences` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `pref_key` varchar(100) NOT NULL UNIQUE,
    `pref_value` text,
    `pref_type` varchar(20) DEFAULT 'text',
    `pref_category` varchar(50) DEFAULT 'General',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");

// Default preferences
$defaults = [
    ['currency_symbol', 'GH₵', 'select', 'Currency'],
    ['currency_position', 'before', 'select', 'Currency'],
    ['decimal_places', '2', 'number', 'Currency'],
    ['tax_rate', '0', 'number', 'Tax'],
    ['tax_enabled', '0', 'boolean', 'Tax'],
    ['low_stock_alert', '10', 'number', 'Inventory'],
    ['expiry_alert_days', '30', 'number', 'Inventory'],
    ['invoice_prefix', 'INV-', 'text', 'Sales'],
    ['receipt_footer', 'Thank you for your purchase!', 'textarea', 'Sales'],
    ['date_format', 'Y-m-d', 'select', 'Display'],
    ['time_format', 'H:i:s', 'select', 'Display'],
    ['theme_color', 'primary', 'select', 'Display'],
    ['enable_sounds', '1', 'boolean', 'Display'],
    ['auto_logout_minutes', '30', 'number', 'Security'],
    ['require_password_change', '0', 'boolean', 'Security'],
];

// Insert defaults if not exist
foreach ($defaults as $pref) {
    $check = $conn->query("SELECT id FROM system_preferences WHERE pref_key = '{$pref[0]}'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO system_preferences (pref_key, pref_value, pref_type, pref_category) VALUES ('{$pref[0]}', '{$pref[1]}', '{$pref[2]}', '{$pref[3]}')");
    } else {
        $conn->query("UPDATE system_preferences SET pref_type = '{$pref[2]}' WHERE pref_key = '{$pref[0]}'");
    }
}


// Handle Save BEFORE including header.php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Handle Preferences Updates
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'pref_') === 0) {
            $pref_key = str_replace('pref_', '', $key);
            $safe_value = $conn->real_escape_string($value);
            $conn->query("UPDATE system_preferences SET pref_value = '$safe_value' WHERE pref_key = '$pref_key'");
        }
    }

    // 2. Handle Company Info (settings table)
    if (isset($_POST['company_name'])) {
        $name = $conn->real_escape_string($_POST['company_name']);
        $address = $conn->real_escape_string($_POST['company_address']);
        $phone = $conn->real_escape_string($_POST['company_details']);

        $check_settings = $conn->query("SELECT id FROM settings LIMIT 1");
        if ($check_settings->num_rows > 0) {
            $conn->query("UPDATE settings SET company_name='$name', company_address='$address', company_details='$phone' WHERE id=1");
        } else {
            $conn->query("INSERT INTO settings (company_name, company_address, company_details) VALUES ('$name', '$address', '$phone')");
        }
    }

    // 3. Handle Logo Upload
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['company_logo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_name = 'logo_' . time() . '.' . $ext;
            $upload_path = '../../assets/img/logos/' . $new_name;

            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path)) {
                $db_path = 'assets/img/logos/' . $new_name;
                $conn->query("UPDATE settings SET logo='$db_path' WHERE id=1");
            }
        }
    }

    $message = "<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>Settings updated successfully. Changes are now active.</div>";
}

// Include header AFTER processing POST
require_once '../../includes/header.php';
require_once '../../includes/licensing_helper.php';

// Fetch Data
$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
$prefs_result = $conn->query("SELECT * FROM system_preferences ORDER BY pref_category, pref_key");
$preferences = [];
while ($row = $prefs_result->fetch_assoc()) {
    $preferences[$row['pref_category']][] = $row;
}
$license_info = validate_license($SYSTEM_SETTINGS['license_key'] ?? '');
$system_id = get_system_id();
?>

<form method="POST" enctype="multipart/form-data">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-gear-fill me-2"></i> System Settings</h2>
            <p class="text-muted mb-0">Manage your company information and system preferences.</p>
        </div>
        <div class="col-md-6 text-end">
            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                <i class="bi bi-check2-circle me-2"></i>Apply Changes
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white p-0 border-bottom">
            <ul class="nav nav-tabs border-0" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 px-4 border-0" id="company-tab" data-bs-toggle="tab"
                    data-bs-target="#company" type="button" role="tab"><i class="bi bi-building me-2"></i>Company
                    Info</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 px-4 border-0" id="general-tab" data-bs-toggle="tab"
                    data-bs-target="#general" type="button" role="tab"><i class="bi bi-sliders me-2"></i>General
                    Preferences</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 px-4 border-0" id="license-tab" data-bs-toggle="tab"
                    data-bs-target="#license" type="button" role="tab"><i class="bi bi-patch-check me-2"></i>License &
                    Subscription</button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4">
            <div class="tab-content" id="settingsTabsContent">
                <!-- Company Information Tab -->
                <div class="tab-pane fade show active" id="company" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Company Name</label>
                                <input type="text" name="company_name" class="form-control"
                                    value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Business Address</label>
                                <textarea name="company_address" class="form-control"
                                    rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Contact Details (Phone / Email)</label>
                                <input type="text" name="company_details" class="form-control"
                                    value="<?php echo htmlspecialchars($settings['company_details'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light border-0">
                                <div class="card-body text-center">
                                    <label class="form-label fw-bold d-block mb-3">Company Logo</label>
                                    <div class="mb-3">
                                        <?php if (!empty($settings['logo'])): ?>
                                            <img src="<?php echo BASE_URL . '/' . $settings['logo']; ?>" alt="Logo"
                                                class="img-fluid rounded mb-2" style="max-height: 120px;">
                                        <?php else: ?>
                                            <div class="bg-secondary text-white d-flex align-items-center justify-content-center mx-auto rounded"
                                                style="width: 120px; height: 120px;">
                                                <i class="bi bi-image h1 mb-0"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="company_logo" class="form-control form-control-sm">
                                    <small class="text-muted mt-2 d-block">Recommended: Square PNG/JPG</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Preferences Tab -->
                <div class="tab-pane fade" id="general" role="tabpanel">
                    <div class="row">
                        <?php foreach ($preferences as $category => $prefs): ?>
                            <div class="col-md-6 mb-4">
                                <h6 class="text-primary border-bottom pb-2 mb-3"><?php echo $category; ?> Settings</h6>
                                <?php foreach ($prefs as $pref): ?>
                                    <?php if (in_array($pref['pref_key'], ['license_key', 'license_type', 'license_expiry']))
                                        continue; ?>
                                    <div class="mb-3">
                                        <label
                                            class="form-label small fw-bold"><?php echo str_replace('_', ' ', ucfirst($pref['pref_key'])); ?></label>

                                        <?php if ($pref['pref_type'] == 'boolean'): ?>
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="pref_<?php echo $pref['pref_key']; ?>" value="0">
                                                <input class="form-check-input" type="checkbox"
                                                    name="pref_<?php echo $pref['pref_key']; ?>" value="1"
                                                    <?php echo $pref['pref_value'] == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Enabled</label>
                                            </div>

                                        <?php elseif ($pref['pref_type'] == 'textarea'): ?>
                                            <textarea name="pref_<?php echo $pref['pref_key']; ?>" class="form-control form-control-sm"
                                                rows="2"><?php echo htmlspecialchars($pref['pref_value']); ?></textarea>

                                        <?php elseif ($pref['pref_type'] == 'number'): ?>
                                            <input type="number" name="pref_<?php echo $pref['pref_key']; ?>"
                                                class="form-control form-control-sm"
                                                value="<?php echo htmlspecialchars($pref['pref_value']); ?>" step="any">

                                        <?php elseif ($pref['pref_type'] == 'select'): ?>
                                            <select name="pref_<?php echo $pref['pref_key']; ?>" class="form-select form-select-sm">
                                                <?php if ($pref['pref_key'] == 'currency_symbol'): ?>
                                                    <option value="GH₵" <?php echo $pref['pref_value'] == 'GH₵' ? 'selected' : ''; ?>>Ghana
                                                        Cedis (GH₵)</option>
                                                    <option value="GHC" <?php echo $pref['pref_value'] == 'GHC' ? 'selected' : ''; ?>>Ghana
                                                        Cedis (GHC)</option>
                                                    <option value="GHS" <?php echo $pref['pref_value'] == 'GHS' ? 'selected' : ''; ?>>Ghana
                                                        Cedis (GHS)</option>
                                                    <option value="$" <?php echo $pref['pref_value'] == '$' ? 'selected' : ''; ?>>US Dollar
                                                        ($)</option>
                                                    <option value="£" <?php echo $pref['pref_value'] == '£' ? 'selected' : ''; ?>>British Pound
                                                        (£)</option>
                                                <?php elseif ($pref['pref_key'] == 'currency_position'): ?>
                                                    <option value="before" <?php echo $pref['pref_value'] == 'before' ? 'selected' : ''; ?>>
                                                        Before (GH₵100)</option>
                                                    <option value="after" <?php echo $pref['pref_value'] == 'after' ? 'selected' : ''; ?>>After
                                                        (100GH₵)</option>
                                                <?php elseif ($pref['pref_key'] == 'date_format'): ?>
                                                    <option value="Y-m-d" <?php echo $pref['pref_value'] == 'Y-m-d' ? 'selected' : ''; ?>>
                                                        YYYY-MM-DD</option>
                                                    <option value="d/m/Y" <?php echo $pref['pref_value'] == 'd/m/Y' ? 'selected' : ''; ?>>
                                                        DD/MM/YYYY</option>
                                                    <option value="m/d/Y" <?php echo $pref['pref_value'] == 'm/d/Y' ? 'selected' : ''; ?>>
                                                        MM/DD/YYYY</option>
                                                <?php elseif ($pref['pref_key'] == 'time_format'): ?>
                                                    <option value="H:i:s" <?php echo $pref['pref_value'] == 'H:i:s' ? 'selected' : ''; ?>>
                                                        24-hour (14:30:00)</option>
                                                    <option value="h:i A" <?php echo $pref['pref_value'] == 'h:i A' ? 'selected' : ''; ?>>
                                                        12-hour (02:30 PM)</option>
                                                <?php elseif ($pref['pref_key'] == 'theme_color'): ?>
                                                    <option value="#0d6efd" <?php echo $pref['pref_value'] == '#0d6efd' ? 'selected' : ''; ?>>
                                                        Ocean Blue</option>
                                                    <option value="#198754" <?php echo $pref['pref_value'] == '#198754' ? 'selected' : ''; ?>>
                                                        Emerald Green</option>
                                                    <option value="#dc3545" <?php echo $pref['pref_value'] == '#dc3545' ? 'selected' : ''; ?>>
                                                        Imperial Red</option>
                                                    <option value="#6610f2" <?php echo $pref['pref_value'] == '#6610f2' ? 'selected' : ''; ?>>
                                                        Royal Purple</option>
                                                <?php endif; ?>
                                            </select>

                                        <?php else: ?>
                                            <input type="text" name="pref_<?php echo $pref['pref_key']; ?>"
                                                class="form-control form-control-sm"
                                                value="<?php echo htmlspecialchars($pref['pref_value']); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- License Tab -->
                <div class="tab-pane fade" id="license" role="tabpanel">
                    <div class="alert alert-info border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1"><i class="bi bi-shield-lock-fill me-2"></i>Active License</h5>
                                <p class="mb-0 small text-muted">Your system is officially licensed and authorized.</p>
                            </div>
                            <span class="badge bg-primary fs-6"><?php echo $license_info['type'] ?? 'Standard'; ?>
                                Edition</span>
                        </div>
                    </div>

                    <div class="row g-4 mt-1">
                        <div class="col-md-4">
                            <label class="small text-muted d-block text-uppercase fw-bold">Licensed To</label>
                            <div class="h5 mb-0"><?php echo $license_info['client'] ?? 'Valued Customer'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted d-block text-uppercase fw-bold">Status</label>
                            <div class="h5 mb-0 <?php echo $license_info['valid'] ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $license_info['valid'] ? 'Active' : 'Invalid'; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted d-block text-uppercase fw-bold">Expiration</label>
                            <div class="h5 mb-0"><?php echo $license_info['expiry'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="col-12 mt-4 pt-4 border-top">
                            <label class="small text-muted d-block text-uppercase fw-bold mb-3">System Identification (For
                                Support)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-cpu"></i></span>
                                <input type="text" class="form-control bg-light" value="<?php echo $system_id; ?>"
                                    readonly id="sysId">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="navigator.clipboard.writeText('<?php echo $system_id; ?>'); alert('System ID Copied!');">
                                    <i class="bi bi-clipboard me-1"></i> Copy ID
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="mt-5 mb-4">
            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                    <i class="bi bi-check2-circle me-2"></i>Save All Changes
                </button>
            </div>
    </div>
</div>
</form>

<style>
    .nav-tabs .nav-link {
        color: #6c757d;
        font-weight: 500;
        transition: all 0.2s;
    }

    .nav-tabs .nav-link:hover {
        background: #f8f9fa;
        color: var(--primary-color);
    }

    .nav-tabs .nav-link.active {
        color: var(--primary-color);
        border-bottom: 3px solid var(--primary-color) !important;
        background: white;
    }

    .card {
        border-radius: 12px;
        overflow: hidden;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.1);
    }
</style>

<?php require_once '../../includes/footer.php'; ?>