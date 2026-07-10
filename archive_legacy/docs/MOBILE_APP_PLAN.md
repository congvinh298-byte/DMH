# Kế hoạch Mobile App — Gọi thợ Điện Máy Hiếu

> Dựa trên bộ source **Enatega Single Vendor Food Delivery** (`food-delivery-singlevendor-main`) để xây dựng app khách hàng + app thợ cho dienmayhieu.com.

## Tình trạng bộ source

- **Admin Dashboard**: React + Apollo + Argon UI, yêu cầu Node 14 (cũ).
- **CustomerApp**: React Native (Expo), Apollo Client v2, React Navigation v5.
- **RiderApp**: React Native (Expo), Apollo Client v2, React Navigation v5.
- **Backend trong source**: không có — Enatega backend là proprietary / trả phí. Ta sẽ dùng backend PHP DTH hiện tại.

## Trình tự tối ưu

### Phase 1 — Khảo sát & thiết kế API (tuần 1)
1. Inventory toàn bộ màn hình & query/mutation của RiderApp và CustomerApp.
2. Mapping chức năng gọi đồ ăn → gọi thợ:
   - Food / restaurant → dịch vụ sửa chữa (điện lạnh, điện nước, điện tử...).
   - Order → ca sửa chữa (`job_posts`).
   - Rider → thợ (`worker_profiles`).
   - Order status → `pending` → `assigned` → `accepted` → `completed`/`cancelled`.
3. Thiết kế REST API contract cho mobile (JSON), gồm:
   - Auth: đăng nhập/đăng ký khách hàng, đăng nhập thợ (bằng Telegram ID / SĐT / OTP).
   - Services: danh sách dịch vụ & bảng giá.
   - Jobs: tạo ca, theo dõi ca, hủy ca.
   - Worker: nhận ca, từ chối ca, cập nhật trạng thái, cập nhật GPS.
   - Payments: thanh toán Momo/VNPay/ZaloPay.
   - Notifications: đăng ký push token.
   - History/income: lịch sử ca & thu nhập tháng (thợ thực nhận).

### Phase 2 — Backend DTH cho mobile (tuần 1–2)
1. Thêm bảng `mobile_sessions` / JWT token handling.
2. Thêm API endpoints vào `api_master.php`:
   - `mobile_login_customer`, `mobile_register_customer`, `mobile_login_worker`.
   - `mobile_services`, `mobile_create_job`, `mobile_job_status`, `mobile_cancel_job`.
   - `mobile_worker_jobs`, `mobile_worker_claim_job`, `mobile_worker_update_status`, `mobile_worker_location`.
   - `mobile_register_push_token`.
   - `mobile_worker_history`, `mobile_worker_earnings`.
3. Bảo mật: rate limit, validate input, HTTPS, API key/secret cho app.
4. Push notification trigger từ Telegram bot → FCM/APNs khi có ca mới / ca được nhận / hoàn thành.

### Phase 3 — App thợ (RiderApp) (tuần 2–3)
1. Fork/copy RiderApp thành `WorkerApp`.
2. Cập nhật `app.json`: bundle ID, tên app, icon, splash, Google Maps key.
3. Thay Apollo Client bằng fetch wrapper gọi DTH API.
4. Điều chỉnh screens:
   - **Login**: đăng nhập bằng Telegram ID / SĐT + OTP hoặc mật khẩu.
   - **NewOrders** → danh sách ca chờ (`pending`).
   - **OrderDetail** → chi tiết ca, địa chỉ khách, bản đồ, nút Nhận ca / Đang đi / Đã xong / Hủy.
   - **Orders** → ca đã nhận / đang làm / lịch sử.
   - **Earnings** → thu nhập tháng (thợ thực nhận từ `tech_net_income`).
5. Tích hợp background location update khi thợ đang làm ca.
6. Tích hợp push notification.

### Phase 4 — App khách hàng (CustomerApp) (tuần 3–4)
1. Fork/copy CustomerApp thành `CustomerApp-DMH`.
2. Cập nhật `app.json`, branding, Apple Sign-In.
3. Thay Apollo bằng fetch wrapper gọi DTH API.
4. Điều chỉnh screens:
   - Chọn dịch vụ (điện lạnh, điện nước...).
   - Nhập địa chỉ / chọn trên bản đồ.
   - Tạo ca, ước tính giá.
   - Theo dõi trạng thái ca & thợ.
   - Thanh toán & đánh giá.

### Phase 5 — Push + Realtime (tuần 4)
1. Cấu hình Firebase project mới cho iOS/Android.
2. Tạo APNs auth key trong Apple Developer.
3. Upload `GoogleService-Info.plist` & `google-services.json`.
4. Backend gửi push qua FCM khi có sự kiện.
5. App polling 30s khi foreground, push khi background.

### Phase 6 — App Store (tuần 5)
1. Thay brand Enatega → Điện Máy Hiếu.
2. Tạo icon, splash, screenshots, preview video.
3. Privacy policy, terms, support URL.
4. Apple Developer Account, bundle ID, provisioning profile.
5. TestFlight internal testing.
6. Submit App Store.

## Lưu ý kỹ thuật quan trọng

- Node 14 là quá cũ. Cần nâng cấp Node lên 18–20 & Expo SDK lên ~49–51 để Apple không từ chối vì SDK cũ.
- Apollo Client v2 cũng cũ. Có thể giữ tạm nhưng nên migrate sang RTK Query / SWR / fetch wrapper.
- Apple bắt buộc **Sign in with Apple** nếu có đăng nhập xã hội.
- Thanh toán dịch vụ thực tại nhà được phép dùng gateway ngoài (không bắt buộc IAP), nhưng phải rõ ràng trong mô tả app.
- Không hardcode API key. Dùng `app.config.js` / EAS secrets.

## Bước tiếp theo

Bắt đầu **Phase 1**: inventory chi tiết RiderApp & CustomerApp, sau đó viết file `MOBILE_API_CONTRACT.md`.
