<?php
// MoMo API Configuration
define('MOMO_PARTNER_CODE', 'MOMOBKUN20180529');
define('MOMO_ACCESS_KEY', 'klm05TvNBzhg7h7j');
define('MOMO_SECRET_KEY', 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa');
define('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create');

// Cập nhật URL ngrok mới (bỏ khoảng trắng thừa)
$publicUrl = 'https://0d5b154066b4.ngrok-free.app/luxury_store1/backend';

// Sử dụng callback đơn giản cho test
if (strpos($_SERVER['REQUEST_URI'], 'test_momo') !== false) {
    define('MOMO_RETURN_URL', $publicUrl . '/simple_callback.php');
    define('MOMO_NOTIFY_URL', $publicUrl . '/simple_callback.php');
} else {
    define('MOMO_RETURN_URL', $publicUrl . '/momo_handler.php');
    define('MOMO_NOTIFY_URL', $publicUrl . '/momo_ipn.php');
}
?>