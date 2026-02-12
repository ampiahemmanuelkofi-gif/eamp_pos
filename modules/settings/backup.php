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
$message = '';

// Backup Directory
$backup_dir = '../../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle Backup
if (isset($_POST['action']) && $_POST['action'] == 'backup') {
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;

    // Get all tables
    $tables_result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $tables_result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sql_dump = "-- EAMP POS Database Backup\n";
    $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Server: " . $conn->server_info . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $create[1] . ";\n\n";

        // Table data
        $data = $conn->query("SELECT * FROM `$table`");
        while ($row = $data->fetch_assoc()) {
            $values = array_map(function ($val) use ($conn) {
                return $val === null ? 'NULL' : "'" . $conn->real_escape_string($val) . "'";
            }, array_values($row));
            $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql_dump .= "\n";
    }

    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    if (file_put_contents($filepath, $sql_dump)) {
        $message = "<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>Backup created: <strong>$filename</strong></div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to create backup.</div>";
    }
}

// Handle Restore
if (isset($_POST['action']) && $_POST['action'] == 'restore') {
    $restore_file = $_POST['restore_file'];
    $filepath = $backup_dir . $restore_file;

    if (file_exists($filepath)) {
        $sql_content = file_get_contents($filepath);

        // Split by semicolons and execute
        $conn->multi_query($sql_content);

        // Clear results
        while ($conn->next_result()) {
            ;
        }

        $message = "<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>Database restored from: <strong>$restore_file</strong></div>";
    } else {
        $message = "<div class='alert alert-danger'>Backup file not found.</div>";
    }
}

// Handle Download
if (isset($_GET['download'])) {
    $file = $backup_dir . basename($_GET['download']);
    if (file_exists($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $file = $backup_dir . basename($_GET['delete']);
    if (file_exists($file)) {
        unlink($file);
        $message = "<div class='alert alert-success'>Backup deleted.</div>";
    }
}

// Get existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    // Sort by date descending
    usort($backups, function ($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-database me-2"></i> Backup & Restore</h2>
        <p class="text-muted">Create database backups and restore from previous backups.</p>
    </div>
    <div class="col-md-4 text-end">
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="backup">
            <button type="submit" class="btn btn-success" onclick="return confirm('Create a new backup now?');">
                <i class="bi bi-cloud-arrow-up me-1"></i> Create Backup
            </button>
        </form>
    </div>
</div>

<?php echo $message; ?>

<!-- Info Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3>
                    <?php echo count($backups); ?>
                </h3>
                <div>Available Backups</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3>
                    <?php
                    $total_size = array_sum(array_column($backups, 'size'));
                    echo round($total_size / 1024 / 1024, 2) . ' MB';
                    ?>
                </h3>
                <div>Total Backup Size</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3>
                    <?php
                    echo !empty($backups) ? date('M d, H:i', $backups[0]['date']) : 'Never';
                    ?>
                </h3>
                <div>Last Backup</div>
            </div>
        </div>
    </div>
</div>

<!-- Backup List -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Backup History</h5>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Date Created</th>
                    <th>Size</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($backups) > 0): ?>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><i class="bi bi-file-earmark-code me-2"></i>
                                <?php echo $backup['name']; ?>
                            </td>
                            <td>
                                <?php echo date('Y-m-d H:i:s', $backup['date']); ?>
                            </td>
                            <td>
                                <?php echo round($backup['size'] / 1024, 2); ?> KB
                            </td>
                            <td>
                                <a href="?download=<?php echo urlencode($backup['name']); ?>"
                                    class="btn btn-sm btn-outline-primary" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                                <form method="POST" class="d-inline"
                                    onsubmit="return confirm('RESTORE from this backup? Current data will be OVERWRITTEN!');">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="restore_file" value="<?php echo $backup['name']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Restore">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                                <a href="?delete=<?php echo urlencode($backup['name']); ?>"
                                    class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this backup?');"
                                    title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="bi bi-cloud-slash" style="font-size: 2rem;"></i>
                            <p class="mb-0 mt-2">No backups found. Create your first backup.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-warning mt-4">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Important:</strong> Regularly backup your database to prevent data loss. Store backups in a secure location.
</div>

<?php require_once '../../includes/footer.php'; ?>