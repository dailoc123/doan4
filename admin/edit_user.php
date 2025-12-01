<?php
// admin/edit_user.php
session_start();
require '../db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Truy cập bị từ chối!");
}

// Lấy ID người dùng từ URL
$id = $_GET['id'] ?? 0;
$id = intval($id); // Ép kiểu để tránh lỗi SQL Injection

// Kiểm tra nếu không có id trong URL, chuyển hướng về trang danh sách người dùng
if ($id == 0) {
    header("Location: users.php");
    exit;
}

// Lấy thông tin người dùng
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);  // Sử dụng prepare()
if ($stmt === false) {
    die('Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error . ' Câu lệnh SQL: ' . $sql);
}

$stmt->bind_param("i", $id); // Truyền tham số id với kiểu dữ liệu là integer (i)
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("Người dùng không tồn tại.");
}

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        // Cập nhật câu lệnh SQL với password
        $sql = "UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die('Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error . ' Câu lệnh SQL: ' . $sql);
        }
        $stmt->bind_param("ssssi", $username, $email, $role, $password, $id);
    } else {
        // Cập nhật câu lệnh SQL mà không có thay đổi mật khẩu
        $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die('Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error . ' Câu lệnh SQL: ' . $sql);
        }
        $stmt->bind_param("sssi", $username, $email, $role, $id);
    }

    // Thực thi câu lệnh
    if ($stmt->execute()) {
        $success = "Cập nhật thành công!";
        header("refresh:1;url=users.php"); // Tự động chuyển trang sau 1 giây
    } else {
        die("Lỗi thực thi câu lệnh SQL: " . $stmt->error);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa người dùng</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="header-content">
                    <h1><i class="fas fa-user-edit"></i> Chỉnh sửa người dùng</h1>
                    <a href="users.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Quay lại danh sách
                    </a>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-cog"></i> Thông tin người dùng
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tên người dùng</label>
                                            <input type="text" name="username" class="form-control" 
                                                   value="<?= htmlspecialchars($user['name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" 
                                                   value="<?= htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Vai trò</label>
                                            <select name="role" class="form-select">
                                                <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                <option value="user" <?= ($user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Đổi mật khẩu (để trống nếu không đổi)</label>
                                            <input type="password" name="password" class="form-control" 
                                                   placeholder="Nhập mật khẩu mới">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Lưu thay đổi
                                    </button>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Hủy bỏ
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
