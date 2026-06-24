<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/api/core.php';

$logout = isset($_GET['logout']);
if ($logout) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin_xxx.php');
    exit;
}

$error = '';
$user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email']) && !empty($_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $pdo = pdo();
        $stmt = $pdo->prepare("SELECT id, email, fullname, role, password_hash FROM users WHERE email = ? AND role = 'admin' AND (status IS NULL OR status = 'active') LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_fullname'] = $user['fullname'];
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin_xxx.php');
            exit;
        } else {
            $error = 'Email hoáº·c máº­t kháº©u khÃ´ng Ä‘Ãºng.';
        }
    } catch (Exception $e) {
        $error = 'Lá»—i há»‡ thá»‘ng: ' . $e->getMessage();
    }
} elseif (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    try {
        $pdo = pdo();
        $stmt = $pdo->prepare("SELECT id, email, fullname, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $user = null;
    }
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']) && $user !== false && $user !== null;
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Chá»£ Láº¥p VÃ² Online</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f4f6;color:#111827;font-family:Arial,sans-serif;padding:20px}
        main{width:min(480px,100%);background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:24px;box-shadow:0 12px 30px rgba(17,24,39,.08)}
        h1{font-size:21px;margin:0 0 8px;color:#dc2626}
        p{margin:0 0 18px;color:#4b5563;line-height:1.5}
        label{display:block;font-size:13px;font-weight:700;margin:14px 0 6px}
        input{width:100%;padding:11px 12px;border:1px solid #9ca3af;border-radius:6px;font:inherit}
        button{width:100%;margin-top:18px;padding:11px 14px;border:1px solid #b91c1c;border-radius:6px;background:#dc2626;color:#fff;font:inherit;font-weight:700;cursor:pointer}
        .error{padding:10px 12px;border:1px solid #fecaca;border-radius:6px;background:#fef2f2;color:#991b1b;margin-bottom:14px;line-height:1.4}
        .success{padding:10px 12px;border:1px solid #a7f3d0;border-radius:6px;background:#ecfdf3;color:#047857;margin-bottom:14px;line-height:1.4}
        .note{font-size:12px;margin-top:14px;margin-bottom:0;color:#6b7280}
        .dashboard-links{display:grid;gap:10px;margin-top:16px}
        .dashboard-links a{display:block;padding:12px;border:1px solid #d1d5db;border-radius:6px;background:#f9fafb;color:#111827;text-decoration:none;font-weight:700}
        .dashboard-links a:hover{background:#f3f4f6}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb}
        .topbar a{color:#dc2626;font-size:13px;text-decoration:none}
    </style>
</head>
<body>
<main>
<?php if ($isLoggedIn): ?>
    <div class="topbar">
        <span><strong>Admin</strong>: <?= esc_html($user['fullname'] ?? $user['email']) ?></span>
        <a href="admin_xxx.php?logout=1">ÄÄƒng xuáº¥t</a>
    </div>
    <div class="success">ÄÄƒng nháº­p thÃ nh cÃ´ng.</div>
    <h1>Dashboard</h1>
    <p>ChÃ o má»«ng <strong><?= esc_html($user['fullname'] ?? $user['email']) ?></strong> Ä‘áº¿n trang quáº£n trá»‹ Chá»£ Láº¥p VÃ² Online.</p>
    <div class="dashboard-links">
        <a href="bct_portal.php" target="_blank">ðŸ“‹ Cá»•ng bÃ¡o cÃ¡o BCT</a>
        <a href="admin/chat_logs.php" target="_blank">ðŸ’¬ Chat logs</a>
        <a href="/" target="_blank">ðŸŒ Xem website</a>
    </div>
<?php else: ?>
    <h1>ÄÄƒng nháº­p Admin</h1>
    <p>Chá»£ Láº¥p VÃ² Online - Trang quáº£n trá»‹</p>
    <?php if ($error !== ''): ?>
        <div class="error"><?= esc_html($error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <label for="email">Email admin</label>
        <input id="email" name="email" type="email" autocomplete="username" required autofocus placeholder="qltmdt@moit.gov.vn">
        <label for="password">Máº­t kháº©u</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Admin@123">
        <button type="submit">ÄÄƒng nháº­p</button>
    </form>
    <p class="note">TÃ i khoáº£n test BCT: qltmdt@moit.gov.vn / Admin@123</p>
<?php endif; ?>
</main>
</body>
</html>
