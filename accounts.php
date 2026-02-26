<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// নতুন খরচ বা আয় যুক্ত করার কোড (SaaS Update: shop_id যুক্ত করা হয়েছে)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_transaction'])) {
    $type = $_POST['type']; // Expense, Income ইত্যাদি
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $note = $_POST['note'];
    $date = $_POST['date'];
    $user_id = $current_user_id;

    try {
        $sql = "INSERT INTO transactions (shop_id, type, amount, payment_method, note, date, user_id) VALUES (:shop_id, :type, :amount, :method, :note, :date, :user_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'shop_id' => $current_shop_id,
            'type' => $type,
            'amount' => $amount,
            'method' => $payment_method,
            'note' => $note,
            'date' => $date,
            'user_id' => $user_id
        ]);
        $success_msg = "হিসাব সফলভাবে ক্যাশবুকে যুক্ত হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// আজকের আয়ের হিসাব (SaaS Update: shop_id ফিল্টার)
$today = date('Y-m-d');
$income_stmt = $pdo->prepare("SELECT SUM(amount) as total_in FROM transactions WHERE shop_id = :shop_id AND type IN ('Income', 'Due Collection') AND date = :today");
$income_stmt->execute(['shop_id' => $current_shop_id, 'today' => $today]);
$today_income = $income_stmt->fetch()->total_in ?? 0;

// আজকের ব্যয়ের হিসাব (SaaS Update: shop_id ফিল্টার)
$expense_stmt = $pdo->prepare("SELECT SUM(amount) as total_out FROM transactions WHERE shop_id = :shop_id AND type IN ('Expense', 'Supplier Payment') AND date = :today");
$expense_stmt->execute(['shop_id' => $current_shop_id, 'today' => $today]);
$today_expense = $expense_stmt->fetch()->total_out ?? 0;

// আজকের নেট ক্যাশ ব্যালেন্স
$today_balance = $today_income - $today_expense;

// ক্যাশবুকের লিস্ট (সর্বশেষ ৫০টি ট্রানজেকশন) (SaaS Update: shop_id ফিল্টার)
$trans_stmt = $pdo->prepare("SELECT * FROM transactions WHERE shop_id = ? ORDER BY id DESC LIMIT 50");
$trans_stmt->execute([$current_shop_id]);
$transactions = $trans_stmt->fetchAll();

// ২. Frontend Design: এখান থেকে লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-wallet text-success"></i> Daily Cashbook & Accounts</h3>
    <div>
        <button class="btn btn-danger fw-bold me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-minus-circle"></i> Add Expense (খরচ)
        </button>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
            <i class="fas fa-plus-circle"></i> Add Income (আয়)
        </button>
    </div>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card bg-success text-white shadow-sm border-0 h-100" style="border-radius: 10px;">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h6 class="fw-bold"><i class="fas fa-arrow-down"></i> Today's Cash In (আজকের জমা)</h6>
                <h2 class="fw-bold mb-0"><?php echo format_taka($today_income); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card bg-danger text-white shadow-sm border-0 h-100" style="border-radius: 10px;">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h6 class="fw-bold"><i class="fas fa-arrow-up"></i> Today's Cash Out (আজকের খরচ)</h6>
                <h2 class="fw-bold mb-0"><?php echo format_taka($today_expense); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white shadow-sm border-0 h-100" style="border-radius: 10px;">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h6 class="fw-bold"><i class="fas fa-cash-register"></i> Net Balance (গাল্লায় আছে)</h6>
                <h2 class="fw-bold mb-0"><?php echo format_taka($today_balance); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0" style="border-radius: 10px;">
    <div class="card-body p-0">
        <div class="card-header bg-white border-bottom p-3">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-list text-primary"></i> Recent Transactions</h5>
        </div>
        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Details (বিবরণ)</th>
                        <th>Payment Method</th>
                        <th class="text-end">Cash In (জমা)</th>
                        <th class="text-end pe-3">Cash Out (খরচ)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transactions as $row): ?>
                    <tr>
                        <td class="ps-3 fw-bold text-muted"><?php echo format_date($row->date); ?></td>
                        <td>
                            <span class="fw-bold text-dark"><?php echo $row->type; ?></span><br>
                            <small class="text-muted"><?php echo $row->note; ?></small>
                        </td>
                        <td><span class="badge bg-secondary px-2 py-1"><?php echo $row->payment_method; ?></span></td>
                        
                        <td class="text-success fw-bold text-end fs-6">
                            <?php echo ($row->type == 'Income' || $row->type == 'Due Collection') ? '+ ' . format_taka($row->amount) : '-'; ?>
                        </td>
                        
                        <td class="text-danger fw-bold text-end pe-3 fs-6">
                            <?php echo ($row->type == 'Expense' || $row->type == 'Supplier Payment') ? '- ' . format_taka($row->amount) : '-'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($transactions) == 0): ?>
                        <tr><td colspan="5" class="text-center text-muted p-4">আজকের কোনো লেনদেন নেই!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-danger border-3">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-minus-circle"></i> Add New Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="type" value="Expense">
                <div class="modal-body bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date *</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Expense Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg text-danger fw-bold border-danger" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Method *</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash (ক্যাশ বাক্স)</option>
                            <option value="Bkash">Bkash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note / Details *</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="যেমন: চা-নাস্তা বা কারেন্ট বিল" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_transaction" class="btn btn-danger fw-bold fs-5 px-4"><i class="fas fa-save"></i> Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addIncomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-primary border-3">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle"></i> Add Other Income</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="type" value="Income">
                <div class="modal-body bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date *</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Income Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg text-success fw-bold border-success" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Received In *</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash (গাল্লায়)</option>
                            <option value="Bkash">Bkash</option>
                            <option value="Nagad">Nagad</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note / Details *</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="যেমন: পুরাতন কার্টন বিক্রি বা ডেলিভারি চার্জ আদায়" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_transaction" class="btn btn-primary fw-bold fs-5 px-4"><i class="fas fa-save"></i> Save Income</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>