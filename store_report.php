<?php
require 'api_master.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    die("Token không hợp lệ.");
}

$stmt = $pdo->prepare("SELECT * FROM marketplace_stores WHERE report_token = ? OR login_key = ? LIMIT 1");
$stmt->execute([$token, $token]);
$store = $stmt->fetch();

if (!$store) {
    die("Cửa hàng không tồn tại hoặc token sai.");
}

$storeId = $store['id'];
$storeName = $store['store_name'];
$mst = $store['tax_code'];
$phone = $store['phone'];

// Start of current month to end of current month
$firstDay = date('Y-m-01 00:00:00');
$lastDay = date('Y-m-t 23:59:59');

$sql = "SELECT * FROM marketplace_orders WHERE store_id = ? AND created_at >= ? AND created_at <= ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$storeId, $firstDay, $lastDay]);
$orders = $stmt->fetchAll();

$totalSales = 0;
foreach ($orders as $o) {
    if (in_array($o['status'], ['completed', 'paid', 'confirmed', 'pending'])) {
        $totalSales += $o['total_amount'];
    }
}

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Bao_cao_' . $mst . '_' . date('Y_m') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Mã Đơn', 'Khách hàng', 'SĐT', 'Địa chỉ', 'Ngày tạo', 'Trạng thái', 'Tổng tiền (VND)']);
    foreach ($orders as $o) {
        fputcsv($output, [$o['id'], $o['customer_name'] ?? 'Khách', $o['customer_phone'], $o['customer_address'], $o['created_at'], $o['status'], $o['total_amount']]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Báo cáo doanh thu - <?= htmlspecialchars($storeName) ?></title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 20px; }
        .card { background: #fff; max-width: 800px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #dc2626; margin-top: 0; }
        .info { margin-bottom: 30px; line-height: 1.6; }
        .info b { display: inline-block; width: 120px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
        th { background: #f9fafb; font-weight: bold; }
        .summary { font-size: 20px; margin-top: 20px; text-align: right; }
        .btn { display: inline-block; padding: 10px 20px; background: #10b981; color: #fff; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Báo cáo bán hàng chi tiết</h1>
        <div class="info">
            <div><b>Tên cửa hàng:</b> <?= htmlspecialchars($storeName) ?></div>
            <div><b>Mã số thuế:</b> <?= htmlspecialchars($mst) ?></div>
            <div><b>Số điện thoại:</b> <?= htmlspecialchars($phone) ?></div>
            <div><b>Kỳ báo cáo:</b> <?= date('01/m/Y') ?> - <?= date('t/m/Y') ?></div>
        </div>

        <a href="?token=<?= urlencode($token) ?>&download=csv" class="btn">Tải xuống CSV</a>
        <button class="btn" style="background:#3b82f6" onclick="window.print()">In báo cáo (PDF)</button>

        <table>
            <thead>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách hàng</th>
                    <th>Ngày mua</th>
                    <th>Trạng thái</th>
                    <th>Tổng tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="5" style="text-align:center">Chưa có giao dịch trong tháng này.</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['customer_phone'] ?? '') ?></td>
                    <td><?= $o['created_at'] ?></td>
                    <td><?= htmlspecialchars($o['status']) ?></td>
                    <td><b><?= number_format($o['total_amount']) ?> đ</b></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary">
            Tổng giao dịch trong kỳ: <b style="color:#dc2626"><?= number_format($totalSales) ?> VND</b>
        </div>
    </div>
</body>
</html>
