# 🚨 CẦN FIX GẤP: FTP user directory sai

## Vấn đề

Khi kết nối FTP với user `dientuhieu@dienmayhieu.com`, em chỉ thấy 2 file trong root:
- `.ftpquota` (file hệ thống Pure-FTPd)
- `test-upload.txt` (file test anh đã upload lên trước)

**KHÔNG thấy `public_html` hay bất kỳ thư mục nào khác.**

## Nguyên nhân

Khi anh tạo FTP account trong cPanel, có thể anh:
1. **Chọn sai Directory** — để trống hoặc chỉ `/` thay vì `public_html/`
2. **Quota = 0 / Unlimited** nhưng giới hạn truy cập thư mục

Đường dẫn đúng phải là: `/home/kwkrbcce/public_html/` (theo ảnh cPanel anh gửi lúc đầu — File path ghi: `/home/kwkrbc.su_vinh298-byte`)

## Cách fix

### Cách 1: Xóa user cũ, tạo lại đúng directory (khuyến nghị)

1. Vào cPanel VinaHost → **FTP Accounts**
2. Tìm user `dientuhieu@dienmayhieu.com` → click **Delete** (bỏ tích "Delete Account's Home Directory" nếu có)
3. Click **Add FTP Account**
4. Điền:
   - **Login:** `dientuhieu` (KHÔNG có `@dienmayhieu.com` ở đây — cPanel tự thêm)
   - **Password:** `Anhthien369@` (giữ nguyên)
   - **Directory:** GÕ CHÍNH XÁC → `public_html` (KHÔNG có `/` ở đầu, KHÔNG có dấu `/` ở cuối)
   - **Quota:** Unlimited
5. Click **Create**

### Cách 2: Edit user hiện tại (nếu cPanel cho phép)

1. Vào **FTP Accounts** → tìm user → click **Manage** hoặc **Edit**
2. Đổi Directory thành: `public_html`

## Sau khi fix

Test lại bằng cách gửi em screenshot mới. Em sẽ:
1. List `/` → phải thấy `public_html/` (directory)
2. List `/public_html/` → phải thấy `index.php` cũ của web cũ
3. Deploy code mới lên

## Backup plan: cPanel File Manager

Nếu FTP cứ bị lỗi, dùng **cPanel File Manager** thủ công:
1. Login cPanel: `https://vdc-whm-cheaphosting-1112.vinahost.org:2083` (port cPanel)
2. Vào **File Manager** → mở `public_html/`
3. Em sẽ zip code ở local thành `dienmayhieu-deploy.zip`
4. Anh upload file zip lên → click phải → **Extract**
5. Xong!

## Lưu ý

File `test-upload.txt` anh upload ở root `/` KHÔNG ảnh hưởng web. Khi fix xong, em sẽ xóa file này.

---
*Cập nhật: 24/06/2026 - 08:24 GMT+7*
