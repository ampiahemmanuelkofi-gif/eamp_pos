<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $name = sanitizeInput($_POST['name']);
        $contact = sanitizeInput($_POST['contact']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);

        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, address) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $contact, $phone, $address);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Supplier added successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error adding supplier: " . $conn->error . "</div>";
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = (int) $_POST['id'];
        $conn->query("DELETE FROM suppliers WHERE id = $id");
        $message = "<div class='alert alert-success'>Supplier deleted.</div>";
    }
}

// Fetch All Suppliers
$result = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Suppliers</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="list.php" class="btn btn-secondary">Back to Products</a>
    </div>
</div>

<div class="row">
    <!-- Add Supplier Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Add New Supplier</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add" class="btn btn-primary w-100">Save Supplier</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Supplier List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th width="100">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold">
                                            <?php echo $row['name']; ?>
                                        </td>
                                        <td>
                                            <?php echo $row['contact_person']; ?>
                                        </td>
                                        <td>
                                            <?php echo $row['phone']; ?>
                                        </td>
                                        <td class="small">
                                            <?php echo $row['address']; ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" onsubmit="return confirm('Delete this supplier?');">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-3 text-muted">No suppliers found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>