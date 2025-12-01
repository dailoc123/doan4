<!-- filepath: c:\xampp\htdocs\luxury_store1\backend\admin\edit_voucher.php -->
<?php
require '../db.php';

$id = (int)$_GET['id'];
$voucher = $conn->query("SELECT * FROM vouchers WHERE id = $id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $discount = (float)$_POST['discount'];
    $expiry_date = $_POST['expiry_date'];

    $stmt = $conn->prepare("UPDATE vouchers SET code = ?, discount = ?, expiry_date = ? WHERE id = ?");
    $stmt->bind_param("sdsi", $code, $discount, $expiry_date, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: vouchers.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chỉnh sửa Voucher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">Chỉnh sửa Voucher</h1>
    <form method="POST">
        <div class="mb-3">
            <label for="code" class="form-label">Mã giảm giá</label>
            <input type="text" name="code" id="code" class="form-control" value="<?= htmlspecialchars($voucher['code']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="discount" class="form-label">Phần trăm giảm (%)</label>
            <input type="number" name="discount" id="discount" class="form-control" value="<?= $voucher['discount'] ?>" step="0.01" required>
        </div>
        <div class="mb-3">
            <label for="expiry_date" class="form-label">Ngày hết hạn</label>
            <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="<?= $voucher['expiry_date'] ?>" required>
        </div>
        <button type="submit" class="btn btn-success">Lưu thay đổi</button>
        <a href="vouchers.php" class="btn btn-secondary">Quay lại</a>
    </form>
</div>
</body>
</html>