<?php
require 'api_master.php';

try {
    $pdo->exec("ALTER TABLE marketplace_stores MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");
    
    // Add columns to marketplace_stores if not exist
    try { $pdo->exec("ALTER TABLE marketplace_stores ADD COLUMN rating_score DECIMAL(3,1) NOT NULL DEFAULT 5.0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE marketplace_stores ADD COLUMN rating_count INT NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE marketplace_stores ADD COLUMN report_token VARCHAR(64) NULL"); } catch (Exception $e) {}

    // Add columns to worker_profiles if not exist
    try { $pdo->exec("ALTER TABLE worker_profiles ADD COLUMN rating_score DECIMAL(3,1) NOT NULL DEFAULT 5.0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE worker_profiles ADD COLUMN rating_count INT NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    
    echo "DB Schema Altered Successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
