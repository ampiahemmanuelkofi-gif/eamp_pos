<?php
require_once '../../includes/auth.php';
checkLogin();

if (!isset($_GET['invoice'])) {
    echo "Invalid Invoice ID";
    exit();
}

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/settings_loader.php';

$db = new Database();
$conn = $db->getConnection();

$invoice_id = $_GET['invoice'];

// Fetch Sale Details
$stmt = $conn->prepare("SELECT * FROM sales WHERE invoice = ?");
$stmt->bind_param("s", $invoice_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    echo "Invoice not found";
    exit();
}

// Fetch Items
$stmt_items = $conn->prepare("SELECT * FROM sales_order WHERE invoice = ?");
$stmt_items->bind_param("s", $invoice_id);
$stmt_items->execute();
$items = $stmt_items->get_result();

$lines = [];
while ($row = $items->fetch_assoc()) {
    $lines[] = $row;
}
?>
<?php if (!isset($_GET['view_type']) || $_GET['view_type'] !== 'partial'): ?>
    <!DOCTYPE html>
    <html lang="en">
<?php endif; ?>

<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $sale['invoice']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .card-header {
                background: none !important;
                border-bottom: 2px solid #000 !important;
            }
        }

        /* Thermal Printer Styles (80mm) */
        .thermal-receipt {
            max-width: 80mm;
            width: 100%;
            margin: 0 auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.3;
            background: white;
            padding: 5px;
            color: #000;
        }

        .thermal-receipt .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .thermal-receipt .receipt-title {
            font-weight: bold;
            font-size: 18px;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }

        .thermal-receipt .company-info {
            font-size: 11px;
            margin: 2px 0;
            white-space: pre-line;
        }

        .thermal-receipt .receipt-subtitle {
            font-size: 12px;
            font-weight: bold;
            margin: 10px 0 5px 0;
            text-decoration: underline;
        }

        .thermal-receipt .receipt-info {
            font-size: 11px;
            margin: 4px 0;
            display: flex;
            justify-content: space-between;
        }

        .thermal-receipt .items-section {
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            margin: 5px 0;
        }

        .thermal-receipt .item-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin: 5px 0;
        }

        .thermal-receipt .item-name {
            flex: 2;
            word-break: break-word;
            padding-right: 5px;
        }

        .thermal-receipt .item-qty {
            flex: 0.5;
            text-align: center;
        }

        .thermal-receipt .item-price {
            flex: 1;
            text-align: right;
        }

        .thermal-receipt .totals {
            margin: 10px 0;
            font-size: 12px;
        }

        .thermal-receipt .total-line {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }

        .thermal-receipt .total-amount {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 8px 0;
            margin-top: 10px;
        }

        .thermal-receipt .footer {
            text-align: center;
            font-size: 11px;
            margin-top: 15px;
            padding-top: 5px;
        }

        @media print {
            .thermal-receipt {
                width: 80mm;
                max-width: 80mm;
                margin: 0;
                padding: 0;
            }

            @page {
                margin: 0;
                size: 80mm auto;
            }
        }
    </style>
    </style>
</head>
<?php if (!isset($_GET['view_type']) || $_GET['view_type'] !== 'partial'): ?>

    <body>
    <?php endif; ?>

    <div class="container mt-4 mb-3 no-print">
        <a href="history.php" class="btn btn-secondary">&larr; Back to Sales History</a>
        <button onclick="window.print()" class="btn btn-primary ms-2"><i class="bi bi-printer"></i> Print
            Receipt</button>
        <button onclick="toggleInvoiceView()" class="btn btn-info ms-2" id="viewToggleBtn"><i class="bi bi-receipt"></i>
            Thermal Receipt View</button>
    </div>

    <div class="invoice-card" id="standard-view">
        <div class="header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="m-0">
                    <?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?>
                </h3>
                <p class="m-0 text-muted">Official Receipt</p>
            </div>
            <div class="text-end">
                <h5 class="m-0 text-primary">#
                    <?php echo $sale['invoice']; ?>
                </h5>
                <small>
                    <?php echo $sale['date']; ?>
                </small>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <h6 class="text-uppercase text-muted small">Billed To</h6>
                <div class="fw-bold"><?php echo $sale['name']; ?></div>
                <?php if (!empty($sale['mobile'])): ?>
                    <div><?php echo $sale['mobile']; ?></div><?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-uppercase text-muted small">Payment Details</h6>
                <div>Method: <span class="fw-bold"><?php echo $sale['payment_method'] ?? 'Cash'; ?></span></div>
                <?php if (!empty($sale['momo_ref'])): ?>
                    <div class="small text-muted">Ref: <?php echo $sale['momo_ref']; ?></div>
                <?php endif; ?>
                <div class="mt-2 text-muted small">Cashier: <?php echo $sale['agent']; ?></div>
            </div>
        </div>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Total</th>
                    <th class="text-center no-print">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $item): ?>
                    <tr
                        class="<?php echo ($item['returned_item'] == 'Yes') ? 'text-decoration-line-through text-danger' : ''; ?>">
                        <td>
                            <div class="fw-bold">
                                <?php echo $item['name']; ?>
                            </div>
                            <small class="text-muted">
                                <?php echo $item['code']; ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <?php echo $item['qty']; ?>
                            <?php if ($item['returned_item'] == 'Yes'): ?>
                                <br><span class="badge bg-danger">Returned</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php echo format_currency($item['price']); ?>
                            <?php echo format_currency($item['amount']); ?>
                        </td>
                        <td class="text-center no-print">
                            <?php if ($item['returned_item'] != 'Yes'): ?>
                                <a href="return.php?id=<?php echo $item['id']; ?>&invoice=<?php echo $invoice_id; ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Process return for this item?')">Return</a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Grand Total</td>
                    <td class="text-end fw-bold bg-light">
                        <?php echo format_currency($sale['amount']); ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            Thank you for your business!
        </div>
    </div>

    <!-- Thermal Receipt Printer Version -->
    <div id="thermal-view" class="thermal-receipt" style="display: none;">
        <div class="header">
            <p class="receipt-title"><?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?></p>
            <?php if (!empty($SYSTEM_SETTINGS['company_address'])): ?>
                <div class="company-info"><?php echo $SYSTEM_SETTINGS['company_address']; ?></div>
            <?php endif; ?>
            <?php if (!empty($SYSTEM_SETTINGS['company_details'])): ?>
                <div class="company-info"><?php echo $SYSTEM_SETTINGS['company_details']; ?></div>
            <?php endif; ?>

            <p class="receipt-subtitle">OFFICIAL RECEIPT</p>
            <div class="receipt-info" style="margin-top: 10px;">
                <span>Invoice: #<?php echo $sale['invoice']; ?></span>
                <span><?php echo date('m/d/Y', strtotime($sale['date'])); ?></span>
            </div>
        </div>

        <div class="receipt-info" style="font-size: 10px; margin: 5px 0;">
            <strong>Customer:</strong> <?php echo $sale['name']; ?>
        </div>
        <?php if (!empty($sale['mobile'])): ?>
            <div class="receipt-info" style="font-size: 10px; margin: 2px 0;">
                <strong>Phone:</strong> <?php echo $sale['mobile']; ?>
            </div>
        <?php endif; ?>

        <div class="items-section">
            <div class="item-row" style="border-bottom: 1px dashed #000; padding-bottom: 3px;">
                <span class="item-name"><strong>Item</strong></span>
                <span class="item-qty"><strong>Qty</strong></span>
                <span class="item-price"><strong>Total</strong></span>
            </div>
            <?php foreach ($lines as $item): ?>
                <?php if ($item['returned_item'] != 'Yes'): ?>
                    <div class="item-row">
                        <span class="item-name"><?php echo substr($item['name'], 0, 20); ?></span>
                        <span class="item-qty"><?php echo $item['qty']; ?></span>
                        <span class="item-price"><?php echo format_currency($item['qty'] * $item['price']); ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="totals">
            <div class="total-line">
                <span>Subtotal:</span>
                <span><?php echo format_currency($sale['amount']); ?></span>
            </div>
            <div class="total-line total-amount">
                <span>TOTAL:</span>
                <span><?php echo format_currency($sale['amount']); ?></span>
            </div>
        </div>

        <div class="receipt-info" style="font-size: 10px; margin: 5px 0;">
            <strong>Payment:</strong> <?php echo $sale['payment_method'] ?? 'Cash'; ?>
        </div>
        <?php if (!empty($sale['momo_ref'])): ?>
            <div class="receipt-info" style="font-size: 10px; margin: 2px 0;">
                <strong>Ref:</strong> <?php echo $sale['momo_ref']; ?>
            </div>
        <?php endif; ?>
        <div class="receipt-info" style="font-size: 10px; margin: 2px 0;">
            <strong>Cashier:</strong> <?php echo $sale['agent']; ?>
        </div>

        <div class="footer">
            <p style="margin: 3px 0;">Thank you for your business!</p>
            <p style="margin: 3px 0; font-size: 9px;"><?php echo date('Y-m-d H:i:s', strtotime($sale['date'])); ?></p>
        </div>
    </div>

    <script>
        function toggleInvoiceView() {
            const standardView = document.getElementById('standard-view');
            const thermalView = document.getElementById('thermal-view');
            const btn = document.getElementById('viewToggleBtn');

            if (standardView.style.display === 'none') {
                standardView.style.display = 'block';
                thermalView.style.display = 'none';
                btn.innerHTML = '<i class="bi bi-receipt"></i> Thermal Receipt View';
            } else {
                standardView.style.display = 'none';
                thermalView.style.display = 'block';
                btn.innerHTML = '<i class="bi bi-file-text"></i> Standard Invoice View';
            }
        }

        // Auto-print if parameter is present
        window.onload = function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('print')) {
                window.print();
            }
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (!isset($_GET['view_type']) || $_GET['view_type'] !== 'partial'): ?>
    </body>

    </html>
<?php endif; ?>