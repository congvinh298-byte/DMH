<?php
define('DTH_API_LIBRARY_ONLY', true);
require 'api_master.php';
try {
    $pdo = pdo();
    $pdo->exec("ALTER TABLE orders ADD COLUMN viettel_invoice_exported TINYINT(1) NOT NULL DEFAULT 0");
    $pdo->exec("ALTER TABLE orders ADD COLUMN viettel_invoice_no VARCHAR(50) NULL");
    echo 'DB altered successfully.';
} catch (Exception $e) {
    echo 'DB error: ' . $e->getMessage();
}
