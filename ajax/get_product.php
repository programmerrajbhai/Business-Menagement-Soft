<?php
// ajax/get_product.php (SaaS Update)
session_start();
require '../includes/db_connect.php';

// যদি ইউজারের সেশন না থাকে, তবে ব্লক করে দাও
if(!isset($_SESSION['shop_id'])){
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if(isset($_GET['barcode'])) {
    $barcode = trim($_GET['barcode']);
    $shop_id = $_SESSION['shop_id']; // SaaS Magic
    
    // শুধু নির্দিষ্ট দোকানের প্রোডাক্ট টানা হচ্ছে
    $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ? AND shop_id = ? LIMIT 1");
    $stmt->execute([$barcode, $shop_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($product) {
        if($product['stock_qty'] > 0) {
            echo json_encode(['status' => 'success', 'data' => $product]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Out of stock']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    }
}
?>