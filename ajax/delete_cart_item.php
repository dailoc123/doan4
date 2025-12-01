<?php
session_start();
require_once '../db.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Set error handling with default values
$response = [
    'success' => false,
    'message' => 'Đã xảy ra lỗi không xác định',
    'cart_count' => 0
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Vui lòng đăng nhập để thực hiện chức năng này';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate input
if (!isset($_POST['cart_id'])) {
    $response['message'] = 'Thiếu thông tin cần thiết';
    echo json_encode($response);
    exit;
}

$cart_id = intval($_POST['cart_id']);

// Check if item exists in cart
$check_stmt = $conn->prepare("SELECT id FROM cart WHERE id = ? AND user_id = ?");
if (!$check_stmt) {
    $response['message'] = 'Lỗi hệ thống: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$check_stmt->bind_param("ii", $cart_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'Sản phẩm không tồn tại trong giỏ hàng của bạn';
    echo json_encode($response);
    exit;
}

// Delete item from cart
$stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
if (!$stmt) {
    $response['message'] = 'Lỗi hệ thống: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ii", $cart_id, $user_id);

if ($stmt->execute()) {
    // Get updated cart count - fixing the function call
    $cart_count = 0; // Default value
    
    // Count items in cart
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    if ($count_stmt) {
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_row = $count_result->fetch_assoc()) {
            $cart_count = (int)$count_row['total'];
        }
    }
    
    $response = [
        'success' => true, 
        'cart_count' => $cart_count,
        'message' => 'Sản phẩm đã được xóa khỏi giỏ hàng',
        'cart_id' => $cart_id
    ];
} else {
    $response['message'] = 'Lỗi khi xóa sản phẩm: ' . $stmt->error;
}

echo json_encode($response);
exit;
?>