<?php
// config.php - Cấu hình kết nối database
session_start();

// Cấu hình database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'futa_bus');

// Kết nối database
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Kết nối thất bại: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Lỗi database: " . $e->getMessage());
}

// Hàm helper
function escape_string($string)
{
    global $conn;
    return $conn->real_escape_string($string);
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function get_user_info()
{
    if (!is_logged_in()) {
        return null;
    }

    global $conn;
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function format_currency($amount)
{
    return number_format($amount, 0, ',', '.') . 'đ';
}

function format_date($date)
{
    return date('d/m/Y', strtotime($date));
}

function format_time($time)
{
    return date('H:i', strtotime($time));
}

// Tạo bảng database nếu chưa có
function create_database_tables()
{
    global $conn;

    // Bảng users
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(15) UNIQUE NOT NULL,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Bảng routes (tuyến đường)
    $sql_routes = "CREATE TABLE IF NOT EXISTS routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_city VARCHAR(100) NOT NULL,
        to_city VARCHAR(100) NOT NULL,
        distance INT NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Bảng schedules (lịch trình)
    $sql_schedules = "CREATE TABLE IF NOT EXISTS schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        route_id INT NOT NULL,
        bus_number VARCHAR(20) NOT NULL,
        departure_time DATETIME NOT NULL,
        arrival_time DATETIME NOT NULL,
        price INT NOT NULL,
        bus_type ENUM('standard', 'vip', 'limousine') DEFAULT 'standard',
        total_seats INT NOT NULL DEFAULT 40,
        available_seats INT NOT NULL DEFAULT 40,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Bảng bookings (đặt vé)
    $sql_bookings = "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        schedule_id INT NOT NULL,
        booking_date DATE NOT NULL,
        num_tickets INT NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        passenger_name VARCHAR(100) NOT NULL,
        passenger_phone VARCHAR(15) NOT NULL,
        seat_numbers VARCHAR(255),
        status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
        booking_code VARCHAR(20) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Thực thi các câu lệnh tạo bảng
    $conn->query($sql_users);
    $conn->query($sql_routes);
    $conn->query($sql_schedules);
    $conn->query($sql_bookings);
}

// Khởi tạo database
create_database_tables();

// Thêm dữ liệu mẫu nếu chưa có
function insert_sample_data()
{
    global $conn;

    // Kiểm tra xem đã có dữ liệu chưa
    $result = $conn->query("SELECT COUNT(*) as count FROM routes");
    $row = $result->fetch_assoc();

    if ($row['count'] == 0) {
        // Thêm tuyến đường
        $routes = [
            ['TP. Hồ Chí Minh', 'Cần Thơ', 170],
            ['TP. Hồ Chí Minh', 'Đà Lạt', 300],
            ['TP. Hồ Chí Minh', 'Nha Trang', 450],
            ['TP. Hồ Chí Minh', 'Vũng Tàu', 125],
            ['Cần Thơ', 'Đà Lạt', 280],
            ['Đà Lạt', 'Nha Trang', 200]
        ];

        foreach ($routes as $route) {
            $stmt = $conn->prepare("INSERT INTO routes (from_city, to_city, distance) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $route[0], $route[1], $route[2]);
            $stmt->execute();
        }

        // ===============================================
        // BẮT ĐẦU PHẦN CODE ĐÃ SỬA
        // ===============================================

        // Lấy ngày hôm nay và ngày mai để làm dữ liệu mẫu
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        // Thêm lịch trình
        // Cấu trúc dữ liệu mới:
        // [route_id, bus_number, departure_time, arrival_time, price, bus_type, total_seats, available_seats]
        $schedules = [
            [1, '51B-123.45', "$today 06:00:00", "$today 09:00:00", 150000, 'standard', 45, 45],
            [1, '51B-123.46', "$today 14:00:00", "$today 17:00:00", 150000, 'standard', 45, 45],
            [2, '51F-222.22', "$today 07:30:00", "$today 13:30:00", 250000, 'vip', 24, 24],
            [2, '51F-222.23', "$today 21:00:00", "$tomorrow 03:00:00", 280000, 'vip', 24, 24], // Chuyến qua đêm
            [3, '51F-333.33', "$today 22:00:00", "$tomorrow 06:00:00", 300000, 'vip', 24, 24], // Chuyến qua đêm
            [4, '51A-444.44', "$today 09:00:00", "$today 11:00:00", 120000, 'limousine', 16, 16],
            [4, '51A-444.45', "$today 15:00:00", "$today 17:00:00", 120000, 'limousine', 16, 16],
            [5, '65C-555.55', "$today 05:00:00", "$today 11:00:00", 230000, 'limousine', 16, 16],
            [6, '49B-666.66', "$today 08:00:00", "$today 12:00:00", 180000, 'standard', 45, 45]
        ];

        // Câu lệnh INSERT đã sửa (thêm bus_number, bỏ duration)
        $stmt = $conn->prepare("INSERT INTO schedules (route_id, bus_number, departure_time, arrival_time, price, bus_type, total_seats, available_seats) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($schedules as $schedule) {
            // bind_param đã sửa: "isssdsii" (8 tham số)
            $stmt->bind_param("isssdsii", $schedule[0], $schedule[1], $schedule[2], $schedule[3], $schedule[4], $schedule[5], $schedule[6], $schedule[7]);
            $stmt->execute();
        }

        // ===============================================
        // KẾT THÚC PHẦN CODE ĐÃ SỬA
        // ===============================================
    }
}

insert_sample_data();

// echo "Cấu hình và dữ liệu mẫu đã được khởi tạo thành công!"; // Bỏ comment dòng này nếu muốn thấy thông báo
