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
        // WORKER AUTH & DASHBOARD JS
// ============================================================



function openWorkerDashboard(data) {
    // Đóng modal login
    const modal = document.getElementById('modalLogin');
    if (modal) modal.style.display = 'none';

    // Cập nhật top bar
    const bar = document.getElementById('topBarStatus');
    if (bar) {
        bar.innerHTML = `
            <span style="background:#7c3aed;color:white;padding:3px 10px;border-radius:20px;font-size:13px;font-weight:bold;display:flex;align-items:center;gap:6px;">
                🛠️ <b>${data.name || 'Thợ'}</b>
                <span id="workerShiftBadge" style="background:${data.shift_status==='on_shift'?'#10b981':'#6b7280'};border-radius:20px;padding:2px 8px;font-size:11px;">
                    ${data.shift_status==='on_shift'?'🟢 Sẵn sàng':'⚫ Offline'}
                </span>
            </span>
            <a href="javascript:void(0)" onclick="openWorkerPanel()" style="color:#fbbf24;text-decoration:underline;font-weight:bold;font-size:13px;margin-left:8px;">Bảng điều khiển</a>
            <a href="javascript:void(0)" onclick="logoutWorker()" style="color:#fca5a5;text-decoration:underline;font-size:13px;margin-left:8px;">Đăng xuất</a>
        `;
    }

    // Mở Worker Panel
    openWorkerPanel();
}

function logoutWorker() {
    localStorage.removeItem('dth_worker_token');
    localStorage.removeItem('dth_worker_id');
    localStorage.removeItem('dth_worker_data');
    localStorage.removeItem('dth_worker_time');
    window.location.reload();
}

function openWorkerPanel() {
    let panel = document.getElementById('workerDashboardPanel');
    if (!panel) {
        panel = document.createElement('div');
        panel.id = 'workerDashboardPanel';
        panel.style.cssText = `
            position: fixed; top: 0; right: 0; bottom: 0; width: min(420px, 100vw);
            background: #0f172a; color: white; z-index: 9999;
            box-shadow: -8px 0 32px rgba(0,0,0,0.5); overflow-y: auto;
            font-family: -apple-system, system-ui, sans-serif; transition: transform 0.3s ease;
        `;
        document.body.appendChild(panel);
    }
    panel.style.display = 'block';
    loadWorkerDashboard(panel);
}

function closeWorkerPanel() {
    const p = document.getElementById('workerDashboardPanel');
    if (p) p.style.display = 'none';
}

function loadWorkerDashboard(panel) {
    const token = localStorage.getItem('dth_worker_token') || '';
    const wdata = JSON.parse(localStorage.getItem('dth_worker_data') || '{}');

    panel.innerHTML = `
        <div style="background: linear-gradient(135deg,#7c3aed,#4c1d95); padding: 20px; position: sticky; top: 0; z-index: 1;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:20px;font-weight:900;">🛠️ Bảng Điều Khiển Thợ</div>
                    <div style="font-size:13px;opacity:0.8;margin-top:2px;">Điện Máy Hiếu — Nội bộ</div>
                </div>
                <button onclick="closeWorkerPanel()" style="background:rgba(255,255,255,0.15);border:none;color:white;border-radius:50%;width:36px;height:36px;font-size:20px;cursor:pointer;">✕</button>
            </div>
        </div>
        <div style="padding:16px;" id="workerPanelBody">
            <div style="text-align:center;padding:40px 0;opacity:0.5;">Đang tải...</div>
        </div>
    `;

    if (!token) {
        { const _el = document.getElementById('workerPanelBody'); if(_el) _el.innerHTML = `
            <div style="text-align:center; padding:40px 16px;color:#fca5a5;">
                <div style="font-size:40px;margin-bottom:12px;">🔒</div>
                <div>Phiên đăng nhập hết hạn.</div>
                <button onclick="logoutWorker()" style="margin-top:16px;background:#dc2626;color:white;border:none;border-radius:8px;padding:10px 24px;cursor:pointer;">Đăng nhập lại</button>
            </div>`;
        return;
    }

    fetch('../api_master.php?action=mobile_worker_dashboard', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
        body: JSON.stringify({ token }),
    })
    .then(readJsonResponse)
    .then(d => {
        if (d.status !== 'success') {
            { const _el = document.getElementById('workerPanelBody'); if(_el) _el.innerHTML = `
                <div style="text-align:center; padding:30px;color:#fca5a5;">${d.message || 'Lỗi tải dashboard.'}</div>`;
            return;
        }
        renderWorkerDashboard(d);
    })
    .catch(() => {
        { const _el = document.getElementById('workerPanelBody'); if(_el) _el.innerHTML = `
            <div style="text-align:center; padding:30px;color:#fca5a5;">Lỗi kết nối. Kiểm tra mạng.</div>`;
    });
}

function renderWorkerDashboard(d) {
    const w           = d.worker || {};
    const shiftOn     = d.shift_status === 'on_shift';
    const earnings    = d.earnings_this_month || {};
    const activeJobs  = d.active_jobs || [];
    const notifs      = d.recent_notifications || [];
    const feeDebt     = d.fee_debt || {};
    const token       = localStorage.getItem('dth_worker_token') || '';

    const shiftColor  = shiftOn ? '#10b981' : '#6b7280';
    const shiftLabel  = shiftOn ? '🟢 Đang sẵn sàng nhận đơn' : '⚫ Offline — Chưa bắt ca';
    const shiftBtnLabel = shiftOn ? 'Kết thúc ca' : 'Bắt đầu ca';
    const shiftBtnBg  = shiftOn ? '#dc2626' : '#10b981';
    const shiftAction = shiftOn ? 'mobile_worker_shift_end' : 'mobile_worker_shift_start';

    const jobsHtml = activeJobs.length ? activeJobs.map(j => `
        <div style="background:#1e293b;border-radius:10px;padding:12px;margin-bottom:8px;border-left:3px solid #f59e0b;">
            <div style="font-weight:bold;color:#fbbf24;">#${j.job_id} — ${j.service_name || 'Dịch vụ'}</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:4px;">📍 ${j.address || 'Chưa có địa chỉ'}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;">Trạng thái: ${j.status_text || j.status}</div>
        </div>
    `).join('') : '<div style="color:#64748b;font-size:13px;padding:8px 0;">Không có ca đang hoạt động.</div>';

    const notifHtml = notifs.length ? notifs.slice(0,3).map(n => `
        <div style="background:#1e293b;border-radius:8px;padding:10px;margin-bottom:6px;${!n.is_read?'border-left:3px solid #7c3aed;':''}">
            <div style="font-size:13px;font-weight:bold;">${n.title}</div>
            <div style="font-size:12px;color:#94a3b8;margin-top:2px;">${n.body}</div>
        </div>
    `).join('') : '<div style="color:#64748b;font-size:13px;">Không có thông báo mới.</div>';

    { const _el = document.getElementById('workerPanelBody'); if(_el) _el.innerHTML = `
        <!-- Profile + Shift -->
        <div style="background:#1e293b; border-radius:12px;padding:16px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                <div style="background:#7c3aed;border-radius:50%;width:48px;height:48px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🛠️</div>
                <div>
                    <div style="font-weight:bold;font-size:16px;">${w.name || 'Thợ'}</div>
                    <div style="font-size:12px;color:#94a3b8;">${w.phone || ''} ${w.worker_code ? '· ' + w.worker_code : ''}</div>
                </div>
            </div>
            <div style="background:${shiftColor}22;border:1px solid ${shiftColor}44;border-radius:8px;padding:10px;text-align:center;margin-bottom:10px;">
                <span style="color:${shiftColor};font-weight:bold;font-size:14px;">${shiftLabel}</span>
            </div>
            <button onclick="toggleWorkerShift('${shiftAction}','${token}')" id="shiftToggleBtn"
                style="width:100%;background:${shiftBtnBg};color:white;border:none;border-radius:8px;padding:10px;font-size:14px;font-weight:bold;cursor:pointer;">
                ${shiftBtnLabel}
            </button>
        </div>

        <!-- Thu nhập tháng -->
        <div style="background:#1e293b;border-radius:12px;padding:16px;margin-bottom:12px;">
            <div style="font-size:13px;font-weight:bold;color:#94a3b8;margin-bottom:8px;">💰 THU NHẬP THÁNG NÀY</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center;">
                <div><div style="font-size:18px;font-weight:900;color:#10b981;">${earnings.jobs_completed || 0}</div><div style="font-size:11px;color:#64748b;">Ca xong</div></div>
                <div><div style="font-size:18px;font-weight:900;color:#fbbf24;">${formatWorkerMoney(earnings.gross_revenue || 0)}</div><div style="font-size:11px;color:#64748b;">Doanh thu</div></div>
                <div><div style="font-size:18px;font-weight:900;color:#60a5fa;">${formatWorkerMoney(earnings.net_income || 0)}</div><div style="font-size:11px;color:#64748b;">Thực nhận</div></div>
            </div>
            ${(feeDebt.total_debt||0)>0 ? `<div style="margin-top:8px;background:#7c3aed22;border:1px solid #7c3aed44;border-radius:6px;padding:8px;font-size:12px;color:#a78bfa;text-align:center;">⚠️ Phí nền tảng còn nợ: ${formatWorkerMoney(feeDebt.total_debt||0)}</div>` : ''}
        </div>

        <!-- Ca đang hoạt động -->
        <div style="background:#1e293b;border-radius:12px;padding:16px;margin-bottom:12px;">
            <div style="font-size:13px;font-weight:bold;color:#94a3b8;margin-bottom:8px;">📋 CA ĐANG HOẠT ĐỘNG (${activeJobs.length})</div>
            ${jobsHtml}
        </div>

        <!-- Thông báo -->
        <div style="background:#1e293b;border-radius:12px;padding:16px;margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div style="font-size:13px;font-weight:bold;color:#94a3b8;">🔔 THÔNG BÁO ${d.unread_notifications>0?`<span style="background:#dc2626;color:white;border-radius:20px;padding:1px 7px;font-size:11px;">${d.unread_notifications}</span>`:''}</div>
            </div>
            ${notifHtml}
        </div>

        <!-- Actions -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
            <button onclick="loadWorkerDashboard(document.getElementById('workerDashboardPanel'))"
                style="background:#334155;color:white;border:none;border-radius:8px;padding:10px;font-size:13px;cursor:pointer;">🔄 Làm mới</button>
            <button onclick="logoutWorker()"
                style="background:#dc2626;color:white;border:none;border-radius:8px;padding:10px;font-size:13px;cursor:pointer;">🚪 Đăng xuất</button>
        </div>
    `;
}

function formatWorkerMoney(n) {
    return new Intl.NumberFormat('vi-VN').format(n) + 'đ';
}

function toggleWorkerShift(action, token) {
    const btn = document.getElementById('shiftToggleBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Đang xử lý...'; }
    fetch('../api_master.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
        body: JSON.stringify({ token }),
    })
    .then(readJsonResponse)
    .then(d => {
        if (d.status === 'success') {
            // Cập nhật localStorage
            const wdata = JSON.parse(localStorage.getItem('dth_worker_data') || '{}');
            wdata.shift_status = d.shift_status || (action.includes('start') ? 'on_shift' : 'off');
            localStorage.setItem('dth_worker_data', JSON.stringify(wdata));
        }
        // Reload dashboard
        loadWorkerDashboard(document.getElementById('workerDashboardPanel'));
    })
    .catch(() => {
        if (btn) { btn.disabled = false; btn.textContent = 'Thử lại'; }
    });
}



// Fallback

async function promptChangeWorkerPin() {
    const oldPin = prompt('Nhập mật khẩu (PIN) cũ:');
    if (!oldPin) return;
    const newPin = prompt('Nhập mật khẩu (PIN) mới:');
    if (!newPin || newPin.length < 4) return alert('Mật khẩu mới quá ngắn (tối thiểu 4 ký tự).');
    
    const token = localStorage.getItem('dth_worker_token');
    const res = await fetch('../api_master.php?action=worker_change_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, old_pin: oldPin, new_pin: newPin })
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') {
        logoutWorker();
    }
}


function requestGpsAndLogin() {
            const err = document.getElementById('workerLoginError');
            const btn = document.getElementById('workerLoginSubmitBtn');
            if (err) err.style.display = 'none';

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
            const phone = (document.getElementById('workerPhoneInput') ? document.getElementById('workerPhoneInput').value : '').trim();
            const pin   = (document.getElementById('workerPinInput') ? document.getElementById('workerPinInput').value : '').trim();
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
                document.querySelector('.login-box').style.display = 'none';
                openWorkerPanel();
            }
        });
    </script>
</body>
</html>
