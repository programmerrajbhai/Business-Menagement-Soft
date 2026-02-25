<?php
// ১. Backend Logic: সিকিউরিটি এবং ডাটাবেস
require 'includes/auth.php';
require 'includes/db_connect.php';
require 'includes/functions.php';

// ডাটাবেস থেকে শুধু সেই প্রোডাক্টগুলো আনবো যাদের বারকোড আছে
$stmt = $pdo->query("SELECT id, name, barcode, sale_price FROM products WHERE barcode IS NOT NULL AND barcode != '' ORDER BY id DESC");
$products = $stmt->fetchAll();

// ২. Frontend Design: লেআউট শুরু
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @media print {
        body * {
            visibility: hidden; /* পুরো পেজ গায়েব করে দাও */
        }
        #printArea, #printArea * {
            visibility: visible; /* শুধু বারকোড এরিয়া দেখাও */
        }
        #printArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none !important;
        }
        /* স্টিকারের সাইজ এবং মার্জিন ঠিক করার জন্য */
        .barcode-sticker {
            page-break-inside: avoid;
            margin-bottom: 10px;
        }
    }
    
    .barcode-sticker {
        border: 1px dashed #ccc;
        padding: 10px;
        text-align: center;
        background: #fff;
        border-radius: 5px;
        display: inline-block;
        width: 100%;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h3 class="fw-bold text-dark"><i class="fas fa-barcode text-dark"></i> Barcode Generator</h3>
</div>

<div class="row no-print">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0" style="border-radius: 10px;">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="fas fa-cogs"></i> Generate Settings
            </div>
            <div class="card-body bg-light">
                <form id="barcodeForm" onsubmit="event.preventDefault(); generateBarcodes();">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Product *</label>
                        <select id="product_select" class="form-control" required>
                            <option value="">-- প্রোডাক্ট সিলেক্ট করুন --</option>
                            <?php foreach($products as $p): ?>
                                <option value="<?php echo $p->barcode; ?>" data-name="<?php echo addslashes($p->name); ?>" data-price="<?php echo format_taka($p->sale_price); ?>">
                                    <?php echo $p->name; ?> (<?php echo $p->barcode; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantity (কয়টি স্টিকার লাগবে?) *</label>
                        <input type="number" id="sticker_qty" class="form-control" value="10" min="1" max="100" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-magic"></i> Generate Barcodes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0" style="border-radius: 10px;">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-success"><i class="fas fa-print"></i> Print Preview</span>
                <button class="btn btn-success btn-sm fw-bold" onclick="window.print()"><i class="fas fa-print"></i> Print Now</button>
            </div>
            <div class="card-body bg-light" style="min-height: 300px; max-height: 500px; overflow-y: auto;">
                
                <div id="printArea">
                    <div class="row" id="barcodeContainer">
                        <div class="col-12 text-center text-muted mt-5">
                            <i class="fas fa-barcode fa-3x mb-2"></i>
                            <h5>বাম পাশ থেকে প্রোডাক্ট সিলেক্ট করে Generate বাটনে ক্লিক করুন</h5>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script>
    function generateBarcodes() {
        let select = document.getElementById('product_select');
        let option = select.options[select.selectedIndex];
        
        let barcodeValue = option.value;
        let productName = option.getAttribute('data-name');
        let productPrice = option.getAttribute('data-price');
        let qty = document.getElementById('sticker_qty').value;
        let container = document.getElementById('barcodeContainer');
        
        // কনটেইনার ফাঁকা করা
        container.innerHTML = '';
        
        // দোকান বা কোম্পানির নাম (আপনার ইচ্ছামতো পরিবর্তন করতে পারেন)
        let companyName = "Bseba Enterprise"; 

        for (let i = 0; i < qty; i++) {
            // প্রতিটি স্টিকারের জন্য HTML তৈরি
            let col = document.createElement('div');
            col.className = 'col-4 mb-3'; // এক লাইনে ৩টা স্টিকার (A4 পেপারের জন্য পারফেক্ট)
            
            let stickerHTML = `
                <div class="barcode-sticker shadow-sm">
                    <strong style="font-size: 14px; color: #333;">${companyName}</strong><br>
                    <small style="font-size: 11px;" class="text-muted">${productName}</small><br>
                    <svg class="barcode-img" jsbarcode-format="CODE128" jsbarcode-value="${barcodeValue}" jsbarcode-textmargin="0" jsbarcode-height="40" jsbarcode-width="1.5" jsbarcode-fontSize="12"></svg><br>
                    <strong class="text-success" style="font-size: 15px;">MRP: ${productPrice}</strong>
                </div>
            `;
            col.innerHTML = stickerHTML;
            container.appendChild(col);
        }

        // JsBarcode দিয়ে <svg> ট্যাগগুলোকে আসল বারকোড ছবিতে রূপান্তর করা
        JsBarcode(".barcode-img").init();
    }
</script>

<?php 
// ৩. Footer: সবার শেষে ফুটার
include 'includes/footer.php'; 
?>