<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$search = $_GET['search'] ?? '';

// Fetch all shops for dynamic columns
$shops_res = $conn->query("SELECT * FROM shops");
$shops = [];
while ($s = $shops_res->fetch_assoc()) {
    $shops[] = $s['name'];
}

// Build Query
// We need products and their stock in each shop.
// This can be complex with dynamic columns.
// Strategy: distinct products, then subqueries or left joins for each shop?
// Simpler: Fetch all products, then for each product fetch stock in shops.
// Or: group_concat or pivot.
// Simplest for now: Fetch all products (limit with pagination), then in loop fetch shop stocks.

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT id, code, name, qty as global_qty FROM products";
if ($search) {
    $sql .= " WHERE name LIKE '%$search%' OR code LIKE '%$search%'";
}
$sql .= " LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Stock Overview</h2>
        <p class="text-muted">Multi-location stock levels.</p>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <form method="GET" class="row g-3">
            <div class="col-auto">
                <input type="text" name="search" class="form-control" placeholder="Search product..."
                    value="<?php echo $search; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary mb-3">Search</button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Main Warehouse</th>
                        <?php foreach ($shops as $shop): ?>
                            <th><?php echo $shop; ?></th>
                        <?php endforeach; ?>
                        <th>Total Global</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            // Calculate total stock (Main + Shops)
                            // "Main Warehouse" stock is stored in products.qty
                            // "Shop" stock is in product_stock table
                            
                            $id = $row['id'];
                            $total = $row['global_qty']; // Start with Main
                            
                            // Fetch shop stocks
                            $shop_qtys = [];
                            $stmt_s = $conn->prepare("SELECT shop_id, qty FROM product_stock WHERE product_id = ?");
                            $stmt_s->bind_param("i", $id);
                            $stmt_s->execute();
                            $res_s = $stmt_s->get_result();
                            while ($s_row = $res_s->fetch_assoc()) {
                                $shop_qtys[$s_row['shop_id']] = $s_row['qty'];
                                $total += $s_row['qty']; // Wait, is products.qty ALL stock or just MAIN stock?
                                // Implementation plan says: "The main products table's qty field will be treated as the 'Main Warehouse' or Global stock."
                                // It also says "Transfers will move stock between this main pool and specific shops".
                                // This implies products.qty IS the Main Warehouse stock.
                                // So Total = products.qty (Main) + sum(product_stock.qty).
                                // Wait, usually 'Global Qty' means EVERYTHING.
                                // If products.qty is Main Warehouse, then Total is correct.
                            }
                            ?>
                            <tr>
                                <td><?php echo $row['code']; ?></td>
                                <td><?php echo $row['name']; ?></td>
                                <td class="fw-bold text-success"><?php echo $row['global_qty']; ?></td>
                                <?php foreach ($shops as $shop): ?>
                                    <td>
                                        <?php echo isset($shop_qtys[$shop]) ? $shop_qtys[$shop] : '0'; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="fw-bold"><?php echo $total; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo count($shops) + 4; ?>" class="text-center">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination logic could go here -->
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
