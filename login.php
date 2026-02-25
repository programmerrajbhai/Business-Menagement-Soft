<?php
// login.php (রুট ডিরেক্টরিতে থাকবে)
session_start();

// যদি আগে থেকেই লগিন করা থাকে, তবে সরাসরি ড্যাশবোর্ডে পাঠিয়ে দাও
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require 'includes/db_connect.php'; // ডাটাবেস কানেকশন

$error_msg = "";

// ফর্ম সাবমিট হলে এই ব্লক কাজ করবে (Backend)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = $_POST['phone'];
    $password = md5($_POST['password']); // ডাটাবেসে আমরা md5 দিয়ে ডামি ডাটা দিয়েছিলাম

    // ডাটাবেসে চেক করা হচ্ছে
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = :phone AND password = :password AND status = 1");
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লগিন | Bseba ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-card h3 {
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        .btn-primary {
            background-color: #28a745; /* Green theme like your picture */
            border-color: #28a745;
            width: 100%;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h3>Bseba ERP Login</h3>
    
    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger text-center">
            <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label">মোবাইল নাম্বার</label>
            <input type="text" name="phone" class="form-control" placeholder="যেমন: 01711000000" required>
        </div>
        <div class="mb-3">
            <label class="form-label">পাসওয়ার্ড</label>
            <input type="password" name="password" class="form-control" placeholder="******" required>
        </div>
        <button type="submit" class="btn btn-primary mt-2">লগিন করুন</button>
    </form>
    
    <div class="text-center mt-3 text-muted" style="font-size: 14px;">
        <p>Admin: 01711000000 | Pass: 123456</p>
    </div>
</div>

</body>
</html>