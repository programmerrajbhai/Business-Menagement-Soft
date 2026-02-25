<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// [অ্যাকশন ১] নতুন সাপ্লায়ার/মহাজন যুক্ত করা
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supplier'])) {
    $company_name = $_POST['company_name'];
    $contact_person = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $total_due = !empty($_POST['total_due']) ? $_POST['total_due'] : 0.00;

    try {
        $sql = "INSERT INTO suppliers (company_name, contact_person, phone, address, total_due) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$company_name, $contact_person, $phone, $address, $total_due]);
        $success_msg = "নতুন সাপ্লায়ার/কোম্পানি সফলভাবে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// [অ্যাকশন ২] মহাজনকে টাকা পেমেন্ট করা (Pay to Supplier)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay_supplier'])) {
    $s_id = $_POST['supplier_id'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'];
    $note = $_POST['note'];
    $date = date('Y-m-d');

    try {
        $pdo->beginTransaction();
        
        // ১. সাপ্লায়ারের মোট দেনা কমানো
        $stmtDue = $pdo->prepare("UPDATE suppliers SET total_due = total_due - ? WHERE id = ?");
        $stmtDue->execute([$amount, $s_id]);

        // ২. ক্যাশবুকে খরচের এন্ট্রি করা (supplier_id সহ)
        $stmtCash = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id, supplier_id) VALUES ('Supplier Payment', ?, ?, ?, ?, ?, ?)");
        $stmtCash->execute([$amount, $method, $note, $date, $current_user_id, $s_id]);

        $pdo->commit();
        $success_msg = "মহাজনকে পেমেন্ট সফলভাবে সম্পন্ন হয়েছে এবং ক্যাশবুক আপডেট হয়েছে!";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_msg = "পেমেন্ট করতে সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// [অ্যাকশন ৩] ম্যানুয়াল দেনা/বকেয়া যোগ করা (Manual Due Add)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_manual_due'])) {
    $s_id = $_POST['supplier_id'];
    $amount = $_POST['amount'];
    $note = $_POST['note'];
    $date = date('Y-m-d');

    try {
        $pdo->beginTransaction();
        
        // ১. সাপ্লায়ারের দেনা বাড়ানো
        $stmtDue = $pdo->prepare("UPDATE suppliers SET total_due = total_due + ? WHERE id = ?");
        $stmtDue->execute([$amount, $s_id]);

        // ২. লেজারে দেখানোর জন্য ট্রানজেকশনে এন্ট্রি (ক্যাশবুক থেকে টাকা কাটবে না)
        $stmtCash = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id, supplier_id) VALUES ('Supplier Manual Due', ?, 'N/A', ?, ?, ?, ?)");
        $stmtCash->execute([$amount, $note, $date, $current_user_id, $s_id]);

        $pdo->commit();
        $success_msg = "মহাজনের খাতায় নতুন দেনা সফলভাবে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_msg = "দেনা যোগ করতে সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// চেক করা হচ্ছে ইউজার কি প্রোফাইল দেখতে চাচ্ছে নাকি লিস্ট?
$view_profile_id = isset($_GET['profile']) ? $_GET['profile'] : null;

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #printLedgerArea, #printLedgerArea * { visibility: visible; }
        #printLedgerArea { position: absolute; left: 0; top: 0; width: 100%; }
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

<?php if($view_profile_id): 
    // সাপ্লায়ারের ডাটা আনা
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$view_profile_id]);
    $supplier = $stmt->fetch();

    if(!$supplier) {
        die("<div class='alert alert-danger text-center mt-5'>Supplier Not Found!</div>");
    }

    // সাপ্লায়ারের সম্পূর্ণ লেজার (Purchase + Payment Sent + Manual Due) একসাথে আনা
    $ledger_stmt = $pdo->prepare("
        SELECT purchase_date as date, invoice_no as ref, 'Purchase Bill' as type, total_amount as bill, paid_amount as paid, due_amount as due 
        FROM purchases WHERE supplier_id = ?
        UNION ALL
        SELECT date as date, note as ref, 'Payment Sent' as type, 0 as bill, amount as paid, 0 as due 
        FROM transactions WHERE type = 'Supplier Payment' AND supplier_id = ?
        UNION ALL
        SELECT date as date, note as ref, 'Manual Due Added' as type, amount as bill, 0 as paid, amount as due 
        FROM transactions WHERE type = 'Supplier Manual Due' AND supplier_id = ?
        ORDER BY date DESC
    ");
    $ledger_stmt->execute([$view_profile_id, $view_profile_id, $view_profile_id]);
    $ledgers = $ledger_stmt->fetchAll();
?>
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3 class="fw-bold text-dark"><i class="fas fa-truck text-warning"></i> Supplier Ledger</h3>
        <a href="suppliers.php" class="btn btn-secondary fw-bold"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3 no-print">
            <div class="card shadow-sm border-0 border-top border-warning border-3" style="border-radius: 10px;">
                <div class="card-body text-center mt-3">
                    <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-building text-warning fa-3x"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $supplier->company_name; ?></h4>
                    <p class="text-muted mb-1"><i class="fas fa-user-tie"></i> <?php echo $supplier->contact_person; ?></p>
                    <p class="text-muted mb-2"><i class="fas fa-map-marker-alt"></i> <?php echo $supplier->address; ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <a href="tel:<?php echo $supplier->phone; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="fas fa-phone-alt"></i> Call</a>
                        <a href="https://wa.me/88<?php echo $supplier->phone; ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                    </div>
                    <hr>

                    <div class="bg-warning text-dark p-3 rounded mb-3 shadow-sm border border-warning">
                        <h6 class="mb-1 fw-bold">Payable Due (কোম্পানির পাওনা)</h6>
                        <h2 class="fw-bold mb-0"><?php echo format_taka($supplier->total_due); ?></h2>
                    </div>
                    
                    <button class="btn btn-success fw-bold w-100 mb-2 py-2 shadow-sm" onclick="openPayModal(<?php echo $supplier->id; ?>, '<?php echo addslashes($supplier->company_name); ?>', <?php echo $supplier->total_due; ?>)">
                        <i class="fas fa-money-bill-wave"></i> Pay to Supplier (পেমেন্ট করুন)
                    </button>
                    <button class="btn btn-outline-danger fw-bold w-100 mb-2" onclick="openAddDueModal(<?php echo $supplier->id; ?>, '<?php echo addslashes($supplier->company_name); ?>')">
                        <i class="fas fa-plus-circle"></i> Add Due (নতুন দেনা যোগ)
                    </button>
                    <button class="btn btn-dark fw-bold w-100" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Ledger (খাতা প্রিন্ট)
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0" style="border-radius: 10px;" id="printLedgerArea">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-warning text-dark fs-5"><i class="fas fa-book"></i> Supplier Ledger Book (কোম্পানির খাতা)</span>
                    <span class="d-none d-print-block fs-5 fw-bold text-dark">Supplier: <?php echo $supplier->company_name; ?> (<?php echo $supplier->phone; ?>)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th>Particulars (বিবরণ)</th>
                                    <th>Ref / Invoice</th>
                                    <th class="text-danger text-end">Bill (মালের দাম)</th>
                                    <th class="text-success text-end">Paid (পেমেন্ট)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($ledgers as $l): ?>
                                <tr>
                                    <td class="fw-bold text-muted"><?php echo format_date($l->date); ?></td>
                                    <td>
                                        <?php if($l->type == 'Purchase Bill'): ?>
                                            <span class="badge bg-primary">Purchase Invoice</span>
                                        <?php elseif($l->type == 'Payment Sent'): ?>
                                            <span class="badge bg-success">Payment Sent</span>
                                        <?php elseif($l->type == 'Manual Due Added'): ?>
                                            <span class="badge bg-danger">Manual Due Add</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-dark small fw-bold"><?php echo $l->ref; ?></td>
                                    <td class="text-danger text-end fw-bold"><?php echo ($l->bill > 0) ? format_taka($l->bill) : '-'; ?></td>
                                    <td class="text-success text-end fw-bold"><?php echo ($l->paid > 0) ? format_taka($l->paid) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(count($ledgers) == 0): ?>
                                    <tr><td colspan="5" class="text-center text-muted p-4">কোনো লেনদেনের রেকর্ড নেই!</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light d-none d-print-table-row">
                                <tr>
                                    <th colspan="3" class="text-end">Current Payable Due (কোম্পানি পাবে):</th>
                                    <th colspan="2" class="text-center text-danger fs-5"><?php echo format_taka($supplier->total_due); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: 
    // ==========================================
    // VIEW 2: SUPPLIER LIST & SEARCH (মেইন পেজ)
    // ==========================================
    $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC")->fetchAll();
?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fas fa-truck text-warning"></i> Suppliers & Companies</h3>
        <button class="btn btn-warning text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="fas fa-plus-circle"></i> Add New Supplier
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-warning border-end-0"><i class="fas fa-search text-warning"></i></span>
                <input type="text" id="searchInput" class="form-control border-warning border-start-0 form-control-lg" placeholder="কোম্পানি বা মহাজনের নাম দিয়ে খুঁজুন..." onkeyup="filterTable()">
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 10px;">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" id="supplierTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Company Info</th>
                        <th>Contact Person</th>
                        <th>Payable Due (দেনা)</th>
                        <th class="text-center pe-4">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($suppliers as $row): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-primary fs-6"><?php echo $row->company_name; ?></div>
                            <div class="text-muted small"><i class="fas fa-phone-alt"></i> <?php echo $row->phone; ?></div>
                        </td>
                        <td class="text-muted"><i class="fas fa-user"></i> <?php echo $row->contact_person; ?></td>
                        <td>
                            <?php if($row->total_due > 0): ?>
                                <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill shadow-sm"><?php echo format_taka($row->total_due); ?></span>
                            <?php else: ?>
                                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill shadow-sm">Clear</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group">
                                <a href="suppliers.php?profile=<?php echo $row->id; ?>" class="btn btn-sm btn-dark fw-bold" title="হালখাতা দেখুন">
                                    <i class="fas fa-book"></i> লেজার
                                </a>
                                <button type="button" class="btn btn-sm btn-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                    <li><a class="dropdown-item fw-bold text-success" href="#" onclick="openPayModal(<?php echo $row->id; ?>, '<?php echo addslashes($row->company_name); ?>', <?php echo $row->total_due; ?>)"><i class="fas fa-money-bill-wave"></i> পেমেন্ট করুন</a></li>
                                    <li><a class="dropdown-item fw-bold text-danger" href="#" onclick="openAddDueModal(<?php echo $row->id; ?>, '<?php echo addslashes($row->company_name); ?>')"><i class="fas fa-plus-circle"></i> নতুন দেনা যোগ করুন</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="tel:<?php echo $row->phone; ?>"><i class="fas fa-phone-alt text-dark"></i> Call Manager</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-warning border-3">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-truck"></i> Add New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="suppliers.php">
                <div class="modal-body bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Company / Supplier Name *</label>
                        <input type="text" name="company_name" class="form-control" required placeholder="যেমন: Pran RFL">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Contact Person Name *</label>
                        <input type="text" name="contact_person" class="form-control" required placeholder="ম্যানেজারের নাম">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="01XXXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="অফিসের ঠিকানা"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Previous Due (সাবেক পাওনা/দেনা)</label>
                        <input type="number" step="0.01" name="total_due" class="form-control border-danger text-danger fw-bold" value="0">
                        <small class="text-muted">আগে থেকে কোম্পানির পাওনা টাকা থাকলে এখানে লিখুন</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_supplier" class="btn btn-warning text-dark fw-bold w-100 fs-5"><i class="fas fa-save"></i> Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="paySupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-success border-3">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-money-bill-wave"></i> Pay to Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="suppliers.php<?php echo isset($view_profile_id) ? '?profile='.$view_profile_id : ''; ?>">
                <div class="modal-body bg-light">
                    <input type="hidden" name="supplier_id" id="pay_supplier_id">
                    
                    <div class="text-center mb-3 bg-white p-3 rounded shadow-sm border">
                        <h5 class="fw-bold text-primary mb-1" id="pay_company_name">Company Name</h5>
                        <p class="text-danger fw-bold fs-5 mb-0">Payable Amount: <span id="pay_current_due">0.00</span> ৳</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Payment Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg border-success text-success fw-bold" placeholder="কত টাকা দিচ্ছেন?" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash (ক্যাশ বাক্স)</option>
                            <option value="Bank">Bank Transfer / Cheque</option>
                            <option value="Bkash">Bkash / Nagad</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note / Details</label>
                        <input type="text" name="note" class="form-control" value="মহাজনকে বকেয়া পরিশোধ" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="pay_supplier" class="btn btn-success fw-bold w-100 fs-5"><i class="fas fa-check-circle"></i> Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addDueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-danger border-3">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle"></i> Add Manual Due (নতুন দেনা)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="suppliers.php<?php echo isset($view_profile_id) ? '?profile='.$view_profile_id : ''; ?>">
                <div class="modal-body bg-light">
                    <input type="hidden" name="supplier_id" id="add_due_supplier_id">
                    
                    <div class="text-center mb-3">
                        <h5 class="fw-bold text-primary mb-0" id="add_due_company_name">Company Name</h5>
                        <small class="text-muted">মাল কেনা ছাড়াই মহাজনের পাওনা টাকা যোগ করুন।</small>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Due Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg border-danger text-danger fw-bold" placeholder="কত টাকা দেনা বাড়াবেন?" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason / Note *</label>
                        <input type="text" name="note" class="form-control" placeholder="যেমন: আগের হিসাবের জের" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_manual_due" class="btn btn-danger fw-bold w-100 fs-5"><i class="fas fa-plus"></i> Add to Ledger</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // রিয়েল-টাইম স্মার্ট সাপ্লায়ার সার্চ
    function filterTable() {
        let input = document.getElementById("searchInput").value.toUpperCase();
        let table = document.getElementById("supplierTable");
        let tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            let tdInfo = tr[i].getElementsByTagName("td")[0];
            let tdPerson = tr[i].getElementsByTagName("td")[1];
            if (tdInfo || tdPerson) {
                let txtValue = (tdInfo.textContent || tdInfo.innerText) + " " + (tdPerson.textContent || tdPerson.innerText);
                if (txtValue.toUpperCase().indexOf(input) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }       
        }
    }

    // পেমেন্ট পপআপে ডাটা পাঠানো
    function openPayModal(id, name, dueAmount) {
        if(dueAmount <= 0) {
            alert("এই কোম্পানির কোনো পাওনা টাকা নেই! তাই পেমেন্টের প্রয়োজন নেই।");
            return;
        }
        document.getElementById('pay_supplier_id').value = id;
        document.getElementById('pay_company_name').innerText = name;
        document.getElementById('pay_current_due').innerText = parseFloat(dueAmount).toFixed(2);
        
        var myModal = new bootstrap.Modal(document.getElementById('paySupplierModal'));
        myModal.show();
    }

    // ম্যানুয়াল দেনা যোগ পপআপে ডাটা পাঠানো
    function openAddDueModal(id, name) {
        document.getElementById('add_due_supplier_id').value = id;
        document.getElementById('add_due_company_name').innerText = name;
        
        var myModal = new bootstrap.Modal(document.getElementById('addDueModal'));
        myModal.show();
    }
</script>

<?php include 'includes/footer.php'; ?>