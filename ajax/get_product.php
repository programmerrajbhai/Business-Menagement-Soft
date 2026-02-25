<?php
// ajax/get_product.php
require '../includes/db_connect.php';

if(isset($_GET['barcode'])) {
    $barcode = $_GET['barcode'];
    
    $stmt = $pdo->prepare("SELECT id, name, sale_price, stock_qty FROM products WHERE barcode = :barcode AND stock_qty > 0 LIMIT 1");
    $stmt->execute(['barcode' => $barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if($product) {
        echo json_encode(['status' => 'success', 'data' => $product]);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
?>