<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Lấy cart items với thông tin sản phẩm
    $stmt = $conn->prepare("
        SELECT 
            c.id as cart_id,
            c.quantity,
            p.id as product_id,
            p.name as product_name,
            p.price,
            p.image,
            col.name as color_name,
            s.name as size_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN colors col ON c.color_id = col.id
        LEFT JOIN sizes s ON c.size_id = s.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cart_items = [];
    $total = 0;
    
    while ($row = $result->fetch_assoc()) {
        $subtotal = $row['price'] * $row['quantity'];
        $total += $subtotal;
        
        $cart_items[] = [
            'cart_id' => $row['cart_id'],
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'price' => $row['price'],
            'quantity' => $row['quantity'],
            'subtotal' => $subtotal,
            'image' => 'admin/' . $row['image'],
            'color_name' => $row['color_name'],
            'size_name' => $row['size_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cart_items' => $cart_items,
        'total' => $total,
        'count' => count($cart_items)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}

$conn->close();
?>