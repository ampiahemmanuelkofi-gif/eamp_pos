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

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add') {
        $name = sanitizeInput($_POST['name']);
        $serial = sanitizeInput($_POST['serial'] ?? '');
        $details = sanitizeInput($_POST['details'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $manager = sanitizeInput($_POST['manager'] ?? '');

        // Check for columns, add if missing
        $conn->query("ALTER TABLE shops ADD COLUMN IF NOT EXISTS address varchar(255) DEFAULT NULL");
        $conn->query("ALTER TABLE shops ADD COLUMN IF NOT EXISTS phone varchar(50) DEFAULT NULL");
        $conn->query("ALTER TABLE shops ADD COLUMN IF NOT EXISTS manager varchar(100) DEFAULT NULL");
        $conn->query("ALTER TABLE shops ADD COLUMN IF NOT EXISTS status varchar(20) DEFAULT 'Active'");

        $stmt = $conn->prepare("INSERT INTO shops (name, serial, shop_details, address, phone, manager, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("ssssss", $name, $serial, $details, $address, $phone, $manager);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Shop added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }

    } elseif ($action == 'edit') {
        $id = (int) $_POST['id'];
        $name = sanitizeInput($_POST['name']);
        $serial = sanitizeInput($_POST['serial'] ?? '');
        $details = sanitizeInput($_POST['details'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $manager = sanitizeInput($_POST['manager'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'Active');

        $stmt = $conn->prepare("UPDATE shops SET name = ?, serial = ?, shop_details = ?, address = ?, phone = ?, manager = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $name, $serial, $details, $address, $phone, $manager, $status, $id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Shop updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating shop.</div>";
        }

    } elseif ($action == 'delete') {
        $id = (int) $_POST['id'];
        // Check if shop has stock or sales first
        $name_res = $conn->query("SELECT name FROM shops WHERE id = $id")->fetch_assoc();
        $shop_name = $name_res['name'];

        $stock_check = $conn->query("SELECT COUNT(*) as cnt FROM product_stock WHERE shop_id = '$shop_name'")->fetch_assoc()['cnt'];
        if ($stock_check > 0) {
            $message = "<div class='alert alert-danger'>Cannot delete shop with existing inventory. Transfer stock first.</div>";
        } else {
            $conn->query("DELETE FROM shops WHERE id = $id");
            $message = "<div class='alert alert-success'>Shop deleted.</div>";
        }
    }
}

// Fetch Shops with performance data
$shops_sql = "SELECT s.*, 
    (SELECT COALESCE(SUM(amount), 0) FROM sales WHERE shop = s.name) as total_sales,
    (SELECT COUNT(*) FROM sales WHERE shop = s.name) as transaction_count,
    (SELECT COALESCE(SUM(qty), 0) FROM product_stock WHERE shop_id = s.name) as stock_count
    FROM shops s ORDER BY s.name";
$result = $conn->query($shops_sql);

// Summary
$total_shops = $conn->query("SELECT COUNT(*) as cnt FROM shops")->fetch_assoc()['cnt'];
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2><i class="bi bi-shop me-2"></i> Multi-Shop Management</h2>
        <p class="text-muted mb-0">Configure and manage all shop locations and their performance.</p>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary btn-lg shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addShopModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Shop
        </button>
        <a href="../reports/shop_comparison.php" class="btn btn-outline-info btn-lg shadow-sm px-4">
            <i class="bi bi-bar-chart me-1"></i> Performance
        </a>
    </div>
</div>

<?php echo $message; ?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-white bg-opacity-20 p-3 me-3">
                    <i class="bi bi-building fs-4"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?php echo $total_shops; ?></h4>
                    <small class="text-white-50 text-uppercase small fw-bold">Active Shops</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shops List -->
<div class="row">
    <?php while ($shop = $result->fetch_assoc()): ?>
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 shop-card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 bg-light p-2 me-3">
                            <i class="bi bi-shop fs-4 text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold"><?php echo $shop['name']; ?></h5>
                            <code class="small text-primary"><?php echo $shop['serial'] ?: 'NO_CODE'; ?></code>
                        </div>
                    </div>
                    <?php
                    $status_color = (isset($shop['status']) && $shop['status'] == 'Active') ? 'success' : 'secondary';
                    ?>
                    <span
                        class="badge rounded-pill bg-<?php echo $status_color; ?> bg-opacity-10 text-<?php echo $status_color; ?> px-3">
                        <i class="bi bi-circle-fill me-1 small"></i> <?php echo $shop['status'] ?? 'Active'; ?>
                    </span>
                </div>
                <div class="card-body py-2">
                    <div class="row g-2 mb-4">
                        <div class="col-4">
                            <div class="bg-light rounded-3 p-3 text-center h-100">
                                <h5 class="text-success mb-0 fw-bold"><?php echo format_currency($shop['total_sales']); ?>
                                </h5>
                                <small class="text-muted small text-uppercase fw-bold">Sales</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded-3 p-3 text-center h-100">
                                <h4 class="text-primary mb-0 fw-bold"><?php echo $shop['transaction_count']; ?></h4>
                                <small class="text-muted small text-uppercase fw-bold">TXs</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded-3 p-3 text-center h-100">
                                <h4 class="text-info mb-0 fw-bold"><?php echo $shop['stock_count']; ?></h4>
                                <small class="text-muted small text-uppercase fw-bold">Stock</small>
                            </div>
                        </div>
                    </div>

                    <div class="px-2">
                        <p class="mb-2 small"><i class="bi bi-geo-alt me-2 text-primary"></i>
                            <strong>Address:</strong> <?php echo $shop['address'] ?: 'Not specified'; ?>
                        </p>
                        <p class="mb-2 small"><i class="bi bi-telephone me-2 text-primary"></i>
                            <strong>Contact:</strong> <?php echo $shop['phone'] ?: 'N/A'; ?>
                        </p>
                        <p class="mb-0 small"><i class="bi bi-person-badge me-2 text-primary"></i>
                            <strong>Manager:</strong>
                            <?php echo $shop['manager'] ?: '<span class="text-muted italic">Unassigned</span>'; ?>
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 py-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-grow-1 edit-shop-btn"
                        data-id="<?php echo $shop['id']; ?>" data-name="<?php echo htmlspecialchars($shop['name']); ?>"
                        data-serial="<?php echo htmlspecialchars($shop['serial'] ?? ''); ?>"
                        data-details="<?php echo htmlspecialchars($shop['shop_details'] ?? ''); ?>"
                        data-address="<?php echo htmlspecialchars($shop['address'] ?? ''); ?>"
                        data-phone="<?php echo htmlspecialchars($shop['phone'] ?? ''); ?>"
                        data-manager="<?php echo htmlspecialchars($shop['manager'] ?? ''); ?>"
                        data-status="<?php echo $shop['status'] ?? 'Active'; ?>" data-bs-toggle="modal"
                        data-bs-target="#editShopModal">
                        <i class="bi bi-pencil-square me-1"></i> Edit
                    </button>
                    <a href="../inventory/view_stock.php?shop=<?php echo urlencode($shop['name']); ?>"
                        class="btn btn-sm btn-outline-info flex-grow-1">
                        <i class="bi bi-box-seam me-1"></i> Stock
                    </a>
                    <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item" href="../inventory/transfer.php"><i
                                    class="bi bi-arrow-left-right me-2 text-warning"></i>Transfer Items</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" onsubmit="return confirm('Delete this shop?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $shop['id']; ?>">
                                <button type="submit" class="dropdown-item text-danger"><i
                                        class="bi bi-trash me-2"></i>Delete Shop</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<style>
    .shop-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .shop-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
    }
</style>

<!-- Add Shop Modal -->
<div class="modal fade" id="addShopModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Shop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Shop Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shop Code</label>
                        <input type="text" name="serial" class="form-control" placeholder="e.g. SHP001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address/Location</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager</label>
                        <input type="text" name="manager" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes/Details</label>
                        <textarea name="details" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Shop</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Shop Modal -->
<div class="modal fade" id="editShopModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Shop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Shop Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shop Code</label>
                        <input type="text" name="serial" id="editSerial" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="editAddress" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="editPhone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager</label>
                        <input type="text" name="manager" id="editManager" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="details" id="editDetails" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
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
    document.querySelectorAll('.edit-shop-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('editId').value = this.dataset.id;
            document.getElementById('editName').value = this.dataset.name;
            document.getElementById('editSerial').value = this.dataset.serial;
            document.getElementById('editAddress').value = this.dataset.address;
            document.getElementById('editPhone').value = this.dataset.phone;
            document.getElementById('editManager').value = this.dataset.manager;
            document.getElementById('editDetails').value = this.dataset.details;
            document.getElementById('editStatus').value = this.dataset.status;
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>