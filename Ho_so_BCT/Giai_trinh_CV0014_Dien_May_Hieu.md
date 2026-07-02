# GIẢI TRÌNH ĐỐI CHIẾU CV0014 – WEBSITE ĐIỆN MÁY HIẾU

**Ngày lập:** 02/07/2026  
**Chủ sở hữu website:** Trần Công Vinh (Điện Máy Hiếu)  
**Tên miền:** https://dienmayhieu.com  
**Mã số thuế:** 1402228630  
**Địa chỉ:** 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp  
**Điện thoại:** 0979.553.289

---

## 1. Nội dung yêu cầu của Bộ Công Thương (CV0014)

Trang web cũ **Chợ Lấp Vò Online** bị xác định là có dấu hiệu của **sàn giao dịch thương mại điện tử / nền tảng kết nối nhiều cửa hàng**, chưa đáp ứng điều kiện để vận hành theo quy định. Do đó, chủ thể phải:

- Hoặc **tạm ngừng / đặt chế độ demo mật khẩu** cho đến khi hoàn thiện hồ sơ.  
- Hoặc **chỉnh sửa triệt để** để chứng minh website **không phải là sàn giao dịch TMĐT đa cửa hàng**.

---

## 2. Định tính pháp lý sau chỉnh sửa

Căn cứ:

- **Nghị định 52/2013/NĐ-CP** (sửa đổi, bổ sung bởi **Nghị định 85/2021/NĐ-CP**)  
- **Thông tư 01/2022/TT-BCT** hướng dẫn chi tiết.

Theo đó:

> **Sàn giao dịch thương mại điện tử** là trang thông tin điện tử do tổ chức, cá nhân thiết lập để **cung cấp môi trường cho các thương nhân, tổ chức, cá nhân khác** tiến hành hoạt động mua bán hàng hóa, cung ứng dịch vụ.

Ngược lại:

> **Website thương mại điện tử bán hàng / cung cấp dịch vụ** là trang thông tin điện tử do tổ chức, cá nhân thiết lập để **trực tiếp bán hàng hóa, cung ứng dịch vụ của chính mình** cho người tiêu dùng/khách hàng.

Sau khi chỉnh sửa, **dienmayhieu.com** chỉ giới thiệu và bán hàng/cung cấp dịch vụ **do chính Trần Công Vinh (Điện Máy Hiếu) thực hiện**, không cho phép thương nhân/tổ chức/cá nhân khác đăng ký gian hàng, đăng bán sản phẩm hay cung ứng dịch vụ trên website. Do đó, website thuộc nhóm **website TMĐT bán hàng / cung cấp dịch vụ TMĐT của cá nhân**, **không phải sàn giao dịch TMĐT**.

---

## 3. Các thay đổi đã thực hiện trên mã nguồn

### 3.1. Nhận diện thương hiệu

| Trước | Sau |
|-------|-----|
| Chợ Lấp Vò Online | **Điện Máy Hiếu** |
| Logo cũ | **LOGO.svg** mới cho Điện Máy Hiếu |
| Mục đích: nền tảng kết nối đa cửa hàng/xã | Mục đích: website cá nhân của cửa hàng Điện Máy Hiếu |

Đã cập nhật:

- `index.php`, `admin_xxx.php`, `admin/db_screenshot.php`, `api/core.php` (trang test), `assets/js/main.js`, `api/shipping.php`, `api/notify.php`, `api/openclaw_chat.php`.
- Header/footer chung: `pages/inc/header.php`, `pages/inc/footer.php`, `_legal_header.php`, `_legal_footer.php`.
- Logo mới: `LOGO.svg`.

### 3.2. Dịch vụ được giữ lại / loại bỏ

| Dịch vụ | Kết quả |
|---------|---------|
| **Gọi thợ** (dịch vụ kỹ thuật) | **Giữ lại** |
| Vệ sinh máy lạnh, nệm, sofa, thảm | **Giữ lại** |
| Sửa chữa/lắp đặt điện máy, điện thoại | **Giữ lại** |
| Rửa xe/kiểm tra điều hòa ô tô | **Giữ lại** |
| **Gọi xe / vận chuyển** | **Đã xóa toàn bộ** |
| **Quay phim / chụp ảnh / flycam / photobooth** | **Đã xóa toàn bộ** |

Đã xóa:

- Tab/filter `Gọi xe`, `Quay + Chụp` trong `index.php`.
- Route/price map logic cho xe/phương tiện.
- Form submit branch xe/phương tiện.
- Danh mục dịch vụ xe/drone trong `api/jobs.php`.
- Role `bike`, `drone`, `vendor` trong `api/core.php`, `api/workers.php`, `api/notify.php`.
- Các Telegram bot/chat cho xe/drone: `BOT_BIKE_TOKEN`, `BOT_DRONE_TOKEN`, `BIKE_CHAT_ID`, `DRONE_CHAT_ID` trong `.env` và `.env.example`.
- Xóa folder `photobooth/` và `uploads/photobooth/`.
- Xóa trang `vendor.php` và các API vendor (`vendor_get_orders`, `vendor_update_order_status`, `vendor_close_shift`, `cron_vendor_daily_closing`, `app_store_register`).

### 3.3. Các module QR được giữ nguyên

- **QR.png**: link truy cập website.
- **QR_THANH_TOAN.jpg**: thanh toán.
- **QR_DANH_GIA.png**: đánh giá.
- **QR khuyến mãi / bảo hành / hóa đơn** trong tài khoản khách hàng.

### 3.4. Trang pháp lý và điều khoản

Đã viết lại hoàn toàn để phản ánh mô hình **cá nhân – chủ sở hữu duy nhất**:

- `pages/gioi-thieu.php`
- `pages/quy-che-hoat-dong.php`
- `pages/dieu-khoan-su-dung.php`
- `pages/giai-quyet-tranh-chap.php`
- `pages/chinh-sach-bao-mat.php`
- `pages/lien-he.php`
- `pages/de-an-dich-vu.php`
- `pages/huong-dan-mua-hang.php`
- `pages/chinh-sach-doi-tra.php`, `chinh-sach-thanh-toan.php`, `chinh-sach-van-chuyen.php`
- Modal pháp lý trong `index.php` (`quyche`, `baomat`, `dean`).

Đã xóa:

- `pages/huong-dan-ban-hang.php` (không còn nhiều cửa hàng để hướng dẫn bán hàng).

### 3.5. Cơ sở dữ liệu

- Xóa bảng `partners` khỏi `database/schema.sql`.
- Xóa comment “ĐỐI TÁC” trong bảng `workers`.
- Tên DB vận hành vẫn tạm giữ `kwkrbcce_Choxalapvo` trên host để tránh đứt kết nối; việc đổi tên DB sẽ thực hiện riêng khi triển khai chính thức.

### 3.6. Tài liệu cũ đã lưu trữ

Các tài liệu thuộc mô hình marketplace cũ đã được chuyển vào `archive/` để lưu trữ, không còn xuất hiện như tài liệu hiện hành của website:

- `QuyUoc_DuAn.md.old`
- `README-BOCONGTHUONG.md.old`
- `CHECKLIST.md.old`
- `De_an_cung_cap_dich_vu_TMDT.html.old`
- `De_an_cung_cap_dich_vu_TMDT.md.old`
- `Quy_che_hoat_dong.html.old`
- `Quy_che_hoat_dong.md.old`
- `database_migration.sql.old`
- `PROMPT_FOR_CODEX.md.old`
- `PROMPT_FOR_CODEX_V2.md.old`

---

## 4. Điểm khác biệt then chốt so với sàn giao dịch TMĐT

| Tiêu chí của sàn TMĐT | Tình trạng trên dienmayhieu.com sau chỉnh sửa |
|-----------------------|-----------------------------------------------|
| Có chức năng đăng ký gian hàng cho bên thứ ba | **Không còn**. Đã xóa `vendor.php` và API `app_store_register`. |
| Có trang quản lý riêng cho từng cửa hàng đối tác | **Không còn**. Chỉ còn một trang admin duy nhất của chủ sở hữu. |
| Nhiều cửa hàng/đối tác cùng bán trên một website | **Không còn**. Website chỉ bán hàng/dịch vụ của Điện Máy Hiếu. |
| Hoa hồng/phí nền tảng trên giao dịch của bên thứ ba | **Không còn**. Không có cơ chế chia sẻ doanh thu với bên thứ ba. |
| Có dịch vụ trung gian kết nối nhiều bên (gọi xe, quay chụp đa đối tác) | **Không còn**. Chỉ còn dịch vụ gọi thợ trực tiếp của chủ sở hữu. |

---

## 5. Kiểm thử và cam kết

- Đã kiểm tra **syntax PHP toàn bộ file** trong dự án: không còn lỗi cú pháp.
- Đã kiểm tra toàn bộ file thực thi (PHP/JS/HTML) và không còn references đến `gọi xe`, `quay phim`, `bike`, `drone`, `vendor portal`, `gian hàng`, `đối tác`, `Chợ Lấp Vò Online` trong phần runtime.
- Các biến môi trường/token Telegram cho xe/drone đã được xóa khỏi `.env` và `.env.example`.
- Tên miền chính thức vẫn là `dienmayhieu.com`.

---

## 6. Đề nghị

Kính đề nghị Bộ Công Thương/Bộ phận tiếp nhận hồ sơ xem xét:

- Website **https://dienmayhieu.com** hiện tại là **website thương mại điện tử bán hàng / cung cấp dịch vụ TMĐT của cá nhân**, không thuộc phạm trù **sàn giao dịch thương mại điện tử** theo quy định.
- Do đó, website đã đáp ứng yêu cầu tại CV0014 và có thể **tiếp tục vận hành** sau khi bổ sung/gửi giải trình này.

Nếu cần bổ sung thêm minh chứng, code review, hoặc demo qua màn hình, chủ thể sẵn sàng phối hợp.

---

**Chủ sở hữu website:**

Trần Công Vinh  
Cửa hàng Điện Máy Hiếu  
Số điện thoại: 0979.553.289  
Email: qlhdtmdt@gmail.com  
Địa chỉ: 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp
