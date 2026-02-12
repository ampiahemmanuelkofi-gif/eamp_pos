<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';
$user = $_SESSION['username'];
$today = date('Y-m-d');
$shop = $_GET['shop'] ?? 'Main';

// Handle Open/Close Register
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'open') {
        $opening = (float) $_POST['opening_balance'];

        // Check if already open
        $check = $conn->query("SELECT id FROM cash_register WHERE date = '$today' AND shop_id = '$shop'");
        if ($check->num_rows > 0) {
            $message = "<div class='alert alert-warning'>Register already opened for today.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO cash_register (date, shop_id, opening_balance, opened_by, status) VALUES (?, ?, ?, ?, 'Open')");
            $stmt->bind_param("ssds", $today, $shop, $opening, $user);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Register opened with balance: " . number_format($opening, 2) . "</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error opening register.</div>";
            }
        }
    } elseif ($action == 'close') {
        $closing = (float) $_POST['closing_balance'];
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $reg_id = (int) $_POST['register_id'];

        // Calculate expected balance
        // Expected = Opening + Sales Income - Expenses (for today)
        $reg = $conn->query("SELECT opening_balance FROM cash_register WHERE id = $reg_id")->fetch_assoc();
        $opening = $reg['opening_balance'];

        // Today's cash sales (assuming all sales are cash for simplicity, or filter by payment_method='Cash')
        $sales_res = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM sales WHERE date = '$today'");
        $sales_total = $sales_res->fetch_assoc()['total'];

        // Today's expenses
        $exp_res = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE(date_time) = '$today'");
        $exp_total = $exp_res->fetch_assoc()['total'];

        $expected = $opening + $sales_total - $exp_total;
        $difference = $closing - $expected;

        $stmt = $conn->prepare("UPDATE cash_register SET closing_balance = ?, expected_balance = ?, difference = ?, notes = ?, closed_by = ?, status = 'Closed' WHERE id = ?");
        $stmt->bind_param("dddssi", $closing, $expected, $difference, $notes, $user, $reg_id);

        if ($stmt->execute()) {
            $diff_class = $difference >= 0 ? 'success' : 'danger';
            $message = "<div class='alert alert-success'>Register closed. Difference: <span class='text-$diff_class fw-bold'>" . number_format($difference, 2) . "</span></div>";
        } else {
            $message = "<div class='alert alert-danger'>Error closing register.</div>";
        }
    }
}

// Get Today's Register Status
$reg_check = $conn->query("SELECT * FROM cash_register WHERE date = '$today' AND shop_id = '$shop'");
$register = $reg_check->fetch_assoc();

// Fetch Shops
$shops = $conn->query("SELECT * FROM shops");
$shop_list = [];
while ($s = $shops->fetch_assoc()) {
    $shop_list[] = $s['name'];
}

// Fetch History
$history = $conn->query("SELECT * FROM cash_register WHERE shop_id = '$shop' ORDER BY date DESC LIMIT 14");
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Daily Cash Reconciliation</h2>
        <p class="text-muted">Open and close the cash register daily.</p>
    </div>
    <div class="col-md-4 text-end">
        <form method="GET" class="d-inline">
            <select name="shop" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                <option value="Main" <?php if ($shop == 'Main')
                    echo 'selected'; ?>>Main</option>
                <?php foreach ($shop_list as $s): ?>
                    <option value="<?php echo $s; ?>" <?php if ($shop == $s)
                           echo 'selected'; ?>>
                        <?php echo $s; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-5">
        <!-- Current Day Status -->
        <div class="card mb-4">
            <div
                class="card-header bg-<?php echo !$register ? 'warning' : ($register['status'] == 'Open' ? 'success' : 'secondary'); ?> text-white">
                <h5 class="mb-0">Today:
                    <?php echo date('M d, Y'); ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!$register): ?>
                    <!-- Open Register Form -->
                    <p class="text-muted">Register is not open. Start the day by opening the register.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="open">
                        <div class="mb-3">
                            <label class="form-label">Opening Cash Balance</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" value="0" required>
                            <small class="text-muted">Enter the physical cash counted in the register.</small>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Open Register</button>
                    </form>

                <?php elseif ($register['status'] == 'Open'): ?>
                    <!-- Close Register Form -->
                    <div class="mb-3">
                        <strong>Opening Balance:</strong>
                        <?php echo number_format($register['opening_balance'], 2); ?><br>
                        <strong>Opened By:</strong>
                        <?php echo $register['opened_by']; ?>
                    </div>
                    <hr>
                    <form method="POST">
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="register_id" value="<?php echo $register['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Closing Cash Balance (Physical Count)</label>
                            <input type="number" step="0.01" name="closing_balance" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Close Register</button>
                    </form>

                <?php else: ?>
                    <!-- Already Closed -->
                    <div class="text-center">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-2">Register Closed</h5>
                        <table class="table table-sm mt-3">
                            <tr>
                                <td>Opening:</td>
                                <td class="text-end">
                                    <?php echo number_format($register['opening_balance'], 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Expected:</td>
                                <td class="text-end">
                                    <?php echo number_format($register['expected_balance'], 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Actual:</td>
                                <td class="text-end">
                                    <?php echo number_format($register['closing_balance'], 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Difference:</strong></td>
                                <td
                                    class="text-end <?php echo $register['difference'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                    <?php echo number_format($register['difference'], 2); ?>
                                </td>
                            </tr>
                        </table>
                        <small class="text-muted">Closed by
                            <?php echo $register['closed_by']; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent History (
                    <?php echo $shop; ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Opening</th>
                            <th class="text-end">Expected</th>
                            <th class="text-end">Actual</th>
                            <th class="text-end">Diff</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($h = $history->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $h['date']; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($h['opening_balance'], 2); ?>
                                </td>
                                <td class="text-end">
                                    <?php echo $h['expected_balance'] !== null ? number_format($h['expected_balance'], 2) : '-'; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo $h['closing_balance'] !== null ? number_format($h['closing_balance'], 2) : '-'; ?>
                                </td>
                                <td
                                    class="text-end <?php echo ($h['difference'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $h['difference'] !== null ? number_format($h['difference'], 2) : '-'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $h['status'] == 'Open' ? 'success' : 'secondary'; ?>">
                                        <?php echo $h['status']; ?>
                                    </span>
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