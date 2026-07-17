<?php
/**
 * Zalo Open API Webhook endpoint
 * Nhận các sự kiện từ Zalo Mini App / Open API và ghi log.
 */

header('Content-Type: application/json; charset=utf-8');

// Hỗ trợ GET để anh Vinh có thể kiểm tra bằng trình duyệt
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'msg' => 'Zalo webhook endpoint is active. Only POST requests from Zalo are processed.',
        'registered_url' => 'https://dienmayhieu.com/api/zalo_webhook.php',
    ]);
    exit;
}

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'msg' => 'Method not allowed']);
    exit;
}

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

if (!$event || !is_array($event)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'msg' => 'Invalid payload']);
    exit;
}

// Log webhook để debug (giới hạn kích thước)
$logDir = __DIR__ . '/../storage/private';
if (!is_dir($logDir)) {
    $logDir = __DIR__ . '/..';
}
$logFile = $logDir . '/zalo_webhook.log';
$logEntry = date('Y-m-d H:i:s') . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' - ' . substr($payload, 0, 2000) . PHP_EOL;
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// TODO: Xử lý các sự kiện Zalo khi cần
$eventName = $event['event_name'] ?? '';
$appId = $event['app_id'] ?? '';

switch ($eventName) {
    case 'user_submit_info':
    case 'user_send_message':
        // Lưu thông tin người dùng nếu cần
        break;
    case 'oa_send_message_status':
        // Cập nhật trạng thái tin nhắn
        break;
    default:
        // Không xử lý gì thêm
        break;
}

// Trả về 200 OK để Zalo biết đã nhận
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'msg' => 'Webhook received',
    'event' => $eventName,
    'app_id' => $appId,
]);
