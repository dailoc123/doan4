<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debug VNPAY Payment</h2>";
echo "<h3>POST Data:</h3>";
echo "<pre>" . print_r($_POST, true) . "</pre>";
echo "<h3>Session:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
?>

<form method="POST">
    <h3>Test Form:</h3>
    <input type="hidden" name="total_amount" value="100000">
    <input type="hidden" name="customer_name" value="Test User">
    <input type="hidden" name="customer_email" value="test@example.com">
    <input type="hidden" name="customer_phone" value="0123456789">
    <input type="hidden" name="customer_address" value="Test Address">
    <button type="submit">Test VNPAY</button>
</form>
