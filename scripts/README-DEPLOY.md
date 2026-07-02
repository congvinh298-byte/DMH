# Deploy dienmayhieu.com (DTH) → VinaHost

Hai cach deploy code len hosting VinaHost.

## Cach 1: GitHub Actions (khuyen dung, tu dong)

Workflow da co san tai `.github/workflows/deploy.yml`.

### Setup 1 lan:
1. Vao GitHub repo: https://github.com/congvinh298-byte/DTH
2. **Settings → Secrets and variables → Actions → New repository secret**
3. Tao 3 secrets:
   - `FTP_SERVER` = `ftp.dienmayhieu.com` (hoac IP host: `vdc-whm-cheaphosting-1112.vinahost.org`)
   - `FTP_USERNAME` = `kwkrbcce` (cPanel user) — hoac Tao FTP account rieng trong cPanel > FTP Accounts
   - `FTP_PASSWORD` = password FTP (KHONG phai cPanel password)

### Lay FTP password:
Vao cPanel VinaHost > **FTP Accounts** > Tao account moi:
- Login: `dth@congvinh298-byte` (vi du)
- Password: tu dat
- Directory: `public_html/` (cho full access) hoac sub-folder
- Quota: Unlimited
- Save → copy password lai → paste vao GitHub Secret

### Deploy:
```bash
git push origin main
```
→ GitHub Actions se tu dong upload code moi len host.

---

## Cach 2: Script PowerShell local (de test/thu cong)

Dung khi:
- Can deploy nhanh khong qua GitHub
- Dang test FTP credentials
- GitHub Actions bi loi

### Chay:
```powershell
cd C:\Users\pcpv\OneDrive\Desktop\DTH
.\scripts\deploy-vinahost.ps1 `
    -FtpServer "ftp.dienmayhieu.com" `
    -FtpUser "dth@congvinh298-byte" `
    -FtpPassword "your_ftp_password" `
    -RemotePath "/public_html"
```

### Output:
- Tao folder staging trong %TEMP%
- Loai bo .git, .env, dev tools
- Nen thanh `deploy-dienmayhieu.zip`
- Upload len `/public_html/deploy-dienmayhieu.zip`
- Sau do can vao cPanel > File Manager > Extract zip

---

## Cach 3: cPanel File Manager (thu cong, an toan nhat)

1. May local: Nen thanh file zip (VD: `dienmayhieu-deploy.zip`)
2. cPanel > **File Manager** > `public_html/`
3. **Upload** > chon file zip > Upload
4. Click phai file zip > **Extract**
5. Move files ra root neu can
6. Xoa file zip

---

## Checklist sau khi deploy

- [ ] File `.env` tren host co dung thong tin that (DB_PASS, BOT_TOKEN, ...)
- [ ] `.htaccess` duoc upload
- [ ] Database `kwkrbcce_Choxalapvo` (co the doi ten thanh `dienmayhieu` sau) da duoc cap nhat schema tu `database/schema.sql` hoac migration tu dong qua index.php.
- [ ] Test: `https://dienmayhieu.com` load trang chu
- [ ] Test: `https://dienmayhieu.com/pages/dieu-khoan-su-dung.php`
- [ ] Test: `https://dienmayhieu.com/pages/gioi-thieu.php`
- [ ] Test admin: `https://dienmayhieu.com/admin/login.php` voi `qlhdtmdt@gmail.com / Admin@123`
- [ ] SSL: Vao cPanel > SSL/TLS Status > run AutoSSL neu chua co HTTPS

---

## Troubleshooting

| Loi | Nguyen nhan | Fix |
|---|---|---|
| `530 Login authentication failed` | Sai FTP user/pass (cPanel pass ≠ FTP pass) | Tao FTP account rieng trong cPanel |
| `Connection timed out` | Firewall block port 21 | Dung passive mode (FTPS - port 990) hoac nho VinaHost mo port |
| Upload xong nhung web van SPA cu | .htaccess cua host dang fallback ve public/ hoac public_html/index.html | Xoa SPA cu, upload .htaccess moi |
| 500 Internal Server Error | Sai permission hoac .htaccess loi | Check file permission (644 cho file, 755 cho folder), xem error_log |
| Trang trang return 404 | .htaccess chua duoc deploy hoac sai rewrite | Verify .htaccess da o root public_html/ |

---

## File lien quan

- `.github/workflows/deploy.yml` — GitHub Actions deploy workflow
- `scripts/deploy-vinahost.ps1` — Local PowerShell deploy script
- `.htaccess` — Apache config (HTTPS, rewrite, cache)
- `.env.example` — Mau env (KHONG co secrets that)
- `database_migration.sql` — Schema database can import

---
*Cap nhat: 24/06/2026*
