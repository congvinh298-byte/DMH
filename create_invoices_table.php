<?php
define('DTH_API_LIBRARY_ONLY', true);
require 'api_master.php';
try {
    $pdo = pdo();
    $sql = "
    CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_code VARCHAR(100) UNIQUE NOT NULL,
        order_id INT NULL,
        customer_id INT NULL,
        customer_name VARCHAR(150) NULL,
        customer_phone VARCHAR(20) NULL,
        customer_tax_code VARCHAR(50) NULL,
        customer_address VARCHAR(1000) NULL,
        product_name VARCHAR(255) NULL,
        quantity INT DEFAULT 1,
        unit_gross_amount INT DEFAULT 0,
        gross_before_discount INT DEFAULT 0,
        discount_amount INT DEFAULT 0,
        promo_code VARCHAR(50) NULL,
        gift_name VARCHAR(500) NULL,
        warranty_years INT DEFAULT 0,
        warranty_expires_at DATE NULL,
        invoice_date DATE NULL,
        subtotal_amount INT DEFAULT 0,
        vat_amount INT DEFAULT 0,
        vat_rate INT DEFAULT 10,
        adjustment_amount INT DEFAULT 0,
        total_amount INT DEFAULT 0,
        total_price INT DEFAULT 0,
        payment_method VARCHAR(50) NULL,
        note TEXT NULL,
        status VARCHAR(50) DEFAULT 'issued',
        created_by VARCHAR(100) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sql);
    echo 'Invoices table created successfully.';
} catch (Exception $e) {
    echo 'DB error: ' . $e->getMessage();
}
