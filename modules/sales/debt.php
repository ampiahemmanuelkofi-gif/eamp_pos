<?php
require_once '../../includes/auth.php';
checkLogin();
require_once '../../includes/header.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle Payment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $invoice_id = $_POST['invoice'];
    $payment_amount = (float) $_POST['amount'];
    $user = $_SESSION['username'];

    if ($payment_amount <= 0) {
        $message = "<div class='alert alert-danger'>Amount must be greater than 0.</div>";
    } else {
        // Fetch Invoice details
        $stmt = $conn->prepare("SELECT id, amount, amt_due, name FROM sales WHERE invoice = ?");
        $stmt->bind_param("s", $invoice_id);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc();

        if ($sale) {
            $current_due = (float) $sale['amt_due'];
            if ($payment_amount > $current_due) {
                $message = "<div class='alert alert-danger'>Payment amount cannot exceed due amount ($current_due).</div>";
            } else {
                $new_due = $current_due - $payment_amount;

                $conn->begin_transaction();
                try {
                    // Update Sales
                    $update = $conn->prepare("UPDATE sales SET amt_due = ? WHERE invoice = ?");
                    // Using string for decimal precision if needed, but float works for simple cases
                    $update->bind_param("ds", $new_due, $invoice_id);
                    $update->execute();

                    // Log Payment
                    $log = $conn->prepare("INSERT INTO debt_payment_history (invoice, amount, amt_due, amt_paid, name, agent) VALUES (?, ?, ?, ?, ?, ?)");
                    // Note: Schema has 'amount' (total invoice?), 'amt_due' (remaining?), 'amt_paid' (this payment?).
                    // Let's assume: amount = total invoice amount (reference), amt_due = remaining AFTER this, amt_paid = THIS payment.
                    // Schema columns: invoice, amount, amt_due, amt_paid, name, address, mobile, agent, date
                    
                    $total_amount = $sale['amount'];
                    $log->bind_param("ssssss", $invoice_id, $total_amount, $new_due, $payment_amount, $sale['name'], $user);
                    $log->execute();
                    
                    $conn->commit();
                    $message = "<div class='alert alert-success'>Payment of " . number_format($payment_amount, 2) . " recorded. New Due: " . number_format($new_due, 2) . "</div>";

                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                }
            }
        } else {
            $message = "<div class='alert alert-danger'>Invoice not found.</div>";
        }
    }
}

// Fetch Debts
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM sales WHERE amt_due > 0";
if ($search) {
    $sql .= " AND (name LIKE '%$search%' OR invoice LIKE '%$search%')";
}
$sql .= " ORDER BY id DESC";
$result = $conn->query($sql);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Debt Management</h2>
        <p class="text-muted">Track and collect outstanding payments.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
             <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search Customer or Invoice..." value="<?php echo $search; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Outstanding Invoices</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Due</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['date']; ?></td>
                                        <td><a href="invoice.php?invoice=<?php echo $row['invoice']; ?>"><?php echo $row['invoice']; ?></a></td>
                                        <td><?php echo $row['name']; ?></td>
                                        <td><?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="text-danger fw-bold"><?php echo number_format($row['amt_due'], 2); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success pay-btn" 
                                                data-invoice="<?php echo $row['invoice']; ?>" 
                                                data-due="<?php echo $row['amt_due']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#paymentModal">
                                                Pay
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">No debts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
         <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Payments</h5>
            </div>
            <div class="card-body">
                 <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Inv</th>
                            <th>Paid</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                         <?php
                        $audit = $conn->query("SELECT * FROM debt_payment_history ORDER BY id DESC LIMIT 10");
                        while ($log = $audit->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $log['invoice'] . "</td>";
                            echo "<td class='text-success'>" . number_format($log['amt_paid'], 2) . "</td>";
                            echo "<td>" . $log['agent'] . "</td>"; // schema says agent
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                 </table>
            </div>
         </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="invoice" id="modalInvoice">
            <div class="mb-3">
                <label class="form-label">Invoice</label>
                <input type="text" class="form-control" id="modalInvoiceDisplay" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Amount Due</label>
                <input type="text" class="form-control" id="modalDueDisplay" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Payment Amount</label>
                <input type="number" name="amount" class="form-control" step="0.01" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Payment</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var paymentModal = document.getElementById('paymentModal');
    paymentModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var invoice = button.getAttribute('data-invoice');
        var due = button.getAttribute('data-due');
        
        document.getElementById('modalInvoice').value = invoice;
        document.getElementById('modalInvoiceDisplay').value = invoice;
        document.getElementById('modalDueDisplay').value = due;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
