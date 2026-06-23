<?php
require_once __DIR__ . '/../api_master.php';

// Assuming admin authentication is handled via session or some other method, but for this basic view we just check if it's accessed (you should add actual auth check here).
// For example, if(!isset($_SESSION['admin_logged_in'])) { die('Unauthorized'); }

$stmt = $pdo->query("SELECT * FROM openclaw_chat_logs ORDER BY id DESC LIMIT 200");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>OpenClaw AI Chat Logs</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc2626; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; font-weight: bold; color: #374151; }
        .msg-user { color: #1d4ed8; font-weight: bold; margin-bottom: 8px; }
        .msg-ai { color: #047857; margin-bottom: 8px; }
        .actions { font-size: 12px; color: #6b7280; background: #f3f4f6; padding: 4px; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Báo cáo & Giám sát: OpenClaw AI</h1>
        <p>Xem lại lịch sử trò chuyện giữa khách hàng và trợ lý AI để cải thiện Knowledge Base.</p>
        
        <table>
            <thead>
                <tr>
                    <th width="10%">ID / Thời gian</th>
                    <th width="15%">Phiên Chat</th>
                    <th width="75%">Nội dung Hội thoại</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($logs)): ?>
                    <tr><td colspan="3" style="text-align:center">Chưa có dữ liệu.</td></tr>
                <?php else: ?>
                    <?php foreach($logs as $log): ?>
                        <tr>
                            <td>
                                <b>#<?= $log['id'] ?></b><br>
                                <span style="font-size: 12px; color: #6b7280;"><?= $log['created_at'] ?></span>
                            </td>
                            <td><?= substr($log['session_id'], 0, 10) ?>...</td>
                            <td>
                                <div class="msg-user">Khách: <?= htmlspecialchars($log['user_message']) ?></div>
                                <div class="msg-ai">AI: <?= htmlspecialchars($log['ai_response']) ?></div>
                                <?php if($log['actions_provided'] && $log['actions_provided'] !== '[]'): ?>
                                    <div class="actions">Hành động AI đề xuất: <?= htmlspecialchars($log['actions_provided']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
