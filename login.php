<?php
// login.php
session_start();
require 'includes/db_connect.php'; 

$super_admin_phone = '01711000000'; 

if (isset($_SESSION['user_id'])) {
    if(isset($_SESSION['user_phone']) && $_SESSION['user_phone'] == $super_admin_phone){
        header("Location: super_admin.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error_msg = "";

// লক আউট হয়ে আসলে সেই মেসেজটি রিসিভ করা
if(isset($_SESSION['lock_msg'])){
    $error_msg = $_SESSION['lock_msg'];
    unset($_SESSION['lock_msg']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = $_POST['phone'];
    $password = md5($_POST['password']); 

    try {
        $stmt = $pdo->prepare("SELECT u.*, s.valid_until, s.status as shop_status, s.shop_name FROM users u LEFT JOIN shops s ON u.shop_id = s.id WHERE u.phone = :phone AND u.password = :password");
        $stmt->execute(['phone' => $phone, 'password' => $password]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user->phone == $super_admin_phone) {
                $_SESSION['user_id'] = $user->id;
                $_SESSION['user_name'] = $user->name;
                $_SESSION['user_role'] = 'super_admin';
                $_SESSION['user_phone'] = $user->phone;
                header("Location: super_admin.php"); 
                exit();
            } else {
                $current_datetime = date('Y-m-d H:i:s');
                
                if ($user->shop_status == 'suspended') {
                    $error_msg = "⛔ আপনার অ্যাকাউন্টটি লক করা হয়েছে!";
                } elseif ($user->valid_until < $current_datetime) {
                    $error_msg = "⚠️ আপনার মেয়াদ (".date('d M, Y - h:i A', strtotime($user->valid_until)).") শেষ! বিল পরিশোধ করুন।";
                } else {
                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['shop_id'] = $user->shop_id;
                    $_SESSION['shop_name'] = $user->shop_name;
                    $_SESSION['user_name'] = $user->name;
                    $_SESSION['user_role'] = $user->role;
                    $_SESSION['user_phone'] = $user->phone;
                    header("Location: index.php");
                    exit();
                }
            }
        } else {
            $error_msg = "ভুল মোবাইল নাম্বার অথবা পাসওয়ার্ড!";
        }
    } catch(PDOException $e) {
        $error_msg = "সিস্টেম এরর: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লগিন | Bseba ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 420px; border-top: 5px solid #28a745; }
        .login-card h3 { font-weight: 800; color: #333; margin-bottom: 5px; text-align: center; }
        .form-control:focus { box-shadow: none; border-color: #ced4da; }
    </style>
</head>
<body>
<div class="login-card">
    <h3><i class="fas fa-layer-group text-success"></i> Bseba ERP</h3>
    <p class="text-center text-muted mb-4">অ্যাডমিন প্যানেলে নিরাপদ লগিন</p>
    
    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger text-center shadow-sm py-2 fw-bold" style="font-size: 14px;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label fw-bold text-dark">মোবাইল নাম্বার</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text"><i class="fas fa-phone-alt text-success"></i></span>
                <input type="text" name="phone" class="form-control form-control-lg" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold text-dark">পাসওয়ার্ড</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success w-100 fw-bold fs-5 shadow-sm mt-2"><i class="fas fa-sign-in-alt"></i> লগিন করুন</button>
    </form>
</div>
</body>
</html>