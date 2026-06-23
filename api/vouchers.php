<?php
// Module: vouchers



function extract_voucher_from_url(string $rawCode): string {
    $code = trim($rawCode);
    if (strpos($code, 'http') === 0) {
        $parsed = parse_url($code);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            if (!empty($params['voucher'])) {
                return $params['voucher'];
            }
        }
        if (isset($parsed['path'])) {
            $parts = explode('/', trim($parsed['path'], '/'));
            $last = end($parts);
            if (!empty($last)) return $last;
        }
    }
    return $code;
}

function apply_voucher_if_valid(PDO $pdo, string $code, int $subtotal, bool $forUpdate = false): array
{
    if ($code === '') {
        return ['amount' => 0, 'voucher_id' => null, 'is_qr' => false, 'qr_data' => null, 'error' => 'Mã không tồn tại.'];
    }
    
    $lockClause = $forUpdate ? ' FOR UPDATE' : '';

    // Check QR coupons first
    $stmtQr = $pdo->prepare('SELECT * FROM qr_coupons WHERE code = ? LIMIT 1' . $lockClause);
    $stmtQr->execute([$code]);
    $qr = $stmtQr->fetch();
    
    if ($qr) {
        if ((int)$qr['is_used'] === 1) {
            return ['amount' => 0, 'voucher_id' => null, 'is_qr' => true, 'qr_data' => null, 'error' => 'Mã QR này đã được sử dụng.'];
        }
        $amount = (int)($qr['discount_amount'] ?? 0);
        $amount = min($amount, $subtotal);
        return ['amount' => $amount, 'voucher_id' => null, 'is_qr' => true, 'qr_data' => $qr, 'type' => $qr['type'] ?? 'discount'];
    }

    // Check normal vouchers
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE code = ? LIMIT 1' . $lockClause);
    $stmt->execute([$code]);
    $voucher = $stmt->fetch();
    
    if (!$voucher) {
        return ['amount' => 0, 'voucher_id' => null, 'is_qr' => false, 'qr_data' => null, 'error' => 'Mã giảm giá không tồn tại.'];
    }
    
    $maxUses = (int)($voucher['max_uses'] ?? $voucher['usage_limit'] ?? 1);
    $used = (int)($voucher['used_count'] ?? 0);
    $active = array_key_exists('is_active', $voucher) ? (int)$voucher['is_active'] === 1 : true;
    $expires = (string)($voucher['expires_at'] ?? '');
    
    if (!$active) {
        return ['amount' => 0, 'voucher_id' => null, 'is_qr' => false, 'qr_data' => null, 'error' => 'Mã giảm giá đã bị vô hiệu hóa.'];
    }
    if ($used >= $maxUses) {
        return ['amount' => 0, 'voucher_id' => null, 'is_qr' => false, 'qr_data' => null, 'error' => 'Mã giảm giá đã hết lượt sử dụng.'];
    }
    if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) {
        return ['amount' => 0, 'voucher_id' => null, 'is_qr' => false, 'qr_data' => null, 'error' => 'Mã giảm giá đã hết hạn.'];
    }
    
    $percent = (int)($voucher['discount_percent'] ?? 0);
    $amount = (int)($voucher['discount_amount'] ?? 0);
    if ($amount <= 0 && $percent <= 0 && isset($voucher['type'], $voucher['value'])) {
        if ($voucher['type'] === 'percent') {
            $percent = (int)$voucher['value'];
        } else {
            $amount = (int)$voucher['value'];
        }
    }
    
    if ($percent > 0) {
        $amount = max($amount, (int)round($subtotal * min(100, $percent) / 100));
    }
    
    return ['amount' => min($amount, $subtotal), 'voucher_id' => $voucher['id'], 'is_qr' => false, 'qr_data' => null, 'type' => $voucher['type'] ?? 'discount'];
}

function qr_image_url_for_payload(string $payload, int $size = 180): string
{
    if ($payload === '') {
        return '';
    }
    $safeSize = max(120, min(360, $size));
    return "https://api.qrserver.com/v1/create-qr-code/?size={$safeSize}x{$safeSize}&data=" . rawurlencode($payload);
}

function customer_qr_payload(string $loginKey): string
{
    return 'DTH-CUSTOMER:' . $loginKey;
}