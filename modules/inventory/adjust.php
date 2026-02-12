<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle Adjustment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_code = sanitizeInput($_POST['product_code']);
    $qty = (int) $_POST['qty'];
    $shop = sanitizeInput($_POST['shop']);
    $type = sanitizeInput($_POST['type']); // Damage, Write-off, Correction
    $reason = sanitizeInput($_POST['reason']);
    $user = $_SESSION['username'];

    if ($qty <= 0) {
        $message = "<div class='alert alert-danger'>Quantity must be greater than 0.</div>";
    } else {
        // Find Product
        $stmt_check = $conn->prepare("SELECT id, name, qty FROM products WHERE code = ?");
        $stmt_check->bind_param("s", $product_code);
        $stmt_check->execute();
        $prod = $stmt_check->get_result()->fetch_assoc();

        if ($prod) {
            $product_id = $prod['id'];
            $conn->begin_transaction();

            try {
                // Deduct from Stock
                if ($shop == 'Main Warehouse') {
                    if ($prod['qty'] >= $qty) {
                        $new_qty = $prod['qty'] - $qty;
                        $update = $conn->prepare("UPDATE products SET qty = ? WHERE id = ?");
                        $update->bind_param("ii", $new_qty, $product_id);
                        $update->execute();
                    } else {
                        throw new Exception("Insufficient stock in Main Warehouse.");
                    }
                } else {
                    // Check Shop Stock
                    $stmt_stock = $conn->prepare("SELECT qty FROM product_stock WHERE product_id = ? AND shop_id = ?");
                    $stmt_stock->bind_param("is", $product_id, $shop);
                    $stmt_stock->execute();
                    $stock = $stmt_stock->get_result()->fetch_assoc();

                    if ($stock && $stock['qty'] >= $qty) {
                        $new_qty = $stock['qty'] - $qty;
                        $update = $conn->prepare("UPDATE product_stock SET qty = ? WHERE product_id = ? AND shop_id = ?");
                        $update->bind_param("iis", $new_qty, $product_id, $shop);
                        $update->execute();
                    } else {
                        throw new Exception("Insufficient stock in $shop.");
                    }
                }

                // Record Adjustment
                $insert = $conn->prepare("INSERT INTO stock_adjustments (product_id, shop_id, qty, type, reason, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->bind_param("isisss", $product_id, $shop, $qty, $type, $reason, $user);
                $insert->execute();

                $conn->commit();
                $message = "<div class='alert alert-success'>Adjustment recorded successfully. Stock deducted.</div>";

            } catch (Exception $e) {
                $conn->rollback();
                $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Product not found.</div>";
        }
    }
}

// Fetch Shops
$shops = $conn->query("SELECT * FROM shops");
$shop_list = [];
while ($row = $shops->fetch_assoc()) {
    $shop_list[] = $row;
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Stock Adjustments</h2>
        <p class="text-muted">Record damages, write-offs, or corrections.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">New Adjustment</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Product Code</label>
                        <input type="text" name="product_code" class="form-control" placeholder="Scan or type code"
                            required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="qty" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="Damage">Damage</option>
                                <option value="Write-off">Write-off</option>
                                <option value="Correction">Correction (Deduct)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shop / Location</label>
                        <select name="shop" class="form-select" required>
                            <option value="Main Warehouse">Main Warehouse</option>
                            <?php foreach ($shop_list as $s): ?>
                                <option value="<?php echo $s['name']; ?>">
                                    <?php echo $s['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="3" required
                            placeholder="e.g. Broken during transport"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Record Adjustment</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Adjustments</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Type</th>
                            <th>Shop</th>
                            <th>Reason</th>
                            <th>By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $adj = $conn->query("SELECT sa.*, p.name as product_name FROM stock_adjustments sa LEFT JOIN products p ON sa.product_id = p.id ORDER BY sa.id DESC LIMIT 15");
                        if ($adj->num_rows > 0) {
                            while ($row = $adj->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $row['product_name'] . "</td>";
                                echo "<td class='text-danger fw-bold'>-" . $row['qty'] . "</td>";
                                echo "<td>" . $row['type'] . "</td>";
                                echo "<td>" . $row['shop_id'] . "</td>";
                                echo "<td>" . $row['reason'] . "</td>";
                                echo "<td>" . $row['user_id'] . "</td>";
                                echo "<td>" . $row['date_created'] . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center'>No adjustments found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>