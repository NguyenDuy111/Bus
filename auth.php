<?php
// auth.php - DEBUG VERSION
require_once 'config.php';

// BẬT HIỂN THỊ LỖI
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            handleLogin();
            break;
        case 'register':
            handleRegister();
            break;
        case 'logout':
            handleLogout();
            break;
        case 'check_session':
            checkSession();
            break;
        default:
            $response['message'] = 'Action không hợp lệ';
            echo json_encode($response);
    }
}

// ========================================================
// HÀM LOGIN ĐÃ SỬA
// ========================================================
function handleLogin()
{
    global $conn, $response;

    $phone = escape_string(trim($_POST['phone'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($phone) || empty($password)) {
        $response['message'] = 'Vui lòng nhập đầy đủ thông tin';
        echo json_encode($response);
        return;
    }

    if (!preg_match('/^(0[3|5|7|8|9])[0-9]{8}$/', $phone)) {
        $response['message'] = 'Số điện thoại không hợp lệ';
        echo json_encode($response);
        return;
    }

    $stmt = $conn->prepare("SELECT id, full_name, phone, email, password, role FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $response['message'] = 'Số điện thoại chưa được đăng ký';
        echo json_encode($response);
        return;
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        $response['message'] = 'Mật khẩu không chính xác';
        echo json_encode($response);
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['email'] = $user['email'];
    // Sửa lỗi logic: Dùng ?: để xử lý cả NULL và chuỗi rỗng ""
    $_SESSION['role'] = $user['role'] ?: 'customer';

    $response['success'] = true;
    $response['message'] = 'Đăng nhập thành công';
    $response['user'] = [
        'id' => $user['id'],
        'full_name' => $user['full_name'],
        'phone' => $user['phone'],
        'email' => $user['email'],
        'role' => $_SESSION['role']
    ];

    // ================== SỬA LỖI LOGIC REDIRECT ==================
    // Lấy URL redirect_to từ POST (đã được JS gửi lên)
    $redirect_to = $_POST['redirect_to'] ?? '';

    if (!empty($redirect_to)) {
        // Nếu có URL_redirect (từ trang đặt vé), ưu tiên nó
        $response['redirect'] = $redirect_to;
    } else {
        // Nếu không, dùng logic phân quyền như cũ
        switch ($_SESSION['role']) {
            case 'admin':
                $response['redirect'] = './admin/admin_dashboard.php';
                break;
            case 'staff':
                $response['redirect'] = 'staff_dashboard.php';
                break;
            default:
                $response['redirect'] = 'index.php';
        }
    }
    // ================== KẾT THÚC SỬA ==================

    echo json_encode($response);
}
// ========================================================
// KẾT THÚC HÀM LOGIN ĐÃ SỬA
// ========================================================


function handleRegister()
{
    global $conn, $response;

    try {
        $full_name = escape_string(trim($_POST['full_name'] ?? ''));
        $phone = escape_string(trim($_POST['phone'] ?? ''));
        $email = escape_string(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        error_log("Register attempt - Name: $full_name, Phone: $phone, Email: $email");

        // Validate
        if (empty($full_name) || empty($phone) || empty($password)) {
            $response['message'] = 'Vui lòng nhập đầy đủ thông tin bắt buộc';
            echo json_encode($response);
            return;
        }

        if (strlen($full_name) < 2) {
            $response['message'] = 'Họ và tên phải có ít nhất 2 ký tự';
            echo json_encode($response);
            return;
        }

        if (!preg_match('/^(0[3|5|7|8|9])[0-9]{8}$/', $phone)) {
            $response['message'] = 'Số điện thoại không hợp lệ (VD: 0912345678)';
            echo json_encode($response);
            return;
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Email không hợp lệ';
            echo json_encode($response);
            return;
        }

        if (strlen($password) < 6) {
            $response['message'] = 'Mật khẩu phải có ít nhất 6 ký tự';
            echo json_encode($response);
            return;
        }

        if ($password !== $confirm_password) {
            $response['message'] = 'Mật khẩu xác nhận không khớp';
            echo json_encode($response);
            return;
        }

        // Kiểm tra số điện thoại đã tồn tại
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $response['message'] = 'Số điện thoại đã được đăng ký';
            echo json_encode($response);
            return;
        }

        // Kiểm tra email đã tồn tại
        if (!empty($email)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response['message'] = 'Email đã được đăng ký';
                echo json_encode($response);
                return;
            }
        }

        // Hash mật khẩu
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Thêm user mới (mặc định role là 'customer')
        $stmt = $conn->prepare("INSERT INTO users (full_name, phone, email, password, role) VALUES (?, ?, ?, ?, 'customer')");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssss", $full_name, $phone, $email, $hashed_password);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Đăng ký thành công! Vui lòng đăng nhập.';
            error_log("User registered successfully - Phone: $phone");
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        $response['message'] = 'Lỗi: ' . $e->getMessage();
        error_log("Register error: " . $e->getMessage());
    }

    echo json_encode($response);
}

function handleLogout()
{
    global $response;

    session_destroy();
    $response['success'] = true;
    $response['message'] = 'Đăng xuất thành công';

    echo json_encode($response);
}

function checkSession()
{
    global $response;

    if (is_logged_in()) {
        $response['success'] = true;
        $response['user'] = [
            'id' => $_SESSION['user_id'],
            'full_name' => $_SESSION['full_name'],
            'phone' => $_SESSION['phone'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'] ?? 'customer'
        ];
    }

    echo json_encode($response);
}
