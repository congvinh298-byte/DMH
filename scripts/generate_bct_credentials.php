<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$username = trim((string)($argv[1] ?? 'qltmdt@moit.gov.vn'));
$password = (string)($argv[2] ?? rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='));
$apiKey = 'bct_' . bin2hex(random_bytes(24));

if ($username === '' || strlen($password) < 16) {
    fwrite(STDERR, "Username is required and password must contain at least 16 characters.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$apiKeyHash = hash('sha256', $apiKey);

echo "BCT credentials generated. Plain credentials are shown once; do not store them in source code.\n\n";
echo "Username: {$username}\n";
echo "Password: {$password}\n";
echo "API key:  {$apiKey}\n\n";
echo "Add these hashed values to .env:\n";
echo "BCT_REPORT_USER={$username}\n";
echo "BCT_REPORT_PASS_HASH={$passwordHash}\n";
echo "BCT_REPORT_API_KEY_HASH={$apiKeyHash}\n";
