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
 * 
 * Chay:
 *   php scripts/seed-bct-test-account.php
 * 
 * HOAC qua web (an toan hon):
 *   Truy cap https://dienmayhieu.com/api/users.php?action=admin_create_test
 *   voi CRON_SECRET lam xac thuc.
 * 
 * Updated: 2026-06-24
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/core.php';

$emails = [
    'qltmdt@moit.gov.vn',
    'qlhdtmdt@gmail.com',
];

$pdo = db();
$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_BCRYPT);

foreach ($emails as $email) {
    $stmt = $pdo->prepare('SELECT id, role, fullname FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Cap nhat password + flag
        $stmt = $pdo->prepare(
            'UPDATE users SET password_hash = ?, role = "admin", is_test_account = 1, status = "active" WHERE id = ?'
        );
        $stmt->execute([$hash, $existing['id']]);
        echo "[UPDATE] {$email} (id={$existing['id']}, role=admin, pass=Admin@123)\n";
    } else {
        // Tao moi
        $stmt = $pdo->prepare(
            'INSERT INTO users (role, fullname, email, phone, password_hash, is_test_account, status, created_at)
             VALUES ("admin", "BCT Test Account", ?, "0123456789", ?, 1, "active", NOW())'
        );
        $stmt->execute([$email, $hash]);
        echo "[CREATE] {$email} (role=admin, pass=Admin@123)\n";
    }
}

echo "\n=== HOAN TAT ===\n";
echo "BCT co the dang nhap tai: https://dienmayhieu.com/admin/login.php\n";
echo "Email:    qltmdt@moit.gov.vn  HOAC  qlhdtmdt@gmail.com\n";
echo "Password: Admin@123\n";
