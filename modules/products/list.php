<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>Product deleted successfully.</div>";
    } else {
        echo "<div class='alert alert-danger'>Error deleting product.</div>";
    }
}

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search Logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_sql = "";
$params = [];
$types = "";

if ($search) {
    $where_sql = " WHERE name LIKE ? OR code LIKE ? OR department LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $types = "sss";
}

// Total count
$count_sql = "SELECT COUNT(*) as count FROM products" . $where_sql;
if ($search) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_result = $stmt->get_result();
} else {
    $total_result = $conn->query($count_sql);
}
$total_rows = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_rows / $limit);

// Fetch Products
$sql = "SELECT * FROM products" . $where_sql . " ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

if ($search) {
    // Merge params for limit/offset
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Products</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="categories.php" class="btn btn-outline-primary me-2">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a href="import.php" class="btn btn-outline-success me-2">
            <i class="bi bi-file-earmark-spreadsheet"></i> Import CSV
        </a>
        <button type="submit" form="barcodeForm" class="btn btn-outline-dark me-2">
            <i class="bi bi-upc-scan"></i> Print Barcodes
        </button>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add Product
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                        placeholder="Search by name, code, or category..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">Search</button>
                    <?php if ($search): ?>
                        <a href="list.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body">
        <form id="barcodeForm" action="barcodes.php" method="POST" target="_blank">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Cost</th>
                            <th>Price</th>
                            <th>Margin</th>
                            <th>Stock</th>
                            <th>Alert Lvl</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="<?php echo ($row['qty'] <= $row['alert']) ? 'table-warning' : ''; ?>">
                                    <td>
                                        <input type="checkbox" name="products[]" value="<?php echo $row['id']; ?>"
                                            class="product-check">
                                    </td>
                                    <td>
                                        <?php echo $row['code']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['name']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['department']; ?>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo format_currency($row['factory_price']); ?>
                                    </td>
                                    <td>
                                        <?php echo format_currency($row['price']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $margin_val = (float) $row['price'] - (float) $row['factory_price'];
                                        $margin_pct = ($row['price'] > 0) ? ($margin_val / $row['price']) * 100 : 0;
                                        $badge_class = ($margin_pct > 30) ? 'bg-success' : (($margin_pct > 15) ? 'bg-info' : 'bg-warning');
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo number_format($margin_pct, 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $row['qty']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['alert']; ?>
                                    </td>
                                    <td>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary shadow-sm view-product-btn me-1"
                                            data-id="<?php echo $row['id']; ?>" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>"
                                            class="btn btn-sm btn-outline-info shadow-sm me-1" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?delete=<?php echo $row['id']; ?>"
                                            class="btn btn-sm btn-outline-danger shadow-sm"
                                            onclick="return confirm('Are you sure?')" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <script>
            document.getElementById('selectAll').addEventListener('change', function () {
                var checkboxes = document.querySelectorAll('.product-check');
                for (var checkbox of checkboxes) {
                    checkbox.checked = this.checked;
                }
            });

            // View Product AJAX Logic
            document.querySelectorAll('.view-product-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.dataset.id;
                    const modal = new bootstrap.Modal(document.getElementById('viewProductModal'));
                    const content = document.getElementById('productDetailsContent');

                    content.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
                    modal.show();

                    fetch('get_product.php?id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const p = data.data;
                                content.innerHTML = `
                                    <div class="row g-3">
                                        <div class="col-md-6 border-end">
                                            <h6 class="text-primary border-bottom pb-2">Basic Info</h6>
                                            <p><strong>Code:</strong> ${p.code}</p>
                                            <p><strong>Name:</strong> ${p.name}</p>
                                            <p><strong>Category:</strong> ${p.department || 'N/A'}</p>
                                            <p><strong>Barcode:</strong> ${p.barcode || 'N/A'}</p>
                                            <p><strong>Description:</strong> ${p.description || 'No description'}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary border-bottom pb-2">Pricing & Inventory</h6>
                                            <p><strong>Cost Price:</strong> ${p.cost || '0.00'}</p>
                                            <p><strong>Factory Price:</strong> ${p.factory_price || '0.00'}</p>
                                            <p class="text-success fw-bold"><strong>Selling Price:</strong> ${p.price}</p>
                                            <p><strong>Current Stock:</strong> <span class="badge ${p.qty <= p.alert ? 'bg-danger' : 'bg-success'}">${p.qty}</span></p>
                                            <p><strong>Alert Level:</strong> ${p.alert}</p>
                                        </div>
                                        <div class="col-12 border-top pt-3">
                                            <h6 class="text-primary border-bottom pb-2">Box / Wholesale Details</h6>
                                            <div class="row">
                                                <div class="col-md-3"><p><strong>Box Qty:</strong> ${p.qty_in_box || 'N/A'}</p></div>
                                                <div class="col-md-3"><p><strong>Box Cost:</strong> ${p.box_price || 'N/A'}</p></div>
                                                <div class="col-md-3"><p><strong>Box Wholesale:</strong> ${p.box_wholesale || 'N/A'}</p></div>
                                                <div class="col-md-3"><p><strong>Box Stock:</strong> ${p.box_qty || 'N/A'}</p></div>
                                            </div>
                                        </div>
                                        <div class="col-12 border-top pt-3">
                                            <h6 class="text-primary border-bottom pb-2">Logistics</h6>
                                            <p><strong>Supplier:</strong> ${p.supplied_by || 'N/A'}</p>
                                            <p><strong>Batch No:</strong> ${p.batch_no || 'N/A'}</p>
                                            <p><strong>Expiry Date:</strong> <span class="text-danger">${p.expire || 'N/A'}</span></p>
                                        </div>
                                    </div>
                                `;
                            } else {
                                content.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                            }
                        })
                        .catch(error => {
                            content.innerHTML = '<div class="alert alert-danger">Error loading product details.</div>';
                        });
                });
            });
        </script>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <!-- View Product Modal -->
        <div class="modal fade" id="viewProductModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i> Product Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="productDetailsContent">
                        <!-- Data will be loaded here via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>