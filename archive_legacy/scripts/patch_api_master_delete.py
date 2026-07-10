import sys
with open('c:/Users/pcpv/OneDrive/Desktop/DTH/api_master.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()
insert_idx = -1
for i, line in enumerate(lines):
    if 'case \'admin_set_worker_pin\':' in line:
        insert_idx = i
        break
if insert_idx != -1:
    code = '''    case 'admin_delete_worker':
        admin_require($pdo, $input);
        $phone = digits_only((string)($input['phone'] ?? ''));
        if (!$phone) json_out(['status' => 'error', 'message' => 'Thiếu số điện thoại.']);
        $stmt = $pdo->prepare('UPDATE worker_profiles SET is_active = 0 WHERE phone = ?');
        $stmt->execute([$phone]);
        if ($stmt->rowCount() > 0) {
            json_out(['status' => 'success', 'message' => 'Đã vô hiệu hóa tài khoản thợ thành công.']);
        } else {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy thợ.']);
        }

'''
    if "case 'admin_delete_worker':" not in "".join(lines):
        lines.insert(insert_idx, code)
        with open('c:/Users/pcpv/OneDrive/Desktop/DTH/api_master.php', 'w', encoding='utf-8') as f:
            f.writelines(lines)
        print('Added admin_delete_worker')
    else:
        print('Already exists')
else:
    print('Could not find insert position')
