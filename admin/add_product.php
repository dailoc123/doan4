<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

define('UPLOAD_DIR', __DIR__ . '/uploads/');
$message = "";

// Thêm đoạn code kiểm tra và tạo thư mục nếu chưa tồn tại
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// Xử lý thêm sản phẩm
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $price = (float)$_POST["price"];
    $discount_price = !empty($_POST["discount_price"]) ? (float)$_POST["discount_price"] : null;
    $category_id = (int)$_POST["category_id"];
    $status = (int)$_POST["status"];
    $colors = $_POST["colors"] ?? [];
    $sizes = $_POST["sizes"] ?? [];
    
    // Thêm biến cho inventory
    $initial_quantity = isset($_POST['initial_quantity']) ? (int)$_POST['initial_quantity'] : 0;
    $min_stock = isset($_POST['min_stock']) ? (int)$_POST['min_stock'] : 5;
    $inventory_note = isset($_POST['inventory_note']) ? trim($_POST['inventory_note']) : '';

    // Xử lý nhiều ảnh theo màu sắc
    $upload_dir = "uploads/";
    $image_paths = [];
    $color_images = [];

    foreach ($_FILES['images']['name'] as $key => $nameImg) {
        $tmp_name = $_FILES['images']['tmp_name'][$key];
        $basename = basename($nameImg);
        $target_path = $upload_dir . time() . "_" . $key . "_" . $basename;
        
        $selected_color = $_POST['image_colors'][$key] ?? null;

        if (move_uploaded_file($tmp_name, $target_path)) {
            if ($key === 0) {
                $first_image = $target_path;
            }
            $image_paths[] = [
                'path' => $target_path,
                'color_id' => $selected_color
            ];
        }
    }

    if (count($image_paths) > 0) {
        $main_image = $image_paths[0]['path'];
        
        $material = trim($_POST['material'] ?? '');
        $care = trim($_POST['care'] ?? '');
        
        // Bắt đầu transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Thêm sản phẩm
            $stmt = $conn->prepare("INSERT INTO products (name, description, material, care, price, discount_price, image, status, category_id, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssddsii", 
                $name, 
                $description, 
                $material,
                $care,
                $price, 
                $discount_price, 
                $main_image, 
                $status, 
                $category_id
            );

            if ($stmt->execute()) {
                $product_id = $stmt->insert_id;

                // Thêm inventory
                if ($initial_quantity > 0) {
                    $stmt_inv = $conn->prepare("INSERT INTO inventory (product_id, quantity, min_stock) VALUES (?, ?, ?)");
                    $stmt_inv->bind_param("iii", $product_id, $initial_quantity, $min_stock);
                    $stmt_inv->execute();
                    $stmt_inv->close();

                    // Thêm lịch sử nhập kho
                    $stmt_hist = $conn->prepare("INSERT INTO inventory_history (product_id, type, quantity, note) VALUES (?, 'import', ?, ?)");
                    $stmt_hist->bind_param("iis", $product_id, $initial_quantity, $inventory_note);
                    $stmt_hist->execute();
                    $stmt_hist->close();
                }

                // Lưu ảnh sản phẩm
                foreach ($image_paths as $img_data) {
                    $stmt_img = $conn->prepare("INSERT INTO product_images (product_id, image_path, color_id) VALUES (?, ?, ?)");
                    $stmt_img->bind_param("isi", $product_id, $img_data['path'], $img_data['color_id']);
                    $stmt_img->execute();
                    $stmt_img->close();
                }

                // Tạo biến thể sản phẩm
                foreach ($colors as $color_id) {
                    foreach ($sizes as $size_id) {
                        $stmt_variant = $conn->prepare("INSERT INTO product_variants (product_id, color_id, size_id) VALUES (?, ?, ?)");
                        $stmt_variant->bind_param("iii", $product_id, $color_id, $size_id);
                        $stmt_variant->execute();
                        $stmt_variant->close();
                    }
                }

                mysqli_commit($conn);
                echo "<script>alert('Đã thêm sản phẩm thành công!'); window.location.href='products.php';</script>";
                exit;
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Lỗi khi thêm sản phẩm: " . $e->getMessage();
        }
        $stmt->close();
    }
}

// Lấy dữ liệu danh mục, màu sắc, size
$categories = $conn->query("SELECT id, name FROM categories")->fetch_all(MYSQLI_ASSOC);
$colors = $conn->query("SELECT id, name FROM colors")->fetch_all(MYSQLI_ASSOC);
$sizes = $conn->query("SELECT id, name FROM sizes")->fetch_all(MYSQLI_ASSOC);

$page_title = "Thêm sản phẩm mới";
include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Thêm sản phẩm mới</h5>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Tên sản phẩm</label>
                        <input type="text" name="name" class="form-control" placeholder="Nhập tên sản phẩm" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả & Độ vừa vặn</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Nhập mô tả sản phẩm" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chất liệu</label>
                        <textarea name="material" class="form-control" rows="3" placeholder="Nhập thông tin chất liệu"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Hướng dẫn chăm sóc sản phẩm</label>
                        <textarea name="care" class="form-control" rows="3" placeholder="Nhập hướng dẫn chăm sóc"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giá (VNĐ)</label>
                                <input type="number" name="price" step="0.01" class="form-control" placeholder="Nhập giá sản phẩm" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giá giảm (VNĐ)</label>
                                <input type="number" name="discount_price" step="0.01" class="form-control" placeholder="Nhập giá sau khi giảm">
                                <div class="discount-preview mt-2 text-success"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Danh mục</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="1">Hiển thị</option>
                            <option value="0">Ẩn</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Ảnh sản phẩm (nhiều ảnh)</label>
                        <input type="file" name="images[]" accept="image/*" class="form-control" multiple required onchange="previewImages(event)">
                        <div class="preview-images mt-3" id="preview-images"></div>
                        <div id="image-colors" class="mt-3"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Màu sắc</label>
                        <div class="row">
                            <?php foreach ($colors as $color): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="colors[]" value="<?= $color['id'] ?>" id="color<?= $color['id'] ?>">
                                        <label class="form-check-label" for="color<?= $color['id'] ?>"><?= htmlspecialchars($color['name']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kích thước</label>
                        <div class="row">
                            <?php foreach ($sizes as $size): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="<?= $size['id'] ?>" id="size<?= $size['id'] ?>">
                                        <label class="form-check-label" for="size<?= $size['id'] ?>"><?= htmlspecialchars($size['name']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Số lượng ban đầu</label>
                                <input type="number" name="initial_quantity" class="form-control" value="0" min="0">
                                <small class="form-text text-muted">Nhập số lượng hàng ban đầu nếu có</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mức tồn kho tối thiểu</label>
                                <input type="number" name="min_stock" class="form-control" value="5" min="1">
                                <small class="form-text text-muted">Số lượng tối thiểu trước khi cần nhập thêm hàng</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ghi chú nhập kho</label>
                        <textarea name="inventory_note" class="form-control" rows="2" placeholder="Ghi chú về lô hàng nhập kho ban đầu"></textarea>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Thêm sản phẩm
                </button>
                <a href="products.php" class="btn btn-secondary btn-lg ms-2">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.preview-images {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
}

.preview-images img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
}

.discount-preview {
    font-weight: 600;
}
</style>

<script>
function previewImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('preview-images');
    const colorContainer = document.getElementById('image-colors');
    previewContainer.innerHTML = '';
    colorContainer.innerHTML = '';

    Array.from(files).forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Create image preview
                const imgContainer = document.createElement('div');
                imgContainer.className = 'position-relative';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                imgContainer.appendChild(img);
                
                // Add color selector for each image
                const colorSelect = document.createElement('select');
                colorSelect.name = `image_colors[${index}]`;
                colorSelect.className = 'form-select mt-2';
                colorSelect.innerHTML = `
                    <option value="">Không có màu</option>
                    <?php foreach ($colors as $color): ?>
                        <option value="<?= $color['id'] ?>"><?= htmlspecialchars($color['name']) ?></option>
                    <?php endforeach; ?>
                `;
                
                imgContainer.appendChild(colorSelect);
                previewContainer.appendChild(imgContainer);
            };
            reader.readAsDataURL(file);
        }
    });
}

// Calculate discount percentage
document.querySelector('input[name="price"]').addEventListener('input', calculateDiscount);
document.querySelector('input[name="discount_price"]').addEventListener('input', calculateDiscount);

function calculateDiscount() {
    const originalPrice = parseFloat(document.querySelector('input[name="price"]').value) || 0;
    const discountPrice = parseFloat(document.querySelector('input[name="discount_price"]').value) || 0;
    const previewElement = document.querySelector('.discount-preview');

    if (discountPrice && discountPrice < originalPrice) {
        const discountPercent = ((originalPrice - discountPrice) / originalPrice * 100).toFixed(1);
        previewElement.innerHTML = `<i class="fas fa-tag"></i> Giảm ${discountPercent}%`;
    } else {
        previewElement.innerHTML = '';
    }
}

// Inventory status indicator
document.querySelector('input[name="initial_quantity"]').addEventListener('input', function() {
    const quantity = parseInt(this.value) || 0;
    const minStock = parseInt(document.querySelector('input[name="min_stock"]').value) || 5;
    
    const statusText = quantity === 0 ? 'Hết hàng' : 
                      quantity <= minStock ? 'Sắp hết hàng' : 'Đủ hàng';
    const statusColor = quantity === 0 ? 'danger' : 
                       quantity <= minStock ? 'warning' : 'success';
    
    this.nextElementSibling.innerHTML = `
        <span class="text-${statusColor}">
            <i class="fas fa-${quantity === 0 ? 'times-circle' : 
                            quantity <= minStock ? 'exclamation-triangle' : 'check-circle'}"></i>
            ${statusText}
        </span>
    `;
});
</script>

<?php include 'includes/footer.php'; ?>




