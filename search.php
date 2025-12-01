<?php
session_start();
require_once 'db.php';

$search_term = $_GET['q'] ?? '';
$search_history = [];

if (!empty($search_term)) {
    // Save search history if user is logged in
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO search_history (user_id, search_term) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $search_term);
        $stmt->execute();
    }

    // Search products
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ? AND status = 1");
    $search_param = "%{$search_term}%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả tìm kiếm - <?= htmlspecialchars($search_term) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .search-results {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .search-header {
            margin-bottom: 30px;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }

        .product-card {
            text-decoration: none;
            color: inherit;
        }

        .product-card img {
            width: 100%;
            height: 350px;
            object-fit: cover;
        }

        .product-info {
            margin-top: 10px;
        }

        .product-name {
            font-size: 14px;
            margin: 5px 0;
        }

        .product-price {
            font-weight: bold;
        }

        .no-results {
            text-align: center;
            padding: 50px 0;
        }
    </style>
</head>
<body>
    <?php require 'header.php'; ?>

    <div class="search-results">
        <div class="search-header">
            <h1>Kết quả tìm kiếm cho "<?= htmlspecialchars($search_term) ?>"</h1>
        </div>

        <?php if (!empty($search_term) && $result->num_rows > 0): ?>
            <div class="results-grid">
                <?php while ($product = $result->fetch_assoc()): ?>
                    <a href="product_detail.php?id=<?= $product['id'] ?>" class="product-card">
                        <img src="admin/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <div class="product-info">
                            <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-price"><?= number_format($product['price'], 0, ',', '.') ?>₫</p>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <h2>Không tìm thấy sản phẩm phù hợp</h2>
                <p>Vui lòng thử lại với từ khóa khác</p>
            </div>
        <?php endif; ?>
    </div>

    <?php require 'footer.php'; ?>
</body>
</html>