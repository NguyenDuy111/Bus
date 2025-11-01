<?php
require_once 'config.php';

// 1. Kiểm tra đăng nhập
if (!is_logged_in()) {
    // Chuyển hướng về login VÀ mang theo mã vé
    $redirect_url = urlencode($_SERVER['REQUEST_URI']);
    redirect("login.php?error=require_login&redirect_to=$redirect_url");
}

// 2. Lấy mã đặt vé
$booking_code = escape_string(trim($_GET['booking_code'] ?? ''));
if (empty($booking_code)) {
    die("Mã đặt vé không hợp lệ.");
}

// =================================================================
// SỬA LỖI RACE CONDITION (LỖI TỐC ĐỘ)
// Buộc PHP dừng 1 giây để CSDL có thời gian COMMIT (lưu) vé
sleep(1);
// =================================================================


// 3. Lấy thông tin vé
$booking = null;
try {
    $stmt = $conn->prepare("
        SELECT b.*, s.departure_time, s.arrival_time, r.from_city, r.to_city, s.bus_type
        FROM bookings b
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE b.booking_code = ? AND b.user_id = ?
    ");

    // Câu lệnh này sẽ kiểm tra vé VÀ user_id trong session
    $stmt->bind_param("si", $booking_code, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Lỗi này 99% là do xung đột session
        die("Không tìm thấy thông tin đặt vé của bạn. (Lý do: Vé này có thể thuộc về một tài khoản khác đang đăng nhập trên trình duyệt của bạn.)");
    }

    $booking = $result->fetch_assoc();

    // Nếu đã thanh toán, chuyển đi
    if ($booking['payment_status'] === 'paid') {
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
        <a href="chon-ghe.php?schedule_id=<?= htmlspecialchars($booking['schedule_id']) ?>" class="back-home">← Quay
            lại</a>
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
                        <h2>Chọn hình thức thanh toán</h2>

                        <div class="payment-option">
                            <input type="radio" id="pay-counter" name="payment_method" value="counter" checked>
                            <label for="pay-counter">
                                <b>Thanh toán tại quầy</b>
                                <span>(Giữ vé, thanh toán tại văn phòng FUTA)</span>
                            </label>
                        </div>
                        <div class="payment-option disabled">
                            <input type="radio" id="pay-momo" name="payment_method" value="momo" disabled>
                            <label for="pay-momo">
                                <b>Ví Momo</b>
                                <span>(Tính năng đang phát triển)</span>
                            </label>
                        </div>
                        <div class="payment-option disabled">
                            <input type="radio" id="pay-card" name="payment_method" value="card" disabled>
                            <label for="pay-card">
                                <b>Thẻ ATM/Visa</b>
                                <span>(Tính năng đang phát triển)</span>
                            </label>
                        </div>

                        <br>
                        <button class="btn-submit" id="btn-confirm-payment">Xác nhận Thanh toán</button>
                        <div id="form-message" class="form-message"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('btn-confirm-payment').addEventListener('click', async function() {
            const btn = this;
            const formMessage = document.getElementById('form-message');
            const bookingCode = '<?= $booking_code ?>';

            btn.textContent = 'Đang xử lý...';
            btn.disabled = true;
            formMessage.textContent = '';

            try {
                const formData = new FormData();
                formData.append('action', 'confirm_payment');
                formData.append('booking_code', bookingCode);

                const response = await fetch('booking.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = `dat-ve-thanh-cong.php?booking_code=${bookingCode}`;
                } else {
                    formMessage.textContent = `❌ ${data.message}`;
                    formMessage.className = 'form-message error';
                    btn.textContent = 'Xác nhận Thanh toán';
                    btn.disabled = false;
                }
            } catch (error) {
                formMessage.textContent = '❌ Lỗi kết nối. Vui lòng thử lại.';
                formMessage.className = 'form-message error';
                btn.textContent = 'Xác nhận Thanh toán';
                btn.disabled = false;
            }
        });
    </script>
</body>

</html>