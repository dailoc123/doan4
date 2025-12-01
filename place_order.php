<?php
session_start();
require 'db.php';

// Ép buộc thời gian lên năm 2025 cho môi trường test
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Tắt hiển thị lỗi để tăng tốc độ
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'cod';
$total_price = floatval($_POST['total_price'] ?? 0);
$voucher_code = trim($_POST['voucher_code'] ?? '');

// Kiểm tra xem có phải là mua ngay hay không
$is_direct_buy = isset($_POST['is_direct_buy']) && $_POST['is_direct_buy'] == 1;

// -- Bắt đầu: server-side validate voucher (replace existing voucher block) --
$applied_discount = 0.0;
$valid_voucher = false;
$voucher_code = trim($voucher_code ?? '');

if ($voucher_code !== '') {
    $vcode = strtoupper($voucher_code);

    // Prepare safe select
    $v_stmt = $conn->prepare("SELECT id, code, discount, expiry_date, usage_limit, is_percent, min_order_amount, max_discount, status FROM vouchers WHERE code = ? LIMIT 1");
    if ($v_stmt === false) {
        error_log("place_order.php - voucher prepare failed: " . $conn->error);
        // treat as no voucher
        $voucher_code = null;
    } else {
        $v_stmt->bind_param("s", $vcode);
        $v_stmt->execute();
        $vres = $v_stmt->get_result();
        $voucher = $vres ? $vres->fetch_assoc() : null;
        $v_stmt->close();

        if (!$voucher) {
            $voucher_code = null;
        } else {
            // basic checks
            $today = date('Y-m-d');
            if ((!empty($voucher['status']) && $voucher['status'] == 0)
                || (!empty($voucher['expiry_date']) && $voucher['expiry_date'] < $today)) {
                $voucher_code = null;
            } else {
                // check min order amount
                if (!empty($voucher['min_order_amount']) && $total_price < floatval($voucher['min_order_amount'])) {
                    $voucher_code = null;
                } else {
                    // check usage limit
                    if (!empty($voucher['usage_limit'])) {
                        $u_stmt = $conn->prepare("SELECT COUNT(*) as used FROM orders WHERE voucher_code = ?");
                        if ($u_stmt) {
                            $u_stmt->bind_param("s", $vcode);
                            $u_stmt->execute();
                            $ures = $u_stmt->get_result();
                            $used_row = $ures ? $ures->fetch_assoc() : ['used' => 0];
                            $u_stmt->close();
                            if ((int)$used_row['used'] >= (int)$voucher['usage_limit']) {
                                $voucher_code = null;
                            }
                        }
                    }
                }
            }

            // compute discount if still valid
            if ($voucher_code !== null) {
                $disc_val = floatval($voucher['discount']);
                if (!empty($voucher['is_percent'])) {
                    $discount_amount = ($total_price * $disc_val) / 100.0;
                    if (!empty($voucher['max_discount'])) {
                        $discount_amount = min($discount_amount, floatval($voucher['max_discount']));
                    }
                } else {
                    $discount_amount = $disc_val;
                }
                $discount_amount = min($discount_amount, $total_price);
                $applied_discount = round($discount_amount, 2);
                $total_price = max(0, round($total_price - $applied_discount, 2));
                $valid_voucher = true;
                // keep voucher_code uppercased to store
                $voucher_code = $vcode;
            } else {
                $voucher_code = null;
            }
        }
    }
} else {
    $voucher_code = null;
}
// -- Kết thúc validate voucher --

// Thiết lập payment_status dựa trên phương thức thanh toán
$payment_status = 'unpaid';
if ($payment_method === 'vnpay' || $payment_method === 'momo') {
    $payment_status = 'pending';
}

// Build order SQL (voucher_code optional)
$order_sql = "INSERT INTO orders (user_id, name, email, phone, address, payment_method, total_price, status, payment_status, created_at";
$order_sql .= $voucher_code !== null ? ", voucher_code" : "";
// nếu bảng orders có cột discount_amount thì chèn vào (tự phát hiện)
$has_discount_col = false;
$col_check = $conn->query("SHOW COLUMNS FROM orders LIKE 'discount_amount'");
if ($col_check && $col_check->num_rows > 0) {
    $has_discount_col = true;
    $order_sql .= ", discount_amount";
}
$order_sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW()";
$order_sql .= $voucher_code !== null ? ", ?" : "";
$order_sql .= $has_discount_col ? ", ?" : "";
$order_sql .= ")";

// Prepare statement
$stmt = $conn->prepare($order_sql);
if ($stmt === false) {
    error_log("place_order.php - prepare order_sql failed: " . $conn->error . " | SQL: " . $order_sql);
    die("Lỗi SQL khi chuẩn bị đơn hàng: " . htmlspecialchars($conn->error));
}

// build params array dynamically and types
$bind_params = [$user_id, $name, $email, $phone, $address, $payment_method, $total_price, $payment_status];
if ($voucher_code !== null) $bind_params[] = $voucher_code;
if ($has_discount_col) $bind_params[] = $applied_discount;

// auto detect types
$types = '';
foreach ($bind_params as $p) {
    if (is_int($p)) $types .= 'i';
    elseif (is_float($p) || is_double($p)) $types .= 'd';
    else $types .= 's';
}

// call bind_param with references
$bind_names = [];
$bind_names[] = $types;
foreach ($bind_params as $key => $value) {
    $bind_names[] = &$bind_params[$key];
}
$bindOk = call_user_func_array([$stmt, 'bind_param'], $bind_names);
if ($bindOk === false) {
    error_log("place_order.php - bind_param failed: " . $stmt->error);
    $stmt->close();
    die("Lỗi bind_param khi tạo đơn hàng: " . htmlspecialchars($stmt->error));
}

if (!$stmt->execute()) {
    error_log("place_order.php - execute order insert failed: " . $stmt->error);
    $stmt->close();
    die("Đặt hàng thất bại: " . htmlspecialchars($stmt->error));
}

// Lấy order_id ngay sau khi insert thành công
$order_id = $stmt->insert_id ?? 0;
$stmt->close();

// Nếu orders không có discount_amount nhưng bạn muốn lưu, cập nhật an toàn
if (!$has_discount_col && $applied_discount > 0) {
    // nếu muốn lưu, bạn cần thêm cột discount_amount vào DB; else skip
    // error_log("place_order.php - applied_discount={$applied_discount} but orders.discount_amount missing");
}

// Không đặt lại $order_id lần nữa (tránh bị 0 khi $stmt đã đóng)

if ($is_direct_buy) {
    // Xử lý mua ngay
    $product_id = $_POST['product_id'];
    $color_id = $_POST['color_id'] ?? null;
    $size_id = $_POST['size_id'] ?? null;
    $quantity = $_POST['quantity'];
    
    // Lấy giá sản phẩm
    $price_query = $conn->prepare("SELECT price, discount_price FROM products WHERE id = ?");
    $price_query->bind_param("i", $product_id);
    $price_query->execute();
    $price_result = $price_query->get_result();
    $product = $price_result->fetch_assoc();
    $price = $product['discount_price'] > 0 ? $product['discount_price'] : $product['price'];
    
    // Thêm vào bảng order_items
    $insert_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, color_id, size_id)
    VALUES (?, ?, ?, ?, ?, ?)");
    $insert_item->bind_param("iiidii", $order_id, $product_id, $quantity, $price, $color_id, $size_id);
    $insert_item->execute();
} else {
    // Lấy sản phẩm trong giỏ hàng
    $cart_query = $conn->prepare("SELECT * FROM cart WHERE user_id = ?");
    $cart_query->bind_param("i", $user_id);
    $cart_query->execute();
    $cart_result = $cart_query->get_result();

    while ($item = $cart_result->fetch_assoc()) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $color_id = $item['color_id'];
        $size_id = $item['size_id'];

        // Lấy giá sản phẩm
        $price_query = $conn->prepare("SELECT price, discount_price FROM products WHERE id = ?");
        $price_query->bind_param("i", $product_id);
        $price_query->execute();
        $price_result = $price_query->get_result();
        $product = $price_result->fetch_assoc();
        $price = $product['discount_price'] > 0 ? $product['discount_price'] : $product['price'];

        // Thêm vào bảng order_items
        $insert_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, color_id, size_id)
        VALUES (?, ?, ?, ?, ?, ?)");
        $insert_item->bind_param("iiidii", $order_id, $product_id, $quantity, $price, $color_id, $size_id);
        $insert_item->execute();
    }

    // Xoá giỏ hàng
    $delete_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $delete_cart->bind_param("i", $user_id);
    $delete_cart->execute();
}

// Xử lý chuyển hướng dựa trên phương thức thanh toán
// Xử lý chuyển hướng dựa trên phương thức thanh toán
if ($payment_method === 'momo') {
    require 'momo_handler.php';
    $order_info = "Thanh toan don hang #$order_id tai Luxury Store";
    $unique_order_id = $order_id . '_' . time();
    
    $momo_response = createMomoPaymentRequest($unique_order_id, (int)$total_price, $order_info, 'captureWallet');
    
    if ($momo_response['url']) {
        header("Location: " . $momo_response['url']);
        exit;
    } else {
        $conn->query("UPDATE orders SET status = 'failed' WHERE id = $order_id");
        echo "<h3>Có lỗi xảy ra trong quá trình tạo yêu cầu thanh toán MoMo.</h3>";
        echo "<p><strong>Thông báo lỗi:</strong> " . htmlspecialchars($momo_response['error']) . "</p>";
        echo '<a href="checkout.php">Thử lại</a>';
        exit;
    }
} elseif ($payment_method === 'vnpay') {
    // Lưu thông tin đơn hàng vào session
    $_SESSION['pending_order'] = [
        'order_id' => $order_id,
        'amount' => $total_price,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'user_id' => $user_id,
        'is_direct_buy' => $is_direct_buy,
        'voucher_code' => $voucher_code,
        'total_price' => $total_price
    ];
    
    // Chuyển hướng đến vnpay_payment.php với POST data
    echo '<form id="vnpay_form" method="POST" action="vnpay_payment.php">';
    echo '<input type="hidden" name="customer_name" value="' . htmlspecialchars($name) . '">';
    echo '<input type="hidden" name="customer_email" value="' . htmlspecialchars($email) . '">';
    echo '<input type="hidden" name="customer_phone" value="' . htmlspecialchars($phone) . '">';
    echo '<input type="hidden" name="customer_address" value="' . htmlspecialchars($address) . '">';
    echo '<input type="hidden" name="total_amount" value="' . $total_price . '">';
    echo '<input type="hidden" name="voucher_code" value="' . htmlspecialchars($voucher_code) . '">';
    echo '<input type="hidden" name="is_direct_buy" value="' . ($is_direct_buy ? '1' : '0') . '">';
    echo '<input type="hidden" name="redirect" value="1">';
    echo '</form>';
    echo '<script>document.getElementById("vnpay_form").submit();</script>';
    exit;
} elseif ($payment_method === 'bank_transfer') {
    $conn->query("UPDATE orders SET status = 'pending_payment' WHERE id = $order_id");
    header("Location: thank_you.php?order_id=$order_id&payment_method=bank_transfer");
    exit;
} else {
    // Mặc định là COD
    header("Location: thank_you.php?order_id=$order_id");
    exit;
}

// Sau khi $order_id được tạo và order_items đã được insert
// Giả sử bạn có $order_items array each item: ['product_id'=>..., 'quantity'=>...]
// Nếu bạn lưu cart trong DB, lấy danh sách item từ order_items table vừa tạo.

$updateInventoryStmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?");
$historyStmt = $conn->prepare("INSERT INTO inventory_history (product_id, type, quantity, note, created_at) VALUES (?, 'order', ?, ?, NOW())");

if (!$updateInventoryStmt || !$historyStmt) {
    error_log("place_order.php - prepare inventory statements failed: " . $conn->error);
    // xử lý rollback nếu dùng transaction
}

// Lấy danh sách item từ order_items nếu không có biến $order_items
$items_res = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = " . intval($order_id));
if ($items_res) {
    while ($it = $items_res->fetch_assoc()) {
        $pid = (int)$it['product_id'];
        $qty = (int)$it['quantity'];

        // Giảm tồn kho (không để âm)
        $updateInventoryStmt->bind_param("ii", $qty, $pid);
        if (!$updateInventoryStmt->execute()) {
            error_log("place_order.php - inventory update failed for product $pid : " . $updateInventoryStmt->error);
            // bạn có thể rollback transaction và báo lỗi; ở đây chỉ log
        } else {
            // đảm bảo quantity không âm (bổ sung): set quantity = GREATEST(quantity,0)
            // nếu muốn nghiêm ngặt: kiểm tra số lượng trước khi tạo đơn và từ chối nếu thiếu

            // Thêm vào lịch sử kho
            $note = "Order #{$order_id}";
            $historyStmt->bind_param("iis", $pid, $qty, $note);
            if (!$historyStmt->execute()) {
                error_log("place_order.php - insert inventory_history failed for product $pid : " . $historyStmt->error);
            }
        }
    }
    $items_res->free();
}

$updateInventoryStmt->close();
$historyStmt->close();
?>