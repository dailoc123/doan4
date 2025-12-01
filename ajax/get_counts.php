<?php
session_start();
require_once '../db.php';

// Set proper content type
header('Content-Type: application/json; charset=utf-8');

// Initialize response
$response = [
    'cart_count' => 0,
    'wishlist_count' => 0
];

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    try {
        // Get cart count
        $cartStmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        if ($cartStmt) {
            $cartStmt->bind_param("i", $userId);
            $cartStmt->execute();
            $cartResult = $cartStmt->get_result();
            if ($cartResult) {
                $cartRow = $cartResult->fetch_assoc();
                $response['cart_count'] = intval($cartRow['total'] ?? 0);
            }
            $cartStmt->close();
        }
        
        // Get wishlist count
        $wishlistStmt = $conn->prepare("SELECT COUNT(*) as total FROM wishlist WHERE user_id = ?");
        if ($wishlistStmt) {
            $wishlistStmt->bind_param("i", $userId);
            $wishlistStmt->execute();
            $wishlistResult = $wishlistStmt->get_result();
            if ($wishlistResult) {
                $wishlistRow = $wishlistResult->fetch_assoc();
                $response['wishlist_count'] = intval($wishlistRow['total'] ?? 0);
            }
            $wishlistStmt->close();
        }
        
    } catch (Exception $e) {
        error_log("Error getting counts: " . $e->getMessage());
    }
}

// Return JSON response
echo json_encode($response);

// Close connection
if (isset($conn)) {
    $conn->close();
}
?>