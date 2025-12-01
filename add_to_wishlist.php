<?php
session_start();
require 'db.php';

// Set proper content type and charset
header('Content-Type: application/json; charset=utf-8');

// Check if product ID is provided
if (isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hỗ trợ cả form-urlencoded và JSON body
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if ($productId === 0) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json['product_id'])) {
                $productId = intval($json['product_id']);
            }
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm']);
    exit;
}

// Check if product exists
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
    exit;
}

// Nếu chưa đăng nhập: lưu wishlist trong session cho khách
if (!isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['guest_wishlist'])) {
        $_SESSION['guest_wishlist'] = [];
    }

    // Đã có trong wishlist của khách?
    if (in_array($productId, $_SESSION['guest_wishlist'], true)) {
        // Cập nhật đếm và trả về thành công để tránh popup lỗi
        $_SESSION['wishlist_count'] = count($_SESSION['guest_wishlist']);
        echo json_encode(['success' => true, 'message' => 'Sản phẩm đã có trong danh sách yêu thích', 'count' => $_SESSION['wishlist_count'], 'action' => 'already']);
        exit;
    }

    // Thêm vào wishlist của khách
    $_SESSION['guest_wishlist'][] = $productId;
    $_SESSION['wishlist_count'] = count($_SESSION['guest_wishlist']);

    echo json_encode(['success' => true, 'message' => 'Đã thêm vào danh sách yêu thích', 'count' => $_SESSION['wishlist_count'], 'action' => 'added']);
    exit;
}

// Người dùng đã đăng nhập: lưu vào DB như cũ
// Get user ID from session
$userId = $_SESSION['user_id'];

// Check if product is already in wishlist
$stmt = $conn->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $userId, $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm đã có trong danh sách yêu thích']);
    exit;
}

// Add product to wishlist
$stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
$stmt->bind_param("ii", $userId, $productId);

if ($stmt->execute()) {
    // Update wishlist count in session
    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $_SESSION['wishlist_count'] = $countRow['count'];
    
    echo json_encode(['success' => true, 'message' => 'Sản phẩm đã được thêm vào danh sách yêu thích', 'count' => $_SESSION['wishlist_count'], 'action' => 'added']);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể thêm sản phẩm vào danh sách yêu thích']);
}

// Close connection
$conn->close();
?>