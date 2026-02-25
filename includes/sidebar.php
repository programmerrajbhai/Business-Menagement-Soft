<nav id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-layer-group"></i> Bseba ERP
    </div>
    
    <ul class="list-unstyled components">
        <li>
            <a href="index.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
        </li>
        
        <li>
            <a href="#partySubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-users"></i> Customer & Supplier
            </a>
            <ul class="collapse list-unstyled" id="partySubmenu">
                <li><a href="customers.php"><i class="fas fa-angle-right"></i> Customer</a></li>
                <li><a href="suppliers.php"><i class="fas fa-angle-right"></i> Supplier</a></li>
            </ul>
        </li>

        <li>
            <a href="#productSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-box-open"></i> Product
            </a>
            <ul class="collapse list-unstyled" id="productSubmenu">
                <li><a href="products.php?action=add"><i class="fas fa-angle-right"></i> New Product</a></li>
                <li><a href="products.php"><i class="fas fa-angle-right"></i> Product List</a></li>
                <li><a href="barcode.php"><i class="fas fa-barcode"></i> Barcode Print</a></li>
            </ul>
        </li>

        <li><a href="purchases.php"><i class="fas fa-shopping-basket"></i> Purchase</a></li>
        <li><a href="pos.php" class="text-warning"><i class="fas fa-desktop"></i> Point of Sale (POS)</a></li>
        <li><a href="accounts.php"><i class="fas fa-wallet"></i> Accounts & Expense</a></li>
        <li><a href="hr.php"><i class="fas fa-user-tie"></i> HR (Staff)</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Report</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Business Setting</a></li>
    </ul>
</nav>

<div id="content">
    
    <div class="top-navbar">
        <div>
            <button class="btn btn-outline-secondary" id="sidebarCollapse">
                <i class="fas fa-bars"></i>
            </button>
            <span class="ms-3 fw-bold text-success d-none d-md-inline">
                <i class="fas fa-headset"></i> Support: 096 3838 0101
            </span>
        </div>
        <div>
            <a href="pos.php" class="btn btn-outline-success me-2 fw-bold">
                <i class="fas fa-plus-circle"></i> Sale
            </a>
            <div class="btn-group">
                <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle fs-5 text-primary"></i> 
                    <span class="ms-1 fw-bold"><?php echo isset($current_user_name) ? $current_user_name : 'User'; ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="settings.php">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="main-content">