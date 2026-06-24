<?php
/**
 * DTH Backup Script
 * Run via cron or browser with secret key:
 *   https://dienmayhieu.com/scripts/backup-dth.php?secret=YOUR_BACKUP_SECRET
 *
 * Creates:
 *   - Database dump to /backups/db/
 *   - File archive (zip) of public_html to /backups/files/
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/core.php';

$secret = clean_string($_GET['secret'] ?? '', 64);
$expected = app_env('BACKUP_SECRET', '');

if ($expected === '' || !hash_equals($expected, $secret)) {
    http_response_code(403);
    json_out(['status' => 'error', 'message' => 'Invalid backup secret.']);
}

$backupDir = '/home/kwkrbcce/backups';
$dbDir = $backupDir . '/db';
$fileDir = $backupDir . '/files';
$date = date('Y-m-d_H-i-s');

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}
if (!is_dir($fileDir)) {
    mkdir($fileDir, 0755, true);
}

try {
    $pdo = pdo();
    $dbName = app_env('DB_NAME', '');
    $dumpFile = $dbDir . '/' . $dbName . '_' . $date . '.sql';

    // Simple mysqldump via exec (hosting must allow)
    $cmd = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --default-character-set=utf8mb4 %s > %s 2>&1',
        escapeshellarg(app_env('DB_HOST', 'localhost')),
        escapeshellarg(app_env('DB_USER', '')),
        escapeshellarg(app_env('DB_PASS', '')),
        escapeshellarg($dbName),
        escapeshellarg($dumpFile)
    );
    exec($cmd, $output, $exitCode);

    $dbOk = $exitCode === 0 && file_exists($dumpFile) && filesize($dumpFile) > 0;

    // File backup via zip
    $zipFile = $fileDir . '/public_html_' . $date . '.zip';
    $sourceDir = '/home/kwkrbcce/public_html';
    $zipCmd = sprintf('cd %s && zip -r %s . -x "*.log" -x "backups/*"', escapeshellarg($sourceDir), escapeshellarg($zipFile));
    exec($zipCmd, $zipOutput, $zipExit);

    $fileOk = $zipExit === 0 && file_exists($zipFile) && filesize($zipFile) > 0;

    json_out([
        'status' => 'ok',
        'db_backup' => $dbOk ? basename($dumpFile) : null,
        'file_backup' => $fileOk ? basename($zipFile) : null,
        'db_size' => $dbOk ? filesize($dumpFile) : 0,
        'file_size' => $fileOk ? filesize($zipFile) : 0,
        'message' => 'Backup completed.',
    ]);
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
