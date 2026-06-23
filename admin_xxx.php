<?php
declare(strict_types=1);

/*
 * Dien Tu Hieu - emergency admin console.
 * Keep this file simple: authenticate, render a control panel, let api_master.php
 * do the database work through secured AJAX endpoints.
 */

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
date_default_timezone_set('Asia/Ho_Chi_Minh');

const DTH_DEFAULT_ADMIN_HASH = '$2y$12$PKMb6p4cl7PeYD7EEfpEg.NF2cqFJdgs/vnAXCHbiUYQbBDePJSOa'; // password_hash('845409', PASSWORD_BCRYPT)

function app_load_env($path = null)
{
    $path = $path ?? __DIR__ . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim(str_replace("\xEF\xBB\xBF", '', $line));
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function app_env($key, $default = '')
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return (string)$default;
    }
    return (string)$value;
}

function app_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function app_ensure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => app_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', app_is_https(), true);
    }
    session_start();
}

function app_admin_logout()
{
    app_ensure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
}

app_load_env();

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dth_admin_qr_src($code)
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=96x96&margin=6&data=' . rawurlencode((string)$code);
}

function dth_admin_password_ok($password)
{
    $hash = app_env('ADMIN_PASS_HASH', '');
    if ($hash !== '' && password_verify($password, $hash)) {
        return true;
    }

    $plain = app_env('ADMIN_PASS', '');
    if ($plain !== '' && hash_equals($plain, $password)) {
        return true;
    }

    return password_verify($password, DTH_DEFAULT_ADMIN_HASH);
}

function dth_admin_csrf()
{
    app_ensure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function dth_admin_db()
{
    $name = app_env('DB_NAME', '');
    $user = app_env('DB_USER', '');
    if ($name === '' || $user === '') {
        throw new RuntimeException('Missing database credentials. Set DB_NAME and DB_USER in .env.');
    }

    $dsn = 'mysql:host=' . app_env('DB_HOST', 'localhost') . ';dbname=' . $name . ';charset=' . app_env('DB_CHARSET', 'utf8mb4');
    return new PDO($dsn, $user, app_env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function dth_admin_column_exists(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function dth_admin_add_column(PDO $pdo, $table, $column, $definition)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$table) || !preg_match('/^[A-Za-z0-9_]+$/', (string)$column)) {
        return;
    }
    if (!dth_admin_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}

function dth_admin_index_exists(PDO $pdo, $table, $index)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function dth_admin_add_index(PDO $pdo, $table, $index, $definition)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$table) || !preg_match('/^[A-Za-z0-9_]+$/', (string)$index)) {
        return;
    }
    if (!dth_admin_index_exists($pdo, $table, $index)) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` ' . $definition);
    }
}

function dth_admin_auto_schema(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(120) NULL,
        image VARCHAR(700) NULL,
        price INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dth_admin_add_column($pdo, 'products', 'name', 'VARCHAR(255) NOT NULL DEFAULT ""');
    dth_admin_add_column($pdo, 'products', 'category', 'VARCHAR(120) NULL');
    dth_admin_add_column($pdo, 'products', 'image', 'VARCHAR(700) NULL');
    dth_admin_add_column($pdo, 'products', 'price', 'INT NOT NULL DEFAULT 0');
    dth_admin_add_index($pdo, 'products', 'idx_products_category', '(category)');
    dth_admin_add_index($pdo, 'products', 'idx_products_price', '(price)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_coupons (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(80) NOT NULL UNIQUE,
        discount_amount INT NOT NULL DEFAULT 0,
        quantity_left INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dth_admin_add_column($pdo, 'qr_coupons', 'code', 'VARCHAR(80) NOT NULL');
    dth_admin_add_column($pdo, 'qr_coupons', 'discount_amount', 'INT NOT NULL DEFAULT 0');
    dth_admin_add_column($pdo, 'qr_coupons', 'quantity_left', 'INT NOT NULL DEFAULT 0');
    dth_admin_add_column($pdo, 'qr_coupons', 'type', "VARCHAR(30) NOT NULL DEFAULT 'discount'");
    dth_admin_add_column($pdo, 'qr_coupons', 'value', 'INT NOT NULL DEFAULT 0');
    dth_admin_add_column($pdo, 'qr_coupons', 'description', 'TEXT NULL');
    dth_admin_add_column($pdo, 'qr_coupons', 'is_used', 'TINYINT(1) NOT NULL DEFAULT 0');
    dth_admin_add_index($pdo, 'qr_coupons', 'idx_qr_coupons_code', '(code)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_posts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_phone VARCHAR(30) NULL,
        issue TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        tech_target_price INT NOT NULL DEFAULT 0,
        final_price INT NOT NULL DEFAULT 0,
        bot_message_id BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dth_admin_add_column($pdo, 'job_posts', 'customer_phone', 'VARCHAR(30) NULL');
    dth_admin_add_column($pdo, 'job_posts', 'issue', 'TEXT NULL');
    dth_admin_add_column($pdo, 'job_posts', 'status', "VARCHAR(30) NOT NULL DEFAULT 'pending'");
    dth_admin_add_column($pdo, 'job_posts', 'tech_target_price', 'INT NOT NULL DEFAULT 0');
    dth_admin_add_column($pdo, 'job_posts', 'final_price', 'INT NOT NULL DEFAULT 0');
    dth_admin_add_column($pdo, 'job_posts', 'bot_message_id', 'BIGINT NULL');
    dth_admin_add_index($pdo, 'job_posts', 'idx_job_posts_customer_phone', '(customer_phone)');
    dth_admin_add_index($pdo, 'job_posts', 'idx_job_posts_status', '(status)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS finances (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(40) NOT NULL,
        amount INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS banned_entities (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_or_phone VARCHAR(255) NOT NULL,
        type VARCHAR(30) NOT NULL DEFAULT 'ip',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_banned_entities (ip_or_phone, type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dth_admin_add_index($pdo, 'banned_entities', 'idx_banned_entities_lookup', '(ip_or_phone, type)');
}

function dth_admin_money_int($value)
{
    return max(0, (int)(preg_replace('/[^\d]/', '', (string)$value) ?: 0));
}

function dth_admin_handle_promo(PDO $pdo)
{
    $action = (string)($_POST['promo_action'] ?? '');
    if ($action === '') {
        return '';
    }
    if (!hash_equals(dth_admin_csrf(), (string)($_POST['csrf'] ?? ''))) {
        return 'CSRF khong hop le.';
    }
    if ($action === 'create') {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $discount = dth_admin_money_int($_POST['discount_amount'] ?? 0);
        $qty = max(0, (int)($_POST['quantity_left'] ?? 0));
        if ($code === '') {
            return 'Vui long nhap ma promo.';
        }
        $stmt = $pdo->prepare("INSERT INTO qr_coupons (code, discount_amount, quantity_left, type, value, description, created_at)
            VALUES (?, ?, ?, 'discount', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE discount_amount = VALUES(discount_amount), quantity_left = VALUES(quantity_left), value = VALUES(value), description = VALUES(description)");
        $stmt->execute([$code, $discount, $qty, $discount, 'Promo code manual']);
        return 'Da luu promo code.';
    }
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = max(0, (int)($_POST['quantity_left'] ?? 0));
        $discount = dth_admin_money_int($_POST['discount_amount'] ?? 0);
        if ($id <= 0) {
            return 'ID promo khong hop le.';
        }
        $stmt = $pdo->prepare('UPDATE qr_coupons SET quantity_left = ?, discount_amount = ?, value = ? WHERE id = ?');
        $stmt->execute([$qty, $discount, $discount, $id]);
        return 'Da cap nhat promo.';
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            return 'ID promo khong hop le.';
        }
        $stmt = $pdo->prepare('DELETE FROM qr_coupons WHERE id = ?');
        $stmt->execute([$id]);
        return 'Da xoa promo.';
    }
    return '';
}

function dth_admin_promos(PDO $pdo)
{
    try {
        $stmt = $pdo->query('SELECT id, code, discount_amount, quantity_left, type, value, description, is_used, created_at FROM qr_coupons ORDER BY id DESC LIMIT 300');
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        error_log('[admin promos] ' . $e->getMessage());
        return [];
    }
}

app_ensure_session();

$error = '';
$schemaError = '';
$promoMessage = '';
$promoRows = [];

if (isset($_GET['logout'])) {
    app_admin_logout();
    header('Location: admin_xxx.php');
    exit;
}

$loggedIn = !empty($_SESSION['admin_logged_in']);

if (!$loggedIn && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    $password = (string)($_POST['password'] ?? '');
    if (dth_admin_password_ok($password)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_at'] = time();
        dth_admin_csrf();
        header('Location: admin_xxx.php');
        exit;
    }
    $error = 'Sai mat khau admin.';
}

$loggedIn = !empty($_SESSION['admin_logged_in']);

if ($loggedIn) {
    try {
        $adminPdo = dth_admin_db();
        dth_admin_auto_schema($adminPdo);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $promoMessage = dth_admin_handle_promo($adminPdo);
        }
        $promoRows = dth_admin_promos($adminPdo);
    } catch (Throwable $e) {
        error_log('[admin schema] ' . $e->getMessage());
        $schemaError = 'Loi khoi tao database: ' . $e->getMessage();
    }
}

if (!$loggedIn):
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Dien Tu Hieu</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22%3E%3Crect width=%2264%22 height=%2264%22 rx=%2212%22 fill=%22%23dc2626%22/%3E%3Ctext x=%2232%22 y=%2241%22 font-size=%2228%22 text-anchor=%22middle%22 fill=%22white%22 font-family=%22Arial%22 font-weight=%22700%22%3EH%3C/text%3E%3C/svg%3E">
    <style>
        body{margin:0;min-block-size:100vh;display:grid;place-items:center;background:#111827;font-family:Arial,sans-serif;color:#111827}
        .box{inline-size:min(420px,92vw);background:#fff;border:1px solid #e5e7eb;padding:28px;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
        h1{font-size:22px;margin:0 0 18px}
        input,button{inline-size:100%;box-sizing:border-box;padding:12px 14px;border-radius:6px;font-size:16px}
        input{border:1px solid #cbd5e1;margin-block-end:12px}
        button{border:0;background:#dc2626;color:#fff;font-weight:700;cursor:pointer}
        .err{padding:10px 12px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:6px;margin-block-end:12px}
        .hint{font-size:12px;color:#64748b;margin-block-start:12px;line-height:1.5}
    </style>
</head>
<body>
<main class="box">
    <h1>Admin Dien Tu Hieu</h1>
    <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <input type="password" name="password" placeholder="Mat khau admin" required autofocus>
        <button type="submit">Dang nhap</button>
    </form>
    <div class="hint">Nen dat ADMIN_PASS_HASH trong file .env. File nay co fallback hash cho mat khau cu.</div>
</main>
</body>
</html>
<?php
exit;
endif;

$csrf = dth_admin_csrf();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Van phong giam doc - Dien Tu Hieu</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22%3E%3Crect width=%2264%22 height=%2264%22 rx=%2212%22 fill=%22%23dc2626%22/%3E%3Ctext x=%2232%22 y=%2241%22 font-size=%2228%22 text-anchor=%22middle%22 fill=%22white%22 font-family=%22Arial%22 font-weight=%22700%22%3EH%3C/text%3E%3C/svg%3E">
    <style>
        :root{--bg:#f8fafc;--panel:#fff;--line:#e2e8f0;--text:#0f172a;--muted:#64748b;--brand:#dc2626;--ok:#16a34a;--warn:#d97706}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--text);font-family:Arial,sans-serif}
        header{position:sticky;inset-block-start:0;background:#111827;color:#fff;padding:14px 20px;display:flex;gap:12px;align-items:center;justify-content:space-between;z-index:2}
        header h1{font-size:18px;margin:0}
        header a{color:#fecaca;text-decoration:none;font-weight:700}
        main{padding:18px;max-inline-size:1360px;margin:0 auto}
        nav{display:flex;gap:8px;flex-wrap:wrap;margin-block-end:14px}
        nav button,.btn{border:1px solid var(--line);background:#fff;color:var(--text);padding:9px 12px;border-radius:6px;cursor:pointer;font-weight:700}
        nav button.active,.btn.primary{background:var(--brand);color:#fff;border-color:var(--brand)}
        .grid{display:grid;gap:12px}
        .stats{grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-block-end:14px}
        .card{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}
        .stat-label{font-size:12px;color:var(--muted);text-transform:uppercase}
        .stat-value{font-size:24px;font-weight:800;margin-block-start:6px}
        section{display:none}
        section.active{display:block}
        table{inline-size:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:8px;overflow:hidden}
        th,td{padding:9px 10px;border-block-end:1px solid var(--line);font-size:13px;text-align:start;vertical-align:top}
        th{background:#f1f5f9;color:#334155;font-size:12px;text-transform:uppercase}
        tr:last-child td{border-block-end:0}
        .thumb{inline-size:46px;block-size:46px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:6px;display:block}
        .qr-mini{inline-size:70px;block-size:70px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:6px;padding:4px;display:block}
        .qr-actions{display:flex;gap:6px;flex-wrap:wrap;margin-block-start:7px}
        .copy-mini{padding:6px 8px;font-size:12px}
        .qr-copy-text{margin-block-start:7px;font-size:12px}
        .created-codes{display:grid;gap:8px;margin-block-start:10px}
        .code-card{display:grid;grid-template-columns:76px minmax(0,1fr);gap:8px;align-items:center;border:1px solid var(--line);border-radius:8px;background:#fff;padding:8px}
        .code-card b{display:block;margin-block-end:5px;word-break:break-all}
        .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:800;border:1px solid transparent}
        .badge.ok{background:#ecfdf3;color:#047857;border-color:#bbf7d0}.badge.used{background:#fef2f2;color:#b91c1c;border-color:#fecaca}.badge.warn{background:#fff7ed;color:#c2410c;border-color:#fed7aa}
        .price-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-block-end:12px}.price-mini .card b{display:block;margin-block-end:6px}
        input,select,textarea{inline-size:100%;padding:9px 10px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;background:#fff}
        label{display:block;font-size:12px;color:#475569;font-weight:700;margin:10px 0 5px}
        .cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-block-end:14px}
        .row-actions{display:flex;gap:6px;flex-wrap:wrap}
        .danger{background:#dc2626!important;color:#fff!important;border-color:#dc2626!important}
        .success{background:#16a34a!important;color:#fff!important;border-color:#16a34a!important}
        .warn{background:#d97706!important;color:#fff!important;border-color:#d97706!important}
        .muted{color:var(--muted)}
        .msg{margin:10px 0;padding:10px 12px;border-radius:6px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;display:none}
        .print-box{background:#fff;border:1px solid var(--line);border-radius:8px;padding:24px;max-inline-size:900px;margin:0 auto}
        .invoice-print{font-family:Arial,Tahoma,"Helvetica Neue",sans-serif;font-size:13px;line-height:1.45;color:#111827}
        .invoice-warranty{margin-block-start:12px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc}
        .invoice-warranty span{color:#475569}
        .invoice-print-head{display:flex;justify-content:space-between;gap:24px;border-block-end:2px solid #111827;padding-block-end:14px;margin-block-end:16px}.invoice-brand{display:flex;gap:14px;align-items:flex-start}.invoice-logo{inline-size:96px;block-size:72px;object-fit:contain;border:none;background:transparent}.invoice-print-head h1{margin:0 0 8px;font-size:23px}.invoice-print-head p{margin:3px 0}.invoice-print-title{text-align:end}.invoice-print-title h2{margin:0 0 8px;font-size:22px}.invoice-print-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px 24px;margin-block-end:16px}.invoice-print table{border-color:#94a3b8}.invoice-print th,.invoice-print td{border:1px solid #cbd5e1}.invoice-totals{margin-inline-start:auto;inline-size:min(420px,100%);margin-block-start:14px}.invoice-totals div{display:flex;justify-content:space-between;gap:20px;padding:5px 0}.invoice-totals .grand{border-block-start:2px solid #111827;font-size:17px;font-weight:800}.invoice-signatures{display:grid;grid-template-columns:1fr 1fr;gap:80px;text-align:center;margin-block-start:42px;min-block-size:100px}.quote-box{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;margin-block-start:14px}.quote-box div{background:#f8fafc;border:1px solid var(--line);border-radius:6px;padding:10px}.quote-box span{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;margin-block-end:5px}.quote-box b{font-size:15px}
        .section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:16px 0 10px}.section-head h2{margin:0;font-size:18px}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px;background:#fff}.table-wrap table{border:0;min-inline-size:980px}
        .table-wrap.compact-table table{min-inline-size:0}
        .worker-name{font-weight:800}.worker-meta{display:block;color:var(--muted);font-size:11px;margin-block-start:3px}
        .money-due{font-weight:800;color:#b91c1c}.money-paid{font-weight:800;color:#047857}
        @media print{@page{size:A4;margin:12mm}body{background:#fff!important;font-family:Arial,Tahoma,"Helvetica Neue",sans-serif!important}body>*:not(.print-only){display:none!important}.print-only{display:block!important}.print-box{border:0;max-inline-size:none}.invoice-print{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
    </style>
</head>
<body>
<header class="print-only" style="display:none"></header>
<header>
    <h1>Van phong giam doc - Dien Tu Hieu</h1>
    <div><span id="clock"></span> &nbsp; <a href="?logout=1">Dang xuat</a></div>
</header>
<main class="print-only" style="display:none" id="printRoot"></main>
<main class="print-hide">
    <nav>
        <button class="active" data-page="dash">Dashboard</button>
        <button data-page="sales">Ban hang</button>
        <button data-page="orders">Don hang</button>
        <button data-page="jobs">Goi tho</button>
        <button data-page="products">Kho san pham</button>
        <button data-page="codes">Promo/QR</button>
        <button data-page="workers">Tho va Unban</button>
        <button data-page="users">Khach hang</button>
        <button data-page="stores">Cửa hàng</button>
        <button data-page="invoices">In hoa don</button>
        <button data-page="bct">Bao cao BCT</button>
        <button data-page="contracts">In hợp đồng</button>
    </nav>

    <div id="globalMsg" class="msg"></div>
    <?php if ($schemaError !== ''): ?><div class="msg" style="display:block;background:#fef2f2;color:#991b1b;border-color:#fecaca"><?= h($schemaError) ?></div><?php endif; ?>
    <?php if ($promoMessage !== ''): ?><div class="msg" style="display:block"><?= h($promoMessage) ?></div><?php endif; ?>

    <section id="page-dash" class="active">
        <div class="row-actions" style="margin-block-end:14px">
            <button class="btn primary" onclick="downloadSettlement()">Ket toan ngay - Tai Excel</button>
            <button class="btn warn" onclick="notifyWorkerFees()">Nhac phi toan bo tho</button>
            <button class="btn danger" onclick="enforceWorkerFeeLock()">Khoa tho con no qua han</button>
            <button class="btn" onclick="loadDashboard()">Tai lai dashboard</button>
        </div>
        <div class="grid stats" id="stats"></div>
        <div class="section-head"><h2>Tong hop tho va cong no</h2><span class="muted">Du lieu dong bo tu ca goi tho, Telegram va thanh toan</span></div>
        <div class="table-wrap"><table><thead><tr><th>Tho</th><th>SDT / Ma dinh danh</th><th>Ca xong</th><th>Thu nhap</th><th>Da dong phi</th><th>No hien tai</th><th>Thanh toan</th><th>Trang thai</th><th>Lenh</th></tr></thead><tbody id="dashboardWorkersBody"></tbody></table></div>
        <div class="section-head"><h2>Thanh toan phi gan day</h2><span class="muted">SePay, admin va thong bao cho doi soat</span></div>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>Tho</th><th>So tien</th><th>Da phan bo</th><th>Phuong thuc</th><th>Ma tham chieu</th><th>Trang thai</th><th>Thoi gian</th></tr></thead><tbody id="paymentsBody"></tbody></table></div>
    </section>

    <section id="page-sales">
        <div class="section-head"><div><h2>Ban hang tai quay</h2><span class="muted">Tao hoa don dien tu, cap nhat doanh thu va tich diem theo so dien thoai</span></div><button class="btn" onclick="loadRetailSale()">Tai lai</button></div>
        <div class="cols">
            <div class="card">
                <h2>Khach hang</h2>
                <div style="display:flex; gap:8px; margin-block-end:10px;">
                    <div style="flex:1;">
                        <label>Ma so thue (MST)</label>
                        <input id="pos_customer_tax_code" placeholder="Nhap MST cong ty/ca nhan">
                    </div>
                    <div style="align-self:flex-end;">
                        <button class="btn primary" onclick="lookupMST()">Kiem tra</button>
                    </div>
                </div>
                <label>Ten khach / Cong ty</label><input id="pos_customer_name" placeholder="Nhap ten khach / cong ty" required>
                <label>So dien thoai tich diem</label><input id="pos_customer_phone" inputmode="numeric" placeholder="09xxxxxxxx" oninput="scheduleRetailCustomerLookup()" required>
                <label>Dia chi</label><textarea id="pos_customer_address" rows="3"></textarea>
                <div id="posCustomerStatus" class="muted" style="margin-block-start:10px">Nhap so dien thoai de nhan dien thanh vien.</div>
            </div>
            <div class="card">
                <h2>Hang hoa</h2>
                <input id="pos_product_id" type="hidden">
                <input id="pos_product_source" type="hidden">
                <label>Ten hang hoa</label><input id="pos_product_name" list="posProductList" oninput="selectRetailProduct();scheduleRetailQuote()" required>
                <datalist id="posProductList"></datalist>
                <label>So luong</label><input id="pos_quantity" type="number" min="1" max="10000" value="1" oninput="scheduleRetailQuote()">
                <label>Don gia da gom VAT 10%</label><input id="pos_unit_gross" type="number" min="1" value="0" oninput="scheduleRetailQuote()">
                <label>Qua tang kem</label><input id="pos_gift_name">
                <label>Số năm bảo hành</label><input id="pos_warranty_years" type="number" min="0" max="30" step="1" value="1" oninput="scheduleRetailQuote()">
            </div>
            <div class="card">
                <h2>Thanh toan</h2>
                <label>Ma khuyen mai neu co</label><input id="pos_promo_code" oninput="scheduleRetailQuote()">
                <label>Phuong thuc thanh toan</label><select id="pos_payment_method"><option value="cash">Tien mat</option><option value="bank">Chuyen khoan</option><option value="momo">MoMo</option><option value="card">The</option></select>
                <label>Ghi chu</label><textarea id="pos_note" rows="3"></textarea>
                <div class="row-actions">
                    <button class="btn" onclick="previewRetailSale()">Tinh tien</button>
                    <button class="btn success" onclick="completeRetailSale()">Ban hang va in hoa don</button>
                </div>
                <div id="posSaleStatus" class="muted" style="margin-block-start:12px">Moi <?= h(number_format(max(1, (int)app_env('LOYALTY_VND_PER_POINT', '10000')), 0, ',', '.')) ?> VND thanh toan duoc cong 1 diem.</div>
            </div>
        </div>
        <div id="posQuote" class="quote-box"></div>
        <div class="section-head"><h2>Hoa don ban hang gan day</h2><span class="muted">Co the in lai tu danh sach nay</span></div>
        <div class="table-wrap"><table><thead><tr><th>Ma / Ngay</th><th>Khach</th><th>Hang hoa</th><th>Thanh toan</th><th>Diem cong</th><th>Lenh</th></tr></thead><tbody id="posSalesBody"></tbody></table></div>
    </section>

    <section id="page-orders">
        <h2>Don hang moi nhat <button class="btn" style="float:inline-end; background-color:#e11d48; color:white; border:none;" onclick="exportViettelBatch()">Xuất Hóa Đơn Tổng Hợp Ngày</button></h2>
        <table><thead><tr><th>ID</th><th>Khach</th><th>Phone</th><th>San pham</th><th>Tien</th><th>TT</th><th>Ngay</th><th>Lenh</th></tr></thead><tbody id="ordersBody"></tbody></table>
    </section>

    <section id="page-jobs">
        <div class="row-actions" style="margin-block-end:12px">
            <button class="btn warn" onclick="sendTestJob()">Gui ca test len Bot 1</button>
            <button class="btn" onclick="loadJobs()">Tai lai</button>
        </div>
        <div class="price-mini">
            <div class="card"><b>Ve sinh may lanh</b><span>Tho nhan 150.000 VND, bao khach +10% VAT, phi nen tang 5%.</span></div>
            <div class="card"><b>Lap may lanh 1HP / 1.5HP</b><span>Cong 400.000 VND, chua gom vat tu phat sinh.</span></div>
            <div class="card"><b>Lap may lanh 2HP / 3HP</b><span>Cong 500.000 VND, may am tran lien he hang.</span></div>
            <div class="card"><b>Sua chua / tivi / loc nuoc</b><span>Cong tho 200.000 VND + linh kien/phu kien cong khai.</span></div>
        </div>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>Khach</th><th>Phone</th><th>Dich vu</th><th>Dia chi / Ban do</th><th>Gia khach</th><th>Tho</th><th>TT</th><th>Ngay</th></tr></thead><tbody id="jobsBody"></tbody></table></div>
    </section>

    <section id="page-products">
        <div class="cols">
            <div class="card">
                <h2>Luu san pham</h2>
                <input type="hidden" id="prod_id">
                <label>Ten san pham</label><input id="prod_name">
                <label>Gia ban</label><input id="prod_price" type="number" min="0">
                <label>Ton kho</label><input id="prod_stock" type="number" min="0" value="100">
                <label>Danh muc</label><input id="prod_category">
                <label>URL anh</label><input id="prod_image">
                <button class="btn primary" onclick="saveProduct()">Luu</button>
            </div>
            <div class="card">
                <h2>Anh Thiên AI - Thêm SP</h2>
                <p class="muted">Tải ảnh hoặc dán nội dung. AI sẽ tự đọc, phân loại và lưu kho!</p>
                <input type="file" id="ai_product_image" accept="image/*" style="margin-block-end: 10px;">
                <textarea id="ai_product_text" rows="5" placeholder="Hoặc dán thông tin giới thiệu sản phẩm vào đây..."></textarea>
                <button class="btn success" id="btnAiImport" onclick="aiImportProduct()" style="margin-block-start: 10px;">Phân tích & Thêm sản phẩm</button>
                <div id="aiImportStatus" class="muted" style="margin-block-start:10px;"></div>
            </div>
        </div>
        <table><thead><tr><th>ID</th><th>Anh</th><th>Ten</th><th>Danh muc</th><th>Gia</th><th>Ton</th><th>Lenh</th></tr></thead><tbody id="productsBody"></tbody></table>
    </section>

    <section id="page-codes">
        <div class="cols">
            <div class="card">
                <h2>Quan ly Promo Code</h2>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="promo_action" value="create">
                    <label>Code</label><input name="code" placeholder="VD: DTH50K" required>
                    <label>Discount VND</label><input name="discount_amount" type="number" value="50000" min="0" required>
                    <label>Quantity</label><input name="quantity_left" type="number" value="10" min="0" required>
                    <button class="btn primary" type="submit">Tao / Cap nhat code</button>
                </form>
            </div>
            <div class="card">
                <h2>Tạo Mã / QR Code tự động</h2>
                <label>Loại Mã / QR</label>
                <select id="qr_type_selector" onchange="toggleQrForms()" style="margin-block-end: 15px; padding: 8px; inline-size: 100%;">
                    <option value="voucher">Voucher giảm %</option>
                    <option value="coupon">QR Coupon giảm tiền VND</option>
                    <option value="freeship">QR Miễn phí vận chuyển</option>
                    <option value="ac">QR Bảo hành Vệ sinh AC</option>
                    <option value="bh">QR Tem Bảo Hành</option>
                </select>

                <!-- Voucher Form -->
                <div id="form_voucher" class="qr-form-section">
                    <label>Giảm %</label><input id="voucher_percent" type="number" value="10" min="0" max="100">
                    <label>Số lượng</label><input id="voucher_count" type="number" value="5" min="1" max="500">
                    <button class="btn primary" onclick="generateVoucher()" style="inline-size:100%">Tạo voucher</button>
                    <div id="voucherResult" class="muted"></div>
                </div>

                <!-- Coupon Form -->
                <div id="form_coupon" class="qr-form-section" style="display:none">
                    <label>Giá trị VND</label><input id="qr_value" type="number" value="50000" min="0">
                    <label>Mô tả</label><input id="qr_desc" value="Giảm giá QR">
                    <label>Số lượng</label><input id="qr_count" type="number" value="1" min="1" max="500">
                    <button class="btn primary" onclick="generateQR()" style="inline-size:100%">Tạo QR Giảm tiền</button>
                    <div id="qrResult" class="muted"></div>
                </div>

                <!-- Freeship Form -->
                <div id="form_freeship" class="qr-form-section" style="display:none">
                    <label>Mô tả</label><input id="freeship_desc" value="Miễn phí vận chuyển">
                    <label>Số lượng</label><input id="freeship_count" type="number" value="1" min="1" max="500">
                    <button class="btn primary" onclick="generateFreeshipQR()" style="inline-size:100%">Tạo QR Miễn phí vận chuyển</button>
                    <div id="freeshipResult" class="muted"></div>
                </div>

                <!-- AC Warranty Form -->
                <div id="form_ac" class="qr-form-section" style="display:none">
                    <label>Tên khách hàng</label><input id="qr_ac_name" placeholder="VD: Nguyễn Văn A" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>SĐT khách hàng</label><input id="qr_ac_phone" placeholder="VD: 0912345678" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>Tên sản phẩm</label><input id="qr_ac_product" placeholder="VD: Máy lạnh Daikin 1HP" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>Số Seri (S/N)</label><input id="qr_ac_serial" placeholder="VD: S/N 12345678" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <button class="btn primary" onclick="generateACWarrantyQR()" style="inline-size:100%;">Tạo & In QR</button>
                </div>

                <!-- Warranty Form -->
                <div id="form_bh" class="qr-form-section" style="display:none">
                    <label>Tên khách hàng</label><input id="qr_bh_name" placeholder="VD: Nguyễn Văn B" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>SĐT khách hàng</label><input id="qr_bh_phone" placeholder="VD: 0987654321" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>Tên sản phẩm</label><input id="qr_bh_product" placeholder="VD: Tivi Sony 55 inch" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>Số Seri (S/N)</label><input id="qr_bh_serial" placeholder="VD: S/N 87654321" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>Ngày mua hàng</label><input id="qr_bh_date" type="date" value="<?= date('Y-m-d') ?>" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <label>Thời hạn bảo hành (Năm)</label><input id="qr_bh_years" type="number" min="1" max="20" value="2" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <button class="btn primary" onclick="generateWarrantyQR()" style="inline-size:100%;">Tạo & In Tem</button>
                </div>
            </div>
        </div>
        <div class="cols">
            <div>
                <h2>Promo Code Manual</h2>
                <table>
                    <thead><tr><th>ID</th><th>QR</th><th>Code</th><th>Discount / Quantity</th><th>Trang thai</th><th>Lenh</th></tr></thead>
                    <tbody>
                    <?php if (!$promoRows): ?>
                        <tr><td colspan="6" class="muted">Chua co promo code.</td></tr>
                    <?php else: ?>
                        <?php foreach ($promoRows as $promo): ?>
                            <tr>
                                <td><?= h($promo['id'] ?? '') ?></td>
                                <td><img class="qr-mini" src="<?= h(dth_admin_qr_src($promo['code'] ?? '')) ?>" alt="QR <?= h($promo['code'] ?? '') ?>"></td>
                                <td>
                                    <b><?= h($promo['code'] ?? '') ?></b>
                                    <div class="qr-actions">
                                        <button class="btn copy-mini" type="button" data-copy="<?= h($promo['code'] ?? '') ?>">Copy code</button>
                                        <button class="btn copy-mini" type="button" data-copy="<?= h(dth_admin_qr_src($promo['code'] ?? '')) ?>">Copy QR link</button>
                                    </div>
                                    <input class="qr-copy-text" readonly value="<?= h(dth_admin_qr_src($promo['code'] ?? '')) ?>" onclick="this.select()">
                                </td>
                                <td>
                                    <form method="post" class="row-actions">
                                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="promo_action" value="update">
                                        <input type="hidden" name="id" value="<?= h($promo['id'] ?? '') ?>">
                                        <input name="discount_amount" type="number" min="0" value="<?= h($promo['discount_amount'] ?? 0) ?>" style="max-inline-size:130px">
                                        <input name="quantity_left" type="number" min="0" value="<?= h($promo['quantity_left'] ?? 0) ?>" style="max-inline-size:110px">
                                        <button class="btn success" type="submit">Luu</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ((int)($promo['is_used'] ?? 0) === 1): ?>
                                        <span class="badge used">Da dung</span>
                                    <?php elseif ((int)($promo['quantity_left'] ?? 0) <= 0): ?>
                                        <span class="badge warn">Het luot</span>
                                    <?php else: ?>
                                        <span class="badge ok">Chua dung</span>
                                    <?php endif; ?>
                                    <div class="muted"><?= h($promo['created_at'] ?? '') ?></div>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Xoa promo <?= h($promo['code'] ?? '') ?>?')">
                                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="promo_action" value="delete">
                                        <input type="hidden" name="id" value="<?= h($promo['id'] ?? '') ?>">
                                        <button class="btn danger" type="submit">Xoa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div><h2>Voucher</h2><table><thead><tr><th>QR</th><th>Code</th><th>Giam</th><th>Su dung</th><th>Trang thai</th><th>Han</th></tr></thead><tbody id="voucherBody"></tbody></table></div>
            <div><h2>QR Coupon</h2><table><thead><tr><th>ID</th><th>QR</th><th>Code</th><th>Loai</th><th>Gia tri</th><th>Mo ta</th><th>Trang thai</th></tr></thead><tbody id="couponBody"></tbody></table></div>
        </div>
    </section>

    <section id="page-workers">
        <div class="cols">
            <div class="card">
                <h2>Dang ky / cap nhat tho</h2>
                <label>Telegram user ID</label><input id="worker_register_id" type="number" placeholder="8729878070">
                <label>So dien thoai</label><input id="worker_register_phone" inputmode="numeric" placeholder="09xxxxxxxx">
                <label>Ten tho / ho kinh doanh</label><input id="worker_register_name" placeholder="Ten tho">
                <label>Vai tro</label><select id="worker_register_role"><option value="worker">Thợ Điện Lạnh (worker)</option><option value="bike">Tài xế / Shipper (bike)</option><option value="drone">Thợ Ghi Hình (drone)</option></select>
                <button class="btn primary" onclick="registerWorker()" style="margin-block-start:10px">Luu ho so tho</button>
                <p class="muted">Tren Telegram admin co the gui: /idtelegram | TELEGRAM_ID | SO_DIEN_THOAI | TEN_THO</p>
            </div>
            <div class="card">
                <h2>Unban tho</h2>
                <label>Telegram user ID</label><input id="worker_unban_id" type="number">
                <button class="btn success" onclick="unbanWorker()">Mo khoa tho</button>
            </div>
            <div class="card">
                <h2>Unban IP/device</h2>
                <label>Identifier</label><input id="device_unban_id">
                <button class="btn success" onclick="unbanDevice()">Mo khoa thiet bi</button>
            </div>
        </div>
        <h2>Danh sach tho</h2>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>Ten / Username</th><th>SDT</th><th>Loai</th><th>So ca</th><th>Tong tien</th><th>Da dong</th><th>No phi</th><th>Block</th><th>Lenh</th></tr></thead><tbody id="workersBody"></tbody></table></div>
        <h2>Device/IP bi khoa</h2>
        <table><thead><tr><th>ID</th><th>Identifier</th><th>Loai</th><th>Ly do</th><th>Spam</th><th>Ngay</th><th>Lenh</th></tr></thead><tbody id="bansBody"></tbody></table>
    </section>

    <section id="page-users">
        <div class="row-actions" style="margin-block-end:14px; justify-content:space-between;">
            <div><h2 style="margin:0;">Quan ly Khach hang</h2><span class="muted">Thong tin thanh vien va tich diem</span></div>
            <button class="btn success" onclick="openUserModal()">+ Them Khach hang</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Khach hang</th>
                        <th>Lien he</th>
                        <th>Hang & Tong tien</th>
                        <th>QR Thanh vien</th>
                        <th>Status</th>
                        <th>Lenh</th>
                    </tr>
                </thead>
                <tbody id="usersBody"></tbody>
            </table>
        </div>
    </section>

    <section id="page-stores">
        <div class="row-actions" style="margin-block-end:14px; justify-content:space-between;">
            <div><h2 style="margin:0;">Quan ly Cua hang (Cho Xa Lap Vo)</h2><span class="muted">Danh sach cua hang doi tac va doanh thu</span></div>
            <button class="btn warn" onclick="settleStores()">Chot doi soat cuoi thang</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ten cua hang</th>
                        <th>MST / Lien he</th>
                        <th>Loai / Dia chi</th>
                        <th>Tong giao dich</th>
                        <th>Status</th>
                        <th>Lenh</th>
                    </tr>
                </thead>
                <tbody id="storesBody"></tbody>
            </table>
        </div>
    </section>

    <section id="page-invoices">
        <div class="section-head"><div><h2>Tao hoa don ban hang</h2><span class="muted">Gia nhap la gia da gom VAT 10%</span></div><button class="btn" onclick="loadInvoices()">Tai lai so hoa don</button></div>
        <div class="cols">
            <div class="card">
                <h2>Khach hang</h2>
                <div style="display:flex; gap:8px; margin-block-end:10px;">
                    <div style="flex:1;">
                        <label>Ma so thue (MST)</label>
                        <input id="sale_customer_tax_code" placeholder="Nhap MST cong ty/ca nhan">
                    </div>
                    <div style="align-self:flex-end;">
                        <button class="btn primary" onclick="lookupMST_invoice()">Kiem tra</button>
                    </div>
                </div>
                <label>Ten khach</label><input id="sale_customer_name" placeholder="Khach le">
                <label>So dien thoai</label><input id="sale_customer_phone" inputmode="numeric">
                <label>Dia chi</label><textarea id="sale_customer_address" rows="4"></textarea>
                <div id="saleCustomerStatus" class="muted" style="margin-block-start:10px"></div>
            </div>
            <div class="card">
                <h2>Hang hoa va uu dai</h2>
                <label>Hang hoa</label><input id="sale_product_name" oninput="scheduleInvoiceQuote()" required>
                <label>So luong</label><input id="sale_quantity" type="number" min="1" max="10000" value="1" oninput="scheduleInvoiceQuote()">
                <label>Gia moi san pham da gom VAT</label><input id="sale_unit_gross" type="number" min="1" value="0" oninput="scheduleInvoiceQuote()">
                <label>Qua tang kem</label><input id="sale_gift_name">
                <label>Số năm bảo hành</label><input id="sale_warranty_years" type="number" min="0" max="30" step="1" value="1" oninput="scheduleInvoiceQuote()">
            </div>
            <div class="card">
                <h2>Khuyen mai va in</h2>
                <label>Ma khuyen mai neu co</label><input id="sale_promo_code" oninput="scheduleInvoiceQuote()">
                <label>Ghi chu</label><textarea id="sale_note" rows="3"></textarea>
                <div class="row-actions">
                    <button class="btn" onclick="previewInvoice()">Tinh lai</button>
                    <button class="btn primary" onclick="createAndPrintInvoice()">Tao va in hoa don</button>
                </div>
                <div id="saleQuoteStatus" class="muted" style="margin-block-start:12px">Nhap hang hoa va gia de tinh hoa don.</div>
            </div>
        </div>
        <div id="saleQuote" class="quote-box"></div>
        <div class="section-head"><h2>Thong tin cong ty tren hoa don</h2><span class="muted">Cau hinh tu file .env</span></div>
        <div id="invoiceCompanyInfo" class="card muted">Dang tai thong tin cong ty...</div>
        <div class="section-head"><h2>So hoa don da tao</h2><span class="muted">Co the in lai bat ky luc nao</span></div>
        <div class="table-wrap"><table><thead><tr><th>Ma / Ngay</th><th>Khach</th><th>Hang hoa</th><th>Truoc VAT</th><th>VAT 10%</th><th>Giam</th><th>Thanh toan</th><th>Lenh</th></tr></thead><tbody id="invoiceBody"></tbody></table></div>
    </section>

    <section id="page-bct">
        <div class="section-head">
            <div><h2>Doi soat bao cao Bo Cong Thuong</h2><span class="muted">So lieu he thong, hoa don dau vao PDF va hoa don dau ra</span></div>
            <div class="row-actions">
                <input id="bct_from" type="date" value="<?= h(date('Y-01-01')) ?>" style="inline-size:auto">
                <input id="bct_to" type="date" value="<?= h(date('Y-m-d')) ?>" style="inline-size:auto">
                <button class="btn primary" onclick="loadBctReport()">Doi soat</button>
            </div>
        </div>
        <div class="grid stats" id="bctStats"></div>
        <div class="cols">
            <div class="card">
                <h2>Trang thai bao cao</h2>
                <div id="bctReportStatus" class="muted">Chua tai doi soat.</div>
                <p class="muted">API ket noi: <code><?= h(rtrim(app_env('APP_URL', 'https://dienmayhieu.com'), '/')) ?>/api_baocao_bct.php</code></p>
                <p class="muted">Chi dung mat khau thuong hoac API key rieng. Chuoi hash trong .env khong the dung de dang nhap.</p>
            </div>
            <div class="card">
                <h2>Van de can xu ly</h2>
                <div class="table-wrap compact-table"><table><thead><tr><th>Muc do</th><th>Ma doi soat</th><th>So luong</th><th>ID lien quan</th></tr></thead><tbody id="bctIssuesBody"></tbody></table></div>
            </div>
        </div>

        <div class="section-head"><h2>Nhap hoa don dien tu dau vao</h2><span class="muted">Chi them, khong ghi de; kiem tra cong thuc va SHA-256 cua PDF</span></div>
        <form id="inputInvoiceForm" class="card" onsubmit="uploadInputInvoice(event)" enctype="multipart/form-data">
            <div class="cols">
                <div>
                    <label>So hoa don</label><input name="invoice_number" required>
                    <label>Ky hieu hoa don</label><input name="invoice_series" required>
                    <label>Ngay hoa don</label><input name="invoice_date" type="date" value="<?= h(date('Y-m-d')) ?>" required>
                    <label>Don vi ban</label><input name="seller_name" required>
                    <label>Ma so thue don vi ban</label><input name="seller_tax_code" required>
                </div>
                <div>
                    <label>Tien truoc thue</label><input id="input_subtotal" name="subtotal_amount" type="number" min="0" value="0" oninput="updateInputInvoiceTotal()" required>
                    <label>VAT</label><input id="input_vat" name="vat_amount" type="number" min="0" value="0" oninput="updateInputInvoiceTotal()" required>
                    <label>Dieu chinh (+/-)</label><input id="input_adjustment" name="adjustment_amount" type="number" value="0" oninput="updateInputInvoiceTotal()" required>
                    <label>Tong thanh toan</label><input id="input_total" name="total_amount" type="number" value="0" readonly required>
                </div>
                <div>
                    <label>PDF hoa don dien tu</label><input name="pdf" type="file" accept="application/pdf,.pdf" required>
                    <label>Ghi chu doi soat</label><textarea name="note" rows="5"></textarea>
                    <button class="btn success" type="submit">Luu PDF va doi soat</button>
                </div>
            </div>
        </form>

        <div class="section-head"><h2>So hoa don dau vao</h2><button class="btn" onclick="loadInputInvoices()">Tai lai</button></div>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>So / Ngay</th><th>Don vi ban</th><th>Truoc thue</th><th>VAT</th><th>Dieu chinh</th><th>Tong</th><th>PDF / SHA-256</th><th>Trang thai</th></tr></thead><tbody id="inputInvoicesBody"></tbody></table></div>
    </section>

    <section id="page-contracts">
        <div class="section-head"><h2>In Hợp đồng Hợp tác</h2><span class="muted">Tạo và in Hợp đồng hợp tác</span></div>
        <form id="contractForm" class="card" onsubmit="event.preventDefault(); printContract();">
            <div class="cols">
                <div>
                    <label>Tên người lao động (Bên B)</label><input id="contract_name" required placeholder="Nhập họ và tên...">
                    <label>Số điện thoại</label><input id="contract_phone" required placeholder="09xxxxxxxx">
                    <label>Số Căn Cước Công Dân (CCCD)</label><input id="contract_cccd" required placeholder="Nhập số CCCD...">
                </div>
                <div>
                    <label>Hạng mục hợp tác</label><input id="contract_category" required placeholder="VD: Điện lạnh, Xây dựng, Giao hàng...">
                    <div style="margin-block-start:24px"><button class="btn primary" type="submit" style="inline-size:100%; padding: 12px; font-size: 16px;">In Hợp Đồng Ngay</button></div>
                </div>
            </div>
        </form>
    </section>
</main>

<script>
const API = 'api_master.php';
const CSRF = <?= json_encode($csrf) ?>;
const LOYALTY_VND_PER_POINT = <?= json_encode(max(1, (int)app_env('LOYALTY_VND_PER_POINT', '10000'))) ?>;

function fmt(n){ return new Intl.NumberFormat('vi-VN').format(Number(n || 0)) + ' VND'; }
function esc(s){ return String(s ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
function viDate(value){
    const s=String(value ?? '').trim();
    if(!s) return '';
    const m=s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if(m) return `${m[3]}/${m[2]}/${m[1]}`;
    const d=new Date(s);
    return Number.isNaN(d.getTime()) ? s : d.toLocaleDateString('vi-VN');
}
function paymentLabel(value){
    const key=String(value||'').toLowerCase();
    return ({cash:'Tiền mặt',bank:'Chuyển khoản',momo:'MoMo',card:'Thẻ',cod:'Thanh toán khi nhận hàng'}[key] || String(value||'-'));
}
function warrantyLabel(i){
    const years=Number(i?.warranty_years||0);
    const until=i?.warranty_expires_at||i?.warranty_until||'';
    if(years<=0) return 'Không bảo hành';
    return until ? `${years} năm - hết hạn ngày ${viDate(until)}` : `${years} năm`;
}
function msg(text){ const el=document.getElementById('globalMsg'); el.textContent=text; el.style.display='block'; setTimeout(()=>el.style.display='none',3500); }
function copyText(text){
    const value = String(text || '');
    if (!value) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(()=>msg('Da copy')).catch(()=>fallbackCopy(value));
    } else {
        fallbackCopy(value);
    }
}
function fallbackCopy(text){
    const area=document.createElement('textarea');
    area.value=text;
    document.body.appendChild(area);
    area.select();
    document.execCommand('copy');
    area.remove();
    msg('Da copy');
}
document.addEventListener('click', event => {
    const button = event.target.closest('[data-copy]');
    if (button) copyText(button.dataset.copy);
});
function api(action, data={}, method='GET'){
    const url = API + '?action=' + encodeURIComponent(action) + (method === 'GET' ? '&' + new URLSearchParams(data).toString() : '');
    const opt = {method, credentials:'same-origin', headers:{}};
    if (method !== 'GET') { opt.headers['Content-Type']='application/json'; opt.headers['X-CSRF-Token']=CSRF; opt.body=JSON.stringify(data); }
    return fetch(url,opt).then(async r => {
        const t = await r.text();
        try { return JSON.parse(t); } catch(e) { throw new Error(t || 'Invalid JSON'); }
    });
}
function setClock(){ document.getElementById('clock').textContent = new Date().toLocaleString('vi-VN',{hour12:false}); }
setInterval(setClock,1000); setClock();

document.querySelectorAll('nav button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('nav button').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('section').forEach(s=>s.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('page-' + btn.dataset.page).classList.add('active');
        loadPage(btn.dataset.page);
    });
});

function loadPage(page){
    if (page === 'dash') loadDashboard();
    if (page === 'sales') loadRetailSale();
    if (page === 'orders') loadOrders();
    if (page === 'jobs') loadJobs();
    if (page === 'products') loadProducts();
    if (page === 'codes') { loadVouchers(); loadCoupons(); }
    if (page === 'workers') { loadWorkers(); loadBans(); }
    if (page === 'users') loadUsers();
    if (page === 'stores') loadStores();
    if (page === 'invoices') loadInvoices();
    if (page === 'bct') { loadBctReport(); loadInputInvoices(); }
}

let posProductRows = [];
let posQuoteTimer = null;
let posCustomerLookupTimer = null;
function retailPayload(){
    return {
        customer_name:document.getElementById('pos_customer_name').value,
        customer_phone:document.getElementById('pos_customer_phone').value,
        customer_tax_code:document.getElementById('pos_customer_tax_code').value,
        customer_address:document.getElementById('pos_customer_address').value,
        product_id:document.getElementById('pos_product_id').value,
        product_source:document.getElementById('pos_product_source').value,
        product_name:document.getElementById('pos_product_name').value,
        quantity:document.getElementById('pos_quantity').value,
        unit_gross_amount:document.getElementById('pos_unit_gross').value,
        gift_name:document.getElementById('pos_gift_name').value,
        warranty_years:document.getElementById('pos_warranty_years').value,
        promo_code:document.getElementById('pos_promo_code').value,
        payment_method:document.getElementById('pos_payment_method').value,
        note:document.getElementById('pos_note').value
    };
}
function loadRetailProducts(){
    return api('admin_products').then(d=>{
        posProductRows=d.data||[];
        document.getElementById('posProductList').innerHTML=posProductRows.map(p=>`<option value="${esc(p.name)}">${fmt(p.price)} - Ton ${esc(p.stock_quantity||0)}</option>`).join('');
    });
}
function selectRetailProduct(){
    const name=String(document.getElementById('pos_product_name').value||'').trim().toLowerCase();
    const product=posProductRows.find(p=>String(p.name||'').trim().toLowerCase()===name);
    document.getElementById('pos_product_id').value=product?product.id:'';
    document.getElementById('pos_product_source').value=product?product.src:'';
    if(product) document.getElementById('pos_unit_gross').value=Number(product.price||0);
}
function scheduleRetailCustomerLookup(){
    clearTimeout(posCustomerLookupTimer);
    const phone=String(document.getElementById('pos_customer_phone').value||'').replace(/\D/g,'');
    if(phone.length<8){
        document.getElementById('posCustomerStatus').textContent='Nhap so dien thoai de nhan dien thanh vien.';
        return;
    }
    posCustomerLookupTimer=setTimeout(()=>api('admin_customer_lookup',{phone}).then(d=>{
        const c=d.data;
        if(!c){
            document.getElementById('posCustomerStatus').innerHTML=statusBadge('warn','Khach moi')+' Se tao thanh vien khi hoan tat ban hang.';
            return;
        }
        if(!document.getElementById('pos_customer_name').value.trim()) document.getElementById('pos_customer_name').value=c.fullname||'';
        document.getElementById('posCustomerStatus').innerHTML=statusBadge('ok',c.member_rank||'Thanh vien')+' Diem hien tai: <b>'+esc(c.loyalty_points||0)+'</b> - Tong chi: <b>'+fmt(c.total_spent||0)+'</b>';
    }).catch(e=>msg(e.message)),350);
}
function lookupMST(){
    const mst = String(document.getElementById('pos_customer_tax_code').value||'').trim();
    if(!mst){ msg('Vui long nhap Ma so thue'); return; }
    document.getElementById('posCustomerStatus').innerHTML = statusBadge('warn', 'Dang tra cuu MST...');
    fetch('https://api.vietqr.io/v2/business/'+mst)
        .then(r=>r.json())
        .then(d=>{
            if(d.code==='00' && d.data) {
                document.getElementById('pos_customer_name').value = d.data.name || '';
                document.getElementById('pos_customer_address').value = d.data.address || '';
                document.getElementById('posCustomerStatus').innerHTML = statusBadge('ok', 'Da tim thay thong tin MST');
            } else {
                document.getElementById('posCustomerStatus').innerHTML = statusBadge('used', 'Khong tim thay thong tin MST');
            }
        })
        .catch(e=>{
            document.getElementById('posCustomerStatus').innerHTML = statusBadge('used', 'Loi tra cuu MST');
            msg('Loi tra cuu MST');
        });
}
function renderRetailQuote(c){
    const discount=c.discount||{};
    document.getElementById('posQuote').innerHTML=[
        ['Tong truoc giam',fmt(c.gross_before_discount)],
        ['Khuyen mai',discount.code?esc(discount.code)+' - '+fmt(c.discount_amount):'Khong ap ma'],
        ['Tien truoc VAT',fmt(c.subtotal_amount)],
        ['VAT 10%',fmt(c.vat_amount)],
        ['Khach thanh toan',fmt(c.total_amount)],
        ['Bảo hành',esc(warrantyLabel(c))],
        ['Diem duoc cong',esc(c.loyalty_points_earned||0)+' diem']
    ].map(i=>`<div><span>${esc(i[0])}</span><b>${i[1]}</b></div>`).join('');
    document.getElementById('posSaleStatus').innerHTML=statusBadge('ok','Da tinh tien')+' Gia nhap duoc hieu la gia da gom VAT 10%.';
}
function scheduleRetailQuote(){
    clearTimeout(posQuoteTimer);
    const p=retailPayload();
    if(!String(p.product_name||'').trim()||Number(p.unit_gross_amount||0)<=0){
        document.getElementById('posQuote').innerHTML='';
        return;
    }
    posQuoteTimer=setTimeout(()=>previewRetailSale(true).catch(()=>{}),400);
}
function previewRetailSale(quiet=false){
    return api('admin_invoice_quote',retailPayload(),'POST').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tinh duoc giao dich');
        renderRetailQuote(d.calculation||{});
        return d.calculation||{};
    }).catch(e=>{
        document.getElementById('posSaleStatus').innerHTML=statusBadge('used','Khong hop le')+' '+esc(e.message);
        document.getElementById('posQuote').innerHTML='';
        if(!quiet) msg(e.message);
        throw e;
    });
}
function resetRetailSale(){
    ['pos_customer_name','pos_customer_phone','pos_customer_tax_code','pos_customer_address','pos_product_id','pos_product_source','pos_product_name','pos_gift_name','pos_promo_code','pos_note'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('pos_quantity').value=1;
    document.getElementById('pos_unit_gross').value=0;
    document.getElementById('pos_warranty_years').value=1;
    document.getElementById('pos_payment_method').value='cash';
    document.getElementById('posQuote').innerHTML='';
    document.getElementById('posCustomerStatus').textContent='Nhap so dien thoai de nhan dien thanh vien.';
    document.getElementById('posSaleStatus').textContent='Moi '+new Intl.NumberFormat('vi-VN').format(LOYALTY_VND_PER_POINT)+' VND thanh toan duoc cong 1 diem.';
}
function completeRetailSale(){
    if(!confirm('Xac nhan hoan tat ban hang, cong diem va tao hoa don?')) return;
    api('admin_retail_sale',retailPayload(),'POST').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong hoan tat duoc giao dich');
        const invoice=d.invoice||{};
        invoiceCache[invoice.id]=invoice;
        printSalesInvoice(invoice);
        resetRetailSale();
        loadRetailInvoices();
    }).catch(e=>msg(e.message));
}
function loadRetailInvoices(){
    return api('admin_sales_invoices').then(d=>{
        const rows=(d.data||[]).filter(i=>Number(i.customer_id||0)>0);
        document.getElementById('posSalesBody').innerHTML=rows.slice(0,100).map(i=>{
            invoiceCache[i.id]=i;
            const warranty=Number(i.warranty_years||0)>0?`<span class="worker-meta">Bảo hành: ${esc(warrantyLabel(i))}</span>`:'';
            return `<tr><td><b>${esc(i.invoice_code)}</b><span class="worker-meta">${esc(i.invoice_date||i.created_at)}</span></td><td>${esc(i.customer_name)}<span class="worker-meta">${esc(i.customer_phone)}</span></td><td>${esc(i.product_name)} x ${esc(i.quantity||1)}${warranty}</td><td><b>${fmt(i.total_amount||i.total_price)}</b></td><td>${esc(i.loyalty_points_earned||0)} diem</td><td><button class="btn" onclick="printSavedInvoice(${Number(i.id)})">In lai</button></td></tr>`;
        }).join('')||'<tr><td colspan="6" class="muted">Chua co giao dich ban hang tai quay.</td></tr>';
    }).catch(e=>msg(e.message));
}
function loadRetailSale(){ loadRetailProducts().catch(e=>msg(e.message)); loadRetailInvoices(); }

function loadStats(){
    api('admin_stats').then(d=>{
        const s=d.stats||{};
        const items=[
            ['Don hang',s.total_orders],['Doanh thu',fmt(s.total_revenue)],['Don hom nay',s.today_orders],
            ['Thu hom nay',fmt(s.today_revenue)],['Ca goi tho',s.total_jobs],['Ca cho tho',s.pending_jobs],
            ['Ca xong',s.completed_jobs],['San pham',s.total_products],['Tong tho',s.total_workers],
            ['Tho active',s.active_workers],['Tho bi khoa',s.blocked_workers],['No phi nen tang',fmt(s.unpaid_total)],
            ['Phi da thu hom nay',fmt(s.fees_paid_today)],['Cho doi soat',s.pending_worker_payments],['Device ban',s.banned_devices]
        ];
        document.getElementById('stats').innerHTML=items.map(i=>`<div class="card"><div class="stat-label">${esc(i[0])}</div><div class="stat-value">${esc(i[1]??0)}</div></div>`).join('');
    }).catch(e=>msg(e.message));
}

function workerStatus(w){
    if (Number(w.is_admin||0) === 1) return statusBadge('warn','Admin');
    if (Number(w.is_receive_blocked||0) === 1 || Number(w.payment_blocked||0) === 1) return statusBadge('used','Dang khoa');
    const debt = Number(w.unpaid_fee || 0);
    if (debt > 0) {
        const day = new Date().getDay(); // 0 is Sun, 1 is Mon, 2 is Tue
        if (day === 1) return statusBadge('warn', 'No phi'); // Thứ 2: Màu cau (Orange/Warning)
        return statusBadge('used', 'No phi'); // Thứ 3 - CN: Màu đỏ (Danger/Blocked)
    }
    return statusBadge('ok','Hoat dong'); // Màu xanh (OK)
}
function workerActionButtons(w){
    if (Number(w.is_admin||0) === 1) return '';
    return `<button class="btn warn" onclick="notifyWorkerFee(${Number(w.worker_id)})">Nhac phi</button> <button class="btn success" onclick="unbanWorkerId(${Number(w.worker_id)})">Mo khoa</button> <button class="btn" onclick="markPaid(${Number(w.worker_id)})">Xac nhan da TT</button>`;
}
function renderDashboardWorkers(rows){
    dashboardWorkersBody.innerHTML=(rows||[]).map(w=>`<tr><td><span class="worker-name">${esc(w.telegram_name||'Chua co ten')}</span><span class="worker-meta">ID ${esc(w.worker_id)} ${w.telegram_username?'@'+esc(w.telegram_username):''}</span></td><td>${esc(w.phone||'-')}<span class="worker-meta">${esc(w.identity_code||'-')} / ${esc(w.worker_type||'-')}</span></td><td>${esc(w.jobs_completed||0)}</td><td>${fmt(w.total_earned)}</td><td class="money-paid">${fmt(w.confirmed_paid_fee||w.total_paid_fee)}</td><td class="money-due">${fmt(w.unpaid_fee)}</td><td>${Number(w.pending_payment_count||0)>0?statusBadge('warn',w.pending_payment_count+' cho doi soat'):'-'}</td><td>${workerStatus(w)}<span class="worker-meta">${esc(w.block_reason||'')}</span></td><td><div class="row-actions">${workerActionButtons(w)}</div></td></tr>`).join('') || '<tr><td colspan="9" class="muted">Chua co du lieu tho.</td></tr>';
}
function loadDashboardWorkers(){ return api('admin_workers').then(d=>renderDashboardWorkers(d.data||[])); }
function loadPayments(){ return api('admin_worker_payments').then(d=>{ paymentsBody.innerHTML=(d.data||[]).map(p=>`<tr><td>#${esc(p.id)}</td><td>${esc(p.telegram_name||p.worker_id)}<span class="worker-meta">${esc(p.phone||'')}</span></td><td>${fmt(p.amount)}</td><td class="money-paid">${fmt(p.applied_amount)}</td><td>${esc(p.method)}</td><td>${esc(p.reference_code||p.external_transaction_id||'-')}</td><td>${esc(p.status)}</td><td>${esc(p.confirmed_at||p.created_at||'')}</td></tr>`).join('') || '<tr><td colspan="8" class="muted">Chua co thanh toan.</td></tr>'; }); }
function loadDashboard(){ loadStats(); loadDashboardWorkers(); loadPayments(); }
function downloadSettlement(){ window.location.href=API+'?action=admin_daily_settlement_excel'; }
function notifyWorkerFees(){ if(!confirm('Gui dung so no hien tai va QR thanh toan cho tat ca tho con no?')) return; api('admin_notify_worker_fees',{},'POST').then(d=>{msg(d.message||'Da gui nhac phi');loadDashboard();}); }
function notifyWorkerFee(id){ api('admin_notify_worker_fee',{worker_id:id},'POST').then(d=>{msg(d.message||'Da gui nhac phi');loadDashboard();}); }
function enforceWorkerFeeLock(){ if(!confirm('Khoa chuc nang nhan ca cua tat ca tho con no?')) return; api('admin_enforce_worker_fee_lock',{},'POST').then(d=>{msg(d.message||'Da khoa');loadDashboard();}); }

function loadOrders(){
    api('admin_orders').then(d=>{
        document.getElementById('ordersBody').innerHTML=(d.data||[]).map(o=>`<tr><td>#${o.id}</td><td>${esc(o.customer_name)}</td><td>${esc(o.customer_phone)}</td><td>${esc(o.product_name)}</td><td>${fmt(o.total_price)}</td><td>${esc(o.status)}</td><td>${esc(o.created_at)}</td><td><button class="btn" onclick="printInvoice(${o.id})">In</button> <button class="btn" style="background-color:#e11d48; color:white; border:none;" onclick="openViettelDraftModal(${o.id})">${o.viettel_invoice_exported==1?'Xem HĐ':'HĐ Viettel'}</button></td></tr>`).join('');
    });
}

function openViettelDraftModal(id) {
    api('admin_get_order', {id:id}).then(d => {
        const o = d.data;
        const exported = (o.viettel_invoice_exported == 1);
        const modalHtml = `
        <div id="viettelDraftModal" style="position:fixed; inset-block-start:0; inset-inline-start:0; inline-size:100%; block-size:100%; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; inline-size:500px; max-inline-size:90%;">
                <h2 style="margin-block-start:0; margin-block-end:16px;">${exported ? 'Chi Tiết Hóa Đơn (Đã xuất)' : 'Hóa Đơn Nháp - Sửa Đơn Hàng'}</h2>
                <input type="hidden" id="draft_order_id" value="${o.id}">
                <div style="margin-block-end:10px;">
                    <label style="display:block; font-weight:bold;">Tên khách hàng / Công ty</label>
                    <input id="draft_customer_name" style="inline-size:100%; padding:8px; box-sizing:border-box;" value="${esc(o.customer_name||'')}" ${exported?'disabled':''}>
                </div>
                <div style="margin-block-end:10px;">
                    <label style="display:block; font-weight:bold;">Mã số thuế</label>
                    <input id="draft_customer_tax_code" style="inline-size:100%; padding:8px; box-sizing:border-box;" value="${esc(o.customer_tax_code||'')}" ${exported?'disabled':''}>
                </div>
                <div style="margin-block-end:10px;">
                    <label style="display:block; font-weight:bold;">Địa chỉ</label>
                    <input id="draft_customer_address" style="inline-size:100%; padding:8px; box-sizing:border-box;" value="${esc(o.customer_address||'')}" ${exported?'disabled':''}>
                </div>
                <div style="margin-block-end:10px;">
                    <label style="display:block; font-weight:bold;">Số điện thoại</label>
                    <input id="draft_customer_phone" style="inline-size:100%; padding:8px; box-sizing:border-box;" value="${esc(o.customer_phone||'')}" ${exported?'disabled':''}>
                </div>
                <div style="margin-block-end:10px;">
                    <label style="display:block; font-weight:bold;">Tên sản phẩm / Dịch vụ</label>
                    <input id="draft_product_name" style="inline-size:100%; padding:8px; box-sizing:border-box;" value="${esc(o.product_name||'')}" ${exported?'disabled':''}>
                </div>
                <div style="margin-block-end:20px;">
                    <label style="display:block; font-weight:bold;">Tổng tiền (VND - Đã gồm VAT)</label>
                    <input id="draft_total_price" type="number" style="inline-size:100%; padding:8px; box-sizing:border-box;" value="${o.total_price||0}" ${exported?'disabled':''}>
                </div>
                <div style="display:flex; gap:10px;">
                    ${!exported ? `<button type="button" class="btn success" onclick="saveViettelDraft()" style="flex:1;">Lưu Nháp</button>
                    <button type="button" class="btn primary" onclick="saveAndSignViettel()" style="flex:1; background-color:#e11d48; border:none; color:white;">Lưu & Ký HĐ</button>` : ''}
                    <button type="button" class="btn" onclick="document.getElementById('viettelDraftModal').remove()" style="flex:1;">Đóng</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    });
}

function saveViettelDraft() {
    const id = document.getElementById('draft_order_id').value;
    const data = {
        id: id,
        customer_name: document.getElementById('draft_customer_name').value,
        customer_tax_code: document.getElementById('draft_customer_tax_code').value,
        customer_address: document.getElementById('draft_customer_address').value,
        customer_phone: document.getElementById('draft_customer_phone').value,
        product_name: document.getElementById('draft_product_name').value,
        total_price: document.getElementById('draft_total_price').value
    };
    return api('admin_update_order', data, 'POST').then(d => {
        if(d.status === 'success') {
            msg('Lưu nháp thành công!');
            if (document.getElementById('viettelDraftModal')) {
                document.getElementById('viettelDraftModal').remove();
            }
            loadOrders();
            return true;
        } else {
            msg(d.message || 'Lỗi lưu nháp');
            return false;
        }
    });
}

function saveAndSignViettel() {
    const id = document.getElementById('draft_order_id').value;
    const data = {
        id: id,
        customer_name: document.getElementById('draft_customer_name').value,
        customer_tax_code: document.getElementById('draft_customer_tax_code').value,
        customer_address: document.getElementById('draft_customer_address').value,
        customer_phone: document.getElementById('draft_customer_phone').value,
        product_name: document.getElementById('draft_product_name').value,
        total_price: document.getElementById('draft_total_price').value
    };
    api('admin_update_order', data, 'POST').then(d => {
        if(d.status === 'success') {
            if (document.getElementById('viettelDraftModal')) {
                document.getElementById('viettelDraftModal').remove();
            }
            loadOrders();
            exportViettelSingle(id);
        } else {
            msg(d.message || 'Lỗi lưu nháp');
        }
    });
}

let invoiceCache = {};
let invoiceQuoteTimer = null;
function invoicePayload(){
    return {
        customer_name:document.getElementById('sale_customer_name').value,
        customer_phone:document.getElementById('sale_customer_phone').value,
        customer_tax_code:document.getElementById('sale_customer_tax_code').value,
        customer_address:document.getElementById('sale_customer_address').value,
        product_name:document.getElementById('sale_product_name').value,
        quantity:document.getElementById('sale_quantity').value,
        unit_gross_amount:document.getElementById('sale_unit_gross').value,
        gift_name:document.getElementById('sale_gift_name').value,
        warranty_years:document.getElementById('sale_warranty_years').value,
        promo_code:document.getElementById('sale_promo_code').value,
        note:document.getElementById('sale_note').value
    };
}
function renderInvoiceQuote(c){
    const discount=c.discount||{};
    document.getElementById('saleQuote').innerHTML=[
        ['Tong da gom VAT truoc giam',fmt(c.gross_before_discount)],
        ['Khuyen mai',discount.code?esc(discount.code)+' - '+fmt(c.discount_amount):'Khong ap ma'],
        ['Tien truoc VAT',fmt(c.subtotal_amount)],
        ['VAT 10%',fmt(c.vat_amount)],
        ['Khach thanh toan',fmt(c.total_amount)],
        ['Bảo hành',esc(warrantyLabel(c))]
    ].map(i=>`<div><span>${esc(i[0])}</span><b>${i[1]}</b></div>`).join('');
    document.getElementById('saleQuoteStatus').innerHTML=statusBadge('ok','Da tinh dung VAT 10%')+' '+esc(discount.label||'');
}
function scheduleInvoiceQuote(){
    clearTimeout(invoiceQuoteTimer);
    const p=invoicePayload();
    if(!String(p.product_name||'').trim() || Number(p.unit_gross_amount||0)<=0){
        document.getElementById('saleQuote').innerHTML='';
        document.getElementById('saleQuoteStatus').textContent='Nhap hang hoa va gia de tinh hoa don.';
        return;
    }
    invoiceQuoteTimer=setTimeout(()=>previewInvoice(true).catch(()=>{}),400);
}
function previewInvoice(quiet=false){
    return api('admin_invoice_quote',invoicePayload(),'POST').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tinh duoc hoa don');
        renderInvoiceQuote(d.calculation||{});
        return d.calculation||{};
    }).catch(e=>{
        document.getElementById('saleQuoteStatus').innerHTML=statusBadge('used','Khong hop le')+' '+esc(e.message);
        document.getElementById('saleQuote').innerHTML='';
        if(!quiet) msg(e.message);
        throw e;
    });
}
function lookupMST_invoice(){
    const mst = String(document.getElementById('sale_customer_tax_code').value||'').trim();
    if(!mst){ msg('Vui long nhap Ma so thue'); return; }
    document.getElementById('saleCustomerStatus').innerHTML = statusBadge('warn', 'Dang tra cuu MST...');
    fetch('https://api.vietqr.io/v2/business/'+mst)
        .then(r=>r.json())
        .then(d=>{
            if(d.code==='00' && d.data) {
                document.getElementById('sale_customer_name').value = d.data.name || '';
                document.getElementById('sale_customer_address').value = d.data.address || '';
                document.getElementById('saleCustomerStatus').innerHTML = statusBadge('ok', 'Da tim thay thong tin MST');
            } else {
                document.getElementById('saleCustomerStatus').innerHTML = statusBadge('used', 'Khong tim thay thong tin MST');
            }
        })
        .catch(e=>{
            document.getElementById('saleCustomerStatus').innerHTML = statusBadge('used', 'Loi tra cuu MST');
            msg('Loi tra cuu MST');
        });
}
function resetSalesInvoiceForm(){
    ['sale_customer_name','sale_customer_phone','sale_customer_tax_code','sale_customer_address','sale_product_name','sale_gift_name','sale_promo_code','sale_note'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('sale_quantity').value=1;
    document.getElementById('sale_unit_gross').value=0;
    document.getElementById('sale_warranty_years').value=1;
    document.getElementById('saleQuote').innerHTML='';
    document.getElementById('saleCustomerStatus').textContent='';
    document.getElementById('saleQuoteStatus').textContent='Nhap hang hoa va gia de tinh hoa don.';
}
function createAndPrintInvoice(){
    api('admin_create_sales_invoice',invoicePayload(),'POST').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tao duoc hoa don');
        printSalesInvoice(d.invoice||{});
        resetSalesInvoiceForm();
        loadInvoices();
    }).catch(e=>msg(e.message));
}
function loadInvoices(){
    api('admin_sales_invoices').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tai duoc so hoa don');
        invoiceCache={};
        const company=d.company||{};
        document.getElementById('invoiceCompanyInfo').innerHTML=`<b>${esc(company.name||'Chua cau hinh ten cong ty')}</b><br>MST: ${esc(company.tax_code||'Chua cau hinh')}<br>Dia chi: ${esc(company.address||'Chua cau hinh')}<br>Dien thoai: ${esc(company.phone||'Chua cau hinh')} | Email: ${esc(company.email||'Chua cau hinh')}<br>Website: ${esc(company.website||'Chua cau hinh')}`;
        document.getElementById('invoiceBody').innerHTML=(d.data||[]).map(i=>{
            invoiceCache[i.id]=i;
            const warranty=Number(i.warranty_years||0)>0?`<span class="worker-meta">Bảo hành: ${esc(warrantyLabel(i))}</span>`:'';
            return `<tr><td><b>${esc(i.invoice_code)}</b><span class="worker-meta">${esc(i.invoice_date||i.created_at)}</span></td><td>${esc(i.customer_name||'Khach le')}<span class="worker-meta">${esc(i.customer_phone||'')}</span></td><td>${esc(i.product_name)} x ${esc(i.quantity||1)}${i.gift_name?`<span class="worker-meta">Qua: ${esc(i.gift_name)}</span>`:''}${warranty}</td><td>${fmt(i.subtotal_amount)}</td><td>${fmt(i.vat_amount)}</td><td>${fmt(i.discount_amount)}</td><td><b>${fmt(i.total_amount||i.total_price)}</b></td><td><button class="btn" onclick="printSavedInvoice(${Number(i.id)})">In lai</button></td></tr>`;
        }).join('') || '<tr><td colspan="8" class="muted">Chua co hoa don ban hang.</td></tr>';
    }).catch(e=>msg(e.message));
}

function updateInputInvoiceTotal(){
    const subtotal=Number(document.getElementById('input_subtotal').value||0);
    const vat=Number(document.getElementById('input_vat').value||0);
    const adjustment=Number(document.getElementById('input_adjustment').value||0);
    document.getElementById('input_total').value=Math.round(subtotal+vat+adjustment);
}
function loadBctReport(){
    const from=document.getElementById('bct_from').value;
    const to=document.getElementById('bct_to').value;
    api('admin_bct_reconciliation',{from,to,detail:0}).then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tai duoc bao cao');
        const r=d.report||{}, registers=r.invoice_registers||{}, ops=r.operational_records||{}, status=r.submission_status||{};
        const input=registers.input_purchase_invoices||{}, output=registers.output_sales_invoices||{};
        const orders=ops.confirmed_product_orders||{}, jobs=ops.completed_service_jobs||{}, fees=ops.platform_fee_accrual||{};
        const items=[
            ['HD dau vao',input.document_count||0],['Tong dau vao',fmt(input.total_amount)],
            ['HD dau ra',output.document_count||0],['Tong dau ra',fmt(output.total_amount)],
            ['Don da xac nhan',orders.count||0],['Tong don xac nhan',fmt(orders.total_amount)],
            ['Ca dich vu xong',jobs.count||0],['Phi nen tang',fmt(fees.total_amount)]
        ];
        document.getElementById('bctStats').innerHTML=items.map(i=>`<div class="card"><div class="stat-label">${esc(i[0])}</div><div class="stat-value">${esc(i[1]??0)}</div></div>`).join('');
        document.getElementById('bctReportStatus').innerHTML=(status.ready_for_submission?statusBadge('ok','San sang xuat bao cao'):statusBadge('used','Can doi soat'))
            + `<p>Ky: <b>${esc((r.period||{}).from||from)}</b> den <b>${esc((r.period||{}).to||to)}</b></p>`
            + `<p>Loi chan gui: <b>${esc(status.blocking_issue_count||0)}</b>. Canh bao: <b>${esc(status.warning_count||0)}</b>.</p>`;
        document.getElementById('bctIssuesBody').innerHTML=(r.issues||[]).map(i=>`<tr><td>${i.severity==='blocking'?statusBadge('used','Chan gui'):statusBadge('warn','Canh bao')}</td><td><code>${esc(i.code)}</code></td><td>${esc(i.count||0)}</td><td>${esc((i.ids||[]).join(', ')||'-')}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">Khong co van de trong pham vi doi soat he thong.</td></tr>';
    }).catch(e=>msg(e.message));
}
function loadInputInvoices(){
    api('admin_input_invoices').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tai duoc so hoa don');
        document.getElementById('inputInvoicesBody').innerHTML=(d.data||[]).map(i=>`<tr><td>#${esc(i.id)}</td><td><b>${esc(i.invoice_series)} / ${esc(i.invoice_number)}</b><span class="worker-meta">${esc(i.invoice_date)}</span></td><td>${esc(i.seller_name)}<span class="worker-meta">MST ${esc(i.seller_tax_code)}</span></td><td>${fmt(i.subtotal_amount)}</td><td>${fmt(i.vat_amount)}</td><td>${fmt(i.adjustment_amount)}</td><td><b>${fmt(i.total_amount)}</b></td><td><a class="btn" href="${esc(i.download_url)}" target="_blank" rel="noopener">Mo PDF</a><span class="worker-meta">${esc(i.pdf_original_name)} / ${esc(String(i.pdf_sha256||'').slice(0,16))}...</span></td><td>${statusBadge(i.status==='active'?'ok':'warn',i.status||'-')}</td></tr>`).join('') || '<tr><td colspan="9" class="muted">Chua co hoa don dau vao.</td></tr>';
    }).catch(e=>msg(e.message));
}
function uploadInputInvoice(event){
    event.preventDefault();
    const form=event.currentTarget;
    const button=form.querySelector('button[type="submit"]');
    button.disabled=true;
    const data=new FormData(form);
    fetch(API+'?action=admin_upload_input_invoice',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':CSRF},body:data})
        .then(async r=>{const text=await r.text();let d;try{d=JSON.parse(text);}catch(e){throw new Error(text||'Phan hoi khong hop le');}if(!r.ok||d.status!=='success')throw new Error(d.message||'Tai len that bai');return d;})
        .then(d=>{msg(d.message||'Da luu hoa don');form.reset();form.querySelector('[name="invoice_date"]').value=new Date().toISOString().slice(0,10);updateInputInvoiceTotal();loadInputInvoices();loadBctReport();})
        .catch(e=>msg(e.message))
        .finally(()=>{button.disabled=false;});
}

function loadStores(){
    api('admin_get_stores').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tai duoc cua hang');
        document.getElementById('storesBody').innerHTML=(d.data||[]).map(s=>{
            const isActive=s.status==='active';
            const hasKey=!!s.login_key;
            // QR dang nhap (sau khi duyet)
            const loginQR=isActive&&hasKey&&s.qr_image_url
                ?`<div style="margin-block-end:10px;background:#f0fdf4;border:2px solid #10b981;padding:10px;border-radius:8px;">
                    <div style="color:#065f46;font-weight:bold;font-size:12px;margin-block-end:6px;">QR DANG NHAP APP CUA HANG</div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="${esc(s.qr_image_url)}" style="inline-size:90px;block-size:90px;border-radius:6px;border:1px solid #6ee7b7;">
                        <div>
                            <button class="btn success" data-key="${esc(s.login_key||'')}" onclick="copyStoreKeyFromButton(this)">Copy Key</button>
                            <div style="margin-block-start:5px;font-size:11px;color:#6b7280;">Gui QR nay cho chu cua hang</div>
                        </div>
                    </div>
                </div>`
                :'';
            // QR bao cao doanh thu
            const reportQR=s.report_qr_image_url
                ?`<div style="background:#eff6ff;border:2px solid #3b82f6;padding:10px;border-radius:8px;">
                    <div style="color:#1e40af;font-weight:bold;font-size:12px;margin-block-end:6px;">QR BAO CAO DOANH THU</div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="${esc(s.report_qr_image_url)}" style="inline-size:74px;block-size:74px;border-radius:6px;border:1px solid #93c5fd;">
                        <div>
                            <a class="btn" href="${esc(s.report_url||'#')}" target="_blank">Xem Link</a><br><br>
                            <a class="btn" href="${esc(s.report_qr_image_url)}" target="_blank" download>Tai QR</a>
                        </div>
                    </div>
                </div>`
                :'';
            // Nut phe duyet
            const actions=isActive&&hasKey
                ?`<div style="display:flex;flex-direction:column;gap:8px;">${loginQR}${reportQR}<button onclick="deleteStore(${Number(s.id)},this)" style="background:#ef4444;color:#fff;border:none;padding:8px;border-radius:6px;font-weight:bold;cursor:pointer;inline-size:100%;margin-block-start:5px;">XOA CUA HANG</button></div>`
                :`<div style="text-align:center;padding:8px;">
                    <div style="background:#fef3c7;border:2px solid #f59e0b;border-radius:8px;padding:10px;margin-block-end:8px;">
                        <div style="color:#92400e;font-weight:bold;font-size:13px;">CHO PHE DUYET</div>
                        <div style="color:#78350f;font-size:11px;margin-block-start:3px;">Giam doc chua phe duyet cua hang nay</div>
                    </div>
                    <button onclick="approveStore(${Number(s.id)},this)" style="background:#16a34a;color:#fff;border:none;padding:10px 18px;border-radius:8px;font-weight:bold;font-size:14px;cursor:pointer;inline-size:100%;margin-block-end:8px;">
                        PHE DUYET CUA HANG
                    </button>
                    <button onclick="deleteStore(${Number(s.id)},this)" style="background:#ef4444;color:#fff;border:none;padding:10px 18px;border-radius:8px;font-weight:bold;font-size:14px;cursor:pointer;inline-size:100%;">
                        XOA CUA HANG
                    </button>
                </div>`;
            return `<tr>
                <td><b>#${s.id}</b></td>
                <td><b>${esc(s.store_name)}</b><br><span class="worker-meta">Chu: ${esc(s.owner_name||'-')}<br>DK: ${esc(s.created_at||'-')}</span></td>
                <td>MST: <b>${esc(s.tax_code)}</b><br>SDT: ${esc(s.phone)}</td>
                <td>${esc(s.store_type)}<br><small>${esc(s.address)}</small></td>
                <td><b style="color:#dc2626">${fmt(s.total_sales||0)}</b><br><span class="worker-meta">${esc(s.order_count||0)} don</span></td>
                <td>${statusBadge(isActive?'ok':'warn',isActive?'active':'pending')}</td>
                <td style="min-inline-size:220px">${actions}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" style="text-align:center;padding:20px;color:#6b7280;">Chua co cua hang nao dang ky.</td></tr>';
    }).catch(e=>{
        document.getElementById('storesBody').innerHTML=`<tr><td colspan="7" class="muted">Loi tai cua hang: ${esc(e.message)}</td></tr>`;
    });
}
function copyStoreKey(value){
    const text=String(value||'');
    if(!text){ msg('Chua co key cua hang'); return; }
    if(navigator.clipboard&&navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(()=>msg('Da copy key dang nhap cua hang')).catch(()=>prompt('Copy key cua hang:',text));
    }else{
        prompt('Copy key cua hang:',text);
    }
}
function copyStoreKeyFromButton(button){ copyStoreKey(button.dataset.key||''); }
function approveStore(id, btn){
    if(!confirm('Xac nhan PHE DUYET cua hang nay?\n\nSau khi duyet, cua hang se duoc cap ma QR dang nhap va hoat dong tren nen tang Cho Xa Lap Vo.')) return;
    if(btn){btn.disabled=true;btn.textContent='Dang xu ly...';}
    api('admin_approve_store',{id},'POST').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong phe duyet duoc');
        msg('Da phe duyet cua hang thanh cong!');
        loadStores();
    }).catch(e=>{
        if(btn){btn.disabled=false;btn.textContent='PHE DUYET CUA HANG';}
        msg('Loi: ' + e.message);
    });
}

function deleteStore(id, btn){
    if(!confirm('XAC NHAN XOA CUA HANG NAY?\n\nHanh dong nay khong the phuc hoi. Moi thong tin cua hang se bi xoa khoi he thong.')) return;
    if(btn){btn.disabled=true;btn.textContent='Dang xoa...';}
    api('admin_delete_store',{id},'POST').then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong xoa duoc cua hang');
        msg('Da xoa cua hang khoi he thong!');
        loadStores();
    }).catch(e=>{
        if(btn){btn.disabled=false;btn.textContent='XOA CUA HANG';}
        msg('Loi: ' + e.message);
    });
}

function settleStores(){
    if(!confirm('Xac nhan chot doi soat cuoi thang cho tat ca cua hang?')) return;
    api('admin_settle_stores').then(d=>{
        if(d.status==='success'){
            alert(d.message);
            loadStores();
        }else{
            throw new Error(d.message||'Khong chot duoc doi soat cua hang');
        }
    }).catch(e=>msg(e.message));
}

let cachedUsers = [];
function loadUsers(){
    api('admin_users').then(d=>{
        cachedUsers = d.data || [];
        usersBody.innerHTML = cachedUsers.map(u=>`
        <tr>
            <td>${u.id}</td>
            <td>
                <strong>${esc(u.fullname)}</strong><br>
                <small class="muted">Role: ${esc(u.role)}</small>
            </td>
            <td>${esc(u.phone)}</td>
            <td>
                <span style="color:#047857; font-weight:bold;">${esc(u.member_rank)}</span><br>
                <small class="muted">${fmt(u.total_spent)} - ${esc(u.loyalty_points||0)} diem</small>
            </td>
            <td>
                ${u.qr_image_url ? `<img src="${esc(u.qr_image_url)}" alt="QR" style="inline-size:80px;block-size:80px;border-radius:4px; border:1px solid #ddd;"><br><small class="muted"><code>${esc(u.login_key||'')}</code></small>` : '<span class="muted">Chua co QR</span>'}
            </td>
            <td>
                ${Number(u.is_active) === 1 ? '<span class="status ok" style="display:inline-block; margin:0; padding:4px 8px;">Active</span>' : '<span class="status err" style="display:inline-block; margin:0; padding:4px 8px;">Banned</span>'}
            </td>
            <td>
                <div class="row-actions">
                    <button class="btn" onclick="editUser(${u.id})">Sua</button>
                    <button class="btn warn" onclick="toggleUserStatus(${u.id})">${Number(u.is_active) === 1 ? 'Ban' : 'Unban'}</button>
                    <button class="btn danger" onclick="deleteUser(${u.id})">Xoa</button>
                </div>
            </td>
        </tr>
        `).join('') || '<tr><td colspan="7" class="muted">Chua co khach hang nao.</td></tr>';
    });
}

function openUserModal(user = null) {
    const existing = document.getElementById('userModal');
    if (existing) existing.remove();
    const modalHtml = `
    <div id="userModal" style="position:fixed; inset-block-start:0; inset-inline-start:0; inline-size:100%; block-size:100%; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; border-radius:8px; inline-size:400px; max-inline-size:90%;">
            <h2>${user ? 'Sua Khach hang' : 'Them Khach hang'}</h2>
            <form onsubmit="saveUser(event, ${user ? user.id : 0})">
                <label>Ten</label>
                <input id="u_fullname" value="${user ? esc(user.fullname) : ''}" required style="margin-block-end:10px; inline-size:100%; padding:8px;">
                <label>SDT</label>
                <input id="u_phone" value="${user ? esc(user.phone) : ''}" required style="margin-block-end:10px; inline-size:100%; padding:8px;">
                <label>Role</label>
                <select id="u_role" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                    <option value="buyer" ${user && user.role==='buyer'?'selected':''}>Nguoi mua (buyer)</option>
                    <option value="admin" ${user && user.role==='admin'?'selected':''}>Admin</option>
                </select>
                <label>Hang Thanh vien</label>
                <input id="u_rank" value="${user ? esc(user.member_rank) : 'Thành viên'}" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                <label>Tong chi tieu (VND)</label>
                <input type="number" id="u_spent" value="${user ? user.total_spent : 0}" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                <label>Diem thanh vien</label>
                <input type="number" id="u_points" value="${user ? user.loyalty_points : 0}" min="0" style="margin-block-end:10px; inline-size:100%; padding:8px;">
                <input type="hidden" id="u_active" value="${user ? user.is_active : 1}">
                <div style="display:flex; gap:10px; margin-block-start:15px;">
                    <button type="submit" class="btn primary" style="flex:1;">Luu</button>
                    <button type="button" class="btn" onclick="document.getElementById('userModal').remove()" style="flex:1;">Huy</button>
                </div>
            </form>
        </div>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function editUser(id) {
    const user = cachedUsers.find(u => Number(u.id) === id);
    if(user) openUserModal(user);
}

function toggleUserStatus(id) {
    const user = cachedUsers.find(u => Number(u.id) === id);
    if(!user) return;
    const newData = {
        action: 'admin_save_user',
        id: id,
        fullname: user.fullname,
        phone: user.phone,
        role: user.role,
        member_rank: user.member_rank,
        total_spent: user.total_spent,
        loyalty_points: user.loyalty_points,
        is_active: Number(user.is_active) === 1 ? 0 : 1
    };
    api(newData.action, newData).then(d=>{
        msg(d.message);
        loadUsers();
    });
}

function saveUser(e, id) {
    e.preventDefault();
    const form = e.target;
    api('admin_save_user', {
        id: id,
        fullname: form.querySelector('#u_fullname').value,
        phone: form.querySelector('#u_phone').value,
        role: form.querySelector('#u_role').value,
        member_rank: form.querySelector('#u_rank').value,
        total_spent: form.querySelector('#u_spent').value,
        loyalty_points: form.querySelector('#u_points').value,
        is_active: form.querySelector('#u_active') ? form.querySelector('#u_active').value : 1
    }).then(d=>{
        if(d.status === 'success') {
            msg(d.message);
            document.getElementById('userModal').remove();
            loadUsers();
        } else {
            msg(d.message || 'Có lỗi xảy ra', 'error');
        }
    }).catch(e=>{
        msg('Lỗi hệ thống: ' + e.message, 'error');
    });
}

function deleteUser(id) {
    if(!confirm('Xoa khach hang nay? Hanh dong khong the phuc hoi.')) return;
    api('admin_delete_user', {id: id}).then(d=>{
        msg(d.message);
        loadUsers();
    });
}

function printInvoice(id){
    api('admin_invoice',{order_id:id}).then(d=>{
        if(d.status!=='success') throw new Error(d.message||'Khong tao duoc hoa don');
        printSalesInvoice(d.invoice||{});
    }).catch(e=>msg(e.message));
}
function printSavedInvoice(id){ const invoice=invoiceCache[id]; if(invoice) printSalesInvoice(invoice); }
function printSalesInvoice(i){
    const promo=i.promo_code?`<p><b>Mã khuyến mãi:</b> ${esc(i.promo_code)}</p>`:'';
    const gift=i.gift_name?`<p><b>Quà tặng kèm:</b> ${esc(i.gift_name)}</p>`:'';
    const note=i.note?`<p><b>Ghi chú:</b> ${esc(i.note)}</p>`:'';
    const points=Number(i.loyalty_points_earned||0);
    const loyalty=points>0?`<p><b>Điểm thành viên được cộng:</b> ${esc(points)} điểm${i.customer_loyalty_points?' - Tổng điểm: '+esc(i.customer_loyalty_points):''}</p>`:'';
    const warranty=Number(i.warranty_years||0)>0?`<div class="invoice-warranty"><b>Bảo hành:</b> ${esc(warrantyLabel(i))}<br><span>Khách vui lòng giữ hóa đơn này để được hỗ trợ bảo hành.</span></div>`:'';
    document.getElementById('printRoot').innerHTML=`<div class="print-box invoice-print">
        <div class="invoice-print-head">
            <div class="invoice-brand"><img id="invoiceLogo" class="invoice-logo" src="logo.jpg" alt="Logo Điện Tử Hiếu"><div><h1>${esc(i.company_name||'ĐIỆN TỬ HIẾU')}</h1><p><b>Mã số thuế:</b> ${esc(i.company_tax_code||'-')}</p><p><b>Địa chỉ:</b> ${esc(i.company_address||'-')}</p><p><b>Điện thoại:</b> ${esc(i.company_phone||'-')} &nbsp; <b>Email:</b> ${esc(i.company_email||'-')}</p><p><b>Website:</b> ${esc(i.company_website||'-')}</p></div></div>
            <div class="invoice-print-title"><h2>HÓA ĐƠN BÁN HÀNG</h2><p><b>Số:</b> ${esc(i.invoice_code||i.id)}</p><p><b>Ngày:</b> ${esc(viDate(i.invoice_date||i.created_at||''))}</p><p>Thuế suất VAT: <b>10%</b></p><p>Chứng từ do công ty phát hành từ hệ thống bán hàng.</p></div>
        </div>
        <div class="invoice-print-meta"><div><b>Khách hàng:</b> ${esc(i.customer_name||'Khách lẻ')}</div><div><b>Mã số thuế:</b> ${esc(i.customer_tax_code||'-')}</div><div><b>Số điện thoại:</b> ${esc(i.customer_phone||'-')}</div><div><b>Phương thức thanh toán:</b> ${esc(paymentLabel(i.payment_method||'-'))}</div><div><b>Hạng thành viên:</b> ${esc(i.customer_member_rank||'-')}</div><div style="grid-column:1/-1"><b>Địa chỉ:</b> ${esc(i.customer_address||'-')}</div></div>
        <table><thead><tr><th>Hàng hóa</th><th>Số lượng</th><th>Đơn giá đã gồm VAT</th><th>Thành tiền trước giảm</th></tr></thead><tbody><tr><td>${esc(i.product_name||'')}</td><td>${esc(i.quantity||1)}</td><td>${fmt(i.unit_gross_amount||i.total_amount)}</td><td>${fmt(i.gross_before_discount||i.total_amount)}</td></tr></tbody></table>
        ${gift}${promo}${loyalty}${note}${warranty}
        <div class="invoice-totals"><div><span>Tổng đã gồm VAT trước giảm</span><b>${fmt(i.gross_before_discount||i.total_amount)}</b></div><div><span>Khuyến mãi</span><b>- ${fmt(i.discount_amount||0)}</b></div><div><span>Tiền hàng trước VAT</span><b>${fmt(i.subtotal_amount)}</b></div><div><span>VAT 10%</span><b>${fmt(i.vat_amount)}</b></div><div class="grand"><span>Khách thanh toán</span><b>${fmt(i.total_amount||i.total_price)}</b></div></div>
        <div class="invoice-signatures"><div><b>Người mua hàng</b><p class="muted">Ký, ghi rõ họ tên</p></div><div><b>Người bán hàng</b><p class="muted">Ký, ghi rõ họ tên</p></div></div>
    </div>`;
    const logo=document.getElementById('invoiceLogo');
    let printed=false;
    const doPrint=()=>{if(printed)return;printed=true;window.print();};
    if(!logo||logo.complete) doPrint(); else {logo.onload=doPrint;logo.onerror=doPrint;setTimeout(doPrint,1200);}
}

function printContract() {
    const c_name = document.getElementById('contract_name').value.trim();
    const c_phone = document.getElementById('contract_phone').value.trim();
    const c_cccd = document.getElementById('contract_cccd').value.trim();
    const c_cat = document.getElementById('contract_category').value.trim();
    if(!c_name || !c_phone || !c_cccd || !c_cat) {
        alert('Vui lòng nhập đầy đủ thông tin'); return;
    }
    
    const d = new Date();
    const code = 'HDHT-' + d.getFullYear() + (d.getMonth()+1).toString().padStart(2,'0') + d.getDate().toString().padStart(2,'0') + '-' + Math.floor(Math.random()*9000+1000);
    const day = d.getDate().toString().padStart(2,'0');
    const month = (d.getMonth()+1).toString().padStart(2,'0');
    const year = d.getFullYear();

    document.getElementById('printRoot').innerHTML=`<div class="print-box invoice-print" style="line-height:1.6; font-size: 14px;">
        <div style="text-align:center; margin-block-end: 20px;">
            <h2 style="margin:0; font-size: 20px; font-weight: bold;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h2>
            <p style="margin:5px 0; font-size: 16px; font-weight: bold; text-decoration: underline;">Độc lập - Tự do - Hạnh phúc</p>
        </div>
        <div style="text-align:center; margin-block-end: 30px;">
            <h1 style="margin:0; font-size: 24px;">HỢP ĐỒNG HỢP TÁC</h1>
            <p style="margin:5px 0;">Số hồ sơ: <b>${code}</b></p>
            <p style="margin:5px 0; font-style: italic;">Đồng Tháp, ngày ${day} tháng ${month} năm ${year}</p>
        </div>
        <p>Hôm nay, tại văn phòng CÔNG TY TNHH MTV ĐIỆN TỬ HIẾU, chúng tôi gồm có:</p>
        
        <h3 style="font-size: 16px; text-transform: uppercase;">BÊN A (BÊN TỔ CHỨC CUNG ỨNG DỊCH VỤ): CÔNG TY TNHH MTV ĐIỆN TỬ HIẾU</h3>
        <p style="margin:5px 0;"><b>Mã số thuế:</b> 1402228630</p>
        <p style="margin:5px 0;"><b>Địa chỉ:</b> 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp</p>
        <p style="margin:5px 0;"><b>Điện thoại:</b> 0979.553.289</p>

        <h3 style="font-size: 16px; text-transform: uppercase;">BÊN B (ĐỐI TÁC THỰC HIỆN DỊCH VỤ):</h3>
        <p style="margin:5px 0;"><b>Họ và tên:</b> ${esc(c_name)}</p>
        <p style="margin:5px 0;"><b>Số CCCD:</b> ${esc(c_cccd)}</p>
        <p style="margin:5px 0;"><b>Điện thoại:</b> ${esc(c_phone)}</p>
        <p style="margin:5px 0;"><b>Hạng mục hợp tác:</b> ${esc(c_cat)}</p>

        <h3 style="font-size: 16px;">ĐIỀU 1: NỘI DUNG HỢP TÁC</h3>
        <p>Hai bên thống nhất ký kết hợp đồng hợp tác để triển khai thực hiện các công việc thuộc hạng mục <b>${esc(c_cat)}</b> do Bên A điều phối từ nền tảng ứng dụng (app/web).</p>

        <h3 style="font-size: 16px;">ĐIỀU 2: TÍNH CHẤT HỢP TÁC</h3>
        <p>Đây là <b>Hợp đồng Hợp tác</b>, hai bên cùng kinh doanh, cùng chia sẻ doanh thu theo thỏa thuận từng đơn hàng. Hợp đồng này KHÔNG phải là hợp đồng lao động, do đó không phát sinh các quyền lợi về bảo hiểm xã hội, bảo hiểm y tế, bảo hiểm thất nghiệp, hay lương thưởng cố định cho Bên B.</p>

        <h3 style="font-size: 16px;">ĐIỀU 3: TRÁCH NHIỆM VÀ QUYỀN LỢI HAI BÊN</h3>
        <ul style="padding-inline-start: 20px;">
            <li style="margin-block-end: 8px;"><b>Bên A:</b> Cung cấp nền tảng ứng dụng, tìm kiếm khách hàng, điều phối đơn hàng cho Bên B. Đảm bảo thanh toán đối soát phần chia sẻ doanh thu cho Bên B theo đúng quy định của nền tảng.</li>
            <li style="margin-block-end: 8px;"><b>Bên B:</b> Tiếp nhận và thực hiện công việc với khách hàng một cách chuyên nghiệp, đúng quy trình, đảm bảo an toàn lao động. Tự chuẩn bị đồ nghề, vật tư, phương tiện đi lại và tự chịu trách nhiệm về an toàn trong quá trình thi công/cung cấp dịch vụ.</li>
            <li style="margin-block-end: 8px;"><b>Tỷ lệ chia sẻ doanh thu:</b> Được tính dựa trên biểu phí dịch vụ công khai trên hệ thống và đối soát định kỳ theo quy định của nền tảng.</li>
        </ul>

        <h3 style="font-size: 16px;">ĐIỀU 4: HIỆU LỰC VÀ CHẤM DỨT HỢP ĐỒNG</h3>
        <p>Hợp đồng có hiệu lực kể từ ngày ký. Hợp đồng sẽ tự động chấm dứt khi một trong hai bên thông báo ngừng hợp tác hoặc Bên B vi phạm nghiêm trọng quy chế hoạt động của nền tảng.</p>

        <div class="invoice-signatures" style="margin-block-start: 40px; padding-block-end: 50px;">
            <div>
                <b>ĐẠI DIỆN BÊN B</b>
                <p class="muted">(Ký, ghi rõ họ tên)</p>
            </div>
            <div>
                <b>ĐẠI DIỆN BÊN A</b>
                <p class="muted">(Ký, ghi rõ họ tên, đóng dấu)</p>
            </div>
        </div>
    </div>`;
    
    let printed=false;
    const doPrint=()=>{if(printed)return;printed=true;window.print();};
    setTimeout(doPrint, 200);
}

function loadJobs(){
    api('admin_jobs').then(d=>{
        document.getElementById('jobsBody').innerHTML=(d.data||[]).map(j=>`<tr><td>#${j.id}</td><td>${esc(j.customer_name)}</td><td>${esc(j.customer_phone)}</td><td>${esc(j.service_type)}</td><td>${esc(j.address)}${j.maps_url?`<span class="worker-meta">${esc(j.map_location)}</span><a class="btn" href="${esc(j.maps_url)}" target="_blank" rel="noopener">Google Maps</a>`:''}</td><td>${fmt(j.final_total)}</td><td>${esc(j.worker_id||'-')}</td><td>${esc(j.status)}</td><td>${esc(j.created_at)}</td></tr>`).join('');
    });
}
function sendTestJob(){ if(!confirm('Gui ca test len nhom tho?')) return; api('admin_test_worker_job',{},'POST').then(d=>{ msg(d.message||'Da gui test'); loadJobs(); loadStats(); }); }

let productCache = {};
const BLANK_IMG='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
function imageSrc(src){ src = String(src || '').trim(); return src === '' ? BLANK_IMG : src; }
function loadProducts(){
    api('admin_products').then(d=>{
        productCache = {};
        document.getElementById('productsBody').innerHTML=(d.data||[]).map(p=>{
            productCache[p.id] = p;
            return `<tr><td>${p.id}</td><td><img class="thumb" src="${esc(imageSrc(p.image_url))}" onerror="this.src=BLANK_IMG;this.onerror=null"></td><td>${esc(p.name)}</td><td>${esc(p.category)}</td><td>${fmt(p.price)}</td><td>${esc(p.stock_quantity)}</td><td><button class="btn" onclick="editProductById(${p.id})">Sua</button> <button class="btn danger" onclick="deleteProduct(${p.id})">Xoa</button></td></tr>`;
        }).join('');
    });
}
function editProductById(id){ const p = productCache[id] || {}; prod_id.value=p.id||''; prod_name.value=p.name||''; prod_price.value=p.price||0; prod_stock.value=p.stock_quantity||0; prod_category.value=p.category||''; prod_image.value=p.image_url||''; }
function saveProduct(){ api('admin_save_product',{id:prod_id.value,name:prod_name.value,price:prod_price.value,stock:prod_stock.value,category:prod_category.value,image_url:prod_image.value},'POST').then(d=>{ msg(d.message||'Da luu'); loadProducts(); }); }
function deleteProduct(id){ if(!confirm('Xoa san pham #' + id + '?')) return; api('admin_delete_product',{id},'POST').then(d=>{ msg(d.message||'Da xoa'); loadProducts(); }); }
function aiImportProduct() {
    const fileInput = document.getElementById('ai_product_image');
    const textInput = document.getElementById('ai_product_text').value.trim();
    const btn = document.getElementById('btnAiImport');
    const status = document.getElementById('aiImportStatus');

    if (!fileInput.files[0] && !textInput) {
        alert('Vui lòng chọn ảnh hoặc nhập thông tin sản phẩm!');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Đang phân tích... (Anh Thiên đang nghĩ)';
    status.textContent = 'Đang gửi dữ liệu lên Gemini AI...';

    const reqData = { text: textInput };

    const sendApi = () => {
        api('ai_import_product', reqData, 'POST').then(d => {
            btn.disabled = false;
            btn.textContent = 'Phân tích & Thêm sản phẩm';
            if (d.success) {
                status.innerHTML = `<span style="color:#16a34a">Đã thêm thành công: ${esc(d.data.name)}</span>`;
                loadProducts();
                document.getElementById('ai_product_text').value = '';
                fileInput.value = '';
            } else {
                status.innerHTML = `<span style="color:#dc2626">Lỗi: ${esc(d.error || 'Không rõ nguyên nhân')}</span>`;
            }
        }).catch(err => {
            btn.disabled = false;
            btn.textContent = 'Phân tích & Thêm sản phẩm';
            status.innerHTML = `<span style="color:#dc2626">Lỗi kết nối: ${esc(err.message)}</span>`;
        });
    };

    if (fileInput.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            reqData.image = e.target.result;
            sendApi();
        };
        reader.readAsDataURL(fileInput.files[0]);
    } else {
        sendApi();
    }
}

function qrCodeUrl(code){ return 'https://api.qrserver.com/v1/create-qr-code/?size=96x96&margin=6&data=' + encodeURIComponent(String(code || '')); }
function statusBadge(kind, text){ return `<span class="badge ${kind}">${esc(text)}</span>`; }
function copyControls(code){
    const qr = qrCodeUrl(code);
    return `<div class="qr-actions"><button class="btn copy-mini" type="button" data-copy="${esc(code)}">Copy code</button><button class="btn copy-mini" type="button" data-copy="${esc(qr)}">Copy QR link</button></div><input class="qr-copy-text" readonly value="${esc(qr)}" onclick="this.select()">`;
}
function renderCreatedCodes(target, codes){
    target.classList.remove('muted');
    target.classList.add('created-codes');
    target.innerHTML = (codes || []).map(code => {
        const qr = qrCodeUrl(code);
        return `<div class="code-card"><img class="qr-mini" src="${esc(qr)}" alt="QR ${esc(code)}"><div><b>${esc(code)}</b>${copyControls(code)}</div></div>`;
    }).join('') || '<span class="muted">Khong tao duoc ma.</span>';
}
function loadVouchers(){
    api('admin_vouchers').then(d=>{
        voucherBody.innerHTML=(d.data||[]).map(v=>{
            const used = Number(v.used_count || 0);
            const max = Number(v.max_uses || v.usage_limit || 0);
            const exhausted = max > 0 && used >= max;
            const badge = used > 0 ? statusBadge('used','Da dung') : (exhausted ? statusBadge('warn','Het luot') : statusBadge('ok','Chua dung'));
            return `<tr><td><img class="qr-mini" src="${qrCodeUrl(v.code)}" alt="QR ${esc(v.code)}"></td><td><b>${esc(v.code)}</b>${copyControls(v.code)}</td><td>${esc(v.discount_percent||0)}%</td><td>${esc(used)}/${esc(max)}</td><td>${badge}</td><td>${esc(v.expires_at||'')}</td></tr>`;
        }).join('');
    });
}
function loadCoupons(){
    api('admin_coupons').then(d=>{
        couponBody.innerHTML=(d.data||[]).map(c=>{
            const used = Number(c.is_used || 0) === 1;
            const badge = used ? statusBadge('used','Da dung') : statusBadge('ok','Chua dung');
            return `<tr><td>${esc(c.id)}</td><td><img class="qr-mini" src="${qrCodeUrl(c.code)}" alt="QR ${esc(c.code)}"></td><td><b>${esc(c.code)}</b>${copyControls(c.code)}</td><td>${esc(c.type)}</td><td>${esc(c.value || c.discount_amount || 0)}</td><td>${esc(c.description)}</td><td>${badge}</td></tr>`;
        }).join('');
    });
}
function generateVoucher(){ api('generate_voucher',{discount_percent:voucher_percent.value,count:voucher_count.value,max_uses:100},'POST').then(d=>{ renderCreatedCodes(voucherResult, d.codes||[]); loadVouchers(); }); }
function toggleQrForms() {
    const type = document.getElementById('qr_type_selector').value;
    document.querySelectorAll('.qr-form-section').forEach(el => el.style.display = 'none');
    document.getElementById('form_' + type).style.display = 'block';
}

function generateFreeshipQR() {
    api('generate_qr', {
        value: 0, 
        description: document.getElementById('freeship_desc').value, 
        count: document.getElementById('freeship_count').value, 
        type: 'freeship'
    }, 'POST').then(d => { 
        renderCreatedCodes(document.getElementById('freeshipResult'), d.codes || []); 
        loadCoupons(); 
    });
}

function generateQR(){ api('generate_qr',{value:qr_value.value,description:qr_desc.value,count:qr_count.value,type:'discount'},'POST').then(d=>{ renderCreatedCodes(qrResult, d.codes||[]); loadCoupons(); }); }

function generateACWarrantyQR() {
    const name = document.getElementById('qr_ac_name').value.trim();
    const phone = document.getElementById('qr_ac_phone').value.trim();
    const product = document.getElementById('qr_ac_product').value.trim();
    const serial = document.getElementById('qr_ac_serial').value.trim();

    if (!name || !phone || !product) {
        msg('Vui lòng nhập đầy đủ Tên KH, SĐT và Tên sản phẩm', 'error');
        return;
    }

    const today = new Date();
    const dateStr = today.getDate().toString().padStart(2, '0') + '/' +
                    (today.getMonth() + 1).toString().padStart(2, '0') + '/' +
                    today.getFullYear();

    const textPayload = `Cty phát hành: CTY TNHH MTV Điện Tử Hiếu
MST: 1402228630
ĐC: 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp
Sđt: 0979.553.289
Bắt đầu từ ngày lập mã: ${dateStr}
THÔNG TIN MÃ KHUYẾN MÃI: Miễn phí vệ sinh máy lạnh trọn đời máy (Tối đa 15 năm)
Thông tin khách hàng: ${name}, ${phone}
Sản phẩm: ${product} ${serial ? '(S/N: '+serial+')' : ''}`.trim();

    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=5&data=' + encodeURIComponent(textPayload);

    const printWindow = window.open('', '_blank', 'width=600,height=800');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>In QR Bao Hanh Ve Sinh AC</title>
            <style>
                body { font-family: sans-serif; text-align: center; padding: 20px; background: #f0f0f0; margin: 0; }
                .print-area { background: #fff; border: 2px dashed #333; padding: 20px; display: inline-block; margin-top: 20px; width: 90%; max-width: 350px; box-sizing: border-box; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                h2 { margin: 0 0 10px 0; font-size: 18px; text-transform: uppercase; color: #d0021b; }
                p { margin: 5px 0; font-size: 14px; color: #333; line-height: 1.4; }
                img { width: 80%; max-width: 200px; height: auto; margin: 15px auto; display: block; border: none; box-sizing: border-box; }
                .btn-print { padding: 12px 24px; font-size: 16px; cursor: pointer; background: #0066cc; color: #fff; border: none; border-radius: 4px; font-weight: bold; }
                .btn-print:hover { background: #005bb5; }
                @media print {
                    @page { margin: 10mm; }
                    body { background: #fff; padding: 0; text-align: center; }
                    .no-print { display: none; }
                    .print-area { border: 1px solid #000; padding: 20px; margin: 0 auto; width: 95%; max-width: 320px; box-shadow: none; border-radius: 0; page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-block-end: 20px;">
                <button class="btn-print" onclick="window.print()">🖨️ IN TEM QR NÀY</button>
                <p style="color: #666; font-size: 13px; margin-block-start: 10px;">Lưu ý: Bạn có thể lưu dưới dạng PDF hoặc in trực tiếp ra máy in tem.</p>
            </div>
            <div class="print-area">
                <h2>TEM VỆ SINH TRỌN ĐỜI</h2>
                <p style="font-size: 16px;"><b>${product}</b></p>
                <p>Khách hàng: <b>${name}</b><br>${phone}</p>
                <img src="${qrUrl}" alt="QR Code" onload="window.setTimeout(function(){window.print();}, 500)">
                <p style="margin-block-start:10px; font-size:12px; color:#555; font-style: italic;">(Thợ quét mã bằng camera để xem thông tin dịch vụ)</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function generateWarrantyQR() {
    const name = document.getElementById('qr_bh_name').value.trim();
    const phone = document.getElementById('qr_bh_phone').value.trim();
    const product = document.getElementById('qr_bh_product').value.trim();
    const serial = document.getElementById('qr_bh_serial').value.trim();
    const dateStrInput = document.getElementById('qr_bh_date').value;
    const years = parseInt(document.getElementById('qr_bh_years').value, 10);

    if (!name || !phone || !product || !dateStrInput || isNaN(years)) {
        msg('Vui lòng nhập đầy đủ thông tin Tên KH, SĐT, Sản phẩm, Ngày mua và Số năm BH', 'error');
        return;
    }

    const purchaseDate = new Date(dateStrInput);
    const expDate = new Date(purchaseDate);
    expDate.setFullYear(expDate.getFullYear() + years);

    const formatD = (d) => d.getDate().toString().padStart(2, '0') + '/' + (d.getMonth() + 1).toString().padStart(2, '0') + '/' + d.getFullYear();

    const pDateFormatted = formatD(purchaseDate);
    const eDateFormatted = formatD(expDate);

    const textPayload = `Cty phát hành: CTY TNHH MTV Điện Tử Hiếu
MST: 1402228630
ĐC: 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp
Sđt: 0979.553.289
Thông tin khách hàng: ${name}, ${phone}
Sản phẩm: ${product} ${serial ? '(S/N: '+serial+')' : ''}
Ngày mua hàng: ${pDateFormatted}
Thời hạn bảo hành: ${years} năm (Tính từ ngày mua)
Ngày hết hạn bảo hành: ${eDateFormatted}`.trim();

    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=5&data=' + encodeURIComponent(textPayload);

    const printWindow = window.open('', '_blank', 'width=600,height=800');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>In QR Tem Bao Hanh</title>
            <style>
                body { font-family: sans-serif; text-align: center; padding: 20px; background: #f0f0f0; margin: 0; }
                .print-area { background: #fff; border: 2px dashed #333; padding: 20px; display: inline-block; margin-top: 20px; width: 90%; max-width: 350px; box-sizing: border-box; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                h2 { margin: 0 0 10px 0; font-size: 18px; text-transform: uppercase; color: #0066cc; }
                p { margin: 5px 0; font-size: 14px; color: #333; line-height: 1.4; }
                img { width: 80%; max-width: 200px; height: auto; margin: 15px auto; display: block; border: none; box-sizing: border-box; }
                .btn-print { padding: 12px 24px; font-size: 16px; cursor: pointer; background: #0066cc; color: #fff; border: none; border-radius: 4px; font-weight: bold; }
                .btn-print:hover { background: #005bb5; }
                @media print {
                    @page { margin: 10mm; }
                    body { background: #fff; padding: 0; text-align: center; }
                    .no-print { display: none; }
                    .print-area { border: 1px solid #000; padding: 20px; margin: 0 auto; width: 95%; max-width: 320px; box-shadow: none; border-radius: 0; page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-block-end: 20px;">
                <button class="btn-print" onclick="window.print()">🖨️ IN TEM QR NÀY</button>
                <p style="color: #666; font-size: 13px; margin-block-start: 10px;">Lưu ý: Bạn có thể lưu dưới dạng PDF hoặc in trực tiếp ra máy in tem.</p>
            </div>
            <div class="print-area">
                <h2>TEM BẢO HÀNH CHÍNH HÃNG</h2>
                <p style="font-size: 16px;"><b>${product}</b></p>
                <p>Khách hàng: <b>${name}</b><br>${phone}</p>
                <p>Mua ngày: <b>${pDateFormatted}</b><br>Hạn bảo hành: <b style="color:red;">${eDateFormatted}</b></p>
                <img src="${qrUrl}" alt="QR Code" onload="window.setTimeout(function(){window.print();}, 500)">
                <p style="margin-block-start:10px; font-size:12px; color:#555; font-style: italic;">(Quét mã bằng camera để kiểm tra thời hạn)</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function loadWorkers(){ api('admin_workers').then(d=>{ workersBody.innerHTML=(d.data||[]).map(w=>`<tr><td>${esc(w.worker_id)}</td><td><span class="worker-name">${esc(w.telegram_name)}</span><span class="worker-meta">${w.telegram_username?'@'+esc(w.telegram_username):''}</span></td><td>${esc(w.phone||'-')}</td><td>${esc(w.worker_type||w.role||'-')}</td><td>${esc(w.jobs_completed||w.job_count||0)}</td><td>${fmt(w.total_earned)}</td><td class="money-paid">${fmt(w.confirmed_paid_fee||w.total_paid_fee)}</td><td class="money-due">${fmt(w.unpaid_fee)}</td><td>${workerStatus(w)}</td><td><div class="row-actions">${workerActionButtons(w)}</div></td></tr>`).join('') || '<tr><td colspan="10" class="muted">Chua co du lieu tho.</td></tr>'; }); }
function loadBans(){ api('admin_banned_devices').then(d=>{ bansBody.innerHTML=(d.data||[]).map(b=>`<tr><td>${b.id}</td><td><code>${esc(b.identifier)}</code></td><td>${esc(b.ban_type)}</td><td>${esc(b.reason)}</td><td>${esc(b.spam_count)}</td><td>${esc(b.created_at)}</td><td><button class="btn success" onclick='unbanDeviceValue(${JSON.stringify(b.identifier)})'>Unban</button></td></tr>`).join(''); }); }
function unbanWorker(){ unbanWorkerId(worker_unban_id.value); }
function unbanWorkerId(id){ if(!id) return msg('Nhap Telegram user ID'); api('admin_unban_worker',{worker_id:id},'POST').then(d=>{ msg(d.message||'Da mo khoa'); loadWorkers(); }); }
function unbanDevice(){ unbanDeviceValue(device_unban_id.value); }
function unbanDeviceValue(id){ if(!id) return msg('Nhap identifier'); api('admin_unban_device',{identifier:id},'POST').then(d=>{ msg(d.message||'Da mo khoa'); loadBans(); }); }
function registerWorker(){ api('admin_register_worker',{worker_id:worker_register_id.value,phone:worker_register_phone.value,name:worker_register_name.value,role:worker_register_role.value},'POST').then(d=>{ msg(d.message||'Da luu'); loadWorkers(); loadDashboard(); }); }
function markPaid(id){ if(!confirm('Xac nhan da thu toan bo phi nen tang hien tai cua tho nay?')) return; api('admin_mark_worker_paid',{worker_id:id},'POST').then(d=>{ msg(d.message||'Da ghi nhan'); loadWorkers(); loadDashboard(); }); }

async function exportViettelBatch() {
    if(!confirm('Xác nhận xuất hóa đơn tổng hợp cuối ngày cho các đơn chưa xuất?')) return;
    doViettelExport(0);
}
async function exportViettelSingle(orderId) {
    if(!confirm('Xác nhận xuất hóa đơn điện tử cho đơn hàng này?')) return;
    doViettelExport(orderId);
}
async function doViettelExport(orderId) {
    msg('Đang lấy dữ liệu hóa đơn...', 'ok');
    try {
        const res = await api('viettel_invoice_get_hash', {order_id: orderId}, 'POST');
        if (res.status === 'error') { msg(res.message || 'Lỗi lấy hash', 'error'); return; }
        msg('Đang gọi LocalSignService để ký...', 'warn');
        const signRes = await fetch('http://localhost:8080/sign', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({hashString: res.hashString})
        });
        const signData = await signRes.json();
        if (!signData.signature) { msg('Lỗi ký số: ' + (signData.error || 'Unknown'), 'error'); return; }
        msg('Ký thành công, đang phát hành lên cơ quan thuế...', 'warn');
        const submitRes = await api('viettel_invoice_submit_sign', {
            hashString: res.hashString,
            signature: signData.signature,
            invoiceData: res.invoiceData,
            order_ids: res.order_ids
        }, 'POST');
        if (submitRes.status === 'error') { msg(submitRes.message || 'Lỗi phát hành', 'error'); return; }
        msg(submitRes.message || 'Phát hành thành công!', 'ok');
        loadOrders();
    } catch (e) {
        msg('Lỗi kết nối. Hãy chắc chắn phần mềm LocalSignService đang bật trên máy bạn.', 'error');
        console.error(e);
    }
}

if (<?= json_encode($promoMessage !== '') ?>) {
    document.querySelector('nav button[data-page="codes"]').click();
} else {
    loadDashboard();
}
</script>
</body>
</html>
