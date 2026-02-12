<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: list.php");
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$success = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = sanitizeInput($_POST['code']);
    $name = sanitizeInput($_POST['name']);
    $price = sanitizeInput($_POST['price']);
    $cost = sanitizeInput($_POST['cost']);
    $qty = sanitizeInput($_POST['qty']);
    $alert = sanitizeInput($_POST['alert']);
    $category = sanitizeInput($_POST['department']);
    $factory_price = sanitizeInput($_POST['factory_price']);
    $barcode = sanitizeInput($_POST['barcode']);
    $description = sanitizeInput($_POST['description']);
    $expire = sanitizeInput($_POST['expire']);

    if (empty($code) || empty($name) || empty($price)) {
        $error = "Code, Name and Price are required.";
    } else {
        $sql = "UPDATE products SET code=?, name=?, price=?, cost=?, qty=?, alert=?, department=?, barcode=?, description=?, expire=?, factory_price=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssissssssi", $code, $name, $price, $cost, $qty, $alert, $category, $barcode, $description, $expire, $factory_price, $id);

        if ($stmt->execute()) {
            $success = "Product updated successfully!";
        } else {
            $error = "Error updating product: " . $conn->error;
        }
    }
}

// Fetch Current Data
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo "Product not found.";
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Edit Product</h2>
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
                    <input type="text" name="code" class="form-control" value="<?php echo $product['code']; ?>"
                        required>
                </div>
                <!-- ... other fields similar to add.php ... -->
                <div class="col-md-4">
                    <label class="form-label">Barcode</label>
                    <div class="input-group">
                        <input type="text" name="barcode" id="barcodeInput" class="form-control"
                            value="<?php echo $product['barcode']; ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="generateBarcode()">
                            <i class="bi bi-magic"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $product['name']; ?>"
                        required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <input type="text" name="department" class="form-control"
                        value="<?php echo $product['department']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control"
                        value="<?php echo $product['description']; ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Cost Price</label>
                    <input type="number" step="0.01" name="cost" class="form-control"
                        value="<?php echo $product['cost']; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling Price *</label>
                    <input type="number" step="0.01" name="price" class="form-control"
                        value="<?php echo $product['price']; ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="qty" class="form-control" value="<?php echo $product['qty']; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Factory Price</label>
                    <input type="number" step="0.01" name="factory_price" class="form-control"
                        value="<?php echo $product['factory_price']; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Alert Qty</label>
                    <input type="number" name="alert" class="form-control" value="<?php echo $product['alert']; ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expire" class="form-control" value="<?php echo $product['expire']; ?>">
                </div>
            </div>

            <!-- Supplier Field (Missing in original edit.php, adding it now) -->
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
                                $selected = ($product['supplied_by'] == $s['name']) ? 'selected' : '';
                                echo "<option value='" . $s['name'] . "' $selected>" . $s['name'] . "</option>";
                            }
                            ?>
                        </select>
                        <a href="suppliers.php" class="btn btn-outline-secondary" title="Manage Suppliers"><i
                                class="bi bi-plus"></i></a>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Batch No.</label>
                    <input type="text" name="batch_no" class="form-control" value="<?php echo $product['batch_no']; ?>">
                </div>
                <!-- Expire moved here or duplicated? Original had it in row 4, let's keep consistency -->
            </div>

            <button type="submit" class="btn btn-primary">Update Product</button>
        </form>
        <script>
            function generateBarcode() {
                const timestamp = Date.now().toString();
                const random = Math.floor(Math.random() * 1000);
                const code = timestamp.substring(0, 10) + random.toString().padStart(3, '0');
                document.getElementById('barcodeInput').value = code;
            }
        </script>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>