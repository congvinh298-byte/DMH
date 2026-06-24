\u003c?php\n// Seed BCT test account - v2
declare(strict_types=1);

require_once __DIR__ . '/api/core.php';

$secret = $_GET['secret'] ?? '';
$expected = app_env('CRON_SECRET', '');

if ($secret !== $expected || $expected === '') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$emails = ['qltmdt@moit.gov.vn', 'qlhdtmdt@gmail.com'];
try {
    $pdo = pdo();
    $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
    $results = [];
    foreach ($emails as $email) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $pdo->prepare('UPDATE users SET password_hash=?, role="admin", is_test_account=1, status="active" WHERE email=?')->execute([$hash, $email]);
            $results[] = "[UPDATE] $email (role=admin, pass=Admin@123)";
        } else {
            $pdo->prepare('INSERT INTO users (role,fullname,email,phone,password_hash,is_test_account,status,created_at) VALUES ("admin","BCT Test Account",?,"0123456789",?,1,"active",NOW())')->execute([$email, $hash]);
            $results[] = "[CREATE] $email (role=admin, pass=Admin@123)";
        }
    }
    echo json_encode(['status' => 'success', 'messages' => $results], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
