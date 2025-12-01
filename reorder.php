<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['order_id'])) {
    header('Location: order_history.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'];

// Verify order belongs to user
$stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: order_history.php');
    exit;
}

// Get order items
$stmt = $conn->prepare("SELECT product_id, color_id, size_id, quantity FROM order_items WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Begin transaction
$conn->begin_transaction();

try {
    // Clear existing cart items
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Add items to cart
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, color_id, size_id, quantity) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($items as $item) {
        // Verify product still exists and is active
        $check_stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
        $check_stmt->bind_param("i", $item['product_id']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $stmt->bind_param("iiiii", 
                $user_id, 
                $item['product_id'], 
                $item['color_id'], 
                $item['size_id'], 
                $item['quantity']
            );
            $stmt->execute();
        }
    }

    // Commit transaction
    $conn->commit();
    
    // Redirect to cart
    header('Location: cart.php?reorder=success');
    exit;

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Log error
    error_log("Reorder failed: " . $e->getMessage());
    
    // Redirect with error
    header('Location: order_history.php?error=reorder_failed');
    exit;
}
?>