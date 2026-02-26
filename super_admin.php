<?php
// super_admin.php
session_start();
require 'includes/db_connect.php';

$super_admin_phone = '01711000000'; 

if (!isset($_SESSION['user_id']) || $_SESSION['user_phone'] != $super_admin_phone) {
    die("<div style='text-align:center; margin-top:100px;'>
            <h1 style='color:red;'>⛔ Access Denied!</h1>
            <a href='index.php'>Go Back</a>
         </div>");
}

$msg = "";

// ১. নতুন দোকান
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shop'])) {
    $shop_name = $_POST['shop_name'];
    $owner_name = $_POST['owner_name'];
    $phone = $_POST['phone'];
    $password = md5($_POST['password']);
    
    // Y-m-d H:i:s ফরমেটে কনভার্ট করা
    $valid_until = date('Y-m-d H:i:s', strtotime($_POST['valid_until']));

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO shops (shop_name, owner_name, phone, password, valid_until, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$shop_name, $owner_name, $phone, $password, $valid_until]);
        $new_shop_id = $pdo->lastInsertId();

        $stmtUser = $pdo->prepare("INSERT INTO users (shop_id, name, phone, password, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmtUser->execute([$new_shop_id, $owner_name, $phone, $password]);

        $pdo->commit();
        $msg = "<div class='alert alert-success fw-bold'>নতুন দোকান সফলভাবে তৈরি হয়েছে!</div>";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $msg = "<div class='alert alert-danger fw-bold'>এরর: " . $e->getMessage() . "</div>";
    }
}

// ২. মেয়াদ বাড়ানো (মিনিট/ঘণ্টা সহ)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['extend_validity'])) {
    $shop_id = $_POST['shop_id'];
    $new_date = date('Y-m-d H:i:s', strtotime($_POST['new_date']));
    
    $stmt = $pdo->prepare("UPDATE shops SET valid_until = ?, status = 'active' WHERE id = ?");
    $stmt->execute([$new_date, $shop_id]);
    $msg = "<div class='alert alert-info fw-bold'>মেয়াদ বাড়ানো হয়েছে এবং সফটওয়্যার আনলক হয়েছে!</div>";
}

// ৩. লক/আনলক করা (Instant)
if (isset($_GET['action']) && isset($_GET['shop_id'])) {
    $s_id = $_GET['shop_id'];
    $new_status = ($_GET['action'] == 'suspend') ? 'suspended' : 'active';
    
    $stmt = $pdo->prepare("UPDATE shops SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $s_id]);
    header("Location: super_admin.php?msg=updated");
    exit();
}
if(isset($_GET['msg'])) $msg = "<div class='alert alert-warning fw-bold'>স্ট্যাটাস আপডেট করা হয়েছে! (ইউজার ইনস্ট্যান্ট লক হবে)</div>";

$shops = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id) as total_products FROM shops s ORDER BY s.id DESC")->fetchAll();
$total_clients = count($shops);
$active_clients = 0; foreach($shops as $s) { if($s->status == 'active') $active_clients++; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin | Bseba ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background: #f0f2f5; } .table th { background: #343a40; color: white; } </style>
</head>
<body>

<nav class="navbar navbar-dark bg-danger shadow-sm mb-4 p-3">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-crown text-warning"></i> BSEBA SUPER ADMIN</a>
        <a href="index.php" class="btn btn-dark btn-sm fw-bold">Go to Dashboard</a>
    </div>
</nav>

<div class="container-fluid px-4">
    <?php echo $msg; ?>

    <div class="row mb-4">
        <div class="col-md-4"><div class="card bg-primary text-white p-3 h-100"><h5>Total Clients</h5><h2><?php echo $total_clients; ?> Shops</h2></div></div>
        <div class="col-md-4"><div class="card bg-success text-white p-3 h-100"><h5>Active Shops</h5><h2><?php echo $active_clients; ?> Active</h2></div></div>
        <div class="col-md-4"><div class="card bg-danger text-white p-3 h-100"><h5>Locked Shops</h5><h2><?php echo $total_clients - $active_clients; ?> Locked</h2></div></div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card border-top border-danger border-4 shadow-sm">
                <div class="card-header bg-white fw-bold fs-5 text-danger"><i class="fas fa-store"></i> Register New Client</div>
                <div class="card-body bg-light">
                    <form method="POST">
                        <input type="text" name="shop_name" class="form-control mb-3" placeholder="Shop Name" required>
                        <input type="text" name="owner_name" class="form-control mb-3" placeholder="Owner Name" required>
                        <input type="text" name="phone" class="form-control mb-3 border-primary" placeholder="Login Phone" required>
                        <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
                        
                        <label class="fw-bold text-danger">Valid Until (Select Time)</label>
                        <input type="datetime-local" name="valid_until" class="form-control border-danger mb-3" value="<?php echo date('Y-m-d\TH:i', strtotime('+30 days')); ?>" required>
                        
                        <button type="submit" name="add_shop" class="btn btn-danger w-100 fw-bold fs-5"><i class="fas fa-plus"></i> Create Account</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Shop Name</th>
                                <th>Phone</th>
                                <th>Valid Until (Exact Time)</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($shops as $row): 
                                $is_expired = (strtotime($row->valid_until) < time());
                                $status_class = ($row->status == 'active') ? 'bg-success' : 'bg-danger';
                                if($is_expired && $row->status == 'active') $status_class = 'bg-warning text-dark';
                            ?>
                            <tr class="<?php echo ($row->status == 'suspended') ? 'table-danger' : ''; ?>">
                                <td class="fw-bold text-primary"><?php echo $row->shop_name; ?></td>
                                <td><?php echo $row->phone; ?></td>
                                <td>
                                    <span class="fw-bold <?php echo $is_expired ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo date('d M, Y - h:i A', strtotime($row->valid_until)); ?>
                                    </span>
                                    <?php if($is_expired): ?><br><span class="badge bg-danger">Time Expired!</span><?php endif; ?>
                                </td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo strtoupper($row->status); ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-info text-white fw-bold" data-bs-toggle="modal" data-bs-target="#extend<?php echo $row->id; ?>"><i class="fas fa-clock"></i> Set Time</button>
                                    
                                    <?php if($row->status == 'active'): ?>
                                        <a href="super_admin.php?action=suspend&shop_id=<?php echo $row->id; ?>" class="btn btn-sm btn-danger fw-bold" onclick="return confirm('লক করলে সাথে সাথে বের হয়ে যাবে। নিশ্চিত?')"><i class="fas fa-lock"></i></a>
                                    <?php else: ?>
                                        <a href="super_admin.php?action=active&shop_id=<?php echo $row->id; ?>" class="btn btn-sm btn-success fw-bold"><i class="fas fa-unlock"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <div class="modal fade" id="extend<?php echo $row->id; ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content border-info">
                                        <div class="modal-header bg-info text-white">
                                            <h6 class="modal-title">Extend Time</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="shop_id" value="<?php echo $row->id; ?>">
                                                <input type="datetime-local" name="new_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', max(time(), strtotime($row->valid_until))); ?>" required>
                                            </div>
                                            <div class="modal-footer"><button type="submit" name="extend_validity" class="btn btn-info w-100 text-white fw-bold">Update</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>