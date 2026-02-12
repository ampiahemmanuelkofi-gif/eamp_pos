let cart = [];

$(document).ready(function () {

    // Initial Load
    loadProducts();
    updateClock();
    setInterval(updateClock, 1000);

    // Filter Listeners
    $('#searchProduct').on('keyup', function () {
        const query = $(this).val();
        loadProducts(query);
    });

    // Helper: Format Currency
    function formatCurrency(amount) {
        amount = parseFloat(amount).toFixed(SETTINGS.decimal_places || 2);
        if (SETTINGS.currency_position === 'after') {
            return amount + SETTINGS.currency_symbol;
        }
        return SETTINGS.currency_symbol + amount;
    }


    // Load Products Function
    function loadProducts(search = '', category = '') {
        $.ajax({
            url: BASE_URL + '/ajax/get_products.php',
            method: 'GET',
            data: { search: search, category: category },
            dataType: 'json',
            success: function (data) {
                let html = '';
                if (data.length > 0) {
                    data.forEach(p => {
                        html += `
                        <div class="pos-product-card" onclick='addToCart(${JSON.stringify(p)})'>
                            <div class="name">${p.name}</div>
                            <div class="code text-muted small">${p.code}</div>
                            <div class="price">${formatCurrency(p.price)}</div>
                            <div class="stock small text-success">Stock: ${p.qty}</div>
                        </div>
                        `;
                    });
                } else {
                    html = '<div class="col-12 text-center text-muted">No products found</div>';
                }
                $('#productGrid').html(html);
            }
        });
    }

    // Add to Cart (Global scope to work with onclick)
    window.addToCart = function (product) {
        const existingItem = cart.find(item => item.id == product.id);
        if (existingItem) {
            existingItem.qty++;
        } else {
            cart.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.price),
                qty: 1
            });
        }
        renderCart();
    }

    // Global logic variables
    let currentCartTotal = 0;

    // Render Cart
    function renderCart() {
        let html = '';
        let subtotal = 0;

        cart.forEach((item, index) => {
            const total = item.price * item.qty;
            subtotal += total;
            html += `
                <tr>
                    <td>${item.name}</td>
                    <td>${item.price.toFixed(2)}</td>
                    <td>
                        <div class="input-group input-group-sm" style="width: 80px;">
                            <button class="btn btn-outline-secondary px-1" onclick="updateQty(${index}, -1)">-</button>
                            <input type="text" class="form-control text-center px-1" value="${item.qty}" readonly>
                            <button class="btn btn-outline-secondary px-1" onclick="updateQty(${index}, 1)">+</button>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-danger btn-sm" onclick="removeFromCart(${index})"><i class="bi bi-x"></i></button>
                    </td>
                </tr>
            `;
        });

        $('#cartTableBody').html(html);
        $('#cartSubtotal').text(formatCurrency(subtotal));

        const discount = parseFloat($('#cartDiscount').val()) || 0;
        let taxableAmount = Math.max(0, subtotal - discount);

        let tax = 0;
        if (SETTINGS.tax_enabled == '1') {
            const taxRate = parseFloat(SETTINGS.tax_rate) || 0;
            tax = taxableAmount * (taxRate / 100);
        }

        const total = taxableAmount + tax;
        currentCartTotal = total; // Updates global

        $('#cartTax').text(formatCurrency(tax));
        $('#cartTotal').text(formatCurrency(total));
    }

    // Update Qty
    window.updateQty = function (index, change) {
        if (cart[index].qty + change > 0) {
            cart[index].qty += change;
        }
        renderCart();
    }

    // Remove Item
    window.removeFromCart = function (index) {
        cart.splice(index, 1);
        renderCart();
    }

    // Clear Cart
    $('#btnClear').click(function () {
        if (confirm('Clear cart?')) {
            cart = [];
            renderCart();
        }
    });

    // Discount Change
    $('#cartDiscount').on('input', renderCart);

    // Pay Button - Open Modal
    $('#btnPay').click(function () {
        if (cart.length === 0) {
            alert('Cart is empty!');
            return;
        }
        $('#modalTotal').text(formatCurrency(currentCartTotal));
        // Default amount paid to total
        $('#amountPaid').val(currentCartTotal.toFixed(2));
        $('#balanceDisplay').addClass('d-none');

        $('#paymentModal').modal('show');
    });

    // Payment Method Change
    $('#paymentMethod').change(function () {
        const method = $(this).val();
        if (method === 'Cash' || method === 'Credit') {
            $('#refGroup').addClass('d-none');
        } else {
            $('#refGroup').removeClass('d-none');
        }
    });

    // Amount Paid Change - Calculate Balance
    $('#amountPaid').on('input', function () {
        const paid = parseFloat($(this).val()) || 0;
        const total = currentCartTotal;
        const balance = total - paid;

        if (balance > 0.01) { // Floating point tolerance
            $('#balanceDisplay').removeClass('d-none').text('Balance Due: ' + formatCurrency(balance));
        } else {
            $('#balanceDisplay').addClass('d-none'); // Or show change? For now focus on Due.
            // If paid > total, show change?
            if (balance < 0) {
                $('#balanceDisplay').removeClass('d-none').removeClass('text-danger').addClass('text-success').text('Change: ' + formatCurrency(Math.abs(balance)));
            }
        }
    });

    // Confirm Payment
    $('#confirmPayment').click(function () {
        const total = currentCartTotal;
        const method = $('#paymentMethod').val();
        const ref = $('#paymentRef').val();
        const customer = $('#customerName').val().trim();
        const amountPaid = parseFloat($('#amountPaid').val()) || 0;

        // Validation for Credit
        if (method === 'Credit') {
            if (customer === '') {
                alert('Customer Name is required for Credit sales.');
                return;
            }
            if (amountPaid >= total) {
                // If they pay full amount, warn or auto-switch to Cash? 
                // Using Credit method usually implies tracking debt, but if fully paid, it's just a named sale.
                // Let's allow it, but debt will be 0.
            }
        }

        // Validation for Cash/Other where paid < total and NOT credit (Partial payment implies credit)
        if (method !== 'Credit' && amountPaid < total - 0.01) {
            if (!confirm('Amount paid is less than total. Record as Credit Sale?')) {
                return;
            }
            // Switch to Credit logic backend handling or force user to switch?
            // Simplest: Auto-switch method val or let backend handle? 
            // Let's force user to change method for clarity, OR change it here.
            $('#paymentMethod').val('Credit').change();
            if (customer === '') {
                alert('Please enter Customer Name for credit sale.');
                return;
            }
        }

        // Defaults for non-credit if user didn't fill anything is 'Walk-in'
        const finalCustomer = customer || 'Walk-in Customer';

        // Disable button to prevent double submit
        $(this).prop('disabled', true).text('Processing...');

        $.ajax({
            url: 'process.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                items: cart,
                discount: $('#cartDiscount').val(),
                total: total,
                payment_method: method,
                payment_ref: ref,
                customer_name: finalCustomer,
                customer_contact: $('#customerContact').val().trim(),
                amount_paid: amountPaid
            }),
            success: function (res) {
                console.log('Sale response:', res);

                if (res && res.success) {
                    console.log('Sale successful, invoice:', res.invoice);

                    // Show receipt modal with options instead of auto-opening
                    showReceiptModal(res.invoice, res.profit);

                } else {
                    alert('Error: ' + (res?.error || 'Unknown error'));
                    $('#confirmPayment').prop('disabled', false).text('Confirm Payment');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                alert('Server error processing sale: ' + error);
                $('#confirmPayment').prop('disabled', false).text('Confirm Payment');
            }
        });
    });

    function updateClock() {
        const now = new Date();
        $('#clock').text(now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
    }

    // Show Receipt Modal with Print and Return options
    function showReceiptModal(invoice, profit) {
        if (!invoice) {
            alert('Error: No invoice generated');
            return;
        }

        // Set invoice number in modal
        const invoiceSpan = document.getElementById('receiptInvoiceNumber');
        if (invoiceSpan) {
            invoiceSpan.textContent = invoice;
        }

        // Set profit in modal
        const profitSpan = document.getElementById('receiptProfitAmount');
        if (profitSpan) {
            profitSpan.textContent = formatCurrency(profit || 0);
        }

        // Close payment modal first
        try {
            const paymentModalEl = document.getElementById('paymentModal');
            if (paymentModalEl) {
                const paymentModalInstance = bootstrap.Modal.getInstance(paymentModalEl);
                if (paymentModalInstance) {
                    paymentModalInstance.hide();
                }
            }
        } catch (e) {
            console.error('Error hiding payment modal:', e);
        }

        // Show receipt modal after a short delay
        setTimeout(function () {
            try {
                const receiptModalEl = document.getElementById('receiptModal');
                if (receiptModalEl) {
                    const receiptModal = new bootstrap.Modal(receiptModalEl, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    receiptModal.show();
                }
            } catch (e) {
                console.error('Error showing receipt modal:', e);
            }
        }, 300);
    }

    // Receipt Modal Actions
    window.printReceipt = function (invoice) {
        console.log('Printing invoice via hidden iframe:', invoice);

        const printFrame = document.getElementById('printFrame');
        if (!printFrame) {
            console.error('Print frame not found!');
            // Fallback to old behavior if frame missing
            window.open(BASE_URL + '/modules/sales/invoice.php?invoice=' + invoice + '&print=1', '_blank');
            return;
        }

        // Set source to invoice page with print param
        printFrame.src = BASE_URL + '/modules/sales/invoice.php?invoice=' + invoice + '&print=1';

        // The invoice.php already has window.print() on load.
        // We just need to wait a tiny bit and then reset the POS.
        setTimeout(function () {
            returnToPOS();
        }, 500);
    }

    window.returnToPOS = function () {
        console.log('Returning to POS');

        // Reset cart and UI
        cart = [];
        renderCart();

        // Reset all form fields
        $('#cartDiscount').val(0);
        $('#cartTotal').text(formatCurrency(0));
        $('#paymentMethod').val('Cash').change();
        $('#customerName').val('');
        $('#customerContact').val('');
        $('#paymentRef').val('');
        $('#amountPaid').val('');
        $('#balanceDisplay').addClass('d-none');
        $('#confirmPayment').prop('disabled', false).text('Confirm Payment');

        // Close any open modals
        try {
            // Hide Payment Modal
            const paymentModalEl = document.getElementById('paymentModal');
            if (paymentModalEl) {
                const paymentModal = bootstrap.Modal.getInstance(paymentModalEl);
                if (paymentModal) paymentModal.hide();
            }

            // Hide Receipt Modal
            const receiptModalEl = document.getElementById('receiptModal');
            if (receiptModalEl) {
                const receiptModal = bootstrap.Modal.getInstance(receiptModalEl);
                if (receiptModal) receiptModal.hide();
            }
        } catch (e) {
            console.error('Error closing modals:', e);
        }

        // Focus on search and Refresh Products
        setTimeout(() => {
            $('#searchProduct').focus();
            loadProducts(); // Fresh stock count from DB
        }, 100);
    }
});
