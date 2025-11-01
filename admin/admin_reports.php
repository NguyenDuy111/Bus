<?php
// admin_reports.php - Trang báo cáo và thống kê
require_once '../config.php';
require_once '../roles.php';

// YÊU CẦU QUYỀN ADMIN
requireRole('admin');

$user = get_user_info();

// --- LOGIC THỐNG KÊ BÁO CÁO CHUYÊN SÂU ---

// 1. Thống kê Doanh thu theo tháng trong năm hiện tại
$revenue_by_month = [];
$current_year = date('Y');
$result = $conn->query("
    SELECT 
        MONTH(created_at) as month, 
        SUM(total_price) as total_revenue
    FROM bookings
    WHERE payment_status = 'paid' AND YEAR(created_at) = '$current_year'
    GROUP BY month
    ORDER BY month ASC
");
while ($row = $result->fetch_assoc()) {
    $revenue_by_month[$row['month']] = $row['total_revenue'];
}

// Chuẩn bị dữ liệu cho biểu đồ (12 tháng)
$monthly_revenue_data = [];
$monthly_labels = [];
for ($i = 1; $i <= 12; $i++) {
    $monthly_revenue_data[] = $revenue_by_month[$i] ?? 0;
    $monthly_labels[] = "Tháng $i";
}
$monthly_revenue_json = json_encode($monthly_revenue_data);
$monthly_labels_json = json_encode($monthly_labels);


// 2. Top 5 Tuyến đường có Doanh thu cao nhất
$top_routes = [];
$result = $conn->query("
    SELECT 
        r.from_city, r.to_city, 
        SUM(b.total_price) as route_revenue
    FROM bookings b
    INNER JOIN schedules s ON b.schedule_id = s.id
    INNER JOIN routes r ON s.route_id = r.id
    WHERE b.payment_status = 'paid'
    GROUP BY r.id
    ORDER BY route_revenue DESC
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $top_routes[] = $row;
}


// 3. Tỷ lệ trạng thái đặt vé (Confirmed vs Pending vs Cancelled)
$status_count = [];
$result = $conn->query("
    SELECT status, COUNT(*) as count
    FROM bookings
    GROUP BY status
");
while ($row = $result->fetch_assoc()) {
    $status_count[$row['status']] = $row['count'];
}

$total_bookings_all = array_sum($status_count);
$status_data_json = json_encode(array_values($status_count));
$status_labels_json = json_encode(array_keys($status_count));


// Hàm hỗ trợ format tiền tệ (cần định nghĩa trong config.php hoặc tạo riêng)
if (!function_exists('format_currency')) {
    function format_currency($amount)
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }
}
// Hàm hỗ trợ format ngày (cần định nghĩa trong config.php hoặc tạo riêng)
if (!function_exists('format_date')) {
    function format_date($datetime)
    {
        return date('d/m/Y H:i', strtotime($datetime));
    }
}

// Định nghĩa mảng trạng thái cho badge (đã có trong admin_dashboard)
$status_badges = [
    'pending' => 'warning',
    'confirmed' => 'success',
    'cancelled' => 'danger'
];
$status_labels_full = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'cancelled' => 'Đã hủy'
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo & Thống kê - FUTA Bus Lines</title>
    <link rel="stylesheet" href="../css/admin_reports.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

    <style>

    </style>
</head>

<body>
    <div class="header">
        <div class="header-left">
            <h1>
                <span>👑</span> QUẢN TRỊ VIÊN
            </h1>
            <span class="role-badge">ADMIN</span>
        </div>
        <div class="user-info">
            <span class="user-name">👤 <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="../index.php" class="btn-logout">Về trang chủ</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li>
                    <a href="./admin_dashboard.php">
                        <span class="menu-icon">📊</span>
                        <span>Tổng quan</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_users.php">
                        <span class="menu-icon">👥</span>
                        <span>Quản lý người dùng</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_routes.php">
                        <span class="menu-icon">🛣️</span>
                        <span>Quản lý tuyến đường</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_schedules.php">
                        <span class="menu-icon">🕐</span>
                        <span>Quản lý lịch trình</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_bookings.php">
                        <span class="menu-icon">🎫</span>
                        <span>Quản lý đặt vé</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_reports.php" class="active">
                        <span class="menu-icon">📈</span>
                        <span>Báo cáo & Thống kê</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_settings.php">
                        <span class="menu-icon">⚙️</span>
                        <span>Cấu hình hệ thống</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <h1 class="page-title">Báo Cáo & Thống Kê</h1>
            <p class="page-subtitle">Phân tích sâu về hiệu suất kinh doanh và hoạt động của hệ thống.</p>

            <div class="card">
                <div class="card-header">
                    <h2>💰 Biểu đồ Doanh thu (Năm <?= $current_year ?>)</h2>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="reports-grid">
                <div class="card">
                    <div class="card-header">
                        <h2>📊 Tỷ lệ Trạng thái Đặt vé</h2>
                    </div>
                    <div class="chart-container-small">
                        <canvas id="statusDoughnutChart"></canvas>
                    </div>
                    <?php if ($total_bookings_all > 0): ?>
                        <div style="margin-top: 20px;">
                            <?php foreach ($status_count as $status => $count): ?>
                                <p style="font-size: 14px; margin: 5px 0;">
                                    <span class="badge badge-<?= $status_badges[$status] ?>">
                                        <?= $status_labels_full[$status] ?>
                                    </span>:
                                    **<?= number_format($count) ?>** vé
                                    (<?= round(($count / $total_bookings_all) * 100, 1) ?>%)
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">❌</div>
                            <p>Không có dữ liệu đặt vé để thống kê.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>🥇 Top 5 Tuyến đường có Doanh thu</h2>
                    </div>

                    <?php if (empty($top_routes)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🛣️</div>
                            <p>Chưa có dữ liệu doanh thu tuyến đường.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Hạng</th>
                                        <th>Tuyến đường</th>
                                        <th>Doanh thu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_routes as $index => $route): ?>
                                        <tr>
                                            <td>#<?= $index + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($route['from_city']) ?> →
                                                    <?= htmlspecialchars($route['to_city']) ?></strong>
                                            </td>
                                            <td>
                                                <strong
                                                    style="color: #667eea;"><?= format_currency($route['route_revenue']) ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

    <script>
        // Dữ liệu cho biểu đồ doanh thu theo tháng
        const monthlyRevenueData = {
            labels: <?= $monthly_labels_json ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?= $monthly_revenue_json ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                borderColor: '#667eea',
                borderWidth: 1,
                borderRadius: 5,
            }]
        };

        const revenueConfig = {
            type: 'bar',
            data: monthlyRevenueData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Doanh thu',
                        },
                        // Custom tooltip cho trục Y
                        ticks: {
                            callback: function(value, index, ticks) {
                                if (value >= 1000000000) {
                                    return (value / 1000000000).toFixed(1) + ' Tỷ';
                                }
                                if (value >= 1000000) {
                                    return (value / 1000000).toFixed(1) + ' Tr';
                                }
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND'
                                    }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        };

        const revenueChart = new Chart(
            document.getElementById('revenueChart'),
            revenueConfig
        );


        // Dữ liệu cho biểu đồ trạng thái đặt vé
        const statusDoughnutData = {
            labels: <?= $status_labels_json ?>,
            datasets: [{
                data: <?= $status_data_json ?>,
                backgroundColor: [
                    '#28a745', // Confirmed (green)
                    '#ffc107', // Pending (yellow)
                    '#dc3545' // Cancelled (red)
                ],
                hoverOffset: 4
            }]
        };

        const statusDoughnutConfig = {
            type: 'doughnut',
            data: statusDoughnutData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                }
            },
        };

        const statusDoughnutChart = new Chart(
            document.getElementById('statusDoughnutChart'),
            statusDoughnutConfig
        );
    </script>
</body>

</html>