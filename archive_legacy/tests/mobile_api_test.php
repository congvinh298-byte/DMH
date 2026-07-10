<?php
/**
 * Mobile API Test Suite
 * Chạy trên host: php tests/mobile_api_test.php
 * Mục tiêu: kiểm tra toàn bộ mobile endpoints không cần test tay.
 */

// Ngăn api_master chạy routing HTTP
define('DTH_API_LIBRARY_ONLY', true);

// Load để lấy DB credentials từ .env
require_once __DIR__ . '/../api_master.php';

// Tắt gửi Telegram thật trong test bằng cách override env sau khi .env đã load
$_ENV['TELEGRAM_BOT_TOKEN_WORKER'] = '';
$_ENV['TELEGRAM_BOT_TOKEN_REPORT'] = '';
$_ENV['TELEGRAM_CHAT_WORKER'] = '';
$_ENV['TELEGRAM_CHAT_REPORT'] = '';
$_ENV['TELEGRAM_CHAT_SALES'] = '';
$_SERVER['TELEGRAM_BOT_TOKEN_WORKER'] = '';
$_SERVER['TELEGRAM_BOT_TOKEN_REPORT'] = '';
$_SERVER['TELEGRAM_CHAT_WORKER'] = '';
$_SERVER['TELEGRAM_CHAT_REPORT'] = '';
$_SERVER['TELEGRAM_CHAT_SALES'] = '';
putenv('TELEGRAM_BOT_TOKEN_WORKER=');
putenv('TELEGRAM_BOT_TOKEN_REPORT=');
putenv('TELEGRAM_CHAT_WORKER=');
putenv('TELEGRAM_CHAT_REPORT=');
putenv('TELEGRAM_CHAT_SALES=');

$_ENV['MOBILE_OTP_MOCK'] = 'true';
$_SERVER['MOBILE_OTP_MOCK'] = 'true';
putenv('MOBILE_OTP_MOCK=true');

$pdo = pdo();
$tester = new MobileApiTester($pdo);
$tester->runAll();

class MobileApiTester
{
    private PDO $pdo;
    private array $errors = [];
    private int $passed = 0;
    private int $failed = 0;
    private array $cleanupUsers = [];
    private array $cleanupWorkers = [];
    private array $cleanupJobs = [];
    private array $cleanupSessions = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function runAll(): void
    {
        echo "=== DTH Mobile API Test Suite ===\n\n";

        $this->testCustomerAuth();
        $this->testCustomerCreateJobAndQuery();
        $this->testWorkerAuth();
        $this->testWorkerPendingJobs();
        $this->testWorkerClaimAndStatusFlow();
        $this->testEarningsAndHistory();
        $this->testPushToken();
        $this->testWorkerLocation();
        $this->testServices();

        $this->cleanup();

        echo "\n=== Kết quả ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        if ($this->errors) {
            echo "\nLỗi chi tiết:\n";
            foreach ($this->errors as $i => $err) {
                echo ($i + 1) . ". " . $err . "\n";
            }
            exit(1);
        }
        echo "\nTất cả test đều PASS ✅\n";
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->errors[] = $message;
            echo "  ❌ FAIL: {$message}\n";
            return;
        }
        echo "  ✅ PASS: {$message}\n";
    }

    private function expect(array $response, string $expectedStatus, string $step): void
    {
        $status = $response['status'] ?? 'missing';
        $this->assert($status === $expectedStatus, "{$step} expects status={$expectedStatus}, got {$status}");
    }

    private function uniquePhone(): string
    {
        return '09' . substr(date('mdHis') . random_int(1000, 9999), 0, 8);
    }

    private function uniqueWorkerId(): int
    {
        return (int)(date('YmdHis') . random_int(10, 99));
    }

    private function registerWorker(int $workerId): array
    {
        $this->cleanupWorkers[] = $workerId;
        $this->pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, phone, role, is_admin, is_active, payment_blocked, pin_hash)
            VALUES (?, ?, ?, ?, 0, 1, 0, ?)
            ON DUPLICATE KEY UPDATE telegram_name=VALUES(telegram_name), phone=VALUES(phone), is_active=VALUES(is_active), payment_blocked=VALUES(payment_blocked), pin_hash=VALUES(pin_hash)")->execute([$workerId, 'Tho Test ' . $workerId, '0909999888', 'worker', password_hash('1234', PASSWORD_BCRYPT)]);
        $resp = mobile_handle_action($this->pdo, 'mobile_worker_login', ['worker_id' => $workerId, 'pin' => '1234']);
        return $resp;
    }

    private function registerCustomer(): array
    {
        $phone = $this->uniquePhone();
        $resp = mobile_handle_action($this->pdo, 'mobile_customer_register', [
            'phone' => $phone,
            'otp' => '123456',
            'name' => 'Khach Test ' . $phone,
        ]);
        $this->cleanupUsers[] = (int)($resp['user']['id'] ?? 0);
        return $resp;
    }

    private function testCustomerAuth(): void
    {
        echo "\n[Customer Auth]\n";

        // Send OTP
        $resp = mobile_handle_action($this->pdo, 'mobile_customer_send_otp', ['phone' => $this->uniquePhone()]);
        $this->expect($resp, 'success', 'send_otp');

        // Register
        $resp = $this->registerCustomer();
        $this->expect($resp, 'success', 'customer_register');
        $this->assert(!empty($resp['token']), 'register returns token');
        $this->assert(!empty($resp['user']['login_key']), 'register returns login_key');

        $token = $resp['token'];
        $this->cleanupSessions[] = $token;

        // Login
        $resp = mobile_handle_action($this->pdo, 'mobile_customer_login', [
            'phone' => $resp['user']['phone'],
            'otp' => '123456',
        ]);
        $this->expect($resp, 'success', 'customer_login');
        $this->assert(!empty($resp['token']), 'login returns token');

        // Profile
        $resp = mobile_handle_action($this->pdo, 'mobile_customer_profile', ['token' => $token]);
        $this->expect($resp, 'success', 'customer_profile');
        $this->assert(!empty($resp['user']), 'profile returns user');
    }

    private function testCustomerCreateJobAndQuery(): void
    {
        echo "\n[Customer Create Job & Query]\n";
        $customer = $this->registerCustomer();
        $token = $customer['token'];
        $this->cleanupSessions[] = $token;

        // Create job
        $resp = mobile_handle_action($this->pdo, 'mobile_create_job', [
            'token' => $token,
            'service_type' => 'dien_lanh',
            'selected_service_name' => 'Vệ sinh máy lạnh gia đình (dưới 2HP)',
            'issue_description' => 'Máy lạnh không lạnh (test)',
            'customer_name' => $customer['user']['name'],
            'customer_phone' => $customer['user']['phone'],
            'address' => '166 Ấp Bình Thạnh 1, Xã Lấp Vò, Đồng Tháp',
            'map_lat' => 10.357422,
            'map_lng' => 105.522124,
        ]);
        $this->expect($resp, 'success', 'create_job');
        $this->assert(!empty($resp['job_id']), 'create_job returns job_id');
        $this->assert(($resp['platform_fee'] ?? 0) > 0, 'create_job returns platform_fee');
        $jobId = $resp['job_id'];
        $this->cleanupJobs[] = $jobId;

        // Customer jobs
        $resp = mobile_handle_action($this->pdo, 'mobile_customer_jobs', ['token' => $token, 'limit' => 5]);
        $this->expect($resp, 'success', 'customer_jobs');
        $this->assert(count($resp['jobs'] ?? []) >= 1, 'customer_jobs returns at least 1 job');

        // Job detail
        $resp = mobile_handle_action($this->pdo, 'mobile_customer_job_detail', ['token' => $token, 'job_id' => $jobId]);
        $this->expect($resp, 'success', 'customer_job_detail');
        $this->assert(($resp['job']['job_id'] ?? 0) === $jobId, 'job_detail returns correct job_id');

        // Cancel job
        $resp = mobile_handle_action($this->pdo, 'mobile_customer_cancel_job', [
            'token' => $token,
            'job_id' => $jobId,
            'reason' => 'Test cancel',
        ]);
        $this->expect($resp, 'success', 'customer_cancel_job');
    }

    private function testWorkerAuth(): void
    {
        echo "\n[Worker Auth]\n";

        $workerId = $this->uniqueWorkerId();

        // Login before PIN set
        $resp = mobile_handle_action($this->pdo, 'mobile_worker_login', [
            'worker_id' => $workerId,
            'pin' => '1234',
        ]);
        $this->expect($resp, 'error', 'worker_login_before_pin');
        $this->assert(($resp['code'] ?? '') === 'WORKER_NOT_FOUND', 'login without registration returns WORKER_NOT_FOUND');

        // Set PIN
        $this->cleanupWorkers[] = $workerId;
        $this->pdo->prepare("INSERT INTO worker_profiles (telegram_user_id, telegram_name, phone, role, is_admin, is_active, payment_blocked)
            VALUES (?, ?, ?, ?, 0, 1, 0)
            ON DUPLICATE KEY UPDATE telegram_name=VALUES(telegram_name), is_active=VALUES(is_active), payment_blocked=VALUES(payment_blocked)")->execute([$workerId, 'Tho Test ' . $workerId, '0909999888', 'worker']);

        $resp = mobile_handle_action($this->pdo, 'mobile_worker_set_pin', [
            'worker_id' => $workerId,
            'pin' => '1234',
            'confirm_pin' => '1234',
        ]);
        $this->expect($resp, 'success', 'worker_set_pin');
        $this->assert(!empty($resp['token']), 'set_pin returns token');

        // Login
        $resp = mobile_handle_action($this->pdo, 'mobile_worker_login', [
            'worker_id' => $workerId,
            'pin' => '1234',
        ]);
        $this->expect($resp, 'success', 'worker_login');
        $this->assert(!empty($resp['token']), 'worker_login returns token');

        // Wrong PIN
        $resp = mobile_handle_action($this->pdo, 'mobile_worker_login', [
            'worker_id' => $workerId,
            'pin' => '9999',
        ]);
        $this->expect($resp, 'error', 'worker_login_wrong_pin');
        $this->assert(($resp['code'] ?? '') === 'INVALID_PIN', 'wrong pin returns INVALID_PIN');
    }

    private function testWorkerPendingJobs(): void
    {
        echo "\n[Worker Pending Jobs]\n";
        $customer = $this->registerCustomer();
        $worker = $this->registerWorker($this->uniqueWorkerId());

        // Create a job
        $resp = mobile_handle_action($this->pdo, 'mobile_create_job', [
            'token' => $customer['token'],
            'service_type' => 'dien_lanh',
            'selected_service_name' => 'Vệ sinh máy lạnh gia đình (dưới 2HP)',
            'issue_description' => 'Test pending job',
            'customer_name' => $customer['user']['name'],
            'customer_phone' => $customer['user']['phone'],
            'address' => 'Lấp Vò, Đồng Tháp',
        ]);
        $this->expect($resp, 'success', 'create_job_for_pending_list');
        $jobId = $resp['job_id'];
        $this->cleanupJobs[] = $jobId;

        // Query pending
        $resp = mobile_handle_action($this->pdo, 'mobile_worker_jobs_pending', ['token' => $worker['token'], 'limit' => 50]);
        $this->expect($resp, 'success', 'worker_jobs_pending');
        $jobIds = array_column($resp['jobs'] ?? [], 'job_id');
        $this->assert(in_array($jobId, $jobIds, true), 'pending list contains newly created job');

        // Customer cancel it
        mobile_handle_action($this->pdo, 'mobile_customer_cancel_job', [
            'token' => $customer['token'],
            'job_id' => $jobId,
            'reason' => 'Test cleanup',
        ]);
    }

    private function testWorkerClaimAndStatusFlow(): void
    {
        echo "\n[Worker Claim & Status Flow]\n";
        $customer = $this->registerCustomer();
        $workerId = $this->uniqueWorkerId();
        $worker = $this->registerWorker($workerId);

        // Create job
        $resp = mobile_handle_action($this->pdo, 'mobile_create_job', [
            'token' => $customer['token'],
            'service_type' => 'dien_lanh',
            'selected_service_name' => 'Vệ sinh máy lạnh gia đình (dưới 2HP)',
            'issue_description' => 'Test claim flow',
            'customer_name' => $customer['user']['name'],
            'customer_phone' => $customer['user']['phone'],
            'address' => 'Lấp Vò, Đồng Tháp',
        ]);
        $jobId = $resp['job_id'];
        $this->cleanupJobs[] = $jobId;

        // Claim job: sẽ thất bại nếu worker chưa /start Telegram bot (hành vi đúng)
        $resp = mobile_handle_action($this->pdo, 'mobile_worker_claim_job', [
            'token' => $worker['token'],
            'job_id' => $jobId,
        ]);
        // Vì Telegram bị tắt trong test, claim_job sẽ thất bại ở bước DM. Chúng ta ghi nhận.
        $this->assert(($resp['status'] ?? '') === 'error' && ($resp['code'] ?? '') === 'CLAIM_FAILED', 'claim_job fails when DM blocked (expected behavior)');

        // Update status on non-owned job should fail
        $resp = mobile_handle_action($this->pdo, 'mobile_worker_update_status', [
            'token' => $worker['token'],
            'job_id' => $jobId,
            'status' => 'in_progress',
        ]);
        $this->expect($resp, 'error', 'update_status_not_assigned');
        $this->assert(($resp['code'] ?? '') === 'JOB_NOT_ASSIGNED', 'update_status returns JOB_NOT_ASSIGNED for unassigned job');

        // Cleanup
        mobile_handle_action($this->pdo, 'mobile_customer_cancel_job', [
            'token' => $customer['token'],
            'job_id' => $jobId,
            'reason' => 'Test cleanup',
        ]);
    }

    private function testEarningsAndHistory(): void
    {
        echo "\n[Worker Earnings & History]\n";
        $workerId = $this->uniqueWorkerId();
        $worker = $this->registerWorker($workerId);

        $resp = mobile_handle_action($this->pdo, 'mobile_worker_earnings', [
            'token' => $worker['token'],
            'month' => date('Y-m'),
        ]);
        $this->expect($resp, 'success', 'worker_earnings');
        $this->assert(isset($resp['total_worker_income']), 'earnings returns total_worker_income');

        $resp = mobile_handle_action($this->pdo, 'mobile_worker_history', [
            'token' => $worker['token'],
            'month' => date('Y-m'),
        ]);
        $this->expect($resp, 'success', 'worker_history');
        $this->assert(is_array($resp['jobs'] ?? []), 'history returns jobs array');
    }

    private function testPushToken(): void
    {
        echo "\n[Push Token]\n";
        $workerId = $this->uniqueWorkerId();
        $worker = $this->registerWorker($workerId);

        $resp = mobile_handle_action($this->pdo, 'mobile_register_push_token', [
            'token' => $worker['token'],
            'push_token' => 'ExponentPushToken[test123]',
            'platform' => 'android',
        ]);
        $this->expect($resp, 'success', 'register_push_token');
    }

    private function testWorkerLocation(): void
    {
        echo "\n[Worker Location]\n";
        $workerId = $this->uniqueWorkerId();
        $worker = $this->registerWorker($workerId);

        $resp = mobile_handle_action($this->pdo, 'mobile_worker_location', [
            'token' => $worker['token'],
            'lat' => 10.3574,
            'lng' => 105.5221,
        ]);
        $this->expect($resp, 'success', 'worker_location');
    }

    private function testServices(): void
    {
        echo "\n[Services Catalog]\n";
        $resp = mobile_handle_action($this->pdo, 'mobile_services', []);
        $this->expect($resp, 'success', 'mobile_services');
        $this->assert(count($resp['categories'] ?? []) > 0, 'services returns categories');
    }

    private function cleanup(): void
    {
        echo "\n[Cleanup]\n";
        foreach ($this->cleanupJobs as $jobId) {
            $this->pdo->prepare('DELETE FROM job_posts WHERE id = ?')->execute([$jobId]);
            $this->pdo->prepare('DELETE FROM job_pricing WHERE job_id = ?')->execute([$jobId]);
            $this->pdo->prepare('DELETE FROM job_claims WHERE job_id = ?')->execute([$jobId]);
            echo "  Deleted job {$jobId}\n";
        }
        foreach ($this->cleanupSessions as $token) {
            $this->pdo->prepare('DELETE FROM mobile_sessions WHERE token = ?')->execute([$token]);
            echo "  Deleted session {$token}\n";
        }
        foreach ($this->cleanupUsers as $userId) {
            $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            echo "  Deleted user {$userId}\n";
        }
        foreach ($this->cleanupWorkers as $workerId) {
            $this->pdo->prepare('DELETE FROM worker_profiles WHERE telegram_user_id = ?')->execute([$workerId]);
            echo "  Deleted worker {$workerId}\n";
        }
    }
}
