<?php
// Module: ai



function gemini_fallback_reply(string $message, array $input = []): string
{
    $publicPrice = clean_string($input['public_price'] ?? '', 120);
    $service = clean_string($input['selected_service'] ?? $input['service_type'] ?? '', 150);
    $line = $service !== '' ? "Dịch vụ đang chọn: {$service}." : 'Bạn có thể chọn dịch vụ trong bảng giá trước.';
    $price = $publicPrice !== '' ? " Giá tham khảo: {$publicPrice}." : '';
    return "{$line}{$price} Giá đã gồm VAT; vật tư/linh kiện phát sinh sẽ được báo riêng trước khi làm. Để chốt nhanh, vui lòng gửi form Gọi Thợ hoặc gọi 0979.553.289.";
}

function ai_strip_thinking(string $text): string
{
    $cleaned = preg_replace('/<\|channel\>thought\s*.*?<channel\|>/is', '', $text);
    $text = is_string($cleaned) ? $cleaned : $text;
    $cleaned = preg_replace('/<think>.*?<\/think>/is', '', $text);
    $text = is_string($cleaned) ? $cleaned : $text;
    $cleaned = preg_replace('/^\s*<\|channel\>final\s*/i', '', $text);
    return trim(is_string($cleaned) ? $cleaned : $text);
}

function ollama_quote_reply(string $prompt): string
{
    if (!app_bool_env('OLLAMA_ENABLED', true)) {
        return '';
    }

    $baseUrl = rtrim(app_env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/');
    $model = app_env('OLLAMA_MODEL', 'gemma4:12b');
    if ($baseUrl === '' || $model === '') {
        return '';
    }

    $systemPrompt = clean_string(app_env('ANH_THIEN_SYSTEM_PROMPT', ''), 2200);
    if ($systemPrompt === '') {
        $systemPrompt = 'Bạn là Anh Thiên, trợ lý tư vấn của ĐIỆN MÁY HIẾU. Trả lời tiếng Việt ngắn gọn, thực tế, lịch sự. Chỉ đưa giá theo bảng công khai, không hứa giảm giá ngoài bảng. Nếu khách muốn chốt đơn, hướng dẫn khách gửi form Gọi Thợ hoặc gọi 0979.553.289.';
    }
    $thinkingMode = strtolower(app_env('GEMMA_THINKING_LEVEL', ''));
    $think = false;
    if (in_array($thinkingMode, ['1', 'true', 'yes', 'on', 'think'], true)) {
        $think = true;
        $systemPrompt = '<|think|> ' . $systemPrompt;
    } elseif (in_array($thinkingMode, ['low', 'medium', 'high'], true)) {
        $think = $thinkingMode;
        $systemPrompt = '<|think|> ' . $systemPrompt;
    }

    $options = [
        'temperature' => (float)app_env('GEMMA_TEMPERATURE', '0.30'),
        'top_p' => (float)app_env('OLLAMA_TOP_P', '0.95'),
        'top_k' => (int)app_env('OLLAMA_TOP_K', '64'),
        'num_predict' => max(64, (int)app_env('GEMMA_MAX_OUTPUT_TOKENS', '520')),
    ];
    $numCtx = (int)app_env('OLLAMA_NUM_CTX', '0');
    if ($numCtx > 0) {
        $options['num_ctx'] = $numCtx;
    }

    $payload = [
        'model' => $model,
        'stream' => false,
        'think' => $think,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ],
        'options' => $options,
    ];
    $keepAlive = app_env('OLLAMA_KEEP_ALIVE', '10m');
    if ($keepAlive !== '') {
        $payload['keep_alive'] = $keepAlive;
    }

    $url = $baseUrl . '/api/chat';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $err = '';
    $http = 0;
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(1, (int)app_env('OLLAMA_CONNECT_TIMEOUT', '2')),
            CURLOPT_TIMEOUT => max(5, (int)app_env('OLLAMA_TIMEOUT', '60')),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => max(5, (int)app_env('OLLAMA_TIMEOUT', '60')),
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $err = $raw === false ? 'file_get_contents failed' : '';
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$header, $matches)) {
                $http = (int)$matches[1];
                break;
            }
        }
    }

    $decoded = json_decode((string)$raw, true);
    $reply = trim((string)($decoded['message']['content'] ?? $decoded['response'] ?? ''));
    if ($http === 200 && $reply !== '') {
        return ai_strip_thinking($reply);
    }

    error_log('[anh_thien_chat][ollama] HTTP=' . $http . ' model=' . $model . ' ' . ($err !== '' ? $err : substr((string)$raw, 0, 300)));
    return '';
}

function gemini_quote_reply(array $input): string
{
    $message = clean_string($input['message'] ?? '', 1000);
    if ($message === '') {
        return gemini_fallback_reply($message, $input);
    }

    $token = app_env('HF_TOKEN', '');
    $model = app_env('HF_TEXT_MODEL', 'google/gemma-2-9b-it');
    $geminiKey = app_env('GEMINI_API_KEY', '');
    $serviceType = clean_string($input['service_type'] ?? '', 150);
    $selected = clean_string($input['selected_service'] ?? '', 150);
    $publicPrice = clean_string($input['public_price'] ?? '', 120);
    $address = clean_string($input['address'] ?? '', 500);
    $prompt = "Bạn là Anh Thiên 2, trợ lí báo giá của ĐIỆN MÁY HIẾU. Trả lời tiếng Việt, ngắn gọn, thấu hiểu tâm lý khách hàng, thực tế, không hứa giảm giá ngoài bảng. "
        . "Nhấn mạnh giá công khai đã gồm VAT, vật tư/linh kiện phát sinh báo riêng trước khi làm. "
        . "Bảng tham khảo: vệ sinh máy lạnh 165.000 VND; lắp máy lạnh 1HP/1.5HP 440.000 VND; lắp máy lạnh 2HP/3HP 550.000 VND; sửa chữa điện lạnh, treo tivi, lắp máy lọc nước, lắp máy giặt, kiểm tra/sửa điện thoại 220.000 VND. "
        . "Nhóm: {$serviceType}. Dịch vụ chọn: {$selected}. Giá đang hiển thị: {$publicPrice}. Địa chỉ: {$address}. Câu hỏi khách: {$message}";

    $prompt = preg_replace('/Anh Thi.{1,12}n 2/u', 'Anh Thien 4', $prompt) ?? $prompt;
    $ollamaReply = ollama_quote_reply($prompt);
    if ($ollamaReply !== '') {
        return clean_string($ollamaReply, 1400);
    }

    if (!function_exists('curl_init')) {
        return gemini_fallback_reply($message, $input);
    }

    if ($geminiKey !== '') {
        $geminiPayload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => (float)app_env('GEMMA_TEMPERATURE', '0.30'),
                'maxOutputTokens' => (int)app_env('GEMMA_MAX_OUTPUT_TOKENS', '520'),
            ],
        ];
        $geminiModel = app_env('GEMINI_TEXT_MODEL', 'gemini-1.5-flash');
        $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($geminiModel) . ':generateContent?key=' . rawurlencode($geminiKey);
        $geminiCh = curl_init($geminiUrl);
        curl_setopt_array($geminiCh, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($geminiPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
        ]);
        $geminiRaw = curl_exec($geminiCh);
        $geminiErr = curl_error($geminiCh);
        $geminiHttp = (int)curl_getinfo($geminiCh, CURLINFO_HTTP_CODE);
        curl_close($geminiCh);

        $geminiDecoded = json_decode((string)$geminiRaw, true);
        $geminiReply = trim((string)($geminiDecoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($geminiHttp === 200 && $geminiReply !== '') {
            return clean_string($geminiReply, 1400);
        }
        error_log('[anh_thien_chat][gemini] HTTP=' . $geminiHttp . ' ' . ($geminiErr !== '' ? $geminiErr : substr((string)$geminiRaw, 0, 300)));
    }

    if ($token === '') {
        return gemini_fallback_reply($message, $input);
    }

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 360,
        'temperature' => 0.4
    ];

    $url = 'https://api-inference.huggingface.co/models/' . $model . '/v1/chat/completions';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string)$raw, true);
    $reply = '';
    if (is_array($decoded) && isset($decoded['choices'][0]['message']['content'])) {
        $reply = trim((string)$decoded['choices'][0]['message']['content']);
    }
    
    if ($http !== 200 || $reply === '') {
        error_log('[gemini_chat] HTTP=' . $http . ' ' . ($err !== '' ? $err : substr((string)$raw, 0, 300)));
        return gemini_fallback_reply($message, $input);
    }
    return clean_string($reply, 1400);
}

function dth_gemini_analyze_product($base64_image, $text_input) {
    $geminiKey = (string)app_env('GEMINI_API_KEY', '');
    if ($geminiKey === '') {
        throw new DomainException('Chua cau hinh GEMINI_API_KEY trong file .env.');
    }

    $prompt = "Bạn là Anh Thiên, một trợ lý quản lý sản phẩm. Hãy phân tích hình ảnh và/hoặc văn bản cung cấp và trích xuất thông tin sản phẩm. Trả về KẾT QUẢ ĐẦU RA là một JSON hợp lệ có cấu trúc: {\"name\": \"Tên sản phẩm (ngắn gọn)\", \"category\": \"Danh mục (Điện tử, Gia dụng, Lạnh, Lọc nước, Điện thoại, Sim, Khác)\", \"price\": Gia_ban_du_kien (số nguyên, bỏ . đ), \"stock\": 100}. Chú ý: Trả về CHỈ JSON, không giải thích gì thêm, không dùng markdown code block.\n\nThông tin người dùng cung cấp:\n" . ($text_input ?: 'Không có văn bản.');

    $parts = [['text' => $prompt]];

    if ($base64_image !== '') {
        $imgData = explode(',', $base64_image);
        $base64 = $imgData[1] ?? $imgData[0];
        $mime = 'image/jpeg';
        if (preg_match('/data:(.*?);base64/', $base64_image, $matches)) {
            $mime = $matches[1];
        }
        $parts[] = [
            'inlineData' => [
                'mimeType' => $mime,
                'data' => $base64
            ]
        ];
    }

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => $parts
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 800,
        ]
    ];

    $geminiModel = 'gemini-1.5-pro';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $geminiModel . ':generateContent?key=' . rawurlencode($geminiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 40,
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) {
        throw new DomainException('Loi ket noi Gemini API: HTTP ' . $http . ' ' . substr((string)$raw, 0, 200));
    }

    $decoded = json_decode((string)$raw, true);
    $reply = trim((string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    if ($reply === '') {
        throw new DomainException('Gemini khong tra ve ket qua.');
    }

    // Clean up markdown
    $reply = preg_replace('/^```json/i', '', $reply);
    $reply = preg_replace('/```$/', '', $reply);
    $reply = trim($reply);

    $json = json_decode($reply, true);
    if (!is_array($json)) {
        throw new DomainException('Gemini tra ve sai dinh dang JSON: ' . $reply);
    }
    return $json;
}
