<?php

require_once '../../db.php';

// Lấy thống kê tổng quan
$totalQuery = "SELECT 
    COUNT(*) as total_orders,
    SUM(revenue) as total_revenue 
    FROM statistics";
$totalResult = mysqli_query($conn, $totalQuery);
$totalStats = mysqli_fetch_assoc($totalResult);

// Lấy thống kê theo tháng
$monthlyQuery = "SELECT 
    MONTH(created_at) as month,
    COUNT(*) as orders,
    SUM(revenue) as revenue
    FROM statistics 
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at) ASC";
$monthlyResult = mysqli_query($conn, $monthlyQuery);

$monthlyStats = [];
$previousRevenue = 0;

while ($row = mysqli_fetch_assoc($monthlyResult)) {
    $growth = $previousRevenue > 0 
        ? round(($row['revenue'] - $previousRevenue) / $previousRevenue * 100, 2)
        : 0;
    
    $monthlyStats[] = [
        'month' => $row['month'],
        'orders' => (int)$row['orders'],
        'revenue' => (float)$row['revenue'],
        'growth' => $growth
    ];
    
    $previousRevenue = $row['revenue'];
}

// Tính doanh thu trung bình/đơn
$averageRevenue = $totalStats['total_orders'] > 0 
    ? $totalStats['total_revenue'] / $totalStats['total_orders']
    : 0;

$response = [
    'totalRevenue' => (float)$totalStats['total_revenue'],
    'completedOrders' => (int)$totalStats['total_orders'],
    'averageRevenue' => $averageRevenue,
    'monthlyStats' => $monthlyStats
];

header('Content-Type: application/json');
echo json_encode($response);