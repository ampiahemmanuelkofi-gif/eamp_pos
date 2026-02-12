<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($product_id) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
}

// Fetch all products for search/select
$products = $conn->query("SELECT id, name, code, barcode FROM products ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="row mb-4 no-print">
    <div class="col-md-8">
        <h2><i class="bi bi-upc-scan me-2"></i> Barcode Generator</h2>
        <p class="text-muted">Generate and print high-quality barcode labels for your products.</p>
    </div>
    <div class="col-md-4 text-end">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print Labels
        </button>
    </div>
</div>

<div class="row no-print mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET">
                    <label class="form-label">Select Product</label>
                    <select name="id" class="form-select select2" onchange="this.form.submit()">
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($product_id == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($product): ?>
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm mb-4 no-print">
            <div class="card-header bg-white">
                <h5 class="mb-0">Label Preview</h5>
            </div>
            <div class="card-body text-center p-5">
                <div class="barcode-container p-3 border d-inline-block bg-white shadow-sm">
                    <div class="small fw-bold mb-1"><?php echo $SYSTEM_SETTINGS['company_name']; ?></div>
                    <div class="mb-1"><?php echo htmlspecialchars($product['name']); ?></div>
                    <svg id="barcode"></svg>
                    <div class="fw-bold mt-1"><?php echo format_currency($product['price']); ?></div>
                </div>
            </div>
        </div>

        <!-- Print Grid -->
        <div class="print-only">
            <div class="row g-3">
                <?php for($i=0; $i<24; $i++): ?>
                <div class="col-4 text-center mb-4">
                    <div class="p-2 border" style="width: 200px; margin: 0 auto;">
                        <div style="font-size: 10px; font-weight: bold;"><?php echo $SYSTEM_SETTINGS['company_name']; ?></div>
                        <div style="font-size: 12px;"><?php echo htmlspecialchars($product['name']); ?></div>
                        <svg class="print-barcode" data-value="<?php echo $product['barcode'] ?: $product['code']; ?>"></svg>
                        <div style="font-size: 14px; font-weight: bold;"><?php echo format_currency($product['price']); ?></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Main Preview
    JsBarcode("#barcode", "<?php echo $product['barcode'] ?: $product['code']; ?>", {
        format: "CODE128",
        lineColor: "#000",
        width: 2,
        height: 80,
        displayValue: true
    });

    // Print Grid
    document.querySelectorAll('.print-barcode').forEach(el => {
        JsBarcode(el, el.getAttribute('data-value'), {
            format: "CODE128",
            width: 1.5,
            height: 40,
            displayValue: true,
            fontSize: 10
        });
    });
});
</script>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .main-content { padding: 0 !important; margin: 0 !important; }
    .print-only { display: block !important; }
    body { background: white !important; }
    .card { border: none !important; box-shadow: none !important; }
}
.print-only { display: none; }
.barcode-container svg { max-width: 100%; height: auto; }
</style>

<?php require_once '../../includes/footer.php'; ?>
