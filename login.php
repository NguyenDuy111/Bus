<?php
require_once 'config.php';

// Nếu đã đăng nhập, chuyển về trang chủ
if (is_logged_in()) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập / Đăng ký - FUTA Bus Lines</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* HEADER MINI */
        .header-mini {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-mini {
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .back-home {
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            border: 2px solid white;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }

        .back-home:hover {
            background: white;
            color: #764ba2;
        }

        /* MAIN CONTAINER */
        .main-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .login-container {
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .container-header {
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }

        .container-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .container-header p {
            font-size: 14px;
            opacity: 0.95;
        }

        /* TABS */
        .tabs {
            display: flex;
            background: #f5f5f5;
            border-bottom: 3px solid #e0e0e0;
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 18px;
            cursor: pointer;
            font-weight: 700;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .tab:hover {
            background: #ebebeb;
        }

        .tab.active {
            color: #ff6b35;
            background: white;
            border-bottom-color: #ff6b35;
        }

        /* FORMS */
        .form-wrapper {
            padding: 35px 40px;
        }

        form {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        form.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .input-group label .required {
            color: #ff6b35;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 13px 15px;
            padding-left: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafafa;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #ff6b35;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }

        .input-wrapper input.error {
            border-color: #f44336;
            background: #ffebee;
        }

        .input-wrapper input.success {
            border-color: #4caf50;
            background: #f1f8f4;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #999;
        }

        .input-wrapper input:focus+.input-icon {
            color: #ff6b35;
        }

        .error-message {
            color: #f44336;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            color: #999;
            user-select: none;
        }

        .password-toggle:hover {
            color: #ff6b35;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .form-footer a {
            color: #ff6b35;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .form-footer a:hover {
            color: #ff8c5a;
            text-decoration: underline;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
            animation: slideDown 0.3s ease;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .form-wrapper {
                padding: 25px 25px;
            }
        }
    </style>
</head>

<body>
    <div class="header-mini">
        <a href="index.php" class="logo-mini">🚍 FUTA Bus Lines</a>
        <a href="index.php" class="back-home">← Quay lại</a>
    </div>

    <div class="main-container">
        <div class="login-container">
            <div class="container-header">
                <h1>🚍 FUTA Bus Lines</h1>
                <p>Chào mừng bạn đến với dịch vụ đặt vé trực tuyến</p>
            </div>

            <div class="tabs">
                <div class="tab active" id="login-tab">Đăng nhập</div>
                <div class="tab" id="register-tab">Đăng ký</div>
            </div>

            <div class="form-wrapper">
                <div class="alert alert-success" id="success-alert"></div>
                <div class="alert alert-error" id="error-alert"></div>

                <form id="login-form" class="active">
                    <div class="input-group">
                        <label>Số điện thoại <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="tel" id="login-phone" name="phone" placeholder="Nhập số điện thoại"
                                maxlength="10" required>
                            <span class="input-icon">📱</span>
                        </div>
                        <div class="error-message" id="login-phone-error"></div>
                    </div>

                    <div class="input-group">
                        <label>Mật khẩu <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="login-password" name="password" placeholder="Nhập mật khẩu"
                                required>
                            <span class="input-icon">🔒</span>
                            <span class="password-toggle" onclick="togglePassword('login-password')">👁️</span>
                        </div>
                        <div class="error-message" id="login-password-error"></div>
                    </div>

                    <button type="submit" class="submit-btn">Đăng nhập</button>

                    <div class="form-footer">
                        <a href="#" id="forgot-password">Quên mật khẩu?</a>
                    </div>
                </form>

                <form id="register-form">
                    <div class="input-group">
                        <label>Họ và tên <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="register-name" name="full_name" placeholder="Nhập họ và tên"
                                required>
                            <span class="input-icon">👤</span>
                        </div>
                        <div class="error-message" id="register-name-error"></div>
                    </div>

                    <div class="input-group">
                        <label>Số điện thoại <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="tel" id="register-phone" name="phone" placeholder="Nhập số điện thoại"
                                maxlength="10" required>
                            <span class="input-icon">📱</span>
                        </div>
                        <div class="error-message" id="register-phone-error"></div>
                    </div>

                    <div class="input-group">
                        <label>Email</label>
                        <div class="input-wrapper">
                            <input type="email" id="register-email" name="email" placeholder="email@example.com">
                            <span class="input-icon">✉️</span>
                        </div>
                        <div class="error-message" id="register-email-error"></div>
                    </div>

                    <div class="input-group">
                        <label>Mật khẩu <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="register-password" name="password"
                                placeholder="Tạo mật khẩu (tối thiểu 6 ký tự)" required>
                            <span class="input-icon">🔒</span>
                            <span class="password-toggle" onclick="togglePassword('register-password')">👁️</span>
                        </div>
                        <div class="error-message" id="register-password-error"></div>
                    </div>

                    <div class="input-group">
                        <label>Xác nhận mật khẩu <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="register-confirm" name="confirm_password"
                                placeholder="Nhập lại mật khẩu" required>
                            <span class="input-icon">🔐</span>
                            <span class="password-toggle" onclick="togglePassword('register-confirm')">👁️</span>
                        </div>
                        <div class="error-message" id="register-confirm-error"></div>
                    </div>

                    <button type="submit" class="submit-btn">Đăng ký</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        const loginTab = document.getElementById('login-tab');
        const registerTab = document.getElementById('register-tab');
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');

        loginTab.onclick = () => {
            loginTab.classList.add('active');
            registerTab.classList.remove('active');
            loginForm.classList.add('active');
            registerForm.classList.remove('active');
            hideAlerts();
        };

        registerTab.onclick = () => {
            registerTab.classList.add('active');
            loginTab.classList.remove('active');
            registerForm.classList.add('active');
            loginForm.classList.remove('active');
            hideAlerts();
        };

        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Show/hide alerts
        function showSuccess(message) {
            const alert = document.getElementById('success-alert');
            alert.textContent = message;
            alert.classList.add('show');
            setTimeout(() => alert.classList.remove('show'), 5000);
        }

        function showError(message) {
            const alert = document.getElementById('error-alert');
            alert.textContent = message;
            alert.classList.add('show');
            setTimeout(() => alert.classList.remove('show'), 5000);
        }

        function hideAlerts() {
            document.querySelectorAll('.alert').forEach(alert => alert.classList.remove('show'));
        }

        // ================== SỬA LỖI LOGIN FORM ONSUBMIT ==================
        loginForm.onsubmit = async (e) => {
            e.preventDefault();

            const submitBtn = e.target.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang đăng nhập...';

            const formData = new FormData(e.target);
            formData.append('action', 'login');

            const urlParams = new URLSearchParams(window.location.search);
            const redirectTo = urlParams.get('redirect_to');
            if (redirectTo) {
                // SỬA LỖI TẠI ĐÂY: Bỏ hàm decodeURIComponent
                formData.append('redirect_to', redirectTo);
            }

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.message);
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showError(data.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Đăng nhập';
                }
            } catch (error) {
                showError('Có lỗi xảy ra, vui lòng thử lại');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Đăng nhập';
            }
        };
        // ================== KẾT THÚC SỬA ==================

        // REGISTER FORM
        registerForm.onsubmit = async (e) => {
            e.preventDefault();

            const submitBtn = e.target.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang đăng ký...';

            const formData = new FormData(e.target);
            formData.append('action', 'register');

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.message);
                    registerForm.reset();
                    setTimeout(() => {
                        loginTab.click();
                        document.getElementById('login-phone').value = formData.get('phone');
                    }, 1500);
                } else {
                    showError(data.message);
                }
            } catch (error) {
                showError('Có lỗi xảy ra, vui lòng thử lại');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Đăng ký';
            }
        };

        // Forgot password
        document.getElementById('forgot-password').onclick = (e) => {
            e.preventDefault();
            const phone = prompt('📱 Nhập số điện thoại đã đăng ký:');
            if (phone) {
                alert('✅ Liên kết đặt lại mật khẩu đã được gửi đến số điện thoại của bạn!');
            }
        };
    </script>
</body>

</html>