<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// [অ্যাকশন ১] মাল কেনার ফর্ম সাবমিট হলে
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_purchase'])) {
    $supplier_id = $_POST['supplier_id'];
    $purchase_date = $_POST['purchase_date'];
    $paid_amount = !empty($_POST['paid_amount']) ? $_POST['paid_amount'] : 0;
    $payment_method = $_POST['payment_method'];
    
    $product_ids = $_POST['product_id']; // Array
    $qtys = $_POST['qty']; // Array
    $prices = $_POST['price']; // Array
    
    // টোটাল বিল হিসাব করা
    $total_amount = 0;
    for($i = 0; $i < count($product_ids); $i++) {
        $total_amount += ($qtys[$i] * $prices[$i]);
    }
    
    $due_amount = $total_amount - $paid_amount;
    if($due_amount < 0) $due_amount = 0;

    $invoice_no = "PUR-" . date('dmy') . "-" . rand(100, 999);

    try {
        $pdo->beginTransaction(); // Transaction শুরু

        // ১. Purchases টেবিলে এন্ট্রি
        $stmt = $pdo->prepare("INSERT INTO purchases (invoice_no, supplier_id, total_amount, paid_amount, due_amount, purchase_date, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_no, $supplier_id, $total_amount, $paid_amount, $due_amount, $purchase_date, $current_user_id]);
        $purchase_id = $pdo->lastInsertId();

        // ২. Purchase Items এবং Stock আপডেট করা
        for($i = 0; $i < count($product_ids); $i++) {
            $p_id = $product_ids[$i];
            $q = $qtys[$i];
            $p = $prices[$i];
            $t = $q * $p;

            // আইটেম সেভ
            $stmtItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, qty, price, total) VALUES (?, ?, ?, ?, ?)");
            $stmtItem->execute([$purchase_id, $p_id, $q, $p, $t]);
            
            // গোডাউনে স্টক প্লাস (+) করা
            $stmtStock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtStock->execute([$q, $p, $p_id]); // কেনা দামও আপডেট হয়ে যাবে
        }

        // ৩. মহাজনের পাওনা (Due) আপডেট করা
        if($due_amount > 0) {
            $stmtDue = $pdo->prepare("UPDATE suppliers SET total_due = total_due + ? WHERE id = ?");
            $stmtDue->execute([$due_amount, $supplier_id]);
        }

        // ৪. নগদ টাকা দিলে ক্যাশবুক থেকে মাইনাস (Expense) করা
        if($paid_amount > 0) {
            $note = "Purchase Invoice: " . $invoice_no;
            $stmtCash = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id, supplier_id) VALUES ('Supplier Payment', ?, ?, ?, ?, ?, ?)");
            $stmtCash->execute([$paid_amount, $payment_method, $note, $purchase_date, $current_user_id, $supplier_id]);
        }

        $pdo->commit();
        $success_msg = "নতুন মাল সফলভাবে স্টকে যুক্ত হয়েছে! Invoice: " . $invoice_no;

    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// চেক করা হচ্ছে ইউজার কি ইনভয়েস দেখতে চাচ্ছে নাকি লিস্ট?
$view_invoice_id = isset($_GET['view']) ? $_GET['view'] : null;

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #printInvoiceArea, #printInvoiceArea * { visibility: visible; }
        #printInvoiceArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if($view_invoice_id): 
    // ইনভয়েসের ডাটা আনা
    $stmt = $pdo->prepare("SELECT p.*, s.company_name, s.phone, s.address, u.name as entry_by 
                           FROM purchases p 
                           JOIN suppliers s ON p.supplier_id = s.id 
                           JOIN users u ON p.user_id = u.id
                           WHERE p.id = ?");
    $stmt->execute([$view_invoice_id]);
    $invoice = $stmt->fetch();

    if(!$invoice) { die("<div class='alert alert-danger text-center mt-5'>Invoice Not Found!</div>"); }

    // ইনভয়েসের আইটেমগুলো আনা
    $stmtItems = $pdo->prepare("SELECT pi.*, pr.name as product_name FROM purchase_items pi JOIN products pr ON pi.product_id = pr.id WHERE pi.purchase_id = ?");
    $stmtItems->execute([$view_invoice_id]);
    $items = $stmtItems->fetchAll();
?>
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3 class="fw-bold text-dark"><i class="fas fa-file-invoice text-primary"></i> Purchase Invoice</h3>
        <div>
            <button class="btn btn-dark fw-bold me-2" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
            <a href="purchases.php" class="btn btn-secondary fw-bold"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 10px;" id="printInvoiceArea">
        <div class="card-body p-5">
            <div class="row border-bottom pb-4 mb-4">
                <div class="col-sm-6">
                    <h2 class="fw-bold text-primary mb-0">Bseba Enterprise</h2>
                    <p class="text-muted mb-0">Purchase & Inventory Record</p>
                </div>
                <div class="col-sm-6 text-end">
                    <h4 class="fw-bold text-dark">INVOICE: <?php echo $invoice->invoice_no; ?></h4>
                    <p class="text-muted fw-bold mb-0">Date: <?php echo format_date($invoice->purchase_date); ?></p>
                    <small class="text-muted">Entry by: <?php echo $invoice->entry_by; ?></small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-sm-12">
                    <h6 class="fw-bold text-secondary text-uppercase mb-2">Supplier / Company Details:</h6>
                    <h5 class="fw-bold text-dark mb-1"><?php echo $invoice->company_name; ?></h5>
                    <p class="mb-0"><i class="fas fa-phone-alt text-muted"></i> <?php echo $invoice->phone; ?></p>
                    <p class="mb-0"><i class="fas fa-map-marker-alt text-muted"></i> <?php echo $invoice->address; ?></p>
                </div>
            </div>

            <table class="table table-bordered align-middle">
                <thead class="table-light text-center fw-bold">
                    <tr>
                        <th width="5%">SL</th>
                        <th width="45%" class="text-start">Product Description</th>
                        <th width="15%">Unit Price</th>
                        <th width="15%">Quantity</th>
                        <th width="20%" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sl = 1; foreach($items as $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $sl++; ?></td>
                        <td class="fw-bold"><?php echo $item->product_name; ?></td>
                        <td class="text-center"><?php echo format_taka($item->price); ?></td>
                        <td class="text-center fw-bold"><?php echo $item->qty; ?></td>
                        <td class="text-end fw-bold"><?php echo format_taka($item->total); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="row justify-content-end mt-4">
                <div class="col-sm-5">
                    <table class="table table-sm table-borderless text-end fs-6">
                        <tr>
                            <td class="fw-bold text-muted">Grand Total:</td>
                            <td class="fw-bold text-dark fs-5"><?php echo format_taka($invoice->total_amount); ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="fw-bold text-success">Paid Amount:</td>
                            <td class="fw-bold text-success">- <?php echo format_taka($invoice->paid_amount); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-danger">Due Balance:</td>
                            <td class="fw-bold text-danger fs-4"><?php echo format_taka($invoice->due_amount); ?></td>
                        </tr>
                    </table>
                    <?php if($invoice->due_amount <= 0): ?>
                        <div class="text-center mt-3">
                            <h3 class="fw-bold text-success border border-2 border-success rounded p-2 d-inline-block px-4">PAID IN FULL</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-5 pt-5 border-top text-muted small">
                <p class="mb-0">This is a computer-generated invoice and requires no signature.</p>
            </div>
        </div>
    </div>

<?php else: 
    // ==========================================
    // VIEW 2: PURCHASES LIST & SEARCH (মেইন পেজ)
    // ==========================================
    $purchases = $pdo->query("SELECT p.*, s.company_name FROM purchases p JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.id DESC")->fetchAll();
    $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY company_name ASC")->fetchAll();
    $products = $pdo->query("SELECT id, name, purchase_price FROM products ORDER BY name ASC")->fetchAll();
?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fas fa-shopping-basket text-primary"></i> Purchase History</h3>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
            <i class="fas fa-cart-plus"></i> New Purchase (নতুন মাল এন্ট্রি)
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-primary border-end-0"><i class="fas fa-search text-primary"></i></span>
                <input type="text" id="searchInput" class="form-control border-primary border-start-0 form-control-lg" placeholder="ইনভয়েস নম্বর বা কোম্পানির নাম দিয়ে খুঁজুন..." onkeyup="filterTable()">
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 10px;">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" id="purchaseListTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Invoice No</th>
                        <th>Supplier / Company</th>
                        <th>Total Bill</th>
                        <th>Status</th>
                        <th class="text-center pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($purchases as $row): ?>
                    <tr>
                        <td class="ps-4 text-muted fw-bold"><?php echo format_date($row->purchase_date); ?></td>
                        <td><span class="badge bg-secondary px-2 py-1"><i class="fas fa-file-invoice"></i> <?php echo $row->invoice_no; ?></span></td>
                        <td class="fw-bold text-primary fs-6"><?php echo $row->company_name; ?></td>
                        <td class="fw-bold text-dark"><?php echo format_taka($row->total_amount); ?></td>
                        <td>
                            <?php if($row->due_amount <= 0): ?>
                                <span class="badge bg-success px-3 py-2 rounded-pill shadow-sm">Paid</span>
                            <?php elseif($row->paid_amount > 0 && $row->due_amount > 0): ?>
                                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">Partial (Due: <?php echo $row->due_amount; ?>)</span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm">Unpaid (Due: <?php echo $row->due_amount; ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <a href="purchases.php?view=<?php echo $row->id; ?>" class="btn btn-sm btn-info text-white fw-bold shadow-sm">
                                <i class="fas fa-eye"></i> View Invoice
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addPurchaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl"> 
            <div class="modal-content border-primary border-3">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-cart-plus"></i> Add New Purchase (গোডাউনে মাল তুলুন)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body bg-light">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Supplier / Company *</label>
                                <select name="supplier_id" class="form-control form-control-lg border-primary" required>
                                    <option value="">-- মহাজন সিলেক্ট করুন --</option>
                                    <?php foreach($suppliers as $s): ?>
                                        <option value="<?php echo $s->id; ?>"><?php echo $s->company_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Purchase Date *</label>
                                <input type="date" name="purchase_date" class="form-control form-control-lg border-primary" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="card mb-3 shadow-sm border-0">
                            <div class="card-body p-2">
                                <table class="table table-bordered align-middle mb-0" id="purchaseTable">
                                    <thead class="table-dark text-center">
                                        <tr>
                                            <th width="40%">Product Name</th>
                                            <th width="20%">Quantity (পরিমাণ)</th>
                                            <th width="20%">Unit Price (কেনা দাম)</th>
                                            <th width="15%">Line Total</th>
                                            <th width="5%"><button type="button" class="btn btn-sm btn-success fw-bold w-100" onclick="addRow()"><i class="fas fa-plus"></i></button></th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchaseBody" class="bg-white">
                                        <tr>
                                            <td>
                                                <select name="product_id[]" class="form-control" required>
                                                    <option value="">-- প্রোডাক্ট সিলেক্ট করুন --</option>
                                                    <?php foreach($products as $p): ?>
                                                        <option value="<?php echo $p->id; ?>" data-price="<?php echo $p->purchase_price; ?>"><?php echo $p->name; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="number" name="qty[]" class="form-control text-center fw-bold qty-input" value="1" min="1" required onkeyup="calculateTotal()" onchange="calculateTotal()"></td>
                                            <td><input type="number" step="0.01" name="price[]" class="form-control text-end fw-bold text-danger price-input" value="0" required onkeyup="calculateTotal()"></td>
                                            <td><input type="text" class="form-control text-end fw-bold text-dark line-total bg-light" value="0.00" readonly></td>
                                            <td class="text-center"><button type="button" class="btn btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row justify-content-end">
                            <div class="col-md-5">
                                <div class="bg-white p-3 rounded shadow-sm border">
                                    <table class="table table-sm table-borderless fw-bold text-end mb-0">
                                        <tr>
                                            <td class="align-middle">Total Bill:</td>
                                            <td><input type="text" id="grand_total" class="form-control text-end fs-4 fw-bold text-primary bg-light" value="0.00" readonly></td>
                                        </tr>
                                        <tr>
                                            <td class="align-middle text-success">Paid Amount:</td>
                                            <td><input type="number" step="0.01" name="paid_amount" id="paid_amount" class="form-control form-control-lg text-end border-success text-success fw-bold" value="0" onkeyup="calculateDue()"></td>
                                        </tr>
                                        <tr>
                                            <td class="align-middle text-danger">Due Amount:</td>
                                            <td><input type="text" id="due_amount" class="form-control text-end fs-5 text-danger bg-white border-0" value="0.00" readonly></td>
                                        </tr>
                                        <tr>
                                            <td class="align-middle text-muted">Payment Method:</td>
                                            <td>
                                                <select name="payment_method" class="form-control">
                                                    <option value="Cash">Cash (গাল্লা থেকে)</option>
                                                    <option value="Bank">Bank / Cheque</option>
                                                    <option value="Bkash">Bkash / Nagad</option>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-top-0">
                        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save_purchase" class="btn btn-primary fw-bold fs-5 px-5"><i class="fas fa-save"></i> Save Purchase</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // রিয়েল-টাইম স্মার্ট সার্চ
        function filterTable() {
            let input = document.getElementById("searchInput").value.toUpperCase();
            let table = document.getElementById("purchaseListTable");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let tdInvoice = tr[i].getElementsByTagName("td")[1];
                let tdSupplier = tr[i].getElementsByTagName("td")[2];
                if (tdInvoice || tdSupplier) {
                    let txtValue = (tdInvoice.textContent || tdInvoice.innerText) + " " + (tdSupplier.textContent || tdSupplier.innerText);
                    if (txtValue.toUpperCase().indexOf(input) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }

        // ড্রপডাউন থেকে প্রোডাক্ট সিলেক্ট করলে অটোমেটিক কেনা দাম বসে যাবে
        $(document).on('change', 'select[name="product_id[]"]', function() {
            let price = $(this).find(':selected').data('price');
            $(this).closest('tr').find('.price-input').val(price);
            calculateTotal();
        });

        // নতুন প্রোডাক্টের সারি (Row) যোগ করা
        function addRow() {
            let tr = `<tr>
                <td>
                    <select name="product_id[]" class="form-control" required>
                        <option value="">-- প্রোডাক্ট সিলেক্ট করুন --</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p->id; ?>" data-price="<?php echo $p->purchase_price; ?>"><?php echo $p->name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="qty[]" class="form-control text-center fw-bold qty-input" value="1" min="1" required onkeyup="calculateTotal()" onchange="calculateTotal()"></td>
                <td><input type="number" step="0.01" name="price[]" class="form-control text-end fw-bold text-danger price-input" value="0" required onkeyup="calculateTotal()"></td>
                <td><input type="text" class="form-control text-end fw-bold text-dark line-total bg-light" value="0.00" readonly></td>
                <td class="text-center"><button type="button" class="btn btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
            </tr>`;
            $('#purchaseBody').append(tr);
        }

        // সারি মুছে ফেলা
        function removeRow(btn) {
            if($('#purchaseBody tr').length > 1) {
                $(btn).closest('tr').remove();
                calculateTotal();
            } else {
                alert("অন্তত একটি প্রোডাক্ট থাকতে হবে!");
            }
        }

        // টোটাল হিসাব করা
        function calculateTotal() {
            let grandTotal = 0;
            $('#purchaseBody tr').each(function() {
                let qty = parseFloat($(this).find('.qty-input').val()) || 0;
                let price = parseFloat($(this).find('.price-input').val()) || 0;
                let lineTotal = qty * price;
                
                $(this).find('.line-total').val(lineTotal.toFixed(2));
                grandTotal += lineTotal;
            });
            $('#grand_total').val(grandTotal.toFixed(2));
            calculateDue();
        }

        // বাকি হিসাব করা
        function calculateDue() {
            let grandTotal = parseFloat($('#grand_total').val()) || 0;
            let paid = parseFloat($('#paid_amount').val()) || 0;
            let due = grandTotal - paid;
            if(due < 0) due = 0;
            $('#due_amount').val(due.toFixed(2));
        }
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>