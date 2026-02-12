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

// Staff Performance Query
$sql = "SELECT 
            agent,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_sales,
            COALESCE(SUM(amt_due), 0) as total_debt,
            COALESCE(AVG(amount), 0) as avg_sale
        FROM sales 
        WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?
        GROUP BY agent
        ORDER BY total_sales DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Overall Totals
$totals_sql = "SELECT 
    COUNT(*) as tx, 
    COALESCE(SUM(amount), 0) as sales,
    COUNT(DISTINCT agent) as staff_count
    FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?";
$totals_stmt = $conn->prepare($totals_sql);
$totals_stmt->bind_param("ss", $start_date, $end_date);
$totals_stmt->execute();
$totals = $totals_stmt->get_result()->fetch_assoc();

// Top Performer
$top_sql = "SELECT agent, SUM(amount) as total FROM sales WHERE STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ? GROUP BY agent ORDER BY total DESC LIMIT 1";
$top_stmt = $conn->prepare($top_sql);
$top_stmt->bind_param("ss", $start_date, $end_date);
$top_stmt->execute();
$top = $top_stmt->get_result()->fetch_assoc();

// Handle Export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="staff_performance_' . $start_date . '_to_' . $end_date . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Staff', 'Transactions', 'Total Sales', 'Avg Sale', 'Debt Collected', '% of Total']);

    $export_stmt = $conn->prepare($sql);
    $export_stmt->bind_param("ss", $start_date, $end_date);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();

    while ($row = $export_result->fetch_assoc()) {
        $pct = $totals['sales'] > 0 ? ($row['total_sales'] / $totals['sales']) * 100 : 0;
        fputcsv($output, [
            $row['agent'],
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
        <h2>Staff Performance</h2>
        <p class="text-muted">Sales performance by staff member.</p>
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
                <i class="bi bi-download me-1"></i> Export
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
                    <?php echo $totals['staff_count']; ?>
                </h3>
                <div>Active Staff</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo number_format($totals['sales'], 2); ?>
                </h3>
                <div>Total Sales</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo $totals['tx']; ?>
                </h3>
                <div>Transactions</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h3>
                    <?php echo $top['agent'] ?? 'N/A'; ?>
                </h3>
                <div>Top Performer</div>
            </div>
        </div>
    </div>
</div>

<!-- Performance Table -->
<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Staff Member</th>
                    <th class="text-center">Transactions</th>
                    <th class="text-end">Total Sales</th>
                    <th class="text-end">Avg Sale</th>
                    <th class="text-end">Debt</th>
                    <th>% of Total</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rank = 1;
                while ($row = $result->fetch_assoc()):
                    $pct = $totals['sales'] > 0 ? ($row['total_sales'] / $totals['sales']) * 100 : 0;
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
                                <?php echo $row['agent'] ?: 'Unknown'; ?>
                            </strong></td>
                        <td class="text-center">
                            <?php echo $row['transaction_count']; ?>
                        </td>
                        <td class="text-end fw-bold text-success">
                            <?php echo number_format($row['total_sales'], 2); ?>
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
                            <?php if ($pct >= 30): ?>
                                <span class="badge bg-success">Excellent</span>
                            <?php elseif ($pct >= 15): ?>
                                <span class="badge bg-primary">Good</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Average</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php $rank++; endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>