<?php
session_start();
require 'includes/db_connect.php';
require 'includes/functions.php';

// সিকিউরিটি: শুধু আপনার (Super Admin) নাম্বার দিয়ে লগিন করলেই এই পেজ ওপেন হবে
if (!isset($_SESSION['user_phone']) || $_SESSION['user_phone'] != '01700000000') {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>⛔ Access Denied! Only Super Admin Allowed.</h2>");
}

$msg = "";

// নতুন দোকান (Client) যুক্ত করার কোড
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shop'])) {
    $shop_name = $_POST['shop_name'];
    $owner_name = $_POST['owner_name'];
    $phone = $_POST['phone'];
    $password = md5($_POST['password']);
    $monthly_fee = $_POST['monthly_fee'];
    $valid_until = $_POST['valid_until'];

    try {
        $stmt = $pdo->prepare("INSERT INTO shops (shop_name, owner_name, phone, password, monthly_fee, valid_until) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$shop_name, $owner_name, $phone, $password, $monthly_fee, $valid_until]);
        
        // দোকান খোলার সাথে সাথে ঐ দোকানের একজন ডিফল্ট 'admin' তৈরি করে দেওয়া
        $new_shop_id = $pdo->lastInsertId();
        $stmtUser = $pdo->prepare("INSERT INTO users (shop_id, name, phone, password, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmtUser->execute([$new_shop_id, $owner_name, $phone, $password]);

        $msg = "<div class='alert alert-success'>✅ নতুন দোকান সফলভাবে তৈরি হয়েছে!</div>";
    } catch(PDOException $e) {
        $msg = "<div class='alert alert-danger'>❌ এরর (হয়তো নাম্বারটি আগেই আছে): " . $e->getMessage() . "</div>";
    }
}

// বিল পেমেন্ট ও মেয়াদ বাড়ানোর কোড
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['extend_validity'])) {
    $shop_id = $_POST['shop_id'];
    $new_date = $_POST['new_date'];
    
    $stmt = $pdo->prepare("UPDATE shops SET valid_until = ?, status = 'active' WHERE id = ?");
    $stmt->execute([$new_date, $shop_id]);
    $msg = "<div class='alert alert-info'>✅ বিল রিসিভড এবং মেয়াদ বাড়ানো হয়েছে!</div>";
}

// সব দোকানের লিস্ট
$shops = $pdo->query("SELECT * FROM shops WHERE phone != '01700000000' ORDER BY id DESC")->fetchAll();

include 'includes/header.php'; // শুধু ডিজাইন পাওয়ার জন্য
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-danger"><i class="fas fa-crown"></i> Super Admin Control Panel</h2>
        <a href="index.php" class="btn btn-dark">Go to My Dashboard</a>
    </div>

    <?php echo $msg; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white fw-bold">
                    <i class="fas fa-store"></i> Register New Client
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="text" name="shop_name" class="form-control mb-2" placeholder="দোকানের নাম" required>
                        <input type="text" name="owner_name" class="form-control mb-2" placeholder="মালিকের নাম" required>
                        <input type="text" name="phone" class="form-control mb-2" placeholder="মোবাইল নাম্বার (Login ID)" required>
                        <input type="password" name="password" class="form-control mb-2" placeholder="পাসওয়ার্ড" required>
                        <input type="number" name="monthly_fee" class="form-control mb-2" placeholder="মাসিক বিল (যেমন: 500)" value="500" required>
                        <label class="fw-bold small text-muted">Valid Until (কতদিন চলবে?)</label>
                        <input type="date" name="valid_until" class="form-control mb-3" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        <button type="submit" name="add_shop" class="btn btn-danger w-100 fw-bold">Create Account</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-dark">
                <div class="card-body">
                    <h5 class="fw-bold border-bottom pb-2">All Registered Shops (Clients)</h5>
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Shop Name</th>
                                <th>Phone</th>
                                <th>Monthly Fee</th>
                                <th>Valid Until</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($shops as $row): 
                                $is_expired = (strtotime($row->valid_until) < time());
                            ?>
                            <tr class="<?php echo $is_expired ? 'table-danger' : ''; ?>">
                                <td class="fw-bold"><?php echo $row->shop_name; ?><br><small class="text-muted"><?php echo $row->owner_name; ?></small></td>
                                <td><?php echo $row->phone; ?></td>
                                <td>৳<?php echo $row->monthly_fee; ?></td>
                                <td class="fw-bold <?php echo $is_expired ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo format_date($row->valid_until); ?>
                                </td>
                                <td>
                                    <?php if($is_expired): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex">
                                        <input type="hidden" name="shop_id" value="<?php echo $row->id; ?>">
                                        <input type="date" name="new_date" class="form-control form-control-sm me-1" value="<?php echo date('Y-m-d', strtotime('+30 days', strtotime($row->valid_until))); ?>" required>
                                        <button type="submit" name="extend_validity" class="btn btn-sm btn-success" title="বিল জমা নিয়ে মেয়াদ বাড়ান"><i class="fas fa-check"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>