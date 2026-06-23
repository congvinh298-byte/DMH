CREATE TABLE IF NOT EXISTS openclaw_chat_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL COMMENT 'Mã phiên chat của user',
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    actions_provided JSON NULL COMMENT 'Các nút hành động AI trả về',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
