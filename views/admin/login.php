<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Điện Máy Hiếu</title>
    <!-- CSS is simplified for brevity but maintains the core styling -->
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; color: #333; margin: 0; padding: 0; }
        .login-page { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 30px; width: 100%; max-width: 420px; }
        h1, h2, h3 { color: #d4a76e; }
        input[type=text], input[type=password] { width: 100%; padding: 12px; margin: 8px 0 16px; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; }
        button { background: #d4a76e; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #b58855; }
        .error { color: red; margin-bottom: 15px; text-align: center; }
        .legal-box { background: #fff9f0; border-left: 4px solid #d4a76e; padding: 15px; border-radius: 0 8px 8px 0; margin-top: 25px; }
        .legal-box h3 { margin-top: 0; color: #8b694f; font-size: 16px; }
        .legal-box ul { margin: 0; padding-left: 20px; font-size: 13px; }
    </style>
</head>
<body>

<div class="login-page">
    <div class="card">
        <h1 style="text-align: center;">🔐 Admin Điện Máy Hiếu</h1>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="/admin/index.php">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" required>
            <label>Mật khẩu</label>
            <input type="password" name="password" required>
            <button type="submit" name="login">Đăng nhập</button>
        </form>

        <div class="legal-box">
            <h3>📋 Thông tin pháp lý</h3>
            <ul>
                <li><strong>Chủ sở hữu:</strong> Vinh Tran</li>
                <li><strong>Tên cửa hàng:</strong> Điện Máy Hiếu</li>
                <li><strong>Địa chỉ:</strong> Khu vực Lấp Vò, Đồng Tháp</li>
                <li><strong>Pháp lý:</strong> Nghị định 52/2013/NĐ-CP & Thông tư 47/2014/TT-BCT</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>
