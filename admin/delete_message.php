<?php
session_start();
require '../db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit();
}

// Lấy dữ liệu từ request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tin nhắn không hợp lệ']);
    exit();
}

$id = mysqli_real_escape_string($conn, $data['id']);

// Xóa tin nhắn từ database
$sql = "DELETE FROM contact_messages WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>