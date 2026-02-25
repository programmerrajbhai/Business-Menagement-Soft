<?php
// ১. Backend Logic
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// শুধু এডমিন এই পেজে এক্সেস পাবে
if($current_user_role != 'admin') {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>Access Denied!</h2>");
}

$success_msg = "";

// নতুন স্টাফ অ্যাড করার কোড
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_staff'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $password = md5($_POST['password']);
    $role = $_POST['role'];

    $stmt = $pdo->prepare("INSERT INTO users (name, phone, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $password, $role]);
    $success_msg = "নতুন স্টাফ সফলভাবে যোগ করা হয়েছে!";
}

// বেতন দেওয়ার কোড (Expense হিসেবে ক্যাশবুকে যাবে)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay_salary'])) {
    $staff_id = $_POST['staff_id'];
    $amount = $_POST['amount'];
    $month = $_POST['month'];
    
    // স্টাফের নাম বের করা
    $staff = $pdo->query("SELECT name FROM users WHERE id = $staff_id")->fetch();
    $note = $staff->name . " এর বেতন - মাস: " . $month;

    $stmt = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id) VALUES ('Expense', ?, 'Cash', ?, CURDATE(), ?)");
    $stmt->execute([$amount, $note, $current_user_id]);
    $success_msg = "বেতন সফলভাবে ক্যাশবুকে (Expense) যুক্ত হয়েছে!";
}

$staffs = $pdo->query("SELECT * FROM users WHERE role != 'admin'")->fetchAll();

// ২. Frontend Design
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-user-tie text-info"></i> HR & Staff Management</h3>
    <button class="btn btn-info fw-bold text-white" data-bs-toggle="modal" data-bs-target="#addStaffModal">
        <i class="fas fa-plus-circle"></i> Add New Staff
    </button>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0" style="border-radius: 10px;">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Mobile (Login ID)</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($staffs as $row): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?php echo $row->name; ?></td>
                            <td><?php echo $row->phone; ?></td>
                            <td><span class="badge bg-secondary"><?php echo strtoupper($row->role); ?></span></td>
                            <td><span class="badge bg-success">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-info border-3" style="border-radius: 10px;">
            <div class="card-body bg-light">
                <h5 class="fw-bold text-dark border-bottom pb-2"><i class="fas fa-money-check-alt text-info"></i> Pay Salary</h5>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="fw-bold">Select Staff</label>
                        <select name="staff_id" class="form-control" required>
                            <option value="">-- স্টাফ সিলেক্ট করুন --</option>
                            <?php foreach($staffs as $row): ?>
                                <option value="<?php echo $row->id; ?>"><?php echo $row->name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Month</label>
                        <input type="month" name="month" class="form-control" value="<?php echo date('Y-m'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold text-danger">Salary Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control text-end fw-bold" required>
                    </div>
                    <button type="submit" name="pay_salary" class="btn btn-info w-100 fw-bold text-white"><i class="fas fa-check"></i> Pay Now</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">Staff Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Mobile Number (Login ID)</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Role Access</label>
                        <select name="role" class="form-control">
                            <option value="salesman">Salesman (Only POS & Product View)</option>
                            <option value="manager">Manager (Can view stock but no profit)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_staff" class="btn btn-info fw-bold text-white">Save Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>