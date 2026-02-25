<?php
// pos.php
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// কাস্টমার এবং প্রোডাক্ট লিস্ট টেনে আনা হচ্ছে ড্রপডাউনের জন্য
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT * FROM products WHERE stock_qty > 0")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="row">
    <div class="col-md-7">
        <div class="card shadow-sm mb-3" style="border-radius: 10px; border: none;">
            <div class="card-body bg-light">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-success text-white"><i class="fas fa-barcode"></i></span>
                    <input type="text" id="barcode_scanner" class="form-control form-control-lg" placeholder="বারকোড স্ক্যান করুন..." autofocus>
                </div>
                
                <div class="row">
                    <?php foreach($products as $p): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-primary" style="cursor: pointer;" onclick="addToCart(<?php echo $p->id; ?>, '<?php echo addslashes($p->name); ?>', <?php echo $p->sale_price; ?>, <?php echo $p->stock_qty; ?>)">
                            <div class="card-body text-center p-2">
                                <h6 class="text-dark fw-bold mb-1" style="font-size: 14px;"><?php echo $p->name; ?></h6>
                                <span class="badge bg-success"><?php echo format_taka($p->sale_price); ?></span><br>
                                <small class="text-muted">Stock: <?php echo $p->stock_qty; ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card shadow-sm" style="border-radius: 10px; border: none;">
            <div class="card-body">
                <h5 class="fw-bold text-primary border-bottom pb-2"><i class="fas fa-shopping-cart"></i> Current Sale</h5>
                
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mt-2" id="cartTable">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th width="20%">Qty</th>
                                <th>Total</th>
                                <th><i class="fas fa-trash text-danger"></i></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-light fw-bold">Customer</span>
                        <select id="customer_id" class="form-control">
                            <option value="3">Walking Customer (Cash)</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?php echo $c->id; ?>"><?php echo $c->name; ?> (<?php echo $c->phone; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <table class="table table-sm table-borderless fw-bold text-end">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="fs-5" id="sub_total_display">৳ 0.00</td>
                        </tr>
                        <tr>
                            <td class="align-middle">Discount (৳):</td>
                            <td><input type="number" id="discount" class="form-control text-end" value="0" onkeyup="calculateFinal()"></td>
                        </tr>
                        <tr>
                            <td class="text-success fs-5">Net Payable:</td>
                            <td class="text-success fs-4" id="net_payable_display">৳ 0.00</td>
                        </tr>
                        <tr>
                            <td class="align-middle text-primary">Paid Amount:</td>
                            <td><input type="number" id="paid_amount" class="form-control text-end border-primary" value="0" onkeyup="calculateFinal()"></td>
                        </tr>
                        <tr>
                            <td class="text-danger">Due (বকেয়া):</td>
                            <td class="text-danger fs-5" id="due_display">৳ 0.00</td>
                        </tr>
                        <tr>
                            <td class="align-middle">Payment Method:</td>
                            <td>
                                <select id="payment_method" class="form-control">
                                    <option value="Cash">Cash</option>
                                    <option value="Bkash">Bkash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Bank">Bank</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <button class="btn btn-success w-100 fw-bold fs-5 mt-2 py-2" onclick="completeSale()"><i class="fas fa-check-circle"></i> Complete Sale (বিল করুন)</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    let cart = []; // শপিং কার্ট অ্যারে

    // বারকোড স্ক্যানার লজিক
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
                            alert("প্রোডাক্ট পাওয়া যায়নি!");
                        }
                    }
                });
                $(this).val(''); // স্ক্যানারের ঘর আবার ফাঁকা করে দেওয়া
            }
        }
    });

    // কার্টে প্রোডাক্ট অ্যাড করার ফাংশন
    function addToCart(id, name, price, max_stock) {
        let existingItem = cart.find(item => item.id == id);
        if(existingItem) {
            if(existingItem.qty < max_stock) {
                existingItem.qty++;
                existingItem.total = existingItem.qty * existingItem.price;
            } else {
                alert("স্টক শেষ! আর অ্যাড করা যাবে না।");
            }
        } else {
            cart.push({id: id, name: name, price: price, qty: 1, total: price, max_stock: max_stock});
        }
        renderCart();
    }

    // কার্টের ডিজাইন আপডেট করার ফাংশন
    function renderCart() {
        let html = '';
        let subTotal = 0;
        cart.forEach((item, index) => {
            subTotal += item.total;
            html += `
            <tr>
                <td class="fw-bold text-dark" style="font-size:13px;">${item.name}</td>
                <td>${item.price}</td>
                <td><input type="number" class="form-control form-control-sm" value="${item.qty}" min="1" max="${item.max_stock}" onchange="updateQty(${index}, this.value)"></td>
                <td class="fw-bold">${item.total}</td>
                <td><button class="btn btn-sm btn-danger" onclick="removeItem(${index})"><i class="fas fa-times"></i></button></td>
            </tr>`;
        });
        $('#cartBody').html(html);
        $('#sub_total_display').text('৳ ' + subTotal.toFixed(2));
        calculateFinal(subTotal);
    }

    function updateQty(index, newQty) {
        if(newQty > cart[index].max_stock) {
            alert("এত স্টক নেই!");
            newQty = cart[index].max_stock;
        }
        cart[index].qty = newQty;
        cart[index].total = cart[index].price * newQty;
        renderCart();
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
    }

    // চূড়ান্ত হিসাব (ভ্যাট/ডিসকাউন্ট/বাকি)
    function calculateFinal(subTotal = null) {
        if(subTotal === null) {
            subTotal = cart.reduce((sum, item) => sum + item.total, 0);
        }
        let discount = parseFloat($('#discount').val()) || 0;
        let netPayable = subTotal - discount;
        $('#net_payable_display').text('৳ ' + netPayable.toFixed(2));

        let paid = parseFloat($('#paid_amount').val()) || 0;
        let due = netPayable - paid;
        if(due < 0) due = 0; // বেশি টাকা দিলে বাকি মাইনাস হবে না
        $('#due_display').text('৳ ' + due.toFixed(2));
    }

    // ফাইনাল বিল সেভ করা (AJAX)
    function completeSale() {
        if(cart.length == 0) {
            alert("কার্টে কোনো প্রোডাক্ট নেই!"); return;
        }
        
        let subTotal = cart.reduce((sum, item) => sum + item.total, 0);
        let discount = parseFloat($('#discount').val()) || 0;
        let payable = subTotal - discount;
        let paid = parseFloat($('#paid_amount').val()) || 0;
        let due = payable - paid;

        let saleData = {
            customer_id: $('#customer_id').val(),
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
                    alert("✅ বিল সফলভাবে সম্পন্ন হয়েছে! Invoice: " + res.invoice_no);
                    window.location.reload(); // বিল শেষে পেজ ফ্রেশ করে দেওয়া
                } else {
                    alert("Error: " + res.message);
                }
            }
        });
    }
</script>