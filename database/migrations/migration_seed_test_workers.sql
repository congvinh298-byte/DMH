-- =========================================================================================
-- MIGRATION SEED: TÀI KHOẢN THỢ TEST - HỆ SINH THÁI KHÉP KÍN
-- Version: v1.0 | Date: 2026-07-09
-- Mục tiêu: Seed tài khoản thợ test với SĐT + PIN để login ngay không cần Telegram
--
-- CÁCH CHẠY:
--   Trực tiếp trên cPanel phpMyAdmin, hoặc qua API:
--   POST /api_master.php
--   {"action":"mobile_worker_init_account","admin_key":"Anhthienvodich","phone":"0979553289","name":"Vinh Tran (Admin)","pin":"123456"}
--
-- LOGIN SAU KHI SEED:
--   POST /api_master.php
--   {"action":"mobile_worker_login_by_phone","phone":"0979553289","pin":"123456"}
-- =========================================================================================

-- ---------------------------------------------------------------------------
-- 1. Thêm cột phone nếu chưa có (an toàn nếu migration_worker_app.sql chưa chạy)
-- ---------------------------------------------------------------------------
ALTER TABLE worker_profiles
    ADD COLUMN IF NOT EXISTS phone           VARCHAR(20)     NULL COMMENT 'Số điện thoại đăng nhập App',
    ADD COLUMN IF NOT EXISTS worker_code     VARCHAR(20)     NULL UNIQUE COMMENT 'Mã thợ nội bộ: DTH-001',
    ADD COLUMN IF NOT EXISTS shift_status    ENUM('off','on_shift','busy') NOT NULL DEFAULT 'off',
    ADD COLUMN IF NOT EXISTS pin_hash        VARCHAR(255)    NULL COMMENT 'Bcrypt hash của PIN App',
    ADD COLUMN IF NOT EXISTS pin_reset_token VARCHAR(64)     NULL,
    ADD COLUMN IF NOT EXISTS pin_reset_expires DATETIME      NULL;

ALTER TABLE worker_profiles
    ADD INDEX IF NOT EXISTS idx_wp_phone (phone);

-- ---------------------------------------------------------------------------
-- 2. Seed tài khoản test: Vinh Tran (Admin worker - dùng SĐT 0979553289)
--    PIN mặc định: 123456
--    PIN hash được tạo bằng: password_hash('123456', PASSWORD_BCRYPT)
-- ---------------------------------------------------------------------------
-- NOTE: Hash dưới đây là bcrypt của '123456' — dùng để test ngay
-- Khi deploy production, admin gọi mobile_worker_reset_pin để đặt PIN thật
INSERT INTO worker_profiles
    (telegram_user_id, telegram_name, phone, identity_code, worker_type, role, is_admin, is_active,
     worker_code, pin_hash, shift_status, created_at, updated_at)
VALUES
    -- Tài khoản test chính: SĐT 0979553289, PIN 123456
    (648065292, 'Vinh Tran (Admin)', '0979553289', '0979553289', 'ho_kinh_doanh', 'admin', 1, 1,
     'DTH-ADM', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'off', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    phone       = COALESCE(NULLIF(phone,''), VALUES(phone)),
    is_active   = 1,
    worker_code = COALESCE(worker_code, VALUES(worker_code)),
    pin_hash    = COALESCE(NULLIF(pin_hash,''), VALUES(pin_hash)),
    updated_at  = NOW();

-- Tài khoản thợ test: SĐT 0979553289 map vào worker 8729878070 (INITIAL_WORKER_TELEGRAM_ID)
UPDATE worker_profiles SET
    phone       = '0979553289',
    worker_code = COALESCE(worker_code, 'DTH-001'),
    pin_hash    = COALESCE(NULLIF(pin_hash,''), '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    is_active   = 1,
    updated_at  = NOW()
WHERE telegram_user_id = 8729878070
  AND (phone IS NULL OR phone = '')
LIMIT 1;

-- ---------------------------------------------------------------------------
-- 3. Đảm bảo bảng mobile_sessions tồn tại (dùng cho token)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mobile_sessions (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token         VARCHAR(200)    NOT NULL UNIQUE,
    type          ENUM('worker','customer') NOT NULL,
    user_id       BIGINT          NOT NULL,
    ip_address    VARCHAR(50)     NULL,
    user_agent    VARCHAR(300)    NULL,
    worker_phone  VARCHAR(20)     NULL,
    last_active_at DATETIME       NULL,
    expires_at    DATETIME        NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ms_token (token),
    INDEX idx_ms_user (type, user_id),
    INDEX idx_ms_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Session token cho Mobile App (Worker + Customer)';

ALTER TABLE mobile_sessions
    ADD COLUMN IF NOT EXISTS worker_phone VARCHAR(20) NULL,
    ADD INDEX IF NOT EXISTS idx_ms_worker_phone (worker_phone);

-- ---------------------------------------------------------------------------
-- 4. Bảng OTP codes
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mobile_otp_codes (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone      VARCHAR(20)     NOT NULL,
    otp        VARCHAR(10)     NOT NULL,
    purpose    VARCHAR(30)     NOT NULL DEFAULT 'login',
    is_used    TINYINT(1)      NOT NULL DEFAULT 0,
    used_at    DATETIME        NULL,
    expires_at DATETIME        NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_moc_phone (phone),
    INDEX idx_moc_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='OTP đăng nhập Mobile App';

-- =========================================================================================
-- XONG MIGRATION SEED v1.0
-- =========================================================================================
SELECT 'Migration seed_test_workers v1.0 completed' AS result;
SELECT
    telegram_user_id AS worker_id,
    telegram_name AS name,
    phone,
    worker_code,
    role,
    is_admin,
    is_active,
    CASE WHEN pin_hash IS NOT NULL AND pin_hash != '' THEN 'YES' ELSE 'NO' END AS has_pin
FROM worker_profiles
WHERE phone IS NOT NULL AND phone != ''
ORDER BY id;
