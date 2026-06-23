<?php
// Module: cron



function verify_cron_secret()
{
    $expected = app_env('CRON_SECRET', '');
    $actual = (string)($_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if ($expected === '' || !hash_equals($expected, $actual)) {
        json_out(['status' => 'error', 'message' => 'Invalid cron secret.'], 403);
    }
}

function cron_vendor_daily_closing(PDO $pdo): array
{
    $date = date('Y-m-d');
    $cols = column_exists($pdo, 'marketplace_stores', 'vendor_telegram_chat_id') ? 'id, store_name, vendor_telegram_chat_id' : 'id, store_name';
    $stmt = $pdo->query("SELECT {$cols} FROM marketplace_stores WHERE status = 'active'");
    $stores = $stmt->fetchAll();
    $processed = 0;
    foreach ($stores as $store) {
        $storeId = (int)$store['id'];
        
        // Skip if already closed
        $checkStmt = $pdo->prepare("SELECT id FROM store_daily_reports WHERE store_id = ? AND report_date = ? AND is_closed = 1 LIMIT 1");
        $checkStmt->execute([$storeId, $date]);
        if ($checkStmt->fetch()) continue;
        
        // Get products
        $prodStmt = $pdo->prepare("SELECT id FROM marketplace_products WHERE store_id = ?");
        $prodStmt->execute([$storeId]);
        $productIds = $prodStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $totalOrders = 0;
        $totalRevenue = 0;
        
        if (!empty($productIds)) {
            $in = str_repeat('?,', count($productIds) - 1) . '?';
            $params = array_merge([$date . ' 00:00:00', $date . ' 23:59:59'], $productIds);
            $orderStmt = $pdo->prepare("
                SELECT count(id) as total_orders, sum(total_price) as total_revenue
                FROM orders
                WHERE created_at BETWEEN ? AND ?
                AND status IN ('confirmed', 'completed', 'Dã xác nhận', 'Hoàn thành')
                AND product_id IN ($in)
            ");
            $orderStmt->execute($params);
            $stats = $orderStmt->fetch();
            $totalOrders = (int)($stats['total_orders'] ?? 0);
            $totalRevenue = (int)($stats['total_revenue'] ?? 0);
        }
        
        try {
            $pdo->exec("INSERT INTO store_daily_reports (store_id, report_date, total_orders, total_revenue, is_closed, closed_at) 
                        VALUES ($storeId, '$date', $totalOrders, $totalRevenue, 1, NOW()) 
                        ON DUPLICATE KEY UPDATE total_orders = $totalOrders, total_revenue = $totalRevenue, is_closed = 1, closed_at = NOW()");
                        
            if (!empty($store['vendor_telegram_chat_id']) && $totalOrders > 0) {
                $msg = "<b>[TỔNG KẾT NGÀY ".date('d/m/Y')."]</b>\n"
                     . "Cửa hàng: <b>".esc_html($store['store_name'])."</b>\n"
                     . "Tổng đơn: $totalOrders\n"
                     . "Doanh thu: ".fmt_money($totalRevenue)."\n\n"
                     . "<i>Hệ thống tự động chốt sổ cuối ngày. Cảm ơn bạn đã đồng hành!</i>";
                tg_send('vendor', $store['vendor_telegram_chat_id'], $msg);
            }
            $processed++;
        } catch (PDOException $e) {}
    }
    
    return ['processed' => $processed, 'date' => $date];
}

function send_daily_business_report(PDO $pdo): array
{
    $stats = admin_stats($pdo);
    $paidToday = 0;
    $platformFeeToday = 0;
    if (table_exists($pdo, 'worker_payments')) {
        $paidToday = (int)$pdo->query("SELECT COALESCE(SUM(applied_amount),0) FROM worker_payments WHERE status = 'confirmed' AND DATE(confirmed_at) = CURDATE()")->fetchColumn();
    }
    if (table_exists($pdo, 'job_pricing') && table_exists($pdo, 'job_posts')) {
        $platformFeeToday = (int)$pdo->query("SELECT COALESCE(SUM(jp.platform_fee),0)
            FROM job_pricing jp JOIN job_posts j ON j.id = jp.job_id
            WHERE DATE(j.completed_at) = CURDATE()")->fetchColumn();
    }
    $text = "<b>BAO CAO NGAY " . date('d/m/Y') . "</b>\n"
        . "Don hang: " . (int)$stats['today_orders'] . "\n"
        . "Doanh thu don hang: " . fmt_money((int)$stats['today_revenue']) . "\n"
        . "Ca goi tho hom nay: " . (int)$stats['today_jobs'] . "\n"
        . "Tong tho: " . (int)($stats['total_workers'] ?? 0) . "\n"
        . "Phi nen tang phat sinh hom nay: " . fmt_money($platformFeeToday) . "\n"
        . "Phi nen tang da thu hom nay: " . fmt_money($paidToday) . "\n"
        . "Tong no phi nen tang: " . fmt_money((int)$stats['unpaid_total']);
    $chatId = telegram_chat('sales');
    $response = $chatId !== '' ? tg_send('sales', $chatId, $text) : ['ok' => false];
    return ['sent' => !empty($response['ok']), 'stats' => $stats, 'platform_fee_today' => $platformFeeToday, 'paid_today' => $paidToday];
}