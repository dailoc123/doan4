<?php
session_start();
require '../db.php';

// Kiểm tra quyền admin hoặc người dùng
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lấy thông tin người dùng
$user_id = $_SESSION['user_id'];

// Lấy thông tin giỏ hàng
$sql = "SELECT c.product_id, c.quantity, p.price, p.name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Kiểm tra nếu giỏ hàng trống
if ($result->num_rows == 0) {
    echo "Giỏ hàng của bạn trống!";
    exit;
}

// Lấy thông tin từ form thanh toán
$address = $_POST['address'];
$payment_method = $_POST['payment_method'];

// Tính tổng giá trị đơn hàng
$total_price = 0;
$order_items = [];
while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
    $total_price += $row['price'] * $row['quantity'];
}

// Lưu thông tin đơn hàng vào database
$stmt = $conn->prepare("INSERT INTO orders (user_id, address, payment_method, total_price, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issdi", $user_id, $address, $payment_method, $total_price, $status);
$status = 'pending';  // Trạng thái đơn hàng khi mới tạo
$stmt->execute();
$order_id = $stmt->insert_id;  // Lấy ID đơn hàng mới tạo

// Lưu chi tiết đơn hàng vào order_items
foreach ($order_items as $item) {
    $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
    $stmt2->execute();
}

// Xóa giỏ hàng sau khi thanh toán
$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Chuyển hướng đến trang xác nhận đơn hàng
header("Location: order_confirmation.php?order_id=$order_id");
exit;
?>
