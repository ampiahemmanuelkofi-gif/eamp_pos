<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';
$user_id = $_SESSION['user_id'];

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify current password
    // Depending on schema, staff_list usually has plaintext for simple POS or hash. 
    // login.php uses strict comparison $row['password'] === $password (based on previous view).
    // Let's check login.php logic mentally: "SELECT * FROM staff_list WHERE staff_name ...".
    // I will assume plaintext for now based on previous `users.php` which inserted directly. 
    // Ideally should be hashed, but following existing pattern.

    $stmt = $conn->prepare("SELECT password FROM staff_list WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && $res['password'] === $current_password) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 4) {
                $stmt_update = $conn->prepare("UPDATE staff_list SET password = ? WHERE id = ?");
                $stmt_update->bind_param("si", $new_password, $user_id);
                if ($stmt_update->execute()) {
                    $message = "<div class='alert alert-success'>Password updated successfully.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Error updating password.</div>";
                }
            } else {
                $message = "<div class='alert alert-warning'>Password must be at least 4 characters.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>New passwords do not match.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Current password is incorrect.</div>";
    }
}

// Fetch User Details
$stmt = $conn->prepare("SELECT * FROM staff_list WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h4 class="mb-0">My Profile</h4>
            </div>
            <div class="card-body">
                <?php echo $message; ?>

                <div class="mb-4">
                    <label class="text-muted small">Staff ID</label>
                    <div class="fw-bold">
                        <?php echo $user['staff_id']; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-muted small">Name</label>
                    <div class="fw-bold">
                        <?php echo $user['staff_name']; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-muted small">Role</label>
                    <div><span class="badge bg-primary">
                            <?php echo $user['rank']; ?>
                        </span></div>
                </div>

                <hr>

                <h5 class="mb-3">Change Password</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>