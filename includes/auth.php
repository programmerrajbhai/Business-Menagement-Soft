<?php
// includes/auth.php
session_start();

// ইউজার লগিন করা না থাকলে login.php তে পাঠিয়ে দাও
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ইউজারের ডাটা ভেরিয়েবলে রেখে দিচ্ছি যাতে সব পেজে ইউজ করা যায়
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'];
$current_user_role = $_SESSION['user_role'];
?>