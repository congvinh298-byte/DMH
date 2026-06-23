<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$allowed = ['cron_worker_fee_notice', 'cron_worker_fee_lock', 'cron_baocao_ngay'];
$action = (string)($argv[1] ?? '');
if (!in_array($action, $allowed, true)) {
    fwrite(STDERR, "Usage: php run_scheduled_action.php <cron_worker_fee_notice|cron_worker_fee_lock|cron_baocao_ngay>\n");
    exit(1);
}

$secret = '';
$envPath = dirname(__DIR__) . '/.env';
foreach (is_file($envPath) ? (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
    $line = trim(str_replace("\xEF\xBB\xBF", '', $line));
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    if ($key === 'CRON_SECRET') {
        $secret = trim($value, "\"'");
        break;
    }
}

$_GET['action'] = $action;
$_GET['secret'] = $secret;
require dirname(__DIR__) . '/api_master.php';
