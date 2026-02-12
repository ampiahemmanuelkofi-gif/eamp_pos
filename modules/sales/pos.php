<?php
require_once '../../includes/auth.php';
checkLogin();
// Header/Footer not standard here as POS usually takes full screen or specific layout, 
// using standard layout for now but maybe simplified.
require_once '../../config/constants.php';
require_once '../../includes/settings_loader.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Point of Sale -
        <?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body {
            overflow: hidden;
            height: 100vh;
        }

        .pos-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .pos-header {
            padding: 10px;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }

        .pos-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .product-panel {
            flex: 2;
            padding: 15px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .cart-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #ddd;
            background: #fff;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        .cart-footer {
            padding: 15px;
            border-top: 1px solid #ddd;
            background: #f1f1f1;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }

        .pos-product-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .pos-product-card:hover {
            border-color: var(--primary-color);
            transform: scale(1.02);
        }

        .pos-product-card .price {
            font-weight: bold;
            color: var(--primary-color);
        }

        .pos-product-card .name {
            font-size: 0.9rem;
            margin: 5px 0;
            height: 40px;
            overflow: hidden;
        }

        .cart-table th,
        .cart-table td {
            padding: 8px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>

    <div class="pos-container">
        <div class="pos-header d-flex justify-content-between align-items-center">
            <h4 class="m-0"><a href="../dashboard/index.php" class="text-decoration-none text-dark"><i
                        class="bi bi-arrow-left"></i> POS</a></h4>
            <div>
                <span class="me-3">
                    <a href="history.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-return-left"></i>
                        Returns</a>
                </span>
                <span class="me-3"><i class="bi bi-shop"></i>
                    <?php echo $_SESSION['shop_id'] ?? 'Main Shop'; ?>
                </span>
                <span class="me-3"><i class="bi bi-person"></i>
                    <?php echo $_SESSION['username']; ?>
                </span>
                <span id="clock" class="fw-bold">00:00</span>
            </div>
        </div>

        <div class="pos-content">
            <!-- Product Panel -->
            <div class="product-panel">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input type="text" id="searchProduct" class="form-control"
                            placeholder="Search by name, code or scan barcode..." autofocus>
                    </div>
                    <div class="col-md-4">
                        <select id="categoryFilter" class="form-select">
                            <option value="">All Categories</option>
                            <!-- To be populated if needed, or static for MVP -->
                        </select>
                    </div>
                </div>

                <div id="productGrid" class="product-grid">
                    <!-- Products loaded via AJAX -->
                    <div class="text-center w-100 mt-5 text-muted">Loading products...</div>
                </div>
            </div>

            <!-- Cart Panel -->
            <div class="cart-panel">
                <div class="p-2 bg-primary text-white">
                    <h5 class="m-0"><i class="bi bi-cart"></i> Current Sale</h5>
                </div>
                <div class="cart-items">
                    <table class="table table-striped cart-table mb-0">
                        <thead>
                            <tr>
                                <th width="40%">Item</th>
                                <th width="20%">Price</th>
                                <th width="25%">Qty</th>
                                <th width="15%"></th>
                            </tr>
                        </thead>
                        <tbody id="cartTableBody">
                            <!-- Cart Items -->
                        </tbody>
                    </table>
                </div>
                <div class="cart-footer">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Subtotal:</span>
                        <span id="cartSubtotal">0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Discount:</span>
                        <input type="number" id="cartDiscount" class="form-control form-control-sm text-end"
                            style="width: 80px" value="0" min="0">
                    </div>
                    <div
                        class="d-flex justify-content-between mb-1 <?php echo ($SYSTEM_SETTINGS['tax_enabled'] ?? 0) == 1 ? '' : 'd-none'; ?>">
                        <span>Tax:</span>
                        <span id="cartTax">0.00</span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold fs-4 mb-3">
                        <span>Total:</span>
                        <span id="cartTotal">0.00</span>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success btn-lg" id="btnPay">PAY NOW</button>
                        <button class="btn btn-danger btn-sm" id="btnClear">Clear Cart</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h2 class="text-center mb-4" id="modalTotal"><?php echo format_currency(0); ?></h2>

                    <div class="mb-3">
                        <label class="form-label">Customer Name (Optional)</label>
                        <input type="text" id="customerName" class="form-control" placeholder="Walk-in Customer">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Customer Phone/Email (Optional)</label>
                        <input type="text" id="customerContact" class="form-control"
                            placeholder="Phone or email for receipt">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select id="paymentMethod" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Card">Card</option>
                            <option value="Credit">Credit (Pay Later)</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="refGroup">
                        <label class="form-label">Reference Number (Momo/Card)</label>
                        <input type="text" id="paymentRef" class="form-control" placeholder="Transaction ID">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount Paid</label>
                        <input type="number" id="amountPaid" class="form-control" step="0.01">
                        <small class="text-danger fw-bold d-none" id="balanceDisplay">Balance Due: 0.00</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmPayment">Confirm Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Options Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Sale Completed!</h5>
                </div>
                <div class="modal-body text-center py-5">
                    <h2 class="text-success mb-3">
                        <i class="bi bi-check-circle" style="font-size: 4rem;"></i>
                    </h2>
                    <h5 class="mb-2">Invoice: <span id="receiptInvoiceNumber" class="badge bg-primary"
                            style="font-size: 1.1rem; padding: 0.5rem 1rem;">INV-XXXXX</span></h5>
                    <div class="mb-4">
                        <small class="text-muted">Profit Earned:</small>
                        <span id="receiptProfitAmount" class="fw-bold text-success"
                            style="font-size: 1.2rem;">0.00</span>
                    </div>
                    <p class="text-muted">What would you like to do next?</p>

                    <div class="d-grid gap-3 mt-4">
                        <button type="button" class="btn btn-primary btn-lg py-3"
                            onclick="printReceipt(document.getElementById('receiptInvoiceNumber').textContent)">
                            <i class="bi bi-printer"></i> PRINT RECEIPT
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" onclick="returnToPOS()">
                            <i class="bi bi-plus-circle"></i> NEW SALE
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Print Container -->
    <iframe id="printFrame" style="display:none;"></iframe>

    <!-- Constants for JS -->
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const SETTINGS = <?php echo json_encode($SYSTEM_SETTINGS); ?>;

    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/pos.js?v=<?php echo time(); ?>"></script>
</body>

</html>