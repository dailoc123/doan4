<?php
// admin/delete_user.php
session_start();
require '../db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Truy cập bị từ chối!");
}

$id = $_GET['id'] ?? 0;

if ($id) {
    // Chuẩn bị câu lệnh SQL đúng với MySQLi
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    
    if (!$stmt) {
        die("Lỗi chuẩn bị truy vấn: " . $conn->error);  // Debug lỗi nếu prepare thất bại
    }

    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        die("Lỗi thực thi truy vấn: " . $stmt->error);
    }

    $stmt->close();
}

$conn->close();
header("Location: users.php");
exit;
?>
