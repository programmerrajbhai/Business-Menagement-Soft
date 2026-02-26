<?php
// ajax/save_sale.php
session_start();
require '../includes/db_connect.php';

if(isset($_POST['saleData'])) {
    $data = json_decode($_POST['saleData'], true);
    
    $customer_id = $data['customer_id'];
    $total_amount = $data['total_amount'];
    $discount = $data['discount'];
    $payable_amount = $data['payable_amount'];
    $paid_amount = $data['paid_amount'];
    $due_amount = $data['due_amount'];
    $payment_method = $data['payment_method'];
    $items = $data['items'];
    
    // SaaS Variables
    $user_id = $_SESSION['user_id'];
    $shop_id = $_SESSION['shop_id']; // <--- The SaaS Magic
    
    $invoice_no = "INV-" . date('dmy') . "-" . rand(1000, 9999);
    $sale_date = date('Y-m-d');

    try {
        $pdo->beginTransaction();

        // ১. Sales টেবিলে shop_id সহ সেভ
        $stmt = $pdo->prepare("INSERT INTO sales (shop_id, invoice_no, customer_id, total_amount, discount, payable_amount, paid_amount, due_amount, payment_method, sale_date, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$shop_id, $invoice_no, $customer_id, $total_amount, $discount, $payable_amount, $paid_amount, $due_amount, $payment_method, $sale_date, $user_id]);
        $sale_id = $pdo->lastInsertId();

        // ২. Sale Items এবং Stock আপডেট (shop_id অনুযায়ী স্টক কমবে)
        foreach($items as $item) {
            $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, total) VALUES (?, ?, ?, ?, ?)");
            $stmtItem->execute([$sale_id, $item['id'], $item['qty'], $item['price'], $item['total']]);
            
            $stmtStock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND shop_id = ?");
            $stmtStock->execute([$item['qty'], $item['id'], $shop_id]);
        }

        // ৩. কাস্টমারের বাকি লেজার আপডেট (shop_id অনুযায়ী)
        if($due_amount > 0 && $customer_id != 3) { 
            $stmtDue = $pdo->prepare("UPDATE customers SET total_due = total_due + ? WHERE id = ? AND shop_id = ?");
            $stmtDue->execute([$due_amount, $customer_id, $shop_id]);
        }

        // ৪. ক্যাশবুকে shop_id সহ সেভ
        if($paid_amount > 0) {
            $note = "Sale Invoice: " . $invoice_no;
            $stmtCash = $pdo->prepare("INSERT INTO transactions (shop_id, type, amount, payment_method, note, date, user_id) VALUES (?, 'Income', ?, ?, ?, ?, ?)");
            $stmtCash->execute([$shop_id, $paid_amount, $payment_method, $note, $sale_date, $user_id]);
        }

        $pdo->commit(); 
        echo json_encode(['status' => 'success', 'invoice_no' => $invoice_no]);

    } catch(PDOException $e) {
        $pdo->rollBack(); 
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>