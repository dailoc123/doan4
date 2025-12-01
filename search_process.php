<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

// Xử lý lấy lịch sử tìm kiếm
if (isset($_GET['action']) && $_GET['action'] == 'get_history') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['history' => []]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT id, search_term FROM search_history 
            WHERE user_id = ? 
            ORDER BY last_searched DESC 
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode(['history' => $history]);
    exit;
}

// Xử lý xóa lịch sử tìm kiếm
if (isset($_GET['action']) && $_GET['action'] == 'delete_history' && isset($_GET['id'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $history_id = (int)$_GET['id'];
    
    $sql = "DELETE FROM search_history WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $history_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Xử lý tìm kiếm sản phẩm
if (isset($_GET['term'])) {
    $term = '%' . $_GET['term'] . '%';
    
    $sql = "SELECT id, name, price, image FROM products WHERE name LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'image' => $row['image']
        ];
    }
    
    echo json_encode(['products' => $products]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
