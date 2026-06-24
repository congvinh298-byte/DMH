<?php
/**
 * Script seed tai khoan test cho Bo Cong Thuong.
 *
 * Tao user:
 *   - Email:    qltmdt@moit.gov.vn  (tai khoan BCT se login)
 *   - Hoac:     qlhdtmdt@gmail.com (theo checklist)
 *   - Pass:     Admin@123
 *   - Role:     admin
 *   - Flag:     is_test_account = 1
 */

declare(strict_types=1);

$corePath = __DIR__ . '/api/core.php';
if (!is_file($corePath)) {
    $corePath = __DIR__ . '/../api/core.php';
}
require_once $corePath;

$emails = [
    'qltmdt@moit.gov.vn' => '0123456789',
    'qlhdtmdt@gmail.com' => '0123456788',
];

$pdo = pdo();
$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_BCRYPT);

foreach ($emails as $email => $phone) {
    $stmt = $pdo->prepare('SELECT id, role, fullname FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $values = [
            'password_hash' => $hash,
            'role' => 'admin',
        ];
        if (column_exists($pdo, 'users', 'is_test_account')) {
            $values['is_test_account'] = 1;
        }
        if (column_exists($pdo, 'users', 'status')) {
            $values['status'] = 'active';
        }
        update_compat($pdo, 'users', $values, 'id = ?', [$existing['id']]);
        echo "[UPDATE] {$email} (id={$existing['id']}, role=admin, pass=Admin@123)\n";
    } else {
        $values = [
            'role' => 'admin',
            'fullname' => 'BCT Test Account',
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $hash,
        ];
        if (column_exists($pdo, 'users', 'is_test_account')) {
            $values['is_test_account'] = 1;
        }
        if (column_exists($pdo, 'users', 'status')) {
            $values['status'] = 'active';
        }
        insert_compat($pdo, 'users', $values, ['created_at' => 'NOW()']);
        echo "[CREATE] {$email} (role=admin, pass=Admin@123)\n";
    }
}

echo "\n=== HOAN TAT ===\n";
echo "BCT co the dang nhap tai: https://dienmayhieu.com/admin/login.php\n";
echo "Email:    qltmdt@moit.gov.vn  HOAC  qlhdtmdt@gmail.com\n";
echo "Password: Admin@123\n";
