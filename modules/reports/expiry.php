<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Filter by days
$days = $_GET['days'] ?? 30;

// Summary
$expired_count = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE expire IS NOT NULL AND STR_TO_DATE(expire, '%Y-%m-%d') < CURDATE()")->fetch_assoc()['cnt'];
$expiring_count = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE expire IS NOT NULL AND STR_TO_DATE(expire, '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY)")->fetch_assoc()['cnt'];

// Already Expired
$expired = $conn->query("SELECT * FROM products WHERE expire IS NOT NULL AND STR_TO_DATE(expire, '%Y-%m-%d') < CURDATE() ORDER BY expire ASC");

// Expiring Soon
$expiring = $conn->query("SELECT * FROM products WHERE expire IS NOT NULL AND STR_TO_DATE(expire, '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY) ORDER BY expire ASC");

// Handle CSV Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="expiry_report_' . $type . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'Qty', 'Expiry Date', 'Days Until/Since']);
    
    $data = $type == 'expired' ? $expired : $expiring;
    while ($row = $data->fetch_assoc()) {
        $exp_date = strtotime($row['expire']);
        $diff = floor(($exp_date - strtotime('today')) / 86400);
        fputcsv($output, [$row['code'], $row['name'], $row['qty'], $row['expire'], $diff]);
    }
    fclose($output);
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Expiry Report</h2>
        <p class="text-muted">Products approaching or past expiry date.</p>
    </div>
    <div class="col-md-6 text-end">
        <form method="GET" class="d-inline">
            <select name="days" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                <option value="7" <?php if($days == 7) echo 'selected'; ?>>Next 7 Days</option>
                <option value="14" <?php if($days == 14) echo 'selected'; ?>>Next 14 Days</option>
                <option value="30" <?php if($days == 30) echo 'selected'; ?>>Next 30 Days</option>
                <option value="60" <?php if($days == 60) echo 'selected'; ?>>Next 60 Days</option>
                <option value="90" <?php if($days == 90) echo 'selected'; ?>>Next 90 Days</option>
            </select>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3><?php echo $expired_count; ?></h3>
                <div>Already Expired</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h3><?php echo $expiring_count; ?></h3>
                <div>Expiring in <?php echo $days; ?> Days</div>
            </div>
        </div>
    </div>
</div>

<!-- Already Expired -->
<div class="card mb-4">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i> Already Expired</h5>
        <a href="?days=<?php echo $days; ?>&export=expired" class="btn btn-sm btn-light">Export CSV</a>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Product</th>
                    <th class="text-end">Qty</th>
                    <th>Expiry Date</th>
                    <th>Days Ago</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expired->num_rows > 0): ?>
                    <?php while ($row = $expired->fetch_assoc()): 
                        $exp_date = strtotime($row['expire']);
                        $days_ago = floor((strtotime('today') - $exp_date) / 86400);
                    ?>
                    <tr class="table-danger">
                        <td><?php echo $row['code']; ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td class="text-end"><?php echo $row['qty']; ?></td>
                        <td><?php echo $row['expire']; ?></td>
                        <td><span class="badge bg-danger"><?php echo $days_ago; ?> days</span></td>
                        <td>
                            <a href="../inventory/adjust.php" class="btn btn-sm btn-outline-danger">Write Off</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted">No expired products.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Expiring Soon -->
<div class="card">
    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock me-2"></i> Expiring in <?php echo $days; ?> Days</h5>
        <a href="?days=<?php echo $days; ?>&export=expiring" class="btn btn-sm btn-dark">Export CSV</a>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Product</th>
                    <th class="text-end">Qty</th>
                    <th>Expiry Date</th>
                    <th>Days Left</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expiring->num_rows > 0): ?>
                    <?php while ($row = $expiring->fetch_assoc()): 
                        $exp_date = strtotime($row['expire']);
                        $days_left = floor(($exp_date - strtotime('today')) / 86400);
                    ?>
                    <tr class="<?php echo $days_left <= 7 ? 'table-warning' : ''; ?>">
                        <td><?php echo $row['code']; ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td class="text-end"><?php echo $row['qty']; ?></td>
                        <td><?php echo $row['expire']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $days_left <= 7 ? 'danger' : 'warning text-dark'; ?>">
                                <?php echo $days_left; ?> days
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted">No products expiring soon.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
