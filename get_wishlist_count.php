<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Nếu chưa đăng nhập: trả về số lượng từ session (guest_wishlist)
if (!isset($_SESSION['user_id'])) {
    $guestCount = isset($_SESSION['guest_wishlist']) ? count($_SESSION['guest_wishlist']) : 0;
    echo json_encode(['success' => true, 'count' => $guestCount]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'count' => (int)$row['count']]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}

$conn->close();
?>