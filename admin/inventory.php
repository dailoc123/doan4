<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Xử lý nhập kho
if(isset($_POST['import'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $note = $_POST['note'];

    // Cập nhật số lượng trong inventory
    $check_query = "SELECT * FROM inventory WHERE product_id = $product_id";
    $check_result = mysqli_query($conn, $check_query);

    if(mysqli_num_rows($check_result) > 0) {
        mysqli_query($conn, "UPDATE inventory SET quantity = quantity + $quantity WHERE product_id = $product_id");
    } else {
        mysqli_query($conn, "INSERT INTO inventory (product_id, quantity) VALUES ($product_id, $quantity)");
    }

    // Thêm lịch sử nhập kho
    mysqli_query($conn, "INSERT INTO inventory_history (product_id, type, quantity, note) 
                        VALUES ($product_id, 'import', $quantity, '$note')");
}

// Xử lý xuất kho
if(isset($_POST['export'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $note = $_POST['note'];

    mysqli_query($conn, "UPDATE inventory SET quantity = quantity - $quantity WHERE product_id = $product_id");
    mysqli_query($conn, "INSERT INTO inventory_history (product_id, type, quantity, note) 
                        VALUES ($product_id, 'export', $quantity, '$note')");
}

// Lấy danh sách tồn kho
$query = "SELECT i.*, p.name as product_name, p.image, 
          (SELECT COUNT(*) FROM inventory_history WHERE product_id = i.product_id) as history_count
          FROM inventory i 
          LEFT JOIN products p ON i.product_id = p.id
          ORDER BY i.quantity ASC";
$result = mysqli_query($conn, $query);

$page_title = "Quản lý Kho hàng";
include 'includes/header.php';
?>

<!-- Link đến CSS file -->
<link rel="stylesheet" href="assets/css/admin-style.css">

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-warehouse me-3"></i>
        Quản lý Kho hàng
    </h1>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <?php
    $total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM inventory"))['count'];
    $low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM inventory WHERE quantity <= min_stock"))['count'];
    $total_quantity = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as total FROM inventory"))['total'];
    ?>
    
    <div class="stat-item">
        <div class="stat-number"><?= number_format($total_products) ?></div>
        <div class="stat-label">Tổng sản phẩm</div>
    </div>
    
    <div class="stat-item">
        <div class="stat-number" style="color: var(--danger-color);"><?= number_format($low_stock) ?></div>
        <div class="stat-label">Sắp hết hàng</div>
    </div>
    
    <div class="stat-item">
        <div class="stat-number" style="color: var(--success-color);"><?= number_format($total_quantity ?? 0) ?></div>
        <div class="stat-label">Tổng tồn kho</div>
    </div>
</div>

<!-- Form nhập/xuất kho -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus-circle text-success me-2"></i>
                    Nhập kho
                </h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group mb-3">
                        <label class="form-label">Sản phẩm</label>
                        <select name="product_id" class="form-control" required>
                            <option value="">-- Chọn sản phẩm --</option>
                            <?php
                            $products = mysqli_query($conn, "SELECT * FROM products ORDER BY name");
                            while($product = mysqli_fetch_assoc($products)) {
                                echo "<option value='{$product['id']}'>{$product['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Số lượng</label>
                        <input type="number" name="quantity" class="form-control" min="1" required placeholder="Nhập số lượng...">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Ghi chú về lô hàng..."></textarea>
                    </div>
                    <button type="submit" name="import" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Nhập kho
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-minus-circle text-warning me-2"></i>
                    Xuất kho
                </h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group mb-3">
                        <label class="form-label">Sản phẩm</label>
                        <select name="product_id" class="form-control" required>
                            <option value="">-- Chọn sản phẩm --</option>
                            <?php
                            mysqli_data_seek($products, 0);
                            while($product = mysqli_fetch_assoc($products)) {
                                echo "<option value='{$product['id']}'>{$product['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Số lượng</label>
                        <input type="number" name="quantity" class="form-control" min="1" required placeholder="Nhập số lượng...">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Lý do xuất kho..."></textarea>
                    </div>
                    <button type="submit" name="export" class="btn btn-warning w-100">
                        <i class="fas fa-minus me-2"></i>Xuất kho
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bảng tồn kho -->
<div class="card">
    <div class="card-header">
        <h4 class="mb-0">
            <i class="fas fa-list-alt text-primary me-2"></i>
            Danh sách tồn kho
        </h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="fas fa-box me-2"></i>Sản phẩm</th>
                        <th><i class="fas fa-image me-2"></i>Hình ảnh</th>
                        <th><i class="fas fa-cubes me-2"></i>Tồn kho</th>
                        <th><i class="fas fa-chart-line me-2"></i>Trạng thái</th>
                        <th><i class="fas fa-history me-2"></i>Lịch sử</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['product_name']) ?></strong>
                            </td>
                            <td>
                                <img src="<?= htmlspecialchars($row['image']) ?>" 
                                     class="product-image"
                                     alt="<?= htmlspecialchars($row['product_name']) ?>"
                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td>
                                <span class="badge bg-success"><?= number_format($row['quantity']) ?></span>
                            </td>
                            <td>
                                <?php if($row['quantity'] <= $row['min_stock']): ?>
                                    <span class="text-danger">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Cần nhập thêm
                                    </span>
                                <?php else: ?>
                                    <span class="text-success">
                                        <i class="fas fa-check-circle"></i> 
                                        Đủ hàng
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-info btn-sm" 
                                        onclick="showHistory(<?= $row['product_id'] ?>)">
                                    <i class="fas fa-history me-1"></i> 
                                    <?= $row['history_count'] ?> giao dịch
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$extra_js = <<<EOT
<script>
function showHistory(productId) {
    // Tạo modal hiển thị lịch sử
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lịch sử nhập xuất</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Xóa modal khi đóng
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
    
    // Giả lập tải dữ liệu
    setTimeout(() => {
        modal.querySelector('.modal-body').innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Chức năng lịch sử chi tiết đang được phát triển.
            </div>
        `;
    }, 1000);
}

// Thêm hiệu ứng loading cho form
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
        submitBtn.disabled = true;
    });
});

// Validation form
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', function() {
        if (this.value < 0) {
            this.value = 0;
        }
    });
});
</script>
EOT;

include 'includes/footer.php';
?>