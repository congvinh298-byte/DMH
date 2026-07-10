# Prompt cho Codex - DTH (dienmayhieu.com) - CONTINUE

> **Copy toàn bộ text dưới đây và gửi cho Codex trong VSCode/Antigravity**

---

Codex ơi, **tiếp tục** xử lý DTH project. Anh Vinh vừa reset máy, nếu session cũ bị treo thì làm lại từ đầu các bước dưới đây.

## Tình hình (24/06/2026 - 13:10)

- **Workflow deploy** đã được update (có `danger-clean: true` + `server-dir: /home/kwkrbcce/public_html/`) trong commit trước
- **Code DTH mới** đã lên GitHub
- **Web `dienmayhieu.com` vẫn đang serve SPA cũ** "Chợ Xã Lấp Vò" 401KB - vấn đề này em đã verify bằng `Invoke-WebRequest` từ máy

## Bạn cần làm (theo thứ tự)

### Bước 1: CHECK workflow mới nhất
- Vào `https://github.com/congvinh298-byte/DTH/actions`
- Xem workflow mới nhất có chạy không, có lỗi gì không
- Nếu workflow chưa được update `server-dir` → sửa `.github/workflows/deploy.yml`:

```yaml
- name: 📂 Sync files
  uses: SamKirkland/FTP-Deploy-Action@v4.3.5
  with:
    server: ${{ secrets.FTP_SERVER }}
    username: ${{ secrets.FTP_USERNAME }}
    password: ${{ secrets.FTP_PASSWORD }}
    protocol: ftp
    port: 21
    local-dir: ./
    server-dir: /home/kwkrbcce/public_html/   # ← QUAN TRỌNG: trỏ thẳng vào public_html
    state-name: .ftp-deploy-state.json
    danger-clean: true                          # ← Xóa file cũ trên host
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
      .ftp-deploy-state.json
      node_modules/**
      vendor/**
      PROMPT_FOR_CODEX.md
```

### Bước 2: Commit + Push + Trigger workflow
```bash
git add .github/workflows/deploy.yml
git commit -m "fix: server-dir + danger-clean for cPanel deploy"
git push origin main
```

Sau đó trigger manual:
```bash
gh workflow run deploy.yml --ref main
```
(Nếu không có `gh` CLI thì dùng GitHub UI: Actions → Deploy to cPanel via FTP → Run workflow)

### Bước 3: Verify kỹ - ĐỪNG TIN BÁO CÁO CŨ

**Verify chính xác bằng PowerShell:**
```powershell
$r = Invoke-WebRequest -Uri "https://dienmayhieu.com" -UseBasicParsing -MaximumRedirection 0
Write-Host "Length: $($r.Content.Length)"
$r.Content.Substring(0, 500)
```

**Kết quả mong đợi:**
- Length phải khác `401502` (size SPA cũ)
- Phải thấy "Điện Tử Hiếu" hoặc nội dung DTH mới

**Nếu vẫn 401502 bytes:**
- Check xem `server-dir` trong workflow có đúng `/home/kwkrbcce/public_html/` không
- Check xem GitHub Secrets có đúng không (FTP_SERVER, FTP_USERNAME, FTP_PASSWORD)
- Thử truy cập file cụ thể: `https://dienmayhieu.com/index.php` (không phải `/`) - xem content

### Bước 4: Test 8 trang pháp lý
```powershell
$pages = @("dieu-khoan-su-dung", "chinh-sach-bao-mat", "quy-che-hoat-dong", "huong-dan-mua-hang", "lien-he", "gioi-thieu", "huong-dan-ban-hang", "giai-quyet-tranh-chap")
foreach ($p in $pages) {
    $r = Invoke-WebRequest -Uri "https://dienmayhieu.com/pages/$p.php" -UseBasicParsing
    Write-Host "$p.php: $($r.Content.Length) bytes"
}
```

**Mỗi trang phải trả về content length KHÁC 401502 (và làm content riêng của nó, không phải SPA cũ).**

### Bước 5: Seed BCT test account
- File `scripts/seed-bct-test-account.php` đã có sẵn
- Cần cách chạy trên host. Có 2 options:
  - **Option A:** Tạo file `seed-bct-once.php` ở root, tự xóa sau khi chạy
  - **Option B:** Dùng cPanel API `execute/Fileman/upload_files` (đã test hoạt động) để upload 1 file PHP chạy seed
- Chạy seed: `https://dienmayhieu.com/seed-bct-once.php`
- Sau khi chạy xong, **XÓA file seed ngay** (security)

### Bước 6: Báo cáo cuối

In ra cho anh Thiên:
1. ✅/❌ Workflow deploy status
2. ✅/❌ Web DTH mới load đúng (size != 401502, content có "Điện Tử Hiếu")
3. ✅/❌ 8 trang pháp lý (bảng kết quả)
4. ✅/❌ Seed BCT account (2 email + password)
5. Còn vấn đề gì cần fix

## Lưu ý quan trọng

- **ĐỪNG BÁO "XONG"** nếu chưa verify bằng `Invoke-WebRequest` thực tế
- **KHÔNG PUSH** file `.env` thật, password, hay secrets
- Nếu workflow fail, **đọc log chi tiết** trong GitHub Actions
- Nếu FTP user bị jail, **báo anh Thiên ngay** để xử lý bằng cách khác

## Bắt đầu

Codex, bắt đầu từ **Bước 1** và làm tuần tự. Báo cáo sau mỗi bước. Khi xong 6 bước thì in báo cáo cuối cùng.
