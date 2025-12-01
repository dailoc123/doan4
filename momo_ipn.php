<?php
require_once 'db.php';
require_once 'momo_config.php';

// Log tất cả request
file_put_contents('momo_debug.log', date('Y-m-d H:i:s') . " - IPN called\n", FILE_APPEND);

// Nhận dữ liệu từ MoMo
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log chi tiết
file_put_contents('momo_debug.log', "Raw input: " . $input . "\n", FILE_APPEND);
file_put_contents('momo_debug.log', "Decoded data: " . print_r($data, true) . "\n", FILE_APPEND);

if ($data) {
    $orderId = $data['orderId'];
    $resultCode = $data['resultCode'];
    $message = $data['message'];
    
    file_put_contents('momo_debug.log', "Processing order: $orderId, result: $resultCode\n", FILE_APPEND);
    
    // Tách orderId thực từ unique orderId
    $real_order_id = explode('_', $orderId)[0];
    
    if ($resultCode == 0) {
        // Thanh toán thành công
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'confirmed' WHERE id = ?");
        $stmt->bind_param("i", $real_order_id);
        $result = $stmt->execute();
        
        file_put_contents('momo_debug.log', "Order update result: " . ($result ? 'success' : 'failed') . " for order ID: $real_order_id\n", FILE_APPEND);
    } else {
        // Thanh toán thất bại
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?");
        $stmt->bind_param("i", $real_order_id);
        $stmt->execute();
        
        file_put_contents('momo_debug.log', "Payment failed for order ID: $real_order_id, Result code: $resultCode\n", FILE_APPEND);
    }
}

http_response_code(200);
echo 'OK';
?>