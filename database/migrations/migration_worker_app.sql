-- =========================================================================================
-- MIGRATION: WORKER APP - HỆ SINH THÁI KHÉP KÍN
-- Version: v1.0 | Date: 2026-07-09
-- Mục tiêu: Thoát phụ thuộc Telegram, xây dựng hạ tầng nội bộ
-- Chạy: php database/run_migration.php migration_worker_app.sql
-- =========================================================================================

-- ---------------------------------------------------------------------------
-- 1. CẬP NHẬT BẢNG worker_profiles
--    Thêm: phone, address, skill_tags, shift_status, location, worker_code
-- ---------------------------------------------------------------------------

ALTER TABLE worker_profiles
    ADD COLUMN IF NOT EXISTS phone              VARCHAR(20)     NULL COMMENT 'Số điện thoại đăng nhập App',
    ADD COLUMN IF NOT EXISTS address            VARCHAR(500)    NULL COMMENT 'Địa chỉ thường trú',
    ADD COLUMN IF NOT EXISTS skill_tags         JSON            NULL COMMENT 'Mảng kỹ năng: ["dien_lanh","giat_nem"]',
    ADD COLUMN IF NOT EXISTS shift_status       ENUM('off','on_shift','busy') NOT NULL DEFAULT 'off' COMMENT 'Trạng thái ca làm việc',
    ADD COLUMN IF NOT EXISTS current_lat        DECIMAL(10,7)   NULL COMMENT 'Vĩ độ GPS hiện tại',
    ADD COLUMN IF NOT EXISTS current_lng        DECIMAL(10,7)   NULL COMMENT 'Kinh độ GPS hiện tại',
    ADD COLUMN IF NOT EXISTS last_location_at   DATETIME        NULL COMMENT 'Lần cuối cập nhật GPS',
    ADD COLUMN IF NOT EXISTS current_shift_id   INT UNSIGNED    NULL COMMENT 'ID ca đang làm (FK worker_shifts)',
    ADD COLUMN IF NOT EXISTS worker_code        VARCHAR(20)     NULL UNIQUE COMMENT 'Mã thợ nội bộ: DTH-001',
    ADD COLUMN IF NOT EXISTS pin_reset_token    VARCHAR(64)     NULL COMMENT 'Token reset PIN (OTP-based)',
    ADD COLUMN IF NOT EXISTS pin_reset_expires  DATETIME        NULL;

-- Index nhanh cho tìm thợ đang sẵn sàng theo ca
ALTER TABLE worker_profiles
    ADD INDEX IF NOT EXISTS idx_wp_shift_status (shift_status),
    ADD INDEX IF NOT EXISTS idx_wp_phone (phone),
    ADD INDEX IF NOT EXISTS idx_wp_location (current_lat, current_lng);

-- ---------------------------------------------------------------------------
-- 2. TẠO BẢNG worker_shifts — Ca làm việc
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_shifts (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    worker_id       BIGINT          NOT NULL COMMENT 'telegram_user_id của thợ',
    started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at        DATETIME        NULL,
    start_lat       DECIMAL(10,7)   NULL COMMENT 'GPS lúc bắt đầu ca',
    start_lng       DECIMAL(10,7)   NULL,
    end_lat         DECIMAL(10,7)   NULL COMMENT 'GPS lúc kết thúc ca',
    end_lng         DECIMAL(10,7)   NULL,
    jobs_received   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Số đơn nhận trong ca',
    jobs_completed  INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Số đơn hoàn thành trong ca',
    total_earned    INT             NOT NULL DEFAULT 0 COMMENT 'Thu nhập trong ca (VNĐ)',
    status          ENUM('active','ended') NOT NULL DEFAULT 'active',
    note            TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ws_worker (worker_id),
    INDEX idx_ws_status (status),
    INDEX idx_ws_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Lịch sử ca làm việc của thợ';

-- ---------------------------------------------------------------------------
-- 3. TẠO BẢNG in_app_notifications — Thông báo nội bộ
--    Thay thế cho DM Telegram với thợ và khách
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS in_app_notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type     ENUM('worker','customer','admin') NOT NULL COMMENT 'Đối tượng nhận',
    target_id       BIGINT          NOT NULL COMMENT 'worker telegram_user_id hoặc user.id',
    title           VARCHAR(255)    NOT NULL,
    body            TEXT            NOT NULL,
    type            VARCHAR(50)     NOT NULL DEFAULT 'info'
                        COMMENT 'new_job|job_assigned|job_completed|job_cancelled|platform_fee|shift_reminder|admin_msg',
    reference_type  VARCHAR(50)     NULL COMMENT 'job|order|payment|...',
    reference_id    BIGINT          NULL COMMENT 'ID của đối tượng liên quan',
    payload         JSON            NULL COMMENT 'Data thêm cho App xử lý deeplink',
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    read_at         DATETIME        NULL,
    sent_via_push   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Đã gửi Expo Push chưa',
    push_sent_at    DATETIME        NULL,
    push_error      TEXT            NULL COMMENT 'Lỗi push nếu có',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ian_target (target_type, target_id),
    INDEX idx_ian_unread (target_type, target_id, is_read),
    INDEX idx_ian_type (type),
    INDEX idx_ian_ref (reference_type, reference_id),
    INDEX idx_ian_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Hệ thống thông báo nội bộ thay thế Telegram DM';

-- ---------------------------------------------------------------------------
-- 4. TẠO BẢNG push_dispatch_log — Log phân phối đơn hàng
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS push_dispatch_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id          INT UNSIGNED    NOT NULL,
    worker_id       BIGINT          NOT NULL COMMENT 'Thợ được push',
    distance_km     DECIMAL(8,3)    NULL COMMENT 'Khoảng cách GPS lúc dispatch (km)',
    shift_status    VARCHAR(20)     NULL COMMENT 'Trạng thái ca của thợ lúc nhận push',
    push_token      VARCHAR(255)    NULL COMMENT 'Expo push token',
    push_status     ENUM('sent','failed','no_token','skip_busy','skip_radius') NOT NULL DEFAULT 'sent',
    push_response   TEXT            NULL,
    dispatched_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pdl_job (job_id),
    INDEX idx_pdl_worker (worker_id),
    INDEX idx_pdl_dispatched (dispatched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log phân phối đơn hàng theo GPS đến từng thợ';

-- ---------------------------------------------------------------------------
-- 5. THÊM INDEX GPS VÀO job_posts (nếu chưa có)
-- ---------------------------------------------------------------------------
ALTER TABLE job_posts
    ADD COLUMN IF NOT EXISTS app_worker_id BIGINT NULL
        COMMENT 'Worker ID nội bộ (dùng khi thợ login bằng phone, không phải Telegram)',
    ADD INDEX IF NOT EXISTS idx_jp_map_lat (map_lat),
    ADD INDEX IF NOT EXISTS idx_jp_map_lng (map_lng),
    ADD INDEX IF NOT EXISTS idx_jp_app_worker (app_worker_id);

-- ---------------------------------------------------------------------------
-- 6. THÊM ENUM TRẠNG THÁI MỚI VÀO job_posts.status (nếu chưa có)
--    Thêm: 'dispatching' - đang tìm thợ qua nội bộ
-- ---------------------------------------------------------------------------
-- Không ALTER ENUM trực tiếp vì không idempotent cross-version;
-- trạng thái 'dispatching' dùng value = 'matching' (đã tồn tại) hoặc
-- xử lý ở tầng application.

-- ---------------------------------------------------------------------------
-- 7. MOBILE SESSIONS: thêm worker_app_user_id để tách biệt khỏi telegram_user_id
-- ---------------------------------------------------------------------------
ALTER TABLE mobile_sessions
    ADD COLUMN IF NOT EXISTS worker_phone VARCHAR(20) NULL
        COMMENT 'SĐT đăng nhập của thợ (không phải Telegram ID)',
    ADD INDEX IF NOT EXISTS idx_ms_worker_phone (worker_phone);

-- ---------------------------------------------------------------------------
-- 8. SEQUENCE: Tạo worker_code tự động cho thợ mới
--    Format: DTH-001, DTH-002, ...
-- ---------------------------------------------------------------------------
-- Xử lý ở tầng application PHP (không dùng SEQUENCE vì MySQL < 8)

-- =========================================================================================
-- XONG MIGRATION v1.0
-- =========================================================================================
SELECT 'Migration worker_app v1.0 completed successfully' AS result;
