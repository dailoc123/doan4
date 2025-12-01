<?php

session_start();
require '../db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : 0;

$query = "SELECT h.*, p.name as product_name 
          FROM inventory_history h
          LEFT JOIN products p ON h.product_id = p.id
          WHERE h.product_id = $product_id
          ORDER BY h.created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory History - Luxury Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h3>Lịch sử nhập xuất kho</h3>
        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Loại</th>
                            <th>Số lượng</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <?php if($row['type'] == 'import'): ?>
                                        <span class="badge bg-success">Nhập kho</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Xuất kho</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= htmlspecialchars($row['note']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>