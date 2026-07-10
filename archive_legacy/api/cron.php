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