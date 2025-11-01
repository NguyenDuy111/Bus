<?php
// admin/admin_bookings.php - Quản lý Đặt vé

// Sửa 1: Thêm ../ để gọi file từ thư mục gốc (Bus/)
require_once '../config.php';
require_once '../roles.php';

// YÊU CẦU QUYỀN ADMIN
requireRole('admin');

$user = get_user_info();

// XỬ LÝ CÁC ACTION
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $booking_id = intval($_POST['booking_id'] ?? 0);

    if ($booking_id > 0) {
        switch ($action) {
            case 'confirm_booking':
                $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
                $stmt->bind_param("i", $booking_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = 'Xác nhận vé thành công!';
                    $message_type = 'success';
                } else {
                    $message = 'Lỗi! Không thể xác nhận vé (có thể đã được xác nhận).';
                    $message_type = 'error';
                }
                break;

            case 'mark_paid':
                $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ? AND payment_status = 'unpaid'");
                $stmt->bind_param("i", $booking_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = 'Đánh dấu đã thanh toán thành công!';
                    $message_type = 'success';
                } else {
                    $message = 'Lỗi! Không thể cập nhật thanh toán (có thể đã được thanh toán).';
                    $message_type = 'error';
                }
                break;

            case 'cancel_booking':
                // Bắt đầu một giao dịch
                $conn->begin_transaction();
                try {
                    // 1. Lấy thông tin vé (schedule_id, num_tickets, status)
                    $stmt_get = $conn->prepare("SELECT schedule_id, num_tickets, status FROM bookings WHERE id = ?");
                    $stmt_get->bind_param("i", $booking_id);
                    $stmt_get->execute();
                    $booking = $stmt_get->get_result()->fetch_assoc();

                    if ($booking && $booking['status'] != 'cancelled') {
                        // 2. Cập nhật trạng thái vé thành 'cancelled'
                        $stmt_cancel = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                        $stmt_cancel->bind_param("i", $booking_id);
                        $stmt_cancel->execute();

                        // 3. Hoàn trả lại số ghế trống cho lịch trình
                        $stmt_restore = $conn->prepare("UPDATE schedules SET available_seats = available_seats + ? WHERE id = ?");
                        $stmt_restore->bind_param("ii", $booking['num_tickets'], $booking['schedule_id']);
                        $stmt_restore->execute();

                        // Hoàn tất giao dịch
                        $conn->commit();
                        $message = 'Hủy vé thành công, đã hoàn lại ' . $booking['num_tickets'] . ' ghế trống.';
                        $message_type = 'success';
                    } else {
                        $conn->rollback();
                        $message = 'Vé này đã được hủy trước đó hoặc không tồn tại.';
                        $message_type = 'error';
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = 'Lỗi giao dịch: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
        }
    }
}

// LẤY DANH SÁCH ROUTES (CHO BỘ LỌC)
$routes = [];
$routes_result = $conn->query("SELECT id, from_city, to_city FROM routes ORDER BY from_city, to_city");
while ($route = $routes_result->fetch_assoc()) {
    $routes[] = $route;
}

// LẤY DANH SÁCH BOOKINGS
$search = $_GET['search'] ?? '';
$route_filter = intval($_GET['route'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$date_filter = $_GET['date'] ?? ''; // Lọc theo ngày đi

$sql = "SELECT b.*, 
               u.full_name as user_full_name, 
               r.from_city, r.to_city,
               s.departure_time, s.bus_number
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (b.booking_code LIKE ? OR b.passenger_phone LIKE ? OR b.passenger_name LIKE ? OR u.full_name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}
if ($route_filter > 0) {
    $sql .= " AND r.id = ?";
    $params[] = $route_filter;
    $types .= 'i';
}
if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($payment_filter) && $payment_filter !== 'all') {
    $sql .= " AND b.payment_status = ?";
    $params[] = $payment_filter;
    $types .= 's';
}
if (!empty($date_filter)) {
    // Lọc theo ngày đi (booking_date)
    $sql .= " AND b.booking_date = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

// THỐNG KÊ
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'],
    'pending' => $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")->fetch_assoc()['count'],
    'confirmed' => $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'")->fetch_assoc()['count'],
    'revenue' => $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['total'] ?? 0
];

// HELPER ARRAYS (CHO BADGES)
$status_labels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'cancelled' => 'Đã hủy'
];
$status_badges = [
    'pending' => 'badge-warning',
    'confirmed' => 'badge-success',
    'cancelled' => 'badge-danger'
];
$payment_labels = [
    'unpaid' => 'Chưa TT',
    'paid' => 'Đã TT'
];
$payment_badges = [
    'unpaid' => 'badge-warning',
    'paid' => 'badge-success'
];

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đặt vé - FUTA Bus Lines</title>
    <link rel="stylesheet" href="../css/admin_bookings.css">

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
                    <a href="./admin_dashboard.php"> <span class="menu-icon">📊</span>
                        <span>Tổng quan</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_users.php"> <span class="menu-icon">👥</span>
                        <span>Quản lý người dùng</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_routes.php"> <span class="menu-icon">🛣️</span>
                        <span>Quản lý tuyến đường</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_schedules.php"> <span class="menu-icon">🕐</span>
                        <span>Quản lý lịch trình</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_bookings.php" class="active"> <span class="menu-icon">🎫</span>
                        <span>Quản lý đặt vé</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_reports.php"> <span class="menu-icon">📈</span>
                        <span>Báo cáo & Thống kê</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_settings.php"> <span class="menu-icon">⚙️</span>
                        <span>Cấu hình hệ thống</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">🎫 Quản lý Đặt vé</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Tổng số vé</h3>
                    <div class="number"><?= number_format($stats['total']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Chờ xác nhận</h3>
                    <div class="number"><?= number_format($stats['pending']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Đã xác nhận</h3>
                    <div class="number"><?= number_format($stats['confirmed']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Tổng doanh thu</h3>
                    <div class="number"><?= number_format($stats['revenue'] / 1000, 0) ?>k</div>
                </div>
            </div>

            <div class="card">
                <form class="filter-section" method="GET" action="admin_bookings.php">
                    <input type="text" name="search" placeholder="🔍 Tìm mã vé, SĐT, tên..."
                        value="<?= htmlspecialchars($search) ?>">

                    <select name="route">
                        <option value="all">🛣️ Tất cả tuyến</option>
                        <?php foreach ($routes as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $route_filter == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['from_city']) ?> → <?= htmlspecialchars($r['to_city']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status">
                        <option value="all">Vé: Tất cả</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chờ xác nhận
                        </option>
                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận
                        </option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Đã hủy
                        </option>
                    </select>

                    <select name="payment">
                        <option value="all">TT: Tất cả</option>
                        <option value="unpaid" <?= $payment_filter === 'unpaid' ? 'selected' : '' ?>>Chưa thanh toán
                        </option>
                        <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Đã thanh toán</option>
                    </select>

                    <input type="date" name="date" title="Lọc theo ngày đi"
                        value="<?= htmlspecialchars($date_filter) ?>">

                    <button type="submit" class="btn-add">Tìm</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Mã vé</th>
                                <th>Hành khách</th>
                                <th>Tuyến</th>
                                <th>Ngày đi / Giờ đi</th>
                                <th>Ghế</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái vé</th>
                                <th>Thanh toán</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 50px;">
                                        <div class="empty-state">
                                            <div class="empty-icon">🎫</div>
                                            <h3>Không tìm thấy vé nào</h3>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                                    <td>
                                        <div><?= htmlspecialchars($b['passenger_name']) ?></div>
                                        <small style="color: #777;"><?= htmlspecialchars($b['passenger_phone']) ?></small>
                                    </td>
                                    <td>
                                        <div class="route-info">
                                            <span><?= htmlspecialchars($b['from_city']) ?></span>
                                            <span class="route-arrow">→</span>
                                            <span><?= htmlspecialchars($b['to_city']) ?></span>
                                        </div>
                                        <small style="color: #777;">Xe: <?= htmlspecialchars(@$b['bus_number']) ?></small>
                                    </td>
                                    <td>
                                        <div><?= format_date($b['booking_date']) ?></div>
                                        <small style="color: #777;"><?= format_time($b['departure_time']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= $b['num_tickets'] ?> vé</strong>
                                        <div style="font-size: 12px; color: #777; max-width: 150px; word-wrap: break-word;">
                                            <?= htmlspecialchars($b['seat_numbers']) ?>
                                        </div>
                                    </td>
                                    <td><strong style="color: #28a745;"><?= format_currency($b['total_price']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_badges[$b['status']] ?>">
                                            <?= $status_labels[$b['status']] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $payment_badges[$b['payment_status']] ?>">
                                            <?= $payment_labels[$b['payment_status']] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-view"
                                            onclick='openDetailModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)'>Xem</button>
                                        <?php if ($b['status'] == 'pending'): ?>
                                            <button class="btn btn-sm btn-confirm" onclick="confirmBooking(<?= $b['id'] ?>)">Xác
                                                nhận</button>
                                        <?php endif; ?>
                                        <?php if ($b['payment_status'] == 'unpaid'): ?>
                                            <button class="btn btn-sm btn-paid" onclick="markAsPaid(<?= $b['id'] ?>)">Đã
                                                TT</button>
                                        <?php endif; ?>
                                        <?php if ($b['status'] != 'cancelled'): ?>
                                            <button class="btn btn-sm btn-cancel" onclick="cancelBooking(<?= $b['id'] ?>)">Hủy
                                                vé</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Chi tiết Vé #<span id="detail_booking_code"></span></h2>
                <span class="close-modal" onclick="closeDetailModal()">×</span>
            </div>
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <label>Tuyến</label>
                    <span id="detail_route"></span>
                </div>
                <div class="detail-item">
                    <label>Ngày đi</label>
                    <span id="detail_booking_date"></span>
                </div>
                <div class="detail-item">
                    <label>Giờ khởi hành</label>
                    <span id="detail_departure_time"></span>
                </div>
                <div class="detail-item">
                    <label>Hành khách</label>
                    <span id="detail_passenger_name"></span>
                </div>
                <div class="detail-item">
                    <label>Số điện thoại</label>
                    <span id="detail_passenger_phone"></span>
                </div>
                <div class="detail-item">
                    <label>Số lượng vé</label>
                    <span id="detail_num_tickets"></span>
                </div>
                <div class="detail-item">
                    <label>Tổng tiền</label>
                    <span id="detail_total_price"></span>
                </div>
                <div class="detail-item full-width">
                    <label>Số ghế</label>
                    <span id="detail_seat_numbers"></span>
                </div>
                <div class="detail-item">
                    <label>Trạng thái vé</label>
                    <span id="detail_status"></span>
                </div>
                <div class="detail-item">
                    <label>Trạng thái thanh toán</label>
                    <span id="detail_payment_status"></span>
                </div>
                <div class="detail-item">
                    <label>Người đặt (Tài khoản)</label>
                    <span id="detail_user_full_name"></span>
                </div>
                <div class="detail-item">
                    <label>Ngày đặt</label>
                    <span id="detail_created_at"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- Helpers cho Actions ---
        function submitAction(action, bookingId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_bookings.php'; // Gửi về chính nó
            form.innerHTML = `
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="booking_id" value="${bookingId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function confirmBooking(id) {
            if (confirm('Bạn có chắc muốn xác nhận vé này?')) {
                submitAction('confirm_booking', id);
            }
        }

        function markAsPaid(id) {
            if (confirm('Bạn có chắc muốn đánh dấu vé này là ĐÃ THANH TOÁN?')) {
                submitAction('mark_paid', id);
            }
        }

        function cancelBooking(id) {
            if (confirm(
                    '!!! CẢNH BÁO !!!\nBạn có chắc muốn HỦY VÉ này?\n\nHành động này sẽ hoàn trả ghế trống về hệ thống.')) {
                submitAction('cancel_booking', id);
            }
        }

        // --- Modal Detail ---
        const statusLabels = {
            pending: 'Chờ xác nhận',
            confirmed: 'Đã xác nhận',
            cancelled: 'Đã hủy'
        };
        const paymentLabels = {
            unpaid: 'Chưa thanh toán',
            paid: 'Đã thanh toán'
        };

        function formatCurrencyJS(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount);
        }

        function formatDateJS(dateString) {
            const options = {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            };
            return new Date(dateString).toLocaleDateString('vi-VN', options);
        }

        function formatTimeJS(timeString) {
            const options = {
                hour: '2-digit',
                minute: '2-digit'
            };
            return new Date(timeString).toLocaleTimeString('vi-VN', options);
        }

        function openDetailModal(booking) {
            document.getElementById('detail_booking_code').innerText = booking.booking_code;
            document.getElementById('detail_route').innerText = `${booking.from_city} → ${booking.to_city}`;
            document.getElementById('detail_booking_date').innerText = formatDateJS(booking.booking_date);
            document.getElementById('detail_departure_time').innerText = formatTimeJS(booking.departure_time);
            document.getElementById('detail_passenger_name').innerText = booking.passenger_name;
            document.getElementById('detail_passenger_phone').innerText = booking.passenger_phone;
            document.getElementById('detail_num_tickets').innerText = booking.num_tickets;
            document.getElementById('detail_total_price').innerText = formatCurrencyJS(booking.total_price);
            document.getElementById('detail_seat_numbers').innerText = booking.seat_numbers || '(Chưa chọn)';
            document.getElementById('detail_status').innerText = statusLabels[booking.status] || booking.status;
            document.getElementById('detail_payment_status').innerText = paymentLabels[booking.payment_status] || booking
                .payment_status;
            document.getElementById('detail_user_full_name').innerText = booking.user_full_name;
            document.getElementById('detail_created_at').innerText = new Date(booking.created_at).toLocaleString('vi-VN');

            document.getElementById('detailModal').classList.add('show');
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.remove('show');
        }

        // --- JS Chung ---
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                closeDetailModal();
            }
        }

        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);

        async function handleLogout() {
            if (!confirm('Bạn có chắc muốn đăng xuất?')) return;
            const formData = new FormData();
            formData.append('action', 'logout');
            try {
                // Thêm ../ để gọi file auth.php từ thư mục gốc
                const response = await fetch('../auth.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    alert('Đăng xuất thành công!');
                    window.location.href = '../login.php'; // Thêm ../
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