<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitizeInput($_POST['title']);
    $amount = sanitizeInput($_POST['amount']);
    $description = sanitizeInput($_POST['description']);
    $user = $_SESSION['username'];
    
    // Schema: expenses table. Columns: expenses (title/type?), amount, about_expenses, registered_by, date_time
    $stmt = $conn->prepare("INSERT INTO expenses (expenses, amount, about_expenses, registered_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $amount, $description, $user);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Expense recorded successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}

// Fetch Recent Expenses
$result = $conn->query("SELECT * FROM expenses ORDER BY id DESC LIMIT 20");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Expense Management</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Record Expense</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Expense Title/Type</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Utility Bill" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description/Notes</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Record Expense</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Expenses</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['expenses']; ?></td>
                                <td class="text-danger">-$<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo $row['about_expenses']; ?></td>
                                <td><?php echo $row['registered_by']; ?></td>
                                <td><?php echo $row['date_time']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">No expenses recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
