<?php
// Khởi tạo session nếu chưa có
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Thêm kết nối database
require_once 'db.php';

// Kiểm tra quyền admin
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $admin_check = "SELECT role FROM users WHERE id = ?";
    $admin_stmt = $conn->prepare($admin_check);
    if ($admin_stmt) {
        $admin_stmt->bind_param("i", $_SESSION['user_id']);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        if ($admin_result && $admin_row = $admin_result->fetch_assoc()) {
            $is_admin = ($admin_row['role'] === 'admin');
        }
        $admin_stmt->close();
    }
}

// Lấy danh mục từ database
$categories_query = "SELECT * FROM categories WHERE parent_id = 0 OR parent_id IS NULL ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result) {
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
}

// Tạo mapping cho các danh mục theo slug/tên
$category_mapping = [];
foreach ($categories as $category) {
    $category_name = strtolower(trim($category['name']));
    if ($category_name === 'nam') {
        $category_mapping['men'] = $category;
    } elseif ($category_name === 'nữ') {
        $category_mapping['women'] = $category;
    } elseif ($category_name === 'trẻ em') {
        $category_mapping['kids'] = $category;
    }
    $category_mapping[$category['id']] = $category;
}

// Lấy số lượng giỏ hàng và wishlist
$cart_count = 0;
$wishlist_count = 0;
if (isset($_SESSION['user_id'])) {
    // Đếm sản phẩm trong giỏ hàng
    $cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $cart_stmt = $conn->prepare($cart_query);
    if ($cart_stmt) {
        $cart_stmt->bind_param("i", $_SESSION['user_id']);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        if ($cart_row = $cart_result->fetch_assoc()) {
            $cart_count = $cart_row['total'] ?? 0;
        }
        $cart_stmt->close();
    }
    
    // Đếm sản phẩm trong wishlist
    $wishlist_query = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
    $wishlist_stmt = $conn->prepare($wishlist_query);
    if ($wishlist_stmt) {
        $wishlist_stmt->bind_param("i", $_SESSION['user_id']);
        $wishlist_stmt->execute();
        $wishlist_result = $wishlist_stmt->get_result();
        if ($wishlist_row = $wishlist_result->fetch_assoc()) {
            $wishlist_count = $wishlist_row['count'] ?? 0;
        }
        $wishlist_stmt->close();
    }
} else {
    // Khách: dùng session guest_wishlist để hiển thị số yêu thích ban đầu
    if (isset($_SESSION['guest_wishlist']) && is_array($_SESSION['guest_wishlist'])) {
        $wishlist_count = count($_SESSION['guest_wishlist']);
    } else {
        $wishlist_count = 0;
    }
}

// Cập nhật session với số lượng mới
$_SESSION['cart_count'] = $cart_count;
$_SESSION['wishlist_count'] = $wishlist_count;

// Chuẩn bị biến thông báo merge wishlist sau đăng nhập
$__wishlist_merged = isset($_SESSION['wishlist_merged']);
$__wishlist_merged_count = isset($_SESSION['wishlist_merged_count']) ? (int)$_SESSION['wishlist_merged_count'] : 0;
if ($__wishlist_merged) {
    unset($_SESSION['wishlist_merged'], $_SESSION['wishlist_merged_count']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Luxury Store - Trang chủ</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<!--===============================================================================================-->	
    <link rel="icon" type="image/png" href="../cozastore-master/images/icons/favicon.png"/>
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/bootstrap/css/bootstrap.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/fonts/font-awesome-4.7.0/css/font-awesome.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/fonts/iconic/css/material-design-iconic-font.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/fonts/linearicons-v1.0.0/icon-font.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/animate/animate.css">
<!--===============================================================================================-->	
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/css-hamburgers/hamburgers.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/animsition/css/animsition.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/select2/select2.min.css">
<!--===============================================================================================-->	
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/daterangepicker/daterangepicker.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/slick/slick.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/MagnificPopup/magnific-popup.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/vendor/perfect-scrollbar/perfect-scrollbar.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="../cozastore-master/css/util.css">
    <link rel="stylesheet" type="text/css" href="../cozastore-master/css/main.css">
<!--===============================================================================================-->
    <!-- jQuery Library -->
    <script src="../cozastore-master/vendor/jquery/jquery-3.2.1.min.js"></script>
<!--===============================================================================================-->
    <style>
        /* Custom styles for discount pricing */
        .product-price {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .original-price {
            text-decoration: line-through;
            color: #9ca3af;
            font-size: 14px;
        }
        
        .discount-price {
            color: #ef4444;
            font-weight: bold;
            font-size: 16px;
        }
        
        .discount-badge {
            background-color: #ef4444;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .regular-price {
            color: #374151;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .discount-badge-image {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #ef4444;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            z-index: 2;
        }
        
        /* Quick View Modal Responsive Styles */
        .wrap-modal1 {
            z-index: 9999;
        }
        
        .modal1 {
            max-width: 95vw;
            max-height: 95vh;
            overflow-y: auto;
        }
        
        .container-modal1 {
            padding: 20px;
        }
        
        /* Mobile Styles */
        @media (max-width: 767px) {
            .modal1 {
                width: 95vw !important;
                max-width: none;
                margin: 2.5vh auto;
                max-height: 95vh;
            }
            
            .container-modal1 {
                padding: 15px;
                flex-direction: column;
            }
            
            .wrap-slick3 {
                width: 100% !important;
                margin-bottom: 20px;
            }
            
            .wrap-slick3-dots {
                margin-top: 15px;
            }
            
            .wrap-pic-w {
                width: 100% !important;
                height: 300px !important;
                margin-bottom: 15px;
            }
            
            .pic-w img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .wrap-modal1-right {
                width: 100% !important;
                padding-left: 0 !important;
            }
            
            .product-detail h4 {
                font-size: 20px;
                margin-bottom: 15px;
            }
            
            .product-detail .mtext-106 {
                font-size: 18px;
                margin-bottom: 15px;
            }
            
            .product-detail .stext-102 {
                font-size: 14px;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            
            .wrap-dropdown-content {
                margin-bottom: 15px;
            }
            
            .dropdown-content {
                min-height: 45px;
                font-size: 14px;
            }
            
            .btn-addcart-product-detail {
                width: 100%;
                height: 50px;
                font-size: 16px;
                margin-top: 20px;
            }
            
            .wrap-btn {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn-addwish-b2 {
                width: 100%;
                height: 45px;
                justify-content: center;
            }
            
            .social-item {
                margin-right: 15px;
            }
            
            .stock-info {
                font-size: 14px;
                margin-bottom: 15px;
            }
        }
        
        /* Tablet Styles */
        @media (min-width: 768px) and (max-width: 1024px) {
            .modal1 {
                width: 90vw;
                max-width: 800px;
                max-height: 90vh;
            }
            
            .container-modal1 {
                padding: 25px;
            }
            
            .wrap-slick3 {
                width: 45% !important;
            }
            
            .wrap-modal1-right {
                width: 50% !important;
                padding-left: 25px !important;
            }
            
            .wrap-pic-w {
                height: 350px !important;
            }
        }
        
        /* Desktop Improvements */
        @media (min-width: 1025px) {
            .modal1 {
                max-width: 1000px;
                max-height: 90vh;
            }
            
            .wrap-pic-w {
                height: 400px !important;
            }
        }
        
        /* Loading State */
        .quick-view-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            font-size: 16px;
            color: #666;
        }
        
        .quick-view-loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #333;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Stock Info Styles */
        .stock-info {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .stock-info.in-stock {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .stock-info.low-stock {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .stock-info.out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Smooth Transitions */
        .modal1, .container-modal1, .wrap-modal1-right {
            transition: all 0.3s ease;
        }
        
        /* Accessibility Improvements */
        .btn-addcart-product-detail:focus,
        .btn-addwish-b2:focus,
        .dropdown-content:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }
        
        /* Print Styles */
        @media print {
            .wrap-modal1 {
                display: none !important;
            }
        }
        /* Hide Back to Top globally across user pages */
        .btn-back-to-top,
        .back-to-top,
        #myBtn,
        #back-to-top {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
    </style>
</head>
<body class="animsition">
    
    <!-- Header -->
    <header>
        <!-- Header desktop -->
        <div class="container-menu-desktop">
            <!-- Topbar -->
            <div class="top-bar">
                <div class="content-topbar flex-sb-m h-full container">
                    <div class="left-top-bar">
                        Free shipping for standard order over $100
                    </div>

                    <div class="right-top-bar flex-w h-full">
                        <a href="#" class="flex-c-m trans-04 p-lr-25">
                            Help & FAQs
                        </a>

                        <?php if (isset($_SESSION['user_name'])): ?>
                            <a href="profile.php" class="flex-c-m trans-04 p-lr-25">
                                <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </a>
                            <a href="auth/logout.php" class="flex-c-m trans-04 p-lr-25">
                                Logout
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="flex-c-m trans-04 p-lr-25">
                                My Account
                            </a>
                        <?php endif; ?>

                        <a href="#" class="flex-c-m trans-04 p-lr-25">
                            EN
                        </a>

                        <a href="#" class="flex-c-m trans-04 p-lr-25">
                            USD
                        </a>
                    </div>
                </div>
            </div>

            <div class="wrap-menu-desktop">
                <nav class="limiter-menu-desktop container">
                    
                    <!-- Logo desktop -->		
                    <a href="userhome.php" class="logo">
                        <img src="../cozastore-master/images/icons/logo-01.png" alt="IMG-LOGO">
                    </a>

                    <!-- Menu desktop -->
                    <div class="menu-desktop">
                        <ul class="main-menu">
                            <li class="active-menu">
                                <a href="userhome.php">Home</a>
                            </li>

                            <li>
                                <a href="products.php">Shop</a>
                            </li>

                            <li>
                                <a href="about.php">About</a>
                            </li>

                            <li>
                                <a href="contact.php">Contact</a>
                            </li>
                        </ul>
                    </div>	

                    <!-- Icon header -->
                    <div class="wrap-icon-header flex-w flex-r-m">
                        <div class="icon-header-item cl2 hov-cl1 trans-04 p-l-22 p-r-11 js-show-modal-search">
                            <i class="zmdi zmdi-search"></i>
                        </div>

                        <div class="icon-header-item cl2 hov-cl1 trans-04 p-l-22 p-r-11 icon-header-noti js-show-cart" data-notify="<?= $cart_count ?>">
                            <i class="zmdi zmdi-shopping-cart"></i>
                        </div>

                        <a href="wishlist.php" class="dis-block icon-header-item cl2 hov-cl1 trans-04 p-l-22 p-r-11 icon-header-noti" data-notify="<?= $wishlist_count ?>">
                            <i class="zmdi zmdi-favorite-outline"></i>
                        </a>
                    </div>
                </nav>
            </div>	
        </div>

        <!-- Header Mobile -->
        <div class="wrap-header-mobile">
            <!-- Logo moblie -->		
            <div class="logo-mobile">
                <a href="userhome.php"><img src="../cozastore-master/images/icons/logo-01.png" alt="IMG-LOGO"></a>
            </div>

            <!-- Icon header -->
            <div class="wrap-icon-header flex-w flex-r-m m-r-15">
                <div class="icon-header-item cl2 hov-cl1 trans-04 p-r-11 js-show-modal-search">
                    <i class="zmdi zmdi-search"></i>
                </div>

                <div class="icon-header-item cl2 hov-cl1 trans-04 p-r-11 p-l-10 icon-header-noti js-show-cart" data-notify="<?= $cart_count ?>">
                    <i class="zmdi zmdi-shopping-cart"></i>
                </div>

                <a href="wishlist.php" class="dis-block icon-header-item cl2 hov-cl1 trans-04 p-r-11 p-l-10 icon-header-noti" data-notify="<?= $wishlist_count ?>">
                    <i class="zmdi zmdi-favorite-outline"></i>
                </a>
            </div>

            <!-- Button show menu -->
            <div class="btn-show-menu-mobile hamburger hamburger--squeeze">
                <span class="hamburger-box">
                    <span class="hamburger-inner"></span>
                </span>
            </div>
        </div>

        <!-- Menu Mobile -->
        <div class="menu-mobile">
            <ul class="topbar-mobile">
                <li>
                    <div class="left-top-bar">
                        Free shipping for standard order over $100
                    </div>
                </li>

                <li>
                    <div class="right-top-bar flex-w h-full">
                        <a href="#" class="flex-c-m p-lr-10 trans-04">
                            Help & FAQs
                        </a>

                        <?php if (isset($_SESSION['user_name'])): ?>
                            <a href="profile.php" class="flex-c-m p-lr-10 trans-04">
                                <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </a>
                            <a href="auth/logout.php" class="flex-c-m p-lr-10 trans-04">
                                Logout
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="flex-c-m p-lr-10 trans-04">
                                My Account
                            </a>
                        <?php endif; ?>

                        <a href="#" class="flex-c-m p-lr-10 trans-04">
                            EN
                        </a>

                        <a href="#" class="flex-c-m p-lr-10 trans-04">
                            USD
                        </a>
                    </div>
                </li>
            </ul>

            <ul class="main-menu-m">
                <li>
                    <a href="userhome.php">Home</a>
                </li>

                <li>
                    <a href="products.php">Shop</a>
                </li>

                <li>
                    <a href="about.php">About</a>
                </li>

                <li>
                    <a href="contact.php">Contact</a>
                </li>
            </ul>
        </div>

        <!-- Modal Search -->
        <div class="modal-search-header flex-c-m trans-04 js-hide-modal-search">
            <div class="container-search-header">
                <button class="flex-c-m btn-hide-modal-search trans-04 js-hide-modal-search">
                    <img src="../cozastore-master/images/icons/icon-close2.png" alt="CLOSE">
                </button>

                <form class="wrap-search-header flex-w p-l-15" action="search.php" method="GET">
                    <button class="flex-c-m trans-04" type="submit">
                        <i class="zmdi zmdi-search"></i>
                    </button>
                    <input class="plh3" type="text" name="search" placeholder="Search...">
                </form>
            </div>
        </div>
    </header>

    <!-- Cart -->
    <div class="wrap-header-cart js-panel-cart">
        <div class="s-full js-hide-cart"></div>

        <div class="header-cart flex-col-l p-l-65 p-r-25">
            <div class="header-cart-title flex-w flex-sb-m p-b-8">
                <span class="mtext-103 cl2">
                    Your Cart
                </span>

                <div class="fs-35 lh-10 cl2 p-lr-5 pointer hov-cl1 trans-04 js-hide-cart">
                    <i class="zmdi zmdi-close"></i>
                </div>
            </div>
            
            <div class="header-cart-content flex-w js-pscroll">
                <ul class="header-cart-wrapitem w-full">
                    <!-- Cart items will be loaded here via AJAX -->
                </ul>
                
                <div class="w-full">
                    <div class="header-cart-total w-full p-tb-40">
                        Total: $0.00
                    </div>

                    <div class="header-cart-buttons flex-w w-full">
                        <a href="cart.php" class="flex-c-m stext-101 cl0 size-107 bg3 bor2 hov-btn3 p-lr-15 trans-04 m-r-8 m-b-10">
                            View Cart
                        </a>

                        <a href="checkout.php" class="flex-c-m stext-101 cl0 size-107 bg3 bor2 hov-btn3 p-lr-15 trans-04 m-b-10">
                            Check Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart JavaScript Functions -->
    <script>
        // Cờ đăng nhập cho JS
        const IS_LOGGED_IN = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        // Thông báo merge wishlist sau đăng nhập
        const WISHLIST_MERGED = <?php echo $__wishlist_merged ? 'true' : 'false'; ?>;
        const WISHLIST_MERGED_COUNT = <?php echo (int)$__wishlist_merged_count; ?>;
        function updateWishlistCount() {
            fetch('get_wishlist_count.php')
            .then(response => response.json())
            .then(data => {
                const countElement = document.querySelector('.icon-header-noti');
                if (countElement && data.count !== undefined) {
                    countElement.setAttribute('data-notify', data.count);
                }
            })
            .catch(error => console.error('Error updating wishlist count:', error));
        }

        // Add to cart functionality
        function addToCart(productId, quantity = 1) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=' + quantity
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    swal("Product", "is added to cart !", "success");
                    // Update cart count if needed
                    updateCartCount();
                    loadCartItems();
                } else {
                    swal("Error", data.message || 'Có lỗi xảy ra!', "error");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                swal("Error", 'Có lỗi xảy ra!', "error");
            });
        }

        function updateCartCount() {
            fetch('get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const cartCountElements = document.querySelectorAll('.js-show-cart');
                cartCountElements.forEach(element => {
                    if (data.count !== undefined) {
                        element.setAttribute('data-notify', data.count);
                    }
                });
            })
            .catch(error => console.error('Error updating cart count:', error));
        }

        // Load cart items
        function loadCartItems() {
            fetch('get_cart_items.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartWrapper = document.querySelector('.header-cart-wrapitem');
                    const cartTotal = document.querySelector('.header-cart-total');
                    
                    if (cartWrapper) {
                        cartWrapper.innerHTML = '';
                        let total = 0;
                        
                        data.cart_items.forEach(item => {
                            const cartItem = `
                                <li class="header-cart-item flex-w flex-t m-b-12">
                                    <div class="header-cart-item-img">
                                        <img src="${item.image}" alt="IMG">
                                    </div>
                                    <div class="header-cart-item-txt p-t-8">
                                        <a href="product_detail.php?id=${item.product_id}" class="header-cart-item-name m-b-18 hov-cl1 trans-04">
                                            ${item.product_name}
                                        </a>
                                        <span class="header-cart-item-info">
                                            ${item.quantity} x $${item.price}
                                        </span>
                                    </div>
                                </li>
                            `;
                            cartWrapper.innerHTML += cartItem;
                            total += parseFloat(item.subtotal);
                        });
                        
                        if (cartTotal) {
                            cartTotal.innerHTML = `Total: $${total.toFixed(2)}`;
                        }
                        
                        // Update cart count in header
                        const cartIcons = document.querySelectorAll('.js-show-cart');
                        cartIcons.forEach(icon => {
                            icon.setAttribute('data-notify', data.count);
                        });
                    }
                } else {
                    console.log('Cart is empty or user not logged in');
                }
            })
            .catch(error => console.error('Error loading cart items:', error));
        }

        // Cart sidebar functionality
        $(document).ready(function() {
            // Cart sidebar show/hide
            $('.js-show-cart').on('click', function(){
                $('.js-panel-cart').addClass('show-header-cart');
                loadCartItems(); // Reload cart items when opening
            });

            $('.js-hide-cart').on('click', function(){
                $('.js-panel-cart').removeClass('show-header-cart');
            });
            
            // Load cart items on page load
            loadCartItems();
            // Prefer centralized counters if available
            if (window.Counters && typeof window.Counters.initCounts === 'function') {
                window.Counters.initCounts();
            } else {
                updateCartCount();
                updateWishlistCount();
            }

            // Hiển thị thông báo sau đăng nhập nếu có merge wishlist
            if (IS_LOGGED_IN && WISHLIST_MERGED) {
                try {
                    const count = WISHLIST_MERGED_COUNT || 0;
                    const msg = count > 0 
                        ? `Đã nhập ${count} sản phẩm yêu thích vào tài khoản của bạn.`
                        : `Danh sách yêu thích tạm thời đã được đồng bộ vào tài khoản của bạn.`;
                    if (typeof swal === 'function') {
                        swal('Danh sách yêu thích', msg, 'success');
                    }
                } catch (e) { /* noop */ }
            }

            // Chèn banner thông báo wishlist tạm thời cho khách trên trang wishlist
            if (!IS_LOGGED_IN) {
                const isWishlistPage = /\/backend\/wishlist\.php$/.test(window.location.pathname) || /wishlist\.php$/.test(window.location.pathname);
                if (isWishlistPage) {
                    const banner = document.createElement('div');
                    banner.style.background = '#fff7ed';
                    banner.style.border = '1px solid #fdba74';
                    banner.style.color = '#9a3412';
                    banner.style.padding = '12px 16px';
                    banner.style.margin = '12px auto';
                    banner.style.borderRadius = '8px';
                    banner.style.maxWidth = '1100px';
                    banner.style.boxShadow = '0 2px 6px rgba(0,0,0,0.05)';
                    banner.innerHTML = '<strong>Wishlist tạm thời</strong>: Danh sách yêu thích của bạn hiện đang được lưu tạm thời. Hãy đăng nhập để đồng bộ vào tài khoản.';
                    const bodyEl = document.body;
                    bodyEl.insertBefore(banner, bodyEl.firstChild);
                }
            }
        });
    </script>
