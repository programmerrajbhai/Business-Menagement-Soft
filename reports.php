<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// মালিক ছাড়া অন্য কেউ যেন লাভ-ক্ষতি দেখতে না পারে তার সিকিউরিটি
if($current_user_role != 'admin') {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied! শুধু মালিক এই পেজ দেখতে পারবেন।</h2>");
}

// ২. স্মার্ট ফিল্টার লজিক (Quick Filters)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'this_month';
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

if ($filter == 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($filter == 'last_7_days') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
} elseif ($filter == 'this_year') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
} elseif (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $filter = 'custom';
}

// ৩. ডাটাবেস থেকে ক্যালকুলেশন (Date Range অনুযায়ী)

// ক. সেলস রিপোর্ট (বিক্রি এবং বাকি)
$stmt_sales = $pdo->prepare("SELECT SUM(total_amount) as total_sales, SUM(paid_amount) as total_paid, SUM(due_amount) as total_due FROM sales WHERE sale_date BETWEEN :start AND :end");
$stmt_sales->execute(['start' => $start_date, 'end' => $end_date]);
$sales_data = $stmt_sales->fetch();
$total_sales = $sales_data->total_sales ?? 0;
$sales_paid = $sales_data->total_paid ?? 0;
$sales_due = $sales_data->total_due ?? 0;

// খ. পারচেস রিপোর্ট (মাল ক্রয়)
$stmt_pur = $pdo->prepare("SELECT SUM(total_amount) as total_pur, SUM(due_amount) as due_pur FROM purchases WHERE purchase_date BETWEEN :start AND :end");
$stmt_pur->execute(['start' => $start_date, 'end' => $end_date]);
$pur_data = $stmt_pur->fetch();
$total_purchase = $pur_data->total_pur ?? 0;
$purchase_due = $pur_data->due_pur ?? 0;

// গ. গ্রস প্রফিট (Gross Profit = বিক্রয় মূল্য - কেনা মূল্য)
$stmt_profit = $pdo->prepare("
    SELECT SUM((si.price - p.purchase_price) * si.qty) as gross_profit 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    JOIN products p ON si.product_id = p.id 
    WHERE s.sale_date BETWEEN :start AND :end
");
$stmt_profit->execute(['start' => $start_date, 'end' => $end_date]);
$gross_profit = $stmt_profit->fetch()->gross_profit ?? 0;

// ঘ. খরচ (Expenses)
$stmt_expense = $pdo->prepare("SELECT SUM(amount) as total_expense FROM transactions WHERE type = 'Expense' AND date BETWEEN :start AND :end");
$stmt_expense->execute(['start' => $start_date, 'end' => $end_date]);
$total_expense = $stmt_expense->fetch()->total_expense ?? 0;

// ঙ. বকেয়া আদায় (Due Collections)
$stmt_collected = $pdo->prepare("SELECT SUM(amount) as total_collected FROM transactions WHERE type = 'Due Collection' AND date BETWEEN :start AND :end");
$stmt_collected->execute(['start' => $start_date, 'end' => $end_date]);
$total_collected = $stmt_collected->fetch()->total_collected ?? 0;

// চ. নেট প্রফিট (প্রকৃত লাভ)
$net_profit = $gross_profit - $total_expense;

// ছ. গ্লোবাল সামারি (পুরো ব্যবসার বর্তমান অবস্থা - Date Range এর বাইরে)
$total_market_due = $pdo->query("SELECT SUM(total_due) as amt FROM customers")->fetch()->amt ?? 0;
$total_supplier_payable = $pdo->query("SELECT SUM(total_due) as amt FROM suppliers")->fetch()->amt ?? 0;

// টপ ১০ বকেয়া কাস্টমার এবং সাপ্লায়ার
$top_customers = $pdo->query("SELECT name, phone, total_due FROM customers WHERE total_due > 0 ORDER BY total_due DESC LIMIT 10")->fetchAll();
$top_suppliers = $pdo->query("SELECT company_name, phone, total_due FROM suppliers WHERE total_due > 0 ORDER BY total_due DESC LIMIT 10")->fetchAll();

// ৪. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #printReport, #printReport * { visibility: visible; }
        #printReport { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    }
    .filter-btn.active { background-color: #0d6efd; color: white; border-color: #0d6efd; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h3 class="fw-bold text-dark"><i class="fas fa-chart-pie text-primary"></i> Business Reports & Analytics</h3>
    <button class="btn btn-dark fw-bold shadow-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Full Report</button>
</div>

<div class="card shadow-sm border-0 mb-4 no-print" style="border-radius: 10px;">
    <div class="card-body bg-light">
        <div class="row align-items-center">
            <div class="col-md-5 mb-3 mb-md-0">
                <div class="btn-group w-100 shadow-sm" role="group">
                    <a href="?filter=today" class="btn btn-outline-primary fw-bold filter-btn <?php echo ($filter=='today')?'active':''; ?>">Today</a>
                    <a href="?filter=last_7_days" class="btn btn-outline-primary fw-bold filter-btn <?php echo ($filter=='last_7_days')?'active':''; ?>">Last 7 Days</a>
                    <a href="?filter=this_month" class="btn btn-outline-primary fw-bold filter-btn <?php echo ($filter=='this_month')?'active':''; ?>">This Month</a>
                    <a href="?filter=this_year" class="btn btn-outline-primary fw-bold filter-btn <?php echo ($filter=='this_year')?'active':''; ?>">This Year</a>
                </div>
            </div>
            
            <div class="col-md-7">
                <form method="GET" action="" class="d-flex gap-2">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white fw-bold">From</span>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white fw-bold">To</span>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm px-4"><i class="fas fa-filter"></i> Filter</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="printReport">
    <div class="text-center mb-4 d-none d-print-block">
        <h2 class="fw-bold text-dark">Bseba Enterprise</h2>
        <h5 class="text-muted">Business Analytics & Profit-Loss Report</h5>
        <p class="fw-bold">Report Period: <span class="text-primary"><?php echo format_date($start_date); ?></span> to <span class="text-primary"><?php echo format_date($end_date); ?></span></p>
        <hr>
    </div>

    <h5 class="fw-bold text-secondary mb-3"><i class="fas fa-calendar-alt"></i> Period Analytics (<?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?>)</h5>
    
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100 rounded">
                <div class="card-body">
                    <h6 class="text-muted fw-bold"><i class="fas fa-shopping-cart text-primary"></i> Total Sales (মোট বিক্রি)</h6>
                    <h3 class="fw-bold text-dark my-2"><?php echo format_taka($total_sales); ?></h3>
                    <div class="d-flex justify-content-between small">
                        <span class="text-success fw-bold">Cash: <?php echo format_taka($sales_paid); ?></span>
                        <span class="text-danger fw-bold">Due: <?php echo format_taka($sales_due); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100 rounded">
                <div class="card-body">
                    <h6 class="text-muted fw-bold"><i class="fas fa-hand-holding-usd text-success"></i> Due Collected (বকেয়া আদায়)</h6>
                    <h3 class="fw-bold text-success my-2">+ <?php echo format_taka($total_collected); ?></h3>
                    <small class="text-muted fw-bold">Old dues collected in this period</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 border-start border-warning border-4 h-100 rounded">
                <div class="card-body">
                    <h6 class="text-muted fw-bold"><i class="fas fa-box-open text-warning"></i> Total Purchase (মাল ক্রয়)</h6>
                    <h3 class="fw-bold text-dark my-2"><?php echo format_taka($total_purchase); ?></h3>
                    <small class="text-danger fw-bold">Unpaid to Supplier: <?php echo format_taka($purchase_due); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 border-start border-danger border-4 h-100 rounded">
                <div class="card-body">
                    <h6 class="text-muted fw-bold"><i class="fas fa-minus-circle text-danger"></i> Total Expenses (দোকানের খরচ)</h6>
                    <h3 class="fw-bold text-danger my-2">- <?php echo format_taka($total_expense); ?></h3>
                    <small class="text-muted fw-bold">Rent, Staff, Bills, etc.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 rounded bg-dark text-white">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 border-bottom border-secondary pb-2"><i class="fas fa-chart-line text-info"></i> Profit & Loss Statement</h5>
                    
                    <div class="row text-center">
                        <div class="col-md-4 border-end border-secondary mb-3 mb-md-0">
                            <h6 class="text-light">Gross Profit (মাল বেচে লাভ)</h6>
                            <h2 class="fw-bold text-info"><?php echo format_taka($gross_profit); ?></h2>
                            <small class="text-muted">Sales Revenue minus Purchase Cost</small>
                        </div>
                        <div class="col-md-4 border-end border-secondary mb-3 mb-md-0">
                            <h6 class="text-light">Operating Expenses (মোট খরচ)</h6>
                            <h2 class="fw-bold text-danger">- <?php echo format_taka($total_expense); ?></h2>
                            <small class="text-muted">All daily shop expenses</small>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-light">Net Profit (পকেটে আসল লাভ)</h6>
                            <h2 class="fw-bold <?php echo ($net_profit >= 0) ? 'text-success' : 'text-warning'; ?>">
                                <?php echo format_taka($net_profit); ?>
                            </h2>
                            <small class="fw-bold <?php echo ($net_profit >= 0) ? 'text-success' : 'text-warning'; ?>">
                                <?php echo ($net_profit >= 0) ? 'Alhamdulillah! You are in Profit.' : 'Warning: Business is in Loss!'; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold text-secondary mb-3"><i class="fas fa-globe"></i> Overall Market Status (Current)</h5>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0 rounded h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-primary"><i class="fas fa-users"></i> Top Due Customers (মার্কেটে পাওনা)</span>
                    <span class="badge bg-danger fs-6">Total: <?php echo format_taka($total_market_due); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Customer Name</th>
                                    <th>Phone</th>
                                    <th class="text-end pe-3">Due Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_customers as $c): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-dark"><?php echo $c->name; ?></td>
                                    <td class="text-muted small"><?php echo $c->phone; ?></td>
                                    <td class="text-end pe-3 text-danger fw-bold"><?php echo format_taka($c->total_due); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(count($top_customers) == 0): ?>
                                    <tr><td colspan="3" class="text-center text-success fw-bold p-3">মার্কেটে কোনো বকেয়া নেই! Excellent!</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0 rounded h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-warning text-dark"><i class="fas fa-truck"></i> Top Payable Suppliers (মহাজনের দেনা)</span>
                    <span class="badge bg-warning text-dark border border-dark fs-6">Total: <?php echo format_taka($total_supplier_payable); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Company Name</th>
                                    <th>Phone</th>
                                    <th class="text-end pe-3">Payable Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_suppliers as $s): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-dark"><?php echo $s->company_name; ?></td>
                                    <td class="text-muted small"><?php echo $s->phone; ?></td>
                                    <td class="text-end pe-3 text-warning text-dark fw-bold"><?php echo format_taka($s->total_due); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(count($top_suppliers) == 0): ?>
                                    <tr><td colspan="3" class="text-center text-success fw-bold p-3">কোম্পানির কোনো দেনা নেই! All Clear!</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// ৩. Footer
include 'includes/footer.php'; 
?>