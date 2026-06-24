<?php
declare(strict_types=1);

require_once __DIR__ . '/api/core.php';

app_ensure_session();

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');

$logout = isset($_GET['logout']) && $_GET['logout'] === '1';
if ($logout) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin_xxx.php');
    exit;
}

$error = '';
$success = '';
$user = null;

function safe_input(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email']) && !empty($_POST['password'])) {
    $email = safe_input('email');
    $password = safe_input('password');

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } else {
        try {
            $pdo = pdo();
            $statusCondition = column_exists($pdo, 'users', 'status')
                ? " AND (status IS NULL OR status = '' OR status = 'active')"
                : " AND (is_active = 1 OR is_active IS NULL)";

            $stmt = $pdo->prepare("SELECT id, email, fullname, role, password_hash FROM users WHERE email = ? AND role = 'admin'" . $statusCondition . " LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, (string)($user['password_hash'] ?? ''))) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_fullname'] = $user['fullname'];
                $_SESSION['admin_logged_in'] = true;
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
                header('Location: admin_xxx.php');
                exit;
            } else {
                $error = 'Email hoặc mật khẩu không đúng.';
                $user = null;
            }
        } catch (Throwable $e) {
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
} elseif (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    try {
        $pdo = pdo();
        $stmt = $pdo->prepare("SELECT id, email, fullname, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $_SESSION = [];
            session_destroy();
            header('Location: admin_xxx.php');
            exit;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    } catch (Throwable $e) {
        $user = null;
        $error = 'Lỗi tải thông tin admin: ' . $e->getMessage();
    }
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']) && $user !== false && $user !== null;
$displayName = esc_html($user['fullname'] ?? $user['email'] ?? 'Admin');
$csrfToken = esc_html($_SESSION['csrf_token'] ?? '');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Chợ Lấp Vò Online</title>
    <style>
        :root {
            --primary: #dc2626;
            --primary-dark: #b91c1c;
            --primary-light: #fef2f2;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f8fafc;
            --card: #ffffff;
            --shadow: 0 10px 30px rgba(17, 24, 39, 0.08);
            --radius: 12px;
        }
        * { box-sizing: border-box; font-family: "Times New Roman", Times, serif; }
        html, body { margin: 0; min-height: 100%; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }
        .page { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: min(520px, 100%); background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; padding: 32px; text-align: center; }
        .card-header h1 { margin: 12px 0 4px; font-size: 22px; font-weight: 700; }
        .card-header p { margin: 0; opacity: 0.92; font-size: 14px; }
        .logo-mark { width: 56px; height: 56px; background: rgba(255,255,255,0.18); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; }
        .card-body { padding: 28px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        .alert-error { background: var(--primary-light); color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        label { display: block; font-size: 13px; font-weight: 600; margin: 16px 0 6px; color: #374151; }
        input, select, textarea { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; transition: border-color .15s, box-shadow .15s; font-family: "Times New Roman", Times, serif; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12); }
        button { width: 100%; margin-top: 22px; padding: 13px 16px; border: none; border-radius: 8px; background: var(--primary); color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .15s, transform .05s; }
        button:hover { background: var(--primary-dark); }
        button:active { transform: translateY(1px); }
        button.secondary { background: #fff; color: var(--primary); border: 1px solid #fecaca; }
        button.secondary:hover { background: var(--primary-light); }
        button.success { background: #16a34a; }
        button.success:hover { background: #15803d; }
        button.danger { background: #991b1b; }
        button.danger:hover { background: #7f1d1d; }
        button.small { width: auto; margin-top: 0; padding: 6px 12px; font-size: 13px; }
        .dashboard { width: min(1180px, 100%); }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        .topbar h1 { margin: 0; font-size: 22px; }
        .user-chip { display: inline-flex; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--border); padding: 8px 14px; border-radius: 999px; font-size: 14px; font-weight: 600; }
        .user-chip .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid transparent; }
        .btn-outline { background: #fff; color: var(--primary); border-color: #fecaca; }
        .btn-outline:hover { background: var(--primary-light); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .tile { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 22px; text-decoration: none; color: inherit; transition: transform .12s, box-shadow .12s, border-color .12s; display: flex; flex-direction: column; gap: 10px; }
        .tile:hover { transform: translateY(-3px); box-shadow: var(--shadow); border-color: #fecaca; }
        .tile-icon { width: 44px; height: 44px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; background: var(--primary-light); }
        .tile-title { font-weight: 700; font-size: 16px; margin: 0; }
        .tile-desc { font-size: 13px; color: var(--muted); margin: 0; }
        .panel { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-top: 18px; }
        .panel h2 { margin: 0 0 14px; font-size: 18px; }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .tab { padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px; background: #fff; cursor: pointer; font-weight: 600; }
        .tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border-bottom: 1px solid var(--border); padding: 10px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; font-weight: 700; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; }
        .status.active { background: #dcfce7; color: #166534; }
        .status.pending { background: #fef3c7; color: #92400e; }
        .status.rejected { background: #fee2e2; color: #991b1b; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .form-row label { margin-top: 0; }
        .qr-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
        .qr-card { border: 1px solid var(--border); border-radius: 8px; padding: 12px; text-align: center; }
        .qr-card img { width: 100%; max-width: 120px; height: auto; }
        .qr-card code { display: block; font-size: 11px; margin-top: 6px; word-break: break-all; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #d1d5db; border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        footer { text-align: center; margin-top: 24px; font-size: 12px; color: var(--muted); }
        @media (max-width: 640px) { .card-body, .card-header { padding: 22px; } .topbar { flex-direction: column; align-items: flex-start; } .user-chip { width: 100%; justify-content: space-between; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php if ($isLoggedIn): ?>
<div class="page">
    <div class="dashboard">
        <div class="topbar">
            <div>
                <h1>Dashboard</h1>
                <div style="color: var(--muted); font-size: 14px;">Chợ Lấp Vò Online</div>
            </div>
            <div class="user-chip">
                <span class="avatar"><?= mb_substr($displayName, 0, 1, 'UTF-8') ?></span>
                <span><?= $displayName ?></span>
                <a class="btn btn-outline" href="admin_xxx.php?logout=1">Đăng xuất</a>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <div class="grid">
            <a class="tile" href="bct_portal.php" target="_blank" rel="noopener">
                <div class="tile-icon">&#128203;</div>
                <div class="tile-title">Cổng báo cáo BCT</div>
                <div class="tile-desc">Truy cập cổng thông tin Bộ Công Thương.</div>
            </a>
            <a class="tile" href="admin/chat_logs.php" target="_blank" rel="noopener">
                <div class="tile-icon">&#128172;</div>
                <div class="tile-title">Chat logs</div>
                <div class="tile-desc">Xem lịch sử chat và tương tác.</div>
            </a>
            <a class="tile" href="/" target="_blank" rel="noopener">
                <div class="tile-icon">&#127760;</div>
                <div class="tile-title">Xem website</div>
                <div class="tile-desc">Mở trang chủ dienmayhieu.com.</div>
            </a>
            <a class="tile" href="admin_xxx.php?logout=1">
                <div class="tile-icon">&#128682;</div>
                <div class="tile-title">Đăng xuất</div>
                <div class="tile-desc">Thoát khỏi trang quản trị.</div>
            </a>
        </div>

        <!-- Store Approval -->
        <div class="panel">
            <h2>🏪 Phê duyệt cửa hàng đăng ký</h2>
            <div class="tabs">
                <button type="button" class="tab active" data-tab="stores-pending" onclick="switchTab('stores-pending')">Chờ duyệt</button>
                <button type="button" class="tab" data-tab="stores-all" onclick="switchTab('stores-all')">Tất cả</button>
            </div>
            <div id="stores-pending" class="tab-content active">
                <div id="pending-stores-table">Đang tải...</div>
            </div>
            <div id="stores-all" class="tab-content">
                <div id="all-stores-table">Đang tải...</div>
            </div>
        </div>

        <!-- Voucher / QR -->
        <div class="panel">
            <h2>🎟️ Chương trình khuyến mãi & QR</h2>
            <div class="tabs">
                <button type="button" class="tab active" data-tab="voucher-list" onclick="switchTab('voucher-list')">Voucher</button>
                <button type="button" class="tab" data-tab="qr-list" onclick="switchTab('qr-list')">QR Coupons</button>
                <button type="button" class="tab" data-tab="voucher-create" onclick="switchTab('voucher-create')">Tạo Voucher</button>
                <button type="button" class="tab" data-tab="qr-create" onclick="switchTab('qr-create')">Tạo QR</button>
            </div>
            <div id="voucher-list" class="tab-content active">
                <div id="vouchers-table">Đang tải...</div>
            </div>
            <div id="qr-list" class="tab-content">
                <div id="qr-coupons-grid">Đang tải...</div>
            </div>
            <div id="voucher-create" class="tab-content">
                <form id="voucher-form" onsubmit="return saveVoucher(event)">
                    <div class="form-row">
                        <div>
                            <label>Mã voucher</label>
                            <input type="text" name="code" required placeholder="VD: SUMMER2026">
                        </div>
                        <div>
                            <label>Loại</label>
                            <select name="type">
                                <option value="fixed">Giảm số tiền (VNĐ)</option>
                                <option value="percent">Giảm %</option>
                            </select>
                        </div>
                        <div>
                            <label>Giá trị</label>
                            <input type="number" name="value" min="1" required placeholder="VD: 50000">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Đơn tối thiểu (VNĐ)</label>
                            <input type="number" name="min_order" min="0" value="0">
                        </div>
                        <div>
                            <label>Giảm tối đa (VNĐ)</label>
                            <input type="number" name="max_discount" min="0" value="0">
                        </div>
                        <div>
                            <label>Số lượt sử dụng</label>
                            <input type="number" name="usage_limit" min="1" value="100">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Hết hạn</label>
                            <input type="datetime-local" name="expires_at">
                        </div>
                        <div>
                            <label>Trạng thái</label>
                            <select name="is_active">
                                <option value="1">Kích hoạt</option>
                                <option value="0">Vô hiệu</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Tạo voucher</button>
                </form>
            </div>
            <div id="qr-create" class="tab-content">
                <form id="qr-form" onsubmit="return generateQR(event)">
                    <div class="form-row">
                        <div>
                            <label>Số lượng</label>
                            <input type="number" name="count" min="1" max="100" value="1" required>
                        </div>
                        <div>
                            <label>Loại</label>
                            <select name="type">
                                <option value="discount">Giảm giá</option>
                                <option value="prize">Quà / Vòng quay</option>
                            </select>
                        </div>
                        <div>
                            <label>Giá trị (VNĐ / %)</label>
                            <input type="number" name="value" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Mô tả</label>
                            <input type="text" name="description" placeholder="VD: Khuyến mãi tháng 6">
                        </div>
                    </div>
                    <button type="submit">Tạo mã QR</button>
                </form>
                <div id="qr-created-result" style="margin-top:16px;"></div>
            </div>
        </div>

        <footer>
            &copy; <?= date('Y') ?> Chợ Lấp Vò Online - Admin Dashboard
        </footer>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';
const API_BASE = 'api_master.php';

async function api(action, data = {}, method = 'POST') {
    const url = method === 'GET' ? `${API_BASE}?action=${action}` : API_BASE + '?action=' + action;
    const opts = {
        method,
        headers: {
            'X-CSRF-Token': CSRF_TOKEN,
            'Accept': 'application/json'
        }
    };
    if (method === 'POST') {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data);
    }
    const res = await fetch(url, opts);
    return res.json();
}

function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelector(`.tab[data-tab="${id}"]`).classList.add('active');
}

function fmtMoney(n) {
    return Number(n || 0).toLocaleString('vi-VN') + ' VNĐ';
}

function badge(status) {
    const s = (status || 'pending').toLowerCase();
    return `<span class="status ${s}">${s.toUpperCase()}</span>`;
}

async function loadStores(filter, containerId) {
    const res = await api('admin_store_list', { status: filter }, 'GET');
    const container = document.getElementById(containerId);
    if (!res || res.status !== 'ok' || !res.data) {
        container.innerHTML = '<p>Không tải được danh sách cửa hàng.</p>';
        return;
    }
    if (res.data.length === 0) {
        container.innerHTML = '<p>Không có cửa hàng nào.</p>';
        return;
    }
    let html = '<div class="table-wrap"><table><thead><tr><th>ID</th><th>Cửa hàng</th><th>Chủ</th><th>SĐT</th><th>MST</th><th>Trạng thái</th><th>Ngày đăng ký</th><th>Thao tác</th></tr></thead><tbody>';
    for (const s of res.data) {
        const actions = s.status === 'pending'
            ? `<button class="small success" onclick="approveStore(${s.id})">Duyệt</button> <button class="small danger" onclick="rejectStore(${s.id})">Từ chối</button>`
            : '';
        html += `<tr>
            <td>${s.id}</td>
            <td><strong>${s.store_name || ''}</strong><br><small>${s.store_type || ''}</small></td>
            <td>${s.owner_name || ''}</td>
            <td>${s.phone || ''}</td>
            <td>${s.tax_code || ''}</td>
            <td>${badge(s.status)}</td>
            <td>${s.created_at ? new Date(s.created_at).toLocaleDateString('vi-VN') : ''}</td>
            <td class="actions">${actions}</td>
        </tr>`;
    }
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

async function approveStore(id) {
    if (!confirm('Phê duyệt cửa hàng này?')) return;
    const res = await api('admin_approve_store', { id });
    alert(res.message || (res.status === 'ok' ? 'Thành công' : 'Lỗi'));
    loadStores('pending', 'pending-stores-table');
    loadStores('', 'all-stores-table');
}

async function rejectStore(id) {
    if (!confirm('Từ chối cửa hàng này?')) return;
    const res = await api('admin_reject_store', { id });
    alert(res.message || (res.status === 'ok' ? 'Thành công' : 'Lỗi'));
    loadStores('pending', 'pending-stores-table');
    loadStores('', 'all-stores-table');
}

async function loadVouchers() {
    const res = await api('admin_voucher_list', {}, 'GET');
    const container = document.getElementById('vouchers-table');
    if (!res || res.status !== 'ok' || !res.vouchers) {
        container.innerHTML = '<p>Không tải được danh sách voucher.</p>';
        return;
    }
    if (res.vouchers.length === 0) {
        container.innerHTML = '<p>Chưa có voucher nào.</p>';
        return;
    }
    let html = '<div class="table-wrap"><table><thead><tr><th>Mã</th><th>Loại</th><th>Giá trị</th><th>Đã dùng</th><th>Lượt</th><th>Hết hạn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
    for (const v of res.vouchers) {
        const type = v.type === 'percent' ? `${v.value}%` : fmtMoney(v.value);
        const used = `${v.used_count || 0}/${v.usage_limit || v.max_uses || 1}`;
        const active = parseInt(v.is_active) ? '<span class="status active">KÍCH HOẠT</span>' : '<span class="status rejected">VÔ HIỆU</span>';
        html += `<tr>
            <td><code>${v.code}</code></td>
            <td>${v.type === 'percent' ? 'Phần trăm' : 'Cố định'}</td>
            <td>${type}</td>
            <td>${used}</td>
            <td>${v.usage_limit || v.max_uses || 1}</td>
            <td>${v.expires_at ? new Date(v.expires_at).toLocaleDateString('vi-VN') : 'Không'}</td>
            <td>${active}</td>
            <td><button class="small danger" onclick="deleteVoucher(${v.id})">Xóa</button></td>
        </tr>`;
    }
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

async function loadQRCoupons() {
    const res = await api('admin_voucher_list', {}, 'GET');
    const container = document.getElementById('qr-coupons-grid');
    if (!res || res.status !== 'ok' || !res.qr_coupons) {
        container.innerHTML = '<p>Không tải được danh sách QR.</p>';
        return;
    }
    if (res.qr_coupons.length === 0) {
        container.innerHTML = '<p>Chưa có mã QR nào.</p>';
        return;
    }
    let html = '<div class="qr-list">';
    for (const q of res.qr_coupons) {
        const payload = `https://dienmayhieu.com/?voucher=${q.code}`;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(payload)}`;
        const used = parseInt(q.is_used) ? '<span class="status rejected">ĐÃ DÙNG</span>' : '<span class="status active">CHƯA DÙNG</span>';
        html += `<div class="qr-card">
            <img src="${qrUrl}" alt="QR" loading="lazy">
            <code>${q.code}</code>
            <div>${q.type === 'prize' ? 'Vòng quay' : 'Giảm ' + fmtMoney(q.value)}</div>
            <div>${used}</div>
            <button class="small danger" style="margin-top:8px;" onclick="deleteQR(${q.id})">Xóa</button>
        </div>`;
    }
    html += '</div>';
    container.innerHTML = html;
}

async function saveVoucher(e) {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form));
    data.value = Number(data.value);
    data.min_order = Number(data.min_order || 0);
    data.max_discount = Number(data.max_discount || 0);
    data.usage_limit = Number(data.usage_limit || 1);
    data.is_active = Number(data.is_active);
    const res = await api('admin_create_voucher', data);
    alert(res.message || (res.status === 'ok' ? 'Đã lưu voucher' : 'Lỗi'));
    if (res.status === 'ok') {
        form.reset();
        switchTab('voucher-list');
        loadVouchers();
    }
    return false;
}

async function generateQR(e) {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form));
    data.count = Number(data.count);
    data.value = Number(data.value);
    const res = await api('admin_create_qr', data);
    const result = document.getElementById('qr-created-result');
    if (res.status === 'ok' && res.codes) {
        let html = '<h3>Đã tạo các mã QR:</h3><div class="qr-list">';
        for (const code of res.codes) {
            const payload = `https://dienmayhieu.com/?voucher=${code}`;
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(payload)}`;
            html += `<div class="qr-card"><img src="${qrUrl}" alt="QR"><code>${code}</code></div>`;
        }
        html += '</div>';
        result.innerHTML = html;
        loadQRCoupons();
    } else {
        result.innerHTML = '<p class="alert alert-error">' + (res.message || 'Lỗi tạo QR') + '</p>';
    }
    return false;
}

async function deleteVoucher(id) {
    if (!confirm('Xóa voucher này?')) return;
    const res = await api('admin_delete_voucher', { id });
    alert(res.message || (res.status === 'ok' ? 'Đã xóa' : 'Lỗi'));
    loadVouchers();
}

async function deleteQR(id) {
    if (!confirm('Xóa mã QR này?')) return;
    const res = await api('admin_delete_qr', { id });
    alert(res.message || (res.status === 'ok' ? 'Đã xóa' : 'Lỗi'));
    loadQRCoupons();
}

window.addEventListener('DOMContentLoaded', () => {
    loadStores('pending', 'pending-stores-table');
    loadStores('', 'all-stores-table');
    loadVouchers();
    loadQRCoupons();
});
</script>

<?php else: ?>
<div class="page">
    <div class="card">
        <div class="card-header">
            <div class="logo-mark">&#128722;</div>
            <h1>Đăng nhập Admin</h1>
            <p>Chợ Lấp Vò Online - Trang quản trị</p>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= esc_html($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <label for="email">Email admin</label>
                <input id="email" name="email" type="text" autocomplete="username" required autofocus placeholder="Nhập email admin">

                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Nhập mật khẩu">

                <button type="submit">Đăng nhập</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
</body>
</html>
