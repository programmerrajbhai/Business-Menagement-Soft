<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

$success_msg = "";
$error_msg = "";

// [অ্যাকশন ১] নতুন প্রোডাক্ট সেভ করা
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $barcode = !empty($_POST['barcode']) ? $_POST['barcode'] : rand(10000000, 99999999);
    $purchase_price = $_POST['purchase_price'];
    $sale_price = $_POST['sale_price'];
    $wholesale_price = $_POST['wholesale_price'];
    $stock_qty = $_POST['stock_qty'];
    $unit = $_POST['unit'];
    $alert_qty = $_POST['alert_qty'];

    try {
        $sql = "INSERT INTO products (name, category_id, barcode, purchase_price, sale_price, wholesale_price, stock_qty, unit, alert_qty) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category_id, $barcode, $purchase_price, $sale_price, $wholesale_price, $stock_qty, $unit, $alert_qty]);
        $success_msg = "নতুন প্রোডাক্ট সফলভাবে স্টকে যোগ করা হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "সমস্যা হয়েছে (বারকোড হয়তো মিলে গেছে): " . $e->getMessage();
    }
}

// [অ্যাকশন ২] প্রোডাক্ট এডিট/আপডেট করা
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_product'])) {
    $id = $_POST['product_id'];
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $barcode = $_POST['barcode'];
    $purchase_price = $_POST['purchase_price'];
    $sale_price = $_POST['sale_price'];
    $wholesale_price = $_POST['wholesale_price'];
    $stock_qty = $_POST['stock_qty'];
    $unit = $_POST['unit'];
    $alert_qty = $_POST['alert_qty'];

    try {
        $sql = "UPDATE products SET name=?, category_id=?, barcode=?, purchase_price=?, sale_price=?, wholesale_price=?, stock_qty=?, unit=?, alert_qty=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category_id, $barcode, $purchase_price, $sale_price, $wholesale_price, $stock_qty, $unit, $alert_qty, $id]);
        $success_msg = "প্রোডাক্টের তথ্য সফলভাবে আপডেট করা হয়েছে!";
    } catch(PDOException $e) {
        $error_msg = "আপডেট করতে সমস্যা হয়েছে: " . $e->getMessage();
    }
}

// [অ্যাকশন ৩] প্রোডাক্ট ডিলিট করা
if (isset($_GET['delete_id'])) {
    if($current_user_role == 'admin') {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_GET['delete_id']]);
            $success_msg = "প্রোডাক্ট ডাটাবেস থেকে মুছে ফেলা হয়েছে!";
        } catch(PDOException $e) {
            $error_msg = "এই প্রোডাক্টটি অলরেডি সেল বা পারচেস করা হয়েছে, তাই ডিলিট করা যাবে না। আপনি চাইলে স্টক ০ করে দিতে পারেন।";
        }
    } else {
        $error_msg = "আপনার প্রোডাক্ট ডিলিট করার পারমিশন নেই!";
    }
}

// --- ইনভেন্টরি ক্যালকুলেশন (মালিকের জন্য) ---
$total_products = 0;
$total_stock_value = 0;
$total_retail_value = 0;
$low_stock_count = 0;

$stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
$products = $stmt->fetchAll();

foreach($products as $p) {
    $total_products++;
    $total_stock_value += ($p->purchase_price * $p->stock_qty); // গোডাউনে কেনা দামের মাল
    $total_retail_value += ($p->sale_price * $p->stock_qty);    // গোডাউনে বিক্রয় দামের মাল
    if($p->stock_qty <= $p->alert_qty) {
        $low_stock_count++;
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fas fa-box-open text-primary"></i> Advanced Product Management</h3>
    <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="fas fa-plus-circle"></i> Add New Product
    </button>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-white shadow-sm border-0 border-start border-primary border-4 h-100 rounded">
            <div class="card-body">
                <p class="text-muted fw-bold mb-1"><i class="fas fa-boxes"></i> Total Products</p>
                <h3 class="fw-bold text-dark"><?php echo $total_products; ?> Items</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-white shadow-sm border-0 border-start border-danger border-4 h-100 rounded">
            <div class="card-body">
                <p class="text-muted fw-bold mb-1"><i class="fas fa-exclamation-triangle text-danger"></i> Low Stock Alert</p>
                <h3 class="fw-bold text-danger"><?php echo $low_stock_count; ?> Items</h3>
                <small class="text-muted">Immediately re-order required</small>
            </div>
        </div>
    </div>
    
    <?php if($current_user_role == 'admin'): ?>
    <div class="col-md-3">
        <div class="card bg-white shadow-sm border-0 border-start border-warning border-4 h-100 rounded">
            <div class="card-body">
                <p class="text-muted fw-bold mb-1"><i class="fas fa-money-bill-wave"></i> Stock Value (কেনা দাম)</p>
                <h3 class="fw-bold text-dark"><?php echo format_taka($total_stock_value); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-white shadow-sm border-0 border-start border-success border-4 h-100 rounded">
            <div class="card-body">
                <p class="text-muted fw-bold mb-1"><i class="fas fa-tags"></i> Retail Value (বিক্রয় দাম)</p>
                <h3 class="fw-bold text-success"><?php echo format_taka($total_retail_value); ?></h3>
                <small class="text-success fw-bold">Est. Profit: <?php echo format_taka($total_retail_value - $total_stock_value); ?></small>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0 mb-3 rounded bg-light">
    <div class="card-body py-2">
        <div class="row align-items-center">
            <div class="col-md-6 mb-2 mb-md-0">
                <div class="input-group">
                    <span class="input-group-text bg-white border-primary"><i class="fas fa-search text-primary"></i></span>
                    <input type="text" id="searchProduct" class="form-control border-primary" placeholder="প্রোডাক্টের নাম বা বারকোড লিখে খুঁজুন..." onkeyup="filterProducts()">
                </div>
            </div>
            <div class="col-md-4">
                <select id="filterCategory" class="form-control border-primary" onchange="filterProducts()">
                    <option value="ALL">All Categories (সব ক্যাটাগরি)</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat->name; ?>"><?php echo $cat->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 rounded">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="productTable">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">Barcode</th>
                        <th>Product Details</th>
                        <?php if($current_user_role == 'admin'): ?>
                        <th>Purchase (কেনা)</th>
                        <?php endif; ?>
                        <th>Pricing (খুচরা / পাইকারি)</th>
                        <th>Current Stock</th>
                        <th class="text-center pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $row): 
                        $margin = $row->sale_price - $row->purchase_price;
                    ?>
                    <tr>
                        <td class="ps-3"><span class="badge bg-secondary px-2 py-1"><i class="fas fa-barcode"></i> <?php echo $row->barcode; ?></span></td>
                        <td>
                            <div class="fw-bold text-primary fs-6"><?php echo $row->name; ?></div>
                            <small class="text-muted badge bg-light text-dark border"><i class="fas fa-tags"></i> <span class="cat-name"><?php echo $row->category_name; ?></span></small>
                        </td>
                        
                        <?php if($current_user_role == 'admin'): ?>
                        <td>
                            <span class="fw-bold text-danger"><?php echo format_taka($row->purchase_price); ?></span><br>
                            <small class="text-success fw-bold">Profit: +<?php echo format_taka($margin); ?></small>
                        </td>
                        <?php endif; ?>
                        
                        <td>
                            <span class="text-success fw-bold fs-6"><?php echo format_taka($row->sale_price); ?></span> <small class="text-muted">(Retail)</small><br>
                            <span class="text-primary fw-bold"><?php echo format_taka($row->wholesale_price); ?></span> <small class="text-muted">(Wholesale)</small>
                        </td>
                        
                        <td>
                            <?php if($row->stock_qty <= $row->alert_qty): ?>
                                <span class="badge bg-danger fs-6 blink_me px-3 py-2 shadow-sm" title="Stock is low!"><?php echo $row->stock_qty . ' ' . $row->unit; ?> <i class="fas fa-exclamation-circle"></i></span>
                            <?php else: ?>
                                <span class="badge bg-success fs-6 px-3 py-2 shadow-sm"><?php echo $row->stock_qty . ' ' . $row->unit; ?></span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="text-center pe-3">
                            <div class="btn-group shadow-sm">
                                <button class="btn btn-sm btn-info text-white fw-bold" title="Edit" 
                                    onclick="openEditModal(
                                        <?php echo $row->id; ?>, 
                                        '<?php echo addslashes($row->name); ?>', 
                                        <?php echo $row->category_id; ?>, 
                                        '<?php echo $row->barcode; ?>', 
                                        <?php echo $row->purchase_price; ?>, 
                                        <?php echo $row->sale_price; ?>, 
                                        <?php echo $row->wholesale_price; ?>, 
                                        <?php echo $row->stock_qty; ?>, 
                                        '<?php echo $row->unit; ?>', 
                                        <?php echo $row->alert_qty; ?>
                                    )">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <a href="barcode.php?product_barcode=<?php echo $row->barcode; ?>" class="btn btn-sm btn-dark" title="Print Barcode">
                                    <i class="fas fa-print"></i>
                                </a>
                                
                                <?php if($current_user_role == 'admin'): ?>
                                <a href="products.php?delete_id=<?php echo $row->id; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('আপনি কি নিশ্চিত যে এই প্রোডাক্টটি মুছে ফেলতে চান?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-primary border-3">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-box"></i> Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body bg-light">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Product Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="যেমন: Lux Soap 100g" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">-- সিলেক্ট করুন --</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat->id; ?>"><?php echo $cat->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Barcode (Optional)</label>
                            <input type="text" name="barcode" class="form-control border-dark" placeholder="ফাঁকা রাখলে অটোমেটিক তৈরি হবে">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Unit (একক) *</label>
                            <select name="unit" class="form-control">
                                <option value="Pcs">Pcs (পিস)</option>
                                <option value="Box">Box (বক্স)</option>
                                <option value="Kg">Kg (কেজি)</option>
                                <option value="Dozen">Dozen (ডজন)</option>
                                <option value="Ltr">Liter (লিটার)</option>
                                <option value="Meter">Meter (মিটার)</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-danger">Purchase Price (কেনা দাম) *</label>
                            <input type="number" step="0.01" name="purchase_price" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-success">Sale Price (খুচরা দাম) *</label>
                            <input type="number" step="0.01" name="sale_price" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-primary">Wholesale Price (পাইকারি) *</label>
                            <input type="number" step="0.01" name="wholesale_price" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Initial Stock (বর্তমান স্টক)</label>
                            <input type="number" name="stock_qty" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-warning text-dark">Low Stock Alert Quantity</label>
                            <input type="number" name="alert_qty" class="form-control" value="5" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_product" class="btn btn-primary fw-bold w-100 fs-5"><i class="fas fa-save"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-info border-3">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit"></i> Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="product_id" id="edit_id">
                <div class="modal-body bg-light">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Product Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category *</label>
                            <select name="category_id" id="edit_category" class="form-control" required>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat->id; ?>"><?php echo $cat->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Barcode</label>
                            <input type="text" name="barcode" id="edit_barcode" class="form-control border-dark" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Unit (একক) *</label>
                            <select name="unit" id="edit_unit" class="form-control">
                                <option value="Pcs">Pcs (পিস)</option>
                                <option value="Box">Box (বক্স)</option>
                                <option value="Kg">Kg (কেজি)</option>
                                <option value="Dozen">Dozen (ডজন)</option>
                                <option value="Ltr">Liter (লিটার)</option>
                                <option value="Meter">Meter (মিটার)</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-danger">Purchase Price</label>
                            <input type="number" step="0.01" name="purchase_price" id="edit_purchase" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-success">Sale Price</label>
                            <input type="number" step="0.01" name="sale_price" id="edit_sale" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-primary">Wholesale Price</label>
                            <input type="number" step="0.01" name="wholesale_price" id="edit_wholesale" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Update Stock (ম্যানুয়াল স্টক)</label>
                            <input type="number" name="stock_qty" id="edit_stock" class="form-control fw-bold" required>
                            <small class="text-muted">স্টক এডিট করলে সরাসরি গোডাউনের মাল চেঞ্জ হয়ে যাবে।</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Low Stock Alert</label>
                            <input type="number" name="alert_qty" id="edit_alert" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_product" class="btn btn-info text-white fw-bold w-100 fs-5"><i class="fas fa-sync"></i> Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Smart Filter (Search by Name/Barcode + Dropdown Category)
    function filterProducts() {
        let input = document.getElementById("searchProduct").value.toUpperCase();
        let filterCat = document.getElementById("filterCategory").value.toUpperCase();
        let table = document.getElementById("productTable");
        let tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            let tdBarcode = tr[i].getElementsByTagName("td")[0];
            let tdNameCat = tr[i].getElementsByTagName("td")[1];
            
            if (tdBarcode && tdNameCat) {
                let textBarcode = tdBarcode.textContent || tdBarcode.innerText;
                let textName = tdNameCat.textContent || tdNameCat.innerText;
                let textCategory = tdNameCat.querySelector('.cat-name').innerText.toUpperCase();
                
                // Check if search matches
                let matchesSearch = (textName.toUpperCase().indexOf(input) > -1 || textBarcode.toUpperCase().indexOf(input) > -1);
                // Check if category matches
                let matchesCat = (filterCat === "ALL" || textCategory === filterCat);
                
                if (matchesSearch && matchesCat) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }       
        }
    }

    // Open Edit Modal & Populate Data
    function openEditModal(id, name, cat_id, barcode, pur_price, sale_price, ws_price, stock, unit, alert) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_category').value = cat_id;
        document.getElementById('edit_barcode').value = barcode;
        document.getElementById('edit_purchase').value = pur_price;
        document.getElementById('edit_sale').value = sale_price;
        document.getElementById('edit_wholesale').value = ws_price;
        document.getElementById('edit_stock').value = stock;
        document.getElementById('edit_unit').value = unit;
        document.getElementById('edit_alert').value = alert;
        
        var myModal = new bootstrap.Modal(document.getElementById('editProductModal'));
        myModal.show();
    }
</script>

<style>
/* স্টক কমে গেলে লাল লেখাটা ব্লিঙ্ক করার অ্যানিমেশন */
.blink_me { animation: blinker 1.5s linear infinite; }
@keyframes blinker { 50% { opacity: 0; } }
</style>

<?php include 'includes/footer.php'; ?>