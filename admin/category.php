<?php
session_start();
require '../db.php'; // Sử dụng kết nối chung

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Xóa dòng này vì đã có trong db.php:
// $conn = new mysqli("localhost", "root", "", "luxury_store");
// if ($conn->connect_error) {
//     die("Kết nối thất bại: " . $conn->connect_error);
// }

// Thêm danh mục
if (isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $gender = isset($_POST['gender']) ? (int)$_POST['gender'] : 0; // 0: Không xác định, 1: Nam, 2: Nữ, 3: Trẻ em
    $parent_id = $gender > 0 ? $gender : NULL;
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/";
        $image = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $image);
    }
    
    $stmt = $conn->prepare("INSERT INTO categories (name, description, image, parent_id) VALUES (?, ?, ?, ?)");
    // parent_id là INT, có thể NULL
    $stmt->bind_param("sssi", $name, $description, $image, $parent_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: category.php");
    exit;
}

// Sửa danh mục
if (isset($_POST['edit_category'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $gender = isset($_POST['gender']) ? (int)$_POST['gender'] : 0;
    $parent_id = $gender > 0 ? $gender : NULL;
    $existing_image = $_POST['existing_image'];
    $image = $existing_image;

    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/";
        $image = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $image);
    }

    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, image = ?, parent_id = ? WHERE id = ?");
    $stmt->bind_param("sssii", $name, $description, $image, $parent_id, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: category.php");
    exit;
}

// Xoá danh mục
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: category.php");
    exit;
}

// Lấy danh sách danh mục
$result = $conn->query("SELECT * FROM categories ORDER BY id DESC");
$categories = $result->fetch_all(MYSQLI_ASSOC);

// Lọc sản phẩm theo danh mục (nếu có)
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $products = $stmt->get_result();
    $stmt->close();
} else {
    $products = $conn->query("SELECT * FROM products ORDER BY id DESC");
}

$page_title = "Quản lý Danh mục";
include 'includes/header.php';
?>

<!-- Link đến CSS file -->
<link rel="stylesheet" href="assets/css/admin-style.css">

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-tags me-3"></i>
        Quản lý Danh mục
    </h1>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <?php
    $total_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM categories"))['count'];
    $total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM products"))['count'];
    $categories_with_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT category_id) as count FROM products WHERE category_id IS NOT NULL"))['count'];
    ?>
    
    <div class="stat-item">
        <div class="stat-number"><?= number_format($total_categories) ?></div>
        <div class="stat-label">Tổng danh mục</div>
    </div>
    
    <div class="stat-item">
        <div class="stat-number" style="color: var(--success-color);"><?= number_format($categories_with_products) ?></div>
        <div class="stat-label">Có sản phẩm</div>
    </div>
    
    <div class="stat-item">
        <div class="stat-number" style="color: var(--info-color);"><?= number_format($total_products) ?></div>
        <div class="stat-label">Tổng sản phẩm</div>
    </div>
</div>

<!-- Form thêm/sửa danh mục -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus-circle text-success me-2"></i>
                    Thêm danh mục
                </h4>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_category" value="1">
                    <div class="form-group mb-3">
                        <label class="form-label">Tên danh mục</label>
                        <input type="text" name="name" class="form-control" required placeholder="Nhập tên danh mục...">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Mô tả danh mục..."></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Giới tính mục tiêu</label>
                        <select name="gender" class="form-select">
                            <option value="0">Không xác định</option>
                            <option value="1">Nam</option>
                            <option value="2">Nữ</option>
                            <option value="3">Trẻ em</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Hình ảnh</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Thêm danh mục
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-filter text-info me-2"></i>
                    Lọc sản phẩm
                </h4>
            </div>
            <div class="card-body">
                <div class="form-group mb-3">
                    <label class="form-label">Chọn danh mục để lọc</label>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?category_id=0" class="btn btn-outline-secondary btn-sm <?= $category_id == 0 ? 'active' : '' ?>">Tất cả</a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="?category_id=<?= $cat['id']; ?>" 
                               class="btn btn-outline-secondary btn-sm <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Hiện đang hiển thị: <strong><?= $category_id == 0 ? 'Tất cả sản phẩm' : 'Sản phẩm theo danh mục' ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bảng danh mục -->
<div class="card">
    <div class="card-header">
        <h4 class="mb-0">
            <i class="fas fa-list-alt text-primary me-2"></i>
            Danh sách danh mục
        </h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag me-2"></i>ID</th>
                        <th><i class="fas fa-image me-2"></i>Hình ảnh</th>
                        <th><i class="fas fa-tag me-2"></i>Tên danh mục</th>
                        <th><i class="fas fa-align-left me-2"></i>Mô tả</th>
                        <th><i class="fas fa-venus-mars me-2"></i>Giới tính</th>
                        <th><i class="fas fa-box me-2"></i>Số sản phẩm</th>
                        <th><i class="fas fa-cogs me-2"></i>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        $product_count = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                        $product_count->bind_param("i", $cat['id']);
                        $product_count->execute();
                        $count = $product_count->get_result()->fetch_assoc()['count'];
                        $product_count->close();
                        ?>
                        <tr>
                            <td><strong><?= $cat['id']; ?></strong></td>
                            <td>
                                <img src="<?= $cat['image'] ?: 'https://via.placeholder.com/60'; ?>" 
                                     class="category-image"
                                     alt="<?= htmlspecialchars($cat['name']) ?>"
                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td><strong><?= htmlspecialchars($cat['name']); ?></strong></td>
                            <td><?= htmlspecialchars(substr($cat['description'] ?? '', 0, 50)) . (strlen($cat['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td>
                                <?php
                                  $genderLabel = 'Không xác định';
                                  if ((int)($cat['parent_id'] ?? 0) === 1) $genderLabel = 'Nam';
                                  elseif ((int)($cat['parent_id'] ?? 0) === 2) $genderLabel = 'Nữ';
                                  elseif ((int)($cat['parent_id'] ?? 0) === 3) $genderLabel = 'Trẻ em';
                                ?>
                                <span class="badge bg-secondary"><?= $genderLabel ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $count > 0 ? 'success' : 'secondary' ?>">
                                    <?= number_format($count) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-info btn-sm me-1" 
                                        onclick="editCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>', '<?= htmlspecialchars($cat['description'] ?? '') ?>', '<?= $cat['image'] ?>', <?= (int)($cat['parent_id'] ?? 0) ?>)">
                                    <i class="fas fa-edit me-1"></i> Sửa
                                </button>
                                <a href="?delete=<?= $cat['id']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('Xoá danh mục này?')">
                                    <i class="fas fa-trash me-1"></i> Xoá
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bảng sản phẩm -->
<div class="card">
    <div class="card-header">
        <h4 class="mb-0">
            <i class="fas fa-box-open text-warning me-2"></i>
            Danh sách sản phẩm <?= $category_id > 0 ? '(Theo danh mục)' : '' ?>
        </h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="fas fa-image me-2"></i>Hình ảnh</th>
                        <th><i class="fas fa-box me-2"></i>Tên sản phẩm</th>
                        <th><i class="fas fa-align-left me-2"></i>Mô tả</th>
                        <th><i class="fas fa-dollar-sign me-2"></i>Giá</th>
                        <th><i class="fas fa-tag me-2"></i>Danh mục</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($products) && mysqli_num_rows($products) > 0): ?>
                        <?php while ($product = mysqli_fetch_assoc($products)): ?>
                            <?php
                            // Lấy tên danh mục
                            $cat_query = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                            $cat_query->bind_param("i", $product['category_id']);
                            $cat_query->execute();
                            $cat_result = $cat_query->get_result();
                            $category_name = $cat_result->num_rows > 0 ? $cat_result->fetch_assoc()['name'] : 'Không có';
                            $cat_query->close();
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= $product['image']; ?>" 
                                         class="product-image"
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                </td>
                                <td><strong><?= htmlspecialchars($product['name']); ?></strong></td>
                                <td><?= htmlspecialchars(substr($product['description'], 0, 100)); ?>...</td>
                                <td><span class="text-success fw-bold"><?= number_format($product['price'], 0, ',', '.'); ?> VNĐ</span></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?= htmlspecialchars($category_name) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
                                Không có sản phẩm nào!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal sửa danh mục -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">
                    <i class="fas fa-edit me-2"></i>Sửa danh mục
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_category" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="existing_image" id="edit_existing_image">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Tên danh mục</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Mô tả</label>
                                <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Giới tính mục tiêu</label>
                                <select name="gender" id="edit_gender" class="form-select">
                                    <option value="0">Không xác định</option>
                                    <option value="1">Nam</option>
                                    <option value="2">Nữ</option>
                                    <option value="3">Trẻ em</option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Chọn ảnh mới (tuỳ chọn)</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Ảnh hiện tại:</label>
                                <div class="text-center">
                                    <img id="edit_current_image" src="" 
                                         class="img-thumbnail" 
                                         style="max-width: 200px; max-height: 200px;" 
                                         alt="Ảnh hiện tại">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Hủy
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = <<<EOT
<script>
function editCategory(id, name, description, image, gender) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_existing_image').value = image;
    const genderSelect = document.getElementById('edit_gender');
    if (genderSelect) {
        genderSelect.value = (gender && [0,1,2,3].includes(parseInt(gender))) ? String(gender) : '0';
    }
    
    const currentImage = document.getElementById('edit_current_image');
    currentImage.src = image || 'https://via.placeholder.com/200';
    
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

// Thêm hiệu ứng loading cho form
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            submitBtn.disabled = true;
        }
    });
});

// Validation form
document.querySelectorAll('input[name="name"]').forEach(input => {
    input.addEventListener('input', function() {
        if (this.value.length < 2) {
            this.setCustomValidity('Tên danh mục phải có ít nhất 2 ký tự');
        } else {
            this.setCustomValidity('');
        }
    });
});

// Preview image khi chọn file
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Tạo preview image nếu cần
                console.log('File selected:', file.name);
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
EOT;

include 'includes/footer.php';
?>