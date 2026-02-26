<?php
// বর্তমান পেজের নাম বের করা (যেমন: index.php, customers.php)
$current_page = basename($_SERVER['PHP_SELF']);

// সাবমেনুগুলো অটোমেটিক খোলার জন্য লজিক
$is_party_menu = in_array($current_page, ['customers.php', 'suppliers.php']);
$is_product_menu = in_array($current_page, ['products.php', 'barcode.php']);
?>

<style>
    /* বেসিক ট্রানজিশন ও মেনু স্টাইল */
    #sidebar ul.components li a {
        padding: 12px 20px;
        font-size: 15px;
        display: block;
        color: #a2a3b7; /* নরমাল কালার */
        text-decoration: none;
        transition: all 0.3s ease-in-out; /* স্মুথ অ্যানিমেশন */
        position: relative;
        border-left: 4px solid transparent;
    }
    
    /* মাউস হোভার (Hover) করলে স্লাইড ইফেক্ট */
    #sidebar ul.components li a:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.05);
        padding-left: 28px; /* একটু ডানদিকে সরে যাবে */
        border-left: 4px solid rgba(40, 167, 69, 0.5);
    }

    /* যে পেজে আছেন (Active State) তার স্টাইল */
    #sidebar ul.components li.active > a {
        color: #fff;
        background: linear-gradient(90deg, rgba(40, 167, 69, 0.8) 0%, rgba(40, 167, 69, 0) 100%);
        border-left: 4px solid #28a745; /* সবুজ বর্ডার */
        font-weight: 600;
    }

    /* সাবমেনুর (Dropdwon) আইটেমগুলোর স্টাইল */
    #sidebar ul.collapse li a {
        padding-left: 40px;
        font-size: 14px;
        background: transparent;
    }
    
    /* সাবমেনু হোভার */
    #sidebar ul.collapse li a:hover {
        padding-left: 45px;
        color: #28a745;
        background: rgba(40, 167, 69, 0.05);
    }

    /* সাবমেনুর Active State (যে সাবমেনুতে আছেন) */
    #sidebar ul.collapse li.active > a {
        color: #28a745; /* টেক্সট সবুজ হবে */
        background: rgba(40, 167, 69, 0.1);
        border-left: 4px solid #28a745;
        font-weight: bold;
    }

    /* ড্রপডাউন অ্যারো (Arrow) এর রোটেশন অ্যানিমেশন */
    #sidebar a.dropdown-toggle::after {
        transition: transform 0.3s ease;
        float: right;
        margin-top: 5px;
    }
    #sidebar a.dropdown-toggle[aria-expanded="true"]::after {
        transform: rotate(90deg); /* ক্লিক করলে অ্যারো ঘুরে যাবে */
    }
</style>

<nav id="sidebar">
    <div class="sidebar-header border-bottom border-secondary pb-3 mb-3 text-center">
        <i class="fas fa-layer-group text-warning fs-3 mb-2"></i> 
        <h4 class="fw-bold mb-0 text-white"><?php echo $current_shop_name; ?></h4>
    </div>
    
    <ul class="list-unstyled components">
        <li class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php"><i class="fas fa-th-large me-2"></i> ড্যাশবোর্ড</a>
        </li>
        
        <li class="<?php echo $is_party_menu ? 'active' : ''; ?>">
            <a href="#partySubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_party_menu ? 'true' : 'false'; ?>" class="dropdown-toggle">
                <i class="fas fa-users me-2"></i> কাস্টমার ও মহাজন
            </a>
            <ul class="collapse list-unstyled <?php echo $is_party_menu ? 'show' : ''; ?>" id="partySubmenu">
                <li class="<?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>">
                    <a href="customers.php"><i class="fas fa-angle-right me-2"></i> কাস্টমার হালখাতা</a>
                </li>
                <li class="<?php echo ($current_page == 'suppliers.php') ? 'active' : ''; ?>">
                    <a href="suppliers.php"><i class="fas fa-angle-right me-2"></i> মহাজনের খাতা</a>
                </li>
            </ul>
        </li>

        <li class="<?php echo $is_product_menu ? 'active' : ''; ?>">
            <a href="#productSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_product_menu ? 'true' : 'false'; ?>" class="dropdown-toggle">
                <i class="fas fa-box-open me-2"></i> প্রোডাক্ট ও স্টক
            </a>
            <ul class="collapse list-unstyled <?php echo $is_product_menu ? 'show' : ''; ?>" id="productSubmenu">
                <li class="<?php echo ($current_page == 'products.php' && isset($_GET['action'])) ? 'active' : ''; ?>">
                    <a href="products.php?action=add"><i class="fas fa-angle-right me-2"></i> নতুন মাল তুলুন</a>
                </li>
                <li class="<?php echo ($current_page == 'products.php' && !isset($_GET['action'])) ? 'active' : ''; ?>">
                    <a href="products.php"><i class="fas fa-angle-right me-2"></i> প্রোডাক্ট লিস্ট</a>
                </li>
                <li class="<?php echo ($current_page == 'barcode.php') ? 'active' : ''; ?>">
                    <a href="barcode.php"><i class="fas fa-barcode me-2"></i> বারকোড প্রিন্ট</a>
                </li>
            </ul>
        </li>

        <li class="<?php echo ($current_page == 'purchases.php') ? 'active' : ''; ?>">
            <a href="purchases.php"><i class="fas fa-shopping-basket me-2"></i> মাল ক্রয়ের হিসাব</a>
        </li>
        <li class="<?php echo ($current_page == 'pos.php') ? 'active' : ''; ?>">
            <a href="pos.php" class="<?php echo ($current_page == 'pos.php') ? 'text-white' : 'text-warning'; ?> fw-bold"><i class="fas fa-desktop me-2"></i> পয়েন্ট অফ সেল (POS)</a>
        </li>
        <li class="<?php echo ($current_page == 'accounts.php') ? 'active' : ''; ?>">
            <a href="accounts.php"><i class="fas fa-wallet me-2"></i> ক্যাশবুক ও হিসাব</a>
        </li>
        <li class="<?php echo ($current_page == 'hr.php') ? 'active' : ''; ?>">
            <a href="hr.php"><i class="fas fa-user-tie me-2"></i> কর্মচারী (স্টাফ)</a>
        </li>
        <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <a href="reports.php"><i class="fas fa-chart-line me-2"></i> রিপোর্ট ও লাভ-ক্ষতি</a>
        </li>
        
        <li class="<?php echo ($current_page == 'backup.php') ? 'active' : ''; ?>">
            <a href="backup.php"><i class="fas fa-database me-2"></i> ডাটা ব্যাকআপ (Backup)</a>
        </li>
        
        <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <a href="settings.php"><i class="fas fa-cog me-2"></i> সিস্টেম সেটিংস</a>
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
                <i class="fas fa-headset"></i> সাপোর্ট: ০৯৬ ৩৮৩৮ ০১০১
            </span>
        </div>
        
        <div class="d-flex align-items-center">
            <a href="pos.php" class="btn btn-success me-3 fw-bold shadow-sm rounded-pill px-4">
                <i class="fas fa-shopping-cart"></i> নতুন সেল
            </a>
            
            <div class="dropdown">
                <button type="button" class="btn btn-light shadow-sm border border-secondary dropdown-toggle d-flex align-items-center gap-2 px-3 rounded-pill" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle fs-4 text-primary"></i> 
                    <span class="fw-bold text-dark">
                        <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'অ্যাডমিন ইউজার'; ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <a class="dropdown-item fw-bold text-secondary py-2" href="settings.php">
                            <i class="fas fa-user-cog"></i> প্রোফাইল সেটিং
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item fw-bold text-danger py-2" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> লগআউট
                        </a>
                        
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="main-content">