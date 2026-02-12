<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';
$user = $_SESSION['username'];

// Pre-select from GET params
$deposit_to = $_GET['deposit'] ?? null;
$withdraw_from = $_GET['withdraw'] ?? null;

// Handle Transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = sanitizeInput($_POST['type']); // Deposit, Withdrawal, Transfer
    $amount = (float) $_POST['amount'];
    $description = sanitizeInput($_POST['description'] ?? '');
    $reference = sanitizeInput($_POST['reference'] ?? '');

    if ($amount <= 0) {
        $message = "<div class='alert alert-danger'>Amount must be greater than 0.</div>";
    } else {
        $conn->begin_transaction();
        try {
            if ($type == 'Deposit') {
                $to_id = (int) $_POST['to_account'];
                // Update Balance
                $conn->query("UPDATE bank_accounts SET balance = balance + $amount WHERE id = $to_id");
                // Log Transaction
                $stmt = $conn->prepare("INSERT INTO fund_transactions (type, to_account_id, amount, description, reference, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sidsss", $type, $to_id, $amount, $description, $reference, $user);
                $stmt->execute();

            } elseif ($type == 'Withdrawal') {
                $from_id = (int) $_POST['from_account'];
                // Check balance
                $bal = $conn->query("SELECT balance FROM bank_accounts WHERE id = $from_id")->fetch_assoc()['balance'];
                if ($bal < $amount) {
                    throw new Exception("Insufficient balance.");
                }
                // Update Balance
                $conn->query("UPDATE bank_accounts SET balance = balance - $amount WHERE id = $from_id");
                // Log Transaction
                $stmt = $conn->prepare("INSERT INTO fund_transactions (type, from_account_id, amount, description, reference, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sidsss", $type, $from_id, $amount, $description, $reference, $user);
                $stmt->execute();

            } elseif ($type == 'Transfer') {
                $from_id = (int) $_POST['from_account'];
                $to_id = (int) $_POST['to_account'];

                if ($from_id == $to_id) {
                    throw new Exception("Cannot transfer to the same account.");
                }
                // Check balance
                $bal = $conn->query("SELECT balance FROM bank_accounts WHERE id = $from_id")->fetch_assoc()['balance'];
                if ($bal < $amount) {
                    throw new Exception("Insufficient balance in source account.");
                }
                // Update Balances
                $conn->query("UPDATE bank_accounts SET balance = balance - $amount WHERE id = $from_id");
                $conn->query("UPDATE bank_accounts SET balance = balance + $amount WHERE id = $to_id");
                // Log Transaction
                $stmt = $conn->prepare("INSERT INTO fund_transactions (type, from_account_id, to_account_id, amount, description, reference, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siidsss", $type, $from_id, $to_id, $amount, $description, $reference, $user);
                $stmt->execute();
            }

            $conn->commit();
            $message = "<div class='alert alert-success'>$type recorded successfully.</div>";

        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch Accounts
$accounts = $conn->query("SELECT * FROM bank_accounts WHERE status = 'Active' ORDER BY name");
$account_list = [];
while ($a = $accounts->fetch_assoc()) {
    $account_list[] = $a;
}

// Fetch Recent Transactions
$recent = $conn->query("SELECT ft.*, 
    fa.name as from_name, ta.name as to_name 
    FROM fund_transactions ft
    LEFT JOIN bank_accounts fa ON ft.from_account_id = fa.id
    LEFT JOIN bank_accounts ta ON ft.to_account_id = ta.id
    ORDER BY ft.id DESC LIMIT 20");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Fund Transfers</h2>
        <p class="text-muted">Deposit, withdraw, or transfer funds between accounts.</p>
    </div>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-5">
        <!-- Transaction Form -->
        <div class="card">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="txnTabs" role="tablist">
                    <li class="nav-item">
                        <button
                            class="nav-link <?php echo $deposit_to ? 'active' : ($withdraw_from ? '' : 'active'); ?>"
                            data-bs-toggle="tab" data-bs-target="#deposit">Deposit</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo $withdraw_from ? 'active' : ''; ?>" data-bs-toggle="tab"
                            data-bs-target="#withdraw">Withdraw</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#transfer">Transfer</button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Deposit Tab -->
                    <div class="tab-pane fade <?php echo $deposit_to ? 'show active' : ($withdraw_from ? '' : 'show active'); ?>"
                        id="deposit">
                        <form method="POST">
                            <input type="hidden" name="type" value="Deposit">
                            <div class="mb-3">
                                <label class="form-label">To Account</label>
                                <select name="to_account" class="form-select" required>
                                    <?php foreach ($account_list as $a): ?>
                                        <option value="<?php echo $a['id']; ?>" <?php if ($deposit_to == $a['id'])
                                               echo 'selected'; ?>>
                                            <?php echo $a['name']; ?> (
                                            <?php echo number_format($a['balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference</label>
                                <input type="text" name="reference" class="form-control" placeholder="e.g. Check #123">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Record Deposit</button>
                        </form>
                    </div>

                    <!-- Withdraw Tab -->
                    <div class="tab-pane fade <?php echo $withdraw_from ? 'show active' : ''; ?>" id="withdraw">
                        <form method="POST">
                            <input type="hidden" name="type" value="Withdrawal">
                            <div class="mb-3">
                                <label class="form-label">From Account</label>
                                <select name="from_account" class="form-select" required>
                                    <?php foreach ($account_list as $a): ?>
                                        <option value="<?php echo $a['id']; ?>" <?php if ($withdraw_from == $a['id'])
                                               echo 'selected'; ?>>
                                            <?php echo $a['name']; ?> (
                                            <?php echo number_format($a['balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference</label>
                                <input type="text" name="reference" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">Record Withdrawal</button>
                        </form>
                    </div>

                    <!-- Transfer Tab -->
                    <div class="tab-pane fade" id="transfer">
                        <form method="POST">
                            <input type="hidden" name="type" value="Transfer">
                            <div class="mb-3">
                                <label class="form-label">From Account</label>
                                <select name="from_account" class="form-select" required>
                                    <?php foreach ($account_list as $a): ?>
                                        <option value="<?php echo $a['id']; ?>">
                                            <?php echo $a['name']; ?> (
                                            <?php echo number_format($a['balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">To Account</label>
                                <select name="to_account" class="form-select" required>
                                    <?php foreach ($account_list as $a): ?>
                                        <option value="<?php echo $a['id']; ?>">
                                            <?php echo $a['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Transfer Funds</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Transactions</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th class="text-end">Amount</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($txn = $recent->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo date('M d', strtotime($txn['date_created'])); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                    echo $txn['type'] == 'Deposit' ? 'success' :
                                        ($txn['type'] == 'Withdrawal' ? 'danger' : 'primary');
                                    ?>">
                                        <?php echo $txn['type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $txn['from_name'] ?: '-'; ?>
                                </td>
                                <td>
                                    <?php echo $txn['to_name'] ?: '-'; ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo number_format($txn['amount'], 2); ?>
                                </td>
                                <td>
                                    <?php echo $txn['user_id']; ?>
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