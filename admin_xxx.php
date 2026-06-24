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

            // Kiểm tra cột status có tồn tại không
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
    } catch (Throwable $e) {
        $user = null;
        $error = 'Lỗi tải thông tin admin: ' . $e->getMessage();
    }
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']) && $user !== false && $user !== null;
$displayName = esc_html($user['fullname'] ?? $user['email'] ?? 'Admin');
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
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: min(520px, 100%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 32px;
            text-align: center;
        }
        .card-header h1 {
            margin: 12px 0 4px;
            font-size: 22px;
            font-weight: 700;
        }
        .card-header p {
            margin: 0;
            opacity: 0.92;
            font-size: 14px;
        }
        .logo-mark {
            width: 56px;
            height: 56px;
            background: rgba(255,255,255,0.18);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }
        .card-body {
            padding: 28px;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }
        .alert-error { background: var(--primary-light); color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin: 16px 0 6px;
            color: #374151;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color .15s, box-shadow .15s;
        }
        input[type="email"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
        }
        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px 16px;
            border: none;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s, transform .05s;
        }
        button:hover { background: var(--primary-dark); }
        button:active { transform: translateY(1px); }
        .note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 18px;
            text-align: center;
        }

        /* Dashboard */
        .dashboard {
            width: min(960px, 100%);
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .topbar h1 {
            margin: 0;
            font-size: 22px;
        }
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
        }
        .user-chip .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-outline {
            background: #fff;
            color: var(--primary);
            border-color: #fecaca;
        }
        .btn-outline:hover { background: var(--primary-light); }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .tile {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
            text-decoration: none;
            color: inherit;
            transition: transform .12s, box-shadow .12s, border-color .12s;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: #fecaca;
        }
        .tile-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: var(--primary-light);
        }
        .tile-title {
            font-weight: 700;
            font-size: 16px;
            margin: 0;
        }
        .tile-desc {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }
        footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--muted);
        }
        @media (max-width: 480px) {
            .card-body, .card-header { padding: 22px; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .user-chip { width: 100%; justify-content: space-between; }
        }
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
                <div class="tile-icon">📋</div>
                <div class="tile-title">Cổng báo cáo BCT</div>
                <div class="tile-desc">Truy cập cổng thông tin Bộ Công Thương.</div>
            </a>
            <a class="tile" href="admin/chat_logs.php" target="_blank" rel="noopener">
                <div class="tile-icon">💬</div>
                <div class="tile-title">Chat logs</div>
                <div class="tile-desc">Xem lịch sử chat và tương tác.</div>
            </a>
            <a class="tile" href="/" target="_blank" rel="noopener">
                <div class="tile-icon">🌐</div>
                <div class="tile-title">Xem website</div>
                <div class="tile-desc">Mở trang chủ dienmayhieu.com.</div>
            </a>
            <a class="tile" href="admin_xxx.php?logout=1">
                <div class="tile-icon">🚪</div>
                <div class="tile-title">Đăng xuất</div>
                <div class="tile-desc">Thoát khỏi trang quản trị.</div>
            </a>
        </div>

        <footer>
            © <?= date('Y') ?> Chợ Lấp Vò Online — Admin Dashboard
        </footer>
    </div>
</div>
<?php else: ?>
<div class="page">
    <div class="card">
        <div class="card-header">
            <div class="logo-mark">🔐</div>
            <h1>Đăng nhập Admin</h1>
            <p>Chợ Lấp Vò Online - Trang quản trị</p>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= esc_html($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <label for="email">Email admin</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus placeholder="qltmdt@moit.gov.vn">

                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••">

                <button type="submit">Đăng nhập</button>
            </form>
            <p class="note">Tài khoản test: qltmdt@moit.gov.vn / Admin@123</p>
        </div>
    </div>
</div>
<?php endif; ?>
</body>
</html>
