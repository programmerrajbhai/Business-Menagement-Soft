<?php
session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

require 'includes/db_connect.php';
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = $_POST['phone'];
    $password = md5($_POST['password']); 

    // ইউজার এবং তার দোকানের তথ্য চেক করা
    $stmt = $pdo->prepare("
        SELECT u.*, s.valid_until, s.status as shop_status, s.shop_name 
        FROM users u 
        JOIN shops s ON u.shop_id = s.id 
        WHERE u.phone = :phone AND u.password = :password AND u.status = 1
    ");
    $stmt->execute(['phone' => $phone, 'password' => $password]);
    $user = $stmt->fetch();

    if ($user) {
        // SaaS Logic: মেয়াদ চেক করা
        $today = date('Y-m-d');
        if($user->valid_until < $today || $user->shop_status != 'active') {
            $error_msg = "⚠️ আপনার সাবস্ক্রিপশনের মেয়াদ (".$user->valid_until.") শেষ হয়ে গেছে! দয়া করে বিকাশ/নগদে বিল পরিশোধ করে সার্ভিসটি চালু করুন। হেল্পলাইন: 017XXXXX";
        } else {
            // লগিন সাকসেস! সেশনে ডাটা সেভ করছি (সবচেয়ে ইম্পরট্যান্ট হলো shop_id)
            $_SESSION['user_id'] = $user->id;
            $_SESSION['shop_id'] = $user->shop_id; // SaaS এর প্রাণ!
            $_SESSION['user_name'] = $user->name;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['user_phone'] = $user->phone;
            $_SESSION['shop_name'] = $user->shop_name;
            
            // যদি সুপার এডমিন হয়, তবে সুপার এডমিন প্যানেলে পাঠাও
            if($user->phone == '01700000000') {
                header("Location: super_admin.php");
            } else {
                header("Location: index.php");
            }
            exit();
        }
    } else {
        $error_msg = "ভুল মোবাইল নাম্বার অথবা পাসওয়ার্ড!";
    }
}
?>