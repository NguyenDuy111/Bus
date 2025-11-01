<?php
// admin_schedules.php - Quản lý lịch trình

// Sửa 1: Xóa ../ để gọi file từ thư mục gốc
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

    switch ($action) {
        case 'add_schedule':
            $route_id = intval($_POST['route_id'] ?? 0);
            $bus_number = escape_string(trim($_POST['bus_number'] ?? ''));
            $departure_time = escape_string($_POST['departure_time'] ?? '');
            $arrival_time = escape_string($_POST['arrival_time'] ?? '');
            $price = intval($_POST['price'] ?? 0);
            $total_seats = intval($_POST['total_seats'] ?? 40);
            $bus_type = escape_string($_POST['bus_type'] ?? 'standard');

            if ($route_id <= 0 || empty($bus_number) || empty($departure_time) || empty($arrival_time) || $price <= 0) {
                $message = 'Vui lòng nhập đầy đủ thông tin!';
                $message_type = 'error';
            } else if ($departure_time >= $arrival_time) {
                $message = 'Thời gian đến phải sau thời gian đi!';
                $message_type = 'error';
            } else {
                // Kiểm tra trùng lịch
                $stmt = $conn->prepare("SELECT id FROM schedules WHERE route_id = ? AND departure_time = ?");
                $stmt->bind_param("is", $route_id, $departure_time);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $message = 'Lịch trình này đã tồn tại!';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO schedules (route_id, bus_number, departure_time, arrival_time, price, total_seats, available_seats, bus_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("isssiiis", $route_id, $bus_number, $departure_time, $arrival_time, $price, $total_seats, $total_seats, $bus_type);

                    if ($stmt->execute()) {
                        $message = 'Thêm lịch trình thành công!';
                        $message_type = 'success';
                    } else {
                        $message = 'Có lỗi xảy ra!';
                        $message_type = 'error';
                    }
                }
            }
            break;

        case 'update_schedule':
            $schedule_id = intval($_POST['schedule_id'] ?? 0);
            $route_id = intval($_POST['route_id'] ?? 0);
            $bus_number = escape_string(trim($_POST['bus_number'] ?? ''));
            $departure_time = escape_string($_POST['departure_time'] ?? '');
            $arrival_time = escape_string($_POST['arrival_time'] ?? '');
            $price = intval($_POST['price'] ?? 0);
            $total_seats = intval($_POST['total_seats'] ?? 40);
            $bus_type = escape_string($_POST['bus_type'] ?? 'standard');
            $status = escape_string($_POST['status'] ?? 'active');

            if ($schedule_id > 0 && $route_id > 0 && !empty($bus_number) && $price > 0) {
                if ($departure_time >= $arrival_time) {
                    $message = 'Thời gian đến phải sau thời gian đi!';
                    $message_type = 'error';
                } else {
                    // Tính toán lại số ghế trống
                    $stmt_booked = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE schedule_id = ? AND status != 'cancelled'");
                    $stmt_booked->bind_param("i", $schedule_id);
                    $stmt_booked->execute();
                    $booked = $stmt_booked->get_result()->fetch_assoc()['count'] ?? 0;

                    if ($total_seats < $booked) {
                        $message = "Không thể giảm số ghế! Đã có $booked ghế được đặt.";
                        $message_type = 'error';
                    } else {
                        $available = $total_seats - $booked;
                        $stmt = $conn->prepare("UPDATE schedules SET route_id = ?, bus_number = ?, departure_time = ?, arrival_time = ?, price = ?, total_seats = ?, available_seats = ?, bus_type = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("isssiisssi", $route_id, $bus_number, $departure_time, $arrival_time, $price, $total_seats, $available, $bus_type, $status, $schedule_id);

                        if ($stmt->execute()) {
                            $message = 'Cập nhật thành công!';
                            $message_type = 'success';
                        } else {
                            $message = 'Có lỗi xảy ra!';
                            $message_type = 'error';
                        }
                    }
                }
            }
            break;

        case 'delete_schedule':
            $schedule_id = intval($_POST['schedule_id'] ?? 0);

            if ($schedule_id > 0) {
                // Kiểm tra có booking không
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE schedule_id = ? AND status != 'cancelled'");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc()['count'];

                if ($count > 0) {
                    $message = "Không thể xóa! Có $count vé đang hoạt động.";
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
                    $stmt->bind_param("i", $schedule_id);

                    if ($stmt->execute()) {
                        $message = 'Xóa lịch trình thành công!';
                        $message_type = 'success';
                    } else {
                        $message = 'Có lỗi xảy ra!';
                        $message_type = 'error';
                    }
                }
            }
            break;

        case 'toggle_status':
            $schedule_id = intval($_POST['schedule_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? 'inactive';

            if ($schedule_id > 0) {
                $stmt = $conn->prepare("UPDATE schedules SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $schedule_id);

                if ($stmt->execute()) {
                    $message = 'Cập nhật trạng thái thành công!';
                    $message_type = 'success';
                } else {
                    $message = 'Có lỗi xảy ra!';
                    $message_type = 'error';
                }
            }
            break;
    }
}

// LẤY DANH SÁCH LỊCH TRÌNH
$search = $_GET['search'] ?? '';
$route_filter = $_GET['route'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

$sql = "SELECT s.*, 
        r.from_city, r.to_city, r.distance,
        (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status != 'cancelled') as booking_count
        FROM schedules s
        INNER JOIN routes r ON s.route_id = r.id
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (s.bus_number LIKE ? OR r.from_city LIKE ? OR r.to_city LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($route_filter) && $route_filter !== 'all') {
    $sql .= " AND s.route_id = ?";
    $params[] = intval($route_filter);
    $types .= 'i';
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(s.departure_time) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$sql .= " ORDER BY s.departure_time ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

// Lấy danh sách routes cho dropdown
$routes = [];
$routes_result = $conn->query("SELECT id, from_city, to_city FROM routes ORDER BY from_city, to_city");
while ($route = $routes_result->fetch_assoc()) {
    $routes[] = $route;
}

// Thống kê
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM schedules")->fetch_assoc()['count'],
    'active' => $conn->query("SELECT COUNT(*) as count FROM schedules WHERE status = 'active'")->fetch_assoc()['count'],
    'today' => $conn->query("SELECT COUNT(*) as count FROM schedules WHERE DATE(departure_time) = CURDATE()")->fetch_assoc()['count'],
    'booked_seats' => $conn->query("SELECT SUM(total_seats - available_seats) as total FROM schedules")->fetch_assoc()['total'] ?? 0
];

$bus_types_labels = [
    'standard' => '🚌 Thường',
    'vip' => '⭐ VIP',
    'limousine' => '🚐 Limousine' // Thêm nếu bạn có
];

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Lịch trình - FUTA Bus Lines</title>
    <link rel="stylesheet" href="../css/admin_schedules.css">
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
                    <a href="./admin_schedules.php" class="active"> <span class="menu-icon">🕐</span>
                        <span>Quản lý lịch trình</span>
                    </a>
                </li>
                <li>
                    <a href="./admin_bookings.php"> <span class="menu-icon">🎫</span>
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
            <div class="page-header">
                <h1 class="page-title">🕐 Quản lý Lịch trình</h1>
                <button class="btn-add" onclick="openAddModal()">➕ Thêm lịch trình</button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Tổng lịch trình</h3>
                    <div class="number"><?= number_format($stats['total']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Đang hoạt động</h3>
                    <div class="number"><?= number_format($stats['active']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Chuyến hôm nay</h3>
                    <div class="number"><?= number_format($stats['today']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Ghế đã đặt</h3>
                    <div class="number"><?= number_format($stats['booked_seats']) ?></div>
                </div>
            </div>

            <div class="card">
                <form class="filter-section" method="GET" action="admin_schedules.php">
                    <input type="text" name="search" placeholder="🔍 Tìm theo số xe, tuyến..."
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
                        <option value="all">📊 Trạng thái</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Tạm ngưng
                        </option>
                    </select>

                    <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">

                    <button type="submit" class="btn-add">Tìm kiếm</button>
                </form>

                <?php if (empty($schedules)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🕐</div>
                        <h3>Chưa có lịch trình nào</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tuyến đường</th>
                                    <th>Số xe</th>
                                    <th>Loại xe</th>
                                    <th>Giờ đi - Giờ đến</th>
                                    <th>Giá vé</th>
                                    <th>Ghế trống</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $s): ?>
                                    <tr>
                                        <td><?= $s['id'] ?></td>
                                        <td>
                                            <div class="route-info">
                                                <span><?= htmlspecialchars($s['from_city']) ?></span>
                                                <span class="route-arrow">→</span>
                                                <span><?= htmlspecialchars($s['to_city']) ?></span>
                                            </div>
                                            <small style="color: #999;"><?= number_format($s['distance']) ?> km</small>
                                        </td>
                                        <td><strong><?= htmlspecialchars($s['bus_number']) ?></strong></td>
                                        <td>
                                            <span class="badge badge-<?= $s['bus_type'] ?>">
                                                <?= $bus_types_labels[$s['bus_type']] ?? '🚌 Thường' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 14px;">
                                                <div><?= date('d/m/Y H:i', strtotime($s['departure_time'])) ?></div>
                                                <div style="color: #999;">
                                                    <?= date('d/m/Y H:i', strtotime($s['arrival_time'])) ?></div>
                                            </div>
                                        </td>
                                        <td><strong style="color: #28a745;"><?= number_format($s['price']) ?> đ</strong></td>
                                        <td>
                                            <div class="seat-info">
                                                <span style="font-size: 13px;">
                                                    <?= $s['available_seats'] ?>/<?= $s['total_seats'] ?> ghế
                                                </span>
                                                <div class="seat-bar">
                                                    <?php
                                                    $booked = $s['total_seats'] - $s['available_seats'];
                                                    $percent = $s['total_seats'] > 0 ? ($booked / $s['total_seats']) * 100 : 0;
                                                    ?>
                                                    <div class="seat-fill" style="width: <?= $percent ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $s['status'] ?>">
                                                <?= $s['status'] === 'active' ? '✅ Hoạt động' : '⏸️ Tạm ngưng' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-edit"
                                                onclick='openEditModal(<?= json_encode($s) ?>)'>✏️</button>
                                            <button class="btn btn-toggle"
                                                onclick="toggleStatus(<?= $s['id'] ?>, '<?= $s['status'] === 'active' ? 'inactive' : 'active' ?>')">
                                                <?= $s['status'] === 'active' ? '⏸️' : '▶️' ?>
                                            </button>
                                            <button class="btn btn-delete"
                                                onclick="deleteSchedule(<?= $s['id'] ?>, '<?= htmlspecialchars($s['bus_number']) ?>', <?= $s['booking_count'] ?>)">🗑️</button>
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

    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Thêm lịch trình mới</h2>
                <span class="close-modal" onclick="closeAddModal()">×</span>
            </div>
            <form method="POST" action="admin_schedules.php">
                <input type="hidden" name="action" value="add_schedule">

                <div class="form-group">
                    <label>Tuyến đường <span style="color: red;">*</span></label>
                    <select name="route_id" required>
                        <option value="">-- Chọn tuyến --</option>
                        <?php foreach ($routes as $r): ?>
                            <option value="<?= $r['id'] ?>">
                                <?= htmlspecialchars($r['from_city']) ?> → <?= htmlspecialchars($r['to_city']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Số xe <span style="color: red;">*</span></label>
                        <input type="text" name="bus_number" placeholder="79B-12345" required>
                    </div>
                    <div class="form-group">
                        <label>Loại xe <span style="color: red;">*</span></label>
                        <select name="bus_type" required>
                            <option value="standard">🚌 Xe thường</option>
                            <option value="vip">⭐ Xe VIP</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Giờ khởi hành <span style="color: red;">*</span></label>
                        <input type="datetime-local" name="departure_time" required>
                    </div>
                    <div class="form-group">
                        <label>Giờ đến <span style="color: red;">*</span></label>
                        <input type="datetime-local" name="arrival_time" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Giá vé (VNĐ) <span style="color: red;">*</span></label>
                        <input type="number" name="price" min="1" placeholder="150000" required>
                    </div>
                    <div class="form-group">
                        <label>Số ghế <span style="color: red;">*</span></label>
                        <input type="number" name="total_seats" min="1" value="40" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Thêm lịch trình</button>
            </form>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Chỉnh sửa lịch trình</h2>
                <span class="close-modal" onclick="closeEditModal()">×</span>
            </div>
            <form method="POST" action="admin_schedules.php">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="schedule_id" id="edit_id">

                <div class="form-group">
                    <label>Tuyến đường <span style="color: red;">*</span></label>
                    <select name="route_id" id="edit_route_id" required>
                        <option value="">-- Chọn tuyến --</option>
                        <?php foreach ($routes as $r): ?>
                            <option value="<?= $r['id'] ?>">
                                <?= htmlspecialchars($r['from_city']) ?> → <?= htmlspecialchars($r['to_city']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Số xe <span style="color: red;">*</span></label>
                        <input type="text" name="bus_number" id="edit_bus_number" required>
                    </div>
                    <div class="form-group">
                        <label>Loại xe <span style="color: red;">*</span></label>
                        <select name="bus_type" id="edit_bus_type" required>
                            <option value="standard">🚌 Xe thường</option>
                            <option value="vip">⭐ Xe VIP</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Giờ khởi hành <span style="color: red;">*</span></label>
                        <input type="datetime-local" name="departure_time" id="edit_departure" required>
                    </div>
                    <div class="form-group">
                        <label>Giờ đến <span style="color: red;">*</span></label>
                        <input type="datetime-local" name="arrival_time" id="edit_arrival" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Giá vé (VNĐ) <span style="color: red;">*</span></label>
                        <input type="number" name="price" id="edit_price" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Số ghế <span style="color: red;">*</span></label>
                        <input type="number" name="total_seats" id="edit_total_seats" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Trạng thái <span style="color: red;">*</span></label>
                    <select name="status" id="edit_status" required>
                        <option value="active">✅ Hoạt động</option>
                        <option value="inactive">⏸️ Tạm ngưng</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Cập nhật</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        function openEditModal(schedule) {
            document.getElementById('edit_id').value = schedule.id;
            document.getElementById('edit_route_id').value = schedule.route_id;
            document.getElementById('edit_bus_number').value = schedule.bus_number;
            document.getElementById('edit_bus_type').value = schedule.bus_type;
            document.getElementById('edit_price').value = schedule.price;
            document.getElementById('edit_total_seats').value = schedule.total_seats;
            document.getElementById('edit_status').value = schedule.status;

            // Format datetime for input
            document.getElementById('edit_departure').value = formatDateTimeLocal(new Date(schedule.departure_time));
            document.getElementById('edit_arrival').value = formatDateTimeLocal(new Date(schedule.arrival_time));

            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function formatDateTimeLocal(date) {
            // Lấy thông tin ngày giờ
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            // Trả về chuỗi đúng định dạng 'YYYY-MM-DDTHH:MM'
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        function deleteSchedule(id, busNumber, bookingCount) {
            if (bookingCount > 0) {
                alert('❌ Không thể xóa!\nCó ' + bookingCount + ' vé đang hoạt động cho chuyến xe này.');
                return;
            }
            if (!confirm('Xóa lịch trình xe ' + busNumber + '?')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_schedules.php'; // Gửi về chính nó
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_schedule">
                <input type="hidden" name="schedule_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function toggleStatus(id, newStatus) {
            const statusText = newStatus === 'active' ? 'kích hoạt' : 'tạm ngưng';
            if (!confirm('Bạn muốn ' + statusText + ' lịch trình này?')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_schedules.php'; // Gửi về chính nó
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="schedule_id" value="${id}">
                <input type="hidden" name="new_status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
        }

        // AUTO HIDE ALERT (Đồng bộ từ admin_users)
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);

        // JS cho nút Đăng xuất (Thêm vào)
        async function handleLogout() {
            if (!confirm('Bạn có chắc muốn đăng xuất?')) return;

            const formData = new FormData();
            formData.append('action', 'logout');

            try {
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