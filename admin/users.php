<?php
session_start();
require_once '../db.php';  // Kết nối với cơ sở dữ liệu

// Kiểm tra nếu người dùng là admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php'); // Chuyển hướng nếu không phải admin
    exit;
}

$page_title = "Quản lý Người dùng";

// Tìm kiếm người dùng nếu có
$search = '';
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $search = "%$search%";
} else {
    $search = '%'; // Lấy tất cả nếu không tìm kiếm
}

// Số bản ghi mỗi trang
$limit = 10;

// Lấy trang hiện tại từ query string (mặc định là trang 1)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Lấy tổng số người dùng
$sqlCount = "SELECT COUNT(*) as total FROM users WHERE name LIKE ?";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param("s", $search);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$rowCount = $resultCount->fetch_assoc();
$totalUsers = $rowCount['total'];

// Lấy danh sách người dùng theo phân trang và tìm kiếm
$sql = "SELECT * FROM users WHERE name LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $search, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Tính tổng số trang
$totalPages = ceil($totalUsers / $limit);

// Include header
include 'includes/header.php';
?>

<!-- Search and Add User Button -->
<div class="row mb-4">
    <div class="col-md-6">
        <form method="GET" action="">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Tìm kiếm người dùng..." value="<?= htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : '') ?>">
            </div>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <a href="edit_user.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Thêm người dùng mới
        </a>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if ($user['role'] == 'admin'): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Khách hàng</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa người dùng này?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page-1 ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page+1 ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
