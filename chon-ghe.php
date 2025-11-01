<?php
require_once 'config.php';

// ================== SỬA LỖI REDIRECT ==================
if (!is_logged_in()) {
    // Mã hóa URL hiện tại để gửi qua GET
    $redirect_url = urlencode($_SERVER['REQUEST_URI']);
    redirect("login.php?error=require_login&redirect_to=$redirect_url");
}
// ================== KẾT THÚC SỬA ==================

// 2. Lấy ID lịch trình từ URL
$schedule_id = intval($_GET['schedule_id'] ?? 0);
if ($schedule_id === 0) {
    die("Lịch trình không hợp lệ.");
}

// 3. Lấy thông tin lịch trình và các ghế đã đặt
$schedule = null;
$booked_seats = [];

try {
    // Lấy thông tin chuyến đi
    $stmt = $conn->prepare("
        SELECT s.*, r.from_city, r.to_city
        FROM schedules s
        JOIN routes r ON s.route_id = r.id
        WHERE s.id = ? AND s.status = 'active'
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Không tìm thấy lịch trình này.");
    }
    $schedule = $result->fetch_assoc();

    // Lấy tất cả các ghế đã được đặt cho chuyến này (chưa bị hủy)
    $stmt = $conn->prepare("
        SELECT seat_numbers 
        FROM bookings 
        WHERE schedule_id = ? AND status != 'cancelled' AND seat_numbers IS NOT NULL
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $seats_in_booking = explode(',', $row['seat_numbers']);
        $booked_seats = array_merge($booked_seats, $seats_in_booking);
    }
} catch (Exception $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

// 4. Hàm helper để vẽ sơ đồ ghế (ĐÃ SỬA LỖI)
function renderSeatMap($total_seats, $bus_type, $booked_seats)
{
    echo '<div class="seat-map ' . $bus_type . '">';

    if ($bus_type == 'limousine' || $bus_type == 'vip') {
        $rows = ['A', 'B']; // Tầng dưới, Tầng trên

        // ==========================================================
        // SỬA Ở ĐÂY: Tự động tính số cột (số ghế mỗi tầng)
        // Thay vì gán cứng "$cols = 12;"
        // 16 ghế / 2 tầng = 8 cột (A1-A8, B1-B8)
        // 24 ghế / 2 tầng = 12 cột (A1-A12, B1-B12)
        // ==========================================================
        $cols = $total_seats / 2;

        foreach ($rows as $row) {
            echo '<div class="seat-row">';
            echo '<div class="row-label">Tầng ' . $row . '</div>';

            // Vòng lặp for bây giờ sẽ chạy $cols lần (8 hoặc 12)
            for ($i = 1; $i <= $cols; $i++) {
                $seat_id = $row . $i;
                $class = 'seat';
                if (in_array($seat_id, $booked_seats)) {
                    $class .= ' booked';
                } else {
                    $class .= ' available';
                }
                echo '<div class="' . $class . '" data-seat-id="' . $seat_id . '">' . $seat_id . '</div>';
            }
            echo '</div>';
        }
    } else {
        // Ví dụ cho xe 45 ghế (2 tầng) - Giữ nguyên
        $rows = ['A', 'B']; // Tầng dưới, Tầng trên
        $cols = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22]; // 22 ghế/tầng

        foreach ($rows as $row) {
            echo '<div class="seat-row">';
            echo '<div class="row-label">Tầng ' . $row . '</div>';
            foreach ($cols as $i) {
                $seat_id = $row . $i;
                $class = 'seat';
                if (in_array($seat_id, $booked_seats)) {
                    $class .= ' booked';
                } else {
                    $class .= ' available';
                }
                echo '<div class="' . $class . '" data-seat-id="' . $seat_id . '">' . $seat_id . '</div>';
            }
            echo '</div>';
        }
        echo '<div class="seat-row"><div class="row-label">Cuối xe</div>';
        for ($i = 43; $i <= 45; $i++) {
            $seat_id = 'C' . $i;
            $class = 'seat';
            if (in_array($seat_id, $booked_seats)) {
                $class .= ' booked';
            } else {
                $class .= ' available';
            }
            echo '<div class="' . $class . '" data-seat-id="' . $seat_id . '">' . $i . '</div>';
        }
        echo '</div>';
    }

    echo '</div>'; // end seat-map
}

$user_info = get_user_info();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn ghế - FUTA Bus Lines</title>
    <link rel="stylesheet" href="./css/booking-process.css">
</head>

<body>
    <div class="header-mini">
        <a href="index.php" class="logo-mini">🚍 FUTA Bus Lines</a>
        <a href="lichtrinh.php" class="back-home">← Quay lại</a>
    </div>

    <div class="main-container">
        <div class="booking-process-container">
            <div class="container-header">
                <h1>Hoàn tất đặt vé</h1>
                <p>Chọn ghế và điền thông tin của bạn</p>
            </div>

            <div class="form-wrapper">

                <div class="booking-steps">
                    <div class="step active">1. Chọn ghế</div>
                    <div class="step">2. Thanh toán</div>
                    <div class="step">3. Hoàn tất</div>
                </div>

                <form id="booking-form" action="booking.php" method="POST">
                    <input type="hidden" name="action" value="book_ticket">
                    <input type="hidden" name="schedule_id" value="<?= $schedule_id ?>">
                    <input type="hidden" name="price_per_ticket" id="price_per_ticket"
                        value="<?= $schedule['price'] ?>">
                    <input type="hidden" name="seat_numbers" id="seat_numbers">
                    <input type="hidden" name="num_tickets" id="num_tickets">
                    <input type="hidden" name="total_price" id="total_price">

                    <div class="content-wrapper">
                        <div class="seat-selection">
                            <h2>Chọn ghế</h2>
                            <div class="seat-info">
                                Tuyến: <strong><?= htmlspecialchars($schedule['from_city']) ?> →
                                    <?= htmlspecialchars($schedule['to_city']) ?></strong><br>
                                Giờ đi: <strong><?= format_time($schedule['departure_time']) ?> -
                                    <?= format_date($schedule['departure_time']) ?></strong>
                            </div>

                            <div class="seat-legend">
                                <div class="legend-item"><span class="seat available"></span> Trống</div>
                                <div class="legend-item"><span class="seat booked"></span> Đã đặt</div>
                                <div class="legend-item"><span class="seat selected"></span> Đang chọn</div>
                            </div>

                            <?php renderSeatMap($schedule['total_seats'], $schedule['bus_type'], $booked_seats); ?>
                        </div>

                        <div class="booking-summary">
                            <h2>Thông tin hành khách</h2>

                            <div class="input-group">
                                <label for="passenger_name">Họ tên <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <input type="text" id="passenger_name" name="passenger_name"
                                        value="<?= htmlspecialchars($user_info['full_name']) ?>" required>
                                    <span class="input-icon">👤</span>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="passenger_phone">Số điện thoại <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <input type="tel" id="passenger_phone" name="passenger_phone"
                                        value="<?= htmlspecialchars($user_info['phone']) ?>" required>
                                    <span class="input-icon">📱</span>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="passenger_email">Email</label>
                                <div class="input-wrapper">
                                    <input type="email" id="passenger_email" name="passenger_email"
                                        value="<?= htmlspecialchars($user_info['email'] ?? '') ?>">
                                    <span class="input-icon">✉️</span>
                                </div>
                            </div>

                            <div class="price-summary">
                                <h2>Tổng cộng</h2>
                                <div class="price-row">
                                    <span>Ghế đã chọn:</span>
                                    <strong id="selected-seats-list">Chưa chọn</strong>
                                </div>
                                <div class="price-row">
                                    <span>Số lượng vé:</span>
                                    <strong id="ticket-count">0</strong>
                                </div>
                                <div class="price-row total">
                                    <span>Tổng tiền:</span>
                                    <strong id="total-price-display">0đ</strong>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit" id="btn-submit">Tiếp tục</button>
                            <div id="form-message" class="form-message"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const seatMap = document.querySelector('.seat-map');
            const selectedSeatsList = document.getElementById('selected-seats-list');
            const ticketCount = document.getElementById('ticket-count');
            const totalPriceDisplay = document.getElementById('total-price-display');

            const hiddenSeatNumbers = document.getElementById('seat_numbers');
            const hiddenNumTickets = document.getElementById('num_tickets');
            const hiddenTotalPrice = document.getElementById('total_price');

            const pricePerTicket = parseFloat(document.getElementById('price_per_ticket').value);

            let selectedSeats = [];

            seatMap.addEventListener('click', function(e) {
                const seat = e.target;
                if (!seat.classList.contains('seat') || seat.classList.contains('booked')) {
                    return;
                }

                const seatId = seat.dataset.seatId;

                if (seat.classList.contains('selected')) {
                    seat.classList.remove('selected');
                    selectedSeats = selectedSeats.filter(s => s !== seatId);
                } else {
                    seat.classList.add('selected');
                    selectedSeats.push(seatId);
                }

                updateSummary();
            });

            function updateSummary() {
                const count = selectedSeats.length;
                const total = count * pricePerTicket;

                selectedSeatsList.textContent = count > 0 ? selectedSeats.join(', ') : 'Chưa chọn';
                ticketCount.textContent = count;
                totalPriceDisplay.textContent = new Intl.NumberFormat('vi-VN', {
                    style: 'currency',
                    currency: 'VND'
                }).format(total);

                hiddenSeatNumbers.value = selectedSeats.join(',');
                hiddenNumTickets.value = count;
                hiddenTotalPrice.value = total;
            }

            const form = document.getElementById('booking-form');
            const btnSubmit = document.getElementById('btn-submit');
            const formMessage = document.getElementById('form-message');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                formMessage.textContent = '';

                if (selectedSeats.length === 0) {
                    formMessage.textContent = '❌ Vui lòng chọn ít nhất 1 ghế.';
                    formMessage.className = 'form-message error';
                    return;
                }

                btnSubmit.textContent = 'Đang xử lý...';
                btnSubmit.disabled = true;

                try {
                    const formData = new FormData(form);
                    const response = await fetch('booking.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.href = `thanh-toan.php?booking_code=${data.data.booking_code}`;
                    } else {
                        formMessage.textContent = `❌ ${data.message}`;
                        formMessage.className = 'form-message error';
                        btnSubmit.textContent = 'Tiếp tục';
                        btnSubmit.disabled = false;
                    }
                } catch (error) {
                    formMessage.textContent = '❌ Lỗi kết nối. Vui lòng thử lại.';
                    formMessage.className = 'form-message error';
                    btnSubmit.textContent = 'Tiếp tục';
                    btnSubmit.disabled = false;
                }
            });
        });
    </script>
</body>

</html>