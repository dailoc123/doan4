<?php
session_start();
require 'db.php';

// Set proper content type and charset
header('Content-Type: application/json; charset=utf-8');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'cart_count' => 0
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng';
    echo json_encode($response);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Check if product ID is provided
if (isset($_GET['id'])) {
    $productId = $_GET['id'];
    $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = isset($_POST['id']) ? $_POST['id'] : (isset($_POST['product_id']) ? $_POST['product_id'] : 0);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
} else {
    $response['message'] = 'Không tìm thấy sản phẩm';
    echo json_encode($response);
    exit;
}

// Validate quantity
if ($quantity <= 0) {
    $quantity = 1;
}

// Check if product exists
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'Sản phẩm không tồn tại';
    echo json_encode($response);
    exit;
}

// Get product details
$product = $result->fetch_assoc();

// Check if product is already in cart
$stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $userId, $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Product already in cart, update quantity
    $row = $result->fetch_assoc();
    $newQuantity = $row['quantity'] + $quantity;
    
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $newQuantity, $row['id']);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Đã cập nhật số lượng sản phẩm trong giỏ hàng';
    } else {
        $response['message'] = 'Không thể cập nhật giỏ hàng: ' . $conn->error;
    }
} else {
    // Add new product to cart
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $userId, $productId, $quantity);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Sản phẩm đã được thêm vào giỏ hàng';
    } else {
        $response['message'] = 'Không thể thêm sản phẩm vào giỏ hàng: ' . $conn->error;
    }
}

// Get updated cart count
$countStmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();
$response['cart_count'] = intval($countRow['total'] ?? 0);

// Update session cart count
$_SESSION['cart_count'] = $response['cart_count'];

// Return JSON response
echo json_encode($response);

// Close connection
$conn->close();
?>