<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = "Quản lý Sản phẩm";

$query = "SELECT p.*, c.name AS category_name, 
          (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count,
          (SELECT GROUP_CONCAT(image_path) FROM product_images WHERE product_id = p.id) as additional_images,
          i.quantity as stock_quantity,
          i.min_stock,
          (SELECT COUNT(*) FROM inventory_history WHERE product_id = p.id) as history_count,
          p.discount_price
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id
          LEFT JOIN inventory i ON p.id = i.product_id
          ORDER BY p.id DESC";
$result = mysqli_query($conn, $query);

// Include header
include 'includes/header.php';
?>

<!-- Search and Add Product Button -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="productSearch" class="form-control" placeholder="Tìm kiếm sản phẩm...">
        </div>
    </div>
    <div class="col-md-6 text-end">
        <a href="add_product.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Thêm sản phẩm mới
        </a>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="productsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hình ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td>
                            <img src="<?= $row['image'] ?>" alt="<?= htmlspecialchars($row['name']) ?>" width="50" height="50" style="object-fit: cover; border-radius: 8px;">
                        </td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['category_name'] ?? 'Không có') ?></td>
                        <td>
                            <?php if (isset($row['discount_price']) && $row['discount_price'] > 0 && $row['discount_price'] < $row['price']): ?>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <span style="text-decoration: line-through; color: #6c757d; font-size: 12px;"><?= number_format($row['price']) ?> đ</span>
                                    <span style="color: #dc3545; font-weight: bold;"><?= number_format($row['discount_price']) ?> đ</span>
                                    <span style="background: #dc3545; color: white; padding: 1px 4px; border-radius: 3px; font-size: 10px; width: fit-content;">
                                        -<?= round(($row['price'] - $row['discount_price']) / $row['price'] * 100) ?>%
                                    </span>
                                </div>
                            <?php else: ?>
                                <?= number_format($row['price']) ?> đ
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(is_null($row['stock_quantity'])): ?>
                                <span class="badge bg-secondary">Chưa có kho</span>
                            <?php elseif($row['stock_quantity'] <= 0): ?>
                                <span class="badge bg-danger">Hết hàng</span>
                            <?php elseif($row['stock_quantity'] <= $row['min_stock']): ?>
                                <span class="badge bg-warning"><?= $row['stock_quantity'] ?> (Sắp hết)</span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $row['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] == 1): ?>
                                <span class="badge bg-success">Đang bán</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Ẩn</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="edit_product.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_product.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Extra JS for product page
$extra_js = <<<EOT
<script>
    // Product search functionality
    document.getElementById('productSearch').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const table = document.getElementById('productsTable');
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < cells.length; j++) {
                const cellText = cells[j].textContent.toLowerCase();
                if (cellText.indexOf(searchValue) > -1) {
                    found = true;
                    break;
                }
            }
            
            row.style.display = found ? '' : 'none';
        }
    });
</script>
EOT;

// Include footer
include 'includes/footer.php';
?>