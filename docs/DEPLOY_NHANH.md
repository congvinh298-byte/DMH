# Deploy nhanh DTH lên dienmayhieu.com

## Cách 1: Dùng cPanel Git Version Control (khuyên)

1. Đăng nhập cPanel: `https://dienmayhieu.com:2083` (hoặc link cPanel anh có).
2. Tìm **Git Version Control**.
3. Chọn **Create** → nhập repo URL:
   ```
   https://github.com/congvinh298-byte/DMH.git
   ```
   Repository Path để là `public_html` (hoặc thư mục web root).
4. Sau khi clone xong, chọn **Pull** để cập nhật code mới nhất.
5. Upload file `.env` vào thư mục web root nếu chưa có (cPanel File Manager → Upload).

## Cách 2: Dùng SSH trên host (nếu host bật SSH)

```bash
cd /home/kwkrbcce/public_html
git pull origin main
```

Hoặc chạy script có sẵn:
```bash
bash /home/kwkrbcce/public_html/scripts/deploy-from-host.sh
```

## Cách 3: Upload thủ công (nếu không dùng git)

1. Trên máy, nén thư mục `DTH` thành zip.
2. Vào cPanel File Manager → Upload zip.
3. Giải nén zip vào `public_html`.
4. Upload file `.env` riêng.

## Sau khi deploy

1. Cấu hình webhook Telegram:
   ```
   https://dienmayhieu.com/api_master.php?action=telegram_set_webhook&bot=worker&secret=***
   https://dienmayhieu.com/api_master.php?action=telegram_set_webhook&bot=report&secret=***
   ```
2. Kiểm tra health:
   ```
   https://dienmayhieu.com/api_master.php?action=health
   ```
3. Test tạo ca trên web.

## Lưu ý
- **KHÔNG** xóa file `.env` trên host.
- Nếu dùng zip, đảm bảo không ghi đè `.env` cũ.
