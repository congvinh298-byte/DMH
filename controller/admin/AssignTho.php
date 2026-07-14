<?php
define("IN_SITE", true);
require_once(__DIR__."/../../core/config.php");
require_once(__DIR__."/../../core/function.php");

header('Content-Type: application/json');

try {
    // Cho phep admin da dang nhap qua session hoac co cookie token hop le
    $isAdminSession = !empty($_SESSION['loginadmin']);
    $isValidToken = isset($_COOKIE['token']) && !empty($getUser) && in_array($getUser['level'], ['admin','bct'], true);
    if (!$isAdminSession && !$isValidToken) {
        echo json_encode(['status' => 'error', 'msg' => 'Khong co quyen']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id']) || empty($_POST['tho_id'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Thieu thong tin']);
        exit;
    }

    $id = (int)$_POST['id'];
    $tho_id = (int)$_POST['tho_id'];

    $tho = $DMH->get_row("SELECT id FROM `users` WHERE `id` = '$tho_id' AND `level` = 'tho'");
    if (!$tho) {
        echo json_encode(['status' => 'error', 'msg' => 'Tho khong ton tai']);
        exit;
    }

    $don = $DMH->get_row("SELECT * FROM `dat_lich` WHERE `id` = '$id' AND `trangthai` IN ('CHO_XU_LY','DANG_XU_LY')");
    if (!$don) {
        echo json_encode(['status' => 'error', 'msg' => 'Don khong kha dung']);
        exit;
    }

    $update = $DMH->update("dat_lich", [
        'trangthai' => 'DANG_XU_LY',
        'tho_id' => $tho_id
    ], "`id` = '$id'");

    if ($update) {
        echo json_encode(['status' => 'success', 'msg' => 'Da giao don cho tho.']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Cap nhat that bai.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'msg' => 'Loi he thong: ' . $e->getMessage()]);
}
