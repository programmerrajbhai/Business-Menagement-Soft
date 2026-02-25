<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// মাল কেনার ফর্ম সাবমিট হলে
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
            $stmtStock->execute([$q, $p, $p_id]); // কেনা দামও আপডেট হয়ে যাবে
        }

        // ৩. মহাজনের পাওনা (Due) আপডেট করা
        if($due_amount > 0) {
            $stmtDue = $pdo->prepare("UPDATE suppliers SET total_due = total_due + ? WHERE id = ?");
            $stmtDue->execute([$due_amount, $supplier_id]);
        }

        // ৪. নগদ টাকা দিলে ক্যাশবুক থেকে মাইনাস (Expense) করা
        if($paid_amount > 0) {
            $note = "Purchase Invoice: " . $invoice_no;
            $stmtCash = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id) VALUES ('Supplier Payment', ?, ?, ?, ?, ?)");
            $stmtCash->execute([$paid_amount, $payment_method, $note, $purchase_date, $current_user_id]);
        }

        $pdo->commit();
        $success_msg = "নতুন মাল সফলভাবে স্টকে যুক্ত হয়েছে! Invoice: " . $invoice_no;

    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// লিস্ট দেখানোর জন্য ডাটা আনা
$purchases = $pdo->query("SELECT p.*, s.company_name FROM purchases p JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.id DESC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY company_name ASC")->fetchAll();
$products = $pdo->query("SELECT id, name, purchase_price FROM products ORDER BY name ASC")->fetchAll();

// ২. Frontend Design
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-shopping-basket text-primary"></i> Purchase / Procurement</h3>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
        <i class="fas fa-cart-plus"></i> New Purchase (নতুন মাল তুলুন)
    </button>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
<?php endif; ?>
<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="border-radius: 10px; border: none;">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Invoice No</th>
                    <th>Supplier / Company</th>
                    <th>Total Bill</th>
                    <th>Paid Amount</th>
                    <th>Due (বাকি)</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($purchases as $row): ?>
                <tr>
                    <td><?php echo format_date($row->purchase_date); ?></td>
                    <td><span class="badge bg-secondary"><?php echo $row->invoice_no; ?></span></td>
                    <td class="fw-bold text-primary"><?php echo $row->company_name; ?></td>
                    <td class="fw-bold"><?php echo format_taka($row->total_amount); ?></td>
                    <td class="text-success fw-bold"><?php echo format_taka($row->paid_amount); ?></td>
                    <td>
                        <?php if($row->due_amount > 0): ?>
                            <span class="text-danger fw-bold"><?php echo format_taka($row->due_amount); ?></span>
                        <?php else: ?>
                            <span class="badge bg-success">Paid</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info text-white"><i class="fas fa-eye"></i> View</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addPurchaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-cart-plus"></i> Add New Purchase (মাল এন্ট্রি)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body bg-light">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Supplier / Company *</label>
                            <select name="supplier_id" class="form-control" required>
                                <option value="">-- মহাজন সিলেক্ট করুন --</option>
                                <?php foreach($suppliers as $s): ?>
                                    <option value="<?php echo $s->id; ?>"><?php echo $s->company_name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Purchase Date *</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body p-2">
                            <table class="table table-bordered table-sm mb-0" id="purchaseTable">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th width="40%">Product Name</th>
                                        <th width="20%">Quantity (পরিমাণ)</th>
                                        <th width="20%">Unit Price (কেনা দাম)</th>
                                        <th width="15%">Line Total</th>
                                        <th width="5%"><button type="button" class="btn btn-sm btn-success" onclick="addRow()"><i class="fas fa-plus"></i></button></th>
                                    </tr>
                                </thead>
                                <tbody id="purchaseBody">
                                    <tr>
                                        <td>
                                            <select name="product_id[]" class="form-control form-control-sm" required>
                                                <option value="">-- প্রোডাক্ট সিলেক্ট করুন --</option>
                                                <?php foreach($products as $p): ?>
                                                    <option value="<?php echo $p->id; ?>" data-price="<?php echo $p->purchase_price; ?>"><?php echo $p->name; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="qty[]" class="form-control form-control-sm text-center qty-input" value="1" min="1" required onkeyup="calculateTotal()" onchange="calculateTotal()"></td>
                                        <td><input type="number" step="0.01" name="price[]" class="form-control form-control-sm text-end price-input" value="0" required onkeyup="calculateTotal()"></td>
                                        <td><input type="text" class="form-control form-control-sm text-end line-total" value="0.00" readonly></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <table class="table table-sm table-borderless fw-bold text-end">
                                <tr>
                                    <td class="align-middle">Total Bill:</td>
                                    <td><input type="text" id="grand_total" class="form-control text-end fs-5 fw-bold text-primary" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <td class="align-middle">Paid Amount:</td>
                                    <td><input type="number" step="0.01" name="paid_amount" id="paid_amount" class="form-control text-end border-success" value="0" onkeyup="calculateDue()"></td>
                                </tr>
                                <tr>
                                    <td class="align-middle text-danger">Due Amount:</td>
                                    <td><input type="text" id="due_amount" class="form-control text-end fs-5 text-danger bg-white" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <td class="align-middle">Payment Method:</td>
                                    <td>
                                        <select name="payment_method" class="form-control">
                                            <option value="Cash">Cash (গাল্লা থেকে)</option>
                                            <option value="Bank">Bank / Cheque</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="save_purchase" class="btn btn-primary fw-bold fs-5"><i class="fas fa-save"></i> Save Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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
                <select name="product_id[]" class="form-control form-control-sm" required>
                    <option value="">-- প্রোডাক্ট সিলেক্ট করুন --</option>
                    <?php foreach($products as $p): ?>
                        <option value="<?php echo $p->id; ?>" data-price="<?php echo $p->purchase_price; ?>"><?php echo $p->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" name="qty[]" class="form-control form-control-sm text-center qty-input" value="1" min="1" required onkeyup="calculateTotal()" onchange="calculateTotal()"></td>
            <td><input type="number" step="0.01" name="price[]" class="form-control form-control-sm text-end price-input" value="0" required onkeyup="calculateTotal()"></td>
            <td><input type="text" class="form-control form-control-sm text-end line-total" value="0.00" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
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

<?php include 'includes/footer.php'; ?>