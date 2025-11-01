<?php

/**
 * ROLES.PHP - Hệ thống phân quyền Futa Bus
 * 
 * Các role trong hệ thống:
 * - admin: Quản trị viên - Toàn quyền
 * - customer: Khách hàng - Đặt vé, xem lịch sử
 */

// Load config nếu chưa có
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

// Kiểm tra user đã đăng nhập chưa
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Lấy role của user hiện tại
function getCurrentRole()
{
    if (!isLoggedIn()) {
        return 'customer';
    }

    // Lấy role từ database thay vì session
    global $conn;
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Xử lý role rỗng hoặc 'user' -> 'customer'
    $role = $user['role'] ?? 'customer';
    if (empty($role) || $role === 'user') {
        $role = 'customer';
    }

    return $role;
}

// Kiểm tra user có role cụ thể không
function hasRole($role)
{
    if (!isLoggedIn()) {
        return false;
    }

    $current_role = getCurrentRole();

    // Xử lý trường hợp role = 'user' hoặc 'customer'
    if ($role === 'customer' && ($current_role === 'customer' || $current_role === 'user' || empty($current_role))) {
        return true;
    }

    return $current_role === $role;
}

// Kiểm tra user có 1 trong các role không
function hasAnyRole($roles)
{
    if (!isLoggedIn()) {
        return false;
    }
    $current_role = getCurrentRole();
    return in_array($current_role, $roles);
}

// Kiểm tra có phải admin không
function isAdmin()
{
    return hasRole('admin');
}

// Kiểm tra có phải customer không
function isCustomer()
{
    return hasRole('customer');
}

// YÊU CẦU phải đăng nhập
function requireLogin()
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . getBaseUrl() . 'login.php?error=require_login');
        exit();
    }
}

// YÊU CẦU phải có role cụ thể
function requireRole($required_role)
{
    requireLogin();

    $current_role = getCurrentRole();

    // Admin có toàn quyền
    if ($current_role === 'admin') {
        return true;
    }

    // Kiểm tra role
    if (!hasRole($required_role)) {
        showAccessDenied();
    }

    return true;
}

// YÊU CẦU phải có 1 trong các role
function requireAnyRole($required_roles)
{
    requireLogin();

    if (!hasAnyRole($required_roles)) {
        showAccessDenied();
    }
}

// Hiển thị trang Access Denied
function showAccessDenied()
{
    http_response_code(403);
?>
    <!DOCTYPE html>
    <html lang="vi">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Không có quyền truy cập - FUTA Bus Lines</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .error-container {
                background: white;
                padding: 60px 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                text-align: center;
                max-width: 500px;
            }

            .error-icon {
                font-size: 100px;
                margin-bottom: 30px;
            }

            h1 {
                color: #dc3545;
                font-size: 32px;
                margin-bottom: 15px;
            }

            p {
                color: #666;
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 30px;
            }

            .btn {
                display: inline-block;
                padding: 14px 30px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin: 0 5px;
            }

            .btn:hover {
                background: #5568d3;
            }
        </style>
    </head>

    <body>
        <div class="error-container">
            <div class="error-icon">🚫</div>
            <h1>Không có quyền truy cập</h1>
            <p>Bạn không có quyền truy cập vào trang này.</p>
            <a href="<?= getBaseUrl() ?>index.php" class="btn">🏠 Về trang chủ</a>
        </div>
    </body>

    </html>
<?php
    exit();
}

// Lấy base URL
function getBaseUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);

    if (strpos($path, '/admin') !== false) {
        $path = str_replace('/admin', '', $path);
    }

    return $protocol . '://' . $host . rtrim($path, '/') . '/';
}

// Lấy tên hiển thị của role
function getRoleName($role = null)
{
    if ($role === null) {
        $role = getCurrentRole();
    }

    $role_names = [
        'admin' => 'Quản trị viên',
        'customer' => 'Khách hàng',
        'user' => 'Khách hàng'
    ];

    return $role_names[$role] ?? 'Khách hàng';
}

// Lấy icon của role
function getRoleIcon($role = null)
{
    if ($role === null) {
        $role = getCurrentRole();
    }

    $role_icons = [
        'admin' => '👑',
        'customer' => '👤',
        'user' => '👤'
    ];

    return $role_icons[$role] ?? '👤';
}

// Lấy màu của role
function getRoleColor($role = null)
{
    if ($role === null) {
        $role = getCurrentRole();
    }

    $role_colors = [
        'admin' => '#dc3545',
        'customer' => '#007bff',
        'user' => '#007bff'
    ];

    return $role_colors[$role] ?? '#007bff';
}

// Lấy URL dashboard theo role
function getDashboardUrl($role = null)
{
    if ($role === null) {
        $role = getCurrentRole();
    }

    $dashboards = [
        'admin' => 'admin/admin_dashboard.php',
        'customer' => 'customer/customer_dashboard.php',
        'user' => 'customer/customer_dashboard.php'
    ];

    return $dashboards[$role] ?? 'index.php';
}
