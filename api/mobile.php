<?php
// Module: mobile - REST API cho Mobile App (Worker + Customer)

function mobile_handle_action(PDO $pdo, string $action, array $input): array
{
    switch ($action) {
        // ===== Auth Worker =====
        case 'mobile_worker_login':
            return mobile_worker_login_action($pdo, $input);
        case 'mobile_worker_set_pin':
            return mobile_worker_set_pin_action($pdo, $input);
        case 'mobile_worker_profile':
            return mobile_worker_profile_action($pdo, $input);

        // ===== Auth Customer =====
        case 'mobile_customer_send_otp':
            return mobile_customer_send_otp_action($pdo, $input);
        case 'mobile_customer_register':
        case 'mobile_customer_login':
            return mobile_customer_auth_action($pdo, $input);
        case 'mobile_customer_profile':
            return mobile_customer_profile_action($pdo, $input);

        // ===== Services =====
        case 'mobile_services':
            return ['status' => 'success', 'categories' => public_service_catalog()];

        // ===== Customer Jobs =====
        case 'mobile_create_job':
            return mobile_customer_create_job_action($pdo, $input);
        case 'mobile_customer_jobs':
            return mobile_customer_jobs_action($pdo, $input);
        case 'mobile_customer_job_detail':
            return mobile_customer_job_detail_action($pdo, $input);
        case 'mobile_customer_cancel_job':
            return mobile_customer_cancel_job_action($pdo, $input);
        case 'mobile_customer_review_worker':
            return mobile_customer_review_worker_action($pdo, $input);

        // ===== Worker Jobs =====
        case 'mobile_worker_jobs_pending':
            return mobile_worker_jobs_pending_action($pdo, $input);
        case 'mobile_worker_jobs_assigned':
            return mobile_worker_jobs_assigned_action($pdo, $input);
        case 'mobile_worker_claim_job':
            return mobile_worker_claim_job_action($pdo, $input);
        case 'mobile_worker_update_status':
            return mobile_worker_update_status_action($pdo, $input);
        case 'mobile_worker_location':
            return mobile_worker_location_action($pdo, $input);

        // ===== Earnings =====
        case 'mobile_worker_earnings':
            return mobile_worker_earnings_action($pdo, $input);
        case 'mobile_worker_history':
            return mobile_worker_history_action($pdo, $input);

        // ===== Push =====
        case 'mobile_register_push_token':
            return mobile_register_push_token_action($pdo, $input);

        default:
            return ['status' => 'error', 'message' => 'Unknown mobile action: ' . $action, 'code' => 'UNKNOWN_ACTION'];
    }
}

// ============================================================
// Helpers
// ============================================================

function mobile_token_from_request(array $input): string
{
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Bearer ') === 0) {
        return clean_string(substr($auth, 7), 128);
    }
    return clean_string($input['token'] ?? $_GET['token'] ?? '', 128);
}

function mobile_session(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM mobile_sessions WHERE token = ? AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mobile_require_auth(PDO $pdo, array $input, string $allowedType = 'any'): array
{
    $token = mobile_token_from_request($input);
    $session = mobile_session($pdo, $token);
    if (!$session) {
        json_out(['status' => 'error', 'message' => 'Token khong hop le hoac da het han.', 'code' => 'INVALID_TOKEN'], 401);
    }
    if ($allowedType !== 'any' && (string)$session['type'] !== $allowedType) {
        json_out(['status' => 'error', 'message' => 'Token khong du quyen.', 'code' => 'WRONG_ROLE'], 403);
    }
    $pdo->prepare('UPDATE mobile_sessions SET last_active_at = NOW() WHERE id = ?')->execute([(int)$session['id']]);
    return $session;
}

function mobile_create_token(PDO $pdo, string $type, int $userId): string
{
    $token = 'DTHM' . strtoupper(bin2hex(random_bytes(32)));
    $expires = date('Y-m-d H:i:s', strtotime('+90 days'));
    insert_compat($pdo, 'mobile_sessions', [
        'token' => $token,
        'type' => $type,
        'user_id' => $userId,
        'ip_address' => client_ip(),
        'user_agent' => clean_string((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 300),
        'expires_at' => $expires,
    ], ['created_at' => 'NOW()']);
    return $token;
}

function mobile_mock_otp_enabled(): bool
{
    return app_bool_env('MOBILE_OTP_MOCK', false);
}

function mobile_generate_otp(): string
{
    return mobile_mock_otp_enabled() ? '123456' : sprintf('%06d', random_int(0, 999999));
}

function mobile_worker_row(array $profile): array
{
    return [
        'worker_id' => (int)($profile['telegram_user_id'] ?? 0),
        'name' => (string)($profile['telegram_name'] ?? ''),
        'phone' => (string)($profile['phone'] ?? ''),
        'role' => (string)($profile['role'] ?? 'worker'),
        'is_admin' => (int)($profile['is_admin'] ?? 0),
        'is_active' => (int)($profile['is_active'] ?? 1),
        'payment_blocked' => (int)($profile['payment_blocked'] ?? 0),
        'is_receive_blocked' => (int)($profile['is_receive_blocked'] ?? 0),
        'jobs_claimed' => (int)($profile['jobs_claimed'] ?? 0),
        'jobs_completed' => (int)($profile['jobs_completed'] ?? 0),
        'rating_score' => (float)($profile['rating_score'] ?? 5.0),
        'rating_count' => (int)($profile['rating_count'] ?? 0),
    ];
}

function mobile_customer_row(array $user): array
{
    return [
        'id' => (int)($user['id'] ?? 0),
        'name' => (string)($user['fullname'] ?? ''),
        'phone' => (string)($user['phone'] ?? ''),
        'role' => (string)($user['role'] ?? 'buyer'),
        'login_key' => (string)($user['login_key'] ?? ''),
        'is_active' => (int)($user['is_active'] ?? 1),
        'member_rank' => (string)($user['member_rank'] ?? 'Thành viên'),
        'loyalty_points' => (int)($user['loyalty_points'] ?? 0),
    ];
}

function mobile_job_row(PDO $pdo, array $job): array
{
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

    $pricing = get_job_pricing($pdo, (int)$job['id']);
    $workerId = job_worker_telegram_id($job);
    $worker = null;
    if ($workerId > 0) {
        $wp = get_worker_profile($pdo, $workerId);
        $worker = [
            'id' => $workerId,
            'name' => (string)($wp['telegram_name'] ?? "Thợ {$workerId}"),
            'phone' => (string)($wp['phone'] ?? ''),
            'rating_score' => (float)($wp['rating_score'] ?? 5.0),
            'rating_count' => (int)($wp['rating_count'] ?? 0),
        ];
    }

    $customerPhone = (string)($job['customer_phone'] ?? '');
    $phoneDigits = digits_only($customerPhone);
    $safeCustomerPhone = strlen($phoneDigits) >= 8 ? mask_phone($customerPhone) : $customerPhone;

    return [
        'job_id' => (int)$job['id'],
        'status_code' => $statusCode,
        'status_text' => $statusText,
        'service_type' => (string)($job['service_type'] ?? ''),
        'customer_name' => (string)($job['customer_name'] ?? ''),
        'customer_phone' => $safeCustomerPhone,
        'address' => (string)($job['address'] ?? $job['location'] ?? ''),
        'map_lat' => isset($job['map_lat']) ? (float)$job['map_lat'] : null,
        'map_lng' => isset($job['map_lng']) ? (float)$job['map_lng'] : null,
        'issue_description' => (string)($job['description'] ?? ''),
        'amount' => (int)($job['final_total'] ?? $pricing['final_customer_price'] ?? 0),
        'platform_fee' => (int)($pricing['platform_fee'] ?? 0),
        'estimated_net' => (int)($pricing['tech_net_income'] ?? 0),
        'worker' => $worker,
        'created_at' => (string)($job['created_at'] ?? ''),
        'assigned_at' => (string)($job['assigned_at'] ?? ''),
        'completed_at' => (string)($job['completed_at'] ?? ''),
        'cancel_reason' => (string)($job['cancel_reason'] ?? ''),
        'review_score' => isset($job['review_score']) ? (int)$job['review_score'] : null,
    ];
}

// ============================================================
// Worker Auth
// ============================================================

function mobile_worker_login_action(PDO $pdo, array $input): array
{
    $workerId = (int)($input['worker_id'] ?? 0);
    $pin = (string)($input['pin'] ?? '');

    if ($workerId <= 0) {
        return ['status' => 'error', 'message' => 'Vui long nhap worker_id (Telegram ID).', 'code' => 'MISSING_WORKER_ID'];
    }
    if ($pin === '') {
        return ['status' => 'error', 'message' => 'Vui long nhap PIN.', 'code' => 'MISSING_PIN'];
    }

    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Tho chua duoc dang ky.', 'code' => 'WORKER_NOT_FOUND'];
    }
    if ((int)($profile['is_active'] ?? 1) !== 1) {
        return ['status' => 'error', 'message' => 'Tai khoan tho dang bi khoa.', 'code' => 'WORKER_INACTIVE'];
    }

    $pinHash = (string)($profile['pin_hash'] ?? '');
    if ($pinHash === '') {
        return [
            'status' => 'error',
            'message' => 'Tai khoan chua dat PIN. Vui long goi action mobile_worker_set_pin truoc.',
            'code' => 'PIN_NOT_SET',
            'needs_pin_setup' => true,
        ];
    }
    if (!password_verify($pin, $pinHash)) {
        return ['status' => 'error', 'message' => 'PIN khong dung.', 'code' => 'INVALID_PIN'];
    }

    $token = mobile_create_token($pdo, 'worker', $workerId);
    upsert_worker($pdo, $workerId, (string)($profile['telegram_name'] ?? ''), (string)($profile['telegram_username'] ?? ''), (string)($profile['role'] ?? 'worker'));

    return [
        'status' => 'success',
        'token' => $token,
        'worker' => mobile_worker_row($profile),
    ];
}

function mobile_worker_set_pin_action(PDO $pdo, array $input): array
{
    $workerId = (int)($input['worker_id'] ?? 0);
    $pin = (string)($input['pin'] ?? '');
    $confirmPin = (string)($input['confirm_pin'] ?? '');

    if ($workerId <= 0) {
        return ['status' => 'error', 'message' => 'Vui long nhap worker_id.', 'code' => 'MISSING_WORKER_ID'];
    }
    if (!preg_match('/^\d{4,6}$/', $pin)) {
        return ['status' => 'error', 'message' => 'PIN phai tu 4 den 6 chu so.', 'code' => 'INVALID_PIN_FORMAT'];
    }
    if ($pin !== $confirmPin) {
        return ['status' => 'error', 'message' => 'PIN xac nhan khong khop.', 'code' => 'PIN_MISMATCH'];
    }

    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Tho chua duoc dang ky.', 'code' => 'WORKER_NOT_FOUND'];
    }

    update_compat($pdo, 'worker_profiles', ['pin_hash' => password_hash($pin, PASSWORD_BCRYPT)], 'telegram_user_id = ?', [$workerId], ['updated_at' => 'NOW()']);
    $token = mobile_create_token($pdo, 'worker', $workerId);

    return [
        'status' => 'success',
        'message' => 'Da dat PIN thanh cong.',
        'token' => $token,
        'worker' => mobile_worker_row($profile),
    ];
}

function mobile_worker_profile_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Khong tim thay tho.', 'code' => 'WORKER_NOT_FOUND'];
    }

    $month = clean_string($input['month'] ?? date('Y-m'), 7);
    $earnings = mobile_worker_earnings_internal($pdo, $workerId, $month);

    return [
        'status' => 'success',
        'worker' => mobile_worker_row($profile),
        'month' => $month,
        'earnings' => $earnings,
    ];
}

// ============================================================
// Customer Auth (OTP)
// ============================================================

function mobile_customer_send_otp_action(PDO $pdo, array $input): array
{
    $phone = digits_only((string)($input['phone'] ?? ''));
    if (strlen($phone) < 8) {
        return ['status' => 'error', 'message' => 'So dien thoai khong hop le.', 'code' => 'INVALID_PHONE'];
    }

    $otp = mobile_generate_otp();
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    insert_compat($pdo, 'mobile_otp_codes', [
        'phone' => $phone,
        'otp' => $otp,
        'purpose' => 'login',
        'expires_at' => $expires,
    ], ['created_at' => 'NOW()']);

    if (mobile_mock_otp_enabled()) {
        return ['status' => 'success', 'message' => 'OTP (mock): ' . $otp, 'otp' => $otp, 'phone' => $phone];
    }

    // TODO: gửi OTP qua SMS/Telegram trong production
    $salesChat = telegram_chat('sales');
    if ($salesChat !== '') {
        tg_send('sales', $salesChat, "<b>OTP Mobile App</b>\nSDT: " . esc_html($phone) . "\nMa: <code>" . esc_html($otp) . "</code>");
    }

    return ['status' => 'success', 'message' => 'Da gui OTP. Vui long kiem tra tin nhan.', 'phone' => $phone];
}

function mobile_customer_auth_action(PDO $pdo, array $input): array
{
    $phone = digits_only((string)($input['phone'] ?? ''));
    $otp = (string)($input['otp'] ?? '');
    $name = clean_string($input['name'] ?? $input['fullname'] ?? '', 150);
    $isRegister = (string)($input['action'] ?? '') === 'mobile_customer_register' || ((string)($input['name'] ?? '') !== '');

    if (strlen($phone) < 8) {
        return ['status' => 'error', 'message' => 'So dien thoai khong hop le.', 'code' => 'INVALID_PHONE'];
    }
    if (!preg_match('/^\d{6}$/', $otp)) {
        return ['status' => 'error', 'message' => 'OTP phai gom 6 chu so.', 'code' => 'INVALID_OTP'];
    }

    if (!mobile_mock_otp_enabled()) {
        $stmt = $pdo->prepare('SELECT otp FROM mobile_otp_codes WHERE phone = ? AND otp = ? AND verified = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$phone, $otp]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['status' => 'error', 'message' => 'OTP khong dung hoac da het han.', 'code' => 'INVALID_OTP'];
        }
        $pdo->prepare('UPDATE mobile_otp_codes SET verified = 1 WHERE phone = ? AND otp = ?')->execute([$phone, $otp]);
    }

    $user = retail_customer_by_phone($pdo, $phone, false);
    if (!$user && !$isRegister) {
        return [
            'status' => 'error',
            'message' => 'So dien thoai chua dang ky. Vui long dang ky tai khoan.',
            'code' => 'ACCOUNT_NOT_FOUND',
            'needs_register' => true,
        ];
    }

    if (!$user) {
        if ($name === '') {
            $name = 'Khach ' . substr($phone, -4);
        }
        $user = retail_customer_by_phone($pdo, $phone, false);
        if (!$user) {
            $customerId = insert_compat($pdo, 'users', [
                'role' => 'buyer',
                'fullname' => $name,
                'phone' => $phone,
                'login_key' => customer_generate_login_key($pdo),
                'is_active' => 1,
                'member_rank' => loyalty_member_rank(0),
                'total_spent' => 0,
                'loyalty_points' => 0,
            ], ['created_at' => 'NOW()']);
            $user = retail_customer_by_phone($pdo, $phone, false) ?: [
                'id' => $customerId,
                'fullname' => $name,
                'phone' => $phone,
                'login_key' => customer_generate_login_key($pdo),
                'member_rank' => loyalty_member_rank(0),
                'total_spent' => 0,
                'loyalty_points' => 0,
            ];
        }
    }

    $token = mobile_create_token($pdo, 'customer', (int)$user['id']);
    return [
        'status' => 'success',
        'token' => $token,
        'user' => mobile_customer_row($user),
    ];
}

function mobile_customer_profile_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'customer');
    $userId = (int)$session['user_id'];
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['status' => 'error', 'message' => 'Khong tim thay tai khoan.', 'code' => 'CUSTOMER_NOT_FOUND'];
    }

    // Lich su ca gan day
    $stmt = $pdo->prepare('SELECT * FROM job_posts WHERE customer_phone = ? ORDER BY id DESC LIMIT 10');
    $stmt->execute([$user['phone']]);
    $jobs = array_map(static function (array $job) use ($pdo): array {
        return mobile_job_row($pdo, $job);
    }, $stmt->fetchAll());

    return [
        'status' => 'success',
        'user' => mobile_customer_row($user),
        'recent_jobs' => $jobs,
    ];
}

// ============================================================
// Customer Jobs
// ============================================================

function mobile_customer_create_job_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'customer');
    $userId = (int)$session['user_id'];
    $stmt = $pdo->prepare('SELECT fullname, phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $payload = [
        'service_type' => $input['service_type'] ?? $input['selected_service_name'] ?? $input['service_name'] ?? '',
        'selected_service_name' => $input['selected_service_name'] ?? $input['service_name'] ?? '',
        'issue_description' => $input['issue_description'] ?? $input['description'] ?? '',
        'customer_name' => $input['customer_name'] ?? $user['fullname'] ?? '',
        'customer_phone' => $input['customer_phone'] ?? $user['phone'] ?? '',
        'phone' => $input['customer_phone'] ?? $user['phone'] ?? '',
        'address' => $input['address'] ?? '',
        'map_lat' => $input['map_lat'] ?? null,
        'map_lng' => $input['map_lng'] ?? null,
        'preferred_time' => $input['preferred_time'] ?? '',
        'images' => $input['images'] ?? [],
    ];

    // Validate service
    $serviceName = clean_string((string)($payload['selected_service_name'] ?: $payload['service_type']), 150);
    $service = public_service_by_name($serviceName);
    if (!$service) {
        return ['status' => 'error', 'message' => 'Dich vu khong hop le: ' . $serviceName, 'code' => 'INVALID_SERVICE'];
    }
    $payload['estimated_price'] = (int)($service['public_price'] ?? $service['base'] ?? 0);

    $result = create_job_action($payload);
    if (empty($result['success']) && empty($result['ok'])) {
        return ['status' => 'error', 'message' => $result['message'] ?? 'Tao ca that bai.', 'code' => 'CREATE_JOB_FAILED'];
    }

    $jobId = (int)($result['data']['job_id'] ?? $result['job_id'] ?? 0);
    $job = get_job_row($pdo, $jobId);
    $pricing = get_job_pricing($pdo, $jobId);

    return [
        'status' => 'success',
        'job_id' => $jobId,
        'platform_fee' => (int)($pricing['platform_fee'] ?? 0),
        'estimated_net' => (int)($pricing['tech_net_income'] ?? 0),
        'job_status' => 'pending',
        'job' => $job ? mobile_job_row($pdo, $job) : null,
    ];
}

function mobile_customer_jobs_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'customer');
    $userId = (int)$session['user_id'];
    $stmt = $pdo->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $phone = (string)($stmt->fetchColumn() ?? '');

    $statusFilter = clean_string($input['status'] ?? $_GET['status'] ?? '', 30);
    $limit = max(1, min(100, (int)($input['limit'] ?? $_GET['limit'] ?? 20)));
    $offset = max(0, (int)($input['offset'] ?? $_GET['offset'] ?? 0));

    $where = 'customer_phone = ?';
    $params = [$phone];
    if ($statusFilter !== '') {
        if ($statusFilter === 'pending') {
            $where .= " AND COALESCE(telegram_worker_id, worker_id, 0) = 0 AND status NOT IN ('completed','cancelled','spam','failed')";
        } elseif ($statusFilter === 'assigned') {
            $where .= " AND COALESCE(telegram_worker_id, worker_id, 0) > 0 AND status NOT IN ('completed','cancelled','spam')";
        } elseif ($statusFilter === 'in_progress') {
            $where .= " AND COALESCE(telegram_worker_id, worker_id, 0) > 0 AND status NOT IN ('completed','cancelled','spam')";
        } elseif ($statusFilter === 'completed') {
            $where .= " AND (status = 'completed' OR completed_at IS NOT NULL)";
        } elseif ($statusFilter === 'cancelled') {
            $where .= " AND status IN ('cancelled','spam','failed')";
        }
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM job_posts WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM job_posts WHERE {$where} ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $jobs = array_map(static function (array $job) use ($pdo): array {
        return mobile_job_row($pdo, $job);
    }, $stmt->fetchAll());

    return [
        'status' => 'success',
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'jobs' => $jobs,
    ];
}

function mobile_customer_job_detail_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'customer');
    $userId = (int)$session['user_id'];
    $stmt = $pdo->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $phone = (string)($stmt->fetchColumn() ?? '');

    $jobId = (int)($input['job_id'] ?? $_GET['job_id'] ?? 0);
    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thieu job_id.', 'code' => 'MISSING_JOB_ID'];
    }

    $job = get_job_row($pdo, $jobId);
    if (!$job || (string)($job['customer_phone'] ?? '') !== $phone) {
        return ['status' => 'error', 'message' => 'Khong tim thay ca.', 'code' => 'JOB_NOT_FOUND'];
    }

    return [
        'status' => 'success',
        'job' => mobile_job_row($pdo, $job),
    ];
}

function mobile_customer_cancel_job_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'customer');
    $userId = (int)$session['user_id'];
    $stmt = $pdo->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $phone = (string)($stmt->fetchColumn() ?? '');

    $jobId = (int)($input['job_id'] ?? 0);
    $reason = clean_string($input['reason'] ?? 'Khach hang huy qua app', 500);
    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thieu job_id.', 'code' => 'MISSING_JOB_ID'];
    }

    $job = get_job_row($pdo, $jobId);
    if (!$job || (string)($job['customer_phone'] ?? '') !== $phone) {
        return ['status' => 'error', 'message' => 'Khong tim thay ca.', 'code' => 'JOB_NOT_FOUND'];
    }

    $statusCode = job_display_status($job);
    if ($statusCode === 'completed') {
        return ['status' => 'error', 'message' => 'Ca da hoan thanh, khong the huy.', 'code' => 'JOB_ALREADY_COMPLETED'];
    }
    if ($statusCode === 'cancelled') {
        return ['status' => 'error', 'message' => 'Ca da bi huy truoc do.', 'code' => 'JOB_ALREADY_CANCELLED'];
    }

    update_compat($pdo, 'job_posts', [
        'worker_id' => null,
        'telegram_worker_id' => null,
        'status' => job_status($pdo, 'cancelled'),
        'cancel_reason' => $reason,
    ], 'id = ?', [$jobId], ['cancelled_at' => 'NOW()', 'updated_at' => 'NOW()']);

    return ['status' => 'success', 'message' => 'Da huy ca.', 'job_id' => $jobId];
}

function mobile_customer_review_worker_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'customer');
    $userId = (int)$session['user_id'];
    $stmt = $pdo->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $phone = (string)($stmt->fetchColumn() ?? '');

    $jobId = (int)($input['job_id'] ?? 0);
    $rating = max(1, min(5, (int)($input['rating'] ?? 5)));
    $comment = clean_string($input['comment'] ?? '', 1000);
    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thieu job_id.', 'code' => 'MISSING_JOB_ID'];
    }

    $job = get_job_row($pdo, $jobId);
    if (!$job || (string)($job['customer_phone'] ?? '') !== $phone) {
        return ['status' => 'error', 'message' => 'Khong tim thay ca.', 'code' => 'JOB_NOT_FOUND'];
    }
    if (job_display_status($job) !== 'completed') {
        return ['status' => 'error', 'message' => 'Chi duoc danh gia ca da hoan thanh.', 'code' => 'JOB_NOT_COMPLETED'];
    }

    update_compat($pdo, 'job_posts', ['review_score' => $rating], 'id = ?', [$jobId]);

    $workerId = job_worker_telegram_id($job);
    if ($workerId > 0) {
        mobile_update_worker_rating($pdo, $workerId, $rating);
    }

    return ['status' => 'success', 'message' => 'Da gui danh gia.', 'job_id' => $jobId, 'rating' => $rating];
}

function mobile_update_worker_rating(PDO $pdo, int $workerId, int $newRating): void
{
    $stmt = $pdo->prepare('SELECT AVG(review_score) AS avg_score, COUNT(*) AS cnt FROM job_posts WHERE review_score IS NOT NULL AND COALESCE(telegram_worker_id, worker_id, 0) = ?');
    $stmt->execute([$workerId]);
    $row = $stmt->fetch();
    $avg = $row['avg_score'] ?? 5;
    $cnt = (int)($row['cnt'] ?? 0);
    if (column_exists($pdo, 'worker_profiles', 'rating_score')) {
        update_compat($pdo, 'worker_profiles', ['rating_score' => (float)$avg, 'rating_count' => $cnt], 'telegram_user_id = ?', [$workerId], ['updated_at' => 'NOW()']);
    }
}

// ============================================================
// Worker Jobs
// ============================================================

function mobile_worker_jobs_pending_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $profile = get_worker_profile($pdo, $workerId);
    if (worker_is_blocked($profile)) {
        return ['status' => 'error', 'message' => 'Tai khoan dang bi khoa nhan ca.', 'code' => 'WORKER_BLOCKED'];
    }

    $limit = max(1, min(100, (int)($input['limit'] ?? $_GET['limit'] ?? 50)));
    $stmt = $pdo->prepare("SELECT * FROM job_posts
        WHERE COALESCE(telegram_worker_id, worker_id, 0) = 0
        AND status NOT IN ('completed','cancelled','spam','failed')
        ORDER BY id DESC LIMIT ?");
    $stmt->execute([$limit]);
    $jobs = array_map(static function (array $job) use ($pdo): array {
        return mobile_job_row($pdo, $job);
    }, $stmt->fetchAll());

    return ['status' => 'success', 'jobs' => $jobs];
}

function mobile_worker_jobs_assigned_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];

    $statusFilter = clean_string($input['status'] ?? $_GET['status'] ?? '', 30);
    $limit = max(1, min(100, (int)($input['limit'] ?? $_GET['limit'] ?? 50)));
    $offset = max(0, (int)($input['offset'] ?? $_GET['offset'] ?? 0));

    $where = 'COALESCE(telegram_worker_id, worker_id, 0) = ?';
    $params = [$workerId];

    if ($statusFilter === 'assigned') {
        $where .= " AND status NOT IN ('completed','cancelled','spam','failed') AND completed_at IS NULL";
    } elseif ($statusFilter === 'in_progress') {
        $where .= " AND status NOT IN ('completed','cancelled','spam','failed') AND completed_at IS NULL";
    } elseif ($statusFilter === 'completed') {
        $where .= " AND (status = 'completed' OR completed_at IS NOT NULL)";
    } elseif ($statusFilter === 'cancelled') {
        $where .= " AND status IN ('cancelled','spam','failed')";
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM job_posts WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM job_posts WHERE {$where} ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $jobs = array_map(static function (array $job) use ($pdo): array {
        return mobile_job_row($pdo, $job);
    }, $stmt->fetchAll());

    return ['status' => 'success', 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'jobs' => $jobs];
}

function mobile_worker_claim_job_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $jobId = (int)($input['job_id'] ?? 0);
    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thieu job_id.', 'code' => 'MISSING_JOB_ID'];
    }

    $profile = get_worker_profile($pdo, $workerId);
    if (worker_is_blocked($profile)) {
        $debt = worker_fee_debt($pdo, $workerId);
        return ['status' => 'error', 'message' => $debt > 0 ? 'No phi nen tang: ' . fmt_money($debt) . '.' : 'Tai khoan dang bi khoa.', 'code' => 'WORKER_BLOCKED'];
    }

    $workerName = (string)($profile['telegram_name'] ?? "Tho {$workerId}");
    $username = (string)($profile['telegram_username'] ?? '');
    $role = (string)($profile['role'] ?? 'worker');

    $result = claim_job($pdo, $jobId, $workerId, $workerName, $username, $role);
    if (empty($result['ok'])) {
        return ['status' => 'error', 'message' => $result['message'] ?? 'Nhan ca that bai.', 'code' => 'CLAIM_FAILED'];
    }

    $job = get_job_row($pdo, $jobId);
    return [
        'status' => 'success',
        'message' => $result['message'] ?? 'Da nhan ca.',
        'job' => $job ? mobile_job_row($pdo, $job) : null,
    ];
}

function mobile_worker_update_status_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $jobId = (int)($input['job_id'] ?? 0);
    $newStatus = clean_string($input['status'] ?? '', 30);
    $note = clean_string($input['note'] ?? '', 1000);
    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thieu job_id.', 'code' => 'MISSING_JOB_ID'];
    }
    if (!in_array($newStatus, ['in_progress', 'completed', 'cancelled'], true)) {
        return ['status' => 'error', 'message' => 'Trang thai khong hop le.', 'code' => 'INVALID_STATUS'];
    }

    $job = get_job_row($pdo, $jobId);
    if (!$job || !job_belongs_to_worker($pdo, $job, $workerId)) {
        return ['status' => 'error', 'message' => 'Ca khong thuoc tho nay.', 'code' => 'JOB_NOT_ASSIGNED'];
    }

    $profile = get_worker_profile($pdo, $workerId);
    $workerName = (string)($profile['telegram_name'] ?? "Tho {$workerId}");

    if ($newStatus === 'in_progress') {
        update_compat($pdo, 'job_posts', ['status' => job_status($pdo, 'assigned')], 'id = ?', [$jobId], ['updated_at' => 'NOW()']);
    } elseif ($newStatus === 'completed') {
        $amount = (int)($input['amount'] ?? $job['final_total'] ?? 0);
        mobile_complete_job($pdo, $jobId, $workerId, $workerName, $note, $amount);
    } elseif ($newStatus === 'cancelled') {
        cancel_worker_job($pdo, $jobId, $workerId, $workerName, $note ?: 'Tho huy ca qua app', (string)($profile['role'] ?? 'worker'));
    }

    $job = get_job_row($pdo, $jobId);
    return [
        'status' => 'success',
        'message' => 'Da cap nhat trang thai.',
        'job' => $job ? mobile_job_row($pdo, $job) : null,
    ];
}

function mobile_complete_job(PDO $pdo, int $jobId, int $workerId, string $workerName, string $note, int $amount): void
{
    if ($amount <= 0) {
        $amount = (int)(get_job_row($pdo, $jobId)['final_total'] ?? 0);
    }
    $pricing = get_job_pricing($pdo, $jobId);
    $calculated = calculate_job_pricing((int)($pricing['tech_target_base'] ?? 0), $amount, max(1, (int)(get_job_row($pdo, $jobId)['quantity'] ?? 1)));

    update_compat($pdo, 'job_posts', [
        'status' => job_status($pdo, 'completed'),
        'final_total' => $calculated['final_customer_price'],
        'customer_total' => $calculated['gross_customer_price'],
    ], 'id = ?', [$jobId], ['completed_at' => 'NOW()', 'updated_at' => 'NOW()']);

    // Update pricing record
    if ($pricing) {
        update_compat($pdo, 'job_pricing', [
            'gross_customer_price' => $calculated['gross_customer_price'],
            'final_customer_price' => $calculated['final_customer_price'],
            'platform_fee' => $calculated['platform_fee'],
            'tech_net_income' => $calculated['tech_net_income'],
        ], 'id = ?', [(int)$pricing['id']]);
    }

    $pdo->prepare('UPDATE worker_profiles SET jobs_completed = jobs_completed + 1, updated_at = NOW() WHERE telegram_user_id = ?')->execute([$workerId]);

    $groupChat = get_bot_group_chat_id((string)(get_job_row($pdo, $jobId)['bot_role'] ?? 'worker'));
    if ($groupChat !== '') {
        tg_send('worker', $groupChat, "Ca #{$jobId} da hoan thanh boi " . esc_html($workerName) . ".");
    }
}

function mobile_worker_location_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $lat = isset($input['lat']) ? (float)$input['lat'] : null;
    $lng = isset($input['lng']) ? (float)$input['lng'] : null;
    if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
        return ['status' => 'error', 'message' => 'Toa do khong hop le.', 'code' => 'INVALID_COORDS'];
    }

    // Luu vi tri gan nhat vao worker_profiles
    update_compat($pdo, 'worker_profiles', [
        'last_lat' => $lat,
        'last_lng' => $lng,
    ], 'telegram_user_id = ?', [$workerId], ['updated_at' => 'NOW()']);

    return ['status' => 'success', 'message' => 'Da cap nhat vi tri.'];
}

// ============================================================
// Earnings
// ============================================================

function mobile_worker_earnings_internal(PDO $pdo, int $workerId, string $month): array
{
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));
    $stmt = $pdo->prepare("SELECT
        COUNT(*) AS job_count,
        COALESCE(SUM(jp.gross_customer_price), 0) AS total_customer_price,
        COALESCE(SUM(jp.platform_fee), 0) AS total_platform_fee,
        COALESCE(SUM(jp.tech_net_income), 0) AS total_worker_income
        FROM job_posts j
        JOIN job_pricing jp ON jp.job_id = j.id
        WHERE COALESCE(j.telegram_worker_id, j.worker_id) = ?
        AND j.completed_at IS NOT NULL
        AND j.completed_at >= ? AND j.completed_at <= ?");
    $stmt->execute([$workerId, $start . ' 00:00:00', $end . ' 23:59:59']);
    $row = $stmt->fetch();

    return [
        'month' => $month,
        'job_count' => (int)($row['job_count'] ?? 0),
        'total_customer_price' => (int)($row['total_customer_price'] ?? 0),
        'total_platform_fee' => (int)($row['total_platform_fee'] ?? 0),
        'total_worker_income' => (int)($row['total_worker_income'] ?? 0),
        'fee_debt' => worker_fee_debt($pdo, $workerId),
    ];
}

function mobile_worker_earnings_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $month = clean_string($input['month'] ?? $_GET['month'] ?? date('Y-m'), 7);
    return ['status' => 'success'] + mobile_worker_earnings_internal($pdo, $workerId, $month);
}

function mobile_worker_history_action(PDO $pdo, array $input): array
{
    $session = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $month = clean_string($input['month'] ?? $_GET['month'] ?? date('Y-m'), 7);
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));

    $stmt = $pdo->prepare("SELECT j.* FROM job_posts j
        WHERE COALESCE(j.telegram_worker_id, j.worker_id) = ?
        AND j.completed_at IS NOT NULL
        AND j.completed_at >= ? AND j.completed_at <= ?
        ORDER BY j.completed_at DESC");
    $stmt->execute([$workerId, $start . ' 00:00:00', $end . ' 23:59:59']);
    $jobs = array_map(static function (array $job) use ($pdo): array {
        return mobile_job_row($pdo, $job);
    }, $stmt->fetchAll());

    return [
        'status' => 'success',
        'month' => $month,
        'jobs' => $jobs,
    ];
}

// ============================================================
// Push Token
// ============================================================

function mobile_register_push_token_action(PDO $pdo, array $input): array
{
    $token = mobile_token_from_request($input);
    $session = mobile_session($pdo, $token);
    if (!$session) {
        return ['status' => 'error', 'message' => 'Token khong hop le.', 'code' => 'INVALID_TOKEN'];
    }

    $pushToken = clean_string($input['push_token'] ?? '', 255);
    $platform = clean_string($input['platform'] ?? '', 20);
    if ($pushToken === '') {
        return ['status' => 'error', 'message' => 'Thieu push_token.', 'code' => 'MISSING_PUSH_TOKEN'];
    }

    update_compat($pdo, 'mobile_sessions', [
        'push_token' => $pushToken,
        'platform' => $platform,
    ], 'id = ?', [(int)$session['id']], ['last_active_at' => 'NOW()']);

    return ['status' => 'success', 'message' => 'Da dang ky push token.'];
}
