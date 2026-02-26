<?php
// includes/auth.php
session_start();

// ইউজার লগিন করা না থাকলে login.php তে পাঠিয়ে দাও
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ডাটাবেস কানেকশন (লাইভ স্ট্যাটাস চেক করার জন্য)
require_once __DIR__ . '/db_connect.php';



$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'];
$current_user_role = $_SESSION['user_role'];
$current_user_phone = isset($_SESSION['user_phone']) ? $_SESSION['user_phone'] : '';

$current_shop_id = isset($_SESSION['shop_id']) ? $_SESSION['shop_id'] : null;
$current_shop_name = isset($_SESSION['shop_name']) ? $_SESSION['shop_name'] : 'Bseba ERP';

// সুপার এডমিন বাইপাস
if ($current_user_phone != '01711000000') {
    
    if (empty($current_shop_id)) {
        header("Location: logout.php");
        exit();
    }

    // 🚀 INSTANT LOCKOUT MAGIC (প্রতি ক্লিকে লাইভ চেক)
    $stmt = $pdo->prepare("SELECT status, valid_until FROM shops WHERE id = ?");
    $stmt->execute([$current_shop_id]);
    $shop = $stmt->fetch();

    if (!$shop) {
        header("Location: logout.php");
        exit();
    }

    $current_datetime = date('Y-m-d H:i:s'); // বর্তমান সেকেন্ড পর্যন্ত সময়

    // যদি এডমিন সাসপেন্ড করে বা সেকেন্ডের কাঁটায় মেয়াদ শেষ হয়
    if ($shop->status == 'suspended' || $shop->valid_until < $current_datetime) {
        session_destroy();
        session_start();
        $_SESSION['lock_msg'] = "⛔ আপনার সফটওয়্যারটি লক করা হয়েছে অথবা মেয়াদ শেষ! এডমিনের সাথে যোগাযোগ করুন।";
        header("Location: login.php");
        exit();
    }
}
?>