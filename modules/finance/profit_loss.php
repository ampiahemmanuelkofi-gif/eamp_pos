<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Date Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Calculate Income (Net Revenue = Gross - Tax)
$income_sql = "SELECT COALESCE(SUM(amount - COALESCE(tax_amount, 0)), 0) as total FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?";
$stmt = $conn->prepare($income_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_income = (float) $stmt->get_result()->fetch_assoc()['total'];

// Calculate Expenses
$exp_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE(date_time) BETWEEN ? AND ?";
$stmt2 = $conn->prepare($exp_sql);
$stmt2->bind_param("ss", $start_date, $end_date);
$stmt2->execute();
$total_expenses = (float) $stmt2->get_result()->fetch_assoc()['total'];

// Calculate COGS (Cost of Goods Sold) - accounting for returns
$cogs_sql = "SELECT SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2))) as total 
             FROM sales_order so 
             JOIN sales s ON so.invoice = s.invoice 
             WHERE STR_TO_DATE(s.date, '%Y-%m-%d') BETWEEN ? AND ?";
$stmt3 = $conn->prepare($cogs_sql);
$stmt3->bind_param("ss", $start_date, $end_date);
$stmt3->execute();
$total_cogs = (float) $stmt3->get_result()->fetch_assoc()['total'];

// Calculations
$gross_profit = $total_income - $total_cogs;
$net_profit = $gross_profit - $total_expenses;
$gross_margin = $total_income > 0 ? ($gross_profit / $total_income) * 100 : 0;
$net_margin = $total_income > 0 ? ($net_profit / $total_income) * 100 : 0;

// Expense Breakdown
$exp_breakdown = $conn->query("SELECT expenses as category, SUM(amount) as total FROM expenses WHERE DATE(date_time) BETWEEN '$start_date' AND '$end_date' GROUP BY expenses ORDER BY total DESC");

// Account Balances
$accounts = $conn->query("SELECT * FROM bank_accounts WHERE status = 'Active' ORDER BY balance DESC");
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Profit & Loss Report</h2>
        <p class="text-muted">Financial performance overview for selected period.</p>
    </div>
    <div class="col-md-4 text-end">
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="start_date" class="form-control form-control-sm"
                value="<?php echo $start_date; ?>">
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
            <button class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Total Revenue</h6>
                <h3 class="mb-0">
                    <?php echo number_format($total_income, 2); ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>Cost of Goods</h6>
                <h3 class="mb-0">
                    <?php echo number_format($total_cogs, 2); ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6>Total Expenses</h6>
                <h3 class="mb-0">
                    <?php echo number_format($total_expenses, 2); ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-<?php echo $net_profit >= 0 ? 'primary' : 'danger'; ?> text-white">
            <div class="card-body">
                <h6>Net Profit</h6>
                <h3 class="mb-0">
                    <?php echo number_format($net_profit, 2); ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- P&L Statement -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Profit & Loss Statement</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <td><strong>Revenue (Sales)</strong></td>
                            <td class="text-end text-success">
                                <?php echo number_format($total_income, 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Less: Cost of Goods Sold</td>
                            <td class="text-end text-danger">(
                                <?php echo number_format($total_cogs, 2); ?>)
                            </td>
                        </tr>
                        <tr class="border-top">
                            <td><strong>Gross Profit</strong></td>
                            <td class="text-end fw-bold">
                                <?php echo number_format($gross_profit, 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Gross Margin</td>
                            <td class="text-end text-muted small">
                                <?php echo number_format($gross_margin, 1); ?>%
                            </td>
                        </tr>
                        <tr>
                            <td>Less: Operating Expenses</td>
                            <td class="text-end text-danger">(
                                <?php echo number_format($total_expenses, 2); ?>)
                            </td>
                        </tr>
                        <tr class="border-top border-2">
                            <td><strong>Net Profit</strong></td>
                            <td
                                class="text-end fw-bold <?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo number_format($net_profit, 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Net Margin</td>
                            <td class="text-end text-muted small">
                                <?php echo number_format($net_margin, 1); ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expense Breakdown -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Expense Breakdown</h5>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($exp = $exp_breakdown->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $exp['category']; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($exp['total'], 2); ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?php echo $total_expenses > 0 ? number_format(($exp['total'] / $total_expenses) * 100, 1) : 0; ?>%
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Account Balances -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Account Balances</h5>
                <a href="accounts.php" class="btn btn-sm btn-outline-primary">Manage Accounts</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php while ($acc = $accounts->fetch_assoc()): ?>
                        <div class="col-md-3 mb-3">
                            <div class="border rounded p-3">
                                <small class="text-muted">
                                    <?php echo $acc['account_type']; ?>
                                </small>
                                <h6>
                                    <?php echo $acc['name']; ?>
                                </h6>
                                <h4 class="mb-0 <?php echo $acc['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($acc['balance'], 2); ?>
                                </h4>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>