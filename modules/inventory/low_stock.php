<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$shop = $_SESSION['shop_id'] ?? '';

$low_stock_default = (int) ($SYSTEM_SETTINGS['low_stock_alert'] ?? 10);

// Fetch products where stock is below alert level for THIS shop
// If p.alert is 0, use the system global default
$sql = "SELECT p.id, p.code, p.name, 
        CASE WHEN p.alert > 0 THEN p.alert ELSE ? END as effective_alert, 
        p.qty as warehouse_qty, COALESCE(ps.qty, 0) as shop_qty, p.department
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id AND ps.shop_id = ?
        WHERE (COALESCE(ps.qty, 0) <= CASE WHEN p.alert > 0 THEN p.alert ELSE ? END)
        ORDER BY (COALESCE(ps.qty, 0) - CASE WHEN p.alert > 0 THEN p.alert ELSE ? END) ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isii", $low_stock_default, $shop, $low_stock_default, $low_stock_default);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="row mb-4">
    <div class="col-md-9">
        <h2><i class="bi bi-exclamation-triangle text-danger me-2"></i> Low Stock Alerts</h2>
        <p class="text-muted">Items running low at <strong>
                <?php echo htmlspecialchars($shop ?: 'Global'); ?>
            </strong></p>
    </div>
    <div class="col-md-3 text-end">
        <a href="transfer.php" class="btn btn-primary">
            <i class="bi bi-arrow-left-right"></i> Request Transfer
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Code</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th class="text-center">Alert Level</th>
                    <th class="text-center">Shop Stock</th>
                    <th class="text-center">Warehouse Stock</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()):
                        $is_out = $row['shop_qty'] <= 0;
                        ?>
                        <tr>
                            <td><?php echo $row['code']; ?></td>
                            <td><strong><?php echo $row['name']; ?></strong></td>
                            <td><?php echo $row['department']; ?></td>
                            <td class="text-center"><?php echo $row['effective_alert']; ?></td>
                            <td class="text-center fw-bold <?php echo $is_out ? 'text-danger' : 'text-warning'; ?>">
                                <?php echo $row['shop_qty']; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $row['warehouse_qty'] > 0 ? 'bg-info' : 'bg-secondary'; ?>">
                                    <?php echo $row['warehouse_qty']; ?> available
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($is_out): ?>
                                    <span class="badge bg-danger">Empty Local</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Low Local</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle fs-1 d-block mb-3 text-success"></i>
                            All inventory levels are currently sufficient for this shop.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>