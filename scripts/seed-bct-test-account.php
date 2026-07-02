<?php
/**
 * Script seed tai khoan test cho Bo Cong Thuong.
 *
 * Tao user:
 *   - Email:    qltmdt@moit.gov.vn  (tai khoan BCT se login)
 *   - Hoac:     qlhdtmdt@gmail.com (theo checklist)
 *   - Pass:     Admin@123
 *   - Role:     admin
 *   - Flag:     is_test_account = 1
 */

declare(strict_types=1);

$corePath = __DIR__ . '/api/core.php';
if (!is_file($corePath)) {
    $corePath = __DIR__ . '/../api/core.php';
}
require_once $corePath;

$pdo = pdo();
$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_BCRYPT);

// 1. Seed users table
$usersData = [
    [
        'email' => 'qltmdt@moit.gov.vn',
        'phone' => '0123456789',
        'fullname' => 'BCT Admin Test',
        'role' => 'admin',
        'login_key' => 'DTHA-BCT-TEST-ADMIN'
    ],
    [
        'email' => 'qlhdtmdt@gmail.com',
        'phone' => '0899988481',
        'fullname' => 'BCT Seller Test',
        'role' => 'seller',
        'login_key' => 'DTHS-BCT-TEST-SELLER'
    ],
    [
        'email' => 'khachtest@gmail.com',
        'phone' => '0899988482',
        'fullname' => 'BCT Buyer Test',
        'role' => 'buyer',
        'login_key' => 'DTHC-BCT-TEST-BUYER'
    ]
];

foreach ($usersData as $u) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$u['email']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $values = [
        'fullname' => $u['fullname'],
        'phone' => $u['phone'],
        'role' => $u['role'],
        'password_hash' => $hash,
        'login_key' => $u['login_key'],
        'is_active' => 1
    ];

    if ($existing) {
        update_compat($pdo, 'users', $values, 'id = ?', [$existing['id']]);
        echo "[UPDATE USER] {$u['email']} (role={$u['role']})\n";
    } else {
        insert_compat($pdo, 'users', $values, ['created_at' => 'NOW()']);
        echo "[CREATE USER] {$u['email']} (role={$u['role']})\n";
    }
}

// 2. Seed marketplace_stores table
$storeTax = 'BCTTEST001';
$stmtStore = $pdo->prepare('SELECT id FROM marketplace_stores WHERE tax_code = ? LIMIT 1');
$stmtStore->execute([$storeTax]);
$existingStore = $stmtStore->fetch(PDO::FETCH_ASSOC);

$storeValues = [
    'phone' => '0899988481',
    'tax_code' => $storeTax,
    'tax_code_date' => '2026-06-01',
    'tax_code_place' => 'Sở KH&ĐT Đồng Tháp',
    'owner_name' => 'BCT Seller Test',
    'email' => 'qlhdtmdt@gmail.com',
    'store_name' => 'Cửa hàng Thử nghiệm BCT',
    'address' => '166, Ấp Bình Thạnh 1, Xã Lấp Vò, Huyện Lấp Vò, Tỉnh Đồng Tháp',
    'lat' => 10.2783,
    'lng' => 105.5255,
    'store_type' => 'Dịch vụ',
    'login_key' => 'DTHS-BCT-TEST-SELLER',
    'status' => 'active',
    'trust_badge' => 'Cửa Hàng Thử Nghiệm'
];

if ($existingStore) {
    update_compat($pdo, 'marketplace_stores', $storeValues, 'id = ?', [$existingStore['id']], [
        'updated_at' => 'NOW()'
    ]);
    echo "[UPDATE STORE] Cửa hàng Thử nghiệm BCT (tax_code={$storeTax})\n";
} else {
    insert_compat($pdo, 'marketplace_stores', $storeValues, [
        'created_at' => 'NOW()',
        'updated_at' => 'NOW()'
    ]);
    echo "[CREATE STORE] Cửa hàng Thử nghiệm BCT (tax_code={$storeTax})\n";
}

echo "\n=== HOAN TAT SEEDING ===\n";
echo "1. Dang nhap Admin: https://dienmayhieu.com/admin/login.php (qltmdt@moit.gov.vn / Admin@123)\n";
echo "2. Dang nhap Seller: qlhdtmdt@gmail.com / Admin@123 (Chuyen huong den admin_xxx.php)\n";
echo "3. Dang nhap Buyer: khachtest@gmail.com / Admin@123\n";
