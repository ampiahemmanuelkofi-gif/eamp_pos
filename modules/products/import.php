<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

if (isset($_POST['import'])) {
    if ($_FILES['file']['name']) {
        $filename = explode(".", $_FILES['file']['name']);
        if ($filename[1] == 'csv') {
            $handle = fopen($_FILES['file']['tmp_name'], "r");
            $count = 0;
            while ($data = fgetcsv($handle)) {
                // Formatting for consistency
                // Expected CSV format: Code, Name, Price, Cost, Qty, Category, Barcode
                // Skip header if needed (add logic if header exists)

                $code = mysqli_real_escape_string($conn, $data[0]);
                $name = mysqli_real_escape_string($conn, $data[1]);
                $price = (float) $data[2];
                $cost = (float) $data[3];
                $qty = (int) $data[4];
                $category = mysqli_real_escape_string($conn, $data[5]);
                $barcode = mysqli_real_escape_string($conn, $data[6]);

                // New Fields
                $supplier = isset($data[7]) ? mysqli_real_escape_string($conn, $data[7]) : '';
                $expire = isset($data[8]) ? mysqli_real_escape_string($conn, $data[8]) : '';
                $batch_no = isset($data[9]) ? mysqli_real_escape_string($conn, $data[9]) : '';
                $box_qty = isset($data[10]) ? (int) $data[10] : 0;
                $qty_in_box = isset($data[11]) ? (int) $data[11] : 0;
                $box_price = isset($data[12]) ? (float) $data[12] : 0;
                $box_wholesale = isset($data[13]) ? (float) $data[13] : 0;

                // Check duplicate
                $check = $conn->query("SELECT id FROM products WHERE code = '$code'");
                if ($check->num_rows == 0) {
                    $sql = "INSERT INTO products (code, name, price, cost, qty, department, barcode, supplied_by, expire, batch_no, box_qty, qty_in_box, box_price, box_wholesale) 
                            VALUES ('$code', '$name', '$price', '$cost', '$qty', '$category', '$barcode', '$supplier', '$expire', '$batch_no', '$box_qty', '$qty_in_box', '$box_price', '$box_wholesale')";
                    if ($conn->query($sql)) {
                        $count++;
                    }
                }
            }
            fclose($handle);
            $message = "<div class='alert alert-success'>Successfully imported $count products.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Invalid file format. Please upload CSV.</div>";
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Bulk Product Import</h2>
        <p class="text-muted">Upload a CSV file to add multiple products at once.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Upload CSV</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <div class="mb-3 text-end">
                    <a href="download_template.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download"></i> Download CSV Template
                    </a>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label">Select CSV File</label>
                        <input type="file" name="file" class="form-control" accept=".csv" required>
                        <div class="form-text mt-2">
                            <strong>Format:</strong> Code, Name, Selling Price, Cost Price, Quantity, Category, Barcode,
                            Supplier, Expiry, Batch No, Box Qty, Items/Box, Box Price, Box Wholesale
                        </div>
                    </div>

                    <div class="alert alert-light border">
                        <h6>Example content:</h6>
                        <code>P001, Coca Cola, 5.00, 3.50, 100, Beverages, 5449000000996</code>
                    </div>

                    <button type="submit" name="import" class="btn btn-success w-100">
                        <i class="bi bi-upload"></i> Import Products
                    </button>

                    <div class="mt-3 text-center">
                        <a href="../products/list.php" class="text-decoration-none">Back to Product List</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>