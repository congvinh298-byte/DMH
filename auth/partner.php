<?php
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Permissions-Policy: geolocation=(self)');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đăng nhập Thợ - Điện Máy Hiếu</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .icon-wrap { background: #7c3aed; color: white; border-radius: 50%; width: 60px; height: 60px; line-height: 60px; font-size: 30px; margin: 0 auto 16px; }
        h1 { margin: 0 0 8px; font-size: 22px; color: #111827; }
        p.subtitle { color: #6b7280; font-size: 14px; margin-bottom: 24px; }
        input { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 16px; font-size: 16px; box-sizing: border-box; }
        input#workerPinInput { font-size: 24px; letter-spacing: 8px; text-align: center; font-weight: bold; }
        .btn { width: 100%; background: #7c3aed; color: white; padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .btn:disabled { background: #9ca3af; cursor: not-allowed; }
        .error { color: #dc2626; margin-top: 12px; font-size: 14px; display: none; }
        .link { display: block; margin-top: 16px; color: #6b7280; text-decoration: none; font-size: 14px; }
        .link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="icon-wrap">🛠️</div>
        <h1>Đăng nhập Thợ</h1>
        <p class="subtitle">Hệ thống Điều phối Nội bộ</p>
        
        <div style="text-align: left; margin-bottom: 4px; font-size: 14px; font-weight: bold; color: #374151;">Số điện thoại / Mã thợ</div>
        <input type="tel" id="workerPhoneInput" placeholder="VD: 0979553289 hoặc DTH-001">
        
        <div style="text-align: left; margin-bottom: 4px; font-size: 14px; font-weight: bold; color: #374151;">PIN (4-6 chữ số)</div>
        <input type="password" id="workerPinInput" placeholder="Nhập PIN" maxlength="6" inputmode="numeric">
        
        <button id="workerLoginSubmitBtn" class="btn" onclick="requestGpsAndLogin()">🚀 Đăng nhập & Sẵn sàng</button>
        <div id="workerLoginError" class="error"></div>
        <a href="../index.php" class="link">← Quay lại trang chủ</a>
    </div>

    <script>
        function requestGpsAndLogin() {
            const err = document.getElementById('workerLoginError');
            const btn = document.getElementById('workerLoginSubmitBtn');
            err.style.display = 'none';

            if (!navigator.geolocation) {
                err.textContent = 'Trình duyệt của bạn không hỗ trợ định vị GPS.';
                err.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = '📍 Đang lấy vị trí GPS...';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    executeLogin(lat, lng);
                },
                (error) => {
                    btn.disabled = false;
                    btn.textContent = '🚀 Đăng nhập & Sẵn sàng';
                    err.textContent = 'Bắt buộc phải cấp quyền Vị trí (GPS) để nhận đơn. Vui lòng tải lại trang và chọn Cho phép.';
                    err.style.display = 'block';
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        function executeLogin(lat, lng) {
            const phone = document.getElementById('workerPhoneInput').value.trim();
            const pin   = document.getElementById('workerPinInput').value.trim();
            const err   = document.getElementById('workerLoginError');
            const btn   = document.getElementById('workerLoginSubmitBtn');

            if (!phone || (!pin || pin.length < 4)) {
                err.textContent = 'Vui lòng nhập số điện thoại và PIN hợp lệ.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.textContent = '🚀 Đăng nhập & Sẵn sàng';
                return;
            }

            btn.textContent = 'Đang xác thực...';

            const payload = phone.startsWith('DTH') || phone.startsWith('dth')
                ? { action: 'mobile_worker_login', worker_code: phone, pin }
                : { action: 'mobile_worker_login_by_phone', phone, pin };

            fetch('../api_master.php?' + new URLSearchParams({ action: payload.action }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(r => r.json())
            .then(d => {
                if (d.status !== 'success') {
                    throw new Error(d.message || 'Đăng nhập thất bại.');
                }
                
                const worker = d.worker || {};
                const loginData = {
                    type:         'worker',
                    worker_id:    worker.worker_id || d.worker_id || 0,
                    name:         worker.name || 'Thợ',
                    phone:        worker.phone || phone,
                    worker_code:  worker.worker_code || '',
                    role:         worker.role || 'worker',
                    is_admin:     worker.is_admin || 0,
                    shift_status: d.shift_status || 'off',
                    token:        d.token || '',
                };
                localStorage.setItem('dth_worker_token', loginData.token);
                localStorage.setItem('dth_worker_id',    String(loginData.worker_id));
                localStorage.setItem('dth_worker_data',  JSON.stringify(loginData));
                localStorage.setItem('dth_worker_time',  Date.now());

                btn.textContent = 'Đang kích hoạt ca...';
                return fetch('../api_master.php?action=mobile_worker_shift_start', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + loginData.token },
                    body: JSON.stringify({ token: loginData.token, lat: lat, lng: lng }),
                });
            })
            .then(() => {
                window.location.href = '../index.php';
            })
            .catch(e => {
                btn.disabled = false;
                btn.textContent = '🚀 Đăng nhập & Sẵn sàng';
                err.textContent = e.message || 'Lỗi kết nối. Vui lòng thử lại.';
                err.style.display = 'block';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('dth_worker_token')) {
                window.location.href = '../index.php';
            }
        });
    </script>
</body>
</html>
