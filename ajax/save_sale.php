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
    $user_id = $_SESSION['user_id'];
    
    // ইউনিক ইনভয়েস নাম্বার তৈরি (যেমন: INV-250226-001)
    $invoice_no = "INV-" . date('dmy') . "-" . rand(1000, 9999);
    $sale_date = date('Y-m-d');

    try {
        $pdo->beginTransaction(); // Transaction শুরু (যাতে মাঝপথে ইন্টারনেট গেলে ডাটা লস না হয়)

        // ১. Sales টেবিলে মূল বিল সেভ করা
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, total_amount, discount, payable_amount, paid_amount, due_amount, payment_method, sale_date, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_no, $customer_id, $total_amount, $discount, $payable_amount, $paid_amount, $due_amount, $payment_method, $sale_date, $user_id]);
        $sale_id = $pdo->lastInsertId();

        // ২. Sale Items এবং Stock আপডেট করা
        foreach($items as $item) {
            // আইটেম সেভ
            $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, total) VALUES (?, ?, ?, ?, ?)");
            $stmtItem->execute([$sale_id, $item['id'], $item['qty'], $item['price'], $item['total']]);
            
            // গোডাউন থেকে স্টক মাইনাস করা
            $stmtStock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
            $stmtStock->execute([$item['qty'], $item['id']]);
        }

        // ৩. কাস্টমারের বাকি থাকলে তার লেজার (খাতা) আপডেট করা
        if($due_amount > 0 && $customer_id != 3) { // 3 মানে ওয়াকিং কাস্টমার, তার বাকির হিসাব নেই
            $stmtDue = $pdo->prepare("UPDATE customers SET total_due = total_due + ? WHERE id = ?");
            $stmtDue->execute([$due_amount, $customer_id]);
        }

        // ৪. ক্যাশবুকে (transactions) জমা টাকার হিসাব অ্যাড করা
        if($paid_amount > 0) {
            $note = "Sale Invoice: " . $invoice_no;
            $stmtCash = $pdo->prepare("INSERT INTO transactions (type, amount, payment_method, note, date, user_id) VALUES ('Income', ?, ?, ?, ?, ?)");
            $stmtCash->execute([$paid_amount, $payment_method, $note, $sale_date, $user_id]);
        }

        $pdo->commit(); // সব সফল হলে ডাটাবেসে পার্মানেন্ট সেভ
        
        echo json_encode(['status' => 'success', 'invoice_no' => $invoice_no]);

    } catch(PDOException $e) {
        $pdo->rollBack(); // কোনো এরর হলে আগের সব কাজ বাতিল (Rollback)
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>