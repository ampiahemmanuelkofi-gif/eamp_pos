<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Filters
$filter = $_GET['filter'] ?? 'all';
$shop = $_GET['shop'] ?? 'Main Warehouse';

// Summary Stats
$total_products = $conn->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc()['cnt'];
$low_stock = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE qty <= alert AND alert > 0")->fetch_assoc()['cnt'];
$out_of_stock = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE qty = 0")->fetch_assoc()['cnt'];
$total_value = $conn->query("SELECT COALESCE(SUM(CAST(cost AS DECIMAL(10,2)) * qty), 0) as val FROM products")->fetch_assoc()['val'];

// Build Query
if ($shop == 'Main Warehouse') {
    $sql = "SELECT id, code, name, qty, alert, cost, price, expire FROM products";
} else {
    $sql = "SELECT p.id, p.code, p.name, COALESCE(ps.qty, 0) as qty, p.alert, p.cost, p.price, p.expire 
            FROM products p 
            LEFT JOIN product_stock ps ON p.id = ps.product_id AND ps.shop_id = '$shop'";
}

switch ($filter) {
    case 'low':
        $sql .= $shop == 'Main Warehouse'
            ? " WHERE qty <= alert AND alert > 0"
            : " WHERE COALESCE(ps.qty, 0) <= p.alert AND p.alert > 0";
        break;
    case 'out':
        $sql .= $shop == 'Main Warehouse'
            ? " WHERE qty = 0"
            : " WHERE COALESCE(ps.qty, 0) = 0";
        break;
    case 'expiring':
        $sql .= $shop == 'Main Warehouse'
            ? " WHERE expire IS NOT NULL AND STR_TO_DATE(expire, '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            : " WHERE p.expire IS NOT NULL AND STR_TO_DATE(p.expire, '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        break;
}
$sql .= " ORDER BY " . ($shop == 'Main Warehouse' ? "name" : "p.name");
$result = $conn->query($sql);

// Shops for filter
$shops = $conn->query("SELECT * FROM shops");
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Inventory Report</h2>
        <p class="text-muted">Stock levels and alerts.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?filter=<?php echo $filter; ?>&shop=<?php echo $shop; ?>&export=csv" class="btn btn-success">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo $total_products; ?>
                </h3>
                <div>Total Products</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h3>
                    <?php echo $low_stock; ?>
                </h3>
                <div>Low Stock</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo $out_of_stock; ?>
                </h3>
                <div>Out of Stock</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo number_format($total_value, 2); ?>
                </h3>
                <div>Stock Value (Cost)</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="shop" class="form-select">
                    <option value="Main Warehouse" <?php if ($shop == 'Main Warehouse')
                        echo 'selected'; ?>>Main
                        Warehouse</option>
                    <?php while ($s = $shops->fetch_assoc()): ?>
                        <option value="<?php echo $s['name']; ?>" <?php if ($shop == $s['name'])
                               echo 'selected'; ?>>
                            <?php echo $s['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter</label>
                <select name="filter" class="form-select">
                    <option value="all" <?php if ($filter == 'all')
                        echo 'selected'; ?>>All Products</option>
                    <option value="low" <?php if ($filter == 'low')
                        echo 'selected'; ?>>Low Stock</option>
                    <option value="out" <?php if ($filter == 'out')
                        echo 'selected'; ?>>Out of Stock</option>
                    <option value="expiring" <?php if ($filter == 'expiring')
                        echo 'selected'; ?>>Expiring Soon (30 days)
                    </option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>
</div>

<!-- Results Table -->
<div class="card">
    <div class="card-body">
        <table class="table table-striped" id="inventoryTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Product Name</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Alert Level</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">Price</th>
                    <th>Expiry</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()):
                    $qty = $row['qty'];
                    $alert = $row['alert'] ?? 0;
                    $status = 'ok';
                    if ($qty == 0)
                        $status = 'out';
                    elseif ($alert > 0 && $qty <= $alert)
                        $status = 'low';
                    ?>
                    <tr>
                        <td>
                            <?php echo $row['code']; ?>
                        </td>
                        <td>
                            <?php echo $row['name']; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php echo $qty; ?>
                        </td>
                        <td class="text-end text-muted">
                            <?php echo $alert ?: '-'; ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($row['cost'] ?? 0, 2); ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($row['price'] ?? 0, 2); ?>
                        </td>
                        <td>
                            <?php echo $row['expire'] ?: '-'; ?>
                        </td>
                        <td>
                            <?php if ($status == 'out'): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($status == 'low'): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Re-run query for export
    $export_result = $conn->query($sql);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'Qty', 'Alert', 'Cost', 'Price', 'Expiry']);

    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [$row['code'], $row['name'], $row['qty'], $row['alert'], $row['cost'], $row['price'], $row['expire']]);
    }
    fclose($output);
    exit();
}
?>

<?php require_once '../../includes/footer.php'; ?>