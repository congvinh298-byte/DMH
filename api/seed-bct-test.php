<?php
declare(strict_types=1);

require_once __DIR__ . '/core.php';

$secret = $_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
$expected = app_env('CRON_SECRET', '');

if ($secret !== $expected || $expected === '') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$emails = [
    'qltmdt@moit.gov.vn',
    'qlhdtmdt@gmail.com',
];

try {
    $pdo = pdo();
    $password = 'Admin@123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $results = [];

    foreach ($emails as $email) {
        $stmt = $pdo->prepare('SELECT id, role, fullname FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE users SET password_hash = ?, role = "admin", is_test_account = 1, status = "active" WHERE id = ?'
            );
            $stmt->execute([$hash, $existing['id']]);
            $results[] = "[UPDATE] {$email} (id={$existing['id']}, role=admin, pass=Admin@123)";
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (role, fullname, email, phone, password_hash, is_test_account, status, created_at)
                 VALUES ("admin", "BCT Test Account", ?, "0123456789", ?, 1, "active", NOW())'
            );
            $stmt->execute([$email, $hash]);
            $results[] = "[CREATE] {$email} (role=admin, pass=Admin@123)";
        }
    }

    echo json_encode(['status' => 'success', 'messages' => $results], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
