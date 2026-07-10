<?php
// Module: workers



function admin_telegram_id(): int
{
    return (int)app_env('ADMIN_TELEGRAM_ID', '648065292');
}

function is_admin_telegram_id(int $telegramUserId): bool
{
    return $telegramUserId > 0 && $telegramUserId === admin_telegram_id();
}

if (!function_exists('seed_known_telegram_profiles')) {
    function seed_known_telegram_profiles(PDO $pdo)
    {
    $adminId = admin_telegram_id();
    if ($adminId > 0) {
        $pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, identity_code, role, is_admin, created_at, updated_at)
            VALUES (?, 'Vinh Tran.2908', 'ADMIN', 'admin', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE role = 'admin', is_admin = 1, identity_code = COALESCE(identity_code, 'ADMIN')")
            ->execute([$adminId]);
    }

    $workerId = (int)app_env('INITIAL_WORKER_TELEGRAM_ID', '8729878070');
    $workerPhone = app_env('INITIAL_WORKER_PHONE', '');
    if ($workerId > 0 && $workerId !== $adminId) {
        $pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, phone, identity_code, worker_type, role, is_admin, registered_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'ho_kinh_doanh', 'worker', 0, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                role = 'worker', is_admin = 0,
                worker_type = COALESCE(worker_type, 'ho_kinh_doanh'),
                phone = COALESCE(NULLIF(phone,''), VALUES(phone)),
                identity_code = COALESCE(identity_code, VALUES(identity_code))")
            ->execute([
                $workerId,
                "Ho kinh doanh {$workerId}",
                $workerPhone ?: (string)$workerId,
                $workerPhone ?: (string)$workerId,
                $adminId,
            ]);

        // Thêm worker_code DTH-001 nếu cột tồn tại và chưa có
        if (column_exists($pdo, 'worker_profiles', 'worker_code')) {
            $pdo->prepare("UPDATE worker_profiles SET worker_code = 'DTH-001'
                WHERE telegram_user_id = ? AND (worker_code IS NULL OR worker_code = '')
                LIMIT 1")
                ->execute([$workerId]);
        }
    }
}

}
function worker_payment_code(int $workerId): string
{
    return 'DTHP' . $workerId;
}

function vietqr_payment_url(int $amount, int $workerId): string
{
    $bank = rawurlencode(app_env('VNB_BIN', 'ICB'));
    $account = rawurlencode(app_env('VNB_ACC', ''));
    $holder = rawurlencode(app_env('VNB_HOLDER', 'DIEN TU HIEU'));
    $code = rawurlencode(worker_payment_code($workerId));
    if ($account === '') {
        return app_public_url() . '/QR_THANH_TOAN.jpg';
    }
    return "https://img.vietqr.io/image/{$bank}-{$account}-compact2.jpg?amount={$amount}&addInfo={$code}&accountName={$holder}";
}

function vietqr_bank_deeplink(int $amount, int $workerId): string
{
    $account = app_env('VNB_ACC', '');
    $bank = app_env('VNB_BIN', 'ICB');
    $holder = app_env('VNB_HOLDER', 'DIEN TU HIEU');
    if ($account === '') {
        return vietqr_payment_url($amount, $workerId);
    }
    return 'https://dl.vietqr.io/pay?ba=' . rawurlencode($account . '@' . $bank)
        . '&am=' . $amount
        . '&tn=' . rawurlencode(worker_payment_code($workerId))
        . '&bn=' . rawurlencode($holder);
}

function payment_url_from_template(string $template, int $amount, int $workerId): string
{
    return strtr($template, [
        '{amount}' => (string)$amount,
        '{code}' => rawurlencode(worker_payment_code($workerId)),
        '{worker_id}' => (string)$workerId,
    ]);
}

function momo_payment_configured(): bool
{
    foreach (['MOMO_PARTNER_CODE', 'MOMO_ACCESS_KEY', 'MOMO_SECRET_KEY'] as $key) {
        if (trim(app_env($key, '')) === '') {
            return false;
        }
    }
    return true;
}

function momo_worker_payment_signature(int $workerId): string
{
    return hash_hmac('sha256', 'worker_payment|' . $workerId, app_env('MOMO_SECRET_KEY', ''));
}

function momo_worker_payment_link(int $workerId): string
{
    return app_public_url() . '/api_master.php?action=momo_worker_payment&worker_id=' . $workerId
        . '&token=' . rawurlencode(momo_worker_payment_signature($workerId));
}

function worker_payment_keyboard(int $workerId, int $amount): array
{
    $bankUrl = trim(app_env('BANK_PAYMENT_URL', ''));
    if ($bankUrl === '') {
        $bankUrl = vietqr_bank_deeplink($amount, $workerId);
    } else {
        $bankUrl = payment_url_from_template($bankUrl, $amount, $workerId);
    }
    $row = [['text' => 'Thanh toan ngan hang', 'url' => $bankUrl]];
    $momoUrl = momo_payment_configured() ? momo_worker_payment_link($workerId) : trim(app_env('MOMO_PAYMENT_URL', ''));
    if ($momoUrl !== '') {
        $row[] = [
            'text' => 'Thanh toan MoMo',
            'url' => momo_payment_configured() ? $momoUrl : payment_url_from_template($momoUrl, $amount, $workerId),
        ];
    }
    return [
        'inline_keyboard' => [
            $row,
            [['text' => 'Xem QR chuyen khoan', 'url' => vietqr_payment_url($amount, $workerId)]],
            [['text' => 'Toi da chuyen khoan', 'callback_data' => "paid_notice_{$workerId}"]],
        ],
    ];
}

function worker_name(array $from): string
{
    $name = trim((string)($from['first_name'] ?? '') . ' ' . (string)($from['last_name'] ?? ''));
    if ($name === '') {
        $name = (string)($from['username'] ?? 'worker');
    }
    return clean_string($name, 150);
}

function upsert_worker(PDO $pdo, int $telegramUserId, string $name, string $username = '', string $botRole = 'worker')
{
    if ($telegramUserId <= 0) {
        return;
    }
    $isAdmin = is_admin_telegram_id($telegramUserId) ? 1 : 0;
    $botRole = telegram_normalize_role($botRole);
    $role = $isAdmin === 1 ? 'admin' : (in_array($botRole, ['worker'], true) ? $botRole : 'worker');
    $stmt = $pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, telegram_username, role, is_admin, last_seen_bot, last_seen_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE telegram_name = VALUES(telegram_name), telegram_username = VALUES(telegram_username),
            role = IF(is_admin = 1, role, VALUES(role)), is_admin = GREATEST(is_admin, VALUES(is_admin)),
            last_seen_bot = VALUES(last_seen_bot), last_seen_at = NOW(), updated_at = NOW()");
    $stmt->execute([$telegramUserId, $name, $username, $role, $isAdmin, $botRole]);
}

function get_worker_profile(PDO $pdo, int $telegramUserId): array
{
    $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE telegram_user_id = ? LIMIT 1');
    $stmt->execute([$telegramUserId]);
    return $stmt->fetch() ?: [];
}

function worker_fee_debt(PDO $pdo, int $workerId): int
{
    if ($workerId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(jp.platform_fee - COALESCE(jp.paid_amount, 0), 0)), 0)
        FROM job_pricing jp
        JOIN job_posts j ON j.id = jp.job_id
        WHERE COALESCE(j.telegram_worker_id, j.worker_id) = ? AND j.completed_at IS NOT NULL");
    $stmt->execute([$workerId]);
    return max(0, (int)$stmt->fetchColumn());
}

function worker_map_coordinates(array $job): array
{
    $lat = isset($job['map_lat']) && is_numeric($job['map_lat']) ? (float)$job['map_lat'] : null;
    $lng = isset($job['map_lng']) && is_numeric($job['map_lng']) ? (float)$job['map_lng'] : null;
    if ($lat === null || $lng === null) {
        $address = (string)($job['address'] ?? $job['location'] ?? '');
        if (preg_match('/(?:Toa do|Tọa độ)\s*:\s*(-?\d{1,3}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/iu', $address, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
        }
    }
    if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
        return [];
    }
    return ['lat' => $lat, 'lng' => $lng, 'text' => number_format($lat, 6, '.', '') . ',' . number_format($lng, 6, '.', '')];
}

function worker_google_maps_url(array $job): string
{
    $coords = worker_map_coordinates($job);
    if ($coords !== []) {
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($coords['text']);
    }
    $address = trim((string)($job['address'] ?? $job['location'] ?? ''));
    if ($address !== '') {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
    }
    return '';
}

function payment_status_message(int $amount, int $remaining, bool $wasBlocked): string
{
    if ($remaining > 0) {
        return 'Da ghi nhan thanh toan ' . fmt_money($amount) . '. No phi nen tang con lai: ' . fmt_money($remaining) . '.';
    }
    if ($wasBlocked || (int)date('N') >= 2) {
        return 'Da ghi nhan thanh toan phi nen tang ' . fmt_money($amount) . '. Da mo khoa chuc nang nhan ca.';
    }
    return 'Da ghi nhan thanh toan phi nen tang ' . fmt_money($amount) . '. Tai khoan nhan ca hoat dong binh thuong.';
}

function settle_worker_payment(PDO $pdo, int $workerId, int $receivedAmount, string $method, string $reference, string $confirmedBy, string $externalId = ''): array
{
    if ($workerId <= 0 || $receivedAmount <= 0) {
        return ['ok' => false, 'message' => 'Thong tin thanh toan khong hop le.'];
    }
    if ($externalId !== '') {
        $check = $pdo->prepare('SELECT * FROM worker_payments WHERE external_transaction_id = ? LIMIT 1');
        $check->execute([$externalId]);
        $existing = $check->fetch();
        if ($existing) {
            return ['ok' => true, 'message' => 'Giao dich da duoc ghi nhan truoc do.', 'payment_id' => (int)$existing['id'], 'duplicate' => true];
        }
    }

    $before = worker_fee_debt($pdo, $workerId);
    if ($before <= 0) {
        return ['ok' => false, 'message' => 'Tho khong con no phi nen tang.', 'remaining' => 0];
    }
    $profile = get_worker_profile($pdo, $workerId);
    $wasBlocked = (int)($profile['payment_blocked'] ?? 0) === 1;
    $plannedApply = min($before, $receivedAmount);
    $remainingToApply = $plannedApply;
    $appliedActual = 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT jp.id, jp.platform_fee, COALESCE(jp.paid_amount, 0) paid_amount
            FROM job_pricing jp
            JOIN job_posts j ON j.id = jp.job_id
            WHERE COALESCE(j.telegram_worker_id, j.worker_id) = ? AND j.completed_at IS NOT NULL AND jp.platform_fee > COALESCE(jp.paid_amount, 0)
            ORDER BY j.completed_at ASC, jp.id ASC FOR UPDATE");
        $stmt->execute([$workerId]);
        foreach ($stmt->fetchAll() as $fee) {
            if ($remainingToApply <= 0) {
                break;
            }
            $balance = max(0, (int)$fee['platform_fee'] - (int)$fee['paid_amount']);
            $allocated = min($balance, $remainingToApply);
            $newPaid = (int)$fee['paid_amount'] + $allocated;
            $newStatus = $newPaid >= (int)$fee['platform_fee'] ? 'paid' : 'partial';
            $paidAtExpr = $newStatus === 'paid' ? 'NOW()' : 'paid_at';
            $update = $pdo->prepare("UPDATE job_pricing SET paid_amount = ?, payment_status = ?, payment_method = ?, payment_reference = ?, paid_at = {$paidAtExpr} WHERE id = ?");
            $update->execute([$newPaid, $newStatus, $method, $reference, (int)$fee['id']]);
            $remainingToApply -= $allocated;
            $appliedActual += $allocated;
        }

        $paymentId = insert_compat($pdo, 'worker_payments', [
            'worker_id' => $workerId,
            'amount' => $receivedAmount,
            'applied_amount' => $appliedActual,
            'method' => $method,
            'reference_code' => $reference,
            'external_transaction_id' => $externalId !== '' ? $externalId : null,
            'status' => 'confirmed',
            'note' => $receivedAmount > $appliedActual ? 'Received amount exceeds current platform fee debt or was reconciled concurrently.' : '',
            'confirmed_by' => $confirmedBy,
        ], ['confirmed_at' => 'NOW()', 'created_at' => 'NOW()']);

        $pdo->prepare("UPDATE worker_profiles SET total_paid_fee = total_paid_fee + ?, last_payment_amount = ?,
            last_payment_at = NOW(), updated_at = NOW()
            WHERE telegram_user_id = ?")->execute([$appliedActual, $appliedActual, $workerId]);
        $pdo->prepare("UPDATE worker_payments SET status = 'superseded' WHERE worker_id = ? AND status = 'pending' AND id <> ?")
            ->execute([$workerId, $paymentId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $remaining = worker_fee_debt($pdo, $workerId);
    if ($remaining <= 0) {
        $pdo->prepare("UPDATE worker_profiles SET payment_blocked = 0,
            blocked_until = IF(is_receive_blocked = 0 AND block_reason LIKE 'platform_fee%', NULL, blocked_until),
            block_reason = IF(is_receive_blocked = 0 AND block_reason LIKE 'platform_fee%', NULL, block_reason), updated_at = NOW()
            WHERE telegram_user_id = ?")->execute([$workerId]);
    } elseif ((int)date('N') >= 2) {
        enforce_worker_payment_lock($pdo, $workerId);
    }
    $finalProfile = get_worker_profile($pdo, $workerId);
    if ($appliedActual <= 0) {
        $message = 'Da ghi nhan giao dich ' . fmt_money($receivedAmount) . ' nhung khong con cong no de phan bo. Admin se kiem tra phan tien du.';
    } else {
        $message = $remaining === 0 && worker_is_blocked($finalProfile)
            ? 'Da ghi nhan thanh toan phi nen tang ' . fmt_money($appliedActual) . '. Tai khoan van dang bi khoa vi ly do khac; vui long lien he admin.'
            : payment_status_message($appliedActual, $remaining, $wasBlocked);
    }
    $noticeRole = telegram_normalize_role((string)($finalProfile['role'] ?? 'worker'));
    tg_send($noticeRole, (string)$workerId, $message);
    return [
        'ok' => true,
        'message' => $message,
        'payment_id' => $paymentId,
        'received_amount' => $receivedAmount,
        'applied_amount' => $appliedActual,
        'remaining' => $remaining,
    ];
}

function send_worker_debt_notice(PDO $pdo, int $workerId, string $role = 'worker', string $reason = 'Nhac phi nen tang', bool $allowZero = false): array
{
    $debt = worker_fee_debt($pdo, $workerId);
    if ($debt <= 0 && !$allowZero) {
        return ['ok' => false, 'message' => 'Khong co no phi.', 'debt' => 0];
    }
    $profile = get_worker_profile($pdo, $workerId);
    $name = (string)($profile['telegram_name'] ?? "Tho {$workerId}");
    if ($debt <= 0) {
        $text = "<b>{$reason}</b>\n"
            . "Tho: " . esc_html($name) . " ({$workerId})\n"
            . "Phi nen tang can nop: <b>0 VND</b>\n"
            . "Ban khong co cong no va van tiep tuc nhan ca binh thuong.";
        $response = tg_send($role, (string)$workerId, $text);
        if (!empty($response['ok'])) {
            $pdo->prepare('UPDATE worker_profiles SET last_fee_notice_at = NOW(), updated_at = NOW() WHERE telegram_user_id = ?')->execute([$workerId]);
        }
        return ['ok' => !empty($response['ok']), 'message' => !empty($response['ok']) ? 'Da gui thong bao 0 VND.' : 'Khong gui duoc thong bao.', 'debt' => 0];
    }
    $code = worker_payment_code($workerId);
    $caption = "<b>{$reason}</b>\n"
        . "Tho: " . esc_html($name) . " ({$workerId})\n"
        . "Tong phi nen tang con no den hien tai: <b>" . fmt_money($debt) . "</b>\n"
        . "Noi dung chuyen khoan: <code>{$code}</code>\n"
        . "Thanh toan thu 2: tai khoan hoat dong binh thuong. Tu thu 3 neu con no: khoa nhan ca.";
    $response = tg_send_photo($role, (string)$workerId, vietqr_payment_url($debt, $workerId), $caption, worker_payment_keyboard($workerId, $debt));
    if (empty($response['ok'])) {
        $response = tg_send($role, (string)$workerId, $caption, worker_payment_keyboard($workerId, $debt));
    }
    if (!empty($response['ok'])) {
        $pdo->prepare('UPDATE worker_profiles SET last_fee_notice_at = NOW(), updated_at = NOW() WHERE telegram_user_id = ?')->execute([$workerId]);
    }
    return ['ok' => !empty($response['ok']), 'message' => !empty($response['ok']) ? 'Da gui nhac phi.' : 'Khong gui duoc nhac phi.', 'debt' => $debt];
}

function notify_all_worker_debts(PDO $pdo, string $reason = 'Nhac phi nen tang'): array
{
    $stmt = $pdo->query("SELECT telegram_user_id, role FROM worker_profiles WHERE is_admin = 0 AND role IN ('worker') ORDER BY telegram_user_id");
    $sent = 0;
    $failed = 0;
    $totalDebt = 0;
    foreach ($stmt->fetchAll() as $row) {
        $workerId = (int)$row['telegram_user_id'];
        $role = (string)($row['role'] ?? 'worker');
        $debt = worker_fee_debt($pdo, $workerId);
        $result = send_worker_debt_notice($pdo, $workerId, $role, $reason, true);
        $totalDebt += $debt;
        if ($result['ok']) {
            $sent++;
        } else {
            $failed++;
        }
    }
    return ['sent' => $sent, 'failed' => $failed, 'total_debt' => $totalDebt];
}

function enforce_worker_payment_lock(PDO $pdo, int $workerId): array
{
    $debt = worker_fee_debt($pdo, $workerId);
    if ($debt > 0 && (int)date('N') >= 2) {
        $pdo->prepare("UPDATE worker_profiles SET payment_blocked = 1,
            block_reason = IF(is_receive_blocked = 1, block_reason, ?),
            blocked_until = IF(is_receive_blocked = 1, blocked_until, NULL), updated_at = NOW() WHERE telegram_user_id = ?")
            ->execute(['platform_fee_debt: ' . $debt, $workerId]);
    }
    return get_worker_profile($pdo, $workerId);
}

function lock_all_workers_with_debt(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT telegram_user_id, role FROM worker_profiles WHERE is_admin = 0 AND role IN ('worker')");
    $locked = 0;
    $totalDebt = 0;
    foreach ($stmt->fetchAll() as $row) {
        $workerId = (int)$row['telegram_user_id'];
        $debt = worker_fee_debt($pdo, $workerId);
        if ($debt <= 0) {
            continue;
        }
        $pdo->prepare("UPDATE worker_profiles SET payment_blocked = 1,
            block_reason = IF(is_receive_blocked = 1, block_reason, ?),
            blocked_until = IF(is_receive_blocked = 1, blocked_until, NULL), updated_at = NOW() WHERE telegram_user_id = ?")
            ->execute(['platform_fee_debt: ' . $debt, $workerId]);
        $role = telegram_normalize_role((string)($row['role'] ?? 'worker'));
        tg_send($role, (string)$workerId, 'Tai khoan da bi khoa nhan ca do con no phi nen tang ' . fmt_money($debt) . '. Thanh toan xong he thong se mo khoa.');
        send_worker_debt_notice($pdo, $workerId, $role, 'Yeu cau thanh toan de mo khoa nhan ca');
        $locked++;
        $totalDebt += $debt;
    }
    return ['locked' => $locked, 'total_debt' => $totalDebt];
}

function record_worker_payment_notice(PDO $pdo, int $workerId): array
{
    $debt = worker_fee_debt($pdo, $workerId);
    if ($debt <= 0) {
        return ['ok' => true, 'message' => 'He thong khong ghi nhan cong no can thanh toan.'];
    }
    $check = $pdo->prepare("SELECT COUNT(*) FROM worker_payments WHERE worker_id = ? AND status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $check->execute([$workerId]);
    if ((int)$check->fetchColumn() > 0) {
        return ['ok' => true, 'message' => 'Yeu cau doi soat da duoc gui. Vui long cho admin hoac SePay xac nhan.'];
    }
    $reference = 'NOTICE-' . $workerId . '-' . date('YmdHis');
    insert_compat($pdo, 'worker_payments', [
        'worker_id' => $workerId,
        'amount' => $debt,
        'applied_amount' => 0,
        'method' => 'worker_notice',
        'reference_code' => $reference,
        'status' => 'pending',
        'note' => 'Worker clicked paid notice; waiting for SePay or admin confirmation.',
    ], ['created_at' => 'NOW()']);
    $profile = get_worker_profile($pdo, $workerId);
    $name = (string)($profile['telegram_name'] ?? "Tho {$workerId}");
    $bossChat = telegram_chat('sales');
    if ($bossChat !== '') {
        tg_send('sales', $bossChat, "<b>THO BAO DA THANH TOAN</b>\nTho: " . esc_html($name) . " ({$workerId})\nCong no dang cho doi soat: <b>" . fmt_money($debt) . "</b>", [
            'inline_keyboard' => [[
                ['text' => 'Xac nhan da thu', 'callback_data' => "confirm_worker_pay_{$workerId}"],
            ]],
        ]);
    }
    return ['ok' => true, 'message' => 'Da bao admin kiem tra giao dich. He thong se mo khoa sau khi xac nhan thanh toan.'];
}

function register_worker_from_admin_command(PDO $pdo, int $senderId, string $text, string $botRole): array
{
    if (!is_admin_telegram_id($senderId)) {
        return ['ok' => false, 'message' => 'Chi admin duoc dung lenh /idtelegram.'];
    }
    $parts = array_map('trim', preg_split('/\|/u', $text) ?: []);
    $workerId = isset($parts[1]) ? (int)digits_only($parts[1]) : 0;
    $phone = isset($parts[2]) ? digits_only($parts[2]) : '';
    $name = clean_string($parts[3] ?? "Ho kinh doanh {$workerId}", 150);
    if ($workerId <= 0 || strlen($phone) < 8) {
        return ['ok' => false, 'message' => 'Dung cu phap: /idtelegram | TELEGRAM_ID | SO_DIEN_THOAI | TEN_THO (ten co the bo trong).'];
    }
    if ($workerId === admin_telegram_id()) {
        return ['ok' => false, 'message' => 'Telegram ID nay la admin, khong dang ky thanh tho.'];
    }
    $stmt = $pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, phone, identity_code, worker_type, role, is_admin, registered_by, last_seen_bot, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'ho_kinh_doanh', ?, 0, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE telegram_name = VALUES(telegram_name), phone = VALUES(phone), identity_code = VALUES(identity_code),
            worker_type = 'ho_kinh_doanh', role = VALUES(role), is_admin = 0, registered_by = VALUES(registered_by), last_seen_bot = VALUES(last_seen_bot), updated_at = NOW()");
    $stmt->execute([$workerId, $name, $phone, (string)$workerId, $botRole, $senderId, $botRole]);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM worker_profiles WHERE is_admin = 0 AND role IN ('worker')")->fetchColumn();
    return ['ok' => true, 'message' => "Da dang ky tho {$name}. Telegram ID: {$workerId}. SDT: {$phone}. Tong so tho: {$count}.", 'worker_id' => $workerId, 'worker_count' => $count];
}

function tech_cancel_limit(): int
{
    $limit = (int)app_env('TECH_CANCEL_LIMIT', '3');
    return max(3, min(10, $limit));
}

function worker_is_blocked(array $profile): bool
{
    if ((int)($profile['payment_blocked'] ?? 0) === 1) {
        return true;
    }
    if ((int)($profile['is_receive_blocked'] ?? 0) === 1) {
        $until = (string)($profile['blocked_until'] ?? '');
        return $until === '' || strtotime($until) === false || strtotime($until) > time();
    }
    return false;
}

function increment_worker_penalty(PDO $pdo, int $workerId, string $reason): array
{
    $limit = tech_cancel_limit();
    $stmt = $pdo->prepare('UPDATE worker_profiles SET cancel_count = cancel_count + 1, abuse_count = abuse_count + 1, updated_at = NOW() WHERE telegram_user_id = ?');
    $stmt->execute([$workerId]);

    $profile = get_worker_profile($pdo, $workerId);
    $count = (int)($profile['cancel_count'] ?? 0);
    if ($count >= $limit) {
        $stmt = $pdo->prepare("UPDATE worker_profiles SET is_receive_blocked = 1, block_reason = ?, blocked_until = NULL, updated_at = NOW() WHERE telegram_user_id = ?");
        $stmt->execute([$reason . " ({$count}/{$limit})", $workerId]);
        $profile = get_worker_profile($pdo, $workerId);
    }
    return $profile;
}

function marketplace_default_coordinates(string $seed = ''): array
{
    $centerLat = (float)app_env('SERVICE_CENTER_LAT', '10.357422');
    $centerLng = (float)app_env('SERVICE_CENTER_LNG', '105.522124');
    $hash = crc32($seed !== '' ? $seed : 'lap-vo-market');
    $latOffset = (((int)($hash % 7000)) - 3500) / 1000000;
    $lngOffset = (((int)(($hash >> 8) % 7000)) - 3500) / 1000000;

    return [
        'lat' => round($centerLat + $latOffset, 7),
        'lng' => round($centerLng + $lngOffset, 7),
    ];
}

function marketplace_business_lookup(string $taxCode): array
{
    $taxCode = digits_only($taxCode);
    if ($taxCode === '' || !function_exists('curl_init')) {
        return [];
    }

    $ch = curl_init('https://api.vietqr.io/v2/business/' . rawurlencode($taxCode));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if (!is_array($data) || (string)($data['code'] ?? '') !== '00' || !is_array($data['data'] ?? null)) {
        return [];
    }

    return [
        'name' => clean_string($data['data']['name'] ?? '', 150),
        'address' => clean_string($data['data']['address'] ?? '', 500),
    ];
}

function marketplace_generate_login_key(PDO $pdo): string
{
    do {
        $key = 'DTHS-' . strtoupper(bin2hex(random_bytes(12)));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM marketplace_stores WHERE login_key = ?');
        $stmt->execute([$key]);
    } while ((int)$stmt->fetchColumn() > 0);

    return $key;
}

function marketplace_qr_payload(string $loginKey): string
{
    return 'DTH-STORE:' . trim($loginKey);
}

function marketplace_qr_image_url(string $loginKey, int $size = 180): string
{
    if ($loginKey === '') {
        return '';
    }
    return qr_image_url_for_payload(marketplace_qr_payload($loginKey), $size);
}

function marketplace_normalize_login_key(string $value): string
{
    $value = trim($value);
    if (stripos($value, 'DTH-STORE:') === 0) {
        $value = substr($value, strlen('DTH-STORE:'));
    }
    return strtoupper(clean_string($value, 128));
}

function marketplace_store_report_token(array $store): string
{
    $secret = app_env('APP_SECRET', app_env('ADMIN_PASS_HASH', 'dien-tu-hieu-store-report'));
    $payload = 'store_report|' . (int)($store['id'] ?? 0) . '|' . (string)($store['tax_code'] ?? '') . '|' . (string)($store['created_at'] ?? '');
    return hash_hmac('sha256', $payload, $secret);
}

function marketplace_store_report_url(array $store): string
{
    $id = (int)($store['id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    return app_public_url() . '/api_master.php?action=store_public_report&id=' . $id . '&token=' . marketplace_store_report_token($store);
}

function marketplace_store_report_qr_url(array $store, int $size = 160): string
{
    $url = marketplace_store_report_url($store);
    return $url !== '' ? qr_image_url_for_payload($url, $size) : '';
}

function marketplace_store_row(array $row): array
{
    $loginKey = (string)($row['login_key'] ?? '');
    $reportUrl = marketplace_store_report_url($row);
    return [
        'id' => (int)($row['id'] ?? 0),
        'phone' => (string)($row['phone'] ?? ''),
        'tax_code' => (string)($row['tax_code'] ?? ''),
        'owner_name' => (string)($row['owner_name'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'store_name' => (string)($row['store_name'] ?? ''),
        'address' => (string)($row['address'] ?? ''),
        'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
        'lng' => isset($row['lng']) ? (float)$row['lng'] : null,
        'store_type' => (string)($row['store_type'] ?? 'Cua hang'),
        'status' => (string)($row['status'] ?? 'active'),
        'login_key' => $loginKey,
        'qr_payload' => $loginKey !== '' ? marketplace_qr_payload($loginKey) : '',
        'qr_image_url' => marketplace_qr_image_url($loginKey, 160),
        'report_url' => $reportUrl,
        'report_qr_image_url' => $reportUrl !== '' ? qr_image_url_for_payload($reportUrl, 150) : '',
        'approved_at' => (string)($row['approved_at'] ?? ''),
        'approved_by' => (string)($row['approved_by'] ?? ''),
        'order_count' => (int)($row['order_count'] ?? 0),
        'pending_orders' => (int)($row['pending_orders'] ?? 0),
        'total_sales' => money_int($row['total_sales'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'last_login_at' => (string)($row['last_login_at'] ?? ''),
    ];
}


function app_store_save_product_action(PDO $pdo, array $input): array
{
    try {
        $loginKey = marketplace_normalize_login_key((string)($input['login_key'] ?? ''));
        if ($loginKey === '') {
            return ['status' => 'error', 'message' => 'Thiếu key đăng nhập.'];
        }
        $stmt = $pdo->prepare("SELECT id, status FROM marketplace_stores WHERE login_key = ? LIMIT 1");
        $stmt->execute([$loginKey]);
        $store = $stmt->fetch();
        if (!$store || (string)$store['status'] !== 'active') {
            return ['status' => 'error', 'message' => 'Cửa hàng không hợp lệ hoặc bị khóa.'];
        }

        $storeId = (int)$store['id'];
        $productId = (int)($input['id'] ?? 0);
        $name = clean_string($input['name'] ?? '', 255);
        $price = money_int($input['price'] ?? 0);
        $stock = max(0, (int)($input['stock'] ?? 0));
        $category = clean_string($input['category'] ?? '', 120);
        $description = clean_string($input['description'] ?? '', 2000);
        $image = clean_string($input['image_url'] ?? '', 700);

        if ($name === '') {
            return ['status' => 'error', 'message' => 'Vui lòng nhập tên sản phẩm.'];
        }

        // BCT Compliance: Keyword pre-moderation filter
        $badWord = app_check_content_keywords($pdo, $name . ' ' . $description);
        if ($badWord !== null) {
            return ['status' => 'error', 'message' => 'Nội dung chứa từ khóa bị cấm: "' . $badWord . '". Vui lòng loại bỏ từ khóa này.'];
        }

        // Chợ Xã Lập Vỏ sử dụng cấu trúc chuẩn, bỏ qua các cột dư thừa cũ
        $values = [
            'store_id' => $storeId,
            'name' => $name,
            'price' => $price,
            'stock' => $stock,
            'type' => $category !== '' ? $category : 'Khác',
            'description' => $description,
            'image_url' => $image,
            'status' => 'active',
        ];

        if ($productId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM marketplace_products WHERE id = ? AND store_id = ? LIMIT 1");
            $stmt->execute([$productId, $storeId]);
            if (!$stmt->fetch()) {
                return ['status' => 'error', 'message' => 'Không tìm thấy sản phẩm của bạn.'];
            }
            update_compat($pdo, 'marketplace_products', $values, 'id = ?', [$productId], ['updated_at' => 'NOW()']);
        } else {
            $productId = insert_compat($pdo, 'marketplace_products', $values, ['created_at' => 'NOW()']);
        }

        return ['status' => 'success', 'message' => 'Lưu sản phẩm thành công.'];
        
    } catch (Throwable $e) {
        error_log('[app_store_save_product_action] ERROR: ' . $e->getMessage());
        return [
            'status' => 'error', 
            'message' => 'Lỗi hệ thống khi lưu sản phẩm. Đội ngũ kỹ thuật đang xử lý.',
            'data' => []
        ];
    }
}

function app_store_scan_menu_action(PDO $pdo, array $input): array
{
    $loginKey = marketplace_normalize_login_key((string)($input['login_key'] ?? ''));
    if ($loginKey === '') {
        json_out(['status' => 'error', 'message' => 'Thieu key dang nhap.'], 401);
    }
    $stmt = $pdo->prepare("SELECT id, status FROM marketplace_stores WHERE login_key = ? LIMIT 1");
    $stmt->execute([$loginKey]);
    $store = $stmt->fetch();
    if (!$store || (string)$store['status'] !== 'active') {
        json_out(['status' => 'error', 'message' => 'Cua hang khong hop le hoac bi khoa.'], 403);
    }
    
    $base64Image = $input['image_base64'] ?? '';
    if ($base64Image === '') {
        json_out(['status' => 'error', 'message' => 'Vui long cung cap hinh anh menu.'], 400);
    }
    
    // Strip data URI scheme if present
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
        $mimeType = "image/" . $matches[1];
        $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
    } else {
        $mimeType = "image/jpeg";
    }

    $apiKey = app_env('GEMINI_API_KEY', '');
    if ($apiKey === '' || !function_exists('curl_init')) {
        json_out(['status' => 'error', 'message' => 'He thong chua cau hinh AI Vision.'], 500);
    }

    $prompt = "Mày là Anh Thiên, một trợ lý thông minh. Hãy đọc bức ảnh chụp menu thực đơn/bảng giá này. Trích xuất TẤT CẢ tên các món (sản phẩm), giá tiền, và phân loại (category) của chúng. KHÔNG được viết chữ gì khác ngoài 1 chuỗi JSON duy nhất định dạng như sau: [{\"name\": \"Cà phê đen\", \"price\": 15000, \"category\": \"Cà phê\"}, ...]";

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => $mimeType,
                            "data" => $base64Image
                        ]
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        json_out(['status' => 'error', 'message' => 'Loi ket noi den Anh Thien AI.'], 500);
    }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        json_out(['status' => 'error', 'message' => 'Anh Thien khong doc duoc hinh anh nay.'], 400);
    }

    // Clean markdown code blocks from AI response
    $text = preg_replace('/```(?:json)?\s*(.*?)\s*```/is', '$1', $text);
    $items = json_decode(trim($text), true);

    if (!is_array($items) || count($items) === 0) {
        json_out(['status' => 'error', 'message' => 'Anh Thien khong tim thay san pham nao trong anh.'], 400);
    }

    $storeId = (int)$store['id'];
    $addedCount = 0;
    
    foreach ($items as $item) {
        $name = clean_string($item['name'] ?? '', 255);
        if ($name === '') continue;
        
        $priceStr = preg_replace('/\D/', '', (string)($item['price'] ?? '0'));
        $price = (int)$priceStr;
        $category = clean_string($item['category'] ?? 'Khác', 120);

        insert_compat($pdo, 'marketplace_products', [
            'store_id' => $storeId,
            'name' => $name,
            'price' => $price,
            'sale_price' => $price,
            'stock' => 100, // Default stock as asked
            'type' => $category,
            'status' => 'active',
        ], ['created_at' => 'NOW()']);
        $addedCount++;
    }

    return ['status' => 'success', 'message' => "Anh Thien da doc va tu dong them {$addedCount} mon vao cua hang cua ban."];
}

function app_store_delete_product_action(PDO $pdo, array $input): array
{
    $loginKey = marketplace_normalize_login_key((string)($input['login_key'] ?? ''));
    if ($loginKey === '') {
        json_out(['status' => 'error', 'message' => 'Thieu key dang nhap.'], 401);
    }
    $stmt = $pdo->prepare("SELECT id, status FROM marketplace_stores WHERE login_key = ? LIMIT 1");
    $stmt->execute([$loginKey]);
    $store = $stmt->fetch();
    if (!$store || (string)$store['status'] !== 'active') {
        json_out(['status' => 'error', 'message' => 'Cua hang khong hop le hoac bi khoa.'], 403);
    }

    $storeId = (int)$store['id'];
    $productId = (int)($input['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id FROM marketplace_products WHERE id = ? AND store_id = ? LIMIT 1");
    $stmt->execute([$productId, $storeId]);
    if (!$stmt->fetch()) {
        json_out(['status' => 'error', 'message' => 'Khong tim thay san pham cua ban.'], 404);
    }
    
    update_compat($pdo, 'marketplace_products', ['status' => 'hidden'], 'id = ?', [$productId], ['updated_at' => 'NOW()']);

    return ['status' => 'success', 'message' => 'Da xoa san pham.'];
}

function admin_store_rows(PDO $pdo): array
{
    if (!table_exists($pdo, 'marketplace_stores')) {
        return [];
    }

    $sql = "SELECT s.*,
            COUNT(o.id) AS order_count,
            SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            COALESCE(SUM(CASE WHEN o.status IN ('pending','completed','confirmed','paid') THEN o.total_amount ELSE 0 END), 0) AS total_sales
        FROM marketplace_stores s
        LEFT JOIN marketplace_orders o ON o.store_id = s.id
        GROUP BY s.id
        ORDER BY s.id DESC";
    $stmt = $pdo->query($sql);

    return array_map('marketplace_store_row', $stmt->fetchAll());
}

function admin_settle_stores_action(PDO $pdo): array
{
    $stores = admin_store_rows($pdo);
    $totalSales = 0;
    foreach ($stores as $store) {
        $totalSales += (int)$store['total_sales'];
    }

    return [
        'status' => 'success',
        'message' => 'Da chot doi soat cua hang: ' . count($stores) . ' cua hang, tong giao dich ' . fmt_money($totalSales) . '.',
        'store_count' => count($stores),
        'total_sales' => $totalSales,
        'data' => $stores,
    ];
}

function admin_approve_store_action(PDO $pdo, array $input): array
{
    $storeId = (int)($input['id'] ?? $input['store_id'] ?? 0);
    if ($storeId <= 0) {
        json_out(['status' => 'error', 'message' => 'ID cua hang khong hop le.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM marketplace_stores WHERE id = ? LIMIT 1');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
    if (!$store) {
        json_out(['status' => 'error', 'message' => 'Khong tim thay cua hang.'], 404);
    }

    $loginKey = (string)($store['login_key'] ?? '');
    if ($loginKey === '') {
        $loginKey = marketplace_generate_login_key($pdo);
    }

    update_compat($pdo, 'marketplace_stores', [
        'status' => 'active',
        'login_key' => $loginKey,
        'approved_by' => 'admin',
    ], 'id = ?', [$storeId], [
        'approved_at' => 'NOW()',
        'updated_at' => 'NOW()',
    ]);

    $stmt = $pdo->prepare('SELECT * FROM marketplace_stores WHERE id = ? LIMIT 1');
    $stmt->execute([$storeId]);
    $row = marketplace_store_row($stmt->fetch() ?: []);

    return [
        'status' => 'success',
        'message' => 'Da duyet cua hang va cap QR/key dang nhap.',
        'data' => $row,
    ];
}

function admin_delete_store_action(PDO $pdo, array $input): array
{
    $storeId = (int)($input['id'] ?? $input['store_id'] ?? 0);
    if ($storeId <= 0) {
        json_out(['status' => 'error', 'message' => 'ID cua hang khong hop le.'], 400);
    }

    $stmt = $pdo->prepare('DELETE FROM marketplace_stores WHERE id = ?');
    $stmt->execute([$storeId]);

    // Optional: also hide or delete products belonging to this store
    $stmtProducts = $pdo->prepare('UPDATE marketplace_products SET status = "hidden" WHERE store_id = ?');
    $stmtProducts->execute([$storeId]);

    return [
        'status' => 'success',
        'message' => 'Da xoa cua hang khoi he thong.',
    ];
}

function store_public_report_action(PDO $pdo, array $input): void
{
    $storeId = (int)($input['id'] ?? $_GET['id'] ?? 0);
    $token = clean_string($input['token'] ?? $_GET['token'] ?? '', 128);

    $sendError = static function (int $status, string $message): void {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>QR doi soat</title></head><body style="font-family:Arial,sans-serif;padding:24px"><h1>Khong mo duoc bao cao</h1><p>' . esc_html($message) . '</p></body></html>';
        exit;
    };

    if ($storeId <= 0 || $token === '') {
        $sendError(400, 'Thieu ID cua hang hoac token QR.');
    }

    $stmt = $pdo->prepare('SELECT * FROM marketplace_stores WHERE id = ? LIMIT 1');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
    if (!$store) {
        $sendError(404, 'Khong tim thay cua hang.');
    }
    if (!hash_equals(marketplace_store_report_token($store), $token)) {
        $sendError(403, 'Token QR doi soat khong hop le.');
    }

    $monthStart = new DateTimeImmutable('first day of last month 00:00:00');
    $monthEnd = new DateTimeImmutable('first day of this month 00:00:00');
    $stmt = $pdo->prepare('SELECT * FROM marketplace_orders WHERE store_id = ? AND created_at >= ? AND created_at < ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([
        $storeId,
        $monthStart->format('Y-m-d H:i:s'),
        $monthEnd->format('Y-m-d H:i:s'),
    ]);
    $orders = $stmt->fetchAll();
    $total = 0;
    foreach ($orders as $order) {
        $total += money_int($order['total_amount'] ?? 0);
    }

    $storeRow = marketplace_store_row($store);
    $period = $monthStart->format('d/m/Y') . ' - ' . $monthEnd->modify('-1 second')->format('d/m/Y');

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Doi soat ' . esc_html($storeRow['store_name']) . '</title><style>
        body{font-family:Arial,sans-serif;margin:0;background:#f4f6f8;color:#0f172a}
        .wrap{max-width:980px;margin:0 auto;padding:20px}
        .card{background:#fff;border:1px solid #dfe3e8;border-radius:8px;padding:18px;margin-bottom:14px}
        h1{font-size:24px;margin:0 0 8px} h2{font-size:18px;margin:0 0 10px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
        .label{color:#64748b;font-size:13px}.value{font-weight:800;margin-top:3px}
        table{width:100%;border-collapse:collapse;background:#fff} th,td{border-bottom:1px solid #e5e7eb;text-align:left;padding:9px;font-size:14px} th{background:#f1f5f9}
        .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}.btn{display:inline-block;border:1px solid #d1d5db;border-radius:6px;padding:9px 12px;background:#fff;color:#111827;text-decoration:none;font-weight:700}.primary{background:#dc2626;border-color:#dc2626;color:#fff}
        @media print{body{background:#fff}.actions{display:none}.wrap{padding:0}.card{border:0}}
    </style></head><body><main class="wrap">';
    echo '<section class="card"><h1>Thong tin doi soat cua hang</h1><div class="grid">';
    echo '<div><div class="label">Ten co so</div><div class="value">' . esc_html($storeRow['store_name']) . '</div></div>';
    echo '<div><div class="label">Ma so thue</div><div class="value">' . esc_html($storeRow['tax_code']) . '</div></div>';
    echo '<div><div class="label">So dien thoai</div><div class="value">' . esc_html($storeRow['phone']) . '</div></div>';
    echo '<div><div class="label">Chu co so</div><div class="value">' . esc_html($storeRow['owner_name'] ?: '-') . '</div></div>';
    echo '<div><div class="label">Loai cua hang</div><div class="value">' . esc_html($storeRow['store_type']) . '</div></div>';
    echo '<div><div class="label">Trang thai</div><div class="value">' . esc_html($storeRow['status']) . '</div></div>';
    echo '</div><p><b>Dia chi:</b> ' . esc_html($storeRow['address']) . '</p>';
    echo '<div class="actions"><a class="btn primary" href="' . esc_html($storeRow['report_qr_image_url']) . '" target="_blank" download>Tải hinh QR doi soat</a><button class="btn" onclick="window.print()">In bao cao</button></div></section>';
    echo '<section class="card"><h2>Doanh thu thang truoc</h2><div class="grid">';
    echo '<div><div class="label">Ky bao cao</div><div class="value">' . esc_html($period) . '</div></div>';
    echo '<div><div class="label">So don</div><div class="value">' . count($orders) . '</div></div>';
    echo '<div><div class="label">Tong doanh thu</div><div class="value">' . esc_html(fmt_money($total)) . '</div></div>';
    echo '</div></section><section class="card"><h2>Chi tiet giao dich</h2><table><thead><tr><th>ID</th><th>Ngay</th><th>Khach</th><th>Dia chi</th><th>Trang thai</th><th>Tien</th></tr></thead><tbody>';
    if (!$orders) {
        echo '<tr><td colspan="6">Thang truoc chua co giao dich.</td></tr>';
    }
    foreach ($orders as $order) {
        echo '<tr>';
        echo '<td>#' . (int)($order['id'] ?? 0) . '</td>';
        echo '<td>' . esc_html((string)($order['created_at'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($order['customer_name'] ?? $order['customer_phone'] ?? '-')) . '</td>';
        echo '<td>' . esc_html((string)($order['customer_address'] ?? '-')) . '</td>';
        echo '<td>' . esc_html((string)($order['status'] ?? '-')) . '</td>';
        echo '<td><b>' . esc_html(fmt_money(money_int($order['total_amount'] ?? 0))) . '</b></td>';
        echo '</tr>';
    }
    echo '</tbody></table></section></main></body></html>';
    exit;
}

function app_store_counts(PDO $pdo): array
{
    if (!table_exists($pdo, 'marketplace_stores')) {
        return [
            'active_total' => 0,
            'pending_total' => 0,
            'types' => [],
        ];
    }

    $activeTotal = (int)$pdo->query("SELECT COUNT(*) FROM marketplace_stores WHERE status = 'active'")->fetchColumn();
    $pendingTotal = (int)$pdo->query("SELECT COUNT(*) FROM marketplace_stores WHERE status = 'pending'")->fetchColumn();
    $stmt = $pdo->query("SELECT store_type, COUNT(*) AS count FROM marketplace_stores WHERE status = 'active' GROUP BY store_type");
    $types = [];
    foreach ($stmt->fetchAll() as $row) {
        $type = (string)($row['store_type'] ?? 'Cua hang');
        $types[$type] = (int)($row['count'] ?? 0);
    }

    return [
        'active_total' => $activeTotal,
        'pending_total' => $pendingTotal,
        'types' => $types,
    ];
}

function admin_worker_rows(PDO $pdo): array
{
    if (!table_exists($pdo, 'worker_profiles')) {
        return [];
    }
    $stmt = $pdo->query("SELECT wp.telegram_user_id AS worker_id, wp.telegram_name, wp.telegram_username, wp.phone, wp.identity_code,
        wp.worker_type, wp.role, wp.is_admin, wp.is_active, wp.cancel_count, wp.is_receive_blocked, wp.payment_blocked, wp.block_reason,
        wp.jobs_claimed, wp.jobs_completed, wp.total_paid_fee, wp.last_payment_amount, wp.last_payment_at, wp.last_fee_notice_at,
        wp.last_seen_bot, wp.last_seen_at, wp.created_at,
        COALESCE((SELECT COUNT(*) FROM job_posts j WHERE COALESCE(j.telegram_worker_id, j.worker_id) = wp.telegram_user_id), 0) AS job_count,
        COALESCE((SELECT SUM(jp.tech_net_income) FROM job_pricing jp JOIN job_posts j ON j.id = jp.job_id WHERE COALESCE(j.telegram_worker_id, j.worker_id) = wp.telegram_user_id AND j.completed_at IS NOT NULL), 0) AS total_earned,
        COALESCE((SELECT SUM(GREATEST(jp.platform_fee - COALESCE(jp.paid_amount, 0), 0)) FROM job_pricing jp JOIN job_posts j ON j.id = jp.job_id WHERE COALESCE(j.telegram_worker_id, j.worker_id) = wp.telegram_user_id AND j.completed_at IS NOT NULL), 0) AS unpaid_fee,
        COALESCE((SELECT SUM(p.applied_amount) FROM worker_payments p WHERE p.worker_id = wp.telegram_user_id AND p.status = 'confirmed'), 0) AS confirmed_paid_fee,
        COALESCE((SELECT COUNT(*) FROM worker_payments p WHERE p.worker_id = wp.telegram_user_id AND p.status = 'pending'), 0) AS pending_payment_count
        FROM worker_profiles wp
        ORDER BY wp.is_admin DESC, unpaid_fee DESC, wp.updated_at DESC, wp.created_at DESC LIMIT 500");
    return $stmt->fetchAll();
}

function admin_worker_payments(PDO $pdo, int $limit = 200): array
{
    if (!table_exists($pdo, 'worker_payments')) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $stmt = $pdo->query("SELECT p.*, wp.telegram_name, wp.phone
        FROM worker_payments p LEFT JOIN worker_profiles wp ON wp.telegram_user_id = p.worker_id
        ORDER BY p.id DESC LIMIT {$limit}");
    return $stmt->fetchAll();
}

// ================================================================
// ADMIN CRUD — QUẢN LÝ TÀI KHOẢN THỢ (CLOSED-LOOP, KHÔNG CẦN TELEGRAM)
// Chủ cửa hàng có thể tạo, cập nhật, khóa/mở tài khoản thợ
// hoàn toàn qua Admin Panel mà không cần Telegram ID
// ================================================================

/**
 * Tạo tài khoản thợ mới từ Admin Panel
 *
 * Tự động:
 * - Sinh worker_code: DTH-001, DTH-002, ...
 * - Sinh telegram_user_id nội bộ (âm số, không trùng Telegram)
 * - Set pin tạm thời nếu được cung cấp
 *
 * @param  array $data  [fullname, phone, address, skill_tags[], pin, worker_type]
 * @return array [success, worker_id, worker_code, message]
 */
function admin_create_worker_account(PDO $pdo, array $data): array
{
    $fullname    = clean_string($data['fullname'] ?? $data['name'] ?? '', 150);
    $phone       = digits_only((string)($data['phone'] ?? ''));
    $address     = clean_string($data['address'] ?? '', 500);
    $skillTags   = $data['skill_tags'] ?? [];
    $pin         = (string)($data['pin'] ?? '');
    $workerType  = clean_string($data['worker_type'] ?? 'ho_kinh_doanh', 50);
    $note        = clean_string($data['note'] ?? '', 500);

    // Validate
    $errors = [];
    if ($fullname === '') $errors[] = 'Tên thợ không được để trống.';
    if (strlen($phone) < 9) $errors[] = 'Số điện thoại không hợp lệ (tối thiểu 9 chữ số).';
    if (!empty($pin) && !preg_match('/^\d{4,6}$/', $pin)) {
        $errors[] = 'PIN phải từ 4-6 chữ số.';
    }
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors];
    }

    // Kiểm tra SĐT đã tồn tại chưa
    if (column_exists($pdo, 'worker_profiles', 'phone')) {
        $check = $pdo->prepare('SELECT telegram_user_id FROM worker_profiles WHERE phone = ? LIMIT 1');
        $check->execute([$phone]);
        if ($check->fetchColumn()) {
            return ['success' => false, 'message' => 'Số điện thoại này đã được đăng ký cho một thợ khác.', 'code' => 'PHONE_EXISTS'];
        }
    }

    // Sinh internal worker ID (dùng timestamp âm để phân biệt với Telegram ID thật)
    // Telegram ID thật luôn > 0, internal ID dùng timestamp để unique
    $internalWorkerId = -1 * (int)microtime(true);

    // Sinh mã thợ
    $workerCode = generate_worker_code($pdo);

    // Hash PIN nếu có
    $pinHash = $pin !== '' ? password_hash($pin, PASSWORD_BCRYPT) : null;

    // Ghi vào worker_profiles
    $insertData = [
        'telegram_user_id'  => $internalWorkerId,
        'telegram_name'     => $fullname,
        'identity_code'     => $workerCode ?: $phone,
        'role'              => 'worker',
        'is_admin'          => 0,
        'is_active'         => 1,
        'worker_type'       => $workerType,
        'phone'             => $phone,
        'address'           => $address,
        'pin_hash'          => $pinHash,
        'skill_tags'        => !empty($skillTags) ? json_encode($skillTags, JSON_UNESCAPED_UNICODE) : null,
        'worker_code'       => $workerCode ?: null,
        'shift_status'      => 'off',
        'jobs_claimed'      => 0,
        'jobs_completed'    => 0,
        'cancel_count'      => 0,
        'rating_score'      => 5.0,
        'rating_count'      => 0,
        'registered_by'     => admin_telegram_id(),
    ];

    insert_compat($pdo, 'worker_profiles', $insertData, ['created_at' => 'NOW()', 'updated_at' => 'NOW()']);

    return [
        'success'     => true,
        'message'     => "Đã tạo tài khoản thợ {$fullname} ({$workerCode}) thành công.",
        'worker_id'   => $internalWorkerId,
        'worker_code' => $workerCode,
        'phone'       => $phone,
        'pin_set'     => $pinHash !== null,
    ];
}

/**
 * Cập nhật thông tin tài khoản thợ từ Admin Panel
 *
 * @param  int   $workerId  telegram_user_id (hoặc internal ID)
 * @param  array $data      Các field cần cập nhật
 * @return array [success, message]
 */
function admin_update_worker_account(PDO $pdo, int $workerId, array $data): array
{
    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['success' => false, 'message' => 'Không tìm thấy tài khoản thợ.', 'code' => 'WORKER_NOT_FOUND'];
    }

    $updateFields = [];

    if (isset($data['fullname']) && $data['fullname'] !== '') {
        $updateFields['telegram_name'] = clean_string($data['fullname'], 150);
    }
    if (isset($data['phone'])) {
        $phone = digits_only((string)$data['phone']);
        if (strlen($phone) >= 9) {
            // Kiểm tra SĐT không trùng với thợ khác
            if (column_exists($pdo, 'worker_profiles', 'phone')) {
                $check = $pdo->prepare('SELECT telegram_user_id FROM worker_profiles WHERE phone = ? AND telegram_user_id != ? LIMIT 1');
                $check->execute([$phone, $workerId]);
                if ($check->fetchColumn()) {
                    return ['success' => false, 'message' => 'Số điện thoại này đã được đăng ký cho thợ khác.', 'code' => 'PHONE_EXISTS'];
                }
            }
            $updateFields['phone'] = $phone;
        }
    }
    if (isset($data['address'])) {
        $updateFields['address'] = clean_string($data['address'], 500);
    }
    if (isset($data['skill_tags']) && is_array($data['skill_tags'])) {
        $updateFields['skill_tags'] = json_encode($data['skill_tags'], JSON_UNESCAPED_UNICODE);
    }
    if (isset($data['worker_type'])) {
        $updateFields['worker_type'] = clean_string($data['worker_type'], 50);
    }
    if (isset($data['pin'])) {
        $newPin = (string)$data['pin'];
        if (preg_match('/^\d{4,6}$/', $newPin)) {
            $updateFields['pin_hash'] = password_hash($newPin, PASSWORD_BCRYPT);
        } else {
            return ['success' => false, 'message' => 'PIN mới phải từ 4-6 chữ số.', 'code' => 'INVALID_PIN'];
        }
    }
    // Reset PIN (xóa PIN để thợ tự set lại)
    if (!empty($data['reset_pin'])) {
        $updateFields['pin_hash'] = null;
    }

    if (empty($updateFields)) {
        return ['success' => false, 'message' => 'Không có thông tin nào để cập nhật.', 'code' => 'NOTHING_TO_UPDATE'];
    }

    update_compat($pdo, 'worker_profiles', $updateFields, 'telegram_user_id = ?', [$workerId], ['updated_at' => 'NOW()']);

    return ['success' => true, 'message' => 'Đã cập nhật thông tin thợ thành công.', 'worker_id' => $workerId];
}

/**
 * Khoá / Mở khoá tài khoản thợ
 *
 * @param  int    $workerId  telegram_user_id
 * @param  bool   $isActive  true = mở khoá, false = khoá
 * @param  string $reason    Lý do khoá (nếu khoá)
 * @return array [success, message]
 */
function admin_toggle_worker_status(PDO $pdo, int $workerId, bool $isActive, string $reason = ''): array
{
    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['success' => false, 'message' => 'Không tìm thấy tài khoản thợ.', 'code' => 'WORKER_NOT_FOUND'];
    }

    $updateFields = ['is_active' => $isActive ? 1 : 0];
    if (!$isActive && $reason !== '') {
        $updateFields['block_reason'] = clean_string($reason, 500);
        // Nếu khoá → kết thúc ca ngay lập tức
        $updateFields['shift_status'] = 'off';
        $updateFields['current_shift_id'] = null;
    } elseif ($isActive) {
        $updateFields['block_reason'] = null;
    }

    update_compat($pdo, 'worker_profiles', $updateFields, 'telegram_user_id = ?', [$workerId], ['updated_at' => 'NOW()']);

    $name = (string)($profile['telegram_name'] ?? "Thợ #{$workerId}");
    $action = $isActive ? 'mở khoá' : 'khoá';

    // Thông báo nội bộ cho thợ
    if (table_exists($pdo, 'in_app_notifications')) {
        create_in_app_notification(
            $pdo,
            'worker',
            $workerId,
            $isActive ? '✅ Tài khoản đã được mở khoá' : '🔒 Tài khoản bị khoá',
            $isActive
                ? 'Tài khoản của bạn đã được mở khoá. Bạn có thể bắt đầu ca và nhận đơn.'
                : "Tài khoản của bạn bị khoá. Lý do: " . ($reason ?: 'Vi phạm quy định.') . " Liên hệ admin để biết thêm.",
            $isActive ? 'admin_msg' : 'admin_msg',
            '',
            0
        );
    }

    return ['success' => true, 'message' => "Đã {$action} tài khoản thợ {$name}."];
}

/**
 * Danh sách thợ từ Admin Panel (v2) — kèm trạng thái ca + worker_code
 *
 * @param  array $filters [shift_status, is_active, search]
 * @param  int   $limit
 * @param  int   $offset
 * @return array
 */
function admin_workers_list_v2(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0): array
{
    if (!table_exists($pdo, 'worker_profiles')) {
        return ['total' => 0, 'workers' => []];
    }

    $where  = '1=1';
    $params = [];

    if (isset($filters['is_active'])) {
        $where .= ' AND wp.is_active = ?';
        $params[] = $filters['is_active'] ? 1 : 0;
    }
    if (isset($filters['shift_status']) && $filters['shift_status'] !== '') {
        if (column_exists($pdo, 'worker_profiles', 'shift_status')) {
            $where .= ' AND wp.shift_status = ?';
            $params[] = $filters['shift_status'];
        }
    }
    if (isset($filters['search']) && $filters['search'] !== '') {
        $q = '%' . $filters['search'] . '%';
        $where .= ' AND (wp.telegram_name LIKE ? OR wp.phone LIKE ? OR wp.worker_code LIKE ? OR wp.identity_code LIKE ?)';
        $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM worker_profiles wp WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Build SELECT — chỉ lấy các cột chắc chắn tồn tại + optional columns
    $optCols = [];
    $optionalColumns = ['phone', 'address', 'skill_tags', 'shift_status', 'current_lat', 'current_lng', 'worker_code', 'current_shift_id'];
    foreach ($optionalColumns as $col) {
        if (column_exists($pdo, 'worker_profiles', $col)) {
            $optCols[] = "wp.{$col}";
        }
    }
    $optColsStr = $optCols ? ', ' . implode(', ', $optCols) : '';

    $stmt = $pdo->prepare("
        SELECT
            wp.telegram_user_id, wp.telegram_name, wp.telegram_username, wp.identity_code,
            wp.role, wp.is_admin, wp.is_active, wp.worker_type,
            wp.jobs_claimed, wp.jobs_completed, wp.cancel_count, wp.rating_score, wp.rating_count,
            wp.payment_blocked, wp.is_receive_blocked, wp.block_reason,
            wp.total_paid_fee, wp.last_payment_amount, wp.last_payment_at,
            wp.created_at, wp.updated_at
            {$optColsStr},
            COALESCE((
                SELECT COUNT(*) FROM job_posts j
                WHERE COALESCE(j.telegram_worker_id, j.worker_id) = wp.telegram_user_id
                  AND j.completed_at IS NOT NULL
            ), 0) AS completed_jobs_total,
            COALESCE((
                SELECT SUM(GREATEST(jp.platform_fee - COALESCE(jp.paid_amount, 0), 0))
                FROM job_pricing jp
                JOIN job_posts j ON j.id = jp.job_id
                WHERE COALESCE(j.telegram_worker_id, j.worker_id) = wp.telegram_user_id
                  AND j.completed_at IS NOT NULL
            ), 0) AS fee_debt
        FROM worker_profiles wp
        WHERE {$where}
        ORDER BY wp.is_admin DESC, wp.is_active DESC, wp.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $rows = $stmt->fetchAll();

    $workers = array_map(static function (array $row): array {
        // Decode skill_tags JSON nếu có
        $skillTags = [];
        if (!empty($row['skill_tags'])) {
            $decoded = json_decode($row['skill_tags'], true);
            $skillTags = is_array($decoded) ? $decoded : [];
        }

        return [
            'worker_id'    => (int)$row['telegram_user_id'],
            'name'         => (string)($row['telegram_name'] ?? ''),
            'phone'        => (string)($row['phone'] ?? ''),
            'worker_code'  => (string)($row['worker_code'] ?? ''),
            'identity_code' => (string)($row['identity_code'] ?? ''),
            'role'         => (string)($row['role'] ?? 'worker'),
            'is_admin'     => (bool)$row['is_admin'],
            'is_active'    => (bool)$row['is_active'],
            'worker_type'  => (string)($row['worker_type'] ?? ''),
            'shift_status' => (string)($row['shift_status'] ?? 'off'),
            'skill_tags'   => $skillTags,
            'address'      => (string)($row['address'] ?? ''),
            'jobs_claimed'     => (int)$row['jobs_claimed'],
            'jobs_completed'   => (int)$row['jobs_completed'],
            'cancel_count'     => (int)$row['cancel_count'],
            'rating_score'     => (float)$row['rating_score'],
            'rating_count'     => (int)$row['rating_count'],
            'payment_blocked'  => (bool)$row['payment_blocked'],
            'receive_blocked'  => (bool)$row['is_receive_blocked'],
            'block_reason'     => (string)($row['block_reason'] ?? ''),
            'fee_debt'         => (int)($row['fee_debt'] ?? 0),
            'total_paid_fee'   => (int)($row['total_paid_fee'] ?? 0),
            'last_payment_at'  => (string)($row['last_payment_at'] ?? ''),
            'created_at'       => (string)($row['created_at'] ?? ''),
            'updated_at'       => (string)($row['updated_at'] ?? ''),
        ];
    }, $rows);

    return ['total' => $total, 'limit' => $limit, 'offset' => $offset, 'workers' => $workers];
}

