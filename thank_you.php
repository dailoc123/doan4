<?php
session_start();
require 'db.php';

if (!isset($_GET['order_id'])) {
    header('Location: userhome.php');
    exit;
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    header('Location: userhome.php');
    exit;
}

$items_stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.image as product_image, 
    c.name as color_name, s.name as size_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN colors c ON oi.color_id = c.id
    LEFT JOIN sizes s ON oi.size_id = s.id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Calculate total
$total = 0;
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $total += $item['price'] * $item['quantity'];
    $items[] = $item;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công | Luxury Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #222;
            --secondary-color: #444;
            --accent-color: #000;
            --success-color: #4CAF50;
            --text-color: #333;
            --light-bg: #f9f9f9;
            --border-color: #e0e0e0;
            --border-radius: 12px;
            --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0,0,0,0.03) 0%, rgba(0,0,0,0) 100%);
            z-index: -1;
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #f2d74e;
            opacity: 0.7;
            top: 0;
            animation: confetti-fall linear forwards;
        }

        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }

        .thank-you-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 900px;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
            position: relative;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .thank-you-header {
            background: var(--accent-color);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .thank-you-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            z-index: 0;
        }

        .success-icon {
            width: 90px;
            height: 90px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            position: relative;
            z-index: 1;
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(76, 175, 80, 0); }
            100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
        }

        .success-icon i {
            font-size: 40px;
            color: white;
        }

        .thank-you-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .thank-you-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .thank-you-content {
            padding: 40px;
        }

        .order-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .summary-item {
            background: var(--light-bg);
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .summary-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }

        .summary-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--accent-color);
            transition: var(--transition);
        }

        .summary-item:hover::after {
            width: 100%;
        }

        .summary-label {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
        }

        .summary-value {
            font-weight: 600;
            color: var(--accent-color);
            font-size: 1.1rem;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--accent-color);
            margin-bottom: 20px;
            position: relative;
            padding-left: 15px;
        }

        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 25px;
            background: var(--accent-color);
            border-radius: 3px;
        }

        .products-list {
            list-style: none;
            margin-bottom: 40px;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 20px;
            background: var(--light-bg);
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .product-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent-color);
            transition: var(--transition);
        }

        .product-item:hover {
            transform: translateX(5px);
            background: white;
        }

        .product-item:hover::after {
            width: 100%;
        }

        .product-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .product-item:hover .product-image {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .product-info {
            flex: 1;
            margin-left: 20px;
        }

        .product-name {
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 5px;
            font-family: 'Playfair Display', serif;
        }

        .product-details {
            color: #777;
            font-size: 0.9rem;
        }

        .product-price {
            font-weight: 600;
            color: var(--accent-color);
            margin-left: 20px;
            font-size: 1.1rem;
            white-space: nowrap;
        }

        .total-section {
            background: var(--light-bg);
            padding: 20px;
            border-radius: var(--border-radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .total-label {
            font-size: 1.2rem;
            color: var(--accent-color);
            font-weight: 600;
        }

        .total-value {
            font-size: 1.5rem;
            color: var(--accent-color);
            font-weight: 700;
            font-family: 'Playfair Display', serif;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 15px 30px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            gap: 10px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
            z-index: -1;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            background: #333;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .action-btn.secondary {
            background: white;
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }

        .action-btn.secondary:hover {
            background: var(--light-bg);
            color: var(--accent-color);
        }

        @media (max-width: 768px) {
            .order-summary {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .thank-you-title {
                font-size: 1.8rem;
            }

            .thank-you-content {
                padding: 30px 20px;
            }

            .product-item {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }

            .product-info {
                margin: 15px 0;
            }

            .product-price {
                margin-left: 0;
            }

            .actions {
                flex-direction: column;
                gap: 15px;
            }

            .action-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="thank-you-container">
        <div class="thank-you-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="thank-you-title">Cảm ơn bạn đã mua hàng!</h1>
            <p class="thank-you-subtitle">Đơn hàng của bạn đã được xác nhận và đang được xử lý</p>
        </div>
        
        <div class="thank-you-content">
            <div class="order-summary">
                <div class="summary-item">
                    <span class="summary-label">Mã đơn hàng</span>
                    <div class="summary-value">#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Ngày đặt hàng</span>
                    <div class="summary-value"><?= date('d/m/Y', strtotime($order['created_at'])) ?></div>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Phương thức thanh toán</span>
                    <div class="summary-value">
                        <?php 
                        switch($order['payment_method']) {
                            case 'cod':
                                echo '<i class="fas fa-money-bill-wave"></i> COD';
                                break;
                            case 'momo':
                                echo '<i class="fas fa-wallet"></i> MoMo';
                                break;
                            case 'vnpay':
                                echo '<i class="fas fa-credit-card"></i> VNPAY';
                                break;
                            default:
                                echo strtoupper($order['payment_method']);
                        }
                        ?>
                    </div>
                </div>

                <?php if (!empty($order['voucher_code'])): ?>
                <div class="summary-item">
                    <span class="summary-label">Mã giảm giá</span>
                    <div class="summary-value"><?= htmlspecialchars($order['voucher_code']) ?></div>
                </div>
                <?php endif; ?>

                <?php if (isset($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                <div class="summary-item">
                    <span class="summary-label">Giảm giá</span>
                    <div class="summary-value"><?= number_format($order['discount_amount'],0,',','.') ?>₫</div>
                </div>
                <?php endif; ?>
            </div>

            <h3 class="section-title">Chi tiết đơn hàng</h3>
            <ul class="products-list">
                <?php foreach ($items as $item): ?>
                    <li class="product-item">
                        <img src="admin/<?= htmlspecialchars($item['product_image']) ?>" 
                             alt="<?= htmlspecialchars($item['product_name']) ?>" 
                             class="product-image">
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div class="product-details">
                                <?php if (!empty($item['color_name'])): ?>
                                    Màu: <?= htmlspecialchars($item['color_name']) ?> | 
                                <?php endif; ?>
                                
                                <?php if (!empty($item['size_name'])): ?>
                                    Size: <?= htmlspecialchars($item['size_name']) ?> | 
                                <?php endif; ?>
                                
                                SL: <?= $item['quantity'] ?>
                            </div>
                        </div>
                        <div class="product-price"><?= number_format($item['price'], 0, ',', '.') ?>₫</div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="total-section">
                <div class="total-label">Tổng thanh toán:</div>
                <div class="total-value"><?= number_format($total, 0, ',', '.') ?>₫</div>
            </div>

            <div class="actions">
                <a href="userhome.php" class="action-btn">
                    <i class="fas fa-home"></i>
                    Quay về trang chủ
                </a>
                <a href="order_detail.php" class="action-btn secondary">
                    <i class="fas fa-history"></i>
                    Xem lịch sử đơn hàng
                </a>
            </div>
        </div>
    </div>

    <script>
        // Create confetti effect
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
            
            // Animate product items
            const productItems = document.querySelectorAll('.product-item');
            productItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });
        });

        function createConfetti() {
            const colors = ['#f94144', '#f3722c', '#f8961e', '#f9c74f', '#90be6d', '#43aa8b', '#577590'];
            const totalConfetti = 100;
            
            for (let i = 0; i < totalConfetti; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = Math.random() * 10 + 5 + 'px';
                confetti.style.height = Math.random() * 10 + 5 + 'px';
                confetti.style.opacity = Math.random() + 0.5;
                confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                
                document.body.appendChild(confetti);
                
                // Remove confetti after animation completes
                setTimeout(() => {
                    confetti.remove();
                }, 8000);
            }
        }
    </script>
</body>
</html>
