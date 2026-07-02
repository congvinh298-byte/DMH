<?php
declare(strict_types=1);

require_once __DIR__ . '/demo_gate.php';

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
    <title>Admin Dashboard - Điện Máy Hiếu</title>
    <style>
        :root {
            --primary: #dc2626;
            --primary-dark: #b91c1c;
            --primary-light: #fef2f2;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f3f4f6;
            --card: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            --radius: 16px;
            --radius-sm: 12px;
            --radius-xs: 8px;
            --header-height: 64px;
            --sidebar-width: 260px;
        }
        * { box-sizing: border-box; font-family: "Times New Roman", Times, serif; }
        html, body { margin: 0; min-height: 100%; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }

        /* Login page */
        .login-page { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #fef2f2 0%, #fff 50%, #f3f4f6 100%); }
        .login-card { width: min(440px, 100%); background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow-xl); overflow: hidden; }
        .login-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; padding: 40px; text-align: center; }
        .login-header .logo { width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 16px; }
        .login-header h1 { margin: 0 0 8px; font-size: 24px; font-weight: 700; }
        .login-header p { margin: 0; opacity: 0.92; font-size: 14px; }
        .login-body { padding: 32px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 700; margin-bottom: 8px; color: #374151; }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid var(--border); border-radius: var(--radius-xs); font-size: 15px; transition: all .2s; font-family: "Times New Roman", Times, serif; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(220,38,38,0.1); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border-radius: var(--radius-xs); font-size: 15px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; transition: all .2s; }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 6px -1px rgba(220,38,38,0.3); }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(220,38,38,0.4); }
        .btn-secondary { background: #fff; color: var(--primary); border: 1px solid #fecaca; }
        .btn-secondary:hover { background: var(--primary-light); }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 8px 14px; font-size: 13px; }
        .btn-block { width: 100%; }
        .alert { padding: 14px 16px; border-radius: var(--radius-xs); margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        .alert-error { background: var(--primary-light); color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }

        /* App layout */
        .app { min-height: 100vh; display: flex; }
        .sidebar { width: var(--sidebar-width); background: var(--card); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 50; box-shadow: var(--shadow-lg); }
        .sidebar-brand { height: var(--header-height); display: flex; align-items: center; gap: 12px; padding: 0 24px; border-bottom: 1px solid var(--border); }
        .sidebar-brand .logo { width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; }
        .sidebar-brand .brand-text { font-weight: 700; font-size: 16px; }
        .sidebar-brand .brand-sub { font-size: 11px; color: var(--muted); }
        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: var(--radius-xs); cursor: pointer; transition: all .15s; margin-bottom: 4px; color: var(--text); text-decoration: none; }
        .nav-item:hover { background: #f3f4f6; }
        .nav-item.active { background: var(--primary-light); color: var(--primary); font-weight: 700; border-left: 3px solid var(--primary); }
        .nav-item .icon { font-size: 20px; width: 28px; text-align: center; }
        .sidebar-footer { padding: 16px; border-top: 1px solid var(--border); }
        .main { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .topbar { height: var(--header-height); background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 28px; position: sticky; top: 0; z-index: 40; }
        .topbar-title { font-size: 20px; font-weight: 700; }
        .topbar-user { display: flex; align-items: center; gap: 12px; }
        .user-badge { display: flex; align-items: center; gap: 10px; background: #f9fafb; border: 1px solid var(--border); padding: 6px 14px; border-radius: 999px; }
        .user-badge .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
        .content { padding: 28px; max-width: 1400px; }

        /* Cards & panels */
        .card { background: var(--card); border-radius: var(--radius-sm); box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; }
        .card-body { padding: 24px; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-size: 18px; font-weight: 700; margin: 0; }

        /* Zone selection */
        .zones-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; }
        .zone-card { background: var(--card); border-radius: var(--radius); padding: 32px; box-shadow: var(--shadow); border: 1px solid var(--border); cursor: pointer; transition: all .25s; position: relative; overflow: hidden; }
        .zone-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
        .zone-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--primary); opacity: 0; transition: opacity .2s; }
        .zone-card:hover::before { opacity: 1; }
        .zone-icon { width: 64px; height: 64px; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 20px; background: var(--primary-light); color: var(--primary); }
        .zone-title { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
        .zone-desc { color: var(--muted); font-size: 15px; margin: 0 0 20px; }
        .zone-stats { display: flex; gap: 16px; }
        .zone-stat { background: #f9fafb; border-radius: var(--radius-xs); padding: 10px 16px; font-size: 13px; }
        .zone-stat strong { color: var(--primary); font-size: 16px; }

        /* Stats row */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: var(--card); border-radius: var(--radius-sm); padding: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; align-items: center; gap: 16px; }
        .stat-icon { width: 48px; height: 48px; border-radius: var(--radius-xs); display: inline-flex; align-items: center; justify-content: center; font-size: 22px; }
        .stat-info h3 { margin: 0; font-size: 13px; color: var(--muted); font-weight: 600; }
        .stat-info .value { font-size: 24px; font-weight: 700; margin-top: 4px; }

        /* Tables */
        .table-wrap { overflow-x: auto; border-radius: var(--radius-xs); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead { background: #f9fafb; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 700; color: #374151; font-size: 13px; text-transform: uppercase; letter-spacing: 0.3px; }
        tbody tr:hover { background: #fafafa; }
        tbody tr:last-child td { border-bottom: none; }
        .cell-actions { display: flex; gap: 8px; }
        .status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .status.active { background: #dcfce7; color: #166534; }
        .status.active::before { background: #16a34a; }
        .status.pending { background: #fef3c7; color: #92400e; }
        .status.pending::before { background: #f59e0b; }
        .status.rejected { background: #fee2e2; color: #991b1b; }
        .status.rejected::before { background: #ef4444; }

        /* Empty state */
        .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }

        /* Forms */
        .form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px; }
        .form-row-2 { grid-template-columns: repeat(2, 1fr); }
        .form-row-4 { grid-template-columns: repeat(4, 1fr); }
        .form-label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #374151; }
        .form-hint { font-size: 12px; color: var(--muted); margin-top: 4px; }

        /* QR grid */
        .qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; }
        .qr-item { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-xs); padding: 16px; text-align: center; transition: all .15s; }
        .qr-item:hover { box-shadow: var(--shadow); }
        .qr-item img { width: 120px; height: 120px; margin-bottom: 10px; }
        .qr-item code { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; word-break: break-all; }
        .qr-item .meta { font-size: 12px; color: var(--muted); margin-bottom: 10px; }

        /* Toast */
        #toast-container { position: fixed; top: 24px; right: 24px; z-index: 100; display: flex; flex-direction: column; gap: 12px; }
        .toast { background: var(--card); border-radius: var(--radius-xs); padding: 16px 20px; box-shadow: var(--shadow-xl); border-left: 4px solid var(--primary); min-width: 280px; animation: slideIn .3s ease; }
        .toast.success { border-left-color: #16a34a; }
        .toast.error { border-left-color: #ef4444; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 90; display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--card); border-radius: var(--radius); padding: 28px; width: min(420px, 90%); box-shadow: var(--shadow-xl); }
        .modal h3 { margin: 0 0 12px; font-size: 18px; }
        .modal p { color: var(--muted); margin: 0 0 24px; }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }

        /* Tabs */
        .tabs { display: flex; gap: 8px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
        .tab { padding: 12px 20px; border: none; background: none; font-size: 14px; font-weight: 700; color: var(--muted); cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; }
        .tab:hover { color: var(--primary); }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .zone-pane { display: none; }
        .zone-pane.active { display: block; animation: fadeIn .3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Mobile */
        .mobile-menu-btn { display: none; background: none; border: none; font-size: 24px; cursor: pointer; }
        .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 45; }
        @media (max-width: 1024px) {
            .zones-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .form-row, .form-row-2, .form-row-4 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .3s; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-backdrop.active { display: block; }
            .main { margin-left: 0; }
            .mobile-menu-btn { display: block; }
            .stats-row { grid-template-columns: 1fr; }
            .topbar { padding: 0 16px; }
            .content { padding: 16px; }
            .topbar-title { display: none; }
        }
        .hidden { display: none !important; }
        .section-title { font-size: 24px; font-weight: 700; margin: 0 0 8px; }
        .section-subtitle { color: var(--muted); margin: 0 0 24px; }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<div class="login-page">
    <div class="login-card">
        <div class="login-header">
            <div class="logo">&#128722;</div>
            <h1>Đăng nhập Admin</h1>
            <p>Điện Máy Hiếu - Trang quản trị</p>
        </div>
        <div class="login-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= esc_html($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="email">Tài khoản admin</label>
                    <input id="email" name="email" type="text" class="form-control" autocomplete="username" required autofocus placeholder="Nhập email hoặc username">
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required placeholder="Nhập mật khẩu">
                </div>
                <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">Đăng nhập</button>
            </form>
        </div>
    </div>
</div>

<?php else: ?>

<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="logo">&#128722;</span>
            <div>
                <div class="brand-text">Điện Máy Hiếu</div>
                <div class="brand-sub">Admin Dashboard</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item active" data-zone="dashboard" onclick="navigate('dashboard')">
                <span class="icon">&#127968;</span>
                <span>Tổng quan</span>
            </div>
            <div class="nav-item" data-zone="stores" onclick="navigate('stores')">
                <span class="icon">&#127978;</span>
                <span>Quản lý cửa hàng</span>
            </div>
            <div class="nav-item" data-zone="workers" onclick="navigate('workers')">
                <span class="icon">&#128736;</span>
                <span>Quản lý người lao động</span>
            </div>
            <div class="nav-item" data-zone="promotions" onclick="navigate('promotions')">
                <span class="icon">&#127873;</span>
                <span>Chương trình khuyến mãi</span>
            </div>
            <div class="nav-item" onclick="window.open('bct_portal.php','_blank')">
                <span class="icon">&#128203;</span>
                <span>Cổng báo cáo BCT</span>
            </div>
            <div class="nav-item" onclick="window.open('/','_blank')">
                <span class="icon">&#127760;</span>
                <span>Xem website</span>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="admin_xxx.php?logout=1" class="btn btn-secondary btn-block btn-sm">&#128682; Đăng xuất</a>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    <main class="main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">&#9776;</button>
                <h1 class="topbar-title" id="pageTitle">Tổng quan</h1>
            </div>
            <div class="topbar-user">
                <div class="user-badge">
                    <span class="avatar"><?= mb_substr($displayName, 0, 1, 'UTF-8') ?></span>
                    <span><?= $displayName ?></span>
                </div>
            </div>
        </header>

        <div class="content">
            <!-- DASHBOARD ZONE -->
            <div id="zone-dashboard" class="zone-pane active">
                <h2 class="section-title">Tổng quan hệ thống</h2>
                <p class="section-subtitle">Chào mừng trở lại. Chọn khu vực bên dưới để bắt đầu quản lý.</p>

                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#fef2f2;color:#dc2626;">&#127978;</div>
                        <div class="stat-info">
                            <h3>Cửa hàng</h3>
                            <div class="value" id="stat-stores">-</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#eff6ff;color:#2563eb;">&#128736;</div>
                        <div class="stat-info">
                            <h3>Người lao động</h3>
                            <div class="value" id="stat-workers">-</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#ecfdf5;color:#16a34a;">&#127873;</div>
                        <div class="stat-info">
                            <h3>Khuyến mãi</h3>
                            <div class="value" id="stat-promos">-</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#fef3c7;color:#d97706;">&#128722;</div>
                        <div class="stat-info">
                            <h3>Đơn hàng</h3>
                            <div class="value" id="stat-orders">-</div>
                        </div>
                    </div>
                </div>

                <div class="zones-grid">
                    <div class="zone-card" onclick="navigate('stores')">
                        <div class="zone-icon">&#127978;</div>
                        <h3 class="zone-title">Quản lý cửa hàng</h3>
                        <p class="zone-desc">Phê duyệt đăng ký cửa hàng mới, xem danh sách và trạng thái hoạt động.</p>
                        <div class="zone-stats">
                            <div class="zone-stat">Chờ duyệt: <strong id="zone-stores-pending">-</strong></div>
                            <div class="zone-stat">Tổng: <strong id="zone-stores-total">-</strong></div>
                        </div>
                    </div>
                    <div class="zone-card" onclick="navigate('workers')">
                        <div class="zone-icon">&#128736;</div>
                        <h3 class="zone-title">Quản lý người lao động</h3>
                        <p class="zone-desc">Xem danh sách thợ/cộng tác viên, quản lý công nợ và phân công.</p>
                        <div class="zone-stats">
                            <div class="zone-stat">Đang hoạt động: <strong id="zone-workers-active">-</strong></div>
                            <div class="zone-stat">Tổng: <strong id="zone-workers-total">-</strong></div>
                        </div>
                    </div>
                    <div class="zone-card" onclick="navigate('promotions')">
                        <div class="zone-icon">&#127873;</div>
                        <h3 class="zone-title">Chương trình khuyến mãi</h3>
                        <p class="zone-desc">Tạo voucher, tạo mã QR khuyến mãi và theo dõi lượt sử dụng.</p>
                        <div class="zone-stats">
                            <div class="zone-stat">Voucher: <strong id="zone-vouchers-total">-</strong></div>
                            <div class="zone-stat">QR: <strong id="zone-qr-total">-</strong></div>
                        </div>
                    </div>
                    <div class="zone-card" onclick="window.open('bct_portal.php','_blank')">
                        <div class="zone-icon">&#128203;</div>
                        <h3 class="zone-title">Cổng báo cáo BCT</h3>
                        <p class="zone-desc">Truy cập cổng thông tin đối soát với Bộ Công Thương.</p>
                        <div class="zone-stats">
                            <div class="zone-stat">Xem báo cáo</div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- STORES ZONE -->
            <div id="zone-stores" class="zone-pane">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <button class="btn btn-secondary btn-sm" onclick="navigate('dashboard')">&#8592; Quay lại</button>
                </div>
                <h2 class="section-title">Quản lý cửa hàng</h2>
                <p class="section-subtitle">Phê duyệt đăng ký cửa hàng và theo dõi trạng thái hoạt động.</p>

                <div class="tabs">
                    <button class="tab active" data-stores-tab="pending" onclick="switchStoresTab('pending')">Chờ duyệt</button>
                    <button class="tab" data-stores-tab="all" onclick="switchStoresTab('all')">Tất cả cửa hàng</button>
                </div>

                <div id="stores-tab-pending" class="tab-pane active">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Cửa hàng chờ duyệt</div></div>
                        <div class="card-body" id="pending-stores-table">
                            <div class="empty-state"><div class="icon">&#9203;</div><div>Đang tải danh sách...</div></div>
                        </div>
                    </div>
                </div>

                <div id="stores-tab-all" class="tab-pane">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Tất cả cửa hàng</div></div>
                        <div class="card-body" id="all-stores-table">
                            <div class="empty-state"><div class="icon">&#9203;</div><div>Đang tải danh sách...</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WORKERS ZONE -->
            <div id="zone-workers" class="zone-pane">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <button class="btn btn-secondary btn-sm" onclick="navigate('dashboard')">&#8592; Quay lại</button>
                </div>
                <h2 class="section-title">Quản lý người lao động</h2>
                <p class="section-subtitle">Danh sách thợ, cộng tác viên và tài xế trên hệ thống.</p>

                <div class="tabs">
                    <button class="tab active" data-workers-tab="list" onclick="switchWorkersTab('list')">Danh sách</button>
                    <button class="tab" data-workers-tab="payments" onclick="switchWorkersTab('payments')">Thanh toán</button>
                </div>

                <div id="workers-tab-list" class="tab-pane active">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Người lao động</div></div>
                        <div class="card-body" id="workers-table">
                            <div class="empty-state"><div class="icon">&#9203;</div><div>Đang tải danh sách...</div></div>
                        </div>
                    </div>
                </div>

                <div id="workers-tab-payments" class="tab-pane">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Lịch sử thanh toán</div></div>
                        <div class="card-body" id="worker-payments-table">
                            <div class="empty-state"><div class="icon">&#9203;</div><div>Đang tải danh sách...</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROMOTIONS ZONE -->
            <div id="zone-promotions" class="zone-pane">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <button class="btn btn-secondary btn-sm" onclick="navigate('dashboard')">&#8592; Quay lại</button>
                </div>
                <h2 class="section-title">Chương trình khuyến mãi & QR</h2>
                <p class="section-subtitle">Tạo và quản lý voucher cùng mã QR khuyến mãi.</p>

                <div class="tabs">
                    <button class="tab active" data-promo-tab="vouchers" onclick="switchPromoTab('vouchers')">Voucher</button>
                    <button class="tab" data-promo-tab="qr" onclick="switchPromoTab('qr')">QR Coupons</button>
                    <button class="tab" data-promo-tab="create-voucher" onclick="switchPromoTab('create-voucher')">+ Tạo Voucher</button>
                    <button class="tab" data-promo-tab="create-qr" onclick="switchPromoTab('create-qr')">+ Tạo QR</button>
                </div>

                <div id="promo-tab-vouchers" class="tab-pane active">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Danh sách voucher</div></div>
                        <div class="card-body" id="vouchers-table">
                            <div class="empty-state"><div class="icon">&#9203;</div><div>Đang tải danh sách...</div></div>
                        </div>
                    </div>
                </div>

                <div id="promo-tab-qr" class="tab-pane">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Danh sách QR Coupons</div></div>
                        <div class="card-body" id="qr-coupons-grid">
                            <div class="empty-state"><div class="icon">&#9203;</div><div>Đang tải danh sách...</div></div>
                        </div>
                    </div>
                </div>

                <div id="promo-tab-create-voucher" class="tab-pane">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Tạo voucher mới</div></div>
                        <div class="card-body">
                            <form id="voucher-form" onsubmit="return saveVoucher(event)">
                                <div class="form-row">
                                    <div>
                                        <label class="form-label">Mã voucher</label>
                                        <input type="text" name="code" class="form-control" required placeholder="VD: SUMMER2026">
                                    </div>
                                    <div>
                                        <label class="form-label">Loại giảm giá</label>
                                        <select name="type" class="form-control">
                                            <option value="fixed">Giảm số tiền (VNĐ)</option>
                                            <option value="percent">Giảm phần trăm (%)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Giá trị</label>
                                        <input type="number" name="value" class="form-control" min="1" required placeholder="VD: 50000">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div>
                                        <label class="form-label">Đơn hàng tối thiểu (VNĐ)</label>
                                        <input type="number" name="min_order" class="form-control" min="0" value="0">
                                    </div>
                                    <div>
                                        <label class="form-label">Giảm tối đa (VNĐ)</label>
                                        <input type="number" name="max_discount" class="form-control" min="0" value="0">
                                        <div class="form-hint">Bỏ 0 nếu không giới hạn</div>
                                    </div>
                                    <div>
                                        <label class="form-label">Số lượt sử dụng</label>
                                        <input type="number" name="usage_limit" class="form-control" min="1" value="100">
                                    </div>
                                </div>
                                <div class="form-row form-row-2">
                                    <div>
                                        <label class="form-label">Hạn sử dụng</label>
                                        <input type="datetime-local" name="expires_at" class="form-control">
                                        <div class="form-hint">Để trống nếu không có hạn</div>
                                    </div>
                                    <div>
                                        <label class="form-label">Trạng thái</label>
                                        <select name="is_active" class="form-control">
                                            <option value="1">Kích hoạt</option>
                                            <option value="0">Vô hiệu hóa</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary" style="margin-top:8px;">Tạo voucher</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="promo-tab-create-qr" class="tab-pane">
                    <div class="card">
                        <div class="card-header"><div class="card-title">Tạo mã QR khuyến mãi</div></div>
                        <div class="card-body">
                            <form id="qr-form" onsubmit="return generateQR(event)">
                                <div class="form-row">
                                    <div>
                                        <label class="form-label">Số lượng mã</label>
                                        <input type="number" name="count" class="form-control" min="1" max="100" value="1" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Loại</label>
                                        <select name="type" class="form-control">
                                            <option value="discount">Giảm giá trực tiếp</option>
                                            <option value="prize">Quà / Vòng quay</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Giá trị (VNĐ hoặc %)</label>
                                        <input type="number" name="value" class="form-control" min="0" value="0" required>
                                    </div>
                                </div>
                                <div class="form-row form-row-2">
                                    <div>
                                        <label class="form-label">Mô tả</label>
                                        <input type="text" name="description" class="form-control" placeholder="VD: Khuyến mãi tháng 6">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary" style="margin-top:8px;">Tạo mã QR</button>
                            </form>
                            <div id="qr-created-result" style="margin-top:24px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <footer style="text-align:center;margin-top:40px;font-size:13px;color:var(--muted);">
                &copy; <?= date('Y') ?> Điện Máy Hiếu - Admin Dashboard
            </footer>
        </div>
    </main>
</div>


<!-- Toast container -->
<div id="toast-container"></div>

<!-- Confirm modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <h3 id="confirmTitle">Xác nhận</h3>
        <p id="confirmMessage">Bạn có chắc chắn?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal()">Hủy</button>
            <button class="btn btn-danger" id="confirmBtn">Xác nhận</button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';
const API_BASE = 'api_master.php';
const pageTitles = {
    dashboard: 'Tổng quan',
    stores: 'Quản lý cửa hàng',
    workers: 'Quản lý người lao động',
    promotions: 'Chương trình khuyến mãi'
};

let cachedStores = [];
let cachedWorkers = [];
let cachedPromos = {};

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
        const params = new URLSearchParams();
        for (const [k, v] of Object.entries(data)) {
            params.append(k, v);
        }
        opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        opts.body = params.toString();
    }
    const res = await fetch(url, opts);
    return res.json();
}

function navigate(zone) {
    if (zone === 'dashboard') {
        loadDashboardStats();
    } else if (zone === 'stores') {
        loadStores('pending');
        loadStores('all');
    } else if (zone === 'workers') {
        loadWorkers();
        loadWorkerPayments();
    } else if (zone === 'promotions') {
        loadVouchers();
        loadQRCoupons();
    }

    document.querySelectorAll('.zone-pane').forEach(el => el.classList.remove('active'));
    document.getElementById('zone-' + zone).classList.add('active');
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const nav = document.querySelector(`.nav-item[data-zone="${zone}"]`);
    if (nav) nav.classList.add('active');
    document.getElementById('pageTitle').textContent = pageTitles[zone] || 'Admin';
    window.scrollTo(0, 0);
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarBackdrop').classList.toggle('active');
}

function fmtMoney(n) {
    return Number(n || 0).toLocaleString('vi-VN') + ' VNĐ';
}

function fmtDate(d) {
    if (!d) return '';
    const date = new Date(d.replace(' ', 'T'));
    return isNaN(date) ? d : date.toLocaleDateString('vi-VN');
}

function badge(status) {
    const map = { active: 'Hoạt động', pending: 'Chờ duyệt', rejected: 'Từ chối' };
    const label = map[(status || '').toLowerCase()] || (status || 'Không rõ');
    return `<span class="status ${(status || '').toLowerCase()}">${label}</span>`;
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(100%)';
        setTimeout(() => el.remove(), 300);
    }, 3000);
}

let modalResolve = null;
function confirmDialog(title, message) {
    return new Promise(resolve => {
        modalResolve = resolve;
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmModal').classList.add('active');
        const btn = document.getElementById('confirmBtn');
        btn.onclick = () => { closeModal(); resolve(true); };
    });
}
function closeModal() {
    document.getElementById('confirmModal').classList.remove('active');
    if (modalResolve) { modalResolve(false); modalResolve = null; }
}

async function loadDashboardStats() {
    const [stores, workers, promos] = await Promise.all([
        api('admin_store_list', {}, 'GET').catch(() => ({ data: [] })),
        api('admin_workers', {}, 'GET').catch(() => ({ data: [] })),
        api('admin_voucher_list', {}, 'GET').catch(() => ({ vouchers: [], qr_coupons: [] }))
    ]);
    cachedStores = stores.data || [];
    cachedWorkers = workers.data || [];
    cachedPromos = promos;

    document.getElementById('stat-stores').textContent = cachedStores.length;
    document.getElementById('stat-workers').textContent = cachedWorkers.length;
    document.getElementById('stat-promos').textContent = (promos.vouchers?.length || 0) + (promos.qr_coupons?.length || 0);
    document.getElementById('stat-orders').textContent = '-';

    document.getElementById('zone-stores-pending').textContent = cachedStores.filter(s => (s.status || '').toLowerCase() === 'pending').length;
    document.getElementById('zone-stores-total').textContent = cachedStores.length;
    document.getElementById('zone-workers-active').textContent = cachedWorkers.filter(w => !w.is_blocked).length;
    document.getElementById('zone-workers-total').textContent = cachedWorkers.length;
    document.getElementById('zone-vouchers-total').textContent = promos.vouchers?.length || 0;
    document.getElementById('zone-qr-total').textContent = promos.qr_coupons?.length || 0;
}


function switchStoresTab(tab) {
    document.querySelectorAll('[data-stores-tab]').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-stores-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('#zone-stores .tab-pane').forEach(el => el.classList.remove('active'));
    document.getElementById('stores-tab-' + tab).classList.add('active');
}

async function loadStores(filter) {
    const res = await api('admin_store_list', {}, 'GET');
    cachedStores = res.data || [];
    const data = filter === 'pending' ? cachedStores.filter(s => (s.status || '').toLowerCase() === 'pending') : cachedStores;
    const container = document.getElementById(filter === 'pending' ? 'pending-stores-table' : 'all-stores-table');

    if (!data.length) {
        container.innerHTML = `<div class="empty-state"><div class="icon">&#127978;</div><div>Không có cửa hàng nào.</div></div>`;
        return;
    }

    let html = `<div class="table-wrap"><table><thead><tr>
        <th>ID</th><th>Cửa hàng</th><th>Chủ / SĐT</th><th>MST</th><th>Trạng thái</th><th>Đơn / Doanh thu</th><th>Đăng ký</th><th>Thao tác</th>
    </tr></thead><tbody>`;
    for (const s of data) {
        const actions = (s.status || '').toLowerCase() === 'pending'
            ? `<button class="btn btn-success btn-sm" onclick="approveStore(${s.id})">Duyệt</button> <button class="btn btn-danger btn-sm" onclick="rejectStore(${s.id})">Từ chối</button>`
            : `<button class="btn btn-secondary btn-sm" onclick="window.open('${s.report_url || '#'}','_blank')">Báo cáo</button>`;
        html += `<tr>
            <td>#${s.id}</td>
            <td><strong>${s.store_name || ''}</strong><br><span style="color:var(--muted);font-size:12px;">${s.store_type || ''}</span></td>
            <td>${s.owner_name || '-'} <br><span style="font-size:12px;">${s.phone || ''}</span></td>
            <td>${s.tax_code || '-'}</td>
            <td>${badge(s.status)}</td>
            <td>${s.order_count || 0} đơn<br><span style="font-size:12px;">${fmtMoney(s.total_sales || 0)}</span></td>
            <td>${fmtDate(s.created_at)}</td>
            <td><div class="cell-actions">${actions}</div></td>
        </tr>`;
    }
    html += `</tbody></table></div>`;
    container.innerHTML = html;
}

async function approveStore(id) {
    if (!await confirmDialog('Phê duyệt cửa hàng', 'Bạn có chắc muốn duyệt cửa hàng này?')) return;
    const res = await api('admin_approve_store', { id });
    showToast(res.message || 'Đã phê duyệt', res.status === 'ok' ? 'success' : 'error');
    loadStores('pending');
    loadStores('all');
    loadDashboardStats();
}

async function rejectStore(id) {
    if (!await confirmDialog('Từ chối cửa hàng', 'Bạn có chắc muốn từ chối cửa hàng này?')) return;
    const res = await api('admin_reject_store', { id });
    showToast(res.message || 'Đã từ chối', res.status === 'ok' ? 'success' : 'error');
    loadStores('pending');
    loadStores('all');
    loadDashboardStats();
}

function switchWorkersTab(tab) {
    document.querySelectorAll('[data-workers-tab]').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-workers-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('#zone-workers .tab-pane').forEach(el => el.classList.remove('active'));
    document.getElementById('workers-tab-' + tab).classList.add('active');
}

async function loadWorkers() {
    const res = await api('admin_workers', {}, 'GET').catch(() => ({ data: [] }));
    cachedWorkers = res.data || [];
    const container = document.getElementById('workers-table');
    if (!cachedWorkers.length) {
        container.innerHTML = `<div class="empty-state"><div class="icon">&#128736;</div><div>Chưa có người lao động nào.</div></div>`;
        return;
    }
    let html = `<div class="table-wrap"><table><thead><tr>
        <th>ID</th><th>Tên</th><th>Vai trò</th><th>SĐT</th><th>Đánh giá</th><th>Trạng thái</th><th>Tham gia</th>
    </tr></thead><tbody>`;
    for (const w of cachedWorkers) {
        const status = w.is_blocked ? '<span class="status rejected">Bị khóa</span>' : '<span class="status active">Hoạt động</span>';
        html += `<tr>
            <td>#${w.telegram_user_id || w.id}</td>
            <td><strong>${w.telegram_name || w.name || '-'}</strong></td>
            <td>${w.role || w.worker_type || '-'}</td>
            <td>${w.phone || '-'}</td>
            <td>${w.rating_score || 5} ⭐ (${w.rating_count || 0})</td>
            <td>${status}</td>
            <td>${fmtDate(w.created_at)}</td>
        </tr>`;
    }
    html += `</tbody></table></div>`;
    container.innerHTML = html;
}

async function loadWorkerPayments() {
    const res = await api('admin_worker_payments', {}, 'GET').catch(() => ({ data: [] }));
    const data = res.data || [];
    const container = document.getElementById('worker-payments-table');
    if (!data.length) {
        container.innerHTML = `<div class="empty-state"><div class="icon">&#128178;</div><div>Chưa có giao dịch thanh toán.</div></div>`;
        return;
    }
    let html = `<div class="table-wrap"><table><thead><tr>
        <th>ID</th><th>Thợ</th><th>Số tiền</th><th>Phương thức</th><th>Mã tham chiếu</th><th>Ngày</th>
    </tr></thead><tbody>`;
    for (const p of data) {
        html += `<tr>
            <td>#${p.id}</td>
            <td>${p.worker_name || p.telegram_name || '-'}</td>
            <td>${fmtMoney(p.amount)}</td>
            <td>${p.method || '-'}</td>
            <td><code>${p.reference || '-'}</code></td>
            <td>${fmtDate(p.created_at)}</td>
        </tr>`;
    }
    html += `</tbody></table></div>`;
    container.innerHTML = html;
}


function switchPromoTab(tab) {
    document.querySelectorAll('[data-promo-tab]').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-promo-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('#zone-promotions .tab-pane').forEach(el => el.classList.remove('active'));
    document.getElementById('promo-tab-' + tab).classList.add('active');
}

async function loadVouchers() {
    const res = await api('admin_voucher_list', {}, 'GET').catch(() => ({ vouchers: [] }));
    cachedPromos.vouchers = res.vouchers || [];
    const container = document.getElementById('vouchers-table');
    if (!res.vouchers?.length) {
        container.innerHTML = `<div class="empty-state"><div class="icon">&#127873;</div><div>Chưa có voucher nào.</div></div>`;
        return;
    }
    let html = `<div class="table-wrap"><table><thead><tr>
        <th>Mã</th><th>Loại</th><th>Giá trị</th><th>Đã dùng / Tổng</th><th>Đơn tối thiểu</th><th>Hết hạn</th><th>Trạng thái</th><th>Thao tác</th>
    </tr></thead><tbody>`;
    for (const v of res.vouchers) {
        const typeLabel = v.type === 'percent' ? 'Giảm %' : 'Giảm tiền';
        const valueLabel = v.type === 'percent' ? `${v.value}%` : fmtMoney(v.value);
        const used = `${v.used_count || 0} / ${v.usage_limit || v.max_uses || 1}`;
        const active = parseInt(v.is_active) ? '<span class="status active">Kích hoạt</span>' : '<span class="status rejected">Vô hiệu</span>';
        html += `<tr>
            <td><code>${v.code}</code></td>
            <td>${typeLabel}</td>
            <td>${valueLabel}</td>
            <td>${used}</td>
            <td>${fmtMoney(v.min_order || 0)}</td>
            <td>${v.expires_at ? fmtDate(v.expires_at) : 'Không hạn'}</td>
            <td>${active}</td>
            <td><button class="btn btn-danger btn-sm" onclick="deleteVoucher(${v.id})">Xóa</button></td>
        </tr>`;
    }
    html += `</tbody></table></div>`;
    container.innerHTML = html;
}

async function loadQRCoupons() {
    const res = await api('admin_voucher_list', {}, 'GET').catch(() => ({ qr_coupons: [] }));
    cachedPromos.qr_coupons = res.qr_coupons || [];
    const container = document.getElementById('qr-coupons-grid');
    if (!res.qr_coupons?.length) {
        container.innerHTML = `<div class="empty-state"><div class="icon">&#128247;</div><div>Chưa có mã QR nào.</div></div>`;
        return;
    }
    let html = `<div class="qr-grid">`;
    for (const q of res.qr_coupons) {
        const payload = `https://dienmayhieu.com/?voucher=${q.code}`;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(payload)}`;
        const used = parseInt(q.is_used) ? '<span class="status rejected">Đã dùng</span>' : '<span class="status active">Chưa dùng</span>';
        const valueLabel = q.type === 'prize' ? `Quà ${q.value}%` : `Giảm ${fmtMoney(q.value || q.discount_amount || 0)}`;
        html += `<div class="qr-item">
            <img src="${qrUrl}" alt="QR ${q.code}" loading="lazy">
            <code>${q.code}</code>
            <div class="meta">${valueLabel}</div>
            <div class="meta">${used}</div>
            <button class="btn btn-danger btn-sm" style="margin-top:8px;" onclick="deleteQR(${q.id})">Xóa</button>
        </div>`;
    }
    html += `</div>`;
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
    showToast(res.message || (res.status === 'ok' ? 'Đã tạo voucher' : 'Lỗi'), res.status === 'ok' ? 'success' : 'error');
    if (res.status === 'ok') {
        form.reset();
        switchPromoTab('vouchers');
        loadVouchers();
        loadDashboardStats();
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
        let html = `<h3 style="margin-bottom:16px;">Đã tạo ${res.codes.length} mã QR:</h3><div class="qr-grid">`;
        for (const code of res.codes) {
            const payload = `https://dienmayhieu.com/?voucher=${code}`;
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(payload)}`;
            html += `<div class="qr-item"><img src="${qrUrl}" alt="QR"><code>${code}</code></div>`;
        }
        html += `</div>`;
        result.innerHTML = html;
        loadQRCoupons();
        loadDashboardStats();
    } else {
        result.innerHTML = `<div class="alert alert-error">${res.message || 'Lỗi tạo QR'}</div>`;
    }
    return false;
}

async function deleteVoucher(id) {
    if (!await confirmDialog('Xóa voucher', 'Bạn có chắc muốn xóa voucher này?')) return;
    const res = await api('admin_delete_voucher', { id });
    showToast(res.message || (res.status === 'ok' ? 'Đã xóa' : 'Lỗi'), res.status === 'ok' ? 'success' : 'error');
    loadVouchers();
    loadDashboardStats();
}

async function deleteQR(id) {
    if (!await confirmDialog('Xóa mã QR', 'Bạn có chắc muốn xóa mã QR này?')) return;
    const res = await api('admin_delete_qr', { id });
    showToast(res.message || (res.status === 'ok' ? 'Đã xóa' : 'Lỗi'), res.status === 'ok' ? 'success' : 'error');
    loadQRCoupons();
    loadDashboardStats();
}

window.addEventListener('DOMContentLoaded', () => {
    loadDashboardStats();
});
</script>

<?php endif; ?>
</body>
</html>
