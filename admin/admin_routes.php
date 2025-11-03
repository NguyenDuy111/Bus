<?php
// admin_routes.php - Quản lý tuyến đường

// 1. GỌI FILE CONFIG & ROLES (Ở THƯ MỤC GỐC)
require_once '../config.php';
require_once '../roles.php';

// YÊU CẦU QUYỀN ADMIN
requireRole('admin');

// Lấy thông tin user (dùng cho header)
$user = get_user_info();

// XỬ LÝ CÁC ACTION
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_route':
            $from_city = escape_string(trim($_POST['from_city'] ?? ''));
            $to_city = escape_string(trim($_POST['to_city'] ?? ''));
            $distance = intval($_POST['distance'] ?? 0);

            if (empty($from_city) || empty($to_city) || $distance <= 0) {
                $message = 'Vui lòng nhập đầy đủ thông tin!';
                $message_type = 'error';
            } else if ($from_city === $to_city) {
                $message = 'Điểm đi và điểm đến không thể giống nhau!';
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("SELECT id FROM routes WHERE from_city = ? AND to_city = ?");
                $stmt->bind_param("ss", $from_city, $to_city);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $message = 'Tuyến đường này đã tồn tại!';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO routes (from_city, to_city, distance) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $from_city, $to_city, $distance);

                    if ($stmt->execute()) {
                        $message = 'Thêm tuyến đường thành công!';
                        $message_type = 'success';
                    } else {
                        $message = 'Có lỗi xảy ra!';
                        $message_type = 'error';
                    }
                }
            }
            break;

        case 'update_route':
            $route_id = intval($_POST['route_id'] ?? 0);
            $from_city = escape_string(trim($_POST['from_city'] ?? ''));
            $to_city = escape_string(trim($_POST['to_city'] ?? ''));
            $distance = intval($_POST['distance'] ?? 0);

            if ($route_id > 0 && !empty($from_city) && !empty($to_city) && $distance > 0) {
                if ($from_city === $to_city) {
                    $message = 'Điểm đi và điểm đến không thể giống nhau!';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE routes SET from_city = ?, to_city = ?, distance = ? WHERE id = ?");
                    $stmt->bind_param("ssii", $from_city, $to_city, $distance, $route_id);

                    if ($stmt->execute()) {
                        $message = 'Cập nhật thành công!';
                        $message_type = 'success';
                    } else {
                        $message = 'Có lỗi xảy ra!';
                        $message_type = 'error';
                    }
                }
            }
            break;

        case 'delete_route':
            $route_id = intval($_POST['route_id'] ?? 0);

            if ($route_id > 0) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM schedules WHERE route_id = ?");
                $stmt->bind_param("i", $route_id);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc()['count'];

                if ($count > 0) {
                    $message = "Không thể xóa! Có $count lịch trình đang sử dụng tuyến này.";
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("DELETE FROM routes WHERE id = ?");
                    $stmt->bind_param("i", $route_id);

                    if ($stmt->execute()) {
                        $message = 'Xóa tuyến đường thành công!';
                        $message_type = 'success';
                    } else {
                        $message = 'Có lỗi xảy ra!';
                        $message_type = 'error';
                    }
                }
            }
            break;
    }
}

// LẤY DANH SÁCH TUYẾN ĐƯỜNG
$search = $_GET['search'] ?? '';

$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM schedules WHERE route_id = r.id) as schedule_count,
        (SELECT COUNT(*) FROM schedules s 
         INNER JOIN bookings b ON s.id = b.schedule_id 
         WHERE s.route_id = r.id AND b.status != 'cancelled') as booking_count
        FROM routes r WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (from_city LIKE ? OR to_city LIKE ?)";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $stmt->bind_param("ss", $search_param, $search_param);
}
$stmt->execute();
$result = $stmt->get_result();

$routes = [];
while ($row = $result->fetch_assoc()) {
    $routes[] = $row;
}

// Thống kê
$stats = [
    'total_routes' => count($routes),
    'total_schedules' => $conn->query("SELECT COUNT(*) as count FROM schedules")->fetch_assoc()['count'],
    'total_distance' => $conn->query("SELECT SUM(distance) as total FROM routes")->fetch_assoc()['total'] ?? 0,
    'avg_distance' => 0
];

if ($stats['total_routes'] > 0) {
    $stats['avg_distance'] = round($stats['total_distance'] / $stats['total_routes']);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tuyến đường - FUTA Bus Lines</title>
    <link rel="stylesheet" href="../css/admin_routes.css">
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
                    <a href="./admin_routes.php" class="active">
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
            <div class="page-header">
                <h1 class="page-title">🛣️ Quản lý Tuyến đường</h1>
                <button class="btn-add" onclick="openAddModal()">➕ Thêm tuyến</button>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Tổng tuyến</h3>
                    <div class="number"><?= number_format($stats['total_routes']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Lịch trình</h3>
                    <div class="number"><?= number_format($stats['total_schedules']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Tổng KM</h3>
                    <div class="number"><?= number_format($stats['total_distance']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>TB/Tuyến</h3>
                    <div class="number"><?= number_format($stats['avg_distance']) ?> KM</div>
                </div>
            </div>

            <div class="card">
                <form class="search-section" method="GET" action="admin_routes.php">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="🔍 Tìm tuyến..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn-add">Tìm</button>
                </form>

                <?php if (empty($routes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🛣️</div>
                    <h3>Chưa có tuyến nào</h3>
                </div>
                <?php else: ?>
                <div class="routes-grid">
                    <?php foreach ($routes as $route): ?>
                    <div class="route-card">
                        <div class="route-header">
                            <div class="city-name"><?= htmlspecialchars($route['from_city']) ?></div>
                            <div class="route-arrow">→</div>
                            <div class="city-name"><?= htmlspecialchars($route['to_city']) ?></div>
                        </div>
                        <div class="info-row">
                            <span class="info-label">📏 Khoảng cách:</span>
                            <span class="info-value"><?= number_format($route['distance']) ?> km</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">🕐 Lịch trình:</span>
                            <span class="info-value"><?= $route['schedule_count'] ?> chuyến</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">🎫 Đã đặt:</span>
                            <span class="info-value"><?= number_format($route['booking_count']) ?> vé</span>
                        </div>
                        <div class="route-actions">
                            <button class="btn btn-edit" onclick='openEditModal(<?= json_encode($route) ?>)'>✏️
                                Sửa</button>
                            <button class="btn btn-delete"
                                onclick="deleteRoute(<?= $route['id'] ?>, '<?= addslashes($route['from_city']) ?>', '<?= addslashes($route['to_city']) ?>', <?= $route['schedule_count'] ?>)">🗑️
                                Xóa</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Thêm tuyến mới</h2>
                <span class="close-modal" onclick="closeAddModal()">×</span>
            </div>
            <form method="POST" action="admin_routes.php">
                <input type="hidden" name="action" value="add_route">
                <div class="form-row">
                    <div class="form-group">
                        <label>Điểm đi *</label>
                        <input type="text" name="from_city" placeholder="TP. Hồ Chí Minh" required>
                    </div>
                    <div class="arrow-icon">→</div>
                    <div class="form-group">
                        <label>Điểm đến *</label>
                        <input type="text" name="to_city" placeholder="Cần Thơ" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Khoảng cách (km) *</label>
                    <input type="number" name="distance" min="1" placeholder="170" required>
                </div>
                <button type="submit" class="btn-submit">Thêm tuyến</button>
            </form>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Sửa tuyến</h2>
                <span class="close-modal" onclick="closeEditModal()">×</span>
            </div>
            <form method="POST" action="admin_routes.php">
                <input type="hidden" name="action" value="update_route">
                <input type="hidden" name="route_id" id="edit_id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Điểm đi *</label>
                        <input type="text" name="from_city" id="edit_from" required>
                    </div>
                    <div class="arrow-icon">→</div>
                    <div class="form-group">
                        <label>Điểm đến *</label>
                        <input type="text" name="to_city" id="edit_to" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Khoảng cách (km) *</label>
                    <input type="number" name="distance" id="edit_distance" min="1" required>
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

    function openEditModal(route) {
        document.getElementById('edit_id').value = route.id;
        document.getElementById('edit_from').value = route.from_city;
        document.getElementById('edit_to').value = route.to_city;
        document.getElementById('edit_distance').value = route.distance;
        document.getElementById('editModal').classList.add('show');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }

    function deleteRoute(id, from, to, schedules) {
        if (schedules > 0) {
            alert('❌ Không thể xóa!\nCó ' + schedules + ' lịch trình đang dùng tuyến này.');
            return;
        }
        if (!confirm('Xóa tuyến: ' + from + ' → ' + to + '?')) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin_routes.php';
        form.innerHTML = `
                <input type="hidden" name="action" value="delete_route">
                <input type="hidden" name="route_id" value="${id}">
            `;
        document.body.appendChild(form);
        form.submit();
    }

    // Close modal on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    }

    // Auto hide alert
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);

    // JS cho nút Đăng xuất (Từ file trước)
    async function handleLogout() {
        if (!confirm('Bạn có chắc muốn đăng xuất?')) return;

        const formData = new FormData();
        formData.append('action', 'logout');

        try {
            // ========================================================
            // SỬA LỖI: Thêm ../ để đi ra thư mục gốc
            // ========================================================
            const response = await fetch('../auth.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                alert('Đăng xuất thành công!');
                // Sửa: Thêm ../ để trỏ về file login.php ở gốc
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