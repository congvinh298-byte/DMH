"""
Patch api/users.php: Replace verify_login_key_action with worker-aware version.
Run from project root: python scripts/patch_verify_login.py
"""
import re, sys, os

TARGET = os.path.join(os.path.dirname(__file__), '..', 'api', 'users.php')

with open(TARGET, 'rb') as f:
    raw = f.read()

# Detect line ending
eol = b'\r\n' if b'\r\n' in raw else b'\n'
content = raw.decode('utf-8')

NEW_FUNC = r"""function verify_login_key_action(PDO $pdo, array $input): array
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
}"""

# Find function boundaries using regex
pattern = r'function verify_login_key_action\(PDO \$pdo, array \$input\): array\s*\{.*?\n\}'
match = re.search(pattern, content, re.DOTALL)

if not match:
    print("ERROR: Could not find verify_login_key_action function!")
    sys.exit(1)

print(f"Found function at chars {match.start()}-{match.end()}")
print(f"Original length: {match.end()-match.start()} chars")

new_content = content[:match.start()] + NEW_FUNC + content[match.end():]

# Preserve original line endings
if eol == b'\r\n':
    new_content = new_content.replace('\r\n', '\n').replace('\n', '\r\n')

with open(TARGET, 'wb') as f:
    f.write(new_content.encode('utf-8'))

print(f"SUCCESS: Patched {TARGET}")
print(f"New file size: {len(new_content)} chars")
