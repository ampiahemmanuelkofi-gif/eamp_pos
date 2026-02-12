<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Date Range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Shop Performance Query
$sql = "SELECT 
            COALESCE(s.shop, 'Main/Unassigned') as shop_name,
            COUNT(*) as transaction_count,
            COALESCE(SUM(s.amount), 0) as total_sales,
            COALESCE(SUM(s.tax_amount), 0) as total_tax,
            COALESCE(AVG(s.amount), 0) as avg_sale,
            COALESCE(SUM(s.amt_due), 0) as total_debt,
            (SELECT SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2)))
             FROM sales_order so JOIN sales s2 ON so.invoice = s2.invoice 
             WHERE s2.shop = s.shop AND STR_TO_DATE(s2.date, '%Y-%m-%d') BETWEEN ? AND ?) as total_cogs
        FROM sales s
        WHERE STR_TO_DATE(s.date, '%Y-%m-%d') BETWEEN ? AND ?
        GROUP BY s.shop
        ORDER BY total_sales DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Summary
$summary_sql = "SELECT 
    COUNT(DISTINCT shop) as shop_count,
    COUNT(*) as total_tx,
    COALESCE(SUM(amount), 0) as total_sales
    FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?";
$sum_stmt = $conn->prepare($summary_sql);
$sum_stmt->bind_param("ss", $start_date, $end_date);
$sum_stmt->execute();
$summary = $sum_stmt->get_result()->fetch_assoc();

// Best Performer
$best = null;
$data = [];
$max_sales = 0;

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    if ($row['total_sales'] > $max_sales) {
        $max_sales = $row['total_sales'];
        $best = $row['shop_name'];
    }
}

// Handle Export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="shop_comparison_' . $start_date . '_to_' . $end_date . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Shop', 'Transactions', 'Total Sales', 'Avg Sale', 'Debt', '% of Total']);

    foreach ($data as $row) {
        $pct = $summary['total_sales'] > 0 ? ($row['total_sales'] / $summary['total_sales']) * 100 : 0;
        fputcsv($output, [
            $row['shop_name'],
            $row['transaction_count'],
            $row['total_sales'],
            round($row['avg_sale'], 2),
            $row['total_debt'],
            round($pct, 1) . '%'
        ]);
    }
    fclose($output);
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Shop Performance Comparison</h2>
        <p class="text-muted">Compare sales performance across all locations.</p>
    </div>
    <div class="col-md-6 text-end">
        <form method="GET" class="d-flex gap-2 justify-content-end">
            <input type="date" name="start_date" class="form-control form-control-sm" style="width: auto;"
                value="<?php echo $start_date; ?>">
            <input type="date" name="end_date" class="form-control form-control-sm" style="width: auto;"
                value="<?php echo $end_date; ?>">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=1"
                class="btn btn-success btn-sm">
                <i class="bi bi-download"></i> Export
            </a>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo $summary['shop_count']; ?>
                </h3>
                <div>Active Shops</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo number_format($summary['total_sales'], 2); ?>
                </h3>
                <div>Total Sales</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo $summary['total_tx']; ?>
                </h3>
                <div>Transactions</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h3>
                    <?php echo $best ?: 'N/A'; ?>
                </h3>
                <div>Top Performer</div>
            </div>
        </div>
    </div>
</div>

<!-- Comparison Chart -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sales by Shop</h5>
            </div>
            <div class="card-body">
                <canvas id="shopChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Market Share</h5>
            </div>
            <div class="card-body">
                <canvas id="pieChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Comparison Table -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Detailed Comparison</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Shop</th>
                    <th class="text-center">Transactions</th>
                    <th class="text-end">Total Sales</th>
                    <th class="text-end">Total Profit</th>
                    <th class="text-end">Avg Sale</th>
                    <th class="text-end">Debt</th>
                    <th>% Share</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rank = 1;
                foreach ($data as $row):
                    $pct = $summary['total_sales'] > 0 ? ($row['total_sales'] / $summary['total_sales']) * 100 : 0;
                    ?>
                    <tr>
                        <td>
                            <?php if ($rank == 1): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-trophy"></i> 1</span>
                            <?php elseif ($rank == 2): ?>
                                <span class="badge bg-secondary">2</span>
                            <?php elseif ($rank == 3): ?>
                                <span class="badge bg-dark">3</span>
                            <?php else: ?>
                                <?php echo $rank; ?>
                            <?php endif; ?>
                        </td>
                        <td><strong>
                                <?php echo $row['shop_name']; ?>
                            </strong></td>
                        <td class="text-center">
                            <?php echo $row['transaction_count']; ?>
                        </td>
                        <td class="text-end text-success fw-bold">
                            <?php echo number_format($row['total_sales'], 2); ?>
                        </td>
                        <td class="text-end text-primary fw-bold">
                            <?php 
                            $shop_profit = ($row['total_sales'] - $row['total_tax']) - ($row['total_cogs'] ?? 0);
                            echo number_format($shop_profit, 2); 
                            ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($row['avg_sale'], 2); ?>
                        </td>
                        <td class="text-end <?php echo $row['total_debt'] > 0 ? 'text-danger' : ''; ?>">
                            <?php echo number_format($row['total_debt'], 2); ?>
                        </td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $pct; ?>%;">
                                    <?php echo number_format($pct, 1); ?>%
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="../sales/history.php?shop=<?php echo urlencode($row['shop_name']); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                                class="btn btn-sm btn-outline-primary">
                                View Sales
                            </a>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labels = <?php echo json_encode(array_column($data, 'shop_name')); ?>;
    const salesData = <?php echo json_encode(array_column($data, 'total_sales')); ?>;
    const colors = ['#28a745', '#007bff', '#ffc107', '#dc3545', '#17a2b8', '#6c757d', '#fd7e14', '#6f42c1'];

    // Bar Chart
    new Chart(document.getElementById('shopChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Sales',
                data: salesData,
                backgroundColor: colors.slice(0, labels.length)
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Pie Chart
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: salesData,
                backgroundColor: colors.slice(0, labels.length)
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>