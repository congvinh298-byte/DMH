# THÔNG TIN DỰ ÁN VÀ QUY ƯỚC LẬP TRÌNH BẮT BUỘC (AI SYSTEM PROMPT)

## 1. Bối cảnh dự án (Project Context)
- **Tên dự án:** Hệ Sinh Thái Lấp Vò - ĐIỆN MÁY HIẾU.
- **Mục tiêu cốt lõi:** Xây dựng một nền tảng Marketplace (sàn giao dịch trực tuyến) quy mô lớn đa dịch vụ (Vận tải, Sửa chữa, Lao động thời vụ, Viễn thông, Bảo hiểm...). Mục đích không phải là mở cửa hàng bán lẻ hay tự đi tìm shipper, mà là tạo ra một hệ sinh thái kết nối để **tạo ra nhiều cơ hội việc làm hơn cho cộng đồng địa phương**.
- **Chiến lược Web-First:** Toàn bộ hệ thống hoạt động hoàn toàn trên môi trường Web (dienmayhieu.com), không sử dụng và phát triển ứng dụng di động riêng biệt (Mobile App). Mọi thao tác của khách hàng và đối tác đều thông qua trình duyệt web với phương thức **đăng nhập bằng mã QR** làm chủ đạo, giúp lược bỏ rào cản "phải cài app".
- **Văn phong giao diện (Tone & Voice):** Gần gũi, dân dã, chân chất của người miền Tây. Sử dụng các từ ngữ như: "bà con", "ní", "mắng vốn", "nghen", "dạ", "chọt ngón tay". Tuyệt đối không dùng ngôn ngữ máy móc, khô khan khi viết text hiển thị cho người dùng.

## 2. Quy ước Giao diện (UI/UX & CSS)
- **Thư viện sử dụng:** Chỉ dùng HTML thuần, CSS thuần, Bootstrap 5.3 (qua CDN), FontAwesome 6.4 (Icon), Leaflet (Bản đồ), jsQR (Quét mã).
- **Màu sắc chuyên biệt:** Mỗi một dịch vụ trên nền tảng phải có một bộ 3 màu sắc đại diện (Background, Border, Text). Các biến này phải được khai báo trong `:root`. 
  - *Ví dụ hiện tại:* `--color-xe`, `--border-xe`, `--text-xe`; `--color-tho`, `--border-tho`, `--text-tho`...
  - Khi thêm dịch vụ mới, bắt buộc phải tạo 3 biến CSS màu mới tương ứng.
- **Cấu trúc Form:** Mỗi dịch vụ nằm trong một `<div class="form-section">` (mặc định ẩn bằng CSS `display: none;`), có nút `btn-back` để quay lại `#main-menu`.
- **Thành phần UI:** Sử dụng `.input-group` kết hợp icon của FontAwesome cho mọi ô nhập liệu. Nút submit sử dụng class `.btn-submit`.

## 3. Quy ước Đặt tên (Naming Conventions)
Để DOM không bị xung đột, mỗi dịch vụ **bắt buộc** phải có một tiền tố (prefix) ID riêng biệt cho toàn bộ thẻ HTML và biến JS bên trong nó:
- Form Gọi Xe: `gx_` (VD: `gx_phone`, `gx_note`)
- Form Gọi Thợ: `gt_` (VD: `gt_address`, `gt_type`)
- Form Lao Động: `ld_` (VD: `ld_hours`, `ld_people`)
- Form Tư Vấn: `tv_` 
- Form Viễn Thông: `vt_`
- Form Bảo Hiểm: `bhxh_`
- Form Chợ Đồ Cũ: `kho_`
- Form Bảo Hành: `bh_`
- Form Mua Sắm: `ms_`
> **LUẬT:** Bất kỳ module nào tạo mới cũng phải định nghĩa một tiền tố 2-3 chữ cái mới và tuân thủ chặt chẽ.

## 4. Quy ước Logic & JavaScript
- **KHÔNG sử dụng jQuery.** Viết bằng Vanilla JavaScript 100%.
- **Giao tiếp API:** Sử dụng `fetch()` để gửi dữ liệu tới `api.php` hoặc `api_xxx.php`. Dữ liệu form phải được đóng gói bằng `FormData` trước khi gửi.
- **Tính toán giá cả:** Hàm tính giá phải hiển thị kết quả ngay lập tức khi người dùng thay đổi Input (bắt sự kiện `oninput` hoặc `onchange`). Giá tiền hiển thị phải được format chuẩn Việt Nam (VD: `1.000.000đ`).
- **Lưu trữ cục bộ:** 
  - Lịch sử đơn hàng lưu ở `localStorage` với key `hieu_orders`.
  - Khách hàng VIP lưu ở `localStorage` với key `kh_vip_logged_in`.
  - Đối tác/CTV lưu ở `sessionStorage` với key `ctv_logged_in`.
- **Quản lý trạng thái hiển thị:** Dùng hàm `showForm('id_form')` để hiện form cần thiết và ẩn các form còn lại, ẩn `headerSection` và `main-menu`. Dùng `showMenu()` để quay về trang chủ.

## 5. Quy trình cho AI khi thêm tính năng/dịch vụ mới
Khi được yêu cầu tạo thêm một form dịch vụ mới, AI phải thực hiện đầy đủ 4 bước sau mà không phá vỡ code hiện tại:
1. Tạo bộ 3 biến màu sắc mới trong CSS `:root` và các class CSS `.item-[prefix]`, `.submit-[prefix]`.
2. Thêm HTML của dịch vụ đó vào grid `#main-menu`.
3. Tạo thẻ `<div id="form-[tên]" class="form-section">` chứa cấu trúc UI chuẩn với tiền tố ID riêng biệt.
4. Viết các hàm JS phục vụ việc tính toán riêng của form đó và tích hợp nhánh xử lý dữ liệu mới vào hàm tổng `submitDichVu(loaiForm)`.