<?php
// Quick test script for chatbot API logic
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/chatbot_api.php';

$chatbot = new ChatbotAPI();
$chatbot->createChatTable();

$tests = [
    ['user_id' => 1001, 'message' => 'hello'],
    ['user_id' => 1002, 'message' => 'mình muốn tìm áo nam giá dưới 500k'],
];

$results = [];
foreach ($tests as $t) {
    $res = $chatbot->processMessage($t['user_id'], $t['message']);
    $results[] = [
        'input' => $t,
        'output_type' => $res['response']['type'] ?? 'text',
        'output_message' => is_array($res['response']) ? ($res['response']['message'] ?? '') : $res['response'],
        'products_count' => isset($res['response']['products']) ? count($res['response']['products']) : 0,
    ];
}

echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>