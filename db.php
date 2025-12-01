<?php
$servername = "localhost";
$username = "root"; // Thay bằng user database của bạn
$password = ""; // Thay bằng mật khẩu nếu có
$database = "luxury_store"; // Thay bằng tên database của bạn

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4"); // Đảm bảo hỗ trợ tiếng Việt

// Thêm kết nối PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Kết nối PDO thất bại: " . $e->getMessage());
}
?>
