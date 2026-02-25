<?php
// বর্তমান পেজের নাম বের করা (যেমন: index.php, customers.php)
$current_page = basename($_SERVER['PHP_SELF']);

// সাবমেনুগুলো অটোমেটিক খোলার জন্য লজিক
$is_party_menu = in_array($current_page, ['customers.php', 'suppliers.php']);
$is_product_menu = in_array($current_page, ['products.php', 'barcode.php']);
?>

<nav id="sidebar">
    <div class="sidebar-header border-bottom border-secondary pb-3 mb-3 text-center">
        <i class="fas fa-layer-group text-warning fs-3 mb-2"></i> 
        <h4 class="fw-bold mb-0 text-white">Bseba ERP</h4>
    </div>
    
    <ul class="list-unstyled components">
        <li class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a>
        </li>
        
        <li class="<?php echo $is_party_menu ? 'active' : ''; ?>">
            <a href="#partySubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_party_menu ? 'true' : 'false'; ?>" class="dropdown-toggle">
                <i class="fas fa-users"></i> Customer & Supplier
            </a>
            <ul class="collapse list-unstyled <?php echo $is_party_menu ? 'show' : ''; ?>" id="partySubmenu">
                <li class="<?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>">
                    <a href="customers.php"><i class="fas fa-angle-right"></i> Customers Ledger</a>
                </li>
                <li class="<?php echo ($current_page == 'suppliers.php') ? 'active' : ''; ?>">
                    <a href="suppliers.php"><i class="fas fa-angle-right"></i> Suppliers Ledger</a>
                </li>
            </ul>
        </li>

        <li class="<?php echo $is_product_menu ? 'active' : ''; ?>">
            <a href="#productSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_product_menu ? 'true' : 'false'; ?>" class="dropdown-toggle">
                <i class="fas fa-box-open"></i> Product & Stock
            </a>
            <ul class="collapse list-unstyled <?php echo $is_product_menu ? 'show' : ''; ?>" id="productSubmenu">
                <li class="<?php echo ($current_page == 'products.php' && isset($_GET['action'])) ? 'active' : ''; ?>">
                    <a href="products.php?action=add"><i class="fas fa-angle-right"></i> Add Product</a>
                </li>
                <li class="<?php echo ($current_page == 'products.php' && !isset($_GET['action'])) ? 'active' : ''; ?>">
                    <a href="products.php"><i class="fas fa-angle-right"></i> Product List</a>
                </li>
                <li class="<?php echo ($current_page == 'barcode.php') ? 'active' : ''; ?>">
                    <a href="barcode.php"><i class="fas fa-barcode"></i> Barcode Print</a>
                </li>
            </ul>
        </li>

        <li class="<?php echo ($current_page == 'purchases.php') ? 'active' : ''; ?>">
            <a href="purchases.php"><i class="fas fa-shopping-basket"></i> Purchase History</a>
        </li>
        <li class="<?php echo ($current_page == 'pos.php') ? 'active' : ''; ?>">
            <a href="pos.php" class="<?php echo ($current_page == 'pos.php') ? 'text-white' : 'text-warning'; ?> fw-bold"><i class="fas fa-desktop"></i> Point of Sale (POS)</a>
        </li>
        <li class="<?php echo ($current_page == 'accounts.php') ? 'active' : ''; ?>">
            <a href="accounts.php"><i class="fas fa-wallet"></i> Accounts & Cashbook</a>
        </li>
        <li class="<?php echo ($current_page == 'hr.php') ? 'active' : ''; ?>">
            <a href="hr.php"><i class="fas fa-user-tie"></i> HR (Staff)</a>
        </li>
        <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <a href="reports.php"><i class="fas fa-chart-line"></i> Report & Analytics</a>
        </li>
        <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <a href="settings.php"><i class="fas fa-cog"></i> Business Settings</a>
        </li>
    </ul>
</nav>

<div id="content">
    
    <div class="top-navbar shadow-sm d-flex justify-content-between align-items-center px-4 py-3 bg-white mb-4 rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-primary border-2 shadow-sm" id="sidebarCollapse">
                <i class="fas fa-bars"></i>
            </button>
            <span class="ms-3 fw-bold text-success d-none d-md-inline bg-light px-3 py-2 rounded-pill border border-success">
                <i class="fas fa-headset"></i> Support: 096 3838 0101
            </span>
        </div>
        
        <div class="d-flex align-items-center">
            <a href="pos.php" class="btn btn-success me-3 fw-bold shadow-sm rounded-pill px-4">
                <i class="fas fa-shopping-cart"></i> POS Sale
            </a>
            
            <div class="dropdown">
                <button type="button" class="btn btn-light shadow-sm border border-secondary dropdown-toggle d-flex align-items-center gap-2 px-3 rounded-pill" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle fs-4 text-primary"></i> 
                    <span class="fw-bold text-dark">
                        <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin User'; ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <a class="dropdown-item fw-bold text-secondary py-2" href="settings.php">
                            <i class="fas fa-user-cog"></i> Profile Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item fw-bold text-danger py-2" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="main-content">