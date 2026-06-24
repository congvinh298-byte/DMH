<?php
declare(strict_types=1);

define('DTH_API_LIBRARY_ONLY', true);
require __DIR__ . '/api_master.php';

function bct_endpoint_json(array $payload, int $status = 200, bool $headOnly = false)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    if (!$headOnly) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

function bct_endpoint_prefers_html(): bool
{
    if (strtolower((string)($_GET['format'] ?? '')) === 'json') {
        return false;
    }
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return strpos($accept, 'text/html') !== false;
}

function bct_endpoint_login_csrf(): string
{
    app_ensure_session();
    if (empty($_SESSION['bct_report_login_csrf'])) {
        $_SESSION['bct_report_login_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['bct_report_login_csrf'];
}

function bct_endpoint_clear_session()
{
    app_ensure_session();
    unset(
        $_SESSION['bct_report_logged_in'],
        $_SESSION['bct_report_user'],
        $_SESSION['bct_report_auth_mode'],
        $_SESSION['bct_report_last_seen']
    );
}

function bct_endpoint_session_credentials(): array
{
    app_ensure_session();
    $configuredUser = app_env('BCT_REPORT_USER', '');
    $sessionUser = (string)($_SESSION['bct_report_user'] ?? '');
    $lastSeen = (int)($_SESSION['bct_report_last_seen'] ?? 0);
    $ttl = max(300, (int)app_env('BCT_REPORT_SESSION_TTL', '3600'));
    if (
        empty($_SESSION['bct_report_logged_in'])
        || $configuredUser === ''
        || !hash_equals($configuredUser, $sessionUser)
        || $lastSeen <= 0
        || time() - $lastSeen > $ttl
    ) {
        bct_endpoint_clear_session();
        return ['', ''];
    }
    $_SESSION['bct_report_last_seen'] = time();
    return [$sessionUser, (string)($_SESSION['bct_report_auth_mode'] ?? 'session')];
}

function bct_endpoint_login_page(string $error = '', int $status = 200)
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    $csrf = bct_endpoint_login_csrf();
    $errorHtml = $error !== ''
        ? '<div class="error">' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'
        : '';
    echo '<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dang nhap bao cao Bo Cong Thuong</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f4f6;color:#111827;font-family:"Times New Roman", Times, serif;padding:20px}
        main{width:min(440px,100%);background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:24px;box-shadow:0 12px 30px rgba(17,24,39,.08)}
        h1{font-size:21px;margin:0 0 8px}
        p{margin:0 0 18px;color:#4b5563;line-height:1.5}
        label{display:block;font-size:13px;font-weight:700;margin:14px 0 6px}
        input{width:100%;padding:11px 12px;border:1px solid #9ca3af;border-radius:6px;font:inherit}
        button{width:100%;margin-top:18px;padding:11px 14px;border:1px solid #b91c1c;border-radius:6px;background:#dc2626;color:#fff;font:inherit;font-weight:700;cursor:pointer}
        .error{padding:10px 12px;border:1px solid #fecaca;border-radius:6px;background:#fef2f2;color:#991b1b;margin-bottom:14px;line-height:1.4}
        .note{font-size:12px;margin-top:14px;margin-bottom:0}
    </style>
</head>
<body>
<main>
    <h1>Bao cao Bo Cong Thuong</h1>
    <p>Dang nhap de xem bao cao doi soat cua Dien Tu Hieu.</p>'
    . $errorHtml .
    '<form method="post" autocomplete="off">
        <input type="hidden" name="bct_browser_login" value="1">
        <input type="hidden" name="csrf" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">
        <label for="username">Tai khoan</label>
        <input id="username" name="username" type="text" autocomplete="username" required autofocus>
        <label for="password">Mat khau thuong hoac API key</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Dang nhap</button>
    </form>
    <p class="note">Mat khau thuong duoc doi chieu voi chuoi ma hoa luu trong cau hinh; he thong khong luu mat khau vua nhap.</p>
</main>
</body>
</html>';
    exit;
}

function bct_endpoint_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bct_endpoint_money($value): string
{
    return number_format((int)$value, 0, ',', '.') . ' VND';
}

function bct_endpoint_report_page(array $report)
{
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $period = (array)($report['period'] ?? []);
    $company = (array)($report['company'] ?? []);
    $status = (array)($report['submission_status'] ?? []);
    $registers = (array)($report['invoice_registers'] ?? []);
    $input = (array)($registers['input_purchase_invoices'] ?? []);
    $output = (array)($registers['output_sales_invoices'] ?? []);
    $operations = (array)($report['operational_records'] ?? []);
    $orders = (array)($operations['confirmed_product_orders'] ?? []);
    $jobs = (array)($operations['completed_service_jobs'] ?? []);
    $fees = (array)($operations['platform_fee_accrual'] ?? []);
    $reconciliation = (array)($report['reconciliation'] ?? []);
    $issues = (array)($report['issues'] ?? []);
    $integrity = (array)($report['integrity'] ?? []);
    $ready = !empty($status['ready_for_submission']);
    $statusClass = $ready ? 'ok' : 'block';
    $statusText = $ready ? 'San sang doi soat' : 'Can xu ly truoc khi gui';
    $from = bct_endpoint_h($period['from'] ?? '');
    $to = bct_endpoint_h($period['to'] ?? '');
    $jsonUrl = '?format=json&from=' . rawurlencode((string)($period['from'] ?? ''))
        . '&to=' . rawurlencode((string)($period['to'] ?? ''));

    $cards = [
        ['Hoa don dau vao', (int)($input['document_count'] ?? 0), bct_endpoint_money($input['total_amount'] ?? 0)],
        ['Hoa don dau ra', (int)($output['document_count'] ?? 0), bct_endpoint_money($output['total_amount'] ?? 0)],
        ['Don hang xac nhan', (int)($orders['count'] ?? 0), bct_endpoint_money($orders['total_amount'] ?? 0)],
        ['Ca dich vu hoan thanh', (int)($jobs['count'] ?? 0), bct_endpoint_money($jobs['customer_total'] ?? 0)],
        ['Phi nen tang phat sinh', (int)($fees['count'] ?? 0), bct_endpoint_money($fees['total_amount'] ?? 0)],
    ];
    $cardHtml = '';
    foreach ($cards as $card) {
        $cardHtml .= '<article><span>' . bct_endpoint_h($card[0]) . '</span><strong>'
            . bct_endpoint_h($card[1]) . '</strong><small>' . bct_endpoint_h($card[2]) . '</small></article>';
    }

    $issueHtml = '';
    foreach ($issues as $issue) {
        $severity = (string)($issue['severity'] ?? 'warning');
        $ids = implode(', ', (array)($issue['ids'] ?? []));
        $issueHtml .= '<tr><td><span class="badge ' . ($severity === 'blocking' ? 'block' : 'warn') . '">'
            . bct_endpoint_h($severity) . '</span></td><td><code>' . bct_endpoint_h($issue['code'] ?? '')
            . '</code></td><td>' . bct_endpoint_h($issue['count'] ?? 0) . '</td><td>'
            . bct_endpoint_h($ids !== '' ? $ids : '-') . '</td></tr>';
    }
    if ($issueHtml === '') {
        $issueHtml = '<tr><td colspan="4">Khong co van de doi soat he thong trong ky.</td></tr>';
    }

    $reconcileHtml = '';
    foreach ($reconciliation as $label => $value) {
        $formatted = strpos((string)$label, 'amount') !== false || strpos((string)$label, 'value') !== false
            ? bct_endpoint_money($value)
            : (string)(int)$value;
        $reconcileHtml .= '<tr><td><code>' . bct_endpoint_h($label) . '</code></td><td>'
            . bct_endpoint_h($formatted) . '</td></tr>';
    }

    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Bao cao doi soat Bo Cong Thuong</title><style>'
        . '*{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#111827;font-family:"Times New Roman", Times, serif}header{background:#111827;color:#fff;padding:18px 24px}header div,main{width:min(1180px,100%);margin:auto}header div{display:flex;align-items:center;justify-content:space-between;gap:16px}header h1{font-size:20px;margin:0}a{color:#b91c1c}header a{color:#fff}.actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}main{padding:24px}section{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:18px;margin-bottom:18px}h2{font-size:17px;margin:0 0 14px}.company{line-height:1.6}.filters{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.filters label{font-size:12px;font-weight:700}.filters input,.filters button{display:block;margin-top:5px;padding:9px 10px;border:1px solid #9ca3af;border-radius:5px;font:inherit}.filters button{background:#dc2626;border-color:#b91c1c;color:#fff;font-weight:700;cursor:pointer}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.grid article{border:1px solid #d1d5db;border-radius:6px;padding:14px}.grid span,.grid small{display:block;color:#4b5563}.grid strong{display:block;font-size:24px;margin:8px 0}.badge{display:inline-block;border-radius:4px;padding:5px 8px;font-size:12px;font-weight:700}.badge.ok{background:#dcfce7;color:#166534}.badge.block{background:#fee2e2;color:#991b1b}.badge.warn{background:#fef3c7;color:#92400e}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left;vertical-align:top}th{background:#f9fafb}.table-wrap{overflow:auto}.meta{color:#4b5563;font-size:13px;line-height:1.6}.hash{overflow-wrap:anywhere}code{font-family:Consolas,monospace}@media(max-width:640px){header div{align-items:flex-start;flex-direction:column}main{padding:12px}section{padding:14px}}'
        . '</style></head><body><header><div><h1>Bao cao doi soat Bo Cong Thuong</h1><nav class="actions"><a href="' . bct_endpoint_h($jsonUrl) . '">Tai JSON</a><a href="?logout=1">Dang xuat</a></nav></div></header><main>'
        . '<section><div class="actions" style="justify-content:space-between"><div class="company"><strong>'
        . bct_endpoint_h($company['name'] ?? '') . '</strong><br>MST: ' . bct_endpoint_h($company['tax_code'] ?? '')
        . '<br>Website: ' . bct_endpoint_h($company['website'] ?? '') . '</div><span class="badge ' . $statusClass . '">'
        . $statusText . '</span></div></section>'
        . '<section><form class="filters" method="get"><label>Tu ngay<input type="date" name="from" value="' . $from
        . '" required></label><label>Den ngay<input type="date" name="to" value="' . $to
        . '" required></label><button type="submit">Xem bao cao</button></form></section>'
        . '<section><h2>So lieu tong hop</h2><div class="grid">' . $cardHtml . '</div></section>'
        . '<section><h2>Van de doi soat</h2><div class="table-wrap"><table><thead><tr><th>Muc do</th><th>Ma doi soat</th><th>So luong</th><th>ID lien quan</th></tr></thead><tbody>'
        . $issueHtml . '</tbody></table></div></section>'
        . '<section><h2>Chenh lech va doi chieu</h2><div class="table-wrap"><table><tbody>' . $reconcileHtml
        . '</tbody></table></div></section><section class="meta"><strong>Thoi diem tao:</strong> '
        . bct_endpoint_h($report['generated_at'] ?? '') . '<br><strong>SHA-256 bao cao:</strong> <span class="hash">'
        . bct_endpoint_h($integrity['report_without_integrity_sha256'] ?? '') . '</span></section></main></body></html>';
    exit;
}

function bct_endpoint_credentials(array $bodyInput = []): array
{
    $username = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
    $secret = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
    if ($username !== '' || $secret !== '') {
        return [$username, $secret];
    }

    $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }
    if (preg_match('/^Basic\s+(.+)$/i', trim($authorization), $match)) {
        $decoded = base64_decode($match[1], true);
        if (is_string($decoded) && strpos($decoded, ':') !== false) {
            return explode(':', $decoded, 2);
        }
    }

    $headerUser = (string)($_SERVER['HTTP_X_BCT_USER'] ?? '');
    $headerSecret = (string)($_SERVER['HTTP_X_BCT_SECRET'] ?? '');
    if ($headerUser !== '' || $headerSecret !== '') {
        return [$headerUser, $headerSecret];
    }

    return [
        (string)($bodyInput['username'] ?? $bodyInput['account'] ?? ''),
        (string)($bodyInput['password'] ?? $bodyInput['secret'] ?? $bodyInput['api_key'] ?? ''),
    ];
}

function bct_endpoint_auth_mode(string $username, string $secret): string
{
    $configuredUser = app_env('BCT_REPORT_USER', '');
    $passwordHash = app_env('BCT_REPORT_PASS_HASH', '');
    $apiKeyHash = strtolower(app_env('BCT_REPORT_API_KEY_HASH', ''));

    // Check configured BCT credentials
    if ($configuredUser !== '' && $username !== '' && $secret !== '') {
        if (hash_equals($configuredUser, $username)) {
            if ($passwordHash !== '' && password_verify($secret, $passwordHash)) {
                return 'password';
            }
            if ($apiKeyHash !== '' && hash_equals($apiKeyHash, hash('sha256', $secret))) {
                return 'api_key';
            }
        }
    }

    // Fallback: allow any admin user from users table
    if ($username !== '' && $secret !== '') {
        try {
            $pdo = pdo();
            $statusCondition = column_exists($pdo, 'users', 'status')
                ? " AND (status IS NULL OR status = '' OR status = 'active')"
                : " AND (is_active = 1 OR is_active IS NULL)";
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE email = ? AND role = 'admin'" . $statusCondition . " LIMIT 1");
            $stmt->execute([$username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['password_hash']) && password_verify($secret, (string)$row['password_hash'])) {
                return 'admin_password';
            }
        } catch (Throwable $e) {
            error_log('[bct_portal] users table auth failed: ' . $e->getMessage());
        }
    }

    if ($configuredUser === '' && $passwordHash === '' && $apiKeyHash === '') {
        return 'not_configured';
    }
    return '';
}

function bct_endpoint_start_session(string $username, string $authMode)
{
    app_ensure_session();
    session_regenerate_id(true);
    $_SESSION['bct_report_logged_in'] = true;
    $_SESSION['bct_report_user'] = $username;
    $_SESSION['bct_report_auth_mode'] = 'session_' . $authMode;
    $_SESSION['bct_report_last_seen'] = time();
}

function bct_endpoint_try_log(string $username, string $authMode, ?string $from, ?string $to, ?string $hash, bool $success)
{
    try {
        bct_log_report_access(pdo(), $username, $authMode, $from, $to, $hash, $success);
    } catch (Throwable $e) {
        error_log('[api_baocao_bct] access log failed: ' . $e->getMessage());
    }
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$headOnly = $method === 'HEAD';
if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
    header('Allow: GET, POST, HEAD');
    bct_endpoint_json(['status' => 'error', 'message' => 'Method not allowed.'], 405, $headOnly);
}

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host) ?: $host;
$localHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
if (!app_is_https() && !$localHost) {
    bct_endpoint_json(['status' => 'error', 'message' => 'HTTPS is required.'], 426, $headOnly);
}


$bodyInput = request_data();

$htmlRequest = bct_endpoint_prefers_html();
if ($htmlRequest && isset($_GET['logout'])) {
    bct_endpoint_clear_session();
    bct_endpoint_login_page();
}

$browserLogin = $htmlRequest && $method === 'POST' && (string)($bodyInput['bct_browser_login'] ?? '') === '1';
if ($browserLogin) {
    app_ensure_session();
    $expectedCsrf = (string)($_SESSION['bct_report_login_csrf'] ?? '');
    $actualCsrf = (string)($bodyInput['csrf'] ?? '');
    if ($expectedCsrf === '' || $actualCsrf === '' || !hash_equals($expectedCsrf, $actualCsrf)) {
        bct_endpoint_login_page('Phien dang nhap khong hop le. Vui long thu lai.', 403);
    }
}

list($username, $secret) = bct_endpoint_credentials($bodyInput);
$authMode = bct_endpoint_auth_mode($username, $secret);
if ($authMode === '' && !$browserLogin && $username === '' && $secret === '') {
    list($sessionUser, $sessionMode) = bct_endpoint_session_credentials();
    if ($sessionUser !== '') {
        $username = $sessionUser;
        $authMode = $sessionMode;
    }
}
if ($authMode === 'not_configured') {
    if ($htmlRequest) {
        bct_endpoint_login_page('Tai khoan bao cao chua duoc cau hinh tren may chu.', 503);
    }
    bct_endpoint_json(['status' => 'error', 'message' => 'BCT report credentials are not configured.'], 503, $headOnly);
}
if ($authMode === '') {
    bct_endpoint_try_log($username, 'rejected', null, null, null, false);
    if ($htmlRequest) {
        bct_endpoint_login_page($browserLogin ? 'Tai khoan hoac mat khau khong dung.' : '', $browserLogin ? 401 : 200);
    }
    header('WWW-Authenticate: Basic realm="Dien Tu Hieu BCT Report"');
    bct_endpoint_json(['status' => 'error', 'message' => 'Invalid report credentials.'], 401, $headOnly);
}
if ($browserLogin) {
    bct_endpoint_start_session($username, $authMode);
    bct_endpoint_try_log($username, 'browser_' . $authMode, null, null, null, true);
    header('Location: ' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/api_baocao_bct.php'), '?'), true, 303);
    exit;
}

try {
    $input = array_merge($_GET, $bodyInput);
    $from = clean_string($input['from'] ?? date('Y-01-01'), 10);
    $to = clean_string($input['to'] ?? date('Y-m-d'), 10);
    $includeDetails = (string)($input['detail'] ?? '1') !== '0';
    list($from, $to) = bct_validate_period($from, $to);
    $report = bct_reconciliation_report(pdo(), $from, $to, $includeDetails);
    $reportHash = hash('sha256', json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $report['integrity'] = [
        'algorithm' => 'SHA-256',
        'report_without_integrity_sha256' => $reportHash,
    ];
    bct_endpoint_try_log($username, $authMode, $from, $to, $reportHash, true);
    if ($htmlRequest) {
        bct_endpoint_report_page($report);
    }
    bct_endpoint_json(['status' => 'success', 'report' => $report], 200, $headOnly);
} catch (InvalidArgumentException $e) {
    bct_endpoint_try_log($username, $authMode, null, null, null, false);
    bct_endpoint_json(['status' => 'error', 'message' => $e->getMessage()], 400, $headOnly);
} catch (Throwable $e) {
    error_log('[api_baocao_bct] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    bct_endpoint_try_log($username, $authMode, null, null, null, false);
    bct_endpoint_json(['status' => 'error', 'message' => 'Report service unavailable.'], 500, $headOnly);
}
