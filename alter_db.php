<?php
define('DTH_API_LIBRARY_ONLY', true);
require 'api_master.php';
try {
    $pdo = pdo();
    $pdo->exec('ALTER TABLE job_posts ADD COLUMN review_score INT NULL');
    echo 'DB altered successfully.';
} catch (Exception $e) {
    echo 'DB error: ' . $e->getMessage();
}
