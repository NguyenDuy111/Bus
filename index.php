<?php
require_once 'config.php';

// Lấy danh sách thành phố cho dropdowns
$stmt = $conn->query("SELECT DISTINCT from_city FROM routes UNION SELECT DISTINCT to_city FROM routes ORDER BY from_city");
$cities = [];
while ($row = $stmt->fetch_assoc()) {
    $cities[] = $row['from_city'];
}

// ===============================================
// LOGIC TÌM KIẾM
// ===============================================
$schedules = [];
$from_city = '';
$to_city = '';
$departure_date = '';

// Kiểm tra xem form đã được gửi đi chưa (method GET)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['from_city'])) {

    // Lấy giá trị tìm kiếm
    $from_city = $_GET['from_city'] ?? '';
    $to_city = $_GET['to_city'] ?? '';
    $departure_date = $_GET['departure_date'] ?? '';

    // Chỉ tìm kiếm nếu có đủ 3 thông tin
    if (!empty($from_city) && !empty($to_city) && !empty($departure_date)) {
        $sql = "SELECT s.*, r.from_city, r.to_city 
                FROM schedules s
                JOIN routes r ON s.route_id = r.id
                WHERE r.from_city = ? 
                  AND r.to_city = ? 
                  AND DATE(s.departure_time) = ?
                ORDER BY s.departure_time ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $from_city, $to_city, $departure_date);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $schedules[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTA Bus Lines - Vững tin & Phát triển</title>

    <link rel="stylesheet" href="./css/index.css?v=1.0">

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
                <li><a href="index.php" class="active">TRANG CHỦ</a></li>
                <li><a href="lichtrinh.php">LỊCH TRÌNH</a></li>
                <li><a href="cancel_ticket.php">TRA CỨU VÉ</a></li>
                <li><a href="#">TIN TỨC</a></li>
                <li><a href="#">LIÊN HỆ</a></li>
            </ul>
        </nav>
    </header>

    <section class="banner">
        <div class="banner-content">
            <h2>24 Năm VỮNG TIN & PHÁT TRIỂN</h2>
            <p>Hành trình an toàn - Trải nghiệm đẳng cấp</p>
            <img src="https://futa.vn/assets/images/xe-futa.png" alt="FUTA Bus">
        </div>
    </section>

    <section class="search-section">
        <div class="search-box">
            <form id="search-form" action="index.php" method="GET">
                <div class="trip-type">
                    <label>
                        <input type="radio" name="trip" value="oneway" checked> Một chiều
                    </label>
                    <label>
                        <input type="radio" name="trip" value="roundtrip"> Khứ hồi
                    </label>
                </div>

                <div class="form-fields">
                    <div class="form-group">
                        <label>Điểm đi</label>
                        <select id="from" name="from_city" required>
                            <option value="">-- Chọn điểm đi --</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>"
                                    <?php echo ($city == $from_city) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" class="swap-btn" id="swap-btn">⇄</button>

                    <div class="form-group">
                        <label>Điểm đến</label>
                        <select id="to" name="to_city" required>
                            <option value="">-- Chọn điểm đến --</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>"
                                    <?php echo ($city == $to_city) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ngày đi</label>
                        <input type="date" id="date" name="departure_date"
                            value="<?php echo htmlspecialchars($departure_date); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Số vé</label>
                        <select id="tickets" name="tickets">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>"
                                    <?php echo (isset($_GET['tickets']) && $_GET['tickets'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <center>
                    <button type="submit" class="search-btn">Tìm chuyến xe</button>
                </center>
            </form>
        </div>
    </section>

    <?php if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['from_city'])): ?>
        <section class="results-section">
            <h2>Kết quả tìm kiếm</h2>
            <div class="search-summary">
                Tuyến từ <strong><?php echo htmlspecialchars($from_city); ?></strong>
                &rarr; <strong><?php echo htmlspecialchars($to_city); ?></strong>
                ngày <strong><?php echo format_date($departure_date); ?></strong>
            </div>

            <?php if (!empty($schedules)): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Giờ đi</th>
                            <th>Giờ đến</th>
                            <th>Loại xe</th>
                            <th>Giá vé</th>
                            <th>Số ghế trống</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td>
                                    <strong><?php echo format_time($schedule['departure_time']); ?></strong>
                                    <br>(<?php echo format_date($schedule['departure_time']); ?>)
                                </td>
                                <td>
                                    <strong><?php echo format_time($schedule['arrival_time']); ?></strong>
                                    <br>(<?php echo format_date($schedule['arrival_time']); ?>)
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($schedule['bus_type'])); ?></td>
                                <td class="price"><?php echo format_currency($schedule['price']); ?></td>
                                <td><?php echo $schedule['available_seats']; ?></td>
                                <td>
                                    <a href="chon-ghe.php?schedule_id=<?php echo $schedule['id']; ?>" class="select-btn">Chọn vé</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-results">😢 Rất tiếc, không tìm thấy chuyến xe nào phù hợp với yêu cầu của bạn.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <section class="features">
        <div class="features-container">
            <h2>Vì sao chọn FUTA Bus Lines?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>An toàn tuyệt đối</h3>
                    <p>Đội ngũ lái xe chuyên nghiệp, xe được bảo dưỡng định kỳ</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⭐</div>
                    <h3>Dịch vụ 5 sao</h3>
                    <p>Ghế ngồi êm ái, WiFi miễn phí, nước uống phục vụ suốt chuyến</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3>Giá cả hợp lý</h3>
                    <p>Nhiều chương trình khuyến mãi, ưu đãi cho khách hàng thân thiết</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🕐</div>
                    <h3>Đúng giờ</h3>
                    <p>Cam kết xuất bến đúng giờ, tối ưu thời gian di chuyển</p>
                </div>
            </div>
        </div>
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
                <a href="#">Tra cứu đặt vé</a>
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
        // Set ngày tối thiểu là hôm nay
        const dateInput = document.getElementById('date');
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
        // Chỉ gán 'today' nếu giá trị ngày đi chưa được set (khi đang xem lại kết quả)
        if (!dateInput.value) {
            dateInput.value = today;
        }

        // Nút hoán đổi (Giữ nguyên)
        document.getElementById('swap-btn').addEventListener('click', function() {
            const from = document.getElementById('from');
            const to = document.getElementById('to');
            const temp = from.value;
            from.value = to.value;
            to.value = temp;
        });

        // Submit form (Giữ nguyên)
        document.getElementById('search-form').addEventListener('submit', function(e) {
            const from = document.getElementById('from').value;
            const to = document.getElementById('to').value;

            if (!from || !to) {
                e.preventDefault(); // Ngăn form gửi đi
                alert('⚠️ Vui lòng chọn điểm đi và điểm đến!');
                return;
            }

            if (from === to) {
                e.preventDefault(); // Ngăn form gửi đi
                alert('⚠️ Điểm đi và điểm đến không thể giống nhau!');
                return;
            }
        });

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
                window.location.reload();
            }
        }
    </script>
</body>

</html>