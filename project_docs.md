# Project Documentation - Điện Tử Hiếu

## System Memory & Capabilities

### Admin Panel (xxx.php)
**Last Updated:** 06/02/2026

### Critical Feature: A4 Invoice Generator Module
- **Status:** ✅ Integrated
- **File:** `xxx.php`
- **Tab/Section:** "Tạo Hóa Đơn" (Invoice Generator)
- **Description:** The admin dashboard now contains a fully functional A4 Invoice Generator module featuring:
  - Quick data entry form (Customer Name, Phone, Product Name, Quantity, Price, Warranty, Gifts)
  - Automatic invoice code generation (DTH_YYYYMMDD_HHMM format)
  - Automatic Total calculation (Qty × Price)
  - Automatic Warranty Expiration Date calculation
  - Strictly formatted A4 print layout using CSS `@media print`
  - Professional invoice layout with company header, customer info, product table, warranty terms, and signature blocks

### 🚫 DO NOT ALTER OR BREAK THIS PRINT LAYOUT IN FUTURE UPDATES
The invoice print layout is carefully crafted with specific CSS `@media print` rules. Any modifications to the `#invoice-print-area`, its child elements, or the associated print CSS may break the A4 formatting. Always preserve:
- `@page { size: A4; margin: 1cm; }`
- Company header: CÔNG TY TNHH MTV ĐIỆN TỬ HIẾU
- Address: Số 166, Ấp Bình Thạnh 1, Xã Lấp Vò, Tỉnh Đồng Tháp
- Phone: 0979.553.289 | MST: 1402228630
- Invoice code format: DTH_YYYYMMDD_HHMM
- Signature blocks: Người mua hàng, Người giao hàng, Đại diện Công ty
- Warranty rules text block at bottom left

### Other Features
- Biometric/Fingerprint login
- Store management
- SIM management
- Partner (Đối tác) management
- QR & Marketing campaigns
- Voucher generation
- Inventory management
- Transaction history
- Blacklist management