<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch Income (e.g., from Sales or explicit income table)
// For MVP, letting this be a view of 'Sales' as income + 'Income' table if used.
// Let's just show Sales as Income for now to keep it populated.
$today = date('Y-m-d');
$sql = "SELECT * FROM sales ORDER BY id DESC LIMIT 50";
$result = $conn->query($sql);

?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Finance & Income</h2>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Total Income (Today)</h5>
                <!-- Simple sum for demo -->
                <?php
                $res = $conn->query("SELECT SUM(amount) as total FROM sales WHERE date = '$today'");
                $inc = $res->fetch_assoc()['total'] ?? 0;
                echo "<h3>$" . number_format($inc, 2) . "</h3>";
                ?>
            </div>
        </div>
    </div>
    <!-- Expense placeholder -->
    <div class="col-md-4">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h5 class="card-title">Total Expenses (Today)</h5>
                <h3>$0.00</h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link active" href="#">Income Records</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="expenses.php">Expenses</a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <h5 class="card-title mb-3">Recent Income (Sales)</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Source/Invoice</th>
                        <th>Amount</th>
                        <th>Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo $row['invoice']; ?></td>
                                <td class="text-success">+<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo $row['agent']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
