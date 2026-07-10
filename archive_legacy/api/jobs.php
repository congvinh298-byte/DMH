<?php
// Module: jobs



function public_service_catalog(): array
{
    $services = [
        ['group' => 'Vệ sinh điện lạnh', 'name' => 'Vệ sinh máy lạnh gia đình (dưới 2HP)', 'base' => 150000, 'note' => 'Bao gồm vệ sinh dàn lạnh, kiểm tra gas, nguồn.'],
        ['group' => 'Vệ sinh điện lạnh', 'name' => 'Vệ sinh máy lạnh tủ đứng/âm trần/cassette', 'base' => 250000, 'note' => 'Báo giá cụ thể sau khảo sát.'],
        ['group' => 'Lắp đặt điện lạnh', 'name' => 'Lắp máy lạnh treo tường 1HP - 2HP', 'base' => 400000, 'note' => 'Chưa gồm vật tư phát sinh.'],
        ['group' => 'Lắp đặt điện lạnh', 'name' => 'Lắp đặt máy lạnh 2HP / 3HP', 'base' => 500000, 'note' => 'Chưa gồm vật tư phát sinh.'],
        ['group' => 'Lắp đặt điện lạnh', 'name' => 'Lắp đặt máy lạnh âm trần', 'base' => 0, 'note' => 'Báo giá sau khi tư vấn.'],
        ['group' => 'Sửa chữa điện lạnh', 'name' => 'Sửa chữa máy lạnh (kiểm tra + báo giá)', 'base' => 200000, 'note' => 'Linh kiện phát sinh được báo riêng.'],
        ['group' => 'Vệ sinh gia dụng', 'name' => 'Vệ sinh nệm tại nhà (1 nệm)', 'base' => 250000, 'note' => 'Giặt hơi nước, hút bụi, khử mùi.'],
        ['group' => 'Vệ sinh gia dụng', 'name' => 'Vệ sinh sofa (1 bộ)', 'base' => 300000, 'note' => 'Tùy chất liệu vải/da, báo giá cụ thể khi khảo sát.'],
        ['group' => 'Vệ sinh gia dụng', 'name' => 'Vệ sinh thảm tại nhà', 'base' => 200000, 'note' => 'Theo mét vuông, tối thiểu 1 thảm.'],
        ['group' => 'Lắp đặt gia dụng', 'name' => 'Lắp máy giặt', 'base' => 200000, 'note' => 'Phụ kiện phát sinh được báo riêng.'],
        ['group' => 'Lắp đặt gia dụng', 'name' => 'Lắp máy lọc nước', 'base' => 200000, 'note' => 'Phụ kiện phát sinh được báo riêng.'],
        ['group' => 'Lắp đặt gia dụng', 'name' => 'Treo tivi', 'base' => 200000, 'note' => 'Chưa gồm khung treo.'],
        ['group' => 'Sửa chữa điện tử', 'name' => 'Kiểm tra / sửa điện thoại, tablet', 'base' => 200000, 'note' => 'Linh kiện phát sinh được báo riêng.'],
        ['group' => 'Sửa chữa điện tử', 'name' => 'Sửa bếp từ, bếp hồng ngoại, nồi cơm điện', 'base' => 200000, 'note' => 'Báo giá linh kiện sau kiểm tra.'],
        ['group' => 'Dịch vụ khác', 'name' => 'Khoan treo đồ, lắp kệ, lắp phong cách đơn giản', 'base' => 150000, 'note' => 'Theo món, báo giá trước khi làm.'],
        ['group' => 'Dịch vụ khác', 'name' => 'Rửa xe, vệ sinh máy lạnh xe hơi tại nhà', 'base' => 250000, 'note' => 'Chỉ phục vụ trong phạm vi di chuyển hợp lý.'],
    ];

    foreach ($services as $index => &$service) {
        $service['id'] = 'service-' . ($index + 1);
        $service['tech_base'] = (int)$service['base'];
        $service['public_price'] = (int)$service['base'];
    }
    unset($service);
    return $services;
}

function service_name_key(string $value): string
{
    if (function_exists('transliterator_transliterate')) {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
        if ($ascii !== false) {
            return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $ascii));
        }
    }
    $value = mb_strtolower($value, 'UTF-8');
    $value = str_replace(
        ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
         'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
         'ì','í','ị','ỉ','ĩ',
         'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
         'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
         'ỳ','ý','ỵ','ỷ','ỹ',
         'đ'],
        ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
         'e','e','e','e','e','e','e','e','e','e','e',
         'i','i','i','i','i',
         'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
         'u','u','u','u','u','u','u','u','u','u','u',
         'y','y','y','y','y',
         'd'],
        $value
    );
    return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $value));
}

function public_service_by_name(string $name): ?array
{
    $needle = service_name_key(clean_string($name, 150));
    if ($needle === '') {
        return null;
    }
    foreach (public_service_catalog() as $service) {
        if (service_name_key((string)$service['name']) === $needle) {
            return $service;
        }
    }
    return null;
}

function calculate_job_pricing(int $techTargetBase, int $estimatedCustomerPrice, int $quantity = 1): array
{
    $quantity = max(1, $quantity);
    if ($techTargetBase > 0) {
        $grossCustomerPrice = $techTargetBase * $quantity;
    } else {
        $grossCustomerPrice = max(0, $estimatedCustomerPrice);
    }

    $platformFee = (int)round($grossCustomerPrice * 0.15);
    $techNetIncome = $grossCustomerPrice - $platformFee;

    $vatAmount = 0;
    $profitAmount = $platformFee;
    $finalCustomerPrice = $grossCustomerPrice;

    return [
        'tech_target_base' => $techNetIncome,
        'vat_amount' => $vatAmount,
        'profit_amount' => $profitAmount,
        'gross_customer_price' => $grossCustomerPrice,
        'discount_amount' => 0,
        'discount_roll' => 0,
        'discount_label' => 'Khong ap dung',
        'final_customer_price' => $finalCustomerPrice,
        'platform_fee' => $platformFee,
        'tech_net_income' => $techNetIncome,
    ];
}

function get_job_row(PDO $pdo, int $jobId, bool $forUpdate = false)
{
    $sql = 'SELECT * FROM job_posts WHERE id = ?' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$jobId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_job_pricing(PDO $pdo, int $jobId): array
{
    $stmt = $pdo->prepare('SELECT * FROM job_pricing WHERE job_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$jobId]);
    return $stmt->fetch() ?: [];
}

function job_worker_telegram_id(array $job): int
{
    $telegramWorkerId = (int)($job['telegram_worker_id'] ?? 0);
    return $telegramWorkerId > 0 ? $telegramWorkerId : (int)($job['worker_id'] ?? 0);
}

function job_assignment_values(PDO $pdo, int $workerId): array
{
    $values = ['telegram_worker_id' => $workerId];
    $workerColumnType = strtolower(column_type($pdo, 'job_posts', 'worker_id'));
    $legacyLimit = strpos($workerColumnType, 'unsigned') !== false ? 4294967295 : 2147483647;
    $workerIdFitsLegacyColumn = $workerId <= $legacyLimit;
    $values['worker_id'] = strpos($workerColumnType, 'bigint') !== false || $workerIdFitsLegacyColumn ? $workerId : null;
    return $values;
}

function job_belongs_to_worker(PDO $pdo, array $job, int $workerId): bool
{
    if ($workerId <= 0) {
        return false;
    }
    if (job_worker_telegram_id($job) === $workerId) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT telegram_user_id FROM job_claims
        WHERE job_id = ? AND outcome = 'claimed' ORDER BY id DESC LIMIT 1");
    $stmt->execute([(int)($job['id'] ?? 0)]);
    if ((int)$stmt->fetchColumn() !== $workerId) {
        return false;
    }
    update_compat($pdo, 'job_posts', job_assignment_values($pdo, $workerId), 'id = ?', [(int)$job['id']]);
    return true;
}

function job_display_status(array $job): string
{
    $raw = (string)($job['status'] ?? '');
    if (in_array($raw, ['completed', 'filled', 'closed'], true) && !empty($job['completed_at'])) {
        return 'completed';
    }
    if (in_array($raw, ['cancelled', 'spam'], true)) {
        return $raw;
    }
    if (job_worker_telegram_id($job) > 0) {
        return 'assigned';
    }
    return 'pending';
}

function insert_repair_job(PDO $pdo, array $job): int
{
    $serviceType = clean_string($job['service_type'] ?? 'Dich vu dien lanh', 150);
    $customerName = clean_string($job['customer_name'] ?? 'Khach', 150);
    $customerPhone = digits_only($job['customer_phone'] ?? '');
    $address = clean_string($job['address'] ?? '', 1000);
    $mapLat = isset($job['map_lat']) && is_numeric($job['map_lat']) ? (float)$job['map_lat'] : null;
    $mapLng = isset($job['map_lng']) && is_numeric($job['map_lng']) ? (float)$job['map_lng'] : null;
    $description = clean_string($job['description'] ?? '', 3000);
    $finalTotal = (int)($job['final_total'] ?? 0);
    $customerTotal = (int)($job['customer_total'] ?? $finalTotal);

    $botRole = telegram_normalize_role(clean_string($job['bot_role'] ?? 'worker', 30));
    $targetGroupId = clean_string($job['target_group_id'] ?? get_bot_group_chat_id($botRole), 50);

    $fullDescription = $description;
    if (!column_exists($pdo, 'job_posts', 'customer_phone') || !column_exists($pdo, 'job_posts', 'address')) {
        $fullDescription = trim($description . "\nCustomer: {$customerName}\nPhone: {$customerPhone}\nAddress: {$address}");
    }

    $values = [
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'service_type' => $serviceType,
        'address' => $address,
        'map_lat' => $mapLat,
        'map_lng' => $mapLng,
        'description' => $fullDescription,
        'quantity' => max(1, (int)($job['quantity'] ?? 1)),
        'customer_total' => $customerTotal,
        'discount' => (int)($job['discount'] ?? 0),
        'final_total' => $finalTotal,
        'status' => 'new',
        'bot_role' => $botRole,
        'target_group_id' => $targetGroupId,
        'spam_count' => 0,
        'title' => $serviceType . ' #' . date('His'),
        'location' => $address,
        'salary_min' => $finalTotal,
        'salary_max' => $finalTotal,
        'worker_count' => 1,
    ];
    $systemUserId = get_system_user_id($pdo);
    if ($systemUserId !== null) {
        $values['employer_id'] = $systemUserId;
    }
    return insert_compat($pdo, 'job_posts', $values, ['created_at' => 'NOW()']);
}

function insert_job_pricing(PDO $pdo, int $jobId, array $pricing)
{
    insert_compat($pdo, 'job_pricing', [
        'job_id' => $jobId,
        'tech_target_base' => $pricing['tech_target_base'],
        'vat_amount' => $pricing['vat_amount'],
        'profit_amount' => $pricing['profit_amount'],
        'gross_customer_price' => $pricing['gross_customer_price'],
        'discount_amount' => $pricing['discount_amount'],
        'final_customer_price' => $pricing['final_customer_price'],
        'platform_fee' => $pricing['platform_fee'],
        'tech_net_income' => $pricing['tech_net_income'],
        'paid_amount' => 0,
        'payment_status' => 'unpaid',
    ], ['created_at' => 'NOW()']);
}

function trigger_async_job_dispatch(int $jobId): void
{
    $url = app_public_url() . '/api_master.php?action=async_dispatch_job&job_id=' . $jobId;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 200);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 1]]);
        @file_get_contents($url, false, $ctx);
    }
}

function trigger_async_claim_job(int $jobId, int $workerId, string $workerName, string $username, string $role): void
{
    // Call synchronously to ensure it runs
    process_async_claim_job(pdo(), $jobId, $workerId, $workerName, $username, $role);
}

function get_bot_group_chat_id(string $role): string
{
    return telegram_chat($role);
}

function booking_bot_role(array $input, string $serviceType, string $selectedService, string $description): string
{
    $explicitRole = strtolower(trim((string)($input['bot_role'] ?? $input['service_role'] ?? '')));
    if (in_array($explicitRole, ['worker', 'report'], true)) {
        return telegram_normalize_role($explicitRole);
    }

    $serviceKey = service_name_key($serviceType . ' ' . $selectedService . ' ' . $description);
    if (preg_match('/(su co|khieu nai|bao loi|support|ho tro|bao cao)/', $serviceKey)) {
        return 'report';
    }
    return 'worker';
}

function send_worker_job_to_group(PDO $pdo, int $jobId, int $attempt = 1): bool
{
    $job = get_job_row($pdo, $jobId);
    if (!$job) {
        error_log("[job_dispatch] Job {$jobId} not found");
        return false;
    }
    $role = telegram_normalize_role((string)($job['bot_role'] ?? 'worker'));
    $chatId = trim((string)($job['target_group_id'] ?? ''));
    if ($chatId === '') {
        $chatId = get_bot_group_chat_id($role);
    }
    if ($chatId === '') {
        error_log("[job_dispatch] Job {$jobId} missing target group chat id");
        update_compat($pdo, 'job_posts', ['status' => 'failed', 'cancel_reason' => 'Missing target group chat id'], 'id = ?', [$jobId]);
        return false;
    }

    $pricing = get_job_pricing($pdo, $jobId);

    $publicDescription = mask_phone_like_text((string)($job['description'] ?? ''));
    $job['customer_phone'] = mask_phone((string)($job['customer_phone'] ?? ''));

    // Address shown to the worker group must NOT contain GPS coords or Google Maps links.
    $publicAddress = (string)($job['address'] ?? '');
    if (($pos = strpos($publicAddress, ' | Tọa độ:')) !== false) {
        $publicAddress = substr($publicAddress, 0, $pos);
    }
    $publicAddress = trim($publicAddress);

    $text = "<b>BÁO CA MỚI #{$jobId}</b>\n"
        . "Loại dịch vụ: " . esc_html($job['service_type'] ?? '') . "\n"
        . "SĐT khách: " . esc_html((string)($job['customer_phone'] ?? '')) . "\n"
        . "Địa chỉ: " . esc_html($publicAddress) . "\n"
        . "Ghi chú: " . esc_html($publicDescription) . "\n\n"
        . "Giá trị đơn: <b>" . fmt_money((int)($job['final_total'] ?? $pricing['final_customer_price'] ?? 0)) . "</b>\n"
        . "Thợ nhận được: <b>" . fmt_money((int)($pricing['tech_net_income'] ?? 0)) . "</b>\n\n"
        . "Thợ REPLY vào tin này để NHẬN ca.\n"
        . "Nếu yêu cầu ảo/spam, reply: SPAM";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'Nhận ca', 'callback_data' => "claim_job_{$jobId}"],
                ['text' => 'Báo spam', 'callback_data' => "spam_job_{$jobId}"],
            ],
        ],
    ];

    $resp = tg_send($role, $chatId, $text, $keyboard);
    $messageId = (int)($resp['result']['message_id'] ?? 0);
    if (!empty($resp['ok']) && $messageId > 0) {
        save_tg_map($pdo, $role, (string)$chatId, $messageId, 'job', $jobId);
        update_compat($pdo, 'job_posts', ['status' => 'notified', 'tg_message_id' => $messageId], 'id = ?', [$jobId]);
        error_log("[job_dispatch] Job {$jobId} sent to group {$chatId} message_id={$messageId} attempt={$attempt}");
        return true;
    }

    $errorDesc = $resp['description'] ?? 'Unknown Telegram error';
    error_log("[job_dispatch] Job {$jobId} attempt={$attempt} failed: " . $errorDesc);

    if ($attempt < 3) {
        usleep(500000); // 500ms before retry
        return send_worker_job_to_group($pdo, $jobId, $attempt + 1);
    }

    // Mark as failed only after internal retries exhausted
    update_compat($pdo, 'job_posts', [
        'status' => 'failed',
        'cancel_reason' => 'Lỗi gửi Telegram báo ca: ' . clean_string($errorDesc, 250),
    ], 'id = ?', [$jobId]);
    return false;
}

function claim_job(PDO $pdo, int $jobId, int $workerId, string $workerName, string $username = '', string $role = 'worker'): array
{
    upsert_worker($pdo, $workerId, $workerName, $username, $role);
    $profile = enforce_worker_payment_lock($pdo, $workerId);
    if (worker_is_blocked($profile)) {
        $debt = worker_fee_debt($pdo, $workerId);
        return ['ok' => false, 'message' => $debt > 0 ? 'Tai khoan dang bi khoa nhan ca. No phi nen tang: ' . fmt_money($debt) . '.' : 'Tai khoan tho dang bi khoa nhan ca. Lien he admin.'];
    }

    $claimId = 0;
    $pdo->beginTransaction();
    try {
        $job = get_job_row($pdo, $jobId, true);
        if (!$job) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Khong tim thay ca.'];
        }
        if (job_display_status($job) !== 'pending') {
            insert_compat($pdo, 'job_claims', [
                'job_id' => $jobId,
                'telegram_user_id' => $workerId,
                'telegram_name' => $workerName,
                'outcome' => 'late',
                'note' => 'Job is no longer pending',
            ], ['created_at' => 'NOW()']);
            $pdo->commit();
            return ['ok' => false, 'message' => 'Ca nay da co tho nhan hoac da dong.'];
        }

        update_compat($pdo, 'job_posts', job_assignment_values($pdo, $workerId) + [
            'status' => job_status($pdo, 'assigned'),
        ], 'id = ?', [$jobId], ['assigned_at' => 'NOW()', 'updated_at' => 'NOW()']);

        $claimId = insert_compat($pdo, 'job_claims', [
            'job_id' => $jobId,
            'telegram_user_id' => $workerId,
            'telegram_name' => $workerName,
            'outcome' => 'claimed',
        ], ['created_at' => 'NOW()']);

        $pdo->prepare('UPDATE worker_profiles SET jobs_claimed = jobs_claimed + 1, updated_at = NOW() WHERE telegram_user_id = ?')
            ->execute([$workerId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Notify worker via DM with full customer details
    $dmSuccess = process_async_claim_job($pdo, $jobId, $workerId, $workerName, $username, $role);

    if (!$dmSuccess) {
        // Rollback assignment so another worker can claim or worker can retry after /start
        update_compat($pdo, 'job_posts', [
            'worker_id' => null,
            'telegram_worker_id' => null,
            'status' => job_status($pdo, 'pending'),
            'cancel_reason' => 'DM to worker failed; worker may need /start.',
        ], 'id = ?', [$jobId], ['updated_at' => 'NOW()']);
        if ($claimId > 0) {
            update_compat($pdo, 'job_claims', ['outcome' => 'dm_failed'], 'id = ?', [$claimId]);
        }
        $pdo->prepare('UPDATE worker_profiles SET jobs_claimed = GREATEST(jobs_claimed - 1, 0), updated_at = NOW() WHERE telegram_user_id = ?')
            ->execute([$workerId]);
        return ['ok' => false, 'message' => 'LỖI: Bot chưa thể gửi tin nhắn cho bạn! Vui lòng bấm vào tên Bot, chọn NHẮN TIN (hoặc /start), sau đó quay lại đây bấm NHẬN CA lại nhé!'];
    }

    return ['ok' => true, 'message' => "Đã xác nhận bạn nhận ca #{$jobId}. Vui lòng kiểm tra tin nhắn riêng của Bot!"];
}

function process_async_claim_job(PDO $pdo, int $jobId, int $workerId, string $workerName, string $username, string $role): bool
{
    $job = get_job_row($pdo, $jobId) ?: [];
    if (!$job || job_display_status($job) !== 'assigned' || job_worker_telegram_id($job) !== $workerId) {
        error_log("[job_claim_dm] Job {$jobId} not in assigned state for worker {$workerId}");
        return false;
    }

    $pricing = get_job_pricing($pdo, $jobId);
    $coordinates = worker_map_coordinates($job);
    $mapsUrl = worker_google_maps_url($job);
    $dm = "<b>BAN DA NHAN CA #{$jobId}</b>\n"
        . "Khach: " . esc_html($job['customer_name'] ?? '') . "\n"
        . "SDT day du: <b>" . esc_html($job['customer_phone'] ?? '') . "</b>\n"
        . "Dia chi: " . esc_html($job['address'] ?? $job['location'] ?? '') . "\n"
        . ($coordinates !== [] ? "Toa do da xac nhan: <code>" . esc_html($coordinates['text']) . "</code>\n" : '')
        . "Mo ta: " . esc_html($job['description'] ?? '') . "\n"
        . "Tien tho muc tieu: " . fmt_money((int)($pricing['tech_net_income'] ?? 0)) . "\n\n"
        . "Lam xong: REPLY vao tin nhan nay voi chu XONG.\n"
        . "Neu huy ca: REPLY voi chu HUY.";
    $dmKeyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'Da xong', 'callback_data' => "done_job_{$jobId}"],
                ['text' => 'Huy ca', 'callback_data' => "cancel_job_{$jobId}"],
            ],
        ],
    ];
    if ($mapsUrl !== '') {
        $dmKeyboard['inline_keyboard'][] = [['text' => 'Mo Google Maps den nha khach', 'url' => $mapsUrl]];
    }

    // Retry DM up to 3 times because first message to a worker sometimes fails
    $attempt = 1;
    $resp = [];
    while ($attempt <= 3) {
        $resp = tg_send($role, (string)$workerId, $dm, $dmKeyboard);
        $messageId = (int)($resp['result']['message_id'] ?? 0);
        if (!empty($resp['ok']) && $messageId > 0) {
            save_tg_map($pdo, $role, (string)$workerId, $messageId, 'job', $jobId);
            break;
        }
        error_log("[job_claim_dm] Job {$jobId} worker {$workerId} attempt {$attempt} failed: " . ($resp['description'] ?? 'unknown'));
        if ($attempt < 3) {
            usleep(800000); // 800ms backoff
        }
        $attempt++;
    }

    if (empty($resp['ok']) || (int)($resp['result']['message_id'] ?? 0) <= 0) {
        error_log("[job_claim_dm] Job {$jobId} worker {$workerId} DM failed after 3 attempts");
        return false;
    }

    $groupChat = (string)($job['target_group_id'] ?? get_bot_group_chat_id($role));
    if ($groupChat !== '') {
        tg_send($role, $groupChat, "Ca #{$jobId} da duoc nhan boi " . esc_html($workerName) . " ({$workerId}).");
    }
    return true;
}

function cancel_worker_job(PDO $pdo, int $jobId, int $workerId, string $workerName, string $reason, string $role = 'worker'): array
{
    $job = get_job_row($pdo, $jobId);
    if (!$job || !job_belongs_to_worker($pdo, $job, $workerId)) {
        return ['ok' => false, 'message' => 'Ca khong thuoc tho nay.'];
    }
    update_compat($pdo, 'job_posts', [
        'worker_id' => null,
        'telegram_worker_id' => null,
        'status' => job_status($pdo, 'pending'),
        'cancel_reason' => $reason,
    ], 'id = ?', [$jobId], ['cancelled_at' => 'NOW()', 'updated_at' => 'NOW()']);

    insert_compat($pdo, 'job_claims', [
        'job_id' => $jobId,
        'telegram_user_id' => $workerId,
        'telegram_name' => $workerName,
        'outcome' => 'cancelled',
        'note' => $reason,
    ], ['created_at' => 'NOW()']);

    $profile = increment_worker_penalty($pdo, $workerId, 'cancel_job');
    $message = 'Da huy ca. So lan vi pham: ' . (int)($profile['cancel_count'] ?? 0) . '/' . tech_cancel_limit() . '.';
    if (worker_is_blocked($profile)) {
        $message .= ' Tai khoan da bi khoa nhan ca.';
    }
    $groupChat = get_bot_group_chat_id($role);
    if ($groupChat !== '') {
        tg_send($role, $groupChat, "Ca #{$jobId} bi huy boi " . esc_html($workerName) . ". Ly do: " . esc_html($reason));
    }
    return ['ok' => true, 'message' => $message];
}

function complete_worker_job(PDO $pdo, int $jobId, int $workerId, string $workerName, string $role = 'worker'): array
{
    $pdo->beginTransaction();
    try {
        $job = get_job_row($pdo, $jobId, true);
        if (!$job || !job_belongs_to_worker($pdo, $job, $workerId)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Ca khong thuoc tho nay.'];
        }
        if (job_display_status($job) === 'completed') {
            $pdo->commit();
            return ['ok' => true, 'message' => "Ca #{$jobId} da duoc ghi nhan hoan thanh truoc do."];
        }
        update_compat($pdo, 'job_posts', [
            'status' => job_status($pdo, 'completed'),
        ], 'id = ?', [$jobId], ['completed_at' => 'NOW()', 'updated_at' => 'NOW()']);

        // Update corresponding order status if exists
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE note LIKE ? LIMIT 1");
        $stmt->execute(["%\n[Job ID: {$jobId}]%"]);
        $order = $stmt->fetch();
        if ($order) {
            update_compat($pdo, 'orders', ['status' => 'customer_received'], 'id = ?', [$order['id']]);
        }

        $pricing = get_job_pricing($pdo, $jobId);
        update_compat($pdo, 'job_pricing', ['payment_status' => 'unpaid'], 'job_id = ?', [$jobId]);
        insert_compat($pdo, 'finances', [
            'type' => 'platform_fee_receivable',
            'amount' => (int)($pricing['platform_fee'] ?? 0),
            'source_type' => 'job',
            'source_id' => $jobId,
            'note' => "Platform fee debt from worker {$workerId}",
        ], ['created_at' => 'NOW()']);

        $pdo->prepare('UPDATE worker_profiles SET jobs_completed = jobs_completed + 1, updated_at = NOW() WHERE telegram_user_id = ?')
            ->execute([$workerId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $cumulativeDebt = worker_fee_debt($pdo, $workerId);
    $groupChat = get_bot_group_chat_id($role);
    if ($groupChat !== '') {
        tg_send($role, $groupChat, "Ca #{$jobId} da hoan thanh boi " . esc_html($workerName) . ". Phi ca nay: " . fmt_money((int)($pricing['platform_fee'] ?? 0)) . ". Tong no phi den hien tai: " . fmt_money($cumulativeDebt));
    }
    tg_send($role, (string)$workerId, "<b>PHI NEN TANG CONG DON</b>\n"
        . "Ca vua hoan thanh: #{$jobId}\n"
        . "Phi ca nay: <b>" . fmt_money((int)($pricing['platform_fee'] ?? 0)) . "</b>\n"
        . "Tong phi nen tang den hien tai: <b>" . fmt_money($cumulativeDebt) . "</b>\n"
        . "Thong bao nop phi va QR thanh toan se duoc gui rieng vao 06:00 sang thu 2.");
    return ['ok' => true, 'message' => "Da danh dau ca #{$jobId} hoan thanh. Tong no phi nen tang: " . fmt_money($cumulativeDebt) . '.'];
}

// ================================================================
// CREATE JOB ACTION
// Luồng: Client → api_master.php → create_job_action() → core.php (DB)
//        → insert_repair_job() → send_worker_job_to_group() → Telegram
// ================================================================
function create_job_action(array $input): array
{
    $pdo = pdo();
    $phone = digits_only((string)($input['phone'] ?? $input['sdt'] ?? $input['customer_phone'] ?? ''));
    $address = clean_string((string)($input['address'] ?? $input['dia_chi'] ?? ''), 1000);
    $mapLocation = clean_string((string)($input['map_location'] ?? ''), 80);
    $mapLat = isset($input['map_lat']) && is_numeric($input['map_lat']) ? (float)$input['map_lat'] : null;
    $mapLng = isset($input['map_lng']) && is_numeric($input['map_lng']) ? (float)$input['map_lng'] : null;
    if (($mapLat === null || $mapLng === null) && preg_match('/^(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)$/', $mapLocation, $coords)) {
        $mapLat = (float)$coords[1];
        $mapLng = (float)$coords[2];
    }
    if ($mapLat !== null && $mapLng !== null && (abs($mapLat) > 90 || abs($mapLng) > 180)) {
        $mapLat = null;
        $mapLng = null;
    }
    if ($mapLocation !== '' && preg_match('/^-?\d{1,3}(?:\.\d+)?,-?\d{1,3}(?:\.\d+)?$/', $mapLocation) && strpos($address, $mapLocation) === false) {
        $address = clean_string($address . ' | Tọa độ: ' . $mapLocation, 1000);
    }
    $description      = clean_string((string)($input['issue_description'] ?? $input['mo_ta'] ?? $input['description'] ?? ''), 3000);
    $serviceType      = clean_string((string)($input['service_type'] ?? $input['loai_tho'] ?? ''), 150);
    $customerName     = clean_string((string)($input['customer_name'] ?? $input['name'] ?? ''), 150);
    $quantity         = max(1, (int)($input['quantity'] ?? $input['qty'] ?? 1));
    $techBase         = money_int($input['tech_target_base'] ?? $input['tech_base'] ?? 0);
    $estimated        = money_int($input['estimated_price'] ?? $input['customer_price'] ?? $input['final_total'] ?? 0);
    $selectedServiceName = clean_string((string)($input['selected_service_name'] ?? $input['selected_service'] ?? ''), 150);
    $selectedService  = public_service_by_name($selectedServiceName);
    if ($selectedService !== null) {
        $techBase  = (int)$selectedService['tech_base'];
        $estimated = (int)$selectedService['public_price'] * $quantity;
        if ($serviceType === '') {
            $serviceType = (string)$selectedService['name'];
        }
    }

    // ----------------------------------------------------------------
    // BƯỚC 1: VALIDATE ĐẦU VÀO - trả về JSON 400 nếu thiếu trường bắt buộc.
    // Tuyệt đối KHÔNG để hệ thống sập 500 vì thiếu dữ liệu.
    // ----------------------------------------------------------------
    $errors = [];
    if (strlen($phone) < 8) {
        $errors[] = 'Số điện thoại không hợp lệ (tối thiểu 8 chữ số).';
    }
    if ($customerName === '') {
        $errors[] = 'Tên khách hàng không được để trống.';
    }
    if ($address === '') {
        $errors[] = 'Địa chỉ không được để trống.';
    }
    if ($serviceType === '') {
        $errors[] = 'Loại dịch vụ không được để trống.';
    }
    if ($description === '') {
        $errors[] = 'Mô tả sự cố không được để trống.';
    }
    if (!empty($errors)) {
        json_out([
            'success' => false,
            'message' => implode(' ', $errors),
            'data'    => ['validation_errors' => $errors],
        ], 400);
    }

    $serviceDistanceKm = null;
    if ($mapLat !== null && $mapLng !== null) {
        $serviceDistanceKm = service_area_distance_km($mapLat, $mapLng);
        // Không chặn >15km vì GPS từ IP có thể sai. Thợ sẽ xác minh qua địa chỉ.
    }

    $identifiers   = active_identifiers($input, $phone);
    ensure_not_banned($pdo, $identifiers);
    $pricing       = calculate_job_pricing($techBase, $estimated, $quantity);
    $botRole       = booking_bot_role($input, $serviceType, $selectedServiceName, $description);
    $targetGroupId = get_bot_group_chat_id($botRole);

    // ----------------------------------------------------------------
    // BƯỚC 2: INSERT VÀO DB
    // Client → api_master.php → create_job_action() → insert_repair_job()
    //        → insert_compat() → DB::conn() (core.php) → PDO → MySQL
    // ----------------------------------------------------------------
    $jobId = insert_repair_job($pdo, [
        'customer_name'   => $customerName,
        'customer_phone'  => $phone,
        'service_type'    => $serviceType,
        'address'         => $address,
        'map_lat'         => $mapLat,
        'map_lng'         => $mapLng,
        'description'     => $description,
        'quantity'        => $quantity,
        'customer_total'  => $pricing['gross_customer_price'],
        'discount'        => $pricing['discount_amount'],
        'final_total'     => $pricing['final_customer_price'],
        'bot_role'        => $botRole,
        'target_group_id' => $targetGroupId,
    ]);
    insert_job_pricing($pdo, $jobId, $pricing);
    record_client_request($pdo, $jobId, $identifiers);

    // ----------------------------------------------------------------
    // BƯỚC 3: STATE MACHINE
    // Trạng thái ban đầu: "pending" (đang chờ thợ nhận)
    // ----------------------------------------------------------------
    update_compat($pdo, 'job_posts', ['status' => 'pending'], 'id = ?', [$jobId]);

    // ----------------------------------------------------------------
    // BƯỚC 4: DISPATCH
    // Ưu tiên: Internal (Push App) → Fallback: Telegram group
    // ----------------------------------------------------------------
    $internalDispatched = dispatch_job_to_workers_internal($pdo, $jobId);

    $sent = false;
    if (!$internalDispatched && app_bool_env('WORKER_APP_FALLBACK_TO_TELEGRAM', true)) {
        // Không có thợ nào on_shift hoặc push thất bại → fallback sang Telegram
        $sent = send_worker_job_to_group($pdo, $jobId);
        if ($sent) {
            update_compat($pdo, 'job_posts', ['status' => 'matching'], 'id = ?', [$jobId]);
        } else {
            trigger_async_job_dispatch($jobId);
        }
    } elseif ($internalDispatched) {
        $sent = true; // Coi internal dispatch = gửi thành công
    } else {
        // Cả hai đều thất bại → thử async
        trigger_async_job_dispatch($jobId);
    }

    // ----------------------------------------------------------------
    // BƯỚC 5: RESPONSE - Chuẩn JSON { success, message, data }
    // Tuyệt đối không trả về HTML hoặc để trống khi có lỗi.
    // ----------------------------------------------------------------
    return [
        'success' => true,
        'message' => $internalDispatched
            ? 'Đã tạo yêu cầu và thông báo đến thợ đang trực ca qua ứng dụng.'
            : ($sent
                ? 'Đã tạo yêu cầu và thông báo đến nhóm Thợ qua Telegram.'
                : 'Đã tạo yêu cầu. Hệ thống sẽ thông báo Thợ trong giây lát.'),
        'data' => [
            'job_id'              => $jobId,
            'status'              => $sent ? 'matching' : 'pending',
            'internal_dispatched' => $internalDispatched,
            'telegram_sent'       => $sent && !$internalDispatched,
            'service_distance_km' => $serviceDistanceKm !== null ? round($serviceDistanceKm, 2) : null,
            'pricing' => [
                'tech_target_base' => $pricing['tech_target_base'],
                'vat_amount'       => $pricing['vat_amount'],
                'profit_amount'    => $pricing['profit_amount'],
                'customer_total'   => $pricing['gross_customer_price'],
                'discount'         => $pricing['discount_amount'],
                'final_total'      => $pricing['final_customer_price'],
                'platform_fee'     => $pricing['platform_fee'],
                'tech_net_income'  => $pricing['tech_net_income'],
                'discount_roll'    => $pricing['discount_roll'],
            ],
        ],
    ];
}


function admin_jobs(PDO $pdo): array
{
    if (!table_exists($pdo, 'job_posts')) {
        return [];
    }
    $stmt = $pdo->query('SELECT * FROM job_posts ORDER BY id DESC LIMIT 200');
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $pricing = get_job_pricing($pdo, (int)$row['id']);
        $coordinates = worker_map_coordinates($row);
        $rows[] = [
            'id' => (int)$row['id'],
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_phone' => (string)($row['customer_phone'] ?? ''),
            'service_type' => (string)($row['service_type'] ?? $row['title'] ?? ''),
            'address' => (string)($row['address'] ?? $row['location'] ?? ''),
            'map_location' => (string)($coordinates['text'] ?? ''),
            'maps_url' => worker_google_maps_url($row),
            'description' => (string)($row['description'] ?? ''),
            'final_total' => money_int($row['final_total'] ?? $row['customer_total'] ?? $row['salary_max'] ?? 0),
            'platform_fee' => money_int($pricing['platform_fee'] ?? 0),
            'tech_net_income' => money_int($pricing['tech_net_income'] ?? 0),
            'worker_id' => job_worker_telegram_id($row) ?: null,
            'status' => job_display_status($row),
            'spam_count' => (int)($row['spam_count'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'completed_at' => (string)($row['completed_at'] ?? ''),
        ];
    }
    return $rows;
}

// ================================================================
// INTERNAL DISPATCH ENGINE — HỆ THỐNG PHÂN PHỐI NỘI BỘ
// Thay thế send_worker_job_to_group() → không phụ thuộc Telegram
// Luồng: create_job_action() → dispatch_job_to_workers_internal()
//        → GPS sort → Push Notification (Expo) → in_app_notifications
// ================================================================

/**
 * Tính khoảng cách Haversine giữa 2 điểm GPS (km)
 */
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

/**
 * Ghi thông báo nội bộ (thay Telegram DM)
 *
 * @param  string $targetType  'worker'|'customer'|'admin'
 * @param  int    $targetId    telegram_user_id (worker) hoặc users.id (customer)
 * @param  string $title       Tiêu đề thông báo
 * @param  string $body        Nội dung thông báo
 * @param  string $type        Loại: new_job|job_assigned|job_completed|...
 * @param  string $refType     Đối tượng: 'job'|'order'|'payment'
 * @param  int    $refId       ID đối tượng
 * @param  array  $payload     Dữ liệu thêm cho App (deeplink, v.v.)
 * @return int    ID thông báo vừa tạo
 */
function create_in_app_notification(
    PDO $pdo,
    string $targetType,
    int $targetId,
    string $title,
    string $body,
    string $type = 'info',
    string $refType = '',
    int $refId = 0,
    array $payload = []
): int {
    if (!table_exists($pdo, 'in_app_notifications')) {
        error_log("[notify] Bảng in_app_notifications chưa tồn tại — chạy migration trước");
        return 0;
    }
    return insert_compat($pdo, 'in_app_notifications', [
        'target_type'   => $targetType,
        'target_id'     => $targetId,
        'title'         => mb_substr($title, 0, 255),
        'body'          => $body,
        'type'          => $type,
        'reference_type' => $refType ?: null,
        'reference_id'  => $refId > 0 ? $refId : null,
        'payload'       => !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        'is_read'       => 0,
    ], ['created_at' => 'NOW()']);
}

/**
 * Đẩy thông báo qua Expo Push API cho một thợ cụ thể
 * Không phụ thuộc Telegram — dùng mobile_sessions.push_token
 */
function push_expo_to_worker(PDO $pdo, int $workerId, array $message): array
{
    $stmt = $pdo->prepare(
        "SELECT push_token FROM mobile_sessions
         WHERE user_id = ? AND push_token IS NOT NULL AND push_token != ''
         ORDER BY last_active_at DESC LIMIT 3"
    );
    $stmt->execute([$workerId]);
    $tokens = array_column($stmt->fetchAll(), 'push_token');
    $tokens = array_unique(array_filter($tokens));

    if (!$tokens) {
        return ['sent' => 0, 'error' => 'no_token'];
    }

    $messages = [];
    foreach ($tokens as $token) {
        $messages[] = array_merge([
            'to'       => $token,
            'sound'    => 'default',
            'priority' => 'high',
        ], $message);
    }

    $ch = curl_init('https://exp.host/--/api/v2/push/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Accept-Encoding: gzip, deflate',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("[expo_push] curl error: {$err}");
        return ['sent' => 0, 'error' => $err];
    }
    return ['sent' => count($tokens), 'response' => $resp];
}

/**
 * Lấy danh sách thợ đang on_shift, sắp xếp theo:
 * 1. Khoảng cách GPS (gần → xa)
 * 2. rating_score (cao → thấp)
 * 3. jobs_completed (nhiều → ít kinh nghiệm)
 *
 * @param  float|null $jobLat      Vĩ độ địa chỉ đơn hàng
 * @param  float|null $jobLng      Kinh độ địa chỉ đơn hàng
 * @param  int        $radiusKm    Bán kính tìm kiếm (km), 0 = tất cả
 * @param  int        $limit       Số thợ tối đa
 * @return array      Danh sách profile thợ kèm distance_km
 */
function get_workers_on_shift(
    PDO $pdo,
    ?float $jobLat,
    ?float $jobLng,
    int $radiusKm = 15,
    int $limit = 10
): array {
    if (!table_exists($pdo, 'worker_profiles')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT wp.*, wp.telegram_user_id AS worker_id
         FROM worker_profiles wp
         WHERE wp.shift_status = 'on_shift'
           AND (wp.is_active IS NULL OR wp.is_active = 1)
           AND (wp.is_receive_blocked IS NULL OR wp.is_receive_blocked = 0)
           AND (wp.payment_blocked IS NULL OR wp.payment_blocked = 0)
         ORDER BY wp.rating_score DESC, wp.jobs_completed DESC"
    );
    $stmt->execute();
    $workers = $stmt->fetchAll();

    if (!$workers) {
        return [];
    }

    // Tính khoảng cách GPS và lọc theo bán kính
    foreach ($workers as &$w) {
        $wLat = isset($w['current_lat']) && $w['current_lat'] !== null
            ? (float)$w['current_lat']
            : (isset($w['last_lat']) ? (float)$w['last_lat'] : null);
        $wLng = isset($w['current_lng']) && $w['current_lng'] !== null
            ? (float)$w['current_lng']
            : (isset($w['last_lng']) ? (float)$w['last_lng'] : null);

        if ($jobLat !== null && $jobLng !== null && $wLat !== null && $wLng !== null) {
            $w['distance_km'] = haversine_km($jobLat, $jobLng, $wLat, $wLng);
        } else {
            $w['distance_km'] = null; // Không biết khoảng cách → ưu tiên thấp
        }
    }
    unset($w);

    // Sắp xếp: GPS có khoảng cách < radiusKm trước, NULL sau, ngoài bán kính cuối
    usort($workers, static function (array $a, array $b) use ($radiusKm): int {
        $aInRange = $a['distance_km'] !== null && $a['distance_km'] <= $radiusKm;
        $bInRange = $b['distance_km'] !== null && $b['distance_km'] <= $radiusKm;
        $aUnknown = $a['distance_km'] === null;
        $bUnknown = $b['distance_km'] === null;

        // Thứ tự: trong bán kính → không rõ GPS → ngoài bán kính
        if ($aInRange && !$bInRange) return -1;
        if (!$aInRange && $bInRange) return 1;
        if ($aUnknown && !$bUnknown) return -1;
        if (!$aUnknown && $bUnknown) return 1;

        // Cùng nhóm: gần hơn trước
        if ($a['distance_km'] !== null && $b['distance_km'] !== null) {
            return $a['distance_km'] <=> $b['distance_km'];
        }
        // Fallback: rating cao hơn
        return (float)($b['rating_score'] ?? 5) <=> (float)($a['rating_score'] ?? 5);
    });

    return array_slice($workers, 0, $limit);
}

/**
 * ENGINE CHÍNH: Dispatch đơn hàng đến thợ nội bộ (không Telegram)
 *
 * - Lấy thợ đang on_shift + sắp xếp GPS
 * - Push Expo notification đến từng thợ
 * - Ghi in_app_notification cho mỗi thợ
 * - Log push_dispatch_log để debug
 * - Nếu không có thợ nào trong bán kính → push tất cả thợ on_shift (fallback)
 *
 * @return bool true nếu push đến ít nhất 1 thợ thành công
 */
function dispatch_job_to_workers_internal(PDO $pdo, int $jobId): bool
{
    $job = get_job_row($pdo, $jobId);
    if (!$job) {
        error_log("[dispatch_internal] Job {$jobId} not found");
        return false;
    }

    $jobLat = isset($job['map_lat']) && $job['map_lat'] !== null ? (float)$job['map_lat'] : null;
    $jobLng = isset($job['map_lng']) && $job['map_lng'] !== null ? (float)$job['map_lng'] : null;

    $radiusKm     = (int)app_env('WORKER_APP_DISPATCH_RADIUS_KM', '15');
    $maxPush      = (int)app_env('WORKER_APP_MAX_PUSH_PER_JOB', '10');
    $fallbackAll  = app_bool_env('WORKER_APP_FALLBACK_ALL_IF_EMPTY', true);

    $pricing = get_job_pricing($pdo, $jobId);
    $netIncome = (int)($pricing['tech_net_income'] ?? 0);
    $serviceType = (string)($job['service_type'] ?? '');
    $address = (string)($job['address'] ?? $job['location'] ?? '');

    // Lấy thợ đang sẵn sàng
    $candidates = get_workers_on_shift($pdo, $jobLat, $jobLng, $radiusKm, $maxPush);

    // Fallback: không có thợ trong bán kính → thử tất cả thợ on_shift
    $inRadius = array_filter($candidates, static fn($w) => $w['distance_km'] === null || $w['distance_km'] <= $radiusKm);
    if (!$inRadius && $fallbackAll) {
        error_log("[dispatch_internal] Job {$jobId} no worker in {$radiusKm}km — fallback to all on_shift");
        $candidates = get_workers_on_shift($pdo, $jobLat, $jobLng, 9999, $maxPush);
    }

    if (!$candidates) {
        error_log("[dispatch_internal] Job {$jobId} no on_shift workers at all");
        return false;
    }

    $pushTitle = "📋 Ca mới #{$jobId}: {$serviceType}";
    $pushBody  = ($address ? "📍 {$address}" : "Khu vực Lấp Vò")
        . ($netIncome > 0 ? " · " . number_format($netIncome, 0, ',', '.') . "đ" : '');

    $successCount = 0;
    $hasDispatchLogTable = table_exists($pdo, 'push_dispatch_log');
    $hasNotifyTable = table_exists($pdo, 'in_app_notifications');

    foreach ($candidates as $worker) {
        $workerId   = (int)($worker['worker_id'] ?? $worker['telegram_user_id'] ?? 0);
        $distanceKm = $worker['distance_km'];

        if ($workerId <= 0) continue;

        // Bỏ qua thợ đang bận
        if ((string)($worker['shift_status'] ?? '') === 'busy') {
            if ($hasDispatchLogTable) {
                insert_compat($pdo, 'push_dispatch_log', [
                    'job_id'      => $jobId,
                    'worker_id'   => $workerId,
                    'distance_km' => $distanceKm,
                    'shift_status' => 'busy',
                    'push_status' => 'skip_busy',
                ], ['dispatched_at' => 'NOW()']);
            }
            continue;
        }

        // Ghi thông báo nội bộ
        $notifPayload = [
            'job_id'       => $jobId,
            'service_type' => $serviceType,
            'address'      => $address,
            'net_income'   => $netIncome,
            'distance_km'  => $distanceKm !== null ? round($distanceKm, 2) : null,
        ];

        if ($hasNotifyTable) {
            create_in_app_notification(
                $pdo,
                'worker',
                $workerId,
                $pushTitle,
                $pushBody,
                'new_job',
                'job',
                $jobId,
                $notifPayload
            );
        }

        // Push Expo notification
        $pushResult = push_expo_to_worker($pdo, $workerId, [
            'title' => $pushTitle,
            'body'  => $pushBody,
            'data'  => $notifPayload,
        ]);

        $pushStatus  = ($pushResult['sent'] ?? 0) > 0 ? 'sent' : 'failed';
        $pushError   = $pushStatus === 'failed' ? ($pushResult['error'] ?? 'unknown') : null;
        $pushToken   = null; // token lấy từ session, không lưu lại đây
        if ($pushStatus === 'failed' && ($pushResult['error'] ?? '') === 'no_token') {
            $pushStatus = 'no_token';
        }

        if ($hasDispatchLogTable) {
            insert_compat($pdo, 'push_dispatch_log', [
                'job_id'      => $jobId,
                'worker_id'   => $workerId,
                'distance_km' => $distanceKm !== null ? round($distanceKm, 3) : null,
                'shift_status' => $worker['shift_status'] ?? 'on_shift',
                'push_status' => $pushStatus,
                'push_response' => $pushResult['response'] ?? ($pushError ?? null),
            ], ['dispatched_at' => 'NOW()']);
        }

        if ($pushStatus === 'sent') {
            $successCount++;
        }

        error_log(sprintf(
            "[dispatch_internal] Job %d → Worker %d (%.1fkm) push=%s",
            $jobId,
            $workerId,
            $distanceKm ?? -1,
            $pushStatus
        ));
    }

    if ($successCount > 0) {
        update_compat($pdo, 'job_posts', ['status' => 'matching'], 'id = ?', [$jobId]);
        error_log("[dispatch_internal] Job {$jobId} dispatched to {$successCount} workers");
    }

    return $successCount > 0;
}

/**
 * Thợ nhận ca trực tiếp từ App (không qua Telegram callback)
 * Ghi in_app_notification thay vì gửi DM Telegram
 */
function claim_job_via_app(PDO $pdo, int $jobId, int $workerId): array
{
    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['ok' => false, 'message' => 'Tài khoản thợ không tồn tại.', 'code' => 'WORKER_NOT_FOUND'];
    }
    if (worker_is_blocked($profile)) {
        $debt = worker_fee_debt($pdo, $workerId);
        return [
            'ok'      => false,
            'message' => $debt > 0
                ? 'Tài khoản đang bị khoá nhận ca. Nợ phí nền tảng: ' . fmt_money($debt) . '.'
                : 'Tài khoản thợ đang bị khoá nhận ca. Liên hệ admin.',
            'code'    => 'WORKER_BLOCKED',
        ];
    }

    $workerName = (string)($profile['telegram_name'] ?? $profile['phone'] ?? "Thợ #{$workerId}");

    // Chạy transaction claim
    $pdo->beginTransaction();
    try {
        $job = get_job_row($pdo, $jobId, true);
        if (!$job) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Không tìm thấy ca.', 'code' => 'JOB_NOT_FOUND'];
        }
        if (job_display_status($job) !== 'pending') {
            // Ghi late claim
            insert_compat($pdo, 'job_claims', [
                'job_id'          => $jobId,
                'telegram_user_id' => $workerId,
                'telegram_name'   => $workerName,
                'outcome'         => 'late',
                'note'            => 'Claimed via App but job no longer pending',
            ], ['created_at' => 'NOW()']);
            $pdo->commit();
            return ['ok' => false, 'message' => 'Ca này đã có thợ nhận hoặc đã đóng.', 'code' => 'JOB_ALREADY_TAKEN'];
        }

        // Assign
        update_compat($pdo, 'job_posts', array_merge(
            job_assignment_values($pdo, $workerId),
            ['status' => job_status($pdo, 'assigned'), 'app_worker_id' => $workerId]
        ), 'id = ?', [$jobId], ['assigned_at' => 'NOW()', 'updated_at' => 'NOW()']);

        // Ghi claim
        insert_compat($pdo, 'job_claims', [
            'job_id'          => $jobId,
            'telegram_user_id' => $workerId,
            'telegram_name'   => $workerName,
            'outcome'         => 'claimed',
            'note'            => 'Claimed via Worker App (no Telegram)',
        ], ['created_at' => 'NOW()']);

        // Cập nhật shift status → busy
        if (table_exists($pdo, 'worker_profiles')) {
            update_compat($pdo, 'worker_profiles',
                ['jobs_claimed' => null, 'shift_status' => 'busy'],
                'telegram_user_id = ?', [$workerId],
                ['jobs_claimed' => 'jobs_claimed + 1', 'updated_at' => 'NOW()']
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Gửi thông báo nội bộ đến thợ với đầy đủ chi tiết
    $pricing = get_job_pricing($pdo, $jobId);
    $mapsUrl = worker_google_maps_url($job);
    $body = "Khách: " . ($job['customer_name'] ?? '') . "\n"
          . "SĐT: " . ($job['customer_phone'] ?? '') . "\n"
          . "Địa chỉ: " . ($job['address'] ?? $job['location'] ?? '') . "\n"
          . "Thu nhập dự kiến: " . fmt_money((int)($pricing['tech_net_income'] ?? 0));

    if (table_exists($pdo, 'in_app_notifications')) {
        create_in_app_notification(
            $pdo,
            'worker',
            $workerId,
            "✅ Bạn đã nhận ca #{$jobId}",
            $body,
            'job_assigned',
            'job',
            $jobId,
            [
                'job_id'         => $jobId,
                'customer_phone' => $job['customer_phone'] ?? '',
                'address'        => $job['address'] ?? '',
                'maps_url'       => $mapsUrl,
                'net_income'     => (int)($pricing['tech_net_income'] ?? 0),
                'platform_fee'   => (int)($pricing['platform_fee'] ?? 0),
            ]
        );
    }

    // Push confirmation tới thợ
    push_expo_to_worker($pdo, $workerId, [
        'title' => "✅ Đã xác nhận ca #{$jobId}",
        'body'  => "Xem chi tiết trong ứng dụng.",
        'data'  => ['job_id' => $jobId, 'type' => 'job_assigned'],
    ]);

    // Thông báo cho khách
    notify_customer_job_assigned($pdo, $jobId, $workerName);

    return [
        'ok'      => true,
        'message' => "Đã xác nhận bạn nhận ca #{$jobId}. Kiểm tra thông báo trong ứng dụng!",
        'job_id'  => $jobId,
    ];
}

/**
 * Thợ hoàn thành ca qua App (không gửi DM Telegram)
 */
function complete_job_via_app(PDO $pdo, int $jobId, int $workerId, int $actualAmount = 0): array
{
    $pdo->beginTransaction();
    try {
        $job = get_job_row($pdo, $jobId, true);
        if (!$job || !job_belongs_to_worker($pdo, $job, $workerId)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Ca không thuộc thợ này.', 'code' => 'JOB_NOT_ASSIGNED'];
        }
        if (job_display_status($job) === 'completed') {
            $pdo->commit();
            return ['ok' => true, 'message' => "Ca #{$jobId} đã được ghi nhận hoàn thành trước đó."];
        }

        $pricing = get_job_pricing($pdo, $jobId);

        // Nếu thợ báo giá thực tế khác → recalculate
        if ($actualAmount > 0 && $actualAmount !== (int)($job['final_total'] ?? 0)) {
            $qty = max(1, (int)($job['quantity'] ?? 1));
            $newPricing = calculate_job_pricing(0, $actualAmount, $qty);
            update_compat($pdo, 'job_pricing', [
                'gross_customer_price' => $newPricing['gross_customer_price'],
                'final_customer_price' => $newPricing['final_customer_price'],
                'platform_fee'         => $newPricing['platform_fee'],
                'tech_net_income'      => $newPricing['tech_net_income'],
                'payment_status'       => 'unpaid',
            ], 'job_id = ?', [$jobId]);
            update_compat($pdo, 'job_posts', [
                'final_total'    => $newPricing['final_customer_price'],
                'customer_total' => $newPricing['gross_customer_price'],
            ], 'id = ?', [$jobId]);
            $pricing = $newPricing; // Dùng pricing mới để ghi log
        }

        update_compat($pdo, 'job_posts', [
            'status' => job_status($pdo, 'completed'),
        ], 'id = ?', [$jobId], ['completed_at' => 'NOW()', 'updated_at' => 'NOW()']);

        // Ghi receivable phí nền tảng
        insert_compat($pdo, 'finances', [
            'type'        => 'platform_fee_receivable',
            'amount'      => (int)($pricing['platform_fee'] ?? 0),
            'source_type' => 'job',
            'source_id'   => $jobId,
            'note'        => "Platform fee từ thợ #{$workerId} - Ca #{$jobId}",
        ], ['created_at' => 'NOW()']);

        // Cập nhật thợ: xong ca → trở lại on_shift
        update_compat($pdo, 'worker_profiles', [
            'shift_status' => 'on_shift',
        ], 'telegram_user_id = ?', [$workerId],
        ['jobs_completed' => 'jobs_completed + 1', 'updated_at' => 'NOW()']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $cumulativeDebt = worker_fee_debt($pdo, $workerId);
    $platformFee    = (int)($pricing['platform_fee'] ?? 0);

    // Thông báo nội bộ thợ: phí nền tảng cộng dồn
    if (table_exists($pdo, 'in_app_notifications')) {
        create_in_app_notification(
            $pdo,
            'worker',
            $workerId,
            "💰 Ca #{$jobId} hoàn thành",
            "Phí nền tảng ca này: " . fmt_money($platformFee) . "\n"
            . "Tổng nợ phí đến hiện tại: " . fmt_money($cumulativeDebt) . "\n"
            . "Phí sẽ được nhắc nhở mỗi sáng thứ Hai.",
            'platform_fee',
            'job',
            $jobId,
            ['platform_fee' => $platformFee, 'cumulative_debt' => $cumulativeDebt]
        );
    }

    // Thông báo cho khách
    notify_customer_job_completed($pdo, $jobId);

    return [
        'ok'              => true,
        'message'         => "Đã đánh dấu ca #{$jobId} hoàn thành.",
        'platform_fee'    => $platformFee,
        'cumulative_debt' => $cumulativeDebt,
    ];
}

/**
 * Thợ hủy ca qua App
 */
function cancel_job_via_app(PDO $pdo, int $jobId, int $workerId, string $reason = ''): array
{
    $job = get_job_row($pdo, $jobId);
    if (!$job || !job_belongs_to_worker($pdo, $job, $workerId)) {
        return ['ok' => false, 'message' => 'Ca không thuộc thợ này.', 'code' => 'JOB_NOT_ASSIGNED'];
    }
    $reason = $reason ?: 'Thợ hủy ca qua ứng dụng';

    update_compat($pdo, 'job_posts', [
        'worker_id'        => null,
        'telegram_worker_id' => null,
        'app_worker_id'    => null,
        'status'           => job_status($pdo, 'pending'),
        'cancel_reason'    => $reason,
    ], 'id = ?', [$jobId], ['cancelled_at' => 'NOW()', 'updated_at' => 'NOW()']);

    insert_compat($pdo, 'job_claims', [
        'job_id'          => $jobId,
        'telegram_user_id' => $workerId,
        'telegram_name'   => (string)(get_worker_profile($pdo, $workerId)['telegram_name'] ?? "Thợ #{$workerId}"),
        'outcome'         => 'cancelled',
        'note'            => $reason,
    ], ['created_at' => 'NOW()']);

    $profile = increment_worker_penalty($pdo, $workerId, 'cancel_job');

    // Cập nhật shift_status về on_shift
    update_compat($pdo, 'worker_profiles',
        ['shift_status' => 'on_shift'],
        'telegram_user_id = ?', [$workerId],
        ['updated_at' => 'NOW()']
    );

    // Thông báo nội bộ cho thợ
    $cancelCount = (int)($profile['cancel_count'] ?? 0);
    if (table_exists($pdo, 'in_app_notifications')) {
        create_in_app_notification(
            $pdo,
            'worker',
            $workerId,
            "Ca #{$jobId} đã bị hủy",
            "Lý do: {$reason}\nSố lần vi phạm: {$cancelCount}/" . tech_cancel_limit(),
            'job_cancelled',
            'job',
            $jobId
        );
    }

    // Tái dispatch đơn cho thợ khác
    dispatch_job_to_workers_internal($pdo, $jobId);

    $message = "Đã hủy ca #{$jobId}. Vi phạm: {$cancelCount}/" . tech_cancel_limit() . '.';
    if (worker_is_blocked($profile)) {
        $message .= ' Tài khoản đã bị khoá nhận ca.';
    }
    return ['ok' => true, 'message' => $message];
}

/**
 * Helper: Thông báo khách hàng khi thợ nhận ca
 */
function notify_customer_job_assigned(PDO $pdo, int $jobId, string $workerName): void
{
    try {
        $job = get_job_row($pdo, $jobId);
        if (!$job) return;
        $customerPhone = (string)($job['customer_phone'] ?? '');
        if (!$customerPhone) return;
        $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$customerPhone]);
        $customerId = (int)($stmt->fetchColumn() ?: 0);
        if ($customerId <= 0) return;

        if (table_exists($pdo, 'in_app_notifications')) {
            create_in_app_notification(
                $pdo,
                'customer',
                $customerId,
                "Thợ đã nhận ca của bạn 🔧",
                "Thợ {$workerName} đang trên đường đến địa chỉ của bạn.",
                'job_assigned',
                'job',
                $jobId,
                ['job_id' => $jobId, 'worker_name' => $workerName]
            );
        }
        // Push Expo đến khách
        mobile_send_push($pdo, 'customer', $customerId, [
            'title' => 'Thợ đã nhận ca của bạn 🔧',
            'body'  => "Thợ {$workerName} đang đến địa chỉ của bạn.",
            'data'  => ['job_id' => $jobId, 'type' => 'job_assigned'],
        ]);
    } catch (Throwable $e) {
        error_log('[notify_customer_assigned] ' . $e->getMessage());
    }
}

/**
 * Helper: Thông báo khách hàng khi ca hoàn thành
 */
function notify_customer_job_completed(PDO $pdo, int $jobId): void
{
    try {
        $job = get_job_row($pdo, $jobId);
        if (!$job) return;
        $customerPhone = (string)($job['customer_phone'] ?? '');
        if (!$customerPhone) return;
        $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$customerPhone]);
        $customerId = (int)($stmt->fetchColumn() ?: 0);
        if ($customerId <= 0) return;

        if (table_exists($pdo, 'in_app_notifications')) {
            create_in_app_notification(
                $pdo,
                'customer',
                $customerId,
                "Ca hoàn thành ✅",
                "Thợ đã hoàn thành ca #{$jobId}. Cảm ơn bạn đã sử dụng dịch vụ Điện Máy Hiếu!",
                'job_completed',
                'job',
                $jobId,
                ['job_id' => $jobId]
            );
        }
        mobile_send_push($pdo, 'customer', $customerId, [
            'title' => 'Ca hoàn thành ✅',
            'body'  => "Ca #{$jobId} đã hoàn thành. Vui lòng đánh giá dịch vụ!",
            'data'  => ['job_id' => $jobId, 'type' => 'job_completed'],
        ]);
    } catch (Throwable $e) {
        error_log('[notify_customer_completed] ' . $e->getMessage());
    }
}

/**
 * Tạo mã thợ nội bộ: DTH-001, DTH-002, ...
 */
function generate_worker_code(PDO $pdo): string
{
    if (!column_exists($pdo, 'worker_profiles', 'worker_code')) {
        return '';
    }
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(worker_code, 5) AS UNSIGNED)) AS max_num FROM worker_profiles WHERE worker_code LIKE 'DTH-%'");
    $row = $stmt->fetch();
    $next = ((int)($row['max_num'] ?? 0)) + 1;
    return 'DTH-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

