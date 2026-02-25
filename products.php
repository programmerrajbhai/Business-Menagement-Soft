<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// ফর্ম সাবমিট হলে নতুন প্রোডাক্ট ডাটাবেসে সেভ করার কোড
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    
    // যদি ইউজার বারকোড না দেয়, তবে অটোমেটিক ৮ ডিজিটের বারকোড তৈরি হবে
    $barcode = !empty($_POST['barcode']) ? $_POST['barcode'] : rand(10000000, 99999999);
    
    $purchase_price = $_POST['purchase_price'];
    $sale_price = $_POST['sale_price'];
    $wholesale_price = $_POST['wholesale_price'];
    $stock_qty = $_POST['stock_qty'];
    $unit = $_POST['unit'];
    $alert_qty = $_POST['alert_qty'];

    try {
        $sql = "INSERT INTO products (name, category_id, barcode, purchase_price, sale_price, wholesale_price, stock_qty, unit, alert_qty) 
                VALUES (:name, :category_id, :barcode, :purchase_price, :sale_price, :wholesale_price, :stock_qty, :unit, :alert_qty)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'category_id' => $category_id,
            'barcode' => $barcode,
            'purchase_price' => $purchase_price,
            'sale_price' => $sale_price,
            'wholesale_price' => $wholesale_price,
            'stock_qty' => $stock_qty,
            'unit' => $unit,
            'alert_qty' => $alert_qty
        ]);
        
        $success_msg = "নতুন প্রোডাক্ট সফলভাবে স্টকে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে (হয়তো বারকোড মিলে গেছে): " . $e->getMessage();
    }
}

// ডাটাবেস থেকে সব প্রোডাক্ট এবং তার ক্যাটাগরি টেনে আনার কোড (JOIN Query)
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
");
$products = $stmt->fetchAll();

// Add Product ফর্মে দেখানোর জন্য ক্যাটাগরি লিস্ট
$cat_stmt = $pdo->query("SELECT * FROM categories");
$categories = $cat_stmt->fetchAll();

// ২. Frontend Design: এখান থেকে লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-box-open text-primary"></i> Product Inventory</h3>
    <button class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="fas fa-plus-circle"></i> Add New Product
    </button>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm" style="border-radius: 10px; border: none;">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Barcode</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Purchase</th>
                    <th>Sale / Wholesale</th>
                    <th>Stock Qty</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($products as $row): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?php echo $row->barcode; ?></span></td>
                    <td class="fw-bold text-primary"><?php echo $row->name; ?></td>
                    <td><?php echo $row->category_name; ?></td>
                    
                    <td>
                        <?php if($current_user_role == 'admin'): ?>
                            <?php echo format_taka($row->purchase_price); ?>
                        <?php else: ?>
                            <span class="text-muted">Hidden</span>
                        <?php endif; ?>
                    </td>
                    
                    <td>
                        <span class="text-success fw-bold"><?php echo format_taka($row->sale_price); ?></span> <br>
                        <small class="text-muted">WS: <?php echo format_taka($row->wholesale_price); ?></small>
                    </td>
                    <td>
                        <?php if($row->stock_qty <= $row->alert_qty): ?>
                            <span class="badge bg-danger fs-6 blink_me" title="Stock is low!"><?php echo $row->stock_qty . ' ' . $row->unit; ?> <i class="fas fa-exclamation-circle"></i></span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6"><?php echo $row->stock_qty . ' ' . $row->unit; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-dark" title="Print Barcode"><i class="fas fa-barcode"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-box"></i> Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Product Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="যেমন: Lux Soap 100g" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">-- সিলেক্ট করুন --</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat->id; ?>"><?php echo $cat->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Barcode (Optional)</label>
                            <input type="text" name="barcode" class="form-control" placeholder="ফাঁকা রাখলে অটোমেটিক তৈরি হবে">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Unit (একক)</label>
                            <select name="unit" class="form-control">
                                <option value="Pcs">Pcs (পিস)</option>
                                <option value="Box">Box (বক্স)</option>
                                <option value="Kg">Kg (কেজি)</option>
                                <option value="Dozen">Dozen (ডজন)</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-danger">Purchase Price (কেনা দাম)</label>
                            <input type="number" step="0.01" name="purchase_price" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-success">Sale Price (খুচরা দাম)</label>
                            <input type="number" step="0.01" name="sale_price" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-primary">Wholesale Price (পাইকারি)</label>
                            <input type="number" step="0.01" name="wholesale_price" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Initial Stock (বর্তমান স্টক)</label>
                            <input type="number" name="stock_qty" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Low Stock Alert (কত পিসে নামলে অ্যালার্ট দিবে?)</label>
                            <input type="number" name="alert_qty" class="form-control" value="5" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_product" class="btn btn-success"><i class="fas fa-save"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* স্টক কমে গেলে লাল লেখাটা ব্লিঙ্ক করার জন্য ছোট্ট সিএসএস অ্যানিমেশন */
.blink_me {
    animation: blinker 1.5s linear infinite;
}
@keyframes blinker {
    50% { opacity: 0; }
}
</style>

<?php 
// ৩. Footer: সবার শেষে ফুটার
include 'includes/footer.php'; 
?>