# API Contract — Mobile App Gọi Thợ

> Tất cả endpoint dưới đây gọi qua `https://dienmayhieu.com/api_master.php?action={ACTION}`. Body là JSON. Response JSON với `status: success/error`.

## Auth

### Worker

#### `mobile_worker_login`
- **Method**: POST
- **Body**: `{ "worker_id": 8729878070, "otp": "123456" | "pin": "1234" }`
- **Response**:
```json
{
  "status": "success",
  "token": "dmh_w_xxx",
  "worker": {
    "worker_id": 8729878070,
    "name": "HỘ KINH DOANH ĐIỆN MÁY HIẾU",
    "phone": "090...",
    "role": "worker",
    "is_admin": 0
  }
}
```
- **Ghi chú**: OTP gửi qua Telegram bot thợ; PIN do admin cấp.

#### `mobile_worker_profile`
- **Method**: GET/POST với header `Authorization: Bearer {token}`
- **Response**: profile worker + tổng thu nhập tháng hiện tại.

### Customer

#### `mobile_customer_register`
- **Body**: `{ "phone": "0901234567", "otp": "123456", "name": "Anh Vinh", "apple_id?": "...", "google_id?": "..." }`
- **Response**: token + user.

#### `mobile_customer_login`
- **Body**: `{ "phone": "0901234567", "otp": "123456" }`
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
- **Body**:
```json
{
  "token": "dmh_c_xxx",
  "service_type": "dien_lanh",
  "issue_description": "Máy lạnh không lạnh",
  "customer_name": "Anh Vinh",
  "customer_phone": "0901234567",
  "address": "ấp Mỹ Hòa, Lấp Vò, Đồng Tháp",
  "map_lat": 10.3574,
  "map_lng": 105.5221,
  "images": ["base64...", "base64..."],
  "preferred_time": "2026-07-09 14:00"
}
```
- **Response**: `{ "job_id": 42, "platform_fee": 22500, "estimated_net": 127500, "status": "pending" }`

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
  "images": ["base64..."]
}
```
- **Response**: success + tổng thu nhập cập nhật.

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

## Thanh toán

#### `mobile_payment_create`
- **Body**:
```json
{
  "token": "dmh_c_xxx",
  "job_id": 42,
  "method": "momo|vnpay|zalopay|bank_transfer",
  "amount": 150000
}
```
- **Response**: `{ "payment_url": "...", "transaction_id": "..." }`

#### `mobile_payment_status`
- **Query**: `?token=...&transaction_id=...`
- **Response**: `{ "status": "pending|paid|failed" }`

## Địa chỉ (Customer)

#### `mobile_addresses_list` / `mobile_address_create` / `mobile_address_delete`
- CRUD địa chỉ khách hàng.

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
