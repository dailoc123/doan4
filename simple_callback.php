<?php
// Simple callback for testing
header('HTTP/1.1 200 OK');
header('Content-Type: text/plain');
header('ngrok-skip-browser-warning: true'); // Thêm dòng này

// Log all data
error_log('MoMo Callback GET: ' . print_r($_GET, true));
error_log('MoMo Callback POST: ' . print_r($_POST, true));

echo "OK";
?>