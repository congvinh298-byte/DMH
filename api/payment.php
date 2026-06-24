<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/core.php';

/**
 * Payment gateway integration (VNPAY / MOMO / VietQR)
 * Stubs ready for real credentials.
 */

$gateway = clean_string(request_data()['gateway'] ?? '', 20);
$action = clean_string(request_data()['action'] ?? '', 20);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action !== 'return' && $action !== 'ipn') {
    json_out(['status' => 'error', 'message' => 'POST required'], 405);
}

function create_vnpay_url(int $orderId, int $amount, string $orderInfo): string
{
    $vnp_TmnCode = app_env('VNPAY_TMN_CODE', '');
    $vnp_HashSecret = app_env('VNPAY_HASH_SECRET', '');
    $vnp_Url = app_env('VNPAY_API_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
    $vnp_ReturnUrl = app_env('VNPAY_RETURN_URL', 'https://dienmayhieu.com/?payment=vnpay_return');

    if ($vnp_TmnCode === '' || $vnp_HashSecret === '') {
        throw new RuntimeException('VNPAY chưa được cấu hình.');
    }

    $vnp_TxnRef = str_pad((string)$orderId, 8, '0', STR_PAD_LEFT);
    $vnp_OrderInfo = $orderInfo;
    $vnp_Amount = $amount * 100; // VND cents
    $vnp_Locale = 'vn';
    $vnp_BankCode = '';
    $vnp_IpAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $vnp_CreateDate = date('YmdHis');

    $inputData = [
        "vnp_Version" => "2.1.0",
        "vnp_TmnCode" => $vnp_TmnCode,
        "vnp_Amount" => $vnp_Amount,
        "vnp_Command" => "pay",
        "vnp_CreateDate" => $vnp_CreateDate,
        "vnp_CurrCode" => "VND",
        "vnp_IpAddr" => $vnp_IpAddr,
        "vnp_Locale" => $vnp_Locale,
        "vnp_OrderInfo" => $vnp_OrderInfo,
        "vnp_ReturnUrl" => $vnp_ReturnUrl,
        "vnp_TxnRef" => $vnp_TxnRef,
    ];

    if ($vnp_BankCode !== '') {
        $inputData['vnp_BankCode'] = $vnp_BankCode;
    }

    ksort($inputData);
    $query = '';
    $hashdata = '';
    $i = 0;
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }

    $vnp_SecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    $vnp_Url .= "?" . $query . 'vnp_SecureHash=' . $vnp_SecureHash;

    return $vnp_Url;
}

function verify_vnpay_return(): array
{
    $vnp_HashSecret = app_env('VNPAY_HASH_SECRET', '');
    $inputData = [];
    foreach ($_GET as $key => $value) {
        if (substr($key, 0, 4) == "vnp_") {
            $inputData[$key] = $value;
        }
    }

    $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
    unset($inputData['vnp_SecureHash']);
    unset($inputData['vnp_SecureHashType']);

    ksort($inputData);
    $hashData = '';
    $i = 0;
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashData .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
    }

    $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
    $valid = $secureHash === $vnp_SecureHash;
    return ['valid' => $valid, 'data' => $inputData];
}

try {
    if ($gateway === 'vnpay') {
        if ($action === 'create') {
            $orderId = (int)(request_data()['order_id'] ?? 0);
            $amount = (int)(request_data()['amount'] ?? 0);
            $orderInfo = clean_string(request_data()['order_info'] ?? 'Thanh toan don hang', 255);
            if ($orderId <= 0 || $amount <= 0) {
                json_out(['status' => 'error', 'message' => 'order_id và amount không hợp lệ'], 400);
            }
            $url = create_vnpay_url($orderId, $amount, $orderInfo);
            json_out(['status' => 'ok', 'payment_url' => $url]);
        }

        if ($action === 'return' || $action === 'ipn') {
            $verify = verify_vnpay_return();
            $data = $verify['data'];
            $success = $verify['valid'] && ($data['vnp_ResponseCode'] ?? '99') === '00';
            $orderId = isset($data['vnp_TxnRef']) ? (int)ltrim($data['vnp_TxnRef'], '0') : 0;
            $amount = isset($data['vnp_Amount']) ? (int)$data['vnp_Amount'] / 100 : 0;

            // Cập nhật trạng thái đơn hàng
            if ($success && $orderId > 0) {
                $pdo = pdo();
                $stmt = $pdo->prepare('UPDATE orders SET payment_status = ?, paid_at = NOW() WHERE id = ?');
                $stmt->execute(['paid', $orderId]);
            }

            if ($action === 'ipn') {
                echo $success ? 'OK' : 'FAIL';
                exit;
            }

            json_out(['status' => $success ? 'ok' : 'error', 'order_id' => $orderId, 'amount' => $amount, 'message' => $success ? 'Thanh toán thành công' : 'Thanh toán thất bại']);
        }
    }

    if ($gateway === 'momo') {
        // MOMO integration placeholder
        json_out(['status' => 'error', 'message' => 'MOMO cần được cấu hình partner code, access key và secret key.']);
    }

    json_out(['status' => 'error', 'message' => "Unknown gateway/action: $gateway/$action"]);
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
