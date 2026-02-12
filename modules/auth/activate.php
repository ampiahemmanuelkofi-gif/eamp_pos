<?php
// modules/auth/activate.php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/licensing_helper.php';
require_once '../../includes/settings_loader.php';

$message = "";
$system_id = get_system_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_key'])) {
    $license_key = trim($_POST['license_key']);
    $val = validate_license($license_key);

    if ($val['valid']) {
        $db = new Database();
        $conn = $db->getConnection();

        // Save to preferences
        $stmt = $conn->prepare("UPDATE system_preferences SET pref_value = ? WHERE pref_key = 'license_key'");
        $stmt->bind_param("s", $license_key);
        $stmt->execute();

        $stmt_type = $conn->prepare("UPDATE system_preferences SET pref_value = ? WHERE pref_key = 'license_type'");
        $stmt_type->bind_param("s", $val['type']);
        $stmt_type->execute();

        $stmt_exp = $conn->prepare("UPDATE system_preferences SET pref_value = ? WHERE pref_key = 'license_expiry'");
        $stmt_exp->bind_param("s", $val['expiry']);
        $stmt_exp->execute();

        $message = "<div class='alert alert-success'>Software activated successfully! Redirecting...</div>";
        echo "<script>setTimeout(() => { window.location.href = '" . BASE_URL . "/modules/dashboard/index.php'; }, 2000);</script>";
    } else {
        $message = "<div class='alert alert-danger'>Activation Failed: " . $val['error'] . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Activation -
        <?php echo APP_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        .activation-card {
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: white;
            text-align: center;
        }

        .system-id-box {
            background: #f8f9fa;
            border: 1px dashed #dee2e6;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1.2rem;
            color: #0d6efd;
            margin: 20px 0;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="activation-card">
        <div class="logo">
            <?php echo APP_NAME; ?>
        </div>
        <h4 class="mb-4">Product Activation</h4>

        <?php echo $message; ?>

        <p class="text-muted small">This software is locked to this hardware. Please provide the System ID below to your
            distributor to receive your activation key.</p>

        <div class="system-id-box">
            <?php echo $system_id; ?>
        </div>

        <form method="POST">
            <div class="mb-3 text-start">
                <label class="form-label fw-bold">Enter License Key</label>
                <textarea name="license_key" class="form-control" rows="4"
                    placeholder="Paste your activation key here..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Activate Now</button>
        </form>

        <div class="mt-4 text-muted small">
            &copy;
            <?php echo date('Y'); ?>
            <?php echo $SYSTEM_SETTINGS['company_name'] ?? 'Antigravity'; ?>
        </div>
    </div>
</body>

</html>