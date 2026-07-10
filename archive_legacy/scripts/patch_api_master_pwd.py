import sys
with open('c:/Users/pcpv/OneDrive/Desktop/DTH/api_master.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

insert_idx = -1
for i, line in enumerate(lines):
    if 'case \'admin_register_worker\':' in line:
        insert_idx = i
        break

if insert_idx != -1:
    code = '''    case 'admin_set_worker_pin':
        admin_require($pdo, $input);
        $phone = digits_only((string)($input['phone'] ?? ''));
        $pin = trim($input['pin'] ?? '');
        if (!$phone || !$pin) json_out(['status' => 'error', 'message' => 'Thiếu số điện thoại hoặc mật khẩu.']);
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE worker_profiles SET pin_hash = ? WHERE phone = ? OR telegram_username = ?');
        $stmt->execute([$hash, $phone, $phone]);
        if ($stmt->rowCount() > 0) {
            json_out(['status' => 'success', 'message' => 'Cấp mật khẩu thành công.']);
        } else {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy thợ.']);
        }

    case 'worker_change_pin':
        $token = trim($input['token'] ?? '');
        if (!$token) json_out(['status' => 'error', 'message' => 'Thiếu token.']);
        $stmt = $pdo->prepare('SELECT worker_id FROM mobile_sessions WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$token]);
        $workerId = $stmt->fetchColumn();
        if (!$workerId) json_out(['status' => 'error', 'message' => 'Phiên đã hết hạn.']);
        
        $old_pin = trim($input['old_pin'] ?? '');
        $new_pin = trim($input['new_pin'] ?? '');
        if (strlen($new_pin) < 4) json_out(['status' => 'error', 'message' => 'Mật khẩu mới quá ngắn.']);
        
        $stmt = $pdo->prepare('SELECT pin_hash FROM worker_profiles WHERE telegram_user_id = ?');
        $stmt->execute([$workerId]);
        $hash = (string)$stmt->fetchColumn();
        if ($hash && !password_verify($old_pin, $hash)) {
            json_out(['status' => 'error', 'message' => 'Mật khẩu cũ không chính xác.']);
        }
        $newHash = password_hash($new_pin, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE worker_profiles SET pin_hash = ? WHERE telegram_user_id = ?')->execute([$newHash, $workerId]);
        json_out(['status' => 'success', 'message' => 'Đổi mật khẩu thành công.']);
'''
    lines.insert(insert_idx, code)
    with open('c:/Users/pcpv/OneDrive/Desktop/DTH/api_master.php', 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print('Inserted successfully')
else:
    print('Could not find insertion point')
