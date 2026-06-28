<?php
header("Access-Control-Allow-Origin: https://dienmayhieu.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['message'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing message"]);
    exit();
}

$userMessage = $input['message'];
$sessionId = $input['session_id'] ?? session_id();
if (!$sessionId) {
    $sessionId = bin2hex(random_bytes(16));
}

// Read .env
$envPath = __DIR__ . '/../.env';
$env = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim($val, " \"'");
        }
    }
}

function openclaw_env($env, $key, $default = '')
{
    $value = $env[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return (string)$default;
    }
    return (string)$value;
}

// Database Connection
$host = openclaw_env($env, 'DB_HOST', 'localhost');
$db = openclaw_env($env, 'DB_NAME', '');
$user = openclaw_env($env, 'DB_USER', '');
$pass = openclaw_env($env, 'DB_PASS', '');

$pdo = null;
$knowledgeBase = "Thông tin bảng giá cơ bản:\n";
if ($db !== '' && $user !== '') {
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch products and services
    $stmt = $pdo->query("SELECT name, price, description FROM marketplace_products WHERE status = 'active' LIMIT 50");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $price = number_format($row['price'], 0, ',', '.');
        $knowledgeBase .= "- " . $row['name'] . ": " . $price . " VNĐ. " . $row['description'] . "\n";
    }
    
} catch (PDOException $e) {
    error_log("DB Connection Error in OpenClaw Gateway: " . $e->getMessage());
}
} else {
    error_log("DB Connection Error in OpenClaw Gateway: missing DB_NAME or DB_USER.");
}

// Fallback manual knowledge if DB empty or specific ones needed:
$knowledgeBase .= "
Quy trình làm việc của đội thợ: Tiếp nhận yêu cầu -> Khảo sát/Báo giá -> Thực hiện -> Bàn giao & Thanh toán.
Giá dịch vụ quay phim: 500k cho gói quay trao nhẫn cưới.
Liên hệ vận hành: 0979.553.289 (Công ty TNHH MTV ĐIỆN MÁY HIẾU)
";

$systemPrompt = "
Bạn là OpenClaw Intelligence, trợ lý chăm sóc khách hàng (Customer Success AI) của dienmayhieu.com.
Giọng điệu: Thân thiện, tận tình, chuyên nghiệp và am hiểu sâu sắc về lĩnh vực điện máy/dịch vụ cưới hỏi, gọi xe, gọi thợ tại địa phương (Lấp Vò, Đồng Tháp).
Hãy trả lời câu hỏi của khách hàng dựa trên dữ liệu sau:
$knowledgeBase

NẾU người dùng hỏi đặt đơn, xem giá dịch vụ, hoặc gọi thợ, ngoài câu trả lời, hãy đề xuất các NÚT HÀNH ĐỘNG (Action Buttons) ở dạng JSON.
Phản hồi của bạn CHỈ được phép là một đối tượng JSON hợp lệ (không chứa markdown ```json), có cấu trúc sau:
{
  \"text\": \"Câu trả lời của bạn gửi cho khách hàng\",
  \"actions\": [
    { \"label\": \"Đặt đơn ngay\", \"type\": \"link\", \"value\": \"/dat-don\" },
    { \"label\": \"Xem giá Quay phim\", \"type\": \"link\", \"value\": \"/#goi-tho\" },
    { \"label\": \"Gọi thợ trực tiếp\", \"type\": \"call\", \"value\": \"tel:0979553289\" }
  ]
}
Nếu không cần nút hành động, để mảng actions rỗng [].
Luôn đưa ra giải pháp cụ thể ngay trong cuộc hội thoại. Không trả lời chung chung.
";

// Use Gemini as the OpenClaw AI backend for now
$geminiApiKey = $env['GEMINI_API_KEY'] ?? '';
$aiResponseText = "";
$actions = [];

if ($geminiApiKey) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $geminiApiKey;
    $data = [
        "contents" => [
            ["role" => "user", "parts" => [["text" => $systemPrompt . "\n\nCâu hỏi của khách hàng: " . $userMessage]]]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json"
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $resObj = json_decode($response, true);
        $candidateText = $resObj['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $aiJson = json_decode($candidateText, true);
        if ($aiJson && isset($aiJson['text'])) {
            $aiResponseText = $aiJson['text'];
            $actions = $aiJson['actions'] ?? [];
        } else {
            $aiResponseText = $candidateText; // Fallback
        }
    } else {
        $aiResponseText = "Xin lỗi, hệ thống AI đang bảo trì. Vui lòng thử lại sau.";
    }
} else {
    $aiResponseText = "Xin lỗi, OpenClaw AI chưa được cấu hình API Key.";
}

// Log to Database
if ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO openclaw_chat_logs (session_id, user_message, ai_response, actions_provided) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sessionId, $userMessage, $aiResponseText, json_encode($actions, JSON_UNESCAPED_UNICODE)]);
    } catch (PDOException $e) {
        error_log("Failed to log chat: " . $e->getMessage());
    }
}

echo json_encode([
    "status" => "success",
    "data" => [
        "text" => $aiResponseText,
        "actions" => $actions
    ]
], JSON_UNESCAPED_UNICODE);
