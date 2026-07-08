# API Contract — Mobile App Gọi Thợ

> Tất cả endpoint dưới đây gọi qua `https://dienmayhieu.com/api_master.php?action={ACTION}`. Body là JSON. Response JSON với `status: success/error`.

## Auth

### Worker

#### `mobile_worker_login`
- **Method**: POST
- **Body**: `{ "worker_id": 8729878070, "pin": "1234" }`
- **Response**:
```json
{
  "status": "success",
  "token": "DTHM...",
  "worker": {
    "worker_id": 8729878070,
    "name": "HỘ KINH DOANH ĐIỆN MÁY HIẾU",
    "phone": "090...",
    "role": "worker",
    "is_admin": 0
  }
}
```
- **Ghi chú**: PIN do thợ tự đặt lần đầu qua `mobile_worker_set_pin`. Nếu chưa có PIN, login trả về `code: PIN_NOT_SET`.

#### `mobile_worker_set_pin`
- **Method**: POST
- **Body**: `{ "worker_id": 8729878070, "pin": "1234", "confirm_pin": "1234" }`
- **Response**: token + worker (tương tự login).

#### `mobile_worker_profile`
- **Method**: GET/POST với header `Authorization: Bearer {token}`
- **Response**: profile worker + tổng thu nhập tháng hiện tại.

### Customer

#### `mobile_customer_send_otp`
- **Method**: POST
- **Body**: `{ "phone": "0901234567" }`
- **Response**: `{ "status": "success", "message": "OTP đã gửi" }`
- **Ghi chú**: Môi trường dev bật `MOBILE_OTP_MOCK=true` thì OTP luôn là `123456`. Production sẽ gửi qua SMS/Telegram.

#### `mobile_customer_register` / `mobile_customer_login`
- **Body**: `{ "phone": "0901234567", "otp": "123456", "name": "Anh Vinh" }` (`name` chỉ cần cho register)
- **Response**: token + user.

#### `mobile_customer_profile`
- **Auth**: Bearer token.
- **Response**: profile, addresses, lịch sử ca gần đây.

## Dịch vụ (Customer)

#### `mobile_services`
- **Method**: GET
- **Response**:
```json
{
  "status": "success",
  "categories": [
    { "id": "dien_lanh", "name": "Điện lạnh", "icon": "❄️", "base_price": 150000 },
    { "id": "dien_nuoc", "name": "Điện nước", "icon": "🚰", "base_price": 120000 }
  ]
}
```

## Ca sửa chữa

### Customer

#### `mobile_create_job`
- **Body** (các trường linh hoạt, backend chấp nhận nhiều alias):
```json
{
  "token": "dmh_c_xxx",
  "service_id": "dien_lanh",
  "title": "Máy lạnh không lạnh",
  "description": "Máy lạnh không lạnh, đã vệ sinh vẫn không đỡ",
  "customer_name": "Anh Vinh",
  "customer_phone": "0901234567",
  "address": "ấp Mỹ Hòa, Lấp Vò, Đồng Tháp",
  "lat": 10.3574,
  "lng": 105.5221,
  "scheduled_at": "2026-07-09 14:00",
  "images": ["base64...", "base64..."]
}
```
- **Alias được hỗ trợ**:
  - `service_id` / `service_type` / `service_name` / `selected_service_name`
  - `title` / `issue_description` / `description`
  - `scheduled_at` / `preferred_time`
  - `lat` / `map_lat`, `lng` / `map_lng`
- **Response**: 
```json
{
  "status": "success",
  "job_id": 42,
  "platform_fee": 22500,
  "estimated_net": 127500,
  "job_status": "pending",
  "job": { ... }
}
```

#### `mobile_customer_jobs`
- **Query**: `?token=...&status=pending|assigned|in_progress|completed|cancelled&limit=20&offset=0`
- **Response**: danh sách ca của khách.

#### `mobile_customer_job_detail`
- **Query**: `?token=...&job_id=42`
- **Response**: chi tiết ca, trạng thái, thợ phụ trách, GPS thợ, thanh toán.

#### `mobile_customer_cancel_job`
- **Body**: `{ "token": "...", "job_id": 42, "reason": "Không cần nữa" }`
- **Response**: success.

#### `mobile_customer_review_worker`
- **Body**: `{ "token": "...", "job_id": 42, "rating": 5, "comment": "Thợ nhiệt tình" }`
- **Response**: success.

### Worker

#### `mobile_worker_jobs_pending`
- **Auth**: Bearer token.
- **Response**: danh sách ca `pending` chưa ai nhận (gần theo vị trí nếu có).

#### `mobile_worker_jobs_assigned`
- **Auth**: Bearer token.
- **Query**: `?status=assigned|in_progress|completed`
- **Response**: danh sách ca của thợ.

#### `mobile_worker_claim_job`
- **Body**: `{ "token": "...", "job_id": 42 }`
- **Response**: success/error (ví dụ đã bị người khác nhận, hoặc bị block vì nợ phí).

#### `mobile_worker_update_status`
- **Body**:
```json
{
  "token": "...",
  "job_id": 42,
  "status": "in_progress|completed|cancelled",
  "note": "Đang trên đường",
  "amount": 150000,
  "images": ["base64..."]
}
```
- **Response**: success + job cập nhật.
- **Ghi chú**: `amount` chỉ dùng khi `status=completed` để tính lại giá thực tế.

#### `mobile_worker_location`
- **Body**: `{ "token": "...", "lat": 10.35, "lng": 105.52 }`
- **Response**: success.
- **Ghi chú**: Gọi định kỳ khi thợ đang làm ca.

## Thu nhập & lịch sử (Worker)

#### `mobile_worker_earnings`
- **Auth**: Bearer token.
- **Query**: `?month=2026-07`
- **Response**:
```json
{
  "status": "success",
  "month": "2026-07",
  "job_count": 12,
  "total_customer_price": 1530000,
  "total_platform_fee": 255000,
  "total_worker_income": 1275000
}
```

#### `mobile_worker_history`
- **Auth**: Bearer token.
- **Query**: `?month=2026-07`
- **Response**: chi tiết từng ca đã hoàn thành.

## Push notification

#### `mobile_register_push_token`
- **Body**: `{ "token": "...", "push_token": "ExponentPushToken[xxx]", "platform": "ios|android" }`
- **Response**: success.

## Thanh toán (Phase 4)
> `mobile_payment_create`, `mobile_payment_status` — chưa triển khai. Hiện tại khách thanh toán trực tiếp cho thợ hoặc qua VietQR/Momo khi hoàn thành.

## Địa chỉ (Phase 4)
> `mobile_addresses_list` / `mobile_address_create` / `mobile_address_delete` — chưa triển khai. Khách có thể nhập địa chỉ trực tiếp khi tạo ca.

## Response lỗi chuẩn
```json
{
  "status": "error",
  "message": "Mô tả lỗi",
  "code": "INVALID_TOKEN | JOB_NOT_FOUND | WORKER_BLOCKED | ..."
}
```

## Notes
- Bearer token do backend sinh và lưu trong bảng `mobile_sessions`.
- App gửi kèm `Authorization: Bearer {token}` hoặc `token` trong body/query.
- Push token được lưu để gửi thông báo qua FCM/APNs từ backend PHP.
- Upload ảnh dùng base64 để đơn giản; nếu nhiều ảnh lớn thì chuyển sang multipart sau.
