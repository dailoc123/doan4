<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

try {
    // Lấy thông tin sản phẩm
    $sql = "SELECT p.*, c.name as category_name, p.description, p.material, p.care,
            i.quantity as stock_quantity, i.min_stock
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    // Lấy nhiều ảnh từ bảng product_images theo màu sắc
    $sql = "SELECT pi.image_path, pi.color_id, c.name as color_name 
            FROM product_images pi
            LEFT JOIN colors c ON pi.color_id = c.id
            WHERE pi.product_id = ?
            ORDER BY pi.color_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $image_paths = [];
    $colorImages = [];

    while ($row = $result->fetch_assoc()) {
        $imagePath = 'admin/' . $row['image_path'];
        if (file_exists($imagePath)) {
            if ($row['color_id']) {
                if (!isset($colorImages[$row['color_id']])) {
                    $colorImages[$row['color_id']] = [];
                }
                $colorImages[$row['color_id']][] = $imagePath;
            } else {
                $image_paths[] = $imagePath;
            }
        }
    }

    // Nếu không có ảnh, sử dụng ảnh mặc định từ product
    if (empty($image_paths) && empty($colorImages)) {
        if (!empty($product['image'])) {
            $image_paths[] = 'admin/' . $product['image'];
        } else {
            $image_paths[] = '../cozastore-master/images/product-detail-01.jpg';
        }
    }

    // Lấy màu sắc và kích thước
    $colors_sql = "SELECT DISTINCT c.id, c.name FROM product_variants pv JOIN colors c ON pv.color_id = c.id WHERE pv.product_id = ?";
    $stmt = $conn->prepare($colors_sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $colors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $sizes_sql = "SELECT s.id, s.name FROM product_variants pv JOIN sizes s ON pv.size_id = s.id WHERE pv.product_id = ? GROUP BY s.id";
    $stmt = $conn->prepare($sizes_sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $sizes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Tạo response data
    $response = [
        'success' => true,
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'discount_price' => $product['discount_price'],
            'description' => $product['description'] ?? 'Nulla eget sem vitae eros pharetra viverra. Nam vitae luctus ligula. Mauris consequat ornare feugiat.',
            'material' => $product['material'],
            'care' => $product['care'],
            'category_name' => $product['category_name'],
            'stock_quantity' => $product['stock_quantity'] ?? 0,
            'images' => array_merge($image_paths, array_reduce($colorImages, 'array_merge', [])),
            'colors' => $colors,
            'sizes' => $sizes,
            'colorImages' => $colorImages
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>