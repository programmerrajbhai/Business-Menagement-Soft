<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// --- SaaS Dashboard Calculation (শুধু বর্তমান দোকানের ডাটা) ---

// ১. Sales Calculation
$sale_stmt = $pdo->prepare("SELECT SUM(total_amount) as total, SUM(paid_amount) as paid, SUM(due_amount) as due, COUNT(id) as count FROM sales WHERE shop_id = ?");
$sale_stmt->execute([$current_shop_id]);
$sale_data = $sale_stmt->fetch();
$total_sale = $sale_data->total ?? 0;
$sale_paid = $sale_data->paid ?? 0;
$sale_due = $sale_data->due ?? 0;
$sale_count = $sale_data->count ?? 0;
$sale_progress = ($total_sale > 0) ? round(($sale_paid / $total_sale) * 100) : 0;

// ২. Purchase Calculation
$pur_stmt = $pdo->prepare("SELECT SUM(total_amount) as total, SUM(paid_amount) as paid, SUM(due_amount) as due, COUNT(id) as count FROM purchases WHERE shop_id = ?");
$pur_stmt->execute([$current_shop_id]);
$pur_data = $pur_stmt->fetch();
$total_purchase = $pur_data->total ?? 0;
$pur_paid = $pur_data->paid ?? 0;
$pur_due = $pur_data->due ?? 0;
$pur_count = $pur_data->count ?? 0;
$pur_progress = ($total_purchase > 0) ? round(($pur_paid / $total_purchase) * 100) : 0;

// ৩. Cash Flow (আয় এবং ব্যয়)
$income_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE shop_id = ? AND type IN ('Income', 'Due Collection')");
$income_stmt->execute([$current_shop_id]);
$total_income = $income_stmt->fetch()->total ?? 0;

$expense_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE shop_id = ? AND type IN ('Expense', 'Supplier Payment')");
$expense_stmt->execute([$current_shop_id]);
$total_expense = $expense_stmt->fetch()->total ?? 0;

$pure_expense_stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE shop_id = ? AND type = 'Expense'");
$pure_expense_stmt->execute([$current_shop_id]);
$pure_expense = $pure_expense_stmt->fetch()->total ?? 0;

$net_balance = $total_income - $total_expense;
$cash_flow_progress = ($total_income > 0) ? round(($total_income / ($total_income + $total_expense)) * 100) : 0;

// ৪. Customer Halkhata & Market Due (কাস্টমার ও মহাজনের পাওনা/দেনা)
$cust_stmt = $pdo->prepare("SELECT SUM(total_due) as total FROM customers WHERE shop_id = ?");
$cust_stmt->execute([$current_shop_id]);
$total_customer_due = $cust_stmt->fetch()->total ?? 0; // মার্কেটে আপনার পাওনা

$supp_stmt = $pdo->prepare("SELECT SUM(total_due) as total FROM suppliers WHERE shop_id = ?");
$supp_stmt->execute([$current_shop_id]);
$total_supplier_due = $supp_stmt->fetch()->total ?? 0; // মহাজনের দেনা

$total_halkhata = $total_customer_due + $total_supplier_due;
$customer_due_progress = ($total_halkhata > 0) ? round(($total_customer_due / $total_halkhata) * 100) : 0;

// ৫. Latest 5 Invoices
$latest_sales = $pdo->prepare("
    SELECT s.*, c.name as customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE s.shop_id = ? 
    ORDER BY s.id DESC LIMIT 5
");
$latest_sales->execute([$current_shop_id]);
$latest_sales = $latest_sales->fetchAll();

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="row mb-3 align-items-center bg-white p-3 shadow-sm rounded">
    <div class="col-md-4">
        <h4 class="fw-bold text-success m-0"><i class="fas fa-store"></i> <?php echo $current_shop_name; ?> Dashboard</h4>
    </div>
    <div class="col-md-8 d-flex justify-content-end">
        <div class="input-group w-50 me-2 shadow-sm">
            <span class="input-group-text bg-white border-secondary"><i class="fas fa-calendar-alt"></i></span>
            <input type="date" class="form-control border-secondary" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
        </div>
        <div class="input-group w-50 shadow-sm">
            <span class="input-group-text bg-white border-secondary"><i class="fas fa-calendar-alt"></i></span>
            <input type="date" class="form-control border-secondary" value="<?php echo date('Y-m-d'); ?>">
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-start border-success border-5 h-100" style="border-radius: 10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-success fw-bold"><i class="fas fa-shopping-cart bg-light p-2 rounded text-success"></i> <?php echo $sale_count; ?> Sales</div>
                </div>
                <h3 class="fw-bold text-dark mb-3"><?php echo number_format($total_sale, 2); ?></h3>
                <div class="d-flex justify-content-between text-muted small fw-bold">
                    <span class="text-success">Paid: <?php echo number_format($sale_paid, 2); ?></span>
                    <span class="text-danger">Due: <?php echo number_format($sale_due, 2); ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $sale_progress; ?>%;"></div>
                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo 100 - $sale_progress; ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-start border-primary border-5 h-100" style="border-radius: 10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-primary fw-bold"><i class="fas fa-box bg-light p-2 rounded text-primary"></i> <?php echo $pur_count; ?> Purchase</div>
                </div>
                <h3 class="fw-bold text-dark mb-3"><?php echo number_format($total_purchase, 2); ?></h3>
                <div class="d-flex justify-content-between text-muted small fw-bold">
                    <span class="text-success">Paid: <?php echo number_format($pur_paid, 2); ?></span>
                    <span class="text-danger">Due: <?php echo number_format($pur_due, 2); ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pur_progress; ?>%;"></div>
                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo 100 - $pur_progress; ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-start border-warning border-5 h-100" style="border-radius: 10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-warning text-dark fw-bold"><i class="fas fa-money-bill-wave bg-light p-2 rounded text-warning"></i> Cash Flow (গাল্লা)</div>
                </div>
                <h3 class="fw-bold <?php echo ($net_balance < 0) ? 'text-danger' : 'text-dark'; ?> mb-3">
                    <?php echo number_format($net_balance, 2); ?>
                </h3>
                <div class="d-flex justify-content-between text-muted small fw-bold">
                    <span class="text-success">Income: <?php echo number_format($total_income, 2); ?></span>
                    <span class="text-danger">Expense: <?php echo number_format($total_expense, 2); ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $cash_flow_progress; ?>%;"></div>
                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo 100 - $cash_flow_progress; ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-start border-danger border-5 h-100" style="border-radius: 10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-danger fw-bold"><i class="fas fa-book-open bg-light p-2 rounded text-danger"></i> মার্কেট হালখাতা</div>
                </div>
                <h3 class="fw-bold text-dark mb-3">
                    <?php echo number_format($total_customer_due, 2); ?>
                </h3>
                <div class="d-flex justify-content-between text-muted small fw-bold">
                    <span class="text-success" title="কাস্টমারদের কাছে আপনার মোট পাওনা">পাওনা: <?php echo number_format($total_customer_due, 2); ?></span>
                    <span class="text-danger" title="কোম্পানি/মহাজন আপনার কাছে মোট পাবে">দেনা: <?php echo number_format($total_supplier_due, 2); ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $customer_due_progress; ?>%;" title="Market Due"></div>
                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo 100 - $customer_due_progress; ?>%;" title="Supplier Payable"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 mb-3" style="border-radius: 10px;">
            <div class="card-body">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-chart-pie text-primary"></i> Financial Distribution</h6>
                <canvas id="financeChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0 mb-3" style="border-radius: 10px;">
            <div class="card-body">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-file-invoice text-success"></i> Latest Invoice</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>INVOICE</th>
                                <th>CUSTOMER</th>
                                <th>AMOUNT</th>
                                <th>PAID</th>
                                <th>DUE</th>
                                <th>METHOD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($latest_sales as $inv): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo $inv->invoice_no; ?></span></td>
                                <td class="fw-bold text-primary"><?php echo $inv->customer_name ?? 'Walking Customer'; ?></td>
                                <td class="fw-bold text-dark"><?php echo $inv->payable_amount; ?></td>
                                <td class="text-success fw-bold"><?php echo $inv->paid_amount; ?></td>
                                <td class="text-danger fw-bold"><?php echo $inv->due_amount; ?></td>
                                <td><span class="badge bg-light text-dark border border-secondary"><?php echo $inv->payment_method; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($latest_sales) == 0): ?>
                                <tr><td colspan="6" class="text-center text-muted">No sales data found!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('financeChart').getContext('2d');
    const financeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Total Sales', 'Total Purchase', 'Expenses'],
            datasets: [{
                data: [<?php echo $total_sale; ?>, <?php echo $total_purchase; ?>, <?php echo $pure_expense; ?>],
                backgroundColor: ['rgba(40, 167, 69, 0.8)', 'rgba(0, 123, 255, 0.8)', 'rgba(220, 53, 69, 0.8)'],
                borderWidth: 2
            }]
        }
    });
</script>

<?php include 'includes/footer.php'; ?>