<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

define("IN_SITE", true);
require_once(__DIR__."/../core/config.php");
require_once(__DIR__."/../core/function.php");

if (!isset($DMH)) {
    $DMH = new DMH();
}

$products = $DMH->get_list("SELECT p.*, c.name as category_name FROM `products` p LEFT JOIN `product_categories` c ON c.id = p.category_id WHERE p.`status` = 1 AND p.`featured` = 1 ORDER BY p.id DESC LIMIT 60");

if (!is_array($products)) {
    $products = [];
}

$result = [];
foreach ($products as $p) {
    $result[] = [
        'id' => (int)$p['id'],
        'name' => isset($p['name']) ? (string)$p['name'] : '',
        'category' => !empty($p['category_name']) ? $p['category_name'] : (isset($p['type']) && $p['type'] == '3d' ? 'Mô hình In 3D' : 'Điện Máy & Gia Dụng'),
        'image' => isset($p['image']) ? (string)$p['image'] : '',
        'price' => isset($p['price']) ? (float)$p['price'] : 0,
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
