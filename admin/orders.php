<?php
session_start();
require '../db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Cập nhật trạng thái đơn hàng (sửa lại để kiểm tra prepare/execute)
if (isset($_POST['update_status'])) {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $status = $_POST['status'] ?? '';
    $note = $_POST['note'] ?? '';

    if ($order_id <= 0 || $status === '') {
        header("Location: orders.php?success=0&msg=invalid");
        exit;
    }

    // Start transaction to ensure consistency
    $conn->begin_transaction();

    $update_sql = "UPDATE orders SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    if ($update_stmt === false) {
        $conn->rollback();
        error_log("orders.php - prepare update failed: " . $conn->error);
        header("Location: orders.php?success=0&msg=sql_error");
        exit;
    }
    $update_stmt->bind_param("si", $status, $order_id);
    if (!$update_stmt->execute()) {
        error_log("orders.php - update execute failed: " . $update_stmt->error);
        $update_stmt->close();
        $conn->rollback();
        header("Location: orders.php?success=0&msg=exec_error");
        exit;
    }
    $update_stmt->close();

    // Thêm vào lịch sử trạng thái
    $history_sql = "INSERT INTO order_status_history (order_id, status, note, created_at) VALUES (?, ?, ?, NOW())";
    $history_stmt = $conn->prepare($history_sql);
    if ($history_stmt === false) {
        error_log("orders.php - prepare history failed: " . $conn->error);
        $conn->rollback();
        header("Location: orders.php?success=0&msg=sql_error2");
        exit;
    }
    $history_stmt->bind_param("iss", $order_id, $status, $note);
    if (!$history_stmt->execute()) {
        error_log("orders.php - history execute failed: " . $history_stmt->error);
        $history_stmt->close();
        $conn->rollback();
        header("Location: orders.php?success=0&msg=exec_error2");
        exit;
    }
    $history_stmt->close();

    // Nếu completed -> có thể cập nhật inventory/history hoặc các tác vụ khác ở đây
    if ($status === 'completed') {
        // ví dụ: đánh dấu completed time, cập nhật thống kê nếu cần
    }

    $conn->commit();

    // Chuyển về trang thống kê để admin thấy cập nhật (stats.php sẽ đọc từ orders)
    header("Location: stats.php");
    exit;
}

// Định nghĩa số lượng đơn hàng mỗi trang
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Tìm kiếm đơn hàng (theo ID hoặc tên khách hàng)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_sql = "";
if ($search) {
    $search_sql = "WHERE orders.id LIKE '%$search%' OR users.name LIKE '%$search%'";
}

// Lấy danh sách đơn hàng kèm tên khách hàng với phân trang
$sql = "SELECT orders.*, users.name AS customer_name, 
               orders.total_price AS total_amount
        FROM orders 
        JOIN users ON orders.user_id = users.id
        $search_sql
        ORDER BY orders.created_at DESC
        LIMIT $offset, $items_per_page";

$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Lỗi SQL: " . mysqli_error($conn));
}

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

// Tính toán tổng số trang
$total_sql = "SELECT COUNT(*) FROM orders JOIN users ON orders.user_id = users.id $search_sql";
$total_result = mysqli_query($conn, $total_sql);
$total_row = mysqli_fetch_row($total_result);
$total_orders = $total_row[0];
$total_pages = ceil($total_orders / $items_per_page);

// Hiển thị thông báo thành công nếu có
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'Cập nhật trạng thái đơn hàng thành công!';
}

$page_title = 'Quản lý Đơn hàng';
include 'includes/header.php';
?>

<!-- Link đến CSS file -->
<link rel="stylesheet" href="assets/css/admin-style.css">

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-shopping-cart me-3"></i>
        Quản lý Đơn hàng
    </h1>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <?php
    $total_orders_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders"))['count'];
    $pending_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'"))['count'];
    $completed_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'completed'"))['count'];
    $total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_price) as total FROM orders WHERE status = 'completed'"))['total'] ?? 0;
    ?>
    
    <div class="stat-item">
        <div class="stat-number"><?= number_format($total_orders_count) ?></div>
        <div class="stat-label">Tổng đơn hàng</div>
    </div>
    
    <div class="stat-item">
        <div class="stat-number" style="color: var(--warning-color);"><?= number_format($pending_orders) ?></div>
        <div class="stat-label">Đang xử lý</div>
    </div>
    
    <div class="stat-item">
        <div class="stat-number" style="color: var(--success-color);"><?= number_format($completed_orders) ?></div>
        <div class="stat-label">Hoàn thành</div>
    </div>
    
    <div class="stat-item">
        <div class="stat-number" style="color: var(--info-color);"><?= number_format($total_revenue) ?> VNĐ</div>
        <div class="stat-label">Doanh thu</div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search Box -->
<div class="card">
    <div class="card-header">
        <h4 class="mb-0">
            <i class="fas fa-search text-primary me-2"></i>
            Tìm kiếm đơn hàng
        </h4>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="input-group">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Tìm kiếm đơn hàng theo ID hoặc tên khách hàng..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Tìm kiếm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Orders List -->
<?php if (!empty($orders)): ?>
    <?php foreach ($orders as $order): ?>
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            Đơn hàng #<?= $order['id']; ?>
                        </h5>
                        <small class="text-muted">
                            Khách hàng: <strong><?= htmlspecialchars($order['customer_name']); ?></strong> | 
                            Ngày đặt: <?= date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-<?php 
                            switch($order['status']) {
                                case 'pending': echo 'warning'; break;
                                case 'processing': echo 'info'; break;
                                case 'shipping': echo 'primary'; break;
                                case 'completed': echo 'success'; break;
                                case 'cancelled': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                        ?> fs-6">
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
                        <h5 class="mb-0 text-success">
                            <?= number_format($order['total_amount']) ?> VNĐ
                        </h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php
                // Lấy sản phẩm trong đơn hàng
                $order_id = $order['id'];
                $item_sql = "SELECT oi.*, p.name AS product_name, p.discount_price 
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             WHERE oi.order_id = $order_id";
                $item_result = mysqli_query($conn, $item_sql);
                ?>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-box me-2"></i>Sản phẩm</th>
                                <th><i class="fas fa-sort-numeric-up me-2"></i>Số lượng</th>
                                <th><i class="fas fa-dollar-sign me-2"></i>Giá</th>
                                <th><i class="fas fa-calculator me-2"></i>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($item_result && mysqli_num_rows($item_result) > 0): ?>
                                <?php while ($item = mysqli_fetch_assoc($item_result)): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td><?= $item['quantity']; ?></td>
                                        <td>
                                            <?php if ($item['discount_price'] > 0): ?>
                                                <del class="text-muted"><?= number_format($item['price'], 0, ',', '.'); ?> VNĐ</del><br>
                                                <span class="text-danger fw-bold"><?= number_format($item['discount_price'], 0, ',', '.'); ?> VNĐ</span>
                                            <?php else: ?>
                                                <span class="fw-bold"><?= number_format($item['price'], 0, ',', '.'); ?> VNĐ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $effective_price = $item['discount_price'] > 0 ? $item['discount_price'] : $item['price'];
                                            $subtotal = $effective_price * $item['quantity'];
                                            echo '<span class="fw-bold text-success">' . number_format($subtotal, 0, ',', '.') . ' VNĐ</span>'; 
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
                                        Không có sản phẩm nào!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php
                // Lấy lịch sử trạng thái
                $history_sql = "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC LIMIT 1";
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->bind_param("i", $order_id);
                $history_stmt->execute();
                $history_result = $history_stmt->get_result();
                $last_status = $history_result->fetch_assoc();
                ?>
                
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div class="d-flex gap-2">
                        <a href="edit_order.php?id=<?= $order['id']; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit me-1"></i> Cập nhật trạng thái
                        </a>
                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#historyModal<?= $order['id']; ?>">
                            <i class="fas fa-history me-1"></i> Xem lịch sử
                        </button>
                    </div>
                    
                    <?php if ($last_status): ?>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Cập nhật cuối: <?= date('d/m/Y H:i', strtotime($last_status['created_at'])); ?>
                            <?php if (!empty($last_status['note'])): ?>
                                - <em>"<?= htmlspecialchars($last_status['note']); ?>"</em>
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Modal hiển thị lịch sử trạng thái -->
        <div class="modal fade" id="historyModal<?= $order['id']; ?>" tabindex="-1" aria-labelledby="historyModalLabel<?= $order['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="historyModalLabel<?= $order['id']; ?>">
                            <i class="fas fa-history me-2"></i>Lịch sử trạng thái đơn hàng #<?= $order['id']; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php
                        $full_history_sql = "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC";
                        $full_history_stmt = $conn->prepare($full_history_sql);
                        $full_history_stmt->bind_param("i", $order_id);
                        $full_history_stmt->execute();
                        $full_history_result = $full_history_stmt->get_result();
                        
                        if ($full_history_result->num_rows > 0):
                        ?>
                            <div class="timeline">
                                <?php while ($history = $full_history_result->fetch_assoc()): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-<?php 
                                            switch($history['status']) {
                                                case 'pending': echo 'warning'; break;
                                                case 'processing': echo 'info'; break;
                                                case 'shipping': echo 'primary'; break;
                                                case 'completed': echo 'success'; break;
                                                case 'cancelled': echo 'danger'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>"></div>
                                        <div class="timeline-content">
                                            <h6 class="mb-1">
                                                <?php 
                                                switch($history['status']) {
                                                    case 'pending': echo 'Đang xử lý'; break;
                                                    case 'processing': echo 'Đang chuẩn bị hàng'; break;
                                                    case 'shipping': echo 'Đang giao hàng'; break;
                                                    case 'completed': echo 'Hoàn thành'; break;
                                                    case 'cancelled': echo 'Đã hủy'; break;
                                                    default: echo ucfirst($history['status']);
                                                }
                                                ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($history['created_at'])); ?>
                                            </small>
                                            <?php if (!empty($history['note'])): ?>
                                                <div class="mt-2">
                                                    <em class="text-muted">"<?= htmlspecialchars($history['note']); ?>"</em>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-history fa-3x mb-3 d-block"></i>
                                <p>Chưa có lịch sử trạng thái</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Đóng
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($search) ?>" 
                           class="page-link"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">Không có đơn hàng nào!</h4>
            <p class="text-muted">Chưa có đơn hàng nào trong hệ thống.</p>
        </div>
    </div>
<?php endif; ?>

<?php
$extra_js = <<<EOT
<script>
// Form validation và loading states
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            submitBtn.disabled = true;
        }
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('show')) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }
    });
}, 5000);

// Smooth scroll for pagination
document.querySelectorAll('.pagination a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.href;
        
        // Add loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Navigate after short delay
        setTimeout(() => {
            window.location.href = url;
        }, 300);
    });
});
</script>
EOT;

include 'includes/footer.php';
?>