# Checklist triển khai dịch vụ gọi thợ DienMayHieu.com

## 1. Upload code lên host
- Sync toàn bộ thư mục `C:\Users\pcpv\OneDrive\Desktop\DTH` lên host `dienmayhieu.com` (public_html hoặc www).
- Đảm bảo file `.env` đã có trên host với đầy đủ token.
- Không commit `.env` lên git.

## 2. Kiểm tra database
- Đảm bảo DB `kwkrbcce_dienmayhieulapvo` đã tạo trên host.
- Đảm bảo user `kwkrbcce_baocao` có quyền read/write.
- Schema sẽ tự động migrate khi có request đầu tiên đến `api_master.php`.

## 3. Cấu hình webhook Telegram
Chạy 2 URL sau trên trình duyệt (thay `Anhthienvodich` bằng CRON_SECRET trong `.env`):

```
https://dienmayhieu.com/api_master.php?action=telegram_set_webhook&bot=worker&secret=Anhthienvodich
https://dienmayhieu.com/api_master.php?action=telegram_set_webhook&bot=report&secret=Anhthienvodich
```

Kết quả mong đợi:
```json
{"status":"success","message":"Webhook configured.","url":"https://dienmayhieu.com/api_master.php?action=telegram_webhook&bot=worker","role":"worker"}
```

Kiểm tra webhook info:
```
https://dienmayhieu.com/api_master.php?action=telegram_webhook_info&bot=worker
```

## 4. Kiểm tra bot health
```
https://dienmayhieu.com/api_master.php?action=health
```
Kết quả mong đợi: `telegram_worker.ok = true` hoặc `telegram_report.ok = true`.

## 5. Kiểm tra nhóm thợ
- Bot `@congvinh298_bot` phải được thêm vào nhóm `Thợ Điện Lạnh` (chat ID `-1004297747522`) với quyền gửi tin nhắn.
- Bot `@...` report phải được thêm vào nhóm báo cáo (chat ID `-1003754511106`).
- Nếu chưa có, thêm bot vào nhóm và đảm bảo nó là admin hoặc có quyền gửi tin.

## 6. Test tạo ca thử
Gọi API admin test:
```
https://dienmayhieu.com/api_master.php?action=admin_test_worker_job
```
(Yêu cầu đăng nhập admin hoặc gọi qua script server-side.)

Hoặc thử submit form trên trang chủ và kiểm tra nhóm thợ có báo ca không.

## 7. Test thợ nhận ca
- Trong nhóm thợ, tìm tin báo ca.
- Bấm "Nhận ca".
- Bot phải nhắn riêng cho thợ: SĐT khách, địa chỉ, tọa độ Google Maps.
- Nếu thợ chưa từng nhắn với bot, bot sẽ báo lỗi và ca sẽ quay lại trạng thái pending.
- Thợ cần bấm vào tên bot → "Nhắn tin" hoặc gửi `/start`, sau đó bấm "Nhận ca" lại.

## 8. Test xong ca
- Thợ bấm "Đã xong" trong tin nhắn riêng.
- Bot ghi nhận ca hoàn thành, tính phí nền tảng 15%.

## 9. Cấu hình cronjob
Thêm cronjob trên host để chạy các endpoint:

```bash
# Nhắc phí thợ 06:00 sáng thứ 2 hàng tuần
0 6 * * 2 curl -s "https://dienmayhieu.com/api_master.php?action=cron_worker_fee_notice&secret=Anhthienvodich" > /dev/null 2>&1

# Khóa thợ nợ phí 07:00 sáng thứ 3 hàng tuần
0 7 * * 3 curl -s "https://dienmayhieu.com/api_master.php?action=cron_worker_fee_lock&secret=Anhthienvodich" > /dev/null 2>&1

# Báo cáo ngày 22:00 hàng ngày
0 22 * * * curl -s "https://dienmayhieu.com/api_master.php?action=cron_baocao_ngay&secret=Anhthienvodich" > /dev/null 2>&1
```

## 10. Cấu hình SePay webhook (nếu dùng)
- Trong dashboard SePay, thêm webhook URL:
  `https://dienmayhieu.com/api_master.php?action=sepay_webhook`
- Header `Authorization: Apikey <SEPAY_API_KEY>`

## 11. Log & giám sát
- Kiểm tra error log PHP/host khi có lỗi.
- Các log quan trọng:
  - `[job_dispatch]` — gửi báo ca vào nhóm
  - `[job_claim_dm]` — nhắn riêng thợ
  - `[telegram]` — lỗi Telegram API

## 12. Rollback
Nếu có lỗi nghiêm trọng, file backup đã được tạo:
- `api/jobs.php.bak.20260707`
- `api/core.php.bak.20260707`
- `api_master.php.bak.20260707`
- `index.php.bak.20260707` (nếu có)
