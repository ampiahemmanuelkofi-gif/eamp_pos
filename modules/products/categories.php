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
        $description = sanitizeInput($_POST['description']);
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Category added.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error adding category.</div>";
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = (int) $_POST['id'];
        $conn->query("DELETE FROM categories WHERE id = $id");
        $message = "<div class='alert alert-success'>Category deleted.</div>";
    }
}

// Fetch All Categories
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Categories</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="list.php" class="btn btn-secondary">Back to Products</a>
    </div>
</div>

<div class="row">
    <!-- Add Category Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Add New Category</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" name="add" class="btn btn-primary w-100">Save Category</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Category List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
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
                                        <?php echo $row['description']; ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" onsubmit="return confirm('Delete this category?');">
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
                <?php else: ?>
                    <div class="text-center p-3 text-muted">No categories found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>