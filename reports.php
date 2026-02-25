<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// মালিক ছাড়া অন্য কেউ যেন লাভ-ক্ষতি দেখতে না পারে তার সিকিউরিটি
if($current_user_role != 'admin') {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied! শুধু মালিক এই পেজ দেখতে পারবেন।</h2>");
}

// ডিফল্ট ডেট (আজকের মাসের ১ তারিখ থেকে শেষ তারিখ পর্যন্ত)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// ১. মোট সেল (Total Sales) এর হিসাব
$stmt_sales = $pdo->prepare("SELECT SUM(total_amount) as total_sales, SUM(due_amount) as total_due FROM sales WHERE sale_date BETWEEN :start AND :end");
$stmt_sales->execute(['start' => $start_date, 'end' => $end_date]);
$sales_data = $stmt_sales->fetch();
$total_sales = $sales_data->total_sales ?? 0;
$sales_due = $sales_data->total_due ?? 0;

// ২. গ্রস প্রফিট (Gross Profit = বিক্রয় মূল্য - কেনা মূল্য)
$stmt_profit = $pdo->prepare("
    SELECT SUM((si.price - p.purchase_price) * si.qty) as gross_profit 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    JOIN products p ON si.product_id = p.id 
    WHERE s.sale_date BETWEEN :start AND :end
");
$stmt_profit->execute(['start' => $start_date, 'end' => $end_date]);
$gross_profit = $stmt_profit->fetch()->gross_profit ?? 0;

// ৩. মোট খরচ (Total Expense)
$stmt_expense = $pdo->prepare("SELECT SUM(amount) as total_expense FROM transactions WHERE type = 'Expense' AND date BETWEEN :start AND :end");
$stmt_expense->execute(['start' => $start_date, 'end' => $end_date]);
$total_expense = $stmt_expense->fetch()->total_expense ?? 0;

// ৪. নেট প্রফিট (Net Profit = Gross Profit - Expense)
$net_profit = $gross_profit - $total_expense;

// ৫. মার্কেটে মোট পাওনা (Top 10 Due Customers)
$stmt_due_customers = $pdo->query("SELECT name, phone, total_due FROM customers WHERE total_due > 0 ORDER BY total_due DESC LIMIT 10");
$top_dues = $stmt_due_customers->fetchAll();

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #printReport, #printReport * { visibility: visible; }
        #printReport { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h3 class="fw-bold text-dark"><i class="fas fa-chart-line text-primary"></i> Business Reports (লাভ-ক্ষতি)</h3>
    <button class="btn btn-dark fw-bold" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
</div>

<div class="card shadow-sm border-0 mb-4 no-print" style="border-radius: 10px;">
    <div class="card-body bg-light">
        <form method="GET" action="" class="row align-items-end">
            <div class="col-md-4">
                <label class="fw-bold">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="fw-bold">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-filter"></i> Filter Data</button>
            </div>
        </form>
    </div>
</div>

<div id="printReport">
    <div class="text-center mb-4 d-none d-print-block">
        <h2 class="fw-bold">Bseba Enterprise</h2>
        <h5>Profit & Loss Report</h5>
        <p>Date: <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?></p>
        <hr>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 bg-primary text-white h-100" style="border-radius: 10px;">
                <div class="card-body text-center">
                    <h6><i class="fas fa-shopping-bag"></i> Total Sales (মোট বিক্রি)</h6>
                    <h3 class="fw-bold mt-2"><?php echo format_taka($total_sales); ?></h3>
                    <small>Unpaid/Due: <?php echo format_taka($sales_due); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 bg-info text-white h-100" style="border-radius: 10px;">
                <div class="card-body text-center">
                    <h6><i class="fas fa-coins"></i> Gross Profit (মাল বেচে লাভ)</h6>
                    <h3 class="fw-bold mt-2"><?php echo format_taka($gross_profit); ?></h3>
                    <small>Cost of goods sold deducted</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 bg-danger text-white h-100" style="border-radius: 10px;">
                <div class="card-body text-center">
                    <h6><i class="fas fa-minus-circle"></i> Total Expense (দোকানের খরচ)</h6>
                    <h3 class="fw-bold mt-2"><?php echo format_taka($total_expense); ?></h3>
                    <small>Rent, Bills, Staff etc.</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 <?php echo ($net_profit >= 0) ? 'bg-success' : 'bg-dark'; ?> text-white h-100" style="border-radius: 10px;">
                <div class="card-body text-center">
                    <h6><i class="fas fa-wallet"></i> Net Profit (পকেটে আসল লাভ)</h6>
                    <h3 class="fw-bold mt-2"><?php echo format_taka($net_profit); ?></h3>
                    <small><?php echo ($net_profit >= 0) ? 'Alhamdulillah! Profit' : 'Loss Occurred!'; ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0" style="border-radius: 10px;">
                <div class="card-header bg-white fw-bold text-danger border-bottom">
                    <i class="fas fa-exclamation-triangle"></i> Top 10 Due Customers (সর্বোচ্চ বাকি)
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th class="text-end">Due Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_dues as $due): ?>
                            <tr>
                                <td class="fw-bold"><?php echo $due->name; ?></td>
                                <td><?php echo $due->phone; ?></td>
                                <td class="text-end text-danger fw-bold"><?php echo format_taka($due->total_due); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($top_dues) == 0): ?>
                                <tr><td colspan="3" class="text-center text-muted p-3">মার্কেটে কোনো বাকি নেই!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0" style="border-radius: 10px;">
                <div class="card-header bg-white fw-bold text-primary border-bottom">
                    <i class="fas fa-receipt"></i> Quick Overview
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Sales Revenue (Total)
                            <span class="badge bg-primary rounded-pill fs-6"><?php echo format_taka($total_sales); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Cost of Sold Items
                            <span class="badge bg-secondary rounded-pill fs-6">- <?php echo format_taka($total_sales - $gross_profit); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Operating Expenses
                            <span class="badge bg-danger rounded-pill fs-6">- <?php echo format_taka($total_expense); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center fw-bold fs-5 mt-2 border-top">
                            Final Net Profit
                            <span class="text-success"><?php echo format_taka($net_profit); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// ৩. Footer
include 'includes/footer.php'; 
?>