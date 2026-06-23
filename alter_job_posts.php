<?php
require_once __DIR__ . '/api/core.php';
$pdo = pdo();
$pdo->exec("ALTER TABLE job_posts ADD COLUMN bot_role VARCHAR(30) NULL");
$pdo->exec("ALTER TABLE job_posts ADD COLUMN target_group_id VARCHAR(50) NULL");
$pdo->exec("ALTER TABLE job_posts ADD COLUMN tg_message_id BIGINT NULL");
$pdo->exec("ALTER TABLE job_posts MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'new'");
echo "Done";
