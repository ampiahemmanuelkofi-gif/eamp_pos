<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$shop_filter = $_GET['shop'] ?? '';
$agent_filter = $_GET['agent'] ?? '';

// Build Query
$sql = "SELECT * FROM sales WHERE 1=1";
$types = "";
$params = [];

// Date Filter - relying on 'date' column which seems to be varchar(20). 
// Assuming format 'Y-m-d' or similar. If it's DATETIME/TIMESTAMP, we compare range.
// Schema says `date` varchar(20). Let's assume typical Y-m-d stored.
if ($start_date && $end_date) {
    // Basic string comparison works for Y-m-d
    // Or if `date` only holds date part. `date_time` is timestamp.
    // Let's use `date` column as requested in "Sales by date range".
    $sql .= " AND STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?";
    $types .= "ss";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($shop_filter) {
    $sql .= " AND shop = ?";
    $types .= "s";
    $params[] = $shop_filter;
}

if ($agent_filter) {
    $sql .= " AND agent = ?";
    $types .= "s";
    $params[] = $agent_filter;
}

// Pagination
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare($sql . " ORDER BY id DESC LIMIT ? OFFSET ?");
$p_types = $types . "ii";
$p_params = array_merge($params, [$limit, $offset]);
$stmt->bind_param($p_types, ...$p_params);
$stmt->execute();
$result = $stmt->get_result();

// Summary Query for Filtered Results (Ignore Pagination)
$summary_sql = "SELECT 
                    COALESCE(SUM(amount), 0) as total_amount, 
                    COALESCE(SUM(amt_due), 0) as total_due, 
                    COALESCE(SUM(tax_amount), 0) as total_tax,
                    COUNT(*) as txn_count,
                    (SELECT SUM((CAST(so.qty AS DECIMAL(15,2)) - CAST(COALESCE(so.returned_qty, 0) AS DECIMAL(15,2))) * CAST(COALESCE(so.factory_price, 0) AS DECIMAL(15,2)))
                     FROM sales_order so JOIN sales s2 ON so.invoice = s2.invoice 
                     WHERE STR_TO_DATE(s2.date, '%Y-%m-%d') BETWEEN ? AND ?" .
    ($shop_filter ? " AND s2.shop = ?" : "") .
    ($agent_filter ? " AND s2.agent = ?" : "") .
    ") as total_cogs
                FROM sales WHERE 1=1";

if ($start_date && $end_date) {
    $summary_sql .= " AND STR_TO_DATE(date, '%Y-%m-%d') BETWEEN ? AND ?";
}
if ($shop_filter)
    $summary_sql .= " AND shop = ?";
if ($agent_filter)
    $summary_sql .= " AND agent = ?";

$stmt_sum = $conn->prepare($summary_sql);
// Double the filter params because they are used in both main query and subquery
$filter_params = [$start_date, $end_date];
if ($shop_filter)
    $filter_params[] = $shop_filter;
if ($agent_filter)
    $filter_params[] = $agent_filter;

$final_sum_params = array_merge($filter_params, $filter_params);
$final_sum_types = str_repeat("s", count($final_sum_params));

if (!empty($final_sum_params)) {
    $stmt_sum->bind_param($final_sum_types, ...$final_sum_params);
}
$stmt_sum->execute();
$summary = $stmt_sum->get_result()->fetch_assoc();
$summary['net_profit'] = ($summary['total_amount'] - $summary['total_tax']) - ($summary['total_cogs'] ?? 0);

// Get lists for filters
$shops = $conn->query("SELECT * FROM shops");
$agents = $conn->query("SELECT distinct agent FROM sales");

?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Sales History</h2>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Shop</label>
                <select name="shop" class="form-control">
                    <option value="">All Shops</option>
                    <?php while ($s = $shops->fetch_assoc()): ?>
                        <option value="<?php echo $s['name']; ?>" <?php if ($shop_filter == $s['name'])
                               echo 'selected'; ?>>
                            <?php echo $s['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Agent</label>
                <select name="agent" class="form-control">
                    <option value="">All Agents</option>
                    <?php while ($a = $agents->fetch_assoc()): ?>
                        <option value="<?php echo $a['agent']; ?>" <?php if ($agent_filter == $a['agent'])
                               echo 'selected'; ?>>
                            <?php echo $a['agent']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-end flex-wrap gap-2">
                <div class="btn-group btn-group-sm">
                    <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                        class="btn btn-outline-primary <?php echo ($start_date == date('Y-m-d')) ? 'active' : ''; ?>">Today</a>
                    <a href="?start_date=<?php echo date('Y-m-d', strtotime('yesterday')); ?>&end_date=<?php echo date('Y-m-d', strtotime('yesterday')); ?>"
                        class="btn btn-outline-primary">Yesterday</a>
                    <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                        class="btn btn-outline-primary">Last 7 Days</a>
                    <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                        class="btn btn-outline-primary <?php echo ($start_date == date('Y-m-01')) ? 'active' : ''; ?>">This
                        Month</a>
                    <a href="?start_date=<?php echo date('Y-m-01', strtotime('first day of last month')); ?>&end_date=<?php echo date('Y-m-t', strtotime('last day of last month')); ?>"
                        class="btn btn-outline-primary">Last Month</a>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Filter Sales</button>
                    <a href="history.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body py-2">
                <small class="text-white-50">Total Sales (Filtered)</small>
                <h4 class="mb-0"><?php echo format_currency($summary['total_amount']); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow-sm border-0">
            <div class="card-body py-2 text-center">
                <small class="text-white-50">Net Profit</small>
                <h4 class="mb-0"><?php echo format_currency($summary['net_profit']); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white shadow-sm border-0">
            <div class="card-body py-2 text-end">
                <small class="text-white-50">Total Owed (Debt)</small>
                <h4 class="mb-0"><?php echo format_currency($summary['total_due']); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark text-white shadow-sm border-0">
            <div class="card-body py-2 text-center">
                <small class="text-white-50">Transaction Count</small>
                <h4 class="mb-0"><?php echo $summary['txn_count']; ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Shop</th>
                        <th>Amount</th>
                        <th>Due</th>
                        <th>Agent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo $row['invoice']; ?></td>
                                <td><?php echo $row['name']; ?></td>
                                <td><?php echo $row['shop']; ?></td>
                                <td><?php echo format_currency($row['amount']); ?></td>
                                <td>
                                    <?php if ($row['amt_due'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo format_currency($row['amt_due']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['agent']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-view-invoice"
                                        data-invoice="<?php echo $row['invoice']; ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No sales found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Invoice View Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="invoiceModalBody">
                <div class="text-center p-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="printInvoiceBtn" class="btn btn-dark" target="_blank"><i class="bi bi-printer"></i>
                    Print</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var invoiceModalEndpoint = 'invoice.php';
        var invoiceModal = new bootstrap.Modal(document.getElementById('invoiceModal'));

        // Use delegation for buttons
        document.querySelectorAll('.btn-view-invoice').forEach(btn => {
            btn.addEventListener('click', function () {
                var invoiceId = this.getAttribute('data-invoice');
                var modalBody = document.getElementById('invoiceModalBody');
                var printBtn = document.getElementById('printInvoiceBtn');

                // Reset Content
                modalBody.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

                // Update Print Link
                printBtn.href = invoiceModalEndpoint + '?invoice=' + invoiceId;

                // Show Modal
                invoiceModal.show();

                // Fetch Content
                fetch(invoiceModalEndpoint + '?invoice=' + invoiceId + '&view_type=partial')
                    .then(response => response.text())
                    .then(html => {
                        modalBody.innerHTML = html;

                        // Re-initialize any JS scripts inside the modal content if needed
                        // Simple inline scripts won't run automatically via innerHTML
                        // But now we are just loading a standard view
                    })
                    .catch(err => {
                        modalBody.innerHTML = '<div class="alert alert-danger">Error loading invoice details.</div>';
                    });
            });
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>