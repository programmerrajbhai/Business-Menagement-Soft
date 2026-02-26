<?php
// includes/auth.php
session_start();

// ইউজার লগিন করা না থাকলে login.php তে পাঠিয়ে দাও
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ইউজারের ডাটা ভেরিয়েবলে রেখে দিচ্ছি যাতে সব পেজে ইউজ করা যায়
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'];
$current_user_role = $_SESSION['user_role'];
$current_user_phone = isset($_SESSION['user_phone']) ? $_SESSION['user_phone'] : '';

// SaaS Variables (এই ভেরিয়েবলগুলো ব্যবহার করে আমরা অন্যান্য পেজের ডাটা ফিল্টার করব)
$current_shop_id = isset($_SESSION['shop_id']) ? $_SESSION['shop_id'] : null;
$current_shop_name = isset($_SESSION['shop_name']) ? $_SESSION['shop_name'] : 'Bseba ERP';

// সিকিউরিটি: যদি ইউজারের ফোন নাম্বার সুপার এডমিনের না হয় এবং তার কোনো shop_id না থাকে, তবে তাকে বের করে দাও!
if ($current_user_phone != '01711000000' && empty($current_shop_id)) {
    header("Location: logout.php");
    exit();
}
?>