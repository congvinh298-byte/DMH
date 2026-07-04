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
                <div class="dash-card"><div class="number" id="dashCtv">0</div><div class="label">CTV / Thợ</div></div>
                <div class="dash-card"><div class="number" id="dashHoaDon">0</div><div class="label">Hóa đơn</div></div>
                <div class="dash-card"><div class="number" id="dashHopDong">0</div><div class="label">Hợp đồng</div></div>
                <div class="dash-card"><div class="number" id="dashQr">0</div><div class="label">QR khuyến mãi</div></div>
            </div>
        </div>

        <!-- CTV / THỢ -->
        <div id="ctv" class="section">
            <h3>👷 Quản lý CTV / Thợ sửa chữa</h3>
            <div class="grid-2">
                <div>
                    <div class="form-group"><label>Họ tên</label><input type="text" id="ctvName"></div>
                    <div class="form-group"><label>Số điện thoại</label><input type="text" id="ctvPhone"></div>
                    <div class="form-group"><label>Số CCCD</label><input type="text" id="ctvId"></div>
                    <div class="form-group"><label>Ngành đăng ký</label>
                        <select id="ctvJob">
                            <option value="Điện lạnh">Điện lạnh</option>
                            <option value="Điện tử">Điện tử</option>
                            <option value="Điện nước">Điện nước</option>
                            <option value="Tổng hợp">Tổng hợp</option>
                        </select>
                    </div>
                    <button onclick="addCtv()">Thêm CTV / Thợ</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Tên</th><th>SĐT</th><th>CCCD</th><th>Ngành</th><th></th></tr></thead>
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
            <button onclick="createNhapFromRetail()" class="btn-blue" style="margin-left: 10px;">Lưu hóa đơn nháp</button>
            <div class="print-area" id="hdPrintArea"></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Khách</th><th>Sản phẩm</th><th>Tổng</th><th>Ngày</th><th></th></tr></thead>
                    <tbody id="hdList"></tbody>
                </table>
            </div>

            <h3 style="margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px;">📝 Hóa đơn nháp</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Khách</th><th>Ghi chú / Sản phẩm</th><th>Tạm tính</th><th>Ngày</th><th>Hành động</th></tr></thead>
                    <tbody id="nhapList"></tbody>
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
            <button onclick="createNhapFromGtgt()" class="btn-blue" style="margin-left: 10px;">Lưu hóa đơn nháp</button>
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
        hoadon: 'Hóa đơn bán lẻ', gtgt: 'Hóa đơn GTGT', nhap: 'Hóa đơn nháp', hopdong: 'Hợp đồng lao động'
    };
    document.getElementById('page-title').textContent = titles[id];
    updateDashboard();
}
function updateDashboard(){
    document.getElementById('dashCtv').textContent = getData(LS.ctv).length;
    document.getElementById('dashHoaDon').textContent = getData(LS.hd).length + getData(LS.gtgt).length + getData(LS.nhap).length;
    document.getElementById('dashHopDong').textContent = getData(LS.hdld).length;
    document.getElementById('dashQr').textContent = getData(LS.qr).length;
}
function renderCtv(){
    const data = getData(LS.ctv);
    const tbody = document.getElementById('ctvList');
    tbody.innerHTML = data.map((c, i) => `<tr><td>${c.name}</td><td>${c.phone}</td><td>${c.id}</td><td>${c.job}</td>
        <td><button class="btn-small btn-red" onclick="deleteItem('${LS.ctv}', ${i}, renderCtv)">Xóa</button></td></tr>`).join('');
    updateDashboard();
}
function addCtv(){
    const name = document.getElementById('ctvName').value;
    const phone = document.getElementById('ctvPhone').value;
    const id = document.getElementById('ctvId').value;
    const job = document.getElementById('ctvJob').value;
    if(!name || !phone || !id) return alert('Vui lòng nhập đủ thông tin');
    const data = getData(LS.ctv); data.push({name, phone, id, job});
    setData(LS.ctv, data);
    document.getElementById('ctvName').value=''; document.getElementById('ctvPhone').value=''; document.getElementById('ctvId').value='';
    renderCtv();
}
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
function createNhap(name, note, price){
    if(!name) return alert('Vui lòng nhập tên khách');
    const data = getData(LS.nhap);
    data.push({name, note, price, date: new Date().toLocaleString('vi-VN')});
    setData(LS.nhap, data);
    renderNhap();
}
function createNhapFromRetail(){
    const name = document.getElementById('hdName').value;
    const prod = document.getElementById('hdProduct').value;
    const price = parseFloat(document.getElementById('hdPrice').value) || 0;
    const qty = parseInt(document.getElementById('hdQty').value) || 1;
    if(!name) return alert('Vui lòng nhập tên khách hàng');
    createNhap(name, prod + (qty > 1 ? ' (x' + qty + ')' : ''), price * qty);
    document.getElementById('hdName').value='';
    document.getElementById('hdProduct').value='';
    document.getElementById('hdPrice').value='';
    document.getElementById('hdQty').value=1;
    document.getElementById('hdTotal').textContent='0 ₫';
}
function createNhapFromGtgt(){
    const name = document.getElementById('gtgtName').value;
    const prod = document.getElementById('gtgtProduct').value;
    const price = parseFloat(document.getElementById('gtgtPrice').value) || 0;
    const qty = parseInt(document.getElementById('gtgtQty').value) || 1;
    if(!name) return alert('Vui lòng nhập tên công ty / khách hàng');
    createNhap(name, prod + (qty > 1 ? ' (x' + qty + ')' : ''), price * qty);
    document.getElementById('gtgtName').value='';
    document.getElementById('gtgtTax').value='';
    document.getElementById('gtgtTaxStatus').textContent='';
    document.getElementById('gtgtAddress').value='';
    document.getElementById('gtgtProduct').value='';
    document.getElementById('gtgtPrice').value='';
    document.getElementById('gtgtQty').value=1;
    document.getElementById('gtgtNet').textContent='0 ₫';
    document.getElementById('gtgtVat').textContent='0 ₫';
    document.getElementById('gtgtTotal').textContent='0 ₫';
}
function renderNhap(){
    const data = getData(LS.nhap);
    const tbody = document.getElementById('nhapList');
    tbody.innerHTML = data.map((n, i) => `<tr><td>${n.name}</td><td>${n.note}</td><td>${n.price.toLocaleString('vi-VN')} ₫</td><td>${n.date}</td>
        <td><button class="btn-small btn-blue" onclick="convertNhap(${i})">Chuyển hóa đơn</button>
        <button class="btn-small btn-red" onclick="deleteItem('${LS.nhap}', ${i}, renderNhap)">Xóa</button></td></tr>`).join('');
    updateDashboard();
}
function convertNhap(i){
    const data = getData(LS.nhap);
    const item = data[i];
    document.getElementById('hdName').value = item.name;
    document.getElementById('hdProduct').value = item.note;
    document.getElementById('hdPrice').value = item.price;
    document.getElementById('hdQty').value = 1;
    calcHoaDon();
    showSection('hoadon');
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
function deleteItem(key, index, renderFn){
    const data = getData(key);
    data.splice(index, 1);
    setData(key, data);
    renderFn();
}
window.onload = function(){
    renderCtv(); renderQr(); renderHoaDon(); renderNhap(); renderHopDong(); updateDashboard();
};
</script>

<?php endif; ?>
