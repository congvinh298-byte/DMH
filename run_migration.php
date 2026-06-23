<?php
define('DTH_API_LIBRARY_ONLY', true);
require 'api_master.php';
try {
    $pdo = pdo();
    $sql = file_get_contents(__DIR__ . '/database_migration.sql');
    $pdo->exec($sql);
    echo 'Migration executed successfully.';
} catch (Exception $e) {
    echo 'Migration error: ' . $e->getMessage();
}
