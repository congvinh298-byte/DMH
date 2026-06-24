<?php
// Module: orders



if (!function_exists('next_order_code')) {
    function next_order_code(): string
    {
        return 'DTH-' . date('Ymd-His') . '-' . random_int(100, 999);
    }
}

function order_notification_token(int $orderId): string
{
    $secret = app_env('TELEGRAM_WEBHOOK_SECRET', app_env('CRON_SECRET', ''));
    if ($secret === '') {
        return '';
    }
    return hash_hmac('sha256', 'order:' . $orderId, $secret);
}

function order_notification_token_valid(int $orderId, string $token): bool
{
    $expected = order_notification_token($orderId);
    return $expected !== '' && $token !== '' && hash_equals($expected, $token);
}

function app_request_base_url(): string
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        return (app_is_https() ? 'https://' : 'http://') . $host;
    }
    return app_public_url();
}

function trigger_async_order_notification(int $orderId): bool
{
    if ($orderId <= 0) {
        return false;
    }
    $token = order_notification_token($orderId);
    if ($token === '') {
        return false;
    }

    $url = app_request_base_url() . '/api_master.php?action=async_notify_order&order_id=' . $orderId . '&token=' . rawurlencode($token);
    $parts = parse_url($url);
    if (is_array($parts) && !empty($parts['host'])) {
        $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
        $host = (string)$parts['host'];
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $target = ($scheme === 'https' ? 'ssl://' : '') . $host;
        $path = (string)($parts['path'] ?? '/');
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        $fp = @fsockopen($target, $port, $errno, $errstr, 0.2);
        if (is_resource($fp)) {
            stream_set_blocking($fp, false);
            $hostHeader = $host . ((isset($parts['port']) && !in_array($port, [80, 443], true)) ? ':' . $port : '');
            fwrite($fp, "GET {$path} HTTP/1.1\r\nHost: {$hostHeader}\r\nConnection: Close\r\n\r\n");
            fclose($fp);
            return true;
        }
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 250);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 150);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
        return true;
    }

    $ctx = stream_context_create(['http' => ['timeout' => 1]]);
    @file_get_contents($url, false, $ctx);
    return true;
}

function create_order(array $input): array
{
    $pdo = pdo();
    $name = clean_string($input['ten_khach'] ?? $input['customer_name'] ?? '', 150);
    $phone = digits_only($input['sdt'] ?? $input['phone'] ?? $input['customer_phone'] ?? '');
    $payment = clean_string($input['payment_method'] ?? 'cod', 40);
    $type = clean_string($input['type'] ?? 'product', 30);
    $productId = (int)($input['product_id'] ?? 0);
    $address = clean_string($input['customer_address'] ?? $input['shipping_address'] ?? $input['address'] ?? '', 1000);
    $note = clean_string($input['note'] ?? $input['buyer_note'] ?? '', 1000);

    if ($name === '' || strlen($phone) < 8) {
        json_out(['status' => 'error', 'message' => 'Nhap ten va so dien thoai hop le.'], 400);
    }

    ensure_not_banned($pdo, active_identifiers($input, $phone));
    $snapshot = load_product_snapshot($pdo, $productId, $type, $input);
    if ($snapshot['price'] <= 0) {
        json_out(['status' => 'error', 'message' => 'Gia san pham khong hop le.'], 400);
    }
    
    $quantity = max(1, (int)($input['quantity'] ?? 1));
    $basePrice = (int)$snapshot['price'] * $quantity;

    $rawVoucherCode = trim($input['coupon_code'] ?? $input['voucher_code'] ?? '');
    $voucherCode = clean_string(extract_voucher_from_url($rawVoucherCode), 80);
    $orderCode = next_order_code();

    $goodsType = clean_string($input['goods_type'] ?? 'light', 20);
    $mapLat = isset($input['map_lat']) && is_numeric($input['map_lat']) ? (float)$input['map_lat'] : null;
    $mapLng = isset($input['map_lng']) && is_numeric($input['map_lng']) ? (float)$input['map_lng'] : null;
    $shippingFee = 0;
    $storeLat = null;
    $storeLng = null;
    $storeName = '';
    $storeAddress = '';
    $jobServiceType = 'Giao hàng';

    if ($snapshot['table'] === 'marketplace_products' && $productId > 0) {
        $stmt = $pdo->prepare('SELECT p.store_id, s.store_name, s.address, s.lat, s.lng FROM marketplace_products p JOIN marketplace_stores s ON p.store_id = s.id WHERE p.id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $storeRow = $stmt->fetch();
        if ($storeRow) {
            $storeName = (string)$storeRow['store_name'];
            $storeAddress = (string)$storeRow['address'];
            $storeLat = $storeRow['lat'] !== null ? (float)$storeRow['lat'] : null;
            $storeLng = $storeRow['lng'] !== null ? (float)$storeRow['lng'] : null;

            if ($mapLat !== null && $mapLng !== null && $storeLat !== null && $storeLng !== null) {
                $distanceKm = distance_km($storeLat, $storeLng, $mapLat, $mapLng);
                if ($goodsType === 'bulky') {
                    if ($distanceKm <= 1) {
                        $shippingFee = 16000;
                    } else {
                        $shippingFee = 16000 + ceil($distanceKm - 1) * 4000;
                    }
                    $jobServiceType = 'Giao hàng cồng kềnh';
                } else {
                    if ($distanceKm <= 2) {
                        $shippingFee = 13000;
                    } else {
                        $shippingFee = 13000 + ceil($distanceKm - 2) * 3500;
                    }
                    $jobServiceType = 'Giao hàng / Đi chợ thay';
                }
            }
        }
    }

    $pdo->beginTransaction();
    try {
        $discount = apply_voucher_if_valid($pdo, $voucherCode, $basePrice, true);
        
        $isFreeship = (($discount['type'] ?? '') === 'freeship');
        if ($isFreeship) {
            $discount['amount'] = $shippingFee;
            $discount['label'] = 'Miễn phí vận chuyển';
        }

        if ($voucherCode !== '' && $discount['amount'] === 0 && !$isFreeship) {
            throw new Exception($discount['error'] ?? 'Mã giảm giá không hợp lệ.');
        }
        $total = max(0, $basePrice - ($isFreeship ? 0 : (int)$discount['amount'])) + ($isFreeship ? 0 : $shippingFee);
        
        if ($shippingFee > 0) {
            if ($isFreeship) {
                $note = trim("Phí ship: " . number_format($shippingFee, 0, ',', '.') . " đ (Miễn phí vận chuyển). " . $note);
            } else {
                $note = trim("Phí ship: " . number_format($shippingFee, 0, ',', '.') . " đ. " . $note);
            }
        }

        if ($snapshot['table'] === 'products' && $productId > 0) {
            $cols = legacy_product_columns($pdo);
            $stockCol = $cols['stock'] ?? null;
            if ($stockCol !== null) {
                $updatedExpr = column_exists($pdo, 'products', 'updated_at') ? ', updated_at = NOW()' : '';
                $pdo->prepare('UPDATE products SET ' . db_ident($stockCol) . ' = GREATEST(' . db_ident($stockCol) . ' - ?, 0)' . $updatedExpr . ' WHERE id = ?')
                    ->execute([$quantity, $productId]);
            }
        }
        if (in_array($snapshot['table'], ['marketplace_sims', 'sims'], true) && $productId > 0 && column_exists($pdo, $snapshot['table'], 'status')) {
            update_compat($pdo, $snapshot['table'], ['status' => 'reserved'], 'id = ?', [$productId], ['updated_at' => 'NOW()']);
        }

        $orderData = [
            'order_code' => $orderCode,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_address' => $address,
            'shipping_address' => $address,
            'product_id' => $productId,
            'product_name' => $snapshot['name'],
            'total_price' => $total,
            'total' => $total,
            'subtotal' => $basePrice,
            'discount' => (int)$discount['amount'],
            'status' => order_status($pdo, 'pending'),
            'payment_method' => $payment,
            'payment_status' => $payment === 'bank' ? 'unpaid' : 'unpaid',
            'coupon_code' => $voucherCode,
            'voucher_code' => $voucherCode,
            'buyer_note' => $note,
            'note' => $note,
        ];
        
        if (column_exists($pdo, 'orders', 'quantity')) {
            $orderData['quantity'] = $quantity;
        }

        $orderId = insert_compat($pdo, 'orders', $orderData, ['created_at' => 'NOW()']);

        if (table_exists($pdo, 'order_items')) {
            insert_compat($pdo, 'order_items', [
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_name' => $snapshot['name'],
                'product_type' => $type,
                'quantity' => $quantity,
                'price' => (int)$snapshot['price'],
                'subtotal' => $basePrice,
            ], ['created_at' => 'NOW()']);
        }

        if ($snapshot['table'] === 'marketplace_products' && $productId > 0) {
            if (!function_exists('insert_repair_job')) {
                require_once __DIR__ . '/jobs.php';
            }
            $jobDesc = "Đơn hàng: {$snapshot['name']} (x{$quantity})\n";
            $jobDesc .= "Lấy tại: {$storeName} - {$storeAddress}\n";
            $jobDesc .= "Giao đến: {$name} - {$address}\n";
            $jobDesc .= "Tiền hàng (tài xế ứng trước): " . number_format($basePrice, 0, ',', '.') . " đ\n";
            $jobDesc .= "Phí ship: " . number_format($shippingFee, 0, ',', '.') . " đ\n";
            $jobDesc .= "Tổng thu khách: " . number_format($total, 0, ',', '.') . " đ\n";
            if ($note !== '') {
                $jobDesc .= "Ghi chú: " . $note;
            }
            
            $jobId = insert_repair_job($pdo, [
                'customer_name'   => $name,
                'customer_phone'  => $phone,
                'service_type'    => $jobServiceType,
                'address'         => $address,
                'map_lat'         => $mapLat,
                'map_lng'         => $mapLng,
                'description'     => $jobDesc,
                'customer_total'  => $shippingFee,
                'final_total'     => $shippingFee,
                'bot_role'        => 'bike',
            ]);
            update_compat($pdo, 'job_posts', ['status' => 'pending'], 'id = ?', [$jobId]);
            
            if (function_exists('send_worker_job_to_group')) {
                send_worker_job_to_group($pdo, $jobId);
            }
            
            // Link job_id to order in note for future reference
            $pdo->prepare('UPDATE orders SET note = CONCAT(COALESCE(note, ""), ?) WHERE id = ?')
                ->execute(["\n[Job ID: {$jobId}]", $orderId]);
        }
        
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'status' => 'success',
        'message' => 'Dat hang thanh cong.',
        'order_id' => (string)$orderId,
        'order_code' => $orderCode,
        'product_name' => $snapshot['name'],
        'total_price' => $total,
        'discount' => (int)$discount['amount'],
    ];
}

function invoice_company_profile(): array
{
    return [
        'name' => app_env('COMPANY_NAME', app_env('VNB_HOLDER', app_env('BCT_COMPANY_NAME', 'CONG TY TNHH MTV DIEN TU HIEU'))),
        'tax_code' => app_env('COMPANY_TAX_CODE', app_env('BCT_TAX_CODE', '1402228630')),
        'address' => app_env('COMPANY_ADDRESS', '166, Ap Binh Thanh 1, Xa Lap Vo, Tinh Dong Thap'),
        'phone' => app_env('COMPANY_PHONE', '0979.553.289'),
        'email' => app_env('COMPANY_EMAIL', ''),
        'website' => app_env('COMPANY_WEBSITE', app_env('BCT_WEBSITE', app_env('APP_URL', 'https://dienmayhieu.com'))),
    ];
}

function loyalty_points_for_amount(int $amount): int
{
    $vndPerPoint = max(1, (int)app_env('LOYALTY_VND_PER_POINT', '10000'));
    return max(0, (int)floor($amount / $vndPerPoint));
}

function loyalty_member_rank(int $points): string
{
    if ($points >= 1000) {
        return 'Kim cuong';
    }
    if ($points >= 500) {
        return 'Vang';
    }
    if ($points >= 100) {
        return 'Bac';
    }
    return 'Thanh vien';
}

function manual_invoice_discount(PDO $pdo, string $rawCode, int $grossAmount, bool $consume = false): array
{
    $code = strtoupper(clean_string($rawCode, 80));
    if ($code === '') {
        return ['code' => '', 'source' => 'none', 'label' => 'Khong ap ma', 'amount' => 0];
    }

    $suffix = $consume ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE code = ? LIMIT 1' . $suffix);
    $stmt->execute([$code]);
    $voucher = $stmt->fetch();
    if ($voucher) {
        $maxUses = max(0, (int)($voucher['max_uses'] ?? $voucher['usage_limit'] ?? 0));
        $used = max(0, (int)($voucher['used_count'] ?? 0));
        $active = !array_key_exists('is_active', $voucher) || (int)$voucher['is_active'] === 1;
        $expires = (string)($voucher['expires_at'] ?? '');
        if (!$active || $maxUses <= 0 || $used >= $maxUses || ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time())) {
            throw new DomainException('Ma khuyen mai da het han hoac het luot su dung.');
        }
        $percent = max(0, min(100, (int)($voucher['discount_percent'] ?? 0)));
        $fixed = money_int($voucher['discount_amount'] ?? 0);
        if ($fixed <= 0 && $percent <= 0 && isset($voucher['type'], $voucher['value'])) {
            if ((string)$voucher['type'] === 'percent') {
                $percent = max(0, min(100, (int)$voucher['value']));
            } else {
                $fixed = money_int($voucher['value']);
            }
        }
        $amount = $percent > 0 ? max($fixed, (int)round($grossAmount * $percent / 100)) : $fixed;
        $amount = min($grossAmount, max(0, $amount));
        if ($consume) {
            $pdo->prepare('UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?')->execute([(int)$voucher['id']]);
        }
        return [
            'code' => $code,
            'source' => 'voucher',
            'label' => $percent > 0 ? "Giam {$percent}%" : 'Giam tien truc tiep',
            'amount' => $amount,
        ];
    }

    $stmt = $pdo->prepare('SELECT * FROM qr_coupons WHERE code = ? LIMIT 1' . $suffix);
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    if (!$coupon) {
        throw new DomainException('Khong tim thay ma khuyen mai.');
    }
    $quantityLeft = (int)($coupon['quantity_left'] ?? 0);
    if ((int)($coupon['is_used'] ?? 0) === 1 || $quantityLeft <= 0) {
        throw new DomainException('Ma khuyen mai da duoc su dung hoac het luot.');
    }
    $type = strtolower((string)($coupon['type'] ?? 'discount'));
    $value = money_int($coupon['discount_amount'] ?? $coupon['value'] ?? 0);
    $percent = in_array($type, ['percent', 'prize'], true) ? max(0, min(100, (int)($coupon['value'] ?? 0))) : 0;
    $amount = $percent > 0 ? (int)round($grossAmount * $percent / 100) : $value;
    $amount = min($grossAmount, max(0, $amount));
    if ($consume) {
        $pdo->prepare('UPDATE qr_coupons
            SET is_used = IF(quantity_left <= 1, 1, is_used), quantity_left = GREATEST(quantity_left - 1, 0), used_by = ?, order_ref = ?
            WHERE id = ?')->execute(['admin_invoice', 'manual_invoice', (int)$coupon['id']]);
    }
    return [
        'code' => $code,
        'source' => 'promo',
        'label' => $percent > 0 ? "Giam {$percent}%" : ($type === 'freeship' ? 'Mien phi van chuyen' : 'Giam tien truc tiep'),
        'amount' => $amount,
        'type' => $type,
    ];
}

function invoice_warranty_years(array $input): int
{
    return max(0, min(30, (int)($input['warranty_years'] ?? $input['warranty_year'] ?? 0)));
}

function invoice_warranty_expires_at(string $invoiceDate, int $years): ?string
{
    if ($years <= 0) {
        return null;
    }

    try {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $invoiceDate) ?: new DateTimeImmutable($invoiceDate);
        return $date->modify('+' . $years . ' years')->format('Y-m-d');
    } catch (Throwable $e) {
        error_log('[invoice] warranty date calculation failed: ' . $e->getMessage());
        return null;
    }
}

function manual_invoice_calculation(PDO $pdo, array $input, bool $consumeDiscount = false): array
{
    $quantity = max(1, min(10000, (int)($input['quantity'] ?? 1)));
    $unitGross = money_int($input['unit_gross_amount'] ?? $input['gross_amount'] ?? 0);
    if ($unitGross <= 0) {
        throw new InvalidArgumentException('Gia da gom VAT phai lon hon 0.');
    }
    $grossBeforeDiscount = $unitGross * $quantity;
    $discount = manual_invoice_discount($pdo, (string)($input['promo_code'] ?? ''), $grossBeforeDiscount, $consumeDiscount);
    $total = max(0, $grossBeforeDiscount - (int)$discount['amount']);
    $subtotal = (int)round($total * 100 / 110);
    $vat = $total - $subtotal;
    $warrantyYears = invoice_warranty_years($input);
    return [
        'quantity' => $quantity,
        'unit_gross_amount' => $unitGross,
        'gross_before_discount' => $grossBeforeDiscount,
        'discount' => $discount,
        'discount_amount' => (int)$discount['amount'],
        'subtotal_amount' => $subtotal,
        'vat_rate' => 10,
        'vat_amount' => $vat,
        'total_amount' => $total,
        'loyalty_points_earned' => loyalty_points_for_amount($total),
        'warranty_years' => $warrantyYears,
        'warranty_expires_at' => invoice_warranty_expires_at(date('Y-m-d'), $warrantyYears),
    ];
}

function sales_invoice_row(array $row): array
{
    foreach ([
        'id', 'order_id', 'customer_id', 'quantity', 'unit_gross_amount', 'gross_before_discount', 'discount_amount',
        'subtotal_amount', 'vat_amount', 'adjustment_amount', 'total_amount', 'total_price', 'loyalty_points_earned',
        'customer_loyalty_points', 'customer_total_spent', 'warranty_years',
    ] as $field) {
        $row[$field] = (int)($row[$field] ?? 0);
    }
    $row['vat_rate'] = (float)($row['vat_rate'] ?? 10);
    if ($row['warranty_years'] > 0 && empty($row['warranty_expires_at']) && !empty($row['invoice_date'])) {
        $row['warranty_expires_at'] = invoice_warranty_expires_at((string)$row['invoice_date'], $row['warranty_years']);
    }
    $profile = invoice_company_profile();
    foreach ($profile as $key => $value) {
        $field = 'company_' . $key;
        if (empty($row[$field])) {
            $row[$field] = $value;
        }
    }
    return $row;
}

function admin_sales_invoice_rows(PDO $pdo, int $limit = 300): array
{
    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->query("SELECT i.*, u.loyalty_points AS customer_loyalty_points, u.total_spent AS customer_total_spent,
        u.member_rank AS customer_member_rank
        FROM invoices i LEFT JOIN users u ON u.id = i.customer_id
        WHERE i.status = 'active' ORDER BY i.id DESC LIMIT {$limit}");
    return array_map('sales_invoice_row', $stmt->fetchAll());
}

function create_manual_sales_invoice(PDO $pdo, array $input, bool $rewardCustomer = false): array
{
    $productName = clean_string($input['product_name'] ?? '', 255);
    if ($productName === '') {
        throw new InvalidArgumentException('Ten hang hoa khong duoc de trong.');
    }
    $customerName = clean_string($input['customer_name'] ?? ($rewardCustomer ? '' : 'Khach le'), 150);
    $customerPhone = digits_only((string)($input['customer_phone'] ?? ''));
    $customerTaxCode = clean_string($input['customer_tax_code'] ?? '', 50);
    $customerAddress = clean_string($input['customer_address'] ?? '', 1000);
    $giftName = clean_string($input['gift_name'] ?? '', 500);
    $note = clean_string($input['note'] ?? '', 2000);
    $paymentMethod = clean_string($input['payment_method'] ?? 'cash', 40);
    $warrantyYears = invoice_warranty_years($input);
    $invoiceDate = date('Y-m-d');
    $warrantyExpiresAt = invoice_warranty_expires_at($invoiceDate, $warrantyYears);
    $profile = invoice_company_profile();
    $customer = null;
    $orderId = null;

    $pdo->beginTransaction();
    try {
        $calculation = manual_invoice_calculation($pdo, $input, true);
        if ($rewardCustomer) {
            $customer = reward_retail_customer($pdo, $customerName, $customerPhone, (int)$calculation['total_amount']);
            $orderId = insert_compat($pdo, 'orders', [
                'order_code' => next_order_code(),
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'product_id' => ((int)($input['product_id'] ?? 0)) > 0 ? (int)($input['product_id'] ?? 0) : null,
                'product_name' => $productName,
                'total_price' => $calculation['total_amount'],
                'total' => $calculation['total_amount'],
                'subtotal' => $calculation['gross_before_discount'],
                'discount' => $calculation['discount_amount'],
                'status' => order_status($pdo, 'confirmed'),
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'coupon_code' => $calculation['discount']['code'],
                'voucher_code' => $calculation['discount']['code'],
                'note' => $note,
                'confirmed_by' => 'admin_pos',
            ], ['created_at' => 'NOW()', 'confirmed_at' => 'NOW()']);
            if (table_exists($pdo, 'order_items')) {
                insert_compat($pdo, 'order_items', [
                    'order_id' => $orderId,
                    'product_id' => ((int)($input['product_id'] ?? 0)) > 0 ? (int)($input['product_id'] ?? 0) : null,
                    'product_name' => $productName,
                    'product_type' => clean_string($input['product_source'] ?? 'input', 30),
                    'quantity' => $calculation['quantity'],
                    'price' => $calculation['unit_gross_amount'],
                    'subtotal' => $calculation['gross_before_discount'],
                ], ['created_at' => 'NOW()']);
            }
            decrement_retail_stock($pdo, $input, (int)$calculation['quantity']);
        }
        $invoiceCode = 'HD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $invoiceId = insert_compat($pdo, 'invoices', [
            'invoice_code' => $invoiceCode,
            'order_id' => $orderId,
            'customer_id' => $customer['id'] ?? null,
            'customer_name' => $customerName !== '' ? $customerName : 'Khach le',
            'customer_phone' => $customerPhone,
            'customer_tax_code' => $customerTaxCode,
            'customer_address' => $customerAddress,
            'product_name' => $productName,
            'quantity' => $calculation['quantity'],
            'unit_gross_amount' => $calculation['unit_gross_amount'],
            'gross_before_discount' => $calculation['gross_before_discount'],
            'discount_amount' => $calculation['discount_amount'],
            'promo_code' => $calculation['discount']['code'],
            'gift_name' => $giftName,
            'warranty_years' => $warrantyYears,
            'warranty_expires_at' => $warrantyExpiresAt,
            'invoice_date' => $invoiceDate,
            'subtotal_amount' => $calculation['subtotal_amount'],
            'vat_amount' => $calculation['vat_amount'],
            'vat_rate' => 10,
            'adjustment_amount' => 0,
            'total_amount' => $calculation['total_amount'],
            'total_price' => $calculation['total_amount'],
            'company_name' => $profile['name'],
            'company_tax_code' => $profile['tax_code'],
            'company_address' => $profile['address'],
            'company_phone' => $profile['phone'],
            'company_email' => $profile['email'],
            'company_website' => $profile['website'],
            'loyalty_points_earned' => $rewardCustomer ? $calculation['loyalty_points_earned'] : 0,
            'payment_method' => $paymentMethod,
            'note' => $note,
            'status' => 'active',
        ], ['created_at' => 'NOW()']);
        insert_compat($pdo, 'finances', [
            'type' => 'sales_invoice',
            'amount' => $calculation['total_amount'],
            'source_type' => 'invoice',
            'source_id' => $invoiceId,
            'note' => ($rewardCustomer ? 'Retail POS sale invoice ' : 'Manual sales invoice ') . $invoiceCode,
        ], ['created_at' => 'NOW()']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
    $stmt->execute([$invoiceId]);
    $invoice = sales_invoice_row($stmt->fetch() ?: ['id' => $invoiceId]);
    if ($customer) {
        $invoice['customer_loyalty_points'] = (int)($customer['loyalty_points'] ?? 0);
        $invoice['customer_total_spent'] = (int)($customer['total_spent'] ?? 0);
        $invoice['customer_member_rank'] = (string)($customer['member_rank'] ?? 'Thanh vien');
    }
    return $invoice;
}

function get_order_row(PDO $pdo, int $orderId)
{
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function send_order_to_boss(PDO $pdo, int $orderId): bool
{
    $order = get_order_row($pdo, $orderId);
    if (!$order) {
        return false;
    }

    $chatId = telegram_chat('report');
    if ($chatId === '') {
        error_log('[order] Missing report chat id for order #' . $orderId);
        return false;
    }

    $quantity = max(1, (int)($order['quantity'] ?? 0));
    if (table_exists($pdo, 'order_items') && column_exists($pdo, 'order_items', 'quantity')) {
        $stmtQty = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = ?');
        $stmtQty->execute([$orderId]);
        $itemQuantity = (int)$stmtQty->fetchColumn();
        if ($itemQuantity > 0) {
            $quantity = $itemQuantity;
        }
    }

    $orderCode = (string)($order['order_code'] ?? ('#' . $orderId));
    $total = money_int($order['total_price'] ?? $order['total'] ?? 0);
    $discount = money_int($order['discount'] ?? 0);
    $address = clean_string($order['customer_address'] ?? $order['shipping_address'] ?? '', 1000);
    $note = clean_string($order['note'] ?? $order['buyer_note'] ?? '', 1000);

    $lines = [
        '<b>DON HANG MOI</b>',
        'Ma don: <b>' . esc_html($orderCode) . '</b>',
        'San pham: ' . esc_html((string)($order['product_name'] ?? '')),
        'So luong: ' . $quantity,
        'Tong tien: <b>' . esc_html(fmt_money($total)) . '</b>',
        'Thanh toan: ' . esc_html((string)($order['payment_method'] ?? 'cod')),
        'Khach: ' . esc_html((string)($order['customer_name'] ?? '')),
        'SDT: ' . esc_html(mask_phone((string)($order['customer_phone'] ?? ''))),
    ];
    if ($discount > 0) {
        $lines[] = 'Giam gia: ' . esc_html(fmt_money($discount));
    }
    if ($address !== '') {
        $lines[] = 'Dia chi: ' . esc_html($address);
    }
    if ($note !== '') {
        $lines[] = 'Ghi chu: ' . esc_html($note);
    }
    $lines[] = 'Thoi gian: ' . date('d/m/Y H:i:s');

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'Xac nhan don', 'callback_data' => 'order_confirm_' . $orderId],
                ['text' => 'Tu choi', 'callback_data' => 'order_reject_' . $orderId],
            ],
            [
                ['text' => 'Mo trang admin', 'url' => app_public_url() . '/admin_xxx.php'],
            ],
        ],
    ];

    $sent = tg_send('report', $chatId, implode("\n", $lines), $keyboard);
    if (empty($sent['ok'])) {
        usleep(300000);
        $sent = tg_send('report', $chatId, implode("\n", $lines), $keyboard);
    }
    $ok = !empty($sent['ok']);
    $messageId = $ok ? (int)($sent['result']['message_id'] ?? 0) : 0;

    if (table_exists($pdo, 'order_notifications')) {
        $stmtExisting = $pdo->prepare('SELECT id FROM order_notifications WHERE order_id = ? ORDER BY id DESC LIMIT 1');
        $stmtExisting->execute([$orderId]);
        $existingId = (int)$stmtExisting->fetchColumn();
        $values = [
            'telegram_message_id' => $messageId > 0 ? $messageId : null,
            'boss_chat_id' => $chatId,
            'status' => $ok ? 'sent' : 'failed',
        ];
        if ($existingId > 0) {
            update_compat($pdo, 'order_notifications', $values, 'id = ?', [$existingId]);
        } else {
            insert_compat($pdo, 'order_notifications', array_merge(['order_id' => $orderId], $values), ['created_at' => 'NOW()']);
        }
    }

    if (!$ok) {
        error_log('[order] Telegram order notification failed for order #' . $orderId . ': ' . (string)($sent['description'] ?? 'unknown error'));
    }
    return $ok;
}

function confirm_order(PDO $pdo, int $orderId, string $bossName, bool $accepted): array
{
    $order = get_order_row($pdo, $orderId);
    if (!$order) {
        return ['ok' => false, 'message' => 'Khong tim thay don hang.'];
    }
    $newStatus = $accepted ? order_status($pdo, 'confirmed') : order_status($pdo, 'rejected');
    update_compat($pdo, 'orders', [
        'status' => $newStatus,
        'confirmed_by' => $bossName,
    ], 'id = ?', [$orderId], ['confirmed_at' => 'NOW()', 'updated_at' => 'NOW()']);
    update_compat($pdo, 'order_notifications', [
        'status' => $accepted ? 'confirmed' : 'rejected',
        'confirmed_by' => $bossName,
    ], 'order_id = ?', [$orderId], ['confirmed_at' => 'NOW()']);

    insert_compat($pdo, 'finances', [
        'type' => $accepted ? 'order_confirmed' : 'order_rejected',
        'amount' => (int)($order['total_price'] ?? $order['total'] ?? 0),
        'source_type' => 'order',
        'source_id' => $orderId,
        'note' => $accepted ? 'Boss confirmed order' : 'Boss rejected order',
    ], ['created_at' => 'NOW()']);

    return ['ok' => true, 'message' => $accepted ? "Don #{$orderId} da xac nhan." : "Don #{$orderId} da tu choi."];
}

function admin_orders(PDO $pdo): array
{
    if (!table_exists($pdo, 'orders')) {
        return [];
    }
    $stmt = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 200');
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (int)$row['id'],
            'order_code' => (string)($row['order_code'] ?? ''),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_phone' => (string)($row['customer_phone'] ?? ''),
            'customer_address' => (string)($row['customer_address'] ?? $row['shipping_address'] ?? ''),
            'product_name' => (string)($row['product_name'] ?? ''),
            'total_price' => money_int($row['total_price'] ?? $row['total'] ?? 0),
            'status' => (string)($row['status'] ?? ''),
            'payment_method' => (string)($row['payment_method'] ?? ''),
            'note' => (string)($row['note'] ?? $row['buyer_note'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'viettel_invoice_exported' => (int)($row['viettel_invoice_exported'] ?? 0),
        ];
    }
    return $rows;
}
