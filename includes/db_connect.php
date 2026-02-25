<?php
// includes/db_connect.php

$host = 'localhost';
$dbname = 'bseba_erp'; // আপনার ডাটাবেসের নাম
$username = 'root';    // XAMPP বা लोकल সার্ভারের ডিফল্ট ইউজারনেম
$password = '';        // লোকাল সার্ভারে পাসওয়ার্ড সাধারণত ফাঁকা থাকে

try {
    // PDO Connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Set Error Mode to Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Fetch Mode Object (এতে করে $row->name এভাবে ডাটা এক্সেস করা যাবে)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    
    // Timezone সেট করে দিলাম যাতে বিলের সময় বাংলাদেশি টাইম আসে
    date_default_timezone_set('Asia/Dhaka');
    
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>