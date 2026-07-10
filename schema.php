<?php
require_once 'api/core.php';
$pdo = pdo();
$stmt = $pdo->query("SHOW COLUMNS FROM workers");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
