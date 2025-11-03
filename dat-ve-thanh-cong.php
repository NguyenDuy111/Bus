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
    // Sửa: Chỉ cần `status = 'confirmed'`
    $stmt = $conn->prepare("
        SELECT b.*, s.departure_time, s.arrival_time, r.from_city, r.to_city
        FROM bookings b
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE b.booking_code = ? AND b.user_id = ? AND b.status = 'confirmed'
    ");
    $stmt->bind_param("si", $booking_code, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Không tìm thấy vé đã được xác nhận của bạn.");
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
    <style>
        .success-container {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .success-container h1 {
            font-size: 28px;
            color: #28a745;
            margin-bottom: 15px;
        }

        .success-container p {
            font-size: 16px;
            color: #555;
            margin-bottom: 30px;
        }

        .ticket-info {
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
            padding: 25px;
            max-width: 600px;
            margin: 0 auto 30px auto;
            text-align: left;
        }

        .ticket-info h2 {
            font-size: 20px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .ticket-info .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 15px;
        }

        .ticket-info .summary-item span {
            color: #666;
        }

        .ticket-info .summary-item strong {
            color: #333;
            text-align: right;
        }

        .ticket-info .summary-item.total strong {
            color: #28a745;
            font-size: 1.1em;
        }

        /* Thêm style cho 'chưa thanh toán' */
        .ticket-info .summary-item.total strong.unpaid {
            color: #ff6b35;
            /* Màu cam */
        }

        .qr-code {
            margin-bottom: 30px;
        }

        .qr-code img {
            border: 5px solid #eee;
            border-radius: 8px;
        }

        .qr-code p {
            font-size: 14px;
            color: #777;
            margin-top: 10px;
        }

        .btn-submit {
            display: inline-block;
            width: auto;
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 30px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
        }
    </style>
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

                            <?php if ($booking['payment_status'] === 'paid'): ?>
                                <strong class="paid">
                                    <?= format_currency($booking['total_price']) ?> (Đã thanh toán)
                                </strong>
                            <?php else: ?>
                                <strong class="unpaid">
                                    <?= format_currency($booking['total_price']) ?> (Chưa thanh toán)
                                </strong>
                            <?php endif; ?>
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