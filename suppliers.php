<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// নতুন সাপ্লায়ার বা মহাজন যুক্ত করার কোড
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supplier'])) {
    $company_name = $_POST['company_name'];
    $contact_person = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $total_due = !empty($_POST['total_due']) ? $_POST['total_due'] : 0.00; // কোম্পানির আগের পাওনা

    try {
        $sql = "INSERT INTO suppliers (company_name, contact_person, phone, address, total_due) VALUES (:company_name, :contact_person, :phone, :address, :due)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'company_name' => $company_name,
            'contact_person' => $contact_person,
            'phone' => $phone,
            'address' => $address,
            'due' => $total_due
        ]);
        
        $success_msg = "নতুন সাপ্লায়ার/কোম্পানি সফলভাবে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// ডাটাবেস থেকে সব সাপ্লায়ার টেনে আনার কোড
$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC");
$suppliers = $stmt->fetchAll();

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-truck text-warning"></i> Supplier & Company Ledger</h3>
    <button class="btn btn-warning fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="fas fa-plus-circle"></i> Add New Supplier
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
                    <th>Company Name</th>
                    <th>Contact Person</th>
                    <th>Phone No.</th>
                    <th>Payable Due (দেনা)</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($suppliers as $row): ?>
                <tr>
                    <td>#<?php echo $row->id; ?></td>
                    <td class="fw-bold text-primary"><?php echo $row->company_name; ?></td>
                    <td><?php echo $row->contact_person; ?></td>
                    <td><?php echo $row->phone; ?></td>
                    <td>
                        <?php if($row->total_due > 0): ?>
                            <span class="badge bg-danger fs-6"><?php echo format_taka($row->total_due); ?></span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6">Clear (কোনো দেনা নেই)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info text-white" title="View Ledger">
                            <i class="fas fa-book"></i> লেজার
                        </button>
                        <button class="btn btn-sm btn-success" title="Pay to Supplier">
                            <i class="fas fa-money-bill-wave"></i> পেমেন্ট করুন
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-truck"></i> Add New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Company / Supplier Name *</label>
                        <input type="text" name="company_name" class="form-control" placeholder="যেমন: Pran RFL, Square etc." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Contact Person Name *</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="মহাজন বা ম্যানেজারের নাম" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="অফিসের ঠিকানা"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Previous Due (সাবেক পাওনা/দেনা)</label>
                        <input type="number" step="0.01" name="total_due" class="form-control border-danger text-danger fw-bold" placeholder="কোম্পানি আমাদের কাছে কত পাবে?">
                        <small class="text-muted">আগে থেকে কোম্পানির পাওনা টাকা থাকলে এখানে লিখুন</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_supplier" class="btn btn-warning fw-bold">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// ৩. Footer: সবার শেষে ফুটার
include 'includes/footer.php'; 
?>