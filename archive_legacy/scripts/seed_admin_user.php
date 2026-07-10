<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/core.php';

$email = 'qltmdt@moit.gov.vn';
$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = pdo();

    $statusCol = column_exists($pdo, 'users', 'status') ? 'status' : null;
    $isActiveCol = column_exists($pdo, 'users', 'is_active') ? 'is_active' : null;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();

    $values = [
        'email' => $email,
        'fullname' => 'Quan ly TMĐT',
        'phone' => '0000000000',
        'role' => 'admin',
        'password_hash' => $hash,
    ];

    if ($isActiveCol) {
        $values['is_active'] = 1;
    }
    if ($statusCol) {
        $values['status'] = 'active';
    }

    if ($existing) {
        $sets = [];
        $params = [];
        foreach ($values as $col => $val) {
            $sets[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?";
        $params[] = $existing;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo "UPDATED admin user id={$existing}\n";
    } else {
        $columns = array_keys($values);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO users (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($values));
        echo "INSERTED admin user id=" . $pdo->lastInsertId() . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
