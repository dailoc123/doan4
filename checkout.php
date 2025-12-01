<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user information
$stmt_user = $conn->prepare("SELECT name, email, phone, address FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_info = $user_result->fetch_assoc();

// Check if it's direct buy or cart checkout
$is_direct_buy = isset($_GET['product_id']) && !empty($_GET['product_id']);
$cart_items = [];
$total_price = 0;

if ($is_direct_buy) {
    // Handle direct buy
    $product_id = $_GET['product_id'];
    $color_id = $_GET['color_id'] ?? null;
    $size_id = $_GET['size_id'] ?? null;
    $quantity = $_GET['quantity'] ?? 1;
    
    // Get product information
    $stmt = $conn->prepare("SELECT 
        p.id as product_id,
        p.name, 
        p.price,
        p.discount_price, 
        p.image,
        c.name as color_name,
        s.name as size_name
    FROM products p 
    LEFT JOIN colors c ON c.id = ? 
    LEFT JOIN sizes s ON s.id = ?
    WHERE p.id = ?");
    
    $stmt->bind_param("iii", $color_id, $size_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $row['image'] = str_replace("../", "", $row['image']);
        $effective_price = $row['discount_price'] > 0 ? $row['discount_price'] : $row['price'];
        $row['subtotal'] = $effective_price * $quantity;
        $row['quantity'] = $quantity;
        $row['color_id'] = $color_id;
        $row['size_id'] = $size_id;
        $total_price += $row['subtotal'];
        $cart_items[] = $row;
    }
} else {
    // Get products from cart
    $stmt = $conn->prepare("SELECT 
        c.id as cart_id, 
        p.id as product_id,
        c.quantity, 
        p.name, 
        p.price,
        p.discount_price, 
        p.image,
        c.color_id,
        c.size_id,
        clr.name as color_name,
        sz.name as size_name
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    LEFT JOIN colors clr ON c.color_id = clr.id
    LEFT JOIN sizes sz ON c.size_id = sz.id
    WHERE c.user_id = ?");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['image'] = str_replace("../", "", $row['image']);
        $effective_price = $row['discount_price'] > 0 ? $row['discount_price'] : $row['price'];
        $row['subtotal'] = $effective_price * $row['quantity'];
        $total_price += $row['subtotal'];
        $cart_items[] = $row;
    }
}

// Update cart count
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$cart_count = 0;
if ($count_row = $count_result->fetch_assoc()) {
    $cart_count = (int)$count_row['total'] ?: 0;
}

// Thêm ngay sau phần lấy thông tin người dùng (trước khi output HTML) để truyền mã voucher vào JS
$prefill_voucher = '';
if (!empty($_GET['voucher'])) {
    $prefill_voucher = trim($_GET['voucher']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán | Luxury Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <!-- Bổ sung đường dẫn đúng tới CSS ngoài thư mục backend -->
    <link rel="stylesheet" href="../css/checkout.css?v=<?= time() ?>" >
    <style>
       
    </style>
</head>
<body>
<?php require 'header.php'; ?>

<div class="loading-overlay">
    <span class="loader"></span>
</div>

<div class="checkout-container">
    <div class="checkout-header">
        <h1>Thanh toán</h1>
        <p>Hoàn tất đơn hàng của bạn</p>
    </div>

    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h3>Giỏ hàng của bạn đang trống</h3>
            <p>Vui lòng thêm sản phẩm vào giỏ hàng trước khi thanh toán.</p>
            <a href="products.php">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="checkout-wrapper">
            <div class="form-left">
                <div class="form-header">
                    <h2>Thông tin giao hàng</h2>
                </div>
                <div class="form-content">
                    <form id="checkout-form" action="place_order.php" method="POST">
                        <div class="input-group">
                            <label for="name">Họ và tên</label>
                            <input type="text" id="name" name="name" class="input-field" placeholder="Nhập họ và tên" value="<?= htmlspecialchars($user_info['name'] ?? '') ?>" required>
                            <div class="error-message" id="name-error">Vui lòng nhập họ và tên</div>
                        </div>
                        
                        <div class="input-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="input-field" placeholder="Nhập email" value="<?= htmlspecialchars($user_info['email'] ?? '') ?>" required>
                            <div class="error-message" id="email-error">Vui lòng nhập email hợp lệ</div>
                        </div>
                        
                        <div class="input-group">
                            <label for="phone">Số điện thoại</label>
                            <input type="text" id="phone" name="phone" class="input-field" placeholder="Nhập số điện thoại" value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" required>
                            <div class="error-message" id="phone-error">Vui lòng nhập số điện thoại hợp lệ</div>
                        </div>
                        
                        <div class="input-group">
                            <label for="address">Địa chỉ nhận hàng</label>
                            <input type="text" id="address" name="address" class="input-field" placeholder="Nhập địa chỉ nhận hàng" value="<?= htmlspecialchars($user_info['address'] ?? '') ?>" required>
                            <div class="error-message" id="address-error">Vui lòng nhập địa chỉ nhận hàng</div>
                        </div>
                        
                        <!-- ĐÃ VÔ HIỆU HÓA CHỌN TỈNH/THÀNH PHỐ -->
                        <div class="input-group" style="display:none;">
                            <label for="city">Tỉnh/Thành phố</label>
                            <select id="city" name="city" class="input-field" disabled>
                                <option value="">Chọn tỉnh/thành phố</option>
                            </select>
                            <div class="error-message" id="city-error">Vui lòng chọn tỉnh/thành phố</div>
                        </div>
                        
                        <!-- ĐÃ VÔ HIỆU HÓA CHỌN QUẬN/HUYỆN -->
                        <div class="input-group" style="display:none;">
                            <label for="district">Quận/Huyện</label>
                            <select id="district" name="district" class="input-field" disabled>
                                <option value="">Chọn quận/huyện</option>
                            </select>
                            <div class="error-message" id="district-error">Vui lòng chọn quận/huyện</div>
                        </div>
                        
                        <!-- ĐÃ VÔ HIỆU HÓA CHỌN PHƯỜNG/XÃ -->
                        <div class="input-group" style="display:none;">
                            <label for="ward">Phường/Xã</label>
                            <select id="ward" name="ward" class="input-field" disabled>
                                <option value="">Chọn phường/xã</option>
                            </select>
                            <div class="error-message" id="ward-error">Vui lòng chọn phường/xã</div>
                        </div>
                        
                        <div class="input-group">
                            <label for="note">Ghi chú</label>
                            <textarea id="note" name="note" class="input-field" placeholder="Ghi chú về đơn hàng, ví dụ: thời gian hay chỉ dẫn địa điểm giao hàng chi tiết hơn" rows="3"></textarea>
                        </div>

                        <div class="input-group">
                            <label>Phương thức thanh toán</label>
                            <div class="payment-methods">
                                <label class="payment-method-item">
                                    <input type="radio" name="payment_method" value="cod" checked>
                                    <span class="payment-icon"><i class="fas fa-money-bill-wave"></i></span>
                                    <span>Thanh toán khi nhận hàng</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="radio" name="payment_method" value="momo">
                                    <span class="payment-icon"><i class="fas fa-wallet"></i></span>
                                    <span>Thanh toán qua MoMo</span>
                                </label>
                                <label class="payment-method-item">
                                    <input type="radio" name="payment_method" value="vnpay">
                                    <span class="payment-icon"><i class="fas fa-credit-card"></i></span>
                                    <span>Thanh toán qua VNPAY</span>
                                </label>
                            </div>
                        </div>

                        <div class="input-group">
                            <label for="voucher">Mã giảm giá</label>
                            <input type="text" id="voucher" name="voucher_code" class="input-field" placeholder="Nhập mã giảm giá (nếu có)">
                        </div>

                        <input type="hidden" name="total_price" value="<?= $total_price ?>">
                        
                        <?php if ($is_direct_buy && !empty($cart_items)): ?>
                            <input type="hidden" name="is_direct_buy" value="1">
                            <input type="hidden" name="product_id" value="<?= $cart_items[0]['product_id'] ?>">
                            <input type="hidden" name="color_id" value="<?= $cart_items[0]['color_id'] ?>">
                            <input type="hidden" name="size_id" value="<?= $cart_items[0]['size_id'] ?>">
                            <input type="hidden" name="quantity" value="<?= $cart_items[0]['quantity'] ?>">
                        <?php endif; ?>
                        
                        <button type="submit" class="checkout-btn">
                            <i class="fas fa-lock"></i> Tiến hành thanh toán
                        </button>
                    </form>
                </div>
            </div>

            <div class="summary-right">
                <div class="summary-header">
                    <h2>Đơn hàng của bạn</h2>
                </div>
                <div class="summary-content">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-product">
                            <img src="admin/uploads/<?= htmlspecialchars(basename($item['image'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="product-variant">
                                    <?php if (!empty($item['color_name'])): ?>
                                        <span>Màu: <?= htmlspecialchars($item['color_name']) ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['size_name'])): ?>
                                        <span> | Size: <?= htmlspecialchars($item['size_name']) ?></span>
                                    <?php endif; ?>
                                    
                                    <span> | SL: <?= $item['quantity'] ?></span>
                                </div>
                                <div class="product-price">
                                    <?php if ($item['discount_price'] > 0): ?>
                                        <span class="original-price"><?= number_format($item['price'], 0, ',', '.') ?>₫</span>
                                        <span class="discount-price"><?= number_format($item['discount_price'], 0, ',', '.') ?>₫</span>
                                        <?php 
                                            $discount_percent = round(($item['price'] - $item['discount_price']) / $item['price'] * 100);
                                        ?>
                                        <span class="discount-badge">-<?= $discount_percent ?>%</span>
                                    <?php else: ?>
                                        <span class="discount-price"><?= number_format($item['price'], 0, ',', '.') ?>₫</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="summary-item">
                        <span class="summary-label">Tạm tính:</span>
                        <span class="summary-value"><?= number_format($total_price, 0, ',', '.') ?>₫</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Phí vận chuyển:</span>
                        <span class="summary-value">Miễn phí</span>
                    </div>
                    <div class="summary-item total-row">
                        <span class="summary-label">Tổng cộng:</span>
                        <span class="total-value"><?= number_format($total_price, 0, ',', '.') ?>₫</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show loading overlay
    const loadingOverlay = document.querySelector('.loading-overlay');
    
    function showLoading() {
        loadingOverlay.classList.add('active');
    }
    
    function hideLoading() {
        loadingOverlay.classList.remove('active');
        setTimeout(() => {
            loadingOverlay.style.display = 'none';
        }, 300);
    }
    
    // Hide loading after page loads
    setTimeout(hideLoading, 500);
    
    // Form validation
    const checkoutForm = document.getElementById('checkout-form');
    
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate name
            const nameInput = document.getElementById('name');
            const nameError = document.getElementById('name-error');
            if (!nameInput.value.trim()) {
                nameInput.classList.add('error');
                nameError.style.display = 'block';
                isValid = false;
            } else {
                nameInput.classList.remove('error');
                nameError.style.display = 'none';
            }
            
            // Validate email
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('email-error');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailInput.value.trim())) {
                emailInput.classList.add('error');
                emailError.style.display = 'block';
                isValid = false;
            } else {
                emailInput.classList.remove('error');
                emailError.style.display = 'none';
            }
            
            // Validate phone
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phone-error');
            const phonePattern = /^[0-9]{10,11}$/;
            if (!phonePattern.test(phoneInput.value.trim())) {
                phoneInput.classList.add('error');
                phoneError.style.display = 'block';
                isValid = false;
            } else {
                phoneInput.classList.remove('error');
                phoneError.style.display = 'none';
            }
            
            // Validate address
            const addressInput = document.getElementById('address');
            const addressError = document.getElementById('address-error');
            if (!addressInput.value.trim()) {
                addressInput.classList.add('error');
                addressError.style.display = 'block';
                isValid = false;
            } else {
                addressInput.classList.remove('error');
                addressError.style.display = 'none';
            }
            
            // Thành phố/Quận/Huyện/Phường đã được vô hiệu hóa
            const cityError = document.getElementById('city-error');
            const districtError = document.getElementById('district-error');
            const wardError = document.getElementById('ward-error');
            if (cityError) cityError.style.display = 'none';
            if (districtError) districtError.style.display = 'none';
            if (wardError) wardError.style.display = 'none';
            
            if (!isValid) {
                e.preventDefault();
                
                // Show error message
                               // Show error message
                               Swal.fire({
                    title: 'Lỗi xác thực',
                    text: 'Vui lòng kiểm tra lại thông tin đã nhập',
                    icon: 'error',
                    confirmButtonText: 'Đồng ý',
                    confirmButtonColor: '#000'
                });
                return;
            }
            
            // Show loading animation
            showLoading();
            
            // Apply voucher if entered
            const voucherInput = document.getElementById('voucher');
            if (voucherInput && voucherInput.value.trim()) {
                // Add voucher validation here if needed
                console.log('Applying voucher: ' + voucherInput.value.trim());
            }
        });
    }
    
    // Payment method selection effects
    const paymentMethods = document.querySelectorAll('.payment-method-item');
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            // Remove active class from all methods
            paymentMethods.forEach(m => m.style.borderColor = '');
            
            // Add active class to selected method
            this.style.borderColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color');
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Show different instructions based on payment method
            const paymentMethod = radio.value;
            let paymentInstructions = '';
            
            switch(paymentMethod) {
                case 'momo':
                    // Show MoMo instructions
                    Swal.fire({
                        title: 'Thanh toán qua MoMo',
                        html: 'Bạn sẽ được chuyển đến cổng thanh toán MoMo sau khi xác nhận đơn hàng.',
                        icon: 'info',
                        confirmButtonText: 'Đã hiểu',
                        confirmButtonColor: '#000'
                    });
                    break;
                case 'vnpay':
                    // Show VNPAY instructions
                    Swal.fire({
                        title: 'Thanh toán qua VNPAY',
                        html: 'Bạn sẽ được chuyển đến cổng thanh toán VNPAY sau khi xác nhận đơn hàng.',
                        icon: 'info',
                        confirmButtonText: 'Đã hiểu',
                        confirmButtonColor: '#000'
                    });
                    break;
            }
        });
    });
    
    // Add animation to cart products
    const cartProducts = document.querySelectorAll('.cart-product');
    cartProducts.forEach((product, index) => {
        // Set initial state
        product.style.opacity = '0';
        product.style.transform = 'translateY(20px)';
        
        // Animate in with delay based on index
        setTimeout(() => {
            product.style.transition = 'all 0.5s ease';
            product.style.opacity = '1';
            product.style.transform = 'translateY(0)';
        }, 100 * index);
    });
    
    // Voucher code validation
    const voucherInput = document.getElementById('voucher');
    if (voucherInput) {
        voucherInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        voucherInput.addEventListener('blur', function() {
            if (this.value.trim()) {
                // Here you would typically validate the voucher code with an AJAX call
                // For now, just show a message
                Swal.fire({
                    title: 'Đang kiểm tra mã',
                    text: 'Vui lòng đợi trong giây lát...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        
                        // Simulate AJAX call
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Thông báo',
                                text: 'Mã giảm giá sẽ được áp dụng khi thanh toán',
                                icon: 'info',
                                confirmButtonText: 'Đồng ý',
                                confirmButtonColor: '#000'
                            });
                        }, 1500);
                    }
                });
            }
        });
    }
    
    // Add hover effect to checkout button
    const checkoutBtn = document.querySelector('.checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('mouseenter', function() {
            this.innerHTML = '<i class="fas fa-lock"></i> Xác nhận đặt hàng';
        });
        
        checkoutBtn.addEventListener('mouseleave', function() {
            this.innerHTML = '<i class="fas fa-lock"></i> Tiến hành thanh toán';
        });
    }
    
    // Thêm mã voucher vào ô nhập liệu nếu có
    const prefill = <?= json_encode($prefill_voucher) ?>;
    if (prefill) {
        const voucherInput = document.getElementById('voucher') || document.querySelector('input[name="voucher_code"]');
        if (voucherInput) {
            voucherInput.value = prefill;
            // nếu bạn có hàm validate tự động (blur handler) gọi nó:
            if (typeof checkVoucher === 'function') {
                checkVoucher(prefill);
            } else {
                // nếu dùng AJAX endpoint admin/vouchers.php?action=validate thì trigger blur
                voucherInput.dispatchEvent(new Event('blur'));
            }
        }
    }
});
</script>
<script>
// Prefill voucher value from URL param (server-provided)
document.addEventListener('DOMContentLoaded', function() {
  var voucherPrefill = "<?= htmlspecialchars($prefill_voucher) ?>";
  var voucherEl = document.getElementById('voucher');
  if (voucherEl && voucherPrefill) {
    voucherEl.value = voucherPrefill;
  }
});
</script>

<?php include 'chatbox.php'; ?>
</body>
</html>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show loading overlay
    const loadingOverlay = document.querySelector('.loading-overlay');
    
    function showLoading() {
        loadingOverlay.classList.add('active');
    }
    
    function hideLoading() {
        loadingOverlay.classList.remove('active');
        setTimeout(() => {
            loadingOverlay.style.display = 'none';
        }, 300);
    }
    
    // Hide loading after page loads
    setTimeout(hideLoading, 500);
    
    // Form validation
    const checkoutForm = document.getElementById('checkout-form');
    
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate name
            const nameInput = document.getElementById('name');
            const nameError = document.getElementById('name-error');
            if (!nameInput.value.trim()) {
                nameInput.classList.add('error');
                nameError.style.display = 'block';
                isValid = false;
            } else {
                nameInput.classList.remove('error');
                nameError.style.display = 'none';
            }
            
            // Validate email
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('email-error');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailInput.value.trim())) {
                emailInput.classList.add('error');
                emailError.style.display = 'block';
                isValid = false;
            } else {
                emailInput.classList.remove('error');
                emailError.style.display = 'none';
            }
            
            // Validate phone
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phone-error');
            const phonePattern = /^[0-9]{10,11}$/;
            if (!phonePattern.test(phoneInput.value.trim())) {
                phoneInput.classList.add('error');
                phoneError.style.display = 'block';
                isValid = false;
            } else {
                phoneInput.classList.remove('error');
                phoneError.style.display = 'none';
            }
            
            // Validate address
            const addressInput = document.getElementById('address');
            const addressError = document.getElementById('address-error');
            if (!addressInput.value.trim()) {
                addressInput.classList.add('error');
                addressError.style.display = 'block';
                isValid = false;
            } else {
                addressInput.classList.remove('error');
                addressError.style.display = 'none';
            }
            
            // Thành phố/Quận/Huyện/Phường đã được vô hiệu hóa
            const cityError2 = document.getElementById('city-error');
            const districtError2 = document.getElementById('district-error');
            const wardError2 = document.getElementById('ward-error');
            if (cityError2) cityError2.style.display = 'none';
            if (districtError2) districtError2.style.display = 'none';
            if (wardError2) wardError2.style.display = 'none';
            
            if (!isValid) {
                e.preventDefault();
                
                // Show error message
                               // Show error message
                               Swal.fire({
                    title: 'Lỗi xác thực',
                    text: 'Vui lòng kiểm tra lại thông tin đã nhập',
                    icon: 'error',
                    confirmButtonText: 'Đồng ý',
                    confirmButtonColor: '#000'
                });
                return;
            }
            
            // Show loading animation
            showLoading();
            
            // Apply voucher if entered
            const voucherInput = document.getElementById('voucher');
            if (voucherInput && voucherInput.value.trim()) {
                // Add voucher validation here if needed
                console.log('Applying voucher: ' + voucherInput.value.trim());
            }
        });
    }
    
    // Payment method selection effects
    const paymentMethods = document.querySelectorAll('.payment-method-item');
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            // Remove active class from all methods
            paymentMethods.forEach(m => m.style.borderColor = '');
            
            // Add active class to selected method
            this.style.borderColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color');
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Show different instructions based on payment method
            const paymentMethod = radio.value;
            let paymentInstructions = '';
            
            switch(paymentMethod) {
                case 'momo':
                    // Show MoMo instructions
                    Swal.fire({
                        title: 'Thanh toán qua MoMo',
                        html: 'Bạn sẽ được chuyển đến cổng thanh toán MoMo sau khi xác nhận đơn hàng.',
                        icon: 'info',
                        confirmButtonText: 'Đã hiểu',
                        confirmButtonColor: '#000'
                    });
                    break;
                case 'vnpay':
                    // Show VNPAY instructions
                    Swal.fire({
                        title: 'Thanh toán qua VNPAY',
                        html: 'Bạn sẽ được chuyển đến cổng thanh toán VNPAY sau khi xác nhận đơn hàng.',
                        icon: 'info',
                        confirmButtonText: 'Đã hiểu',
                        confirmButtonColor: '#000'
                    });
                    break;
            }
        });
    });
    
    // Add animation to cart products
    const cartProducts = document.querySelectorAll('.cart-product');
    cartProducts.forEach((product, index) => {
        // Set initial state
        product.style.opacity = '0';
        product.style.transform = 'translateY(20px)';
        
        // Animate in with delay based on index
        setTimeout(() => {
            product.style.transition = 'all 0.5s ease';
            product.style.opacity = '1';
            product.style.transform = 'translateY(0)';
        }, 100 * index);
    });
    
    // Voucher code validation
    const voucherInput = document.getElementById('voucher');
    if (voucherInput) {
        voucherInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        voucherInput.addEventListener('blur', function() {
            if (this.value.trim()) {
                // Here you would typically validate the voucher code with an AJAX call
                // For now, just show a message
                Swal.fire({
                    title: 'Đang kiểm tra mã',
                    text: 'Vui lòng đợi trong giây lát...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        
                        // Simulate AJAX call
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Thông báo',
                                text: 'Mã giảm giá sẽ được áp dụng khi thanh toán',
                                icon: 'info',
                                confirmButtonText: 'Đồng ý',
                                confirmButtonColor: '#000'
                            });
                        }, 1500);
                    }
                });
            }
        });
    }
    
    // Add hover effect to checkout button
    const checkoutBtn = document.querySelector('.checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('mouseenter', function() {
            this.innerHTML = '<i class="fas fa-lock"></i> Xác nhận đặt hàng';
        });
        
        checkoutBtn.addEventListener('mouseleave', function() {
            this.innerHTML = '<i class="fas fa-lock"></i> Tiến hành thanh toán';
        });
    }
    
    // Thêm mã voucher vào ô nhập liệu nếu có
    const prefill = <?= json_encode($prefill_voucher) ?>;
    if (prefill) {
        const voucherInput = document.getElementById('voucher') || document.querySelector('input[name="voucher_code"]');
        if (voucherInput) {
            voucherInput.value = prefill;
            // nếu bạn có hàm validate tự động (blur handler) gọi nó:
            if (typeof checkVoucher === 'function') {
                checkVoucher(prefill);
            } else {
                // nếu dùng AJAX endpoint admin/vouchers.php?action=validate thì trigger blur
                voucherInput.dispatchEvent(new Event('blur'));
            }
        }
    }
});
</script>

<?php require 'footer.php'; ?>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load Vietnam provinces, districts, wards
    const citySelect = document.getElementById('city');
    const districtSelect = document.getElementById('district');
    const wardSelect = document.getElementById('ward');
    
    // ĐÃ TẮT CHỨC NĂNG CHỌN TỈNH/QUẬN/PHƯỜNG
    if (citySelect && districtSelect && wardSelect) {
        // Giữ logic cũ nhưng chỉ chạy khi phần tử tồn tại
        fetch('https://provinces.open-api.vn/api/?depth=1')
            .then(response => response.json())
            .then(data => {
                data.forEach(province => {
                    const option = document.createElement('option');
                    option.value = province.code;
                    option.text = province.name;
                    citySelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error fetching provinces:', error);
            });

        citySelect.addEventListener('change', function() {
            districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
            wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
            wardSelect.disabled = true;
            if (this.value) {
                districtSelect.disabled = false;
                fetch(`https://provinces.open-api.vn/api/p/${this.value}?depth=2`)
                    .then(response => response.json())
                    .then(data => {
                        data.districts.forEach(district => {
                            const option = document.createElement('option');
                            option.value = district.code;
                            option.text = district.name;
                            districtSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching districts:', error);
                    });
            } else {
                districtSelect.disabled = true;
            }
        });

        districtSelect.addEventListener('change', function() {
            wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
            if (this.value) {
                wardSelect.disabled = false;
                fetch(`https://provinces.open-api.vn/api/d/${this.value}?depth=2`)
                    .then(response => response.json())
                    .then(data => {
                        data.wards.forEach(ward => {
                            const option = document.createElement('option');
                            option.value = ward.code;
                            option.text = ward.name;
                            wardSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching wards:', error);
                    });
            } else {
                wardSelect.disabled = true;
            }
        });
    }
    
    // Add animation to cart products
    const cartProducts = document.querySelectorAll('.cart-product');
    cartProducts.forEach((product, index) => {
        // Set initial state
        product.style.opacity = '0';
        product.style.transform = 'translateY(20px)';
        
        // Animate in with delay based on index
        setTimeout(() => {
            product.style.transition = 'all 0.5s ease';
            product.style.opacity = '1';
            product.style.transform = 'translateY(0)';
        }, 100 * index);
    });
    
    // Voucher code validation
    const voucherInput = document.getElementById('voucher');
    if (voucherInput) {
        voucherInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        voucherInput.addEventListener('blur', function() {
            if (this.value.trim()) {
                // Here you would typically validate the voucher code with an AJAX call
                // For now, just show a message
                Swal.fire({
                    title: 'Đang kiểm tra mã',
                    text: 'Vui lòng đợi trong giây lát...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        
                        // Simulate AJAX call
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Thông báo',
                                text: 'Mã giảm giá sẽ được áp dụng khi thanh toán',
                                icon: 'info',
                                confirmButtonText: 'Đồng ý',
                                confirmButtonColor: '#000'
                            });
                        }, 1500);
                    }
                });
            }
        });
    }
    
    // Add hover effect to checkout button
    const checkoutBtn = document.querySelector('.checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('mouseenter', function() {
            this.innerHTML = '<i class="fas fa-lock"></i> Xác nhận đặt hàng';
        });
        
        checkoutBtn.addEventListener('mouseleave', function() {
            this.innerHTML = '<i class="fas fa-lock"></i> Tiến hành thanh toán';
        });
    }
});
</script>