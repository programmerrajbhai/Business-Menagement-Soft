<?php
// ১. Backend Logic
require 'includes/auth.php';
require 'includes/db_connect.php';

$success_msg = "";

// দোকানের তথ্য আপডেট
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_shop'])) {
    $shop_name = $_POST['shop_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $pdo->prepare("UPDATE business_settings SET shop_name = ?, phone = ?, address = ? WHERE id = 1");
    $stmt->execute([$shop_name, $phone, $address]);
    $success_msg = "দোকানের তথ্য সফলভাবে আপডেট হয়েছে!";
}

// পাসওয়ার্ড পরিবর্তন
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $new_pass = md5($_POST['new_password']);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$new_pass, $current_user_id]);
    $success_msg = "আপনার পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!";
}

$shop = $pdo->query("SELECT * FROM business_settings WHERE id = 1")->fetch();

// ২. Frontend Design
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-cogs text-secondary"></i> Business Settings</h3>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 border-top border-primary border-3" style="border-radius: 10px;">
            <div class="card-body">
                <h5 class="fw-bold text-dark border-bottom pb-2"><i class="fas fa-store"></i> Shop Information</h5>
                <form method="POST" action="">
                    <div class="mb-3 mt-3">
                        <label class="fw-bold">Shop Name (দোকানের নাম)</label>
                        <input type="text" name="shop_name" class="form-control" value="<?php echo $shop->shop_name ?? ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Support Mobile Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $shop->phone ?? ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Shop Address (বিলের কপিতে দেখাবে)</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo $shop->address ?? ''; ?></textarea>
                    </div>
                    <button type="submit" name="update_shop" class="btn btn-primary fw-bold w-100">Update Shop Info</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 border-top border-danger border-3" style="border-radius: 10px;">
            <div class="card-body">
                <h5 class="fw-bold text-dark border-bottom pb-2"><i class="fas fa-lock"></i> Security (Change Password)</h5>
                <form method="POST" action="">
                    <div class="mb-3 mt-3">
                        <label class="fw-bold text-danger">New Password</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="নতুন পাসওয়ার্ড দিন">
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Confirm New Password</label>
                        <input type="password" class="form-control" required placeholder="আবার নতুন পাসওয়ার্ড দিন">
                    </div>
                    <button type="submit" name="change_password" class="btn btn-danger fw-bold w-100">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>