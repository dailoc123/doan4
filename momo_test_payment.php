<?php
session_start();

$orderId = $_GET['orderId'] ?? '';
$amount = $_GET['amount'] ?? 0;
$orderInfo = $_GET['orderInfo'] ?? '';

if (empty($orderId)) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoMo Test Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .payment-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .momo-logo {
            color: #d82d8b;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .order-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        .amount {
            font-size: 24px;
            color: #d82d8b;
            font-weight: bold;
            margin: 20px 0;
        }
        .btn {
            padding: 12px 30px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="momo-logo">MoMo Test Payment</div>
        
        <div class="order-info">
            <p><strong>Mã đơn hàng:</strong> <?= htmlspecialchars($orderId) ?></p>
            <p><strong>Thông tin:</strong> <?= htmlspecialchars($orderInfo) ?></p>
        </div>
        
        <div class="amount"><?= number_format($amount, 0, ',', '.') ?>₫</div>
        
        <p>Chọn kết quả thanh toán để test:</p>
        
        <a href="momo_test_callback.php?orderId=<?= urlencode($orderId) ?>&resultCode=0&message=Thanh toán thành công" class="btn btn-success">
            ✓ Thanh toán thành công
        </a>
        
        <a href="momo_test_callback.php?orderId=<?= urlencode($orderId) ?>&resultCode=1&message=Thanh toán thất bại" class="btn btn-danger">
            ✗ Thanh toán thất bại
        </a>
        
        <br><br>
        <a href="checkout.php" style="color: #666; text-decoration: none;">← Quay lại checkout</a>
    </div>
</body>
</html>