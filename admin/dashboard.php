<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Thống kê tổng quan
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT CASE WHEN i.quantity <= i.min_stock THEN p.id END) as low_stock,
        COUNT(DISTINCT CASE WHEN i.quantity = 0 THEN p.id END) as out_of_stock,
        SUM(i.quantity) as total_inventory
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
"));

// Top 5 sản phẩm bán chạy
$top_products = mysqli_query($conn, "
    SELECT p.name, COUNT(ih.id) as transaction_count, SUM(ih.quantity) as total_quantity
    FROM products p
    JOIN inventory_history ih ON p.id = ih.product_id
    WHERE ih.type = 'export'
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 5
");

// Thống kê nhập xuất theo tháng
$monthly_stats = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN type = 'import' THEN quantity ELSE 0 END) as total_import,
        SUM(CASE WHEN type = 'export' THEN quantity ELSE 0 END) as total_export
    FROM inventory_history
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
");

// Sản phẩm sắp hết hàng
$low_stock_products = mysqli_query($conn, "
    SELECT p.name, p.image, i.quantity, i.min_stock
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    WHERE i.quantity <= i.min_stock
    ORDER BY i.quantity ASC
    LIMIT 5
");

// Chuẩn bị dữ liệu cho biểu đồ
$months = [];
$imports = [];
$exports = [];
while($row = mysqli_fetch_assoc($monthly_stats)) {
    $months[] = date('m/Y', strtotime($row['month']));
    $imports[] = $row['total_import'];
    $exports[] = $row['total_export'];
}
$months = array_reverse($months);
$imports = array_reverse($imports);
$exports = array_reverse($exports);

// Chuyển đổi dữ liệu thành JSON để sử dụng trong JavaScript
$chartData = [
    'labels' => $months,
    'imports' => $imports,
    'exports' => $exports
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Luxury Store Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3949ab;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #3949ab;
            --sidebar-text: #94a3b8;
            --sidebar-active-text: #ffffff;
            --card-bg: rgba(255, 255, 255, 0.9);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        body {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            color: #fff;
        }

        #canvas-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .wrapper {
            display: flex;
            position: relative;
        }

        /* Sidebar styling */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }

        .logo-container {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo-container h1 {
            color: #fff;
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-category {
            color: var(--sidebar-text);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 1rem 1.5rem 0.5rem;
            font-weight: 600;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--sidebar-text);
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            color: var(--sidebar-active-text);
            background: var(--sidebar-hover);
            border-left-color: var(--primary-color);
        }

        .nav-link.active {
            color: var(--sidebar-active-text);
            background: var(--sidebar-active);
            border-left-color: #fff;
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
            width: 20px;
            text-align: center;
        }

        /* Main content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
            transition: all 0.3s;
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(45deg, #4361ee, #805dca);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 10px rgba(67, 97, 238, 0.3);
        }

        /* Stat cards */
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            height: 100%;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: var(--dark-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(67, 97, 238, 0.1);
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
            overflow: hidden;
            color: var(--dark-color);
        }

        .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }

        /* Tables */
        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            border-top: none;
            background: rgba(0, 0, 0, 0.02);
        }

        .table td {
            vertical-align: middle;
        }

        /* Badges */
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            border-radius: 6px;
        }

        .bg-success {
            background: #10b981 !important;
        }

        .bg-warning {
            background: #f59e0b !important;
        }

        .bg-danger {
            background: #ef4444 !important;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .toggle-sidebar {
                display: block;
            }
        }

        /* 3D Elements */
        .card-3d {
            perspective: 1000px;
        }

        .card-3d-inner {
            transition: transform 0.6s;
            transform-style: preserve-3d;
        }

        .card-3d:hover .card-3d-inner {
            transform: rotateY(5deg) rotateX(5deg);
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* 3D Globe */
        #globe-container {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 200px;
            height: 200px;
            z-index: 10;
        }
    </style>
</head>
<body>
    <!-- 3D Background Canvas -->
    <div id="canvas-container"></div>

    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-container">
                <h1>Luxury Store</h1>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-category">Tổng quan</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="stats.php" class="nav-link">
                            <i class="fas fa-chart-line"></i> Thống kê
                        </a>
                    </li>
                </ul>

                <div class="menu-category">Quản lý sản phẩm</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <i class="fas fa-box"></i> Sản phẩm
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="category.php" class="nav-link">
                            <i class="fas fa-tags"></i> Danh mục
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="inventory.php" class="nav-link">
                            <i class="fas fa-warehouse"></i> Kho hàng
                        </a>
                    </li>
                </ul>

                <div class="menu-category">Đơn hàng & Khách hàng</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link">
                            <i class="fas fa-shopping-cart"></i> Đơn hàng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-users"></i> Khách hàng
                        </a>
                    </li>
                </ul>

                <div class="menu-category">Marketing</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="vouchers.php" class="nav-link">
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- 3D Globe -->
            <div id="globe-container"></div>

            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <button class="btn btn-primary d-lg-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Stats Row -->
            <div class="row mb-4">
                <!-- Tổng sản phẩm -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card card-3d">
                        <div class="card-3d-inner d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Tổng sản phẩm
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total_products'] ?? 0) ?>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-box fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sản phẩm sắp hết hàng -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card card-3d">
                        <div class="card-3d-inner d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Sắp hết hàng
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['low_stock'] ?? 0) ?>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sản phẩm hết hàng -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card card-3d">
                        <div class="card-3d-inner d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Hết hàng
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['out_of_stock'] ?? 0) ?>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-times-circle fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tổng tồn kho -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card card-3d">
                        <div class="card-3d-inner d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Tổng tồn kho
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($stats['total_inventory'] ?? 0) ?>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-warehouse fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts & Tables Row -->
            <div class="row">
                <!-- Top sản phẩm -->
                <div class="col-xl-6 mb-4">
                    <div class="card card-3d">
                        <div class="card-3d-inner">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Top sản phẩm bán chạy</h6>
                                <a href="products.php" class="btn btn-sm btn-primary">
                                    Xem tất cả
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sản phẩm</th>
                                                <th>Số lượng bán</th>
                                                <th>Tình trạng</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($product = mysqli_fetch_assoc($top_products)): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($product['name']) ?></td>
                                                <td><?= number_format($product['total_quantity']) ?></td>
                                                <td>
                                                    <span class="badge bg-success">Đang bán</span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sản phẩm sắp hết hàng -->
                <div class="col-xl-6 mb-4">
                    <div class="card card-3d">
                        <div class="card-3d-inner">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-warning">Sản phẩm sắp hết hàng</h6>
                                <a href="inventory.php" class="btn btn-sm btn-warning">
                                    Quản lý kho
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sản phẩm</th>
                                                <th>Tồn kho</th>
                                                <th>Mức tối thiểu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($product = mysqli_fetch_assoc($low_stock_products)): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($product['name']) ?></td>
                                                <td><?= number_format($product['quantity']) ?></td>
                                                <td><?= number_format($product['min_stock']) ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Stats Chart -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-3d">
                        <div class="card-3d-inner">
                            <div class="card-header bg-white py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Thống kê nhập xuất theo tháng</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyStats"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3D Product Showcase -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card card-3d">
                        <div class="card-3d-inner">
                            <div class="card-header bg-white py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Hiển thị sản phẩm 3D</h6>
                            </div>
                            <div class="card-body">
                                <div id="product-showcase" style="height: 300px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/examples/js/controls/OrbitControls.js"></script>

    <script>
        // Xử lý đăng xuất
        function handleLogout() {
            if(confirm('Bạn có chắc muốn đăng xuất?')) {
                window.location.href = 'logout.php';
            }
        }

        // Xử lý responsive sidebar
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Khởi tạo biểu đồ thống kê theo tháng
        const ctx = document.getElementById('monthlyStats').getContext('2d');
        const chartData = <?= json_encode($chartData) ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Nhập kho',
                    data: chartData.imports,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.4
                },
                {
                    label: 'Xuất kho',
                    data: chartData.exports,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Khởi tạo hiệu ứng 3D background
        function initBackground() {
            const container = document.getElementById('canvas-container');
            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(window.devicePixelRatio);
            container.appendChild(renderer.domElement);
            
            // Tạo các hạt 3D
            const particlesGeometry = new THREE.BufferGeometry();
            const particlesCount = 1500;
            
            const posArray = new Float32Array(particlesCount * 3);
            for(let i = 0; i < particlesCount * 3; i++) {
                posArray[i] = (Math.random() - 0.5) * 10;
            }
            
            particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
            
            const particlesMaterial = new THREE.PointsMaterial({
                size: 0.02,
                color: 0x4361ee,
                transparent: true,
                opacity: 0.8
            });
            
            const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
            scene.add(particlesMesh);
            
            camera.position.z = 5;
            
            // Animation
            function animate() {
                requestAnimationFrame(animate);
                particlesMesh.rotation.x += 0.0005;
                particlesMesh.rotation.y += 0.0005;
                renderer.render(scene, camera);
            }
            
            animate();
            
            // Resize handler
            window.addEventListener('resize', () => {
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
        }

        // Khởi tạo Globe 3D
        function initGlobe() {
            const container = document.getElementById('globe-container');
            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, 1, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            
            renderer.setSize(200, 200);
            container.appendChild(renderer.domElement);
            
            // Tạo globe
            const geometry = new THREE.SphereGeometry(1, 32, 32);
            const material = new THREE.MeshBasicMaterial({
                color: 0x3949ab,
                wireframe: true
            });
            
            const globe = new THREE.Mesh(geometry, material);
            scene.add(globe);
            
            camera.position.z = 2;
            
            // Controls
            const controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.enableZoom = false;
            
            // Animation
            function animate() {
                requestAnimationFrame(animate);
                globe.rotation.y += 0.005;
                controls.update();
                renderer.render(scene, camera);
            }
            
            animate();
        }

        // Khởi tạo Product Showcase 3D
        function initProductShowcase() {
            const container = document.getElementById('product-showcase');
            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            
            renderer.setSize(container.clientWidth, container.clientHeight);
            container.appendChild(renderer.domElement);
            
            // Tạo các khối 3D đại diện cho sản phẩm
            const products = [];
            const colors = [0x4361ee, 0x3949ab, 0x805dca, 0x10b981, 0xf59e0b];
            
            for(let i = 0; i < 5; i++) {
                const geometry = new THREE.BoxGeometry(1, 1, 1);
                const material = new THREE.MeshBasicMaterial({ color: colors[i] });
                const cube = new THREE.Mesh(geometry, material);
                cube.position.x = (i - 2) * 2;
                cube.position.y = Math.sin(i) * 0.5;
                scene.add(cube);
                products.push(cube);
            }
            
            camera.position.z = 6;
            
            // Controls
            const controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            
            // Animation
            function animate() {
                requestAnimationFrame(animate);
                
                products.forEach((product, index) => {
                    product.rotation.x += 0.01;
                    product.rotation.y += 0.01;
                    product.position.y = Math.sin(Date.now() * 0.001 + index) * 0.5;
                });
                
                controls.update();
                renderer.render(scene, camera);
            }
            
            animate();
            
            // Resize handler
            window.addEventListener('resize', () => {
                camera.aspect = container.clientWidth / container.clientHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(container.clientWidth, container.clientHeight);
            });
        }

        // Khởi tạo tất cả các hiệu ứng 3D
        document.addEventListener('DOMContentLoaded', () => {
            initBackground();
            initGlobe();
            initProductShowcase();
            
            // Hiệu ứng hover cho card-3d
            document.querySelectorAll('.card-3d').forEach(card => {
                card.addEventListener('mousemove', e => {
                    const cardRect = card.getBoundingClientRect();
                    const cardCenterX = cardRect.left + cardRect.width / 2;
                    const cardCenterY = cardRect.top + cardRect.height / 2;
                    const angleX = (e.clientY - cardCenterY) / 15;
                    const angleY = (cardCenterX - e.clientX) / 15;
                    
                    card.querySelector('.card-3d-inner').style.transform = 
                        `rotateX(${angleX}deg) rotateY(${angleY}deg)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.querySelector('.card-3d-inner').style.transform = 
                        'rotateX(0) rotateY(0)';
                });
            });
        });
    </script>
</body>
</html>