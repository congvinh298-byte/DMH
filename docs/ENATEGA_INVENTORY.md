# Inventory Enatega → Mobile App Điện Máy Hiếu

## RiderApp (sẽ đổi thành **WorkerApp**)

### Tech stack
- Expo (cần nâng cấp SDK)
- React Native
- Apollo Client v2 + `react-apollo` hooks
- React Navigation v5 (drawer + stack)
- `expo-notifications`, `expo-location`
- Font/Icon custom

### Màn hình & flow

| Screen | Mục đích gốc | Mapping sang gọi thợ |
|---|---|---|
| `Login` | Rider đăng nhập bằng username/password | Thợ đăng nhập bằng **Telegram ID / SĐT + OTP** hoặc mật khẩu. Lưu token vào `AsyncStorage`. |
| `NewOrders` | Danh sách đơn hàng chưa có rider | Danh sách **ca `pending`** chưa ai nhận. Pull-to-refresh. |
| `Orders` | Đơn đã giao cho rider | **Ca đang làm / đã làm** của thợ. |
| `OrderDetail` | Chi tiết đơn: khách, món, map, cập nhật trạng thái | Chi tiết ca: tên/SĐT khách, địa chỉ, bản đồ, nút **Nhận ca → Đang đi → Đã xong**. |
| `Chat` | Chat với khách | Có thể giữ để chat qua Telegram/Zalo sau này. |
| `Help`, `Language`, `HelpBrowser` | Trợ giúp / ngôn ngữ | Giữ, thay content theo DMH. |

### Apollo operations cần thay

| Type | Tên | Dữ liệu cần |
|---|---|---|
| Query | `profile` | Thông tin thợ (`worker_profiles`). |
| Query | `unassignedOrders` | Ca `pending` chưa có thợ. |
| Query | `assignedOrders` | Ca đã gán cho thợ hiện tại. |
| Mutation | `riderLogin` | Đăng nhập → trả về token. |
| Mutation | `assignOrder` | Thợ nhận ca. |
| Mutation | `updateOrderStatusRider` | Cập nhật trạng thái ca. |
| Mutation | `updateLocation` | Gửi GPS thợ lên server. |
| Subscription | `subscriptionAssignRider` | Realtime khi ca mới được giao. |
| Subscription | `subscriptionUnAssignedOrder` | Realtime khi có ca mới chưa nhận. |

### Components cần điều chỉnh
- `Order` component: hiển thị mã ca, trạng thái, địa chỉ, giá tiền.
- `Sidebar`: menu + thông tin thợ.
- `Header`: icon/breadcrumb.
- Cần thêm màn hình **Earnings** cho thu nhập tháng.

## CustomerApp (sẽ đổi thành **CustomerApp-DMH**)

### Tech stack
- Expo SDK 47
- React Native
- Apollo Client v2
- React Navigation v6
- Có sẵn `expo-apple-authentication` ✅
- Stripe, Paypal, Amplitude

### Màn hình & flow

| Screen | Mục đích gốc | Mapping sang gọi thợ |
|---|---|---|
| `Login` / `Register` | Đăng nhập/đăng ký khách hàng | Đăng nhập/đăng ký bằng SĐT + OTP, Apple Sign-In. |
| `Menu` / `MenuItems` | Danh mục món ăn | Danh mục **dịch vụ sửa chữa**: điện lạnh, điện nước, điện tử, máy giặt... |
| `ItemDetail` | Chi tiết món + chọn variation/addon | Chi tiết dịch vụ + mô tả sự cố + chọn khung giá (nếu có). |
| `Cart` / `CartAddress` | Giỏ hàng + chọn địa chỉ | Xác nhận ca + chọn/nhập địa chỉ sửa chữa. |
| `FullMap` | Chọn địa chỉ trên bản đồ | Giữ nguyên. |
| `MyOrders` | Lịch sử đơn hàng | Lịch sử ca đã đặt. |
| `OrderDetail` | Theo dõi đơn + trạng thái + đánh giá | Theo dõi ca, xem thợ, đánh giá thợ. |
| `RateAndReview` | Đánh giá món/shipper | Đánh giá thợ. |
| `Paypal` / `Stripe` | Thanh toán | Thay bằng **Momo / VNPay / ZaloPay / chuyển khoản**. |
| `Profile` / `Addresses` / `Settings` | Quản lý user/địa chỉ | Giữ. |

### Apollo operations cần thay

| Type | Tên | Dữ liệu cần |
|---|---|---|
| Mutation | `login` / `createUser` | Auth khách hàng. |
| Query | `categories` | Danh sách dịch vụ. |
| Query | `foodByCategory` | Chi tiết/bảng giá dịch vụ. |
| Mutation | `placeOrder` | Tạo ca sửa chữa. |
| Query | `orders` | Lịch sử ca của khách. |
| Query | `order` | Chi tiết ca + trạng thái. |
| Mutation | `addReview` | Đánh giá thợ. |
| Mutation | `updateNotificationStatus` | Đăng ký push token. |

## Khác biệt quan trọng so với gọi đồ ăn

| Đặc điểm | Gọi đồ ăn | Gọi thợ |
|---|---|---|
| Sản phẩm | Món ăn, số lượng, topping | Dịch vụ, mô tả sự cố, địa chỉ, giá ước tính |
| Người giao | Rider giao hàng | Thợ đến tận nhà sửa |
| Trạng thái | PENDING → ACCEPTED → PICKED → DELIVERED | PENDING → ASSIGNED → IN_PROGRESS → COMPLETED / CANCELLED |
| Thanh toán | Thanh toán trước/khi nhận hàng | Có thể thanh toán sau khi hoàn thành |
| Vị trí | Giao đến địa chỉ khách | Thợ đến địa chỉ khách + cập nhật GPS |
| Thu nhập | Rider nhận ship fee | Thợ nhận `tech_net_income` sau khi trừ phí nền tảng |

## Các file config cần thay

- `RiderApp/app.json` → bundle ID, tên, icon, splash, Google Maps key, Apple Sign-In, push.
- `RiderApp/environment.js` → URL backend (DTH API).
- `CustomerApp/app.json` → tương tự.
- `CustomerApp/src/apollo/server.js` → thay tất cả GraphQL strings bằng REST calls.
- Logo, font, color palette.

## Bước tiếp theo

Viết `docs/MOBILE_API_CONTRACT.md` — định nghĩa endpoint REST cho app thợ và app khách.
