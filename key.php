<?php
// generate_key.php
require_once 'includes/licensing_helper.php';

$generated_key = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $system_id = strtoupper(trim($_POST['system_id']));
    $type = $_POST['type'];
    $duration = $_POST['duration'];
    $client = $_POST['client'];
    $features = $_POST['features'] ?? [];

    if (empty($system_id)) {
        $error = "System ID is required!";
    } else {
        // Calculate Expiry
        $expiry = "";
        if ($duration == 'lifetime') {
            $expiry = "LIFETIME";
        } else {
            $expiry = date('Y-m-d', strtotime("+$duration"));
        }

        $data = [
            'system_id' => $system_id,
            'type' => $type,
            'expiry' => $expiry,
            'client' => $client,
            'features' => $features,
            'gen_date' => date('Y-m-d H:i:s')
        ];

        $generated_key = encrypt_license_data($data);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>License Key Generator (Developer Only)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f7f6;
            padding: 50px;
        }

        .gen-card {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .key-box {
            background: #e9ecef;
            word-break: break-all;
            font-family: monospace;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }
    </style>
</head>

<body>
    <div class="gen-card">
        <h3><i class="bi bi-key"></i> POS License Key Generator</h3>
        <hr>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6 text-start">
                    <label class="form-label fw-bold">System ID (from client)</label>
                    <input type="text" name="system_id" class="form-control" placeholder="ABC123XYZ..." required>
                </div>
                <div class="col-md-6 text-start">
                    <label class="form-label fw-bold">Client Name</label>
                    <input type="text" name="client" class="form-control" placeholder="e.g. John Doe Shop" required>
                </div>

                <div class="col-md-6 text-start">
                    <label class="form-label fw-bold">License Type</label>
                    <select name="type" class="form-select">
                        <option value="Full">Full License</option>
                        <option value="Trial">Trial License</option>
                    </select>
                </div>
                <div class="col-md-6 text-start">
                    <label class="form-label fw-bold">Duration</label>
                    <select name="duration" class="form-select">
                        <option value="1 day">1 Day Trial</option>
                        <option value="7 days">1 Week Trial</option>
                        <option value="1 month">1 Month</option>
                        <option value="3 months">3 Months</option>
                        <option value="6 months">6 Months</option>
                        <option value="1 year">1 Year</option>
                        <option value="lifetime" selected>Lifetime</option>
                    </select>
                </div>

                <div class="col-12 text-start">
                    <label class="form-label fw-bold">Enabled Features</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="features[]" value="sms" checked
                                id="f-sms">
                            <label class="form-check-label" for="f-sms">SMS Module</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="features[]" value="multishop" checked
                                id="f-shop">
                            <label class="form-check-label" for="f-shop">Multi-Shop Support</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="features[]" value="reports" checked
                                id="f-reports">
                            <label class="form-check-label" for="f-reports">Advanced Reports</label>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-4 text-start">
                    <button type="submit" class="btn btn-dark w-100 py-2">Generate License Key</button>
                </div>
            </div>
        </form>

        <?php if ($generated_key): ?>
            <div class="mt-5">
                <label class="form-label fw-bold text-success">Generated License Key:</label>
                <div class="key-box" id="keyContent">
                    <?php echo $generated_key; ?>
                </div>
                <button class="btn btn-outline-success btn-sm mt-2" onclick="copyKey()">Copy to Clipboard</button>
            </div>
            <script>
                function copyKey() {
                    const key = document.getElementById('keyContent').innerText;
                    navigator.clipboard.writeText(key);
                    alert('Key copied!');
                }
            </script>
        <?php endif; ?>
    </div>
</body>

</html>