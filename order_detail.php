<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: order_history.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = (int)$_GET['order_id'];
$error_message = '';

// Get order details
try {
    // Check if the order belongs to the current user
    $order_stmt = $conn->prepare("SELECT o.*, u.name as customer_name, u.address as shipping_address
                                 FROM orders o
                                 JOIN users u ON o.user_id = u.id
                                 WHERE o.id = ? AND o.user_id = ?");
    $order_stmt->bind_param("ii", $order_id, $user_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    
    if ($order_result->num_rows === 0) {
        header("Location: order_history.php");
        exit;
    }
    
    $order = $order_result->fetch_assoc();
    
    // Get order items
    $item_stmt = $conn->prepare("SELECT 
            oi.*, 
            p.name as product_name, 
            p.image, 
            c.name as color_name,
            s.name as size_name,
            oi.price,
            p.discount_price,
            oi.quantity,
            CASE 
                WHEN p.discount_price > 0 THEN p.discount_price * oi.quantity
                ELSE oi.price * oi.quantity 
            END as subtotal
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN colors c ON oi.color_id = c.id
        LEFT JOIN sizes s ON oi.size_id = s.id
        WHERE oi.order_id = ?");
    $item_stmt->bind_param("i", $order_id);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();
    
    $items = [];
    $total = 0;
    while ($item = $item_result->fetch_assoc()) {
        $total += $item['subtotal'];
        $items[] = $item;
    }
    
    // Get order status history
    $history_stmt = $conn->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
    $history_stmt->bind_param("i", $order_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    
    $status_history = [];
    while ($history = $history_result->fetch_assoc()) {
        $status_history[] = $history;
    }
    
} catch (Exception $e) {
    $error_message = "Đã xảy ra lỗi khi tải dữ liệu đơn hàng: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?= $order_id ?> | Luxury Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
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

        .order-detail-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .order-header {
            background: var(--accent-color);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-id {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .order-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .order-status {
            display: inline-block;
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

        .status-processing {
            background: rgba(33, 150, 243, 0.15);
            color: #2196F3;
            border: 1px solid #2196F3;
        }

        .status-shipping {
            background: rgba(33, 150, 243, 0.15);
            color: #2196F3;
            border: 1px solid #2196F3;
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

        .order-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
        }

        .meta-group {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .meta-value {
            font-weight: 500;
            color: var(--accent-color);
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--accent-color);
            margin-bottom: 20px;
            padding: 0 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }

        .items-table-container {
            padding: 0 30px 30px;
            overflow-x: auto;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            text-align: left;
            padding: 15px;
            background: var(--light-bg);
            color: var(--secondary-color);
            font-weight: 500;
            border-bottom: 1px solid var(--border-color);
        }

        .items-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-info {
            display: flex;
            flex-direction: column;
        }

        .product-name {
            font-weight: 500;
            color: var(--accent-color);
            margin-bottom: 5px;
        }

        .product-variant {
            font-size: 0.85rem;
            color: #777;
        }

        .price-cell, .quantity-cell, .subtotal-cell {
            font-weight: 500;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9rem;
            margin-right: 5px;
        }

        .discount-price {
            color: #E53935;
            font-weight: 600;
        }

        .order-summary {
            padding: 0 30px 30px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid var(--border-color);
        }

        .summary-label {
            color: #777;
        }

        .summary-value {
            font-weight: 500;
        }

        .total-label, .total-value {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--accent-color);
        }

        .status-timeline {
            padding: 0 30px 30px;
        }

        .timeline-container {
            position: relative;
            padding-left: 30px;
            margin-top: 30px;
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 10px;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -30px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--accent-color);
            z-index: 1;
        }

        .timeline-content {
            background: var(--light-bg);
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .timeline-date {
            font-size: 0.85rem;
            color: #777;
            margin-bottom: 5px;
        }

        .timeline-status {
            font-weight: 500;
            color: var(--accent-color);
            margin-bottom: 5px;
        }

        .timeline-note {
            font-size: 0.9rem;
            color: #555;
            font-style: italic;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-btn {
            background: var(--light-bg);
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }

        .back-btn:hover {
            background: #eee;
            transform: translateY(-3px);
        }

        .reorder-btn {
            background: var(--accent-color);
            color: white;
        }

        .reorder-btn:hover {
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

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .order-meta {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .items-table th:nth-child(3),
            .items-table td:nth-child(3) {
                display: none;
            }

            .actions {
                flex-direction: column;
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
            <h1 class="page-title">Chi tiết đơn hàng</h1>
            <p class="page-subtitle">Đơn hàng #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error_message ?></span>
            </div>
        <?php else: ?>
        <div class="order-detail-container">
            <div class="order-header">
                <div class="order-id">Đơn hàng #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></div>
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
                        $statusClass = 'status-processing';
                        $statusText = 'Đang chuẩn bị hàng';
                        break;
                    case 'shipping':
                        $statusClass = 'status-shipping';
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

            <div class="order-meta">
                <div class="meta-group">
                    <span class="meta-label">Phương thức thanh toán</span>
                    <span class="meta-value">
                        <?php 
                        switch($order['payment_method']) {
                            case 'cod':
                                echo '<i class="fas fa-money-bill-wave"></i> Thanh toán khi nhận hàng';
                                break;
                            case 'momo':
                                echo '<i class="fas fa-wallet"></i> Ví MoMo';
                                break;
                            case 'vnpay':
                                echo '<i class="fas fa-credit-card"></i> VNPAY';
                                break;
                            default:
                                echo strtoupper($order['payment_method']);
                        }
                        ?>
                    </span>
                </div>
                <div class="meta-group">
                    <span class="meta-label">Ngày đặt hàng</span>
                    <span class="meta-value"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="meta-group">
                    <span class="meta-label">Trạng thái thanh toán</span>
                    <span class="meta-value">
                        <?php if ($order['payment_status'] == 'paid' || $order['payment_status'] == 'completed'): ?>
                            <i class="fas fa-check-circle" style="color: var(--success-color);"></i> Đã thanh toán
                        <?php else: ?>
                            <i class="fas fa-clock" style="color: var(--pending-color);"></i> Chưa thanh toán
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <h3 class="section-title">Sản phẩm đã đặt</h3>
            <div class="items-table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <img src="admin/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="product-image">
                                        <div class="product-info">
                                            <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <div class="product-variant">
                                                <?php if (!empty($item['color_name'])): ?>
                                                    Màu: <?= htmlspecialchars($item['color_name']) ?> 
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($item['size_name'])): ?>
                                                    | Size: <?= htmlspecialchars($item['size_name']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="price-cell">
                                    <?php if ($item['discount_price'] > 0): ?>
                                        <span class="original-price"><?= number_format($item['price'], 0, ',', '.') ?>₫</span>
                                        <span class="discount-price"><?= number_format($item['discount_price'], 0, ',', '.') ?>₫</span>
                                    <?php else: ?>
                                        <?= number_format($item['price'], 0, ',', '.') ?>₫
                                    <?php endif; ?>
                                </td>
                                <td class="quantity-cell"><?= $item['quantity'] ?></td>
                                <td class="subtotal-cell"><?= number_format($item['subtotal'], 0, ',', '.') ?>₫</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="order-summary">
                <h3 class="section-title">Thông tin thanh toán</h3>
                
                <div class="summary-row">
                    <span class="summary-label">Tạm tính:</span>
                    <span class="summary-value"><?= number_format($total, 0, ',', '.') ?>₫</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Phí vận chuyển:</span>
                    <span class="summary-value">Miễn phí</span>
                </div>
                <?php if (!empty($order['voucher_code'])): ?>
                <div class="summary-row">
                    <span class="summary-label">Mã giảm giá (<?= htmlspecialchars($order['voucher_code']) ?>):</span>
                    <span class="summary-value">-<?= number_format($order['discount_amount'], 0, ',', '.') ?>₫</span>
                </div>
                <?php endif; ?>
                <div class="summary-row">
                    <span class="total-label">Tổng cộng:</span>
                    <span class="total-value"><?= number_format($order['total_price'], 0, ',', '.') ?>₫</span>
                </div>
            </div>
        </div>

        <div class="order-detail-container">
            <div class="order-items">
                <h3 class="section-title">Thông tin giao hàng</h3>
                
                <div class="order-meta">
                    <div class="meta-group">
                        <span class="meta-label">Họ và tên</span>
                        <span class="meta-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                    </div>
                    <div class="meta-group">
                        <span class="meta-label">Email</span>
                        <span class="meta-value"><?= htmlspecialchars($order['email']) ?></span>
                    </div>
                    <div class="meta-group">
                        <span class="meta-label">Số điện thoại</span>
                        <span class="meta-value"><?= htmlspecialchars($order['phone']) ?></span>
                    </div>
                    <div class="meta-group">
                        <span class="meta-label">Địa chỉ giao hàng</span>
                        <span class="meta-value"><?= htmlspecialchars($order['shipping_address'] ?? 'Không có thông tin') ?></span>
                    </div>
                    <?php if (!empty($order['note'])): ?>
                    <div class="meta-group">
                        <span class="meta-label">Ghi chú</span>
                        <span class="meta-value"><?= htmlspecialchars($order['note']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($status_history)): ?>
        <div class="order-detail-container">
            <div class="status-timeline">
                <h3 class="section-title">Lịch sử trạng thái đơn hàng</h3>
                
                <div class="timeline-container">
                    <?php foreach ($status_history as $status): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-date"><?= date('d/m/Y H:i', strtotime($status['created_at'])) ?></div>
                                <div class="timeline-status">
                                    <?php 
                                    switch($status['status']) {
                                        case 'pending':
                                            echo 'Đơn hàng đã được tiếp nhận';
                                            break;
                                        case 'processing':
                                            echo 'Đang chuẩn bị hàng';
                                            break;
                                        case 'shipping':
                                            echo 'Đang giao hàng';
                                            break;
                                        case 'completed':
                                            echo 'Đơn hàng đã hoàn thành';
                                            break;
                                        case 'cancelled':
                                            echo 'Đơn hàng đã bị hủy';
                                            break;
                                        default:
                                            echo ucfirst($status['status']);
                                    }
                                    ?>
                                </div>
                                <?php if (!empty($status['note'])): ?>
                                    <div class="timeline-note"><?= htmlspecialchars($status['note']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="actions">
            <a href="order_history.php" class="action-btn back-btn">
                <i class="fas fa-arrow-left"></i>
                Quay lại danh sách đơn hàng
            </a>
            <?php if ($order['status'] != 'cancelled'): ?>
            <a href="reorder.php?order_id=<?= $order['id'] ?>" class="action-btn reorder-btn">
                <i class="fas fa-redo"></i>
                Đặt lại đơn hàng này
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php require 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to product rows
            const productRows = document.querySelectorAll('.items-table tbody tr');
            productRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.5s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, 100 * index);
            });
            
            // Add animation to timeline items
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 200 + (100 * index));
            });
        });
    </script>
</body>
</html>