<?php

function migration_load_env($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim(str_replace("\xEF\xBB\xBF", '', $line));
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }

        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function migration_env($key, $default = '')
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return (string)$default;
    }
    return (string)$value;
}

migration_load_env(__DIR__ . '/../.env');

$host = migration_env('DB_HOST', 'localhost');
$db = migration_env('DB_NAME', '');
$user = migration_env('DB_USER', '');
$pass = migration_env('DB_PASS', '');
$charset = migration_env('DB_CHARSET', 'utf8mb4');

if ($db === '' || $user === '') {
    fwrite(STDERR, "Missing database credentials. Set DB_NAME and DB_USER in .env.\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = file_get_contents(__DIR__ . '/openclaw_logs.sql');
    $pdo->exec($sql);
    echo "Migration openclaw_logs.sql executed successfully.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
