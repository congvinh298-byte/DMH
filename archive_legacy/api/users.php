<?php
// Module: users



function active_identifiers(array $input, string $phone = ''): array
{
    $items = [];
    $device = clean_string((string)($input['device_fingerprint'] ?? $input['fingerprint'] ?? $input['device_id'] ?? ''), 255);
    if ($device !== '') {
        $items[] = ['identifier' => $device, 'type' => 'device'];
    }
    $ip = client_ip();
    if ($ip !== '0.0.0.0') {
        $items[] = ['identifier' => $ip, 'type' => 'ip'];
    }
    $digits = digits_only($phone);
    if ($digits !== '') {
        $items[] = ['identifier' => $digits, 'type' => 'phone'];
    }
    return $items;
}

function ensure_not_banned(PDO $pdo, array $identifiers)
{
    if ($identifiers === []) {
        return;
    }
    $stmt = $pdo->prepare("SELECT identifier, ban_type, reason FROM banned_devices
        WHERE identifier = ? AND ban_type = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
    foreach ($identifiers as $item) {
        $stmt->execute([$item['identifier'], $item['type']]);
        $ban = $stmt->fetch();
        if ($ban) {
            json_out([
                'status' => 'error',
                'message' => 'Thiet bi/IP/SDT dang bi khoa do spam. Vui long lien he Dien Tu Hieu.',
                'ban' => $ban,
            ], 403);
        }
    }
}

function record_client_request(PDO $pdo, int $jobId, array $identifiers)
{
    foreach ($identifiers as $item) {
        $pdo->prepare("INSERT IGNORE INTO job_client_identifiers (job_id, identifier, identifier_type) VALUES (?, ?, ?)")
            ->execute([$jobId, $item['identifier'], $item['type']]);
        $pdo->prepare("INSERT INTO client_abuse (identifier, identifier_type, request_count, last_job_id)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE request_count = request_count + 1, last_job_id = VALUES(last_job_id), updated_at = NOW()")
            ->execute([$item['identifier'], $item['type'], $jobId]);
    }
}

function text_is_cancel(string $text): bool
{
    return (bool)preg_match('/\b(huy|hủy|cancel|bo ca|bỏ ca)\b/iu', $text);
}

function text_is_spam(string $text): bool
{
    return (bool)preg_match('/\b(spam|fake|ao|ảo|bom|lua dao|lừa đảo|sai so|sai số)\b/iu', $text);
}

function record_spam_report(PDO $pdo, int $jobId, int $workerId, string $workerName, string $note, string $role = 'worker'): array
{
    upsert_worker($pdo, $workerId, $workerName, '', $role);
    $pdo->prepare("INSERT IGNORE INTO spam_reports (job_id, telegram_user_id, telegram_name, note) VALUES (?, ?, ?, ?)")
        ->execute([$jobId, $workerId, $workerName, $note]);
    insert_compat($pdo, 'job_claims', [
        'job_id' => $jobId,
        'telegram_user_id' => $workerId,
        'telegram_name' => $workerName,
        'outcome' => 'spam_report',
        'note' => $note,
    ], ['created_at' => 'NOW()']);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM spam_reports WHERE job_id = ?');
    $countStmt->execute([$jobId]);
    $reportCount = (int)$countStmt->fetchColumn();

    $job = get_job_row($pdo, $jobId) ?: [];
    update_compat($pdo, 'job_posts', ['spam_count' => $reportCount], 'id = ?', [$jobId]);

    $alreadyAutoSpam = stripos((string)($job['cancel_reason'] ?? ''), 'Auto spam') !== false;
    if ($reportCount >= 5 && job_display_status($job) !== 'spam' && !$alreadyAutoSpam) {
        update_compat($pdo, 'job_posts', [
            'status' => job_status($pdo, 'spam'),
            'cancel_reason' => 'Auto spam: 5+ worker reports',
        ], 'id = ?', [$jobId], ['cancelled_at' => 'NOW()', 'updated_at' => 'NOW()']);

        $idStmt = $pdo->prepare('SELECT identifier, identifier_type FROM job_client_identifiers WHERE job_id = ?');
        $idStmt->execute([$jobId]);
        foreach ($idStmt->fetchAll() as $item) {
            $pdo->prepare("INSERT INTO client_abuse (identifier, identifier_type, request_count, fake_count, last_job_id)
                VALUES (?, ?, 1, 1, ?)
                ON DUPLICATE KEY UPDATE fake_count = fake_count + 1, last_job_id = VALUES(last_job_id), updated_at = NOW()")
                ->execute([$item['identifier'], $item['identifier_type'], $jobId]);

            $check = $pdo->prepare('SELECT fake_count FROM client_abuse WHERE identifier = ? AND identifier_type = ? LIMIT 1');
            $check->execute([$item['identifier'], $item['identifier_type']]);
            $fakeCount = (int)$check->fetchColumn();
            if ($fakeCount >= 3) {
                $pdo->prepare("INSERT INTO banned_devices (identifier, ban_type, reason, spam_job_id, spam_count, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, 'system', NOW())
                    ON DUPLICATE KEY UPDATE reason = VALUES(reason), spam_job_id = VALUES(spam_job_id), spam_count = VALUES(spam_count), expires_at = NULL")
                    ->execute([$item['identifier'], $item['identifier_type'], 'Auto-ban: 3 fake jobs and 5+ worker spam replies.', $jobId, $fakeCount]);
                $pdo->prepare('UPDATE client_abuse SET banned_at = NOW() WHERE identifier = ? AND identifier_type = ?')
                    ->execute([$item['identifier'], $item['identifier_type']]);
            }
        }
    }

    return ['ok' => true, 'message' => "Da ghi nhan spam report ({$reportCount}/5)."];
}

function customer_normalize_login_key(string $value): string
{
    $value = trim($value);
    if (stripos($value, 'DTH-CUSTOMER:') === 0) {
        $value = substr($value, strlen('DTH-CUSTOMER:'));
    }
    return strtoupper(clean_string($value, 128));
}

function customer_generate_login_key(PDO $pdo): string
{
    do {
        $key = 'DTHC-' . strtoupper(bin2hex(random_bytes(8)));
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login_key = ? LIMIT 1');
        $stmt->execute([$key]);
    } while ($stmt->fetch());
    return $key;
}

function retail_customer_row(array $row): array
{
    foreach (['id', 'is_active', 'total_spent', 'loyalty_points'] as $field) {
        $row[$field] = (int)($row[$field] ?? 0);
    }
    $loginKey = (string)($row['login_key'] ?? '');
    $row['login_key'] = $loginKey;
    $row['qr_payload'] = $loginKey !== '' ? customer_qr_payload($loginKey) : '';
    $row['qr_image_url'] = $loginKey !== '' ? qr_image_url_for_payload(customer_qr_payload($loginKey), 160) : '';
    return $row;
}

function retail_customer_by_phone(PDO $pdo, string $phone, bool $lock = false): ?array
{
    $phone = digits_only($phone);
    if (strlen($phone) < 8) {
        return null;
    }
    $suffix = $lock ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE phone = ? ORDER BY id ASC LIMIT 1' . $suffix);
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    return $row ? retail_customer_row($row) : null;
}

function reward_retail_customer(PDO $pdo, string $fullname, string $phone, int $saleAmount): array
{
    $fullname = clean_string($fullname, 150);
    $phone = digits_only($phone);
    if ($fullname === '' || strlen($phone) < 8) {
        throw new InvalidArgumentException('Ban hang tich diem can ten khach va so dien thoai hop le.');
    }

    $earned = loyalty_points_for_amount($saleAmount);
    $customer = retail_customer_by_phone($pdo, $phone, true);
    if ($customer) {
        $newTotalSpent = (int)$customer['total_spent'] + $saleAmount;
        $newPoints = (int)$customer['loyalty_points'] + $earned;
        $updates = [
            'fullname' => $fullname,
            'is_active' => 1,
            'member_rank' => loyalty_member_rank($newPoints),
            'total_spent' => $newTotalSpent,
            'loyalty_points' => $newPoints,
        ];
        if ((string)($customer['login_key'] ?? '') === '') {
            $updates['login_key'] = customer_generate_login_key($pdo);
        }
        update_compat($pdo, 'users', $updates, 'id = ?', [(int)$customer['id']], ['updated_at' => 'NOW()']);
        $customer = retail_customer_by_phone($pdo, $phone, false) ?: $customer;
    } else {
        $customerId = insert_compat($pdo, 'users', [
            'role' => 'buyer',
            'fullname' => $fullname,
            'phone' => $phone,
            'login_key' => customer_generate_login_key($pdo),
            'is_active' => 1,
            'member_rank' => loyalty_member_rank($earned),
            'total_spent' => $saleAmount,
            'loyalty_points' => $earned,
        ], ['created_at' => 'NOW()']);
        $customer = retail_customer_by_phone($pdo, $phone, false) ?: [
            'id' => $customerId,
            'fullname' => $fullname,
            'phone' => $phone,
            'member_rank' => loyalty_member_rank($earned),
            'total_spent' => $saleAmount,
            'loyalty_points' => $earned,
        ];
    }

    $customer = retail_customer_row($customer);
    $customer['points_earned'] = $earned;
    return $customer;
}

function app_customer_register_action(PDO $pdo, array $input): array
{
    $phone = digits_only((string)($input['phone'] ?? $input['customer_phone'] ?? ''));
    $fullname = clean_string($input['name'] ?? $input['fullname'] ?? $input['customer_name'] ?? '', 150);
    if (strlen($phone) < 8) {
        json_out(['status' => 'error', 'message' => 'Vui long nhap so dien thoai khach hang hop le.'], 400);
    }
    if ($fullname === '') {
        $fullname = 'Khach ' . substr($phone, -4);
    }

    $existing = retail_customer_by_phone($pdo, $phone, false);
    if ($existing) {
        if ((string)($existing['login_key'] ?? '') === '') {
            update_compat($pdo, 'users', [
                'fullname' => $fullname,
                'login_key' => customer_generate_login_key($pdo),
                'is_active' => 1,
            ], 'id = ?', [(int)$existing['id']], ['updated_at' => 'NOW()']);
            $existing = retail_customer_by_phone($pdo, $phone, false) ?: $existing;
            return [
                'status' => 'success',
                'message' => 'So dien thoai nay da co tren he thong. Da cap QR dang nhap rieng cho khach hang.',
                'data' => $existing,
            ];
        }
        json_out([
            'status' => 'error',
            'message' => 'So dien thoai nay da co tai khoan. Vui long dang nhap bang QR da cap.',
            'data' => $existing,
        ], 409);
    }

    $customerId = insert_compat($pdo, 'users', [
        'role' => 'buyer',
        'fullname' => $fullname,
        'phone' => $phone,
        'login_key' => customer_generate_login_key($pdo),
        'is_active' => 1,
        'member_rank' => loyalty_member_rank(0),
        'total_spent' => 0,
        'loyalty_points' => 0,
    ], [
        'created_at' => 'NOW()',
        'updated_at' => 'NOW()',
    ]);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$customerId]);
    return [
        'status' => 'success',
        'message' => 'Da cap QR dang nhap rieng cho khach hang. Hay giu QR nay de dang nhap ve sau.',
        'data' => retail_customer_row($stmt->fetch() ?: []),
    ];
}

function app_customer_login_qr_action(PDO $pdo, array $input): array
{
    $loginKey = customer_normalize_login_key((string)($input['login_key'] ?? $input['qr_data'] ?? $input['key'] ?? ''));
    if ($loginKey === '') {
        json_out(['status' => 'error', 'message' => 'Vui long quet QR hoac nhap key khach hang.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE login_key = ? LIMIT 1');
    $stmt->execute([$loginKey]);
    $customer = $stmt->fetch();
    if (!$customer) {
        json_out(['status' => 'error', 'message' => 'QR/key khach hang khong hop le.'], 404);
    }
    if ((int)($customer['is_active'] ?? 0) !== 1) {
        json_out(['status' => 'error', 'message' => 'Tai khoan khach hang dang bi tam dung.'], 403);
    }

    update_compat($pdo, 'users', [], 'id = ?', [(int)$customer['id']], [
        'last_login_at' => 'NOW()',
        'updated_at' => 'NOW()',
    ]);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$customer['id']]);
    return [
        'status' => 'success',
        'message' => 'Dang nhap khach hang thanh cong.',
        'data' => retail_customer_row($stmt->fetch() ?: []),
    ];
}
function login_or_register_phone_action(PDO $pdo, array $input): array
{
    $phone = clean_string($input['phone'] ?? '', 30);
    $fullname = clean_string($input['fullname'] ?? '', 150);
    
    if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
        json_out(['status' => 'error', 'message' => 'So dien thoai khong hop le.']);
    }
    if ($fullname === '') {
        json_out(['status' => 'error', 'message' => 'Vui long nhap ho ten.']);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user) {
        // Create new user
        $loginKey = customer_generate_login_key($pdo);
        $stmtIns = $pdo->prepare('INSERT INTO users (role, fullname, phone, login_key, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
        $stmtIns->execute(['buyer', $fullname, $phone, $loginKey]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    } else {
        // Ensure they have a login_key
        if (empty($user['login_key'])) {
            $loginKey = customer_generate_login_key($pdo);
            update_compat($pdo, 'users', ['login_key' => $loginKey], 'id = ?', [$user['id']], ['updated_at' => 'NOW()']);
            $user['login_key'] = $loginKey;
        }
        // Optionally update their name if it was empty before
        if (empty($user['fullname']) && $fullname !== '') {
            update_compat($pdo, 'users', ['fullname' => $fullname], 'id = ?', [$user['id']], ['updated_at' => 'NOW()']);
            $user['fullname'] = $fullname;
        }
    }

    return [
        'status' => 'success',
        'message' => 'Dang nhap thanh cong',
        'data' => [
            'type' => 'user',
            'id' => (int)$user['id'],
            'fullname' => $user['fullname'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'member_rank' => $user['member_rank'],
            'loyalty_points' => (int)$user['loyalty_points'],
            'login_key' => $user['login_key'],
            'qr_image_url' => qr_image_url_for_payload(customer_qr_payload($user['login_key']), 160)
        ]
    ];
}

function verify_login_key_action(PDO $pdo, array $input): array
{
    $rawKey = trim($input['login_key'] ?? '');
    if ($rawKey === '') {
        json_out(['status' => 'error', 'message' => 'Vui lòng cung cấp mã đăng nhập. Mã QR có thể không đúng chuẩn.']);
    }

    $storeKey    = marketplace_normalize_login_key($rawKey);
    $customerKey = customer_normalize_login_key($rawKey);
    $isStoreScan = null;
    $loginKey    = $rawKey;

    if (stripos($rawKey, 'DTH-STORE:') === 0 || stripos($rawKey, 'DTHS-') === 0) {
        $loginKey    = $storeKey;
        $isStoreScan = true;
    } elseif (stripos($rawKey, 'DTH-CUSTOMER:') === 0 || stripos($rawKey, 'DTHC-') === 0) {
        $loginKey    = $customerKey;
        $isStoreScan = false;
    }

    // ================================================================
    // WORKER FLOW: DTHW- / DTH-WORKER: / worker_code / SĐT / identity_code
    // Không cần Telegram. Tự động on_shift sau khi nhận dạng.
    // ================================================================
    if ($isStoreScan === null && table_exists($pdo, 'worker_profiles')) {
        $workerKey = $rawKey;
        if (stripos($rawKey, 'DTH-WORKER:') === 0) {
            $workerKey = trim(substr($rawKey, strlen('DTH-WORKER:')));
        }

        $workerProfile = null;
        $candidates    = [
            ['worker_code = ?',   $workerKey],
            ['phone = ?',         digits_only($workerKey)],
            ['identity_code = ?', $workerKey],
        ];
        if (is_numeric($workerKey) && (int)$workerKey > 0) {
            $candidates[] = ['telegram_user_id = ?', (int)$workerKey];
        }

        foreach ($candidates as [$cond, $val]) {
            if ($val === '' || $val === 0) {
                continue;
            }
            $stmt = $pdo->prepare("SELECT * FROM worker_profiles WHERE {$cond} LIMIT 1");
            $stmt->execute([$val]);
            $row = $stmt->fetch();
            if ($row) {
                $workerProfile = $row;
                break;
            }
        }

        if ($workerProfile) {
            if ((int)($workerProfile['is_active'] ?? 1) !== 1) {
                json_out(['status' => 'error', 'message' => 'Tài khoản thợ đang bị khoá. Liên hệ quản trị viên.']);
            }

            $workerId = (int)$workerProfile['telegram_user_id'];

            // Tạo mobile_sessions token (90 ngày)
            $workerToken = '';
            if (table_exists($pdo, 'mobile_sessions')) {
                $workerToken = 'DTHM' . strtoupper(bin2hex(random_bytes(32)));
                $expires     = date('Y-m-d H:i:s', strtotime('+90 days'));
                insert_compat($pdo, 'mobile_sessions', [
                    'token'        => $workerToken,
                    'type'         => 'worker',
                    'user_id'      => $workerId,
                    'ip_address'   => client_ip(),
                    'user_agent'   => clean_string((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 300),
                    'worker_phone' => (string)($workerProfile['phone'] ?? ''),
                    'expires_at'   => $expires,
                ], ['created_at' => 'NOW()']);
            }

            // Bật ca làm việc NGAY (on_shift) — bỏ qua mọi bước trung gian
            if (column_exists($pdo, 'worker_profiles', 'shift_status')) {
                update_compat(
                    $pdo,
                    'worker_profiles',
                    ['shift_status' => 'on_shift'],
                    'telegram_user_id = ?',
                    [$workerId],
                    ['updated_at' => 'NOW()']
                );
            }

            return [
                'status' => 'success',
                'data'   => [
                    'type'         => 'worker',
                    'worker_id'    => $workerId,
                    'name'         => (string)($workerProfile['telegram_name'] ?? 'Thợ'),
                    'phone'        => (string)($workerProfile['phone'] ?? ''),
                    'worker_code'  => (string)($workerProfile['worker_code'] ?? ''),
                    'role'         => (string)($workerProfile['role'] ?? 'worker'),
                    'is_admin'     => (int)($workerProfile['is_admin'] ?? 0),
                    'shift_status' => 'on_shift',
                    'token'        => $workerToken,
                    'login_key'    => $rawKey,
                ],
            ];
        }
    }

    // Store check
    if ($isStoreScan !== false && table_exists($pdo, 'marketplace_stores')) {
        $stmtStore = $pdo->prepare('SELECT * FROM marketplace_stores WHERE login_key = ? LIMIT 1');
        $stmtStore->execute([$loginKey]);
        $store = $stmtStore->fetch();
        if ($store) {
            if ($store['status'] !== 'active') {
                json_out(['status' => 'error', 'message' => 'Cửa hàng của bạn đang chờ duyệt hoặc đã bị khóa. Vui lòng liên hệ quản trị viên.']);
            }
            return [
                'status' => 'success',
                'data'   => [
                    'type'          => 'store',
                    'id'            => (int)$store['id'],
                    'store_name'    => $store['store_name'],
                    'owner_name'    => $store['owner_name'],
                    'login_key'     => $loginKey,
                    'is_bot_linked' => !empty($store['vendor_telegram_chat_id']),
                ],
            ];
        }
    }

    // User check
    if ($isStoreScan !== true) {
        $stmtUser = $pdo->prepare('SELECT * FROM users WHERE login_key = ? LIMIT 1');
        $stmtUser->execute([$loginKey]);
        $user = $stmtUser->fetch();
        if ($user) {
            if ((int)$user['is_active'] === 0) {
                json_out(['status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa do vi phạm hoặc lỗi hệ thống.']);
            }
            return [
                'status' => 'success',
                'data'   => [
                    'type'           => 'user',
                    'id'             => (int)$user['id'],
                    'fullname'       => $user['fullname'],
                    'role'           => $user['role'],
                    'member_rank'    => $user['member_rank'],
                    'loyalty_points' => (int)$user['loyalty_points'],
                    'lucky_spins'    => (int)($user['lucky_spins'] ?? 0),
                    'login_key'      => $loginKey,
                    'qr_image_url'   => qr_image_url_for_payload(customer_qr_payload($loginKey), 160),
                ],
            ];
        }
    }

    return ['status' => 'error', 'message' => 'Lỗi: Không tìm thấy tài khoản. Có thể mã QR không hợp lệ, bị sai số hoặc tài khoản đã bị xóa.'];
}

function login_email_password_action(PDO $pdo, array $input): array
{
    $email = clean_string($input['email'] ?? '', 150);
    $password = (string)($input['password'] ?? '');
    
    if ($email === '' || $password === '') {
        return ['status' => 'error', 'message' => 'Vui lòng điền đầy đủ Email và Mật khẩu.'];
    }
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        return ['status' => 'error', 'message' => 'Email hoặc Mật khẩu không chính xác.'];
    }
    
    if ((int)$user['is_active'] === 0) {
        return ['status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.'];
    }
    
    $role = (string)$user['role'];
    $loginKey = (string)$user['login_key'];
    if ($loginKey === '') {
        $loginKey = customer_generate_login_key($pdo);
        $pdo->prepare('UPDATE users SET login_key = ? WHERE id = ?')->execute([$loginKey, $user['id']]);
    }
    
    if ($role === 'seller') {
        $stmtStore = $pdo->prepare('SELECT id, store_name, owner_name, login_key, status FROM marketplace_stores WHERE email = ? OR phone = ? ORDER BY id DESC LIMIT 1');
        $stmtStore->execute([$user['email'], $user['phone']]);
        $store = $stmtStore->fetch();
        if ($store) {
            if ($store['status'] !== 'active') {
                return ['status' => 'error', 'message' => 'Cửa hàng liên kết đang chờ duyệt hoặc bị khóa.'];
            }
            $storeKey = $store['login_key'];
            if (empty($storeKey)) {
                $storeKey = 'DTHS-' . strtoupper(bin2hex(random_bytes(6)));
                $pdo->prepare('UPDATE marketplace_stores SET login_key = ? WHERE id = ?')->execute([$storeKey, $store['id']]);
            }
            return [
                'status' => 'success',
                'data' => [
                    'type' => 'store',
                    'id' => (int)$store['id'],
                    'store_name' => $store['store_name'],
                    'owner_name' => $store['owner_name'],
                    'login_key' => $storeKey
                ]
            ];
        } else {
            return ['status' => 'error', 'message' => 'Không tìm thấy thông tin cửa hàng liên kết với tài khoản người bán này.'];
        }
    }
    
    return [
        'status' => 'success',
        'data' => [
            'type' => 'user',
            'id' => (int)$user['id'],
            'fullname' => $user['fullname'],
            'role' => $user['role'],
            'login_key' => $loginKey,
            'member_rank' => $user['member_rank'] ?? 'Thành viên',
            'loyalty_points' => (int)($user['loyalty_points'] ?? 0),
            'qr_image_url' => qr_image_url_for_payload(customer_qr_payload($loginKey), 160)
        ]
    ];
}
