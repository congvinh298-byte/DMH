<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/core.php';

/**
 * Notification integration (SMTP Email + Telegram)
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['status' => 'error', 'message' => 'POST required'], 405);
}

$input = request_data();
$channel = clean_string($input['channel'] ?? '', 20);
$action = clean_string($input['action'] ?? '', 20);

function send_smtp_email(string $to, string $subject, string $body, bool $isHtml = true): bool
{
    $host = app_env('SMTP_HOST', '');
    $port = (int)app_env('SMTP_PORT', '587');
    $user = app_env('SMTP_USER', '');
    $pass = app_env('SMTP_PASS', '');
    $from = app_env('SMTP_FROM', $user);
    $fromName = app_env('SMTP_FROM_NAME', 'Chợ Lấp Vò Online');

    if ($host === '' || $user === '' || $pass === '') {
        throw new RuntimeException('SMTP chưa được cấu hình.');
    }

    $headers = "From: $fromName <$from>\r\n";
    $headers .= "Reply-To: $from\r\n";
    if ($isHtml) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }

    ini_set('SMTP', $host);
    ini_set('smtp_port', (string)$port);
    ini_set('sendmail_from', $from);

    return mail($to, $subject, $body, $headers);
}

function send_telegram(string $token, string $chatId, string $message): array
{
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $payload = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['http_code' => $httpCode, 'body' => json_decode($response ?: '{}', true)];
}

try {
    if ($channel === 'email') {
        $to = clean_string($input['to'] ?? '', 255);
        $subject = clean_string($input['subject'] ?? '', 255);
        $body = $input['body'] ?? '';
        $isHtml = (bool)($input['html'] ?? 1);

        if ($to === '' || $subject === '' || $body === '') {
            json_out(['status' => 'error', 'message' => 'Thiếu to/subject/body.'], 400);
        }

        $ok = send_smtp_email($to, $subject, $body, $isHtml);
        json_out(['status' => $ok ? 'ok' : 'error', 'message' => $ok ? 'Đã gửi email' : 'Gửi email thất bại']);
    }

    if ($channel === 'telegram') {
        $bot = clean_string($input['bot'] ?? 'report', 20); // report, worker, bike, drone
        $chatId = clean_string($input['chat_id'] ?? '', 50);
        $message = $input['message'] ?? '';

        if ($message === '') {
            json_out(['status' => 'error', 'message' => 'Thiếu nội dung tin nhắn.'], 400);
        }

        $tokenKey = match ($bot) {
            'worker' => 'BOT_WORKER_TOKEN',
            'bike' => 'BOT_BIKE_TOKEN',
            'drone' => 'BOT_DRONE_TOKEN',
            default => 'BOT_REPORT_TOKEN',
        };
        $chatKey = match ($bot) {
            'worker' => 'WORKER_CHAT_ID',
            'bike' => 'BIKE_CHAT_ID',
            'drone' => 'DRONE_CHAT_ID',
            default => 'BOSS_CHAT_ID',
        };

        $token = app_env($tokenKey, '');
        $defaultChatId = app_env($chatKey, '');
        $chatId = $chatId !== '' ? $chatId : $defaultChatId;

        if ($token === '' || $chatId === '') {
            json_out(['status' => 'error', 'message' => 'Telegram token hoặc chat_id chưa được cấu hình.'], 500);
        }

        $result = send_telegram($token, $chatId, $message);
        $ok = $result['http_code'] === 200 && empty($result['body']['error_code']);
        json_out(['status' => $ok ? 'ok' : 'error', 'message' => $ok ? 'Đã gửi Telegram' : 'Gửi Telegram thất bại', 'telegram_response' => $result['body']]);
    }

    if ($channel === 'order_confirmation') {
        // Gửi cả email + telegram cho đơn hàng
        $orderId = (int)($input['order_id'] ?? 0);
        if ($orderId <= 0) {
            json_out(['status' => 'error', 'message' => 'order_id không hợp lệ.'], 400);
        }

        $pdo = pdo();
        $stmt = $pdo->prepare('SELECT o.*, u.email, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            json_out(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $subject = "Xác nhận đơn hàng #{$orderId} - Chợ Lấp Vò Online";
        $body = "<h2>Cảm ơn anh/chị {$order['fullname']} đã đặt hàng!</h2>";
        $body .= "<p>Mã đơn hàng: <strong>#{$orderId}</strong></p>";
        $body .= "<p>Tổng tiền: <strong>" . number_format((float)$order['total_amount'], 0, ',', '.') . " VNĐ</strong></p>";
        $body .= "<p>Trạng thái: {$order['status']}</p>";
        $body .= "<p>Nếu cần hỗ trợ, liên hệ hotline 0979.553.289 hoặc email Congvinh298@gmail.com</p>";

        $emailOk = false;
        if (!empty($order['email'])) {
            try {
                $emailOk = send_smtp_email($order['email'], $subject, $body);
            } catch (Throwable $e) {
                $emailOk = false;
            }
        }

        $tgMessage = "🛒 Đơn hàng mới #{$orderId}\n";
        $tgMessage .= "Khách: {$order['fullname']}\n";
        $tgMessage .= "SĐT: {$order['phone']}\n";
        $tgMessage .= "Tổng: " . number_format((float)$order['total_amount'], 0, ',', '.') . " VNĐ\n";
        $tgMessage .= "Địa chỉ: {$order['shipping_address']}";

        $tgToken = app_env('BOT_REPORT_TOKEN', '');
        $tgChatId = app_env('BOSS_CHAT_ID', '');
        $tgOk = false;
        if ($tgToken !== '' && $tgChatId !== '') {
            $tgResult = send_telegram($tgToken, $tgChatId, $tgMessage);
            $tgOk = $tgResult['http_code'] === 200 && empty($tgResult['body']['error_code']);
        }

        json_out(['status' => 'ok', 'email_sent' => $emailOk, 'telegram_sent' => $tgOk]);
    }

    json_out(['status' => 'error', 'message' => "Unknown channel: $channel"]);
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
