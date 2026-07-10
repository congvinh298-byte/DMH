<?php
require_once __DIR__ . '/../api/core.php';

try {
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT id, email, fullname, role, status, is_active, password_hash FROM users WHERE email = ?");
    $stmt->execute(['qltmdt@moit.gov.vn']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo "FOUND\n";
        var_export($user);
    } else {
        echo "NOT_FOUND\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
