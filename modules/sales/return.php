<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../config/database.php';
require_once '../../includes/settings_loader.php';

$db = new Database();
$conn = $db->getConnection();

$order_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
$invoice_id = isset($_GET['invoice']) ? $_GET['invoice'] : '';

// --- DASHBOARD MODE (List Returns) ---
if (!$order_id && !$invoice_id) {

    // Filters
    $filter_date_start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
    $filter_date_end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
    $filter_shop = isset($_GET['shop']) ? $_GET['shop'] : '';
    $filter_search = isset($_GET['search']) ? $_GET['search'] : '';

    $where = "WHERE so.returned_item = 'Yes'";
    $params = [];
    $types = "";

    if ($filter_date_start && $filter_date_end) {
        $where .= " AND DATE(s.date) BETWEEN ? AND ?";
        $params[] = $filter_date_start;
        $params[] = $filter_date_end;
        $types .= "ss";
    }

    if ($filter_shop) {
        $where .= " AND s.shop = ?";
        $params[] = $filter_shop;
        $types .= "s";
    }

    if ($filter_search) {
        $where .= " AND (s.invoice LIKE ? OR so.name LIKE ? OR s.name LIKE ? OR s.mobile LIKE ?)";
        $searchTerm = "%$filter_search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ssss";
    }

    $query = "
        SELECT so.*, s.date as sale_date, s.shop, s.agent
        FROM sales_order so
        JOIN sales s ON so.invoice = s.invoice
        $where
        ORDER BY s.date DESC
    ";

    $stmt_list = $conn->prepare($query);
    if (!empty($params)) {
        $stmt_list->bind_param($types, ...$params);
    }
    $stmt_list->execute();
    $result = $stmt_list->get_result();

    // Fetch shops for filter
    $shops = $conn->query("SELECT name FROM shops")->fetch_all(MYSQLI_ASSOC);

    include '../../includes/header.php';
    ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Returns Management</h2>
                <div>
                    <a href="history.php" class="btn btn-outline-primary">Go to Sales History</a>
                </div>
            </div>

            <!-- Filter and Manual Search Card -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header bg-white"><strong>Filter Return History</strong></div>
                        <div class="card-body">
                            <form method="GET" class="row g-2">
                                <div class="col-md-4">
                                    <input type="date" name="start" class="form-control form-control-sm"
                                        value="<?php echo $filter_date_start; ?>">
                                </div>
                                <div class="col-md-4">
                                    <input type="date" name="end" class="form-control form-control-sm"
                                        value="<?php echo $filter_date_end; ?>">
                                </div>
                                <div class="col-md-4">
                                    <select name="shop" class="form-select form-select-sm">
                                        <option value="">All Shops</option>
                                        <?php foreach ($shops as $s): ?>
                                            <option value="<?php echo $s['name']; ?>" <?php echo ($filter_shop == $s['name']) ? 'selected' : ''; ?>>
                                                <?php echo $s['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 mt-2">
                                    <input type="text" name="search" class="form-control form-control-sm"
                                        placeholder="Search by Invoice or Product..." value="<?php echo $filter_search; ?>">
                                </div>
                                <div class="col-12 mt-2 text-end">
                                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-primary">
                        <div class="card-header bg-primary text-white"><strong>Find Sale for Return</strong></div>
                        <div class="card-body">
                            <!-- Switcher -->
                            <ul class="nav nav-pills nav-fill mb-3" id="returnSearchTab" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active py-1" id="invoice-tab" data-bs-toggle="pill"
                                        data-bs-target="#search-invoice">Invoice</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link py-1" id="customer-tab" data-bs-toggle="pill"
                                        data-bs-target="#search-customer">Customer</button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="search-invoice">
                                    <p class="small text-muted mb-2">Enter full invoice number:</p>
                                    <form action="invoice.php" method="GET">
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="invoice" class="form-control" placeholder="INV-XXXXXX"
                                                required>
                                            <button class="btn btn-primary" type="submit">Go</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="search-customer">
                                    <p class="small text-muted mb-2">Enter Customer Name or Mobile:</p>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="custSearchInput" class="form-control"
                                            placeholder="Search...">
                                        <button class="btn btn-info text-white" type="button"
                                            onclick="searchCustomerSales()">Search</button>
                                    </div>
                                    <div id="custSearchResults" class="mt-2 small scrollable-search-results"
                                        style="max-height: 150px; overflow-y: auto;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Returns Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice</th>
                                    <th>Shop</th>
                                    <th>Product</th>
                                    <th>Qty Returned</th>
                                    <th>Amount Refunded</th>
                                    <th>Reason</th>
                                    <th>Agent</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime($row['sale_date'])); ?></td>
                                            <td><a
                                                    href="invoice.php?invoice=<?php echo $row['invoice']; ?>"><?php echo $row['invoice']; ?></a>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo $row['shop'] ?: 'Main'; ?></span></td>
                                            <td>
                                                <div class="fw-bold"><?php echo $row['name']; ?></div>
                                                <small class="text-muted"><?php echo $row['code']; ?></small>
                                            </td>
                                            <td class="text-center fw-bold text-danger"><?php echo $row['qty']; ?></td>
                                            <td><?php echo format_currency($row['amount']); ?></td>
                                            <td><?php echo $row['return_agent_reason'] ?: '<em>No reason provided</em>'; ?></td>
                                            <td><?php echo $row['agent']; ?></td>
                                            <td>
                                                <a href="invoice.php?invoice=<?php echo $row['invoice']; ?>"
                                                    class="btn btn-sm btn-outline-info">
                                                    View Invoice
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No returned items found for this
                                            period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function searchCustomerSales() {
            const query = document.getElementById('custSearchInput').value.trim();
            if (query.length < 2) {
                alert('Please enter at least 2 characters.');
                return;
            }

            const resultsDiv = document.getElementById('custSearchResults');
            resultsDiv.innerHTML = '<div class="text-center py-2"><span class="spinner-border spinner-border-sm text-primary"></span> Searching...</div>';

            fetch('../../ajax/search_sales.php?query=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.results.length > 0) {
                        let html = '<div class="list-group list-group-flush border mt-1 shadow-sm">';
                        data.results.forEach(sale => {
                            html += `
                            <a href="invoice.php?invoice=${sale.invoice}" class="list-group-item list-group-item-action p-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-primary">${sale.invoice}</span>
                                    <small class="text-muted">${sale.date}</small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>${sale.name}</span>
                                    <span class="fw-bold text-success">${sale.amount}</span>
                                </div>
                            </a>
                        `;
                        });
                        html += '</div>';
                        resultsDiv.innerHTML = html;
                    } else {
                        resultsDiv.innerHTML = '<div class="alert alert-secondary py-2 m-0 mt-1">No recent sales found for this customer.</div>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    resultsDiv.innerHTML = '<div class="alert alert-danger py-2 m-0 mt-1">Error searching sales.</div>';
                });
        }
    </script>
    <?php
    include '../../includes/footer.php';
    exit(); // Stop execution here
}

require_once '../../includes/audit_helper.php';

// Fetch Item details
$stmt = $conn->prepare("SELECT so.*, s.shop FROM sales_order so JOIN sales s ON so.invoice = s.invoice WHERE so.id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    die("Item not found");
}

$available_to_return = (int) $item['qty'] - (int) ($item['returned_qty'] ?? 0);

if ($available_to_return <= 0) {
    die("This item has already been fully returned.");
}

// Process Return Request
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['qty_return'])) {
    $reason = sanitizeInput($_POST['reason']);
    $qty_return = (int) $_POST['qty_return'];

    if ($qty_return <= 0 || $qty_return > $available_to_return) {
        $message = "Invalid quantity to return. Available: $available_to_return";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Update sales_order
            $is_fully_returned = ($qty_return == $available_to_return) ? 'Yes' : 'Partial';
            $new_returned_qty = (int) ($item['returned_qty'] ?? 0) + $qty_return;

            $update_order = $conn->prepare("UPDATE sales_order SET returned_item = ?, returned_qty = ?, return_agent_reason = ? WHERE id = ?");
            $update_order->bind_param("sisi", $is_fully_returned, $new_returned_qty, $reason, $order_id);
            if (!$update_order->execute())
                throw new Exception("Failed to update sales_order");

            // 2. Restock Product
            $code = $item['code'];
            $shop = $item['shop'] ?? 'Main Warehouse';

            // Find product id
            $prod_q = $conn->prepare("SELECT id, qty FROM products WHERE code = ?");
            $prod_q->bind_param("s", $code);
            $prod_q->execute();
            $prod_res = $prod_q->get_result();
            $prod = $prod_res->fetch_assoc();

            if ($prod) {
                $product_id = $prod['id'];

                if (!$shop || $shop == 'Main Warehouse') {
                    $stmt_restock = $conn->prepare("UPDATE products SET qty = qty + ? WHERE id = ?");
                    $stmt_restock->bind_param("ii", $qty_return, $product_id);
                } else {
                    $stmt_restock = $conn->prepare("INSERT INTO product_stock (product_id, shop_id, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = qty + ?");
                    $stmt_restock->bind_param("isii", $product_id, $shop, $qty_return, $qty_return);
                }
                if (!$stmt_restock->execute())
                    throw new Exception("Failed to restock items");
            }

            // 3. Update Sales Total (Refund value based on unit price)
            $unit_price = (float) $item['price'];
            $refund_amount = $unit_price * $qty_return;

            $update_sales = $conn->prepare("UPDATE sales SET amount = amount - ? WHERE invoice = ?");
            $update_sales->bind_param("ds", $refund_amount, $invoice_id);
            if (!$update_sales->execute())
                throw new Exception("Failed to update sales amount");

            // 4. Audit Log
            logAction('ITEM_RETURNED', [
                'invoice' => $invoice_id,
                'product' => $item['name'],
                'qty' => $qty_return,
                'refund' => $refund_amount,
                'shop' => $shop,
                'reason' => $reason
            ]);

            $conn->commit();
            header("Location: invoice.php?invoice=" . $invoice_id . "&returned=1");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Return Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-5">
    <div class="card mx-auto" style="max-width: 500px;">
        <div class="card-header">
            <h4>Process Return</h4>
        </div>
        <div class="card-body">
            <?php if ($message)
                echo "<div class='alert alert-danger'>$message</div>"; ?>

            <p><strong>Item:</strong>
                <?php echo $item['name']; ?>
            </p>
            <p><strong>Total Sold:</strong>
                <?php echo $item['qty']; ?>
            </p>
            <p><strong>Already Returned:</strong>
                <span class="text-danger"><?php echo (int) ($item['returned_qty'] ?? 0); ?></span>
            </p>
            <p><strong>Price:</strong>
                <?php echo format_currency($item['price']); ?>
            </p>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Quantity to Return</label>
                    <input type="number" name="qty_return" class="form-control"
                        value="<?php echo $available_to_return; ?>" min="1" max="<?php echo $available_to_return; ?>"
                        required>
                    <div class="form-text">Max available: <?php echo $available_to_return; ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for Return</label>
                    <textarea name="reason" class="form-control" rows="3" required
                        placeholder="e.g. Defective, Expired, Customer changed mind"></textarea>
                </div>
                <button type="submit" class="btn btn-danger w-100">Confirm Return & Restock</button>
                <a href="invoice.php?invoice=<?php echo $invoice_id; ?>" class="btn btn-secondary w-100 mt-2">Cancel</a>
            </form>
        </div>
    </div>
</body>

</html>