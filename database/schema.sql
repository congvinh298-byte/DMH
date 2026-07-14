-- =========================================================================================
-- SCHEMA DATABASE - HỆ THỐNG E-COMMERCE MARKETPLACE
-- Phiên bản: v3.0 (Clean Architecture)
-- =========================================================================================

-- =========================================================================================
-- BẢNG 1: users - NGƯỜI DÙNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('admin','seller','buyer','worker','ctv') NOT NULL DEFAULT 'buyer',
    fullname        VARCHAR(100)    NOT NULL,
    email           VARCHAR(100)    NULL UNIQUE,
    phone           VARCHAR(15)     NOT NULL,
    password_hash   VARCHAR(255)    NULL,
    avatar_url      VARCHAR(500)    NULL,
    wallet_balance  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_earned    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_spent     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    rating          DECIMAL(3,2)    NOT NULL DEFAULT 5.00,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    telegram_chat_id VARCHAR(50)    NULL,
    telegram_username VARCHAR(100)  NULL,
    metadata        JSON            NULL,
    last_login_at   DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_phone (phone),
    INDEX idx_users_telegram (telegram_chat_id),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 2: categories - DANH MỤC SẢN PHẨM
-- =========================================================================================
CREATE TABLE IF NOT EXISTS categories (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    parent_id       INT UNSIGNED    NULL,
    name            VARCHAR(100)    NOT NULL,
    slug            VARCHAR(150)    NOT NULL UNIQUE,
    description     TEXT            NULL,
    icon            VARCHAR(50)     NULL,
    sort_order      INT             NOT NULL DEFAULT 0,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (id, parent_id, name, slug, icon, sort_order) VALUES
    (1, NULL, 'SIM Số Đẹp',          'sim-so-dep',       '📱', 1),
    (2, NULL, 'Điện Máy',             'dien-may',         '💻', 2),
    (3, NULL, 'Điện Thoại & Phụ Kiện','dien-thoai',       '📲', 3),
    (4, NULL, 'Dịch Vụ Lao Động',     'dich-vu-lao-dong', '🛠', 4),
    (5, NULL, 'Vận Tải & Giao Hàng',  'van-tai',          '🚚', 5),
    (6, NULL, 'Tư Vấn & Bảo Hiểm',    'tu-van-bao-hiem',  '📋', 6),
    (7, NULL, 'Đồ Cũ & Thanh Lý',     'do-cu-thanh-ly',   '📦', 7),
    (8, NULL, 'Việc Làm & Tuyển Dụng','viec-lam-tuyen-dung','👔', 8);

-- =========================================================================================
-- BẢNG 3: marketplace_products - SẢN PHẨM
-- =========================================================================================
CREATE TABLE IF NOT EXISTS marketplace_products (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    seller_id       INT UNSIGNED    NULL,
    category_id     INT UNSIGNED    NULL,
    type            ENUM('sim','dien_may','dien_thoai','phu_kien','dich_vu','van_tai','do_cu','other') NOT NULL DEFAULT 'other',
    name            VARCHAR(255)    NOT NULL,
    slug            VARCHAR(255)    NULL UNIQUE,
    description     TEXT            NULL,
    price           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    sale_price      DECIMAL(15,2)   NULL,
    stock           INT             NOT NULL DEFAULT 0,
    sold_count      INT             NOT NULL DEFAULT 0,
    images          JSON            NULL,
    attributes      JSON            NULL,
    status          ENUM('draft','active','sold','hidden','disabled') NOT NULL DEFAULT 'draft',
    is_featured     TINYINT(1)      NOT NULL DEFAULT 0,
    view_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_mp_type (type),
    INDEX idx_mp_status (status),
    INDEX idx_mp_price (price),
    INDEX idx_mp_seller (seller_id),
    INDEX idx_mp_category (category_id),
    FULLTEXT INDEX idx_mp_search (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 4: sims - SIM SỐ ĐẸP (table riêng vì có nhiều field đặc thù)
-- =========================================================================================
CREATE TABLE IF NOT EXISTS sims (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    phone_number    VARCHAR(20)     NOT NULL UNIQUE,
    network         VARCHAR(50)     NOT NULL COMMENT 'Viettel, Mobi, Vina...',
    sim_type        VARCHAR(100)    NOT NULL COMMENT 'Tứ quý, Lục quý, Taxi...',
    price           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    original_price  DECIMAL(15,2)   NULL,
    stock           INT             NOT NULL DEFAULT 1,
    status          ENUM('available','sold','reserved','hidden') NOT NULL DEFAULT 'available',
    images          JSON            NULL,
    description     TEXT            NULL,
    is_featured     TINYINT(1)      NOT NULL DEFAULT 0,
    view_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    sold_at         DATETIME        NULL,
    buyer_id        INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sim_network (network),
    INDEX idx_sim_type (sim_type),
    INDEX idx_sim_price (price),
    INDEX idx_sim_status (status),
    FULLTEXT INDEX idx_sim_search (phone_number, network, sim_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 5: orders - ĐƠN HÀNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    order_code      VARCHAR(30)     NOT NULL UNIQUE,
    buyer_id        INT UNSIGNED    NULL,
    seller_id       INT UNSIGNED    NULL,
    worker_id       INT UNSIGNED    NULL,
    type            ENUM('product','sim','service') NOT NULL DEFAULT 'product',
    subtotal        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    shipping_fee    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    discount        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    commission_rate DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    commission_fee  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    status          ENUM('pending','confirmed','processing','shipping','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    payment_method  VARCHAR(50)     NULL,
    payment_status  ENUM('unpaid','paid','partial','refunded') NOT NULL DEFAULT 'unpaid',
    voucher_code    VARCHAR(50)     NULL,
    shipping_address TEXT           NULL,
    buyer_note      TEXT            NULL,
    admin_note      TEXT            NULL,
    completed_at    DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_buyer (buyer_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at),
    INDEX idx_orders_code (order_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 6: order_items - CHI TIẾT ĐƠN HÀNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS order_items (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED    NOT NULL,
    product_id      INT UNSIGNED    NULL,
    product_type    VARCHAR(50)     NOT NULL,
    product_name    VARCHAR(255)    NOT NULL,
    quantity        INT UNSIGNED    NOT NULL DEFAULT 1,
    price           DECIMAL(15,2)   NOT NULL,
    subtotal        DECIMAL(15,2)   NOT NULL,
    metadata        JSON            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_oi_order (order_id),
    INDEX idx_oi_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 7: transactions - GIAO DỊCH
-- =========================================================================================
CREATE TABLE IF NOT EXISTS transactions (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    type            ENUM('deposit','withdraw','payment','refund','commission','earnings','transfer') NOT NULL,
    amount          DECIMAL(15,2)   NOT NULL,
    balance_before  DECIMAL(15,2)   NOT NULL,
    balance_after   DECIMAL(15,2)   NOT NULL,
    reference_type  VARCHAR(50)     NULL,
    reference_id    INT UNSIGNED    NULL,
    description     TEXT            NULL,
    status          ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tx_user (user_id),
    INDEX idx_tx_type (type),
    INDEX idx_tx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 8: vouchers - MÃ GIẢM GIÁ
-- =========================================================================================
CREATE TABLE IF NOT EXISTS vouchers (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(50)     NOT NULL UNIQUE,
    type            ENUM('percent','fixed') NOT NULL DEFAULT 'fixed',
    value           DECIMAL(15,2)   NOT NULL,
    min_order       DECIMAL(15,2)   NOT NULL DEFAULT 0,
    max_discount    DECIMAL(15,2)   NULL,
    usage_limit     INT             NOT NULL DEFAULT 1,
    used_count      INT             NOT NULL DEFAULT 0,
    expires_at      DATETIME        NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_voucher_code (code),
    INDEX idx_voucher_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 9: voucher_usage - LỊCH SỬ DÙNG VOUCHER
-- =========================================================================================
CREATE TABLE IF NOT EXISTS voucher_usage (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    voucher_id      INT UNSIGNED    NOT NULL,
    user_id         INT UNSIGNED    NOT NULL,
    order_id        INT UNSIGNED    NULL,
    discount_amount DECIMAL(15,2)   NOT NULL,
    used_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 10: commissions - HOA HỒNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS commissions (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL COMMENT 'Người được hưởng hoa hồng',
    order_id        INT UNSIGNED    NULL,
    type            ENUM('seller','worker','ctv','affiliate') NOT NULL,
    amount          DECIMAL(15,2)   NOT NULL,
    rate            DECIMAL(5,2)    NOT NULL,
    status          ENUM('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
    description     TEXT            NULL,
    approved_at     DATETIME        NULL,
    paid_at         DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_comm_user (user_id),
    INDEX idx_comm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 11: cart_items - GIỎ HÀNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS cart_items (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    product_type    VARCHAR(50)     NOT NULL COMMENT 'sim hoặc marketplace_products',
    product_id      INT UNSIGNED    NOT NULL,
    quantity        INT UNSIGNED    NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart (user_id, product_type, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 12: workers - THÔNG TIN THỢ
-- =========================================================================================
CREATE TABLE IF NOT EXISTS workers (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL UNIQUE,
    fullname        VARCHAR(100)    NOT NULL,
    phone           VARCHAR(15)     NOT NULL,
    skills          TEXT            NULL,
    experience      TEXT            NULL,
    bank_name       VARCHAR(100)    NULL,
    bank_account    VARCHAR(50)     NULL,
    id_number       VARCHAR(20)     NULL COMMENT 'Số CMND/CCCD',
    is_verified     TINYINT(1)      NOT NULL DEFAULT 0,
    is_available    TINYINT(1)      NOT NULL DEFAULT 1,
    commission_rate DECIMAL(5,2)    NOT NULL DEFAULT 10.00,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_worker_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 13: worker_reviews - ĐÁNH GIÁ THỢ
-- =========================================================================================
CREATE TABLE IF NOT EXISTS worker_reviews (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    worker_id       INT UNSIGNED    NOT NULL,
    reviewer_id     INT UNSIGNED    NOT NULL,
    order_id        INT UNSIGNED    NULL,
    rating          TINYINT UNSIGNED NOT NULL DEFAULT 5,
    comment         TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 14: activity_logs - NHẬT KÝ HOẠT ĐỘNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NULL,
    action          VARCHAR(100)    NOT NULL,
    entity_type     VARCHAR(50)     NULL,
    entity_id       INT UNSIGNED    NULL,
    description     TEXT            NULL,
    ip_address      VARCHAR(45)     NULL,
    user_agent      VARCHAR(500)    NULL,
    metadata        JSON            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_user (user_id),
    INDEX idx_log_action (action),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 16: anti_spam - CHỐNG SPAM
-- =========================================================================================
CREATE TABLE IF NOT EXISTS anti_spam (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier      VARCHAR(100)    NOT NULL COMMENT 'IP hoặc Telegram ID',
    action          VARCHAR(50)     NOT NULL,
    blocked_until   DATETIME        NOT NULL,
    attempt_count   INT             NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_spam_id (identifier),
    INDEX idx_spam_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 17: settings - CẤU HÌNH HỆ THỐNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS settings (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    `key`           VARCHAR(100)    NOT NULL UNIQUE,
    value           TEXT            NOT NULL,
    description     VARCHAR(255)    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (`key`, `value`, `description`) VALUES
    ('site_name', 'Điện Tử Hiếu', 'Tên trang web'),
    ('commission_rate', '10', 'Hoa hồng nền tảng (%)'),
    ('min_withdraw', '50000', 'Số tiền rút tối thiểu'),
    ('free_shipping_min', '500000', 'Miễn phí ship từ'),
    ('assignment_enabled', '1', 'Bật/tắt tính năng giao ca cho thợ trong admin (1=bật, 0=tắt)');

-- =========================================================================================
-- BẢNG 18: blacklist - DANH SÁCH ĐEN
-- =========================================================================================
CREATE TABLE IF NOT EXISTS blacklist (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    identifier      VARCHAR(100)    NOT NULL UNIQUE COMMENT 'IP, Telegram ID, Phone',
    reason          TEXT            NULL,
    created_by      INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- VIEW TỔNG HỢP
-- =========================================================================================

CREATE OR REPLACE VIEW v_dashboard AS
SELECT
    (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS total_users,
    (SELECT COUNT(*) FROM marketplace_products WHERE status='active' AND deleted_at IS NULL) AS total_products,
    (SELECT COUNT(*) FROM sims WHERE status='available') AS total_sims,
    (SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE()) AS today_orders,
    (SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= CURDATE() AND status NOT IN ('cancelled')) AS today_revenue,
    (SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ('cancelled')) AS total_revenue,
    (SELECT COALESCE(SUM(wallet_balance),0) FROM users) AS total_wallet;

-- =========================================================================================
-- DỮ LIỆU BAN ĐẦU
-- =========================================================================================
INSERT IGNORE INTO users (role, fullname, phone, is_active) VALUES
    ('admin', 'Quản trị viên', '0900000000', 1);