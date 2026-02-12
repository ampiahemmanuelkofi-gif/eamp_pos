<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../config/database.php';
// lib/barcode.php not needed, using JS client-side
// We will use JsBarcode cdn for client-side generation to avoid complex PHP deps.

$db = new Database();
$conn = $db->getConnection();

$products = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['products'])) {
    // Generate for selected
    $ids = implode(',', array_map('intval', $_POST['products']));
    $result = $conn->query("SELECT name, code, price, barcode FROM products WHERE id IN ($ids)");
} else {
    // Default show last 20
    $result = $conn->query("SELECT name, code, price, barcode FROM products ORDER BY id DESC LIMIT 20");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Barcodes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        .label {
            border: 1px dashed #ccc;
            width: 200px;
            padding: 10px;
            margin: 10px;
            float: left;
            text-align: center;
            page-break-inside: avoid;
        }

        @media print {
            .no-print {
                display: none;
            }

            .label {
                border: none;
                outline: 1px dotted #000;
            }
        }
    </style>
</head>

<body class="bg-light p-4">

    <div class="no-print mb-4 d-flex justify-content-between">
        <h3>Barcode Generator</h3>
        <div>
            <button onclick="window.print()" class="btn btn-primary">Print Labels</button>
            <a href="list.php" class="btn btn-secondary">Back</a>
        </div>
    </div>

    <div class="bg-white p-4 shadow-sm" style="min-height: 500px;">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="label">
                <div class="fw-bold text-truncate">
                    <?php echo $row['name']; ?>
                </div>
                <div class="small mb-1">$
                    <?php echo number_format($row['price'], 2); ?>
                </div>
                <svg id="barcode-<?php echo $row['code']; ?>"></svg>
                <script>
                    JsBarcode("#barcode-<?php echo $row['code']; ?>", "<?php echo $row['barcode'] ? $row['barcode'] : $row['code']; ?>", {
                        format: "CODE128",
                        width: 1.5,
                        height: 40,
                        displayValue: true
                    });
                </script>
            </div>
        <?php endwhile; ?>
    </div>

</body>

</html>