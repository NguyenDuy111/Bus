<?php
require_once 'config.php';

// BỔ SUNG LẠI PHẦN NÀY: Lấy full_name an toàn
$full_name = '';
if (is_logged_in()) {
    // Giả sử hàm get_user_info() trả về một mảng chứa thông tin user
    $user = get_user_info();
    $full_name = $user['full_name'] ?? ''; // Lấy full_name từ mảng
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch trình - FUTA Bus Lines</title>
    <link rel="stylesheet" href="./css/lichtrinh.css">
    <style>

    </style>
</head>

<body>
    <header class="header">
        <div class="header-top">
            <div class="left">
                <span class="flag">🇻🇳</span> VI
                <button class="app-btn">📱 Tải ứng dụng</button>
            </div>
            <div class="right">
                <?php if (is_logged_in()): ?>
                <div class="user-info">
                    👤 <?php echo htmlspecialchars($full_name); ?>
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
                <li><a href="lichtrinh.php" class="active">LỊCH TRÌNH</a></li>
                <li><a href="cancel_ticket.php">TRA CỨU VÉ</a></li>
                <li><a href="#">TIN TỨC</a></li>
                <li><a href="#">LIÊN HỆ</a></li>
            </ul>
        </nav>
    </header>

    <section class="search-section">
        <div class="search-container">
            <div class="search-box">
                <input type="text" id="search-route" placeholder="🔍 Tìm kiếm tuyến đường (VD: Hồ Chí Minh - Cần Thơ)">
                <button id="find-route">🔍</button>
            </div>
            <div class="filter-tags">
                <div class="filter-tag active" data-filter="all">Tất cả</div>
                <div class="filter-tag" data-filter="vip">Giường nằm VIP</div>
                <div class="filter-tag" data-filter="limousine">Limousine</div>
                <div class="filter-tag" data-filter="standard">Ghế ngồi</div>
            </div>
        </div>
    </section>

    <section class="table-section">
        <div class="results-header">
            <div class="results-count">
                Tìm thấy <strong id="results-count">0</strong> chuyến xe
            </div>
            <div class="sort-options">
                <label>Sắp xếp:</label>
                <select id="sort-select">
                    <option value="time">Giờ khởi hành</option>
                    <option value="price-asc">Giá tăng dần</option>
                    <option value="price-desc">Giá giảm dần</option>
                    <option value="duration">Thời gian</option>
                </select>
            </div>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Đang tải lịch trình...</p>
        </div>

        <div class="schedule-list" id="schedule-list">
        </div>

        <div class="no-results" id="no-results">
            <div class="no-results-icon">🚌</div>
            <h3>Không tìm thấy chuyến xe phù hợp</h3>
            <p>Vui lòng thử tìm kiếm với từ khóa khác</p>
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
    let allSchedules = [];
    let filteredSchedules = [];
    let currentFilter = 'all';

    window.addEventListener('DOMContentLoaded', loadSchedules);

    async function loadSchedules() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_all_schedules');

            const response = await fetch('booking.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                allSchedules = data.data;
                filteredSchedules = [...allSchedules];
                renderSchedules();
            } else {
                showError(data.message);
            }
        } catch (error) {
            showError('Có lỗi xảy ra khi tải dữ liệu');
        } finally {
            document.getElementById('loading').style.display = 'none';
        }
    }

    function renderSchedules() {
        const scheduleList = document.getElementById('schedule-list');
        const noResults = document.getElementById('no-results');
        const resultsCount = document.getElementById('results-count');

        resultsCount.textContent = filteredSchedules.length;

        if (filteredSchedules.length === 0) {
            scheduleList.innerHTML = '';
            noResults.style.display = 'block';
            return;
        }

        noResults.style.display = 'none';

        // BỔ SUNG LẠI: Thêm ( ${trip.departure_date} ) vào sau ${trip.time}
        // Dữ liệu này được cung cấp bởi booking.php
        scheduleList.innerHTML = filteredSchedules.map(trip => `
        <div class="schedule-card">
          <div class="card-header">
            <div class="route-info">
              <div class="route-city">${trip.from}</div>
              <div class="route-arrow">→</div>
              <div class="route-city">${trip.to}</div>
            </div>
            <div class="bus-type">${trip.type}</div>
          </div>
          <div class="card-body">
            <div class="info-item">
              <div class="info-label">Giờ khởi hành</div>
              <div class="info-value">⏰ ${trip.time} (${trip.departure_date})</div>
            </div>
            <div class="info-item">
              <div class="info-label">Giờ đến dự kiến</div>
              <div class="info-value">🏁 ${trip.arrival_time}</div>
            </div>
            <div class="info-item">
              <div class="info-label">Thời gian</div>
              <div class="info-value">⏱️ ${trip.duration}</div>
            </div>
            <div class="info-item">
              <div class="info-label">Giá vé</div>
              <div class="info-value price">${trip.price_formatted}</div>
            </div>
          </div>
          <div class="card-footer">
            <div class="seats-left">
              Còn <span class="seats-number">${trip.seats} chỗ</span>
            </div>
            <button class="book-btn" onclick="bookTicket(${trip.id})">
              Chọn ghế
            </button>
          </div>
        </div>
        `).join('');
    }

    document.getElementById('find-route').addEventListener('click', searchSchedules);
    document.getElementById('search-route').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchSchedules();
    });

    async function searchSchedules() {
        const keyword = document.getElementById('search-route').value.trim();

        const formData = new FormData();
        formData.append('action', 'get_all_schedules');
        formData.append('keyword', keyword);
        formData.append('bus_type', currentFilter);
        formData.append('sort_by', document.getElementById('sort-select').value);

        document.getElementById('loading').style.display = 'block';

        try {
            const response = await fetch('booking.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                filteredSchedules = data.data;
                renderSchedules();
            }
        } catch (error) {
            showError('Có lỗi xảy ra');
        } finally {
            document.getElementById('loading').style.display = 'none';
        }
    }

    document.querySelectorAll('.filter-tag').forEach(tag => {
        tag.addEventListener('click', function() {
            document.querySelectorAll('.filter-tag').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            searchSchedules();
        });
    });

    document.getElementById('sort-select').addEventListener('change', searchSchedules);

    function bookTicket(scheduleId) {
        <?php if (!is_logged_in()): ?>
        // Nếu chưa đăng nhập, yêu cầu đăng nhập
        alert('⚠️ Vui lòng đăng nhập để đặt vé!');

        // TẠO URL ĐỂ QUAY LẠI ĐÚNG TRANG CHỌN GHẾ
        const redirectUrl = encodeURIComponent(`chon-ghe.php?schedule_id=${scheduleId}`);
        window.location.href = `login.php?redirect_to=${redirectUrl}`;
        <?php else: ?>
        // Nếu đã đăng nhập, chuyển đến trang chọn ghế
        window.location.href = `chon-ghe.php?schedule_id=${scheduleId}`;
        <?php endif; ?>
    }

    // ===============================================
    // HÀM LOGOUT (ĐÃ SỬA CHUYỂN VỀ login.php)
    // ===============================================
    async function logout() {
        if (!confirm('Bạn có chắc muốn đăng xuất?')) return;

        const formData = new FormData();
        formData.append('action', 'logout');

        try {
            const response = await fetch('auth.php', { // Giả sử file xử lý logout là auth.php
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                // SỬA Ở ĐÂY: Chuyển về trang login.php thay vì reload
                window.location.href = 'login.php';
            } else {
                showError(data.message || 'Lỗi khi đăng xuất');
            }
        } catch (error) {
            showError('Lỗi kết nối khi đăng xuất.');
        }
    }

    function showError(message) {
        alert('❌ ' + message);
    }
    </script>
</body>

</html>