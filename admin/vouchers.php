<?php
require_once '../db.php';
session_start();

// API: validate voucher (dùng AJAX từ checkout.php)
if (isset($_GET['action']) && $_GET['action'] === 'validate') {
    header('Content-Type: application/json; charset=utf-8');

    $code = trim($_GET['code'] ?? '');
    $total = floatval($_GET['total'] ?? 0);

    if ($code === '') {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá rỗng']);
        exit;
    }

    // Prepare safe select
    $stmt = $conn->prepare("SELECT id, code, discount, expiry_date, usage_limit, is_percent FROM vouchers WHERE code = ? LIMIT 1");
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Lỗi prepare: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $code);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi execute: ' . $stmt->error]);
        $stmt->close();
        exit;
    }

    $res = $stmt->get_result();
    if ($res === false) {
        echo json_encode(['success' => false, 'message' => 'Lỗi get_result: ' . $stmt->error]);
        $stmt->close();
        exit;
    }

    $voucher = $res->fetch_assoc();
    $stmt->close();

    if (!$voucher) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không tồn tại']);
        exit;
    }

    // Check expiry
    if (!empty($voucher['expiry_date']) && $voucher['expiry_date'] < date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Mã đã hết hạn']);
        exit;
    }

    // Check usage limit if present
    if (!empty($voucher['usage_limit'])) {
        $u_stmt = $conn->prepare("SELECT COUNT(*) as used FROM orders WHERE voucher_code = ?");
        if ($u_stmt) {
            $u_stmt->bind_param("s", $code);
            $u_stmt->execute();
            $u_res = $u_stmt->get_result();
            $used_row = $u_res ? $u_res->fetch_assoc() : ['used' => 0];
            $u_stmt->close();

            if ((int)$used_row['used'] >= (int)$voucher['usage_limit']) {
                echo json_encode(['success' => false, 'message' => 'Mã đã đạt giới hạn sử dụng']);
                exit;
            }
        }
    }

    // Compute discount (support fixed amount or percent if is_percent flag exists)
    $discount_amount = floatval($voucher['discount']);
    $new_total = $total;
    if (!empty($voucher['is_percent']) && $voucher['is_percent']) {
        $new_total = max(0, $total - ($total * $discount_amount / 100));
    } else {
        $new_total = max(0, $total - $discount_amount);
    }

    echo json_encode([
        'success' => true,
        'discount' => $discount_amount,
        'is_percent' => !empty($voucher['is_percent']) ? 1 : 0,
        'new_total' => $new_total,
        'message' => 'Áp dụng mã thành công'
    ]);
    exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";
$message_type = "";

// Xử lý thêm voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_voucher'])) {
    $code = trim($_POST['code']);
    $discount = (float)$_POST['discount'];
    $expiry_date = $_POST['expiry_date'];
    $min_order_amount = !empty($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : 0;
    $max_discount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
    $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
    $description = trim($_POST['description'] ?? '');
    $status = (int)$_POST['status'];

    // Kiểm tra mã voucher đã tồn tại
    $check_stmt = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
    $check_stmt->bind_param("s", $code);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "Mã voucher đã tồn tại!";
        $message_type = "danger";
    } else {
        $stmt = $conn->prepare("INSERT INTO vouchers (code, discount, expiry_date, min_order_amount, max_discount, usage_limit, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sdsddiis", $code, $discount, $expiry_date, $min_order_amount, $max_discount, $usage_limit, $description, $status);
        
        if ($stmt->execute()) {
            $message = "Đã thêm voucher thành công!";
            $message_type = "success";
        } else {
            $message = "Lỗi khi thêm voucher: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Đếm tổng số voucher
$count_sql = "SELECT COUNT(*) as total FROM vouchers";
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(code LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($where_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_vouchers = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_vouchers / $limit);
$count_stmt->close();

// Kiểm tra xem cột 'status' có tồn tại trong bảng vouchers không
$col_check = $conn->query("SHOW COLUMNS FROM vouchers LIKE 'status'");
$status_exists = ($col_check && $col_check->num_rows > 0);

// Tạo biểu thức voucher_status an toàn (không tham chiếu v.status nếu cột không tồn tại)
if ($status_exists) {
    $status_case = "CASE 
            WHEN v.expiry_date < CURDATE() THEN 'expired'
            WHEN v.status = 0 THEN 'inactive'
            ELSE 'active'
        END as voucher_status";
} else {
    $status_case = "CASE 
            WHEN v.expiry_date < CURDATE() THEN 'expired'
            ELSE 'active'
        END as voucher_status";
}

// Xây dựng truy vấn chính
$sql = "SELECT v.*, 
        $status_case,
        COALESCE(usage_count.used, 0) as times_used
        FROM vouchers v
        LEFT JOIN (
            SELECT voucher_code, COUNT(*) as used 
            FROM orders 
            WHERE voucher_code IS NOT NULL 
            GROUP BY voucher_code
        ) usage_count ON v.code = usage_count.voucher_code";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";

// Thêm tham số limit/offset vào mảng
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("vouchers.php - prepare failed: " . $conn->error . " | SQL: " . $sql);
    die("Lỗi SQL khi chuẩn bị câu lệnh: " . htmlspecialchars($conn->error));
}

if (!empty($params)) {
    // Sử dụng trả kết quả bind_param bằng call_user_func_array để tương thích PHP < 5.6
    $bind_names[] = $types;
    foreach ($params as $key => $value) {
        $bind_names[] = &$params[$key];
    }
    $bindOk = call_user_func_array([$stmt, 'bind_param'], $bind_names);
    if ($bindOk === false) {
        error_log("vouchers.php - bind_param failed: " . $stmt->error);
        $stmt->close();
        die("Lỗi bind_param: " . htmlspecialchars($stmt->error));
    }
}

if ($stmt->execute() === false) {
    error_log("vouchers.php - execute failed: " . $stmt->error);
    $stmt->close();
    die("Lỗi khi thực thi câu lệnh: " . htmlspecialchars($stmt->error));
}

// Lấy kết quả để dùng ở phần hiển thị
$result = $stmt->get_result();
if ($result === false) {
    error_log("vouchers.php - get_result failed: " . $stmt->error);
    $stmt->close();
    die("Lỗi khi lấy dữ liệu: " . htmlspecialchars($stmt->error));
}

$vouchers = $result;
$stmt->close();

// Thống kê voucher
$stats_sql = "SELECT 
    COUNT(*) as total_vouchers,
    SUM(CASE WHEN status = 1 AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) as active_vouchers,
    SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_vouchers,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_vouchers
    FROM vouchers";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$page_title = "Quản lý Voucher";
include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Thống kê voucher -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Tổng voucher</h6>
                        <h3 class="mb-0"><?= $stats['total_vouchers'] ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-ticket-alt fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Đang hoạt động</h6>
                        <h3 class="mb-0"><?= $stats['active_vouchers'] ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Đã hết hạn</h6>
                        <h3 class="mb-0"><?= $stats['expired_vouchers'] ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Tạm dừng</h6>
                        <h3 class="mb-0"><?= $stats['inactive_vouchers'] ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-pause-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form tìm kiếm và thêm voucher -->
<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Tìm kiếm & Thêm voucher</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVoucherModal">
                <i class="fas fa-plus me-2"></i>Thêm voucher mới
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" placeholder="Tìm kiếm theo mã voucher hoặc mô tả..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-fill">
                        <i class="fas fa-search me-2"></i>Tìm kiếm
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="vouchers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Danh sách voucher -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Danh sách voucher</h5>
    </div>
    <div class="card-body">
        <?php if ($vouchers->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã voucher</th>
                            <th>Mô tả</th>
                            <th>Giảm giá</th>
                            <th>Đơn tối thiểu</th>
                            <th>Giảm tối đa</th>
                            <th>Hạn sử dụng</th>
                            <th>Đã dùng</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($voucher = $vouchers->fetch_assoc()): ?>
                            <tr>
                                <td><?= $voucher['id'] ?></td>
                                <td>
                                    <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($voucher['code']) ?></code>
                                </td>
                                <td><?= htmlspecialchars($voucher['description'] ?: 'Không có mô tả') ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $voucher['discount'] ?>%</span>
                                </td>
                                <td>
                                    <?= $voucher['min_order_amount'] > 0 ? number_format($voucher['min_order_amount']) . ' VNĐ' : 'Không giới hạn' ?>
                                </td>
                                <td>
                                    <?= $voucher['max_discount'] ? number_format($voucher['max_discount']) . ' VNĐ' : 'Không giới hạn' ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($voucher['expiry_date'])) ?>
                                    <?php if ($voucher['voucher_status'] === 'expired'): ?>
                                        <br><small class="text-danger"><i class="fas fa-clock"></i> Đã hết hạn</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $voucher['times_used'] ?></span>
                                    <?php if ($voucher['usage_limit']): ?>
                                        <small class="text-muted">/ <?= $voucher['usage_limit'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = match($voucher['voucher_status']) {
                                        'active' => 'success',
                                        'expired' => 'warning',
                                        'inactive' => 'secondary',
                                        default => 'secondary'
                                    };
                                    $status_text = match($voucher['voucher_status']) {
                                        'active' => 'Hoạt động',
                                        'expired' => 'Hết hạn',
                                        'inactive' => 'Tạm dừng',
                                        default => 'Không xác định'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_voucher.php?id=<?= $voucher['id'] ?>" class="btn btn-outline-primary" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_voucher.php?id=<?= $voucher['id'] ?>" 
                                           class="btn btn-outline-danger" 
                                           title="Xóa"
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa voucher này?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Không tìm thấy voucher nào</h5>
                <?php if (!empty($search)): ?>
                    <p class="text-muted">Thử tìm kiếm với từ khóa khác hoặc <a href="vouchers.php">xem tất cả voucher</a></p>
                <?php else: ?>
                    <p class="text-muted">Hãy thêm voucher đầu tiên của bạn</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal thêm voucher -->
<div class="modal fade" id="addVoucherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Thêm voucher mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addVoucherForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mã voucher <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" placeholder="VD: SALE20" required>
                                <small class="form-text text-muted">Mã voucher phải là duy nhất</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phần trăm giảm (%) <span class="text-danger">*</span></label>
                                <input type="number" name="discount" class="form-control" placeholder="VD: 20" step="0.01" min="0" max="100" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ngày hết hạn <span class="text-danger">*</span></label>
                                <input type="date" name="expiry_date" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select name="status" class="form-select">
                                    <option value="1">Hoạt động</option>
                                    <option value="0">Tạm dừng</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mô tả</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Mô tả về voucher này..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Đơn hàng tối thiểu (VNĐ)</label>
                                <input type="number" name="min_order_amount" class="form-control" placeholder="VD: 500000" min="0">
                                <small class="form-text text-muted">Để trống nếu không giới hạn</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Giảm giá tối đa (VNĐ)</label>
                                <input type="number" name="max_discount" class="form-control" placeholder="VD: 100000" min="0">
                                <small class="form-text text-muted">Để trống nếu không giới hạn</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Giới hạn sử dụng</label>
                                <input type="number" name="usage_limit" class="form-control" placeholder="VD: 100" min="1">
                                <small class="form-text text-muted">Để trống nếu không giới hạn</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Hủy
                    </button>
                    <button type="submit" name="add_voucher" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Thêm voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Validation form
document.getElementById('addVoucherForm').addEventListener('submit', function(e) {
    const code = document.querySelector('input[name="code"]').value.trim();
    const discount = parseFloat(document.querySelector('input[name="discount"]').value);
    const expiryDate = document.querySelector('input[name="expiry_date"]').value;
    
    if (!code) {
        e.preventDefault();
        alert('Vui lòng nhập mã voucher!');
        return;
    }
    
    if (discount <= 0 || discount > 100) {
        e.preventDefault();
        alert('Phần trăm giảm phải từ 0.01% đến 100%!');
        return;
    }
    
    if (!expiryDate) {
        e.preventDefault();
        alert('Vui lòng chọn ngày hết hạn!');
        return;
    }
    
    const today = new Date();
    const expiry = new Date(expiryDate);
    if (expiry <= today) {
        e.preventDefault();
        alert('Ngày hết hạn phải sau ngày hôm nay!');
        return;
    }
});

// Set minimum date to tomorrow
document.querySelector('input[name="expiry_date"]').min = new Date(Date.now() + 86400000).toISOString().split('T')[0];

// Auto uppercase voucher code
document.querySelector('input[name="code"]').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});

// Calculate discount preview
function updateDiscountPreview() {
    const discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
    const minOrder = parseFloat(document.querySelector('input[name="min_order_amount"]').value) || 0;
    const maxDiscount = parseFloat(document.querySelector('input[name="max_discount"]').value) || 0;
    
    let preview = '';
    if (discount > 0) {
        preview = `Giảm ${discount}%`;
        if (minOrder > 0) {
            preview += ` cho đơn từ ${minOrder.toLocaleString('vi-VN')}đ`;
        }
        if (maxDiscount > 0) {
            preview += ` (tối đa ${maxDiscount.toLocaleString('vi-VN')}đ)`;
        }
    }
    
    // You can add a preview element if needed
}

document.querySelector('input[name="discount"]').addEventListener('input', updateDiscountPreview);
document.querySelector('input[name="min_order_amount"]').addEventListener('input', updateDiscountPreview);
document.querySelector('input[name="max_discount"]').addEventListener('input', updateDiscountPreview);
</script>

<?php include 'includes/footer.php'; ?>