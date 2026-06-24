<?php
/**
 * =========================================================================================
 * CART API - GIO HANG + CHECKOUT
 * 
 * Endpoints:
 *   POST cart_add        { product_id, quantity }
 *   POST cart_list       {}
 *   POST cart_update     { product_id, quantity }
 *   POST cart_remove     { product_id }
 *   POST cart_clear      {}
 *   POST cart_checkout   { address, phone, payment_method, note }
 * 
 * Auth: can user_id (phone OTP / JWT)
 * Payment methods: cod | vietqr | momo
 * 
 * File: api/cart.php
 * Updated: 2026-06-24
 * =========================================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/core.php';

header('Content-Type: application/json; charset=utf-8');

function cart_require_user(array $input): array {
    $userId = (int)($input['user_id'] ?? 0);
    if ($userId <= 0) {
        api_error('Vui long dang nhap de su dung gio hang', 401);
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, fullname, phone, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        api_error('Tai khoan khong ton tai', 401);
    }
    return $user;
}

function cart_load_items(PDO $pdo, int $userId): array {
    $sql = 'SELECT ci.id, ci.product_id, ci.quantity, ci.unit_price,
                   p.title, p.image_url, p.stock, p.is_active
              FROM cart_items ci
              LEFT JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = ?
             ORDER BY ci.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cart_recalculate(array $items): array {
    $subtotal = 0.0;
    $count = 0;
    foreach ($items as $it) {
        $price = (float)$it['unit_price'];
        $qty = (int)$it['quantity'];
        $subtotal += $price * $qty;
        $count += $qty;
    }
    return [
        'item_count' => $count,
        'subtotal' => $subtotal,
        'shipping_fee' => $subtotal > 0 ? 30000 : 0,
        'total' => $subtotal + ($subtotal > 0 ? 30000 : 0),
    ];
}

// === ROUTER ===
$action = $_REQUEST['action'] ?? $_REQUEST['cmd'] ?? '';
$body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$input = array_merge($_REQUEST, $body);

switch ($action) {

    // ---------------------------------------------------------------------------------
    case 'cart_add':
        $user = cart_require_user($input);
        $productId = (int)($input['product_id'] ?? 0);
        $qty = max(1, (int)($input['quantity'] ?? 1));
        if ($productId <= 0) api_error('product_id khong hop le');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, price, stock, is_active FROM products WHERE id = ? LIMIT 1');
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product || !$product['is_active']) api_error('San pham khong kha dung', 404);
            if ($qty > (int)$product['stock']) api_error('So luong vuot qua ton kho');

            $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1');
            $stmt->execute([$user['id'], $productId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $newQty = $existing['quantity'] + $qty;
                if ($newQty > (int)$product['stock']) api_error('So luong vuot qua ton kho');
                $stmt = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                $stmt->execute([$newQty, $existing['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO cart_items (user_id, product_id, quantity, unit_price) VALUES (?,?,?,?)'
                );
                $stmt->execute([$user['id'], $productId, $qty, $product['price']]);
            }
            $pdo->commit();
            api_ok(['message' => 'Da them vao gio hang']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            api_error('Loi: ' . $e->getMessage(), 500);
        }
        break;

    // ---------------------------------------------------------------------------------
    case 'cart_list':
        $user = cart_require_user($input);
        $pdo = db();
        $items = cart_load_items($pdo, $user['id']);
        $summary = cart_recalculate($items);
        api_ok([
            'items' => $items,
            'summary' => $summary,
        ]);
        break;

    // ---------------------------------------------------------------------------------
    case 'cart_update':
        $user = cart_require_user($input);
        $productId = (int)($input['product_id'] ?? 0);
        $qty = (int)($input['quantity'] ?? 0);
        if ($productId <= 0) api_error('product_id khong hop le');
        if ($qty < 0) api_error('So luong khong hop le');
        $pdo = db();
        if ($qty === 0) {
            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$user['id'], $productId]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE cart_items ci
                    JOIN products p ON p.id = ci.product_id
                    SET ci.quantity = ?
                  WHERE ci.user_id = ? AND ci.product_id = ?
                    AND ci.quantity <= p.stock'
            );
            $stmt->execute([$qty, $user['id'], $productId]);
            if ($stmt->rowCount() === 0) api_error('So luong vuot qua ton kho');
        }
        api_ok(['message' => 'Da cap nhat']);
        break;

    // ---------------------------------------------------------------------------------
    case 'cart_remove':
        $user = cart_require_user($input);
        $productId = (int)($input['product_id'] ?? 0);
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$user['id'], $productId]);
        api_ok(['message' => 'Da xoa khoi gio']);
        break;

    // ---------------------------------------------------------------------------------
    case 'cart_clear':
        $user = cart_require_user($input);
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        api_ok(['message' => 'Da xoa sach gio hang']);
        break;

    // ---------------------------------------------------------------------------------
    case 'cart_checkout':
        $user = cart_require_user($input);
        $address = clean_string($input['address'] ?? '', 500);
        $phone = clean_string($input['phone'] ?? '', 15);
        $payment = clean_string($input['payment_method'] ?? 'cod', 20);
        $note = clean_string($input['note'] ?? '', 500);
        $allowedPayments = ['cod', 'vietqr', 'momo'];
        if (!in_array($payment, $allowedPayments, true)) api_error('Phuong thuc thanh toan khong hop le');
        if ($address === '' || $phone === '') api_error('Vui long nhap dia chi va so dien thoai');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $items = cart_load_items($pdo, $user['id']);
            if (count($items) === 0) api_error('Gio hang trong');
            $summary = cart_recalculate($items);

            $orderCode = next_order_code();
            $stmt = $pdo->prepare(
                'INSERT INTO marketplace_orders
                    (order_code, buyer_id, buyer_name, buyer_phone, address,
                     subtotal, shipping_fee, total, payment_method, note, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,"cho_xac_nhan", NOW())'
            );
            $stmt->execute([
                $orderCode,
                $user['id'],
                $user['fullname'],
                $phone,
                $address,
                $summary['subtotal'],
                $summary['shipping_fee'],
                $summary['total'],
                $payment,
                $note,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO marketplace_order_items
                    (order_id, product_id, title_snapshot, unit_price, quantity)
                 VALUES (?,?,?,?,?)'
            );
            $stockStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($items as $it) {
                $itemStmt->execute([
                    $orderId,
                    (int)$it['product_id'],
                    (string)($it['title'] ?? ''),
                    (float)$it['unit_price'],
                    (int)$it['quantity'],
                ]);
                $stockStmt->execute([(int)$it['quantity'], (int)$it['product_id'], (int)$it['quantity']]);
            }

            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
            $stmt->execute([$user['id']]);

            $pdo->commit();

            trigger_async_order_notification($orderId);

            api_ok([
                'message' => 'Dat hang thanh cong',
                'order_id' => $orderId,
                'order_code' => $orderCode,
                'total' => $summary['total'],
                'payment_method' => $payment,
                'payment_url' => $payment === 'vietqr'
                    ? ('https://img.vietqr.io/image/' . (getenv('VNB_BIN') ?: 'ICB') . '-' . (getenv('VNB_ACC') ?: '') . '-compact2.png?amount=' . (int)$summary['total'] . '&addInfo=' . urlencode($orderCode))
                    : null,
            ]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            api_error('Loi checkout: ' . $e->getMessage(), 500);
        }
        break;

    // ---------------------------------------------------------------------------------
    default:
        api_error('Unknown action: ' . $action, 400);
}
