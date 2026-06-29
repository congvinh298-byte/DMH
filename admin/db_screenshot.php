<?php
/**
 * BCT Compliance Utility: Database Hashed Password Screenshot Helper
 * URL: /admin/db_screenshot.php?token=bct_view
 */
require_once __DIR__ . '/../api/core.php';

// Auth Check: Require token 'bct_view' or admin session
app_ensure_session();
$token = $_GET['token'] ?? '';
$isAdmin = app_admin_is_authenticated();

if ($token !== 'bct_view' && !$isAdmin) {
    http_response_code(403);
    echo "<h3>Truy cập bị từ chối</h3><p>Vui lòng đăng nhập quyền Admin hoặc cung cấp mã token hợp lệ để xem thông tin này.</p>";
    exit;
}

$pdo = pdo();
$stmt = $pdo->prepare("SELECT id, fullname, email, phone, role, password_hash, created_at FROM users WHERE email IN (?, ?, ?) ORDER BY id ASC");
$stmt->execute([
    'qltmdt@moit.gov.vn',
    'qlhdtmdt@gmail.com',
    'khachtest@gmail.com'
]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cơ sở dữ liệu - Xác thực mã hóa mật khẩu - Chợ Lấp Vò Online</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; background: #0f172a; color: #38bdf8; padding: 40px; margin: 0; }
        .container { max-width: 1000px; margin: 0 auto; background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h1 { font-size: 20px; color: #f8fafc; border-bottom: 2px solid #38bdf8; padding-bottom: 12px; margin-top: 0; }
        p { font-size: 14px; color: #94a3b8; line-height: 1.6; }
        .system-info { margin-bottom: 20px; font-size: 13px; color: #a7f3d0; background: #064e3b; padding: 12px; border-radius: 6px; border: 1px dashed #34d399; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        th, td { border: 1px solid #334155; padding: 12px 14px; text-align: left; word-break: break-all; }
        th { background: #0f172a; color: #f8fafc; font-weight: bold; }
        tr:nth-child(even) td { background: #1e293b; }
        tr:nth-child(odd) td { background: #1e293b; opacity: 0.95; }
        .hash { font-family: 'Courier New', Courier, monospace; color: #fb7185; font-weight: bold; }
        .footer-note { margin-top: 30px; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <h1>[DATABASE AUDIT] BẢNG USERS (TÀI KHOẢN KHẢO SÁT BỘ CÔNG THƯƠNG)</h1>
    <div class="system-info">
        <strong>Hệ quản trị CSDL:</strong> MySQL / MariaDB (Engine: InnoDB)<br>
        <strong>Trạng thái bảo mật:</strong> Mật khẩu người dùng được băm tự động bằng thuật toán <strong>BCRYPT</strong> bảo mật cao (PHP password_hash). Không lưu trữ mật khẩu dưới dạng văn bản rõ (plain-text).
    </div>
    <p>Dưới đây là danh sách các tài khoản thử nghiệm của Bộ Công Thương đang lưu trữ trong cơ sở dữ liệu thực tế:</p>
    
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th style="width: 150px;">Họ tên</th>
                <th style="width: 180px;">Email</th>
                <th style="width: 110px;">Số điện thoại</th>
                <th style="width: 80px;">Vai trò</th>
                <th>Mật khẩu đã mã hóa (password_hash)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #f43f5e; padding: 20px;">
                        Chưa tìm thấy dữ liệu tài khoản thử nghiệm. Vui lòng chạy tệp seed-bct-test-account.php trước!
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><?= htmlspecialchars($u['fullname']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['phone']) ?></td>
                        <td><span style="color: #fbbf24; font-weight: bold;"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td class="hash"><?= htmlspecialchars($u['password_hash']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer-note">
        Hệ sinh thái thương mại điện tử Chợ Lấp Vò Online - Thiết lập tuân thủ theo Nghị định số 52/2013/NĐ-CP
    </div>
</div>
</body>
</html>
