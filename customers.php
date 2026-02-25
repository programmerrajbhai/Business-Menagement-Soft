<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// [অ্যাকশন ১] নতুন কাস্টমার সেভ করা
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $total_due = !empty($_POST['total_due']) ? $_POST['total_due'] : 0.00;
    $credit_limit = !empty($_POST['credit_limit']) ? $_POST['credit_limit'] : 10000.00;

    try {
        $sql = "INSERT INTO customers (name, phone, address, total_due, credit_limit) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $phone, $address, $total_due, $credit_limit]);
        $success_msg = "নতুন কাস্টমার সফলভাবে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// [অ্যাকশন ২] বকেয়া (Due) আদায় বা রিসিভ করা
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['receive_due'])) {
    $c_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'];
    $note = $_POST['note'];
    $date = date('Y-m-d');

    try {
        $pdo->beginTransaction();
        // কাস্টমারের বকেয়া কমানো
        $stmtDue = $pdo->prepare("UPDATE customers SET total_due = total_due - ? WHERE id = ?");
        $stmtDue->execute([$amount, $c_id]);

        // ক্যাশবুকে জমার এন্ট্রি করা (customer_id সহ)
        $stmtCash = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id, customer_id) VALUES ('Due Collection', ?, ?, ?, ?, ?, ?)");
        $stmtCash->execute([$amount, $method, $note, $date, $current_user_id, $c_id]);

        $pdo->commit();
        $success_msg = "বকেয়া টাকা সফলভাবে আদায় হয়েছে এবং গাল্লায় জমা হয়েছে!";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_msg = "টাকা জমা নিতে সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// [অ্যাকশন ৩] নতুন বকেয়া বা হাওলাত যোগ করা (Manual Due Add)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_manual_due'])) {
    $c_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $note = $_POST['note'];
    $date = date('Y-m-d');

    try {
        $pdo->beginTransaction();
        // কাস্টমারের বকেয়া বাড়ানো
        $stmtDue = $pdo->prepare("UPDATE customers SET total_due = total_due + ? WHERE id = ?");
        $stmtDue->execute([$amount, $c_id]);

        // হালখাতায় দেখানোর জন্য ট্রানজেকশনে এন্ট্রি (কিন্তু গাল্লায় ক্যাশ ইন হবে না)
        $stmtCash = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id, customer_id) VALUES ('Manual Due Add', ?, 'N/A', ?, ?, ?, ?)");
        $stmtCash->execute([$amount, $note, $date, $current_user_id, $c_id]);

        $pdo->commit();
        $success_msg = "কাস্টমারের খাতায় নতুন বকেয়া সফলভাবে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_msg = "বকেয়া যোগ করতে সমস্যা হয়েছে: " . $e->getMessage();
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
    // কাস্টমারের ডাটা আনা
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$view_profile_id]);
    $customer = $stmt->fetch();

    if(!$customer) {
        die("<div class='alert alert-danger text-center mt-5'>Customer Not Found!</div>");
    }

    // কাস্টমারের সম্পূর্ণ লেজার (Sale + Due Collection + Manual Due) একসাথে আনা
    $ledger_stmt = $pdo->prepare("
        SELECT sale_date as date, invoice_no as ref, 'Sale Bill' as type, total_amount as bill, paid_amount as paid, due_amount as due 
        FROM sales WHERE customer_id = ?
        UNION ALL
        SELECT date as date, note as ref, 'Payment Received' as type, 0 as bill, amount as paid, 0 as due 
        FROM transactions WHERE type = 'Due Collection' AND customer_id = ?
        UNION ALL
        SELECT date as date, note as ref, 'Manual Due Added' as type, amount as bill, 0 as paid, amount as due 
        FROM transactions WHERE type = 'Manual Due Add' AND customer_id = ?
        ORDER BY date DESC
    ");
    $ledger_stmt->execute([$view_profile_id, $view_profile_id, $view_profile_id]);
    $ledgers = $ledger_stmt->fetchAll();
?>
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3 class="fw-bold text-dark"><i class="fas fa-user-circle text-primary"></i> Customer Profile</h3>
        <a href="customers.php" class="btn btn-secondary fw-bold"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3 no-print">
            <div class="card shadow-sm border-0 border-top border-primary border-3" style="border-radius: 10px;">
                <div class="card-body text-center mt-3">
                    <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-user text-primary fa-3x"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $customer->name; ?></h4>
                    <p class="text-muted mb-2"><i class="fas fa-map-marker-alt"></i> <?php echo $customer->address; ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <a href="tel:<?php echo $customer->phone; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="fas fa-phone-alt"></i> Call</a>
                        <a href="https://wa.me/88<?php echo $customer->phone; ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                    </div>
                    <hr>

                    <div class="bg-danger text-white p-3 rounded mb-3 shadow-sm">
                        <h6 class="mb-1">Total Due (বর্তমান বকেয়া)</h6>
                        <h2 class="fw-bold mb-0"><?php echo format_taka($customer->total_due); ?></h2>
                    </div>
                    
                    <button class="btn btn-success fw-bold w-100 mb-2 py-2 shadow-sm" onclick="openReceiveDueModal(<?php echo $customer->id; ?>, '<?php echo addslashes($customer->name); ?>', <?php echo $customer->total_due; ?>)">
                        <i class="fas fa-hand-holding-usd"></i> Receive Payment (বকেয়া আদায়)
                    </button>
                    <button class="btn btn-outline-danger fw-bold w-100 mb-2" onclick="openAddDueModal(<?php echo $customer->id; ?>, '<?php echo addslashes($customer->name); ?>')">
                        <i class="fas fa-plus-circle"></i> Add Due (নতুন বকেয়া যোগ)
                    </button>
                    <button class="btn btn-dark fw-bold w-100" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Ledger (খাতা প্রিন্ট)
                    </button>
                    
                    <p class="small text-muted mt-3 mb-0">Credit Limit: <?php echo format_taka($customer->credit_limit); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0" style="border-radius: 10px;" id="printLedgerArea">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-primary fs-5"><i class="fas fa-book-open"></i> Ledger Book (হালখাতা)</span>
                    <span class="d-none d-print-block fs-5 fw-bold text-dark">Customer: <?php echo $customer->name; ?> (<?php echo $customer->phone; ?>)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th>Particulars (বিবরণ)</th>
                                    <th>Reference / Note</th>
                                    <th class="text-danger text-end">Bill/Given (খরচ)</th>
                                    <th class="text-success text-end">Paid (জমা)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($ledgers as $l): ?>
                                <tr>
                                    <td class="fw-bold text-muted"><?php echo format_date($l->date); ?></td>
                                    <td>
                                        <?php if($l->type == 'Sale Bill'): ?>
                                            <span class="badge bg-primary">Sale Invoice</span>
                                        <?php elseif($l->type == 'Payment Received'): ?>
                                            <span class="badge bg-success">Cash Received</span>
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
                                    <th colspan="3" class="text-end">Current Total Due (সর্বমোট বকেয়া):</th>
                                    <th colspan="2" class="text-center text-danger fs-5"><?php echo format_taka($customer->total_due); ?></th>
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
    // VIEW 2: CUSTOMERS LIST & SEARCH (মেইন পেজ)
    // ==========================================
    $customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fas fa-users text-primary"></i> Business Customers</h3>
        <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-user-plus"></i> Add New Customer
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-primary border-end-0"><i class="fas fa-search text-primary"></i></span>
                <input type="text" id="searchInput" class="form-control border-primary border-start-0 form-control-lg" placeholder="কাস্টমারের নাম বা নাম্বার দিয়ে খুঁজুন..." onkeyup="filterTable()">
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 10px;">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" id="customerTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Customer Info</th>
                        <th>Address</th>
                        <th>Total Due (বকেয়া)</th>
                        <th class="text-center pe-4">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($customers as $row): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-primary fs-6"><?php echo $row->name; ?></div>
                            <div class="text-muted small"><i class="fas fa-phone-alt"></i> <?php echo $row->phone; ?></div>
                        </td>
                        <td class="text-muted"><small><?php echo empty($row->address) ? 'N/A' : $row->address; ?></small></td>
                        <td>
                            <?php if($row->total_due > 0): ?>
                                <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill shadow-sm"><?php echo format_taka($row->total_due); ?></span>
                            <?php else: ?>
                                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill shadow-sm">Clear</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group">
                                <a href="customers.php?profile=<?php echo $row->id; ?>" class="btn btn-sm btn-primary fw-bold" title="হালখাতা দেখুন">
                                    <i class="fas fa-book-open"></i> খাতা
                                </a>
                                <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                    <li><a class="dropdown-item fw-bold text-success" href="#" onclick="openReceiveDueModal(<?php echo $row->id; ?>, '<?php echo addslashes($row->name); ?>', <?php echo $row->total_due; ?>)"><i class="fas fa-hand-holding-usd"></i> বকেয়া আদায় করুন</a></li>
                                    <li><a class="dropdown-item fw-bold text-danger" href="#" onclick="openAddDueModal(<?php echo $row->id; ?>, '<?php echo addslashes($row->name); ?>')"><i class="fas fa-plus-circle"></i> নতুন বকেয়া যোগ করুন</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="tel:<?php echo $row->phone; ?>"><i class="fas fa-phone-alt text-dark"></i> Call Customer</a></li>
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

<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-success border-3">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus"></i> Add New Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customers.php">
                <div class="modal-body bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="যেমন: রহিম শেখ">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="01XXXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="পূর্ণাঙ্গ ঠিকানা"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-danger">Previous Due (সাবেক বকেয়া)</label>
                            <input type="number" step="0.01" name="total_due" class="form-control" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Credit Limit (বাকি লিমিট)</label>
                            <input type="number" step="0.01" name="credit_limit" class="form-control" value="10000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_customer" class="btn btn-success fw-bold w-100 fs-5"><i class="fas fa-save"></i> Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="receiveDueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-success border-3">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-hand-holding-usd"></i> Receive Due (বকেয়া আদায়)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customers.php<?php echo isset($view_profile_id) ? '?profile='.$view_profile_id : ''; ?>">
                <div class="modal-body bg-light">
                    <input type="hidden" name="customer_id" id="receive_customer_id">
                    
                    <div class="text-center mb-3 bg-white p-3 rounded shadow-sm border">
                        <h5 class="fw-bold text-primary mb-1" id="receive_customer_name">Customer Name</h5>
                        <p class="text-danger fw-bold fs-5 mb-0">Total Due: <span id="receive_current_amount">0.00</span> ৳</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Receive Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg border-success text-success fw-bold" placeholder="কত টাকা দিচ্ছেন?" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash (গাল্লায়)</option>
                            <option value="Bkash">Bkash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Bank">Bank Check</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note / Receipt Details</label>
                        <input type="text" name="note" class="form-control" value="কিস্তি বা বকেয়া পরিশোধ" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="receive_due" class="btn btn-success fw-bold w-100 fs-5"><i class="fas fa-check-circle"></i> Confirm Collection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addDueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-danger border-3">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle"></i> Add Manual Due (বকেয়া যোগ)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customers.php<?php echo isset($view_profile_id) ? '?profile='.$view_profile_id : ''; ?>">
                <div class="modal-body bg-light">
                    <input type="hidden" name="customer_id" id="add_due_customer_id">
                    
                    <div class="text-center mb-3">
                        <h5 class="fw-bold text-primary mb-0" id="add_due_customer_name">Customer Name</h5>
                        <small class="text-muted">এই ফর্ম দিয়ে বেচাকেনা ছাড়াই কাস্টমারের খাতায় বকেয়া যোগ করা যাবে।</small>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Due Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg border-danger text-danger fw-bold" placeholder="কত টাকা বকেয়া যোগ করবেন?" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason / Note *</label>
                        <input type="text" name="note" class="form-control" placeholder="যেমন: নগদ হাওলাত বা আগের হিসাবের জের" required>
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
    // রিয়েল-টাইম স্মার্ট কাস্টমার সার্চ
    function filterTable() {
        let input = document.getElementById("searchInput").value.toUpperCase();
        let table = document.getElementById("customerTable");
        let tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            let tdInfo = tr[i].getElementsByTagName("td")[0];
            if (tdInfo) {
                let txtValue = tdInfo.textContent || tdInfo.innerText;
                if (txtValue.toUpperCase().indexOf(input) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }       
        }
    }

    // বকেয়া আদায় পপআপে ডাটা পাঠানো
    function openReceiveDueModal(id, name, dueAmount) {
        if(dueAmount <= 0) {
            alert("এই কাস্টমারের কোনো বকেয়া নেই! তাই জমার প্রয়োজন নেই।");
            return;
        }
        document.getElementById('receive_customer_id').value = id;
        document.getElementById('receive_customer_name').innerText = name;
        document.getElementById('receive_current_amount').innerText = parseFloat(dueAmount).toFixed(2);
        
        var myModal = new bootstrap.Modal(document.getElementById('receiveDueModal'));
        myModal.show();
    }

    // ম্যানুয়াল বকেয়া যোগ পপআপে ডাটা পাঠানো
    function openAddDueModal(id, name) {
        document.getElementById('add_due_customer_id').value = id;
        document.getElementById('add_due_customer_name').innerText = name;
        
        var myModal = new bootstrap.Modal(document.getElementById('addDueModal'));
        myModal.show();
    }
</script>

<?php include 'includes/footer.php'; ?>