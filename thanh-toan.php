<?php
require_once 'config.php';

// 1. Kiểm tra đăng nhập
if (!is_logged_in()) {
    $redirect_url = urlencode($_SERVER['REQUEST_URI']);
    redirect("login.php?error=require_login&redirect_to=$redirect_url");
}

// 2. Lấy mã đặt vé
$booking_code = escape_string(trim($_GET['booking_code'] ?? ''));
if (empty($booking_code)) {
    die("Mã đặt vé không hợp lệ.");
}

// 3. Lấy thông tin vé
$booking = null;
try {
    $stmt = $conn->prepare("
        SELECT b.*, s.departure_time, s.arrival_time, r.from_city, r.to_city, s.bus_type,
               s.id as schedule_id 
        FROM bookings b
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE b.booking_code = ? AND b.user_id = ?
    ");

    $stmt->bind_param("si", $booking_code, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Không tìm thấy thông tin đặt vé của bạn.");
    }

    $booking = $result->fetch_assoc();

    if ($booking['status'] === 'confirmed') {
        redirect("dat-ve-thanh-cong.php?booking_code=" . $booking_code);
    }
} catch (Exception $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - FUTA Bus Lines</title>
    <link rel="stylesheet" href="./css/booking-process.css">
</head>

<body>
    <div class="header-mini">
        <a href="index.php" class="logo-mini">🚍 FUTA Bus Lines</a>

        <a href="javascript:void(0);" onclick="cancelAndGoBack(<?= $booking['id'] ?>, <?= $booking['schedule_id'] ?>)"
            class="back-home">← Quay lại
        </a>
    </div>

    <div class="main-container">
        <div class="booking-process-container">
            <div class="container-header">
                <h1>Xác nhận thanh toán</h1>
                <p>Kiểm tra thông tin và hoàn tất đặt vé</p>
            </div>

            <div class="form-wrapper">

                <div class="booking-steps">
                    <div class="step complete">1. Chọn ghế</div>
                    <div class="step active">2. Thanh toán</div>
                    <div class="step">3. Hoàn tất</div>
                </div>

                <div class="content-wrapper">
                    <div class="payment-summary">
                        <h2>Tóm tắt chuyến đi</h2>
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
                            <span>Hành khách:</span>
                            <strong><?= htmlspecialchars($booking['passenger_name']) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Số điện thoại:</span>
                            <strong><?= htmlspecialchars($booking['passenger_phone']) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Số ghế:</span>
                            <strong><?= htmlspecialchars($booking['seat_numbers']) ?> (<?= $booking['num_tickets'] ?>
                                vé)</strong>
                        </div>
                        <div class="summary-item total">
                            <span>Tổng tiền:</span>
                            <strong><?= format_currency($booking['total_price']) ?></strong>
                        </div>
                        <div class="payment-note">
                            Vé của bạn đang được tạm giữ. Vui lòng hoàn tất thanh toán trong <strong>15:00</strong>.
                        </div>
                    </div>

                    <div class="payment-methods">
                        <h2>Hình thức thanh toán</h2>

                        <div class="payment-option">
                            <input type="radio" id="pay-counter" name="payment_method" value="counter" checked disabled>
                            <label for="pay-counter" style="cursor: default;">
                                <b>Thanh toán bằng Tiền mặt</b>
                                <span>(Giữ vé, thanh toán tại văn phòng FUTA)</span>
                            </label>
                        </div>

                        <br>
                        <button class="btn-submit" id="btn-confirm-payment">Xác nhận</button>
                        <div id="form-message" class="form-message" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hàm Xác nhận (Tiền mặt)
        document.getElementById('btn-confirm-payment').addEventListener('click', async function() {
            const btn = this;
            const formMessage = document.getElementById('form-message');
            const bookingCode = '<?= $booking_code ?>';

            btn.textContent = 'Đang xử lý...';
            btn.disabled = true;
            formMessage.textContent = '';
            formMessage.style.display = 'none';

            try {
                const formData = new FormData();
                formData.append('action', 'confirm_payment');
                formData.append('booking_code', bookingCode);
                formData.append('payment_method', 'counter');

                const response = await fetch('booking.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = `dat-ve-thanh-cong.php?booking_code=${bookingCode}`;
                } else {
                    formMessage.textContent = `❌ ${data.message || 'Có lỗi xảy ra'}`;
                    formMessage.className = 'form-message error';
                    formMessage.style.display = 'block';
                    btn.textContent = 'Xác nhận';
                    btn.disabled = false;
                }
            } catch (error) {
                formMessage.textContent = '❌ Lỗi kết nối. Vui lòng thử lại.';
                formMessage.className = 'form-message error';
                formMessage.style.display = 'block';
                btn.textContent = 'Xác nhận';
                btn.disabled = false;
            }
        });

        // =======================================================
        // THÊM MỚI: Hàm Hủy vé và Quay lại
        // =======================================================
        async function cancelAndGoBack(bookingId, scheduleId) {
            if (!confirm('Bạn có chắc muốn quay lại?\nVé đang giữ của bạn sẽ bị hủy.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'cancel_booking');
            formData.append('booking_id', bookingId);

            try {
                // Gọi API 'booking.php' để hủy vé
                const response = await fetch('booking.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    // Hủy thành công, quay lại trang chọn ghế
                    window.location.href = `chon-ghe.php?schedule_id=${scheduleId}`;
                } else {
                    alert('Lỗi khi hủy vé: ' + (data.message || 'Lỗi không xác định'));
                    // Dù lỗi vẫn quay về
                    window.location.href = `chon-ghe.php?schedule_id=${scheduleId}`;
                }
            } catch (error) {
                alert('Lỗi kết nối. Vui lòng thử lại.');
            }
        }
    </script>
</body>

</html>