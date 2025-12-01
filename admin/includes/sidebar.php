<aside class="sidebar">
    <div class="logo-container">
        <h1>Luxury Store</h1>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-category">Tổng quan</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="stats.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> Thống kê
                </a>
            </li>
        </ul>

        <div class="menu-category">Quản lý sản phẩm</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i> Sản phẩm
                </a>
            </li>
            <li class="nav-item">
                <a href="category.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'category.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> Danh mục
                </a>
            </li>
            <li class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i> Kho hàng
                </a>
            </li>
        </ul>

        <div class="menu-category">Đơn hàng & Khách hàng</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="orders.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Đơn hàng</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <p>Khách hàng</p>
                </a>
            </li>

            <!-- thêm chat admin -->
            <li class="nav-item">
                <a href="chat_admin.php" class="nav-link">
                    <i class="fas fa-comments"></i>
                    <p>Chat</p>
                </a>
            </li>
        </ul>

        <div class="menu-category">Marketing</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="vouchers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vouchers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-ticket-alt"></i> Mã giảm giá
                </a>
            </li>
        </ul>

        <div class="menu-category">Hệ thống</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="javascript:handleLogout()" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </li>
        </ul>
    </nav>
</aside>