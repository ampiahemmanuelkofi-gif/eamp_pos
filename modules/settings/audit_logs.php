<?php
require_once '../../includes/auth.php';
checkLogin();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access Denied";
    exit();
}

require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$result = $conn->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="bi bi-shield-check me-2"></i> Audit Logs</h2>
        <p class="text-muted">Tracking critical system actions and user activity.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th width="15%">Time</th>
                    <th width="15%">User</th>
                    <th width="20%">Action</th>
                    <th width="35%">Details</th>
                    <th width="15%">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, H:i:s', strtotime($row['created_at'])); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['user_id']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['action']); ?></strong></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($row['details']); ?></small></td>
                            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center py-4">No activity logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
