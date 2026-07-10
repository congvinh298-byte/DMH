<?php
// Load env for site password check
function header_load_env($path)
{
    if (!is_file($path) || !is_readable($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        $line = trim(str_replace("\xEF\xBB\xBF", '', $line));
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        $first = substr($value, 0, 1);
        $last = substr($value, -1);
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}
header_load_env(__DIR__ . '/../../.env');

// Site-wide password protection check (BCT compliance)
if (isset($_ENV['SITE_PASSWORD_PROTECT']) && (string)$_ENV['SITE_PASSWORD_PROTECT'] === '1') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_POST['dth_site_pwd'])) {
        $sitePwd = (string)$_POST['dth_site_pwd'];
        $expectedPwd = isset($_ENV['SITE_PASSWORD']) ? $_ENV['SITE_PASSWORD'] : 'dth123';
        if ($sitePwd === $expectedPwd) {
            $_SESSION['dth_site_unlocked'] = true;
            setcookie('dth_site_unlocked', '1', time() + 30 * 86400, '/');
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $GLOBALS['dth_site_pwd_error'] = 'Mật khẩu truy cập không đúng!';
        }
    }
    $isUnlocked = !empty($_SESSION['dth_site_unlocked']) || !empty($_COOKIE['dth_site_unlocked']);
    if (!$isUnlocked) {
        http_response_code(403);
        $errorHtml = isset($GLOBALS['dth_site_pwd_error']) ? '<div style="color:#b91c1c;background:#fef2f2;border:1px solid #fee2e2;padding:12px;border-radius:8px;margin-bottom:15px;font-size:14px;text-align:center;">' . htmlspecialchars($GLOBALS['dth_site_pwd_error']) . '</div>' : '';
        echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Khu vực Thử nghiệm - Điện Máy Hiếu</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fef2f2;color:#111827;font-family:system-ui,-apple-system,sans-serif;padding:20px}
        main{width:min(440px,100%);background:#fff;border:1px solid #fed7aa;border-radius:16px;padding:30px 24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);text-align:center}
        .logo{width:80px;height:80px;margin-bottom:20px;border-radius:16px;object-fit:contain}
        h1{font-size:22px;margin:0 0 8px;font-weight:800}
        p{margin:0 0 20px;color:#4b5563;font-size:14px;line-height:1.6}
        input{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font:inherit;text-align:center;margin-bottom:15px}
        input:focus{outline:none;border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,0.1)}
        button{width:100%;padding:12px 14px;border:none;border-radius:8px;background:#ea580c;color:#fff;font:inherit;font-weight:700;cursor:pointer;box-shadow:0 4px 6px -1px rgba(234,88,12,0.2)}
        button:hover{background:#d97706}
    </style>
</head>
<body>
<main>
    <img src="/LOGO.png" alt="Logo" class="logo" onerror="this.src=\'data:image/svg+xml;utf8,<svg xmlns=\\\'http://www.w3.org/2000/svg\\\' width=\\\'80\\\' height=\\\'80\\\' viewBox=\\\'0 0 80 80\\\'>&lt;rect width=\\\x22100%\\\x22 height=\\\x22100%\\\x22 fill=\\\x22%23ea580c\\\x22/&gt;&lt;text x=\\\x2250%\\\x22 y=\\\x2255%\\\x22 font-family=\\\x22sans-serif\\\x22 font-size=\\\x2216\\\x22 fill=\\\x22white\\\x22 font-weight=\\\x22bold\\\x22 text-anchor=\\\x22middle\\\x22&gt;DTH&lt;/text&gt;</svg>\'">
    <h1>Khu vực Thử nghiệm</h1>
    <p>Website đang trong chế độ thử nghiệm nội bộ để cơ quan quản lý duyệt hồ sơ. Vui lòng nhập mật khẩu được cung cấp để tiếp tục.</p>
    ' . $errorHtml . '
    <form method="post">
        <input type="password" name="dth_site_pwd" placeholder="Nhập mật khẩu truy cập" required autofocus autocomplete="off">
        <button type="submit">Xác nhận truy cập</button>
    </form>
</main>
</body>
</html>';
        exit;
    }
}

/**
 * Layout chung cho cac trang phap ly - dienmayhieu.com
 */
if (!isset($PAGE_TITLE)) $PAGE_TITLE = 'Điện Máy Hiếu';
if (!isset($PAGE_DESC)) $PAGE_DESC = 'Điện Máy Hiếu - Dịch vụ gọi thợ và cửa hàng điện máy tại Lấp Vò';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($PAGE_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars($PAGE_DESC) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg:#f6f7f9; --panel:#fff; --line:#e5e7eb; --text:#111827; --muted:#667085; --brand:#dc2626; --dark:#111827; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fef2f2; color: var(--text); line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        .wrap { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }
        header { position: sticky; inset-block-start: 0; z-index: 10; background: #fff; border-block-end: 1px solid var(--line); }
        .head { min-height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 10px 0; }
        .logo { font-size: 20px; font-weight: 900; color: var(--brand); }
        .logo small { display: block; color: var(--muted); font-size: 12px; }
        .btn-page { display: inline-block; border: 0; border-radius: 8px; background: var(--brand); color: #fff; font-weight: 800; padding: 10px 16px; cursor: pointer; text-align: center; }
        .btn-page:hover { background: #b91c1c; color: #fff; }
        main { padding: 32px 0 48px; }
        .page-panel { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 10px 24px rgba(15,23,42,.06); padding: 32px; }
        .page-title { font-size: 28px; font-weight: 900; margin-block-end: 8px; color: var(--dark); }
        .page-subtitle { color: var(--muted); font-size: 15px; margin-block-end: 24px; }
        .page-content h2 { font-size: 20px; font-weight: 800; margin-block-start: 28px; margin-block-end: 12px; color: var(--dark); border-block-end: 2px solid var(--brand); padding-block-end: 8px; display: inline-block; }
        .page-content h3 { font-size: 17px; font-weight: 800; margin-block-start: 20px; margin-block-end: 10px; color: #374151; }
        .page-content p { margin-block-end: 12px; text-align: justify; }
        .page-content ul, .page-content ol { margin-block-end: 16px; padding-inline-start: 24px; }
        .page-content li { margin-block-end: 8px; }
        .page-content table { width: 100%; border-collapse: collapse; margin-block-end: 16px; }
        .page-content th, .page-content td { border: 1px solid var(--line); padding: 10px 12px; text-align: left; }
        .page-content th { background: #f8fafc; font-weight: 800; }
        .breadcrumb { font-size: 13px; color: var(--muted); margin-block-end: 16px; }
        .breadcrumb a { color: var(--brand); }
        .breadcrumb a:hover { text-decoration: underline; }
        footer { background: #111827; color: #d1d5db; padding: 24px 0; font-size: 13px; margin-block-start: auto; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 18px; }
        .footer-grid h3 { margin: 0 0 8px; color: #fff; font-size: 15px; }
        .footer-grid p { margin: 4px 0; }
        .footer-grid a { color: #d1d5db; }
        .footer-grid a:hover { color: #fff; }
        .footer-bottom { border-block-start: 1px solid #374151; margin-block-start: 18px; padding-block-start: 12px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .back-home { display: inline-flex; align-items: center; gap: 6px; margin-block-end: 16px; }
        @media(max-width:900px){ .footer-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width:620px){ .head { flex-wrap: wrap; } .page-panel { padding: 20px; } .footer-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <div class="wrap head">
        <a class="logo" href="/" style="display:flex; align-items:center; gap: 8px;">
            <img src="/LOGO.svg" alt="Logo Điện Máy Hiếu" style="height: 40px; border-radius: 6px; object-fit: contain;">
            <div>Điện Máy Hiếu<small>Dịch vụ gọi thợ & cửa hàng điện máy</small></div>
        </a>
        <a href="/" class="btn-page" style="font-size: 14px;">Ve trang chu</a>
    </div>
</header>
<main>
    <div class="wrap">
        <div class="breadcrumb">
            <a href="/">Trang chu</a> / <?= htmlspecialchars($PAGE_TITLE) ?>
        </div>
        <div class="page-panel">
            <h1 class="page-title"><?= htmlspecialchars($PAGE_TITLE) ?></h1>
            <p class="page-subtitle"><?= htmlspecialchars($PAGE_DESC) ?></p>
            <div class="page-content">