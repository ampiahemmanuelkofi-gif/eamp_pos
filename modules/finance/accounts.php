<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle Add/Edit Account
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'add';
    $name = sanitizeInput($_POST['name']);
    $account_number = sanitizeInput($_POST['account_number'] ?? '');
    $bank_name = sanitizeInput($_POST['bank_name'] ?? '');
    $account_type = sanitizeInput($_POST['account_type']);
    $balance = (float) ($_POST['balance'] ?? 0);

    if ($action == 'add') {
        $stmt = $conn->prepare("INSERT INTO bank_accounts (name, account_number, bank_name, account_type, balance) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $name, $account_number, $bank_name, $account_type, $balance);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Account added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    } elseif ($action == 'edit') {
        $id = (int) $_POST['account_id'];
        $stmt = $conn->prepare("UPDATE bank_accounts SET name = ?, account_number = ?, bank_name = ?, account_type = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $account_number, $bank_name, $account_type, $id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Account updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    }
}

// Delete Account
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    // Don't allow deleting default account
    $check = $conn->query("SELECT is_default FROM bank_accounts WHERE id = $id");
    $row = $check->fetch_assoc();
    if ($row && $row['is_default'] == 1) {
        $message = "<div class='alert alert-danger'>Cannot delete the default account.</div>";
    } else {
        $conn->query("DELETE FROM bank_accounts WHERE id = $id");
        $message = "<div class='alert alert-success'>Account deleted.</div>";
    }
}

// Fetch Accounts
$accounts = $conn->query("SELECT * FROM bank_accounts ORDER BY is_default DESC, name ASC");

// Calculate Total Balance
$total_res = $conn->query("SELECT SUM(balance) as total FROM bank_accounts WHERE status = 'Active'");
$total_balance = $total_res->fetch_assoc()['total'] ?? 0;
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Bank Accounts</h2>
        <p class="text-muted">Manage your cash, bank, and mobile money accounts.</p>
    </div>
    <div class="col-md-4 text-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="bi bi-plus-circle me-1"></i> Add Account
        </button>
    </div>
</div>

<!-- Summary -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Balance (All Accounts)</h6>
                <h3 class="mb-0">
                    <?php echo number_format($total_balance, 2); ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th>Bank</th>
                    <th>Account #</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($acc = $accounts->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo $acc['name']; ?>
                            <?php if ($acc['is_default']): ?>
                                <span class="badge bg-info">Default</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $acc['account_type']; ?>
                        </td>
                        <td>
                            <?php echo $acc['bank_name'] ?: '-'; ?>
                        </td>
                        <td>
                            <?php echo $acc['account_number'] ?: '-'; ?>
                        </td>
                        <td class="text-end fw-bold <?php echo $acc['balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo number_format($acc['balance'], 2); ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $acc['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                <?php echo $acc['status']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="transfer.php?deposit=<?php echo $acc['id']; ?>" class="btn btn-sm btn-outline-success"
                                title="Deposit">
                                <i class="bi bi-arrow-down-circle"></i>
                            </a>
                            <a href="transfer.php?withdraw=<?php echo $acc['id']; ?>" class="btn btn-sm btn-outline-danger"
                                title="Withdraw">
                                <i class="bi bi-arrow-up-circle"></i>
                            </a>
                            <?php if (!$acc['is_default']): ?>
                                <a href="?delete=<?php echo $acc['id']; ?>" class="btn btn-sm btn-outline-secondary"
                                    onclick="return confirm('Delete this account?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Bank Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Account Name *</label>
                        <input type="text" name="name" class="form-control" required
                            placeholder="e.g. Petty Cash, GTBank">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Type *</label>
                        <select name="account_type" class="form-select" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                            <option value="Mobile Money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. GTBank, Stanbic">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opening Balance</label>
                        <input type="number" step="0.01" name="balance" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>