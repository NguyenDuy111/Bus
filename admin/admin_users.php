<?php

// admin_users.php - Quản lý người dùng

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
        case 'add_user':
            $full_name = escape_string(trim($_POST['full_name'] ?? ''));
            $phone = escape_string(trim($_POST['phone'] ?? ''));
            $email = escape_string(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $role = escape_string($_POST['role'] ?? 'customer');

            if (empty($full_name) || empty($phone) || empty($password)) {
                $message = 'Vui lòng nhập đầy đủ thông tin!';
                $message_type = 'error';
            } else {
                // Kiểm tra số điện thoại đã tồn tại
                $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $message = 'Số điện thoại đã được đăng ký!';
                    $message_type = 'error';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (full_name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $full_name, $phone, $email, $hashed_password, $role);

                    if ($stmt->execute()) {
                        $message = 'Thêm người dùng thành công!';
                        $message_type = 'success';
                    } else {
                        $message = 'Có lỗi xảy ra!';
                        $message_type = 'error';
                    }
                }
            }
            break;

        case 'update_user':
            $user_id = intval($_POST['user_id'] ?? 0);
            $full_name = escape_string(trim($_POST['full_name'] ?? ''));
            $phone = escape_string(trim($_POST['phone'] ?? ''));
            $email = escape_string(trim($_POST['email'] ?? ''));
            $role = escape_string($_POST['role'] ?? 'customer');

            if ($user_id > 0 && !empty($full_name) && !empty($phone)) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $full_name, $phone, $email, $role, $user_id);

                if ($stmt->execute()) {
                    $message = 'Cập nhật thành công!';
                    $message_type = 'success';
                } else {
                    $message = 'Có lỗi xảy ra!';
                    $message_type = 'error';
                }
            }
            break;

        case 'delete_user':
            $user_id = intval($_POST['user_id'] ?? 0);

            if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);

                if ($stmt->execute()) {
                    $message = 'Xóa người dùng thành công!';
                    $message_type = 'success';
                } else {
                    $message = 'Có lỗi xảy ra!';
                    $message_type = 'error';
                }
            } else {
                $message = 'Không thể xóa tài khoản này!';
                $message_type = 'error';
            }
            break;

        case 'change_password':
            $user_id = intval($_POST['user_id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';

            if ($user_id > 0 && !empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);

                if ($stmt->execute()) {
                    $message = 'Đổi mật khẩu thành công!';
                    $message_type = 'success';
                } else {
                    $message = 'Có lỗi xảy ra!';
                    $message_type = 'error';
                }
            }
            break;
    }
}

// LẤY DANH SÁCH NGƯỜI DÙNG
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($role_filter) && $role_filter !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Thống kê
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'admin' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'],
    'staff' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff'")->fetch_assoc()['count'],
    'customer' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' OR role = ''")->fetch_assoc()['count'] // Đếm cả role rỗng
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Người dùng - FUTA Bus Lines</title>
    <link rel="stylesheet" href="../css/admin_users.css">

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
                    <a href="./admin_users.php" class="active"> <span class="menu-icon">👥</span>
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
                    <a href="./admin_schedules.php"> <span class="menu-icon">🕐</span>
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
                <h1 class="page-title">👥 Quản lý Người dùng</h1>
                <button class="btn-add" onclick="openAddModal()">
                    <span>➕</span> Thêm người dùng
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Tổng người dùng</h3>
                    <div class="number"><?= number_format($stats['total']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Quản trị viên</h3>
                    <div class="number"><?= number_format($stats['admin']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Nhân viên</h3>
                    <div class="number"><?= number_format($stats['staff']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Khách hàng</h3>
                    <div class="number"><?= number_format($stats['customer']) ?></div>
                </div>
            </div>

            <div class="card">
                <form class="filter-section" method="GET" action="admin_users.php">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="🔍 Tìm kiếm theo tên, SĐT, email..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="filter-box">
                        <select name="role" onchange="this.form.submit()">
                            <option value="all" <?= $role_filter === 'all' || $role_filter === '' ? 'selected' : '' ?>>
                                Tất cả vai trò</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Nhân viên</option>
                            <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Khách hàng
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn-add">Tìm kiếm</button>
                </form>

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
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['phone']) ?></td>
                                    <td><?= htmlspecialchars($u['email'] ?? 'Chưa có') ?></td>
                                    <td>
                                        <?php
                                        // ================== SỬA LỖI 2: Undefined array key ==================
                                        // Sửa logic để xử lý cả chuỗi rỗng "" và NULL
                                        $role = $u['role'] ?: 'customer';
                                        // ===================================================================
                                        $role_labels = [
                                            'admin' => '👑 Admin',

                                            'customer' => '👤 Khách hàng'
                                        ];
                                        ?>
                                        <span class="badge badge-<?= $role ?>">
                                            <?= $role_labels[$role] ?>
                                        </span>
                                    </td>
                                    <td><?= format_date($u['created_at']) ?></td>
                                    <td>
                                        <button class="btn btn-edit"
                                            onclick='openEditModal(<?= json_encode($u) ?>)'>Sửa</button>
                                        <button class="btn btn-password"
                                            onclick="changePassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">Đổi
                                            MK</button>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-delete"
                                                onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">Xóa</button>
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

    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Thêm người dùng mới</h2>
                <span class="close-modal" onclick="closeAddModal()">×</span>
            </div>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action" value="add_user">

                <div class="form-group">
                    <label>Họ và tên <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" required>
                </div>

                <div class="form-group">
                    <label>Số điện thoại <span style="color: red;">*</span></label>
                    <input type="tel" name="phone" placeholder="0xxxxxxxxx" maxlength="10" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@example.com">
                </div>

                <div class="form-group">
                    <label>Mật khẩu <span style="color: red;">*</span></label>
                    <input type="password" name="password" minlength="6" required>
                </div>

                <div class="form-group">
                    <label>Vai trò <span style="color: red;">*</span></label>
                    <select name="role" required>
                        <option value="customer">👤 Khách hàng</option>
                        <option value="staff">👨‍💼 Nhân viên</option>
                        <option value="admin">👑 Quản trị viên</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Thêm người dùng</button>
            </form>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Chỉnh sửa người dùng</h2>
                <span class="close-modal" onclick="closeEditModal()">×</span>
            </div>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="form-group">
                    <label>Họ và tên <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>

                <div class="form-group">
                    <label>Số điện thoại <span style="color: red;">*</span></label>
                    <input type="tel" name="phone" id="edit_phone" maxlength="10" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>

                <div class="form-group">
                    <label>Vai trò <span style="color: red;">*</span></label>
                    <select name="role" id="edit_role" required>
                        <option value="customer">👤 Khách hàng</option>
                        <option value="staff">👨‍💼 Nhân viên</option>
                        <option value="admin">👑 Quản trị viên</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Cập nhật</button>
            </form>
        </div>
    </div>

    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🔒 Đổi mật khẩu</h2>
                <span class="close-modal" onclick="closePasswordModal()">×</span>
            </div>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="user_id" id="pwd_user_id">

                <div class="form-group">
                    <label>Người dùng</label>
                    <input type="text" id="pwd_user_name" readonly style="background: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label>Mật khẩu mới <span style="color: red;">*</span></label>
                    <input type="password" name="new_password" minlength="6" required>
                </div>

                <button type="submit" class="btn-submit">Đổi mật khẩu</button>
            </form>
        </div>
    </div>

    <script>
        // MODAL ADD
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        // MODAL EDIT
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // MODAL PASSWORD
        function changePassword(userId, userName) {
            document.getElementById('pwd_user_id').value = userId;
            document.getElementById('pwd_user_name').value = userName;
            document.getElementById('passwordModal').classList.add('show');
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('show');
        }

        // DELETE USER
        function deleteUser(userId, userName) {
            if (confirm(`Bạn có chắc muốn xóa người dùng "${userName}"?\n\nLưu ý: Hành động này không thể hoàn tác!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_users.php'; // Đảm bảo submit về đúng trang
                form.innerHTML = `
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="${userId}">
            `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // CLOSE MODALS ON CLICK OUTSIDE
        window.onclick = function(event) {
            const modals = ['addModal', 'editModal', 'passwordModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }

        // AUTO HIDE ALERT
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
                // ================== SỬA LỖI 3: Sai đường dẫn fetch ==================
                // Phải thêm ../ vì file auth.php nằm ở thư mục gốc
                const response = await fetch('../auth.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert('Đăng xuất thành công!');
                    // Sửa luôn đường dẫn redirect về login.php
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