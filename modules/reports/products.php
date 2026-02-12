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
$sort = $_GET['sort'] ?? 'qty'; // qty or revenue

// Product Performance Query
$order_by = $sort == 'revenue' ? 'total_revenue DESC' : ($sort == 'profit' ? 'total_profit DESC' : 'total_qty DESC');

$sql = "SELECT 
            so.code,
            so.name,
            SUM(CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) as total_qty,
            SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(so.price AS DECIMAL(15,2))) as total_revenue,
            SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * (CAST(so.price AS DECIMAL(15,2)) - CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2)))) as total_profit,
            COUNT(DISTINCT so.invoice) as order_count,
            AVG(CAST(so.price AS DECIMAL(15,2))) as avg_price
        FROM sales_order so
        INNER JOIN sales s ON so.invoice = s.invoice
        WHERE STR_TO_DATE(s.date, '%Y-%m-%d') BETWEEN ? AND ?
        GROUP BY so.code, so.name
        HAVING total_qty > 0
        ORDER BY $order_by
        LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Summary Stats
$summary_sql = "SELECT 
    COUNT(DISTINCT so.code) as unique_products,
    SUM(CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) as total_units,
    SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(so.price AS DECIMAL(15,2))) as total_revenue,
    SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * (CAST(so.price AS DECIMAL(15,2)) - CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2)))) as total_profit
    FROM sales_order so
    INNER JOIN sales s ON so.invoice = s.invoice
    WHERE STR_TO_DATE(s.date, '%Y-%m-%d') BETWEEN ? AND ?";
$sum_stmt = $conn->prepare($summary_sql);
$sum_stmt->bind_param("ss", $start_date, $end_date);
$sum_stmt->execute();
$summary = $sum_stmt->get_result()->fetch_assoc();

// Handle Export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="product_performance_' . $start_date . '_to_' . $end_date . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Product', 'Qty Sold', 'Revenue', 'Orders', 'Avg Price']);

    $export_stmt = $conn->prepare($sql);
    $export_stmt->bind_param("ss", $start_date, $end_date);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();

    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['code'],
            $row['name'],
            $row['total_qty'],
            $row['total_revenue'],
            $row['order_count'],
            round($row['avg_price'], 2)
        ]);
    }
    fclose($output);
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Product Performance</h2>
        <p class="text-muted">Best and worst selling products.</p>
    </div>
    <div class="col-md-6 text-end">
        <form method="GET" class="d-flex gap-2 justify-content-end flex-wrap">
            <input type="date" name="start_date" class="form-control form-control-sm" style="width: auto;"
                value="<?php echo $start_date; ?>">
            <input type="date" name="end_date" class="form-control form-control-sm" style="width: auto;"
                value="<?php echo $end_date; ?>">
            <select name="sort" class="form-select form-select-sm" style="width: auto;">
                <option value="qty" <?php if ($sort == 'qty')
                    echo 'selected'; ?>>By Quantity</option>
                <option value="revenue" <?php if ($sort == 'revenue')
                    echo 'selected'; ?>>By Revenue</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&sort=<?php echo $sort; ?>&export=1"
                class="btn btn-success btn-sm">
                <i class="bi bi-download"></i>
            </a>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $summary['unique_products'] ?? 0; ?></h3>
                <div>Products Sold</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($summary['total_units'] ?? 0); ?></h3>
                <div>Total Units</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></h3>
                <div>Total Revenue</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($summary['total_profit'] ?? 0, 2); ?></h3>
                <div class="text-info">Total Profit</div>
            </div>
        </div>
    </div>
</div>

<!-- Performance Table -->
<div class="card">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link active" href="#">Top 50 Products</a>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Code</th>
                    <th>Product</th>
                    <th class="text-end">Qty Sold</th>
                    <th class="text-end">Revenue</th>
                    <th class="text-end">Profit</th>
                    <th class="text-center">Orders</th>
                    <th class="text-end">Avg Price</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rank = 1;
                $max_qty = 0;
                $max_rev = 0;

                // Get max for progress bars
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                    if ($row['total_qty'] > $max_qty)
                        $max_qty = $row['total_qty'];
                    if ($row['total_revenue'] > $max_rev)
                        $max_rev = $row['total_revenue'];
                }

                foreach ($data as $row):
                    $pct = $sort == 'revenue'
                        ? ($max_rev > 0 ? ($row['total_revenue'] / $max_rev) * 100 : 0)
                        : ($max_qty > 0 ? ($row['total_qty'] / $max_qty) * 100 : 0);
                    ?>
                    <tr>
                        <td>
                            <?php if ($rank <= 3): ?>
                                <span
                                    class="badge bg-<?php echo $rank == 1 ? 'warning text-dark' : ($rank == 2 ? 'secondary' : 'dark'); ?>">
                                    <?php echo $rank; ?>
                                </span>
                            <?php else: ?>
                                <?php echo $rank; ?>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo $row['code']; ?></code></td>
                        <td>
                            <?php echo $row['name']; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php echo number_format($row['total_qty']); ?>
                        </td>
                        <td class="text-end text-success">
                            <?php echo number_format($row['total_revenue'], 2); ?>
                        </td>
                        <td class="text-end text-primary fw-bold">
                            <?php echo number_format($row['total_profit'], 2); ?>
                        </td>
                        <td class="text-center">
                            <?php echo $row['order_count']; ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($row['avg_price'], 2); ?>
                        </td>
                        <td style="width: 150px;">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>