<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['message']) || empty(trim($input['message']))) {
        echo json_encode([
            'success' => false,
            'error' => 'Message is required'
        ]);
        exit;
    }
    
    $message = trim($input['message']);
    
    // Simple response for testing
    echo json_encode([
        'success' => true,
        'response' => "Bạn đã gửi: " . $message,
        'language' => 'vi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
?>