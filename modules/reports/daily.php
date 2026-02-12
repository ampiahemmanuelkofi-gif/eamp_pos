<?php
require_once '../../includes/auth.php';
checkLogin();
if (!hasPermission('manager')) {
    die("Access Denied");
}
require_once '../../includes/header.php';
require_once '../../config/database.php';

$date = $_GET['date'] ?? date('Y-m-d');
$db = new Database();
$conn = $db->getConnection();

// Summary Query
$sql = "SELECT 
            SUM(amount) as total_sales,
            COUNT(*) as transaction_count,
            SUM(amt_due) as total_debt
        FROM sales 
        WHERE date LIKE ?"; // Using LIKE for date if it's timestamp or varchar

// Check schema usage. process.php saved date as 'Y-m-d' (varchar? date?). 
// If column type is date: WHERE date = ?
// If column type is timestamp: WHERE DATE(date) = ?
// Looking at schema snippet from pos.sql: `date` varchar(20) NOT NULL. So LIKE 'YYYY-MM-DD%' works best if time included, or = if just date.
// process.php did $date = date('Y-m-d'), so exact match likely fine.

$param = $date . '%';
// 1. Get Revenue & TX Count
$stmt_sum = $conn->prepare("SELECT SUM(amount) as total_sales, COUNT(*) as tx_count, SUM(tax_amount) as total_tax FROM sales WHERE date LIKE ?");
$stmt_sum->bind_param("s", $param);
$stmt_sum->execute();
$sales_sum = $stmt_sum->get_result()->fetch_assoc();

// 2. Get COGS
$stmt_cogs = $conn->prepare("SELECT SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2))) as total_cogs 
                             FROM sales_order so JOIN sales s ON so.invoice = s.invoice 
                             WHERE s.date LIKE ?");
$stmt_cogs->bind_param("s", $param);
$stmt_cogs->execute();
$cogs = (float) $stmt_cogs->get_result()->fetch_assoc()['total_cogs'];

$summary = [
    'total_sales' => $sales_sum['total_sales'] ?? 0,
    'tx_count' => $sales_sum['tx_count'] ?? 0,
    'total_profit' => ($sales_sum['total_sales'] - ($sales_sum['total_tax'] ?? 0)) - $cogs
];

// List sales for that day
$stmt_list = $conn->prepare("SELECT * FROM sales WHERE date LIKE ? ORDER BY id DESC");
$stmt_list->bind_param("s", $param);
$stmt_list->execute();
$list_result = $stmt_list->get_result();
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2>Daily Report</h2>
    </div>
    <div class="col-md-6">
        <form class="d-flex justify-content-end">
            <input type="date" name="date" class="form-control w-auto me-2" value="<?php echo $date; ?>">
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo format_currency($summary['total_sales'] ?? 0); ?></h3>
                <div>Total Sales (Revenue)</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo format_currency($summary['total_profit'] ?? 0); ?></h3>
                <div>Daily Profit</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo $summary['tx_count'] ?? 0; ?></h3>
                <div>Transactions</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Transactions on
            <?php echo $date; ?>
        </h5>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $list_result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo $row['invoice']; ?>
                        </td>
                        <td>
                            <?php echo $row['name']; ?>
                        </td>
                        <td>
                            <?php echo format_currency($row['amount']); ?>
                        </td>
                        <td>
                            <?php echo $row['agent']; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>