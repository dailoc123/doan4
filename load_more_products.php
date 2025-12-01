<?php
require 'db.php';

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$sql_more_products = "SELECT * FROM products ORDER BY created_at DESC LIMIT 8 OFFSET $offset";
$result_more_products = mysqli_query($conn, $sql_more_products);

while ($product = mysqli_fetch_assoc($result_more_products)): ?>
    <div class="product-card">
        <img src="admin/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; height: 200px; object-fit: cover;">
        <h3><?= htmlspecialchars($product['name']) ?></h3>
        <p><?= number_format($product['price'], 0, ',', '.') ?> đ</p>
        <a href="product_detail.php?id=<?= $product['id'] ?>" class="btn">Xem chi tiết</a>
    </div>
<?php endwhile;

mysqli_close($conn);
?>
