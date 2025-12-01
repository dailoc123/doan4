<?php
session_start();
require_once '../db.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện chức năng này']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate input
if (!isset($_POST['cart_id']) || !isset($_POST['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin cần thiết']);
    exit;
}

$cart_id = intval($_POST['cart_id']);
$quantity = intval($_POST['quantity']);

// Validate quantity
if ($quantity < 1) {
    $quantity = 1;
} elseif ($quantity > 99) {
    $quantity = 99;
}

// Get current quantity for rollback if needed
$stmt = $conn->prepare("SELECT quantity FROM cart WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $cart_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại trong giỏ hàng']);
    exit;
}

$current_item = $result->fetch_assoc();
$previous_quantity = $current_item['quantity'];

// Update quantity
$stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param("iii", $quantity, $cart_id, $user_id);

if ($stmt->execute()) {
    // Get updated cart count - replacing the problematic function call
    $cart_count = 0; // Default value
    
    // Count items in cart directly
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    if ($count_stmt) {
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_row = $count_result->fetch_assoc()) {
            $cart_count = (int)$count_row['total'] ?: 0; // Use 0 if null
        }
    }
    
    echo json_encode([
        'success' => true, 
        'cart_count' => $cart_count,
        'message' => 'Cập nhật số lượng thành công'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi khi cập nhật số lượng',
        'previous_quantity' => $previous_quantity
    ]);
}
?>