<?php
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đăng nhập Khách - Điện Máy Hiếu</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .logo { width: 70px; height: 70px; border-radius: 16px; margin-bottom: 15px; object-fit: contain; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h1 { margin: 0 0 8px; font-size: 24px; color: #111827; }
        p.subtitle { color: #6b7280; font-size: 14px; margin-bottom: 24px; }
        input { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 16px; font-size: 16px; box-sizing: border-box; }
        .btn { width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; margin-bottom: 12px; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-red { background: #dc2626; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2); }
        .btn-blue { background: #2563eb; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        .btn-green { background: #10b981; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2); }
        .btn-ghost { background: transparent; color: #6b7280; margin-top: 8px; }
        .error { color: #dc2626; margin-top: 12px; font-size: 14px; display: none; }
        .form-panel { display: none; text-align: left; }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="/assets/images/logo.png" alt="Logo" class="logo" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'><rect width=\'100%\' height=\'100%\' fill=\'%23ea580c\'/><text x=\'50%\' y=\'55%\' font-family=\'sans-serif\' font-size=\'16\' fill=\'white\' font-weight=\'bold\' text-anchor=\'middle\'>DTH</text></svg>'">
        <h1 style="font-weight: 900;">Đăng Nhập <br><span style="color: #dc2626; font-size: 20px;">Điện Máy Hiếu</span></h1>
        
        <div id="loginMethods">
            <p class="subtitle">Chào mừng bạn quay lại, vui lòng chọn phương thức đăng nhập.</p>
            <button class="btn btn-red" onclick="showForm('phone')">📱 Tiếp tục với Số điện thoại</button>
            <button class="btn btn-blue" onclick="showForm('email')">✉️ Tiếp tục với Email & Mật khẩu</button>
            <button class="btn btn-green" onclick="showForm('qr')">🪪 Đăng nhập bằng Mã Thẻ / QR</button>
            <a href="partner.php" class="btn" style="background: #7c3aed; text-decoration: none;">🛠️ Đăng nhập Thợ (Nội bộ)</a>
            <a href="../index.php" class="btn btn-ghost" style="text-decoration:none;">← Quay lại trang chủ</a>
        </div>

        <div id="phoneForm" class="form-panel">
            <h2 style="font-size: 18px; text-align:center;">Đăng nhập Số điện thoại</h2>
            <div style="font-weight:bold; font-size: 13px; margin-bottom: 4px;">Họ và Tên</div>
            <input type="text" id="loginNameInput" placeholder="VD: Nguyễn Văn A">
            <div style="font-weight:bold; font-size: 13px; margin-bottom: 4px;">Số điện thoại</div>
            <input type="tel" id="loginPhoneInput" placeholder="VD: 0979553289">
            <button class="btn btn-red" id="phoneSubmitBtn" onclick="submitPhoneLogin()">Đăng nhập / Đăng ký</button>
            <div id="phoneError" class="error"></div>
            <button class="btn btn-ghost" onclick="showMethods()">Quay lại</button>
        </div>

        <div id="qrForm" class="form-panel">
            <h2 style="font-size: 18px; text-align:center;">Đăng nhập Mã Thẻ</h2>
            <input type="text" id="loginKeyInput" placeholder="Nhập mã thẻ hoặc token">
            <button class="btn btn-green" id="qrSubmitBtn" onclick="submitQrLogin()">Xác nhận Đăng nhập</button>
            <div id="qrError" class="error"></div>
            <button class="btn btn-ghost" onclick="showMethods()">Quay lại</button>
        </div>

        <div id="emailForm" class="form-panel">
            <h2 style="font-size: 18px; text-align:center;">Đăng nhập Email</h2>
            <div style="font-weight:bold; font-size: 13px; margin-bottom: 4px;">Email</div>
            <input type="email" id="loginEmailInput" placeholder="VD: email@example.com">
            <div style="font-weight:bold; font-size: 13px; margin-bottom: 4px;">Mật khẩu</div>
            <input type="password" id="loginPasswordInput" placeholder="Mật khẩu">
            <button class="btn btn-blue" id="emailSubmitBtn" onclick="submitEmailLogin()">Đăng nhập</button>
            <div id="emailError" class="error"></div>
            <button class="btn btn-ghost" onclick="showMethods()">Quay lại</button>
        </div>
    </div>

    <script>
        function showMethods() {
            document.querySelectorAll('.form-panel').forEach(el => {
                if (el) el.style.display = 'none';
            });
            const loginMethods = document.getElementById('loginMethods');
            if (loginMethods) loginMethods.style.display = 'block';
        }

        function showForm(type) {
            const loginMethods = document.getElementById('loginMethods');
            if (loginMethods) loginMethods.style.display = 'none';
            const form = document.getElementById(type + 'Form');
            if (form) form.style.display = 'block';
        }

        function submitPhoneLogin() {
            const phone = (document.getElementById('loginPhoneInput') ? document.getElementById('loginPhoneInput').value : '').trim();
            const name = (document.getElementById('loginNameInput') ? document.getElementById('loginNameInput').value : '').trim();
            const err = document.getElementById('phoneError');
            const btn = document.getElementById('phoneSubmitBtn');

            if (!phone) { err.textContent = 'Vui lòng nhập số điện thoại.'; err.style.display = 'block'; return; }
            err.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';

            fetch('../api_master.php?action=mobile_login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone, fullname: name })
            }).then(r => r.json()).then(d => {
                if (d.status === 'success') {
                    localStorage.setItem('dth_user_key', d.login_key || d.token);
                    localStorage.setItem('dth_user_time', Date.now());
                    
                    // Offline fallback: save data
                    if (d.user) {
                        localStorage.setItem('dth_user_data', JSON.stringify({
                            id: d.user.id, name: d.user.name, role: d.user.role
                        }));
                    }
                    window.location.href = '../index.php';
                } else {
                    err.textContent = d.message || 'Đăng nhập thất bại';
                    err.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Đăng nhập / Đăng ký';
                }
            }).catch(e => {
                err.textContent = 'Lỗi kết nối';
                err.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Đăng nhập / Đăng ký';
            });
        }

        function submitQrLogin() {
            const key = (document.getElementById('loginKeyInput') ? document.getElementById('loginKeyInput').value : '').trim();
            const err = document.getElementById('qrError');
            const btn = document.getElementById('qrSubmitBtn');

            if (!key) { err.textContent = 'Vui lòng nhập mã.'; err.style.display = 'block'; return; }
            err.style.display = 'none';
            btn.disabled = true;

            fetch('../api_master.php?action=verify_login_key', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ login_key: key })
            }).then(r => r.json()).then(d => {
                if (d.status === 'success' && d.data.type === 'user') {
                    localStorage.setItem('dth_user_key', key);
                    localStorage.setItem('dth_user_time', Date.now());
                    localStorage.setItem('dth_user_data', JSON.stringify(d.data));
                    window.location.href = '../index.php';
                } else {
                    err.textContent = 'Mã không hợp lệ hoặc không phải tài khoản khách.';
                    err.style.display = 'block';
                    btn.disabled = false;
                }
            }).catch(e => {
                err.textContent = 'Lỗi kết nối';
                err.style.display = 'block';
                btn.disabled = false;
            });
        }
        
        function submitEmailLogin() {
            // Placeholder cho email login
            const err = document.getElementById('emailError');
            err.textContent = 'Tính năng đăng nhập email đang bảo trì.';
            err.style.display = 'block';
        }

        // Auto-Handshake Local
        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('dth_user_key')) {
                window.location.href = '../index.php';
            }
        });
    </script>
</body>
</html>
