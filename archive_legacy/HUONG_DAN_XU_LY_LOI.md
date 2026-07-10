# HƯỚNG DẪN XỬ LÝ LỖI ĐĂNG NHẬP VÀ CLINE

## 🔴 VẤN ĐỀ 1: GOOGLE CLOUD CODE CHƯA XÁC THỰC

### Triệu chứng:
- Extension Google Cloud Code báo đỏ
- Thông báo "Cloud Code is not authenticated"
- Có thông báo "Once you have completed the login flow..."

### Cách xử lý:

#### Bước 1: Mở Terminal trong VSCode
- Bấm tổ hợp phím: `Ctrl + ` (phím backtick, bên trái số 1)
- Hoặc vào menu: View → Terminal

#### Bước 2: Tìm link đăng nhập
Trong Terminal, Sếp sẽ thấy một trong các dạng sau:
```
Go to the following link in your browser:
https://accounts.google.com/o/oauth2/auth?...

Your browser has been opened to visit:
https://accounts.google.com/o/oauth2/auth?...
```

#### Bước 3: Đăng nhập
1. **Click vào link** trong Terminal (hoặc Ctrl+Click)
2. Trình duyệt sẽ mở ra
3. **Chọn tài khoản Google** của Sếp
4. **Cho phép quyền truy cập** khi được hỏi
5. Sau khi thấy thông báo "Authentication successful", **đóng tab trình duyệt**

#### Bước 4: Reload VSCode
1. Bấm phím `F1`
2. Gõ: `Reload Window`
3. Nhấn `Enter`
4. VSCode sẽ tải lại và nhận xác thực mới

---

## 🔴 VẤN ĐỀ 2: CLINE BỊ LỖI KẾT NỐI API

### Triệu chứng:
Trong Console (Developer Tools) có lỗi:
```
FetchError: invalid json response body at https://api.anthropic.com/v1/messages
reason: Unexpected end of JSON input
```

### Nguyên nhân:
1. **Mạng internet không ổn định** - kết nối quốc tế bị chập chờn
2. **API Key hết hạn hoặc hết quota** - cần kiểm tra lại
3. **Proxy/Firewall chặn** - kết nối đến Anthropic bị chặn

### Cách xử lý:

#### Giải pháp 1: Kiểm tra mạng
1. **Test kết nối quốc tế:**
   - Mở Command Prompt (Win + R, gõ `cmd`)
   - Chạy lệnh: `ping 8.8.8.8`
   - Nếu có packet loss > 10% → mạng không ổn định

2. **Đổi DNS:**
   - Vào Settings → Network & Internet → Change adapter options
   - Chuột phải vào card mạng đang dùng → Properties
   - Chọn Internet Protocol Version 4 (TCP/IPv4) → Properties
   - Chọn "Use the following DNS server addresses":
     - Preferred: `8.8.8.8`
     - Alternate: `8.8.4.4`
   - OK và restart máy

#### Giải pháp 2: Chuyển sang DeepSeek API (KHUYẾN NGHỊ)

**DeepSeek** là giải pháp tốt hơn vì:
- ✅ Ổn định hơn với mạng Việt Nam
- ✅ Giá rẻ hơn nhiều so với Anthropic
- ✅ Hiệu năng mạnh mẽ (V3/V4 hoặc R1 Reasoner)

**Cách cấu hình DeepSeek cho Cline:**

1. **Mở Settings của Cline:**
   - Bấm `Ctrl + Shift + P`
   - Gõ: `Cline: Open Settings`
   - Hoặc click vào icon Cline ở thanh bên trái → Settings (biểu tượng bánh răng)

2. **Cấu hình DeepSeek:**

   **Bước 1: Chọn API Provider**
   - Tìm mục "API Provider"
   - Chọn: **DeepSeek** (nếu có sẵn)
   - Hoặc chọn: **OpenAI Compatible** (nếu Cline bản cũ không có DeepSeek)

   **Bước 2: Nhập Base URL** (nếu được yêu cầu)
   - Tìm mục "Base URL" hoặc "API Base URL"
   - Nhập: `https://api.deepseek.com/v1`
   - Hoặc: `https://api.deepseek.com/beta` (nếu dùng luồng beta)

   **Bước 3: Nhập API Key**
   - Tìm mục "API Key" hoặc "DeepSeek API Key"
   - Nhập API key riêng của Sếp, ví dụ: `sk-...`
   - ⚠️ **LƯU Ý:** API key là bí mật, không lưu key thật vào tài liệu dự án.

   **Bước 4: Chọn Model**
   - Tìm mục "Model ID" hoặc "Model"
   - Nhập một trong hai:
     - `deepseek-chat` → Dùng cho công việc thông thường (V3/V4 mạnh nhất)
     - `deepseek-reasoner` → Dùng khi cần suy luận siêu sâu (như R1)

3. **Lưu và Reload:**
   - Bấm "Save" hoặc settings tự động lưu
   - Bấm `F1` → gõ `Reload Window` → Enter
   - Cline sẽ khởi động lại với DeepSeek API

4. **Test thử:**
   - Mở Cline chat
   - Gửi tin nhắn test: "Hello, bạn đang dùng model gì?"
   - Nếu trả lời được → Thành công! ✅

---

#### Giải pháp 3: Nếu vẫn muốn dùng Anthropic

1. **Kiểm tra API Key:**
   - Vào: https://console.anthropic.com/
   - Đăng nhập bằng tài khoản đã tạo API key
   - Vào mục "API Keys" hoặc "Usage"
   - Kiểm tra:
     - API key còn hoạt động không?
     - Còn credit/quota không?
     - Có bị rate limit không?

2. **Nếu API key hết hạn/hết quota:**
   - Tạo API key mới tại: https://console.anthropic.com/settings/keys
   - Copy API key mới
   - Paste vào Settings của Cline
   - Reload VSCode (F1 → Reload Window)

#### Giải pháp 4: Thử lại kết nối

1. **Restart Cline:**
   - Bấm `Ctrl + Shift + P`
   - Gõ: `Developer: Reload Window`
   - Nhấn Enter

2. **Nếu vẫn lỗi, kiểm tra Console:**
   - Bấm `Ctrl + Shift + I` để mở Developer Tools
   - Chọn tab "Console"
   - Chụp màn hình lỗi chi tiết để debug

---

## 📋 CHECKLIST XỬ LÝ NHANH

### Cho vấn đề Google Cloud Code:
- [ ] Mở Terminal (Ctrl + `)
- [ ] Tìm link đăng nhập trong Terminal
- [ ] Click link và đăng nhập Google
- [ ] Reload VSCode (F1 → Reload Window)

### Cho vấn đề Cline API (KHUYẾN NGHỊ: Dùng DeepSeek):
- [ ] Mở Cline Settings (Ctrl + Shift + P → Cline: Open Settings)
- [ ] Chọn API Provider: **DeepSeek** (hoặc OpenAI Compatible)
- [ ] Nhập Base URL: `https://api.deepseek.com/v1`
- [ ] Nhập API Key riêng: `sk-...`
- [ ] Chọn Model: `deepseek-chat` hoặc `deepseek-reasoner`
- [ ] Reload VSCode (F1 → Reload Window)
- [ ] Test chat với Cline để kiểm tra

### Nếu vẫn muốn dùng Anthropic:
- [ ] Kiểm tra kết nối mạng (ping 8.8.8.8)
- [ ] Đổi DNS sang Google DNS (8.8.8.8)
- [ ] Vào console.anthropic.com kiểm tra quota
- [ ] Tạo API key mới nếu cần
- [ ] Reload VSCode

---

## 🆘 NẾU VẪN KHÔNG ĐƯỢC

### Cần cung cấp thông tin sau để debug:

1. **Chụp màn hình Terminal** - xem link đăng nhập chính xác
2. **Chụp màn hình Console** (Ctrl + Shift + I) - xem lỗi chi tiết
3. **Kiểm tra API Key status** - vào console.anthropic.com chụp màn hình phần Usage
4. **Test kết nối:**
   ```cmd
   curl -I https://api.anthropic.com
   ```
   Chụp kết quả lệnh này

---

## 💡 LƯU Ý QUAN TRỌNG

1. **API Key là bí mật** - không share cho ai
2. **DeepSeek rẻ hơn Anthropic rất nhiều** - phù hợp cho dùng lâu dài
3. **Mạng Việt Nam kết nối DeepSeek ổn định hơn** - ít bị lỗi timeout
4. **Backup API Key** - lưu ở nơi an toàn để không mất
5. **Model deepseek-chat** - dùng cho công việc thông thường
6. **Model deepseek-reasoner** - dùng khi cần suy luận phức tạp (chậm hơn nhưng thông minh hơn)

---

## 🎯 CẤU HÌNH DEEPSEEK CHO CLINE - TÓM TẮT NHANH

```
API Provider: DeepSeek (hoặc OpenAI Compatible)
Base URL: https://api.deepseek.com/v1
API Key: sk-...
Model ID: deepseek-chat (hoặc deepseek-reasoner)
```

**Sau khi điền xong:**
1. Save settings
2. F1 → Reload Window
3. Test chat với Cline
4. Xong! ✅

---

**Tạo bởi:** Cline Assistant  
**Ngày:** 31/05/2026  
**Phiên bản:** 1.0
