# PHẦN 3 - KẾ HOẠCH PHÁT TRIỂN TÍNH NĂNG (MARKETPLACE)

> Cập nhật: 24/06/2026
> Trạng thái: SẴN SÀNG TRIỂN KHAI

## 📊 Tổng quan hiện trạng (code audit 24/06)

| # | Tính năng | Trạng thái | File liên quan |
|---|---|---|---|
| 1 | Đăng ký/đăng nhập (OTP phone) | ✅ **ĐÃ CÓ** | `api/users.php` (login_or_register_phone_action, app_customer_register_action) |
| 2 | Quản lý gian hàng | ✅ **ĐÃ CÓ** | `api/workers.php` (worker CRUD, marketplace_products_for_store) |
| 3 | Tìm kiếm/filter sản phẩm | ✅ **ĐÃ CÓ** | `api/products.php` (search, filter, keyword, 13 hits) |
| 4 | Đơn hàng + trạng thái | ✅ **ĐÃ CÓ** | `api/orders.php` (next_order_code, create_order, order_status 6 hits) |
| 5 | Đánh giá/review | ✅ **ĐÃ CÓ** | `review/rating` (2 hits) |
| 6 | Chat realtime | ✅ **ĐÃ CÓ** | `messages` (2 hits trong api/) |
| 7 | Email thông báo đơn | ✅ **ĐÃ CÓ** | `mail/smtp` (9 hits) |
| 8 | **JWT/Session token chuẩn** | ❌ Chỉ có `login_key` đơn giản | Cần viết wrapper |
| 9 | **Giỏ hàng + checkout (COD/CK)** | ❌ THIẾU | Cần tạo `api/cart.php` |
| 10 | **Tài khoản test BCT** | ❌ THIẾU | Cần script seed user test |

## 🎯 Roadmap triển khai (5 phases)

### Phase 1: Cart & Checkout (QUAN TRỌNG NHẤT - ưu tiên #1)
**Mục tiêu:** User add sản phẩm vào giỏ → checkout → tạo order

- [ ] `api/cart.php` (mới)
  - `cart_add` — thêm SP vào giỏ (user_id, product_id, qty)
  - `cart_list` — liệt kê giỏ hàng (kèm product info, total)
  - `cart_update` — cập nhật số lượng
  - `cart_remove` — xóa SP khỏi giỏ
  - `cart_clear` — xóa sạch giỏ
  - `cart_checkout` — tạo order từ giỏ (COD / VietQR / MoMo)
- [ ] Bảng `cart_items` (mới trong `database_migration.sql`)
- [ ] UI giỏ hàng (icon cart + dropdown mini-cart trên `index.php`)
- [ ] UI checkout (form nhập địa chỉ, chọn PTTT, hiển thị tổng tiền)
- [ ] Sau checkout: trừ stock, tạo order, gửi email xác nhận

### Phase 2: JWT/Session wrapper (ưu tiên #2)
- [ ] Tạo `api/jwt.php` — sign/verify token (HS256, secret từ .env)
- [ ] Middleware check JWT trong các API cần auth
- [ ] Cập nhật `login_or_register_phone_action` → trả JWT thay vì login_key
- [ ] Frontend lưu JWT trong localStorage + gửi header `Authorization: Bearer <token>`

### Phase 3: Tài khoản test BCT (ưu tiên #3)
- [ ] `scripts/seed-bct-test.php` — tạo user `qlhdtmdt@gmail.com / Admin@123` (role: admin)
- [ ] Có flag `is_test_account = 1` để phân biệt
- [ ] BCT sẽ login vào `https://dienmayhieu.com/admin` để test

### Phase 4: UI/UX polish
- [ ] Responsive mobile-first (chưa test kỹ)
- [ ] Dark mode (nếu cần)
- [ ] Loading skeleton, toast notif, error handling
- [ ] SEO: meta description, OG tags cho từng trang

### Phase 5: Báo cáo BCT tự động
- [ ] Auto-send báo cáo định kỳ theo NĐ 85/2021
- [ ] `bct_portal.php` đã có sẵn (19KB) — test thử

## 📁 File mới sẽ tạo

```
api/
  cart.php          (Phase 1 - ~15KB)
  jwt.php           (Phase 2 - ~5KB)
  middleware.php    (Phase 2 - ~3KB, auth check)

scripts/
  seed-bct-test.php (Phase 3 - ~3KB)

docs/
  PHAN-3-ROADMAP.md (file này)
  PHAN-3-API-CART.md (docs chi tiết cho cart API)
```

## 📦 Cấu trúc bảng `cart_items` (đề xuất)

```sql
CREATE TABLE IF NOT EXISTS cart_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    quantity        INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price      DECIMAL(15,2) NOT NULL,  -- snapshot lúc add
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_product (user_id, product_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 🧪 Test plan sau khi dev

- [ ] Mở `https://dienmayhieu.com` (sau deploy) → click vào 1 sản phẩm
- [ ] Click "Thêm vào giỏ" → check DB có row mới trong cart_items
- [ ] Mở giỏ → update qty, remove item
- [ ] Click "Thanh toán" → form checkout
- [ ] Submit → check DB có order mới, stock bị trừ
- [ ] Email xác nhận được gửi

## ⏱️ Ước lượng thời gian

- **Phase 1 (Cart):** 4-6 giờ dev
- **Phase 2 (JWT):** 2-3 giờ dev
- **Phase 3 (BCT test account):** 30 phút
- **Phase 4 (UI polish):** 3-4 giờ
- **Phase 5 (Báo cáo tự động):** 2-3 giờ

**Tổng:** ~12-16 giờ làm việc

---
*File này là roadmap, KHÔNG phải code. Mỗi phase cần brief riêng khi bắt đầu.*
