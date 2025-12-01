<?php
session_start();
require 'db.php';

// Kiểm tra người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Kiểm tra thông tin thanh toán
if (isset($_POST['address'], $_POST['payment_method']) && !empty($_POST['address']) && !empty($_POST['payment_method'])) {
    $address = $_POST['address'];  // Địa chỉ giao hàng
    $payment_method = $_POST['payment_method'];  // Phương thức thanh toán

    // Lấy thông tin giỏ hàng
    $stmt = $conn->prepare("SELECT 
                                c.product_id, 
                                c.quantity, 
                                p.price
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id
                            WHERE c.user_id = ?");
    if ($stmt === false) {
        die('Lỗi khi chuẩn bị câu lệnh SQL: ' . mysqli_error($conn));
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_price = 0;
    $order_items = [];
    
    while ($row = $result->fetch_assoc()) {
        $total_price += $row['price'] * $row['quantity'];
        $order_items[] = $row;
    }

    if ($total_price > 0) {
        // Thêm đơn hàng vào bảng orders
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, address, payment_method, created_at) 
                                VALUES (?, ?, 'Chờ xử lý', ?, ?, NOW())");
        if ($stmt === false) {
            die('Lỗi khi chuẩn bị câu lệnh SQL (orders): ' . mysqli_error($conn));
        }

        $stmt->bind_param("idss", $user_id, $total_price, $address, $payment_method);
        $stmt->execute();

        if ($stmt->affected_rows == 0) {
            die('Lỗi khi thêm đơn hàng vào cơ sở dữ liệu: ' . mysqli_error($conn));
        }

        $order_id = $stmt->insert_id;

        // Thêm sản phẩm vào bảng order_items
        foreach ($order_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                    VALUES (?, ?, ?, ?)");
            if ($stmt === false) {
                die('Lỗi khi chuẩn bị câu lệnh SQL (order_items): ' . mysqli_error($conn));
            }

            $stmt->bind_param("iiii", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }

        // Xóa giỏ hàng sau khi thanh toán thành công
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        if ($stmt === false) {
            die('Lỗi khi chuẩn bị câu lệnh SQL (xóa giỏ hàng): ' . mysqli_error($conn));
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Điều hướng đến trang chi tiết đơn hàng của người dùng
        header("Location: order_detail.php?order_id=" . $order_id);
        exit;
    } else {
        echo "Giỏ hàng trống!";
    }
} else {
    echo "Thông tin thanh toán không hợp lệ!";
}
