<?php
require_once '../../includes/auth.php';
checkLogin();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access Denied";
    exit();
}

require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/audit_helper.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $name = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $shop = $_POST['shop'];
        // Schema: staff_id, staff_name, password, rank, shop
        // Generate a staff ID
        $staff_id = 'ST' . rand(1000, 9999);

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO staff_list (staff_id, staff_name, password, rank, shop) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $staff_id, $name, $hashed_password, $role, $shop);
        if ($stmt->execute()) {
            logAction('USER_ADDED', "Added user: $name ($role) to $shop");
            $message = "<div class='alert alert-success'>User added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error adding user.</div>";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = $_POST['id'];
        $name = sanitizeInput($_POST['username']);
        $role = $_POST['role'];
        $shop = $_POST['shop'];
        $password = $_POST['password'];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE staff_list SET staff_name = ?, password = ?, rank = ?, shop = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $hashed_password, $role, $shop, $id);
        } else {
            $stmt = $conn->prepare("UPDATE staff_list SET staff_name = ?, rank = ?, shop = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $role, $shop, $id);
        }

        if ($stmt->execute()) {
            logAction('USER_UPDATED', "Updated user ID: $id ($name)");
            $message = "<div class='alert alert-success'>User updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating user.</div>";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM staff_list WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            logAction('USER_DELETED', "Deleted user ID: $id");
            $message = "<div class='alert alert-success'>User deleted successfully.</div>";
        }
    }
}

// Fetch Shops for dropdown
$shops_list = $conn->query("SELECT name FROM shops ORDER BY name");

// Fetch Users
$result = $conn->query("SELECT * FROM staff_list");
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2><i class="bi bi-people-fill me-2"></i> User Management</h2>
        <p class="text-muted mb-0">Manage staff accounts, roles, and shop assignments.</p>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="collapse" data-bs-target="#addUserForm">
            <i class="bi bi-plus-circle me-1"></i> Add New User
        </button>
    </div>
</div>

<?php echo $message; ?>

<div class="collapse mb-4" id="addUserForm">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0">Create Staff Account</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Username</label>
                        <input type="text" name="username" class="form-control form-control-sm" placeholder="Username"
                            required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Password</label>
                        <input type="password" name="password" class="form-control form-control-sm"
                            placeholder="Password" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Role</label>
                        <select name="role" class="form-select form-select-sm">
                            <option value="cashier">Cashier</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Assigned Shop</label>
                        <select name="shop" class="form-select form-select-sm" required>
                            <option value="">-- Select Shop --</option>
                            <?php $shops_list->data_seek(0);
                            while ($s = $shops_list->fetch_assoc()): ?>
                                <option value="<?php echo $s['name']; ?>"><?php echo $s['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary px-4">Create User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Staff List</h5>
        <div class="badge bg-light text-dark border">Total: <?php echo $result->num_rows; ?></div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase small fw-bold">
                    <tr>
                        <th class="ps-4">Staff ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Shop Assignment</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 font-monospace small">
                                <span class="badge bg-light text-dark border"><?php echo $row['staff_id']; ?></span>
                            </td>
                            <td class="fw-bold">
                                <?php echo $row['staff_name']; ?>
                            </td>
                            <td>
                                <?php
                                $role_colors = ['admin' => 'danger', 'manager' => 'warning', 'cashier' => 'info'];
                                $color = $role_colors[strtolower($row['rank'])] ?? 'secondary';
                                ?>
                                <span
                                    class="badge rounded-pill bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> px-3">
                                    <?php echo ucfirst($row['rank']); ?>
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-shop me-2 text-muted"></i><?php echo $row['shop'] ?: 'Unassigned'; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm">
                                    <button class="btn btn-sm btn-white edit-user-btn" data-id="<?php echo $row['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['staff_name']); ?>"
                                        data-role="<?php echo $row['rank']; ?>"
                                        data-shop="<?php echo htmlspecialchars($row['shop']); ?>" data-bs-toggle="modal"
                                        data-bs-target="#editUserModal">
                                        <i class="bi bi-pencil text-primary"></i>
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('Delete user?');"
                                        class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-white">
                                            <i class="bi bi-trash text-danger"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="editUserName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="editUserRole" class="form-select">
                            <option value="cashier">Cashier</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assigned Shop</label>
                        <select name="shop" id="editUserShop" class="form-select" required>
                            <option value="">-- Select Shop --</option>
                            <?php
                            $shops_list->data_seek(0);
                            while ($s = $shops_list->fetch_assoc()):
                                ?>
                                <option value="<?php echo $s['name']; ?>"><?php echo $s['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('editUserId').value = this.dataset.id;
            document.getElementById('editUserName').value = this.dataset.name;
            document.getElementById('editUserRole').value = this.dataset.role;
            document.getElementById('editUserShop').value = this.dataset.shop;
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>