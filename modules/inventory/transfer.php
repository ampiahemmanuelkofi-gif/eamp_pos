<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle Transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_code = sanitizeInput($_POST['product_code']);
    $qty = (int) $_POST['qty'];
    $source_shop = sanitizeInput($_POST['source_shop']);
    $dest_shop = sanitizeInput($_POST['dest_shop']);
    $user = $_SESSION['username'];

    if ($source_shop == $dest_shop) {
        $message = "<div class='alert alert-danger'>Source and Destination shops must be different.</div>";
    } else {
        // Find Product
        $stmt_check = $conn->prepare("SELECT id, name, qty FROM products WHERE code = ?");
        $stmt_check->bind_param("s", $product_code);
        $stmt_check->execute();
        $prod = $stmt_check->get_result()->fetch_assoc();

        if ($prod) {
            $product_id = $prod['id'];
            $success = false;
            $conn->begin_transaction();

            try {
                // 1. DEDUCT from Source
                if ($source_shop == 'Main Warehouse') {
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
                    $stmt_stock->bind_param("is", $product_id, $source_shop);
                    $stmt_stock->execute();
                    $stock = $stmt_stock->get_result()->fetch_assoc();

                    if ($stock && $stock['qty'] >= $qty) {
                        $new_qty = $stock['qty'] - $qty;
                        $update = $conn->prepare("UPDATE product_stock SET qty = ? WHERE product_id = ? AND shop_id = ?");
                        $update->bind_param("iis", $new_qty, $product_id, $source_shop);
                        $update->execute();
                    } else {
                        throw new Exception("Insufficient stock in $source_shop.");
                    }
                }

                // 2. ADD to Destination
                if ($dest_shop == 'Main Warehouse') {
                    // Refresh product data first to avoid race condition overwrite, but simpler here:
                    // Use UPDATE products SET qty = qty + ?
                    $update_dest = $conn->prepare("UPDATE products SET qty = qty + ? WHERE id = ?");
                    $update_dest->bind_param("ii", $qty, $product_id);
                    $update_dest->execute();
                } else {
                    // Update or Insert into product_stock
                    $stmt_dest_stock = $conn->prepare("SELECT id FROM product_stock WHERE product_id = ? AND shop_id = ?");
                    $stmt_dest_stock->bind_param("is", $product_id, $dest_shop);
                    $stmt_dest_stock->execute();
                    if ($stmt_dest_stock->get_result()->num_rows > 0) {
                        $update_dest = $conn->prepare("UPDATE product_stock SET qty = qty + ? WHERE product_id = ? AND shop_id = ?");
                        $update_dest->bind_param("iis", $qty, $product_id, $dest_shop);
                        $update_dest->execute();
                    } else {
                        $insert_dest = $conn->prepare("INSERT INTO product_stock (product_id, shop_id, qty) VALUES (?, ?, ?)");
                        $insert_dest->bind_param("isi", $product_id, $dest_shop, $qty);
                        $insert_dest->execute();
                    }
                }

                // 3. Record Transfer
                $item_details = $prod['name'] . " (" . $product_code . ")";
                $date_simple = date('Y-m-d');
                $insert = $conn->prepare("INSERT INTO items_transfer (shop_sending, shop_receiving, trans_out, item_details, entered_by, date) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->bind_param("ssisss", $source_shop, $dest_shop, $qty, $item_details, $user, $date_simple);
                $insert->execute();

                $conn->commit();
                $message = "<div class='alert alert-success'>Transfer successful!</div>";

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
        <h2>Stock Transfer</h2>
        <p class="text-muted">Transfer items between locations.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">New Transfer</h5>
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
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">From Shop</label>
                            <select name="source_shop" class="form-select">
                                <option value="Main Warehouse">Main Warehouse</option>
                                <?php foreach ($shop_list as $s): ?>
                                    <option value="<?php echo $s['name']; ?>">
                                        <?php echo $s['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Shop</label>
                            <select name="dest_shop" class="form-select">
                                <?php foreach ($shop_list as $s): ?>
                                    <option value="<?php echo $s['name']; ?>">
                                        <?php echo $s['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Process Transfer</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Transfers</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $transfers = $conn->query("SELECT * FROM items_transfer ORDER BY id DESC LIMIT 15");
                        if ($transfers->num_rows > 0) {
                            while ($t = $transfers->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $t['item_details'] . "</td>";
                                echo "<td class='fw-bold'>" . ($t['trans_out'] ?: $t['trans_in']) . "</td>";
                                echo "<td>" . $t['shop_sending'] . "</td>";
                                echo "<td>" . $t['shop_receiving'] . "</td>";
                                echo "<td>" . $t['date'] . "</td>";
                                echo "<td>" . $t['entered_by'] . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>No transfers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>