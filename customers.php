<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// ফর্ম সাবমিট হলে নতুন কাস্টমার ডাটাবেসে সেভ করার কোড
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $total_due = !empty($_POST['total_due']) ? $_POST['total_due'] : 0.00;
    $credit_limit = !empty($_POST['credit_limit']) ? $_POST['credit_limit'] : 10000.00;

    try {
        // PDO Prepared Statement (সুপার সিকিউর, হ্যাক হওয়ার চান্স জিরো)
        $sql = "INSERT INTO customers (name, phone, address, total_due, credit_limit) VALUES (:name, :phone, :address, :due, :limit)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'due' => $total_due,
            'limit' => $credit_limit
        ]);
        
        $success_msg = "নতুন কাস্টমার সফলভাবে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// ডাটাবেস থেকে সব কাস্টমারের লিস্ট টেনে আনার কোড
$stmt = $pdo->query("SELECT * FROM customers ORDER BY id DESC");
$customers = $stmt->fetchAll();

// ২. Frontend Design: এখান থেকে লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-users text-primary"></i> Customer List & Ledger</h3>
    <button class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
        <i class="fas fa-user-plus"></i> Add New Customer
    </button>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm" style="border-radius: 10px; border: none;">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Mobile No.</th>
                    <th>Address</th>
                    <th>Total Due (বকেয়া)</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $row): ?>
                <tr>
                    <td>#<?php echo $row->id; ?></td>
                    <td class="fw-bold"><?php echo $row->name; ?></td>
                    <td><?php echo $row->phone; ?></td>
                    <td><?php echo $row->address; ?></td>
                    <td>
                        <?php if($row->total_due > 0): ?>
                            <span class="badge bg-danger fs-6"><?php echo format_taka($row->total_due); ?></span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6">No Due</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info text-white" title="View Ledger">
                            <i class="fas fa-book-open"></i> খাতা
                        </button>
                        <button class="btn btn-sm btn-warning" title="Receive Due">
                            <i class="fas fa-hand-holding-usd"></i> জমা নিন
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="নাম লিখুন" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="ঠিকানা"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-danger">Previous Due (সাবেক জের)</label>
                            <input type="number" step="0.01" name="total_due" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Credit Limit (বাকি লিমিট)</label>
                            <input type="number" step="0.01" name="credit_limit" class="form-control" value="10000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_customer" class="btn btn-success">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// ৩. Footer: সবার শেষে ফুটার এবং জাভাস্ক্রিপ্ট
include 'includes/footer.php'; 
?>