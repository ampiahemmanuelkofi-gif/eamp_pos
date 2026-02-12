<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$today = date('Y-m-d');
$month_start = date('Y-m-01');

// Quick Stats
$today_sales = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM sales WHERE date LIKE '$today%'")->fetch_assoc()['total'];
$month_sales = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') >= '$month_start'")->fetch_assoc()['total'];

// Quick Profit
$today_net_rev = $conn->query("SELECT SUM(amount - COALESCE(tax_amount, 0)) as total FROM sales WHERE date LIKE '$today%'")->fetch_assoc()['total'];
$today_cogs = $conn->query("SELECT SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2))) as total FROM sales_order so JOIN sales s ON so.invoice = s.invoice WHERE s.date LIKE '$today%'")->fetch_assoc()['total'];
$today_profit = max(0, (float) $today_net_rev - (float) $today_cogs);

$month_net_rev = $conn->query("SELECT SUM(amount - COALESCE(tax_amount, 0)) as total FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') >= '$month_start'")->fetch_assoc()['total'];
$month_cogs = $conn->query("SELECT SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2))) as total FROM sales_order so JOIN sales s ON so.invoice = s.invoice WHERE STR_TO_DATE(s.date, '%Y-%m-%d') >= '$month_start'")->fetch_assoc()['total'];
$month_profit = max(0, (float) $month_net_rev - (float) $month_cogs);

$low_stock = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE qty <= alert AND alert > 0")->fetch_assoc()['cnt'];
$expiring = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE expire IS NOT NULL AND STR_TO_DATE(expire, '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['cnt'];
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Reports Center</h2>
        <p class="text-muted">Access all system reports from one place.</p>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h4 class="text-success mb-0"><?php echo number_format($today_sales, 2); ?></h4>
                <div class="text-info small mb-1">Profit: <?php echo number_format($today_profit, 2); ?></div>
                <small class="text-muted">Today's Sales</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h4 class="text-primary mb-0"><?php echo number_format($month_sales, 2); ?></h4>
                <div class="text-info small mb-1">Profit: <?php echo number_format($month_profit, 2); ?></div>
                <small class="text-muted">Month to Date</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h4 class="text-warning">
                    <?php echo $low_stock; ?>
                </h4>
                <small class="text-muted">Low Stock Items</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h4 class="text-danger">
                    <?php echo $expiring; ?>
                </h4>
                <small class="text-muted">Expiring Soon</small>
            </div>
        </div>
    </div>
</div>

<!-- Report Categories -->
<div class="row">
    <!-- Sales Reports -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cart-check me-2"></i> Sales Reports</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="daily.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Daily Sales Report
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="sales.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Sales Analytics
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="../sales/history.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Sales History
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="../sales/debt.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Debt Report
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Inventory Reports -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i> Inventory Reports</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="inventory.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Stock Levels
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="inventory.php?filter=low"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Low Stock Alert
                    <span class="badge bg-warning text-dark">
                        <?php echo $low_stock; ?>
                    </span>
                </a>
                <a href="expiry.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Expiry Report
                    <span class="badge bg-danger">
                        <?php echo $expiring; ?>
                    </span>
                </a>
                <a href="../inventory/view_stock.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Multi-Location Stock
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Financial Reports -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i> Financial Reports</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="../finance/profit_loss.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Profit & Loss
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="../finance/accounts.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Account Balances
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="../finance/expenses.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Expense Report
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="../finance/reconciliation.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Cash Reconciliation
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Performance Reports -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i> Performance Reports</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="staff.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Staff Performance</strong>
                        <small class="d-block text-muted">Sales by staff member with rankings</small>
                    </div>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="products.php"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Product Performance</strong>
                        <small class="d-block text-muted">Best and worst selling products</small>
                    </div>
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="sales.php?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                        class="btn btn-outline-success">
                        <i class="bi bi-calendar-day me-2"></i> Today's Sales Report
                    </a>
                    <a href="sales.php?start_date=<?php echo date('Y-m-d', strtotime('monday this week')); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                        class="btn btn-outline-primary">
                        <i class="bi bi-calendar-week me-2"></i> This Week's Report
                    </a>
                    <a href="sales.php?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                        class="btn btn-outline-info">
                        <i class="bi bi-calendar-month me-2"></i> This Month's Report
                    </a>
                    <a href="inventory.php?export=csv" class="btn btn-outline-secondary">
                        <i class="bi bi-download me-2"></i> Export Full Inventory
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>