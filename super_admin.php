<?php
// super_admin.php
session_start();
require 'includes/db_connect.php';
require 'includes/functions.php';

// সিকিউরিটি: শুধু আপনার (Super Admin) নাম্বার দিয়ে লগিন করলেই এই পেজ ওপেন হবে
// এখানে আপনার অ্যাডমিন নাম্বারটি দিন (যেমন: 01711000000)
$super_admin_phone = '01711000000'; 

if (!isset($_SESSION['user_id']) || $_SESSION['user_phone'] != $super_admin_phone) {
    die("<div style='text-align:center; margin-top:100px; font-family:sans-serif;'>
            <h1 style='color:red;'>⛔ Access Denied!</h1>
            <h3>Only Super Admin is allowed here.</h3>
            <a href='index.php'>Go Back to Dashboard</a>
         </div>");
}

$msg = "";

// [অ্যাকশন ১] নতুন ক্লায়েন্ট (দোকান) যুক্ত করা
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shop'])) {
    $shop_name = $_POST['shop_name'];
    $owner_name = $_POST['owner_name'];
    $phone = $_POST['phone'];
    $password = md5($_POST['password']);
    $valid_until = $_POST['valid_until'];

    try {
        $pdo->beginTransaction();
        
        // ১. Shops টেবিলে ডাটা এন্ট্রি (এখানে password কলামটি যুক্ত করা হয়েছে)
        $stmt = $pdo->prepare("INSERT INTO shops (shop_name, owner_name, phone, password, valid_until, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$shop_name, $owner_name, $phone, $password, $valid_until]);
        $new_shop_id = $pdo->lastInsertId();

        // ২. Users টেবিলে ওই দোকানের মালিকের লগিন একাউন্ট তৈরি করা
        $stmtUser = $pdo->prepare("INSERT INTO users (shop_id, name, phone, password, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmtUser->execute([$new_shop_id, $owner_name, $phone, $password]);

        $pdo->commit();
        $msg = "<div class='alert alert-success fw-bold'><i class='fas fa-check-circle'></i> নতুন দোকান সফলভাবে তৈরি হয়েছে!</div>";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $msg = "<div class='alert alert-danger fw-bold'><i class='fas fa-exclamation-triangle'></i> এরর (হয়তো নাম্বারটি আগেই আছে): " . $e->getMessage() . "</div>";
    }
}

// [অ্যাকশন ২] মেয়াদ (Validity) বাড়ানো
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['extend_validity'])) {
    $shop_id = $_POST['shop_id'];
    $new_date = $_POST['new_date'];
    
    $stmt = $pdo->prepare("UPDATE shops SET valid_until = ?, status = 'active' WHERE id = ?");
    $stmt->execute([$new_date, $shop_id]);
    $msg = "<div class='alert alert-info fw-bold'><i class='fas fa-calendar-check'></i> সাবস্ক্রিপশন মেয়াদ বাড়ানো হয়েছে! সফটওয়্যার আনলক হয়ে গেছে।</div>";
}

// [অ্যাকশন ৩] সফটওয়্যার লক/আনলক (Suspend/Active) করা
if (isset($_GET['action']) && isset($_GET['shop_id'])) {
    $s_id = $_GET['shop_id'];
    $new_status = ($_GET['action'] == 'suspend') ? 'suspended' : 'active';
    
    $stmt = $pdo->prepare("UPDATE shops SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $s_id]);
    header("Location: super_admin.php?msg=status_updated");
    exit();
}

if(isset($_GET['msg']) && $_GET['msg'] == 'status_updated'){
    $msg = "<div class='alert alert-warning fw-bold'><i class='fas fa-info-circle'></i> দোকানের স্ট্যাটাস আপডেট করা হয়েছে!</div>";
}

// সব দোকানের লিস্ট এবং পরিসংখ্যান (Product Count) টানা হচ্ছে
$shops = $pdo->query("
    SELECT s.*, 
    (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id) as total_products 
    FROM shops s 
    ORDER BY s.id DESC
")->fetchAll();

$total_clients = count($shops);
$active_clients = 0;
foreach($shops as $s) { if($s->status == 'active') $active_clients++; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin | Bseba ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 900; letter-spacing: 1px; }
        .card { border-radius: 10px; border: none; }
        .table th { background-color: #343a40; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#"><i class="fas fa-crown text-warning"></i> BSEBA SUPER ADMIN</a>
        <div class="d-flex">
            <span class="navbar-text text-white me-3 fw-bold">Hello, Boss!</span>
            <a href="index.php" class="btn btn-dark btn-sm fw-bold"><i class="fas fa-th-large"></i> Go to Dashboard</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    
    <?php echo $msg; ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm h-100">
                <div class="card-body">
                    <h5><i class="fas fa-users"></i> Total Clients</h5>
                    <h2><?php echo $total_clients; ?> Shops</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm h-100">
                <div class="card-body">
                    <h5><i class="fas fa-check-circle"></i> Active Subscriptions</h5>
                    <h2><?php echo $active_clients; ?> Active</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white shadow-sm h-100">
                <div class="card-body">
                    <h5><i class="fas fa-ban"></i> Suspended Shops</h5>
                    <h2><?php echo $total_clients - $active_clients; ?> Suspended</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-top border-danger border-4">
                <div class="card-header bg-white fw-bold fs-5 text-danger border-bottom">
                    <i class="fas fa-store"></i> Register New Client
                </div>
                <div class="card-body bg-light">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="fw-bold text-muted">Shop/Business Name</label>
                            <input type="text" name="shop_name" class="form-control" placeholder="দোকানের নাম" required>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold text-muted">Owner Name</label>
                            <input type="text" name="owner_name" class="form-control" placeholder="মালিকের নাম" required>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold text-muted">Phone Number (Login ID)</label>
                            <input type="text" name="phone" class="form-control border-primary" placeholder="01XXXXXXXXX" required>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold text-muted">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="লগিন পাসওয়ার্ড" required>
                        </div>
                        <div class="mb-4">
                            <label class="fw-bold text-danger">Valid Until (মেয়াদ শেষ হবে)</label>
                            <input type="date" name="valid_until" class="form-control border-danger" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                        <button type="submit" name="add_shop" class="btn btn-danger w-100 fw-bold fs-5 shadow-sm"><i class="fas fa-user-plus"></i> Create Account</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold fs-5 text-dark border-bottom">
                    <i class="fas fa-list"></i> All Registered Clients
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Shop details</th>
                                    <th>Login Phone</th>
                                    <th class="text-center">Products</th>
                                    <th>Validity</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($shops as $row): 
                                    $is_expired = (strtotime($row->valid_until) < time());
                                    $status_class = ($row->status == 'active') ? 'bg-success' : 'bg-danger';
                                    if($is_expired && $row->status == 'active') $status_class = 'bg-warning text-dark';
                                ?>
                                <tr class="<?php echo ($row->status == 'suspended') ? 'table-danger' : ''; ?>">
                                    <td class="ps-3">
                                        <div class="fw-bold text-primary"><?php echo $row->shop_name; ?></div>
                                        <div class="small text-muted"><i class="fas fa-user"></i> <?php echo $row->owner_name; ?></div>
                                    </td>
                                    <td class="fw-bold"><?php echo $row->phone; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill px-3"><?php echo $row->total_products; ?> Items</span>
                                    </td>
                                    <td>
                                        <span class="fw-bold <?php echo $is_expired ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo date('d M, Y', strtotime($row->valid_until)); ?>
                                        </span>
                                        <?php if($is_expired): ?><br><small class="text-danger fw-bold">Expired!</small><?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $status_class; ?> px-2 py-1 text-uppercase"><?php echo $row->status; ?></span>
                                    </td>
                                    <td class="text-end pe-3">
                                        
                                        <button class="btn btn-sm btn-info text-white fw-bold mb-1" data-bs-toggle="modal" data-bs-target="#extendModal<?php echo $row->id; ?>">
                                            <i class="fas fa-calendar-plus"></i> Bill
                                        </button>

                                        <?php if($row->status == 'active'): ?>
                                            <a href="super_admin.php?action=suspend&shop_id=<?php echo $row->id; ?>" class="btn btn-sm btn-danger fw-bold mb-1" onclick="return confirm('এই দোকানটি লক করতে চান?')"><i class="fas fa-lock"></i></a>
                                        <?php else: ?>
                                            <a href="super_admin.php?action=active&shop_id=<?php echo $row->id; ?>" class="btn btn-sm btn-success fw-bold mb-1"><i class="fas fa-unlock"></i></a>
                                        <?php endif; ?>

                                        <div class="modal fade text-start" id="extendModal<?php echo $row->id; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-sm">
                                                <div class="modal-content border-info border-3">
                                                    <div class="modal-header bg-info text-white">
                                                        <h6 class="modal-title fw-bold">Extend Validity</h6>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body bg-light">
                                                            <input type="hidden" name="shop_id" value="<?php echo $row->id; ?>">
                                                            <p class="mb-2">Shop: <strong><?php echo $row->shop_name; ?></strong></p>
                                                            <label class="fw-bold mb-1">New Expire Date:</label>
                                                            <input type="date" name="new_date" class="form-control border-info" value="<?php echo date('Y-m-d', strtotime('+30 days', max(time(), strtotime($row->valid_until)))); ?>" required>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" name="extend_validity" class="btn btn-info text-white w-100 fw-bold">Update & Unlock</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>