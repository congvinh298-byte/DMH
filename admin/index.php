<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Admin dashboard content for dienmayhieu.com
// Included by index.php when route is /admin

// Thông tin đăng nhập admin
$admin_user = 'anhthien';
$admin_pass = 'Anhthien369@';

// Xử lý đăng nhập
if (isset($_POST['login'])) {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if ($u === $admin_user && $p === $admin_pass) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = 'Sai tên đăng nhập hoặc mật khẩu.';
    }
}

// Xử lý đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/index.php');
    exit;
}

$is_logged_in = !empty($_SESSION['admin_logged_in']);
if ($is_logged_in && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Điện Máy Hiếu</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f6f8;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .login-page {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 30px;
            width: 100%;
            max-width: 420px;
        }
        h1, h2, h3 { color: #d4a76e; }
        input[type=text], input[type=password], input[type=number], input[type=date], select, textarea {
            width: 100%;
            padding: 12px;
            margin: 8px 0 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: inherit;
        }
        button, .btn {
            background: #d4a76e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        button:hover, .btn:hover { background: #b58855; }
        .error { color: red; margin-bottom: 15px; }
        .legal-box {
            background: #fff9f0;
            border-left: 4px solid #d4a76e;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-top: 15px;
        }
        .legal-box h3 { margin-top: 0; color: #8b694f; }
        .legal-box ul { margin: 0; padding-left: 20px; }

        /* Admin dashboard */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 260px;
            background: #2c3e50;
            color: white;
            flex-shrink: 0;
        }
        .sidebar .brand {
            padding: 20px;
            background: #1a252f;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
        }
        .sidebar nav a {
            display: block;
            padding: 14px 20px;
            color: #ecf0f1;
            text-decoration: none;
            border-bottom: 1px solid #34495e;
            cursor: pointer;
        }
        .sidebar nav a:hover, .sidebar nav a.active {
            background: #d4a76e;
            color: white;
        }
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .section {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .section.active { display: block; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: bold; display: block; margin-bottom: 5px; }
        .table-wrap { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #d4a76e; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn-small { padding: 6px 12px; font-size: 13px; margin-right: 5px; }
        .btn-red { background: #e74c3c; }
        .btn-green { background: #27ae60; }
        .btn-blue { background: #2980b9; }
        .print-area {
            background: #fff;
            border: 1px solid #ccc;
            padding: 25px;
            margin-top: 15px;
            border-radius: 8px;
            max-width: 800px;
        }
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { position: absolute; left: 0; top: 0; width: 100%; border: none; }
        }
        .qr-preview { text-align: center; margin: 15px 0; }
        .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .dash-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }
        .dash-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .dash-card.active-card {
            border-color: #d4a76e;
            background: #fffbf5;
        }
        .dash-card .number { font-size: 28px; font-weight: bold; color: #d4a76e; }
        .dash-card .label { color: #777; font-size: 14px; }
        .footer-legal {
            text-align: center;
            font-size: 13px;
            color: #777;
            margin-top: 30px;
            padding: 15px;
        }
    </style>
</head>
<body>

<?php if (!$is_logged_in): ?>

<div class="login-page">
    <div class="card">
        <h1>🔐 Admin Điện Máy Hiếu</h1>
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
                <li><strong>Thông báo Bộ Công Thương:</strong> Đã đăng ký / đang hoàn thiện theo Nghị định 52/2013/NĐ-CP và Thông tư 47/2014/TT-BCT</li>
            </ul>
        </div>
    </div>
</div>

<?php else: ?>

<div class="admin-layout">
    <div class="sidebar">
        <div class="brand">🏪 Điện Máy Hiếu</div>
        <nav>
            <a class="active" href="/admin#dashboard" onclick="showSection('dashboard', event); return false;">📊 Tổng quan</a>
            <a href="/admin#ctv" onclick="showSection('ctv', event); return false;">👷 Quản lý CTV / Thợ</a>
            <a href="/admin#qr" onclick="showSection('qr', event); return false;">🎁 Tạo QR khuyến mãi</a>
            <a href="/admin#hoadon" onclick="showSection('hoadon', event); return false;">🧾 Hóa đơn bán lẻ</a>
            <a href="/admin#gtgt" onclick="showSection('gtgt', event); return false;">📑 Hóa đơn GTGT</a>
            <a href="/admin#hopdong" onclick="showSection('hopdong', event); return false;">📄 Hợp đồng lao động</a>
            <a href="/admin/index.php?logout=1">🔓 Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h2 id="page-title">Tổng quan</h2>
            <div>Xin chào, <strong>anhthien</strong> (Chủ cửa hàng)</div>
        </div>

        <!-- DASHBOARD -->
        <div id="dashboard" class="section active">
            <h3>Chào mừng Chủ cửa hàng điện máy Hiếu</h3>
            <p>Hệ thống quản lý tự động — anh chỉ cần nhập dữ liệu, mọi việc còn lại đã được xử lý.</p>
            <div class="dashboard-cards">
                <div class="dash-card" id="cardCtv" onclick="showDashboardDetail('ctv')"><div class="number" id="dashCtv">0</div><div class="label">CTV / Thợ</div></div>
                <div class="dash-card" id="cardHoaDon" onclick="showDashboardDetail('hoadon')"><div class="number" id="dashHoaDon">0</div><div class="label">Hóa đơn</div></div>
                <div class="dash-card" id="cardHopDong" onclick="showDashboardDetail('hopdong')"><div class="number" id="dashHopDong">0</div><div class="label">Hợp đồng</div></div>
                <div class="dash-card" id="cardQr" onclick="showDashboardDetail('qr')"><div class="number" id="dashQr">0</div><div class="label">QR khuyến mãi</div></div>
            </div>

            <!-- DETAIL AREA -->
            <div id="dashboard-detail" style="margin-top: 35px; display: none; border-top: 2px solid #eee; padding-top: 25px;">
                <h3 id="dash-detail-title" style="margin: 0 0 15px 0; font-size: 18px; color: #d4a76e;">Chi tiết</h3>
                <p style="font-size: 13px; color: #777; margin-bottom: 15px;">Anh có thể xem nhanh danh sách, xem chi tiết hóa đơn/hợp đồng hoặc thực hiện xóa trực tiếp tại đây.</p>
                <div class="table-wrap">
                    <table>
                        <thead id="dash-detail-thead"></thead>
                        <tbody id="dash-detail-tbody"></tbody>
                    </table>
                </div>
                <div id="dash-detail-preview"></div>
            </div>
        </div>

        <!-- CTV / THỢ -->
        <div id="ctv" class="section">
            <h3>👷 Quản lý CTV / Thợ sửa chữa</h3>
            <div class="grid-2">
                <div>
                    <div class="form-group">
                        <label>Telegram ID (số)</label>
                        <input type="text" id="ctvTelegramId" placeholder="VD: 123456789">
                    </div>
                    <div class="form-group">
                        <label>Họ tên hiển thị</label>
                        <input type="text" id="ctvName">
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <input type="text" id="ctvPhone" placeholder="VD: 0901234567">
                    </div>
                    <div class="form-group">
                        <label>Loại thợ</label>
                        <select id="ctvType">
                            <option value="ho_kinh_doanh">Hộ kinh doanh</option>
                            <option value="ca_nhan">Cá nhân / Tự do</option>
                            <option value="ctv">CTV giới thiệu</option>
                            <option value="cong_ty">Công ty đối tác</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vai trò</label>
                        <select id="ctvRole">
                            <option value="worker">Thợ / CTV</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button onclick="addCtv()">Đăng ký / Cập nhật</button>
                    <button class="btn-blue" style="margin-left: 10px;" onclick="syncCtv()">🔄 Đồng bộ nhóm Telegram</button>
                    <p id="ctvStatus" style="margin-top:10px;font-size:13px;color:#555;"></p>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>TG ID</th>
                                <th>Tên</th>
                                <th>Username</th>
                                <th>SĐT</th>
                                <th>Loại</th>
                                <th>Vai trò</th>
                                <th>Ca</th>
                                <th>Nợ phí</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody id="ctvList"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- QR KHUYẾN MÃI -->
        <div id="qr" class="section">
            <h3>🎁 Tạo QR khuyến mãi</h3>
            <div class="grid-2">
                <div>
                    <div class="form-group"><label>Tên chương trình</label><input type="text" id="qrName"></div>
                    <div class="form-group"><label>Mã khuyến mãi</label><input type="text" id="qrCode"></div>
                    <div class="form-group"><label>Giảm giá</label><input type="text" id="qrValue" placeholder="VD: 20% hoặc 100.000đ"></div>
                    <button onclick="generateQr()">Tạo QR</button>
                </div>
                <div>
                    <div class="qr-preview" id="qrPreview">QR sẽ hiển thị ở đây</div>
                    <button class="btn-blue" onclick="printQr()">In QR</button>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Chương trình</th><th>Mã</th><th>Giảm giá</th><th></th></tr></thead>
                    <tbody id="qrList"></tbody>
                </table>
            </div>
        </div>

        <!-- HÓA ĐƠN BÁN LẺ -->
        <div id="hoadon" class="section">
            <h3>🧾 Hóa đơn bán lẻ</h3>
            <div class="form-group"><label>Tên khách hàng</label><input type="text" id="hdName"></div>
            <div class="form-group"><label>Sản phẩm</label><input type="text" id="hdProduct" placeholder="VD: Tủ lạnh Mini 48L"></div>
            <div class="grid-2">
                <div class="form-group"><label>Đơn giá</label><input type="number" id="hdPrice" oninput="calcHoaDon()"></div>
                <div class="form-group"><label>Số lượng</label><input type="number" id="hdQty" value="1" oninput="calcHoaDon()"></div>
            </div>
            <div class="form-group"><label>Tổng tiền: <strong id="hdTotal">0 ₫</strong></label></div>
            <button onclick="createHoaDon()">Tạo hóa đơn</button>
            <button onclick="previewHoaDon()" class="btn-blue" style="margin-left: 10px;">Xem hóa đơn nháp</button>
            <div class="print-area" id="hdPrintArea"></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Khách</th><th>Sản phẩm</th><th>Tổng</th><th>Ngày</th><th></th></tr></thead>
                    <tbody id="hdList"></tbody>
                </table>
            </div>
        </div>

        <!-- HÓA ĐƠN GTGT -->
        <div id="gtgt" class="section">
            <h3>📑 Hóa đơn GTGT</h3>
            <div class="form-group"><label>Tên công ty / khách hàng</label><input type="text" id="gtgtName"></div>
            <div class="form-group">
                <label>Mã số thuế</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="gtgtTax" style="flex:1;">
                    <button type="button" class="btn-blue" onclick="checkTaxCode()">🔍 Kiểm tra MST</button>
                </div>
                <div id="gtgtTaxStatus" style="margin-top:6px; font-size:13px; color:#555;"></div>
            </div>
            <div class="form-group"><label>Địa chỉ công ty / khách hàng</label><input type="text" id="gtgtAddress"></div>
            <div class="form-group"><label>Sản phẩm / dịch vụ</label><input type="text" id="gtgtProduct"></div>
            <div class="grid-2">
                <div class="form-group"><label>Đơn giá (chưa VAT)</label><input type="number" id="gtgtPrice" oninput="calcGtgt()"></div>
                <div class="form-group"><label>Số lượng</label><input type="number" id="gtgtQty" value="1" oninput="calcGtgt()"></div>
            </div>
            <div class="form-group">
                Tiền hàng: <strong id="gtgtNet">0 ₫</strong> &nbsp;|&nbsp;
                VAT 10%: <strong id="gtgtVat">0 ₫</strong> &nbsp;|&nbsp;
                Tổng cộng: <strong id="gtgtTotal">0 ₫</strong>
            </div>
            <button onclick="createGtgt()">Lập hóa đơn GTGT</button>
            <button onclick="previewGtgt()" class="btn-blue" style="margin-left: 10px;">Xem hóa đơn nháp</button>
            <div class="print-area" id="gtgtPrintArea"></div>
        </div>

        <!-- HỢP ĐỒNG LAO ĐỘNG -->
        <div id="hopdong" class="section">
            <h3>📄 Hợp đồng lao động với đối tác / thợ</h3>
            <div class="grid-2">
                <div>
                    <div class="form-group"><label>Tên đối tác / thợ</label><input type="text" id="hdldName"></div>
                    <div class="form-group"><label>Số điện thoại</label><input type="text" id="hdldPhone"></div>
                    <div class="form-group"><label>Số CCCD</label><input type="text" id="hdldId"></div>
                    <div class="form-group"><label>Ngành đăng ký</label>
                        <select id="hdldJob">
                            <option value="Điện lạnh">Điện lạnh</option>
                            <option value="Điện tử">Điện tử</option>
                            <option value="Điện nước">Điện nước</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Ngày bắt đầu</label><input type="date" id="hdldStart"></div>
                    <button onclick="createHopDong()">Tự động lập hợp đồng</button>
                </div>
                <div class="print-area" id="hdldPrintArea">
                    <p><em>Hợp đồng tự động sinh sẽ hiển thị ở đây. Anh chỉ cần nhập thông tin và in ra cho đối tác ký tên.</em></p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Đối tác</th><th>SĐT</th><th>CCCD</th><th>Ngành</th><th>Ngày bắt đầu</th><th></th></tr></thead>
                    <tbody id="hdldList"></tbody>
                </table>
            </div>
        </div>

        <div class="footer-legal">
            © <?php echo date('Y'); ?> Điện Máy Hiếu — Tuân thủ Nghị định 52/2013/NĐ-CP & Thông tư 47/2014/TT-BCT
        </div>
    </div>
</div>

<script>
window.CSRF_TOKEN = "<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>";
let dashWorkers = [];
let currentDashType = '';
const LS = {
    ctv: 'dmh_ctv', qr: 'dmh_qr', hd: 'dmh_hd', gtgt: 'dmh_gtgt', nhap: 'dmh_nhap', hdld: 'dmh_hdld'
};
function getData(key){ return JSON.parse(localStorage.getItem(key) || '[]'); }
function setData(key, data){ localStorage.setItem(key, JSON.stringify(data)); }
function showSection(id, ev){
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.sidebar nav a').forEach(a => a.classList.remove('active'));
    if (ev && ev.target) ev.target.classList.add('active');
    const titles = {
        dashboard: 'Tổng quan', ctv: 'Quản lý CTV / Thợ', qr: 'Tạo QR khuyến mãi',
        hoadon: 'Hóa đơn bán lẻ', gtgt: 'Hóa đơn GTGT', hopdong: 'Hợp đồng lao động'
    };
    document.getElementById('page-title').textContent = titles[id];
    updateDashboard();
    if (id === 'ctv') loadCtv();
}
function updateDashboard(){
    document.getElementById('dashCtv').textContent = dashWorkers.length || getData(LS.ctv).length;
    document.getElementById('dashHoaDon').textContent = getData(LS.hd).length + getData(LS.gtgt).length;
    document.getElementById('dashHopDong').textContent = getData(LS.hdld).length;
    document.getElementById('dashQr').textContent = getData(LS.qr).length;
}
async function loadDashWorkers() {
    try {
        const res = await fetch('/api_master.php?action=admin_workers', { credentials: 'same-origin' });
        const json = await res.json();
        dashWorkers = Array.isArray(json.data) ? json.data : [];
    } catch (e) {
        dashWorkers = [];
    }
    updateDashboard();
    if (currentDashType === 'ctv') renderDashCtvDetail();
}
function renderCtv(){
    loadCtv();
}
async function loadCtv(){
    const statusEl = document.getElementById('ctvStatus');
    statusEl.textContent = 'Đang tải danh sách...';
    try {
        const res = await fetch('/api_master.php?action=admin_workers', { credentials: 'same-origin' });
        const json = await res.json();
        const data = (json.data || []);
        dashWorkers = data;
        const tbody = document.getElementById('ctvList');
        tbody.innerHTML = data.map(w => renderWorkerRow(w)).join('');
        updateDashboard();
        statusEl.textContent = `Tải xong ${data.length} thợ.`;
    } catch(e) {
        statusEl.textContent = 'Lỗi tải danh sách: ' + e.message;
    }
}
function renderWorkerRow(w){
    const isAdmin = parseInt(w.is_admin, 10) === 1;
    const roleLabel = isAdmin ? '👑 Admin' : (w.role || 'worker');
    const status = parseInt(w.is_active, 10) === 0 ? '⛔ Đã rời nhóm' : '✅ Hoạt động';
    return `<tr>
        <td>${w.worker_id || ''}</td>
        <td>${escHtml(w.telegram_name || '')}</td>
        <td>@${escHtml(w.telegram_username || '')}</td>
        <td>${escHtml(w.phone || '')}</td>
        <td>${escHtml(w.worker_type || '')}</td>
        <td><strong>${roleLabel}</strong></td>
        <td>${w.job_count || 0}</td>
        <td>${fmtMoney(w.unpaid_fee || 0)}</td>
        <td>${status}</td>
    </tr>`;
}
function renderDashCtvDetail() {
    const titleEl = document.getElementById('dash-detail-title');
    const thead = document.getElementById('dash-detail-thead');
    const tbody = document.getElementById('dash-detail-tbody');
    titleEl.textContent = '👷 Chi tiết CTV / Thợ';
    thead.innerHTML = '<tr><th>Telegram ID</th><th>Họ tên</th><th>Username</th><th>SĐT</th><th>Loại</th><th>Vai trò</th><th>Ca</th><th>Nợ phí</th><th>Trạng thái</th></tr>';
    if (dashWorkers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Chưa có dữ liệu</td></tr>';
        return;
    }
    tbody.innerHTML = dashWorkers.map(w => renderWorkerRow(w)).join('');
}
async function syncCtv(){
    const statusEl = document.getElementById('ctvStatus');
    statusEl.textContent = 'Đang đồng bộ từ nhóm Telegram...';
    try {
        const res = await fetch('/api_master.php?action=admin_sync_telegram_group', { credentials: 'same-origin' });
        const json = await res.json();
        statusEl.textContent = json.status === 'success'
            ? `Đồng bộ xong ${json.synced || 0} admin/thành viên.`
            : 'Lỗi đồng bộ: ' + (json.message || 'Unknown');
        await loadCtv();
    } catch(e) {
        statusEl.textContent = 'Lỗi đồng bộ: ' + e.message;
    }
}
async function addCtv(){
    const workerId = document.getElementById('ctvTelegramId').value.replace(/\D/g, '');
    const name = document.getElementById('ctvName').value.trim();
    const phone = document.getElementById('ctvPhone').value.replace(/\D/g, '');
    const workerType = document.getElementById('ctvType').value;
    const role = document.getElementById('ctvRole').value;
    const statusEl = document.getElementById('ctvStatus');
    if(!workerId || workerId.length < 6) return alert('Vui lòng nhập Telegram ID hợp lệ (tối thiểu 6 chữ số)');
    if(!name) return alert('Vui lòng nhập họ tên');
    if(phone.length < 8) return alert('Vui lòng nhập số điện thoại hợp lệ');
    statusEl.textContent = 'Đang lưu...';
    try {
        const res = await fetch('/api_master.php?action=admin_register_worker', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN },
            body: JSON.stringify({ worker_id: workerId, name, phone, worker_type: workerType, role })
        });
        const json = await res.json();
        statusEl.textContent = json.status === 'success' ? 'Đã đăng ký/cập nhật thợ.' : 'Lỗi: ' + (json.message || 'Unknown');
        if(json.status === 'success'){
            document.getElementById('ctvTelegramId').value='';
            document.getElementById('ctvName').value='';
            document.getElementById('ctvPhone').value='';
            await loadCtv();
        }
    } catch(e) {
        statusEl.textContent = 'Lỗi gửi dữ liệu: ' + e.message;
    }
}
function escHtml(str){ return (str || '').replace(/[<>&"']/g, c => ({'<':'\u0026lt;','>':'\u0026gt;','\u0026':'\u0026amp;','"':'\u0026quot;',"'":'\u0026#39;'}[c])); }
function fmtMoney(n){ return parseInt(n || 0, 10).toLocaleString('vi-VN') + ' ₫'; }
function renderQr(){
    const data = getData(LS.qr);
    const tbody = document.getElementById('qrList');
    tbody.innerHTML = data.map((q, i) => `<tr><td>${q.name}</td><td>${q.code}</td><td>${q.value}</td>
        <td><button class="btn-small btn-blue" onclick="showQr('${q.code}')">Xem</button>
        <button class="btn-small btn-red" onclick="deleteItem('${LS.qr}', ${i}, renderQr)">Xóa</button></td></tr>`).join('');
    updateDashboard();
}
function generateQr(){
    const name = document.getElementById('qrName').value;
    const code = document.getElementById('qrCode').value || 'KM'+Date.now();
    const value = document.getElementById('qrValue').value;
    if(!name) return alert('Vui lòng nhập tên chương trình');
    const data = getData(LS.qr); data.push({name, code, value});
    setData(LS.qr, data);
    document.getElementById('qrName').value=''; document.getElementById('qrCode').value=''; document.getElementById('qrValue').value='';
    showQr(code); renderQr();
}
function showQr(code){
    const url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(code);
    document.getElementById('qrPreview').innerHTML = `<img src="${url}" alt="QR"><p><strong>${code}</strong></p>`;
}
function printQr(){ window.print(); }
function calcHoaDon(){
    const price = parseFloat(document.getElementById('hdPrice').value) || 0;
    const qty = parseFloat(document.getElementById('hdQty').value) || 0;
    document.getElementById('hdTotal').textContent = (price*qty).toLocaleString('vi-VN') + ' ₫';
}
function createHoaDon(){
    const name = document.getElementById('hdName').value;
    const product = document.getElementById('hdProduct').value;
    const price = parseFloat(document.getElementById('hdPrice').value) || 0;
    const qty = parseFloat(document.getElementById('hdQty').value) || 1;
    if(!name || !product) return alert('Vui lòng nhập đủ thông tin');
    const total = price * qty;
    const data = getData(LS.hd);
    const item = {name, product, price, qty, total, date: new Date().toLocaleString('vi-VN')};
    data.push(item); setData(LS.hd, data);
    document.getElementById('hdPrintArea').innerHTML = `
        <div style="text-align:center; border-bottom:2px solid #d4a76e; padding-bottom:15px; margin-bottom:20px;">
            <h2>HÓA ĐƠN BÁN LẺ</h2>
            <p>Điện Máy Hiếu - Khu vực Lấp Vò, Đồng Tháp</p>
        </div>
        <p><strong>Khách hàng:</strong> ${name}</p>
        <p><strong>Ngày:</strong> ${item.date}</p>
        <table style="width:100%; margin:15px 0;">
            <tr><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr>
            <tr><td>${product}</td><td>${qty}</td><td>${price.toLocaleString('vi-VN')} ₫</td><td>${total.toLocaleString('vi-VN')} ₫</td></tr>
        </table>
        <p style="text-align:right; font-size:18px;"><strong>TỔNG CỘNG: ${total.toLocaleString('vi-VN')} ₫</strong></p>
        <p style="text-align:center; margin-top:40px;">Cảm ơn quý khách đã mua hàng tại Điện Máy Hiếu!</p>
        <div style="text-align:center; margin-top:20px;"><button class="btn" onclick="window.print()">In hóa đơn</button></div>`;
    renderHoaDon();
    document.getElementById('hdName').value=''; document.getElementById('hdProduct').value='';
    document.getElementById('hdPrice').value=''; document.getElementById('hdQty').value='1';
    calcHoaDon();
}
function renderHoaDon(){
    const data = getData(LS.hd);
    const tbody = document.getElementById('hdList');
    tbody.innerHTML = data.map((h, i) => `<tr><td>${h.name}</td><td>${h.product}</td><td>${h.total.toLocaleString('vi-VN')} ₫</td><td>${h.date}</td>
        <td><button class="btn-small btn-red" onclick="deleteItem('${LS.hd}', ${i}, renderHoaDon)">Xóa</button></td></tr>`).join('');
    updateDashboard();
}
function calcGtgt(){
    const price = parseFloat(document.getElementById('gtgtPrice').value) || 0;
    const qty = parseFloat(document.getElementById('gtgtQty').value) || 0;
    const net = price * qty;
    const vat = net * 0.1;
    document.getElementById('gtgtNet').textContent = net.toLocaleString('vi-VN') + ' ₫';
    document.getElementById('gtgtVat').textContent = vat.toLocaleString('vi-VN') + ' ₫';
    document.getElementById('gtgtTotal').textContent = (net + vat).toLocaleString('vi-VN') + ' ₫';
}
function createGtgt(){
    const name = document.getElementById('gtgtName').value;
    const tax = document.getElementById('gtgtTax').value;
    const address = document.getElementById('gtgtAddress').value;
    const product = document.getElementById('gtgtProduct').value;
    const price = parseFloat(document.getElementById('gtgtPrice').value) || 0;
    const qty = parseFloat(document.getElementById('gtgtQty').value) || 1;
    if(!name || !tax || !address || !product) return alert('Vui lòng nhập đủ thông tin (tên, MST, địa chỉ, sản phẩm)');
    const net = price * qty;
    const vat = net * 0.1;
    const total = net + vat;
    const date = new Date().toLocaleString('vi-VN');
    const data = getData(LS.gtgt);
    data.push({name, tax, address, product, price, qty, net, vat, total, date});
    setData(LS.gtgt, data);
    document.getElementById('gtgtPrintArea').innerHTML = `
        <div style="text-align:center; border-bottom:2px solid #d4a76e; padding-bottom:15px; margin-bottom:20px;">
            <h2>HÓA ĐƠN GIÁ TRỊ GIA TĂNG</h2>
            <p>Điện Máy Hiếu - Mã số thuế: 1402228630</p>
        </div>
        <p><strong>Đơn vị mua:</strong> ${name}</p>
        <p><strong>Mã số thuế:</strong> ${tax}</p>
        <p><strong>Địa chỉ:</strong> ${address}</p>
        <p><strong>Ngày:</strong> ${date}</p>
        <table style="width:100%; margin:15px 0;">
            <tr><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr>
            <tr><td>${product}</td><td>${qty}</td><td>${price.toLocaleString('vi-VN')} ₫</td><td>${net.toLocaleString('vi-VN')} ₫</td></tr>
            <tr><td colspan="3"><strong>Tổng tiền hàng</strong></td><td><strong>${net.toLocaleString('vi-VN')} ₫</strong></td></tr>
            <tr><td colspan="3"><strong>Thuế GTGT 10%</strong></td><td><strong>${vat.toLocaleString('vi-VN')} ₫</strong></td></tr>
            <tr><td colspan="3"><strong>TỔNG CỘNG</strong></td><td><strong>${total.toLocaleString('vi-VN')} ₫</strong></td></tr>
        </table>
        <div style="text-align:center; margin-top:20px;"><button class="btn" onclick="window.print()">In hóa đơn GTGT</button></div>`;
    document.getElementById('gtgtName').value=''; document.getElementById('gtgtTax').value='';
    document.getElementById('gtgtAddress').value=''; document.getElementById('gtgtProduct').value='';
    document.getElementById('gtgtPrice').value=''; document.getElementById('gtgtQty').value='1'; calcGtgt();
    document.getElementById('gtgtTaxStatus').textContent = '';
    updateDashboard();
}

async function checkTaxCode(){
    const tax = document.getElementById('gtgtTax').value.trim();
    const statusEl = document.getElementById('gtgtTaxStatus');
    if(!tax) return statusEl.textContent = 'Vui lòng nhập mã số thuế.';
    statusEl.textContent = 'Đang kiểm tra...';
    try {
        const response = await fetch('https://api.vietqr.io/v2/business/' + encodeURIComponent(tax), { method: 'GET' });
        if(!response.ok) throw new Error('Không tra cứu được');
        const data = await response.json();
        if(data.code === '00' && data.data){
            const d = data.data;
            document.getElementById('gtgtName').value = d.name || '';
            document.getElementById('gtgtAddress').value = d.address || '';
            statusEl.textContent = '✅ Tìm thấy: ' + (d.name || '') + ' - ' + (d.address || '');
        } else {
            statusEl.textContent = '⚠️ Không tìm thấy MST này. Anh có thể nhập thủ công.';
        }
    } catch(e) {
        statusEl.textContent = '⚠️ Lỗi tra cứu: ' + e.message + '. Anh nhập thủ công.';
    }
}
function docSoTien(so) {
    if (so === 0) return 'Không đồng';
    const mangSo = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    function doc3So(baso) {
        let tram = Math.floor(baso / 100);
        let chuc = Math.floor((baso % 100) / 10);
        let donvi = baso % 10;
        let kq = '';
        if (tram === 0 && chuc === 0 && donvi === 0) return '';
        if (tram !== 0) {
            kq += mangSo[tram] + ' trăm ';
            if (chuc === 0 && donvi !== 0) kq += 'lẻ ';
        }
        if (chuc !== 0 && chuc !== 1) {
            kq += mangSo[chuc] + ' mươi ';
            if (chuc === 0 && donvi !== 0) kq = kq + ' lẻ ';
        }
        if (chuc === 1) kq += 'mười ';
        switch (donvi) {
            case 1:
                if (chuc !== 0 && chuc !== 1) kq += 'mốt';
                else kq += 'một';
                break;
            case 5:
                if (chuc === 0) kq += 'năm';
                else kq += 'lăm';
                break;
            default:
                if (donvi !== 0) kq += mangSo[donvi];
                break;
        }
        return kq;
    }
    let s = Math.abs(so).toString();
    while (s.length % 3 !== 0) s = '0' + s;
    let mang = [];
    for (let i = 0; i < s.length; i += 3) {
        mang.push(parseInt(s.substr(i, 3)));
    }
    const mangDonVi = ['', ' nghìn', ' triệu', ' tỷ', ' nghìn tỷ', ' triệu tỷ'];
    let kq = '';
    let viTri = mang.length;
    for (let i = 0; i < mang.length; i++) {
        viTri--;
        let temp = doc3So(mang[i]);
        if (temp !== '') {
            kq += temp + mangDonVi[viTri] + ' ';
        }
    }
    kq = kq.trim();
    if (kq === '') return 'Không đồng';
    kq = kq.charAt(0).toUpperCase() + kq.slice(1) + ' đồng';
    return kq.replace(/\s+/g, ' ');
}

function previewHoaDon(){
    const name = document.getElementById('hdName').value;
    const product = document.getElementById('hdProduct').value;
    const price = parseFloat(document.getElementById('hdPrice').value) || 0;
    const qty = parseFloat(document.getElementById('hdQty').value) || 1;
    if(!name || !product) return alert('Vui lòng nhập đủ thông tin (tên khách hàng, sản phẩm)');
    const total = price * qty;
    const now = new Date();
    const formattedDate = `Ngày ${String(now.getDate()).padStart(2, '0')} tháng ${String(now.getMonth() + 1).padStart(2, '0')} năm ${now.getFullYear()}`;
    const dStr = `${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()}`;
    const tStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
    
    document.getElementById('hdPrintArea').innerHTML = `
        <div style="border: 2px dashed #ff4d4d; padding: 20px; border-radius: 8px; background: #fff9f9; position: relative; margin-top:20px;">
            <div style="color: red; border: 2px dashed red; padding: 8px; text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase;">
                ⚠️ HÓA ĐƠN NHÁP (DRAFT) - CHƯA CÓ GIÁ TRỊ THANH TOÁN
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 15px;">
                <div>
                    <h2 style="margin:0; color:#333;">HÓA ĐƠN BẢN LẺ (BẢN NHÁP)</h2>
                    <p style="margin:5px 0 0 0; font-size:14px; color:#555;"><strong>Đơn vị bán:</strong> Điện Máy Hiếu</p>
                    <p style="margin:3px 0 0 0; font-size:13px; color:#666;">Địa chỉ: 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp</p>
                </div>
                <div style="text-align: right;">
                    <p style="margin:0; font-size:14px;">Ký hiệu: <strong>BL-NHAP</strong></p>
                    <p style="margin:3px 0 0 0; font-size:14px;">Số: <strong>0000000</strong></p>
                </div>
            </div>
            <p><strong>Khách hàng:</strong> ${name}</p>
            <p><strong>Ngày lập:</strong> ${formattedDate}</p>
            <table style="width:100%; border-collapse:collapse; margin:15px 0;">
                <thead>
                    <tr style="background:#f2f2f2;">
                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Sản phẩm</th>
                        <th style="border:1px solid #ddd; padding:8px; text-align:center;">SL</th>
                        <th style="border:1px solid #ddd; padding:8px; text-align:right;">Đơn giá</th>
                        <th style="border:1px solid #ddd; padding:8px; text-align:right;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border:1px solid #ddd; padding:8px;">${product}</td>
                        <td style="border:1px solid #ddd; padding:8px; text-align:center;">${qty}</td>
                        <td style="border:1px solid #ddd; padding:8px; text-align:right;">${price.toLocaleString('vi-VN')} ₫</td>
                        <td style="border:1px solid #ddd; padding:8px; text-align:right;">${total.toLocaleString('vi-VN')} ₫</td>
                    </tr>
                </tbody>
            </table>
            <p style="text-align:right; font-size:18px; margin-top:20px;"><strong>TỔNG CỘNG: ${total.toLocaleString('vi-VN')} ₫</strong></p>
            <div style="display: flex; justify-content: space-between; margin-top: 40px; border-top: 1px dashed #ccc; padding-top: 20px;">
                <div style="text-align: center; width: 45%;">
                    <p style="margin: 0; font-weight: bold;">NGƯỜI MUA HÀNG</p>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #777;">(Ký, ghi rõ họ tên)</p>
                </div>
                <div style="text-align: center; width: 45%;">
                    <p style="margin: 0; font-weight: bold;">NGƯỜI BÁN HÀNG</p>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #777;">(Ký, đóng dấu)</p>
                    <div style="border: 2px solid red; color: red; display: inline-block; padding: 5px; margin-top: 10px; font-weight: bold; border-radius: 4px; font-size: 11px;">
                        KÝ BỞI: ĐIỆN MÁY HIẾU<br>Ngày ký: ${dStr} ${tStr}
                    </div>
                </div>
            </div>
            <div style="text-align:center; margin-top:30px;"><button class="btn" onclick="window.print()">In hóa đơn nháp</button></div>
        </div>`;
}

function previewGtgt(){
    const name = document.getElementById('gtgtName').value;
    const tax = document.getElementById('gtgtTax').value;
    const address = document.getElementById('gtgtAddress').value;
    const product = document.getElementById('gtgtProduct').value;
    const price = parseFloat(document.getElementById('gtgtPrice').value) || 0;
    const qty = parseFloat(document.getElementById('gtgtQty').value) || 1;
    if(!name || !tax || !address || !product) return alert('Vui lòng nhập đủ thông tin (tên, MST, địa chỉ, sản phẩm)');
    const net = price * qty;
    const vat = net * 0.1;
    const total = net + vat;
    const totalWords = docSoTien(total);
    const now = new Date();
    const formattedDate = `Ngày ${String(now.getDate()).padStart(2, '0')} tháng ${String(now.getMonth() + 1).padStart(2, '0')} năm ${now.getFullYear()}`;
    const dStr = `${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()}`;
    const tStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
    
    document.getElementById('gtgtPrintArea').innerHTML = `
        <div style="border: 2px dashed #ff4d4d; padding: 25px; border-radius: 12px; background: #fff9f9; position: relative; margin-top:20px;">
            <div style="color: red; border: 2px dashed red; padding: 10px; text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 25px; text-transform: uppercase;">
                ⚠️ HÓA ĐƠN NHÁP (DRAFT) - CHƯA CÓ GIÁ TRỊ PHÁP LÝ & THANH TOÁN
            </div>
            
            <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #d4a76e; padding-bottom: 15px; margin-bottom: 20px;">
                <div style="width: 65%;">
                    <h2 style="margin: 0 0 10px 0; color: #b8860b; font-size: 18px; text-transform: uppercase;">CÔNG TY TNHH MTV ĐIỆN MÁY HIẾU</h2>
                    <p style="margin: 3px 0; font-size: 13px;"><strong>Mã số thuế:</strong> 1402228630</p>
                    <p style="margin: 3px 0; font-size: 13px;"><strong>Địa chỉ:</strong> 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp</p>
                    <p style="margin: 3px 0; font-size: 13px;"><strong>Điện thoại:</strong> 0979.553.289 &nbsp;|&nbsp; <strong>Tài khoản:</strong> 115003056025 (VietinBank)</p>
                </div>
                <div style="text-align: right; width: 32%;">
                    <h2 style="margin: 0; font-size: 16px; color: #b8860b;">HÓA ĐƠN GIÁ TRỊ GIA TĂNG</h2>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: red; font-weight: bold;">(BẢN NHÁP - DRAFT)</p>
                    <p style="margin: 5px 0 0 0; font-size: 13px;">Ký hiệu: <strong>C26MTH</strong></p>
                    <p style="margin: 3px 0 0 0; font-size: 13px;">Số: <strong>0000000</strong></p>
                </div>
            </div>
            
            <div style="margin-bottom: 20px; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <h3 style="margin: 0 0 10px 0; font-size: 15px; text-transform: uppercase; color: #555;">Thông tin người mua hàng:</h3>
                <p style="margin: 5px 0;"><strong>Tên đơn vị mua:</strong> ${name}</p>
                <p style="margin: 5px 0;"><strong>Mã số thuế:</strong> ${tax}</p>
                <p style="margin: 5px 0;"><strong>Địa chỉ:</strong> ${address}</p>
                <p style="margin: 5px 0;"><strong>Ngày lập:</strong> ${formattedDate}</p>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 14px;">
                <thead>
                    <tr style="background: #fdf5e6; border-bottom: 2px solid #d4a76e;">
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 5%;">STT</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: left; width: 45%;">Tên hàng hóa, dịch vụ</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 10%;">ĐVT</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 10%;">SL</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: right; width: 15%;">Đơn giá</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: right; width: 15%;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">1</td>
                        <td style="border: 1px solid #ddd; padding: 10px;">${product}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">Lần</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${qty}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${price.toLocaleString('vi-VN')} ₫</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${net.toLocaleString('vi-VN')} ₫</td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td colspan="5" style="border: 1px solid #ddd; padding: 10px; text-align: right;">Cộng tiền hàng (chưa VAT):</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${net.toLocaleString('vi-VN')} ₫</td>
                    </tr>
                    <tr style="font-weight: bold; color: #555;">
                        <td colspan="5" style="border: 1px solid #ddd; padding: 10px; text-align: right;">Thuế suất GTGT: 10% &nbsp;|&nbsp; Tiền thuế GTGT:</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${vat.toLocaleString('vi-VN')} ₫</td>
                    </tr>
                    <tr style="font-weight: bold; background: #fffdf5; font-size: 15px;">
                        <td colspan="5" style="border: 1px solid #ddd; padding: 10px; text-align: right; color: #b8860b;">Tổng cộng tiền thanh toán:</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right; color: #b8860b;">${total.toLocaleString('vi-VN')} ₫</td>
                    </tr>
                </tbody>
            </table>
            
            <p style="font-style: italic; font-size: 13px; margin: 10px 0;"><strong>Số tiền viết bằng chữ:</strong> ${totalWords}</p>
            
            <div style="display: flex; justify-content: space-between; margin-top: 40px; border-top: 1px dashed #d4a76e; padding-top: 25px;">
                <div style="text-align: center; width: 45%;">
                    <p style="margin: 0; font-weight: bold; font-size: 14px;">NGƯỜI MUA HÀNG</p>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #777;">(Ký, ghi rõ họ tên)</p>
                </div>
                <div style="text-align: center; width: 45%;">
                    <p style="margin: 0; font-weight: bold; font-size: 14px;">NGƯỜI BÁN HÀNG</p>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #777;">(Ký, đóng dấu)</p>
                    <div style="border: 2px solid red; color: red; display: inline-block; padding: 8px; margin-top: 15px; font-weight: bold; border-radius: 4px; font-size: 11px; text-align: left; background: #fff5f5;">
                        <strong>Signature Verified</strong><br>
                        Ký bởi: CÔNG TY TNHH MTV ĐIỆN MÁY HIẾU<br>
                        Ngày ký: ${dStr} ${tStr}
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 35px;">
                <button class="btn" onclick="window.print()">In hóa đơn nháp GTGT</button>
            </div>
        </div>`;
}
function createHopDong(){
    const name = document.getElementById('hdldName').value;
    const phone = document.getElementById('hdldPhone').value;
    const id = document.getElementById('hdldId').value;
    const job = document.getElementById('hdldJob').value;
    const start = document.getElementById('hdldStart').value;
    if(!name || !phone || !id || !start) return alert('Vui lòng nhập đủ thông tin');
    const date = new Date(start);
    const dateStr = date.toLocaleDateString('vi-VN');
    const data = getData(LS.hdld);
    data.push({name, phone, id, job, start, created: new Date().toLocaleString('vi-VN')});
    setData(LS.hdld, data);
    document.getElementById('hdldPrintArea').innerHTML = `
        <div style="text-align:center; border-bottom:2px solid #000; padding-bottom:15px; margin-bottom:25px;">
            <h2>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h2>
            <p>Độc lập - Tự do - Hạnh phúc</p>
            <p>-------------------</p>
            <h3>HỢP ĐỒNG HỢP TÁC LAO ĐỘNG</h3>
        </div>
        <p>Số: <strong>HĐLD-${Date.now().toString().slice(-6)}</strong></p>
        <p>Căn cứ Bộ luật Dân sự, Luật Lao động hiện hành;</p>
        <p>Hôm nay, ngày ${dateStr}, tại Khu vực Lấp Vò, Đồng Tháp, chúng tôi gồm có:</p>
        <p><strong>BÊN A (Chủ cửa hàng):</strong> Ông Vinh Tran - Điện Máy Hiếu</p>
        <p><strong>BÊN B (Đối tác / Thợ):</strong> ${name}</p>
        <p><strong>Số CCCD:</strong> ${id}</p>
        <p><strong>Số điện thoại:</strong> ${phone}</p>
        <p><strong>Ngành đăng ký:</strong> ${job}</p>
        <p><strong>Thỏa thuận:</strong> Bên B nhận thi công, sửa chữa, lắp đặt các thiết bị thuộc lĩnh vực ${job} theo yêu cầu của Bên A.</p>
        <p><strong>Thời hạn:</strong> Từ ngày ${dateStr}, có hiệu lực đến khi hai bên thỏa thuận chấm dứt.</p>
        <p><strong>Thanh toán:</strong> Theo từng công việc, sau khi nghiệm thu và khách hàng xác nhận.</p>
        <p style="margin-top:30px;">Hợp đồng được lập thành 02 bản, mỗi bên giữ 01 bản, có giá trị pháp lý như nhau.</p>
        <div style="display:flex; justify-content:space-between; margin-top:60px;">
            <div style="text-align:center;"><p><strong>BÊN A</strong><br>(Ký tên)</p><p style="margin-top:60px;">Vinh Tran</p></div>
            <div style="text-align:center;"><p><strong>BÊN B</strong><br>(Ký tên)</p><p style="margin-top:60px;">${name}</p></div>
        </div>
        <div style="text-align:center; margin-top:30px;"><button class="btn" onclick="window.print()">In hợp đồng</button></div>`;
    document.getElementById('hdldName').value=''; document.getElementById('hdldPhone').value='';
    document.getElementById('hdldId').value=''; document.getElementById('hdldStart').value='';
    renderHopDong();
}
function renderHopDong(){
    const data = getData(LS.hdld);
    const tbody = document.getElementById('hdldList');
    tbody.innerHTML = data.map((h, i) => `<tr><td>${h.name}</td><td>${h.phone}</td><td>${h.id}</td><td>${h.job}</td><td>${h.start}</td>
        <td><button class="btn-small btn-blue" onclick="reprintHopDong(${i})">In lại</button>
        <button class="btn-small btn-red" onclick="deleteItem('${LS.hdld}', ${i}, renderHopDong)">Xóa</button></td></tr>`).join('');
    updateDashboard();
}
function reprintHopDong(i){
    const h = getData(LS.hdld)[i];
    document.getElementById('hdldName').value = h.name;
    document.getElementById('hdldPhone').value = h.phone;
    document.getElementById('hdldId').value = h.id;
    document.getElementById('hdldJob').value = h.job;
    document.getElementById('hdldStart').value = h.start;
    createHopDong();
}
function showDashboardDetail(type) {
    currentDashType = type;
    const detailEl = document.getElementById('dashboard-detail');
    const titleEl = document.getElementById('dash-detail-title');
    const thead = document.getElementById('dash-detail-thead');
    const tbody = document.getElementById('dash-detail-tbody');
    const previewEl = document.getElementById('dash-detail-preview');
    
    previewEl.innerHTML = '';
    detailEl.style.display = 'block';
    
    document.querySelectorAll('.dash-card').forEach(c => c.classList.remove('active-card'));
    
    if (type === 'ctv') {
        document.getElementById('cardCtv').classList.add('active-card');
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Đang tải...</td></tr>';
        loadDashWorkers();
        return;
    } else if (type === 'hoadon') {
        document.getElementById('cardHoaDon').classList.add('active-card');
        titleEl.textContent = '🧾 Chi tiết Hóa đơn đã lập';
        thead.innerHTML = '<tr><th>Loại</th><th>Khách hàng / Công ty</th><th>Tổng tiền</th><th>Ngày lập</th><th>Hành động</th></tr>';
        const hds = getData(LS.hd).map((h, i) => ({...h, type: 'retail', index: i}));
        const gts = getData(LS.gtgt).map((g, i) => ({...g, type: 'gtgt', index: i}));
        const combined = [...hds, ...gts].sort((a, b) => new Date(b.date) - new Date(a.date));
        
        if (combined.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Chưa có dữ liệu</td></tr>';
        } else {
            tbody.innerHTML = combined.map(h => `<tr>
                <td>${h.type === 'retail' ? '🧾 Bán lẻ' : '📑 GTGT'}</td>
                <td>${h.name}</td>
                <td>${h.total.toLocaleString('vi-VN')} ₫</td>
                <td>${h.date}</td>
                <td>
                    <button class="btn-small btn-blue" onclick="previewSavedInvoice('${h.type}', ${h.index})">Xem</button>
                    <button class="btn-small btn-red" onclick="deleteDashboardItem('${h.type}', ${h.index})">Xóa</button>
                </td>
            </tr>`).join('');
        }
    } else if (type === 'hopdong') {
        document.getElementById('cardHopDong').classList.add('active-card');
        titleEl.textContent = '📄 Chi tiết Hợp đồng lao động';
        thead.innerHTML = '<tr><th>Đối tác</th><th>SĐT</th><th>CCCD</th><th>Ngành</th><th>Ngày bắt đầu</th><th>Hành động</th></tr>';
        const data = getData(LS.hdld);
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Chưa có dữ liệu</td></tr>';
        } else {
            tbody.innerHTML = data.map((h, i) => `<tr>
                <td>${h.name}</td>
                <td>${h.phone}</td>
                <td>${h.id}</td>
                <td>${h.job}</td>
                <td>${h.start}</td>
                <td>
                    <button class="btn-small btn-blue" onclick="previewSavedContract(${i})">Xem</button>
                    <button class="btn-small btn-red" onclick="deleteDashboardItem('hdld', ${i})">Xóa</button>
                </td>
            </tr>`).join('');
        }
    } else if (type === 'qr') {
        document.getElementById('cardQr').classList.add('active-card');
        titleEl.textContent = '🎁 Chi tiết Chương trình Khuyến mãi';
        thead.innerHTML = '<tr><th>Chương trình</th><th>Mã</th><th>Giảm giá</th><th>Hành động</th></tr>';
        const data = getData(LS.qr);
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Chưa có dữ liệu</td></tr>';
        } else {
            tbody.innerHTML = data.map((q, i) => `<tr>
                <td>${q.name}</td>
                <td><code>${q.code}</code></td>
                <td>${q.value}</td>
                <td>
                    <button class="btn-small btn-blue" onclick="previewSavedQr('${q.code}')">Xem QR</button>
                    <button class="btn-small btn-red" onclick="deleteDashboardItem('qr', ${i})">Xóa</button>
                </td>
            </tr>`).join('');
        }
    }
}

function deleteDashboardItem(key, index) {
    if (!confirm('Anh chắc chắn muốn xóa mục này chứ?')) return;
    let lsKey = '';
    if (key === 'ctv') lsKey = LS.ctv;
    else if (key === 'retail') lsKey = LS.hd;
    else if (key === 'gtgt') lsKey = LS.gtgt;
    else if (key === 'hdld') lsKey = LS.hdld;
    else if (key === 'qr') lsKey = LS.qr;
    
    const data = getData(lsKey);
    data.splice(index, 1);
    setData(lsKey, data);
    
    if (key === 'ctv') renderCtv();
    else if (key === 'retail') renderHoaDon();
    else if (key === 'hdld') renderHopDong();
    else if (key === 'qr') renderQr();
    
    updateDashboard();
    showDashboardDetail(currentDashType);
}

function previewSavedInvoice(type, index) {
    const previewEl = document.getElementById('dash-detail-preview');
    if (type === 'retail') {
        const item = getData(LS.hd)[index];
        previewEl.innerHTML = `
            <div style="border: 1px solid #ccc; padding: 20px; border-radius: 8px; background: #fff; margin-top: 20px; max-width: 600px; margin-left: auto; margin-right: auto;">
                <div style="text-align:center; border-bottom:2px solid #d4a76e; padding-bottom:15px; margin-bottom:20px;">
                    <h2>HÓA ĐƠN BÁN LẺ</h2>
                    <p>Điện Máy Hiếu - Khu vực Lấp Vò, Đồng Tháp</p>
                </div>
                <p><strong>Khách hàng:</strong> ${item.name}</p>
                <p><strong>Ngày lập:</strong> ${item.date}</p>
                <table style="width:100%; border-collapse: collapse; margin: 15px 0;">
                    <thead>
                        <tr style="background:#f2f2f2;">
                            <th style="border:1px solid #ddd; padding:8px; text-align:left;">Sản phẩm</th>
                            <th style="border:1px solid #ddd; padding:8px; text-align:center;">SL</th>
                            <th style="border:1px solid #ddd; padding:8px; text-align:right;">Đơn giá</th>
                            <th style="border:1px solid #ddd; padding:8px; text-align:right;">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border:1px solid #ddd; padding:8px;">${item.product}</td>
                            <td style="border:1px solid #ddd; padding:8px; text-align:center;">${item.qty}</td>
                            <td style="border:1px solid #ddd; padding:8px; text-align:right;">${item.price.toLocaleString('vi-VN')} ₫</td>
                            <td style="border:1px solid #ddd; padding:8px; text-align:right;">${item.total.toLocaleString('vi-VN')} ₫</td>
                        </tr>
                    </tbody>
                </table>
                <p style="text-align:right; font-size:18px; margin-top:20px;"><strong>TỔNG CỘNG: ${item.total.toLocaleString('vi-VN')} ₫</strong></p>
                <div style="text-align:center; margin-top:20px;"><button class="btn" onclick="window.print()">In hóa đơn</button></div>
            </div>`;
    } else {
        const item = getData(LS.gtgt)[index];
        const totalWords = docSoTien(item.total);
        previewEl.innerHTML = `
            <div style="border: 1px solid #ccc; padding: 25px; border-radius: 12px; background: #fff; margin-top: 20px; max-width: 700px; margin-left: auto; margin-right: auto;">
                <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #d4a76e; padding-bottom: 15px; margin-bottom: 20px;">
                    <div style="width: 65%;">
                        <h2 style="margin: 0 0 10px 0; color: #b8860b; font-size: 18px; text-transform: uppercase;">CÔNG TY TNHH MTV ĐIỆN MÁY HIẾU</h2>
                        <p style="margin: 3px 0; font-size: 13px;"><strong>Mã số thuế:</strong> 1402228630</p>
                        <p style="margin: 3px 0; font-size: 13px;"><strong>Địa chỉ:</strong> 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp</p>
                    </div>
                    <div style="text-align: right; width: 32%;">
                        <h2 style="margin: 0; font-size: 16px; color: #b8860b;">HÓA ĐƠN GTGT</h2>
                        <p style="margin: 5px 0 0 0; font-size: 13px;">Ký hiệu: <strong>C26MTH</strong></p>
                        <p style="margin: 3px 0 0 0; font-size: 13px;">Số: <strong>${String(index + 1).padStart(7, '0')}</strong></p>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                    <p style="margin: 5px 0;"><strong>Tên đơn vị mua:</strong> ${item.name}</p>
                    <p style="margin: 5px 0;"><strong>Mã số thuế:</strong> ${item.tax}</p>
                    <p style="margin: 5px 0;"><strong>Địa chỉ:</strong> ${item.address}</p>
                    <p style="margin: 5px 0;"><strong>Ngày lập:</strong> ${item.date}</p>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 14px;">
                    <thead>
                        <tr style="background: #fdf5e6; border-bottom: 2px solid #d4a76e;">
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 5%;">STT</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: left; width: 45%;">Tên hàng hóa, dịch vụ</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 10%;">ĐVT</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 10%;">SL</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: right; width: 15%;">Đơn giá</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: right; width: 15%;">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">1</td>
                            <td style="border: 1px solid #ddd; padding: 10px;">${item.product}</td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">Lần</td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${item.qty}</td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${item.price.toLocaleString('vi-VN')} ₫</td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${item.net.toLocaleString('vi-VN')} ₫</td>
                        </tr>
                        <tr style="font-weight: bold;">
                            <td colspan="5" style="border: 1px solid #ddd; padding: 10px; text-align: right;">Cộng tiền hàng (chưa VAT):</td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${item.net.toLocaleString('vi-VN')} ₫</td>
                        </tr>
                        <tr style="font-weight: bold; color: #555;">
                            <td colspan="5" style="border: 1px solid #ddd; padding: 10px; text-align: right;">Thuếu suất GTGT: 10% &nbsp;|&nbsp; Tiền thuế GTGT:</td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${item.vat.toLocaleString('vi-VN')} ₫</td>
                        </tr>
                        <tr style="font-weight: bold; background: #fffdf5; font-size: 15px;">
                            <td colspan="5" style="border: 1px solid #ddd; padding: 10px; text-align: right; color: #b8860b;">Tổng cộng tiền thanh toán:</td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right; color: #b8860b;">${item.total.toLocaleString('vi-VN')} ₫</td>
                        </tr>
                    </tbody>
                </table>
                <p style="font-style: italic; font-size: 13px; margin: 10px 0;"><strong>Số tiền viết bằng chữ:</strong> ${totalWords}</p>
                
                <div style="display: flex; justify-content: space-between; margin-top: 30px; border-top: 1px dashed #d4a76e; padding-top: 20px;">
                    <div style="text-align: center; width: 45%;">
                        <p style="margin: 0; font-weight: bold; font-size: 14px;">NGƯỜI MUA HÀNG</p>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #777;">(Ký, ghi rõ họ tên)</p>
                    </div>
                    <div style="text-align: center; width: 45%;">
                        <p style="margin: 0; font-weight: bold; font-size: 14px;">NGƯỜI BÁN HÀNG</p>
                        <div style="border: 2px solid red; color: red; display: inline-block; padding: 8px; margin-top: 10px; font-weight: bold; border-radius: 4px; font-size: 11px; text-align: left; background: #fff5f5;">
                            <strong>Signature Verified</strong><br>
                            Ký bởi: CÔNG TY TNHH MTV ĐIỆN MÁY HIẾU<br>
                            Ngày ký: ${item.date}
                        </div>
                    </div>
                </div>
                <div style="text-align:center; margin-top:20px;"><button class="btn" onclick="window.print()">In hóa đơn</button></div>
            </div>`;
    }
}

function previewSavedContract(index) {
    const h = getData(LS.hdld)[index];
    const previewEl = document.getElementById('dash-detail-preview');
    previewEl.innerHTML = `
        <div style="border: 1px solid #ccc; padding: 25px; border-radius: 8px; background: #fff; margin-top: 20px; max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.6;">
            <h3 style="text-align: center; margin: 0 0 5px 0; text-transform: uppercase;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h3>
            <p style="text-align: center; margin: 0 0 20px 0; font-weight: bold; border-bottom: 1px solid #333; padding-bottom: 10px;">Độc lập - Tự do - Hạnh phúc</p>
            <h4 style="text-align: center; font-size: 18px; margin-bottom: 20px;">HỢP ĐỒNG HỢP TÁC KINH DOANH</h4>
            <p>Hôm nay, ngày ${h.start.split('-')[2]} tháng ${h.start.split('-')[1]} năm ${h.start.split('-')[0]}, chúng tôi gồm có:</p>
            <p><strong>Bên A (Chủ cửa hàng):</strong> CÔNG TY TNHH MTV ĐIỆN MÁY HIẾU</p>
            <p>Địa chỉ: 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp</p>
            <p><strong>Bên B (Đối tác liên kết):</strong> Ông/Bà: <strong>${h.name}</strong></p>
            <p>Số điện thoại: ${h.phone} &nbsp;|&nbsp; Số CCCD: ${h.id}</p>
            <p>Ngành nghề liên kết hợp tác: <strong>${h.job}</strong></p>
            <p><strong>Điều khoản chung:</strong> Bên B cam kết thực hiện đúng quy chế chất lượng dịch vụ của hệ thống Điện Máy Hiếu. Hai bên cùng chia sẻ doanh thu dựa trên từng đơn hàng hoàn thành thực tế.</p>
            <div style="display: flex; justify-content: space-between; margin-top: 40px;">
                <div style="text-align: center; width: 45%; font-weight: bold;">ĐẠI DIỆN BÊN B</div>
                <div style="text-align: center; width: 45%; font-weight: bold;">ĐẠI DIỆN BÊN A</div>
            </div>
            <div style="text-align:center; margin-top:30px;"><button class="btn" onclick="window.print()">In hợp đồng</button></div>
        </div>`;
}

function previewSavedQr(code) {
    const previewEl = document.getElementById('dash-detail-preview');
    const url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(code);
    previewEl.innerHTML = `
        <div style="text-align: center; border: 1px solid #ccc; padding: 20px; border-radius: 8px; background: #fff; max-width: 300px; margin-left: auto; margin-right: auto; margin-top: 20px;">
            <img src="${url}" alt="QR" style="margin-bottom: 10px;">
            <p style="font-weight: bold; margin: 0;">Mã: ${code}</p>
            <button class="btn" style="margin-top: 15px;" onclick="window.print()">In mã QR</button>
        </div>`;
}

function deleteItem(key, index, renderFn){
    const data = getData(key);
    data.splice(index, 1);
    setData(key, data);
    renderFn();
}
window.onload = function(){
    loadDashWorkers(); loadCtv(); renderQr(); renderHoaDon(); renderHopDong(); updateDashboard();
};
</script>

<?php endif; ?>
