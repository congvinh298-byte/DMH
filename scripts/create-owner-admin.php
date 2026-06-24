<?php
require_once __DIR__ . '/../api/core.php';
header('Content-Type: text/plain; charset=utf-8');

$email = 'anhthien';
$password = 'Anhthien369@';
$hash = password_hash($password, PASSWORD_BCRYPT);

$pdo = pdo();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

$values = [
    'role' => 'admin',
    'fullname' => 'Chu Website',
    'email' => $email,
    'phone' => '0979553289',
    'password_hash' => $hash,
];

if (column_exists($pdo, 'users', 'is_test_account')) {
    $values['is_test_account'] = 0;
}
if (column_exists($pdo, 'users', 'status')) {
    $values['status'] = 'active';
}

if ($existing) {
    update_compat($pdo, 'users', $values, 'id = ?', [$existing['id']]);
    echo "[UPDATE] $email (role=admin)\n";
} else {
    insert_compat($pdo, 'users', $values, ['created_at' => 'NOW()']);
    echo "[CREATE] $email (role=admin)\n";
}

echo "DONE\n";
?>
