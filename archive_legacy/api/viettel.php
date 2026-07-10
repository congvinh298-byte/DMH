<?php



class ViettelInvoiceAPI {
    private $baseUrl = 'https://api-vinvoice.viettel.vn/services/einvoiceapplication/api';
    private $authUrl = 'https://api-vinvoice.viettel.vn/auth/login';
    private $user;
    private $pass;
    private $taxCode;
    private $templateCode;
    private $invoiceSeries;
    private $defaultBuyer;
    private $tokenSerial;

    public function __construct() {
        $this->user = getenv('VIETTEL_INVOICE_USER') ?: '1402228630';
        $this->pass = getenv('VIETTEL_INVOICE_PASS') ?: 'Abc123@@';
        $this->taxCode = getenv('VIETTEL_INVOICE_TAX_CODE') ?: '1402228630';
        $this->templateCode = getenv('VIETTEL_INVOICE_TEMPLATE_CODE') ?: '1/001';
        $this->invoiceSeries = getenv('VIETTEL_INVOICE_SERIES') ?: 'C26MTH';
        $this->defaultBuyer = getenv('VIETTEL_INVOICE_DEFAULT_BUYER') ?: 'Khách lẻ không lấy hóa đơn';
        $this->tokenSerial = getenv('VIETTEL_INVOICE_TOKEN_SERIAL') ?: '5405250716014874';
    }

    private function getAccessToken() {
        // Cache token if possible, but for simplicity, we login every time
        $url = $this->authUrl;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $payload = json_encode(['username' => $this->user, 'password' => $this->pass]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if (isset($data['access_token'])) {
            return $data['access_token'];
        }
        throw new Exception("Lỗi đăng nhập Viettel API: " . $res);
    }

    private function callApi($endpoint, $payload, $method = 'POST') {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/' . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $headers = [
            'Content-Type: application/json',
            'Cookie: access_token=' . $token
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }

    public function generateHash($invoiceData) {
        $endpoint = 'InvoiceAPI/InvoiceWS/createInvoiceUsbTokenGetHash/' . $this->taxCode;
        
        // Add required cert serial
        if (!isset($invoiceData['generalInvoiceInfo']['certificateSerial'])) {
            $invoiceData['generalInvoiceInfo']['certificateSerial'] = $this->tokenSerial;
        }

        $res = $this->callApi($endpoint, $invoiceData);
        return $res;
    }

    public function insertSignature($invoiceData, $hashString, $signature) {
        $endpoint = 'InvoiceAPI/InvoiceWS/createInvoiceUsbTokenInsertSignature/' . $this->taxCode;
        
        $payload = [
            'invoiceDto' => $invoiceData,
            'hashString' => $hashString,
            'signature' => $signature
        ];

        $res = $this->callApi($endpoint, $payload);
        return $res;
    }

    public function buildInvoiceData($orders) {
        // $orders can be an array of order objects or a single order object
        if (!is_array($orders)) {
            $orders = [$orders];
        }

        $totalWithoutTax = 0;
        $totalTax = 0;
        $totalWithTax = 0;
        $items = [];
        $lineNumber = 1;
        
        foreach ($orders as $order) {
            // Simplify logic for demo, usually we fetch order_items
            // Assuming order has total, we calculate 10% tax.
            // Since DTH system might not store tax separately, we reverse-calculate or treat total as with tax
            $itemTotalWithTax = (float)($order['total_price'] ?? $order['total'] ?? 0);
            $itemTotalWithoutTax = round($itemTotalWithTax / 1.1);
            $itemTax = $itemTotalWithTax - $itemTotalWithoutTax;
            
            $qty = !empty($order['quantity']) ? (float)$order['quantity'] : 1;
            
            $items[] = [
                'lineNumber' => $lineNumber++,
                'itemCode' => !empty($order['product_id']) ? (string)$order['product_id'] : 'SP01',
                'itemName' => !empty($order['product_name']) ? $order['product_name'] : ("Đơn hàng " . ($order['order_code'] ?? 'SP')),
                'unitName' => "Cái",
                'quantity' => $qty,
                'unitPrice' => round($itemTotalWithoutTax / $qty),
                'itemTotalAmountWithoutTax' => $itemTotalWithoutTax,
                'taxPercentage' => 10,
                'taxAmount' => $itemTax,
                'itemTotalAmountWithTax' => $itemTotalWithTax
            ];
            
            $totalWithoutTax += $itemTotalWithoutTax;
            $totalTax += $itemTax;
            $totalWithTax += $itemTotalWithTax;
        }

        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $buyerName = count($orders) == 1 && !empty($orders[0]['customer_name']) ? $orders[0]['customer_name'] : $this->defaultBuyer;
        $buyerTaxCode = count($orders) == 1 && !empty($orders[0]['customer_tax_code']) ? $orders[0]['customer_tax_code'] : '';
        $buyerAddressLine = count($orders) == 1 && !empty($orders[0]['customer_address']) ? $orders[0]['customer_address'] : '';

        return [
            'generalInvoiceInfo' => [
                'invoiceType' => '1',
                'templateCode' => $this->templateCode,
                'invoiceSeries' => $this->invoiceSeries,
                'currencyCode' => 'VND',
                'adjustmentType' => '1',
                'paymentStatus' => true,
                'transactionUuid' => $uuid,
                'certificateSerial' => $this->tokenSerial
            ],
            'buyerInfo' => [
                'buyerName' => $buyerName,
                'buyerLegalName' => $buyerName,
                'buyerTaxCode' => $buyerTaxCode,
                'buyerAddressLine' => $buyerAddressLine
            ],
            'sellerInfo' => [
                'sellerLegalName' => getenv('COMPANY_NAME') ?: 'CÔNG TY TNHH MTV ĐIỆN MÁY HIẾU',
                'sellerTaxCode' => $this->taxCode,
                'sellerAddressLine' => getenv('COMPANY_ADDRESS') ?: 'Đồng Tháp'
            ],
            'payments' => [
                ['paymentMethodName' => 'TM/CK']
            ],
            'itemInfo' => $items,
            'summarizeInfo' => [
                'sumOfTotalLineAmountWithoutTax' => $totalWithoutTax,
                'totalAmountWithoutTax' => $totalWithoutTax,
                'totalTaxAmount' => $totalTax,
                'totalAmountWithTax' => $totalWithTax,
                'totalAmountWithTaxInWords' => 'Đã thu tiền'
            ],
            'taxBreakdowns' => [
                [
                    'taxPercentage' => 10,
                    'taxableAmount' => $totalWithoutTax,
                    'taxAmount' => $totalTax
                ]
            ]
        ];
    }
}
