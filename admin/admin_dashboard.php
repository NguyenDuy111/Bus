<?php
// admin_dashboard.php - Trang quản trị hệ thống
require_once '../config.php';
require_once '../roles.php';

// YÊU CẦU QUYỀN ADMIN
requireRole('admin');

$user = get_user_info();

// Lấy thống kê tổng quan
$stats = [
    'total_users' => 0,
    'total_bookings' => 0,
    'total_bookings_today' => 0,
    'total_revenue' => 0,
    'total_revenue_today' => 0,
    'total_routes' => 0,
    'total_schedules' => 0
];

// Tổng số người dùng
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Tổng số đặt vé
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status != 'cancelled'");
$stats['total_bookings'] = $result->fetch_assoc()['count'];

// Đặt vé hôm nay
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'");
$stats['total_bookings_today'] = $result->fetch_assoc()['count'];

// Tổng doanh thu
$result = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE payment_status = 'paid'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Doanh thu hôm nay
$result = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
$stats['total_revenue_today'] = $result->fetch_assoc()['total'] ?? 0;

// Tổng tuyến đường
$result = $conn->query("SELECT COUNT(*) as count FROM routes");
$stats['total_routes'] = $result->fetch_assoc()['count'];

// Tổng lịch trình
$result = $conn->query("SELECT COUNT(*) as count FROM schedules WHERE status = 'active'");
$stats['total_schedules'] = $result->fetch_assoc()['count'];

// Lấy danh sách booking gần đây
$recent_bookings = [];
$result = $conn->query("
    SELECT b.*, u.full_name, u.phone, s.departure_time, r.from_city, r.to_city
    FROM bookings b
    INNER JOIN users u ON b.user_id = u.id
    INNER JOIN schedules s ON b.schedule_id = s.id
    INNER JOIN routes r ON s.route_id = r.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
while ($row = $result->fetch_assoc()) {
    $recent_bookings[] = $row;
}

// Lấy người dùng mới nhất
$recent_users = [];
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị viên - FUTA Bus Lines</title>
    <link rel="stylesheet" href="../css/admin_dashboard.css">
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
                    <a href="./admin_dashboard.php" class="active">
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
                    <a href="./admin_reports.php">
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
            <h1 class="page-title">Dashboard Tổng Quan</h1>
            <p class="page-subtitle">Xin chào, <?= htmlspecialchars($user['full_name']) ?>! Chào mừng bạn quay trở
                lại.</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Tổng người dùng</h3>
                        <div class="stat-icon">👥</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                    <div class="stat-change">+12% so với tháng trước</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Đặt vé hôm nay</h3>
                        <div class="stat-icon">🎫</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_bookings_today']) ?></div>
                    <div class="stat-change">Tổng: <?= number_format($stats['total_bookings']) ?> vé</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Doanh thu hôm nay</h3>
                        <div class="stat-icon">💰</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_revenue_today'] / 1000000, 1) ?>M</div>
                    <div class="stat-change">Tổng: <?= format_currency($stats['total_revenue']) ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Tuyến đường</h3>
                        <div class="stat-icon">🛣️</div>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_routes']) ?></div>
                    <div class="stat-change"><?= number_format($stats['total_schedules']) ?> lịch trình</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>📋 Đặt vé gần đây</h2>
                    <a href="admin_bookings.php" class="btn-primary">Xem tất cả</a>
                </div>

                <?php if (empty($recent_bookings)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎫</div>
                    <p>Chưa có đặt vé nào</p>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Mã vé</th>
                                <th>Khách hàng</th>
                                <th>Tuyến đường</th>
                                <th>Số vé</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thanh toán</th>
                                <th>Ngày đặt</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($booking['booking_code']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($booking['full_name']) ?><br>
                                    <small style="color: #999;"><?= htmlspecialchars($booking['phone']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($booking['from_city']) ?> →
                                    <?= htmlspecialchars($booking['to_city']) ?></td>
                                <td><?= $booking['num_tickets'] ?></td>
                                <td><strong><?= format_currency($booking['total_price']) ?></strong></td>
                                <td>
                                    <?php
                                            $status_badges = [
                                                'pending' => 'warning',
                                                'confirmed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $status_labels = [
                                                'pending' => 'Chờ xác nhận',
                                                'confirmed' => 'Đã xác nhận',
                                                'cancelled' => 'Đã hủy'
                                            ];
                                            ?>
                                    <span class="badge badge-<?= $status_badges[$booking['status']] ?>">
                                        <?= $status_labels[$booking['status']] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                            $payment_badges = [
                                                'unpaid' => 'warning',
                                                'paid' => 'success'
                                            ];
                                            $payment_labels = [
                                                'unpaid' => 'Chưa TT',
                                                'paid' => 'Đã TT'
                                            ];
                                            ?>
                                    <span class="badge badge-<?= $payment_badges[$booking['payment_status']] ?>">
                                        <?= $payment_labels[$booking['payment_status']] ?>
                                    </span>
                                </td>
                                <td><?= format_date($booking['created_at']) ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="viewBooking(<?= $booking['id'] ?>)">Chi
                                        tiết</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>👥 Người dùng mới</h2>
                    <a href="admin_users.php" class="btn-primary">Xem tất cả</a>
                </div>

                <?php if (empty($recent_users)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <p>Chưa có người dùng nào</p>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Họ và tên</th>
                                <th>Số điện thoại</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Ngày đăng ký</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $usr): ?>
                            <tr>
                                <td><?= $usr['id'] ?></td>

                                <td><strong><?= htmlspecialchars($usr['full_name']) ?></strong></td>

                                <td><?= htmlspecialchars($usr['phone']) ?></td>
                                <td><?= htmlspecialchars($usr['email'] ?? 'Chưa có') ?></td>
                                <td>
                                    <?php
                                            // ================== SỬA LỖI LOGIC (Undefined array key) ==================
                                            // Sửa từ: $role = $usr['role'] ?? 'customer';
                                            // Thành:
                                            $role = $usr['role'] ?: 'customer';
                                            // ========================================================================

                                            $role_labels = [
                                                'admin' => '👑 Admin',
                                                'staff' => '👨‍💼 Nhân viên',
                                                'customer' => '👤 Khách hàng'
                                            ];
                                            ?>
                                    <span class="badge badge-<?= $role ?>">
                                        <?= $role_labels[$role] ?>
                                    </span>
                                </td>
                                <td><?= format_date($usr['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function viewBooking(id) {
        alert('Xem chi tiết booking #' + id + '\n(Chức năng sẽ được phát triển)');
        // TODO: Mở modal hoặc chuyển trang chi tiết
    }

    // Auto refresh stats every 30 seconds
    /* Bỏ auto-refresh để dễ debug hơn, bạn có thể mở lại sau
    setInterval(() => {
        location.reload();
    }, 30000);
    */
    </script>
</body>

</html>