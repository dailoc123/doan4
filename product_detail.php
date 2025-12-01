<?php
session_start();
require 'header.php'; 
require_once 'db.php';



$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header('Location: products.php');
    exit;
}

// Lấy thông tin sản phẩm
$sql = "SELECT p.*, c.name as category_name, p.description, p.material, p.care,
        i.quantity as stock_quantity, i.min_stock,
        (SELECT COUNT(*) FROM inventory_history WHERE product_id = p.id) as history_count
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
    header('Location: products.php');
    exit;
}

// Lưu hành vi người dùng: sản phẩm đã xem
if (!isset($_SESSION['recently_viewed'])) {
    $_SESSION['recently_viewed'] = [];
}
$recent = &$_SESSION['recently_viewed'];
if (($key = array_search($id, $recent)) !== false) {
    unset($recent[$key]);
}
array_unshift($recent, $id);
$recent = array_slice($recent, 0, 10);

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

// Lấy màu sắc và kích thước
$colors = $conn->query("SELECT DISTINCT c.id, c.name FROM product_variants pv JOIN colors c ON pv.color_id = c.id WHERE pv.product_id = $id")->fetch_all(MYSQLI_ASSOC);
$sizes = $conn->query("SELECT s.id, s.name FROM product_variants pv JOIN sizes s ON pv.size_id = s.id WHERE pv.product_id = $id GROUP BY s.id")->fetch_all(MYSQLI_ASSOC);
?>

    
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Chi tiết sản phẩm</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: "#9f7aea", secondary: "#b794f4" },
                    borderRadius: {
                        none: "0px",
                        sm: "4px",
                        DEFAULT: "8px",
                        md: "12px",
                        lg: "16px",
                        xl: "20px",
                        "2xl": "24px",
                        "3xl": "32px",
                        full: "9999px",
                        button: "8px",
                    },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .hero-gradient {
            background: linear-gradient(90deg, rgba(255,248,240,1) 0%, rgba(255,248,240,0.9) 50%, rgba(255,248,240,0) 100%);
        }
        
        /* Header Spacing Fix */
        body {
            margin-top: 120px; /* Announcement bar (35px) + Header (65px) + padding (20px) */
        }
        
        /* Responsive header spacing */
        @media (max-width: 768px) {
            body {
                margin-top: 100px; /* Giảm margin cho mobile */
            }
        }
        
        @media (max-width: 480px) {
            body {
                margin-top: 90px; /* Giảm thêm cho mobile nhỏ */
            }
        }
    </style>
</head>
<body class="font-sans bg-white">
    
    <!-- Top Banner -->
    <div class="w-full bg-primary text-white text-xs py-2 text-center">
        Free Express Shipping | Easy 30-Day Returns
    </div>

    <!-- Breadcrumb -->
    <div class="bg-gray-50 py-3">
        <div class="container mx-auto px-4">
            <nav class="flex items-center space-x-2 text-sm">
                <a href="userhome.php" class="text-gray-600 hover:text-primary">Home</a>
                <i class="ri-arrow-right-s-line text-gray-400"></i>
                <a href="products.php" class="text-gray-600 hover:text-primary">Products</a>
                <i class="ri-arrow-right-s-line text-gray-400"></i>
                <a href="#" class="text-gray-600 hover:text-primary"><?= htmlspecialchars($product['category_name'] ?? 'Category') ?></a>
                <i class="ri-arrow-right-s-line text-gray-400"></i>
                <span class="text-gray-900"><?= htmlspecialchars($product['name']) ?></span>
            </nav>
        </div>
    </div>

    <!-- Product Section -->
    <section class="py-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row gap-12">
                <!-- Product Images -->
                <div class="w-full lg:w-2/3">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <?php
                            $defaultImage = 'images/no-image.png';
                            $mainImage = !empty($image_paths) ? $image_paths[0] : 
                                        (!empty($colorImages) ? reset($colorImages)[0] : $defaultImage);
                            ?>
                            <img id="main-product-image"
                                 src="<?= htmlspecialchars($mainImage) ?>"
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="w-full h-[600px] object-cover object-top rounded-lg"
                                 onerror="this.src='<?= $defaultImage ?>'">
                        </div>
                        <?php 
                        $galleryImages = !empty($image_paths) ? $image_paths : 
                                        (!empty($colorImages) ? reset($colorImages) : []);
                        $displayImages = array_slice($galleryImages, 1, 2); // Get next 2 images
                        foreach ($displayImages as $img): 
                        ?>
                            <div>
                                <img src="<?= htmlspecialchars($img) ?>" 
                                     alt="Gallery" 
                                     class="w-full h-[400px] object-cover object-top rounded-lg cursor-pointer" 
                                     onclick="changeMainImage(this)"
                                     onerror="this.src='<?= $defaultImage ?>'">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="w-full lg:w-1/3">
                    <div class="sticky top-24">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="flex">
                                <i class="ri-star-fill text-yellow-400"></i>
                                <i class="ri-star-fill text-yellow-400"></i>
                                <i class="ri-star-fill text-yellow-400"></i>
                                <i class="ri-star-fill text-yellow-400"></i>
                                <i class="ri-star-half-fill text-yellow-400"></i>
                            </div>
                            <span class="text-gray-600">(<?= rand(50, 200) ?> reviews)</span>
                        </div>
                        
                        <!-- Price Section -->
                        <?php if ($product['discount_price']): ?>
                            <div class="flex items-baseline gap-4 mb-6">
                                <span class="text-3xl font-bold text-gray-900"><?= number_format($product['discount_price'], 0, ',', '.') ?>₫</span>
                                <span class="text-lg text-gray-500 line-through"><?= number_format($product['price'], 0, ',', '.') ?>₫</span>
                                <?php
                                $discount_percent = round(($product['price'] - $product['discount_price']) / $product['price'] * 100);
                                ?>
                                <span class="text-sm font-medium text-green-600 bg-green-50 px-2 py-1 rounded">Save <?= $discount_percent ?>%</span>
                            </div>
                        <?php else: ?>
                            <div class="flex items-baseline gap-4 mb-6">
                                <span class="text-3xl font-bold text-gray-900"><?= number_format($product['price'], 0, ',', '.') ?>₫</span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Stock Status -->
                        <?php
                        $stock = $product['stock_quantity'] ?? 0;
                        $minStock = $product['min_stock'] ?? 5;
                        ?>
                        <div class="mb-6">
                            <?php if ($stock === 0): ?>
                                <div class="flex items-center gap-2 text-red-600 bg-red-50 px-3 py-2 rounded">
                                    <i class="ri-close-circle-line"></i>
                                    <span>Hết hàng</span>
                                </div>
                            <?php elseif ($stock <= $minStock): ?>
                                <div class="flex items-center gap-2 text-orange-600 bg-orange-50 px-3 py-2 rounded">
                                    <i class="ri-error-warning-line"></i>
                                    <span>Sắp hết hàng (Còn <?= $stock ?> sản phẩm)</span>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center gap-2 text-green-600 bg-green-50 px-3 py-2 rounded">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <span>Còn <?= $stock ?> sản phẩm</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form action="auth/cart.php" method="POST" class="space-y-6">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            
                            <!-- Color Selection -->
                            <?php if (!empty($colors)): ?>
                                <div class="mb-6">
                                    <h3 class="font-medium text-gray-900 mb-2">Màu sắc</h3>
                                    <div class="flex gap-2">
                                        <?php foreach ($colors as $index => $color): ?>
                                            <label class="cursor-pointer">
                                                <input type="radio" 
                                                       name="color_id" 
                                                       value="<?= $color['id'] ?>" 
                                                       class="sr-only"
                                                       data-images='<?= htmlspecialchars(json_encode($colorImages[$color['id']] ?? [])) ?>'
                                                       required
                                                       <?= $index === 0 ? 'checked' : '' ?>>
                                                <div class="w-8 h-8 rounded-full border-2 border-gray-200 hover:border-primary transition-colors" 
                                                     style="background-color: <?= strtolower($color['name']) === 'đen' ? '#000000' : (strtolower($color['name']) === 'trắng' ? '#ffffff' : '#' . substr(md5($color['name']), 0, 6)) ?>">
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Size Selection -->
                            <?php if (!empty($sizes)): ?>
                                <div class="mb-6">
                                    <h3 class="font-medium text-gray-900 mb-2">Kích thước</h3>
                                    <div class="grid grid-cols-5 gap-2">
                                        <?php foreach ($sizes as $index => $size): ?>
                                            <label class="cursor-pointer">
                                                <input type="radio" 
                                                       name="size_id" 
                                                       value="<?= $size['id'] ?>" 
                                                       class="sr-only" 
                                                       required
                                                       <?= $index === 0 ? 'checked' : '' ?>>
                                                <div class="py-2 text-sm font-medium text-gray-800 border border-gray-200 rounded text-center hover:border-primary hover:text-primary transition-colors">
                                                    <?= htmlspecialchars($size['name']) ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <a href="#size-guide" class="text-sm text-primary hover:text-gray-900 mt-2 inline-block">Size Guide</a>
                                </div>
                            <?php endif; ?>

                            <!-- Quantity Selection -->
                            <div class="mb-6">
                                <h3 class="font-medium text-gray-900 mb-2">Số lượng</h3>
                                <div class="flex w-32 border border-gray-200 rounded">
                                    <button type="button" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary border-r border-gray-200" onclick="decrementQuantity()">
                                        <i class="ri-subtract-line"></i>
                                    </button>
                                    <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= $stock ?>" class="w-12 h-10 text-center border-none focus:outline-none" required>
                                    <button type="button" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary border-l border-gray-200" onclick="incrementQuantity()">
                                        <i class="ri-add-line"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-4 mb-8">
                                <?php if ($stock > 0): ?>
                                    <button type="submit" name="add_to_cart" class="flex-1 bg-primary text-white px-6 py-3 font-medium !rounded-button hover:bg-gray-900 transition-all duration-300 flex items-center justify-center gap-2">
                                        <i class="ri-shopping-bag-line"></i>
                                        Thêm vào giỏ
                                    </button>
                                    <a href="checkout.php" class="flex-1 bg-secondary text-white px-6 py-3 font-medium !rounded-button hover:bg-primary transition-all duration-300 flex items-center justify-center gap-2">
                                        <i class="ri-flashlight-line"></i>
                                        Mua ngay
                                    </a>
                                    <button type="button" class="w-12 h-12 flex items-center justify-center border border-gray-200 !rounded-button hover:border-primary hover:text-primary transition-all duration-300" onclick="toggleWishlist(<?= $product['id'] ?>)">
                                        <i class="ri-heart-line"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="flex-1 bg-gray-400 text-white px-6 py-3 font-medium !rounded-button cursor-not-allowed" disabled>
                                        <i class="ri-close-circle-line"></i>
                                        Hết hàng
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Product Features -->
                        <div class="border-t border-gray-200 pt-6 space-y-4">
                            <div class="flex items-center gap-2 text-gray-600">
                                <i class="ri-truck-line text-xl"></i>
                                <span>Miễn phí vận chuyển đơn hàng trên 500.000₫</span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-600">
                                <i class="ri-refresh-line text-xl"></i>
                                <span>Đổi trả trong 30 ngày</span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-600">
                                <i class="ri-shield-check-line text-xl"></i>
                                <span>Bảo hành 2 năm</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Details -->
            <div class="mt-16">
                <div class="border-b border-gray-200">
                    <div class="flex gap-8">
                        <button class="px-6 py-4 text-primary border-b-2 border-primary font-medium tab-button active" data-tab="description">Mô tả</button>
                        <button class="px-6 py-4 text-gray-600 hover:text-gray-900 tab-button" data-tab="material">Chất liệu</button>
                        <button class="px-6 py-4 text-gray-600 hover:text-gray-900 tab-button" data-tab="care">Hướng dẫn chăm sóc</button>
                        <button class="px-6 py-4 text-gray-600 hover:text-gray-900 tab-button" data-tab="reviews">Đánh giá</button>
                    </div>
                </div>
                <div class="py-8">
                    <div class="tab-content active" id="description">
                        <div class="prose max-w-none">
                            <p class="text-gray-600 leading-relaxed">
                                <?= nl2br(htmlspecialchars($product['description'] ?? 'Chưa có mô tả sản phẩm.')) ?>
                            </p>
                        </div>
                    </div>
                    <div class="tab-content" id="material">
                        <div class="prose max-w-none">
                            <p class="text-gray-600 leading-relaxed">
                                <?= nl2br(htmlspecialchars($product['material'] ?? 'Chưa có thông tin chất liệu.')) ?>
                            </p>
                        </div>
                    </div>
                    <div class="tab-content" id="care">
                        <div class="prose max-w-none">
                            <p class="text-gray-600 leading-relaxed">
                                <?= nl2br(htmlspecialchars($product['care'] ?? 'Chưa có hướng dẫn chăm sóc.')) ?>
                            </p>
                        </div>
                    </div>
                    <div class="tab-content" id="reviews">
                        <div class="prose max-w-none">
                            <p class="text-gray-600 leading-relaxed">
                                Chức năng đánh giá sẽ được cập nhật sớm.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <div class="mt-16">
                <h3 class="text-2xl font-bold text-gray-900 mb-8">Sản phẩm gợi ý</h3>
                <div class="grid grid-cols-4 gap-6">
                    <?php
                    $category_id = $product['category_id'];
                    $product_id = $product['id'];
                    $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE category_id = ? AND id != ? AND status = 1 ORDER BY rating DESC, created_at DESC LIMIT 4");
                    $stmt->bind_param("ii", $category_id, $product_id);
                    $stmt->execute();
                    $suggested = $stmt->get_result();
                    while ($row = $suggested->fetch_assoc()):
                    ?>
                        <div class="group">
                            <div class="relative overflow-hidden rounded-lg mb-4">
                                <a href="product_detail.php?id=<?= $row['id'] ?>">
                                    <img src="admin/<?= htmlspecialchars($row['image']) ?>" 
                                         alt="<?= htmlspecialchars($row['name']) ?>" 
                                         class="w-full h-[350px] object-cover object-top transition-transform duration-300 group-hover:scale-105">
                                </a>
                                <div class="absolute top-4 right-4 w-8 h-8 bg-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button onclick="toggleWishlist(<?= $row['id'] ?>)" class="text-gray-700 hover:text-red-500">
                                        <i class="ri-heart-line"></i>
                                    </button>
                                </div>
                            </div>
                            <a href="product_detail.php?id=<?= $row['id'] ?>">
                                <h4 class="text-gray-900 font-medium"><?= htmlspecialchars($row['name']) ?></h4>
                                <p class="text-gray-700"><?= number_format($row['price'], 0, ',', '.') ?>₫</p>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </section>

    <?php require 'footer.php'; ?>
    <?php include 'chatbox.php'; ?>

    <script>
        function changeMainImage(el) {
            document.getElementById('main-product-image').src = el.src;
        }

        function incrementQuantity() {
            const input = document.getElementById('quantity');
            const maxStock = <?= $stock ?>;
            let value = parseInt(input.value);
            
            if (value < maxStock && value < 99) {
                input.value = value + 1;
            } else {
                Swal.fire({
                    title: 'Thông báo',
                    text: 'Đã đạt số lượng tối đa',
                    icon: 'warning',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        function decrementQuantity() {
            const input = document.getElementById('quantity');
            let value = parseInt(input.value);
            if (value > 1) input.value = value - 1;
        }

        // Color selection functionality
        document.querySelectorAll('input[name="color_id"]').forEach(input => {
            input.addEventListener('change', function() {
                const colorId = this.value;
                const colorImages = <?= json_encode($colorImages) ?>;
                
                if (colorImages[colorId] && colorImages[colorId].length > 0) {
                    // Update main image
                    document.getElementById('main-product-image').src = colorImages[colorId][0];
                    
                    // Update gallery images (2 images below main image)
                    const galleryImages = document.querySelectorAll('.grid.grid-cols-2.gap-4 > div:not(.col-span-2) img');
                    
                    // Update first gallery image if available
                    if (colorImages[colorId][1] && galleryImages[0]) {
                        galleryImages[0].src = colorImages[colorId][1];
                    }
                    
                    // Update second gallery image if available
                    if (colorImages[colorId][2] && galleryImages[1]) {
                        galleryImages[1].src = colorImages[colorId][2];
                    }
                    
                    // If color has less than 3 images, use the first image for missing ones
                    if (!colorImages[colorId][1] && galleryImages[0]) {
                        galleryImages[0].src = colorImages[colorId][0];
                    }
                    if (!colorImages[colorId][2] && galleryImages[1]) {
                        galleryImages[1].src = colorImages[colorId][0];
                    }
                }
                
                // Update radio button styling
                document.querySelectorAll('input[name="color_id"]').forEach(radio => {
                    const div = radio.nextElementSibling;
                    if (radio.checked) {
                        div.classList.add('ring-2', 'ring-primary');
                        div.classList.remove('border-gray-200');
                    } else {
                        div.classList.remove('ring-2', 'ring-primary');
                        div.classList.add('border-gray-200');
                    }
                });
            });
        });

        // Size selection functionality
        document.querySelectorAll('input[name="size_id"]').forEach(input => {
            input.addEventListener('change', function() {
                document.querySelectorAll('input[name="size_id"]').forEach(radio => {
                    const div = radio.nextElementSibling;
                    if (radio.checked) {
                        div.classList.add('bg-primary', 'text-white', 'border-primary');
                        div.classList.remove('text-gray-800', 'border-gray-200');
                    } else {
                        div.classList.remove('bg-primary', 'text-white', 'border-primary');
                        div.classList.add('text-gray-800', 'border-gray-200');
                    }
                });
            });
        });

        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('text-primary', 'border-primary', 'active');
                    btn.classList.add('text-gray-600');
                });
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('text-primary', 'border-primary', 'active');
                this.classList.remove('text-gray-600');
                document.getElementById(tabId).classList.add('active');
            });
        });

        function toggleWishlist(productId) {
            fetch('add_to_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Thành công!',
                        text: data.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        title: 'Lỗi!',
                        text: data.message,
                        icon: 'error',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Auto-select first color and size on page load
        // Thêm vào cuối phần script, trước thẻ đóng 
        
        // Auto-select first size if not selected
        document.addEventListener('DOMContentLoaded', function() {
            const firstColorRadio = document.querySelector('input[name="color_id"]');
            if (firstColorRadio && !document.querySelector('input[name="color_id"]:checked')) {
                firstColorRadio.checked = true;
                firstColorRadio.dispatchEvent(new Event('change'));
            }
            
            const firstSizeRadio = document.querySelector('input[name="size_id"]');
            if (firstSizeRadio && !document.querySelector('input[name="size_id"]:checked')) {
                firstSizeRadio.checked = true;
                firstSizeRadio.dispatchEvent(new Event('change'));
            }
        });
        
        // Form validation and AJAX submit
        document.querySelector('form[action="auth/cart.php"]').addEventListener('submit', function(e) {
        e.preventDefault(); // Ngăn form submit bình thường
        
        const colorSelected = document.querySelector('input[name="color_id"]:checked');
        const sizeSelected = document.querySelector('input[name="size_id"]:checked');
        const submitButton = this.querySelector('button[type="submit"]');
        
        // Validation
        if (!colorSelected) {
            Swal.fire({
                title: 'Lỗi!',
                text: 'Vui lòng chọn màu sắc!',
                icon: 'error',
                timer: 2000,
                showConfirmButton: false
            });
            return;
        }
        
        if (!sizeSelected) {
            Swal.fire({
                title: 'Lỗi!',
                text: 'Vui lòng chọn kích thước!',
                icon: 'error',
                timer: 2000,
                showConfirmButton: false
            });
            return;
        }
        
        // Disable button và hiển thị loading
        const originalText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Đang thêm...';
        
        // Gửi dữ liệu bằng AJAX
        const formData = new FormData(this);
        
        fetch('auth/cart.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hiệu ứng thành công đẹp mắt
                Swal.fire({
                    title: 'Thành công!',
                    text: 'Đã thêm sản phẩm vào giỏ hàng!',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                    timerProgressBar: true
                });
            } else {
                Swal.fire({
                    title: 'Lỗi!',
                    text: data.message,
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Lỗi!',
                text: 'Có lỗi xảy ra khi thêm vào giỏ hàng!',
                icon: 'error',
                timer: 2000,
                showConfirmButton: false
            });
        })
        .finally(() => {
            // Khôi phục button
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        });
        });
    </script>

    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button.active {
            border-bottom: 2px solid;
        }
    </style>
</body>
</html>