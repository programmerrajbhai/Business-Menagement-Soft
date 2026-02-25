<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// নতুন খরচ বা আয় যুক্ত করার কোড (ফর্ম সাবমিট হলে)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_transaction'])) {
    $type = $_POST['type']; // Expense, Income ইত্যাদি
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $note = $_POST['note'];
    $date = $_POST['date'];
    $user_id = $current_user_id;

    try {
        $sql = "INSERT INTO transactions (type, amount, payment_method, note, date, user_id) VALUES (:type, :amount, :method, :note, :date, :user_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'type' => $type,
            'amount' => $amount,
            'method' => $payment_method,
            'note' => $note,
            'date' => $date,
            'user_id' => $user_id
        ]);
        $success_msg = "হিসাব সফলভাবে ক্যাশবুকে যুক্ত হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// আজকের আয়ের হিসাব (Sales + Due Collection)
$today = date('Y-m-d');
$income_stmt = $pdo->prepare("SELECT SUM(amount) as total_in FROM transactions WHERE type IN ('Income', 'Due Collection') AND date = :today");
$income_stmt->execute(['today' => $today]);
$today_income = $income_stmt->fetch()->total_in ?? 0;

// আজকের ব্যয়ের হিসাব (Expense + Supplier Payment)
$expense_stmt = $pdo->prepare("SELECT SUM(amount) as total_out FROM transactions WHERE type IN ('Expense', 'Supplier Payment') AND date = :today");
$expense_stmt->execute(['today' => $today]);
$today_expense = $expense_stmt->fetch()->total_out ?? 0;

// আজকের নেট ক্যাশ ব্যালেন্স
$today_balance = $today_income - $today_expense;

// ক্যাশবুকের লিস্ট (সর্বশেষ ৫০টি ট্রানজেকশন)
$trans_stmt = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 50");
$transactions = $trans_stmt->fetchAll();

// ২. Frontend Design: এখান থেকে লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-wallet text-success"></i> Daily Cashbook & Accounts</h3>
    <div>
        <button class="btn btn-danger fw-bold me-2" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-minus-circle"></i> Add Expense (খরচ)
        </button>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
            <i class="fas fa-plus-circle"></i> Add Income (আয়)
        </button>
    </div>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white shadow-sm" style="border-radius: 10px;">
            <div class="card-body text-center">
                <h6><i class="fas fa-arrow-down"></i> Today's Cash In (আজকের জমা)</h6>
                <h3 class="fw-bold"><?php echo format_taka($today_income); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white shadow-sm" style="border-radius: 10px;">
            <div class="card-body text-center">
                <h6><i class="fas fa-arrow-up"></i> Today's Cash Out (আজকের খরচ)</h6>
                <h3 class="fw-bold"><?php echo format_taka($today_expense); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white shadow-sm" style="border-radius: 10px;">
            <div class="card-body text-center">
                <h6><i class="fas fa-cash-register"></i> Net Balance (গাল্লায় আছে)</h6>
                <h3 class="fw-bold"><?php echo format_taka($today_balance); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm" style="border-radius: 10px; border: none;">
    <div class="card-body">
        <h5 class="fw-bold text-secondary border-bottom pb-2 mb-3">Recent Transactions</h5>
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Details (বিবরণ)</th>
                    <th>Payment Method</th>
                    <th>Cash In (জমা)</th>
                    <th>Cash Out (খরচ)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($transactions as $row): ?>
                <tr>
                    <td><?php echo format_date($row->date); ?></td>
                    <td>
                        <span class="fw-bold"><?php echo $row->type; ?></span><br>
                        <small class="text-muted"><?php echo $row->note; ?></small>
                    </td>
                    <td><span class="badge bg-secondary"><?php echo $row->payment_method; ?></span></td>
                    
                    <td class="text-success fw-bold">
                        <?php echo ($row->type == 'Income' || $row->type == 'Due Collection') ? '+ ' . format_taka($row->amount) : '-'; ?>
                    </td>
                    
                    <td class="text-danger fw-bold">
                        <?php echo ($row->type == 'Expense' || $row->type == 'Supplier Payment') ? '- ' . format_taka($row->amount) : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-minus-circle"></i> Add New Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="type" value="Expense">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Expense Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control text-danger fw-bold" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash (ক্যাশ বাক্স)</option>
                            <option value="Bkash">Bkash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note / Details</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="যেমন: চা-নাস্তা বা কারেন্ট বিল" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_transaction" class="btn btn-danger">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addIncomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Other Income</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="type" value="Income">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Income Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control text-success fw-bold" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment From</label>
                        <select name="payment_method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bkash">Bkash</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Note / Details</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="যেমন: পুরাতন কার্টন বিক্রি বা ডেলিভারি চার্জ আদায়" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_transaction" class="btn btn-primary">Save Income</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>