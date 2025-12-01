<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nếu đã đăng nhập: xóa theo wishlist_id trong DB
    if (isset($_SESSION['user_id'])) {
        $wishlistId = isset($_POST['wishlist_id']) ? intval($_POST['wishlist_id']) : 0;
        $userId = $_SESSION['user_id'];

        if ($wishlistId > 0) {
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $wishlistId, $userId);

            if ($stmt->execute()) {
                // Cập nhật số lượng sản phẩm yêu thích trong session
                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
                $countStmt->bind_param("i", $userId);
                $countStmt->execute();
                $result = $countStmt->get_result();
                $row = $result->fetch_assoc();
                $_SESSION['wishlist_count'] = (int)$row['count'];

                echo json_encode(['success' => true, 'count' => $_SESSION['wishlist_count']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể xóa sản phẩm khỏi danh sách yêu thích']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        }
    } else {
        // Khách: xóa theo product_id trong session
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if ($productId > 0) {
            if (!isset($_SESSION['guest_wishlist']) || !is_array($_SESSION['guest_wishlist'])) {
                $_SESSION['guest_wishlist'] = [];
            }
            $before = count($_SESSION['guest_wishlist']);
            // Loại bỏ productId khỏi danh sách
            $_SESSION['guest_wishlist'] = array_values(array_diff($_SESSION['guest_wishlist'], [$productId]));
            $_SESSION['wishlist_count'] = count($_SESSION['guest_wishlist']);
            $removed = count($_SESSION['guest_wishlist']) < $before;
            echo json_encode(['success' => true, 'removed' => $removed, 'count' => $_SESSION['wishlist_count']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
}
?>