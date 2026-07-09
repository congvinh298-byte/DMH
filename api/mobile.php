<?php
// Module: mobile - REST API cho Mobile App (Worker + Customer)

function mobile_handle_action(PDO $pdo, string $action, array $input): array
{
    switch ($action) {
        // ===== Auth Worker =====
        case 'mobile_worker_login':
        case 'mobile_worker_login_v2':  // alias: luôn ưu tiên phone-based login
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

        // ===== CLOSED-LOOP: Auth bằng SĐT (không cần Telegram) =====
        case 'mobile_worker_login_by_phone':
            return mobile_worker_login_by_phone_action($pdo, $input);

        // ===== CLOSED-LOOP: Admin tạo tài khoản thợ mới (không cần Telegram) =====
        case 'mobile_worker_init_account':
            return mobile_worker_init_account_action($pdo, $input);

        // ===== CLOSED-LOOP: Reset PIN thợ =====
        case 'mobile_worker_reset_pin':
            return mobile_worker_reset_pin_action($pdo, $input);

        // ===== CLOSED-LOOP: Quản lý ca làm việc =====
        case 'mobile_worker_shift_start':
            return mobile_worker_shift_start_action($pdo, $input);
        case 'mobile_worker_shift_end':
            return mobile_worker_shift_end_action($pdo, $input);
        case 'mobile_worker_shift_status':
            return mobile_worker_shift_status_action($pdo, $input);

        // ===== CLOSED-LOOP: Nhận/xong/hủy ca qua App (không Telegram) =====
        case 'mobile_worker_claim_job_v2':
            return mobile_worker_claim_job_v2_action($pdo, $input);
        case 'mobile_worker_complete_job':
            return mobile_worker_complete_job_action($pdo, $input);
        case 'mobile_worker_cancel_job_app':
            return mobile_worker_cancel_job_app_action($pdo, $input);

        // ===== CLOSED-LOOP: Thông báo nội bộ (thay Telegram DM) =====
        case 'mobile_worker_notifications':
            return mobile_worker_notifications_action($pdo, $input);
        case 'mobile_worker_read_notification':
            return mobile_worker_read_notification_action($pdo, $input);

        // ===== CLOSED-LOOP: Dashboard tổng hợp =====
        case 'mobile_worker_dashboard':
            return mobile_worker_dashboard_action($pdo, $input);

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
        'images' => json_decode((string)($job['images'] ?? ''), true) ?: [],
    ];
}

// ============================================================
// Push Notifications
// ============================================================

function mobile_send_push(PDO $pdo, string $userType, int $userId, array $message): void
{
    $table = $userType === 'worker' ? 'mobile_sessions' : 'mobile_sessions';
    $stmt = $pdo->prepare("SELECT push_token, platform FROM {$table} WHERE user_id = ? AND push_token IS NOT NULL AND push_token != '' ORDER BY updated_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $tokens = $stmt->fetchAll();
    if (!$tokens) {
        return;
    }

    $expoTokens = [];
    foreach ($tokens as $t) {
        $pt = (string)($t['push_token'] ?? '');
        if ($pt !== '' && !in_array($pt, $expoTokens, true)) {
            $expoTokens[] = $pt;
        }
    }
    if (!$expoTokens) {
        return;
    }

    $chunks = array_chunk($expoTokens, 100);
    foreach ($chunks as $chunk) {
        $messages = [];
        foreach ($chunk as $token) {
            $messages[] = array_merge([
                'to' => $token,
                'sound' => 'default',
                'priority' => 'high',
            ], $message);
        }
        $json = json_encode($messages);
        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
    }
}

// ============================================================
// Worker Auth
// ============================================================

/**
 * Login thợ — Smart auto-routing:
 * - Nếu có `phone` → gọi login by phone (Closed-Loop, không cần Telegram)
 * - Nếu có `worker_id` (Telegram ID) → login by Telegram ID + PIN
 * - Alias: mobile_worker_login_v2 cũng route vào đây
 */
function mobile_worker_login_action(PDO $pdo, array $input): array
{
    $phone    = digits_only((string)($input['phone'] ?? ''));
    $workerId = (int)($input['worker_id'] ?? 0);
    $pin      = (string)($input['pin'] ?? '');

    // --- Ưu tiên 1: Login bằng SĐT (Closed-Loop, không cần Telegram) ---
    if ($phone !== '' && strlen($phone) >= 8) {
        return mobile_worker_login_by_phone_action($pdo, $input);
    }

    // --- Ưu tiên 2: Login bằng worker_code nội bộ (DTH-001, ...) ---
    $workerCode = clean_string($input['worker_code'] ?? '', 20);
    if ($workerCode !== '' && $pin !== '') {
        $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE worker_code = ? LIMIT 1');
        $stmt->execute([$workerCode]);
        $profile = $stmt->fetch() ?: null;
        if ($profile) {
            $input['phone'] = (string)($profile['phone'] ?? '');
            return mobile_worker_login_by_phone_action($pdo, array_merge($input, [
                'phone' => (string)($profile['phone'] ?? $workerCode),
            ]));
        }
    }

    // --- Fallback: Login bằng Telegram ID + PIN (legacy) ---
    if ($workerId <= 0) {
        return [
            'status'      => 'error',
            'message'     => 'Vui lòng nhập số điện thoại (phone) hoặc worker_code để đăng nhập.',
            'code'        => 'MISSING_CREDENTIALS',
            'hint'        => 'Dùng action mobile_worker_login_by_phone với {phone, pin} hoặc {worker_code, pin}.',
        ];
    }
    if ($pin === '') {
        return ['status' => 'error', 'message' => 'Vui lòng nhập PIN.', 'code' => 'MISSING_PIN'];
    }

    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Tài khoản thợ chưa được đăng ký.', 'code' => 'WORKER_NOT_FOUND'];
    }
    if ((int)($profile['is_active'] ?? 1) !== 1) {
        return ['status' => 'error', 'message' => 'Tài khoản thợ đang bị khoá.', 'code' => 'WORKER_INACTIVE'];
    }

    $pinHash = (string)($profile['pin_hash'] ?? '');
    if ($pinHash === '') {
        return [
            'status'          => 'error',
            'message'         => 'Tài khoản chưa đặt PIN. Vui lòng gọi action mobile_worker_set_pin để thiết lập.',
            'code'            => 'PIN_NOT_SET',
            'needs_pin_setup' => true,
            'worker_id'       => $workerId,
        ];
    }
    if (!password_verify($pin, $pinHash)) {
        return ['status' => 'error', 'message' => 'PIN không đúng.', 'code' => 'INVALID_PIN'];
    }

    $token = mobile_create_token($pdo, 'worker', $workerId);
    upsert_worker($pdo, $workerId, (string)($profile['telegram_name'] ?? ''), (string)($profile['telegram_username'] ?? ''), (string)($profile['role'] ?? 'worker'));

    return [
        'status'       => 'success',
        'token'        => $token,
        'worker'       => mobile_worker_row($profile),
        'shift_status' => (string)($profile['shift_status'] ?? 'off'),
        'auth_method'  => 'telegram_id',
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

/**
 * Gửi OTP cho khách hàng — Closed-Loop, không phụ thuộc Telegram.
 * Dev/Test: nếu MOBILE_OTP_MOCK=true → trả OTP thẳng trong response.
 * Production: ghi log server-side, OTP lưu DB → khách nhận qua kênh nội bộ.
 */
function mobile_customer_send_otp_action(PDO $pdo, array $input): array
{
    $phone = digits_only((string)($input['phone'] ?? ''));
    if (strlen($phone) < 8) {
        return ['status' => 'error', 'message' => 'Số điện thoại không hợp lệ.', 'code' => 'INVALID_PHONE'];
    }

    $otp     = mobile_generate_otp();
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    insert_compat($pdo, 'mobile_otp_codes', [
        'phone'      => $phone,
        'otp'        => $otp,
        'purpose'    => 'login',
        'expires_at' => $expires,
    ], ['created_at' => 'NOW()']);

    // Ghi log nội bộ (không phụ thuộc Telegram)
    error_log('[OTP] phone=' . mask_phone($phone) . ' otp_sent expires=' . $expires);

    // Dev/Test mode: trả OTP thẳng trong response
    if (mobile_mock_otp_enabled()) {
        return [
            'status'  => 'success',
            'message' => '[DEV] OTP: ' . $otp,
            'otp'     => $otp,
            'phone'   => $phone,
        ];
    }

    // Production: KHÔNG gửi qua Telegram — OTP chỉ lưu DB
    // SMS gateway hoặc kênh nội bộ tích hợp sau.
    // Hiện tại: client app tự biết OTP từ kênh ops nội bộ (admin cấp).
    return [
        'status'  => 'success',
        'message' => 'Mã OTP đã được tạo. Vui lòng kiểm tra với quản trị viên hoặc SMS.',
        'phone'   => $phone,
    ];
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
        'service_type' => $input['service_type'] ?? $input['service_id'] ?? $input['selected_service_name'] ?? $input['service_name'] ?? '',
        'selected_service_name' => $input['selected_service_name'] ?? $input['service_name'] ?? $input['service_id'] ?? '',
        'issue_description' => $input['issue_description'] ?? $input['description'] ?? $input['title'] ?? '',
        'customer_name' => $input['customer_name'] ?? $user['fullname'] ?? '',
        'customer_phone' => $input['customer_phone'] ?? $user['phone'] ?? '',
        'phone' => $input['customer_phone'] ?? $user['phone'] ?? '',
        'address' => $input['address'] ?? '',
        'map_lat' => $input['map_lat'] ?? $input['lat'] ?? null,
        'map_lng' => $input['map_lng'] ?? $input['lng'] ?? null,
        'preferred_time' => $input['preferred_time'] ?? $input['scheduled_at'] ?? '',
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
    $images = $input['images'] ?? [];
    if ($jobId > 0 && !empty($images) && is_array($images)) {
        $pdo->prepare('UPDATE job_posts SET images = ? WHERE id = ?')->execute([json_encode($images), $jobId]);
    }

    // Push notification to available workers
    try {
        $workerStmt = $pdo->prepare("SELECT DISTINCT user_id FROM mobile_sessions WHERE type = 'worker' AND push_token IS NOT NULL AND push_token != ''");
        $workerStmt->execute();
        $workers = $workerStmt->fetchAll();
        foreach ($workers as $w) {
            mobile_send_push($pdo, 'worker', (int)$w['user_id'], [
                'title' => 'Ca mới tại ' . ($payload['address'] ?: 'khu vực Lấp Vò'),
                'body' => 'Dịch vụ: ' . $serviceName . ' - ' . ($payload['issue_description'] ?: 'Khách cần hỗ trợ'),
                'data' => ['job_id' => $jobId, 'type' => 'new_job'],
            ]);
        }
    } catch (Throwable $e) {
        error_log('Push new job failed: ' . $e->getMessage());
    }

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

    try {
        $job = get_job_row($pdo, $jobId);
        $customerPhone = (string)($job['customer_phone'] ?? '');
        if ($customerPhone !== '') {
            $userStmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
            $userStmt->execute([$customerPhone]);
            $customerUserId = (int)($userStmt->fetchColumn() ?: 0);
            if ($customerUserId > 0) {
                mobile_send_push($pdo, 'customer', $customerUserId, [
                    'title' => 'Thợ đã nhận ca của bạn',
                    'body' => "Thợ {$workerName} đang đến địa chỉ của bạn.",
                    'data' => ['job_id' => $jobId, 'type' => 'job_assigned'],
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log('Push claim job failed: ' . $e->getMessage());
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

    $images = $input['images'] ?? [];
    if (!empty($images) && is_array($images)) {
        $existing = (string)($job['images'] ?? '');
        $existingArr = $existing ? json_decode($existing, true) : [];
        if (!is_array($existingArr)) $existingArr = [];
        $merged = array_merge($existingArr, $images);
        $pdo->prepare('UPDATE job_posts SET images = ? WHERE id = ?')->execute([json_encode($merged), $jobId]);
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

    // Push notification to customer
    try {
        $job = get_job_row($pdo, $jobId);
        $customerPhone = (string)($job['customer_phone'] ?? '');
        if ($customerPhone !== '') {
            $userStmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
            $userStmt->execute([$customerPhone]);
            $customerUserId = (int)($userStmt->fetchColumn() ?: 0);
            if ($customerUserId > 0) {
                mobile_send_push($pdo, 'customer', $customerUserId, [
                    'title' => 'Ca đã hoàn thành',
                    'body' => "Thợ {$workerName} đã hoàn thành ca #{$jobId}. Vui lòng đánh giá dịch vụ.",
                    'data' => ['job_id' => $jobId, 'type' => 'job_completed'],
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log('Push complete job failed: ' . $e->getMessage());
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

// ============================================================
// CLOSED-LOOP: Worker Auth bằng SĐT + PIN (không cần Telegram)
// ============================================================

function mobile_worker_login_by_phone_action(PDO $pdo, array $input): array
{
    $phone = digits_only((string)($input['phone'] ?? ''));
    $pin   = (string)($input['pin'] ?? '');

    if (strlen($phone) < 8) {
        return ['status' => 'error', 'message' => 'Số điện thoại không hợp lệ.', 'code' => 'INVALID_PHONE'];
    }
    if ($pin === '') {
        return ['status' => 'error', 'message' => 'Vui lòng nhập PIN.', 'code' => 'MISSING_PIN'];
    }

    // Tìm thợ theo SĐT (cột phone trong worker_profiles)
    $profile = null;
    if (column_exists($pdo, 'worker_profiles', 'phone')) {
        $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $profile = $stmt->fetch() ?: null;
    }

    // Fallback: tìm theo telegram_username (nếu SĐT chưa được map)
    if (!$profile) {
        $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE phone = ? OR telegram_username = ? LIMIT 1');
        $stmt->execute([$phone, $phone]);
        $profile = $stmt->fetch() ?: null;
    }

    if (!$profile) {
        return ['status' => 'error', 'message' => 'Số điện thoại chưa được đăng ký. Liên hệ admin để được cấp tài khoản.', 'code' => 'WORKER_NOT_FOUND'];
    }
    if ((int)($profile['is_active'] ?? 1) !== 1) {
        return ['status' => 'error', 'message' => 'Tài khoản thợ đang bị khoá.', 'code' => 'WORKER_INACTIVE'];
    }

    $pinHash = (string)($profile['pin_hash'] ?? '');
    if ($pinHash === '') {
        return [
            'status'          => 'error',
            'message'         => 'Tài khoản chưa đặt PIN. Liên hệ admin để được cấp PIN hoặc dùng action mobile_worker_set_pin.',
            'code'            => 'PIN_NOT_SET',
            'needs_pin_setup' => true,
            'worker_id'       => (int)$profile['telegram_user_id'],
        ];
    }
    if (!password_verify($pin, $pinHash)) {
        return ['status' => 'error', 'message' => 'PIN không đúng.', 'code' => 'INVALID_PIN'];
    }

    $workerId = (int)$profile['telegram_user_id'];
    $token = mobile_create_token($pdo, 'worker', $workerId);

    // Cập nhật SĐT vào worker_profiles nếu chưa có
    if (column_exists($pdo, 'worker_profiles', 'phone') && empty($profile['phone'])) {
        update_compat($pdo, 'worker_profiles', ['phone' => $phone], 'telegram_user_id = ?', [$workerId], ['updated_at' => 'NOW()']);
    }

    return [
        'status'      => 'success',
        'token'       => $token,
        'worker'      => mobile_worker_row($profile),
        'shift_status' => (string)($profile['shift_status'] ?? 'off'),
    ];
}

// ============================================================
// CLOSED-LOOP: Quản lý Ca làm việc (Shift)
// ============================================================

/**
 * Thợ bắt đầu ca — chuyển sang "Sẵn sàng" nhận đơn
 */
function mobile_worker_shift_start_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $lat = isset($input['lat']) && is_numeric($input['lat']) ? (float)$input['lat'] : null;
    $lng = isset($input['lng']) && is_numeric($input['lng']) ? (float)$input['lng'] : null;

    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Không tìm thấy tài khoản thợ.', 'code' => 'WORKER_NOT_FOUND'];
    }
    if ((string)($profile['shift_status'] ?? 'off') === 'on_shift') {
        return ['status' => 'error', 'message' => 'Bạn đang trong ca làm việc rồi.', 'code' => 'ALREADY_ON_SHIFT'];
    }

    // Tạo bản ghi shift mới
    $shiftId = 0;
    if (table_exists($pdo, 'worker_shifts')) {
        $shiftId = insert_compat($pdo, 'worker_shifts', [
            'worker_id'  => $workerId,
            'start_lat'  => $lat,
            'start_lng'  => $lng,
            'status'     => 'active',
        ], ['started_at' => 'NOW()', 'created_at' => 'NOW()']);
    }

    // Cập nhật trạng thái thợ
    $updateFields = ['shift_status' => 'on_shift', 'current_shift_id' => $shiftId ?: null];
    if ($lat !== null) $updateFields['current_lat'] = $lat;
    if ($lng !== null) $updateFields['current_lng'] = $lng;
    if ($lat !== null || $lng !== null) $updateFields['last_location_at'] = null;

    update_compat(
        $pdo, 'worker_profiles',
        $updateFields,
        'telegram_user_id = ?', [$workerId],
        array_merge(
            ['updated_at' => 'NOW()'],
            ($lat !== null || $lng !== null) ? ['last_location_at' => 'NOW()'] : []
        )
    );

    return [
        'status'      => 'success',
        'message'     => 'Đã bắt đầu ca. Bạn đang trong danh sách sẵn sàng nhận đơn.',
        'shift_id'    => $shiftId,
        'shift_status' => 'on_shift',
        'location'    => $lat !== null ? ['lat' => $lat, 'lng' => $lng] : null,
    ];
}

/**
 * Thợ kết thúc ca
 */
function mobile_worker_shift_end_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $lat = isset($input['lat']) && is_numeric($input['lat']) ? (float)$input['lat'] : null;
    $lng = isset($input['lng']) && is_numeric($input['lng']) ? (float)$input['lng'] : null;

    $profile = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Không tìm thấy tài khoản thợ.', 'code' => 'WORKER_NOT_FOUND'];
    }

    // Kiểm tra còn ca nào đang assigned không
    $activeJobStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM job_posts
         WHERE COALESCE(telegram_worker_id, worker_id, 0) = ?
           AND status NOT IN ('completed','cancelled','spam','failed')
           AND completed_at IS NULL"
    );
    $activeJobStmt->execute([$workerId]);
    $activeJobs = (int)$activeJobStmt->fetchColumn();
    if ($activeJobs > 0) {
        return [
            'status'  => 'error',
            'message' => "Bạn còn {$activeJobs} ca chưa hoàn thành. Vui lòng hoàn thành hoặc hủy ca trước khi kết thúc ca trực.",
            'code'    => 'HAS_ACTIVE_JOBS',
        ];
    }

    // Đóng shift record
    $shiftId = (int)($profile['current_shift_id'] ?? 0);
    if ($shiftId > 0 && table_exists($pdo, 'worker_shifts')) {
        $updateShift = ['status' => 'ended'];
        if ($lat !== null) $updateShift['end_lat'] = $lat;
        if ($lng !== null) $updateShift['end_lng'] = $lng;
        update_compat($pdo, 'worker_shifts', $updateShift, 'id = ?', [$shiftId], ['ended_at' => 'NOW()', 'updated_at' => 'NOW()']);
    }

    update_compat(
        $pdo, 'worker_profiles',
        ['shift_status' => 'off', 'current_shift_id' => null],
        'telegram_user_id = ?', [$workerId],
        ['updated_at' => 'NOW()']
    );

    // Lấy tổng thu nhập trong ca
    $earnings = [];
    if ($shiftId > 0 && table_exists($pdo, 'worker_shifts')) {
        $shiftStmt = $pdo->prepare('SELECT * FROM worker_shifts WHERE id = ? LIMIT 1');
        $shiftStmt->execute([$shiftId]);
        $shift = $shiftStmt->fetch();
        $earnings = [
            'jobs_received'  => (int)($shift['jobs_received'] ?? 0),
            'jobs_completed' => (int)($shift['jobs_completed'] ?? 0),
            'total_earned'   => (int)($shift['total_earned'] ?? 0),
        ];
    }

    return [
        'status'      => 'success',
        'message'     => 'Đã kết thúc ca. Cảm ơn bạn đã làm việc!',
        'shift_id'    => $shiftId,
        'shift_status' => 'off',
        'shift_summary' => $earnings,
    ];
}

/**
 * Lấy trạng thái ca hiện tại
 */
function mobile_worker_shift_status_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $profile  = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Không tìm thấy tài khoản thợ.'];
    }
    $shiftStatus = (string)($profile['shift_status'] ?? 'off');
    $shiftId = (int)($profile['current_shift_id'] ?? 0);
    $shift = null;
    if ($shiftId > 0 && table_exists($pdo, 'worker_shifts')) {
        $stmt = $pdo->prepare('SELECT * FROM worker_shifts WHERE id = ? LIMIT 1');
        $stmt->execute([$shiftId]);
        $row = $stmt->fetch();
        if ($row) {
            $shift = [
                'id'         => (int)$row['id'],
                'started_at' => (string)$row['started_at'],
                'status'     => (string)$row['status'],
            ];
        }
    }
    return [
        'status'      => 'success',
        'shift_status' => $shiftStatus,
        'current_shift' => $shift,
        'worker'      => mobile_worker_row($profile),
    ];
}

// ============================================================
// CLOSED-LOOP: Nhận / Hoàn thành / Hủy ca qua App
// ============================================================

/**
 * Nhận ca mới qua App (không qua Telegram callback)
 */
function mobile_worker_claim_job_v2_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $jobId    = (int)($input['job_id'] ?? 0);

    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thiếu job_id.', 'code' => 'MISSING_JOB_ID'];
    }

    $result = claim_job_via_app($pdo, $jobId, $workerId);
    if (empty($result['ok'])) {
        return ['status' => 'error', 'message' => $result['message'] ?? 'Nhận ca thất bại.', 'code' => $result['code'] ?? 'CLAIM_FAILED'];
    }

    $job = get_job_row($pdo, $jobId);
    return [
        'status'  => 'success',
        'message' => $result['message'],
        'job'     => $job ? mobile_job_row($pdo, $job) : null,
    ];
}

/**
 * Hoàn thành ca qua App (thay thế reply "XONG" trên Telegram)
 */
function mobile_worker_complete_job_action(PDO $pdo, array $input): array
{
    $session      = mobile_require_auth($pdo, $input, 'worker');
    $workerId     = (int)$session['user_id'];
    $jobId        = (int)($input['job_id'] ?? 0);
    $actualAmount = (int)($input['actual_amount'] ?? $input['amount'] ?? 0);

    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thiếu job_id.', 'code' => 'MISSING_JOB_ID'];
    }

    // Lưu ảnh hoàn thành nếu có
    $images = $input['images'] ?? [];
    if (!empty($images) && is_array($images)) {
        $existingJob = get_job_row($pdo, $jobId);
        if ($existingJob) {
            $existing = json_decode((string)($existingJob['images'] ?? ''), true) ?: [];
            $merged = array_merge($existing, $images);
            $pdo->prepare('UPDATE job_posts SET images = ? WHERE id = ?')->execute([json_encode($merged), $jobId]);
        }
    }

    $result = complete_job_via_app($pdo, $jobId, $workerId, $actualAmount);
    if (empty($result['ok'])) {
        return ['status' => 'error', 'message' => $result['message'] ?? 'Hoàn thành ca thất bại.', 'code' => $result['code'] ?? 'COMPLETE_FAILED'];
    }

    $job = get_job_row($pdo, $jobId);
    return [
        'status'          => 'success',
        'message'         => $result['message'],
        'platform_fee'    => $result['platform_fee'] ?? 0,
        'cumulative_debt' => $result['cumulative_debt'] ?? 0,
        'job'             => $job ? mobile_job_row($pdo, $job) : null,
    ];
}

/**
 * Hủy ca qua App (thay thế reply "HUY" trên Telegram)
 */
function mobile_worker_cancel_job_app_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $jobId    = (int)($input['job_id'] ?? 0);
    $reason   = clean_string($input['reason'] ?? '', 500);

    if ($jobId <= 0) {
        return ['status' => 'error', 'message' => 'Thiếu job_id.', 'code' => 'MISSING_JOB_ID'];
    }

    $result = cancel_job_via_app($pdo, $jobId, $workerId, $reason);
    if (empty($result['ok'])) {
        return ['status' => 'error', 'message' => $result['message'] ?? 'Hủy ca thất bại.', 'code' => $result['code'] ?? 'CANCEL_FAILED'];
    }

    return ['status' => 'success', 'message' => $result['message'], 'job_id' => $jobId];
}

// ============================================================
// CLOSED-LOOP: Thông báo nội bộ (thay Telegram DM)
// ============================================================

/**
 * Lấy danh sách thông báo nội bộ của thợ
 */
function mobile_worker_notifications_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];

    if (!table_exists($pdo, 'in_app_notifications')) {
        return ['status' => 'success', 'notifications' => [], 'unread_count' => 0];
    }

    $limit  = max(1, min(100, (int)($input['limit'] ?? $_GET['limit'] ?? 30)));
    $offset = max(0, (int)($input['offset'] ?? $_GET['offset'] ?? 0));
    $unreadOnly = !empty($input['unread_only']) || !empty($_GET['unread_only']);

    $where  = 'target_type = ? AND target_id = ?';
    $params = ['worker', $workerId];
    if ($unreadOnly) {
        $where .= ' AND is_read = 0';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM in_app_notifications WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM in_app_notifications WHERE target_type = 'worker' AND target_id = ? AND is_read = 0");
    $unreadStmt->execute([$workerId]);
    $unreadCount = (int)$unreadStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM in_app_notifications WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $rows = $stmt->fetchAll();

    $notifications = array_map(static function (array $n): array {
        return [
            'id'             => (int)$n['id'],
            'title'          => (string)$n['title'],
            'body'           => (string)$n['body'],
            'type'           => (string)$n['type'],
            'reference_type' => (string)($n['reference_type'] ?? ''),
            'reference_id'   => isset($n['reference_id']) ? (int)$n['reference_id'] : null,
            'payload'        => !empty($n['payload']) ? json_decode($n['payload'], true) : null,
            'is_read'        => (bool)$n['is_read'],
            'read_at'        => (string)($n['read_at'] ?? ''),
            'created_at'     => (string)$n['created_at'],
        ];
    }, $rows);

    return [
        'status'        => 'success',
        'total'         => $total,
        'unread_count'  => $unreadCount,
        'limit'         => $limit,
        'offset'        => $offset,
        'notifications' => $notifications,
    ];
}

/**
 * Đánh dấu thông báo đã đọc
 * Gửi notification_id=0 hoặc mark_all=true để đánh dấu tất cả
 */
function mobile_worker_read_notification_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];

    if (!table_exists($pdo, 'in_app_notifications')) {
        return ['status' => 'success', 'message' => 'Đã đánh dấu đọc.'];
    }

    $markAll = !empty($input['mark_all']) || !empty($_GET['mark_all']);
    $notifId = (int)($input['notification_id'] ?? $input['id'] ?? 0);

    if ($markAll) {
        $pdo->prepare(
            "UPDATE in_app_notifications SET is_read = 1, read_at = NOW()
             WHERE target_type = 'worker' AND target_id = ? AND is_read = 0"
        )->execute([$workerId]);
        return ['status' => 'success', 'message' => 'Đã đánh dấu tất cả đã đọc.'];
    }

    if ($notifId <= 0) {
        return ['status' => 'error', 'message' => 'Thiếu notification_id.', 'code' => 'MISSING_NOTIF_ID'];
    }

    $pdo->prepare(
        "UPDATE in_app_notifications SET is_read = 1, read_at = NOW()
         WHERE id = ? AND target_type = 'worker' AND target_id = ?"
    )->execute([$notifId, $workerId]);

    return ['status' => 'success', 'message' => 'Đã đánh dấu đọc.', 'notification_id' => $notifId];
}

// ============================================================
// CLOSED-LOOP: Worker Dashboard — Tổng hợp
// ============================================================

/**
 * Dashboard tổng hợp cho Worker App:
 * - Thông tin thợ + trạng thái ca
 * - Danh sách ca đang assigned
 * - Thu nhập tháng hiện tại
 * - Thông báo chưa đọc
 * - Phí nền tảng còn nợ
 */
function mobile_worker_dashboard_action(PDO $pdo, array $input): array
{
    $session  = mobile_require_auth($pdo, $input, 'worker');
    $workerId = (int)$session['user_id'];
    $profile  = get_worker_profile($pdo, $workerId);
    if (!$profile) {
        return ['status' => 'error', 'message' => 'Không tìm thấy tài khoản thợ.', 'code' => 'WORKER_NOT_FOUND'];
    }

    // Ca đang assigned
    $activeJobsStmt = $pdo->prepare(
        "SELECT * FROM job_posts
         WHERE COALESCE(telegram_worker_id, worker_id, 0) = ?
           AND status NOT IN ('completed','cancelled','spam','failed')
           AND completed_at IS NULL
         ORDER BY assigned_at DESC LIMIT 5"
    );
    $activeJobsStmt->execute([$workerId]);
    $activeJobs = array_map(
        static fn(array $job): array => mobile_job_row($pdo, $job),
        $activeJobsStmt->fetchAll()
    );

    // Thu nhập tháng hiện tại
    $month    = date('Y-m');
    $earnings = mobile_worker_earnings_internal($pdo, $workerId, $month);

    // Thông báo chưa đọc
    $unreadCount = 0;
    $recentNotifications = [];
    if (table_exists($pdo, 'in_app_notifications')) {
        $unreadStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM in_app_notifications
             WHERE target_type = 'worker' AND target_id = ? AND is_read = 0"
        );
        $unreadStmt->execute([$workerId]);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        $recentStmt = $pdo->prepare(
            "SELECT * FROM in_app_notifications
             WHERE target_type = 'worker' AND target_id = ?
             ORDER BY created_at DESC LIMIT 5"
        );
        $recentStmt->execute([$workerId]);
        $recentNotifications = array_map(static function (array $n): array {
            return [
                'id'         => (int)$n['id'],
                'title'      => (string)$n['title'],
                'body'       => (string)$n['body'],
                'type'       => (string)$n['type'],
                'is_read'    => (bool)$n['is_read'],
                'payload'    => !empty($n['payload']) ? json_decode($n['payload'], true) : null,
                'created_at' => (string)$n['created_at'],
            ];
        }, $recentStmt->fetchAll());
    }

    // Phí nền tảng còn nợ
    $feeDebt = worker_fee_debt($pdo, $workerId);

    return [
        'status'               => 'success',
        'worker'               => mobile_worker_row($profile),
        'shift_status'         => (string)($profile['shift_status'] ?? 'off'),
        'active_jobs'          => $activeJobs,
        'active_jobs_count'    => count($activeJobs),
        'earnings_this_month'  => $earnings,
        'fee_debt'             => $feeDebt,
        'unread_notifications' => $unreadCount,
        'recent_notifications' => $recentNotifications,
    ];
}

// ============================================================
// CLOSED-LOOP: Admin tạo tài khoản thợ mới (không cần Telegram)
// ============================================================

/**
 * Admin tạo hoặc cập nhật tài khoản thợ bằng SĐT + tên.
 * Không cần Telegram ID. Worker_code được tạo tự động (DTH-001, DTH-002, ...).
 *
 * Input (admin session hoặc admin_token):
 *   - phone: SĐT của thợ (bắt buộc)
 *   - name: Tên thợ (bắt buộc)
 *   - worker_type: loại thợ (mặc định: ho_kinh_doanh)
 *   - pin: PIN ban đầu (4-6 số, mặc định: 123456 nếu không truyền)
 *   - admin_key: CRON_SECRET để xác thực (hoặc dùng admin session)
 */
function mobile_worker_init_account_action(PDO $pdo, array $input): array
{
    // Xác thực: admin session hoặc admin_key
    $adminKey = clean_string($input['admin_key'] ?? '', 100);
    $cronSecret = app_env('CRON_SECRET', '');
    $isAdminSession = app_admin_is_authenticated();
    if (!$isAdminSession && ($adminKey === '' || $adminKey !== $cronSecret)) {
        return ['status' => 'error', 'message' => 'Không có quyền truy cập.', 'code' => 'UNAUTHORIZED'];
    }

    $phone = digits_only((string)($input['phone'] ?? ''));
    if (strlen($phone) < 8) {
        return ['status' => 'error', 'message' => 'Số điện thoại không hợp lệ.', 'code' => 'INVALID_PHONE'];
    }

    $name = clean_string($input['name'] ?? '', 150);
    if ($name === '') {
        return ['status' => 'error', 'message' => 'Vui lòng nhập tên thợ.', 'code' => 'MISSING_NAME'];
    }

    $workerType = clean_string($input['worker_type'] ?? 'ho_kinh_doanh', 50);
    $initialPin = clean_string($input['pin'] ?? '123456', 10);
    if (!preg_match('/^\d{4,6}$/', $initialPin)) {
        $initialPin = '123456';
    }

    // Sinh worker_code tự động (DTH-001, DTH-002, ...)
    $workerCode = null;
    if (column_exists($pdo, 'worker_profiles', 'worker_code')) {
        $lastCode = $pdo->query(
            "SELECT worker_code FROM worker_profiles WHERE worker_code LIKE 'DTH-%' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        $nextNum = 1;
        if ($lastCode && preg_match('/DTH-(\d+)$/', (string)$lastCode, $m)) {
            $nextNum = (int)$m[1] + 1;
        }
        $workerCode = 'DTH-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
    }

    // Dùng phone number làm pseudo telegram_user_id (prefix 9 để tránh trùng)
    // Lấy 9 số cuối của phone: 0979553289 → 979553289
    $pseudoId = (int)('9' . substr(preg_replace('/\D/', '', $phone), -8));

    $pinHash = password_hash($initialPin, PASSWORD_BCRYPT);

    $fields = [
        'telegram_user_id' => $pseudoId,
        'telegram_name'    => $name,
        'phone'            => $phone,
        'identity_code'    => $phone,
        'worker_type'      => $workerType,
        'role'             => 'worker',
        'is_admin'         => 0,
        'is_active'        => 1,
        'pin_hash'         => $pinHash,
    ];
    if ($workerCode !== null) {
        $fields['worker_code'] = $workerCode;
    }

    // Upsert: nếu SĐT đã tồn tại thì cập nhật, không tạo mới
    $pdo->prepare(
        "INSERT INTO worker_profiles
            (telegram_user_id, telegram_name, phone, identity_code, worker_type, role, is_admin, is_active, pin_hash, worker_code, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'worker', 0, 1, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            telegram_name   = VALUES(telegram_name),
            phone           = VALUES(phone),
            identity_code   = COALESCE(identity_code, VALUES(identity_code)),
            worker_type     = VALUES(worker_type),
            is_active       = 1,
            pin_hash        = IF(pin_hash IS NULL OR pin_hash = '', VALUES(pin_hash), pin_hash),
            worker_code     = COALESCE(worker_code, VALUES(worker_code)),
            updated_at      = NOW()"
    )->execute([
        $pseudoId, $name, $phone, $phone,
        $workerType, $pinHash, $workerCode,
    ]);

    // Lấy lại profile vừa tạo/update
    $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $profile = $stmt->fetch() ?: null;

    return [
        'status'      => 'success',
        'message'     => 'Tài khoản thợ đã được tạo/cập nhật thành công.',
        'worker_code' => $workerCode,
        'phone'       => $phone,
        'name'        => $name,
        'pin_default' => $initialPin,
        'worker_id'   => $pseudoId,
        'worker'      => $profile ? mobile_worker_row($profile) : null,
        'note'        => 'Thợ có thể đăng nhập bằng: {action: mobile_worker_login_by_phone, phone: "' . $phone . '", pin: "' . $initialPin . '"}',
    ];
}

// ============================================================
// CLOSED-LOOP: Admin reset PIN thợ
// ============================================================

/**
 * Admin reset PIN của thợ về PIN mới (hoặc PIN mặc định).
 *
 * Input:
 *   - phone: SĐT thợ (bắt buộc)
 *   - new_pin: PIN mới (4-6 số, mặc định: 123456)
 *   - admin_key: CRON_SECRET
 */
function mobile_worker_reset_pin_action(PDO $pdo, array $input): array
{
    // Xác thực admin
    $adminKey = clean_string($input['admin_key'] ?? '', 100);
    $cronSecret = app_env('CRON_SECRET', '');
    $isAdminSession = app_admin_is_authenticated();
    if (!$isAdminSession && ($adminKey === '' || $adminKey !== $cronSecret)) {
        return ['status' => 'error', 'message' => 'Không có quyền truy cập.', 'code' => 'UNAUTHORIZED'];
    }

    $phone = digits_only((string)($input['phone'] ?? ''));
    if (strlen($phone) < 8) {
        return ['status' => 'error', 'message' => 'Số điện thoại không hợp lệ.', 'code' => 'INVALID_PHONE'];
    }

    $newPin = clean_string($input['new_pin'] ?? '123456', 10);
    if (!preg_match('/^\d{4,6}$/', $newPin)) {
        $newPin = '123456';
    }

    // Tìm thợ
    $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $profile = $stmt->fetch() ?: null;

    if (!$profile) {
        // Thử tìm theo telegram_user_id nếu phone là số nguyên
        $workerId = (int)$phone;
        if ($workerId > 0) {
            $profile = get_worker_profile($pdo, $workerId);
        }
        if (!$profile) {
            return ['status' => 'error', 'message' => 'Không tìm thấy thợ với SĐT này.', 'code' => 'WORKER_NOT_FOUND'];
        }
    }

    $newHash = password_hash($newPin, PASSWORD_BCRYPT);
    update_compat(
        $pdo, 'worker_profiles',
        ['pin_hash' => $newHash],
        'telegram_user_id = ?',
        [(int)$profile['telegram_user_id']],
        ['updated_at' => 'NOW()']
    );

    // Xoá toàn bộ session cũ để buộc login lại
    $pdo->prepare(
        "DELETE FROM mobile_sessions WHERE type = 'worker' AND user_id = ?"
    )->execute([(int)$profile['telegram_user_id']]);

    return [
        'status'    => 'success',
        'message'   => 'Đã reset PIN thành công. Các phiên đăng nhập cũ đã bị vô hiệu hoá.',
        'phone'     => $phone,
        'name'      => (string)($profile['telegram_name'] ?? ''),
        'new_pin'   => $newPin,
        'worker_id' => (int)$profile['telegram_user_id'],
    ];
}
