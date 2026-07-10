<?php
// Module: finances



function bct_validate_period(string $from, string $to): array
{
    $from = trim($from);
    $to = trim($to);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $startErrors = DateTimeImmutable::getLastErrors();
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    $endErrors = DateTimeImmutable::getLastErrors();
    $startInvalid = !$start || ($startErrors !== false && ((int)$startErrors['warning_count'] > 0 || (int)$startErrors['error_count'] > 0));
    $endInvalid = !$end || ($endErrors !== false && ((int)$endErrors['warning_count'] > 0 || (int)$endErrors['error_count'] > 0));
    if ($startInvalid || $endInvalid || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to) {
        throw new InvalidArgumentException('Ky bao cao phai dung dinh dang YYYY-MM-DD.');
    }
    if ($start > $end) {
        throw new InvalidArgumentException('Ngay bat dau khong duoc sau ngay ket thuc.');
    }
    $maxDays = max(1, (int)app_env('BCT_REPORT_MAX_DAYS', '370'));
    $days = (int)$start->diff($end)->format('%a') + 1;
    if ($days > $maxDays) {
        throw new InvalidArgumentException("Ky bao cao toi da {$maxDays} ngay.");
    }
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function input_invoice_storage_root(): string
{
    $root = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'bct-invoices';
    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Khong tao duoc thu muc luu hoa don dau vao.');
    }
    $real = realpath($root);
    if ($real === false) {
        throw new RuntimeException('Khong xac dinh duoc thu muc luu hoa don dau vao.');
    }
    return $real;
}

function input_invoice_row_for_admin(array $row): array
{
    foreach (['id', 'subtotal_amount', 'vat_amount', 'adjustment_amount', 'total_amount', 'pdf_size'] as $field) {
        $row[$field] = (int)($row[$field] ?? 0);
    }
    $row['download_url'] = 'api_master.php?action=admin_input_invoice_file&id=' . (int)$row['id'];
    unset($row['pdf_path']);
    return $row;
}

function admin_input_invoice_rows(PDO $pdo, int $limit = 300): array
{
    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->query("SELECT * FROM input_invoices ORDER BY invoice_date DESC, id DESC LIMIT {$limit}");
    return array_map('input_invoice_row_for_admin', $stmt->fetchAll());
}

function admin_save_input_invoice_pdf(PDO $pdo, array $input, array $file): array
{
    $invoiceNumber = clean_string($input['invoice_number'] ?? '', 120);
    $invoiceSeries = strtoupper(clean_string($input['invoice_series'] ?? '', 80));
    $invoiceDate = clean_string($input['invoice_date'] ?? '', 10);
    $sellerName = clean_string($input['seller_name'] ?? '', 255);
    $sellerTaxCode = strtoupper(clean_string($input['seller_tax_code'] ?? '', 50));
    $note = clean_string($input['note'] ?? '', 2000);
    bct_validate_period($invoiceDate, $invoiceDate);
    if ($invoiceNumber === '' || $invoiceSeries === '' || $sellerName === '' || $sellerTaxCode === '') {
        throw new InvalidArgumentException('So hoa don, ky hieu hoa don, ngay hoa don, don vi ban va ma so thue la bat buoc.');
    }
    if (!preg_match('/^\d{10}(?:-\d{3})?$/', $sellerTaxCode)) {
        throw new InvalidArgumentException('Ma so thue don vi ban phai gom 10 chu so hoac 10 chu so kem - va 3 chu so don vi phu thuoc.');
    }

    $subtotal = money_int($input['subtotal_amount'] ?? 0);
    $vat = money_int($input['vat_amount'] ?? 0);
    $adjustment = signed_money_int($input['adjustment_amount'] ?? 0);
    $total = money_int($input['total_amount'] ?? 0);
    if ($subtotal + $vat + $adjustment !== $total) {
        throw new InvalidArgumentException('Tong hoa don phai bang tien truoc thue + VAT + dieu chinh.');
    }

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('File PDF khong duoc tai len hop le. Ma loi: ' . $uploadError);
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $originalName = clean_string(basename((string)($file['name'] ?? 'hoa-don.pdf')), 255);
    $size = (int)($file['size'] ?? 0);
    $maxBytes = max(1, (int)app_env('BCT_INPUT_PDF_MAX_MB', '20')) * 1024 * 1024;
    if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > $maxBytes) {
        throw new InvalidArgumentException('PDF rong, qua dung luong cho phep hoac khong phai file upload hop le.');
    }
    if (strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf') {
        throw new InvalidArgumentException('Chi chap nhan file PDF.');
    }
    $head = file_get_contents($tmp, false, null, 0, 1024);
    if (!is_string($head) || strpos($head, '%PDF-') === false) {
        throw new InvalidArgumentException('Noi dung file khong phai dinh dang PDF.');
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        if (!in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true)) {
            throw new InvalidArgumentException('MIME cua file khong phai PDF.');
        }
    }
    $sha256 = hash_file('sha256', $tmp);
    if (!is_string($sha256) || strlen($sha256) !== 64) {
        throw new RuntimeException('Khong tao duoc SHA-256 cho PDF.');
    }

    $duplicate = $pdo->prepare('SELECT id FROM input_invoices WHERE pdf_sha256 = ? OR (seller_tax_code = ? AND invoice_series = ? AND invoice_number = ?) LIMIT 1');
    $duplicate->execute([$sha256, $sellerTaxCode, $invoiceSeries, $invoiceNumber]);
    if ($duplicate->fetchColumn()) {
        throw new DomainException('Hoa don hoac PDF nay da ton tai, he thong khong ghi trung.');
    }

    $root = input_invoice_storage_root();
    $subdir = date('Y/m', strtotime($invoiceDate));
    $targetDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Khong tao duoc thu muc luu PDF theo ky.');
    }
    $storedName = date('Ymd', strtotime($invoiceDate)) . '-' . bin2hex(random_bytes(16)) . '.pdf';
    $target = $targetDir . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Khong luu duoc PDF hoa don.');
    }
    @chmod($target, 0600);
    $relativePath = $subdir . '/' . $storedName;

    try {
        $stmt = $pdo->prepare("INSERT INTO input_invoices
            (invoice_number, invoice_series, invoice_date, seller_name, seller_tax_code, subtotal_amount, vat_amount, adjustment_amount,
             total_amount, currency, pdf_path, pdf_original_name, pdf_sha256, pdf_size, status, note, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'VND', ?, ?, ?, ?, 'active', ?, 'admin', NOW())");
        $stmt->execute([
            $invoiceNumber, $invoiceSeries, $invoiceDate, $sellerName, $sellerTaxCode, $subtotal, $vat, $adjustment,
            $total, $relativePath, $originalName, $sha256, $size, $note,
        ]);
        $id = (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        @unlink($target);
        throw $e;
    }

    $stmt = $pdo->prepare('SELECT * FROM input_invoices WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return input_invoice_row_for_admin($stmt->fetch() ?: ['id' => $id]);
}

function admin_stream_input_invoice(PDO $pdo, int $id)
{
    $stmt = $pdo->prepare('SELECT pdf_path, pdf_original_name, pdf_sha256 FROM input_invoices WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        json_out(['status' => 'error', 'message' => 'Khong tim thay PDF hoa don.'], 404);
    }
    $root = input_invoice_storage_root();
    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$row['pdf_path']);
    $path = realpath($root . DIRECTORY_SEPARATOR . $relative);
    $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($path === false || strpos($path, $rootPrefix) !== 0 || !is_file($path)) {
        json_out(['status' => 'error', 'message' => 'PDF hoa don khong con tren kho luu tru.'], 404);
    }
    if (!hash_equals((string)$row['pdf_sha256'], hash_file('sha256', $path))) {
        json_out(['status' => 'error', 'message' => 'PDF khong vuot qua kiem tra toan ven SHA-256.'], 409);
    }
    $original = basename((string)$row['pdf_original_name']);
    $asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $original) ?: 'hoa-don.pdf';
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($original));
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    readfile($path);
    exit;
}

function bct_money_summary(array $rows): array
{
    $summary = [
        'document_count' => count($rows),
        'subtotal_amount' => 0,
        'vat_amount' => 0,
        'adjustment_amount' => 0,
        'total_amount' => 0,
    ];
    foreach ($rows as $row) {
        foreach (['subtotal_amount', 'vat_amount', 'adjustment_amount', 'total_amount'] as $field) {
            $summary[$field] += (int)($row[$field] ?? 0);
        }
    }
    return $summary;
}

function bct_reconciliation_report(PDO $pdo, string $from, string $to, bool $includeDetails = true): array
{
    list($from, $to) = bct_validate_period($from, $to);
    $issues = [];
    $companyProfile = invoice_company_profile();
    $companyName = app_env('BCT_COMPANY_NAME', $companyProfile['name']);
    $companyTaxCode = app_env('BCT_TAX_CODE', $companyProfile['tax_code']);
    $companyWebsite = app_env('BCT_WEBSITE', $companyProfile['website']);
    if (!preg_match('/^\d{10}(?:-\d{3})?$/', $companyTaxCode)) {
        $issues[] = ['severity' => 'blocking', 'code' => 'company_tax_code_missing_or_invalid', 'count' => 1, 'ids' => []];
    }

    $stmt = $pdo->prepare("SELECT id, invoice_number, invoice_series, invoice_date, seller_name, seller_tax_code,
        subtotal_amount, vat_amount, adjustment_amount, total_amount, currency, pdf_original_name, pdf_sha256, pdf_size, created_at
        FROM input_invoices WHERE status = 'active' AND invoice_date BETWEEN ? AND ? ORDER BY invoice_date, id");
    $stmt->execute([$from, $to]);
    $inputRows = $stmt->fetchAll();
    $inputFormulaIds = [];
    $inputIntegrityIds = [];
    foreach ($inputRows as &$row) {
        foreach (['id', 'subtotal_amount', 'vat_amount', 'adjustment_amount', 'total_amount', 'pdf_size'] as $field) {
            $row[$field] = (int)($row[$field] ?? 0);
        }
        if ($row['subtotal_amount'] + $row['vat_amount'] + $row['adjustment_amount'] !== $row['total_amount']) {
            $inputFormulaIds[] = $row['id'];
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', (string)$row['pdf_sha256']) || $row['pdf_size'] <= 0) {
            $inputIntegrityIds[] = $row['id'];
        }
    }
    unset($row);
    if ($inputFormulaIds) {
        $issues[] = ['severity' => 'blocking', 'code' => 'input_invoice_formula_mismatch', 'count' => count($inputFormulaIds), 'ids' => $inputFormulaIds];
    }
    if ($inputIntegrityIds) {
        $issues[] = ['severity' => 'blocking', 'code' => 'input_invoice_pdf_integrity_missing', 'count' => count($inputIntegrityIds), 'ids' => $inputIntegrityIds];
    }
    if ($inputRows) {
        $issues[] = ['severity' => 'warning', 'code' => 'input_invoice_pdf_values_require_manual_attestation', 'count' => count($inputRows), 'ids' => array_column($inputRows, 'id')];
    }

    $stmt = $pdo->prepare("SELECT i.id, i.invoice_code, i.order_id, COALESCE(i.invoice_date, DATE(i.created_at)) invoice_date,
        i.subtotal_amount, i.vat_amount, i.adjustment_amount,
        CASE WHEN i.total_amount > 0 THEN i.total_amount ELSE i.total_price END total_amount,
        i.total_amount recorded_total_amount, i.total_price legacy_total_price, i.status, i.created_at,
        o.total_price order_total, o.status order_status
        FROM invoices i LEFT JOIN orders o ON o.id = i.order_id
        WHERE i.status = 'active' AND COALESCE(i.invoice_date, DATE(i.created_at)) BETWEEN ? AND ?
        ORDER BY COALESCE(i.invoice_date, DATE(i.created_at)), i.id");
    $stmt->execute([$from, $to]);
    $outputRows = $stmt->fetchAll();
    $outputFormulaIds = [];
    $outputBreakdownMissingIds = [];
    $outputVatZeroIds = [];
    $outputOrderMismatchIds = [];
    foreach ($outputRows as &$row) {
        foreach (['id', 'order_id', 'subtotal_amount', 'vat_amount', 'adjustment_amount', 'total_amount', 'recorded_total_amount', 'legacy_total_price', 'order_total'] as $field) {
            $row[$field] = (int)($row[$field] ?? 0);
        }
        if ($row['recorded_total_amount'] > 0 && $row['subtotal_amount'] + $row['vat_amount'] + $row['adjustment_amount'] !== $row['recorded_total_amount']) {
            $outputFormulaIds[] = $row['id'];
        }
        if ($row['recorded_total_amount'] <= 0 && $row['legacy_total_price'] > 0) {
            $outputBreakdownMissingIds[] = $row['id'];
        }
        if ($row['total_amount'] > 0 && $row['vat_amount'] === 0) {
            $outputVatZeroIds[] = $row['id'];
        }
        if ($row['order_id'] > 0 && $row['order_total'] !== $row['total_amount']) {
            $outputOrderMismatchIds[] = $row['id'];
        }
    }
    unset($row);
    if ($outputFormulaIds) {
        $issues[] = ['severity' => 'blocking', 'code' => 'output_invoice_formula_mismatch', 'count' => count($outputFormulaIds), 'ids' => $outputFormulaIds];
    }
    if ($outputOrderMismatchIds) {
        $issues[] = ['severity' => 'blocking', 'code' => 'output_invoice_order_total_mismatch', 'count' => count($outputOrderMismatchIds), 'ids' => $outputOrderMismatchIds];
    }
    if ($outputBreakdownMissingIds) {
        $issues[] = ['severity' => 'warning', 'code' => 'legacy_output_invoice_missing_vat_breakdown', 'count' => count($outputBreakdownMissingIds), 'ids' => $outputBreakdownMissingIds];
    }
    if ($outputVatZeroIds) {
        $issues[] = ['severity' => 'warning', 'code' => 'output_invoice_vat_zero_or_not_separated', 'count' => count($outputVatZeroIds), 'ids' => $outputVatZeroIds];
    }
    if ($outputRows) {
        $issues[] = ['severity' => 'warning', 'code' => 'output_invoice_signed_einvoice_document_not_stored', 'count' => count($outputRows), 'ids' => array_column($outputRows, 'id')];
    }

    $stmt = $pdo->prepare("SELECT id, order_code, total_price, status, COALESCE(confirmed_at, created_at) accounting_time
        FROM orders
        WHERE status IN ('confirmed','shipped','processing','completed')
          AND DATE(COALESCE(confirmed_at, created_at)) BETWEEN ? AND ?
        ORDER BY id");
    $stmt->execute([$from, $to]);
    $orderRows = $stmt->fetchAll();
    $orderTotal = 0;
    $missingInvoiceOrderIds = [];
    $invoiceOutsidePeriodOrderIds = [];
    $confirmedOrderInvoiceCount = 0;
    $outputOrderIds = [];
    foreach ($outputRows as $invoice) {
        if ((int)$invoice['order_id'] > 0) {
            $outputOrderIds[(int)$invoice['order_id']] = true;
        }
    }
    $allActiveInvoiceOrderIds = [];
    $allInvoiceStmt = $pdo->query("SELECT DISTINCT order_id FROM invoices WHERE status = 'active' AND order_id IS NOT NULL");
    foreach ($allInvoiceStmt->fetchAll() as $invoice) {
        $allActiveInvoiceOrderIds[(int)$invoice['order_id']] = true;
    }
    foreach ($orderRows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['total_price'] = (int)$row['total_price'];
        $orderTotal += $row['total_price'];
        if (isset($outputOrderIds[$row['id']])) {
            $confirmedOrderInvoiceCount++;
        } elseif (isset($allActiveInvoiceOrderIds[$row['id']])) {
            $invoiceOutsidePeriodOrderIds[] = $row['id'];
        } else {
            $missingInvoiceOrderIds[] = $row['id'];
        }
    }
    unset($row);
    if ($missingInvoiceOrderIds) {
        $issues[] = ['severity' => 'blocking', 'code' => 'confirmed_order_missing_output_invoice', 'count' => count($missingInvoiceOrderIds), 'ids' => $missingInvoiceOrderIds];
    }
    if ($invoiceOutsidePeriodOrderIds) {
        $issues[] = ['severity' => 'blocking', 'code' => 'confirmed_order_output_invoice_outside_report_period', 'count' => count($invoiceOutsidePeriodOrderIds), 'ids' => $invoiceOutsidePeriodOrderIds];
    }

    $stmt = $pdo->prepare("SELECT j.id, DATE(j.completed_at) completed_date, j.service_type, j.final_total customer_total,
        COALESCE((SELECT jp.platform_fee FROM job_pricing jp WHERE jp.job_id = j.id ORDER BY jp.id DESC LIMIT 1), 0) platform_fee,
        COALESCE((SELECT jp.vat_amount FROM job_pricing jp WHERE jp.job_id = j.id ORDER BY jp.id DESC LIMIT 1), 0) vat_amount
        FROM job_posts j WHERE j.completed_at IS NOT NULL AND DATE(j.completed_at) BETWEEN ? AND ? ORDER BY j.id");
    $stmt->execute([$from, $to]);
    $jobRows = $stmt->fetchAll();
    $jobCustomerTotal = 0;
    $platformFeeTotal = 0;
    $jobVatTotal = 0;
    foreach ($jobRows as &$row) {
        foreach (['id', 'customer_total', 'platform_fee', 'vat_amount'] as $field) {
            $row[$field] = (int)($row[$field] ?? 0);
        }
        $jobCustomerTotal += $row['customer_total'];
        $platformFeeTotal += $row['platform_fee'];
        $jobVatTotal += $row['vat_amount'];
    }
    unset($row);

    $inputSummary = bct_money_summary($inputRows);
    $outputSummary = bct_money_summary($outputRows);
    $blockingCount = count(array_filter($issues, static function (array $issue): bool {
        return ($issue['severity'] ?? '') === 'blocking';
    }));
    $warningCount = count(array_filter($issues, static function (array $issue): bool {
        return ($issue['severity'] ?? '') === 'warning';
    }));

    $report = [
        'schema_version' => 'dth-bct-report-1.0',
        'generated_at' => date(DATE_ATOM),
        'period' => ['from' => $from, 'to' => $to, 'timezone' => 'Asia/Ho_Chi_Minh'],
        'company' => [
            'name' => $companyName,
            'tax_code' => $companyTaxCode,
            'website' => $companyWebsite,
        ],
        'submission_status' => [
            'ready_for_submission' => $blockingCount === 0,
            'blocking_issue_count' => $blockingCount,
            'warning_count' => $warningCount,
        ],
        'invoice_registers' => [
            'input_purchase_invoices' => $inputSummary,
            'output_sales_invoices' => $outputSummary,
        ],
        'operational_records' => [
            'confirmed_product_orders' => ['count' => count($orderRows), 'total_amount' => $orderTotal],
            'completed_service_jobs' => ['count' => count($jobRows), 'customer_total' => $jobCustomerTotal, 'vat_amount' => $jobVatTotal],
            'platform_fee_accrual' => ['count' => count($jobRows), 'total_amount' => $platformFeeTotal],
        ],
        'reconciliation' => [
            'output_invoice_minus_confirmed_order_amount' => $outputSummary['total_amount'] - $orderTotal,
            'output_invoice_count_for_confirmed_orders' => $confirmedOrderInvoiceCount,
            'confirmed_orders_missing_output_invoice_count' => count($missingInvoiceOrderIds),
            'confirmed_orders_invoice_outside_report_period_count' => count($invoiceOutsidePeriodOrderIds),
            'input_output_invoice_value_difference' => $outputSummary['total_amount'] - $inputSummary['total_amount'],
        ],
        'issues' => $issues,
        'transparency_notes' => [
            'Input invoice values are admin-entered metadata linked to immutable PDF files and SHA-256 hashes; PDF monetary content is not automatically OCR-verified.',
            'Output invoice totals are reconciled against confirmed product orders. Legacy invoices without VAT breakdown and missing signed e-invoice documents are disclosed as warnings.',
            'Service job customer totals and platform fees are operational records, not represented as legally issued electronic invoices by this system.',
            'This export is a system reconciliation report and does not replace legally issued electronic invoices or an official authority-specific schema.',
        ],
    ];
    if ($includeDetails) {
        $report['details'] = [
            'input_purchase_invoices' => $inputRows,
            'output_sales_invoices' => $outputRows,
            'confirmed_product_orders' => $orderRows,
            'completed_service_jobs' => $jobRows,
        ];
    }
    return $report;
}

function bct_log_report_access(PDO $pdo, string $username, string $authMode, ?string $from, ?string $to, ?string $responseHash, bool $success)
{
    $stmt = $pdo->prepare("INSERT INTO bct_report_access_log
        (username, auth_mode, period_from, period_to, response_sha256, client_ip, user_agent, success, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        clean_string($username, 190),
        clean_string($authMode, 30),
        $from,
        $to,
        $responseHash,
        client_ip(),
        clean_string($_SERVER['HTTP_USER_AGENT'] ?? '', 500),
        $success ? 1 : 0,
    ]);
}

function xml_cell($value, string $type = 'String'): string
{
    $escaped = htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return '<Cell><Data ss:Type="' . $type . '">' . $escaped . '</Data></Cell>';
}

function output_daily_settlement_excel(PDO $pdo)
{
    $stats = admin_stats($pdo);
    $workers = admin_worker_rows($pdo);
    $payments = admin_worker_payments($pdo, 500);
    $today = date('Y-m-d');
    $todayPayments = array_values(array_filter($payments, static function (array $payment) use ($today): bool {
        return dth_starts_with((string)($payment['confirmed_at'] ?? ''), $today);
    }));
    $todayJobs = array_values(array_filter(admin_jobs($pdo), static function (array $job) use ($today): bool {
        return $job['status'] === 'completed' && dth_starts_with((string)$job['completed_at'], $today);
    }));

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="ket-toan-ngay-' . $today . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Worksheet ss:Name="Ket toan ngay"><Table>';
    echo '<Row>' . xml_cell('KET TOAN NGAY ' . date('d/m/Y')) . '</Row>';
    foreach ([
        ['Don hang hom nay', (int)$stats['today_orders']],
        ['Doanh thu don hang hom nay', (int)$stats['today_revenue']],
        ['Ca goi tho hom nay', (int)$stats['today_jobs']],
        ['Tong so tho', (int)($stats['total_workers'] ?? 0)],
        ['Phi nen tang da thu hom nay', array_sum(array_map(static function (array $p): int { return (int)($p['applied_amount'] ?? 0); }, $todayPayments))],
        ['Tong no phi nen tang hien tai', (int)$stats['unpaid_total']],
    ] as $item) {
        echo '<Row>' . xml_cell($item[0]) . xml_cell($item[1], 'Number') . '</Row>';
    }

    echo '<Row></Row><Row>' . xml_cell('TONG HOP THO VA CONG NO') . '</Row>';
    echo '<Row>' . xml_cell('Telegram ID') . xml_cell('Ten tho') . xml_cell('So dien thoai') . xml_cell('Loai') . xml_cell('So ca xong') . xml_cell('Thu nhap') . xml_cell('Da dong phi') . xml_cell('Con no phi') . xml_cell('Trang thai') . '</Row>';
    foreach ($workers as $worker) {
        if ((int)($worker['is_admin'] ?? 0) === 1) {
            continue;
        }
        echo '<Row>'
            . xml_cell($worker['worker_id'])
            . xml_cell($worker['telegram_name'])
            . xml_cell($worker['phone'])
            . xml_cell($worker['worker_type'])
            . xml_cell((int)$worker['jobs_completed'], 'Number')
            . xml_cell((int)$worker['total_earned'], 'Number')
            . xml_cell((int)$worker['confirmed_paid_fee'], 'Number')
            . xml_cell((int)$worker['unpaid_fee'], 'Number')
            . xml_cell(((int)$worker['is_receive_blocked'] === 1 || (int)$worker['payment_blocked'] === 1) ? 'Khoa' : 'Hoat dong')
            . '</Row>';
    }

    echo '<Row></Row><Row>' . xml_cell('CA HOAN THANH HOM NAY') . '</Row>';
    echo '<Row>' . xml_cell('Ma ca') . xml_cell('Dich vu') . xml_cell('Tho') . xml_cell('Gia khach') . xml_cell('Phi nen tang') . xml_cell('Dia chi') . '</Row>';
    foreach ($todayJobs as $job) {
        echo '<Row>'
            . xml_cell('#' . $job['id'])
            . xml_cell($job['service_type'])
            . xml_cell($job['worker_id'])
            . xml_cell((int)$job['final_total'], 'Number')
            . xml_cell((int)$job['platform_fee'], 'Number')
            . xml_cell($job['address'])
            . '</Row>';
    }
    echo '</Table></Worksheet></Workbook>';
    exit;
}

function admin_stats(PDO $pdo): array
{
    $orders = admin_orders($pdo);
    $jobs = admin_jobs($pdo);
    $today = date('Y-m-d');
    $todayOrders = array_filter($orders, static function (array $o) use ($today): bool {
        return dth_starts_with((string)$o['created_at'], $today);
    });
    $todayJobs = array_filter($jobs, static function (array $j) use ($today): bool {
        return dth_starts_with((string)$j['created_at'], $today);
    });

    $unpaidTotal = 0;
    $unpaidCount = 0;
    if (table_exists($pdo, 'job_pricing') && table_exists($pdo, 'job_posts')) {
        $stmt = $pdo->query("SELECT COUNT(*) c, COALESCE(SUM(GREATEST(jp.platform_fee - COALESCE(jp.paid_amount, 0), 0)),0) s
            FROM job_pricing jp JOIN job_posts j ON j.id = jp.job_id
            WHERE j.completed_at IS NOT NULL AND jp.platform_fee > COALESCE(jp.paid_amount, 0)");
        $r = $stmt->fetch() ?: ['c' => 0, 's' => 0];
        $unpaidCount = (int)$r['c'];
        $unpaidTotal = (int)$r['s'];
    }

    $productCount = 0;
    if (legacy_product_columns($pdo) !== []) {
        $productCount += (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }
    if (table_exists($pdo, 'marketplace_products')) {
        $productCount += (int)$pdo->query('SELECT COUNT(*) FROM marketplace_products')->fetchColumn();
    }
    $banCount = table_exists($pdo, 'banned_devices') ? (int)$pdo->query("SELECT COUNT(*) FROM banned_devices WHERE expires_at IS NULL OR expires_at > NOW()")->fetchColumn() : 0;
    $activeWorkers = 0;
    $totalWorkers = 0;
    $blockedWorkers = 0;
    if (table_exists($pdo, 'worker_profiles')) {
        $totalWorkers = (int)$pdo->query("SELECT COUNT(*) FROM worker_profiles WHERE is_admin = 0 AND role IN ('worker')")->fetchColumn();
        $activeWorkers = (int)$pdo->query("SELECT COUNT(*) FROM worker_profiles WHERE is_admin = 0 AND role IN ('worker') AND is_receive_blocked = 0 AND payment_blocked = 0")->fetchColumn();
        $blockedWorkers = (int)$pdo->query("SELECT COUNT(*) FROM worker_profiles WHERE is_admin = 0 AND role IN ('worker') AND (is_receive_blocked = 1 OR payment_blocked = 1)")->fetchColumn();
    }
    $feesPaidToday = table_exists($pdo, 'worker_payments')
        ? (int)$pdo->query("SELECT COALESCE(SUM(applied_amount),0) FROM worker_payments WHERE status = 'confirmed' AND DATE(confirmed_at) = CURDATE()")->fetchColumn()
        : 0;
    $pendingPayments = table_exists($pdo, 'worker_payments')
        ? (int)$pdo->query("SELECT COUNT(*) FROM worker_payments WHERE status = 'pending'")->fetchColumn()
        : 0;

    return [
        'total_orders' => count($orders),
        'total_revenue' => array_sum(array_column($orders, 'total_price')),
        'today_orders' => count($todayOrders),
        'today_revenue' => array_sum(array_column($todayOrders, 'total_price')),
        'total_jobs' => count($jobs),
        'pending_jobs' => count(array_filter($jobs, static function (array $j): bool {
            return $j['status'] === 'pending';
        })),
        'completed_jobs' => count(array_filter($jobs, static function (array $j): bool {
            return $j['status'] === 'completed';
        })),
        'today_jobs' => count($todayJobs),
        'total_products' => $productCount,
        'total_sims' => 0,
        'active_workers' => $activeWorkers,
        'total_workers' => $totalWorkers,
        'blocked_workers' => $blockedWorkers,
        'unpaid_count' => $unpaidCount,
        'unpaid_total' => $unpaidTotal,
        'fees_paid_today' => $feesPaidToday,
        'pending_worker_payments' => $pendingPayments,
        'banned_devices' => $banCount,
    ];
}
