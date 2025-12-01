<?php
// Hàm cập nhật tổng số sản phẩm trong giỏ (dựa trên quantity)
function updateCartCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total_quantity FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $_SESSION['cart_count'] = $row['total_quantity'] ?? 0;
}

// Hàm kiểm tra đăng nhập
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Hàm kiểm tra quyền admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
