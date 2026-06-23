<?php
// Module: webhooks

function verify_sepay_webhook()
{
    $expected = app_env('SEPAY_API_KEY', '');
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($expected === '' || (!hash_equals('Apikey ' . $expected, $authorization) && !hash_equals('Bearer ' . $expected, $authorization))) {
        json_out(['success' => false, 'message' => 'Invalid SePay authorization.'], 401);
    }
}

function momo_ipn_signature(array $payload): string
{
    $raw = 'accessKey=' . app_env('MOMO_ACCESS_KEY', '');
    foreach (['amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'] as $key) {
        $raw .= '&' . $key . '=' . (string)($payload[$key] ?? '');
    }
    return hash_hmac('sha256', $raw, app_env('MOMO_SECRET_KEY', ''));
}

function handle_momo_worker_payment()
{
    if (!momo_payment_configured()) {
        json_out(['status' => 'error', 'message' => 'MoMo merchant chua duoc cau hinh.'], 503);
    }
    $workerId = (int)digits_only($_GET['worker_id'] ?? '');
    $token = clean_string($_GET['token'] ?? '', 200);
    if ($workerId <= 0 || $token === '' || !hash_equals(momo_worker_payment_signature($workerId), $token)) {
        json_out(['status' => 'error', 'message' => 'Lien ket thanh toan MoMo khong hop le.'], 403);
    }

    $pdo = pdo();
    $amount = worker_fee_debt($pdo, $workerId);
    if ($amount <= 0) {
        json_out(['status' => 'success', 'message' => 'Tho khong con no phi nen tang.']);
    }

    $partnerCode = trim(app_env('MOMO_PARTNER_CODE', ''));
    $accessKey = trim(app_env('MOMO_ACCESS_KEY', ''));
    $secretKey = app_env('MOMO_SECRET_KEY', '');
    $requestType = trim(app_env('MOMO_REQUEST_TYPE', 'captureWallet')) ?: 'captureWallet';
    $endpoint = trim(app_env('MOMO_CREATE_ENDPOINT', 'https://payment.momo.vn/v2/gateway/api/create'));
    $orderId = worker_payment_code($workerId) . '-' . date('YmdHis') . '-' . random_int(100, 999);
    $requestId = $orderId;
    $orderInfo = 'Phi nen tang ' . worker_payment_code($workerId);
    $redirectUrl = trim(app_env('MOMO_REDIRECT_URL', app_public_url() . '/?payment=momo'));
    $ipnUrl = app_public_url() . '/api_master.php?action=momo_ipn';
    $extraData = base64_encode(json_encode(['worker_id' => $workerId], JSON_UNESCAPED_SLASHES) ?: '{}');
    $rawSignature = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&ipnUrl={$ipnUrl}&orderId={$orderId}"
        . "&orderInfo={$orderInfo}&partnerCode={$partnerCode}&redirectUrl={$redirectUrl}&requestId={$requestId}&requestType={$requestType}";
    $payload = [
        'partnerCode' => $partnerCode,
        'requestType' => $requestType,
        'ipnUrl' => $ipnUrl,
        'redirectUrl' => $redirectUrl,
        'orderId' => $orderId,
        'amount' => $amount,
        'orderInfo' => $orderInfo,
        'requestId' => $requestId,
        'extraData' => $extraData,
        'lang' => 'vi',
        'autoCapture' => true,
        'signature' => hash_hmac('sha256', $rawSignature, $secretKey),
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 35,
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $response = json_decode((string)$raw, true);
    $payUrl = is_array($response) ? (string)($response['payUrl'] ?? '') : '';
    $payHost = strtolower((string)parse_url($payUrl, PHP_URL_HOST));
    if ($http !== 200 || (int)($response['resultCode'] ?? -1) !== 0 || !filter_var($payUrl, FILTER_VALIDATE_URL)
        || !preg_match('/(^|\.)momo\.vn$/i', $payHost)) {
        error_log('[momo_create] HTTP=' . $http . ' ' . ($error !== '' ? $error : substr((string)$raw, 0, 500)));
        json_out(['status' => 'error', 'message' => 'Khong tao duoc lien ket MoMo luc nay.'], 502);
    }

    insert_compat($pdo, 'worker_payments', [
        'worker_id' => $workerId,
        'amount' => $amount,
        'applied_amount' => 0,
        'method' => 'momo',
        'reference_code' => $orderId,
        'status' => 'pending',
        'note' => 'MoMo payment link created; waiting for verified IPN.',
    ], ['created_at' => 'NOW()']);

    header('Location: ' . $payUrl, true, 302);
    exit;
}

function handle_momo_ipn()
{
    if (!momo_payment_configured()) {
        json_out(['status' => 'error', 'message' => 'MoMo merchant chua duoc cau hinh.'], 503);
    }
    $payload = request_data();
    $partnerCode = (string)($payload['partnerCode'] ?? '');
    $signature = strtolower((string)($payload['signature'] ?? ''));
    if ($partnerCode !== app_env('MOMO_PARTNER_CODE', '') || $signature === ''
        || !hash_equals(momo_ipn_signature($payload), $signature)) {
        json_out(['status' => 'error', 'message' => 'MoMo IPN signature khong hop le.'], 401);
    }
    $orderId = clean_string($payload['orderId'] ?? '', 150);
    $pdo = pdo();
    if ((int)($payload['resultCode'] ?? -1) !== 0) {
        $message = clean_string($payload['message'] ?? 'MoMo payment failed.', 500);
        $pdo->prepare("UPDATE worker_payments SET status = 'failed', note = ?, confirmed_at = NOW()
            WHERE reference_code = ? AND method = 'momo' AND status = 'pending'")
            ->execute([$message, $orderId]);
        http_response_code(204);
        exit;
    }

    if (!preg_match('/^DTHP(\d{5,20})-/', $orderId, $match)) {
        json_out(['status' => 'error', 'message' => 'Khong tim thay ma tho trong giao dich MoMo.'], 400);
    }
    $workerId = (int)$match[1];
    $amount = money_int($payload['amount'] ?? 0);
    $pending = $pdo->prepare("SELECT * FROM worker_payments WHERE worker_id = ? AND reference_code = ? AND method = 'momo' ORDER BY id DESC LIMIT 1");
    $pending->execute([$workerId, $orderId]);
    $payment = $pending->fetch();
    if (!$payment || (int)$payment['amount'] !== $amount) {
        json_out(['status' => 'error', 'message' => 'Giao dich MoMo khong khop yeu cau dang cho.'], 409);
    }

    $transId = clean_string($payload['transId'] ?? '', 120);
    if ($transId === '') {
        json_out(['status' => 'error', 'message' => 'MoMo IPN thieu ma giao dich.'], 400);
    }
    settle_worker_payment($pdo, $workerId, $amount, 'momo', $orderId, 'momo_ipn', 'MOMO-' . $transId);
    http_response_code(204);
    exit;
}

function handle_sepay_webhook()
{
    verify_sepay_webhook();
    $pdo = pdo();
    $payload = request_data();
    $transferType = strtolower(clean_string($payload['transferType'] ?? $payload['transfer_type'] ?? '', 30));
    if ($transferType !== '' && !in_array($transferType, ['in', 'credit', 'incoming'], true)) {
        json_out(['success' => true, 'message' => 'Outgoing transaction ignored.']);
    }
    $content = clean_string($payload['content'] ?? $payload['description'] ?? $payload['transaction_content'] ?? '', 1000);
    if (!preg_match('/DTHP\s*(\d{5,20})/i', $content, $match)) {
        json_out(['success' => true, 'message' => 'Payment code not found; transaction ignored.']);
    }
    $workerId = (int)$match[1];
    $amount = money_int($payload['transferAmount'] ?? $payload['transfer_amount'] ?? $payload['amount'] ?? 0);
    $transactionId = clean_string($payload['id'] ?? $payload['transaction_id'] ?? $payload['referenceCode'] ?? '', 120);
    $reference = clean_string($payload['referenceCode'] ?? $payload['reference_code'] ?? worker_payment_code($workerId), 150);
    $externalId = $transactionId !== '' ? 'SEPAY-' . $transactionId : 'SEPAY-' . hash('sha256', $content . '|' . $amount . '|' . ($payload['transactionDate'] ?? ''));
    $result = settle_worker_payment($pdo, $workerId, $amount, 'sepay', $reference, 'sepay_webhook', $externalId);
    json_out(['success' => true, 'result' => $result]);
}