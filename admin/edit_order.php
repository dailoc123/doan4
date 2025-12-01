<?php
session_start();
require '../db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Truy cập bị từ chối!");
}

// Kiểm tra ID đơn hàng
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = (int)$_GET['id'];

// Lấy thông tin đơn hàng
$sql = "SELECT orders.*, users.name AS customer_name 
        FROM orders 
        JOIN users ON orders.user_id = users.id
        WHERE orders.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: orders.php');
    exit;
}

$order = $result->fetch_assoc();

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'];
    $note = $_POST['note'] ?? '';
    
    // Cập nhật trạng thái đơn hàng
    $update_sql = "UPDATE orders SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $order_id);
    
    if ($update_stmt->execute()) {
        // Thêm vào lịch sử trạng thái
        $history_sql = "INSERT INTO order_status_history (order_id, status, note, created_at) VALUES (?, ?, ?, NOW())";
        $history_stmt = $conn->prepare($history_sql);
        $history_stmt->bind_param("iss", $order_id, $new_status, $note);
        $history_stmt->execute();
        
        // Nếu trạng thái là "completed", chuyển hướng đến trang thống kê để cập nhật doanh thu
        if ($new_status === 'completed') {
            // Trừ tồn kho cho các sản phẩm trong đơn hàng
            $items_query = "SELECT oi.product_id, oi.quantity 
                            FROM order_items oi 
                            WHERE oi.order_id = ?";
            $items_stmt = $conn->prepare($items_query);
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                
                // Kiểm tra tồn kho hiện tại
                $inventory_check = $conn->prepare("SELECT quantity FROM inventory WHERE product_id = ?");
                $inventory_check->bind_param("i", $product_id);
                $inventory_check->execute();
                $inventory_result = $inventory_check->get_result();
                
                if ($inventory_result->num_rows > 0) {
                    $current_inventory = $inventory_result->fetch_assoc();
                    $current_quantity = $current_inventory['quantity'];
                    
                    // Chỉ trừ nếu tồn kho đủ
                    if ($current_quantity >= $quantity) {
                        // Cập nhật tồn kho như trên
                    } else {
                        // Ghi log cảnh báo tồn kho không đủ
                        $warning_note = "CẢNH BÁO: Tồn kho không đủ cho sản phẩm ID " . $product_id . " - Đơn hàng #" . $order_id;
                        $insert_warning = $conn->prepare("INSERT INTO inventory_history (product_id, type, quantity, note, created_at) VALUES (?, 'warning', 0, ?, NOW())");
                        $insert_warning->bind_param("is", $product_id, $warning_note);
                        $insert_warning->execute();
                    }
                }
            }
            
            // Kiểm tra xem bảng statistics đã tồn tại chưa
            $check_table = $conn->query("SHOW TABLES LIKE 'statistics'");
            if ($check_table->num_rows == 0) {
                // Tạo bảng statistics nếu chưa tồn tại
                $create_table = "CREATE TABLE IF NOT EXISTS statistics (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    revenue DECIMAL(10,2) NOT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (order_id) REFERENCES orders(id)
                )";
                $conn->query($create_table);
            }
            
            // Thêm trực tiếp vào bảng statistics
            $get_order = "SELECT total_price FROM orders WHERE id = ?";
            $get_stmt = $conn->prepare($get_order);
            $get_stmt->bind_param("i", $order_id);
            $get_stmt->execute();
            $order_result = $get_stmt->get_result();
            
            if ($order_row = $order_result->fetch_assoc()) {
                $revenue = $order_row['total_price'];
                
                // Kiểm tra xem đơn hàng đã được thêm vào thống kê chưa
                $check_sql = "SELECT id FROM statistics WHERE order_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $order_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    // Thêm vào bảng thống kê
                    $insert_sql = "INSERT INTO statistics (order_id, revenue, created_at) VALUES (?, ?, NOW())";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("id", $order_id, $revenue);
                    $insert_stmt->execute();
                }
            }
            
            header("Location: stats.php?success=1&completed_order=" . $order_id);
            exit;
        }
        
        // Chuyển hướng về trang orders với thông báo thành công
        header('Location: orders.php?success=1');
        exit;
    } else {
        $error = "Không thể cập nhật trạng thái đơn hàng!";
    }
}

// Lấy lịch sử trạng thái
$history_sql = "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $order_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$status_history = [];
while ($row = $history_result->fetch_assoc()) {
    $status_history[] = $row;
}
?>
<?php
// Thiết lập tiêu đề trang và CSS bổ sung cho layout admin chung
$page_title = 'Cập nhật trạng thái đơn hàng #' . $order_id;
$extra_css = '<link rel="stylesheet" href="assets/css/admin-style.css">';
include 'includes/header.php';
?>

<div class="page-header">
    <div class="header-content d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-edit"></i> Cập nhật trạng thái đơn hàng #<?= $order_id ?></h1>
        <a href="orders.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách
        </a>
    </div>
</div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-6">
                    <!-- Thông tin đơn hàng -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i> Thông tin đơn hàng
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-group">
                                <div class="info-item">
                                    <label>Khách hàng:</label>
                                    <span><?= htmlspecialchars($order['customer_name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Tổng tiền:</label>
                                    <span class="text-success fw-bold"><?= number_format($order['total_price'], 0, ',', '.') ?> VND</span>
                                </div>
                                <div class="info-item">
                                    <label>Ngày đặt:</label>
                                    <span><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Trạng thái hiện tại:</label>
                                    <span class="badge 
                                        <?php 
                                        switch($order['status']) {
                                            case 'pending': echo 'bg-warning'; break;
                                            case 'processing': echo 'bg-info'; break;
                                            case 'shipping': echo 'bg-primary'; break;
                                            case 'completed': echo 'bg-success'; break;
                                            case 'cancelled': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php 
                                        switch($order['status']) {
                                            case 'pending': echo 'Đang xử lý'; break;
                                            case 'processing': echo 'Đang chuẩn bị hàng'; break;
                                            case 'shipping': echo 'Đang giao hàng'; break;
                                            case 'completed': echo 'Hoàn thành'; break;
                                            case 'cancelled': echo 'Đã hủy'; break;
                                            default: echo ucfirst($order['status']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form cập nhật trạng thái -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-edit"></i> Cập nhật trạng thái
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Trạng thái mới</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="">-- Chọn trạng thái --</option>
                                        <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Đang xử lý</option>
                                        <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Đang chuẩn bị hàng</option>
                                        <option value="shipping" <?= $order['status'] == 'shipping' ? 'selected' : '' ?>>Đang giao hàng</option>
                                        <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                                        <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="note" class="form-label">Ghi chú (tùy chọn)</label>
                                    <textarea name="note" id="note" class="form-control" rows="3" placeholder="Nhập ghi chú về việc thay đổi trạng thái"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Cập nhật trạng thái
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <!-- Lịch sử trạng thái -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history"></i> Lịch sử trạng thái
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($status_history)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <p>Chưa có lịch sử trạng thái</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($status_history as $index => $history): ?>
                                        <div class="timeline-item <?= $index === 0 ? 'active' : '' ?>">
                                            <div class="timeline-marker">
                                                <i class="fas fa-circle"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="timeline-header">
                                                    <span class="timeline-date">
                                                        <?= date('d/m/Y H:i', strtotime($history['created_at'])) ?>
                                                    </span>
                                                </div>
                                                <div class="timeline-body">
                                                    <div class="status-badge status-<?= $history['status'] ?>">
                                                        <?php 
                                                        switch($history['status']) {
                                                            case 'pending': echo 'Đơn hàng đã được tiếp nhận'; break;
                                                            case 'processing': echo 'Đang chuẩn bị hàng'; break;
                                                            case 'shipping': echo 'Đang giao hàng'; break;
                                                            case 'completed': echo 'Đơn hàng đã hoàn thành'; break;
                                                            case 'cancelled': echo 'Đơn hàng đã bị hủy'; break;
                                                            default: echo ucfirst($history['status']);
                                                        }
                                                        ?>
                                                    </div>
                                                    <?php if (!empty($history['note'])): ?>
                                                        <div class="timeline-note">
                                                            <i class="fas fa-sticky-note"></i>
                                                            <?= htmlspecialchars($history['note']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
<?php include 'includes/footer.php'; ?>