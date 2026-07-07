<?php
ob_start();
register_shutdown_function(static function (): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!is_array($error) || !in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $debug = in_array(strtolower((string)(getenv('APP_DEBUG') ?: 'false')), ['1', 'true', 'yes', 'on'], true);
    echo json_encode([
        'status' => 'error',
        'message' => $debug ? (string)($error['message'] ?? 'Fatal server error') : 'Server error. Check PHP error_log.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function api_master_deployment_error(string $message): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'status' => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

foreach ([
    'core.php',
    'jobs.php',
    'webhooks.php',
    'workers.php',
    'users.php',
    'orders.php',
    'vouchers.php',
    'products.php',
    'finances.php',
    'cron.php',
    'ai.php',
    'viettel.php',
] as $apiModule) {
    $apiModulePath = __DIR__ . '/api/' . $apiModule;
    if (!is_file($apiModulePath)) {
        api_master_deployment_error('Missing deployment file: api/' . $apiModule . '. Upload all api/*.php modules.');
    }
    require_once $apiModulePath;
}



if (defined('DTH_API_LIBRARY_ONLY')) {
    return;
}

$action = clean_string($_GET['action'] ?? '', 80);
if ($action === '') {
    json_out(['status' => 'ok', 'message' => 'api_master v2']);
}

if ($action === 'telegram_webhook_info') {
    $role = telegram_request_role();
    $token = telegram_token($role);
    $info = ['role' => $role, 'token_present' => $token !== '', 'token_length' => strlen($token)];
    if ($token !== '' && function_exists('curl_init')) {
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/getWebhookInfo');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded) && !empty($decoded['ok']) && is_array($decoded['result'])) {
            $info['webhook'] = $decoded['result'];
        } else {
            $info['error'] = $decoded['description'] ?? 'Unable to fetch webhook info';
        }
    }
    json_out(['status' => 'ok', 'info' => $info]);
}

if ($action === 'telegram_set_webhook') {
    try {
        $secret = (string)($_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
        $expected = app_env('CRON_SECRET', '');
        if ($expected === '' || !hash_equals($expected, $secret)) {
            json_out(['status' => 'error', 'message' => 'Invalid cron secret.'], 403);
        }
        $role = telegram_request_role();
        $token = telegram_token($role);
        if ($token === '') {
            json_out(['status' => 'error', 'message' => 'Missing bot token'], 400);
        }
        $url = app_public_url() . '/api_master.php?action=telegram_webhook&bot=' . $role;
        $webhookSecret = app_env('TELEGRAM_WEBHOOK_SECRET', '');
        $payload = [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'edited_message', 'channel_post', 'my_chat_member', 'chat_member'],
        ];
        if ($webhookSecret !== '') {
            $payload['secret_token'] = $webhookSecret;
        }
        $resp = tg_request($role, 'setWebhook', $payload);
        if (!empty($resp['ok'])) {
            json_out(['status' => 'success', 'message' => 'Webhook configured.', 'url' => $url, 'role' => $role]);
        }
        json_out(['status' => 'error', 'message' => $resp['description'] ?? 'Unknown error', 'telegram_response' => $resp], 502);
    } catch (Throwable $e) {
        api_exception_out($e);
    }
}

if ($action === 'telegram_webhook') {
    try {
        handle_telegram_webhook();
    } catch (Throwable $e) {
        api_exception_out($e);
    }
}

if ($action === 'async_dispatch_job') {
    ignore_user_abort(true);
    set_time_limit(0);
    $jobId = (int)($_GET['job_id'] ?? 0);
    if ($jobId > 0) {
        $pdo = pdo();
        $job = get_job_row($pdo, $jobId);
        if ($job && job_display_status($job) === 'pending') {
            $sent = send_worker_job_to_group($pdo, $jobId);
            if ($sent) {
                update_compat($pdo, 'job_posts', ['status' => 'matching'], 'id = ?', [$jobId]);
            } else {
                // Re-queue another attempt in ~30 seconds via recursive async call
                $retryUrl = app_public_url() . '/api_master.php?action=async_dispatch_job&job_id=' . $jobId;
                if (function_exists('curl_init')) {
                    $ch = curl_init($retryUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_TIMEOUT_MS => 200,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
        }
    }
    json_out(['status' => 'ok']);
}

if ($action === 'async_notify_order') {
    ignore_user_abort(true);
    set_time_limit(0);
    $orderId = (int)($_GET['order_id'] ?? 0);
    $token = (string)($_GET['token'] ?? '');
    if ($orderId <= 0 || !order_notification_token_valid($orderId, $token)) {
        json_out(['status' => 'error', 'message' => 'Invalid order notification token.'], 403);
    }
    send_order_to_boss(pdo(), $orderId);
    json_out(['status' => 'ok']);
}


if ($action === 'seed_bct_test') {
    $secret = $_GET['secret'] ?? '';
    $expected = app_env('CRON_SECRET', '');
    if ($secret !== $expected || $expected === '') {
        json_out(['status' => 'error', 'message' => 'Unauthorized'], 403);
    }
    $emails = ['qltmdt@moit.gov.vn', 'qlhdtmdt@gmail.com'];
    try {
        $pdo = pdo();
        $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
        $results = [];
        foreach ($emails as $email) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $pdo->prepare('UPDATE users SET password_hash=?, role="admin", is_test_account=1, status="active" WHERE email=?')->execute([$hash, $email]);
                $results[] = "[UPDATE] $email (role=admin, pass=Admin@123)";
            } else {
                $pdo->prepare('INSERT INTO users (role,fullname,email,phone,password_hash,is_test_account,status,created_at) VALUES ("admin","BCT Test Account",?,"0123456789",?,1,"active",NOW())')->execute([$email, $hash]);
                $results[] = "[CREATE] $email (role=admin, pass=Admin@123)";
            }
        }
        json_out(['status' => 'success', 'messages' => $results]);
    } catch (Exception $e) {
        json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
if ($action === 'async_claim_job') {
    ignore_user_abort(true);
    set_time_limit(0);
    $input = request_data();
    $jobId = (int)($input['job_id'] ?? 0);
    $workerId = (int)($input['worker_id'] ?? 0);
    if ($jobId > 0 && $workerId > 0) {
        process_async_claim_job(pdo(), $jobId, $workerId, (string)($input['worker_name'] ?? ''), (string)($input['username'] ?? ''), (string)($input['role'] ?? 'worker'));
    }
    json_out(['status' => 'ok']);
}

if ($action === 'sepay_webhook') {
    try {
        handle_sepay_webhook();
    } catch (Throwable $e) {
        api_exception_out($e);
    }
}

if ($action === 'momo_worker_payment') {
    try {
        handle_momo_worker_payment();
    } catch (Throwable $e) {
        api_exception_out($e);
    }
}

if ($action === 'momo_ipn') {
    try {
        handle_momo_ipn();
    } catch (Throwable $e) {
        api_exception_out($e);
    }
}

if (in_array($action, ['cron_worker_fee_notice', 'cron_worker_fee_lock', 'cron_baocao_ngay'], true)) {
    try {
        verify_cron_secret();
        $pdo = pdo();
        if ($action === 'cron_worker_fee_notice') {
            json_out(['status' => 'success', 'result' => notify_all_worker_debts($pdo, 'Nhac phi nen tang thu 2')]);
        }
        if ($action === 'cron_worker_fee_lock') {
            json_out(['status' => 'success', 'result' => lock_all_workers_with_debt($pdo)]);
        }
        if ($action === 'cron_baocao_ngay') {
            json_out(['status' => 'success', 'result' => send_daily_business_report($pdo)]);
        }
    } catch (Throwable $e) {
        api_exception_out($e);
    }
}

try {
    require_admin_for_action($action);
    $input = request_data();
    if (in_array($action, ['gemini_chat', 'anh_thien_chat'], true)) {
        json_out(['status' => 'success', 'reply' => gemini_quote_reply($input)]);
    }
    $pdo = pdo();

    switch ($action) {
    case 'health':
        $pdo = pdo();
        $workerOk = false;
        $reportOk = false;
        $workerError = '';
        $reportError = '';
        try {
            $workerChat = telegram_chat('worker');
            $workerToken = telegram_token('worker');
            if ($workerToken !== '' && $workerChat !== '') {
                $me = tg_request('worker', 'getMe', []);
                $workerOk = !empty($me['ok']);
                if (!$workerOk) {
                    $workerError = $me['description'] ?? 'getMe failed';
                }
            } else {
                $workerError = 'Missing worker token or chat id';
            }
        } catch (Throwable $e) {
            $workerError = $e->getMessage();
        }
        try {
            $reportChat = telegram_chat('report');
            $reportToken = telegram_token('report');
            if ($reportToken !== '' && $reportChat !== '') {
                $me = tg_request('report', 'getMe', []);
                $reportOk = !empty($me['ok']);
                if (!$reportOk) {
                    $reportError = $me['description'] ?? 'getMe failed';
                }
            } else {
                $reportError = 'Missing report token or chat id';
            }
        } catch (Throwable $e) {
            $reportError = $e->getMessage();
        }
        json_out([
            'status' => ($workerOk || $reportOk) ? 'ok' : 'degraded',
            'time' => date('c'),
            'database' => $pdo ? 'connected' : 'disconnected',
            'telegram_worker' => ['ok' => $workerOk, 'error' => $workerError],
            'telegram_report' => ['ok' => $reportOk, 'error' => $reportError],
        ]);

    case 'get_products':
        json_out(['status' => 'success', 'data' => products_for_store($pdo, $input)]);

    case 'create_order':
        $result = create_order($input);
        $orderId = (int)($result['order_id'] ?? 0);
        if ($orderId > 0) {
            $result['telegram_notification'] = 'queued';
            if (function_exists('fastcgi_finish_request')) {
                http_response_code(200);
                echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                fastcgi_finish_request();
                try {
                    send_order_to_boss($pdo, $orderId);
                } catch (Throwable $e) {
                    error_log('[order] Telegram sending failed after response: ' . $e->getMessage());
                }
                exit;
            }
            trigger_async_order_notification($orderId);
        }
        json_out($result);

    case 'create_job':
        json_out(create_job_action($input));

    case 'app_services':
        json_out(['status' => 'success', 'data' => public_service_catalog()]);

    case 'app_book_job':
        $input['phone'] = $input['phone'] ?? $input['customer_phone'] ?? '';
        $input['service_type'] = $input['service_type'] ?? $input['service_name'] ?? '';
        $input['selected_service_name'] = $input['selected_service_name'] ?? $input['service_name'] ?? '';
        $input['issue_description'] = $input['issue_description'] ?? $input['description'] ?? $input['service_name'] ?? '';
        $input['estimated_price'] = $input['estimated_price'] ?? $input['price'] ?? 0;
        json_out(create_job_action($input));

    case 'app_job_status':
        $booking_id = (int)($input['booking_id'] ?? 0);
        $job = get_job_row($pdo, $booking_id);
        if (!$job) {
            json_out(['status' => 'error', 'message' => 'Khong tim thay don.'], 404);
        }
        $statusCode = job_display_status($job);
        $statusText = [
            'pending' => 'Chờ thợ nhận ca',
            'assigned' => 'Thợ đang đến',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            'spam' => 'Yêu cầu không hợp lệ',
            'failed' => 'Chưa gửi được đến nhóm thợ',
            'notified' => 'Đã báo nhóm thợ, chờ nhận',
            'matching' => 'Đang tìm thợ',
        ][$statusCode] ?? 'Đang cập nhật';
        $worker = null;
        $workerId = job_worker_telegram_id($job);
        if ($workerId > 0) {
            $profile = get_worker_profile($pdo, $workerId);
            $worker = [
                'id' => $workerId,
                'name' => (string)($profile['telegram_name'] ?? "Thợ {$workerId}"),
                'phone' => (string)($profile['phone'] ?? ''),
                'rating_score' => (float)($profile['rating_score'] ?? 5.0),
                'rating_count' => (int)($profile['rating_count'] ?? 0),
            ];
        }
        json_out([
            'status' => 'success',
            'data' => [
                'status_code' => $statusCode,
                'status_text' => $statusText,
                'amount' => fmt_money((int)($job['final_total'] ?? $job['customer_total'] ?? 0)),
                'worker' => $worker,
                'created_at' => (string)($job['created_at'] ?? ''),
                'assigned_at' => (string)($job['assigned_at'] ?? ''),
                'completed_at' => (string)($job['completed_at'] ?? ''),
            ]
        ]);

    case 'app_rate_job':
        // API để App gửi đánh giá sau khi hoàn thành
        $booking_id = (int)($input['booking_id'] ?? 0);
        $rating = (int)($input['rating'] ?? 5);
        $stmt = $pdo->prepare('UPDATE job_posts SET review_score = ? WHERE id = ?');
        $stmt->execute([$rating, $booking_id]);
        json_out(['status' => 'success']);

        case 'check_voucher':
        $code = clean_string($input['coupon_code'] ?? $input['voucher_code'] ?? $input['code'] ?? '', 80);
        if ($code === '') {
            json_out(['status' => 'error', 'message' => 'Vui lòng nhập mã voucher.'], 400);
        }
        $basePrice = (int)($input['base_price'] ?? 0);
        
        $discountAmount = 0;
        $isValid = false;
        
        // 1. Check vouchers table
        $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $v = $stmt->fetch();
        if ($v) {
            $maxUses = (int)($v['max_uses'] ?? $v['usage_limit'] ?? 1);
            $used = (int)($v['used_count'] ?? 0);
            $active = array_key_exists('is_active', $v) ? (int)$v['is_active'] === 1 : true;
            $expires = (string)($v['expires_at'] ?? '');
            
            if ($active && $used < $maxUses && ($expires === '' || strtotime($expires) === false || strtotime($expires) >= time())) {
                $isValid = true;
                $percent = (int)($v['discount_percent'] ?? 0);
                $amount = (int)($v['discount_amount'] ?? 0);
                if ($amount <= 0 && $percent <= 0 && isset($v['type'], $v['value'])) {
                    if ($v['type'] === 'percent') $percent = (int)$v['value'];
                    else $amount = (int)$v['value'];
                }
                
                if ($percent > 0) $amount = max($amount, (int)round($basePrice * min(100, $percent) / 100));
                $discountAmount = $amount;
            }
        }
        
        // 2. Check qr_coupons table if not valid
        if (!$isValid) {
            $stmt = $pdo->prepare('SELECT * FROM qr_coupons WHERE code = ? LIMIT 1');
            $stmt->execute([$code]);
            $qr = $stmt->fetch();
            if ($qr && (int)$qr['is_used'] === 0) {
                if ($qr['type'] === 'discount') {
                    $isValid = true;
                    $discountAmount = (int)$qr['value'];
                } elseif ($qr['type'] === 'prize') {
                    $isValid = true;
                    $discountAmount = (int)round($basePrice * min(100, (int)$qr['value']) / 100);
                }
            }
        }
        
        if (!$isValid) {
            json_out(['status' => 'error', 'message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn.']);
        }
        
        $discountAmount = min($basePrice, max(0, $discountAmount));
        
        json_out([
            'status' => 'success',
            'code' => $code,
            'discount_amount' => $discountAmount,
            'discount_percent' => $percent ?? 0
        ]);

    case 'save_wheel_prize':
        $code = clean_string($input['code'] ?? '', 80);
        $value = (int)($input['value'] ?? 0);
        if ($code === '' || !in_array($value, [5, 10], true)) {
            json_out(['status' => 'error', 'message' => 'Ma vong quay khong hop le.'], 400);
        }
        insert_compat($pdo, 'qr_coupons', [
            'code' => $code,
            'type' => 'prize',
            'value' => $value,
            'description' => clean_string($input['description'] ?? "Voucher {$value}%", 255),
        ], ['created_at' => 'NOW()']);
        json_out(['status' => 'success', 'message' => 'Da luu prize.']);

    case 'generate_qr':
        $count = max(1, min(500, (int)($input['count'] ?? 1)));
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            do {
                $code = generate_code('QR', 4);
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM qr_coupons WHERE code = ?');
                $stmt->execute([$code]);
            } while ((int)$stmt->fetchColumn() > 0);
            insert_compat($pdo, 'qr_coupons', [
                'code' => $code,
                'type' => clean_string($input['type'] ?? 'discount', 30),
                'value' => money_int($input['value'] ?? 0),
                'discount_amount' => money_int($input['value'] ?? 0),
                'quantity_left' => 1,
                'description' => clean_string($input['description'] ?? '', 500),
                'is_used' => 0,
            ], ['created_at' => 'NOW()']);
            $codes[] = $code;
        }
        json_out(['status' => 'success', 'codes' => $codes]);

    case 'generate_voucher':
        $count = max(1, min(500, (int)($input['count'] ?? 1)));
        $percent = max(0, min(100, (int)($input['discount_percent'] ?? 0)));
        $amount = money_int($input['discount_amount'] ?? 0);
        $maxUses = max(1, (int)($input['max_uses'] ?? 100));
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            do {
                $code = generate_code('V', 4);
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM vouchers WHERE code = ?');
                $stmt->execute([$code]);
            } while ((int)$stmt->fetchColumn() > 0);
            insert_compat($pdo, 'vouchers', [
                'code' => $code,
                'discount_percent' => $percent,
                'discount_amount' => $amount,
                'type' => $percent > 0 ? 'percent' : 'fixed',
                'value' => $percent > 0 ? $percent : $amount,
                'max_uses' => $maxUses,
                'usage_limit' => $maxUses,
                'used_count' => 0,
                'is_active' => 1,
            ], ['created_at' => 'NOW()']);
            $codes[] = $code;
        }
        json_out(['status' => 'success', 'codes' => $codes]);

    case 'admin_stats':
        json_out(['status' => 'success', 'stats' => admin_stats($pdo)]);

    case 'admin_daily_settlement_excel':
        output_daily_settlement_excel($pdo);

    case 'admin_notify_worker_fees':
        $result = notify_all_worker_debts($pdo, 'Admin nhac phi nen tang');
        json_out(['status' => 'success', 'message' => "Da gui nhac phi cho {$result['sent']} tho; loi {$result['failed']}.", 'result' => $result]);

    case 'admin_notify_worker_fee':
        $workerId = (int)($input['worker_id'] ?? 0);
        if ($workerId <= 0) {
            json_out(['status' => 'error', 'message' => 'Worker ID khong hop le.'], 400);
        }
        $profile = get_worker_profile($pdo, $workerId);
        $role = telegram_normalize_role((string)($profile['role'] ?? 'worker'));
        $result = send_worker_debt_notice($pdo, $workerId, $role, 'Admin nhac phi nen tang');
        json_out(['status' => $result['ok'] ? 'success' : 'error'] + $result);

    case 'admin_enforce_worker_fee_lock':
        $result = lock_all_workers_with_debt($pdo);
        json_out(['status' => 'success', 'message' => "Da khoa {$result['locked']} tho con no.", 'result' => $result]);

    case 'admin_users':
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            if ((string)($row['login_key'] ?? '') === '') {
                update_compat($pdo, 'users', ['login_key' => customer_generate_login_key($pdo)], 'id = ?', [(int)$row['id']], ['updated_at' => 'NOW()']);
            }
        }
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
        json_out(['status' => 'success', 'data' => array_map('retail_customer_row', $stmt->fetchAll())]);

    case 'admin_customer_lookup':
        $phone = digits_only((string)($input['phone'] ?? $_GET['phone'] ?? ''));
        json_out(['status' => 'success', 'data' => retail_customer_by_phone($pdo, $phone, false)]);

    case 'admin_save_user':
        $id = (int)($input['id'] ?? 0);
        $role = clean_string($input['role'] ?? 'buyer', 30);
        $fullname = clean_string($input['fullname'] ?? '', 150);
        $phone = digits_only((string)($input['phone'] ?? ''));
        $isActive = (int)($input['is_active'] ?? 1);
        $memberRank = clean_string($input['member_rank'] ?? 'Thành viên', 50);
        $totalSpent = (int)($input['total_spent'] ?? 0);
        $loyaltyPoints = max(0, (int)($input['loyalty_points'] ?? 0));

        if ($fullname === '' || $phone === '') {
            json_out(['status' => 'error', 'message' => 'Tên và Số điện thoại không được để trống.'], 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET role = ?, fullname = ?, phone = ?, is_active = ?, member_rank = ?, total_spent = ?, loyalty_points = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$role, $fullname, $phone, $isActive, $memberRank, $totalSpent, $loyaltyPoints, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (role, fullname, phone, login_key, is_active, member_rank, total_spent, loyalty_points, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$role, $fullname, $phone, customer_generate_login_key($pdo), $isActive, $memberRank, $totalSpent, $loyaltyPoints]);
            $id = (int)$pdo->lastInsertId();
        }
        json_out(['status' => 'success', 'message' => 'Đã lưu khách hàng.', 'id' => $id]);

    case 'delete_user':
    case 'admin_delete_user':
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            json_out(['status' => 'error', 'message' => 'ID không hợp lệ.'], 400);
        }
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        json_out(['status' => 'success', 'message' => 'Đã xóa khách hàng.']);

    case 'admin_get_order':
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) json_out(['status' => 'error', 'message' => 'ID không hợp lệ.'], 400);
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) json_out(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng.'], 404);
        json_out(['status' => 'success', 'data' => $order]);

    case 'admin_update_order':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) json_out(['status' => 'error', 'message' => 'ID không hợp lệ.'], 400);
        
        $stmt = $pdo->prepare('SELECT viettel_invoice_exported FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) json_out(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng.'], 404);
        if ($order['viettel_invoice_exported']) json_out(['status' => 'error', 'message' => 'Hóa đơn đã được xuất, không thể chỉnh sửa.']);
        
        $cName = clean_string($input['customer_name'] ?? '', 150);
        $cPhone = digits_only((string)($input['customer_phone'] ?? ''));
        $cAddress = clean_string($input['customer_address'] ?? '', 1000);
        $cTaxCode = clean_string($input['customer_tax_code'] ?? '', 50);
        $pName = clean_string($input['product_name'] ?? '', 255);
        $tPrice = (int)($input['total_price'] ?? 0);
        
        $stmt = $pdo->prepare("UPDATE orders SET customer_name = ?, customer_phone = ?, customer_address = ?, customer_tax_code = ?, product_name = ?, total_price = ? WHERE id = ?");
        $stmt->execute([$cName, $cPhone, $cAddress, $cTaxCode, $pName, $tPrice, $id]);
        json_out(['status' => 'success', 'message' => 'Đã cập nhật đơn hàng thành công.']);

    case 'admin_orders':
        json_out(['status' => 'success', 'data' => admin_orders($pdo)]);

    case 'admin_sales_invoices':
        json_out(['status' => 'success', 'data' => admin_sales_invoice_rows($pdo), 'company' => invoice_company_profile()]);

    case 'admin_invoice_quote':
        try {
            json_out([
                'status' => 'success',
                'calculation' => manual_invoice_calculation($pdo, $input, false),
                'company' => invoice_company_profile(),
            ]);
        } catch (DomainException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

    case 'admin_create_sales_invoice':
        try {
            $invoice = create_manual_sales_invoice($pdo, $input);
            json_out(['status' => 'success', 'message' => 'Da tao hoa don ban hang.', 'invoice' => $invoice]);
        } catch (DomainException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

    case 'admin_retail_sale':
        try {
            $invoice = create_manual_sales_invoice($pdo, $input, true);
            json_out([
                'status' => 'success',
                'message' => 'Da ban hang, cong diem va tao hoa don dien tu.',
                'invoice' => $invoice,
            ]);
        } catch (DomainException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

    case 'admin_invoice':
        $orderId = (int)($input['order_id'] ?? $_GET['order_id'] ?? 0);
        $order = get_order_row($pdo, $orderId);
        if (!$order) {
            json_out(['status' => 'error', 'message' => 'Khong tim thay don hang.'], 404);
        }
        $invoiceCode = 'INV-' . date('Ymd') . '-' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);
        $total = money_int($order['total_price'] ?? $order['total'] ?? 0);
        $subtotal = (int)round($total * 100 / 110);
        $vat = $total - $subtotal;
        $profile = invoice_company_profile();
        $pdo->prepare("INSERT IGNORE INTO invoices
            (invoice_code, order_id, customer_name, customer_phone, product_name, quantity, unit_gross_amount,
             gross_before_discount, discount_amount, promo_code, invoice_date, subtotal_amount, vat_amount, vat_rate,
             adjustment_amount, total_amount, total_price, company_name, company_tax_code, company_address,
             company_phone, company_email, company_website, status, created_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0, ?, CURDATE(), ?, ?, 10, 0, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())")->execute([
                $invoiceCode,
                $orderId,
                (string)($order['customer_name'] ?? ''),
                (string)($order['customer_phone'] ?? ''),
                (string)($order['product_name'] ?? ''),
                $total,
                $total,
                (string)($order['coupon_code'] ?? $order['voucher_code'] ?? ''),
                $subtotal,
                $vat,
                $total,
                $total,
                $profile['name'],
                $profile['tax_code'],
                $profile['address'],
                $profile['phone'],
                $profile['email'],
                $profile['website'],
            ]);
        update_compat($pdo, 'invoices', [
            'subtotal_amount' => $subtotal,
            'vat_amount' => $vat,
            'vat_rate' => 10,
            'total_amount' => $total,
            'total_price' => $total,
            'company_name' => $profile['name'],
            'company_tax_code' => $profile['tax_code'],
            'company_address' => $profile['address'],
            'company_phone' => $profile['phone'],
            'company_email' => $profile['email'],
            'company_website' => $profile['website'],
        ], 'invoice_code = ?', [$invoiceCode]);
        $order['invoice_code'] = $invoiceCode;
        $order['total_price'] = $total;
        $stmt = $pdo->prepare('SELECT * FROM invoices WHERE invoice_code = ? LIMIT 1');
        $stmt->execute([$invoiceCode]);
        json_out(['status' => 'success', 'order' => $order, 'invoice' => sales_invoice_row($stmt->fetch() ?: [])]);

    case 'admin_input_invoices':
        json_out(['status' => 'success', 'data' => admin_input_invoice_rows($pdo)]);

    case 'admin_upload_input_invoice':
        try {
            $saved = admin_save_input_invoice_pdf($pdo, $input, (array)($_FILES['pdf'] ?? []));
            json_out(['status' => 'success', 'message' => 'Da luu PDF hoa don dau vao va SHA-256.', 'data' => $saved]);
        } catch (DomainException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

    case 'admin_input_invoice_file':
        $invoiceId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($invoiceId <= 0) {
            json_out(['status' => 'error', 'message' => 'ID hoa don khong hop le.'], 400);
        }
        admin_stream_input_invoice($pdo, $invoiceId);

    case 'admin_bct_reconciliation':
        try {
            $from = clean_string($input['from'] ?? date('Y-01-01'), 10);
            $to = clean_string($input['to'] ?? date('Y-m-d'), 10);
            $includeDetails = (string)($input['detail'] ?? '1') !== '0';
            json_out(['status' => 'success', 'report' => bct_reconciliation_report($pdo, $from, $to, $includeDetails)]);
        } catch (InvalidArgumentException $e) {
            json_out(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

    case 'admin_products':
        $target = admin_product_target($pdo);
        $data = $target === 'products'
            ? legacy_products_for_store($pdo, '', '', 500)
            : marketplace_products_for_store($pdo, '', '', 500);
        json_out(['status' => 'success', 'data' => $data, 'source' => $target]);

    case 'admin_save_product':
        $savedId = save_admin_product($pdo, $input);
        json_out(['status' => 'success', 'message' => 'Da luu san pham.', 'id' => $savedId]);

    case 'admin_delete_product':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_out(['status' => 'error', 'message' => 'ID khong hop le.'], 400);
        }
        if (admin_product_target($pdo) === 'products') {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            json_out(['status' => 'success', 'message' => 'Da xoa san pham.']);
        }
        if (!table_exists($pdo, 'marketplace_products')) {
            json_out(['status' => 'error', 'message' => 'Khong tim thay bang san pham thuc te.'], 404);
        }
        if (column_exists($pdo, 'marketplace_products', 'status') && column_allows_value($pdo, 'marketplace_products', 'status', 'hidden')) {
            update_compat($pdo, 'marketplace_products', ['status' => 'hidden'], 'id = ?', [$id], ['updated_at' => 'NOW()']);
            json_out(['status' => 'success', 'message' => 'Da an san pham.']);
        }
        $pdo->prepare('DELETE FROM marketplace_products WHERE id = ?')->execute([$id]);
        json_out(['status' => 'success', 'message' => 'Da xoa san pham.']);

    case 'admin_import_excel':
        $rows = is_array($input['data'] ?? null) ? $input['data'] : [];
        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = clean_string($row['Ten_San_Pham'] ?? $row['name'] ?? '', 255);
            if ($name === '') {
                continue;
            }
            save_admin_product($pdo, [
                'name' => $name,
                'price' => money_int($row['Gia_Ban'] ?? $row['price'] ?? 0),
                'stock' => max(1, (int)($row['Ton_Kho'] ?? $row['stock'] ?? 100)),
                'category' => clean_string($row['Danh_Muc'] ?? $row['category'] ?? '', 120),
                'image_url' => clean_string($row['Hinh_Anh'] ?? $row['image_url'] ?? '', 700),
            ]);
            $count++;
        }
        json_out(['status' => 'success', 'message' => "Da import {$count} san pham."]);

    case 'ai_import_product':
        $text = clean_string($input['text'] ?? '', 1000);
        $image = (string)($input['image'] ?? '');
        if ($text === '' && $image === '') {
            json_out(['status' => 'error', 'message' => 'Khong co hinh anh hoac van ban.'], 400);
        }
        try {
            $parsed = dth_gemini_analyze_product($image, $text);

            $image_url = '';
            if ($image !== '') {
                $imgData = explode(',', $image);
                $base64 = $imgData[1] ?? $imgData[0];
                $bin = base64_decode($base64);
                if ($bin !== false) {
                    $ext = 'jpg';
                    if (preg_match('/data:image\/(png|jpeg|jpg|gif|webp);base64/', $image, $matches)) {
                        $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                    }
                    $filename = 'ai_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    file_put_contents($uploadDir . '/' . $filename, $bin);
                    $image_url = 'uploads/' . $filename;
                }
            }

            $saveData = [
                'name' => clean_string($parsed['name'] ?? 'Sản phẩm mới', 255),
                'price' => money_int($parsed['price'] ?? 0),
                'stock' => max(0, (int)($parsed['stock'] ?? 100)),
                'category' => clean_string($parsed['category'] ?? 'Khác', 120),
                'image_url' => $image_url,
            ];

            $savedId = save_admin_product($pdo, $saveData);
            $saveData['id'] = $savedId;

            json_out(['status' => 'success', 'data' => $saveData]);
        } catch (Exception $e) {
            json_out(['status' => 'error', 'error' => $e->getMessage()]);
        }

    case 'login_email_password':
        json_out(login_email_password_action($pdo, $input));

    case 'login_or_register_phone':
        json_out(login_or_register_phone_action($pdo, $input));

    case 'app_customer_get_orders':
        $loginKey = customer_normalize_login_key((string)($input['login_key'] ?? ''));
        if ($loginKey === '') json_out(['status' => 'error', 'message' => 'Thieu key.']);
        $stmt = $pdo->prepare('SELECT phone FROM users WHERE login_key = ? AND is_active = 1');
        $stmt->execute([$loginKey]);
        $phone = $stmt->fetchColumn();
        if (!$phone) json_out(['status' => 'error', 'message' => 'Loi tai khoan.']);
        
        $stmt = $pdo->prepare("
            SELECT id, order_code, product_name, total_price, status, created_at 
            FROM orders 
            WHERE customer_phone = ? 
            ORDER BY id DESC LIMIT 50
        ");
        $stmt->execute([$phone]);
        $orders = $stmt->fetchAll();
        json_out(['status' => 'success', 'data' => $orders]);

    case 'app_customer_confirm_order':
        $loginKey = customer_normalize_login_key((string)($input['login_key'] ?? ''));
        if ($loginKey === '') json_out(['status' => 'error', 'message' => 'Thieu key.']);
        $stmt = $pdo->prepare('SELECT phone, id FROM users WHERE login_key = ? AND is_active = 1');
        $stmt->execute([$loginKey]);
        $user = $stmt->fetch();
        if (!$user) json_out(['status' => 'error', 'message' => 'Loi tai khoan.']);
        $phone = $user['phone'];
        
        $orderId = (int)($input['order_id'] ?? 0);
        $stmtOrder = $pdo->prepare("SELECT status, total_price FROM orders WHERE id = ? AND customer_phone = ?");
        $stmtOrder->execute([$orderId, $phone]);
        $orderData = $stmtOrder->fetch();
        
        if (!$orderData) {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng này.']);
        }
        
        if ($orderData['status'] === 'completed') {
            json_out(['status' => 'error', 'message' => 'Đơn hàng này đã được xác nhận thành công trước đó.']);
        }

        $affected = update_compat($pdo, 'orders', ['status' => 'completed'], 'id = ?', [$orderId], ['updated_at' => 'NOW()']);
        
        if ($affected > 0) {
            $totalPrice = (float)($orderData['total_price'] ?? 0);
            $pointsToAdd = $totalPrice * 0.001;
            if ($pointsToAdd > 0) {
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ?, lucky_spins = lucky_spins + 1 WHERE id = ?");
                $stmtUpdateUser->execute([$pointsToAdd, $user['id']]);
            }
            json_out(['status' => 'success', 'message' => 'Xác nhận thành công! Bạn đã nhận được điểm thưởng và lượt quay.']);
        } else {
            json_out(['status' => 'error', 'message' => 'Không thể cập nhật đơn hàng này.']);
        }

    case 'app_customer_cancel_order':
        $loginKey = customer_normalize_login_key((string)($input['login_key'] ?? ''));
        if ($loginKey === '') json_out(['status' => 'error', 'message' => 'Thieu key.']);
        $stmt = $pdo->prepare('SELECT phone FROM users WHERE login_key = ? AND is_active = 1');
        $stmt->execute([$loginKey]);
        $phone = $stmt->fetchColumn();
        if (!$phone) json_out(['status' => 'error', 'message' => 'Loi tai khoan.']);
        
        $orderId = (int)($input['order_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND customer_phone = ? AND (status = 'pending' OR status = 'Chờ xác nhận')");
        $stmt->execute([$orderId, $phone]);
        if ($stmt->rowCount() > 0) {
            json_out(['status' => 'success', 'message' => 'Đã hủy đơn hàng.']);
        } else {
            json_out(['status' => 'error', 'message' => 'Không thể hủy đơn hàng này.']);
        }

    case 'app_customer_spin_wheel':
        $loginKey = customer_normalize_login_key((string)($input['login_key'] ?? ''));
        if ($loginKey === '') json_out(['status' => 'error', 'message' => 'Thieu key.']);
        
        $stmt = $pdo->prepare('SELECT id, phone, lucky_spins, loyalty_points FROM users WHERE login_key = ? AND is_active = 1');
        $stmt->execute([$loginKey]);
        $user = $stmt->fetch();
        if (!$user) json_out(['status' => 'error', 'message' => 'Lỗi tài khoản.']);
        
        if ((int)$user['lucky_spins'] <= 0) {
            json_out(['status' => 'error', 'message' => 'Bạn đã hết lượt quay! Hãy mua hàng để nhận thêm.']);
        }
        
        // Trừ 1 lượt quay trước
        $stmtUpdate = $pdo->prepare("UPDATE users SET lucky_spins = lucky_spins - 1 WHERE id = ?");
        $stmtUpdate->execute([$user['id']]);
        
        // Random prize index 0-5
        // Tỉ lệ trúng:
        // 0: 10 điểm (30%)
        // 1: Chúc may mắn (20%)
        // 2: 50 điểm (5%)
        // 3: 1 lượt quay (10%)
        // 4: 20 điểm (15%)
        // 5: Chúc may mắn (20%)
        $rand = mt_rand(1, 100);
        $prizeIndex = 1;
        $addedPoints = 0;
        $addedSpins = 0;
        $msg = '';
        
        if ($rand <= 30) { $prizeIndex = 0; $addedPoints = 10; $msg = 'Bạn nhận được 10 điểm!'; }
        elseif ($rand <= 50) { $prizeIndex = 1; $msg = 'Rất tiếc! Chúc bạn may mắn lần sau.'; }
        elseif ($rand <= 55) { $prizeIndex = 2; $addedPoints = 50; $msg = 'Bạn nhận được 50 điểm!'; }
        elseif ($rand <= 65) { $prizeIndex = 3; $addedSpins = 1; $msg = 'Bạn nhận được thêm 1 lượt quay!'; }
        elseif ($rand <= 80) { $prizeIndex = 4; $addedPoints = 20; $msg = 'Bạn nhận được 20 điểm!'; }
        else { $prizeIndex = 5; $msg = 'Rất tiếc! Chúc bạn may mắn lần sau.'; }
        
        if ($addedPoints > 0 || $addedSpins > 0) {
            $stmtUpdate2 = $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ?, lucky_spins = lucky_spins + ? WHERE id = ?");
            $stmtUpdate2->execute([$addedPoints, $addedSpins, $user['id']]);
        }
        
        $newPoints = (float)$user['loyalty_points'] + $addedPoints;
        $newSpins = (int)$user['lucky_spins'] - 1 + $addedSpins;
        
        json_out([
            'status' => 'success',
            'message' => $msg,
            'data' => [
                'prize_index' => $prizeIndex,
                'loyalty_points' => $newPoints,
                'lucky_spins' => $newSpins
            ]
        ]);

    case 'verify_login_key':
        json_out(verify_login_key_action($pdo, $input));

    case 'admin_get_stores':
        json_out(['status' => 'success', 'data' => admin_store_rows($pdo)]);

    case 'admin_settle_stores':
        json_out(admin_settle_stores_action($pdo));

    case 'admin_approve_store':
        json_out(admin_approve_store_action($pdo, $input));

    case 'admin_delete_store':
        json_out(admin_delete_store_action($pdo, $input));

    case 'store_public_report':
        store_public_report_action($pdo, $input);

    case 'admin_jobs':
        json_out(['status' => 'success', 'data' => admin_jobs($pdo)]);

    case 'admin_test_worker_job':
        $pricing = calculate_job_pricing(150000, 0, 1);
        $jobId = insert_repair_job($pdo, [
            'customer_name' => 'TEST BOT',
            'customer_phone' => clean_string($input['phone'] ?? '0979553289', 30),
            'service_type' => clean_string($input['service_type'] ?? 'Dien lanh - Test', 150),
            'address' => clean_string($input['address'] ?? 'Ap Binh Thanh 1, Lap Vo', 500),
            'map_lat' => isset($input['map_lat']) ? (float)$input['map_lat'] : 10.357422,
            'map_lng' => isset($input['map_lng']) ? (float)$input['map_lng'] : 105.522124,
            'description' => clean_string($input['description'] ?? 'Ca test webhook/dispatcher.', 1000),
            'quantity' => 1,
            'customer_total' => $pricing['gross_customer_price'],
            'discount' => $pricing['discount_amount'],
            'final_total' => $pricing['final_customer_price'],
        ]);
        insert_job_pricing($pdo, $jobId, $pricing);
        $sent = send_worker_job_to_group($pdo, $jobId);
        json_out([
            'status' => 'success',
            'message' => $sent ? 'Da gui ca test.' : 'Tao ca test nhung chua gui duoc Telegram.',
            'job_id' => $jobId,
            'platform_fee' => $pricing['platform_fee'],
            'telegram_sent' => $sent,
        ]);

    case 'admin_workers':
        json_out(['status' => 'success', 'data' => admin_worker_rows($pdo)]);

    case 'admin_worker_payments':
        json_out(['status' => 'success', 'data' => admin_worker_payments($pdo)]);

    case 'admin_worker_history':
        $workerId = (int)digits_only($input['worker_id'] ?? '');
        $month = clean_string($input['month'] ?? date('Y-m'), 7);
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        if ($workerId <= 0) {
            json_out(['status' => 'error', 'message' => 'Worker ID khong hop le.'], 400);
        }
        $start = "{$month}-01 00:00:00";
        $end = date('Y-m-t 23:59:59', strtotime($start));
        $stmt = $pdo->prepare("SELECT j.id, j.customer_name, j.customer_phone, j.service_name, j.address, j.created_at, j.completed_at,
                jp.final_customer_price, jp.platform_fee, jp.tech_net_income, jp.paid_amount
            FROM job_posts j
            JOIN job_pricing jp ON jp.job_id = j.id
            WHERE COALESCE(j.telegram_worker_id, j.worker_id) = ? AND j.completed_at IS NOT NULL AND j.completed_at BETWEEN ? AND ?
            ORDER BY j.completed_at DESC");
        $stmt->execute([$workerId, $start, $end]);
        $jobs = $stmt->fetchAll();
        $totalIncome = 0;
        $totalCustomer = 0;
        $totalFee = 0;
        foreach ($jobs as $job) {
            $totalIncome += (int)($job['tech_net_income'] ?? 0);
            $totalCustomer += (int)($job['final_customer_price'] ?? 0);
            $totalFee += (int)($job['platform_fee'] ?? 0);
        }
        json_out([
            'status' => 'success',
            'worker_id' => $workerId,
            'month' => $month,
            'period' => ['start' => $start, 'end' => $end],
            'jobs' => $jobs,
            'summary' => [
                'total_customer_price' => $totalCustomer,
                'total_platform_fee' => $totalFee,
                'total_worker_income' => $totalIncome,
                'job_count' => count($jobs),
            ],
        ]);

    case 'admin_register_worker':
        $workerId = (int)digits_only($input['worker_id'] ?? '');
        $phone = digits_only($input['phone'] ?? '');
        $name = clean_string($input['name'] ?? "Ho kinh doanh {$workerId}", 150);
        $role = telegram_normalize_role(clean_string($input['role'] ?? 'worker', 20));
        if (!in_array($role, ['worker'], true)) {
            $role = 'worker';
        }
        if ($workerId <= 0 || strlen($phone) < 8 || $workerId === admin_telegram_id()) {
            json_out(['status' => 'error', 'message' => 'Telegram ID hoac so dien thoai khong hop le.'], 400);
        }
        $pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, phone, identity_code, worker_type, role, is_admin, registered_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'ho_kinh_doanh', ?, 0, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE telegram_name = VALUES(telegram_name), phone = VALUES(phone), identity_code = VALUES(identity_code),
                worker_type = 'ho_kinh_doanh', role = VALUES(role), is_admin = 0, registered_by = VALUES(registered_by), updated_at = NOW()")
            ->execute([$workerId, $name, $phone, (string)$workerId, $role, admin_telegram_id()]);
        json_out(['status' => 'success', 'message' => 'Da dang ky/cap nhat tho.']);

    case 'admin_sync_telegram_group':
        $groupChat = telegram_chat('worker');
        if ($groupChat === '') {
            json_out(['status' => 'error', 'message' => 'Chua cau hinh WORKER_CHAT_ID.'], 400);
        }
        $synced = 0;
        $admins = tg_request('worker', 'getChatAdministrators', ['chat_id' => $groupChat]);
        if (!empty($admins['ok']) && is_array($admins['result'])) {
            foreach ($admins['result'] as $member) {
                $user = is_array($member['user'] ?? null) ? $member['user'] : [];
                $status = (string)($member['status'] ?? '');
                $uid = (int)($user['id'] ?? 0);
                if ($uid <= 0 || !empty($user['is_bot'])) {
                    continue;
                }
                $name = worker_name($user);
                $username = clean_string((string)($user['username'] ?? ''), 150);
                upsert_worker($pdo, $uid, $name, $username, 'worker');
                if (in_array($status, ['administrator', 'creator'], true)) {
                    $pdo->prepare("UPDATE worker_profiles SET is_admin = 1, role = 'admin', updated_at = NOW() WHERE telegram_user_id = ?")->execute([$uid]);
                }
                $synced++;
            }
        }
        json_out(['status' => 'success', 'synced' => $synced, 'group_chat' => $groupChat, 'telegram_response' => $admins]);

    case 'admin_mark_worker_paid':
        $workerId = (int)($input['worker_id'] ?? 0);
        if ($workerId <= 0) {
            json_out(['status' => 'error', 'message' => 'Worker ID khong hop le.'], 400);
        }
        $debt = worker_fee_debt($pdo, $workerId);
        if ($debt <= 0) {
            json_out(['status' => 'success', 'message' => 'Tho khong con no phi nen tang.', 'remaining' => 0]);
        }
        $amount = money_int($input['amount'] ?? $debt);
        $method = clean_string($input['method'] ?? 'admin_manual', 40);
        $reference = clean_string($input['reference'] ?? ('ADMIN-' . date('YmdHis')), 150);
        $result = settle_worker_payment($pdo, $workerId, $amount, $method, $reference, 'admin_dashboard');
        json_out(['status' => $result['ok'] ? 'success' : 'error'] + $result);

    case 'admin_unban_worker':
        $workerId = (int)($input['worker_id'] ?? 0);
        if ($workerId <= 0) {
            json_out(['status' => 'error', 'message' => 'Worker ID khong hop le.'], 400);
        }
        $pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_receive_blocked = 0, payment_blocked = 0, blocked_until = NULL, block_reason = NULL, cancel_count = 0, abuse_count = 0, updated_at = NOW()")
            ->execute([$workerId, "Worker {$workerId}"]);
        tg_send('worker', (string)$workerId, 'Admin da mo khoa. Ban co the nhan ca lai.');
        json_out(['status' => 'success', 'message' => 'Da mo khoa tho.']);

    case 'app_store_counts':
        json_out(['status' => 'success', 'data' => app_store_counts($pdo)]);

    case 'app_store_login_qr':
        json_out(app_store_login_qr_action($pdo, $input));

    case 'app_store_get_products':
        json_out(app_store_get_products_action($pdo, $input));
    case 'app_store_save_product':
        json_out(app_store_save_product_action($pdo, $input));
    case 'app_store_delete_product':
        json_out(app_store_delete_product_action($pdo, $input));
    case 'app_store_scan_menu':
        json_out(app_store_scan_menu_action($pdo, $input));

    case 'app_store_login':
        if (isset($input['login_key']) || isset($input['qr_data']) || isset($input['key'])) {
            json_out(app_store_login_qr_action($pdo, $input));
        }
        json_out(['status' => 'error', 'message' => 'Không còn chức năng đăng ký cửa hàng mới.'], 400);

    case 'app_customer_register':
        json_out(app_customer_register_action($pdo, $input));

    case 'app_customer_login_qr':
        json_out(app_customer_login_qr_action($pdo, $input));

    case 'app_services_legacy_products_disabled':
        $services = products_for_store($pdo, []);
        json_out(['status' => 'success', 'data' => $services]);

    case 'app_store_login_legacy_disabled':
        $phone = clean_string($input['phone'] ?? '', 30);
        $tax_code = clean_string($input['tax_code'] ?? '', 30);
        if ($phone === '' || $tax_code === '') {
            json_out(['status' => 'error', 'message' => 'Vui lòng nhập SĐT và Mã Số Thuế.']);
        }
        $ch = curl_init('https://api.vietqr.io/v2/business/' . urlencode($tax_code));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        $bizData = json_decode($res, true);
        if (!$bizData || ($bizData['code'] ?? '') !== '00') {
            json_out(['status' => 'error', 'message' => 'Mã Số Thuế không hợp lệ hoặc không tồn tại.']);
        }
        $store_name = clean_string($bizData['data']['name'] ?? 'Cửa hàng không tên', 150);
        $address = clean_string($bizData['data']['address'] ?? '', 255);
        $stmt = $pdo->prepare('SELECT * FROM marketplace_stores WHERE tax_code = ?');
        $stmt->execute([$tax_code]);
        $store = $stmt->fetch();
        if (!$store) {
            $lat = 10.3547 + (mt_rand(-50, 50) / 10000);
            $lng = 105.5298 + (mt_rand(-50, 50) / 10000);
            insert_compat($pdo, 'marketplace_stores', [
                'phone' => $phone,
                'tax_code' => $tax_code,
                'store_name' => $store_name,
                'address' => $address,
                'lat' => $lat,
                'lng' => $lng,
                'store_type' => 'Cửa hàng',
                'status' => 'pending',
                'report_token' => bin2hex(random_bytes(16))
            ], ['created_at' => 'NOW()']);
            $store_id = $pdo->lastInsertId();
            $store = [
                'id' => $store_id,
                'phone' => $phone,
                'tax_code' => $tax_code,
                'store_name' => $store_name,
                'address' => $address
            ];
        } else {
            $pdo->prepare('UPDATE marketplace_stores SET phone = ? WHERE id = ?')->execute([$phone, $store['id']]);
        }
        json_out(['status' => 'success', 'data' => $store]);

    case 'app_get_map_pins':
        $stmt = $pdo->query("SELECT id, store_name, lat, lng, store_type, address FROM marketplace_stores WHERE status = 'active'");
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_out(['status' => 'success', 'data' => $stores]);

    case 'app_store_menu':
        $store_id = (int)($input['store_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM marketplace_products WHERE store_id = ? AND status = 'active'");
        $stmt->execute([$store_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_out(['status' => 'success', 'data' => $products]);

    case 'app_store_checkout':
        $store_id = (int)($input['store_id'] ?? 0);
        $customer_phone = clean_string($input['customer_phone'] ?? '', 30);
        $customer_address = clean_string($input['customer_address'] ?? '', 255);
        $total_amount = (int)($input['total_amount'] ?? 0);
        insert_compat($pdo, 'marketplace_orders', [
            'store_id' => $store_id,
            'customer_phone' => $customer_phone,
            'customer_address' => $customer_address,
            'total_amount' => $total_amount,
            'status' => 'pending'
        ], ['created_at' => 'NOW()']);
        $order_id = $pdo->lastInsertId();
        $salesChat = telegram_chat('report');
        if ($salesChat !== '') {
            tg_send('report', $salesChat, "CO DON HANG DIEN MAY HIEU!\nCua hang ID: $store_id\nSDT Khach: " . esc_html(mask_phone($customer_phone)) . "\nDia chi: " . esc_html($customer_address) . "\nTong: " . number_format($total_amount) . " d");
        }
        json_out(['status' => 'success', 'order_id' => $order_id]);

    case 'app_submit_rating':
        $target_type = $input['target_type'] ?? ''; // 'store' or 'worker'
        $target_id = (int)($input['target_id'] ?? 0);
        $stars = (int)($input['stars'] ?? 5);
        if ($stars < 1) $stars = 1;
        if ($stars > 5) $stars = 5;
        
        if ($target_type === 'store') {
            $pdo->prepare("UPDATE marketplace_stores SET rating_score = ((rating_score * rating_count) + ?) / (rating_count + 1), rating_count = rating_count + 1 WHERE id = ?")->execute([$stars, $target_id]);
        } else if ($target_type === 'worker') {
            $pdo->prepare("UPDATE worker_profiles SET rating_score = ((rating_score * rating_count) + ?) / (rating_count + 1), rating_count = rating_count + 1 WHERE telegram_user_id = ?")->execute([$stars, $target_id]);
        }
        json_out(['status' => 'success']);

    case 'admin_unban_device':
        $identifier = clean_string($input['identifier'] ?? '', 255);
        if ($identifier === '') {
            json_out(['status' => 'error', 'message' => 'Identifier khong hop le.'], 400);
        }
        $pdo->prepare('DELETE FROM banned_devices WHERE identifier = ?')->execute([$identifier]);
        $pdo->prepare('UPDATE client_abuse SET banned_at = NULL WHERE identifier = ?')->execute([$identifier]);
        json_out(['status' => 'success', 'message' => 'Da mo khoa device/IP/phone.']);

    case 'admin_banned_devices':
        $stmt = $pdo->query('SELECT * FROM banned_devices ORDER BY created_at DESC LIMIT 200');
        json_out(['status' => 'success', 'data' => $stmt->fetchAll()]);

    case 'admin_coupons':
        $stmt = $pdo->query('SELECT * FROM qr_coupons ORDER BY id DESC LIMIT 300');
        json_out(['status' => 'success', 'data' => $stmt->fetchAll()]);

    case 'admin_vouchers':
        $stmt = $pdo->query('SELECT * FROM vouchers ORDER BY id DESC LIMIT 300');
        json_out(['status' => 'success', 'data' => $stmt->fetchAll()]);

    case 'viettel_invoice_get_hash':
        $order_id = (int)($input['order_id'] ?? 0);
        $pdo = pdo();
        
        $ordersToExport = [];
        if ($order_id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            if (!$order) json_out(['status' => 'error', 'message' => 'Not found'], 404);
            if ($order['viettel_invoice_exported']) json_out(['status' => 'error', 'message' => 'Hóa đơn đã được xuất trước đó']);
            $ordersToExport[] = $order;
        } else {
            // Batch mode
            $stmt = $pdo->query('SELECT * FROM orders WHERE viettel_invoice_exported = 0 AND status IN ("completed", "confirmed") AND created_at >= CURDATE()');
            $ordersToExport = $stmt->fetchAll();
            if (empty($ordersToExport)) json_out(['status' => 'error', 'message' => 'Không có đơn hàng nào cần xuất trong ngày hôm nay.']);
        }
        
        $api = new ViettelInvoiceAPI();
        $invoiceData = $api->buildInvoiceData($ordersToExport);
        $res = $api->generateHash($invoiceData);
        
        if (isset($res['hashString'])) {
            json_out([
                'status' => 'success', 
                'hashString' => $res['hashString'],
                'invoiceData' => $invoiceData, // Giữ lại để gửi kèm signature
                'order_ids' => array_column($ordersToExport, 'id')
            ]);
        }
        json_out(['status' => 'error', 'message' => 'Lỗi từ Viettel API: ' . json_encode($res, JSON_UNESCAPED_UNICODE)]);

    case 'viettel_invoice_submit_sign':
        $hashString = $input['hashString'] ?? '';
        $signature = $input['signature'] ?? '';
        $invoiceData = $input['invoiceData'] ?? [];
        $order_ids = $input['order_ids'] ?? [];
        
        if (!$hashString || !$signature || empty($invoiceData) || empty($order_ids)) {
            json_out(['status' => 'error', 'message' => 'Thiếu dữ liệu chữ ký hoặc hash']);
        }
        
        $api = new ViettelInvoiceAPI();
        $res = $api->insertSignature($invoiceData, $hashString, $signature);
        
        // Kiểm tra xem thành công chưa. 
        if (isset($res['result']) && $res['result'] === 'SUCCESS') {
            // Cập nhật DB
            $pdo = pdo();
            $invoiceNo = $res['invoiceNo'] ?? 'UNKNOWN'; // API thường trả về invoiceNo nếu thành công
            $inClause = implode(',', array_fill(0, count($order_ids), '?'));
            $stmt = $pdo->prepare("UPDATE orders SET viettel_invoice_exported = 1, viettel_invoice_no = ? WHERE id IN ($inClause)");
            $params = array_merge([$invoiceNo], $order_ids);
            $stmt->execute($params);
            
            json_out(['status' => 'success', 'message' => 'Phát hành hóa đơn thành công! Mã HĐ: ' . $invoiceNo]);
        }
        json_out(['status' => 'error', 'message' => 'Lỗi phát hành từ Viettel API: ' . json_encode($res, JSON_UNESCAPED_UNICODE)]);

    case 'admin_store_list':
        json_out(['status' => 'success', 'data' => admin_store_rows($pdo)]);

    case 'admin_reject_store':
        $storeId = (int)($input['id'] ?? 0);
        if ($storeId <= 0) {
            json_out(['status' => 'error', 'message' => 'ID cửa hàng không hợp lệ.'], 400);
        }
        update_compat($pdo, 'marketplace_stores', [
            'status' => 'rejected',
        ], 'id = ?', [$storeId], ['updated_at' => 'NOW()']);
        json_out(['status' => 'success', 'message' => 'Đã từ chối cửa hàng.']);

    case 'admin_voucher_list':
        $vStmt = $pdo->query('SELECT * FROM vouchers ORDER BY id DESC LIMIT 200');
        $qrStmt = $pdo->query('SELECT * FROM qr_coupons ORDER BY id DESC LIMIT 200');
        json_out(['status' => 'success', 'vouchers' => $vStmt->fetchAll(), 'qr_coupons' => $qrStmt->fetchAll()]);

    case 'admin_create_voucher':
        $code = clean_string($input['code'] ?? '', 50);
        $type = in_array($input['type'] ?? '', ['percent', 'fixed'], true) ? $input['type'] : 'fixed';
        $value = money_int($input['value'] ?? 0);
        $minOrder = money_int($input['min_order'] ?? 0);
        $maxDiscount = money_int($input['max_discount'] ?? 0);
        $usageLimit = max(1, (int)($input['usage_limit'] ?? 1));
        $expiresAt = clean_string($input['expires_at'] ?? '', 20);
        $isActive = (int)(bool)($input['is_active'] ?? 1);

        if ($code === '') {
            json_out(['status' => 'error', 'message' => 'Mã voucher không được để trống.'], 400);
        }
        if ($value <= 0) {
            json_out(['status' => 'error', 'message' => 'Giá trị voucher phải lớn hơn 0.'], 400);
        }

        $existing = $pdo->prepare('SELECT id FROM vouchers WHERE code = ? LIMIT 1');
        $existing->execute([$code]);
        if ($existing->fetch()) {
            json_out(['status' => 'error', 'message' => 'Mã voucher đã tồn tại.'], 409);
        }

        $discountPercent = $type === 'percent' ? $value : 0;
        $discountAmount = $type === 'fixed' ? $value : 0;
        $data = [
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'min_order' => $minOrder,
            'max_discount' => $maxDiscount,
            'usage_limit' => $usageLimit,
            'max_uses' => $usageLimit,
            'used_count' => 0,
            'is_active' => $isActive,
        ];
        if (column_exists($pdo, 'vouchers', 'discount_percent')) {
            $data['discount_percent'] = $discountPercent;
        }
        if (column_exists($pdo, 'vouchers', 'discount_amount')) {
            $data['discount_amount'] = $discountAmount;
        }

        insert_compat($pdo, 'vouchers', $data, ['created_at' => 'NOW()']);
        $newId = (int)$pdo->lastInsertId();
        if ($expiresAt !== '' && column_exists($pdo, 'vouchers', 'expires_at')) {
            $pdo->prepare('UPDATE vouchers SET expires_at = ? WHERE id = ?')->execute([$expiresAt, $newId]);
        }
        json_out(['status' => 'success', 'message' => 'Đã tạo voucher.', 'id' => $newId]);

    case 'admin_create_qr':
        $count = max(1, min(500, (int)($input['count'] ?? 1)));
        $type = in_array($input['type'] ?? '', ['discount', 'prize'], true) ? $input['type'] : 'discount';
        $value = money_int($input['value'] ?? 0);
        $description = clean_string($input['description'] ?? '', 500);
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            do {
                $code = generate_code('QR', 4);
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM qr_coupons WHERE code = ?');
                $stmt->execute([$code]);
            } while ((int)$stmt->fetchColumn() > 0);
            insert_compat($pdo, 'qr_coupons', [
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'discount_amount' => $value,
                'quantity_left' => 1,
                'description' => $description,
                'is_used' => 0,
            ], ['created_at' => 'NOW()']);
            $codes[] = $code;
        }
        json_out(['status' => 'success', 'message' => 'Đã tạo ' . count($codes) . ' mã QR.', 'codes' => $codes]);

    case 'admin_delete_voucher':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_out(['status' => 'error', 'message' => 'ID voucher không hợp lệ.'], 400);
        }
        $pdo->prepare('DELETE FROM vouchers WHERE id = ?')->execute([$id]);
        json_out(['status' => 'success', 'message' => 'Đã xóa voucher.']);

    case 'admin_delete_qr':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            json_out(['status' => 'error', 'message' => 'ID mã QR không hợp lệ.'], 400);
        }
        $pdo->prepare('DELETE FROM qr_coupons WHERE id = ?')->execute([$id]);
        json_out(['status' => 'success', 'message' => 'Đã xóa mã QR.']);

    default:
        json_out(['status' => 'error', 'message' => "Unknown action: {$action}"], 404);
    }
} catch (Throwable $e) {
    api_exception_out($e);
}

if (!function_exists('column_exists')) {
    function column_exists(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}
