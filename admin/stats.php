<?php

session_start();
require '../db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Helper: an toàn chạy query đơn giản
function fetch_assoc_safe($conn, $sql, $types = null, $params = []) {
    if ($types === null) {
        $res = $conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt = $conn->prepare($sql);
    if ($stmt === false) return [];
    if (!empty($params)) {
        $bind_names = [];
        $bind_names[] = $types;
        foreach ($params as $k => $v) $bind_names[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $data;
}

// Kiểm tra và chuẩn hóa trạng thái cuối cùng từ bảng lịch sử
$has_history = false;
$checkHist = $conn->query("SHOW TABLES LIKE 'order_status_history'");
if ($checkHist && $checkHist->num_rows > 0) { $has_history = true; }

// Subquery lấy trạng thái cuối cùng cho mỗi đơn
$latestStatusSQL = "
    SELECT h.order_id, h.status, h.created_at AS status_time
    FROM order_status_history h
    JOIN (
        SELECT order_id, MAX(id) AS max_id
        FROM order_status_history
        GROUP BY order_id
    ) x ON x.order_id = h.order_id AND x.max_id = h.id
";

// --- Tổng quan số liệu ---
$revenue_row = $has_history
    ? (fetch_assoc_safe($conn, "SELECT COALESCE(SUM(o.total_price),0) AS revenue FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id WHERE ls.status = 'completed'" )[0] ?? ['revenue'=>0])
    : (fetch_assoc_safe($conn, "SELECT COALESCE(SUM(total_price),0) AS revenue FROM orders WHERE status = 'completed'" )[0] ?? ['revenue'=>0]);
$total_revenue = (float)$revenue_row['revenue'];

$total_orders_row = $has_history
    ? (fetch_assoc_safe($conn, "SELECT COUNT(*) AS total_orders FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id WHERE ls.status = 'completed'" )[0] ?? ['total_orders'=>0])
    : (fetch_assoc_safe($conn, "SELECT COUNT(*) AS total_orders FROM orders WHERE status = 'completed'" )[0] ?? ['total_orders'=>0]);
$total_orders = (int)$total_orders_row['total_orders'];

$avg_order = $has_history
    ? (fetch_assoc_safe($conn, "SELECT COALESCE(AVG(o.total_price),0) AS avg_order FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id WHERE ls.status = 'completed'" )[0] ?? ['avg_order'=>0])
    : (fetch_assoc_safe($conn, "SELECT COALESCE(AVG(total_price),0) AS avg_order FROM orders WHERE status = 'completed'" )[0] ?? ['avg_order'=>0]);
$avg_order_value = (float)$avg_order['avg_order'];

// Doanh thu hôm nay / tháng này
$today_row = $has_history
    ? (fetch_assoc_safe($conn, "SELECT COALESCE(SUM(o.total_price),0) AS today_revenue FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id WHERE ls.status='completed' AND DATE(o.created_at)=CURDATE()" )[0] ?? ['today_revenue'=>0])
    : (fetch_assoc_safe($conn, "SELECT COALESCE(SUM(total_price),0) AS today_revenue FROM orders WHERE status='completed' AND DATE(created_at)=CURDATE()" )[0] ?? ['today_revenue'=>0]);
$today_revenue = (float)$today_row['today_revenue'];

$month_row = $has_history
    ? (fetch_assoc_safe($conn, "SELECT COALESCE(SUM(o.total_price),0) AS month_revenue FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id WHERE ls.status='completed' AND MONTH(o.created_at)=MONTH(CURDATE()) AND YEAR(o.created_at)=YEAR(CURDATE())" )[0] ?? ['month_revenue'=>0])
    : (fetch_assoc_safe($conn, "SELECT COALESCE(SUM(total_price),0) AS month_revenue FROM orders WHERE status='completed' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())" )[0] ?? ['month_revenue'=>0]);
$month_revenue = (float)$month_row['month_revenue'];

// Orders by status
$orders_by_status = $has_history
    ? fetch_assoc_safe($conn, "SELECT ls.status, COUNT(*) AS cnt FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id GROUP BY ls.status")
    : fetch_assoc_safe($conn, "SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");

// Revenue last 12 months
$revenue_12m_raw = $has_history
    ? fetch_assoc_safe($conn, "
        SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS ym, COALESCE(SUM(o.total_price),0) AS revenue
        FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id
        WHERE ls.status = 'completed' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY ym
        ORDER BY ym ASC
    " )
    : fetch_assoc_safe($conn, "
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total_price),0) AS revenue
        FROM orders
        WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY ym
        ORDER BY ym ASC
    ");

// Orders per day last 30 days
$orders_30d_raw = $has_history
    ? fetch_assoc_safe($conn, "
        SELECT DATE(o.created_at) AS d, COALESCE(COUNT(*),0) AS orders_count, COALESCE(SUM(o.total_price),0) AS revenue
        FROM orders o JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id
        WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ls.status='completed'
        GROUP BY d
        ORDER BY d ASC
    ")
    : fetch_assoc_safe($conn, "
        SELECT DATE(created_at) AS d, COALESCE(COUNT(*),0) AS orders_count, COALESCE(SUM(total_price),0) AS revenue
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY d
        ORDER BY d ASC
    ");

// Top products
$top_products = $has_history
    ? fetch_assoc_safe($conn, "
        SELECT p.id, p.name, COALESCE(SUM(oi.quantity),0) AS total_sold, COALESCE(SUM(oi.quantity * oi.price),0) AS total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id
        WHERE ls.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 10
    ")
    : fetch_assoc_safe($conn, "
        SELECT p.id, p.name, COALESCE(SUM(oi.quantity),0) AS total_sold, COALESCE(SUM(oi.quantity * oi.price),0) AS total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 10
    ");

// Low stock list (if inventory table exists)
$col_check = $conn->query("SHOW TABLES LIKE 'inventory'");
$low_stock = [];
if ($col_check && $col_check->num_rows > 0) {
    $low_stock = fetch_assoc_safe($conn, "
        SELECT p.id, p.name, COALESCE(i.quantity,0) AS quantity, COALESCE(i.min_stock,0) AS min_stock
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        ORDER BY COALESCE(i.quantity,0) ASC
        LIMIT 20
    ");
}

// Voucher usage
$voucher_usage = $has_history
    ? fetch_assoc_safe($conn, "
        SELECT o.voucher_code, COUNT(*) AS times_used, COALESCE(SUM(o.total_price),0) AS revenue
        FROM orders o
        JOIN (".$latestStatusSQL.") ls ON ls.order_id = o.id
        WHERE o.voucher_code IS NOT NULL AND o.voucher_code != '' AND ls.status='completed'
        GROUP BY o.voucher_code
        ORDER BY times_used DESC
        LIMIT 20
    ")
    : fetch_assoc_safe($conn, "
        SELECT voucher_code, COUNT(*) AS times_used, COALESCE(SUM(total_price),0) AS revenue
        FROM orders
        WHERE voucher_code IS NOT NULL AND voucher_code != ''
        GROUP BY voucher_code
        ORDER BY times_used DESC
        LIMIT 20
    ");

// Prepare data for charts
$chart_months = array_column($revenue_12m_raw, 'ym');
$chart_revenues = array_map('floatval', array_column($revenue_12m_raw, 'revenue'));

$orders_30d_labels = array_column($orders_30d_raw, 'd');
$orders_30d_counts = array_map('intval', array_column($orders_30d_raw, 'orders_count'));
$orders_30d_revenues = array_map('floatval', array_column($orders_30d_raw, 'revenue'));

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Thống kê chi tiết - Luxury Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background:#f5f7fb; color:#1f2937; font-family:Inter, Arial, sans-serif; }
        .card { border-radius:12px; box-shadow:0 6px 20px rgba(16,24,40,0.06); }
        .stat-value { font-size:1.5rem; font-weight:700; }
        .small-muted { color:#6b7280; font-size:.9rem; }
        .table-wrap { max-height:420px; overflow:auto; }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container-fluid py-4">
    <?php
    // Hiển thị cảnh báo thành công khi vừa hoàn thành đơn hàng và doanh thu đã cập nhật
    if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['completed_order'])) {
        $completed_id = (int)$_GET['completed_order'];
        $order_info = fetch_assoc_safe($conn, "SELECT id, total_price FROM orders WHERE id = ?", 'i', [$completed_id]);
        if (!empty($order_info)) {
            $completed_amount = (float)$order_info[0]['total_price'];
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
               . '<i class="fas fa-check-circle me-2"></i>'
               . 'Đơn hàng #' . htmlspecialchars($completed_id) . ' đã hoàn thành. '
               . 'Doanh thu vừa cộng: ' . number_format($completed_amount, 0, ',', '.') . ' đ.'
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
               . '</div>';
        }
    }
    ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card p-3">
                <div class="small-muted">Tổng doanh thu</div>
                <div class="stat-value"><?= number_format($total_revenue,0,',','.') ?> đ</div>
                <div class="small-muted mt-2">Đã hoàn tất: <?= $total_orders ?> đơn</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <div class="small-muted">Doanh thu hôm nay</div>
                <div class="stat-value text-success"><?= number_format($today_revenue,0,',','.') ?> đ</div>
                <div class="small-muted mt-2">Tháng này: <?= number_format($month_revenue,0,',','.') ?> đ</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <div class="small-muted">Giá trị trung bình/đơn</div>
                <div class="stat-value"><?= number_format($avg_order_value,0,',','.') ?> đ</div>
                <div class="small-muted mt-2">Tất cả đơn hàng hoàn tất</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <div class="small-muted">Đơn theo trạng thái</div>
                <?php foreach ($orders_by_status as $st): ?>
                    <div class="d-flex justify-content-between">
                        <div class="small-muted"><?= htmlspecialchars($st['status']) ?></div>
                        <div><?= (int)$st['cnt'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card p-3">
                <h5>Doanh thu 12 tháng gần nhất</h5>
                <canvas id="revenue12Chart" height="110"></canvas>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-3">
                <h5>Top sản phẩm bán chạy</h5>
                <div class="table-wrap mt-2">
                    <table class="table table-sm">
                        <thead><tr><th>Sản phẩm</th><th>Đã bán</th><th>Doanh thu</th></tr></thead>
                        <tbody>
                        <?php if (!empty($top_products)): ?>
                            <?php foreach ($top_products as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= (int)$p['total_sold'] ?></td>
                                    <td><?= number_format($p['total_revenue'],0,',','.') ?> đ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center small-muted">Chưa có dữ liệu</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card p-3">
                <h5>Đơn hàng (30 ngày)</h5>
                <canvas id="orders30Chart" height="120"></canvas>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card p-3">
                <h5>Voucher được sử dụng</h5>
                <div class="table-wrap mt-2">
                    <table class="table table-sm">
                        <thead><tr><th>Mã</th><th>Lần dùng</th><th>Doanh thu</th></tr></thead>
                        <tbody>
                        <?php if (!empty($voucher_usage)): ?>
                            <?php foreach ($voucher_usage as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['voucher_code']) ?></td>
                                    <td><?= (int)$v['times_used'] ?></td>
                                    <td><?= number_format($v['revenue'],0,',','.') ?> đ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center small-muted">Chưa có voucher được dùng</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($low_stock)): ?>
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card p-3">
                <h5>Sản phẩm sắp hết hàng</h5>
                <div class="table-wrap mt-2">
                    <table class="table table-sm">
                        <thead><tr><th>ID</th><th>Sản phẩm</th><th>Tồn kho</th><th>Ngưỡng</th></tr></thead>
                        <tbody>
                        <?php foreach ($low_stock as $r): ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= (int)$r['quantity'] ?></td>
                                <td><?= (int)$r['min_stock'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
const monthsLabels = <?= json_encode($chart_months) ?>;
const monthsData = <?= json_encode($chart_revenues) ?>;

const orders30Labels = <?= json_encode($orders_30d_labels) ?>;
const orders30Counts = <?= json_encode($orders_30d_counts) ?>;
const orders30Rev = <?= json_encode($orders_30d_revenues) ?>;

new Chart(document.getElementById('revenue12Chart').getContext('2d'), {
    type: 'line',
    data: {
        labels: monthsLabels,
        datasets: [{
            label: 'Doanh thu',
            data: monthsData,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 4
        }]
    },
    options: { responsive:true, plugins:{legend:{display:false}} }
});

new Chart(document.getElementById('orders30Chart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: orders30Labels,
        datasets: [
            { label: 'Số đơn', data: orders30Counts, backgroundColor:'#10b981' },
            { label: 'Doanh thu', data: orders30Rev, backgroundColor:'#3b82f6' }
        ]
    },
    options: {
        responsive:true, scales:{ y:{ beginAtZero:true, ticks:{ callback: v=> new Intl.NumberFormat('vi-VN').format(v) } } }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>