<?php
// Module: products



function load_product_snapshot(PDO $pdo, int $productId, string $type, array $input): array
{
    $name = clean_string($input['name'] ?? $input['product_name'] ?? 'San pham', 255);
    $price = money_int($input['gia_ban'] ?? $input['price'] ?? 0);

    if ($productId > 0 && $type === 'sim') {
        foreach (['marketplace_sims', 'sims'] as $table) {
            if (!table_exists($pdo, $table)) {
                continue;
            }
            $cols = '*';
            $stmt = $pdo->prepare("SELECT {$cols} FROM " . db_ident($table) . ' WHERE id = ? LIMIT 1');
            $stmt->execute([$productId]);
            $row = $stmt->fetch();
            if ($row) {
                $simNumber = (string)($row['so_sim'] ?? $row['phone_number'] ?? $row['name'] ?? '');
                $network = (string)($row['nha_mang'] ?? $row['network'] ?? '');
                $name = trim('SIM ' . $simNumber . ($network !== '' ? ' - ' . $network : ''));
                $price = money_int($row['gia_ban'] ?? $row['price'] ?? $price);
                return ['name' => $name, 'price' => $price, 'table' => $table];
            }
        }
    }

    if ($productId > 0 && table_exists($pdo, 'products')) {
        $cols = legacy_product_columns($pdo);
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        if ($row && $cols !== []) {
            $name = (string)($row[$cols['name']] ?? $name);
            $price = money_int($row[$cols['price']] ?? $price);
            return ['name' => $name, 'price' => $price, 'table' => 'products'];
        }
    }

    if ($productId > 0 && table_exists($pdo, 'marketplace_products')) {
        $stmt = $pdo->prepare('SELECT * FROM marketplace_products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        if ($row) {
            $name = (string)($row['name'] ?? $name);
            $price = money_int($row['sale_price'] ?? $row['price'] ?? $price);
            return ['name' => $name, 'price' => $price, 'table' => 'marketplace_products'];
        }
    }

    return ['name' => $name, 'price' => $price, 'table' => 'input'];
}

function decrement_retail_stock(PDO $pdo, array $input, int $quantity)
{
    $productId = (int)($input['product_id'] ?? 0);
    $source = clean_string($input['product_source'] ?? '', 30);
    if ($productId <= 0 || $quantity <= 0) {
        return;
    }
    if ($source === 'product' && table_exists($pdo, 'products')) {
        $columns = legacy_product_columns($pdo);
        $stockColumn = $columns['stock'] ?? null;
        if ($stockColumn !== null) {
            $updated = column_exists($pdo, 'products', 'updated_at') ? ', updated_at = NOW()' : '';
            $pdo->prepare('UPDATE products SET ' . db_ident($stockColumn) . ' = GREATEST(' . db_ident($stockColumn) . ' - ?, 0)' . $updated . ' WHERE id = ?')
                ->execute([$quantity, $productId]);
        }
        return;
    }
    if ($source === 'marketplace' && table_exists($pdo, 'marketplace_products') && column_exists($pdo, 'marketplace_products', 'stock')) {
        $updated = column_exists($pdo, 'marketplace_products', 'updated_at') ? ', updated_at = NOW()' : '';
        $pdo->prepare('UPDATE marketplace_products SET stock = GREATEST(stock - ?, 0)' . $updated . ' WHERE id = ?')
            ->execute([$quantity, $productId]);
    }
}

function legacy_product_columns(PDO $pdo): array
{
    if (!table_exists($pdo, 'products')) {
        return [];
    }
    $nameCol = first_existing_column($pdo, 'products', ['name', 'ten_sp', 'product_name', 'title']);
    $priceCol = first_existing_column($pdo, 'products', ['price', 'gia_ban', 'sale_price', 'gia']);
    if ($nameCol === null || $priceCol === null) {
        return [];
    }
    return [
        'name' => $nameCol,
        'price' => $priceCol,
        'stock' => first_existing_column($pdo, 'products', ['stock_quantity', 'ton_kho', 'stock']),
        'image' => first_existing_column($pdo, 'products', ['image_url', 'hinh_anh', 'image', 'thumbnail']),
        'category' => first_existing_column($pdo, 'products', ['category', 'danh_muc', 'loai_sp']),
        'created' => first_existing_column($pdo, 'products', ['created_at', 'ngay_tao', 'date_created']),
    ];
}

function legacy_products_for_store(PDO $pdo, string $keyword = '', string $sort = '', int $limit = 200): array
{
    $cols = legacy_product_columns($pdo);
    if ($cols === []) {
        return [];
    }
    $select = [
        'id',
        db_ident($cols['name']) . ' AS name',
        db_ident($cols['price']) . ' AS price',
        ($cols['stock'] ? db_ident($cols['stock']) : '0') . ' AS stock_quantity',
        ($cols['image'] ? db_ident($cols['image']) : "''") . ' AS image_url',
        ($cols['category'] ? db_ident($cols['category']) : "'Store'") . ' AS category',
        ($cols['created'] ? db_ident($cols['created']) : 'NOW()') . ' AS created_at',
    ];
    $where = [];
    $params = [];
    if ($keyword !== '') {
        $where[] = db_ident($cols['name']) . ' LIKE ?';
        $params[] = "%{$keyword}%";
        if ($cols['category']) {
            $where[] = db_ident($cols['category']) . ' LIKE ?';
            $params[] = "%{$keyword}%";
        }
    }
    $orderCol = $sort === 'asc' || $sort === 'desc' ? db_ident($cols['price']) . ' ' . strtoupper($sort) : 'id DESC';
    $sql = 'SELECT ' . implode(', ', $select) . ' FROM products'
        . ($where ? ' WHERE (' . implode(' OR ', $where) . ')' : '')
        . ' ORDER BY ' . $orderCol . ' LIMIT ' . max(1, min(500, $limit));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $price = money_int($row['price'] ?? 0);
        $items[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'price' => $price,
            'gia_ban_fm' => fmt_money($price),
            'stock_quantity' => (int)($row['stock_quantity'] ?? 0),
            'image' => (string)$row['image_url'] ?? '',
            'image_url' => (string)$row['image_url'] ?? '',
            'category' => (string)($row['category'] ?? 'Store'),
            'created_at' => (string)$row['created_at'] ?? '',
            'src' => 'product',
        ];
    }
    return $items;
}

function marketplace_products_for_store(PDO $pdo, string $keyword = '', string $sort = '', int $limit = 200): array
{
    if (!table_exists($pdo, 'marketplace_products')) {
        return [];
    }
    $where = [];
    $params = [];
    if (column_exists($pdo, 'marketplace_products', 'status')) {
        $where[] = "status IN ('active','sold','draft')";
    }
    if ($keyword !== '') {
        if (column_exists($pdo, 'marketplace_products', 'description')) {
            $where[] = '(name LIKE ? OR description LIKE ?)';
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        } else {
            $where[] = 'name LIKE ?';
            $params[] = "%{$keyword}%";
        }
    }
    $order = $sort === 'asc' ? 'price ASC' : ($sort === 'desc' ? 'price DESC' : 'id DESC');
    $sql = 'SELECT * FROM marketplace_products'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY ' . $order . ' LIMIT ' . max(1, min(500, $limit));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $price = money_int($row['sale_price'] ?? $row['price'] ?? 0);
        $image = (string)($row['image_url'] ?? '');
        if ($image === '') {
            $images = json_decode((string)($row['images'] ?? ''), true);
            if (is_array($images) && isset($images[0])) {
                $image = (string)$images[0];
            }
        }
        $items[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'description' => (string)($row['description'] ?? ''),
            'price' => $price,
            'gia_ban_fm' => fmt_money($price),
            'stock_quantity' => (int)($row['stock'] ?? 0),
            'image' => $image,
            'image_url' => $image,
            'category' => (string)($row['type'] ?? 'Marketplace'),
            'created_at' => (string)($row['created_at'] ?? ''),
            'src' => 'marketplace',
        ];
    }
    return $items;
}

function admin_product_target(PDO $pdo): string
{
    return legacy_product_columns($pdo) !== [] ? 'products' : 'marketplace_products';
}

function save_admin_product(PDO $pdo, array $input): int
{
    $id = (int)($input['id'] ?? 0);
    $name = clean_string($input['name'] ?? '', 255);
    if ($name === '') {
        json_out(['status' => 'error', 'message' => 'Ten san pham khong duoc trong.'], 400);
    }

    $price = money_int($input['price'] ?? 0);
    $stock = max(0, (int)($input['stock'] ?? $input['stock_quantity'] ?? 0));
    $category = clean_string($input['category'] ?? '', 120);
    $image = clean_string($input['image_url'] ?? $input['image'] ?? '', 700);

    $legacy = legacy_product_columns($pdo);
    if ($legacy !== []) {
        $values = [
            $legacy['name'] => $name,
            $legacy['price'] => $price,
        ];
        if (!empty($legacy['stock'])) {
            $values[$legacy['stock']] = $stock;
        }
        if (!empty($legacy['category'])) {
            $values[$legacy['category']] = $category;
        }
        if (!empty($legacy['image'])) {
            $values[$legacy['image']] = $image;
        }
        if ($id > 0) {
            update_compat($pdo, 'products', $values, 'id = ?', [$id], ['updated_at' => 'NOW()']);
            return $id;
        }
        return insert_compat($pdo, 'products', $values, ['created_at' => 'NOW()']);
    }

    if (!table_exists($pdo, 'marketplace_products')) {
        json_out(['status' => 'error', 'message' => 'Khong tim thay bang san pham thuc te.'], 500);
    }
    $type = $category !== '' ? $category : 'dien_may';
    if (!column_allows_value($pdo, 'marketplace_products', 'type', $type)) {
        $type = column_allows_value($pdo, 'marketplace_products', 'type', 'dien_may') ? 'dien_may' : 'other';
    }
    $values = [
        'name' => $name,
        'price' => $price,
        'sale_price' => $price,
        'stock' => $stock,
        'type' => $type,
        'status' => 'active',
    ];
    if ($image !== '' && column_exists($pdo, 'marketplace_products', 'images')) {
        $values['images'] = json_encode([$image], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($id > 0) {
        update_compat($pdo, 'marketplace_products', $values, 'id = ?', [$id], ['updated_at' => 'NOW()']);
        return $id;
    }
    return insert_compat($pdo, 'marketplace_products', $values, ['created_at' => 'NOW()']);
}

function products_for_store(PDO $pdo, array $input): array
{
    $keyword = clean_string($input['keyword'] ?? '', 100);
    $sort = clean_string($input['sort'] ?? '', 20);
    $items = [];

    $items = array_merge($items, legacy_products_for_store($pdo, $keyword, $sort, 200));

    $items = array_merge($items, marketplace_products_for_store($pdo, $keyword, $sort, 200));

    foreach (['marketplace_sims', 'sims'] as $table) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $where = column_exists($pdo, $table, 'status') ? " WHERE status NOT IN ('hidden','sold','reserved','deleted')" : '';
        $stmt = $pdo->query('SELECT * FROM ' . db_ident($table) . $where . ' ORDER BY id DESC LIMIT 220');
        foreach ($stmt->fetchAll() as $row) {
            $number = (string)($row['so_sim'] ?? $row['phone_number'] ?? '');
            if ($keyword !== '' && stripos($number, $keyword) === false) {
                continue;
            }
            $price = money_int($row['gia_ban'] ?? $row['price'] ?? 0);
            $items[] = [
                'id' => (int)$row['id'],
                'name' => $number,
                'price' => $price,
                'gia_ban_fm' => fmt_money($price),
                'category' => (string)($row['loai_sim'] ?? $row['sim_type'] ?? 'SIM'),
                'nha_mang' => (string)($row['nha_mang'] ?? $row['network'] ?? ''),
                'image' => '',
                'src' => 'sim',
            ];
        }
    }

    return $items;
}