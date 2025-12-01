<?php
include '../db.php';

// Thêm size
if (isset($_POST['add_size'])) {
    $name = $_POST['name'];
    $stmt = $conn->prepare("INSERT INTO sizes (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    header("Location: size.php");
}

// Xóa size
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM sizes WHERE id = $id");
    header("Location: size.php");
}

// Lấy danh sách size
$result = $conn->query("SELECT * FROM sizes");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Size</title>
    <style>
        :root {
            --main-color: #4CAF50;
            --danger-color: #f44336;
            --bg-color: #f9f9f9;
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
            background-color: #388e3c;
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
    <h2>Quản lý Size</h2>
    <form method="POST">
        <input type="text" name="name" placeholder="Tên size" required>
        <button type="submit" name="add_size">Thêm Size</button>
    </form>

    <h3>Danh sách size</h3>
    <ul>
        <?php while($row = $result->fetch_assoc()): ?>
            <li>
                <?= htmlspecialchars($row['name']) ?>
                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Xóa size này?')">Xóa</a>
            </li>
        <?php endwhile; ?>
    </ul>
</body>
</html>
