<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$year = date('Y');
$current_time = date('Y-m-d H:i:s');
$vnpay_format = date('YmdHis');

echo "<h2>Kiểm tra thời gian nhanh:</h2>";
echo "<p><strong>Năm hiện tại:</strong> $year</p>";
echo "<p><strong>Thời gian:</strong> $current_time</p>";
echo "<p><strong>VNPAY format:</strong> $vnpay_format</p>";

if ($year > 2024) {
    echo "<p style='color: red; font-size: 18px;'><strong>❌ LỖI: Năm $year vẫn là tương lai!</strong></p>";
    echo "<p style='color: red;'>VNPAY sẽ từ chối giao dịch. Hãy chỉnh lại thời gian Windows.</p>";
} else {
    echo "<p style='color: green; font-size: 18px;'><strong>✅ OK: Thời gian hợp lệ!</strong></p>";
    echo "<p style='color: green;'>Có thể test VNPAY bình thường.</p>";
}
?>