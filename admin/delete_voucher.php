<!-- filepath: c:\xampp\htdocs\luxury_store1\backend\admin\delete_voucher.php -->
<?php
require '../db.php';

$id = (int)$_GET['id'];
$conn->query("DELETE FROM vouchers WHERE id = $id");
header("Location: vouchers.php");
exit();
?>