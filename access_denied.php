<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truy cập bị từ chối - Futa Bus Lines</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            padding: 50px 40px;
            border-radius: 15px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(-10deg);
            }

            75% {
                transform: rotate(10deg);
            }
        }

        h1 {
            color: #dc3545;
            margin-bottom: 15px;
            font-size: 28px;
        }

        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.8;
            font-size: 16px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-icon">🚫</div>
        <h1>Truy cập bị từ chối</h1>
        <p>
            Bạn không có quyền truy cập vào trang này.<br>
            Vui lòng liên hệ quản trị viên nếu bạn cho rằng đây là lỗi.
        </p>

        <div class="btn-group">
            <a href="index.php" class="btn btn-primary">Về trang chủ</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Quay lại</a>
        </div>

        <div class="info-box">
            💡 <strong>Gợi ý:</strong> Đăng nhập bằng tài khoản có quyền phù hợp để truy cập trang này.
        </div>
    </div>
</body>

</html>