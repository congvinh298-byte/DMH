<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/core.php';

/**
 * Shipping integration (GHN / GHTK)
 * Stubs ready for real credentials.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['status' => 'error', 'message' => 'POST required'], 405);
}

$input = request_data();
$provider = clean_string($input['provider'] ?? app_env('SHIPPING_PROVIDER', 'GHN'), 10);
$action = clean_string($input['action'] ?? '', 20);

function ghn_request(string $endpoint, array $payload, string $method = 'POST'): array
{
    $token = app_env('GHN_API_TOKEN', '');
    $shopId = app_env('GHN_SHOP_ID', '');
    $baseUrl = rtrim(app_env('GHN_API_URL', 'https://dev-online-gateway.ghn.vn/shiip/public-api'), '/');

    if ($token === '') {
        throw new RuntimeException('GHN API token chưa được cấu hình.');
    }

    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Token: ' . $token,
        'ShopId: ' . $shopId,
    ]);
    if ($method !== 'GET' && !empty($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response ?: '{}', true);
    return ['http_code' => $httpCode, 'data' => $data];
}

function ghtk_request(string $endpoint, array $payload, string $method = 'POST'): array
{
    $token = app_env('GHTK_API_TOKEN', '');
    $baseUrl = rtrim(app_env('GHTK_API_URL', 'https://services.giaohangtietkiem.vn/services'), '/');

    if ($token === '') {
        throw new RuntimeException('GHTK API token chưa được cấu hình.');
    }

    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Token: ' . $token,
    ]);
    if ($method !== 'GET' && !empty($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response ?: '{}', true);
    return ['http_code' => $httpCode, 'data' => $data];
}

try {
    if ($provider === 'GHN') {
        if ($action === 'create_order') {
            $orderId = (int)($input['order_id'] ?? 0);
            $toName = clean_string($input['to_name'] ?? '', 100);
            $toPhone = clean_string($input['to_phone'] ?? '', 20);
            $toAddress = clean_string($input['to_address'] ?? '', 300);
            $toWard = clean_string($input['to_ward'] ?? '', 50);
            $toDistrict = clean_string($input['to_district'] ?? '', 50);
            $toProvince = clean_string($input['to_province'] ?? '', 50);
            $weight = (int)($input['weight'] ?? 500); // grams
            $codAmount = (int)($input['cod_amount'] ?? 0);

            if ($orderId <= 0 || $toName === '' || $toPhone === '' || $toAddress === '') {
                json_out(['status' => 'error', 'message' => 'Thiếu thông tin đơn hàng vận chuyển.'], 400);
            }

            $payload = [
                'payment_type_id' => 2,
                'note' => 'Đơn hàng từ Chợ Lấp Vò Online',
                'required_note' => 'CHOXEMHANGKHONGTHU',
                'from_name' => app_env('COMPANY_NAME', 'Chợ Lấp Vò Online'),
                'from_phone' => app_env('COMPANY_PHONE', '0979553289'),
                'from_address' => app_env('COMPANY_ADDRESS', 'Lấp Vò, Đồng Tháp'),
                'to_name' => $toName,
                'to_phone' => $toPhone,
                'to_address' => $toAddress,
                'to_ward_code' => $toWard,
                'to_district_id' => (int)$toDistrict,
                'weight' => $weight,
                'cod_amount' => $codAmount,
                'service_type_id' => 2,
                'items' => $input['items'] ?? [],
            ];

            $result = ghn_request('/v2/shipping-order/create', $payload);
            $trackingCode = $result['data']['data']['order_code'] ?? '';

            // Lưu tracking
            if ($trackingCode && $orderId > 0) {
                $pdo = pdo();
                $stmt = $pdo->prepare('UPDATE orders SET shipping_provider = ?, tracking_code = ?, shipping_status = ? WHERE id = ?');
                $stmt->execute(['GHN', $trackingCode, 'created', $orderId]);
            }

            json_out(['status' => 'ok', 'tracking_code' => $trackingCode, 'provider_response' => $result['data']]);
        }

        if ($action === 'track') {
            $trackingCode = clean_string($input['tracking_code'] ?? '', 50);
            if ($trackingCode === '') {
                json_out(['status' => 'error', 'message' => 'Thiếu mã vận đơn.'], 400);
            }
            $result = ghn_request('/v2/shipping-order/detail', ['order_code' => $trackingCode]);
            json_out(['status' => 'ok', 'tracking' => $result['data']]);
        }
    }

    if ($provider === 'GHTK') {
        if ($action === 'create_order') {
            $payload = [
                'products' => $input['items'] ?? [],
                'order' => [
                    'id' => $input['order_code'] ?? '',
                    'pick_address' => app_env('COMPANY_ADDRESS', 'Lấp Vò, Đồng Tháp'),
                    'pick_phone' => app_env('COMPANY_PHONE', '0979553289'),
                    'name' => clean_string($input['to_name'] ?? '', 100),
                    'phone' => clean_string($input['to_phone'] ?? '', 20),
                    'address' => clean_string($input['to_address'] ?? '', 300),
                    'ward' => clean_string($input['to_ward'] ?? '', 50),
                    'district' => clean_string($input['to_district'] ?? '', 50),
                    'province' => clean_string($input['to_province'] ?? '', 50),
                    'weight' => (int)($input['weight'] ?? 500),
                    'value' => (int)($input['cod_amount'] ?? 0),
                ],
            ];
            $result = ghtk_request('/shipment/order', $payload);
            json_out(['status' => 'ok', 'provider_response' => $result['data']]);
        }

        if ($action === 'track') {
            $trackingCode = clean_string($input['tracking_code'] ?? '', 50);
            $result = ghtk_request('/shipment/v2/' . urlencode($trackingCode), [], 'GET');
            json_out(['status' => 'ok', 'tracking' => $result['data']]);
        }
    }

    json_out(['status' => 'error', 'message' => "Unknown provider/action: $provider/$action"]);
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
