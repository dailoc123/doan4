<?php
require_once 'momo_config.php';

function createMomoPaymentRequest($orderId, $amount, $orderInfo, $requestType = 'captureWallet') {
    // Tạo requestId và orderId unique
    $timestamp = time();
    $requestId = $timestamp . '_' . rand(10000, 99999);
    $uniqueOrderId = $orderId . '_' . $timestamp;
    $extraData = "";

    // Sửa lại rawHash theo đúng thứ tự alphabet
    $rawHash = "accessKey=" . MOMO_ACCESS_KEY .
        "&amount=" . $amount .
        "&extraData=" . $extraData .
        "&ipnUrl=" . MOMO_NOTIFY_URL .
        "&orderId=" . $uniqueOrderId .
        "&orderInfo=" . $orderInfo .
        "&partnerCode=" . MOMO_PARTNER_CODE .
        "&redirectUrl=" . MOMO_RETURN_URL .
        "&requestId=" . $requestId .
        "&requestType=captureWallet"; // Cố định requestType

    $signature = hash_hmac("sha256", $rawHash, MOMO_SECRET_KEY);

    $data = [
        'partnerCode' => MOMO_PARTNER_CODE,
        'partnerName' => 'Test', // Thêm partnerName
        'storeId' => MOMO_PARTNER_CODE, // Thêm storeId
        'requestId' => $requestId,
        'amount' => $amount, // Không chuyển thành string
        'orderId' => $uniqueOrderId,
        'orderInfo' => $orderInfo,
        'redirectUrl' => MOMO_RETURN_URL,
        'ipnUrl' => MOMO_NOTIFY_URL,
        'lang' => 'vi',
        'extraData' => $extraData,
        'requestType' => 'captureWallet', // Cố định
        'signature' => $signature,
    ];

    // Sử dụng error_log thay vì file_put_contents
    error_log('MoMo Request Data: ' . print_r($data, true));
    error_log('MoMo Raw Hash: ' . $rawHash);
    error_log('MoMo Signature: ' . $signature);
    error_log('MoMo Order ID: ' . $uniqueOrderId . ', Amount: ' . $amount);
    error_log('MoMo Return URL: ' . MOMO_RETURN_URL);
    error_log('MoMo Notify URL: ' . MOMO_NOTIFY_URL);

    $ch = curl_init(MOMO_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log response để debug
    error_log('MoMo Response HTTP Code: ' . $httpCode);
    error_log('MoMo Response: ' . $response);

    if ($error) {
        error_log('MoMo cURL Error: ' . $error);
        return ['url' => null, 'error' => 'Lỗi kết nối cURL: ' . $error];
    }

    $jsonResult = json_decode($response, true);

    if (isset($jsonResult['payUrl'])) {
        return ['url' => $jsonResult['payUrl'], 'error' => null];
    } else {
        $errorMessage = $jsonResult['message'] ?? 'Không nhận được phản hồi hợp lệ từ MoMo.';
        error_log('MoMo Payment Error: ' . print_r($jsonResult, true));
        return ['url' => null, 'error' => 'Lỗi từ MoMo: ' . $errorMessage . ' | Mã lỗi: ' . ($jsonResult['resultCode'] ?? 'N/A')];
    }
}
?>
