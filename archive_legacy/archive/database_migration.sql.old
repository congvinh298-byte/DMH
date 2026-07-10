-- =========================================================================================
-- DATABASE MIGRATION - HỆ SINH THÁI MARKETPLACE ĐIỆN TỬ HIẾU
-- 
-- Tầm nhìn: Sàn thương mại điện tử đa mặt hàng + Hệ sinh thái kết nối việc làm
-- 
-- Ngày: 31/05/2026
-- Phiên bản: v2.0 (Marketplace Evolution)
--
-- QUY TẮC:
-- 1. Tất cả bảng mới dùng CREATE TABLE IF NOT EXISTS để không làm mất dữ liệu
-- 2. Dữ liệu cũ (kho_sim, cuoc_xe, tai_xe) KHÔNG BỊ XÓA - chỉ thêm cột/kết nối
-- 3. Charset: utf8mb4 cho toàn bộ hệ thống
-- 4. Foreign Keys được thiết kế để tương thích ngược với code cũ
-- =========================================================================================

-- =========================================================================================
-- BẢNG 1: users - TRÁI TIM CỦA HỆ SINH THÁI
-- Quản lý tất cả người dùng: Admin, Người bán, Người mua, Người lao động (worker)
-- =========================================================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('admin','seller','buyer','worker') NOT NULL DEFAULT 'buyer',
    fullname        VARCHAR(100)    NOT NULL,
    email           VARCHAR(100)    NULL UNIQUE,
    phone           VARCHAR(15)     NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NULL COMMENT 'NULL nếu dùng Telegram Auth',
    avatar_url      VARCHAR(500)    NULL,
    wallet_balance  DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Số dư ví nội bộ',
    total_earned    DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Tổng thu nhập (worker)',
    total_spent     DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Tổng chi tiêu (buyer)',
    rating          DECIMAL(3,2)    NOT NULL DEFAULT 5.00 COMMENT 'Đánh giá uy tín (0.00 - 5.00)',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0=Khóa, 1=Hoạt động',
    telegram_chat_id VARCHAR(50)    NULL UNIQUE COMMENT 'Liên kết với tài khoản Telegram',
    telegram_username VARCHAR(100)  NULL,
    metadata        JSON            NULL COMMENT 'Dữ liệu mở rộng (địa chỉ, sở thích, kỹ năng...)',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL COMMENT 'Soft delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_telegram ON users(telegram_chat_id);
CREATE INDEX idx_users_active ON users(is_active);

-- =========================================================================================
-- BẢNG 2: categories - DANH MỤC SẢN PHẨM ĐA CẤP
-- =========================================================================================
CREATE TABLE IF NOT EXISTS categories (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    parent_id       INT UNSIGNED    NULL COMMENT 'NULL = danh mục gốc',
    name            VARCHAR(100)    NOT NULL,
    slug            VARCHAR(150)    NOT NULL UNIQUE COMMENT 'URL-friendly name',
    description     TEXT            NULL,
    icon            VARCHAR(50)     NULL COMMENT 'Tên icon FontAwesome/Emoji',
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
-- BẢNG 3: marketplace_products - KHUNG SẢN PHẨM ĐA NĂNG
-- Dùng tên marketplace_products để tránh xung đột với bảng products cũ của store/
-- Hỗ trợ: SIM, điện máy, dịch vụ lao động, việc làm, vận tải, bảo hiểm...
-- =========================================================================================
CREATE TABLE IF NOT EXISTS marketplace_products (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    seller_id       INT UNSIGNED    NULL COMMENT 'Người đăng bán (users.id)',
    category_id     INT UNSIGNED    NULL COMMENT 'Danh mục (categories.id)',
    type            ENUM('sim','dien_may','dien_thoai','phu_kien','dich_vu_lao_dong','van_tai','bao_hiem','do_cu','viec_lam','other') NOT NULL DEFAULT 'other',
    name            VARCHAR(255)    NOT NULL,
    slug            VARCHAR(255)    NULL UNIQUE,
    description     TEXT            NULL,
    price           DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Giá bán',
    sale_price      DECIMAL(15,2)   NULL COMMENT 'Giá khuyến mãi (nếu có)',
    stock           INT             NOT NULL DEFAULT 0 COMMENT 'Tồn kho (-1 = không giới hạn)',
    sold_count      INT             NOT NULL DEFAULT 0 COMMENT 'Đã bán',
    images          JSON            NULL COMMENT 'Mảng URL hình ảnh',
    attributes      JSON            NULL COMMENT 'Thuộc tính mở rộng (màu sắc, dung lượng, kỹ năng...)',
    location        VARCHAR(255)    NULL COMMENT 'Địa điểm (cho dịch vụ lao động)',
    latitude        DECIMAL(10,8)   NULL COMMENT 'Tọa độ (cho tìm kiếm gần đây)',
    longitude       DECIMAL(11,8)   NULL,
    status          ENUM('draft','active','sold','hidden','disabled') NOT NULL DEFAULT 'draft',
    is_featured     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Sản phẩm nổi bật',
    view_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_mp_type ON marketplace_products(type);
CREATE INDEX idx_mp_status ON marketplace_products(status);
CREATE INDEX idx_mp_price ON marketplace_products(price);
CREATE INDEX idx_mp_seller ON marketplace_products(seller_id);
CREATE INDEX idx_mp_category ON marketplace_products(category_id);
CREATE INDEX idx_mp_location ON marketplace_products(latitude, longitude);
CREATE FULLTEXT INDEX idx_mp_search ON marketplace_products(name, description);

-- =========================================================================================
-- BẢNG 4: orders - ĐƠN HÀNG (cho cả hàng hóa lẫn dịch vụ lao động)
-- =========================================================================================
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    order_code      VARCHAR(30)     NOT NULL UNIQUE COMMENT 'Mã đơn hàng (VD: DTH-20260531-00001)',
    buyer_id        INT UNSIGNED    NULL COMMENT 'Người mua (users.id)',
    seller_id       INT UNSIGNED    NULL COMMENT 'Người bán (users.id)',
    worker_id       INT UNSIGNED    NULL COMMENT 'Người thực hiện dịch vụ lao động (users.id)',
    type            ENUM('product','service','job') NOT NULL DEFAULT 'product' COMMENT 'Loại đơn hàng',
    total           DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Tổng tiền',
    shipping_fee    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    discount        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    commission_rate DECIMAL(5,2)    NOT NULL DEFAULT 10.00 COMMENT '% hoa hồng nền tảng',
    commission_fee  DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Tiền hoa hồng thực tế',
    worker_earnings DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Thu nhập của worker (nếu có)',
    status          ENUM('pending','confirmed','processing','shipping','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    payment_method  VARCHAR(50)     NULL COMMENT 'Phương thức thanh toán',
    payment_status  ENUM('unpaid','paid','partial','refunded') NOT NULL DEFAULT 'unpaid',
    shipping_address TEXT           NULL,
    buyer_note      TEXT            NULL,
    admin_note      TEXT            NULL,
    old_cuoc_xe_id  INT UNSIGNED    NULL COMMENT 'Liên kết với bảng cuoc_xe cũ (migration)',
    old_order_id    INT UNSIGNED    NULL COMMENT 'Liên kết với bảng orders cũ (store)',
    viettel_invoice_exported TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Đã xuất hóa đơn Viettel',
    viettel_invoice_no VARCHAR(50)  NULL COMMENT 'Mã hóa đơn Viettel',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME        NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_orders_buyer ON orders(buyer_id);
CREATE INDEX idx_orders_seller ON orders(seller_id);
CREATE INDEX idx_orders_worker ON orders(worker_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_orders_code ON orders(order_code);

-- =========================================================================================
-- BẢNG 5: order_items - CHI TIẾT ĐƠN HÀNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS order_items (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED    NOT NULL,
    product_id      INT UNSIGNED    NULL COMMENT 'Sản phẩm (marketplace_products.id)',
    product_name    VARCHAR(255)    NOT NULL COMMENT 'Snapshot tên SP tại thời điểm mua',
    product_type    VARCHAR(50)     NULL COMMENT 'Snapshot loại SP',
    quantity        INT UNSIGNED    NOT NULL DEFAULT 1,
    price           DECIMAL(15,2)   NOT NULL COMMENT 'Đơn giá tại thời điểm mua',
    subtotal        DECIMAL(15,2)   NOT NULL COMMENT 'Tạm tính (price * quantity)',
    worker_id       INT UNSIGNED    NULL COMMENT 'Người thực hiện (cho service)',
    commission_rate DECIMAL(5,2)    NOT NULL DEFAULT 10.00,
    commission_fee  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    metadata        JSON            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES marketplace_products(id) ON DELETE SET NULL,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_oi_order ON order_items(order_id);
CREATE INDEX idx_oi_product ON order_items(product_id);

-- =========================================================================================
-- BẢNG 6: transactions - LỊCH SỬ GIAO DỊCH VÍ
-- =========================================================================================
CREATE TABLE IF NOT EXISTS transactions (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    type            ENUM('deposit','withdraw','payment','refund','commission','earnings','transfer') NOT NULL,
    amount          DECIMAL(15,2)   NOT NULL,
    balance_before  DECIMAL(15,2)   NOT NULL,
    balance_after   DECIMAL(15,2)   NOT NULL,
    reference_type  VARCHAR(50)     NULL COMMENT 'Loại tham chiếu (order, payout...)',
    reference_id    INT UNSIGNED    NULL COMMENT 'ID tham chiếu',
    description     TEXT            NULL,
    status          ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tx_user ON transactions(user_id);
CREATE INDEX idx_tx_type ON transactions(type);
CREATE INDEX idx_tx_created ON transactions(created_at);

-- =========================================================================================
-- BẢNG 7: worker_skills - KỸ NĂNG CỦA NGƯỜI LAO ĐỘNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS worker_skills (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    skill_name      VARCHAR(100)    NOT NULL,
    years_exp       INT             NOT NULL DEFAULT 0,
    certification   VARCHAR(255)    NULL,
    is_verified     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_worker_skill (user_id, skill_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 8: worker_reviews - ĐÁNH GIÁ NGƯỜI LAO ĐỘNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS worker_reviews (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    worker_id       INT UNSIGNED    NOT NULL,
    reviewer_id     INT UNSIGNED    NOT NULL,
    order_id        INT UNSIGNED    NULL,
    rating          TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-5 sao',
    comment         TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 9: cart_items - GIỎ HÀNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS cart_items (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    product_id      INT UNSIGNED    NOT NULL,
    quantity        INT UNSIGNED    NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES marketplace_products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 10: job_posts - TIN TUYỂN DỤNG
-- =========================================================================================
CREATE TABLE IF NOT EXISTS job_posts (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    employer_id     INT UNSIGNED    NOT NULL COMMENT 'Người đăng tuyển (users.id)',
    title           VARCHAR(255)    NOT NULL,
    description     TEXT            NOT NULL,
    requirements    TEXT            NULL,
    salary_min      DECIMAL(15,2)   NULL,
    salary_max      DECIMAL(15,2)   NULL,
    salary_type     ENUM('hourly','daily','monthly','negotiable') NOT NULL DEFAULT 'negotiable',
    location        VARCHAR(255)    NULL,
    category_id     INT UNSIGNED    NULL,
    worker_count    INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'Số lượng cần tuyển',
    status          ENUM('open','closed','filled','cancelled') NOT NULL DEFAULT 'open',
    expires_at      DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- BẢNG 11: job_applications - ĐƠN ỨNG TUYỂN
-- =========================================================================================
CREATE TABLE IF NOT EXISTS job_applications (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    job_post_id     INT UNSIGNED    NOT NULL,
    worker_id       INT UNSIGNED    NOT NULL,
    cover_letter    TEXT            NULL,
    status          ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at     DATETIME        NULL,
    FOREIGN KEY (job_post_id) REFERENCES job_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_post_id, worker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- MIGRATION: ĐỒNG BỘ DỮ LIỆU CŨ SANG CẤU TRÚC MỚI
-- ⚠️ KHÔNG XÓA BẢNG CŨ - Chỉ thêm cột và tạo kết nối
-- Dữ liệu thực tế vẫn đang chạy ở: kho_sim, cuoc_xe, tai_xe
-- =========================================================================================

-- === MIGRATION 1: kho_sim → marketplace_products ===
ALTER TABLE kho_sim 
    ADD COLUMN IF NOT EXISTS mp_product_id INT UNSIGNED NULL AFTER id,
    ADD INDEX IF NOT EXISTS idx_ks_mp_product (mp_product_id);

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sync_kho_sim_to_mp()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_id INT UNSIGNED;
    DECLARE v_so_sim VARCHAR(20);
    DECLARE v_nha_mang VARCHAR(100);
    DECLARE v_loai_sim VARCHAR(100);
    DECLARE v_gia_ban DECIMAL(15,2);
    DECLARE v_trang_thai VARCHAR(50);
    DECLARE v_created_at DATETIME;
    DECLARE v_mp_id INT UNSIGNED;
    DECLARE cur CURSOR FOR SELECT k.id, k.so_sim, k.nha_mang, k.loai_sim, k.gia_ban, k.trang_thai, k.created_at FROM kho_sim k WHERE k.mp_product_id IS NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_id, v_so_sim, v_nha_mang, v_loai_sim, v_gia_ban, v_trang_thai, v_created_at;
        IF done THEN LEAVE read_loop; END IF;
        INSERT IGNORE INTO marketplace_products (type, name, slug, description, price, stock, status, created_at)
        VALUES ('sim',
            CONCAT('SIM ', v_so_sim, ' - ', v_nha_mang),
            CONCAT('sim-', v_so_sim),
            CONCAT('SIM số ', v_so_sim, ' | Mạng: ', v_nha_mang, ' | Loại: ', v_loai_sim),
            v_gia_ban, 1,
            CASE WHEN v_trang_thai = 'Sẵn sàng' THEN 'active' WHEN v_trang_thai = 'Đã bán' THEN 'sold' ELSE 'draft' END,
            v_created_at);
        SET v_mp_id = LAST_INSERT_ID();
        UPDATE kho_sim SET mp_product_id = v_mp_id WHERE id = v_id;
    END LOOP;
    CLOSE cur;
END //
DELIMITER ;

-- === MIGRATION 2: cuoc_xe → orders ===
ALTER TABLE cuoc_xe 
    ADD COLUMN IF NOT EXISTS order_id INT UNSIGNED NULL AFTER id,
    ADD INDEX IF NOT EXISTS idx_cx_order_id (order_id);

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sync_cuoc_xe_to_orders()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_id INT UNSIGNED;
    DECLARE v_loai_dich_vu VARCHAR(255);
    DECLARE v_sdt VARCHAR(20);
    DECLARE v_gia INT;
    DECLARE v_trang_thai VARCHAR(50);
    DECLARE v_created_at DATETIME;
    DECLARE v_order_id INT UNSIGNED;
    DECLARE cur CURSOR FOR SELECT c.id, c.loai_dich_vu, c.sdt_khach, c.gia_du_kien, c.trang_thai, c.thoi_gian_tao FROM cuoc_xe c WHERE c.order_id IS NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_id, v_loai_dich_vu, v_sdt, v_gia, v_trang_thai, v_created_at;
        IF done THEN LEAVE read_loop; END IF;
        SET @order_status = 'pending';
        IF v_trang_thai = 'Chờ nhận' THEN SET @order_status = 'pending'; END IF;
        IF v_trang_thai = 'Đã nhận' THEN SET @order_status = 'processing'; END IF;
        IF v_trang_thai = 'Đang thực hiện' THEN SET @order_status = 'processing'; END IF;
        IF v_trang_thai = 'Hoàn thành' THEN SET @order_status = 'completed'; END IF;
        IF v_trang_thai = 'Khách Hủy' THEN SET @order_status = 'cancelled'; END IF;
        SET @order_code = CONCAT('DTH-', DATE_FORMAT(v_created_at, '%Y%m%d'), '-', LPAD(v_id, 6, '0'));
        INSERT IGNORE INTO orders (order_code, total, status, type, old_cuoc_xe_id, created_at)
        VALUES (@order_code, v_gia, @order_status, 'service', v_id, v_created_at);
        SET v_order_id = LAST_INSERT_ID();
        UPDATE cuoc_xe SET order_id = v_order_id WHERE id = v_id;
    END LOOP;
    CLOSE cur;
END //
DELIMITER ;

-- === MIGRATION 3: tai_xe → users ===
ALTER TABLE tai_xe 
    ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER id,
    ADD INDEX IF NOT EXISTS idx_tx_user_id (user_id);

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sync_tai_xe_to_users()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_id INT UNSIGNED;
    DECLARE v_ten VARCHAR(255);
    DECLARE v_sdt VARCHAR(20);
    DECLARE v_telegram_id VARCHAR(50);
    DECLARE v_user_id INT UNSIGNED;
    DECLARE cur CURSOR FOR SELECT t.id, t.ten_tai_xe, t.sdt_tai_xe, t.telegram_chat_id FROM tai_xe t WHERE t.user_id IS NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_id, v_ten, v_sdt, v_telegram_id;
        IF done THEN LEAVE read_loop; END IF;
        INSERT IGNORE INTO users (role, fullname, phone, telegram_chat_id, is_active, created_at)
        VALUES ('worker', COALESCE(v_ten, CONCAT('Worker #', CAST(v_id AS CHAR))),
                COALESCE(v_sdt, CONCAT('000', CAST(v_id AS CHAR))), v_telegram_id, 1, NOW())
        ON DUPLICATE KEY UPDATE telegram_chat_id = VALUES(telegram_chat_id);
        SET v_user_id = LAST_INSERT_ID();
        IF v_user_id = 0 THEN
            SELECT id INTO v_user_id FROM users WHERE telegram_chat_id = v_telegram_id LIMIT 1;
        END IF;
        IF v_user_id > 0 THEN
            UPDATE tai_xe SET user_id = v_user_id WHERE id = v_id;
        END IF;
    END LOOP;
    CLOSE cur;
END //
DELIMITER ;

-- === MIGRATION 4: Bảng products cũ (store) gắn nhãn ===
-- Bảng products cũ (store) có cấu trúc: id, ten_sp, hinh_anh, gia_ban, gia_goc, ton_kho, da_ban, id_npp
-- KHÔNG ẢNH HƯỞNG đến bảng marketplace_products mới
-- Có thể thêm cột ghi chú để sau này migrate
-- Lưu ý: Lệnh dưới đây comment vì bảng products cũ đã tồn tại, chỉ chạy khi cần
-- ALTER TABLE products COMMENT = 'Store cũ - sẽ migrate lên marketplace_products';

-- =========================================================================================
-- VIEW TỔNG HỢP DỮ LIỆU
-- =========================================================================================

-- View: Tổng quan dashboard cho Sếp
CREATE OR REPLACE VIEW v_dashboard_overview AS
SELECT
    (SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL) AS total_active_users,
    (SELECT COUNT(*) FROM users WHERE role = 'worker' AND is_active = 1) AS total_workers,
    (SELECT COUNT(*) FROM users WHERE role = 'seller' AND is_active = 1) AS total_sellers,
    (SELECT COUNT(*) FROM marketplace_products WHERE status = 'active' AND deleted_at IS NULL) AS total_listings,
    (SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE()) AS today_orders,
    (SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at >= CURDATE() AND status NOT IN ('cancelled')) AS today_revenue,
    (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status NOT IN ('cancelled')) AS total_revenue,
    (SELECT COALESCE(SUM(wallet_balance), 0) FROM users) AS total_wallet_balance,
    (SELECT COUNT(*) FROM kho_sim WHERE trang_thai = 'Sẵn sàng') AS sim_inventory,
    (SELECT COUNT(*) FROM cuoc_xe WHERE trang_thai != 'Khách Hủy' AND trang_thai != 'Hoàn thành') AS pending_services;

-- View: Top sản phẩm bán chạy
CREATE OR REPLACE VIEW v_top_products AS
SELECT p.id, p.name, p.price, p.sold_count, p.stock, p.type, c.name AS category_name,
    u.fullname AS seller_name
FROM marketplace_products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN users u ON p.seller_id = u.id
WHERE p.status = 'active' AND p.deleted_at IS NULL
ORDER BY p.sold_count DESC, p.view_count DESC
LIMIT 50;

-- View: Top lao động uy tín
CREATE OR REPLACE VIEW v_top_workers AS
SELECT u.id, u.fullname, u.phone, u.rating, u.total_earned,
    (SELECT COUNT(*) FROM orders o WHERE o.worker_id = u.id AND o.status = 'completed') AS jobs_completed,
    (SELECT COUNT(*) FROM worker_reviews wr WHERE wr.worker_id = u.id) AS review_count
FROM users u
WHERE u.role = 'worker' AND u.is_active = 1 AND u.deleted_at IS NULL
ORDER BY u.rating DESC, u.total_earned DESC
LIMIT 50;

-- =========================================================================================
-- HƯỚNG DẪN SỬ DỤNG:
-- =========================================================================================
-- 
-- 1. Chạy toàn bộ file này trong phpMyAdmin / MySQL CLI để tạo 11 bảng mới
-- 2. Sau đó chạy 3 stored procedure để đồng bộ dữ liệu cũ:
--    CALL sync_kho_sim_to_mp();
--    CALL sync_cuoc_xe_to_orders();
--    CALL sync_tai_xe_to_users();
-- 3. Các bảng cũ vẫn hoạt động bình thường, không ảnh hưởng
-- 4. Code mới sẽ dần chuyển sang dùng bảng mới
-- 5. Khi đã ổn định, có thể DROP bảng cũ (nếu muốn)
--
-- KIẾN TRÚC HYBRID:
--   Giai đoạn 1: Bảng cũ + bảng mới song song (HIỆN TẠI)
--   Giai đoạn 2: Code mới dùng bảng mới, code cũ vẫn dùng bảng cũ
--   Giai đoạn 3: Chuyển hoàn toàn, tắt bảng cũ
-- =========================================================================================