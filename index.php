<?php
require_once __DIR__ . '/demo_gate.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=(self)');
date_default_timezone_set('Asia/Ho_Chi_Minh');

/*
 * DIEN MAY HIEU - Public Storefront.
 * Do not require api_master.php here. This page calls the backend only by fetch().
 * Database credentials are loaded from .env so they are not exposed in this file.
 */

$pdo = null;
$products = array();
$productError = '';
$dbOnline = false;

function dth_load_env($path)
{
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

function dth_env($key, $default = '')
{
    $value = isset($_ENV[$key]) ? $_ENV[$key] : (isset($_SERVER[$key]) ? $_SERVER[$key] : getenv($key));
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money_vnd($value)
{
    return number_format((float)$value, 0, ',', '.') . ' VND';
}

function money_int($value)
{
    if (is_numeric($value)) {
        return max(0, (int)round((float)$value));
    }
    return max(0, (int)(preg_replace('/[^\d]/', '', (string)$value) ?: 0));
}

function lower_text($value)
{
    $value = (string)$value;
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function dth_has(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute(array($table, $column));
    return (int)$stmt->fetchColumn() > 0;
}

function dth_add_column(PDO $pdo, $table, $column, $definition)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return;
    }

    try {
        if (!dth_has($pdo, $table, $column)) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        }
    } catch (Exception $e) {
        error_log('[index add column] ' . $table . '.' . $column . ': ' . $e->getMessage());
    }
}

function dth_index_exists(PDO $pdo, $table, $index)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute(array($table, $index));
    return (int)$stmt->fetchColumn() > 0;
}

function dth_add_index(PDO $pdo, $table, $index, $definition)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $index)) {
        return;
    }

    try {
        if (!dth_index_exists($pdo, $table, $index)) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` ' . $definition);
        }
    } catch (Exception $e) {
        error_log('[index add index] ' . $table . '.' . $index . ': ' . $e->getMessage());
    }
}

function dth_table_exists(PDO $pdo, $table)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute(array($table));
    return (int)$stmt->fetchColumn() > 0;
}

function dth_ident($identifier)
{
    return preg_match('/^[A-Za-z0-9_]+$/', (string)$identifier) ? '`' . $identifier . '`' : '``';
}

function dth_first_column(PDO $pdo, $table, array $columns)
{
    foreach ($columns as $column) {
        if (dth_has($pdo, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function dth_public_sim_rows(PDO $pdo, $limit = 220)
{
    $items = array();
    $seen = array();
    foreach (array('marketplace_sims', 'sims') as $table) {
        if (!dth_table_exists($pdo, $table)) {
            continue;
        }
        $phoneCol = dth_first_column($pdo, $table, array('so_sim', 'phone_number', 'name'));
        $priceCol = dth_first_column($pdo, $table, array('gia_ban', 'price', 'sale_price'));
        if ($phoneCol === null || $priceCol === null) {
            continue;
        }
        $networkCol = dth_first_column($pdo, $table, array('nha_mang', 'network'));
        $typeCol = dth_first_column($pdo, $table, array('loai_sim', 'sim_type', 'category'));
        $statusCol = dth_first_column($pdo, $table, array('status', 'trang_thai'));
        $select = array(
            'id',
            dth_ident($phoneCol) . ' AS phone_number',
            dth_ident($priceCol) . ' AS price',
            $networkCol ? dth_ident($networkCol) . ' AS network' : "'' AS network",
            $typeCol ? dth_ident($typeCol) . ' AS sim_type' : "'SIM' AS sim_type",
        );
        $where = $statusCol ? " WHERE (" . dth_ident($statusCol) . " IS NULL OR " . dth_ident($statusCol) . " NOT IN ('hidden','sold','reserved','deleted'))" : '';
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . dth_ident($table) . $where . ' ORDER BY id DESC LIMIT ' . max(1, min(500, (int)$limit));
        try {
            $stmt = $pdo->query($sql);
            foreach ($stmt ? $stmt->fetchAll() : array() as $row) {
                $digits = str_replace(array(' ', '.', '-'), '', (string)$row['phone_number']);
                if ($digits === '' || isset($seen[$digits])) {
                    continue;
                }
                $seen[$digits] = true;
                $items[] = array(
                    'id' => (int)$row['id'],
                    'name' => 'SIM ' . (string)$row['phone_number'],
                    'category' => 'Sim',
                    'price' => money_int($row['price']),
                    'image' => '',
                    'src' => 'sim',
                    'network' => (string)($row['network'] ?? ''),
                    'sim_type' => (string)($row['sim_type'] ?? 'SIM'),
                    'phone_number' => (string)$row['phone_number'],
                );
            }
        } catch (Exception $e) {
            error_log('[index sims] ' . $table . ': ' . $e->getMessage());
        }
    }
    return $items;
}

function dth_auto_schema(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NULL,
        category VARCHAR(120) NULL,
        image VARCHAR(700) NULL,
        image_url VARCHAR(700) NULL,
        price INT NOT NULL DEFAULT 0,
        stock_quantity INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dth_add_column($pdo, 'products', 'name', 'VARCHAR(255) NULL');
    dth_add_column($pdo, 'products', 'category', 'VARCHAR(120) NULL');
    dth_add_column($pdo, 'products', 'image', 'VARCHAR(700) NULL');
    dth_add_column($pdo, 'products', 'image_url', 'VARCHAR(700) NULL');
    dth_add_column($pdo, 'products', 'price', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'products', 'stock_quantity', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'products', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    dth_add_index($pdo, 'products', 'idx_products_category', '(category)');
    dth_add_index($pdo, 'products', 'idx_products_price', '(price)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_sims (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    dth_add_column($pdo, 'marketplace_sims', 'phone_number', 'VARCHAR(30) NULL');
    dth_add_column($pdo, 'marketplace_sims', 'price', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'marketplace_sims', 'network', 'VARCHAR(50) NULL');
    dth_add_column($pdo, 'marketplace_sims', 'sim_type', "VARCHAR(50) NOT NULL DEFAULT 'SIM'");
    dth_add_column($pdo, 'marketplace_sims', 'status', "VARCHAR(30) NOT NULL DEFAULT 'active'");
    dth_add_column($pdo, 'marketplace_sims', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    dth_add_column($pdo, 'marketplace_sims', 'updated_at', 'DATETIME NULL DEFAULT NULL');
    dth_add_index($pdo, 'marketplace_sims', 'idx_marketplace_sims_price', '(price)');
    dth_add_index($pdo, 'marketplace_sims', 'idx_marketplace_sims_status', '(status)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_coupons (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(80) NOT NULL UNIQUE,
        discount_amount INT NOT NULL DEFAULT 0,
        quantity_left INT NOT NULL DEFAULT 0,
        type VARCHAR(30) NOT NULL DEFAULT 'discount',
        value INT NOT NULL DEFAULT 0,
        description TEXT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dth_add_column($pdo, 'qr_coupons', 'code', 'VARCHAR(80) NULL');
    dth_add_column($pdo, 'qr_coupons', 'discount_amount', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'qr_coupons', 'quantity_left', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'qr_coupons', 'type', "VARCHAR(30) NOT NULL DEFAULT 'discount'");
    dth_add_column($pdo, 'qr_coupons', 'value', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'qr_coupons', 'description', 'TEXT NULL');
    dth_add_column($pdo, 'qr_coupons', 'is_used', 'TINYINT(1) NOT NULL DEFAULT 0');
    dth_add_index($pdo, 'qr_coupons', 'idx_qr_coupons_code', '(code)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_posts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(150) NULL,
        customer_phone VARCHAR(30) NULL,
        service_type VARCHAR(150) NULL,
        address TEXT NULL,
        issue TEXT NULL,
        description TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        tech_target_price INT NOT NULL DEFAULT 0,
        final_price INT NOT NULL DEFAULT 0,
        bot_message_id BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dth_add_column($pdo, 'job_posts', 'customer_name', 'VARCHAR(150) NULL');
    dth_add_column($pdo, 'job_posts', 'customer_phone', 'VARCHAR(30) NULL');
    dth_add_column($pdo, 'job_posts', 'service_type', 'VARCHAR(150) NULL');
    dth_add_column($pdo, 'job_posts', 'address', 'TEXT NULL');
    dth_add_column($pdo, 'job_posts', 'issue', 'TEXT NULL');
    dth_add_column($pdo, 'job_posts', 'description', 'TEXT NULL');
    dth_add_column($pdo, 'job_posts', 'status', "VARCHAR(30) NOT NULL DEFAULT 'pending'");
    dth_add_column($pdo, 'job_posts', 'tech_target_price', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'job_posts', 'final_price', 'INT NOT NULL DEFAULT 0');
    dth_add_column($pdo, 'job_posts', 'bot_message_id', 'BIGINT NULL');
    dth_add_column($pdo, 'job_posts', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    dth_add_index($pdo, 'job_posts', 'idx_job_posts_customer_phone', '(customer_phone)');
    dth_add_index($pdo, 'job_posts', 'idx_job_posts_status', '(status)');

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
}

function asset_data_uri($names)
{
    $mimes = array(
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    );

    foreach ($names as $name) {
        $file = basename((string)$name);
        $path = __DIR__ . DIRECTORY_SEPARATOR . $file;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset($mimes[$ext]) || !is_file($path) || !is_readable($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        if ($raw !== false && $raw !== '') {
            return 'data:' . $mimes[$ext] . ';base64,' . base64_encode($raw);
        }
    }
    return '';
}

function suggested_component_price($name, $category)
{
    $text = lower_text($name . ' ' . $category);
    $has = function ($keys) use ($text) {
        foreach ($keys as $key) {
            if (strpos($text, $key) !== false) {
                return true;
            }
        }
        return false;
    };

    if ($has(array('composite', 'cột', 'cot', 'khử vôi', 'khu voi'))) {
        return 450000;
    }
    if ($has(array('kệ', 'ke')) && $has(array('inox'))) {
        return 180000;
    }
    if ($has(array('kệ', 'ke')) && $has(array('nhựa', 'nhua'))) {
        return 120000;
    }
    if ($has(array('lõi lọc', 'loi loc'))) {
        return 80000;
    }
    if ($has(array('khung treo'))) {
        return 120000;
    }
    if ($has(array('ống đồng', 'ong dong'))) {
        return 150000;
    }
    if ($has(array('remote', 'điều khiển', 'dieu khien'))) {
        return 120000;
    }
    if ($has(array('lọc nước', 'loc nuoc'))) {
        return 160000;
    }
    if ($has(array('tivi', 'tv'))) {
        return 120000;
    }
    return 99000;
}

dth_load_env(__DIR__ . '/.env');

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
    <img src="/LOGO.svg" alt="Logo" class="logo" onerror="this.src=\'data:image/svg+xml;utf8,<svg xmlns=\\\'http://www.w3.org/2000/svg\\\' width=\\\'80\\\' height=\\\'80\\\' viewBox=\\\'0 0 80 80\\\'>&lt;rect width=\\\x22100%\\\x22 height=\\\x22100%\\\x22 fill=\\\x22%23ea580c\\\x22/&gt;&lt;text x=\\\x2250%\\\x22 y=\\\x2255%\\\x22 font-family=\\\x22sans-serif\\\x22 font-size=\\\x2216\\\x22 fill=\\\x22white\\\x22 font-weight=\\\x22bold\\\x22 text-anchor=\\\x22middle\\\x22&gt;DTH&lt;/text&gt;</svg>\'">
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

try {
    $dbName = dth_env('DB_NAME', '');
    $dbUser = dth_env('DB_USER', '');
    $dbPass = dth_env('DB_PASS', '');

    if ($dbName === '' || $dbUser === '') {
        throw new Exception('Missing database credentials in .env');
    }

    $pdo = new PDO(
        'mysql:host=' . dth_env('DB_HOST', 'localhost') . ';dbname=' . $dbName . ';charset=' . dth_env('DB_CHARSET', 'utf8mb4'),
        $dbUser,
        $dbPass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        )
    );
    $dbOnline = true;
    dth_auto_schema($pdo);

    // Tự động tạo bảng nếu chưa có (Auto-heal cho môi trường Production)
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_stores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(30) NOT NULL,
        tax_code VARCHAR(30) NOT NULL,
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
        working_hours VARCHAR(100) NULL DEFAULT '07:00 - 22:00',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_store_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        price INT NOT NULL DEFAULT 0,
        image_url TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_store (store_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (Exception $e) {
    error_log('[index db] ' . $e->getMessage());
    $productError = 'Kho sản phẩm đang được cập nhật.';
}

$stores = array();
$storeProducts = array();
$products = array();

$qrWeb = asset_data_uri(array('QR.png', 'QR.jpg', 'QR.jpeg', 'QR.webp'));
$qrPay = asset_data_uri(array('QR_THANH_TOAN.png', 'QR_THANH_TOAN.jpg', 'QR_THANH_TOAN.jpeg', 'QR_THANH_TOAN.webp'));
$favicon = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22%3E%3Crect width=%2264%22 height=%2264%22 rx=%2216%22 fill=%22%23062A1D%22/%3E%3Ctext x=%2232%22 y=%2241%22 text-anchor=%22middle%22 font-size=%2228%22 font-weight=%22700%22 font-family=%22Arial%22 fill=%22%23D4AF37%22%3ELV%3C/text%3E%3C/svg%3E';

$categories = array('Tất cả');
if (!empty($stores)) {
    foreach ($stores as $st) {
        $type = $st['store_type'] ?? '';
        if ($type !== '' && !in_array($type, $categories)) {
            $categories[] = $type;
        }
    }
}
$services = array(
    array('group' => 'Thợ điện lạnh', 'name' => 'Vệ sinh máy lạnh', 'base' => 150000, 'note' => 'Thu phí 15%'),
    array('group' => 'Thợ điện lạnh', 'name' => 'Lắp máy', 'base' => 400000, 'note' => 'Thu phí 15%'),
    array('group' => 'Thợ điện lạnh', 'name' => 'Máy lạnh âm trần', 'base' => 0, 'note' => 'Hỗ trợ liên hệ hãng'),
    array('group' => 'Thợ điện lạnh', 'name' => 'Sửa chữa điện lạnh', 'base' => 200000, 'note' => 'Công thợ + linh kiện đặt mua công khai'),
    array('group' => 'Thợ tivi', 'name' => 'Treo tivi', 'base' => 200000, 'note' => 'Công thợ + khung treo'),
    array('group' => 'Thợ máy lọc nước', 'name' => 'Lắp máy lọc nước', 'base' => 200000, 'note' => 'Công thợ + phụ kiện'),
    array('group' => 'Thợ gia dụng', 'name' => 'Lắp máy giặt', 'base' => 200000, 'note' => 'Công thợ + phụ kiện'),
    array('group' => 'Thợ điện thoại', 'name' => 'Kiểm tra / sửa điện thoại', 'base' => 200000, 'note' => 'Công thợ + linh kiện nếu có'),
);
?>
<!doctype html>
<html lang="vi">
<head>
<link rel="icon" href="data:,">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Điện Máy Hiếu - Dịch vụ gọi thợ \u0026 cửa hàng điện máy</title>
    <meta name="description" content="Điện Máy Hiếu - gọi thợ nhanh, điện máy chính hãng, thanh toán tiện lợi.">
    <meta property="og:title" content="Điện Máy Hiếu - Dịch vụ gọi thợ \u0026 cửa hàng điện máy">
    <meta property="og:description" content="Gọi thợ nhanh, điện máy chính hãng, giá dịch vụ công khai.">
    <meta property="og:type" content="website">
    <link rel="icon" href="<?= h($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
:root {
    --gold: #fbbf24;
    --red-dark: #b91c1c;--bg:#f6f7f9;--panel:#fff;--line:#e5e7eb;--text:#111827;--muted:#667085;--brand:#dc2626;--dark:#111827;--ok:#047857}
*{box-sizing:border-box}
body,button,input,textarea,select{font-family:Arial,Helvetica,sans-serif}
body {
    background: #fef2f2; /* Light red tint for empty spaces */
color:var(--text);line-height:1.5}
a{text-decoration:none;color:inherit}.wrap{width:min(1180px,calc(100% - 32px));margin:0 auto}
.top{background:var(--dark);color:#fff;font-size:13px}.top .wrap{min-height:38px;display:flex;justify-content:space-between;align-items:center;gap:12px}.approval-line{background:#fff7ed;color:#9a3412;border-bottom:1px solid #fed7aa;text-align:center;padding:7px 12px;font-size:13px;font-weight:800}
header{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--line)}
.head{min-height:74px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.logo{font-size:22px;font-weight:900;color:var(--brand)}.logo small{display:block;color:var(--muted);font-size:12px}
.search{display:flex;gap:8px;flex:1;max-width:520px}.search input{width:100%;border:1px solid var(--line);border-radius:8px;padding:11px 12px;font-size:15px}
.btn,button{border:0;border-radius:8px;background:var(--brand);color:#fff;font-weight:800;padding:11px 16px;cursor:pointer}.btn.dark{background:var(--dark)}
nav{border-top:1px solid var(--line)}nav .wrap{display:flex;gap:8px;overflow:auto;padding:10px 0}nav button{background:#fff;color:#344054;border:1px solid var(--line);white-space:nowrap}nav button.active{color:var(--brand);border-color:var(--brand);background:#fff5f5}
main{padding:24px 0 44px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:10px;box-shadow:0 10px 24px rgba(15,23,42,.06)}
.hero{display:grid;grid-template-columns:1.35fr .9fr;gap:18px}.hero-main{min-height:260px;padding:28px;background:linear-gradient(120deg,#111827,#b42318);color:#fff;display:flex;flex-direction:column;justify-content:center}.hero-main h1{font-size:clamp(32px,4vw,50px);line-height:1;margin:0 0 12px}.hero-main p{max-width:640px;color:#f3f4f6;margin:0 0 18px}.hero-actions{display:flex;gap:8px;flex-wrap:wrap}.hero-actions .btn{text-align:center}
.qr{padding:18px;display:grid;grid-template-columns:1fr 1fr;gap:12px}.qr-box{border:1px solid var(--line);border-radius:8px;padding:12px}.qr-box h3{margin:0 0 8px;font-size:15px}.qr-box p{margin:0 0 10px;color:var(--muted);font-size:13px}.qr-box img{width:150px;height:150px;object-fit:contain;background:#fff;border:1px solid var(--line);padding:6px}.qr-empty{height:150px;display:grid;place-items:center;background:#fff;border:1px dashed #cbd5e1;border-radius:8px;color:#98a2b3;text-align:center;font-size:13px}
.storefront{display:flex;flex-direction:column}
.section{margin-top:22px}.title{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:12px}.title h2{margin:0;font-size:23px}.muted{color:var(--muted);font-size:14px}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.product{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden;display:flex;flex-direction:column;}.img{height:160px;display:flex;justify-content:center;align-items:center;background:#fff;border-bottom:1px solid var(--line);color:#98a2b3;overflow:hidden;}.img img{max-width:100%;height:100%;object-fit:contain;padding:10px}.sim-face{font-size:24px;font-weight:900;color:var(--ok);letter-spacing:.5px}.body{padding:12px;flex:1;display:flex;flex-direction:column;}.name{font-weight:900;min-height:42px}.cat{font-size:13px;color:var(--muted)}.price{font-size:17px;color:var(--brand);font-weight:900}.buy-row{display:flex;gap:8px;align-items:center;margin-top:auto;padding-top:10px;}.buy-row .btn{width:100%;text-align:center;padding:9px 12px}.suggest{display:inline-block;color:var(--ok);font-size:12px;font-weight:800}.empty{grid-column:1/-1;background:#fff;border:1px dashed #cbd5e1;border-radius:10px;padding:24px;text-align:center;color:var(--muted)}
.booking-shell{padding:18px}.service-head{display:grid;grid-template-columns:minmax(220px,1fr) auto;gap:10px;margin-bottom:10px}.service-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:14px}.service-option{display:grid;grid-template-columns:1fr auto;gap:5px 10px;min-height:64px;padding:10px 12px;text-align:start;background:#fff;color:var(--text);border:1px solid var(--line);border-radius:8px}.service-option:hover,.service-option.selected{border-color:var(--brand);background:#fff7f7}.service-option small{color:var(--muted);font-weight:700}.service-option strong{align-self:center;color:var(--brand);white-space:nowrap}.service-option span{font-size:13px;font-weight:900}.service-option.is-contact strong{color:var(--muted)}
.gemini-panel{display:none;margin:0 0 14px;border:1px solid #c7d2fe;background:#f8fafc;border-radius:8px;padding:12px}.gemini-panel.active{display:grid;gap:10px}.gemini-actions{display:flex;gap:8px;flex-wrap:wrap}.gemini-reply{display:none;border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;white-space:pre-wrap}.gemini-reply.active{display:block}
.gemini-message p:last-child { margin-bottom: 0; }
.gemini-message pre { background: #f1f5f9; padding: 10px; border-radius: 6px; overflow-x: auto; margin: 10px 0; font-size: 13px; }
.gemini-message code { font-family: monospace; }
.typing-indicator { display: none; padding: 8px 12px; font-style: italic; color: #64748b; font-size: 13px; }
.dth-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); overflow: auto; }
.dth-modal-content { background-color: #fff; margin: 5% auto; padding: 24px; border-radius: 12px; width: 90%; max-width: 800px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative; animation: modalFadeIn 0.3s ease; }
@keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
.dth-modal-close { position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; color: #666; cursor: pointer; }
.dth-modal-close:hover { color: #dc2626; }
.dth-modal-title { font-size: 22px; font-weight: 800; margin-bottom: 20px; color: #111827; border-bottom: 2px solid #dc2626; padding-bottom: 10px; display: inline-block; }
.dth-modal-body { font-size: 15px; line-height: 1.6; color: #374151; }
.dth-modal-body h4 { margin-top: 15px; margin-bottom: 8px; color: #1f2937; font-size: 16px; }
.dth-modal-body ul { padding-left: 20px; margin-top: 5px; margin-bottom: 15px; }
.legal-doc{display:grid;gap:16px;text-align:start;color:#374151}.legal-lead{background:#f8fafc;border:1px solid #e2e8f0;border-inline-start:4px solid #dc2626;border-radius:10px;padding:14px 16px}.legal-lead p{margin:0}.legal-section{border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;background:#fff}.legal-section h4{margin:0 0 8px!important;color:#111827!important;font-size:16px!important}.legal-section p{margin:0 0 8px}.legal-section p:last-child{margin-bottom:0}.legal-section ul{margin:0!important;padding-left:20px!important}.legal-section li{margin-bottom:6px}.legal-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}.legal-meta div{background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px;font-size:13px}.legal-note{font-size:13px;color:#64748b;font-style:italic}
.form{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field{display:grid;gap:6px}.full{grid-column:1/-1}label{font-size:13px;font-weight:800}input,textarea,select{width:100%;border:1px solid var(--line);border-radius:8px;padding:11px 12px;font:inherit;background:#fff}textarea{min-height:100px;resize:vertical}.readonly-price{background:#f8fafc;color:#047857;font-weight:900}.map-actions{display:flex;gap:8px;flex-wrap:wrap}.map-actions .btn{padding:9px 12px}.map-preview{margin-top:4px;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#f8fafc}.location-map{width:100%;height:260px}.location-status{padding:8px 10px;background:#f8fafc;border-top:1px solid var(--line);font-size:12px;color:var(--muted)}.status{display:none;margin-top:12px;padding:10px 12px;border-radius:8px}.status.ok{display:block;background:#ecfdf3;color:#047857;border:1px solid #a7f3d0}.status.err{display:block;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.legal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.legal-item{background:#fff;border:1px solid var(--line);border-radius:8px;padding:0 12px}.legal-item summary{cursor:pointer;font-weight:900;padding:12px 0}.legal-content{border-top:1px solid var(--line);padding:10px 0 12px;color:#475569;font-size:13px}.legal-content p{margin:0 0 8px}.legal-content p:last-child{margin-bottom:0}
footer{background:#111827;color:#d1d5db;padding:24px 0;font-size:13px}.footer-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:18px}.footer-grid h3{margin:0 0 8px;color:#fff;font-size:15px}.footer-grid p{margin:4px 0}.footer-bottom{border-top:1px solid #374151;margin-top:18px;padding-top:12px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
@media(max-width:900px){.head,.hero{display:grid;grid-template-columns:1fr}.search{max-width:none}.grid,.qr,.service-list,.legal-grid,.footer-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){header{position:static}.top .wrap{min-height:auto;padding:6px 0;flex-wrap:wrap}.head{padding:12px 0}.title{display:grid;grid-template-columns:1fr;align-items:start;gap:2px}.grid,.qr,.form,.service-head,.service-list,.legal-grid,.footer-grid{grid-template-columns:1fr}.hero-actions .btn{flex:1 1 180px}.full{grid-column:auto}.wrap{width:min(100% - 22px,1180px)}.location-map{height:230px}}


/* Theme Đỏ Vàng Tự Hào */
.red-gold-btn {
    background: #fff;
    border: 2px solid var(--gold) !important;
    border-radius: 8px;
    color: var(--red-dark);
    transition: all 0.3s;
}
.red-gold-btn.selected {
    background: var(--gold) !important;
    color: #991b1b !important;
    border-color: #d97706 !important;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.4);
}
.booking-shell .service-head input {
    border: 2px solid var(--gold);
    border-radius: 8px;
}
.booking-shell .title h2 { color: var(--gold); }

/* Emerald Champagne Gen Z Theme */
:root {
    --emerald-950: #062A1D;
    --emerald-900: #0B3D2B;
    --emerald-800: #0F5138;
    --emerald-700: #126846;
    --champagne: #D4AF37;
    --champagne-soft: #F7D978;
    --champagne-pale: #FFF3BF;
    --glass: rgba(255, 255, 255, 0.08);
    --glass-strong: rgba(255, 255, 255, 0.13);
    --glass-dark: rgba(4, 31, 22, 0.74);
    --line-gold: rgba(212, 175, 55, 0.42);
    --white-soft: rgba(255, 255, 255, 0.86);
    --muted-light: rgba(255, 255, 255, 0.68);
    --aqua: #8DF5CE;
}

html { scroll-behavior: smooth; }

body, button, input, textarea, select {
    font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Arial, sans-serif !important;
}

body {
    min-height: 100vh;
    margin: 0;
    color: #fff !important;
    background:
        radial-gradient(circle at 14% 5%, rgba(212, 175, 55, 0.20), transparent 30%),
        radial-gradient(circle at 92% 12%, rgba(141, 245, 206, 0.15), transparent 34%),
        radial-gradient(circle at 50% 105%, rgba(212, 175, 55, 0.14), transparent 38%),
        linear-gradient(145deg, var(--emerald-950) 0%, var(--emerald-900) 48%, #03150F 100%) !important;
    overflow-x: hidden;
}

body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: radial-gradient(circle at 50% 20%, #000 0%, transparent 70%);
}

.wrap {
    width: min(1180px, calc(100% - 32px)) !important;
}

.approval-line {
    background: linear-gradient(90deg, rgba(6,42,29,.96), rgba(11,61,43,.96)) !important;
    color: var(--champagne-pale) !important;
    border-bottom: 1px solid var(--line-gold) !important;
    letter-spacing: .04em;
}

header {
    background: rgba(6, 42, 29, 0.76) !important;
    border-bottom: 1px solid var(--line-gold) !important;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(22px);
}

.head {
    min-height: 86px !important;
    align-items: center !important;
}

.logo {
    color: #fff !important;
    letter-spacing: .01em;
}

.logo small,
.muted,
.cat,
.suggest {
    color: var(--muted-light) !important;
}

.logo img {
    height: 58px !important;
    width: 58px;
    border: 1px solid rgba(212, 175, 55, .55);
    border-radius: 18px !important;
    box-shadow: 0 12px 28px rgba(0, 0, 0, .25), 0 0 0 6px rgba(212, 175, 55, .08);
}

#topBarStatus button {
    background: rgba(255, 255, 255, .08) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
    border-radius: 999px !important;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .18) !important;
}

.search input {
    min-height: 46px;
    background: rgba(255, 255, 255, .08) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
    border-radius: 999px !important;
    padding-inline: 18px !important;
    outline: none;
}

.search input::placeholder {
    color: rgba(255, 255, 255, .58);
}

.search input:focus {
    border-color: var(--champagne) !important;
    box-shadow: 0 0 0 4px rgba(212, 175, 55, .14) !important;
}

.btn,
button {
    border-radius: 14px !important;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease, background .22s ease;
}

.btn:hover,
button:hover {
    transform: translateY(-1px);
}

.search button,
.hero-actions .btn,
.buy-row .btn,
#orderSubmit,
#phoneLoginSubmitBtn,
#loginSubmitBtn,
#storeRegSubmitBtn {
    background: linear-gradient(135deg, var(--champagne-soft), var(--champagne)) !important;
    color: var(--emerald-950) !important;
    border: 1px solid rgba(255, 243, 191, .65) !important;
    box-shadow: 0 14px 32px rgba(212, 175, 55, .20) !important;
}

.btn.dark {
    background: rgba(255, 255, 255, .09) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
}

.quick-tabs .btn {
    background: rgba(255, 255, 255, .09) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
    box-shadow: 0 12px 26px rgba(0, 0, 0, .18) !important;
}

.quick-tabs .btn:hover {
    background: rgba(212, 175, 55, .18) !important;
    color: var(--champagne-pale) !important;
}

main {
    padding: clamp(18px, 4vw, 42px) 0 56px !important;
}

.storefront {
    display: flex;
    flex-direction: column;
    gap: clamp(18px, 3vw, 28px);
}

.storefront > section {
    order: initial !important;
}

.section {
    margin-top: 0 !important;
}

.glass-panel,
.panel,
.store-block,
.product,
.qr-box,
.legal-item,
.empty {
    background: var(--glass) !important;
    border: 1px solid var(--line-gold) !important;
    border-radius: 16px !important;
    box-shadow: 0 24px 70px rgba(0, 0, 0, .24);
    backdrop-filter: blur(20px);
}

.hero-stage {
    position: relative;
}

.hero-card {
    min-height: clamp(390px, 48vw, 540px);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: clamp(24px, 5vw, 56px);
    overflow: hidden;
}

.hero-card::after {
    content: "";
    position: absolute;
    width: 360px;
    height: 360px;
    right: -130px;
    top: -130px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(212, 175, 55, .23), transparent 68%);
    pointer-events: none;
}

.hero-logo {
    width: min(220px, 52vw);
    aspect-ratio: 1;
    object-fit: contain;
    border-radius: 999px;
    filter: drop-shadow(0 26px 44px rgba(0, 0, 0, .42));
    margin-bottom: clamp(18px, 3vw, 28px);
}

.hero-title {
    max-width: 760px;
    margin: 0 0 16px;
    color: #fff;
    font-size: clamp(38px, 7vw, 78px);
    line-height: .94;
    font-weight: 950;
    letter-spacing: 0;
}

.hero-slogan {
    max-width: 720px;
    margin: 0 0 26px;
    color: var(--white-soft);
    font-size: clamp(17px, 2.4vw, 24px);
    line-height: 1.45;
    font-weight: 650;
}

.hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.hero-actions .btn {
    min-height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding-inline: 20px;
}

.qr-showcase {
    min-height: 100%;
    padding: clamp(18px, 3vw, 28px);
}

.qr-showcase h2,
.category-stage h2,
#products .title h2 {
    color: #fff !important;
    font-weight: 900;
    letter-spacing: 0;
}

.qr-grid {
    display: grid;
    gap: 16px;
}

.qr-frame {
    position: relative;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 14px;
    align-items: center;
    min-height: 178px;
    padding: 16px;
    overflow: hidden;
    background: rgba(255, 255, 255, .08);
    border: 1px solid var(--line-gold);
    border-radius: 18px;
}

.qr-frame::before {
    content: "";
    position: absolute;
    inset: 10px;
    border: 1px solid rgba(212, 175, 55, .18);
    border-radius: 14px;
    pointer-events: none;
}

.qr-frame img,
.footer-grid img {
    width: 132px !important;
    height: 132px !important;
    object-fit: contain;
    background: #fff !important;
    border: 1px solid rgba(212, 175, 55, .65) !important;
    border-radius: 16px !important;
    padding: 8px !important;
    box-shadow: 0 18px 34px rgba(0, 0, 0, .30);
}

.qr-label {
    position: relative;
    z-index: 1;
}

.qr-label strong {
    display: block;
    color: var(--champagne-pale);
    font-size: 17px;
}

.qr-label span {
    display: block;
    margin-top: 5px;
    color: var(--muted-light);
    font-size: 14px;
}

.qr-empty {
    width: 132px;
    height: 132px;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, .08) !important;
    color: var(--muted-light) !important;
    border: 1px dashed var(--line-gold) !important;
    border-radius: 16px !important;
    text-align: center;
    font-size: 13px;
}

/* Map service area */
.map-stage {
    padding: clamp(18px, 3vw, 28px) 0;
}

.map-panel {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
    border: 1px solid rgba(52, 211, 153, .25);
    border-radius: 18px;
    padding: clamp(18px, 3vw, 28px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, .20);
}

.map-panel h2 {
    color: #fde047 !important;
    font-weight: 900;
    margin: 0 0 10px;
    font-size: clamp(22px, 3vw, 28px);
}

.map-desc {
    color: #d1fae5;
    font-size: 15px;
    margin: 0 0 18px;
    max-width: 760px;
}

.map-desc strong {
    color: #fde047;
}

.map-frame {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .25);
    margin-bottom: 18px;
}

.map-frame iframe {
    display: block;
    width: 100%;
    height: 420px;
    border: 0;
    border-radius: 12px;
}

#serviceModal {
    align-items: center !important;
    padding: 20px;
}
#serviceModalInner {
    width: 100% !important;
    max-width: 1050px !important;
    height: auto !important;
    max-height: 90vh !important;
    border-radius: 24px;
}
.service-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 12px;
}

@media(max-width:620px){
    #serviceModal {
        align-items: flex-end !important;
        padding: 0 !important;
    }
    #serviceModalInner {
        width: 100% !important;
        max-width: 100% !important;
        height: 85vh !important;
        max-height: 85vh !important;
        border-radius: 24px 24px 0 0 !important;
    }
    .service-items-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
}

.map-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.map-stat {
    background: rgba(255, 255, 255, .08);
    border: 1px solid rgba(52, 211, 153, .20);
    border-radius: 12px;
    padding: 14px;
    text-align: center;
}

.map-stat strong {
    display: block;
    color: #fde047;
    font-size: 14px;
    margin-bottom: 4px;
}

.map-stat span {
    display: block;
    color: #d1fae5;
    font-size: 13px;
}

@media (max-width: 768px) {
    .map-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .map-frame iframe {
        height: 280px;
    }
}

.category-stage {
    padding: clamp(18px, 3vw, 28px);
}

.category-rack {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 12px;
}

.category-orb {
    min-height: 136px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 10px;
    background: rgba(255, 255, 255, .08) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
    border-radius: 18px !important;
    box-shadow: 0 16px 34px rgba(0, 0, 0, .20);
    -webkit-tap-highlight-color: transparent;
}

.category-orb:hover,
.category-orb.active {
    background: rgba(212, 175, 55, .18) !important;
    border-color: var(--champagne) !important;
    box-shadow: 0 20px 42px rgba(212, 175, 55, .18), inset 0 0 0 1px rgba(255, 243, 191, .15);
}

.category-orb:active {
    transform: scale(.94);
}

.category-icon {
    width: 62px;
    height: 62px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: linear-gradient(145deg, rgba(255, 255, 255, .13), rgba(255, 255, 255, .04));
    border: 1px solid rgba(212, 175, 55, .55);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .20), 0 12px 24px rgba(0, 0, 0, .20);
    color: var(--champagne-pale);
    font-size: 27px;
}

.category-orb strong {
    font-size: 13px;
    color: #fff;
    line-height: 1.15;
}

#goi-tho.booking-shell {
    position: relative;
    overflow: hidden;
    padding: clamp(18px, 4vw, 34px) !important;
    background:
        radial-gradient(circle at 10% 0%, rgba(212, 175, 55, .20), transparent 36%),
        linear-gradient(135deg, rgba(6, 42, 29, .92), rgba(11, 61, 43, .82)) !important;
    border: 1px solid var(--line-gold) !important;
    border-radius: 24px !important;
    box-shadow: 0 28px 80px rgba(0, 0, 0, .32) !important;
}

#sectionMainTitle {
    color: var(--champagne-pale) !important;
    font-size: clamp(25px, 4vw, 38px) !important;
    text-shadow: 0 10px 28px rgba(0, 0, 0, .35) !important;
}

#sectionMainDesc {
    color: var(--white-soft) !important;
}

#bookingForm {
    padding: clamp(18px, 4vw, 30px) !important;
    background: rgba(2, 28, 20, .62) !important;
    border: 1px solid var(--line-gold) !important;
    border-radius: 20px !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .08), 0 24px 54px rgba(0, 0, 0, .26) !important;
    backdrop-filter: blur(18px);
}

#formMainTitle {
    color: #fff !important;
    border-bottom: 1px solid rgba(212, 175, 55, .32) !important;
    letter-spacing: .01em !important;
}

#bookingForm label {
    color: var(--champagne-pale) !important;
    font-size: 13px !important;
    letter-spacing: .02em;
}

#bookingForm input:not([type="hidden"]),
#bookingForm textarea,
#bookingForm select {
    background: transparent !important;
    color: #fff !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(212, 175, 55, .70) !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 13px 4px 12px !important;
}

#bookingForm input::placeholder,
#bookingForm textarea::placeholder {
    color: rgba(255, 255, 255, .46) !important;
}

#bookingForm input:focus,
#bookingForm textarea:focus,
#bookingForm select:focus {
    outline: none !important;
    border-bottom-color: var(--champagne) !important;
    box-shadow: 0 14px 24px -22px var(--champagne), 0 1px 0 var(--champagne) !important;
}

#serviceSelectorTrigger {
    background: rgba(255, 255, 255, .06) !important;
    color: #fff !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(212, 175, 55, .70) !important;
    border-radius: 0 !important;
    padding: 14px 4px !important;
}

#serviceSelectorTrigger span,
#serviceSelectorText,
#serviceSelectorText span {
    color: var(--champagne-pale) !important;
}

#bookingForm .btn:not(#bookingSubmit),
#useCurrentLocation,
#clearLocation,
#usePickupLocation,
#useDropoffLocation,
.open-map-btn,
#confirmMapPicker,
#btnScanQR,
#orderUseCurrentLocation {
    background: rgba(255, 255, 255, .10) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
    box-shadow: 0 12px 26px rgba(0, 0, 0, .18) !important;
}

#bookingSubmit {
    position: relative;
    min-height: 60px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--champagne-soft), var(--champagne)) !important;
    color: var(--emerald-950) !important;
    border: 1px solid rgba(255, 243, 191, .76) !important;
    border-radius: 18px !important;
    box-shadow: 0 22px 44px rgba(212, 175, 55, .26), inset 0 1px 0 rgba(255, 255, 255, .35) !important;
    text-transform: uppercase;
}

#bookingSubmit::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 0%, rgba(255, 255, 255, .42) 45%, transparent 70%);
    transform: translateX(-130%);
    transition: transform .65s ease;
}

#bookingSubmit:hover::after {
    transform: translateX(130%);
}

#bookingSubmit:disabled {
    opacity: .78;
    cursor: wait;
}

.dth-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(6, 42, 29, .24);
    border-top-color: var(--emerald-950);
    border-radius: 50%;
    animation: dthSpin .72s linear infinite;
}

@keyframes dthSpin {
    to { transform: rotate(360deg); }
}

.custom-service-item {
    background: rgba(255, 255, 255, .08) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
}

.custom-service-item span {
    color: #fff !important;
}

.custom-service-item span:last-child {
    color: var(--emerald-950) !important;
    background: linear-gradient(135deg, var(--champagne-soft), var(--champagne)) !important;
}

#serviceModal > div,
#mapPickerModal > div,
.dth-modal-content {
    background: linear-gradient(145deg, rgba(6,42,29,.96), rgba(3,21,15,.96)) !important;
    color: #fff !important;
    border: 1px solid var(--line-gold) !important;
    box-shadow: 0 28px 90px rgba(0, 0, 0, .42) !important;
}

#serviceModal > div > div,
#mapPickerModal > div > div {
    background: transparent !important;
    border-color: var(--line-gold) !important;
}

.dth-modal-title,
.dth-modal-body,
.dth-modal-body h4,
.dth-modal-content h2,
.dth-modal-content h3,
.dth-modal-content p,
.dth-modal-content label {
    color: #fff !important;
}

.dth-modal-close {
    color: var(--champagne-pale) !important;
}

.legal-lead,
.legal-section,
.legal-meta div,
.legal-item,
.legal-content {
    background: rgba(255, 255, 255, .07) !important;
    color: var(--white-soft) !important;
    border-color: var(--line-gold) !important;
}

.store-block {
    padding: clamp(16px, 3vw, 24px) !important;
}

.store-block > div:first-child {
    border-bottom: 1px solid var(--line-gold) !important;
}

.store-block h3,
.store-block h3 span,
.store-block div {
    color: #fff !important;
}

.grid {
    gap: 16px !important;
}

.product {
    min-height: 100%;
    overflow: hidden;
    transition: transform .24s ease, box-shadow .24s ease, border-color .24s ease;
}

.product:hover {
    transform: translateY(-4px);
    border-color: var(--champagne) !important;
    box-shadow: 0 26px 60px rgba(0, 0, 0, .34);
}

.product .img {
    background: linear-gradient(145deg, rgba(255,255,255,.12), rgba(255,255,255,.04)) !important;
    border-bottom: 1px solid rgba(212, 175, 55, .22) !important;
}

.product .body,
.product .name {
    color: #fff !important;
}

.price {
    color: var(--champagne-pale) !important;
}

.empty {
    color: var(--muted-light) !important;
}

footer {
    background: rgba(3, 21, 15, .94) !important;
    color: var(--muted-light) !important;
    border-top: 1px solid var(--line-gold);
}

.footer-grid h3,
.footer-bottom {
    color: #fff !important;
}

.footer-bottom {
    border-top-color: rgba(212, 175, 55, .28) !important;
}

@media (max-width: 992px) {
    .head {
        display: grid !important;
        grid-template-columns: 1fr !important;
        padding-block: 14px !important;
    }

    .head > div[style*="align-items: flex-end"] {
        align-items: stretch !important;
        max-width: none !important;
    }

    .head > div:last-child {
        justify-content: stretch;
    }

    .category-rack {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 620px) {
    .wrap {
        width: min(100% - 22px, 1180px) !important;
    }

    header {
        position: sticky !important;
    }

    .logo {
        font-size: 18px !important;
    }

    .hero-card {
        min-height: auto;
        padding: 24px;
    }

    .hero-logo {
        width: min(180px, 68vw);
    }

    .hero-title {
        font-size: clamp(36px, 13vw, 54px);
    }

    .hero-actions .btn {
        width: 100%;
    }

    .qr-frame {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .qr-frame img,
    .qr-empty {
        margin-inline: auto;
    }

    .category-rack {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .category-orb {
        min-height: 106px;
        padding: 10px 6px;
    }

    .category-icon {
        width: 52px;
        height: 52px;
        font-size: 23px;
    }

    #goi-tho .title {
        display: grid !important;
        gap: 6px;
    }

    #bookingForm [style*="display: flex; gap: 10px"],
    #bookingForm [style*="display: flex; gap: 8px"] {
        flex-direction: column !important;
    }
}

</style>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

</head>
<body>
<div class="approval-line" style="border-bottom: 1px solid #fed7aa;">Website cá nhân Điện Máy Hiếu - Chờ duyệt Bộ Công Thương</div>
<header>
    <div class="wrap head">
        <a class="logo" href="#" style="display:flex; align-items:center; gap: 8px;">
            <img src="LOGO.svg" alt="Logo Điện Máy Hiếu" style="height: 48px; border-radius: 6px; object-fit: contain;">
            <div>Điện Máy Hiếu<small>Dịch vụ gọi thợ \u0026 cửa hàng điện máy</small></div>
        </a>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex: 1; max-width: 520px;">
            <div id="topBarStatus">
                <button type="button" onclick="openLoginModal()" style="background: #fff; color: #dc2626; border: 1px solid #fca5a5; padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(220,38,38,0.1); transition: all 0.2s ease;">
                    <span style="font-size: 15px;">👤</span> Đăng nhập / Đăng ký
                </button>
            </div>
            <form class="search" id="searchForm" style="width: 100%; max-width: 100%;"><input id="searchInput" type="search" placeholder="Tìm sản phẩm..."><button type="submit">Tìm</button></form>
        </div>

<style>
@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
.custom-service-item:hover {
    border-color: #D4AF37 !important;
    background: rgba(212, 175, 55, 0.16) !important;
}
.custom-service-item:active {
    transform: scale(0.98);
}
</style>
        <div class="quick-tabs" style="display: flex; gap: 8px;">
            <a class="btn dark" href="#goi-tho" onclick="selectMainService('worker')" style="flex: 1; padding: 10px 5px; font-size: 14px; text-align: center;">🛠 Gọi thợ</a>
        </div>
    </div>

</header>

<main><div class="wrap storefront">
    <section class="section hero-stage" id="hero">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="glass-panel hero-card">
                    <img class="hero-logo" src="LOGO.svg" alt="Logo Điện Máy Hiếu">
                    <h1 class="hero-title">Điện Máy Hiếu</h1>
                    <p class="hero-slogan">Điện Máy Hiếu - Gọi thợ nhanh, điện máy chính hãng, phục vụ tận nhà.</p>
                    <div class="hero-actions">
                        <a class="btn" href="#goi-tho" onclick="selectMainService('worker')">Gọi thợ quick post</a>
                        <a class="btn dark" href="#products">Mua hàng 1 chạm</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="glass-panel qr-showcase">
                    <h2>Truy cập & thanh toán</h2>
                    <div class="qr-grid">
                        <div class="qr-frame">
                            <?php if ($qrWeb !== ''): ?>
                                <img src="<?= h($qrWeb) ?>" alt="QR truy cập Điện Máy Hiếu">
                            <?php else: ?>
                                <div class="qr-empty">QR.png</div>
                            <?php endif; ?>
                            <div class="qr-label">
                                <strong>Download App/Web quick access</strong>
                                <span>Quét để mở chợ số Lấp Vò trong tích tắc.</span>
                            </div>
                        </div>
                        <div class="qr-frame">
                            <?php if ($qrPay !== ''): ?>
                                <img src="<?= h($qrPay) ?>" alt="QR thanh toán Điện Máy Hiếu">
                            <?php else: ?>
                                <div class="qr-empty">QR_THANH_TOAN.png</div>
                            <?php endif; ?>
                            <div class="qr-label">
                                <strong>One-click checkout screen</strong>
                                <span>Khung thanh toán champagne gold, gọn và rõ trên mobile.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section panel booking-shell" id="goi-tho" style="background: linear-gradient(135deg, #b91c1c, #dc2626); border: 3px solid #fbbf24; border-radius: 12px; box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3); margin-top: 0; margin-bottom: 25px; padding: 25px;">
        <div class="title">
            <h2 id="sectionMainTitle" style="color: #fde047; text-transform: uppercase; letter-spacing: 1px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5); font-size: 26px; margin: 0;">GỌI THỢ QUICK POST</h2>
            <span id="sectionMainDesc" style="color: #fef08a; font-size: 15px;">Chốt thợ nhanh chóng, minh bạch giá cả</span>
        </div>

<form id="bookingForm" style="margin-top: 10px; padding: 25px; background: #fff; border: 2px solid #fca5a5; border-radius: 12px; box-shadow: 0 4px 15px rgba(220,38,38,0.1);">
    <h3 id="formMainTitle" style="color: #dc2626; margin-top: 0; margin-bottom: 20px; font-size: 22px; text-transform: uppercase; border-bottom: 2px solid #fee2e2; padding-bottom: 10px;">📋 Điền thông tin quick post</h3>

    <input type="hidden" id="service_type" name="service_type">
    <input type="hidden" id="tech_target_base" name="tech_target_base">
    <input type="hidden" id="selected_service_name" name="selected_service_name">
    <input type="hidden" id="bot_role" name="bot_role" value="worker">
    <input type="hidden" id="device_fingerprint" name="device_fingerprint">
    <input type="hidden" id="map_lat" name="map_lat">
    <input type="hidden" id="map_lng" name="map_lng">
    <input type="hidden" id="description" name="description">

    <div style="margin-bottom: 15px;">
        <label style="color: #475569; font-weight: bold; display: block; margin-bottom: 5px;">Chọn dịch vụ yêu cầu</label>

        <div id="serviceSelectorTrigger" style="background: #f8fafc; color: #1e293b; border: 2px solid #cbd5e1; border-radius: 10px; padding: 14px; width: 100%; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; transition: all 0.2s;">
            <span id="serviceSelectorText" style="color: #64748b; font-weight: 500;">-- Bấm vào đây để chọn dịch vụ --</span>
            <span style="color: #cbd5e1; font-size: 12px;">▼</span>
        </div>

    </div>



    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
        <div>
            <label style="color: #475569; font-weight: bold; display: block; margin-bottom: 5px;">Tên của bạn</label>
            <input type="text" id="customer_name" name="customer_name" required maxlength="150" placeholder="VD: Nguyễn Văn A" style="border: 2px solid #cbd5e1; border-radius: 8px; padding: 12px; width: 100%; box-sizing: border-box;">
        </div>
        <div>
            <label style="color: #475569; font-weight: bold; display: block; margin-bottom: 5px;">Số điện thoại</label>
            <input type="tel" id="phone" name="phone" required pattern="[0-9]{8,15}" placeholder="VD: 09xxxxxxxx" style="border: 2px solid #cbd5e1; border-radius: 8px; padding: 12px; width: 100%; box-sizing: border-box;">
        </div>
    </div>


    <!-- Khu vực nhập vị trí phục vụ -->
    <div id="location_single_group" style="margin-bottom: 25px;">
        <label style="color: #475569; font-weight: bold; display: block; margin-bottom: 5px;">Địa chỉ / Vị trí của bạn</label>
        <input type="text" id="address" name="address" required placeholder="Nhập số nhà, tên đường, ấp... hoặc bấm Định Vị bên dưới" style="border: 2px solid #cbd5e1; border-radius: 8px; padding: 12px; width: 100%; box-sizing: border-box; margin-bottom: 10px;">

        <div style="display: flex; gap: 10px;">
            <button type="button" id="useCurrentLocation" class="btn" style="flex: 1; background: #2563eb; color: white; border-radius: 8px; padding: 12px; font-weight: bold; font-size: 15px;">📍 Lấy tọa độ hiện tại</button>
            <button type="button" id="clearLocation" class="btn dark" style="flex: 0 0 auto; border-radius: 8px; padding: 12px;">❌ Xóa</button>
        </div>
        <div id="locationStatus" style="color: #047857; font-size: 14px; margin-top: 8px; font-weight: 500;"></div>
    </div>


    <button type="submit" id="bookingSubmit" class="btn" data-original-text="🚀 ALO ANH THIÊN - THỢ ĐẾN LIỀN" style="width: 100%; background: #dc2626; color: #fff; font-size: 18px; padding: 15px; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4); text-transform: uppercase;">🚀 ALO ANH THIÊN - THỢ ĐẾN LIỀN</button>
    <div id="bookingStatus" style="margin-top: 15px; font-weight: bold; text-align: center;"></div>
</form>

<div id="mapPickerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; padding: 15px; backdrop-filter: blur(2px);">
    <div style="background: #fff; width: 100%; max-width: 600px; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 15px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <h3 style="margin: 0; font-size: 18px; color: #1e293b; font-weight: 800;" id="mapPickerTitle">Chọn vị trí</h3>
            <button type="button" id="closeMapPicker" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #64748b; line-height: 1;">&times;</button>
        </div>
        <div id="mapPickerContainer" style="height: 400px; width: 100%;"></div>
        <div style="padding: 15px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
            <button type="button" id="confirmMapPicker" class="btn" style="width: 100%; background: #2563eb; font-size: 16px; font-weight: bold; padding: 12px;">✅ XÁC NHẬN VỊ TRÍ NÀY</button>
        </div>
    </div>
</div>

    <section class="section glass-panel category-stage" id="categories">
        <div class="title">
            <h2>Danh mục hot</h2>
            <span class="muted">Tập lọc nhanh sản phẩm</span>
        </div>
        <div class="category-rack" role="list" aria-label="Danh mục sản phẩm và dịch vụ">
            <button type="button" class="category-orb active" data-category="">
                <span class="category-icon">✦</span>
                <strong>Tất cả</strong>
            </button>
            <button type="button" class="category-orb" data-category="điện tử">
                <span class="category-icon">⚡</span>
                <strong>Điện tử</strong>
            </button>
            <button type="button" class="category-orb" data-category="gia dụng">
                <span class="category-icon">⌂</span>
                <strong>Gia dụng</strong>
            </button>
            <button type="button" class="category-orb" data-category="lạnh">
                <span class="category-icon">❄</span>
                <strong>Lạnh</strong>
            </button>
            <button type="button" class="category-orb" data-category="điện thoại">
                <span class="category-icon">▣</span>
                <strong>Điện thoại</strong>
            </button>
            <button type="button" class="category-orb" data-category="lọc nước">
                <span class="category-icon">◌</span>
                <strong>Lọc nước</strong>
            </button>
            <button type="button" class="category-orb" data-category="sim">
                <span class="category-icon">☎</span>
                <strong>Sim</strong>
            </button>
        </div>
    </section>

    <section class="section" id="products">
        <div class="title"><h2>Sản phẩm</h2><span class="muted"><?= h($productError !== '' ? $productError : (count($stores) . ' sản phẩm')) ?></span></div>
        <div id="productGrid">
            <?php if (empty($stores)): ?>
                <div class="empty">Hiện chưa có sản phẩm nào.</div>
            <?php else: ?>
                <?php foreach ($stores as $store): ?>
                    <?php
                        $storeType = h(lower_text($store['store_type']));
                        $sProducts = isset($storeProducts[$store['id']]) ? $storeProducts[$store['id']] : [];
                        if (empty($sProducts)) continue;
                    ?>
                    <div class="store-block" data-category="<?= $storeType ?>" style="margin-bottom: 30px; background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--line);">
                        <div style="border-bottom: 2px solid #fecaca; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <h3 style="margin: 0; color: var(--brand); font-size: 20px;">
                                🛒 <?= h($store['store_name']) ?> <span style="font-size: 14px; color: var(--muted); font-weight: normal;">(<?= h($store['store_type']) ?>)</span>
                            </h3>
                            <div style="font-size: 13px; color: #475569; display: flex; gap: 15px;">
                                <span>⭐ <?= isset($store['rating_score']) ? number_format((float)$store['rating_score'], 1) : '5.0' ?> (<?= isset($store['rating_count']) ? (int)$store['rating_count'] : 0 ?> đánh giá)</span>
                                <span>🕒 <?= h($store['working_hours'] ?? '07:00 - 22:00') ?></span>
                            </div>
                        </div>
                        <div class="grid">
                            <?php foreach ($sProducts as $p): ?>
                                <?php
                                $name = isset($p['name']) && $p['name'] !== '' ? $p['name'] : 'Món ăn / Đồ uống';
                                $image = isset($p['image_url']) ? $p['image_url'] : '';
                                $price = money_int(isset($p['price']) ? $p['price'] : 0);
                                $productId = (int)$p['id'];
                                ?>
                                <article class="product" data-name="<?= h(lower_text($name)) ?>" data-category="<?= $storeType ?>" data-product-id="<?= h($productId) ?>" data-product-type="product" data-product-name="<?= h($name) ?>" data-product-price="<?= h($price) ?>">
                                    <div class="img">
                                        <?php if ($image !== ''): ?>
                                            <img src="<?= h($image) ?>" alt="<?= h($name) ?>" onerror="this.src='data:image/svg+xml;utf8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%239ca3af\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z\'/%3E%3Ccircle cx=\'12\' cy=\'13\' r=\'4\'/%3E%3C/svg%3E'">
                                        <?php else: ?>
                                            <img src="data:image/svg+xml;utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z'/%3E%3Ccircle cx='12' cy='13' r='4'/%3E%3C/svg%3E" alt="Chưa có ảnh" style="width:50px; opacity:0.5; margin:auto; display:block;">
                                        <?php endif; ?>
                                    </div>
                                    <div class="body">
                                        <div class="name"><?= h($name) ?></div>
                                        <div class="price"><?= money_vnd($price) ?></div>
                                        <div class="buy-row"><button class="btn buy-product" type="button">Đặt mua</button></div>
                                        <span class="suggest" style="margin-top: 5px; display: block; color: #64748b; font-weight: normal;">Giá đã bao gồm VAT</span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- BẢN ĐỒ PHẠM VI HOẠT ĐỘNG -->
    <section class="section map-stage" id="service-area">
        <div class="glass-panel map-panel">
            <div class="map-header">
                <h2>🗺️ Phạm vi hoạt động</h2>
                <p class="map-desc">Điện Máy Hiếu hiện đang phục vụ tại <strong>khu vực Lấp Vò, tỉnh Đồng Tháp</strong> và các vùng lân cận. Dự kiến mở rộng toàn tỉnh Đồng Tháp trong thời gian tới.</p>
            </div>
            <div class="map-frame">
                <div id="service-area-map" style="width:100%; height:420px; border:0; border-radius:12px;"></div>
            </div>
            <script>
                (function() {
                    var center = [10.3611456, 105.5224619];
                    var map = L.map('service-area-map', {
                        center: center,
                        zoom: 12,
                        scrollWheelZoom: false
                    });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(map);
                    L.marker(center).addTo(map)
                        .bindPopup('<b>Điện Máy Hiếu</b><br>Trung tâm hoạt động').openPopup();
                    L.circle(center, {
                        color: '#dc2626',
                        fillColor: '#dc2626',
                        fillOpacity: 0.12,
                        radius: 15000
                    }).addTo(map).bindPopup('Phạm vi phục vụ: 15km');
                })();
            </script>
            <div class="map-stats">
                <div class="map-stat">
                    <strong>Khu vực chính</strong>
                    <span>Lấp Vò, Đồng Tháp</span>
                </div>
                <div class="map-stat">
                    <strong>Phạm vi</strong>
                    <span>15km từ trung tâm</span>
                </div>
                <div class="map-stat">
                    <strong>Thời gian giao</strong>
                    <span>30 phút – 2 giờ</span>
                </div>
                <div class="map-stat">
                    <strong>Mở rộng</strong>
                    <span>Toàn tỉnh Đồng Tháp</span>
                </div>
            </div>
        </div>
    </section>

    <div id="orderModal" class="dth-modal">
        <div class="dth-modal-content" style="max-width: 560px;">
            <span class="dth-modal-close" onclick="closeOrderModal()">&times;</span>
            <div class="dth-modal-title">Đặt mua sản phẩm</div>
            <form id="orderForm">
                <input type="hidden" id="order_product_id" name="product_id">
                <input type="hidden" id="order_product_type" name="type">
                <input type="hidden" id="order_product_name" name="product_name">
                <input type="hidden" id="order_product_price" name="price">
                <div class="field full">
                    <label>Sản phẩm</label>
                    <input id="order_product_display" type="text" readonly style="font-weight: bold; background: #f8fafc; color: #0f172a;">
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label>Đơn giá</label>
                        <input id="order_unit_price_display" type="text" readonly style="background: #f8fafc; color: #dc2626; font-weight: bold; text-align: end;">
                    </div>
                    <div style="width: 100px;">
                        <label for="order_quantity">Số lượng</label>
                        <input type="number" id="order_quantity" name="quantity" min="1" value="1" style="width: 100%; text-align: center; font-weight: bold; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
                    </div>
                </div>
                <div class="form">
                    <div class="field">
                        <label for="order_customer_name">Tên khách</label>
                        <input id="order_customer_name" name="customer_name" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label for="order_customer_phone">Số điện thoại</label>
                        <input id="order_customer_phone" name="customer_phone" type="tel" inputmode="numeric" pattern="[0-9]{8,15}" maxlength="15" required placeholder="09xxxxxxxx">
                    </div>
                    <div class="field full">
                        <label for="order_customer_address">Địa chỉ nhận hàng</label>

<div style="display: flex; gap: 8px;">
    <input id="order_customer_address" name="customer_address" maxlength="500" required placeholder="Nhập địa chỉ nhận hàng" style="flex: 1;">
    <input type="hidden" id="order_map_lat" name="map_lat">
    <input type="hidden" id="order_map_lng" name="map_lng">
    <button type="button" id="orderUseCurrentLocation" style="background: #2563eb; color: white; border: none; border-radius: 6px; padding: 0 12px; font-weight: bold; cursor: pointer; white-space: nowrap;">📍 Định vị</button>
</div>
<div id="orderLocationStatus" style="font-size: 12px; color: #16a34a; margin-top: 5px;"></div>

                    </div>
                    <div class="field full" id="order_goods_type_container" style="display: none;">
                        <label for="order_goods_type">Phân loại hàng hóa (Dành cho tính phí giao hàng)</label>
                        <select id="order_goods_type" name="goods_type">
                            <option value="light">Hàng nhẹ / Đồ ăn uống (Phí: 13k/2km đầu, 3.5k/km sau)</option>
                            <option value="bulky">Hàng cồng kềnh / Máy móc (Phí: 16k/1km đầu, 4k/km sau)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="order_payment_method">Thanh toán</label>
                        <select id="order_payment_method" name="payment_method">
                            <option value="cod">Thanh toán khi nhận hàng</option>
                            <option value="bank">Chuyển khoản</option>
                            <option value="cash">Tiền mặt tại cửa hàng</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="order_voucher_code">Mã giảm giá</label>


                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input id="order_voucher_code" name="voucher_code" maxlength="80" placeholder="Nhập mã" style="flex: 1; min-width: 0;">
                            <button type="button" id="btnScanQR" style="background: #2563eb; color: white; border: none; border-radius: 6px; padding: 0 12px; font-weight: bold; cursor: pointer; white-space: nowrap; height: 42px; display: flex; align-items: center; gap: 5px;">
                                📷 Quét QR
                            </button>
                        </div>
                        <div id="qr-reader" style="width: 100%; display: none; margin-top: 10px; border-radius: 8px; overflow: hidden; border: 2px solid #cbd5e1;"></div>
                        <div id="orderVoucherStatus" style="font-size: 14px; margin-top: 10px; font-weight: 500; background: #fef2f2; padding: 10px; border-radius: 6px; display: none;"></div>

                        <!-- Hiển thị Tổng tiền -->
                        <div style="margin-top: 15px; padding: 15px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; text-align: center;">
                            <span style="font-size: 14px; color: #64748b; text-transform: uppercase; font-weight: bold;">Tổng thanh toán</span><br>
                            <strong id="order_final_total_display" style="font-size: 24px; color: #dc2626;">0 đ</strong>
                        </div>

                    </div>
                    <div class="field full">
                        <label for="order_note">Ghi chú thanh toán</label>
                        <textarea id="order_note" name="note" maxlength="1000" placeholder="Ví dụ: giao giờ hành chính, giao trước 5h chiều..."></textarea>
                    </div>
                </div>
                <p class="muted">Shop sẽ gọi lại để xác nhận đơn và hướng dẫn thanh toán.</p>
                <button id="orderSubmit" type="submit">Gửi đơn mua</button>
                <div id="orderStatus" class="status"></div>
            </form>

        </div>
    </div>




</div></main>

<footer><div class="wrap">
    <div class="footer-grid">
        <div>
            <h3>ĐIỆN MÁY HIẾU</h3>
            <p>Số GCN ĐKKD/MST: 1402228630 do Sở Kế hoạch và Đầu tư tỉnh Đồng Tháp cấp ngày 10/08/2024</p>
            <p>Địa chỉ: 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Huyện Lấp Vò, Tỉnh Đồng Tháp</p>
            <p>Đại diện pháp luật: Ông Trần Công Vinh - Giám đốc</p>
            <p>Khu vực phục vụ: bán kính 15 km tính từ Cầu Lấp Vò</p>
        </div>
        <div>
            <h3>Liên hệ & Khiếu nại</h3>
            <p>Hotline: 0979.553.289 (Mua hàng & gọi thợ)</p>
            <p>Đầu mối giải quyết khiếu nại bảo vệ NTD: Ông Trần Công Vinh - Giám đốc</p>
            <p>Email: <a href="mailto:congvinh298@gmail.com" style="color:#fff;text-decoration:underline;">congvinh298@gmail.com</a></p>
        </div>
        <div>
            <h3>Thông tin pháp lý</h3>
            <p>Mã số thuế / Số ĐKKD: <strong>1402228630</strong></p>
            <p>Đại diện pháp lý: Ông Trần Công Vinh - Giám đốc</p>
            <p>Đã thông báo với Bộ Công Thương theo Nghị định 52/2013/NĐ-CP về thương mại điện tử và Thông tư 47/2014/TT-BCT.</p>
            <p><a href="/pages/dieu-khoan-su-dung">Điều khoản sử dụng</a></p>
            <p><a href="/pages/quy-che-hoat-dong">Quy chế hoạt động</a></p>
            <p><a href="/pages/chinh-sach-bao-mat">Chính sách bảo mật</a></p>
            <p><a href="/pages/giai-quyet-tranh-chap">Giải quyết tranh chấp</a></p>
        </div>
        <div>
            <h3>Hướng dẫn</h3>
            <p><a href="/pages/huong-dan-mua-hang">Hướng dẫn mua hàng</a></p>
            <p><a href="/pages/huong-dan-ban-hang">Hướng dẫn bán hàng</a></p>
            <p><a href="/pages/lien-he">Liên hệ</a></p>
            <p><a href="/pages/gioi-thieu">Giới thiệu</a></p>
        </div>
        <div>
            <h3>Truy cập nhanh</h3>
            <?php if ($qrWeb !== ''): ?><img src="<?= h($qrWeb) ?>" alt="QR truy cập" style="max-width: 120px; border-radius: 8px; background: white; padding: 5px; margin-top: 5px;"><?php endif; ?>
        </div>
    </div>
    <div class="footer-bottom"><span>© Điện Máy Hiếu - MST 1402228630</span><span>Tuân thủ Nghị định 52/2013/NĐ-CP & Thông tư 47/2014/TT-BCT | Đã thông báo Bộ Công Thương</span></div>
</div></footer>

<div id="modalPolicy" class="dth-modal">
    <div class="dth-modal-content">
        <span class="dth-modal-close" onclick="document.getElementById('modalPolicy').style.display='none'">&times;</span>
        <div id="modalTitle" class="dth-modal-title">Tiêu đề</div>
        <div id="modalBody" class="dth-modal-body">Nội dung</div>
    </div>
</div>

<!-- Modal Đăng nhập (Thật) -->
<div id="modalLogin" class="dth-modal">
    <div class="dth-modal-content" style="max-width: 420px; text-align: center; border-radius: 20px; padding: 30px 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        <span class="dth-modal-close" onclick="document.getElementById('modalLogin').style.display='none'" style="font-size: 30px; right: 20px; top: 15px;">&times;</span>
        <img src="LOGO.svg" alt="Logo" style="width: 70px; height: 70px; border-radius: 16px; margin-bottom: 15px; object-fit: contain; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <h2 style="margin-top: 0; color: #111827; font-size: 24px; font-weight: 900;">Đăng Nhập <br><span style="color: #dc2626; font-size: 20px;">Điện Máy Hiếu</span></h2>
        <p style="color: #6b7280; font-size: 14px; margin-bottom: 25px;">Chào mừng bạn quay lại, vui lòng chọn phương thức đăng nhập.</p>

        <div id="loginMethods">
            <button class="btn" style="width: 100%; margin-bottom: 12px; background: #dc2626; color: white; border-radius: 12px; padding: 12px; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);" onclick="showPhoneLogin()">📱 Tiếp tục với Số điện thoại</button>
            <button class="btn" style="width: 100%; margin-bottom: 12px; background: #2563eb; color: white; border-radius: 12px; padding: 12px; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);" onclick="showEmailLogin()">✉️ Tiếp tục với Email & Mật khẩu</button>
            <button class="btn" style="width: 100%; margin-bottom: 20px; background: #10b981; color: white; border-radius: 12px; padding: 12px; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);" onclick="showQrLogin()">🪪 Đăng nhập bằng Mã Thẻ / QR</button>

            

            <div style="font-size: 12px; color: #6b7280; text-align: start; background: #fef2f2; padding: 12px; border-radius: 8px; border: 1px solid #fee2e2;">
                <label style="display: flex; gap: 8px; font-weight: normal; cursor: pointer; align-items: flex-start;">
                    <input type="checkbox" id="tosCheck" checked style="margin-top: 3px;">
                    <span style="line-height: 1.4;">Bằng việc tiếp tục, tôi xác nhận đã đọc và đồng ý với <a href="javascript:void(0)" onclick="openModal('quyche'); document.getElementById('modalLogin').style.display='none'" style="color: #dc2626; font-weight: bold; text-decoration: underline;">Điều khoản dịch vụ</a> và <a href="javascript:void(0)" onclick="openModal('baomat'); document.getElementById('modalLogin').style.display='none'" style="color: #dc2626; font-weight: bold; text-decoration: underline;">Chính sách bảo mật</a> của hệ thống.</span>
                </label>
            </div>
        </div>

        <div id="emailLoginForm" style="display: none; text-align: start;">
            <p style="margin-bottom: 15px; font-weight: bold; color: #374151; text-align: center; font-size: 18px;">Đăng nhập Email & Mật khẩu</p>
            <label style="font-size: 13px; font-weight: bold;">Địa chỉ Email</label>
            <input type="email" id="loginEmailInput" placeholder="VD: qlhdtmdt@gmail.com" style="width: 100%; margin-bottom: 12px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
            <label style="font-size: 13px; font-weight: bold;">Mật khẩu</label>
            <input type="password" id="loginPasswordInput" placeholder="Nhập mật khẩu" style="width: 100%; margin-bottom: 15px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
            <button id="emailLoginSubmitBtn" class="btn primary" style="width: 100%; background: #2563eb;" onclick="submitEmailLogin()">Đăng nhập</button>
            <div id="emailLoginError" style="display: none; color: #dc2626; margin-top: 10px; font-size: 13px; text-align: center;"></div>
            <button class="btn" style="width: 100%; margin-top: 10px; background: transparent; color: #666; border: none; box-shadow: none;" onclick="document.getElementById('emailLoginForm').style.display='none'; document.getElementById('loginMethods').style.display='block';">Quay lại</button>
        </div>

        </div>

<script>
'use strict';
'use strict';

const cards = Array.from(document.querySelectorAll('.product'));
const searchInput = document.getElementById('searchInput');
let activeCategory = '';

function normalize(value) {
    return String(value || '').toLowerCase();
}

function formatVnd(value) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' VND';
}

function filterProducts() {
    const q = normalize(searchInput?.value);
    cards.forEach(card => {
        const okSearch = !q || card.dataset.name.indexOf(q) !== -1 || card.dataset.category.indexOf(q) !== -1;
        const okCategory = !activeCategory || card.dataset.category.indexOf(normalize(activeCategory)) !== -1;
        card.style.display = okSearch && okCategory ? '' : 'none';
    });
}

document.getElementById('searchForm')?.addEventListener('submit', event => {
    event.preventDefault();
    filterProducts();
});
searchInput?.addEventListener('input', filterProducts);

document.querySelectorAll('[data-category]').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-category]').forEach(item => item.classList.remove('active'));
        button.classList.add('active');
        activeCategory = button.dataset.category || '';
        filterProducts();
        const prodSection = document.getElementById('products');
        if (prodSection) prodSection.scrollIntoView({ behavior: 'smooth' });
    });
});

function openModal(type) {
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    const modal = document.getElementById('modalPolicy');

    if (!modal) return;

    const modalContent = {
        quyche: {
            title: 'Quy chế hoạt động website Điện Máy Hiếu',
            html: 
                <div class="legal-doc">
                    <div class="legal-lead">
                        <p><b>Điện Máy Hiếu</b> là website thương mại điện tử bán hàng và cung cấp dịch vụ do Trần Công Vinh (Điện Máy Hiếu) vận hành. Website chỉ giới thiệu, bán hàng và cung cấp dịch vụ gọi thợ của chính chủ sở hữu, không phải sàn giao dịch thương mại điện tử hay nền tảng kết nối nhiều cửa hàng/đối tác. Quy chế này công bố nguyên tắc vận hành, quy trình giao dịch, trách nhiệm của các bên và cơ chế bảo vệ quyền lợi người dùng.</p>
                    </div>
                    <div class="legal-meta">
                        <div><b>Chủ sở hữu website</b><br>Trần Công Vinh (Điện Máy Hiếu)</div>
                        <div><b>Mã số thuế</b><br>1402228630</div>
                        <div><b>Phạm vi phục vụ</b><br>Ưu tiên bán kính 15 km quanh Cầu Lấp Vò, Đồng Tháp</div>
                    </div>
                    <section class="legal-section">
                        <h4>1. Nguyên tắc chung</h4>
                        <ul>
                            <li>Website hoạt động theo quy định về thương mại điện tử, giao dịch điện tử, bảo vệ người tiêu dùng và bảo vệ dữ liệu cá nhân tại Việt Nam.</li>
                            <li>Thông tin hàng hóa, dịch vụ, giá, phí, tình trạng cung ứng và khuyến mại được công bố rõ ràng, trung thực, dễ kiểm tra.</li>
                            <li>Khách hàng và Điện Máy Hiếu phải cung cấp thông tin chính xác, chịu trách nhiệm về nội dung, hàng hóa, dịch vụ và cam kết của mình.</li>
                            <li>Website không khuyến khích và không dung túng hành vi gian lận, hàng cấm, hàng giả, lừa đảo, spam đơn, hủy đơn bất thường hoặc lợi dụng hệ thống để gây thiệt hại.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>2. Mô hình giao dịch</h4>
                        <p>Website cung cấp hai nhóm dịch vụ chính: (1) bán hàng điện máy, điện lạnh, gia dụng; (2) dịch vụ gọi thợ (vệ sinh/lắp đặt/sửa chữa máy lạnh, điện tử, gia dụng, vệ sinh nệm/sofa/thảm, lắp đặt đơn giản). Tất cả dịch vụ do Điện Máy Hiếu trực tiếp thực hiện hoặc điều phối thợ kỹ thuật đã ký hợp đồng/hợp tác với chủ cơ sở.</p>
                    </section>
                    <section class="legal-section">
                        <h4>3. Quy trình đặt hàng và sử dụng dịch vụ</h4>
                        <ul>
                            <li>Người dùng chọn sản phẩm hoặc dịch vụ, nhập thông tin liên hệ, địa chỉ, vị trí hoặc mô tả yêu cầu.</li>
                            <li>Hệ thống ghi nhận yêu cầu, gửi thông báo đến bộ phận vận hành của Điện Máy Hiếu.</li>
                            <li>Điện Máy Hiếu hoặc thợ kỹ thuật xác nhận khả năng phục vụ, thời gian dự kiến, chi phí công khai và các điều kiện phát sinh nếu có.</li>
                            <li>Người dùng kiểm tra, nghiệm thu hàng hóa hoặc dịch vụ trước khi thanh toán theo phương thức đã thỏa thuận.</li>
                            <li>Thông tin đơn hàng, trạng thái xử lý, lịch sử tích điểm, mã QR hoặc chứng từ nội bộ được lưu để phục vụ chăm sóc khách hàng, bảo hành, khiếu nại và đối soát.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>4. Chính sách giá, thanh toán và hóa đơn</h4>
                        <ul>
                            <li>Giá hiển thị là giá tham khảo hoặc giá công bố tại thời điểm đặt, có thể thay đổi khi phát sinh vật tư, linh kiện, khoảng cách, thời gian chờ hoặc yêu cầu ngoài phạm vi ban đầu.</li>
                            <li>Khoản thanh toán thực hiện trực tiếp cho Điện Máy Hiếu hoặc qua phương thức chuyển khoản được công bố.</li>
                            <li>Hóa đơn, phiếu thu, phiếu bảo hành hoặc chứng từ nội bộ được phát hành theo quy định và phạm vi giao dịch thực tế.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>5. Trách nhiệm của người dùng</h4>
                        <ul>
                            <li>Cung cấp đúng họ tên, số điện thoại, địa chỉ, vị trí, mô tả nhu cầu và thông tin cần thiết để xử lý giao dịch.</li>
                            <li>Không đặt đơn ảo, spam yêu cầu, cung cấp vị trí sai, xúc phạm nhân sự phục vụ hoặc lợi dụng chương trình khuyến mại, tích điểm.</li>
                            <li>Kiểm tra hàng hóa, dịch vụ, báo ngay khi có sự cố, lưu lại mã đơn hoặc chứng từ để được hỗ trợ.</li>
                            <li>Thanh toán đầy đủ khoản đã xác nhận và phối hợp giải quyết khiếu nại trên tinh thần thiện chí, trung thực.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>6. Trách nhiệm của Điện Máy Hiếu</h4>
                        <ul>
                            <li>Cung cấp hàng hóa, dịch vụ đúng thông tin đã đăng, đúng chất lượng, đúng giá đã xác nhận với khách hàng.</li>
                            <li>Tuân thủ quy định về nguồn gốc hàng hóa, an toàn lao động, bảo hành, đổi trả, trách nhiệm sau bán hàng và chuẩn mực phục vụ.</li>
                            <li>Không tự ý thu thêm phí ngoài thỏa thuận, không sử dụng dữ liệu khách hàng ngoài mục đích thực hiện giao dịch.</li>
                            <li>Phối hợp xử lý phản ánh, đối soát doanh thu, khóa/mở quyền nhận việc khi có vi phạm.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>7. Xử lý khiếu nại, tranh chấp và vi phạm</h4>
                        <p>Khi phát sinh phản ánh, khách hàng liên hệ hotline 0979.553.289 hoặc gửi thông tin qua kênh hỗ trợ của website. Điện Máy Hiếu tiếp nhận, phân loại, yêu cầu các bên cung cấp chứng cứ và hỗ trợ hòa giải trên cơ sở dữ liệu hệ thống, hình ảnh, tin nhắn, hóa đơn, vị trí, lịch sử đơn và xác nhận của các bên.</p>
                        <p>Tùy mức độ vi phạm, website có thể cảnh báo, tạm khóa quyền nhận việc, hủy đơn, thu hồi ưu đãi, chấm dứt tài khoản hoặc chuyển thông tin cho cơ quan có thẩm quyền.</p>
                    </section>
                    <section class="legal-section">
                        <h4>8. Cập nhật quy chế</h4>
                        <p>Quy chế có thể được điều chỉnh để phù hợp với quy định pháp luật, yêu cầu quản lý, phạm vi dịch vụ và quá trình nâng cấp hệ thống. Phiên bản mới sẽ được công bố trên website trước hoặc tại thời điểm áp dụng.</p>
                        <p class="legal-note">Tài liệu được xây dựng theo định hướng tham chiếu Nghị định 52/2013/NĐ-CP, Nghị định 85/2021/NĐ-CP về thương mại điện tử và các quy định pháp luật liên quan.</p>
                    </section>
                </div>
            
        },
        baomat: {
            title: 'Chính sách bảo mật và bảo vệ dữ liệu cá nhân',
            html: 
                <div class="legal-doc">
                    <div class="legal-lead">
                        <p>Điện Máy Hiếu coi dữ liệu người dùng là tài sản cần được bảo vệ. Chính sách này giải thích cách Điện Máy Hiếu thu thập, sử dụng, lưu trữ, chia sẻ và bảo vệ dữ liệu cá nhân khi người dùng mua hàng, gọi thợ, đăng nhập QR, tích điểm hoặc tương tác với website.</p>
                    </div>
                    <section class="legal-section">
                        <h4>1. Loại dữ liệu có thể được thu thập</h4>
                        <ul>
                            <li>Thông tin định danh cơ bản: họ tên, số điện thoại, mã đăng nhập, mã QR thành viên.</li>
                            <li>Thông tin giao dịch: sản phẩm, dịch vụ đã chọn, thời gian đặt, trạng thái đơn, giá trị thanh toán, điểm thưởng, mã khuyến mại, lịch sử chăm sóc khách hàng.</li>
                            <li>Thông tin vị trí và địa chỉ: địa chỉ giao hàng, tọa độ GPS khi người dùng chủ động cấp quyền hoặc chọn trên bản đồ.</li>
                            <li>Thông tin kỹ thuật: thiết bị, trình duyệt, địa chỉ IP, dấu hiệu chống spam/gian lận, log hệ thống, cookie hoặc mã định danh cần thiết cho bảo mật và vận hành.</li>
                            <li>Nội dung phản ánh: ghi chú đơn hàng, mô tả lỗi kỹ thuật, hình ảnh hoặc tài liệu người dùng cung cấp khi yêu cầu hỗ trợ.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>2. Mục đích xử lý dữ liệu</h4>
                        <ul>
                            <li>Xác minh người dùng, tạo đơn hàng, điều phối thợ và hỗ trợ xử lý yêu cầu.</li>
                            <li>Liên hệ xác nhận, thông báo trạng thái, chăm sóc sau bán hàng, bảo hành, đổi trả, tích điểm và khuyến mại.</li>
                            <li>Đối soát thanh toán, lập chứng từ, quản lý công nợ, phòng chống gian lận, spam, đơn giả và lạm dụng hệ thống.</li>
                            <li>Cải thiện chất lượng dịch vụ, đo lường hiệu quả vận hành, phát triển sản phẩm số và bảo đảm an toàn hệ thống.</li>
                            <li>Thực hiện nghĩa vụ theo yêu cầu hợp pháp của cơ quan nhà nước có thẩm quyền.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>3. Nguyên tắc bảo vệ dữ liệu</h4>
                        <ul>
                            <li>Chỉ thu thập dữ liệu phù hợp với mục đích đã công bố và phạm vi dịch vụ mà người dùng sử dụng.</li>
                            <li>Không bán dữ liệu cá nhân cho bên thứ ba. Không chia sẻ dữ liệu ngoài phạm vi cần thiết để thực hiện giao dịch, vận hành hệ thống hoặc tuân thủ pháp luật.</li>
                            <li>Áp dụng phân quyền truy cập, lưu vết xử lý, giới hạn nhân sự được xem dữ liệu và từng bước nâng cấp các biện pháp bảo mật kỹ thuật.</li>
                            <li>Khi phát hiện rủi ro, thất thoát hoặc truy cập trái phép, Điện Máy Hiếu sẽ ưu tiên khoanh vùng sự cố, giảm thiểu thiệt hại và phối hợp xử lý theo quy định.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>4. Bên được tiếp cận dữ liệu</h4>
                        <p>Dữ liệu có thể được chia sẻ ở mức cần thiết cho thợ kỹ thuật, bộ phận chăm sóc khách hàng, bộ phận kỹ thuật, đơn vị thanh toán, đơn vị hạ tầng công nghệ hoặc cơ quan nhà nước có thẩm quyền. Các bên tiếp cận dữ liệu phải sử dụng đúng mục đích, đúng phạm vi và bảo mật thông tin nhận được.</p>
                    </section>
                    <section class="legal-section">
                        <h4>5. Thời gian lưu trữ</h4>
                        <p>Dữ liệu được lưu trong thời gian cần thiết để vận hành dịch vụ, xử lý đơn hàng, bảo hành, khiếu nại, đối soát, kế toán, bảo mật và tuân thủ nghĩa vụ pháp lý. Khi không còn mục đích sử dụng hợp lý, dữ liệu sẽ được xóa, ẩn, tổng hợp hoặc lưu trữ ở dạng hạn chế theo chính sách nội bộ.</p>
                    </section>
                    <section class="legal-section">
                        <h4>6. Quyền của người dùng đối với dữ liệu cá nhân</h4>
                        <ul>
                            <li>Yêu cầu kiểm tra, cập nhật, chỉnh sửa thông tin cá nhân không chính xác.</li>
                            <li>Yêu cầu giải thích mục đích sử dụng dữ liệu hoặc phạm vi chia sẻ dữ liệu liên quan đến giao dịch của mình.</li>
                            <li>Yêu cầu hạn chế xử lý hoặc xóa dữ liệu trong trường hợp phù hợp, trừ dữ liệu cần giữ để giải quyết giao dịch, khiếu nại, nghĩa vụ kế toán, an ninh hoặc yêu cầu pháp luật.</li>
                            <li>Rút lại sự đồng ý đối với các xử lý không bắt buộc, hiểu rằng việc rút lại có thể ảnh hưởng đến khả năng sử dụng một số tính năng.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>7. Cookie, vị trí và mã QR</h4>
                        <p>Website có thể sử dụng cookie, local storage, mã QR, định danh thiết bị hoặc tọa độ do người dùng cấp quyền để duy trì đăng nhập, tích điểm, chống gian lận, gợi ý vị trí phục vụ và cải thiện trải nghiệm. Người dùng có thể tắt quyền vị trí trên trình duyệt, nhưng một số tính năng như gọi thợ, giao hàng hoặc xác minh phạm vi phục vụ có thể không hoạt động đầy đủ.</p>
                    </section>
                    <section class="legal-section">
                        <h4>8. Liên hệ bảo vệ dữ liệu</h4>
                        <p>Người dùng có câu hỏi, yêu cầu chỉnh sửa/xóa dữ liệu hoặc phản ánh về bảo mật có thể liên hệ: Trần Công Vinh (Điện Máy Hiếu), địa chỉ 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp, hotline 0979.553.289.</p>
                        <p class="legal-note">Chính sách này được xây dựng theo định hướng tuân thủ Nghị định 13/2023/NĐ-CP về bảo vệ dữ liệu cá nhân và các quy định pháp luật liên quan.</p>
                    </section>
                </div>
            
        },
        dean: {
            title: 'Đề án hoạt động website dịch vụ',
            html: 
                <div class="legal-doc">
                    <div class="legal-lead">
                        <p>Điện Máy Hiếu là đề án chuyển đổi số do Trần Công Vinh phát triển theo mô hình website thương mại điện tử bán hàng/cung cấp dịch vụ của cá nhân, kết hợp website, hệ thống quản trị, dữ liệu vận hành và công nghệ thông tin để phục vụ thương mại, dịch vụ kỹ thuật tại địa phương.</p>
                    </div>
                    <section class="legal-section">
                        <h4>1. Tầm nhìn</h4>
                        <p>Xây dựng website dịch vụ điện máy địa phương chuyên nghiệp, minh bạch, có dữ liệu đối soát và khả năng phục vụ khách hàng tại Lấp Vò, Đồng Tháp cùng các khu vực lân cận.</p>
                    </section>
                    <section class="legal-section">
                        <h4>2. Sứ mệnh</h4>
                        <ul>
                            <li>Đưa dịch vụ mua bán hàng điện máy, gọi thợ và chăm sóc sau bán hàng lên môi trường số.</li>
                            <li>Giúp khách hàng tiếp cận dịch vụ nhanh hơn, có giá tham khảo rõ hơn, có lịch sử giao dịch và kênh phản ánh minh bạch.</li>
                            <li>Tạo công cụ quản lý đơn hàng, thợ, khách hàng, khuyến mại, bảo hành và đối soát cho chủ cơ sở.</li>
                            <li>Từng bước chuẩn hóa quy trình vận hành, hồ sơ pháp lý và chất lượng dịch vụ.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>3. Phạm vi sản phẩm công nghệ</h4>
                        <ul>
                            <li>Website/PWA cho người dùng: xem sản phẩm, đặt hàng, gọi thợ, định vị, quét QR, tích điểm và theo dõi đơn.</li>
                            <li>Cổng quản trị nội bộ: quản lý sản phẩm, hóa đơn, khách hàng, thợ, báo cáo, khuyến mại, đối soát và cảnh báo vận hành.</li>
                            <li>Hệ thống thợ: công cụ tiếp nhận báo ca, cập nhật trạng thái, thanh toán và đối soát thu nhập.</li>
                            <li>Hệ thống dữ liệu: hồ sơ khách hàng, lịch sử giao dịch, điểm thưởng, phản ánh, bảo hành, chất lượng phục vụ và thống kê vận hành.</li>
                            <li>Tích hợp công nghệ: bản đồ, QR, thông báo, chatbot, tự động hóa, API, báo cáo và các công cụ công nghệ thông tin cần thiết.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>4. Lộ trình triển khai</h4>
                        <ul>
                            <li><b>Giai đoạn 1:</b> vận hành website, thử nghiệm quy trình đặt hàng/gọi thợ, tích điểm QR, quản trị đơn và hỗ trợ khách hàng tại khu vực Lấp Vò.</li>
                            <li><b>Giai đoạn 2:</b> chuẩn hóa hồ sơ sản phẩm, thợ, khách hàng; bổ sung báo cáo, hóa đơn, bảo hành, đánh giá chất lượng và đối soát minh bạch.</li>
                            <li><b>Giai đoạn 3:</b> phát triển ứng dụng di động, API, hệ thống phân quyền, chuẩn dữ liệu và tự động hóa điều phối.</li>
                            <li><b>Giai đoạn 4:</b> hoàn thiện thủ tục pháp lý, tiêu chuẩn bảo mật, năng lực vận hành và mở rộng sang các khu vực hoặc ngành dịch vụ phù hợp.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>5. Mô hình quản trị và kiểm soát chất lượng</h4>
                        <ul>
                            <li>Thiết lập quy trình tiếp nhận, xác minh, phân công, hoàn tất và lưu vết đơn hàng/dịch vụ.</li>
                            <li>Đánh giá thợ theo lịch sử giao dịch, phản ánh, tỷ lệ hoàn tất, thái độ phục vụ và nghĩa vụ đối soát.</li>
                            <li>Áp dụng cơ chế cảnh báo, tạm khóa, chấm dứt hợp tác đối với thợ vi phạm hoặc gây rủi ro cho khách hàng.</li>
                            <li>Từng bước chuẩn hóa chính sách bảo hành, đổi trả, xử lý sự cố, bảo vệ dữ liệu và an toàn thông tin.</li>
                        </ul>
                    </section>
                    <section class="legal-section">
                        <h4>6. Cam kết phát triển chuyên nghiệp</h4>
                        <p>Điện Máy Hiếu định hướng phát triển website như một sản phẩm công nghệ thông tin nghiêm túc: có hệ thống quản trị, dữ liệu, quy trình, trách nhiệm pháp lý, an toàn thông tin, trải nghiệm người dùng và năng lực mở rộng. Mục tiêu là phần mềm dịch vụ số có khả năng phục vụ thực tế, quản trị được rủi ro và tạo giá trị lâu dài cho khách hàng địa phương.</p>
                    </section>
                    <section class="legal-section">
                        <h4>7. Định hướng pháp lý</h4>
                        <p>Website đang trong quá trình hoàn thiện hồ sơ, quy chế, chính sách, quy trình vận hành và năng lực kỹ thuật để đáp ứng yêu cầu quản lý đối với hoạt động thương mại điện tử, dịch vụ số, bảo vệ dữ liệu cá nhân và bảo vệ quyền lợi người tiêu dùng.</p>
                        <p class="legal-note">Đề án này là bản công bố định hướng phát triển và có thể được cập nhật theo tình hình vận hành, yêu cầu quản lý nhà nước, phản hồi của người dùng và năng lực triển khai thực tế.</p>
                    </section>
                </div>
            
        }
    };

    const selected = modalContent[type];
    if (!selected) return;
    title.innerHTML = selected.title;
    body.innerHTML = selected.html;

    modal.style.display = 'block';
}

function openLoginModal() {
    const modal = document.getElementById('modalLogin');
    if (modal) modal.style.display = 'block';
}

function closeLoginModal() {
    const modal = document.getElementById('modalLogin');
    if (modal) modal.style.display = 'none';
}

let loginQrScanner = null;

function showEmailLogin() {
    document.getElementById('loginMethods').style.display = 'none';
    document.getElementById('qrLoginForm').style.display = 'none';
    document.getElementById('phoneLoginForm').style.display = 'none';
    document.getElementById('emailLoginForm').style.display = 'block';
    document.getElementById('emailLoginError').style.display = 'none';
}

function showPhoneLogin() {
    document.getElementById('loginMethods').style.display = 'none';
    document.getElementById('qrLoginForm').style.display = 'none';
    document.getElementById('phoneLoginForm').style.display = 'block';
    document.getElementById('phoneLoginError').style.display = 'none';
}

function submitPhoneLogin() {
    const name = document.getElementById('loginNameInput').value.trim();
    const phone = document.getElementById('loginPhoneInput').value.trim();
    const err = document.getElementById('phoneLoginError');
    if (!name || !phone) {
        err.textContent = 'Vui lòng nhập đầy đủ Họ Tên và Số điện thoại.';
        err.style.display = 'block';
        return;
    }

    document.getElementById('phoneLoginSubmitBtn').disabled = true;
    document.getElementById('phoneLoginSubmitBtn').textContent = 'Đang xử lý...';

    fetch('api_master.php?action=login_or_register_phone', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fullname: name, phone: phone })
    })
    .then(readJsonResponse)
    .then(d => {
        document.getElementById('phoneLoginSubmitBtn').disabled = false;
        document.getElementById('phoneLoginSubmitBtn').textContent = 'Xác nhận';
        if (d.status !== 'success') {
            err.textContent = d.message || 'Lỗi đăng nhập.';
            err.style.display = 'block';
            return;
        }
        handleLoginSuccess(d.data);
    })
    .catch(e => {
        document.getElementById('phoneLoginSubmitBtn').disabled = false;
        document.getElementById('phoneLoginSubmitBtn').textContent = 'Xác nhận';
        err.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
        err.style.display = 'block';
    });
}

function submitEmailLogin() {
    const email = document.getElementById('loginEmailInput').value.trim();
    const password = document.getElementById('loginPasswordInput').value.trim();
    const err = document.getElementById('emailLoginError');
    if (!email || !password) {
        err.textContent = 'Vui lòng nhập đầy đủ Email và Mật khẩu.';
        err.style.display = 'block';
        return;
    }

    document.getElementById('emailLoginSubmitBtn').disabled = true;
    document.getElementById('emailLoginSubmitBtn').textContent = 'Đang xử lý...';

    fetch('api_master.php?action=login_email_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: password })
    })
    .then(readJsonResponse)
    .then(d => {
        document.getElementById('emailLoginSubmitBtn').disabled = false;
        document.getElementById('emailLoginSubmitBtn').textContent = 'Đăng nhập';
        if (d.status !== 'success') {
            err.textContent = d.message || 'Lỗi đăng nhập.';
            err.style.display = 'block';
            return;
        }
        document.getElementById('emailLoginForm').style.display = 'none';
        handleLoginSuccess(d.data);
    })
    .catch(e => {
        document.getElementById('emailLoginSubmitBtn').disabled = false;
        document.getElementById('emailLoginSubmitBtn').textContent = 'Đăng nhập';
        err.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
        err.style.display = 'block';
    });
}

function showQrLogin() {
    document.getElementById('loginMethods').style.display = 'none';
    document.getElementById('phoneLoginForm').style.display = 'none';
    document.getElementById('qrLoginForm').style.display = 'block';
    document.getElementById('loginError').style.display = 'none';

    if (typeof Html5Qrcode !== 'undefined') {
        if (loginQrScanner) {
            loginQrScanner.clear();
            loginQrScanner = null;
        }
        loginQrScanner = new Html5Qrcode("qr-reader-login");
        loginQrScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText, decodedResult) => {
                document.getElementById('loginKeyInput').value = decodedText;
                closeQrLoginScannerOnly();
                submitLogin();
            },
            (errorMessage) => { }
        ).catch((err) => {
            console.warn("Không khởi tạo được camera", err);
            document.getElementById('qr-reader-login').innerHTML = '<p class="muted">Không truy cập được Camera. Vui lòng nhập mã thủ công.</p>';
        });
    }
}

function closeQrLoginScannerOnly() {
    if (loginQrScanner) {
        loginQrScanner.stop().then(() => {
            loginQrScanner.clear();
            loginQrScanner = null;
        }).catch(e => console.error(e));
    }
}

function closeQrLogin() {
    closeQrLoginScannerOnly();
    document.getElementById('qrLoginForm').style.display = 'none';
    document.getElementById('loginMethods').style.display = 'block';
}

function submitLogin() {
    const key = document.getElementById('loginKeyInput').value.trim();
    const err = document.getElementById('loginError');
    if (!key) {
        err.textContent = 'Vui lòng nhập Mã Thẻ hoặc quét QR.';
        err.style.display = 'block';
        return;
    }

    const btn = document.getElementById('loginSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Đang kiểm tra...';

    fetch('api_master.php?action=verify_login_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ login_key: key })
    })
    .then(readJsonResponse)
    .then(d => {
        btn.disabled = false;
        btn.textContent = 'Xác nhận Đăng nhập';
        if (d.status !== 'success') {
            err.textContent = d.message || 'Mã không hợp lệ.';
            err.style.display = 'block';
            return;
        }
        handleLoginSuccess(d.data);
    })
    .catch(e => {
        btn.disabled = false;
        btn.textContent = 'Xác nhận Đăng nhập';
        err.textContent = 'Lỗi kết nối.';
        err.style.display = 'block';
    });
}

function handleLoginSuccess(data) {
    if (data.type === 'store') {
        localStorage.setItem('dth_store_key', data.login_key);
        alert('Đăng nhập quản trị thành công! Chuyển hướng đến trang quản trị...');
        window.location.href = 'admin_xxx.php';
        return;
    }

    // User login
    localStorage.setItem('dth_user_key', data.login_key);
    localStorage.setItem('dth_user_time', Date.now());

    document.getElementById('phoneLoginForm').style.display = 'none';
    document.getElementById('qrLoginForm').style.display = 'none';
    document.getElementById('loginMethods').style.display = 'none';

    const dpy = document.getElementById('memberQrDisplay');
    dpy.style.display = 'block';

    document.getElementById('successUserName').textContent = data.fullname || 'Khách';
    document.getElementById('successUserRank').textContent = data.member_rank || 'Thành viên';
    document.getElementById('successUserPoints').textContent = (data.loyalty_points || 0) + ' điểm';
    if (data.qr_image_url) {
        document.getElementById('successQrImg').src = data.qr_image_url;
        document.getElementById('successQrImg').style.display = 'inline-block';
    } else {
        document.getElementById('successQrImg').style.display = 'none';
    }

    updateTopBarState(data);
}

function logoutCustomer() {
    localStorage.removeItem('dth_user_key');
    localStorage.removeItem('dth_user_time');
    window.location.reload();
}

function updateTopBarState(user) {
    const bar = document.getElementById('topBarStatus');
    if (bar && user) {
        bar.innerHTML = `<span style="color:white; margin-right:10px;">Xin chào, <b>${user.fullname || 'Khách'}</b></span>
                         <a href="javascript:void(0)" onclick="openCustomerOrdersModal()" style="color: #fbbf24; text-decoration: underline; font-weight: bold; margin-right: 15px;">Đơn hàng của tôi</a>
                         <a href="javascript:void(0)" onclick="openLoginModal()" style="color: #6ee7b7; text-decoration: underline; font-weight: bold; margin-right: 15px;">Mã QR của tôi</a>
                         <a href="javascript:void(0)" onclick="logoutCustomer()" style="color: #fca5a5; text-decoration: underline; font-weight: bold;">Đăng xuất</a>`;
    }
}

function showOrderStatus(type, text) {
    const box = document.getElementById('orderStatus');
    if (!box) return;
    box.className = 'status ' + (type === 'ok' ? 'ok' : 'err');
    box.textContent = text;
}

function closeOrderModal() {
    const modal = document.getElementById('orderModal');
    if (modal) modal.style.display = 'none';
}

function openOrderModal(card) {
    const name = card.dataset.productName || '';
    const price = Number(card.dataset.productPrice || 0);
    document.getElementById('order_product_id').value = card.dataset.productId || '0';
    document.getElementById('order_product_type').value = card.dataset.productType || 'product';
    document.getElementById('order_product_name').value = name;
    document.getElementById('order_product_price').value = String(price);

    document.getElementById('order_product_display').value = name;
    document.getElementById('order_unit_price_display').value = formatVnd(price) + ' đ';
    document.getElementById('order_quantity').value = 1;

    document.getElementById('orderStatus').className = 'status';
    document.getElementById('orderStatus').textContent = '';

    const statusEl = document.getElementById('orderVoucherStatus');
    if (statusEl) {
        statusEl.style.display = 'none';
        statusEl.innerHTML = '';
    }

    // Default show/hide goods type
    if (card.dataset.productType === 'product' && card.dataset.category) {
        document.getElementById('order_goods_type_container').style.display = 'block';
    } else {
        document.getElementById('order_goods_type_container').style.display = 'block'; // Or always show for all products
    }

    updateOrderTotal();

    document.getElementById('orderModal').style.display = 'block';
    document.getElementById('order_customer_name').focus();
}

let checkVoucherTimeout = null;

function updateOrderTotal() {
    const unitPrice = parseInt(document.getElementById('order_product_price').value) || 0;
    const quantityEl = document.getElementById('order_quantity');
    const quantity = quantityEl ? Math.max(1, parseInt(quantityEl.value) || 1) : 1;
    const subtotal = unitPrice * quantity;

    const codeEl = document.getElementById('order_voucher_code');
    const code = codeEl ? codeEl.value.trim() : '';
    const statusEl = document.getElementById('orderVoucherStatus');
    const totalDisplay = document.getElementById('order_final_total_display');

    if (!code) {
        if (statusEl) statusEl.style.display = 'none';
        if (totalDisplay) totalDisplay.innerHTML = formatVnd(subtotal) + ' đ';
        return;
    }

    if (checkVoucherTimeout) clearTimeout(checkVoucherTimeout);
    checkVoucherTimeout = setTimeout(async () => {
        if (statusEl) {
            statusEl.style.display = 'block';
            statusEl.style.background = '#f1f5f9';
            statusEl.style.border = '1px solid #cbd5e1';
            statusEl.innerHTML = '<span style="color:#64748b;">⏳ Đang kiểm tra...</span>';
        }

        try {
            const fd = new FormData();
            fd.append('action', 'check_voucher');
            fd.append('coupon_code', code);
            fd.append('code', code);
            fd.append('base_price', subtotal);

            const res = await fetch('api_master.php?action=check_voucher', { method: 'POST', body: fd });
            const json = await readJsonResponse(res);

            if (json.status === 'success' || json.status === 'ok') {
                const discount = parseInt(json.discount_amount) || 0;
                const finalPrice = Math.max(0, subtotal - discount);

                if (statusEl) {
                    statusEl.style.background = '#f0fdf4';
                    statusEl.style.border = '1px solid #bbf7d0';
                    statusEl.innerHTML = `<span style="color:#16a34a;">✅ Áp dụng mã thành công! Giảm ${formatVnd(discount)} đ</span>`;
                }

                if (totalDisplay) {
                    totalDisplay.innerHTML = `<span style="text-decoration: line-through; color: #94a3b8; font-size: 16px;">${formatVnd(subtotal)} đ</span> <br> ${formatVnd(finalPrice)} đ`;
                }
            } else {
                if (statusEl) {
                    statusEl.style.background = '#fef2f2';
                    statusEl.style.border = '1px solid #fecaca';
                    statusEl.innerHTML = `<span style="color:#dc2626;">❌ ${json.message || 'Mã không hợp lệ'}</span>`;
                }
                if (totalDisplay) totalDisplay.innerHTML = formatVnd(subtotal) + ' đ';
            }
        } catch (e) {
            if (statusEl) {
                statusEl.innerHTML = `<span style="color:#dc2626;">❌ Lỗi kết nối hệ thống.</span>`;
            }
            if (totalDisplay) totalDisplay.innerHTML = formatVnd(subtotal) + ' đ';
        }
    }, 500);
}

// Attach event listeners for real-time calculation
document.addEventListener('DOMContentLoaded', () => {
    const qtyEl = document.getElementById('order_quantity');
    if (qtyEl) {
        qtyEl.addEventListener('input', updateOrderTotal);
        qtyEl.addEventListener('change', updateOrderTotal);
    }
    const voucherEl = document.getElementById('order_voucher_code');
    if (voucherEl) {
        voucherEl.addEventListener('input', updateOrderTotal);
    }

    // Auto Login Check
    const storedUserKey = localStorage.getItem('dth_user_key');
    const storedUserTime = localStorage.getItem('dth_user_time');
    const storedStoreKey = localStorage.getItem('dth_store_key');

    // Check 10-minute expiry for customer on init
    if (storedUserKey && !storedStoreKey) {
        if (storedUserTime && (Date.now() - parseInt(storedUserTime)) > 600000) {
            localStorage.removeItem('dth_user_key');
            localStorage.removeItem('dth_user_time');
        } else {
            // Setup periodic check every minute for auto-logout
            setInterval(() => {
                const time = localStorage.getItem('dth_user_time');
                if (time && (Date.now() - parseInt(time)) > 600000) {
                    localStorage.removeItem('dth_user_key');
                    localStorage.removeItem('dth_user_time');
                    window.location.reload();
                }
            }, 60000);
        }
    }

    // Refresh variable after potential auto-logout
    const validUserKey = localStorage.getItem('dth_user_key');

    if (storedStoreKey) {
        // Quản trị viên chuyển về trang quản trị
        window.location.href = 'admin_xxx.php';
    } else if (validUserKey) {
        fetch('api_master.php?action=verify_login_key', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ login_key: validUserKey })
        })
        .then(readJsonResponse)
        .then(d => {
            if (d.status === 'success' && d.data.type === 'user') {
                updateTopBarState(d.data);
            } else {
                localStorage.removeItem('dth_user_key');
                localStorage.removeItem('dth_user_time');
            }
        }).catch(e => console.error(e));
    }
});

document.querySelectorAll('.buy-product').forEach(button => {
    button.addEventListener('click', () => {
        const card = button.closest('.product');
        if (card) openOrderModal(card);
    });
});

document.getElementById('orderForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = document.getElementById('orderSubmit');
    const phone = document.getElementById('order_customer_phone').value.trim();
    if (!/^[0-9]{8,15}$/.test(phone)) {
        showOrderStatus('err', 'Số điện thoại chỉ nhập số, từ 8 đến 15 chữ số.');
        return;
    }
    const formData = new FormData(form);
    submit.disabled = true;
    submit.textContent = 'Đang gửi...';
    showOrderStatus('ok', 'Đang gửi đơn mua...');
    try {
        const response = await fetch('api_master.php?action=create_order', {
            method: 'POST',
            body: formData
        });
        const data = await readJsonResponse(response);
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'Không gửi được đơn mua.');
        }
        showOrderStatus('ok', 'Đã gửi đơn #' + (data.order_code || data.order_id || '') + '. Shop sẽ gọi xác nhận.');
        ['order_customer_name', 'order_customer_phone', 'order_customer_address', 'order_voucher_code', 'order_note'].forEach(id => {
            const field = document.getElementById(id);
            if (field) field.value = '';
        });
    } catch (error) {
        showOrderStatus('err', error.message || 'Lỗi kết nối backend.');
    } finally {
        submit.disabled = false;
        submit.textContent = 'Gửi đơn mua';
    }
});

function checkLocationRadius(lat, lng) {
    const CENTER_LAT = 10.338528;
    const CENTER_LNG = 105.518472;
    const MAX_RADIUS_KM = 15;

    const R = 6371;
    const dLat = (lat - CENTER_LAT) * Math.PI / 180;
    const dLng = (lng - CENTER_LNG) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(CENTER_LAT * Math.PI / 180) * Math.cos(lat * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const distance = R * c;

    if (distance > MAX_RADIUS_KM) {
        alert('Rất tiếc! Hệ thống chỉ hỗ trợ phục vụ trong bán kính 15km quanh Cầu Lấp Vò, Đồng Tháp. Vui lòng chọn địa chỉ khác.');
        return false;
    }
    return true;
}

const serviceSearchInput = document.getElementById('serviceSearchInput');
if (serviceSearchInput) {
    serviceSearchInput.addEventListener('input', () => {
        const q = normalize(serviceSearchInput.value);
        document.querySelectorAll('.service-option').forEach(option => {
            option.style.display = !q || normalize(option.textContent).indexOf(q) !== -1 ? '' : 'none';
        });
    });
}

// Removed old choose-service buttons listener
/* document.querySelectorAll('.choose-service').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.choose-service').forEach(item => item.classList.remove('selected'));
        button.classList.add('selected');
        const base = Number(button.dataset.base || 0);
        document.getElementById('service_type').value = button.dataset.group || 'Thợ điện lạnh';
        document.getElementById('tech_target_base').value = button.dataset.base || '';
        document.getElementById('selected_service_name').value = button.dataset.service || '';
        document.getElementById('customer_price_display').value = base > 0 ? formatVnd(Math.round(base * 1.10)) + ' - đã gồm VAT' : 'Liên hệ để báo giá';
    });
}); */


document.addEventListener('DOMContentLoaded', () => {
    const serviceSelectorTrigger = document.getElementById('serviceSelectorTrigger');
    const serviceModal = document.getElementById('serviceModal');
    const closeServiceModal = serviceModal ? serviceModal.querySelector('#closeServiceModal') : null;
    const serviceSelectorText = document.getElementById('serviceSelectorText');

    if (serviceSelectorTrigger && serviceModal) {
        serviceSelectorTrigger.addEventListener('click', () => {
            serviceModal.querySelectorAll('.service-group-container').forEach(el => {
                el.style.display = 'block';
            });
            serviceModal.style.display = 'flex';
        });

        if (closeServiceModal) {
            closeServiceModal.addEventListener('click', () => {
                serviceModal.style.display = 'none';
            });
        }

        // Close on backdrop click
        serviceModal.addEventListener('click', (e) => {
            if (e.target === serviceModal) serviceModal.style.display = 'none';
        });

        serviceModal.querySelectorAll('.custom-service-item').forEach(item => {
            item.addEventListener('click', () => {
                const name = item.dataset.name || '';
                const group = item.dataset.group || '';
                const base = item.dataset.base || '0';
                const priceText = item.dataset.price || '';

                document.getElementById('service_type').value = group;
                document.getElementById('tech_target_base').value = base;
                document.getElementById('selected_service_name').value = name;

                serviceSelectorText.innerHTML = `<span style="color:#D4AF37;font-weight:700;">${name}</span> <span style="font-size:13px;color:rgba(255,255,255,.68);"> - ${priceText}</span>`;
                serviceSelectorTrigger.style.borderColor = '#D4AF37';
                serviceSelectorTrigger.style.background = 'rgba(255,255,255,.06)';


                serviceModal.style.display = 'none';
            });
        });
    }
});



const addressInput = document.getElementById('address');
const locationStatus = document.getElementById('locationStatus');

function setLocationStatus(text) {
    const ls = document.getElementById('locationStatus');
    if (ls) ls.textContent = text;
}

document.getElementById('useCurrentLocation')?.addEventListener('click', () => {
    if (!navigator.geolocation) {
        showBookingStatus('err', 'Trình duyệt chưa hỗ trợ lấy vị trí.');
        return;
    }
    setLocationStatus('Đang lấy vị trí...');
    navigator.geolocation.getCurrentPosition(async position => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        if (!checkLocationRadius(lat, lng)) {
            setLocationStatus('Vị trí nằm ngoài vùng phục vụ (15km quanh Lấp Vò).');
            return;
        }
        document.getElementById('map_lat').value = lat;
        document.getElementById('map_lng').value = lng;

        setLocationStatus('Đã lấy tọa độ thành công. Đang tải địa chỉ...');
        try {
            const response = await fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=vi&lat=' + lat + '&lon=' + lng);
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();
            if (data.display_name) {
                document.getElementById('address').value = data.display_name;
            }
            setLocationStatus('Đã nhận diện: ' + lat.toFixed(5) + ', ' + lng.toFixed(5));
        } catch (error) {
            setLocationStatus('Đã lấy tọa độ: ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' (Bạn có thể nhập thêm địa chỉ cụ thể ở trên)');
        }
    }, () => {
        setLocationStatus('Không lấy được vị trí. Vui lòng kiểm tra quyền truy cập vị trí của trình duyệt.');
    });
});

document.getElementById('clearLocation')?.addEventListener('click', () => {
    document.getElementById('map_lat').value = '';
    document.getElementById('map_lng').value = '';
    const addressInput = document.getElementById('address');
    if (addressInput) addressInput.value = '';
    setLocationStatus('');
});



function deviceId() {
    let id = localStorage.getItem('dth_device_id');
    if (!id) {
        id = 'dth-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
        localStorage.setItem('dth_device_id', id);
    }
    return id;
}

function showBookingStatus(type, text) {
    const box = document.getElementById('bookingStatus');
    box.className = 'status ' + (type === 'ok' ? 'ok' : 'err');
    box.textContent = text;
}

async function readJsonResponse(response) {
    const raw = await response.text();
    if (!raw) {
        throw new Error(`Backend tra HTTP ${response.status} nhung khong co noi dung JSON.`);
    }
    try {
        return JSON.parse(raw);
    } catch (error) {
        const preview = raw.replace(/\s+/g, ' ').trim().slice(0, 180);
        throw new Error(`Backend khong tra JSON hop le (HTTP ${response.status}). ${preview || 'Phan hoi rong.'}`);
    }
}

let currentMainService = 'worker';

function selectMainService(type) {
    currentMainService = type;
    const roleInput = document.getElementById('bot_role');
    const formTitle = document.getElementById('formMainTitle');
    const sectionTitle = document.getElementById('sectionMainTitle');
    const sectionDesc = document.getElementById('sectionMainDesc');
    const submitBtn = document.getElementById('bookingSubmit');
    const selectorTrigger = document.getElementById('serviceSelectorTrigger');
    const selectorText = document.getElementById('serviceSelectorText');

    selectorTrigger.style.pointerEvents = 'auto';
    selectorTrigger.style.opacity = '1';

    if (type === 'worker') {
        if (roleInput) roleInput.value = 'worker';
        sectionTitle.innerHTML = 'GỌI THỢ QUICK POST';
        sectionDesc.textContent = 'Chốt thợ nhanh chóng, minh bạch giá cả';
        formTitle.textContent = '📋 Điền thông tin quick post';
        submitBtn.innerHTML = '🚀 ALO ANH THIÊN - THỢ ĐẾN LIỀN';
        submitBtn.dataset.originalText = '🚀 ALO ANH THIÊN - THỢ ĐẾN LIỀN';
        selectorText.innerHTML = '-- Bấm vào đây để chọn dịch vụ --';
        document.getElementById('service_type').value = '';
        document.getElementById('tech_target_base').value = '';
        document.getElementById('selected_service_name').value = '';
        document.getElementById('location_single_group').style.display = 'block';
        document.getElementById('address').required = true;
    }
}

document.getElementById('bookingForm')?.addEventListener('submit', async event => {
    event.preventDefault();

    const form = event.currentTarget;
    const submitButton = document.getElementById('bookingSubmit');
    const phone = document.getElementById('phone').value.trim();
    const selectedService = document.getElementById('selected_service_name').value.trim();

    if (!selectedService) {
        showBookingStatus('err', 'Vui lòng chọn dịch vụ cụ thể trong danh sách phía trên trước khi gửi form.');
        return;
    }

    if (!/^[0-9]{8,15}$/.test(phone)) {
        showBookingStatus('err', 'Số điện thoại chỉ được nhập số, từ 8 đến 15 chữ số.');
        return;
    }

    const lat = document.getElementById('map_lat').value.trim();
    const lng = document.getElementById('map_lng').value.trim();
    const address = document.getElementById('address').value.trim();
    if (!address) {
        showBookingStatus('err', 'Vui lòng nhập địa chỉ hoặc lấy tọa độ hiện tại.');
        document.getElementById('address').focus();
        return;
    }

    // Điền mô tả = tên dịch vụ đã chọn
    document.getElementById('description').value = selectedService;
    document.getElementById('device_fingerprint').value = deviceId();

    const formData = new FormData(form);
    const originalSubmitText = submitButton.dataset.originalText || submitButton.innerHTML;
    submitButton.dataset.originalText = originalSubmitText;
    submitButton.disabled = true;
    submitButton.classList.add('is-loading');
    submitButton.innerHTML = '<span class="dth-spinner" aria-hidden="true"></span><span>Đang chốt kèo...</span>';
    showBookingStatus('ok', 'Đang gửi yêu cầu...');

    try {
        const response = await fetch('api_master.php?action=create_job', {
            method: 'POST',
            body: formData
        });
        const data = await readJsonResponse(response);
        if (!response.ok || !(data.status === 'success' || data.success === true)) {
            throw new Error(data.message || 'Không gửi được yêu cầu.');
        }
        alert('Đã báo cáo thành công!');
        form.reset();
        selectMainService('worker');
        document.getElementById('map_lat').value = '';
        document.getElementById('map_lng').value = '';
        document.querySelectorAll('.choose-service').forEach(item => item.classList.remove('selected'));
        setLocationStatus('Vui lòng nhập địa chỉ hoặc lấy tọa độ hiện tại.');
        showBookingStatus('ok', 'Yêu cầu đã gửi thành công.');
    } catch (error) {
        showBookingStatus('err', error.message || 'Lỗi kết nối backend.');
    } finally {
        submitButton.disabled = false;
        submitButton.classList.remove('is-loading');
        submitButton.innerHTML = submitButton.dataset.originalText || '🚀 ALO ANH THIÊN - THỢ ĐẾN LIỀN';
    }
});


</script>
<script>

// GPS for Order
document.getElementById('orderUseCurrentLocation')?.addEventListener('click', () => {
    if (!navigator.geolocation) return alert('Trình duyệt không hỗ trợ GPS.');
    const ls = document.getElementById('orderLocationStatus');
    ls.textContent = 'Đang lấy vị trí...';
    navigator.geolocation.getCurrentPosition(async position => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        if (!checkLocationRadius(lat, lng)) {
            ls.textContent = 'Vị trí nằm ngoài vùng phục vụ (15km quanh Lấp Vò).';
            return;
        }
        document.getElementById('order_map_lat').value = lat;
        document.getElementById('order_map_lng').value = lng;
        ls.textContent = 'Đã lấy tọa độ. Đang tải địa chỉ...';
        try {
            const response = await fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=vi&lat=' + lat + '&lon=' + lng);
            const data = await response.json();
            if (data && data.display_name) {
                document.getElementById('order_customer_address').value = data.display_name;
            } else {
                document.getElementById('order_customer_address').value = lat.toFixed(6) + ', ' + lng.toFixed(6);
            }
            ls.textContent = 'Đã nhận diện: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
        } catch (e) {
            document.getElementById('order_customer_address').value = lat.toFixed(6) + ', ' + lng.toFixed(6);
            ls.textContent = 'Đã lấy tọa độ: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
        }
    }, () => ls.textContent = 'Lỗi GPS. Vui lòng cấp quyền.');
});

// Voucher Check for Order
// QR Scanner Logic
let html5QrCode = null;
document.getElementById('btnScanQR')?.addEventListener('click', () => {
    const readerDiv = document.getElementById('qr-reader');
    const btn = document.getElementById('btnScanQR');

    if (html5QrCode) {
        // Stop scanning
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
            readerDiv.style.display = 'none';
            btn.innerHTML = '📷 Quét QR';
        }).catch(err => {
            console.error('Failed to stop scanner', err);
        });
        return;
    }

    readerDiv.style.display = 'block';
    btn.innerHTML = '❌ Đóng';

    let isScanningSuccess = false;
    html5QrCode = new Html5Qrcode('qr-reader');
    html5QrCode.start(
        { facingMode: 'environment' },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        (decodedText, decodedResult) => {
            if (isScanningSuccess) return;
            isScanningSuccess = true;

            // Handle on success
            let finalCode = decodedText;
            try {
                const url = new URL(decodedText);
                if (url.searchParams.has('voucher')) {
                    finalCode = url.searchParams.get('voucher');
                } else {
                    const parts = url.pathname.split('/');
                    const lastPart = parts[parts.length - 1];
                    if (lastPart) finalCode = lastPart;
                }
            } catch(e) {
                // Not a URL, use as is
            }
            document.getElementById('order_voucher_code').value = finalCode;

            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                html5QrCode = null;
                readerDiv.style.display = 'none';
                btn.innerHTML = '📷 Quét QR';

                // Auto trigger Check
                updateOrderTotal();
            }).catch(err => {
                console.error('Failed to stop scanner', err);
            });
        },
        (errorMessage) => {
            // parse error, ignore it.
        }
    ).catch((err) => {
        alert('Không thể truy cập camera. Vui lòng cấp quyền camera cho trình duyệt web (Cài đặt -> Ứng dụng -> Trình duyệt -> Quyền -> Camera).');
        html5QrCode = null;
        readerDiv.style.display = 'none';
        btn.innerHTML = '📷 Quét QR';
    });
});
</script>
<div id="customerOrdersModal" class="dth-modal">
    <div class="dth-modal-content" style="max-width: 500px; padding: 20px;">
        <span class="dth-modal-close" onclick="document.getElementById('customerOrdersModal').style.display='none'">&times;</span>
        <h3 style="margin-top:0; color:#dc2626; border-bottom:1px solid #fee2e2; padding-bottom:10px;">📦 Đơn hàng của tôi</h3>
        <div id="customerOrdersList" style="max-height: 400px; overflow-y: auto;">
            Đang tải...
        </div>
    </div>
</div>

<script>
async function openCustomerOrdersModal() {
    const key = localStorage.getItem('dth_user_key');
    if(!key) return;
    const modal = document.getElementById('customerOrdersModal');
    if(!modal) return;
    modal.style.display = 'block';

    const list = document.getElementById('customerOrdersList');
    list.innerHTML = '<div style="text-align:center; padding:20px;">Đang tải đơn hàng...</div>';

    try {
        const formData = new FormData();
        formData.append('login_key', key);
        const response = await fetch('api_master.php?action=app_customer_get_orders', { method: 'POST', body: formData });
        const res = await readJsonResponse(response);

        if (res.status === 'success') {
            const orders = res.data;
            if (orders.length === 0) {
                list.innerHTML = '<div style="text-align:center; padding:20px; color:#6b7280;">Bạn chưa có đơn hàng nào.</div>';
                return;
            }
            let html = '';
            orders.forEach(o => {
                const isCancelled = o.status === 'cancelled' || o.status === 'Đã hủy';
                let statusColor = '#d97706';
                let statusText = 'Chờ xử lý';
                if (o.status === 'completed') { statusColor = '#059669'; statusText = 'Thành công'; }
                else if (o.status === 'delivering') { statusColor = '#3b82f6'; statusText = 'Đang giao'; }
                else if (isCancelled) { statusColor = '#dc2626'; statusText = 'Đã hủy'; }
                else if (o.status === 'customer_received') { statusColor = '#059669'; statusText = 'Đã nhận (Chờ thu tiền)'; }
                html += `
                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <b style="font-size:15px; color:#111827;">${o.product_name}</b>
                        <span style="color:${statusColor}; font-weight:bold; font-size:13px;">${statusText}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:#6b7280; margin-bottom:10px;">
                        <span>Mã: ${o.order_code}</span>
                        <b style="color:#dc2626; font-size:14px;">${new Intl.NumberFormat('vi-VN').format(o.total_price)} đ</b>
                    </div>
                    ${(!isCancelled && o.status !== 'completed' && o.status !== 'delivering' && o.status !== 'customer_received') ? `<button onclick="cancelCustomerOrder(${o.id})" style="width:100%; padding:10px; border:none; background:#dc2626; color:white; border-radius:6px; font-weight:bold; cursor:pointer; margin-bottom:5px;">❌ Hủy đơn hàng</button>` : ''}
                    ${(!isCancelled && o.status !== 'completed') ? `<button onclick="confirmCustomerOrder(${o.id})" style="width:100%; padding:10px; border:none; background:#10b981; color:white; border-radius:6px; font-weight:bold; cursor:pointer;">✅ Đã nhận được hàng</button>` : ''}
                </div>`;
            });
            list.innerHTML = html;
        } else {
            list.innerHTML = '<div style="text-align:center; padding:20px; color:#dc2626;">' + res.message + '</div>';
        }
    } catch(e) {
        list.innerHTML = '<div style="text-align:center; padding:20px; color:#dc2626;">Lỗi tải dữ liệu.</div>';
    }
}

async function cancelCustomerOrder(id) {
    if(!confirm('Xác nhận hủy đơn hàng này?')) return;
    try {
        const key = localStorage.getItem('dth_user_key');
        const formData = new FormData();
        formData.append('login_key', key);
        formData.append('order_id', id);
        const response = await fetch('api_master.php?action=app_customer_cancel_order', { method: 'POST', body: formData });
        const res = await readJsonResponse(response);
        if(res.status === 'success') {
            alert('Đã hủy đơn hàng!');
            openCustomerOrdersModal();
        } else {
            alert(res.message);
        }
    } catch(e) {
        alert('Lỗi kết nối.');
    }
}

async function confirmCustomerOrder(id) {
    if(!confirm('Xác nhận bạn đã nhận được đơn hàng này?')) return;
    try {
        const key = localStorage.getItem('dth_user_key');
        const formData = new FormData();
        formData.append('login_key', key);
        formData.append('order_id', id);
        const response = await fetch('api_master.php?action=app_customer_confirm_order', { method: 'POST', body: formData });
        const res = await readJsonResponse(response);
        if(res.status === 'success') {
            alert(res.message || 'Xác nhận thành công!');
            openCustomerOrdersModal();
            // Refresh user points
            fetch('api_master.php?action=verify_login_key', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ login_key: key })
            })
            .then(readJsonResponse)
            .then(d => {
                if (d.status === 'success' && d.data.type === 'user') {
                    const el = document.getElementById('successUserPoints');
                    if (el) el.textContent = (d.data.loyalty_points || 0) + ' điểm';
                    const sl = document.getElementById('successLuckySpins');
                    if (sl) sl.textContent = (d.data.lucky_spins || 0) + ' lượt quay';
                }
            }).catch(e => console.error(e));
        } else {
            alert(res.message);
        }
    } catch(e) {
        alert('Lỗi kết nối.');
    }
}

const wheelPrizes = ['Thêm 10 điểm', 'Chúc may mắn', 'Thêm 50 điểm', '1 Lượt quay', 'Thêm 20 điểm', 'Chúc may mắn'];
let wheelAngle = 0;
let isSpinning = false;

function drawWheel() {
    const canvas = document.getElementById('wheelCanvas');
    if(!canvas) return;
    const ctx = canvas.getContext('2d');
    const radius = canvas.width / 2;
    const sliceAngle = (2 * Math.PI) / wheelPrizes.length;
    const colors = ['#f87171', '#fbbf24', '#34d399', '#60a5fa', '#a78bfa', '#f472b6'];

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (let i = 0; i < wheelPrizes.length; i++) {
        const startAngle = i * sliceAngle + wheelAngle;
        const endAngle = startAngle + sliceAngle;
        ctx.beginPath();
        ctx.moveTo(radius, radius);
        ctx.arc(radius, radius, radius, startAngle, endAngle);
        ctx.fillStyle = colors[i % colors.length];
        ctx.fill();
        ctx.stroke();

        ctx.save();
        ctx.translate(radius, radius);
        ctx.rotate(startAngle + sliceAngle / 2);
        ctx.textAlign = "right";
        ctx.fillStyle = "#fff";
        ctx.font = "bold 14px Arial";
        ctx.fillText(wheelPrizes[i], radius - 10, 5);
        ctx.restore();
    }
}

function openWheelModal() {
    document.getElementById('wheelModal').style.display = 'block';
    const spinsStr = document.getElementById('successLuckySpins').textContent;
    const spins = parseInt(spinsStr) || 0;
    document.getElementById('wheelSpinsCount').textContent = spins;
    drawWheel();
}

async function spinWheel() {
    if(isSpinning) return;
    let spins = parseInt(document.getElementById('wheelSpinsCount').textContent) || 0;
    if(spins <= 0) {
        alert('Bạn đã hết lượt quay!');
        return;
    }

    isSpinning = true;
    const btn = document.getElementById('spinBtn');
    btn.textContent = 'Đang quay...';
    btn.disabled = true;

    try {
        const key = localStorage.getItem('dth_user_key');
        const formData = new FormData();
        formData.append('login_key', key);
        const response = await fetch('api_master.php?action=app_customer_spin_wheel', { method: 'POST', body: formData });
        const res = await readJsonResponse(response);

        if(res.status === 'success') {
            const prizeIndex = res.data.prize_index;
            const extraRotations = 5;
            const sliceAngle = 360 / wheelPrizes.length;
            const targetSliceCenter = prizeIndex * sliceAngle + sliceAngle / 2;
            const targetRotationDeg = 270 - targetSliceCenter + (extraRotations * 360);

            let currentDeg = wheelAngle * 180 / Math.PI;
            const totalRotation = targetRotationDeg - (currentDeg % 360) + (extraRotations * 360);

            const duration = 4000;
            const start = performance.now();

            function animate(time) {
                let progress = (time - start) / duration;
                if(progress > 1) progress = 1;
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const currentAnimatedDeg = currentDeg + totalRotation * easeOut;
                wheelAngle = currentAnimatedDeg * Math.PI / 180;
                drawWheel();

                if(progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    isSpinning = false;
                    btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)';
                    btn.disabled = false;
                    alert('🎉 Kết quả: ' + wheelPrizes[prizeIndex] + '\\n' + res.message);

                    document.getElementById('wheelSpinsCount').textContent = res.data.lucky_spins;
                    document.getElementById('successLuckySpins').textContent = res.data.lucky_spins + ' lượt quay';
                    document.getElementById('successUserPoints').textContent = res.data.loyalty_points + ' điểm';
                }
            }
            requestAnimationFrame(animate);

        } else {
            alert(res.message);
            isSpinning = false;
            btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)';
            btn.disabled = false;
        }
    } catch(e) {
        alert('Lỗi kết nối.');
        isSpinning = false;
        btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)';
        btn.disabled = false;
    }
}
</script>

<!-- Bảng Giá Dịch Vụ Modal -->
<div id="serviceModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 99999; justify-content: center; align-items: center; backdrop-filter: blur(2px);">
    <div id="serviceModalInner" style="background: #f8fafc; width: 100%; max-width: 1050px; height: auto; max-height: 90vh; border-radius: 24px; display: flex; flex-direction: column; overflow: hidden; animation: slideUp 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.1); box-shadow: 0 30px 80px rgba(0,0,0,0.35);">
        <div style="padding: 18px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 10;">
            <div>
                <h3 style="margin: 0; color: #0f172a; font-size: 20px; font-weight: 800;">Bảng Giá Dịch Vụ</h3>
                <span style="font-size: 13px; color: #64748b;">Minh bạch - Rõ ràng - Nhanh chóng</span>
            </div>
            <button type="button" id="closeServiceModal" style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 18px; font-size: 20px; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div style="overflow-y: auto; padding: 20px; background: #f8fafc; flex: 1;">
            <?php
               $groupedServices = [];
               foreach ($services as $svc) {
                   $groupedServices[$svc['group']][] = $svc;
               }
               foreach ($groupedServices as $groupName => $groupItems):
            ?>
            <div style="margin-bottom: 25px;" class="service-group-container" data-group-name="<?= h($groupName) ?>">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <div style="height: 2px; flex: 1; background: #e2e8f0;"></div>
                    <div style="font-size: 12px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;"><?= h($groupName) ?></div>
                    <div style="height: 2px; flex: 1; background: #e2e8f0;"></div>
                </div>
                <div class="service-items-grid">
                    <?php foreach ($groupItems as $svc):
                        $base = (int)$svc['base'];
                        $publicPrice = $base;
                        $priceLabel = $publicPrice > 0 ? money_vnd($publicPrice) : 'Liên hệ';
                        $priceData = $publicPrice > 0 ? money_vnd($publicPrice) . ' (Đã gồm VAT)' : 'Liên hệ báo giá';
                    ?>
                    <div class="custom-service-item" data-name="<?= h($svc['name']) ?>" data-group="<?= h($svc['group']) ?>" data-base="<?= $base ?>" data-price="<?= $priceData ?>" style="background: #fff; border: 1px solid #cbd5e1; border-radius: 16px; padding: 16px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <span style="font-weight: 700; color: #1e293b; font-size: 15px; flex: 1; padding-right: 10px; line-height: 1.4;"><?= h($svc['name']) ?></span>
                        <span style="font-weight: 800; color: #dc2626; font-size: 14px; background: #fee2e2; padding: 6px 10px; border-radius: 8px; white-space: nowrap;"><?= $priceLabel ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</body>
</html>
