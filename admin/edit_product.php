<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

if (!isset($_GET['id'])) {
    echo "Thiếu ID sản phẩm.";
    exit;
}

$product_id = (int)$_GET['id'];

// Xử lý cập nhật sản phẩm
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $material = trim($_POST["material"]);
    $care = trim($_POST["care"]);
    $price = (float)$_POST["price"];
    $discount_price = !empty($_POST["discount_price"]) ? (float)$_POST["discount_price"] : null;
    $category_id = (int)$_POST["category_id"];
    $status = (int)$_POST["status"];
    $colors = $_POST["colors"] ?? [];
    $sizes = $_POST["sizes"] ?? [];

    // Bắt đầu transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Cập nhật sản phẩm
        $stmt = $conn->prepare("UPDATE products SET name=?, description=?, material=?, care=?, price=?, discount_price=?, status=?, category_id=? WHERE id=?");
        $stmt->bind_param("ssssddiis", $name, $description, $material, $care, $price, $discount_price, $status, $category_id, $product_id);
        
        if ($stmt->execute()) {
            // Cập nhật variants
            $conn->query("DELETE FROM product_variants WHERE product_id = $product_id");
            foreach ($colors as $color_id) {
                foreach ($sizes as $size_id) {
                    $stmt_variant = $conn->prepare("INSERT INTO product_variants (product_id, color_id, size_id) VALUES (?, ?, ?)");
                    $stmt_variant->bind_param("iii", $product_id, $color_id, $size_id);
                    $stmt_variant->execute();
                    $stmt_variant->close();
                }
            }
            
            // Xử lý upload ảnh mới
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = "uploads/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Xóa ảnh cũ nếu có ảnh mới
                $conn->query("DELETE FROM product_images WHERE product_id = $product_id");
                
                // Xử lý từng ảnh được upload
                foreach ($_FILES['images']['name'] as $key => $nameImg) {
                    $tmp_name = $_FILES['images']['tmp_name'][$key];
                    $basename = basename($nameImg);
                    $target_path = $upload_dir . time() . "_" . $key . "_" . $basename;
                    
                    $selected_color = $_POST['image_colors'][$key] ?? null;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $stmt_img = $conn->prepare("INSERT INTO product_images (product_id, image_path, color_id) VALUES (?, ?, ?)");
                        $stmt_img->bind_param("isi", $product_id, $target_path, $selected_color);
                        $stmt_img->execute();
                        $stmt_img->close();
                    }
                }
            }
            
            // Cập nhật inventory
            $new_quantity = (int)$_POST['quantity'];
            $new_min_stock = (int)$_POST['min_stock'];
            $inventory_note = trim($_POST['inventory_note']);
            
            // Lấy số lượng hiện tại
            $current_inventory = $conn->query("SELECT quantity FROM inventory WHERE product_id = $product_id")->fetch_assoc();
            $current_quantity = $current_inventory['quantity'] ?? 0;
            
            // Tính toán thay đổi số lượng
            $quantity_change = $new_quantity - $current_quantity;
            
            if ($quantity_change != 0) {
                // Kiểm tra xem đã có bản ghi inventory chưa
                $check_inventory = $conn->query("SELECT id FROM inventory WHERE product_id = $product_id");
                
                if ($check_inventory->num_rows > 0) {
                    // Cập nhật bản ghi hiện có
                    $stmt_inv = $conn->prepare("UPDATE inventory SET quantity = ?, min_stock = ? WHERE product_id = ?");
                    $stmt_inv->bind_param("iii", $new_quantity, $new_min_stock, $product_id);
                } else {
                    // Tạo bản ghi mới
                    $stmt_inv = $conn->prepare("INSERT INTO inventory (product_id, quantity, min_stock) VALUES (?, ?, ?)");
                    $stmt_inv->bind_param("iii", $product_id, $new_quantity, $new_min_stock);
                }
                
                $stmt_inv->execute();
                $stmt_inv->close();
            
                // Thêm lịch sử inventory
                $type = $quantity_change > 0 ? 'import' : 'export';
                $change_amount = abs($quantity_change);
                
                $stmt_hist = $conn->prepare("INSERT INTO inventory_history 
                                           (product_id, type, quantity, note, created_at) 
                                           VALUES (?, ?, ?, ?, NOW())");
                $stmt_hist->bind_param("isis", 
                    $product_id, $type, $change_amount, $inventory_note
                );
                $stmt_hist->execute();
                $stmt_hist->close();
            }

            mysqli_commit($conn);
            echo "<script>alert('Đã cập nhật sản phẩm thành công!'); window.location.href='products.php';</script>";
            exit;
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Lỗi khi cập nhật sản phẩm: " . $e->getMessage();
    }
    $stmt->close();
}

// Lấy thông tin sản phẩm
$product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
if (!$product) {
    echo "Không tìm thấy sản phẩm.";
    exit;
}

// Lấy dữ liệu danh mục, màu sắc, size
$categories = $conn->query("SELECT id, name FROM categories")->fetch_all(MYSQLI_ASSOC);
$colors = $conn->query("SELECT id, name FROM colors")->fetch_all(MYSQLI_ASSOC);
$sizes = $conn->query("SELECT id, name FROM sizes")->fetch_all(MYSQLI_ASSOC);

$selected_variants = $conn->query("SELECT color_id, size_id FROM product_variants WHERE product_id = $product_id")->fetch_all(MYSQLI_ASSOC);
$selected_colors = array_unique(array_column($selected_variants, 'color_id'));
$selected_sizes = array_unique(array_column($selected_variants, 'size_id'));

// Lấy các ảnh hiện tại
$product_images = $conn->query("SELECT id, image_path, color_id FROM product_images WHERE product_id = $product_id")->fetch_all(MYSQLI_ASSOC);

// Thêm sau dòng 148 (sau khi lấy thông tin inventory)
$inventory = $conn->query("SELECT quantity, min_stock FROM inventory WHERE product_id = $product_id")->fetch_assoc();

// Debug thông tin sản phẩm
echo "<div class='alert alert-info'>";
echo "<h5>Debug Info:</h5>";
echo "<p>Product Name: " . htmlspecialchars($product['name']) . "</p>";
echo "<p>Description: " . htmlspecialchars($product['description']) . "</p>";
echo "<p>Material: " . htmlspecialchars($product['material'] ?? 'N/A') . "</p>";
echo "<p>Care: " . htmlspecialchars($product['care'] ?? 'N/A') . "</p>";
if ($inventory) {
    echo "<p>Inventory Quantity: " . $inventory['quantity'] . "</p>";
    echo "<p>Min Stock: " . $inventory['min_stock'] . "</p>";
}
echo "</div>";

$page_title = "Chỉnh sửa sản phẩm";
include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Chỉnh sửa sản phẩm</h5>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Tên sản phẩm</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" placeholder="Nhập tên sản phẩm" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả & Độ vừa vặn</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Nhập mô tả sản phẩm" required><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chất liệu</label>
                        <textarea name="material" class="form-control" rows="3" placeholder="Nhập thông tin chất liệu"><?= htmlspecialchars($product['material'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Hướng dẫn chăm sóc sản phẩm</label>
                        <textarea name="care" class="form-control" rows="3" placeholder="Nhập hướng dẫn chăm sóc"><?= htmlspecialchars($product['care'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giá (VNĐ)</label>
                                <input type="number" name="price" step="0.01" class="form-control" value="<?= $product['price'] ?>" placeholder="Nhập giá sản phẩm" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giá giảm (VNĐ)</label>
                                <input type="number" name="discount_price" step="0.01" class="form-control" value="<?= $product['discount_price'] ?? '' ?>" placeholder="Nhập giá sau khi giảm">
                                <div class="discount-preview mt-2 text-success"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Danh mục</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="1" <?= $product['status'] == 1 ? 'selected' : '' ?>>Hiển thị</option>
                            <option value="0" <?= $product['status'] == 0 ? 'selected' : '' ?>>Ẩn</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Hiển thị ảnh hiện tại -->
                    <?php if (!empty($product_images)): ?>
                        <div class="mb-3">
                            <label class="form-label">Ảnh hiện tại</label>
                            <div class="current-images">
                                <?php foreach ($product_images as $img): ?>
                                    <div class="current-image-item">
                                        <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Product Image" style="width: 100px; height: 100px; object-fit: cover; margin: 5px; border-radius: 8px;">
                                        <?php if ($img['color_id']): ?>
                                            <?php 
                                            $color_name = '';
                                            foreach ($colors as $color) {
                                                if ($color['id'] == $img['color_id']) {
                                                    $color_name = $color['name'];
                                                    break;
                                                }
                                            }
                                            ?>
                                            <small class="d-block text-muted">Màu: <?= htmlspecialchars($color_name) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Thay ảnh sản phẩm (nhiều ảnh)</label>
                        <input type="file" name="images[]" accept="image/*" class="form-control" multiple onchange="previewImages(event)">
                        <small class="text-muted">Nếu bạn tải lên ảnh mới, tất cả ảnh cũ sẽ bị thay thế.</small>
                        <div class="preview-images mt-3" id="preview-images"></div>
                        <div id="image-colors" class="mt-3"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Màu sắc</label>
                        <div class="row">
                            <?php foreach ($colors as $color): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="colors[]" value="<?= $color['id'] ?>" id="color<?= $color['id'] ?>" <?= in_array($color['id'], $selected_colors) ? 'checked' : '' ?>>
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
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="<?= $size['id'] ?>" id="size<?= $size['id'] ?>" <?= in_array($size['id'], $selected_sizes) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="size<?= $size['id'] ?>"><?= htmlspecialchars($size['name']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Số lượng tồn kho</label>
                                <input type="number" name="quantity" class="form-control" value="<?= $inventory['quantity'] ?? 0 ?>" min="0">
                                <small class="form-text text-muted">Số lượng hiện tại trong kho</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mức tồn kho tối thiểu</label>
                                <input type="number" name="min_stock" class="form-control" value="<?= $inventory['min_stock'] ?? 5 ?>" min="1">
                                <small class="form-text text-muted">Số lượng tối thiểu trước khi cần nhập thêm hàng</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ghi chú cập nhật kho</label>
                        <textarea name="inventory_note" class="form-control" rows="2" placeholder="Ghi chú về việc cập nhật số lượng (nếu có)"></textarea>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Cập nhật sản phẩm
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

.current-images {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.current-image-item {
    text-align: center;
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

// Thêm error handling cho JavaScript
try {
    // Inventory status indicator
    document.querySelector('input[name="quantity"]').addEventListener('input', function() {
        const quantity = parseInt(this.value) || 0;
        const minStock = parseInt(document.querySelector('input[name="min_stock"]').value) || 5;
        
        const statusText = quantity === 0 ? 'Hết hàng' : 
                          quantity <= minStock ? 'Sắp hết hàng' : 'Đủ hàng';
        const statusColor = quantity === 0 ? 'danger' : 
                           quantity <= minStock ? 'warning' : 'success';
        
        // Kiểm tra element tồn tại trước khi cập nhật
        const nextElement = this.nextElementSibling;
        if (nextElement) {
            nextElement.innerHTML = `
                <span class="text-${statusColor}">
                    <i class="fas fa-${quantity === 0 ? 'times-circle' : 
                                    quantity <= minStock ? 'exclamation-triangle' : 'check-circle'}"></i>
                    ${statusText}
                </span>
            `;
        }
    });
} catch (error) {
    console.error('JavaScript error:', error);
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', function() {
    calculateDiscount();
    document.querySelector('input[name="quantity"]').dispatchEvent(new Event('input'));
});
</script>

<?php include 'includes/footer.php'; ?>
