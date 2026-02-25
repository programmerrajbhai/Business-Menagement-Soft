<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// --- ড্যাশবোর্ডের জন্য ডাটাবেস থেকে ক্যালকুলেশন ---

// ১. Sales Calculation (মোট বিক্রি, জমা, বকেয়া এবং ইনভয়েস সংখ্যা)
$sale_stmt = $pdo->query("SELECT SUM(total_amount) as total, SUM(paid_amount) as paid, SUM(due_amount) as due, COUNT(id) as count FROM sales");
$sale_data = $sale_stmt->fetch();
$total_sale = $sale_data->total ?? 0;
$sale_paid = $sale_data->paid ?? 0;
$sale_due = $sale_data->due ?? 0;
$sale_count = $sale_data->count ?? 0;
$sale_progress = ($total_sale > 0) ? round(($sale_paid / $total_sale) * 100) : 0;

// ২. Purchase Calculation (মোট ক্রয়, জমা, বকেয়া এবং ইনভয়েস সংখ্যা)
$pur_stmt = $pdo->query("SELECT SUM(total_amount) as total, SUM(paid_amount) as paid, SUM(due_amount) as due, COUNT(id) as count FROM purchases");
$pur_data = $pur_stmt->fetch();
$total_purchase = $pur_data->total ?? 0;
$pur_paid = $pur_data->paid ?? 0;
$pur_due = $pur_data->due ?? 0;
$pur_count = $pur_data->count ?? 0;
$pur_progress = ($total_purchase > 0) ? round(($pur_paid / $total_purchase) * 100) : 0;

// ৩. Cash Flow (আয় এবং ব্যয়)
$income_stmt = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type IN ('Income', 'Due Collection')");
$total_income = $income_stmt->fetch()->total ?? 0;

$expense_stmt = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type IN ('Expense', 'Supplier Payment')");
$total_expense = $expense_stmt->fetch()->total ?? 0;

// শুধু দোকানের খরচ (Expense)
$pure_expense_stmt = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'Expense'");
$pure_expense = $pure_expense_stmt->fetch()->total ?? 0;

$net_balance = $total_income - $total_expense;
$cash_flow_progress = ($total_income > 0) ? round(($total_income / ($total_income + $total_expense)) * 100) : 0;

// ৪. Latest 5 Invoices (সর্বশেষ ৫টি বিক্রির লিস্ট)
$latest_sales = $pdo->query("
    SELECT s.*, c.name as customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="row mb-3 align-items-center bg-white p-3 shadow-sm rounded">
    <div class="col-md-4">
        <select class="form-select shadow-none border-secondary w-50">
            <option>Today</option>
            <option>This Week</option>
            <option>This Month</option>
            <option selected>Overall (All Time)</option>
        </select>
    </div>
    <div class="col-md-8 d-flex justify-content-end">
        <div class="input-group w-50 me-2">
            <span class="input-group-text bg-white border-secondary"><i class="fas fa-calendar-alt"></i></span>
            <input type="date" class="form-control border-secondary" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
        </div>
        <div class="input-group w-50">
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
                <div class="text-center mt-1 text-muted" style="font-size: 10px;"><?php echo $sale_progress; ?>% Collected</div>
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
                <div class="text-center mt-1 text-muted" style="font-size: 10px;"><?php echo $pur_progress; ?>% Paid</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-start border-warning border-5 h-100" style="border-radius: 10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-warning fw-bold"><i class="fas fa-money-bill-wave bg-light p-2 rounded text-warning"></i> Cash Flow</div>
                </div>
                <h3 class="fw-bold <?php echo ($net_balance < 0) ? 'text-danger' : 'text-dark'; ?> mb-3">
                    <?php echo number_format($net_balance, 2); ?>
                </h3>
                <div class="d-flex justify-content-between text-muted small fw-bold">
                    <span class="text-success">Received: <?php echo number_format($total_income, 2); ?></span>
                    <span class="text-danger">Paid Out: <?php echo number_format($total_expense, 2); ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $cash_flow_progress; ?>%;"></div>
                </div>
                <div class="text-center mt-1 text-muted" style="font-size: 10px;">Cash Flow Ratio</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-start border-danger border-5 h-100" style="border-radius: 10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-danger fw-bold"><i class="fas fa-cog bg-light p-2 rounded text-danger"></i> 500 SMS <span class="text-success">+Info</span></div>
                </div>
                <h5 class="fw-bold text-success text-center mt-3 mb-3">Active Package</h5>
                <div class="d-flex justify-content-between text-muted small fw-bold">
                    <span class="text-success">Balance:</span>
                    <span class="text-success"><?php echo number_format($net_balance, 2); ?></span>
                </div>
                <div class="d-flex justify-content-between text-muted small fw-bold">
                    <span class="text-danger">Expense:</span>
                    <span class="text-danger"><?php echo number_format($pure_expense, 2); ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
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
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',  // Success Green
                    'rgba(0, 123, 255, 0.8)',  // Primary Blue
                    'rgba(220, 53, 69, 0.8)'   // Danger Red
                ],
                borderColor: [
                    'rgba(255, 255, 255, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>