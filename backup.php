<?php
// backup.php - Data Export & Backup Center
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$show_png_report = false;
$png_data = [];
$png_title = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['export_data'])) {
    $type = $_POST['data_type'];
    $format = $_POST['format'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    $data = [];
    $table_name = "";
    $report_title = "";

    // Data Fetching Logic based on Type & Date
    if ($type == 'customers') {
        $stmt = $pdo->prepare("SELECT name, phone, address, total_due, credit_limit FROM customers WHERE shop_id = ? ORDER BY id DESC");
        $stmt->execute([$current_shop_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $table_name = 'customers';
        $report_title = "Customer List & Due Report";
    } 
    elseif ($type == 'suppliers') {
        $stmt = $pdo->prepare("SELECT company_name, contact_person, phone, address, total_due FROM suppliers WHERE shop_id = ? ORDER BY id DESC");
        $stmt->execute([$current_shop_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $table_name = 'suppliers';
        $report_title = "Supplier List & Payable Report";
    } 
    elseif ($type == 'products') {
        $stmt = $pdo->prepare("SELECT barcode, name, purchase_price, sale_price, stock_qty, unit FROM products WHERE shop_id = ? ORDER BY name ASC");
        $stmt->execute([$current_shop_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $table_name = 'products';
        $report_title = "Inventory & Stock Report";
    } 
    elseif ($type == 'sales') {
        $stmt = $pdo->prepare("SELECT invoice_no, sale_date, total_amount, discount, payable_amount, paid_amount, due_amount, payment_method FROM sales WHERE shop_id = ? AND sale_date BETWEEN ? AND ? ORDER BY id DESC");
        $stmt->execute([$current_shop_id, $start_date, $end_date]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $table_name = 'sales';
        $report_title = "Sales History ($start_date to $end_date)";
    } 
    elseif ($type == 'purchases') {
        $stmt = $pdo->prepare("SELECT invoice_no, purchase_date, total_amount, paid_amount, due_amount FROM purchases WHERE shop_id = ? AND purchase_date BETWEEN ? AND ? ORDER BY id DESC");
        $stmt->execute([$current_shop_id, $start_date, $end_date]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $table_name = 'purchases';
        $report_title = "Purchase History ($start_date to $end_date)";
    } 
    elseif ($type == 'transactions') {
        $stmt = $pdo->prepare("SELECT date, type, amount, payment_method, note FROM transactions WHERE shop_id = ? AND date BETWEEN ? AND ? ORDER BY id DESC");
        $stmt->execute([$current_shop_id, $start_date, $end_date]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $table_name = 'transactions';
        $report_title = "Cashbook Transactions ($start_date to $end_date)";
    }

    // ==========================================
    // 1. EXCEL (CSV) EXPORT LOGIC
    // ==========================================
    if ($format == 'csv' && count($data) > 0) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename='.$current_shop_name.'_'.$table_name.'_backup.csv');
        $output = fopen('php://output', 'w');
        
        // Print Headers
        fputcsv($output, array_keys($data[0]));
        
        // Print Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    } 
    
    // ==========================================
    // 2. SQL EXPORT LOGIC
    // ==========================================
    elseif ($format == 'sql' && count($data) > 0) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename='.$current_shop_name.'_'.$table_name.'_backup.sql');
        
        echo "-- Database Backup for: $current_shop_name\n";
        echo "-- Table: $table_name\n";
        echo "-- Date Exported: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($data as $row) {
            $keys = array_keys($row);
            $vals = array_map(function($val) use ($pdo) {
                return $val === null ? 'NULL' : $pdo->quote($val);
            }, array_values($row));
            
            echo "INSERT INTO `$table_name` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $vals) . ");\n";
        }
        exit();
    } 
    
    // ==========================================
    // 3. PNG (IMAGE) EXPORT LOGIC
    // ==========================================
    elseif ($format == 'png') {
        if(count($data) > 0) {
            $show_png_report = true;
            $png_data = $data;
            $png_title = $report_title;
        } else {
            $error_msg = "No data found for this date range!";
        }
    } else {
        $error_msg = "No data found to export!";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-database text-primary"></i> Data Backup & Export Center</h3>
</div>

<?php if(isset($error_msg)): ?>
    <div class="alert alert-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 border-top border-primary border-4" style="border-radius: 10px;">
            <div class="card-body p-4 bg-light">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-download text-success"></i> Download Your Data</h5>
                <p class="text-muted small mb-4">Select the data module and format you want to download. You can filter data by date range for Sales, Purchases, and Cashbook records.</p>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="fw-bold mb-1">1. Select Data Module *</label>
                        <select name="data_type" class="form-control border-primary fw-bold" required>
                            <option value="customers">Customers (কাস্টমার ও পাওনা)</option>
                            <option value="suppliers">Suppliers (মহাজন ও দেনা)</option>
                            <option value="products">Inventory (গোডাউনের মাল)</option>
                            <option value="sales">Sales History (বিক্রির হিসাব)</option>
                            <option value="purchases">Purchase History (ক্রয়ের হিসাব)</option>
                            <option value="transactions">Cashbook (ক্যাশবুক)</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="fw-bold mb-1">From Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="fw-bold mb-1">To Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold mb-1">3. Select Export Format *</label>
                        <div class="d-flex gap-2">
                            <div class="form-check border bg-white p-2 px-3 rounded w-100 shadow-sm">
                                <input class="form-check-input ms-1" type="radio" name="format" value="csv" id="f_csv" checked>
                                <label class="form-check-label fw-bold text-success ms-2" for="f_csv"><i class="fas fa-file-excel"></i> Excel (CSV)</label>
                            </div>
                            <div class="form-check border bg-white p-2 px-3 rounded w-100 shadow-sm">
                                <input class="form-check-input ms-1" type="radio" name="format" value="sql" id="f_sql">
                                <label class="form-check-label fw-bold text-primary ms-2" for="f_sql"><i class="fas fa-file-code"></i> SQL File</label>
                            </div>
                            <div class="form-check border bg-white p-2 px-3 rounded w-100 shadow-sm">
                                <input class="form-check-input ms-1" type="radio" name="format" value="png" id="f_png">
                                <label class="form-check-label fw-bold text-danger ms-2" for="f_png"><i class="fas fa-image"></i> Image (PNG)</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="export_data" class="btn btn-primary w-100 fw-bold fs-5 py-2 shadow-sm"><i class="fas fa-cloud-download-alt"></i> Generate Backup</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm border-0 bg-dark text-white h-100" style="border-radius: 10px;">
            <div class="card-body p-5 text-center d-flex flex-column justify-content-center">
                <i class="fas fa-shield-alt fa-4x text-success mb-3"></i>
                <h4 class="fw-bold mb-3">100% Data Guarantee</h4>
                <p class="text-light" style="font-size: 15px;">Your data is completely safe with us. We recommend downloading an Excel backup of your business at the end of every month. Keep your data on your own device for ultimate peace of mind.</p>
            </div>
        </div>
    </div>
</div>

<?php if($show_png_report && count($png_data) > 0): ?>
<div class="mt-5" id="png_report_container" style="background: white; padding: 20px; border-radius: 10px; width: 100%; border: 2px solid #ddd;">
    <div class="text-center mb-4">
        <h2 class="fw-bold text-primary"><?php echo $current_shop_name; ?></h2>
        <h5 class="text-dark fw-bold"><?php echo $png_title; ?></h5>
        <small class="text-muted">Generated on: <?php echo date('d M, Y h:i A'); ?></small>
        <hr>
    </div>
    
    <table class="table table-bordered align-middle text-center table-sm">
        <thead class="table-dark">
            <tr>
                <?php foreach(array_keys($png_data[0]) as $header): ?>
                    <th class="text-uppercase"><?php echo str_replace('_', ' ', $header); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($png_data as $row): ?>
            <tr>
                <?php foreach($row as $cell): ?>
                    <td><?php echo $cell; ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="text-center mt-3 text-muted small"><p>System Generated Report - Bseba ERP</p></div>
</div>

<script>
    // Auto-trigger PNG download using html2canvas
    window.onload = function() {
        Swal.fire({
            title: 'Generating Image...',
            text: 'Please wait while we prepare your PNG backup.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading() }
        });

        setTimeout(function() {
            html2canvas(document.querySelector("#png_report_container"), { scale: 2 }).then(canvas => {
                let link = document.createElement('a');
                link.download = '<?php echo $current_shop_name; ?>_Backup_<?php echo date("Ymd"); ?>.png';
                link.href = canvas.toDataURL("image/png");
                link.click();
                
                Swal.close();
                document.getElementById('png_report_container').style.display = 'none'; // Hide after download
                Swal.fire({ icon: 'success', title: 'Downloaded!', text: 'Your Image Backup has been saved.' });
            });
        }, 1000);
    }
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>