<?php
require_once 'config.php'; // Đảm bảo file này có $conn và session_start()

// ===============================================
// KHỞI TẠO BIẾN
// ===============================================
$booking = null;
$message = '';
$message_type = ''; // 'success', 'error', 'info'
$is_eligible = false; // Đủ điều kiện hủy

// Giả định lấy từ DB/config
$MAX_CANCELLATION_DAYS_BEFORE = 3;

// =================================================================
// LOGIC HỦY VÉ (ĐÃ SỬA LỖI)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'confirm_cancel') {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $schedule_id = intval($_POST['schedule_id'] ?? 0);

    if ($booking_id > 0 && $schedule_id > 0) {

        // BẮT ĐẦU TRANSACTION ĐỂ ĐẢM BẢO DỮ LIỆU
        $conn->begin_transaction();

        try {
            // 1. LẤY SỐ VÉ (num_tickets) VÀ TRẠNG THÁI (status) CỦA VÉ NÀY
            $stmt_get = $conn->prepare("SELECT num_tickets, status FROM bookings WHERE id = ? FOR UPDATE");
            $stmt_get->bind_param("i", $booking_id);
            $stmt_get->execute();
            $result_get = $stmt_get->get_result();

            if ($result_get->num_rows == 0) {
                throw new Exception("Không tìm thấy vé (ID: $booking_id) để hủy.");
            }

            $booking_to_cancel = $result_get->fetch_assoc();
            $num_tickets_to_refund = intval($booking_to_cancel['num_tickets']); // Lấy số vé
            $current_status = $booking_to_cancel['status'];

            // 2. Kiểm tra trạng thái vé
            if ($current_status == 'cancelled') {
                throw new Exception("Vé này đã được hủy trước đó rồi.");
            }

            // Chỉ cho phép hủy vé 'pending' hoặc 'confirmed'
            if ($current_status != 'pending' && $current_status != 'confirmed') {
                throw new Exception("Không thể hủy vé với trạng thái '$current_status'.");
            }

            // Nếu số vé không hợp lệ (ví dụ = 0), đặt là 1 để tránh lỗi
            if ($num_tickets_to_refund <= 0) {
                $num_tickets_to_refund = 1;
            }

            // 3. Cập nhật trạng thái vé -> 'cancelled'
            $stmt_book = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $stmt_book->bind_param("i", $booking_id);
            $stmt_book->execute();

            // 4. Cộng lại ĐÚNG SỐ GHẾ (lấy từ $num_tickets_to_refund)
            $stmt_sched = $conn->prepare("UPDATE schedules SET available_seats = available_seats + ? WHERE id = ?");
            $stmt_sched->bind_param("ii", $num_tickets_to_refund, $schedule_id); // SỬA TỪ +1 THÀNH +?
            $stmt_sched->execute();

            // 5. Commit transaction
            $conn->commit();
            $message = '✅ Hủy vé thành công! Đã hoàn trả ' . $num_tickets_to_refund . ' ghế vào hệ thống.';
            $message_type = 'success';

            // Hủy thành công, ẩn nút Hủy đi
            $is_eligible = false;
            // Tải lại thông tin vé để hiển thị trạng thái "Đã hủy"
            // (Không bắt buộc, nhưng làm cho $booking['status'] cập nhật ngay)

        } catch (Exception $e) {
            // Có lỗi xảy ra, rollback
            $conn->rollback();
            $message = 'Lỗi! ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Thông tin hủy vé không hợp lệ.';
        $message_type = 'error';
    }
}
// =================================================================
// KẾT THÚC SỬA LỖI
// =================================================================


// ===============================================
// LOGIC TÌM VÉ (KHI USER NHẬP FORM)
// ===============================================
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['booking_code']) && isset($_GET['phone'])) {

    $booking_code = trim($_GET['booking_code']);
    $phone = trim($_GET['phone']);

    if (!empty($booking_code) && !empty($phone)) {
        // Truy vấn thông tin vé, join với lịch trình và tuyến đường
        $sql = "SELECT b.*, 
                       s.departure_time, s.arrival_time,
                       r.from_city, r.to_city
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                JOIN routes r ON s.route_id = r.id
                WHERE b.booking_code = ? AND b.passenger_phone = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $booking_code, $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();

        if ($booking) {
            // ĐÃ TÌM THẤY VÉ -> KIỂM TRA ĐIỀU KIỆN HỦY
            $departure_timestamp = strtotime($booking['departure_time']);
            $now_timestamp = time();

            // Tính thời hạn hủy (VD: 3 ngày trước giờ khởi hành)
            $cancellation_deadline = $departure_timestamp - ($MAX_CANCELLATION_DAYS_BEFORE * 86400); // 86400 giây = 1 ngày

            // Sửa logic: Nếu $message đã được set (ví dụ: "Hủy thành công" từ POST)
            // thì không ghi đè $message nữa
            if (empty($message)) {
                if ($booking['status'] == 'cancelled') {
                    $message = 'Thông tin vé: Vé này đã bị hủy trước đó.';
                    $message_type = 'info';
                    $is_eligible = false;
                } else if ($departure_timestamp <= $now_timestamp) {
                    $message = 'Không thể hủy: Chuyến xe này đã khởi hành.';
                    $message_type = 'error';
                    $is_eligible = false;
                } else if ($now_timestamp >= $cancellation_deadline) {
                    $message = 'Không thể hủy: Đã quá hạn hủy vé (phải hủy trước giờ khởi hành ' . $MAX_CANCELLATION_DAYS_BEFORE . ' ngày).';
                    $message_type = 'error';
                    $is_eligible = false;
                } else if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed') {
                    // Sửa: Chỉ cho hủy khi 'pending' hoặc 'confirmed'
                    $message = 'Đã tìm thấy vé. Bạn có thể hủy vé này.';
                    $message_type = 'success';
                    $is_eligible = true;
                } else {
                    $message = 'Không thể hủy vé với trạng thái: ' . $booking['status'];
                    $message_type = 'error';
                    $is_eligible = false;
                }
            } else {
                // Nếu $message đã có (từ POST), thì cập nhật lại $booking
                // để nó hiển thị trạng thái "Đã hủy"
                $booking['status'] = 'cancelled';
                $is_eligible = false;
            }
        } else {
            // KHÔNG TÌM THẤY VÉ
            $message = 'Không tìm thấy thông tin vé. Vui lòng kiểm tra lại Mã vé và SĐT.';
            $message_type = 'error';
        }
    } else if (isset($_GET['booking_code'])) {
        // Trường hợp user chỉ submit form rỗng
        $message = 'Vui lòng nhập Mã vé và Số điện thoại.';
        $message_type = 'error';
    }
}

// Lấy danh sách thành phố (để copy header/footer của index.php)
$stmt_cities = $conn->query("SELECT DISTINCT from_city FROM routes UNION SELECT DISTINCT to_city FROM routes ORDER BY from_city");
$cities = [];
while ($row = $stmt_cities->fetch_assoc()) {
    $cities[] = $row['from_city'];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tra cứu & Hủy vé - FUTA Bus Lines</title>
    <link rel="stylesheet" href="./css/index.css?v=1.1">

    <style>
        /* CSS cho form tra cứu và kết quả */
        .cancel-section {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .cancel-section h2 {
            text-align: center;
            font-size: 32px;
            margin-bottom: 30px;
            color: #333;
        }

        /* Form tra cứu */
        .cancel-form {
            display: grid;
            grid-template-columns: 1fr 1fr 150px;
            gap: 20px;
            align-items: end;
            margin-bottom: 30px;
        }

        /* Responsive cho form trên mobile */
        @media (max-width: 768px) {
            .cancel-form {
                grid-template-columns: 1fr;
                /* Chồng các input lên nhau */
            }
        }

        /* Nút "Tìm vé" (copy style từ .search-btn) */
        .btn-find-ticket {
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            border: none;
            padding: 14px 20px;
            /* Điều chỉnh padding cho khớp */
            border-radius: 10px;
            /* Khớp với input */
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
            text-transform: uppercase;
        }

        .btn-find-ticket:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
        }

        /* Vùng thông báo (Alert) */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Vùng chi tiết vé */
        .ticket-details {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 25px;
            background: #fdfdfd;
        }

        .ticket-details h3 {
            font-size: 24px;
            color: #ff6b35;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .ticket-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 30px;
            font-size: 16px;
        }

        .ticket-info div {
            line-height: 1.6;
        }

        .ticket-info div strong {
            display: block;
            color: #555;
            font-size: 14px;
            margin-bottom: 2px;
        }

        /* Nút Hủy vé (màu đỏ) */
        .btn-cancel-confirm {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-cancel-confirm:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-top">
            <div class="left">
                <span class="flag">🇻🇳</span> VI
                <button class="app-btn">📱 Tải ứng dụng</button>
            </div>
            <div class="right" id="auth-section">
                <?php if (is_logged_in()): ?>
                    <div class="user-info">
                        👤 <?php echo htmlspecialchars(get_user_info()['full_name']); ?>
                        <button class="logout-btn" onclick="logout()">Đăng xuất</button>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="login-btn">Đăng nhập / Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>

        <nav class="navbar">
            <div class="logo">🚍 FUTA Bus Lines</div>
            <ul class="nav-links">
                <li><a href="index.php">TRANG CHỦ</a></li>
                <li><a href="lichtrinh.php">LỊCH TRÌNH</a></li>
                <li><a href="cancel_ticket.php" class="active">TRA CỨU VÉ</a></li>
                <li><a href="#">TIN TỨC</a></li>
                <li><a href="#">LIÊN HỆ</a></li>
            </ul>
        </nav>
    </header>

    <section class="cancel-section">
        <h2>Tra cứu & Hủy vé</h2>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Vui lòng nhập Mã vé (Booking Code) và Số điện thoại đã dùng để đặt vé để tra cứu.
        </p>

        <form class="cancel-form" action="cancel_ticket.php" method="GET">
            <div class="form-group">
                <label for="booking_code">Mã vé (VD: FUTA12345)</label>
                <input type="text" id="booking_code" name="booking_code"
                    value="<?php echo htmlspecialchars($_GET['booking_code'] ?? ''); ?>"
                    placeholder="Nhập mã vé của bạn" required>
            </div>
            <div class="form-group">
                <label for="phone">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_GET['phone'] ?? ''); ?>"
                    placeholder="Nhập SĐT đặt vé" required>
            </div>
            <button type="submit" class="btn-find-ticket">Tìm vé</button>
        </form>

        <?php if ($message): ?>
            <div class="alert <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($booking): ?>
            <div class="ticket-details">
                <h3>Chi tiết vé: #<?= htmlspecialchars($booking['booking_code']) ?></h3>
                <div class="ticket-info">
                    <div>
                        <strong>Khách hàng</strong>
                        <?= htmlspecialchars($booking['passenger_name']) ?>
                    </div>
                    <div>
                        <strong>Số điện thoại</strong>
                        <?= htmlspecialchars($booking['passenger_phone']) ?>
                    </div>
                    <div>
                        <strong>Tuyến đường</strong>
                        <?= htmlspecialchars($booking['from_city']) ?> &rarr; <?= htmlspecialchars($booking['to_city']) ?>
                    </div>
                    <div>
                        <strong>Ghế ngồi</strong>
                        <?= htmlspecialchars($booking['seat_numbers']) ?> (Tổng:
                        <?= htmlspecialchars($booking['num_tickets']) ?> ghế)
                    </div>
                    <div>
                        <strong>Giờ khởi hành</strong>
                        <?= format_time($booking['departure_time']) ?> - <?= format_date($booking['departure_time']) ?>
                    </div>
                    <div>
                        <strong>Giờ đến (dự kiến)</strong>
                        <?= format_time($booking['arrival_time']) ?> - <?= format_date($booking['arrival_time']) ?>
                    </div>
                    <div>
                        <strong>Giá vé</strong>
                        <span class="price" style="font-size: 1em;"><?= format_currency($booking['total_price']) ?></span>
                    </div>
                    <div>
                        <strong>Trạng thái</strong>
                        <?php
                        $status_text = '';
                        $status_class = '';
                        switch ($booking['status']) {
                            case 'confirmed':
                                $status_text = '✅ Đã xác nhận';
                                $status_class = 'success';
                                break;
                            case 'cancelled':
                                $status_text = '❌ Đã hủy';
                                $status_class = 'error';
                                break;
                            case 'pending':
                                $status_text = '⏳ Đang chờ thanh toán';
                                $status_class = 'info';
                                break;
                            default:
                                $status_text = 'Đang chờ';
                                $status_class = 'info';
                        }
                        ?>
                        <span
                            style="font-weight: bold; color: <?= $status_class == 'success' ? '#28a745' : ($status_class == 'error' ? '#dc3545' : '#17a2b8') ?>">
                            <?= $status_text ?>
                        </span>
                    </div>
                </div>

                <?php if ($is_eligible): // Chỉ hiển thị nút khi đủ điều kiện 
                ?>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">
                    <p style="color: #555;">Vé của bạn đủ điều kiện để hủy. Vui lòng xác nhận bên dưới.</p>

                    <form
                        action="cancel_ticket.php?booking_code=<?= htmlspecialchars($booking['booking_code']) ?>&phone=<?= htmlspecialchars($booking['passenger_phone']) ?>"
                        method="POST">

                        <input type="hidden" name="action" value="confirm_cancel">
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                        <input type="hidden" name="schedule_id" value="<?= $booking['schedule_id'] ?>">

                        <button type="submit" class="btn-cancel-confirm"
                            onclick="return confirm('Bạn có chắc chắn muốn hủy vé này? Hành động này không thể hoàn tác.');">
                            ❌ Xác nhận hủy vé
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </section>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-column">
                <h3>TRUNG TÂM TỔNG ĐÀI</h3>
                <p class="hotline">1900 6067</p>
                <p><strong>CÔNG TY CỔ PHẦN XE KHÁCH PHƯƠNG TRANG</strong></p>
                <p>Địa chỉ: 486 Lê Văn Lương, Tân Hưng, TP.HCM</p>
                <p>Email: hotro@futa.vn</p>
            </div>
            <div class="footer-column">
                <h3>FUTA Bus Lines</h3>
                <a href="#">Về chúng tôi</a>
                <a href="lichtrinh.php">Lịch trình</a>
                <a href="#">Tuyển dụng</a>
                <a href="#">Tin tức</a>
            </div>
            <div class="footer-column">
                <h3>Hỗ trợ</h3>
                <a href="cancel_ticket.php">Tra cứu đặt vé</a>
                <a href="#">Điều khoản</a>
                <a href="#">Câu hỏi thường gặp</a>
                <a href="#">Hướng dẫn đặt vé</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 FUTA Bus Lines - Chất lượng là danh dự</p>
        </div>
    </footer>

    <script>
        // Đăng xuất (Giữ nguyên)
        async function logout() {
            if (!confirm('Bạn có chắc muốn đăng xuất?')) return;
            const formData = new FormData();
            formData.append('action', 'logout');

            // Giả sử bạn có file auth.php
            const response = await fetch('auth.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                // Sửa: Chuyển về login.php thay vì reload
                window.location.href = 'login.php';
            }
        }
    </script>
</body>

</html>