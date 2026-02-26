<?php
// login.php (রুট ডিরেক্টরিতে থাকবে)
session_start();

// যদি আগে থেকেই লগিন করা থাকে, তবে সরাসরি ড্যাশবোর্ডে পাঠিয়ে দাও
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require 'includes/db_connect.php'; 

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = $_POST['phone'];
    $password = md5($_POST['password']); 

    try {
        // সাধারণ লগিন লজিক (SaaS ছাড়া)
        // u.status = 1 বাদ দেওয়া হয়েছে যাতে কোনোভাবেই এরর না আসে
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = :phone AND password = :password");
        $stmt->execute(['phone' => $phone, 'password' => $password]);
        $user = $stmt->fetch();

        if ($user) {
            // লগিন সাকসেস! সেশনে ডাটা সেভ করছি
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_name'] = $user->name;
            $_SESSION['user_role'] = $user->role;
            
            header("Location: index.php"); // ড্যাশবোর্ডে রিডাইরেক্ট
            exit();
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
        body {
            background-color: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            border-top: 5px solid #28a745;
        }
        .login-card h3 {
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
            text-align: center;
        }
        .btn-success {
            width: 100%;
            font-weight: bold;
            padding: 12px;
            font-size: 18px;
            border-radius: 8px;
        }
        .input-group-text {
            background: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #ced4da;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h3><i class="fas fa-layer-group text-success"></i> Bseba ERP</h3>
    <p class="text-center text-muted mb-4">অ্যাডমিন প্যানেলে নিরাপদ লগিন</p>
    
    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger text-center shadow-sm py-2 fw-bold">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label fw-bold text-dark">মোবাইল নাম্বার</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text"><i class="fas fa-phone-alt text-success"></i></span>
                <input type="text" name="phone" class="form-control form-control-lg" placeholder="01XXXXXXXXX" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold text-dark">পাসওয়ার্ড</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                <input type="password" name="password" class="form-control form-control-lg" placeholder="******" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success shadow-sm mt-2"><i class="fas fa-sign-in-alt"></i> লগিন করুন</button>
    </form>
    
    <div class="text-center mt-4 text-muted" style="font-size: 13px;">
        <p>&copy; <?php echo date('Y'); ?> Bseba Enterprise. All rights reserved.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>