<?php
include '../db.php';

// Thêm màu
if (isset($_POST['add_color'])) {
    $name = $_POST['name'];
    $stmt = $conn->prepare("INSERT INTO colors (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    header("Location: color.php");
}

// Xóa màu
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM colors WHERE id = $id");
    header("Location: color.php");
}

// Lấy danh sách màu
$result = $conn->query("SELECT * FROM colors");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Màu</title>
    <style>
        :root {
            --main-color: #2196F3;
            --danger-color: #f44336;
            --bg-color: #f1f7ff;
            --card-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--bg-color);
            padding: 40px;
            margin: 0;
        }

        h2, h3 {
            color: #333;
        }

        form {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 30px;
        }

        input[type="text"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            width: 250px;
            font-size: 16px;
        }

        button {
            background-color: var(--main-color);
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        button:hover {
            background-color: #1976d2;
        }

        ul {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        li {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        li:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        a {
            color: var(--danger-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s;
        }

        a:hover {
            color: #c62828;
        }
    </style>
</head>
<body>
    <h2>Quản lý Màu</h2>
    <form method="POST">
        <input type="text" name="name" placeholder="Tên màu" required>
        <button type="submit" name="add_color">Thêm Màu</button>
    </form>

    <h3>Danh sách màu</h3>
    <ul>
        <?php while($row = $result->fetch_assoc()): ?>
            <li>
                <?= htmlspecialchars($row['name']) ?>
                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Xóa màu này?')">Xóa</a>
            </li>
        <?php endwhile; ?>
    </ul>
</body>
</html>
