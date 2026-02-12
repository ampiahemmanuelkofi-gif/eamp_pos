<?php
require_once '../../includes/auth.php';
checkLogin();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../dashboard/index.php");
    exit();
}

require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$today = date('Y-m-d');
$month_start = date('Y-m-01');

// Overall Stats
$total_shops = $conn->query("SELECT COUNT(*) as cnt FROM shops")->fetch_assoc()['cnt'];
$total_staff = $conn->query("SELECT COUNT(*) as cnt FROM staff_list")->fetch_assoc()['cnt'];
$total_products = $conn->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc()['cnt'];

// Today's Performance
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM sales WHERE date LIKE CONCAT(?, '%')");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_sales = $stmt->get_result()->fetch_assoc()['total'];
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM sales WHERE date LIKE CONCAT(?, '%')");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_tx = $stmt->get_result()->fetch_assoc()['cnt'];

// Month Performance
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') >= ?");
$stmt->bind_param("s", $month_start);
$stmt->execute();
$month_sales = $stmt->get_result()->fetch_assoc()['total'];

// Alerts
$low_stock = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE qty <= alert AND alert > 0")->fetch_assoc()['cnt'];
$expiring = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE expire IS NOT NULL AND STR_TO_DATE(expire, '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['cnt'];
$pending_debt = $conn->query("SELECT COALESCE(SUM(amt_due), 0) as total FROM sales WHERE amt_due > 0")->fetch_assoc()['total'];

// Shop Performance Today
$stmt = $conn->prepare("SELECT COALESCE(shop, 'Main') as shop_name, SUM(amount) as total, COUNT(*) as tx 
    FROM sales WHERE date LIKE CONCAT(?, '%') GROUP BY shop ORDER BY total DESC LIMIT 5");
$stmt->bind_param("s", $today);
$stmt->execute();
$shop_perf = $stmt->get_result();

// Recent Activity
$recent_sales = $conn->query("SELECT * FROM sales ORDER BY id DESC LIMIT 5");
$recent_expenses = $conn->query("SELECT * FROM expenses ORDER BY id DESC LIMIT 5");
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-shield-check me-2"></i> Admin Control Panel</h2>
        <p class="text-muted">Centralized overview and management for all shops.</p>
    </div>
    <div class="col-md-4 text-end">
        <span class="badge bg-success">
            <?php echo date('l, F j, Y'); ?>
        </span>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center py-3">
                <h4 class="mb-0">
                    <?php echo $total_shops; ?>
                </h4>
                <small>Shops</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center py-3">
                <h4 class="mb-0">
                    <?php echo $total_staff; ?>
                </h4>
                <small>Staff</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center py-3">
                <h4 class="mb-0">
                    <?php echo $total_products; ?>
                </h4>
                <small>Products</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center py-3">
                <h4 class="mb-0">
                    <?php echo number_format($today_sales, 2); ?>
                </h4>
                <small>Today's Sales</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark text-white">
            <div class="card-body text-center py-3">
                <h4 class="mb-0">
                    <?php echo number_format($month_sales, 2); ?>
                </h4>
                <small>This Month</small>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Row -->
<?php if ($low_stock > 0 || $expiring > 0 || $pending_debt > 0): ?>
    <div class="row mb-4">
        <?php if ($low_stock > 0): ?>
            <div class="col-md-4">
                <div class="alert alert-warning d-flex justify-content-between align-items-center mb-0">
                    <span><i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo $low_stock; ?> products low on stock
                    </span>
                    <a href="../reports/inventory.php?filter=low" class="btn btn-sm btn-warning">View</a>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($expiring > 0): ?>
            <div class="col-md-4">
                <div class="alert alert-danger d-flex justify-content-between align-items-center mb-0">
                    <span><i class="bi bi-hourglass-split me-2"></i>
                        <?php echo $expiring; ?> products expiring soon
                    </span>
                    <a href="../reports/expiry.php" class="btn btn-sm btn-danger">View</a>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($pending_debt > 0): ?>
            <div class="col-md-4">
                <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
                    <span><i class="bi bi-credit-card me-2"></i>
                        <?php echo number_format($pending_debt, 2); ?> pending debt
                    </span>
                    <a href="../sales/debt.php" class="btn btn-sm btn-info">Collect</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Shop Performance Today -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-shop me-2"></i> Shop Performance (Today)</h5>
                <a href="../reports/shop_comparison.php" class="btn btn-sm btn-outline-primary">Full Report</a>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($shop = $shop_perf->fetch_assoc()): ?>
                            <tr>
                                <td><strong>
                                        <?php echo $shop['shop_name']; ?>
                                    </strong></td>
                                <td class="text-center">
                                    <?php echo $shop['tx']; ?>
                                </td>
                                <td class="text-end text-success fw-bold">
                                    <?php echo number_format($shop['total'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="shops.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="bi bi-shop d-block mb-1" style="font-size: 1.5rem;"></i>
                            Manage Shops
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="users.php" class="btn btn-outline-info w-100 py-3">
                            <i class="bi bi-people d-block mb-1" style="font-size: 1.5rem;"></i>
                            Manage Users
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../finance/profit_loss.php" class="btn btn-outline-success w-100 py-3">
                            <i class="bi bi-graph-up d-block mb-1" style="font-size: 1.5rem;"></i>
                            Profit & Loss
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../reports/index.php" class="btn btn-outline-dark w-100 py-3">
                            <i class="bi bi-file-earmark-text d-block mb-1" style="font-size: 1.5rem;"></i>
                            All Reports
                        </a>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="button" class="btn btn-danger w-100 py-3" data-bs-toggle="modal"
                            data-bs-target="#resetSystemModal">
                            <i class="bi bi-trash3 d-block mb-1" style="font-size: 1.5rem;"></i>
                            System Reset (New Clients)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset System Modal -->
<div class="modal fade" id="resetSystemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i> System Reset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Warning:</strong> This action will clear all products, sales, expenses, and staff accounts.
                    <strong>Admin details and basic settings will be preserved.</strong>
                </div>
                <form id="resetSystemForm">
                    <p>To confirm, please enter your admin password:</p>
                    <div class="mb-3">
                        <input type="password" name="password" id="adminPassword" class="form-control"
                            placeholder="Admin Password" required title="Please enter your password to confirm reset">
                    </div>
                    <div id="resetStatus" class="mt-2"></div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmResetBtn">
                    <span id="btnText">Permanently Reset System</span>
                    <span id="btnLoading" class="spinner-border spinner-border-sm d-none"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('confirmResetBtn').addEventListener('click', function () {
        const password = document.getElementById('adminPassword').value;
        if (!password) {
            alert('Please enter your password.');
            return;
        }

        if (!confirm('ARE YOU ABSOLUTELY SURE? This cannot be undone!')) {
            return;
        }

        const btn = this;
        const btnText = document.getElementById('btnText');
        const btnLoading = document.getElementById('btnLoading');
        const statusDiv = document.getElementById('resetStatus');

        btn.disabled = true;
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        statusDiv.innerHTML = '';

        fetch('reset_system.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'password=' + encodeURIComponent(password)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                    setTimeout(() => {
                        window.location.href = '../../modules/auth/logout.php';
                    }, 2000);
                } else {
                    statusDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                    btn.disabled = false;
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                }
            })
            .catch(error => {
                statusDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                btn.disabled = false;
                btnText.classList.remove('d-none');
                btnLoading.classList.add('d-none');
            });
    });
</script>

<div class="row">
    <!-- Recent Sales -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-receipt me-2"></i> Recent Sales</h5>
                <a href="../sales/history.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Shop</th>
                            <th class="text-end">Amount</th>
                            <th>Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                            <tr>
                                <td><a href="../sales/invoice.php?invoice=<?php echo $sale['invoice']; ?>">
                                        <?php echo $sale['invoice']; ?>
                                    </a></td>
                                <td>
                                    <?php echo $sale['shop'] ?: 'Main'; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($sale['amount'], 2); ?>
                                </td>
                                <td>
                                    <?php echo $sale['agent']; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Expenses -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i> Recent Expenses</h5>
                <a href="../finance/expenses.php" class="btn btn-sm btn-outline-danger">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th>By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($exp = $recent_expenses->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $exp['expenses']; ?>
                                </td>
                                <td class="text-end text-danger">
                                    <?php echo number_format($exp['amount'], 2); ?>
                                </td>
                                <td>
                                    <?php echo $exp['registered_by']; ?>
                                </td>
                                <td>
                                    <?php echo date('M d', strtotime($exp['date_time'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>