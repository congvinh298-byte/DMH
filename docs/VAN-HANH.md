# HƯỚNG DẪN VẬN HÀNH - Chợ Lấp Vò Online

## Thông tin hệ thống

- **Website:** https://dienmayhieu.com/
- **Admin:** https://dienmayhieu.com/admin_xxx.php
- **BCT Portal:** https://dienmayhieu.com/bct_portal.php
- **BCT Hồ sơ:** https://dienmayhieu.com/Ho_so_BCT/
- **Công ty:** CÔNG TY TNHH MTV ĐIỆN MÁY HIẾU
- **MST:** 1402228630

## Tài khoản quản trị

| Vai trò | Username | Password |
|---|---|---|
| Owner admin | `anhthien` | `Anhthien369@` |
| BCT test | `qltmdt@moit.gov.vn` | `Admin@123` |
| BCT test | `qlhdtmdt@gmail.com` | `Admin@123` |

## Các trang pháp lý

- `/pages/chinh-sach-bao-mat.php`
- `/pages/dieu-khoan-su-dung.php`
- `/pages/chinh-sach-doi-tra.php`
- `/pages/chinh-sach-van-chuyen.php`
- `/pages/chinh-sach-thanh-toan.php`
- `/pages/huong-dan-mua-hang.php`
- `/pages/huong-dan-ban-hang.php`
- `/pages/giai-quyet-tranh-chap.php`
- `/pages/gioi-thieu.php`
- `/pages/lien-he.php`
- `/pages/quy-che-hoat-dong.php`
- `/pages/de-an-dich-vu.php`

## API endpoints chính

- `/api/products.php` — sản phẩm
- `/api/cart.php` — giỏ hàng
- `/api/orders.php` — đơn hàng
- `/api/payment.php` — thanh toán VNPAY/MOMO
- `/api/shipping.php` — vận chuyển GHN/GHTK
- `/api/notify.php` — email/SMTP + Telegram
- `/api/users.php` — người dùng
- `/api/workers.php` — người lao động
- `/api/vouchers.php` — khuyến mãi
- `/api_master.php` — admin backend

## Cấu hình cần bổ sung (`.env`)

Để website hoạt động đầy đủ, cần điền các giá trị thật vào `.env`:

```
# VNPAY
VNPAY_TMN_CODE= "..."
VNPAY_HASH_SECRET= "..."

# SMTP
SMTP_HOST= "..."
SMTP_USER= "..."
SMTP_PASS= "..."
SMTP_FROM= "..."

# GHN
GHN_API_TOKEN= "..."
GHN_SHOP_ID= "..."

# GHTK
GHTK_API_TOKEN= "..."

# Backup
BACKUP_SECRET= "..."
```

## Backup

Truy cập:
```
https://dienmayhieu.com/scripts/backup-dth.php?secret=YOUR_BACKUP_SECRET
```

Backup được lưu tại `/home/kwkrbcce/backups/`.

## Deploy

Các thay đổi code:
1. Commit & push lên GitHub
2. Upload qua FTP đến `/home/kwkrbcce/dienmayhieu.com/dth/`
3. Chạy script copy PHP để đưa vào `/home/kwkrbcce/public_html/`

## Liên hệ hỗ trợ

- **Hotline:** 0979.553.289
- **Email:** Congvinh28@gmail.com
- **Telegram:** @congvinh298

---
Tài liệu cập nhật: 24/06/2026
