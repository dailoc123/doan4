<?php
require '../db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
    $conn->begin_transaction();
    
    try {
        // 1. Xóa các bản ghi liên quan trong bảng wishlist
        $conn->query("DELETE FROM wishlist WHERE product_id = $id");
        
        // 2. Xóa các bản ghi liên quan trong bảng cart
        $conn->query("DELETE FROM cart WHERE product_id = $id");
        
        // 3. Xóa các bản ghi liên quan trong bảng product_variants
        $conn->query("DELETE FROM product_variants WHERE product_id = $id");
        
        // 4. Xóa các bản ghi liên quan trong bảng product_images
        $conn->query("DELETE FROM product_images WHERE product_id = $id");
        
        // 5. Xóa lịch sử inventory
        $conn->query("DELETE FROM inventory_history WHERE product_id = $id");
        
        // 6. Xóa inventory
        $conn->query("DELETE FROM inventory WHERE product_id = $id");
        
        // 7. Cuối cùng xóa sản phẩm
        $conn->query("DELETE FROM products WHERE id = $id");
        
        // Commit transaction nếu tất cả các truy vấn thành công
        $conn->commit();
        
        header('Location: products.php?success=Xóa sản phẩm thành công');
        exit;
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollback();
        
        header('Location: products.php?error=' . urlencode('Lỗi khi xóa sản phẩm: ' . $e->getMessage()));
        exit;
    }
}

header('Location: products.php');
exit;
?>