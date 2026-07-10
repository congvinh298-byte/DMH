<?php
/**
 * Seed a test customer account for BCT demo / testing (CV0014).
 * Run once after deploying the database:
 *   php scripts/seed-bct-test-customer.php
 */

require_once __DIR__ . '/../api/core.php';

$testPhone = '0900000001';
$testName  = 'Khách hàng thử nghiệm BCT';

$pdo = pdo();

// Check if test customer already exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
$stmt->execute([$testPhone]);
$existing = $stmt->fetchColumn();

if ($existing) {
    echo "Test customer already exists (ID: {$existing}).\n";
    exit(0);
}

$loginKey = customer_generate_login_key($pdo);
$stmt = $pdo->prepare('INSERT INTO users (role, fullname, phone, login_key, is_active, loyalty_points, created_at) VALUES (?, ?, ?, ?, 1, 0, NOW())');
$stmt->execute(['buyer', $testName, $testPhone, $loginKey]);
$userId = $pdo->lastInsertId();

echo "Created BCT test customer:\n";
echo "  ID:        {$userId}\n";
echo "  Full name: {$testName}\n";
echo "  Phone:     {$testPhone}\n";
echo "  Login key: {$loginKey}\n";
