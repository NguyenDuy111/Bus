<?php
// booking.php - Xử lý tìm kiếm chuyến xe và đặt vé
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_all_schedules':
            getAllSchedules();
            break;
        case 'book_ticket':
            bookTicket();
            break;
        case 'confirm_payment':
            confirmPayment();
            break;
        case 'get_my_bookings':
            getMyBookings();
            break;
        case 'cancel_booking':
            cancelBooking();
            break;
        default:
            $response['message'] = 'Action không hợp lệ';
            echo json_encode($response);
    }
}

// Lấy tất cả lịch trình
function getAllSchedules()
{
    global $conn, $response;
    $keyword = escape_string(trim($_POST['keyword'] ?? ''));
    $bus_type = escape_string($_POST['bus_type'] ?? '');
    $sort_by = $_POST['sort_by'] ?? 'time';
    $sql = "
        SELECT s.*, r.from_city, r.to_city, r.distance,
               TIMESTAMPDIFF(MINUTE, s.departure_time, s.arrival_time) AS duration_minutes
        FROM schedules s
        INNER JOIN routes r ON s.route_id = r.id
        WHERE s.status = 'active' AND s.departure_time > NOW()
    ";
    $params = [];
    $types = '';
    if (!empty($keyword)) {
        $sql .= " AND (r.from_city LIKE ? OR r.to_city LIKE ?)";
        $keyword_param = '%' . $keyword . '%';
        $params[] = $keyword_param;
        $params[] = $keyword_param;
        $types .= 'ss';
    }
    if (!empty($bus_type) && $bus_type !== 'all') {
        $sql .= " AND s.bus_type = ?";
        $params[] = $bus_type;
        $types .= 's';
    }
    switch ($sort_by) {
        case 'price-asc':
            $sql .= " ORDER BY s.price ASC";
            break;
        case 'price-desc':
            $sql .= " ORDER BY s.price DESC";
            break;
        case 'duration':
            $sql .= " ORDER BY duration_minutes ASC";
            break;
        default:
            $sql .= " ORDER BY s.departure_time ASC";
    }
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = formatScheduleData($row);
    }
    $response['success'] = true;
    $response['data'] = $schedules;
    $response['message'] = 'Tìm thấy ' . count($schedules) . ' chuyến xe';
    echo json_encode($response);
}

// Format dữ liệu lịch trình
function formatScheduleData($row)
{
    $bus_type_names = [
        'standard' => 'Ghế ngồi',
        'vip' => 'Giường nằm VIP',
        'limousine' => 'Limousine'
    ];
    $duration_minutes = $row['duration_minutes'] ?? 0;
    $hours = floor($duration_minutes / 60);
    $minutes = $duration_minutes % 60;
    $duration_formatted = '';
    if ($hours > 0) {
        $duration_formatted .= $hours . ' giờ ';
    }
    $duration_formatted .= $minutes . ' phút';
    if ($duration_minutes <= 0) {
        $duration_formatted = 'N/A';
    }
    return [
        'id' => $row['id'],
        'route' => $row['from_city'] . ' → ' . $row['to_city'],
        'from' => $row['from_city'],
        'to' => $row['to_city'],
        'time' => format_time($row['departure_time']),
        'departure_date' => format_date($row['departure_time']),
        'arrival_time' => format_time($row['arrival_time']),
        'duration' => trim($duration_formatted),
        'price' => floatval($row['price']),
        'price_formatted' => format_currency($row['price']),
        'type' => $bus_type_names[$row['bus_type']] ?? $row['bus_type'],
        'type_class' => $row['bus_type'],
        'seats' => $row['available_seats'],
        'total_seats' => $row['total_seats']
    ];
}


// HÀM BOOKTICKET (Đã có logic dọn vé 'pending')
function bookTicket()
{
    global $conn, $response;

    if (!is_logged_in()) {
        $response['message'] = 'Vui lòng đăng nhập để đặt vé';
        echo json_encode($response);
        return;
    }

    $conn->begin_transaction();
    try {
        // Lấy thông tin POST
        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        $num_tickets = intval($_POST['num_tickets'] ?? 0);
        $total_price = floatval($_POST['total_price'] ?? 0);
        $seat_numbers = escape_string(trim($_POST['seat_numbers'] ?? ''));
        $passenger_name = escape_string(trim($_POST['passenger_name'] ?? $_SESSION['full_name']));
        $passenger_phone = escape_string(trim($_POST['passenger_phone'] ?? $_SESSION['phone']));
        $booking_date = date('Y-m-d');
        $user_id = $_SESSION['user_id'];

        if ($schedule_id === 0 || $num_tickets < 1 || $total_price <= 0) {
            throw new Exception('Thông tin đặt vé không hợp lệ (ID, số vé, hoặc giá vé). Vui lòng thử lại.');
        }

        // Tự động hủy vé "pending" cũ của user này
        $stmt_find_old = $conn->prepare("SELECT id, num_tickets FROM bookings WHERE user_id = ? AND schedule_id = ? AND status = 'pending' FOR UPDATE");
        $stmt_find_old->bind_param("ii", $user_id, $schedule_id);
        $stmt_find_old->execute();
        $result_old = $stmt_find_old->get_result();

        $total_refund_seats = 0;
        $old_booking_ids = [];
        while ($row_old = $result_old->fetch_assoc()) {
            $total_refund_seats += $row_old['num_tickets'];
            $old_booking_ids[] = $row_old['id'];
        }

        // Nếu có vé cũ, hủy vé và hoàn ghế
        if ($total_refund_seats > 0) {
            $ids_placeholder = implode(',', array_fill(0, count($old_booking_ids), '?'));
            $stmt_cancel_old = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id IN ($ids_placeholder)");
            $stmt_cancel_old->bind_param(str_repeat('i', count($old_booking_ids)), ...$old_booking_ids);
            $stmt_cancel_old->execute();

            $stmt_refund_old = $conn->prepare("UPDATE schedules SET available_seats = available_seats + ? WHERE id = ?");
            $stmt_refund_old->bind_param("ii", $total_refund_seats, $schedule_id);
            $stmt_refund_old->execute();
        }

        // Tiếp tục quy trình đặt vé MỚI
        if (empty($seat_numbers)) {
            $seats_to_book_arr = [];
            for ($i = 1; $i <= $num_tickets; $i++) {
                $seats_to_book_arr[] = 'A' . str_pad($i, 2, '0', STR_PAD_LEFT);
            }
            $seat_numbers = implode(',', $seats_to_book_arr);
        }

        // Lấy thông tin lịch trình (đã được hoàn ghế nếu có)
        $stmt = $conn->prepare("SELECT * FROM schedules WHERE id = ? AND status = 'active' FOR UPDATE");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Lịch trình không tồn tại hoặc đã hết hạn');
        }
        $schedule = $result->fetch_assoc();

        // Kiểm tra lại số ghế trống
        if ($schedule['available_seats'] < $num_tickets) {
            throw new Exception('Không đủ ghế trống. Chỉ còn ' . $schedule['available_seats'] . ' ghế');
        }

        // Kiểm tra ghế sắp đặt có bị trùng không
        $seats_to_book = explode(',', $seat_numbers);
        $stmt_check_seats = $conn->prepare("SELECT seat_numbers FROM bookings WHERE schedule_id = ? AND status != 'cancelled' FOR UPDATE");
        $stmt_check_seats->bind_param("i", $schedule_id);
        $stmt_check_seats->execute();
        $result_seats = $stmt_check_seats->get_result();
        $all_booked_seats = [];
        while ($row_seats = $result_seats->fetch_assoc()) {
            if (!empty($row_seats['seat_numbers'])) {
                $all_booked_seats = array_merge($all_booked_seats, explode(',', $row_seats['seat_numbers']));
            }
        }
        foreach ($seats_to_book as $seat) {
            if (in_array($seat, $all_booked_seats)) {
                throw new Exception('Ghế ' . $seat . ' vừa có người khác đặt. Vui lòng chọn ghế khác.');
            }
        }

        // Tạo vé MỚI
        $booking_code = 'FUTA' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
        $stmt_insert = $conn->prepare("
            INSERT INTO bookings (user_id, schedule_id, booking_date, num_tickets, total_price, passenger_name, passenger_phone, seat_numbers, booking_code, status, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')
        ");
        $stmt_insert->bind_param("iisidssss", $user_id, $schedule_id, $booking_date, $num_tickets, $total_price, $passenger_name, $passenger_phone, $seat_numbers, $booking_code);

        if (!$stmt_insert->execute()) {
            throw new Exception("Lỗi khi tạo vé: " . $stmt_insert->error);
        }
        $booking_id = $conn->insert_id;

        // Trừ số ghế cho vé MỚI
        $new_available_seats = $schedule['available_seats'] - $num_tickets;
        $stmt_update = $conn->prepare("UPDATE schedules SET available_seats = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $new_available_seats, $schedule_id);

        if (!$stmt_update->execute()) {
            throw new Exception("Lỗi khi cập nhật ghế: " . $stmt_update->error);
        }

        // Hoàn tất
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Tạo vé thành công! Vui lòng thanh toán.';
        $response['data'] = [
            'booking_id' => $booking_id,
            'booking_code' => $booking_code,
            'total_price' => $total_price,
            'total_price_formatted' => format_currency($total_price)
        ];
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
}


// HÀM XÁC NHẬN THANH TOÁN
function confirmPayment()
{
    global $conn, $response;
    if (!is_logged_in()) {
        $response['message'] = 'Vui lòng đăng nhập';
        echo json_encode($response);
        return;
    }

    $booking_code = escape_string(trim($_POST['booking_code'] ?? ''));
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_code = ? AND user_id = ?");
    $stmt->bind_param("si", $booking_code, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $response['message'] = 'Không tìm thấy vé';
        echo json_encode($response);
        return;
    }

    $booking = $result->fetch_assoc();

    // Nếu vé đã xác nhận rồi thì thôi
    if ($booking['status'] === 'confirmed') {
        $response['success'] = true;
        echo json_encode($response);
        return;
    }

    $new_status = 'confirmed'; // Luôn xác nhận vé
    $new_payment_status = 'unpaid'; // Logic của bạn là luôn đặt 'unpaid' (thanh toán sau)

    $stmt_confirm = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE id = ?");
    $stmt_confirm->bind_param("ssi", $new_status, $new_payment_status, $booking['id']);

    if ($stmt_confirm->execute()) {
        $response['success'] = true;
        $response['message'] = 'Xác nhận vé thành công!';
    } else {
        $response['message'] = 'Lỗi khi cập nhật vé.';
    }

    echo json_encode($response);
}


// Lấy danh sách vé đã đặt
function getMyBookings()
{
    global $conn, $response;
    if (!is_logged_in()) {
        $response['message'] = 'Vui lòng đăng nhập';
        echo json_encode($response);
        return;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT b.*, s.departure_time, s.arrival_time, r.from_city, r.to_city
        FROM bookings b
        INNER JOIN schedules s ON b.schedule_id = s.id
        INNER JOIN routes r ON s.route_id = r.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = [
            'id' => $row['id'],
            'booking_code' => $row['booking_code'],
            'route' => $row['from_city'] . ' → ' . $row['to_city'],
            'booking_date' => format_date($row['booking_date']),
            'departure_time' => format_time($row['departure_time']),
            'num_tickets' => $row['num_tickets'],
            'total_price' => format_currency($row['total_price']),
            'status' => $row['status'],
            'payment_status' => $row['payment_status'],
            'created_at' => format_date($row['created_at'])
        ];
    }
    $response['success'] = true;
    $response['data'] = $bookings;
    echo json_encode($response);
}

// HÀM HỦY VÉ (Đã sửa lỗi cú pháp)
function cancelBooking()
{
    global $conn, $response;
    if (!is_logged_in()) {
        $response['message'] = 'Vui lòng đăng nhập';
        echo json_encode($response);
        return;
    }

    $booking_id = intval($_POST['booking_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    // Dùng FOR UPDATE để khóa hàng, tránh lỗi race condition
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $response['message'] = 'Không tìm thấy vé';
        echo json_encode($response);
        return;
    }

    $booking = $result->fetch_assoc();

    if ($booking['status'] === 'cancelled') {
        $response['message'] = 'Vé đã được hủy trước đó';
        echo json_encode($response);
        return;
    }

    $conn->begin_transaction();
    try {
        // 1. Hủy vé
        $stmt_cancel = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $stmt_cancel->bind_param("i", $booking_id);
        $stmt_cancel->execute();

        // 2. Chỉ hoàn ghế nếu vé đó đang 'confirmed' hoặc 'pending'
        if ($booking['status'] === 'confirmed' || $booking['status'] === 'pending') {
            $stmt_refund = $conn->prepare("UPDATE schedules SET available_seats = available_seats + ? WHERE id = ?");
            $stmt_refund->bind_param("ii", $booking['num_tickets'], $booking['schedule_id']);
            $stmt_refund->execute();
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Hủy vé thành công';
    } catch (Exception $e) {
        $conn->rollback();
        // ========================================================
        // SỬA LỖI SYNTAX: Dùng dấu . (một chấm) thay vì ... (ba chấm)
        // ========================================================
        $response['message'] = 'Có lỗi xảy ra: ' . $e->getMessage();
    }

    echo json_encode($response);
}
