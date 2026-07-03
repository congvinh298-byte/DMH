<?php
ob_start();
/**
 * Demo / password-protection gate for CV0014 compliance.
 * Website must operate in demo/test mode with password access before official approval.
 *
 * Bypass: BCT report portal endpoints (so reporting still works without the demo password).
 */

if (PHP_SAPI === 'cli') {
    return;
}

$uri = $_SERVER['REQUEST_URI'] ?? '';
$script = $_SERVER['SCRIPT_NAME'] ?? '';

// Allow BCT reporting endpoints and API endpoints without demo auth.
$bctPaths = ['/bct_portal.php', '/api_baocao_bct.php', '/api_master.php', '/api/'];
foreach ($bctPaths as $bctPath) {
    if (strpos($uri, $bctPath) === 0 || strpos($script, $bctPath) !== false) {
        return;
    }
}

session_start();

if (!empty($_SESSION['demo_auth'])) {
    return;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demo_user'], $_POST['demo_pass'])) {
    $user = (string)$_POST['demo_user'];
    $pass = (string)$_POST['demo_pass'];
    if ($user === 'anhthien' && $pass === 'Anhthien369@') {
        $_SESSION['demo_auth'] = true;
        return;
    }
    $error = 'Sai tài khoản hoặc mật khẩu demo.';
}

header('HTTP/1.1 401 Unauthorized');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Điện Máy Hiếu - Chế độ thử nghiệm</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f4f6f8;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 16px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 420px;
            width: 100%;
            padding: 32px;
            text-align: center;
        }
        .logo { font-size: 48px; margin-bottom: 8px; }
        h1 { font-size: 20px; margin: 0 0 8px; color: #1a73e8; }
        p { font-size: 14px; line-height: 1.6; margin: 0 0 20px; color: #555; }
        .error { color: #d93025; font-size: 14px; margin-bottom: 12px; }
        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 15px;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #1a73e8;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #1557b0; }
        .hint { margin-top: 16px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">🔒</div>
        <h1>Điện Máy Hiếu</h1>
        <p>Website đang trong giai đoạn hoàn thiện hồ sơ Bộ Công Thương. Vui lòng đăng nhập bằng tài khoản demo để truy cập.</p>
        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <input type="text" name="demo_user" placeholder="Tài khoản" autocomplete="username" required>
            <input type="password" name="demo_pass" placeholder="Mật khẩu" autocomplete="current-password" required>
            <button type="submit">Đăng nhập demo</button>
        </form>
        <div class="hint">Chế độ thử nghiệm theo yêu cầu CV0014 - Bộ Công Thương</div>
    </div>
</body>
</html>
<?php
exit;
