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

function send_worker_job_to_group(PDO $pdo, int $jobId): bool
{
    $job = get_job_row($pdo, $jobId);
    if (!$job) {
        return false;
    }
    $role = telegram_normalize_role((string)($job['bot_role'] ?? 'worker'));
    $chatId = trim((string)($job['target_group_id'] ?? ''));
    if ($chatId === '') {
        $chatId = get_bot_group_chat_id($role);
    }

    $pricing = get_job_pricing($pdo, $jobId);

    $publicDescription = mask_phone_like_text((string)($job['description'] ?? ''));
    $coordinates = worker_map_coordinates($job);
    $mapsUrl = worker_google_maps_url($job);
    $job['customer_phone'] = mask_phone((string)($job['customer_phone'] ?? ''));
    
    $text = "<b>BÁO CA MỚI #{$jobId}</b>\n"
        . "Loại dịch vụ: " . esc_html($job['service_type'] ?? '') . "\n"
        . "SĐT khách: " . esc_html((string)($job['customer_phone'] ?? '')) . "\n"
        . "Địa chỉ: " . esc_html((string)($job['address'] ?? '')) . "\n"
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
        return true;
    }
    update_compat($pdo, 'job_posts', ['status' => 'failed', 'cancel_reason' => 'Lỗi gửi Telegram báo ca'], 'id = ?', [$jobId]);
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

        insert_compat($pdo, 'job_claims', [
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

    // Trigger synchronous claim job notification
    $dmSuccess = process_async_claim_job(pdo(), $jobId, $workerId, $workerName, $username, $role);
    
    if (!$dmSuccess) {
        return ['ok' => false, 'message' => 'LỖI: Bot chưa thể gửi tin nhắn cho bạn! Vui lòng bấm vào tên Bot, chọn NHẮN TIN (hoặc /start), sau đó quay lại đây bấm NHẬN CA lại nhé!'];
    }
    
    return ['ok' => true, 'message' => "Đã xác nhận bạn nhận ca #{$jobId}. Vui lòng kiểm tra tin nhắn riêng của Bot!"];
}

function process_async_claim_job(PDO $pdo, int $jobId, int $workerId, string $workerName, string $username, string $role): bool
{
    $job = get_job_row($pdo, $jobId) ?: [];
    if (!$job || job_display_status($job) !== 'assigned' || job_worker_telegram_id($job) !== $workerId) {
        return false; // Job is not in the correct state to notify worker
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
    $resp = tg_send($role, (string)$workerId, $dm, $dmKeyboard);
    $messageId = (int)($resp['result']['message_id'] ?? 0);
    if (empty($resp['ok']) || $messageId <= 0) {
        update_compat($pdo, 'job_posts', [
            'worker_id' => null,
            'telegram_worker_id' => null,
            'status' => 'new',
            'cancel_reason' => 'DM to worker failed; worker may need /start.',
        ], 'id = ?', [$jobId], ['updated_at' => 'NOW()']);
        return false;
    }
    save_tg_map($pdo, $role, (string)$workerId, $messageId, 'job', $jobId);

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
    // BƯỚC 1: VALIDATE ĐẦU VÀO — trả về JSON 400 nếu thiếu trường bắt buộc.
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
    // BƯỚC 4: DISPATCH — gửi Telegram đến nhóm Thợ
    // Nếu thành công → chuyển sang "matching" (đang khớp thợ)
    // Nếu thất bại   → giữ "pending", kích async để thử lại
    // ----------------------------------------------------------------
    $sent = send_worker_job_to_group($pdo, $jobId);
    if ($sent) {
        update_compat($pdo, 'job_posts', ['status' => 'matching'], 'id = ?', [$jobId]);
    } else {
        trigger_async_job_dispatch($jobId);
    }

    // ----------------------------------------------------------------
    // BƯỚC 5: RESPONSE — Chuẩn JSON { success, message, data }
    // Tuyệt đối không trả về HTML hoặc để trống khi có lỗi.
    // ----------------------------------------------------------------
    return [
        'success' => true,
        'message' => $sent
            ? 'Đã tạo yêu cầu và thông báo đến nhóm Thợ qua Telegram.'
            : 'Đã tạo yêu cầu. Hệ thống sẽ thông báo Thợ trong giây lát.',
        'data' => [
            'job_id'              => $jobId,
            'status'              => $sent ? 'matching' : 'pending',
            'telegram_sent'       => $sent,
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
