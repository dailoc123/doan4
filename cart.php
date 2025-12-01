<?php
session_start();
require_once 'db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Xử lý AJAX request để thêm sản phẩm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $product_id = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;
    $color_id = $_POST['color_id'] ?? null;
    $size_id = $_POST['size_id'] ?? null;

    if (!$product_id || !$color_id || !$size_id) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn đầy đủ thông tin sản phẩm']);
        exit();
    }

    // Kiểm tra sản phẩm đã có trong giỏ hàng chưa
    $check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND color_id = ? AND size_id = ?");
    $check_stmt->bind_param("iiii", $user_id, $product_id, $color_id, $size_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing_item = $result->fetch_assoc();

    if ($existing_item) {
        // Cập nhật số lượng
        $new_quantity = $existing_item['quantity'] + $quantity;
        $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_quantity, $existing_item['id']);
        $update_stmt->execute();
    } else {
        // Thêm mới vào giỏ hàng
        $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, color_id, size_id) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("iiiii", $user_id, $product_id, $quantity, $color_id, $size_id);
        $insert_stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Đã thêm sản phẩm vào giỏ hàng']);
    exit();
}

// Xử lý cập nhật số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_quantity') {
    $cart_id = $_POST['cart_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;

    if ($cart_id && $quantity > 0) {
        $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $update_stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        $update_stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Xử lý xóa sản phẩm khỏi giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_item') {
    $cart_id = $_POST['cart_id'] ?? null;

    if ($cart_id) {
        $delete_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $cart_id, $user_id);
        $delete_stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Lấy danh sách sản phẩm trong giỏ hàng
$cart_query = "
    SELECT c.id as cart_id, c.quantity, c.product_id, c.color_id, c.size_id,
           p.name, p.price, p.discount_price, p.image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
";
$cart_stmt = $conn->prepare($cart_query);

if ($cart_stmt === false) {
    die('Lỗi prepare statement: ' . $conn->error);
}

$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$result = $cart_stmt->get_result();
$cart_items = [];
while ($row = $result->fetch_assoc()) {
    // Lấy thông tin màu sắc và kích thước riêng biệt
    if ($row['color_id']) {
        $color_stmt = $conn->prepare("SELECT name FROM colors WHERE id = ?");
        $color_stmt->bind_param("i", $row['color_id']);
        $color_stmt->execute();
        $color_result = $color_stmt->get_result();
        $color_data = $color_result->fetch_assoc();
        $row['color_name'] = $color_data['name'] ?? 'N/A';
        $color_stmt->close();
    } else {
        $row['color_name'] = 'N/A';
    }
    
    if ($row['size_id']) {
        $size_stmt = $conn->prepare("SELECT name FROM sizes WHERE id = ?");
        $size_stmt->bind_param("i", $row['size_id']);
        $size_stmt->execute();
        $size_result = $size_stmt->get_result();
        $size_data = $size_result->fetch_assoc();
        $row['size_name'] = $size_data['name'] ?? 'N/A';
        $size_stmt->close();
    } else {
        $row['size_name'] = 'N/A';
    }
    
    $cart_items[] = $row;
}
$cart_stmt->close();

// Tính tổng tiền
$total = 0;
foreach ($cart_items as $item) {
    // Sử dụng discount_price nếu có, nếu không thì dùng price gốc
    $effective_price = $item['discount_price'] > 0 ? $item['discount_price'] : $item['price'];
    $total += $effective_price * $item['quantity'];
}

// Bỏ phí vận chuyển và thuế để đồng bộ với checkout
$grand_total = $total; // Chỉ lấy tổng tiền sản phẩm
$shipping = 25000; // Phí vận chuyển cố định
$tax = $total * 0.1; // Thuế 10%
$grand_total = $total + $shipping + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Shoping Cart</title>
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
	<!-- Include header.php -->
<?php include 'header.php'; ?>

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
					<?php foreach ($cart_items as $item): ?>
					<li class="header-cart-item flex-w flex-t m-b-12">
						<div class="header-cart-item-img">
							<img src="admin/<?= htmlspecialchars($item['image']) ?>" alt="IMG">
						</div>

						<div class="header-cart-item-txt p-t-8">
							<a href="#" class="header-cart-item-name m-b-18 hov-cl1 trans-04">
								<?= htmlspecialchars($item['name']) ?>
							</a>

							<span class="header-cart-item-info">
								<?= $item['quantity'] ?> x <?= number_format($item['discount_price'] > 0 ? $item['discount_price'] : $item['price']) ?>đ
							</span>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
				
				<div class="w-full">
					<div class="header-cart-total w-full p-tb-40">
						Total: <?= number_format($total) ?>đ
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

	<!-- breadcrumb -->
	<div class="container">
		<div class="bread-crumb flex-w p-l-25 p-r-15 p-t-30 p-lr-0-lg">
			<a href="userhome.php" class="stext-109 cl8 hov-cl1 trans-04">
				Home
				<i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
			</a>

			<span class="stext-109 cl4">
				Shoping Cart
			</span>
		</div>
	</div>
		

	<!-- Shoping Cart -->
	<form class="bg0 p-t-75 p-b-85">
		<div class="container">
			<div class="row">
				<div class="col-lg-10 col-xl-7 m-lr-auto m-b-50">
					<div class="m-l-25 m-r--38 m-lr-0-xl">
						<div class="wrap-table-shopping-cart">
							<table class="table-shopping-cart">
								<tr class="table_head">
									<th class="column-1">Product</th>
									<th class="column-2"></th>
									<th class="column-3">Price</th>
									<th class="column-4">Quantity</th>
									<th class="column-5">Total</th>
								</tr>

								<?php if (empty($cart_items)): ?>
								<tr class="table_row">
									<td colspan="5" class="text-center p-t-50 p-b-50">
										<div class="stext-110 cl6">
											Giỏ hàng của bạn đang trống
										</div>
										<a href="products.php" class="flex-c-m stext-101 cl0 size-116 bg3 bor14 hov-btn3 p-lr-15 trans-04 pointer m-t-20">
											Tiếp tục mua sắm
										</a>
									</td>
								</tr>
								<?php else: ?>
								<?php foreach ($cart_items as $item): ?>
								<tr class="table_row" data-cart-id="<?= $item['cart_id'] ?>">
									<td class="column-1">
										<div class="how-itemcart1">
											<img src="admin/<?= htmlspecialchars($item['image']) ?>" alt="IMG">
										</div>
									</td>
									<td class="column-2">
										<div class="stext-104 cl4 size-116 p-t-8">
											<?= htmlspecialchars($item['name']) ?>
										</div>
										<div class="stext-109 cl6 p-t-5">
											Màu: <?= htmlspecialchars($item['color_name']) ?> | Size: <?= htmlspecialchars($item['size_name']) ?>
										</div>
									</td>
									<td class="column-3">
										<?php if ($item['discount_price'] > 0): ?>
											<span class="stext-105 cl3 line-through"><?= number_format($item['price']) ?>đ</span>
											<span class="stext-105 cl3 p-l-10"><?= number_format($item['discount_price']) ?>đ</span>
										<?php else: ?>
											<span class="stext-105 cl3"><?= number_format($item['price']) ?>đ</span>
										<?php endif; ?>
									</td>
									<td class="column-4">
										<div class="wrap-num-product flex-w m-l-auto m-r-0">
											<div class="btn-num-product-down cl8 hov-btn3 trans-04 flex-c-m decrease-btn" data-cart-id="<?= $item['cart_id'] ?>">
												<i class="fs-16 zmdi zmdi-minus"></i>
											</div>

											<input class="mtext-104 cl3 txt-center num-product quantity-input" type="number" name="num-product1" value="<?= $item['quantity'] ?>" min="1" max="10" data-cart-id="<?= $item['cart_id'] ?>">

											<div class="btn-num-product-up cl8 hov-btn3 trans-04 flex-c-m increase-btn" data-cart-id="<?= $item['cart_id'] ?>">
												<i class="fs-16 zmdi zmdi-plus"></i>
											</div>
										</div>
									</td>
									<td class="column-5">
										<?php 
										$effective_price = $item['discount_price'] > 0 ? $item['discount_price'] : $item['price'];
										$item_total = $effective_price * $item['quantity'];
										?>
										<span class="stext-105 cl3"><?= number_format($item_total) ?>đ</span>
										<button type="button" class="remove-btn cl8 hov-btn3 trans-04 p-l-10" data-cart-id="<?= $item['cart_id'] ?>">
											<i class="zmdi zmdi-close"></i>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
								<?php endif; ?>
							</table>
						</div>

						<div class="flex-w flex-sb-m bor15 p-t-18 p-b-15 p-lr-40 p-lr-15-sm">
							<div class="flex-w flex-m m-r-20 m-tb-5">
								<input class="stext-104 cl2 plh4 size-117 bor13 p-lr-20 m-r-10 m-tb-5" type="text" name="coupon" placeholder="Coupon Code">

								<div class="flex-c-m stext-101 cl2 size-118 bg8 bor13 hov-btn3 p-lr-15 trans-04 pointer m-tb-5">
									Apply coupon
								</div>
							</div>

							<div class="flex-c-m stext-101 cl2 size-119 bg8 bor13 hov-btn3 p-lr-15 trans-04 pointer m-tb-10">
								Update Cart
							</div>
						</div>
					</div>
				</div>

				<div class="col-sm-10 col-lg-7 col-xl-5 m-lr-auto m-b-50">
					<div class="bor10 p-lr-40 p-t-30 p-b-40 m-l-63 m-r-40 m-lr-0-xl p-lr-15-sm">
						<h4 class="mtext-109 cl2 p-b-30">
							Cart Totals
						</h4>

						<div class="flex-w flex-t bor12 p-b-13">
							<div class="size-208">
								<span class="stext-110 cl2">
									Subtotal:
								</span>
							</div>

							<div class="size-209">
								<span class="mtext-110 cl2 subtotal">
									<?= number_format($total) ?>đ
								</span>
							</div>
						</div>

						<div class="flex-w flex-t bor12 p-t-15 p-b-30">
							<div class="size-208 w-full-ssm">
								<span class="stext-110 cl2">
									Shipping:
								</span>
							</div>

							<div class="size-209 p-r-18 p-r-0-sm w-full-ssm">
								<p class="stext-111 cl6 p-t-2">
									There are no shipping methods available. Please double check your address, or contact us if you need any help.
								</p>
								
								<div class="p-t-15">
									<span class="stext-112 cl8">
										Calculate Shipping
									</span>

									<div class="rs1-select2 rs2-select2 bor8 bg0 m-b-12 m-t-9">
										<select class="js-select2" name="time">
											<option>Select a country...</option>
											<option>USA</option>
											<option>UK</option>
										</select>
										<div class="dropDownSelect2"></div>
									</div>

									<div class="bor8 bg0 m-b-12">
										<input class="stext-111 cl8 plh3 size-111 p-lr-15" type="text" name="state" placeholder="State /  country">
									</div>

									<div class="bor8 bg0 m-b-22">
										<input class="stext-111 cl8 plh3 size-111 p-lr-15" type="text" name="postcode" placeholder="Postcode / Zip">
									</div>

									<div class="flex-w">
										<div class="flex-c-m stext-101 cl2 size-115 bg8 bor13 hov-btn3 p-lr-15 trans-04 pointer">
											Update Totals
										</div>
									</div>
										
								</div>
							</div>
						</div>

						<div class="flex-w flex-t p-t-27 p-b-33">
							<div class="size-208">
								<span class="mtext-101 cl2">
									Total:
								</span>
							</div>

							<div class="size-209 p-t-1">
								<span class="mtext-110 cl2 total">
									<?= number_format($total) ?>đ
								</span>
							</div>
						</div>

						<a href="checkout.php" class="flex-c-m stext-101 cl0 size-116 bg3 bor14 hov-btn3 p-lr-15 trans-04 pointer">
							Proceed to Checkout
						</a>
					</div>
				</div>
			</div>
		</div>
	</form>
		
	
	<!-- Footer -->
	<footer class="bg3 p-t-75 p-b-32">
		<div class="container">
			<div class="row">
				<div class="col-sm-6 col-lg-3 p-b-50">
					<h4 class="stext-301 cl0 p-b-30">
						Categories
					</h4>

					<ul>
						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								Women
							</a>
						</li>

						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								Men
							</a>
						</li>

						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								Shoes
							</a>
						</li>

						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								Watches
							</a>
						</li>
					</ul>
				</div>

				<div class="col-sm-6 col-lg-3 p-b-50">
					<h4 class="stext-301 cl0 p-b-30">
						Help
					</h4>

					<ul>
						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								Track Order
							</a>
						</li>

						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								Returns 
							</a>
						</li>

						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								Shipping
							</a>
						</li>

						<li class="p-b-10">
							<a href="#" class="stext-107 cl7 hov-cl1 trans-04">
								FAQs
							</a>
						</li>
					</ul>
				</div>

				<div class="col-sm-6 col-lg-3 p-b-50">
					<h4 class="stext-301 cl0 p-b-30">
						GET IN TOUCH
					</h4>

					<p class="stext-107 cl7 size-201">
						Any questions? Let us know in store at 8th floor, 379 Hudson St, New York, NY 10018 or call us on (+1) 96 716 6879
					</p>

					<div class="p-t-27">
						<a href="#" class="fs-18 cl7 hov-cl1 trans-04 m-r-16">
							<i class="fa fa-facebook"></i>
						</a>

						<a href="#" class="fs-18 cl7 hov-cl1 trans-04 m-r-16">
							<i class="fa fa-instagram"></i>
						</a>

						<a href="#" class="fs-18 cl7 hov-cl1 trans-04 m-r-16">
							<i class="fa fa-pinterest-p"></i>
						</a>
					</div>
				</div>

				<div class="col-sm-6 col-lg-3 p-b-50">
					<h4 class="stext-301 cl0 p-b-30">
						Newsletter
					</h4>

					<form>
						<div class="wrap-input1 w-full p-b-4">
							<input class="input1 bg-none plh1 stext-107 cl7" type="text" name="email" placeholder="email@example.com">
							<div class="focus-input1 trans-04"></div>
						</div>

						<div class="p-t-18">
							<button class="flex-c-m stext-101 cl0 size-103 bg1 bor1 hov-btn2 p-lr-15 trans-04">
								Subscribe
							</button>
						</div>
					</form>
				</div>
			</div>

			<div class="p-t-40">
				<div class="flex-c-m flex-w p-b-18">
					<a href="#" class="m-all-1">
						<img src="../cozastore-master/images/icons/icon-pay-01.png" alt="ICON-PAY">
					</a>

					<a href="#" class="m-all-1">
						<img src="../cozastore-master/images/icons/icon-pay-02.png" alt="ICON-PAY">
					</a>

					<a href="#" class="m-all-1">
						<img src="../cozastore-master/images/icons/icon-pay-03.png" alt="ICON-PAY">
					</a>

					<a href="#" class="m-all-1">
						<img src="../cozastore-master/images/icons/icon-pay-04.png" alt="ICON-PAY">
					</a>

					<a href="#" class="m-all-1">
						<img src="../cozastore-master/images/icons/icon-pay-05.png" alt="ICON-PAY">
					</a>
				</div>

				<p class="stext-107 cl6 txt-center">
					<!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. -->
Copyright &copy;<script>document.write(new Date().getFullYear());</script> All rights reserved | This template is made with <i class="fa fa-heart-o" aria-hidden="true"></i> by <a href="https://colorlib.com" target="_blank">Colorlib</a>
<!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. -->

				</p>
			</div>
		</div>
	</footer>


    <!-- Back to top removed -->

<!--===============================================================================================-->	
	<script src="../cozastore-master/vendor/jquery/jquery-3.2.1.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/animsition/js/animsition.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/bootstrap/js/popper.js"></script>
	<script src="../cozastore-master/vendor/bootstrap/js/bootstrap.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/select2/select2.min.js"></script>
	<script>
		$(".js-select2").each(function(){
			$(this).select2({
				minimumResultsForSearch: 20,
				dropdownParent: $(this).next('.dropDownSelect2')
			});
		})
	</script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/MagnificPopup/jquery.magnific-popup.min.js"></script>
<!--===============================================================================================-->
	<script src="../cozastore-master/vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
	<script>
		$('.js-pscroll').each(function(){
			$(this).css('position','relative');
			$(this).css('overflow','hidden');
			var ps = new PerfectScrollbar(this, {
				wheelSpeed: 1,
				scrollingThreshold: 1000,
				wheelPropagation: false,
			});

			$(window).on('resize', function(){
				ps.update();
			})
		});
	</script>
<!--===============================================================================================-->
	<script src="../cozastore-master/js/main.js"></script>

	<!-- Custom JavaScript for cart functionality -->
	<script>
	// Xử lý tăng/giảm số lượng
	document.addEventListener('DOMContentLoaded', function() {
		// Increase quantity
		document.querySelectorAll('.increase-btn').forEach(btn => {
			btn.addEventListener('click', function() {
				const cartId = this.dataset.cartId;
				const input = document.querySelector(`input[data-cart-id="${cartId}"]`);
				const currentValue = parseInt(input.value);
				if (currentValue < 10) {
					input.value = currentValue + 1;
					updateQuantity(cartId, input.value);
				}
			});
		});
		
		// Decrease quantity
		document.querySelectorAll('.decrease-btn').forEach(btn => {
			btn.addEventListener('click', function() {
				const cartId = this.dataset.cartId;
				const input = document.querySelector(`input[data-cart-id="${cartId}"]`);
				const currentValue = parseInt(input.value);
				if (currentValue > 1) {
					input.value = currentValue - 1;
					updateQuantity(cartId, input.value);
				}
			});
		});
		
		// Direct input change
		document.querySelectorAll('.quantity-input').forEach(input => {
			input.addEventListener('change', function() {
				const cartId = this.dataset.cartId;
				const quantity = parseInt(this.value);
				if (quantity >= 1 && quantity <= 10) {
					updateQuantity(cartId, quantity);
				} else {
					this.value = Math.max(1, Math.min(10, quantity));
				}
			});
		});
		
		// Remove item
		document.querySelectorAll('.remove-btn').forEach(btn => {
			btn.addEventListener('click', function() {
				const cartId = this.dataset.cartId;
				if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?')) {
					removeItem(cartId);
				}
			});
		});
	});

	// Update quantity function
	function updateQuantity(cartId, quantity) {
		fetch('cart.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: `action=update_quantity&cart_id=${cartId}&quantity=${quantity}`
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				location.reload(); // Reload to update totals
			} else {
				alert('Không thể cập nhật số lượng');
			}
		})
		.catch(error => {
			console.error('Error:', error);
			alert('Có lỗi xảy ra');
		});
	}

	// Remove item function
	function removeItem(cartId) {
		fetch('cart.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: `action=remove_item&cart_id=${cartId}`
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert('Sản phẩm đã được xóa khỏi giỏ hàng');
				location.reload();
			} else {
				alert('Không thể xóa sản phẩm');
			}
		})
		.catch(error => {
			console.error('Error:', error);
			alert('Có lỗi xảy ra');
		});
	}
	</script>

	<?php include 'chatbox.php'; ?>
</body>
</html>