<?php
require_once '../../includes/auth.php';
checkLogin();
if (!hasPermission('manager')) {
    die("Access Denied");
}
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Stock Update Logic could go here (e.g. adjust stock manually)

$sql = "SELECT * FROM products ORDER BY qty ASC";
$result = $conn->query($sql);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Inventory Management</h2>
        <p class="text-muted">Monitor and track stock levels.</p>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Current Stock Levels</h5>
        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped custom-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-center">Alert Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php 
                                $status_class = '';
                                $status_text = 'OK';
                                if ($row['qty'] <= 0) {
                                    $status_class = 'bg-danger text-white';
                                    $status_text = 'Out of Stock';
                                } elseif ($row['qty'] <= $row['alert']) {
                                    $status_class = 'bg-warning text-dark';
                                    $status_text = 'Low Stock';
                                }
                            ?>
                            <tr>
                                <td><?php echo $row['code']; ?></td>
                                <td><?php echo $row['name']; ?></td>
                                <td><?php echo $row['department']; ?></td>
                                <td class="text-center fw-bold <?php echo ($status_text != 'OK') ? 'text-danger' : ''; ?>"><?php echo $row['qty']; ?></td>
                                <td class="text-center"><?php echo $row['alert']; ?></td>
                                <td class="text-center">
                                    <?php if($status_text != 'OK'): ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
