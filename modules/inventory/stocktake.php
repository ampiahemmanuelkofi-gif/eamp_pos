<?php
require_once '../../includes/auth.php';
checkLogin();

// Only admin/manager
if ($_SESSION['user_role'] == 'cashier') {
    die("Access Denied");
}

require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';
$shop_selected = $_GET['shop'] ?? 'Main Warehouse';

// Handle Stock Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['product_id'];
    $actual_qty = (int) $_POST['actual_qty'];
    $shop = $_POST['shop_hidden']; // Use hidden field to ensure consistency

    $conn->begin_transaction();
    try {
        if ($shop == 'Main Warehouse') {
            // Update Main Stock
            // Calculate difference for logging using subquery or pre-fetch
            $stmt_old = $conn->prepare("SELECT qty FROM products WHERE id = ?");
            $stmt_old->bind_param("i", $id);
            $stmt_old->execute();
            $old_qty = $stmt_old->get_result()->fetch_assoc()['qty'];

            $update = $conn->prepare("UPDATE products SET qty = ? WHERE id = ?");
            $update->bind_param("ii", $actual_qty, $id);
            $update->execute();
        } else {
            // Update Shop Stock
            // Check if exists first
            $stmt_check = $conn->prepare("SELECT id, qty FROM product_stock WHERE product_id = ? AND shop_id = ?");
            $stmt_check->bind_param("is", $id, $shop);
            $stmt_check->execute();
            $res = $stmt_check->get_result();

            if ($res->num_rows > 0) {
                $old_qty = $res->fetch_assoc()['qty'];
                $update = $conn->prepare("UPDATE product_stock SET qty = ? WHERE product_id = ? AND shop_id = ?");
                $update->bind_param("iis", $actual_qty, $id, $shop);
                $update->execute();
            } else {
                $old_qty = 0;
                $insert = $conn->prepare("INSERT INTO product_stock (product_id, shop_id, qty) VALUES (?, ?, ?)");
                $insert->bind_param("isi", $id, $shop, $actual_qty);
                $insert->execute();
            }
        }

        // Log the stock take adjustment if difference exists
        $diff = $actual_qty - $old_qty;
        if ($diff != 0) {
            $type = ($diff > 0) ? 'Stock Take (Surplus)' : 'Stock Take (Deficit)';
            $reason = "Stock Take Correction";
            $user = $_SESSION['username'];
            // Convert diff to positive for qty field if you want absolute value, but schema usually implies quantity involved.
            // However, stock_adjustments usually tracks "how much changed". 
            // Let's store absolute value in qty and type indicates direction.
            $abs_diff = abs($diff);

            $log = $conn->prepare("INSERT INTO stock_adjustments (product_id, shop_id, qty, type, reason, user_id) VALUES (?, ?, ?, ?, ?, ?)");
            $log->bind_param("isisss", $id, $shop, $abs_diff, $type, $reason, $user);
            $log->execute();
        }

        $conn->commit();
        $message = "Stock updated successfully.";

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error updating stock: " . $e->getMessage();
    }
}

// Fetch Shops
$shops = $conn->query("SELECT * FROM shops");
$shop_list = [];
while ($row = $shops->fetch_assoc()) {
    $shop_list[] = $row;
}

// Search for stock take
$search = $_GET['search'] ?? '';

// Build Query based on Shop
if ($shop_selected == 'Main Warehouse') {
    $sql = "SELECT id, code, name, qty as stock_qty FROM products";
    if ($search) {
        $sql .= " WHERE name LIKE '%$search%' OR code LIKE '%$search%'";
    }
} else {
    // Left join product_stock to get qty for specific shop
    // If null, stock is 0
    $sql = "SELECT p.id, p.code, p.name, COALESCE(ps.qty, 0) as stock_qty 
            FROM products p 
            LEFT JOIN product_stock ps ON p.id = ps.product_id AND ps.shop_id = '$shop_selected'";
    if ($search) {
        $sql .= " WHERE p.name LIKE '%$search%' OR p.code LIKE '%$search%'";
    }
}
$result = $conn->query($sql);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Stock Take</h2>
        <p class="text-muted">Reconcile physical inventory with system records.</p>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="shop" class="form-select onchange-submit">
                    <option value="Main Warehouse" <?php if ($shop_selected == 'Main Warehouse')
                        echo 'selected'; ?>>Main
                        Warehouse</option>
                    <?php foreach ($shop_list as $s): ?>
                        <option value="<?php echo $s['name']; ?>" <?php if ($shop_selected == $s['name'])
                               echo 'selected'; ?>>
                            <?php echo $s['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search product..."
                    value="<?php echo $search; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th width="150">System Qty (<?php echo $shop_selected; ?>)</th>
                    <th width="200">Physical Count / Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['code']; ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo $row['stock_qty']; ?></td>
                        <td>
                            <form method="POST" class="d-flex">
                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="shop_hidden" value="<?php echo $shop_selected; ?>">
                                <input type="number" name="actual_qty" class="form-control form-control-sm me-2"
                                    value="<?php echo $row['stock_qty']; ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>