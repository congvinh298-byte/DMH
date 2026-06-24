# Prompt cho Codex - DTH (dienmayhieu.com) Cleanup & Deploy

> **Copy toàn bộ text dưới đây và gửi cho Codex (trong VSCode/Antigravity) để nó xử lý tiếp.**

---

Codex ơi, tiếp tục xử lý DTH project tại `C:\Users\pcpv\OneDrive\Desktop\DTH` (remote: `https://github.com/congvinh298-byte/DTH`).

## Tình hình hiện tại (24/06/2026 11:30)

1. **Code DTH mới đã lên host** qua GitHub Actions (commit 68ccb8a) → file có trong `/home/kwkrbcce/public_html/`
2. **NHƯNG web `dienmayhieu.com` vẫn serve SPA cũ** "Chợ Xã Lấp Vò" 401KB
3. **Nguyên nhân nghi ngờ**: trong `public_html/` còn lẫn file SPA cũ + file rác cũ chưa xóa
4. **Đã có quyền cPanel** user `kwkrbcce` password `06in7OIh)[YJ7e`
5. **cPanel API** gọi được `list_files` và `upload_files`, NHƯNG KHÔNG có `delete_files`/`remove_files`/`fileop` → cần dùng cách khác để xóa

## Nhiệm vụ cần làm (theo thứ tự)

### Bước 1: Dọn file rác cũ trong `/home/kwkrbcce/public_html/`

Danh sách file cần xóa (89 file):

```
# File rác cũ
admin_xxx.php, alter_*.php, debug_*.php, fix_*.php, test_*.php
temp_*.php, old_index.php, temp.html, deploy-temp.zip
bot_log.txt, error_log, khach_hang_vip.json
project_docs.md, huong_dan_xu_ly_loi.md, quy_uoc_du_an.md
toa_an_phong_thu.json, danh_sach_ctv.json, HUONG_DAN_XU_LY_LOI.md
QuyUoc_DuAn.md, api_master_temp.php, api_master.php, api_rate.php
de_an.php, deploy.php, pull.php, sync_host.py, check.php
check_orders.php, db_test.php, schema.php, dump_schema.php
create_invoices_table.php, show_table*.php, inject_js.php
insert_sims.php, refactor_marketplace.php, update_ui_quantity.php
vendor.php, webhook.php, webhook_entry.php, set_webhook.php
bot_assistant.php, task_progress.md, temp_tg_send.txt
khuyenmai.php, quy_che.php, rate.php, rewrite.php
run_alter*.php, find_tg_send.php, test_env.php, test_fetch.php
test_includes.php, test_info.php, test_job.php, test_phase2.php
test_reflection.php, test_schema.php, test_sims.php, test_spin*.php
test_stores.php, test_update*.php, test_user*.php, test_users.php
test_viettel_curl.php, temp_jobs_old.php
```

**CÁCH XÓA** (cPanel API không có delete):

**Phương án A (Khuyến nghị)**: Cập nhật workflow `.github/workflows/deploy.yml` với `danger-clean: true` để GitHub Actions xóa tất cả file cũ trước khi upload mới:
```yaml
- name: 📂 Sync files
  uses: SamKirkland/FTP-Deploy-Action@v4.3.5
  with:
    server: ${{ secrets.FTP_SERVER }}
    username: ${{ secrets.FTP_USERNAME }}
    password: ${{ secrets.FTP_PASSWORD }}
    server-dir: /home/kwkrbcce/public_html/  # QUAN TRỌNG: trỏ vào public_html
    local-dir: ./
    danger-clean: true  # Xóa hết file cũ không có trong repo
    exclude: |
      .git*
      .github
      .vscode
      docs/**
      scripts/deploy-*
      scripts/seed-*
      .env
      .env.example
      *.md
      *.log
      error_log
      test*.php
      fix_*.php
      alter_*.php
      debug_*.php
```

**Phương án B**: Thêm `server-dir: /home/kwkrbcce/public_html/` (thay vì `/`) — workflow sẽ upload đúng vào public_html.

**Phương án C (nếu cần)**: SSH vào host (nếu có) và `rm -rf` các file trên.

### Bước 2: Verify deploy thành công

Sau khi workflow chạy, check:
- Truy cập `https://dienmayhieu.com` → phải thấy trang DTH mới (title "Điện Tử Hiếu" thay vì "Chợ Xã Lấp Vò")
- Test 8 trang pháp lý: `https://dienmayhieu.com/pages/dieu-khoan-su-dung.php` (và 7 trang còn lại)
- Test admin: `https://dienmayhieu.com/admin/login.php`

### Bước 3: Test các tính năng chính

Test xem web DTH mới hoạt động:
- Trang chủ load OK
- Trang sản phẩm (`/pages/store.php` hoặc tương tự)
- Trang pháp lý (8 trang)
- API: `https://dienmayhieu.com/api/users.php?action=...`

### Bước 4: Chạy seed BCT test account

Sau khi deploy thành công, chạy:
```bash
php scripts/seed-bct-test-account.php
```
Hoặc gọi API: `https://dienmayhieu.com/api/users.php?action=admin_create_test` (nếu có route này)

Tạo 2 tài khoản:
- `qltmdt@moit.gov.vn / Admin@123` (cho Bộ Công Thương)
- `qlhdtmdt@gmail.com / Admin@123` (theo checklist cũ)

Cả 2 có role `admin`, `is_test_account = 1`.

### Bước 5: Báo cáo

In ra từ terminal:
- Kết quả từng bước (PASS/FAIL)
- URL các trang đã test
- Screenshot (nếu được) hoặc HTML content để xác nhận

## Tài nguyên có sẵn

- **VSCode workspace**: `C:\Users\pcpv\OneDrive\Desktop\DTH`
- **GitHub token**: đã setup 3 secrets (FTP_SERVER=123.30.136.221, FTP_USERNAME=dth@dienmayhieu.com, FTP_PASSWORD=Anhthien369@)
- **cPanel password**: `06in7OIh)[YJ7e` cho user `kwkrbcce`
- **Web hiện tại**: `https://dienmayhieu.com` (đang serve SPA cũ)
- **File DTH mới đã có** trong public_html nhưng web không serve (do có file cũ + SPA cũ che)

## Lưu ý quan trọng

1. **KHÔNG push code có chứa `.env` thật, password, hay secrets**
2. **Sau khi deploy, test kỹ rồi mới báo cáo** - đừng báo "xong" nếu chưa verify
3. **Nếu workflow fail vì FTP user jail**, dùng SSH hoặc cPanel UI thủ công
4. **Backup trước khi xóa** - public_html hiện có cả DTH mới + SPA cũ, chỉ xóa file rác

## Kết thúc

Báo cáo với anh Thiên bằng tiếng Việt:
- Trang web đã chạy đúng DTH mới chưa? (URL test + content)
- BCT test account đã tạo chưa? (email + password)
- Còn vấn đề gì cần fix không?
