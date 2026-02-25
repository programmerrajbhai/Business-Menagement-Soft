<?php
// pos.php - Advanced Super POS System
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// [Quick Add Customer] - POS পেজ থেকেই কাস্টমার অ্যাড করার লজিক
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quick_add_customer'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    
    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address, total_due, credit_limit) VALUES (?, ?, '', 0, 10000)");
    $stmt->execute([$name, $phone]);
    header("Location: pos.php?msg=customer_added");
    exit();
}

// কাস্টমার, ক্যাটাগরি এবং প্রোডাক্ট লিস্ট টেনে আনা হচ্ছে
$customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock_qty > 0 ORDER BY p.name ASC")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* POS Special Styling */
    .pos-product-card { transition: all 0.2s; cursor: pointer; border: 1px solid #e0e0e0; }
    .pos-product-card:hover { transform: scale(1.03); border-color: #28a745; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .cart-wrapper { height: 350px; overflow-y: auto; background: #fff; border: 1px solid #dee2e6; border-radius: 5px; }
    .cart-table th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    .quick-cash-btn { border-radius: 20px; font-weight: bold; margin: 2px; }
    .category-pills { overflow-x: auto; white-space: nowrap; padding-bottom: 10px; }
    .category-pills .btn { border-radius: 20px; margin-right: 5px; font-weight: bold; }
    body { background-color: #f4f6f9; }
</style>

<div class="row">
    <div class="col-md-7 mb-3">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 10px;">
            <div class="card-body bg-light">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-success text-white border-success"><i class="fas fa-barcode"></i></span>
                            <input type="text" id="barcode_scanner" class="form-control form-control-lg border-success" placeholder="বারকোড স্ক্যান করুন..." autofocus>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-primary"></i></span>
                            <input type="text" id="product_search" class="form-control form-control-lg" placeholder="প্রোডাক্টের নাম লিখে খুঁজুন..." onkeyup="filterProducts()">
                        </div>
                    </div>
                </div>

                <div class="category-pills mb-3 border-bottom pb-2">
                    <button class="btn btn-primary btn-sm shadow-sm active" onclick="filterCategory('ALL')">All Items</button>
                    <?php foreach($categories as $cat): ?>
                        <button class="btn btn-outline-primary btn-sm shadow-sm" onclick="filterCategory('<?php echo $cat->name; ?>')"><?php echo $cat->name; ?></button>
                    <?php endforeach; ?>
                </div>
                
                <div class="row" id="productGrid" style="max-height: 550px; overflow-y: auto; overflow-x: hidden;">
                    <?php foreach($products as $p): ?>
                    <div class="col-md-3 col-sm-4 col-6 mb-3 product-item" data-name="<?php echo strtolower($p->name); ?>" data-category="<?php echo $p->cat_name; ?>">
                        <div class="card h-100 pos-product-card shadow-sm" onclick="addToCart(<?php echo $p->id; ?>, '<?php echo addslashes($p->name); ?>', <?php echo $p->sale_price; ?>, <?php echo $p->stock_qty; ?>)">
                            <div class="card-body text-center p-2 d-flex flex-column justify-content-center">
                                <h6 class="text-dark fw-bold mb-1" style="font-size: 13px; line-height: 1.2;"><?php echo $p->name; ?></h6>
                                <div class="mt-auto">
                                    <span class="badge bg-success fs-6 mb-1">৳ <?php echo $p->sale_price; ?></span><br>
                                    <small class="text-muted fw-bold">Stock: <?php echo $p->stock_qty; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(count($products) == 0): ?>
                        <div class="col-12 text-center text-danger mt-5">
                            <h5><i class="fas fa-exclamation-circle"></i> স্টকে কোনো প্রোডাক্ট নেই!</h5>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="col-md-5 mb-3">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 10px;">
            <div class="card-body">
                
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                    <h5 class="fw-bold text-primary mb-0"><i class="fas fa-shopping-cart"></i> Shopping Cart</h5>
                    <button class="btn btn-sm btn-danger fw-bold shadow-sm" onclick="clearCart()"><i class="fas fa-trash-alt"></i> Clear All</button>
                </div>

                <div class="input-group mb-2 shadow-sm">
                    <span class="input-group-text bg-white fw-bold"><i class="fas fa-user text-primary"></i></span>
                    <select id="customer_id" class="form-control fw-bold border-start-0 text-dark">
                        <option value="3">Walking Customer (নগদ সেল)</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?php echo $c->id; ?>"><?php echo $c->name; ?> (<?php echo $c->phone; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#quickCustomerModal" title="নতুন কাস্টমার যোগ করুন"><i class="fas fa-plus"></i></button>
                </div>

                <div class="cart-wrapper mb-3 shadow-sm">
                    <table class="table table-sm table-hover align-middle cart-table mb-0" id="cartTable">
                        <thead>
                            <tr class="text-center text-secondary">
                                <th width="45%" class="text-start ps-2">Product Name</th>
                                <th width="20%">Price</th>
                                <th width="20%">Qty</th>
                                <th width="10%">Total</th>
                                <th width="5%"><i class="fas fa-times"></i></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-cart-arrow-down fa-3x mb-3 text-light"></i><br>Cart is empty! Scan or click products.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-light p-2 rounded border shadow-sm">
                    <table class="table table-sm table-borderless fw-bold text-end mb-0">
                        <tr>
                            <td class="align-middle text-muted">Subtotal:</td>
                            <td class="fs-5 text-dark" id="sub_total_display">৳ 0.00</td>
                        </tr>
                        <tr>
                            <td class="align-middle text-muted">Discount (৳):</td>
                            <td><input type="number" id="discount" class="form-control form-control-sm text-end fw-bold border-warning shadow-sm" value="0" onclick="this.select()" onkeyup="calculateFinal()"></td>
                        </tr>
                        <tr class="border-top border-secondary">
                            <td class="text-success fs-5 pt-2">Payable:</td>
                            <td class="text-success fs-3 pt-2" id="net_payable_display">৳ 0.00</td>
                        </tr>
                        <tr>
                            <td class="align-middle text-primary">Cash Paid:</td>
                            <td>
                                <input type="number" id="paid_amount" class="form-control text-end border-primary fw-bold fs-5 shadow-sm" value="0" onclick="this.select()" onkeyup="calculateFinal()">
                                <div class="mt-1 d-flex justify-content-end flex-wrap">
                                    <button class="btn btn-sm btn-outline-secondary quick-cash-btn" onclick="addQuickCash(50)">50</button>
                                    <button class="btn btn-sm btn-outline-secondary quick-cash-btn" onclick="addQuickCash(100)">100</button>
                                    <button class="btn btn-sm btn-outline-secondary quick-cash-btn" onclick="addQuickCash(500)">500</button>
                                    <button class="btn btn-sm btn-outline-secondary quick-cash-btn" onclick="addQuickCash(1000)">1000</button>
                                    <button class="btn btn-sm btn-outline-success quick-cash-btn" onclick="setExactCash()">Exact</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-danger pt-2">Change/Due:</td>
                            <td class="text-danger fs-5 pt-2" id="due_display">৳ 0.00</td>
                        </tr>
                        <tr>
                            <td class="align-middle text-muted">Method:</td>
                            <td>
                                <select id="payment_method" class="form-control form-control-sm fw-bold">
                                    <option value="Cash">Cash</option>
                                    <option value="Bkash">Bkash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Bank">Bank/Card</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <button class="btn btn-success w-100 fw-bold fs-4 mt-3 py-3 shadow" onclick="completeSale()" id="checkoutBtn">
                    <i class="fas fa-check-circle"></i> COMPLETE SALE (F2)
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-primary border-3">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus"></i> Quick Add Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body bg-light">
                    <div class="mb-3">
                        <label class="fw-bold">Customer Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="নাম লিখুন">
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Mobile Number *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="মোবাইল নাম্বার">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="quick_add_customer" class="btn btn-primary fw-bold w-100">Save & Use</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    let cart = []; // মেইন শপিং কার্ট

    // Audio Beep Sound (No external file needed!)
    function playBeep() {
        let context = new (window.AudioContext || window.webkitAudioContext)();
        let oscillator = context.createOscillator();
        let gainNode = context.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(context.destination);
        oscillator.type = "sine";
        oscillator.frequency.value = 800;
        gainNode.gain.setValueAtTime(0.1, context.currentTime);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.1);
    }

    // Live Product Search
    function filterProducts() {
        let input = document.getElementById("product_search").value.toLowerCase();
        let items = document.querySelectorAll(".product-item");
        items.forEach(item => {
            let name = item.getAttribute("data-name");
            if (name.includes(input)) {
                item.style.display = "block";
            } else {
                item.style.display = "none";
            }
        });
    }

    // Category Filter
    function filterCategory(catName) {
        // Update active button color
        $('.category-pills .btn').removeClass('active btn-primary').addClass('btn-outline-primary');
        event.target.classList.remove('btn-outline-primary');
        event.target.classList.add('active', 'btn-primary');

        let items = document.querySelectorAll(".product-item");
        items.forEach(item => {
            let cat = item.getAttribute("data-category");
            if (catName === 'ALL' || cat === catName) {
                item.style.display = "block";
            } else {
                item.style.display = "none";
            }
        });
    }

    // Barcode Scanner Logic
    $('#barcode_scanner').on('keypress', function(e) {
        if(e.which == 13) { // Enter Key
            let barcode = $(this).val();
            if(barcode != '') {
                $.ajax({
                    url: 'ajax/get_product.php',
                    type: 'GET',
                    data: {barcode: barcode},
                    success: function(response) {
                        let res = JSON.parse(response);
                        if(res.status == 'success') {
                            addToCart(res.data.id, res.data.name, res.data.sale_price, res.data.stock_qty);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: 'Product not found!' });
                        }
                    }
                });
                $(this).val(''); // Input clear
            }
        }
    });

    // Add to Cart Logic
    function addToCart(id, name, price, max_stock) {
        playBeep(); // Beep Sound
        let existingItem = cart.find(item => item.id == id);
        if(existingItem) {
            if(existingItem.qty < max_stock) {
                existingItem.qty++;
                existingItem.total = existingItem.qty * existingItem.price;
            } else {
                Swal.fire({ icon: 'warning', title: 'Stock Limit', text: 'Not enough stock available!' });
            }
        } else {
            cart.push({id: id, name: name, price: price, qty: 1, total: price, max_stock: max_stock});
        }
        renderCart();
    }

    // Render Cart HTML
    function renderCart() {
        if(cart.length === 0) {
            $('#cartBody').html('<tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-cart-arrow-down fa-3x mb-3 text-light"></i><br>Cart is empty! Scan or click products.</td></tr>');
            $('#sub_total_display').text('৳ 0.00');
            calculateFinal();
            return;
        }

        let html = '';
        let subTotal = 0;
        cart.forEach((item, index) => {
            subTotal += item.total;
            html += `
            <tr class="bg-white border-bottom">
                <td class="fw-bold text-primary ps-2" style="font-size:13px; line-height:1.2;">${item.name}</td>
                
                <td><input type="number" step="0.01" class="form-control form-control-sm text-center fw-bold text-success border-success" value="${item.price}" onchange="updatePrice(${index}, this.value)" onclick="this.select()"></td>
                
                <td><input type="number" class="form-control form-control-sm text-center fw-bold border-primary" value="${item.qty}" min="1" max="${item.max_stock}" onchange="updateQty(${index}, this.value)" onclick="this.select()"></td>
                
                <td class="fw-bold text-end pe-2">${item.total.toFixed(2)}</td>
                <td class="text-center"><button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})"><i class="fas fa-trash"></i></button></td>
            </tr>`;
        });
        $('#cartBody').html(html);
        $('#sub_total_display').text('৳ ' + subTotal.toFixed(2));
        calculateFinal(subTotal);
    }

    // Dynamic Price Update
    function updatePrice(index, newPrice) {
        let price = parseFloat(newPrice) || 0;
        cart[index].price = price;
        cart[index].total = price * cart[index].qty;
        renderCart();
    }

    // Dynamic Qty Update
    function updateQty(index, newQty) {
        let qty = parseFloat(newQty) || 1;
        if(qty > cart[index].max_stock) {
            Swal.fire({ icon: 'warning', title: 'Stock Limit', text: 'You only have ' + cart[index].max_stock + ' in stock!' });
            qty = cart[index].max_stock;
        }
        cart[index].qty = qty;
        cart[index].total = cart[index].price * qty;
        renderCart();
    }

    // Remove Item
    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
    }
    
    // Clear Cart completely
    function clearCart() {
        if(cart.length > 0) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will empty the cart!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, clear it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    cart = [];
                    renderCart();
                }
            });
        }
    }

    // Quick Cash Buttons Logic
    function addQuickCash(amount) {
        let currentPaid = parseFloat($('#paid_amount').val()) || 0;
        $('#paid_amount').val(currentPaid + amount);
        calculateFinal();
    }
    function setExactCash() {
        let subTotal = cart.reduce((sum, item) => sum + item.total, 0);
        let discount = parseFloat($('#discount').val()) || 0;
        $('#paid_amount').val(subTotal - discount);
        calculateFinal();
    }

    // Final Math (Vat, Discount, Due)
    function calculateFinal(subTotal = null) {
        if(subTotal === null) {
            subTotal = cart.reduce((sum, item) => sum + item.total, 0);
        }
        let discount = parseFloat($('#discount').val()) || 0;
        let netPayable = subTotal - discount;
        $('#net_payable_display').text('৳ ' + netPayable.toFixed(2));

        let paid = parseFloat($('#paid_amount').val()) || 0;
        let due = netPayable - paid;
        
        if(due < 0) {
            // Negative due means Change to give back
            $('#due_display').html('<span class="text-success">Change: ৳ ' + Math.abs(due).toFixed(2) + '</span>');
            due = 0; // For DB, due is 0
        } else {
            $('#due_display').html('৳ ' + due.toFixed(2));
        }
        
        // Disable checkout if walking customer but trying to keep Due
        let customerId = $('#customer_id').val();
        if(customerId == 3 && due > 0) {
            $('#due_display').append('<br><small class="text-danger fw-bold">Walking Customer cannot have Due!</small>');
            $('#checkoutBtn').prop('disabled', true);
        } else {
            $('#checkoutBtn').prop('disabled', false);
        }
    }

    // Keyboard Shortcuts
    $(document).on('keydown', function(e) {
        if (e.key === "F2") {
            e.preventDefault();
            completeSale();
        }
    });

    // Final Submit (AJAX)
    function completeSale() {
        if(cart.length == 0) {
            Swal.fire({ icon: 'warning', title: 'Empty Cart', text: 'Please add products to cart first!' });
            return;
        }
        
        let subTotal = cart.reduce((sum, item) => sum + item.total, 0);
        let discount = parseFloat($('#discount').val()) || 0;
        let payable = subTotal - discount;
        let paid = parseFloat($('#paid_amount').val()) || 0;
        let due = payable - paid;
        if(due < 0) due = 0; // If paid more, due is 0.

        // Prevent walking customer due validation
        let customerId = $('#customer_id').val();
        if(customerId == 3 && due > 0) {
            Swal.fire({ icon: 'error', title: 'Not Allowed', text: 'Walking Customers must pay in full cash. Please add a customer profile to keep Due.' });
            return;
        }

        // Disable button to prevent double click
        $('#checkoutBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        let saleData = {
            customer_id: customerId,
            total_amount: subTotal,
            discount: discount,
            payable_amount: payable,
            paid_amount: paid,
            due_amount: due,
            payment_method: $('#payment_method').val(),
            items: cart
        };

        $.ajax({
            url: 'ajax/save_sale.php',
            type: 'POST',
            data: {saleData: JSON.stringify(saleData)},
            success: function(response) {
                let res = JSON.parse(response);
                if(res.status == 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Bill completed! Invoice: ' + res.invoice_no,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        window.location.reload(); 
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error!', text: res.message });
                    $('#checkoutBtn').prop('disabled', false).html('<i class="fas fa-check-circle"></i> COMPLETE SALE (F2)');
                }
            }
        });
    }

    // Check for URL msg (After adding customer)
    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('msg') === 'customer_added') {
            Swal.fire({ icon: 'success', title: 'Added', text: 'New Customer Added Successfully!', timer: 1500, showConfirmButton: false });
        }
    });
</script>