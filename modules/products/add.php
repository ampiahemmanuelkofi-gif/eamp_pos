<?php
require_once '../../includes/auth.php';
checkLogin();
if (!hasPermission('manager')) {
    die("Access Denied");
}
require_once '../../includes/header.php';
require_once '../../config/database.php';

$success = '';
$error = '';

$db = new Database();
$conn = $db->getConnection();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = sanitizeInput($_POST['code']);
    $name = sanitizeInput($_POST['name']);
    $price = sanitizeInput($_POST['price']);
    $cost = sanitizeInput($_POST['cost']);
    $qty = sanitizeInput($_POST['qty']);
    $alert = sanitizeInput($_POST['alert']);
    $category = sanitizeInput($_POST['department']);
    $factory_price = sanitizeInput($_POST['factory_price']);
    $qty_in_box = sanitizeInput($_POST['qty_in_box']);
    $box_price = sanitizeInput($_POST['box_price']);
    $box_wholesale = sanitizeInput($_POST['box_wholesale']);
    $box_qty = sanitizeInput($_POST['box_qty']);
    $supplied_by = sanitizeInput($_POST['supplied_by']);
    $batch_no = sanitizeInput($_POST['batch_no']);

    if (empty($code) || empty($name) || empty($price)) {
        $error = "Code, Name and Price are required.";
    } else {
        // Check for duplicate code
        $check = $conn->prepare("SELECT id FROM products WHERE code = ?");
        $check->bind_param("s", $code);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Product code already exists!";
        } else {
            // Updated Query
            $sql = "INSERT INTO products (code, name, price, cost, qty, alert, department, barcode, description, expire, factory_price, qty_in_box, box_price, box_wholesale, box_qty, supplied_by, batch_no) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            // Types: sssssissss + sssssss = 17 chars
            // Simplified types: mostly s/d/i. Let's use 's' for flexible strings/decimals or ensure types.
            // sssssissss sssssss
            $stmt->bind_param(
                "ssssissssssssssss",
                $code,
                $name,
                $price,
                $cost,
                $qty,
                $alert,
                $category,
                $barcode,
                $description,
                $expire,
                $factory_price,
                $qty_in_box,
                $box_price,
                $box_wholesale,
                $box_qty,
                $supplied_by,
                $batch_no
            );

            if ($stmt->execute()) {
                $success = "Product added successfully!";
                // Clear POST?
            } else {
                $error = "Error adding product: " . $conn->error;
            }
        }
    }
}

// Auto-generate Product Code
$max_id_query = $conn->query("SELECT MAX(id) as max_id FROM products");
$next_id = 1;
if ($max_id_query) {
    $row = $max_id_query->fetch_assoc();
    $next_id = ($row['max_id'] ?? 0) + 1;
}
$auto_code = 'P' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Add Product</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="list.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Product Code *</label>
                    <div class="input-group">
                        <input type="text" name="code" id="productCode" class="form-control"
                            value="<?php echo $auto_code; ?>" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="refreshCode()"
                            title="Regenerate Code">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Barcode</label>
                    <div class="input-group">
                        <input type="text" name="barcode" id="barcodeInput" class="form-control">
                        <button type="button" class="btn btn-outline-secondary" onclick="generateBarcode()">
                            <i class="bi bi-magic"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <div class="input-group">
                        <select name="department" class="form-select">
                            <option value="">Select Category</option>
                            <?php
                            $cats = $conn->query("SELECT name FROM categories ORDER BY name ASC");
                            while ($c = $cats->fetch_assoc()) {
                                echo "<option value='" . $c['name'] . "'>" . $c['name'] . "</option>";
                            }
                            ?>
                        </select>
                        <a href="categories.php" class="btn btn-outline-secondary" title="Manage Categories"><i
                                class="bi bi-plus"></i></a>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Cost Price</label>
                    <input type="number" step="0.01" name="cost" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling Price *</label>
                    <input type="number" step="0.01" name="price" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="qty" class="form-control" value="0">
                </div>
                <!-- Box/Wholesale -->
                <div class="col-md-2">
                    <label class="form-label">Factory Price</label>
                    <input type="number" step="0.01" name="factory_price" class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Alert Qty</label>
                    <input type="number" name="alert" class="form-control" value="5">
                </div>
            </div>

            <div class="row mb-3 border-top pt-3">
                <h6 class="text-primary">Box / Wholesale Details (Optional)</h6>
                <div class="col-md-3">
                    <label class="form-label">Items per Box</label>
                    <input type="number" name="qty_in_box" class="form-control" placeholder="e.g. 12">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Box Cost</label>
                    <input type="number" step="0.01" name="box_price" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Box Selling Price</label>
                    <input type="number" step="0.01" name="box_wholesale" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Box Quantity (Alert)</label>
                    <input type="number" name="box_qty" class="form-control" placeholder="Box Stock">
                </div>
            </div>

            <div class="row mb-3 border-top pt-3">
                <h6 class="text-primary">Inventory Logistics</h6>
                <div class="col-md-4">
                    <label class="form-label">Supplier / Source</label>
                    <div class="input-group">
                        <select name="supplied_by" class="form-select">
                            <option value="">Select Supplier</option>
                            <?php
                            $sups = $conn->query("SELECT name FROM suppliers ORDER BY name ASC");
                            while ($s = $sups->fetch_assoc()) {
                                echo "<option value='" . $s['name'] . "'>" . $s['name'] . "</option>";
                            }
                            ?>
                        </select>
                        <a href="suppliers.php" class="btn btn-outline-secondary" title="Manage Suppliers"><i
                                class="bi bi-plus"></i></a>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Batch No.</label>
                    <input type="text" name="batch_no" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expire" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100">Save Product</button>
        </form>
    </div>
</div>

<script>
    function generateBarcode() {
        const timestamp = Date.now().toString(); // 13 digits usually
        // Ensure it's 13 digits for EAN-13 compatibility or just random
        const random = Math.floor(Math.random() * 1000);
        const code = timestamp.substring(0, 10) + random.toString().padStart(3, '0');
        document.getElementById('barcodeInput').value = code;
    }

    function refreshCode() {
        // Simply incrementing on client side might be tricky if multiple users are adding
        // But for this simple app, we can just re-fill with the server-side value on page load
        // Or if we want it dynamic, we could use AJAX, but let's keep it simple for now.
        // If they click refresh, we'll just alert them that it's already pre-filled.
        location.reload();
    }
</script>

<?php require_once '../../includes/footer.php'; ?>