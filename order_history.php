<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all orders for this user
$stmt = $conn->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           SUM(oi.price * oi.quantity) as total_amount
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// Get user info
$stmt_user = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user = $user_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử đơn hàng | Luxury Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #222;
            --secondary-color: #444;
            --accent-color: #000;
            --success-color: #4CAF50;
            --pending-color: #FFC107;
            --cancelled-color: #F44336;
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
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--accent-color);
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--accent-color);
        }

        .page-subtitle {
            color: #777;
            font-size: 1.1rem;
        }

        .welcome-message {
            background: white;
            padding: 20px 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .welcome-text {
            font-size: 1.1rem;
        }

        .welcome-text strong {
            color: var(--accent-color);
            font-weight: 600;
        }

        .back-to-shop {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: var(--accent-color);
            color: white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            gap: 8px;
        }

        .back-to-shop:hover {
            background: #333;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .orders-container {
            display: grid;
            gap: 30px;
        }

        .order-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border-color);
            opacity: 0;
            transform: translateY(20px);
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            padding: 20px 25px;
            background: var(--light-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .order-id {
            font-weight: 600;
            color: var(--accent-color);
            font-size: 1.1rem;
        }

        .order-date {
            color: #777;
            font-size: 0.9rem;
        }

        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.15);
            color: var(--pending-color);
            border: 1px solid var(--pending-color);
        }

        .status-completed {
            background: rgba(76, 175, 80, 0.15);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .status-cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: var(--cancelled-color);
            border: 1px solid var(--cancelled-color);
        }

        .order-content {
            padding: 25px;
        }

        .order-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-item {
            text-align: center;
        }

        .summary-label {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .summary-value {
            font-weight: 600;
            color: var(--accent-color);
            font-size: 1.1rem;
        }

        .order-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            gap: 8px;
        }

        .view-details {
            background: var(--accent-color);
            color: white;
        }

        .view-details:hover {
            background: #333;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .reorder {
            background: white;
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }

        .reorder:hover {
            background: var(--light-bg);
            transform: translateY(-3px);
        }

        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .empty-icon {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--accent-color);
            margin-bottom: 15px;
        }

        .empty-text {
            color: #777;
            margin-bottom: 30px;
        }

        .shop-now-btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 25px;
            background: var(--accent-color);
            color: white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            gap: 8px;
        }

        .shop-now-btn:hover {
            background: #333;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
                margin: 20px auto;
            }

            .page-title {
                font-size: 2rem;
            }

            .welcome-message {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 20px;
            }

            .order-summary {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .order-actions {
                flex-direction: column;
                gap: 15px;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php require 'header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Lịch sử đơn hàng</h1>
            <p class="page-subtitle">Xem lại các đơn hàng bạn đã đặt</p>
        </div>

        <div class="welcome-message">
            <div class="welcome-text">
                Xin chào <strong><?= htmlspecialchars($user['name'] ?? 'Quý khách') ?></strong>, đây là danh sách đơn hàng của bạn.
            </div>
            <a href="userhome.php" class="back-to-shop">
                <i class="fas fa-home"></i>
                Quay về trang chủ
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3 class="empty-title">Bạn chưa có đơn hàng nào</h3>
                <p class="empty-text">Hãy khám phá các sản phẩm của chúng tôi và đặt hàng ngay!</p>
                <a href="products.php" class="shop-now-btn">
                    <i class="fas fa-shopping-cart"></i>
                    Mua sắm ngay
                </a>
            </div>
        <?php else: ?>
            <div class="orders-container">
                <?php foreach ($orders as $index => $order): ?>
                    <div class="order-card" data-index="<?= $index ?>">
                        <div class="order-header">
                            <div class="order-id">Đơn hàng #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                            <div class="order-date"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
                            <?php
                            $statusClass = '';
                            $statusText = '';
                            
                            switch($order['status']) {
                                case 'pending':
                                    $statusClass = 'status-pending';
                                    $statusText = 'Đang xử lý';
                                    break;
                                case 'processing':
                                    $statusClass = 'status-pending';
                                    $statusText = 'Đang chuẩn bị hàng';
                                    break;
                                case 'shipping':
                                    $statusClass = 'status-pending';
                                    $statusText = 'Đang giao hàng';
                                    break;
                                case 'completed':
                                    $statusClass = 'status-completed';
                                    $statusText = 'Hoàn thành';
                                    break;
                                case 'cancelled':
                                    $statusClass = 'status-cancelled';
                                    $statusText = 'Đã hủy';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                                    $statusText = 'Đang xử lý';
                            }
                            ?>
                            <span class="order-status <?= $statusClass ?>"><?= $statusText ?></span>
                        </div>
                        <div class="order-content">
                            <div class="order-summary">
                                <div class="summary-item">
                                    <div class="summary-label">Số lượng sản phẩm</div>
                                    <div class="summary-value"><?= $order['item_count'] ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Tổng thanh toán</div>
                                    <div class="summary-value"><?= number_format($order['total_amount'], 0, ',', '.') ?>₫</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Phương thức thanh toán</div>
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
                            </div>
                            <div class="order-actions">
                                <a href="order_detail.php?order_id=<?= $order['id'] ?>" class="action-btn view-details">
                                    <i class="fas fa-eye"></i>
                                    Xem chi tiết
                                </a>
                                <?php if ($order['status'] != 'cancelled'): ?>
                                <a href="#" class="action-btn reorder" data-order-id="<?= $order['id'] ?>">
                                    <i class="fas fa-redo"></i>
                                    Đặt lại
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php require 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate order cards
            const orderCards = document.querySelectorAll('.order-card');
            orderCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
            
            // Handle reorder buttons
            const reorderButtons = document.querySelectorAll('.reorder');
            reorderButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const orderId = this.getAttribute('data-order-id');
                    
                    // Show confirmation dialog
                    if (confirm('Bạn có muốn đặt lại đơn hàng này không?')) {
                        // Redirect to reorder page
                        window.location.href = 'reorder.php?order_id=' + orderId;
                    }
                });
            });
        });
    </script>
</body>
</html>