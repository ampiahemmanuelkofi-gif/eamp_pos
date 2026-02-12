<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Fetch summary data
$conn = (new Database())->getConnection();
$today = date('Y-m-d');

// Total Sales Today
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM sales WHERE date = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$sales_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Low Stock Items
$low_stock = 0;
// Assuming 'alert' column is the threshold
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE qty <= alert");
if ($result) {
    $low_stock = $result->fetch_assoc()['count'];
}

// Pending Debts (Sales where amt_due > 0 or similar logic. Schema has 'amt_due' in sales table?)
// Looking at schema `sales`: `amt_due` varchar.
$stmt = $conn->prepare("SELECT SUM(amt_due) as total FROM sales WHERE amt_due > 0");
$stmt->execute();
$pending_debts = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// License Duration Calculation
$license_expiry = $SYSTEM_SETTINGS['license_expiry'] ?? 'N/A';
$license_duration = 'Unknown';
if ($license_expiry === 'LIFETIME') {
    $license_duration = 'Lifetime';
} elseif ($license_expiry !== 'N/A') {
    $expiry_ts = strtotime($license_expiry);
    $diff = $expiry_ts - time();
    $days = (int) ceil($diff / (60 * 60 * 24));
    if ($days < 0) {
        $license_duration = "Expired (" . abs($days) . " days ago)";
    } elseif ($days == 0) {
        $license_duration = "Expires Today";
    } else {
        $license_duration = $days . " days remaining";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Dashboard</h2>
        <p class="text-muted">Welcome back,
            <?php echo $_SESSION['username']; ?>
        </p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php if (hasPermission('manager')): ?>
        <!-- Today's Sales -->
        <div class="col-md-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Today's Sales</h6>
                            <h2 class="mt-2 mb-0">
                                <?php echo number_format($sales_today, 2); ?>
                            </h2>
                        </div>
                        <i class="bi bi-cash-coin fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Low Stock -->
    <div class="col-md-4">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Low Stock Items</h6>
                        <h2 class="mt-2 mb-0">
                            <?php echo $low_stock; ?>
                        </h2>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if (hasPermission('manager')): ?>
        <!-- Pending Debts -->
        <div class="col-md-4">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Pending Debts</h6>
                            <h2 class="mt-2 mb-0">
                                <?php echo number_format($pending_debts, 2); ?>
                            </h2>
                        </div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row mb-4">
    <?php if (hasPermission('manager')): ?>
        <!-- Low Stock Table -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Low Stock Alerts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Alert Lvl</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ls_query = $conn->query("SELECT name, qty, alert FROM products WHERE qty <= alert ORDER BY qty ASC LIMIT 5");
                                if ($ls_query->num_rows > 0) {
                                    while ($ls = $ls_query->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . $ls['name'] . "</td>";
                                        echo "<td class='text-danger fw-bold'>" . $ls['qty'] . "</td>";
                                        echo "<td>" . $ls['alert'] . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='3' class='text-center text-muted'>No low stock items</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="../products/list.php" class="small text-decoration-none">View All</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Soon Table -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Expiring Soon (30 Days)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Expiry</th>
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Check items expiring within 30 days
                                $thirty_days = date('Y-m-d', strtotime('+30 days'));
                                $today_date = date('Y-m-d');
                                $exp_query = $conn->query("SELECT name, expire FROM products WHERE expire BETWEEN '$today_date' AND '$thirty_days' ORDER BY expire ASC LIMIT 5");
                                if ($exp_query && $exp_query->num_rows > 0) {
                                    while ($ex = $exp_query->fetch_assoc()) {
                                        $days = (strtotime($ex['expire']) - time()) / (60 * 60 * 24);
                                        echo "<tr>";
                                        echo "<td>" . $ex['name'] . "</td>";
                                        echo "<td>" . $ex['expire'] . "</td>";
                                        echo "<td class='text-danger'>" . ceil($days) . " days</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='3' class='text-center text-muted'>No items expiring soon</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="../sales/pos.php"
                            class="btn btn-outline-primary w-100 p-3 d-flex flex-column align-items-center">
                            <i class="bi bi-cart-plus fs-3 mb-2"></i>
                            New Sale (POS)
                        </a>
                    </div>
                    <?php if (hasPermission('manager')): ?>
                        <div class="col-md-3">
                            <a href="../products/add.php"
                                class="btn btn-outline-success w-100 p-3 d-flex flex-column align-items-center">
                                <i class="bi bi-box-seam fs-3 mb-2"></i>
                                Add Product
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../inventory/stocktake.php"
                                class="btn btn-outline-info w-100 p-3 d-flex flex-column align-items-center">
                                <i class="bi bi-clipboard-check fs-3 mb-2"></i>
                                Stock Take
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../reports/daily.php"
                                class="btn btn-outline-secondary w-100 p-3 d-flex flex-column align-items-center">
                                <i class="bi bi-file-earmark-text fs-3 mb-2"></i>
                                Daily Report
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Transactions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare("SELECT invoice, date, amount, agent FROM sales ORDER BY id DESC LIMIT 5");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $row['invoice'] . "</td>";
                                    echo "<td>" . $row['date'] . "</td>";
                                    echo "<td>" . number_format($row['amount'], 2) . "</td>";
                                    echo "<td>" . $row['agent'] . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>No recent transactions</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">System Status</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Database
                        <span class="badge bg-success rounded-pill">Connected</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Server Time
                        <span>
                            <?php echo date('H:i'); ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        PHP Version
                        <span>
                            <?php echo phpversion(); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>License Type</span>
                            <span
                                class="badge bg-<?php echo (($SYSTEM_SETTINGS['license_type'] ?? 'Trial') == 'Full') ? 'success' : 'warning'; ?> rounded-pill">
                                <?php echo $SYSTEM_SETTINGS['license_type'] ?? 'Trial'; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-muted">Duration</span>
                            <span
                                class="fw-bold <?php echo (strpos($license_duration, 'Expired') !== false) ? 'text-danger' : 'text-primary'; ?>">
                                <?php echo $license_duration; ?>
                            </span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>