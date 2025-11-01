<?php
require_once 'config.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$booking_code = escape_string(trim($_GET['booking_code'] ?? ''));
if (empty($booking_code)) {
    die("Mã đặt vé không hợp lệ.");
}

// Lấy thông tin vé
$booking = null;
try {
    $stmt = $conn->prepare("
        SELECT b.*, s.departure_time, s.arrival_time, r.from_city, r.to_city
        FROM bookings b
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE b.booking_code = ? AND b.user_id = ? AND b.payment_status = 'paid'
    ");
    $stmt->bind_param("si", $booking_code, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("Không tìm thấy vé đã thanh toán của bạn.");
    }
    $booking = $result->fetch_assoc();
    
} catch (Exception $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt vé thành công - FUTA Bus Lines</title>
    <link rel="stylesheet" href="./css/booking-process.css">
</head>

<body>
    <div class="header-mini">
        <a href="index.php" class="logo-mini">🚍 FUTA Bus Lines</a>
        <a href="index.php" class="back-home">Về trang chủ</a>
    </div>

    <div class="main-container">
        <div class="booking-process-container">
            <div class="container-header">
                <h1>Đặt vé hoàn tất!</h1>
                <p>Cảm ơn bạn đã tin tưởng FUTA Bus Lines</p>
            </div>

            <div class="form-wrapper">

                <div class="booking-steps">
                    <div class="step complete">1. Chọn ghế</div>
                    <div class="step complete">2. Thanh toán</div>
                    <div class="step active">3. Hoàn tất</div>
                </div>

                <div class="success-container">
                    <div class="success-icon">✅</div>
                    <h1>Đặt vé thành công!</h1>
                    <p>Vé của bạn đã được xác nhận. Vui lòng kiểm tra lại thông tin dưới đây.</p>

                    <div class="ticket-info">
                        <h2>Thông tin vé</h2>
                        <div class="summary-item">
                            <span>Mã đặt vé:</span>
                            <strong><?= htmlspecialchars($booking['booking_code']) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Hành khách:</span>
                            <strong><?= htmlspecialchars($booking['passenger_name']) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Tuyến đường:</span>
                            <strong><?= htmlspecialchars($booking['from_city']) ?> →
                                <?= htmlspecialchars($booking['to_city']) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Thời gian:</span>
                            <strong><?= format_time($booking['departure_time']) ?> -
                                <?= format_date($booking['booking_date']) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Số ghế:</span>
                            <strong><?= htmlspecialchars($booking['seat_numbers']) ?></strong>
                        </div>
                        <div class="summary-item total">
                            <span>Tổng tiền:</span>
                            <strong><?= format_currency($booking['total_price']) ?> (Đã thanh toán)</strong>
                        </div>
                    </div>

                    <div class="qr-code">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= htmlspecialchars($booking['booking_code']) ?>"
                            alt="QR Code Mã vé">
                        <p>Sử dụng mã này để xuất trình khi lên xe.</p>
                    </div>

                    <a href="index.php" class="btn-submit">Về trang chủ</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>