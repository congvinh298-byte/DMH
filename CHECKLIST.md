# CHECKLIST - Xay dung lai dienmayhieu.com (Cho Xa Lap Vo Online)

## PHAN 1: TRANG PHAP LY (Bat buoc)
- [x] Tao thu muc pages/ va pages/inc/ (header.php, footer.php)
- [x] /pages/dieu-khoan-su-dung - Dieu khoan su dung
- [x] /pages/chinh-sach-bao-mat - Chinh sach bao mat
- [x] /pages/quy-che-hoat-dong - Quy che hoat dong san TMDT
- [x] /pages/huong-dan-mua-hang - Huong dan mua hang
- [x] /pages/huong-dan-ban-hang - Huong dan ban hang (cho tho)
- [x] /pages/giai-quyet-tranh-chap - Quy trinh giai quyet tranh chap
- [x] /pages/lien-he - Thong tin lien he
- [x] /pages/gioi-thieu - Gioi thieu chu website
- [x] Cap nhat .htaccess de routing /pages/xxx
- [x] Cap nhat footer trong index.php de link den cac trang moi

## PHAN 2: FOOTER (Bat buoc)
- [x] Ten chu website: CONG TY TNHH MTV DIEN MAY HIEU
- [x] MST/CCCD: 1402228630
- [x] Dia chi: 166, Ap Binh Thanh 1, Xa Lap Vo, Huyen Lap Vo, Dong Thap
- [x] SDT ho tro: 0979.553.289
- [x] Email lien he: Congvinh28@gmail.com
- [x] Nguoi dai dien: Tran Cong Vinh
- [x] Ngay cap MST: 10/08/2024, Bo Cong An
- [ ] Logo Bo Cong Thuong (placeholder - se gan sau khi dang ky)

## PHAN 3: TINH NANG THIEU
- [ ] Dang ky/dang nhap (JWT auth) - 2 role: nguoi mua + nguoi ban (tho)
- [ ] Quan ly gian hang - tho dang/sua/xoa san pham
- [ ] Gio hang + checkout (co thanh toan COD + chuyen khoan)
- [ ] Chat realtime giua nguoi mua va tho
- [ ] Danh gia/review sau giao dich (1-5 sao + comment)
- [ ] Lich su don hang + trang thai (cho xac nhan -> dang giao -> hoan thanh)
- [ ] Thong bao don hang (email)
- [ ] Search + filter san pham theo danh muc

## PHAN 4: UI/UX
- [ ] Responsive mobile-first
- [ ] Dark mode support
- [ ] Loading states
- [ ] Error handling
- [ ] Toast notifications
- [x] Vietnamese language only (da ap dung)

## PHAN 5: HO SO BO CONG THUONG (CV0014)
- [x] De an cung cap dich vu TMDT (/pages/de-an-dich-vu.php + Ho_so_BCT/De_an_cung_cap_dich_vu_TMDT.md)
- [x] Quy che hoat dong (/pages/quy-che-hoat-dong + Ho_so_BCT/Quy_che_hoat_dong.md)
- [x] Chinh sach bao mat (/pages/chinh-sach-bao-mat)
- [x] Co che giai quyet tranh chap (/pages/giai-quyet-tranh-chap)
- [x] Thong tin chu so huu website (/pages/lien-he + footer)
- [x] Tai lieu huong dan nop ho so (README-BOCONGTHUONG.md)
- [x] Cap nhat thong tin nguoi dai dien: Tran Cong Vinh
- [x] Cap nhat MST: 1402228630 (cap 10/08/2024, Bo Cong An)
- [x] Cap nhat email: Congvinh28@gmail.com
- [x] Banner thu nghiem tren trang chu
- [ ] Bo sung hinh anh minh hoa cho cac trang phap ly
- [ ] In de an ra PDF de nop ho so

---

*Cap nhat: 23/06/2026*

## UPDATE 24/06/2026 - BO SUNG SAU REVIEW

### A. Cleanup & sync GitHub
- [x] Xoa 37 file rac (deployment_backups, gemini-code, fix_*, alter_*, src/ stubs) -> commit 68ccb8a
- [x] Push len origin/main thanh cong

### B. Deploy tools
- [x] Tao scripts/deploy-vinahost.ps1 (PowerShell local deploy qua FTP)
- [x] Tao scripts/README-DEPLOY.md (3 cach deploy: GitHub Actions / PowerShell / cPanel manual)
- [x] GitHub Actions workflow da co san (.github/workflows/deploy.yml) - can setup secrets

### C. Ho so BCT - PDF
- [x] Convert De_an_cung_cap_dich_vu_TMDT.md -> .pdf (249KB)
- [x] Convert Quy_che_hoat_dong.md -> .pdf (133KB)
- [x] Convert CV0014_Yeu_cau_chinh_sua_bo_sung.md -> .pdf (101KB)
- [x] Tool: npx md-to-pdf voi pdf-config.json
- [x] Day len GitHub: commit fdba3a5

### D. Phase 3 - Tinh nang marketplace
- [x] Code audit: 7/8 tinh nang da co san (auth, products, orders, reviews, chat, search, email)
- [x] Tao api/cart.php (10KB - 6 actions: add/list/update/remove/clear/checkout)
- [x] Tao scripts/seed-bct-test-account.php (seed user qlhdtmdt@gmail.com/Admin@123)
- [x] Tao docs/PHAN-3-ROADMAP.md (ke hoach 5 phases)
- [x] Day len GitHub: commit 229157a
- [ ] Chay seed script tren host sau khi deploy
- [ ] Test cart API end-to-end
- [ ] Build UI gio hang tren index.php
- [ ] Phase 2: JWT wrapper (thay the login_key)
- [ ] Phase 4-5: UI polish + auto BCT report

