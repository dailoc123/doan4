<?php
session_start();
require 'db.php';

// Lấy thông tin tìm kiếm và danh mục từ URL
$query = strtolower($_GET['q'] ?? '');
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$selected_categories = isset($_GET['categories']) ? (array)$_GET['categories'] : [];
$sort = $_GET['sort'] ?? 'newest';
$in_stock = isset($_GET['in_stock']) ? true : false;
$on_sale = isset($_GET['on_sale']) ? true : false;

// Lấy danh sách danh mục
$sql_categories = "SELECT * FROM categories";
$result_categories = mysqli_query($conn, $sql_categories);
$categories = mysqli_fetch_all($result_categories, MYSQLI_ASSOC);

// Lấy thông tin category hiện tại
$current_category = null;
if ($category_id > 0) {
    $category_query = "SELECT * FROM categories WHERE id = ?";
    $stmt = mysqli_prepare($conn, $category_query);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_category = mysqli_fetch_assoc($result);
}

// Lọc sản phẩm theo danh mục và tìm kiếm
$sql_products = "SELECT p.*, c.name AS category_name, 
                i.quantity as stock_quantity, 
                i.min_stock,
                p.image as image_url
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN inventory i ON p.id = i.product_id";
$conditions = [];
$params = [];
$types = "";

// Xây dựng điều kiện WHERE
if (!empty($selected_categories)) {
    $category_placeholders = str_repeat('?,', count($selected_categories) - 1) . '?';
    $conditions[] = "category_id IN ($category_placeholders)";
    foreach ($selected_categories as $cat_id) {
        $params[] = intval($cat_id);
        $types .= "i";
    }
} elseif ($category_id > 0) {
    $conditions[] = "category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

if (!empty($query)) {
    $conditions[] = "LOWER(name) LIKE ?";
    $params[] = '%' . $query . '%';
    $types .= "s";
}

if ($min_price !== null) {
    $conditions[] = "price >= ?";
    $params[] = $min_price;
    $types .= "d";
}

if ($max_price !== null) {
    $conditions[] = "price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

// Add in_stock filter
if ($in_stock) {
    $conditions[] = "i.quantity > 0";
}

// Add on_sale filter
if ($on_sale) {
    $conditions[] = "discount_price > 0 AND discount_price < price";
}

// Add ORDER BY clause based on sort parameter
$order_by = "";
switch ($sort) {
    case 'price-asc':
        $order_by = " ORDER BY price ASC";
        break;
    case 'price-desc':
        $order_by = " ORDER BY price DESC";
        break;
    case 'name-asc':
        $order_by = " ORDER BY name ASC";
        break;
    case 'name-desc':
        $order_by = " ORDER BY name DESC";
        break;
    default:
        $order_by = " ORDER BY created_at DESC";
}

if (!empty($conditions)) {
    $sql_products .= " WHERE " . implode(" AND ", $conditions);
}

$sql_products .= $order_by;

// Thực hiện truy vấn
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql_products);
    if (!empty($types)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result_products = mysqli_stmt_get_result($stmt);
} else {
    $result_products = mysqli_query($conn, $sql_products);
}

$products = mysqli_fetch_all($result_products, MYSQLI_ASSOC);

// Lấy số lượng wishlist nếu user đã đăng nhập
$wishlist_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $wishlist_query = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $wishlist_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wishlist_data = mysqli_fetch_assoc($result);
    $wishlist_count = $wishlist_data['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<title>Products - Luxury Store</title>
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
</head>
<body class="animsition">
	
	<?php include 'header.php'; ?>

	<!-- Product -->
	<div class="bg0 m-t-120 p-b-140">
		<div class="container">
			<div class="flex-w flex-sb-m p-b-52">
				<div class="flex-w flex-l-m filter-tope-group m-tb-10">
					<button class="stext-106 cl6 hov1 bor3 trans-04 m-r-32 m-tb-5 <?php echo empty($selected_categories) && $category_id == 0 ? 'how-active1' : ''; ?>" 
							data-filter="*">
						All Products
					</button>

					<?php foreach ($categories as $cat): ?>
					<button class="stext-106 cl6 hov1 bor3 trans-04 m-r-32 m-tb-5 <?php echo $category_id == $cat['id'] ? 'how-active1' : ''; ?>" 
							data-filter=".<?php 
								$category_lower = strtolower($cat['name']);
								if (strpos($category_lower, 'nữ') !== false || strpos($category_lower, 'women') !== false) {
									echo 'women';
								} elseif (strpos($category_lower, 'nam') !== false || strpos($category_lower, 'men') !== false) {
									echo 'men';
								} elseif (strpos($category_lower, 'túi') !== false || strpos($category_lower, 'bag') !== false) {
									echo 'bag';
								} elseif (strpos($category_lower, 'giày') !== false || strpos($category_lower, 'shoes') !== false) {
									echo 'shoes';
								} elseif (strpos($category_lower, 'đồng hồ') !== false || strpos($category_lower, 'watches') !== false) {
									echo 'watches';
								} elseif (strpos($category_lower, 'trẻ em') !== false || strpos($category_lower, 'kids') !== false) {
									echo 'kids';
								} else {
									echo strtolower($cat['name']);
								}
							?>">
						<?php echo htmlspecialchars($cat['name']); ?>
					</button>
					<?php endforeach; ?>
				</div>

				<div class="flex-w flex-c-m m-tb-10">
					<div class="flex-c-m stext-106 cl6 size-104 bor4 pointer hov-btn3 trans-04 m-r-8 m-tb-4 js-show-filter">
						<i class="icon-filter cl2 m-r-6 fs-15 trans-04 zmdi zmdi-filter-list"></i>
						<i class="icon-close-filter cl2 m-r-6 fs-15 trans-04 zmdi zmdi-close dis-none"></i>
						 Filter
					</div>

					<div class="flex-c-m stext-106 cl6 size-105 bor4 pointer hov-btn3 trans-04 m-tb-4 js-show-search">
						<i class="icon-search cl2 m-r-6 fs-15 trans-04 zmdi zmdi-search"></i>
						<i class="icon-close-search cl2 m-r-6 fs-15 trans-04 zmdi zmdi-close dis-none"></i>
						Search
					</div>
				</div>
				
				<!-- Search product -->
				<div class="dis-none panel-search w-full p-t-10 p-b-15">
					<form method="GET" action="products.php" class="bor8 dis-flex p-l-15">
						<button type="submit" class="size-113 flex-c-m fs-16 cl2 hov-cl1 trans-04">
							<i class="zmdi zmdi-search"></i>
						</button>
						<input class="mtext-107 cl2 size-114 plh2 p-r-15" type="text" name="q" 
							   value="<?php echo htmlspecialchars($query); ?>" placeholder="Search">
						<?php if ($category_id > 0): ?>
						<input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
						<?php endif; ?>
					</form>
				</div>

				<!-- Filter -->
				<div class="dis-none panel-filter w-full p-t-10">
					<form method="GET" action="products.php" class="wrap-filter flex-w bg6 w-full p-lr-40 p-t-27 p-lr-15-sm">
						<div class="filter-col1 p-r-15 p-b-27">
							<div class="mtext-102 cl2 p-b-15">
								Sort By
							</div>
							<select name="sort" class="stext-106 cl6 size-116 bor13 p-lr-20 m-tb-5">
								<option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest</option>
								<option value="price-asc" <?php echo $sort == 'price-asc' ? 'selected' : ''; ?>>Price: Low to High</option>
								<option value="price-desc" <?php echo $sort == 'price-desc' ? 'selected' : ''; ?>>Price: High to Low</option>
								<option value="name-asc" <?php echo $sort == 'name-asc' ? 'selected' : ''; ?>>Name: A to Z</option>
								<option value="name-desc" <?php echo $sort == 'name-desc' ? 'selected' : ''; ?>>Name: Z to A</option>
							</select>
						</div>

						<div class="filter-col2 p-r-15 p-b-27">
							<div class="mtext-102 cl2 p-b-15">
								Price
							</div>
							<div class="flex-w">
								<input class="stext-106 cl6 size-116 bor13 p-lr-20 m-r-16 m-tb-5" type="number" 
									   name="min_price" placeholder="Min" value="<?php echo $min_price; ?>">
								<input class="stext-106 cl6 size-116 bor13 p-lr-20 m-tb-5" type="number" 
									   name="max_price" placeholder="Max" value="<?php echo $max_price; ?>">
							</div>
						</div>

						<div class="filter-col3 p-r-15 p-b-27">
							<div class="mtext-102 cl2 p-b-15">
								Categories
							</div>
							<?php foreach ($categories as $cat): ?>
							<div class="p-b-6">
								<label class="filter-link stext-106 trans-04">
									<input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" 
										   <?php echo in_array($cat['id'], $selected_categories) ? 'checked' : ''; ?>>
									<?php echo htmlspecialchars($cat['name']); ?>
								</label>
							</div>
							<?php endforeach; ?>
						</div>

						<div class="filter-col4 p-b-27">
							<div class="mtext-102 cl2 p-b-15">
								Options
							</div>
							<div class="p-b-6">
								<label class="filter-link stext-106 trans-04">
									<input type="checkbox" name="in_stock" <?php echo $in_stock ? 'checked' : ''; ?>>
									In Stock Only
								</label>
							</div>
							<div class="p-b-6">
								<label class="filter-link stext-106 trans-04">
									<input type="checkbox" name="on_sale" <?php echo $on_sale ? 'checked' : ''; ?>>
									On Sale
								</label>
							</div>
						</div>

						<div class="filter-col5 p-b-27">
							<button type="submit" class="flex-c-m stext-101 cl0 size-116 bg3 bor14 hov-btn3 p-lr-15 trans-04">
								Apply Filter
							</button>
						</div>
					</form>
				</div>
			</div>

			<div class="row isotope-grid">
				<?php if (empty($products)): ?>
					<div class="col-12 text-center p-t-50 p-b-50">
						<h4 class="mtext-109 cl2">No products found</h4>
						<p class="stext-113 cl6">Try adjusting your search or filter criteria</p>
					</div>
				<?php else: ?>
					<?php foreach ($products as $product): ?>
					<div class="col-sm-6 col-md-4 col-lg-3 p-b-35 isotope-item <?php 
						// Tạo class cho filtering dựa trên category_name
						$category_class = '';
						if (isset($product['category_name'])) {
							$category_lower = strtolower($product['category_name']);
							if (strpos($category_lower, 'nữ') !== false || strpos($category_lower, 'women') !== false) {
								$category_class = 'women';
							} elseif (strpos($category_lower, 'nam') !== false || strpos($category_lower, 'men') !== false) {
								$category_class = 'men';
							} elseif (strpos($category_lower, 'túi') !== false || strpos($category_lower, 'bag') !== false) {
								$category_class = 'bag';
							} elseif (strpos($category_lower, 'giày') !== false || strpos($category_lower, 'shoes') !== false) {
								$category_class = 'shoes';
							} elseif (strpos($category_lower, 'đồng hồ') !== false || strpos($category_lower, 'watches') !== false) {
								$category_class = 'watches';
							} elseif (strpos($category_lower, 'trẻ em') !== false || strpos($category_lower, 'kids') !== false) {
								$category_class = 'kids';
							}
						}
						echo $category_class;
					?>"
						<!-- Block2 -->
						<div class="block2">
							<div class="block2-pic hov-img0">
                                <img src="<?php echo !empty($product['image']) ? 'admin/' . htmlspecialchars($product['image']) : '../cozastore-master/images/product-01.jpg'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">

								<a href="product_detail.php?id=<?php echo $product['id']; ?>" class="block2-btn flex-c-m stext-103 cl2 size-102 bg0 bor2 hov-btn1 p-lr-15 trans-04">
									Quick View
								</a>
							</div>

							<div class="block2-txt flex-w flex-t p-t-14">
								<div class="block2-txt-child1 flex-col-l ">
									<a href="product_detail.php?id=<?php echo $product['id']; ?>" class="stext-104 cl4 hov-cl1 trans-04 js-name-b2 p-b-6">
										<?php echo htmlspecialchars($product['name']); ?>
									</a>

                                <span class="stext-105 cl3">
                                    <?php if ($product['discount_price'] > 0 && $product['discount_price'] < $product['price']): ?>
                                        <span class="text-decoration-line-through">$<?php echo number_format($product['price'], 2); ?></span>
                                        <span class="text-danger">$<?php echo number_format($product['discount_price'], 2); ?></span>
                                    <?php else: ?>
                                        $<?php echo number_format($product['price'], 2); ?>
                                    <?php endif; ?>
                                </span>

                                <div class="p-t-10">
                                    <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="flex-c-m stext-101 cl0 size-101 bg1 bor1 hov-btn1 p-lr-15 trans-04">
                                        Mua ngay
                                    </a>
                                </div>
                            </div>

								<div class="block2-txt-child2 flex-r p-t-3">
									<a href="#" class="btn-addwish-b2 dis-block pos-relative js-addwish-b2" 
									   data-product-id="<?php echo $product['id']; ?>">
										<img class="icon-heart1 dis-block trans-04" src="../cozastore-master/images/icons/icon-heart-01.png" alt="ICON">
										<img class="icon-heart2 dis-block trans-04 ab-t-l" src="../cozastore-master/images/icons/icon-heart-02.png" alt="ICON">
									</a>
								</div>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Load more -->
			<div class="flex-c-m flex-w w-full p-t-45">
				<a href="#" class="flex-c-m stext-101 cl5 size-103 bg2 bor1 hov-btn1 p-lr-15 trans-04">
					Load More
				</a>
			</div>
		</div>
	</div>

	<?php include 'footer.php'; ?>

	

<!--===============================================================================================-->	
	<script src="../cozastore-master/vendor/jquery/jquery-3.2.1.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/animsition/js/animsition.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/bootstrap/js/popper.js"></script>
	<script src="../cozastore-master/vendor/bootstrap/js/bootstrap.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/select2/select2.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/daterangepicker/moment.min.js"></script>
	<script src="../cozastore-master/vendor/daterangepicker/daterangepicker.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/slick/slick.min.js"></script>
	<script src="../cozastore-master/js/slick-custom.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/parallax100/parallax100.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/MagnificPopup/jquery.magnific-popup.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/isotope/isotope.pkgd.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/sweetalert/sweetalert.min.js"></script>
<!--===============================================================================================-->
<script src="../cozastore-master/vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<!--===============================================================================================-->
    <script src="../cozastore-master/js/main.js"></script>
<script src="js/cart.js"></script>
<script src="js/products.js"></script>

<script>
// Override toggle so panel thật sự hiển thị như userhome
// Gỡ handler mặc định của theme để tránh xung đột
$(function(){
    var $filterBtn = $('.js-show-filter');
    var $searchBtn = $('.js-show-search');
    var $filterPanel = $('.panel-filter');
    var $searchPanel = $('.panel-search');

    $filterBtn.off('click');
    $searchBtn.off('click');

    $filterBtn.on('click', function(){
        var $btn = $(this);
        var isOpen = $btn.hasClass('show-filter');

        if (!isOpen) {
            $btn.addClass('show-filter');
            $filterPanel.removeClass('dis-none').stop(true, true).slideDown(300);
            // Close search
            if ($searchBtn.hasClass('show-search')) {
                $searchBtn.removeClass('show-search');
                $searchPanel.stop(true, true).slideUp(300, function(){
                    $searchPanel.addClass('dis-none');
                });
                $searchBtn.find('.icon-search').removeClass('dis-none');
                $searchBtn.find('.icon-close-search').addClass('dis-none');
            }
            $btn.find('.icon-filter').addClass('dis-none');
            $btn.find('.icon-close-filter').removeClass('dis-none');
        } else {
            $btn.removeClass('show-filter');
            $filterPanel.stop(true, true).slideUp(300, function(){
                $filterPanel.addClass('dis-none');
            });
            $btn.find('.icon-filter').removeClass('dis-none');
            $btn.find('.icon-close-filter').addClass('dis-none');
        }
    });

    $searchBtn.on('click', function(){
        var $btn = $(this);
        var isOpen = $btn.hasClass('show-search');

        if (!isOpen) {
            $btn.addClass('show-search');
            $searchPanel.removeClass('dis-none').stop(true, true).slideDown(300);
            // Close filter
            if ($filterBtn.hasClass('show-filter')) {
                $filterBtn.removeClass('show-filter');
                $filterPanel.stop(true, true).slideUp(300, function(){
                    $filterPanel.addClass('dis-none');
                });
                $filterBtn.find('.icon-filter').removeClass('dis-none');
                $filterBtn.find('.icon-close-filter').addClass('dis-none');
            }
            $btn.find('.icon-search').addClass('dis-none');
            $btn.find('.icon-close-search').removeClass('dis-none');
        } else {
            $btn.removeClass('show-search');
            $searchPanel.stop(true, true).slideUp(300, function(){
                $searchPanel.addClass('dis-none');
            });
            $btn.find('.icon-search').removeClass('dis-none');
            $btn.find('.icon-close-search').addClass('dis-none');
        }
    });
});
</script>

<script>
// Initialize Isotope
$(document).ready(function() {
    var $grid = $('.isotope-grid').isotope({
        itemSelector: '.isotope-item',
        layoutMode: 'fitRows',
        percentPosition: true,
        animationEngine: 'best-available',
        transitionDuration: '0.6s',
        masonry: {
            columnWidth: '.isotope-item'
        }
    });

    // Filter functionality with smooth animations
    $('.filter-tope-group button').on('click', function() {
        var filterValue = $(this).attr('data-filter');
        
        // Remove active class from all buttons
        $('.filter-tope-group button').removeClass('how-active1');
        // Add active class to clicked button
        $(this).addClass('how-active1');
        
        // Apply filter with smooth animation
        $grid.isotope({ filter: filterValue });
    });

    // Toggle Filter panel with icon switch (force remove dis-none)
    $('.js-show-filter').on('click', function(){
        const $btn = $(this);
        const $panel = $('.panel-filter');
        const isOpen = $btn.hasClass('show-filter');

        if (!isOpen) {
            // Open filter
            $btn.addClass('show-filter');
            $panel.removeClass('dis-none').stop(true, true).slideDown(400);
            // Close search if open
            const $searchBtn = $('.js-show-search');
            const $searchPanel = $('.panel-search');
            if ($searchBtn.hasClass('show-search')) {
                $searchBtn.removeClass('show-search');
                $searchPanel.stop(true, true).slideUp(400, function(){
                    $searchPanel.addClass('dis-none');
                });
                $searchBtn.find('.icon-search').removeClass('dis-none');
                $searchBtn.find('.icon-close-search').addClass('dis-none');
            }
            // Switch filter icons
            $btn.find('.icon-filter').addClass('dis-none');
            $btn.find('.icon-close-filter').removeClass('dis-none');
        } else {
            // Close filter
            $btn.removeClass('show-filter');
            $panel.stop(true, true).slideUp(400, function(){
                $panel.addClass('dis-none');
            });
            // Switch filter icons
            $btn.find('.icon-filter').removeClass('dis-none');
            $btn.find('.icon-close-filter').addClass('dis-none');
        }
    });

    // Toggle Search panel with icon switch (force remove dis-none)
    $('.js-show-search').on('click', function(){
        const $btn = $(this);
        const $panel = $('.panel-search');
        const isOpen = $btn.hasClass('show-search');

        if (!isOpen) {
            // Open search
            $btn.addClass('show-search');
            $panel.removeClass('dis-none').stop(true, true).slideDown(400);
            // Close filter if open
            const $filterBtn = $('.js-show-filter');
            const $filterPanel = $('.panel-filter');
            if ($filterBtn.hasClass('show-filter')) {
                $filterBtn.removeClass('show-filter');
                $filterPanel.stop(true, true).slideUp(400, function(){
                    $filterPanel.addClass('dis-none');
                });
                $filterBtn.find('.icon-filter').removeClass('dis-none');
                $filterBtn.find('.icon-close-filter').addClass('dis-none');
            }
            // Switch search icons
            $btn.find('.icon-search').addClass('dis-none');
            $btn.find('.icon-close-search').removeClass('dis-none');
        } else {
            // Close search
            $btn.removeClass('show-search');
            $panel.stop(true, true).slideUp(400, function(){
                $panel.addClass('dis-none');
            });
            // Switch search icons
            $btn.find('.icon-search').removeClass('dis-none');
            $btn.find('.icon-close-search').addClass('dis-none');
        }
    });

    // Live search within grid using Isotope
    var $searchInput = $('input[name="q"]');
    if ($searchInput.length) {
        $searchInput.on('input', function(){
            var term = $(this).val().toLowerCase();
            $grid.isotope({
                filter: function(){
                    var name = $(this).find('.js-name-b2').text().toLowerCase();
                    return name.indexOf(term) > -1;
                }
            });
        });
    }

    // Wishlist được xử lý tập trung trong js/products.js (sử dụng Counters)
});
</script>

<?php include 'chatbox.php'; ?>
</body>
</html>
