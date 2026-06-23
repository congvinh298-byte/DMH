<?php
$_GET['action'] = 'vendor_get_orders';
$_SERVER['REQUEST_METHOD'] = 'POST';

$pdo = new PDO('mysql:host=localhost;dbname=choxalapvo;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT login_key, status, id FROM marketplace_stores');
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($stores)) {
    // Pick the first active store
    $activeKey = null;
    foreach ($stores as $s) {
        if ($s['status'] === 'active') {
            $activeKey = $s['login_key'];
            break;
        }
    }
    
    if ($activeKey) {
        // Mock the input stream
        $input = ['login_key' => $activeKey];
        file_put_contents('php://memory', json_encode($input));
        $_POST = $input;
        // The API uses $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        // So we can mock $_POST, but we can also just let it run.
        // Wait, if it requires 'php://input', it will fail in CLI if we don't mock it nicely.
        // Let's just define a fake file_get_contents? No, let's just include the function definition part.
        
        // Let's bypass requiring api_master and just redefine the function here or mock it better.
        // Actually, just let's see if api_master.php throws error.
        
    }
}
