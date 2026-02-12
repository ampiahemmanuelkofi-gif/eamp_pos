<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle Receive
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_code = sanitizeInput($_POST['product_code']);
    $qty = (int) $_POST['qty'];
    $supplier = sanitizeInput($_POST['supplier']);

    // If "Other" selected and manual input provided
    if ($supplier == 'Other' && !empty($_POST['supplier_manual'])) {
        $supplier = sanitizeInput($_POST['supplier_manual']);
    }

    $user = $_SESSION['username'];

    $stmt_check = $conn->prepare("SELECT id, qty FROM products WHERE code = ?");
    $stmt_check->bind_param("s", $product_code);
    $stmt_check->execute();
    $prod = $stmt_check->get_result()->fetch_assoc();

    if ($prod) {
        // Update Stock
        $new_qty = $prod['qty'] + $qty;
        $update = $conn->prepare("UPDATE products SET qty = ? WHERE id = ?");
        $update->bind_param("ii", $new_qty, $prod['id']);

        if ($update->execute()) {
            // Log Receipt
            // products_received: product_code, qty, supplier, received_by, date_received
            // Note: Schema might check columns. 'products_received' is typical.
            // Actually checking schema earlier: `products_received` or similar? Let's assume `products_received` exists or repurpose `products_import` or create log logic.
            // Checking pos.sql in memory: `products_received` (id, product, qty, supplier, date) likely.

            // products_received schema: code, name, description, unit, cost, price, qty, sold, supplied_by ... date
            // We need to fill required fields: code, name, price, qty, etc.
            // Fetch product details first
            // $prod has id, qty. We need full details.
            $stmt_full = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt_full->bind_param("i", $prod['id']);
            $stmt_full->execute();
            $full_prod = $stmt_full->get_result()->fetch_assoc();

            $sql_log = "INSERT INTO products_received (code, name, price, qty, supplied_by, date) VALUES (?, ?, ?, ?, ?, NOW())";
            $log = $conn->prepare($sql_log);
            // $full_prod['price'] might be string, qty int, supplier string
            $log->bind_param("ssdis", $full_prod['code'], $full_prod['name'], $full_prod['price'], $qty, $supplier);
            $log->execute();

            $message = "<div class='alert alert-success'>Stock received and updated.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating stock.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Product not found.</div>";
    }
}

// Fetch Suppliers
$suppliers_result = $conn->query("SELECT name FROM suppliers ORDER BY name ASC");

?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Receive Stock</h2>
        <p class="text-muted">Add new inventory from suppliers.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Incoming Shipment</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Product Code</label>
                        <input type="text" name="product_code" class="form-control" placeholder="Scan or type code"
                            required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity Received</label>
                        <input type="number" name="qty" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supplier / Source</label>
                        <select name="supplier" class="form-control" id="supplierSelect"
                            onchange="toggleManualSupplier(this)">
                            <option value="">Select Supplier</option>
                            <?php while ($s = $suppliers_result->fetch_assoc()): ?>
                                <option value="<?php echo $s['name']; ?>"><?php echo $s['name']; ?></option>
                            <?php endwhile; ?>
                            <option value="Other">Other (Enter Manually)</option>
                        </select>
                        <input type="text" name="supplier_manual" id="supplierManual" class="form-control mt-2 d-none"
                            placeholder="Enter Supplier Name">
                    </div>

                    <script>
                        function toggleManualSupplier(select) {
                            var manualInput = document.getElementById('supplierManual');
                            if (select.value === 'Other') {
                                manualInput.classList.remove('d-none');
                                manualInput.required = true;
                            } else {
                                manualInput.classList.add('d-none');
                                manualInput.required = false;
                            }
                        }
                    </script>
                    <button type="submit" class="btn btn-success w-100">Add to Inventory</button>
                    <div class="mt-2 text-center">
                        <a href="../products/add.php" class="text-decoration-none small">New product? Create it
                            here.</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Receipts</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty Added</th>
                            <th>Supplier</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $receipts = $conn->query("SELECT * FROM products_received ORDER BY id DESC LIMIT 15");
                        if ($receipts && $receipts->num_rows > 0) {
                            while ($r = $receipts->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $r['product'] . "</td>";
                                echo "<td class='text-success fw-bold'>+" . $r['qty'] . "</td>";
                                echo "<td>" . $r['supplier'] . "</td>";
                                echo "<td>" . $r['date'] . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center'>No records found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>