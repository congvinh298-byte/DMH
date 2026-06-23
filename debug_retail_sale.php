<?php
define('DTH_API_LIBRARY_ONLY', true);
require 'api_master.php';
try {
    $input = json_decode('{"product_name":"Camera Imou","customer_name":"TRAN CONG VINH","customer_phone":"0979553289","customer_tax_code":"","customer_address":"","gift_name":"The nho 64 Gb","note":"","payment_method":"cash","quantity":"1","unit_gross_amount":"1100000","warranty_years":"1"}', true);
    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $invoice = create_manual_sales_invoice($pdo, $input, true);
    file_put_contents(__DIR__ . '/uploads/debug_error.txt', 'Success: ' . json_encode($invoice));
    echo 'OK';
} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/uploads/debug_error.txt', 'Throwable: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo 'ERROR_CAUGHT';
}
