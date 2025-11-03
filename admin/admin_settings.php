<?php
// admin_settings.php - Trang cấu hình hệ thống
require_once '../config.php';
require_once '../roles.php';

// YÊU CẦU QUYỀN ADMIN
requireRole('admin');

$user = get_user_info();

// Giả lập Dữ liệu Cấu hình Hệ thống (Thường lưu trong DB hoặc file config)
// Cấu hình mẫu
$settings = [
    'system_name' => 'FUTA Bus Lines - Hệ thống Đặt vé',
    'support_email' => 'support@futabus.vn',
    'base_price_increase_percent' => 15, // Tăng giá vé cơ bản theo %
    'max_cancellation_days' => 3, // Số ngày tối đa được hủy vé trước giờ khởi hành
    'is_maintenance_mode' => false, // Chế độ bảo trì
];

$message = '';

// Xử lý khi nhận dữ liệu POST (Lưu cấu hình)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $settings['system_name'] = $_POST['system_name'] ?? $settings['system_name'];
    $settings['support_email'] = $_POST['support_email'] ?? $settings['support_email'];
    $settings['base_price_increase_percent'] = (int)($_POST['base_price_increase_percent'] ?? $settings['base_price_increase_percent']);
    $settings['max_cancellation_days'] = (int)($_POST['max_cancellation_days'] ?? $settings['max_cancellation_days']);
    $settings['is_maintenance_mode'] = isset($_POST['is_maintenance_mode']);

    // TODO: Thực hiện lưu $settings vào Database hoặc file config

    $message = '<div class="alert alert-success">✅ Đã lưu cấu hình hệ thống thành công!</div>';

    // Giả lập tải lại dữ liệu sau khi lưu để thấy thay đổi
    // Trong môi trường thực tế, cần reload từ DB/file
}

// Hàm hỗ trợ format (chỉ để tránh lỗi nếu config.php không có)
if (!function_exists('format_currency')) {
    function format_currency($amount)
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cấu hình Hệ thống - FUTA Bus Lines</title>
    <link rel="stylesheet" href="../css/admin_settings.css">
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
            <a href="#" onclick="event.preventDefault(); handleLogout();" class="btn-logout">Đăng xuất</a>
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
                    <a href="./admin_reports.php">
                        <span class="menu-icon">📈</span>
                        <span>Báo cáo & Thống kê</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_settings.php" class="active">
                        <span class="menu-icon">⚙️</span>
                        <span>Cấu hình hệ thống</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <h1 class="page-title">Cấu hình Hệ thống</h1>
            <p class="page-subtitle">Thiết lập các thông số hoạt động và thông tin chung của ứng dụng.</p>

            <?= $message ?>

            <form action="admin_settings.php" method="POST">
                <div class="card">
                    <div class="card-header">
                        <h2>Thông tin chung</h2>
                    </div>
                    <div class="form-group">
                        <label for="system_name">Tên hệ thống</label>
                        <input type="text" id="system_name" name="system_name"
                            value="<?= htmlspecialchars($settings['system_name']) ?>" required>
                        <p class="description">Tên này sẽ hiển thị trên tiêu đề trang và các thông báo chung.</p>
                    </div>

                    <div class="form-group">
                        <label for="support_email">Email Hỗ trợ</label>
                        <input type="email" id="support_email" name="support_email"
                            value="<?= htmlspecialchars($settings['support_email']) ?>" required>
                        <p class="description">Email dùng để gửi thông báo và nhận yêu cầu hỗ trợ từ khách hàng.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Cấu hình nghiệp vụ</h2>
                    </div>

                    <div class="form-group">
                        <label for="base_price_increase_percent">Tăng giá vé cơ bản (%)</label>
                        <input type="number" id="base_price_increase_percent" name="base_price_increase_percent"
                            value="<?= htmlspecialchars($settings['base_price_increase_percent']) ?>" min="0" max="100"
                            required>
                        <p class="description">Phần trăm tăng giá áp dụng chung cho tất cả các tuyến (ví dụ: phí dịch
                            vụ, thuế...). Giá trị hiện tại là **<?= $settings['base_price_increase_percent'] ?>%**.</p>
                    </div>

                    <div class="form-group">
                        <label for="max_cancellation_days">Hạn hủy vé (Ngày)</label>
                        <input type="number" id="max_cancellation_days" name="max_cancellation_days"
                            value="<?= htmlspecialchars($settings['max_cancellation_days']) ?>" min="0" max="30"
                            required>
                        <p class="description">Số ngày tối đa trước giờ khởi hành mà khách hàng được phép hủy vé.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Chế độ Bảo trì</h2>
                    </div>

                    <div class="form-group" style="border-bottom: none;">
                        <label>
                            Chế độ Bảo trì (Maintenance Mode)
                            <p class="description" style="margin-top: 5px;">Khi bật, khách hàng sẽ không truy cập được
                                trang web, chỉ có Admin mới có thể đăng nhập.</p>
                        </label>

                        <label class="switch" style="margin-top: 10px;">
                            <input type="checkbox" name="is_maintenance_mode"
                                <?= $settings['is_maintenance_mode'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>

                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="btn-primary">💾 Lưu Cấu hình</button>
                </div>
            </form>

        </div>
    </div>
    <script>
        async function handleLogout() {
            if (!confirm('Bạn có chắc muốn đăng xuất?')) return;

            const formData = new FormData();
            formData.append('action', 'logout');

            try {
                // Đảm bảo đường dẫn này chính xác
                const response = await fetch('../auth.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert('Đăng xuất thành công!');
                    window.location.href = '../login.php';
                } else {
                    alert('Có lỗi xảy ra khi đăng xuất.');
                }
            } catch (error) {
                alert('Lỗi kết nối. Vui lòng thử lại.');
            }
        }
    </script>
</body>

</html>