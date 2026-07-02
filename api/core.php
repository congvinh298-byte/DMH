<?php
declare(strict_types=1);

/*
 * Dien Tu Hieu - THE BRAIN.
 * Pure PHP + PDO + Telegram webhook router.
 *
 * Public actions:
 *   get_products, create_order, create_job, check_voucher, save_wheel_prize,
 *   gemini_chat, anh_thien_chat, telegram_webhook, sepay_webhook, momo_worker_payment, momo_ipn, cron_*
 *
 * Admin actions:
 *   admin_*, generate_qr, generate_voucher
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

function app_security_headers()
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(self)');
}

function app_load_env($path = null)
{
    if ($path === null) {
        // Try api/ first, then project root (parent)
        $candidate1 = __DIR__ . '/.env';
        $candidate2 = dirname(__DIR__) . '/.env';
        if (is_file($candidate1) && is_readable($candidate1)) {
            $path = $candidate1;
        } elseif (is_file($candidate2) && is_readable($candidate2)) {
            $path = $candidate2;
        } else {
            return;
        }
    }
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

function app_env(string $key, $default = ''): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return (string)$default;
    }
    return (string)$value;
}

function app_bool_env(string $key, bool $default = false): bool
{
    $value = strtolower(app_env($key, $default ? 'true' : 'false'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
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

function app_admin_is_authenticated(): bool
{
    app_ensure_session();
    return !empty($_SESSION['admin_logged_in']);
}

function app_require_admin_json()
{
    if (app_admin_is_authenticated()) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Admin login required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function app_request_data(): array
{
    $data = $_POST;
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
    }
    return $data;
}

app_load_env();
app_security_headers();

function dth_starts_with(string $haystack, string $needle): bool
{
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

function api_exception_out(Throwable $e)
{
    error_log('[api_master] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $message = app_bool_env('APP_DEBUG', false) ? $e->getMessage() : 'Server error. Check PHP error_log.';
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler('api_exception_out');

function json_out(array $data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    return app_request_data();
}

function clean_string($value, int $max = 500): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function app_check_content_keywords(PDO $pdo, string $text): ?string
{
    if (trim($text) === '') {
        return null;
    }
    try {
        $stmt = $pdo->query("SELECT word FROM prohibited_keywords WHERE is_active = 1");
        $keywords = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($keywords as $kw) {
            if ($kw !== '' && mb_stripos($text, $kw, 0, 'UTF-8') !== false) {
                return $kw;
            }
        }
    } catch (Throwable $e) {
        error_log('[keywords] check failed: ' . $e->getMessage());
    }
    return null;
}

function app_check_site_password()
{
    $protect = app_bool_env('SITE_PASSWORD_PROTECT', false);
    if (!$protect) {
        return;
    }
    
    app_ensure_session();
    
    if (isset($_POST['dth_site_pwd'])) {
        $sitePwd = (string)$_POST['dth_site_pwd'];
        $expectedPwd = app_env('SITE_PASSWORD', 'dth123');
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
    if ($isUnlocked) {
        return;
    }
    
    http_response_code(403);
    $errorHtml = isset($GLOBALS['dth_site_pwd_error']) ? '<div style="color:#b91c1c;background:#fef2f2;border:1px solid #fee2e2;padding:12px;border-radius:8px;margin-bottom:15px;font-size:14px;">' . htmlspecialchars($GLOBALS['dth_site_pwd_error']) . '</div>' : '';
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

function digits_only($value): string
{
    return preg_replace('/\D+/', '', (string)$value) ?? '';
}

function money_int($value): int
{
    if (is_numeric($value)) {
        return max(0, (int)round((float)$value));
    }
    return max(0, (int)(preg_replace('/[^\d]/', '', (string)$value) ?: 0));
}

function signed_money_int($value): int
{
    if (is_numeric($value)) {
        return (int)round((float)$value);
    }
    $raw = trim((string)$value);
    $negative = strpos($raw, '-') !== false;
    $amount = (int)(preg_replace('/[^\d]/', '', $raw) ?: 0);
    return $negative ? -$amount : $amount;
}

function fmt_money($amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' VND';
}

function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = (string)($_SERVER[$key] ?? '');
        if ($value === '') {
            continue;
        }
        $first = trim(explode(',', $value)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return '0.0.0.0';
}

function esc_html($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mask_phone(string $phone): string
{
    $digits = digits_only($phone);
    $len = strlen($digits);
    if ($len <= 6) {
        return str_repeat('*', max(0, $len));
    }
    return substr($digits, 0, 3) . str_repeat('*', max(3, $len - 6)) . substr($digits, -3);
}

function mask_phone_like_text(string $text): string
{
    return preg_replace_callback('/\b(?:\+?84|0)?\d{8,11}\b/u', static function (array $m): string {
        return mask_phone($m[0]);
    }, $text) ?? $text;
}

function generate_code(string $prefix, int $bytes = 4): string
{
    return strtoupper($prefix . '-' . bin2hex(random_bytes($bytes)));
}

final class DB
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $name = app_env('DB_NAME', '');
        $user = app_env('DB_USER', '');
        $pass = app_env('DB_PASS', '');

        if ($name === '' || $user === '') {
            throw new RuntimeException('Missing database credentials. Set DB_NAME and DB_USER in .env.');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            app_env('DB_HOST', 'localhost'),
            $name,
            app_env('DB_CHARSET', 'utf8mb4')
        );

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$pdo->exec("SET time_zone = '+07:00'");
        return self::$pdo;
    }
}

function pdo(): PDO
{
    $pdo = DB::conn();
    ensure_core_schema($pdo);
    return $pdo;
}

function db_ident(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Unsafe database identifier.');
    }
    return "`{$identifier}`";
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function column_type(PDO $pdo, string $table, string $column): string
{
    $stmt = $pdo->prepare('SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (string)($stmt->fetchColumn() ?: '');
}

function column_allows_value(PDO $pdo, string $table, string $column, string $value): bool
{
    if (!column_exists($pdo, $table, $column)) {
        return false;
    }
    $type = strtolower(column_type($pdo, $table, $column));
    if (!dth_starts_with($type, 'enum(')) {
        return true;
    }
    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);
    $values = array_map('stripcslashes', $matches[1] ?? []);
    return in_array($value, $values, true);
}

function first_existing_column(PDO $pdo, string $table, array $columns)
{
    foreach ($columns as $column) {
        if (column_exists($pdo, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition)
{
    if (!table_exists($pdo, $table) || column_exists($pdo, $table, $column)) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE ' . db_ident($table) . ' ADD COLUMN ' . db_ident($column) . ' ' . $definition);
    } catch (Throwable $e) {
        error_log("[schema] add column skipped {$table}.{$column}: " . $e->getMessage());
    }
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function add_index_if_missing(PDO $pdo, string $table, string $index, string $definition)
{
    if (!table_exists($pdo, $table) || index_exists($pdo, $table, $index)) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE ' . db_ident($table) . ' ADD INDEX ' . db_ident($index) . ' ' . $definition);
    } catch (Throwable $e) {
        error_log("[schema] add index skipped {$table}.{$index}: " . $e->getMessage());
    }
}

function ensure_core_schema(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        role VARCHAR(30) NOT NULL DEFAULT 'buyer',
        fullname VARCHAR(150) NOT NULL,
        phone VARCHAR(30) NOT NULL,
        password_hash VARCHAR(255) NULL,
        telegram_chat_id VARCHAR(60) NULL,
        telegram_username VARCHAR(150) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        member_rank VARCHAR(50) NOT NULL DEFAULT 'Thanh vien',
        total_spent BIGINT NOT NULL DEFAULT 0,
        loyalty_points INT NOT NULL DEFAULT 0,
        lucky_spins INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Do not create a fake products table. The real project uses marketplace_products
    // and may also have a legacy products table with Vietnamese column names.

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(50) NOT NULL UNIQUE,
        customer_name VARCHAR(150) NULL,
        customer_phone VARCHAR(30) NULL,
        customer_address TEXT NULL,
        shipping_address TEXT NULL,
        buyer_note TEXT NULL,
        product_id INT NOT NULL DEFAULT 0,
        product_name VARCHAR(255) NULL,
        quantity INT NOT NULL DEFAULT 1,
        subtotal INT NOT NULL DEFAULT 0,
        discount INT NOT NULL DEFAULT 0,
        total_price INT NOT NULL DEFAULT 0,
        total INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        payment_method VARCHAR(40) NULL DEFAULT 'cod',
        payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
        coupon_code VARCHAR(80) NULL,
        voucher_code VARCHAR(80) NULL,
        note TEXT NULL,
        confirmed_by VARCHAR(150) NULL,
        confirmed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        product_type VARCHAR(50) NOT NULL DEFAULT 'product',
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price INT NOT NULL DEFAULT 0,
        subtotal INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_items_order (order_id),
        INDEX idx_order_items_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(150) NULL,
        customer_phone VARCHAR(30) NULL,
        service_type VARCHAR(150) NULL,
        address TEXT NULL,
        map_lat DECIMAL(10,7) NULL,
        map_lng DECIMAL(10,7) NULL,
        description TEXT NULL,
        quantity INT NOT NULL DEFAULT 1,
        customer_total INT NOT NULL DEFAULT 0,
        discount INT NOT NULL DEFAULT 0,
        final_total INT NOT NULL DEFAULT 0,
        worker_id BIGINT NULL,
        telegram_worker_id BIGINT NULL,
        bot_role VARCHAR(30) NULL,
        target_group_id VARCHAR(50) NULL,
        tg_message_id BIGINT NULL,
        title VARCHAR(255) NULL,
        location TEXT NULL,
        salary_min INT NOT NULL DEFAULT 0,
        salary_max INT NOT NULL DEFAULT 0,
        worker_count INT NOT NULL DEFAULT 1,
        employer_id INT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'new',
        spam_count INT NOT NULL DEFAULT 0,
        cancel_reason TEXT NULL,
        assigned_at DATETIME NULL,
        completed_at DATETIME NULL,
        cancelled_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL,
        review_score INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_pricing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        tech_target_base INT NOT NULL DEFAULT 0,
        vat_amount INT NOT NULL DEFAULT 0,
        profit_amount INT NOT NULL DEFAULT 0,
        gross_customer_price INT NOT NULL DEFAULT 0,
        discount_amount INT NOT NULL DEFAULT 0,
        final_customer_price INT NOT NULL DEFAULT 0,
        platform_fee INT NOT NULL DEFAULT 0,
        paid_amount INT NOT NULL DEFAULT 0,
        tech_net_income INT NOT NULL DEFAULT 0,
        payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
        payment_method VARCHAR(40) NULL,
        payment_reference VARCHAR(150) NULL,
        paid_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_job_pricing_job (job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS worker_profiles (
        telegram_user_id BIGINT PRIMARY KEY,
        telegram_name VARCHAR(150) NULL,
        telegram_username VARCHAR(150) NULL,
        phone VARCHAR(30) NULL,
        identity_code VARCHAR(100) NULL,
        worker_type VARCHAR(80) NULL DEFAULT 'ho_kinh_doanh',
        role VARCHAR(30) NOT NULL DEFAULT 'worker',
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        registered_by BIGINT NULL,
        last_seen_bot VARCHAR(30) NULL,
        last_seen_at DATETIME NULL,
        cancel_count INT NOT NULL DEFAULT 0,
        abuse_count INT NOT NULL DEFAULT 0,
        jobs_claimed INT NOT NULL DEFAULT 0,
        jobs_completed INT NOT NULL DEFAULT 0,
        is_receive_blocked TINYINT(1) NOT NULL DEFAULT 0,
        payment_blocked TINYINT(1) NOT NULL DEFAULT 0,
        blocked_until DATETIME NULL,
        block_reason VARCHAR(255) NULL,
        last_fee_notice_at DATETIME NULL,
        total_paid_fee INT NOT NULL DEFAULT 0,
        last_payment_amount INT NOT NULL DEFAULT 0,
        last_payment_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS worker_payments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        worker_id BIGINT NOT NULL,
        amount INT NOT NULL DEFAULT 0,
        applied_amount INT NOT NULL DEFAULT 0,
        method VARCHAR(40) NOT NULL DEFAULT 'manual',
        reference_code VARCHAR(150) NULL,
        external_transaction_id VARCHAR(150) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        note TEXT NULL,
        confirmed_by VARCHAR(150) NULL,
        confirmed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_worker_payment_external (external_transaction_id),
        INDEX idx_worker_payments_worker (worker_id),
        INDEX idx_worker_payments_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_claims (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        telegram_user_id BIGINT NOT NULL,
        telegram_name VARCHAR(150) NULL,
        outcome VARCHAR(40) NOT NULL DEFAULT 'attempt',
        note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_job_claims_job (job_id),
        INDEX idx_job_claims_worker (telegram_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_message_map (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        bot_role VARCHAR(30) NOT NULL,
        chat_id VARCHAR(80) NOT NULL,
        message_id BIGINT NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_tg_msg (bot_role, chat_id, message_id),
        INDEX idx_tg_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_client_identifiers (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        identifier VARCHAR(255) NOT NULL,
        identifier_type VARCHAR(30) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_job_identifier (job_id, identifier, identifier_type),
        INDEX idx_client_identifier (identifier, identifier_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS spam_reports (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        telegram_user_id BIGINT NOT NULL,
        telegram_name VARCHAR(150) NULL,
        note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_spam_report (job_id, telegram_user_id),
        INDEX idx_spam_job (job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS client_abuse (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(255) NOT NULL,
        identifier_type VARCHAR(30) NOT NULL,
        request_count INT NOT NULL DEFAULT 0,
        fake_count INT NOT NULL DEFAULT 0,
        last_job_id INT NULL,
        banned_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_client_abuse (identifier, identifier_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS banned_devices (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(255) NOT NULL,
        ban_type VARCHAR(30) NOT NULL DEFAULT 'device',
        reason TEXT NULL,
        spam_job_id INT NULL,
        spam_count INT NOT NULL DEFAULT 0,
        created_by VARCHAR(100) NULL DEFAULT 'system',
        expires_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ban (identifier, ban_type),
        INDEX idx_ban_identifier (identifier),
        INDEX idx_ban_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(80) NOT NULL UNIQUE,
        type VARCHAR(30) NOT NULL DEFAULT 'discount',
        value INT NOT NULL DEFAULT 0,
        description TEXT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        used_by VARCHAR(80) NULL,
        order_ref VARCHAR(80) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vouchers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(80) NOT NULL UNIQUE,
        discount_percent INT NOT NULL DEFAULT 0,
        discount_amount INT NOT NULL DEFAULT 0,
        max_uses INT NOT NULL DEFAULT 100,
        used_count INT NOT NULL DEFAULT 0,
        expires_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_notifications (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        telegram_message_id BIGINT NULL,
        boss_chat_id VARCHAR(80) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        confirmed_by VARCHAR(150) NULL,
        confirmed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_notifications_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS finances (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(40) NOT NULL,
        amount INT NOT NULL DEFAULT 0,
        source_type VARCHAR(40) NULL,
        source_id INT NULL,
        note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_finances_source (source_type, source_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        invoice_code VARCHAR(80) NOT NULL UNIQUE,
        order_id INT NULL,
        customer_id INT NULL,
        customer_name VARCHAR(150) NULL,
        customer_phone VARCHAR(30) NULL,
        customer_tax_code VARCHAR(50) NULL,
        customer_address TEXT NULL,
        product_name VARCHAR(255) NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_gross_amount BIGINT NOT NULL DEFAULT 0,
        gross_before_discount BIGINT NOT NULL DEFAULT 0,
        discount_amount BIGINT NOT NULL DEFAULT 0,
        promo_code VARCHAR(80) NULL,
        gift_name VARCHAR(500) NULL,
        warranty_years INT NOT NULL DEFAULT 0,
        warranty_expires_at DATE NULL,
        invoice_date DATE NULL,
        subtotal_amount BIGINT NOT NULL DEFAULT 0,
        vat_amount BIGINT NOT NULL DEFAULT 0,
        vat_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
        adjustment_amount BIGINT NOT NULL DEFAULT 0,
        total_amount BIGINT NOT NULL DEFAULT 0,
        total_price INT NOT NULL DEFAULT 0,
        company_name VARCHAR(255) NULL,
        company_tax_code VARCHAR(50) NULL,
        company_address TEXT NULL,
        company_phone VARCHAR(50) NULL,
        company_email VARCHAR(190) NULL,
        company_website VARCHAR(255) NULL,
        loyalty_points_earned INT NOT NULL DEFAULT 0,
        payment_method VARCHAR(40) NULL,
        note TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoices_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS input_invoices (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(120) NOT NULL,
        invoice_series VARCHAR(80) NOT NULL DEFAULT '',
        invoice_date DATE NOT NULL,
        seller_name VARCHAR(255) NOT NULL,
        seller_tax_code VARCHAR(50) NOT NULL,
        subtotal_amount BIGINT NOT NULL DEFAULT 0,
        vat_amount BIGINT NOT NULL DEFAULT 0,
        adjustment_amount BIGINT NOT NULL DEFAULT 0,
        total_amount BIGINT NOT NULL DEFAULT 0,
        currency VARCHAR(10) NOT NULL DEFAULT 'VND',
        pdf_path VARCHAR(500) NOT NULL,
        pdf_original_name VARCHAR(255) NOT NULL,
        pdf_sha256 CHAR(64) NOT NULL,
        pdf_size BIGINT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        note TEXT NULL,
        uploaded_by VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uniq_input_invoice_document (seller_tax_code, invoice_series, invoice_number),
        UNIQUE KEY uniq_input_invoice_pdf (pdf_sha256),
        INDEX idx_input_invoice_date (invoice_date),
        INDEX idx_input_invoice_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bct_report_access_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(190) NULL,
        auth_mode VARCHAR(30) NULL,
        period_from DATE NULL,
        period_to DATE NULL,
        response_sha256 CHAR(64) NULL,
        client_ip VARCHAR(64) NULL,
        user_agent VARCHAR(500) NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_bct_access_created (created_at),
        INDEX idx_bct_access_user (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_stores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(30) NOT NULL,
        tax_code VARCHAR(30) NOT NULL,
        tax_code_date VARCHAR(50) NULL,
        tax_code_place VARCHAR(150) NULL,
        owner_name VARCHAR(150) NULL,
        email VARCHAR(190) NULL,
        store_name VARCHAR(150) NOT NULL,
        address TEXT NULL,
        lat DECIMAL(10,7) NULL,
        lng DECIMAL(10,7) NULL,
        store_type VARCHAR(50) NULL,
        note TEXT NULL,
        login_key VARCHAR(128) NULL,
        approved_at DATETIME NULL,
        approved_by VARCHAR(150) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        rating_score DECIMAL(3,1) NOT NULL DEFAULT 5.0,
        rating_count INT NOT NULL DEFAULT 0,
        report_token VARCHAR(64) NULL,
        vendor_telegram_chat_id VARCHAR(50) NULL,
        trust_badge VARCHAR(100) NULL,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL,
        INDEX idx_store_phone (phone),
        INDEX idx_store_tax (tax_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seamless update for existing tables
    try {
        $pdo->exec("ALTER TABLE marketplace_stores ADD COLUMN vendor_telegram_chat_id VARCHAR(50) NULL");
    } catch (PDOException $e) { /* ignore if exists */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_stores ADD COLUMN tax_code_date VARCHAR(50) NULL");
    } catch (PDOException $e) { /* ignore if exists */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_stores ADD COLUMN tax_code_place VARCHAR(150) NULL");
    } catch (PDOException $e) { /* ignore if exists */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_stores ADD COLUMN trust_badge VARCHAR(100) NULL");
    } catch (PDOException $e) { /* ignore if exists */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS store_daily_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        report_date DATE NOT NULL,
        total_orders INT NOT NULL DEFAULT 0,
        total_revenue BIGINT NOT NULL DEFAULT 0,
        is_closed TINYINT(1) NOT NULL DEFAULT 0,
        closed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_store_date (store_id, report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        price INT NOT NULL DEFAULT 0,
        stock INT NOT NULL DEFAULT 0,
        type VARCHAR(120) NULL,
        description TEXT NULL,
        image_url TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_store (store_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Thêm các cột thực sự cần thiết cho Chợ Xã Lập Vỏ nếu bảng cũ bị thiếu
    try {
        $pdo->exec("ALTER TABLE marketplace_products ADD COLUMN store_id INT NOT NULL DEFAULT 0 AFTER id");
    } catch (PDOException $e) { /* ignore */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_products ADD INDEX idx_product_store (store_id)");
    } catch (PDOException $e) { /* ignore */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_products ADD COLUMN stock INT NOT NULL DEFAULT 0 AFTER price");
    } catch (PDOException $e) { /* ignore */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_products ADD COLUMN type VARCHAR(120) NULL AFTER stock");
    } catch (PDOException $e) { /* ignore */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_products ADD COLUMN description TEXT NULL AFTER type");
    } catch (PDOException $e) { /* ignore */ }
    try {
        $pdo->exec("ALTER TABLE marketplace_products ADD COLUMN image_url TEXT NULL AFTER description");
    } catch (PDOException $e) { /* ignore */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_sims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(30) NOT NULL,
        price INT NOT NULL DEFAULT 0,
        network VARCHAR(50) NULL,
        sim_type VARCHAR(50) NOT NULL DEFAULT 'SIM',
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uniq_marketplace_sims_phone (phone_number),
        INDEX idx_marketplace_sims_status (status),
        INDEX idx_marketplace_sims_price (price)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        customer_phone VARCHAR(30) NULL,
        customer_address TEXT NULL,
        total_amount INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL DEFAULT NULL,
        INDEX idx_order_store (store_id),
        INDEX idx_order_customer (customer_phone),
        INDEX idx_order_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach ([
        'users' => [
            'role' => "VARCHAR(30) NOT NULL DEFAULT 'buyer'",
            'fullname' => 'VARCHAR(150) NOT NULL',
            'phone' => 'VARCHAR(30) NOT NULL',
            'password_hash' => 'VARCHAR(255) NULL',
            'telegram_chat_id' => 'VARCHAR(60) NULL',
            'telegram_username' => 'VARCHAR(150) NULL',
            'login_key' => 'VARCHAR(128) NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'member_rank' => "VARCHAR(50) NOT NULL DEFAULT 'Thành viên'",
            'total_spent' => 'INT NOT NULL DEFAULT 0',
            'loyalty_points' => 'INT NOT NULL DEFAULT 0',
            'lucky_spins' => 'INT NOT NULL DEFAULT 0',
            'last_login_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
        'products' => [
            'price' => 'INT NOT NULL DEFAULT 0',
            'stock_quantity' => 'INT NOT NULL DEFAULT 0',
            'image_url' => 'VARCHAR(700) NULL',
            'category' => 'VARCHAR(120) NULL',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
        'orders' => [
            'order_code' => 'VARCHAR(50) NULL',
            'customer_name' => 'VARCHAR(150) NULL',
            'customer_phone' => 'VARCHAR(30) NULL',
            'customer_address' => 'TEXT NULL',
            'shipping_address' => 'TEXT NULL',
            'buyer_note' => 'TEXT NULL',
            'product_id' => 'INT NOT NULL DEFAULT 0',
            'product_name' => 'VARCHAR(255) NULL',
            'quantity' => 'INT NOT NULL DEFAULT 1',
            'subtotal' => 'INT NOT NULL DEFAULT 0',
            'discount' => 'INT NOT NULL DEFAULT 0',
            'total_price' => 'INT NOT NULL DEFAULT 0',
            'total' => 'INT NOT NULL DEFAULT 0',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
            'payment_method' => "VARCHAR(40) NULL DEFAULT 'cod'",
            'payment_status' => "VARCHAR(30) NOT NULL DEFAULT 'unpaid'",
            'coupon_code' => 'VARCHAR(80) NULL',
            'voucher_code' => 'VARCHAR(80) NULL',
            'note' => 'TEXT NULL',
            'confirmed_by' => 'VARCHAR(150) NULL',
            'confirmed_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
        'order_items' => [
            'order_id' => 'INT NOT NULL',
            'product_id' => 'INT NULL',
            'product_type' => "VARCHAR(50) NOT NULL DEFAULT 'product'",
            'product_name' => 'VARCHAR(255) NOT NULL',
            'quantity' => 'INT NOT NULL DEFAULT 1',
            'price' => 'INT NOT NULL DEFAULT 0',
            'subtotal' => 'INT NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'job_posts' => [
            'customer_name' => 'VARCHAR(150) NULL',
            'customer_phone' => 'VARCHAR(30) NULL',
            'service_type' => 'VARCHAR(150) NULL',
            'address' => 'TEXT NULL',
            'map_lat' => 'DECIMAL(10,7) NULL',
            'map_lng' => 'DECIMAL(10,7) NULL',
            'description' => 'TEXT NULL',
            'quantity' => 'INT NOT NULL DEFAULT 1',
            'customer_total' => 'INT NOT NULL DEFAULT 0',
            'discount' => 'INT NOT NULL DEFAULT 0',
            'final_total' => 'INT NOT NULL DEFAULT 0',
            'worker_id' => 'BIGINT NULL',
            'telegram_worker_id' => 'BIGINT NULL',
            'bot_role' => 'VARCHAR(30) NULL',
            'target_group_id' => 'VARCHAR(50) NULL',
            'tg_message_id' => 'BIGINT NULL',
            'status' => "VARCHAR(50) NOT NULL DEFAULT 'new'",
            'spam_count' => 'INT NOT NULL DEFAULT 0',
            'cancel_reason' => 'TEXT NULL',
            'assigned_at' => 'DATETIME NULL',
            'completed_at' => 'DATETIME NULL',
            'cancelled_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
        'vouchers' => [
            'discount_percent' => 'INT NOT NULL DEFAULT 0',
            'discount_amount' => 'INT NOT NULL DEFAULT 0',
            'type' => "VARCHAR(30) NULL DEFAULT 'percent'",
            'value' => 'INT NOT NULL DEFAULT 0',
            'max_uses' => 'INT NOT NULL DEFAULT 100',
            'usage_limit' => 'INT NOT NULL DEFAULT 100',
            'used_count' => 'INT NOT NULL DEFAULT 0',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'expires_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'qr_coupons' => [
            'discount_amount' => 'INT NOT NULL DEFAULT 0',
            'quantity_left' => 'INT NOT NULL DEFAULT 0',
            'type' => "VARCHAR(30) NOT NULL DEFAULT 'discount'",
            'value' => 'INT NOT NULL DEFAULT 0',
            'description' => 'TEXT NULL',
            'is_used' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'used_by' => 'VARCHAR(80) NULL',
            'order_ref' => 'VARCHAR(80) NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'worker_profiles' => [
            'telegram_name' => 'VARCHAR(150) NULL',
            'telegram_username' => 'VARCHAR(150) NULL',
            'phone' => 'VARCHAR(30) NULL',
            'identity_code' => 'VARCHAR(100) NULL',
            'worker_type' => "VARCHAR(80) NULL DEFAULT 'ho_kinh_doanh'",
            'role' => "VARCHAR(30) NOT NULL DEFAULT 'worker'",
            'is_admin' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'registered_by' => 'BIGINT NULL',
            'last_seen_bot' => 'VARCHAR(30) NULL',
            'last_seen_at' => 'DATETIME NULL',
            'cancel_count' => 'INT NOT NULL DEFAULT 0',
            'abuse_count' => 'INT NOT NULL DEFAULT 0',
            'jobs_claimed' => 'INT NOT NULL DEFAULT 0',
            'jobs_completed' => 'INT NOT NULL DEFAULT 0',
            'is_receive_blocked' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'payment_blocked' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'blocked_until' => 'DATETIME NULL',
            'block_reason' => 'VARCHAR(255) NULL',
            'last_fee_notice_at' => 'DATETIME NULL',
            'total_paid_fee' => 'INT NOT NULL DEFAULT 0',
            'last_payment_amount' => 'INT NOT NULL DEFAULT 0',
            'last_payment_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
        'job_pricing' => [
            'tech_target_base' => 'INT NOT NULL DEFAULT 0',
            'vat_amount' => 'INT NOT NULL DEFAULT 0',
            'profit_amount' => 'INT NOT NULL DEFAULT 0',
            'gross_customer_price' => 'INT NOT NULL DEFAULT 0',
            'discount_amount' => 'INT NOT NULL DEFAULT 0',
            'final_customer_price' => 'INT NOT NULL DEFAULT 0',
            'platform_fee' => 'INT NOT NULL DEFAULT 0',
            'tech_net_income' => 'INT NOT NULL DEFAULT 0',
            'payment_status' => "VARCHAR(30) NOT NULL DEFAULT 'unpaid'",
            'paid_amount' => 'INT NOT NULL DEFAULT 0',
            'payment_method' => 'VARCHAR(40) NULL',
            'payment_reference' => 'VARCHAR(150) NULL',
            'paid_at' => 'DATETIME NULL',
        ],
        'worker_payments' => [
            'worker_id' => 'BIGINT NOT NULL',
            'amount' => 'INT NOT NULL DEFAULT 0',
            'applied_amount' => 'INT NOT NULL DEFAULT 0',
            'method' => "VARCHAR(40) NOT NULL DEFAULT 'manual'",
            'reference_code' => 'VARCHAR(150) NULL',
            'external_transaction_id' => 'VARCHAR(150) NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
            'note' => 'TEXT NULL',
            'confirmed_by' => 'VARCHAR(150) NULL',
            'confirmed_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'client_abuse' => [
            'identifier' => 'VARCHAR(255) NOT NULL',
            'identifier_type' => 'VARCHAR(30) NOT NULL',
            'request_count' => 'INT NOT NULL DEFAULT 0',
            'fake_count' => 'INT NOT NULL DEFAULT 0',
            'last_job_id' => 'INT NULL',
            'banned_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'banned_devices' => [
            'ban_type' => "VARCHAR(30) NOT NULL DEFAULT 'device'",
            'reason' => 'TEXT NULL',
            'spam_job_id' => 'INT NULL',
            'spam_count' => 'INT NOT NULL DEFAULT 0',
            'created_by' => "VARCHAR(100) NULL DEFAULT 'system'",
            'expires_at' => 'DATETIME NULL',
        ],
        'order_notifications' => [
            'telegram_message_id' => 'BIGINT NULL',
            'boss_chat_id' => 'VARCHAR(80) NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
            'confirmed_by' => 'VARCHAR(150) NULL',
            'confirmed_at' => 'DATETIME NULL',
        ],
        'finances' => [
            'type' => 'VARCHAR(40) NOT NULL',
            'amount' => 'INT NOT NULL DEFAULT 0',
            'source_type' => 'VARCHAR(40) NULL',
            'source_id' => 'INT NULL',
            'note' => 'TEXT NULL',
        ],
        'invoices' => [
            'invoice_code' => 'VARCHAR(80) NULL',
            'order_id' => 'INT NULL',
            'customer_id' => 'INT NULL',
            'customer_name' => 'VARCHAR(150) NULL',
            'customer_phone' => 'VARCHAR(30) NULL',
            'customer_tax_code' => 'VARCHAR(50) NULL',
            'customer_address' => 'TEXT NULL',
            'product_name' => 'VARCHAR(255) NULL',
            'quantity' => 'INT NOT NULL DEFAULT 1',
            'unit_gross_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'gross_before_discount' => 'BIGINT NOT NULL DEFAULT 0',
            'discount_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'promo_code' => 'VARCHAR(80) NULL',
            'gift_name' => 'VARCHAR(500) NULL',
            'warranty_years' => 'INT NOT NULL DEFAULT 0',
            'warranty_expires_at' => 'DATE NULL',
            'invoice_date' => 'DATE NULL',
            'subtotal_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'vat_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'vat_rate' => 'DECIMAL(5,2) NOT NULL DEFAULT 10.00',
            'adjustment_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'total_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'total_price' => 'INT NOT NULL DEFAULT 0',
            'company_name' => 'VARCHAR(255) NULL',
            'company_tax_code' => 'VARCHAR(50) NULL',
            'company_address' => 'TEXT NULL',
            'company_phone' => 'VARCHAR(50) NULL',
            'company_email' => 'VARCHAR(190) NULL',
            'company_website' => 'VARCHAR(255) NULL',
            'loyalty_points_earned' => 'INT NOT NULL DEFAULT 0',
            'payment_method' => 'VARCHAR(40) NULL',
            'note' => 'TEXT NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
        ],
        'input_invoices' => [
            'invoice_number' => 'VARCHAR(120) NOT NULL',
            'invoice_series' => "VARCHAR(80) NOT NULL DEFAULT ''",
            'invoice_date' => 'DATE NOT NULL',
            'seller_name' => 'VARCHAR(255) NOT NULL',
            'seller_tax_code' => 'VARCHAR(50) NOT NULL',
            'subtotal_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'vat_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'adjustment_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'total_amount' => 'BIGINT NOT NULL DEFAULT 0',
            'currency' => "VARCHAR(10) NOT NULL DEFAULT 'VND'",
            'pdf_path' => 'VARCHAR(500) NOT NULL',
            'pdf_original_name' => 'VARCHAR(255) NOT NULL',
            'pdf_sha256' => 'CHAR(64) NOT NULL',
            'pdf_size' => 'BIGINT NOT NULL DEFAULT 0',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
            'note' => 'TEXT NULL',
            'uploaded_by' => 'VARCHAR(150) NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
        'bct_report_access_log' => [
            'username' => 'VARCHAR(190) NULL',
            'auth_mode' => 'VARCHAR(30) NULL',
            'period_from' => 'DATE NULL',
            'period_to' => 'DATE NULL',
            'response_sha256' => 'CHAR(64) NULL',
            'client_ip' => 'VARCHAR(64) NULL',
            'user_agent' => 'VARCHAR(500) NULL',
            'success' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'marketplace_stores' => [
            'phone' => 'VARCHAR(30) NOT NULL',
            'tax_code' => 'VARCHAR(30) NOT NULL',
            'owner_name' => 'VARCHAR(150) NULL',
            'email' => 'VARCHAR(190) NULL',
            'store_name' => 'VARCHAR(150) NOT NULL',
            'address' => 'TEXT NULL',
            'lat' => 'DECIMAL(10,7) NULL',
            'lng' => 'DECIMAL(10,7) NULL',
            'store_type' => 'VARCHAR(50) NULL',
            'note' => 'TEXT NULL',
            'login_key' => 'VARCHAR(128) NULL',
            'approved_at' => 'DATETIME NULL',
            'approved_by' => 'VARCHAR(150) NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
            'last_login_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
        'marketplace_sims' => [
            'phone_number' => 'VARCHAR(30) NULL',
            'price' => 'INT NOT NULL DEFAULT 0',
            'network' => 'VARCHAR(50) NULL',
            'sim_type' => "VARCHAR(50) NOT NULL DEFAULT 'SIM'",
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ],
    ] as $table => $columns) {
        foreach ($columns as $column => $definition) {
            add_column_if_missing($pdo, $table, $column, $definition);
        }
    }

    add_index_if_missing($pdo, 'products', 'idx_products_category', '(category)');
    add_index_if_missing($pdo, 'products', 'idx_products_price', '(price)');
    add_index_if_missing($pdo, 'users', 'idx_users_phone', '(phone)');
    add_index_if_missing($pdo, 'users', 'idx_users_login_key', '(login_key)');
    add_index_if_missing($pdo, 'orders', 'idx_orders_customer_phone', '(customer_phone)');
    add_index_if_missing($pdo, 'orders', 'idx_orders_status', '(status)');
    add_index_if_missing($pdo, 'order_items', 'idx_order_items_order', '(order_id)');
    add_index_if_missing($pdo, 'order_items', 'idx_order_items_product', '(product_id)');
    add_index_if_missing($pdo, 'marketplace_sims', 'idx_marketplace_sims_status', '(status)');
    add_index_if_missing($pdo, 'marketplace_sims', 'idx_marketplace_sims_price', '(price)');
    add_index_if_missing($pdo, 'job_posts', 'idx_job_posts_customer_phone', '(customer_phone)');
    add_index_if_missing($pdo, 'job_posts', 'idx_job_posts_status', '(status)');
    add_index_if_missing($pdo, 'job_posts', 'idx_job_posts_worker', '(worker_id)');
    add_index_if_missing($pdo, 'job_posts', 'idx_job_posts_telegram_worker', '(telegram_worker_id)');
    add_index_if_missing($pdo, 'job_posts', 'idx_job_posts_completed', '(completed_at)');
    add_index_if_missing($pdo, 'qr_coupons', 'idx_qr_coupons_code_lookup', '(code)');
    add_index_if_missing($pdo, 'vouchers', 'idx_vouchers_code_lookup', '(code)');
    add_index_if_missing($pdo, 'invoices', 'idx_invoices_customer', '(customer_id)');
    add_index_if_missing($pdo, 'worker_profiles', 'idx_worker_profiles_blocked', '(is_receive_blocked, payment_blocked)');
    add_index_if_missing($pdo, 'worker_profiles', 'idx_worker_profiles_role', '(role, is_admin)');
    add_index_if_missing($pdo, 'worker_payments', 'idx_worker_payments_worker', '(worker_id)');
    add_index_if_missing($pdo, 'worker_payments', 'idx_worker_payments_status', '(status)');
    add_index_if_missing($pdo, 'input_invoices', 'idx_input_invoice_date', '(invoice_date)');
    add_index_if_missing($pdo, 'input_invoices', 'idx_input_invoice_status', '(status)');
    add_index_if_missing($pdo, 'bct_report_access_log', 'idx_bct_access_created', '(created_at)');
    add_index_if_missing($pdo, 'bct_report_access_log', 'idx_bct_access_user', '(username)');
    add_index_if_missing($pdo, 'marketplace_stores', 'idx_store_phone', '(phone)');
    add_index_if_missing($pdo, 'marketplace_stores', 'idx_store_tax', '(tax_code)');
    add_index_if_missing($pdo, 'marketplace_stores', 'idx_store_status', '(status)');
    add_index_if_missing($pdo, 'marketplace_stores', 'idx_store_login_key', '(login_key)');

    $pdo->exec("UPDATE job_pricing SET paid_amount = platform_fee, paid_at = COALESCE(paid_at, created_at)
        WHERE payment_status = 'paid' AND paid_amount = 0");
    try {
        $pdo->exec("UPDATE job_posts j
            JOIN job_claims jc ON jc.id = (
                SELECT MAX(jc2.id) FROM job_claims jc2
                WHERE jc2.job_id = j.id AND jc2.outcome = 'claimed'
            )
            SET j.telegram_worker_id = jc.telegram_user_id
            WHERE j.telegram_worker_id IS NULL");
    } catch (Throwable $e) {
        error_log('[schema] telegram worker backfill skipped: ' . $e->getMessage());
    }

    // BCT Compliance: Prohibited Keywords
    $pdo->exec("CREATE TABLE IF NOT EXISTS prohibited_keywords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        word VARCHAR(100) NOT NULL UNIQUE,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed default prohibited keywords if empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM prohibited_keywords")->fetchColumn();
    if ($count === 0) {
        $defaultKeywords = ['súng', 'đạn', 'ma túy', 'cần sa', 'thuốc phiện', 'pháo hoa', 'vũ khí', 'cá độ', 'cờ bạc', 'heroin'];
        $stmt = $pdo->prepare("INSERT INTO prohibited_keywords (word, is_active) VALUES (?, 1)");
        foreach ($defaultKeywords as $kw) {
            try {
                $stmt->execute([$kw]);
            } catch (Throwable $e) { /* ignore duplicates */ }
        }
    }

    seed_known_telegram_profiles($pdo);
}

function insert_compat(PDO $pdo, string $table, array $values, array $expressions = []): int
{
    $columns = [];
    $placeholders = [];
    $params = [];
    foreach ($values as $column => $value) {
        if (!column_exists($pdo, $table, $column)) {
            continue;
        }
        $columns[] = db_ident((string)$column);
        $placeholders[] = '?';
        $params[] = $value;
    }
    foreach ($expressions as $column => $expression) {
        if (!column_exists($pdo, $table, $column)) {
            continue;
        }
        $columns[] = db_ident((string)$column);
        $placeholders[] = (string)$expression;
    }
    if ($columns === []) {
        throw new RuntimeException("No compatible columns for {$table} insert.");
    }
    $sql = 'INSERT INTO ' . db_ident($table) . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

function update_compat(PDO $pdo, string $table, array $values, string $where, array $whereParams = [], array $expressions = []): int
{
    $sets = [];
    $params = [];
    foreach ($values as $column => $value) {
        if (!column_exists($pdo, $table, $column)) {
            continue;
        }
        $sets[] = db_ident((string)$column) . ' = ?';
        $params[] = $value;
    }
    foreach ($expressions as $column => $expression) {
        if (!column_exists($pdo, $table, $column)) {
            continue;
        }
        $sets[] = db_ident((string)$column) . ' = ' . (string)$expression;
    }
    if ($sets === []) {
        return 0;
    }
    $stmt = $pdo->prepare('UPDATE ' . db_ident($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where);
    $stmt->execute(array_merge($params, $whereParams));
    return $stmt->rowCount();
}

function order_status(PDO $pdo, string $state): string
{
    switch ($state) {
        case 'pending':
            $candidates = ['pending', 'new', 'processing'];
            break;
        case 'confirmed':
            $candidates = ['confirmed', 'shipped', 'processing', 'completed'];
            break;
        case 'rejected':
        case 'cancelled':
            $candidates = ['cancelled', 'refunded', 'pending'];
            break;
        default:
            $candidates = [$state];
    }
    foreach ($candidates as $candidate) {
        if (column_allows_value($pdo, 'orders', 'status', $candidate)) {
            return $candidate;
        }
    }
    return $state;
}

function job_status(PDO $pdo, string $state): string
{
    switch ($state) {
        case 'pending':
            $candidates = ['pending', 'open'];
            break;
        case 'assigned':
            $candidates = ['assigned', 'open', 'processing'];
            break;
        case 'completed':
            $candidates = ['completed', 'filled', 'closed'];
            break;
        case 'cancelled':
            $candidates = ['cancelled', 'closed'];
            break;
        case 'spam':
            $candidates = ['spam', 'cancelled', 'closed'];
            break;
        default:
            $candidates = [$state];
    }
    foreach ($candidates as $candidate) {
        if (column_allows_value($pdo, 'job_posts', 'status', $candidate)) {
            return $candidate;
        }
    }
    return $state;
}

function get_system_user_id(PDO $pdo)
{
    if (!table_exists($pdo, 'users') || !column_exists($pdo, 'users', 'id')) {
        return null;
    }
    $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $id = $stmt ? (int)$stmt->fetchColumn() : 0;
    if ($id > 0) {
        return $id;
    }

    $values = [
        'role' => 'admin',
        'fullname' => 'Dien Tu Hieu',
        'phone' => '0979553289',
        'is_active' => 1,
    ];
    return insert_compat($pdo, 'users', $values, ['created_at' => 'NOW()']);
}

function app_public_url(): string
{
    return rtrim(app_env('APP_URL', 'https://dienmayhieu.com'), '/');
}

function telegram_normalize_role(string $role): string
{
    $role = strtolower(trim($role));
    switch ($role) {
        case '1':
        case 'tho':
        case 'tech':
        case 'worker':
            return 'worker';
        case '4':
        case 'boss':
        case 'bao-cao':
        case 'baocao':
        case 'sales':
        case 'ai':
        case 'report':
            return 'report';
        default:
            return 'worker';
    }
}

function telegram_role_label(string $role): string
{
    switch (telegram_normalize_role($role)) {
        case 'report':
            return 'Anh Thien - Bao cao / AI';
        case 'worker':
        default:
            return 'Anh Thien - Goi tho';
    }
}

function telegram_token(string $role): string
{
    switch (telegram_normalize_role($role)) {
        case 'report':
            return app_env('BOT_REPORT_TOKEN', '');
        case 'worker':
        default:
            return app_env('BOT_WORKER_TOKEN', '');
    }
}

function telegram_chat(string $role): string
{
    switch (telegram_normalize_role($role)) {
        case 'report':
            return app_env('BOSS_CHAT_ID', '-1003754511106');
        case 'worker':
        default:
            return app_env('WORKER_CHAT_ID', '-1004297747522');
    }
}

function tg_request(string $role, string $method, array $payload): array
{
    $role = telegram_normalize_role($role);
    $token = telegram_token($role);
    if ($token === '') {
        error_log('[telegram] Missing bot token for role=' . $role);
        return ['ok' => false, 'description' => 'Missing bot token'];
    }

    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $raw = false;
    $http = 0;
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 12,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $error = $raw === false ? 'file_get_contents failed' : '';
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$header, $matches)) {
                $http = (int)$matches[1];
                break;
            }
        }
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        $decoded = ['ok' => false, 'description' => $error !== '' ? $error : 'Invalid Telegram response'];
    }
    if (empty($decoded['ok'])) {
        error_log('[telegram] role=' . $role . ' method=' . $method . ' HTTP=' . $http . ' ' . ($decoded['description'] ?? $error));
    }
    return $decoded;
}

function tg_send(string $role, string $chatId, string $text, array $keyboard = []): array
{
    $chatId = trim($chatId);
    if ($chatId === '') {
        return ['ok' => false, 'description' => 'Missing chat id'];
    }
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($keyboard !== []) {
        $payload['reply_markup'] = $keyboard;
    }
    return tg_request($role, 'sendMessage', $payload);
}

function tg_send_photo(string $role, string $chatId, string $photoUrl, string $caption = '', array $keyboard = []): array
{
    $payload = [
        'chat_id' => trim($chatId),
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ];
    if ($keyboard !== []) {
        $payload['reply_markup'] = $keyboard;
    }
    return tg_request($role, 'sendPhoto', $payload);
}

function tg_answer_callback(string $role, string $callbackId, string $text, bool $alert = false): array
{
    if ($callbackId === '') {
        return ['ok' => false];
    }
    return tg_request($role, 'answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => clean_string($text, 180),
        'show_alert' => $alert,
    ]);
}

function save_tg_map(PDO $pdo, string $role, string $chatId, int $messageId, string $entityType, int $entityId): void
{
    if ($chatId === '' || $messageId <= 0 || $entityType === '' || $entityId <= 0) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO telegram_message_map (bot_role, chat_id, message_id, entity_type, entity_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE entity_type = VALUES(entity_type), entity_id = VALUES(entity_id)");
    $stmt->execute([telegram_normalize_role($role), $chatId, $messageId, $entityType, $entityId]);
}

function find_tg_map(PDO $pdo, string $role, string $chatId, int $messageId): array
{
    if ($chatId === '' || $messageId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM telegram_message_map WHERE bot_role = ? AND chat_id = ? AND message_id = ? LIMIT 1');
    $stmt->execute([telegram_normalize_role($role), $chatId, $messageId]);
    return $stmt->fetch() ?: [];
}

function telegram_request_role(): string
{
    return telegram_normalize_role((string)($_GET['bot'] ?? $_GET['role'] ?? 'worker'));
}

function telegram_verify_webhook_secret(): void
{
    $expected = app_env('TELEGRAM_WEBHOOK_SECRET', '');
    if ($expected === '') {
        return;
    }
    $actual = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if ($actual !== '' && !hash_equals($expected, $actual)) {
        json_out(['ok' => false, 'message' => 'Invalid Telegram webhook secret.'], 401);
    }
}

function telegram_update_payload(): array
{
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    return is_array($payload) ? $payload : [];
}

function telegram_job_role(PDO $pdo, int $jobId, string $fallback): string
{
    $job = $jobId > 0 ? get_job_row($pdo, $jobId) : null;
    return telegram_normalize_role((string)($job['bot_role'] ?? $fallback));
}

function handle_telegram_callback(PDO $pdo, string $role, array $callback): void
{
    $callbackId = (string)($callback['id'] ?? '');
    $data = (string)($callback['data'] ?? '');
    $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];
    $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
    $chatId = (string)($message['chat']['id'] ?? '');
    $workerId = (int)($from['id'] ?? 0);
    $workerName = worker_name($from);
    $username = clean_string((string)($from['username'] ?? ''), 150);

    if (preg_match('/^order_(confirm|reject)_(\d+)$/', $data, $m)) {
        if (!is_admin_telegram_id($workerId)) {
            tg_answer_callback($role, $callbackId, 'Chi admin duoc xac nhan don hang.', true);
            return;
        }
        $orderId = (int)$m[2];
        $accepted = $m[1] === 'confirm';
        $result = confirm_order($pdo, $orderId, $workerName, $accepted);
        $messageText = (string)($result['message'] ?? 'Da xu ly.');
        tg_answer_callback($role, $callbackId, $messageText, empty($result['ok']));
        if ($chatId !== '') {
            tg_send($role, $chatId, esc_html($workerName) . ': ' . esc_html($messageText));
        }
        return;
    }

    if (preg_match('/^claim_job_(\d+)$/', $data, $m)) {
        $jobId = (int)$m[1];
        $jobRole = telegram_job_role($pdo, $jobId, $role);
        $result = claim_job($pdo, $jobId, $workerId, $workerName, $username, $jobRole);
        tg_answer_callback($role, $callbackId, $result['message'] ?? 'Da xu ly.');
        if ($chatId !== '') {
            tg_send($jobRole, $chatId, esc_html($workerName) . ': ' . esc_html((string)($result['message'] ?? 'Da xu ly.')));
        }
        return;
    }

    if (preg_match('/^spam_job_(\d+)$/', $data, $m)) {
        $jobId = (int)$m[1];
        $jobRole = telegram_job_role($pdo, $jobId, $role);
        $result = record_spam_report($pdo, $jobId, $workerId, $workerName, 'callback spam', $jobRole);
        tg_answer_callback($role, $callbackId, $result['message'] ?? 'Da ghi nhan.');
        return;
    }

    if (preg_match('/^done_job_(\d+)$/', $data, $m)) {
        $jobId = (int)$m[1];
        $jobRole = telegram_job_role($pdo, $jobId, $role);
        $result = complete_worker_job($pdo, $jobId, $workerId, $workerName, $jobRole);
        tg_answer_callback($role, $callbackId, $result['message'] ?? 'Da xu ly.');
        return;
    }

    if (preg_match('/^cancel_job_(\d+)$/', $data, $m)) {
        $jobId = (int)$m[1];
        $jobRole = telegram_job_role($pdo, $jobId, $role);
        $result = cancel_worker_job($pdo, $jobId, $workerId, $workerName, 'Worker cancelled from Telegram button.', $jobRole);
        tg_answer_callback($role, $callbackId, $result['message'] ?? 'Da huy ca.');
        return;
    }

    if (preg_match('/^paid_notice_(\d+)$/', $data, $m)) {
        $targetWorkerId = (int)$m[1];
        if ($targetWorkerId !== $workerId && !is_admin_telegram_id($workerId)) {
            tg_answer_callback($role, $callbackId, 'Chi dung duoc cho tai khoan cua ban.', true);
            return;
        }
        $result = record_worker_payment_notice($pdo, $targetWorkerId);
        tg_answer_callback($role, $callbackId, $result['message'] ?? 'Da ghi nhan.');
        return;
    }

    if (preg_match('/^confirm_worker_pay_(\d+)$/', $data, $m)) {
        if (!is_admin_telegram_id($workerId)) {
            tg_answer_callback($role, $callbackId, 'Chi admin duoc xac nhan thanh toan.', true);
            return;
        }
        $targetWorkerId = (int)$m[1];
        $amount = worker_fee_debt($pdo, $targetWorkerId);
        $result = $amount > 0
            ? settle_worker_payment($pdo, $targetWorkerId, $amount, 'admin_telegram', 'TGCONFIRM-' . date('YmdHis'), 'telegram_admin_' . $workerId)
            : ['message' => 'Tho khong con no phi nen tang.'];
        tg_answer_callback($role, $callbackId, $result['message'] ?? 'Da xac nhan.');
        return;
    }

    tg_answer_callback($role, $callbackId, 'Lenh khong hop le.', true);
}

function handle_telegram_message(PDO $pdo, string $role, array $message): void
{
    $chatId = (string)($message['chat']['id'] ?? '');
    $from = is_array($message['from'] ?? null) ? $message['from'] : [];
    $senderId = (int)($from['id'] ?? 0);
    $name = worker_name($from);
    $username = clean_string((string)($from['username'] ?? ''), 150);
    $text = trim((string)($message['text'] ?? $message['caption'] ?? ''));
    $key = service_name_key($text);

    if (in_array($role, ['worker'], true)) {
        upsert_worker($pdo, $senderId, $name, $username, $role);
    }

    if ($text === '') {
        return;
    }

    if (dth_starts_with($key, 'start')) {
        tg_send($role, $chatId, '<b>' . esc_html(telegram_role_label($role)) . "</b>\nBot da san sang. Hay dung dung bot theo dung vai tro.");
        return;
    }

    if (dth_starts_with($key, 'idtelegram')) {
        $registerRole = in_array($role, ['worker'], true) ? $role : 'worker';
        $result = register_worker_from_admin_command($pdo, $senderId, $text, $registerRole);
        tg_send($role, $chatId, esc_html((string)($result['message'] ?? 'Da xu ly.')));
        return;
    }

    if ($role === 'report' && (dth_starts_with($key, 'baocao') || dth_starts_with($key, 'bao cao'))) {
        $result = send_daily_business_report($pdo);
        tg_send($role, $chatId, !empty($result['sent']) ? 'Da gui bao cao vao nhom Bao cao.' : 'Chua gui duoc bao cao. Kiem tra BOT_REPORT_TOKEN/BOSS_CHAT_ID.');
        return;
    }

    $replyMessageId = (int)($message['reply_to_message']['message_id'] ?? 0);
    if ($replyMessageId <= 0) {
        return;
    }

    $map = find_tg_map($pdo, $role, $chatId, $replyMessageId);
    if (($map['entity_type'] ?? '') !== 'job') {
        return;
    }

    $jobId = (int)($map['entity_id'] ?? 0);
    $jobRole = telegram_job_role($pdo, $jobId, $role);
    if (strpos($key, 'spam') !== false || strpos($key, 'fake') !== false || strpos($key, 'ao') !== false) {
        $result = record_spam_report($pdo, $jobId, $senderId, $name, $text, $jobRole);
        tg_send($jobRole, $chatId, esc_html((string)($result['message'] ?? 'Da ghi nhan spam.')));
        return;
    }
    if (strpos($key, 'nhan') !== false || strpos($key, 'claim') !== false) {
        $result = claim_job($pdo, $jobId, $senderId, $name, $username, $jobRole);
        tg_send($jobRole, $chatId, esc_html((string)($result['message'] ?? 'Da xu ly.')));
        return;
    }
    if (strpos($key, 'xong') !== false || strpos($key, 'done') !== false) {
        $result = complete_worker_job($pdo, $jobId, $senderId, $name, $jobRole);
        tg_send($jobRole, $chatId, esc_html((string)($result['message'] ?? 'Da xu ly.')));
        return;
    }
    if (strpos($key, 'huy') !== false || strpos($key, 'cancel') !== false) {
        $result = cancel_worker_job($pdo, $jobId, $senderId, $name, $text, $jobRole);
        tg_send($jobRole, $chatId, esc_html((string)($result['message'] ?? 'Da huy ca.')));
    }
}

function handle_telegram_webhook(): void
{
    telegram_verify_webhook_secret();
    $role = telegram_request_role();
    $payload = telegram_update_payload();
    if ($payload === []) {
        json_out(['ok' => true, 'message' => 'empty update']);
    }
    $pdo = pdo();
    if (is_array($payload['callback_query'] ?? null)) {
        handle_telegram_callback($pdo, $role, $payload['callback_query']);
    } elseif (is_array($payload['message'] ?? null)) {
        handle_telegram_message($pdo, $role, $payload['message']);
    }
    json_out(['ok' => true]);
}

function distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadiusKm = 6371;
    $latDelta = deg2rad($lat2 - $lat1);
    $lngDelta = deg2rad($lng2 - $lng1);
    $a = sin($latDelta / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
    $a = min(1, max(0, $a));
    return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function service_area_distance_km(float $lat, float $lng): float
{
    $centerLat = (float)app_env('SERVICE_CENTER_LAT', '10.357422');
    $centerLng = (float)app_env('SERVICE_CENTER_LNG', '105.522124');
    return distance_km($centerLat, $centerLng, $lat, $lng);
}

function require_admin_for_action(string $action)
{
    $adminActions = ['generate_qr', 'generate_voucher'];
    if (!dth_starts_with($action, 'admin_') && !in_array($action, $adminActions, true)) {
        return;
    }
    app_require_admin_json();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        app_ensure_session();
        $expected = (string)($_SESSION['csrf_token'] ?? '');
        $actual = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($expected !== '' && !hash_equals($expected, $actual)) {
            json_out(['status' => 'error', 'message' => 'Invalid CSRF token.'], 403);
        }
    }
}

function api_error(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_ok(array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['status' => 'ok'], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('next_order_code')) {
    function next_order_code(): string
    {
        return 'DH' . date('ymd') . strtoupper(substr(uniqid(), -6));
    }
}

if (!function_exists('seed_known_telegram_profiles')) {
    function seed_known_telegram_profiles(PDO $pdo): void
    {
        // No-op stub: ensures ensure_core_schema() can call this function.
    }
}

if (!function_exists('trigger_async_order_notification')) {
    function trigger_async_order_notification(int $orderId): void
    {
        // No-op stub: real implementation lives in api/orders.php if loaded.
        error_log('[order] async notification stub for order #' . $orderId);
    }
}

if (defined('DTH_API_LIBRARY_ONLY') && DTH_API_LIBRARY_ONLY) {
    return;
}
