<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Date Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of month
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Helper function to get sales total
function getSalesTotal($conn, $start, $end, $shop = null, $agent = null)
{
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?";
    $types = "ss";
    $params = [$start, $end];

    if ($shop) {
        $sql .= " AND shop = ?";
        $types .= "s";
        $params[] = $shop;
    }
    if ($agent) {
        $sql .= " AND agent = ?";
        $types .= "s";
        $params[] = $agent;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

// Helper function to get profit total
function getProfitTotal($conn, $start, $end)
{
    // 1. Get Net Revenue (Gross Sales - Tax)
    $stmt_rev = $conn->prepare("SELECT SUM(amount - COALESCE(tax_amount, 0)) as net_revenue FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?");
    $stmt_rev->bind_param("ss", $start, $end);
    $stmt_rev->execute();
    $net_revenue = (float) $stmt_rev->get_result()->fetch_assoc()['net_revenue'];

    // 2. Get Net COGS (Cost of items sold - Cost of items returned)
    $stmt_cogs = $conn->prepare("SELECT SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2))) as total_cogs 
                                 FROM sales_order so JOIN sales s ON so.invoice = s.invoice 
                                 WHERE STR_TO_DATE(s.date, '%Y-%m-%d') BETWEEN ? AND ?");
    $stmt_cogs->bind_param("ss", $start, $end);
    $stmt_cogs->execute();
    $total_cogs = (float) $stmt_cogs->get_result()->fetch_assoc()['total_cogs'];

    return max(0, $net_revenue - $total_cogs);
}

// Summary Data
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');

$today_sales = getSalesTotal($conn, $today, $today);
$today_profit = getProfitTotal($conn, $today, $today);

$week_sales = getSalesTotal($conn, $week_start, $today);
$week_profit = getProfitTotal($conn, $week_start, $today);

$month_sales = getSalesTotal($conn, $month_start, $today);
$month_profit = getProfitTotal($conn, $month_start, $today);

$filtered_sales = getSalesTotal($conn, $start_date, $end_date);
$filtered_profit = getProfitTotal($conn, $start_date, $end_date);

// Sales by Agent
$agent_sql = "SELECT agent, COUNT(*) as txn_count, COALESCE(SUM(amount), 0) as total 
              FROM sales 
              WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?
              GROUP BY agent 
              ORDER BY total DESC";
$agent_stmt = $conn->prepare($agent_sql);
$agent_stmt->bind_param("ss", $start_date, $end_date);
$agent_stmt->execute();
$agent_results = $agent_stmt->get_result();

// Sales by Shop
$shop_sql = "SELECT COALESCE(shop, 'Main') as shop_name, COUNT(*) as txn_count, COALESCE(SUM(amount), 0) as total 
             FROM sales 
             WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?
             GROUP BY shop 
             ORDER BY total DESC";
$shop_stmt = $conn->prepare($shop_sql);
$shop_stmt->bind_param("ss", $start_date, $end_date);
$shop_stmt->execute();
$shop_results = $shop_stmt->get_result();

// Top Selling Products (by quantity)
$top_sql = "SELECT 
                so.name, 
                SUM(CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) as total_qty, 
                SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(so.price AS DECIMAL(15,2))) as total_revenue,
                SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * (CAST(so.price AS DECIMAL(15,2)) - CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2)))) as total_profit
            FROM sales_order so
            INNER JOIN sales s ON so.invoice = s.invoice
            WHERE STR_TO_DATE(s.date, '%Y-%m-%d') BETWEEN ? AND ?
            GROUP BY so.name
            HAVING total_qty > 0
            ORDER BY total_qty DESC
            LIMIT 10";
$top_stmt = $conn->prepare($top_sql);
$top_stmt->bind_param("ss", $start_date, $end_date);
$top_stmt->execute();
$top_results = $top_stmt->get_result();

// Daily Sales Trend for chart (last 7 days)
$trend_sql = "SELECT date, SUM(amount) as daily_total 
              FROM sales 
              WHERE STR_TO_DATE(date, '%Y-%m-%d') >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY date 
              ORDER BY date ASC";
$trend_result = $conn->query($trend_sql);
$trend_labels = [];
$trend_data = [];
while ($row = $trend_result->fetch_assoc()) {
    $trend_labels[] = $row['date'];
    $trend_data[] = (float) $row['daily_total'];
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Sales Reports</h2>
        <p class="text-muted">Analyze sales performance across date ranges, staff, and locations.</p>
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
        <div class="card bg-primary text-white">
            <div class="card-body">
                <small class="text-white-50">Today's Performance</small>
                <div class="d-flex justify-content-between align-items-end mt-1">
                    <div>
                        <h6 class="mb-0 small text-uppercase">Revenue</h6>
                        <h4 class="mb-0"><?php echo number_format($today_sales, 2); ?></h4>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0 small text-uppercase">Profit</h6>
                        <h4 class="mb-0 text-info"><?php echo number_format($today_profit, 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <small class="text-white-50">This Week</small>
                <div class="d-flex justify-content-between align-items-end mt-1">
                    <div>
                        <h6 class="mb-0 small text-uppercase">Revenue</h6>
                        <h4 class="mb-0"><?php echo number_format($week_sales, 2); ?></h4>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0 small text-uppercase">Profit</h6>
                        <h4 class="mb-0 text-info"><?php echo number_format($week_profit, 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <small class="text-white-50">This Month</small>
                <div class="d-flex justify-content-between align-items-end mt-1">
                    <div>
                        <h6 class="mb-0 small text-uppercase">Revenue</h6>
                        <h4 class="mb-0"><?php echo number_format($month_sales, 2); ?></h4>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0 small text-uppercase">Profit</h6>
                        <h3 class="mb-0"><?php echo number_format($month_profit, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark text-white">
            <div class="card-body">
                <small class="text-white-50">Filtered Period</small>
                <div class="d-flex justify-content-between align-items-end mt-1">
                    <div>
                        <h6 class="mb-0 small text-uppercase">Revenue</h6>
                        <h4 class="mb-0 text-warning"><?php echo number_format($filtered_sales, 2); ?></h4>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-0 small text-uppercase">Profit</h6>
                        <h4 class="mb-0 text-success"><?php echo number_format($filtered_profit, 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables -->
<div class="row">
    <!-- Sales Trend Chart -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Daily Sales Trend (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Sellers -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Top Selling Products</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $top_results->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $row['name']; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo $row['total_qty']; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($row['total_revenue'], 2); ?>
                                </td>
                                <td class="text-end text-primary fw-bold">
                                    <?php echo number_format($row['total_profit'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sales by Agent -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sales by Staff</h5>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $agent_results->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $row['agent'] ?: 'N/A'; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo $row['txn_count']; ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo number_format($row['total'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sales by Shop -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sales by Shop</h5>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $shop_results->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $row['shop_name']; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo $row['txn_count']; ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo number_format($row['total'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [{
                label: 'Daily Sales',
                data: <?php echo json_encode($trend_data); ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>