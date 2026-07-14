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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id']) || empty($_POST['status'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Thieu thong tin']);
        exit;
    }

    $id = (int)$_POST['id'];
    $status = $_POST['status'];
    $valid = ['CHO_XU_LY', 'DANG_XU_LY', 'HOAN_THANH', 'DA_HUY'];
    if (!in_array($status, $valid, true)) {
        echo json_encode(['status' => 'error', 'msg' => 'Trang thai khong hop le']);
        exit;
    }

    $don = $DMH->get_row("SELECT * FROM `dat_lich` WHERE `id` = '$id'");
    if (!$don) {
        echo json_encode(['status' => 'error', 'msg' => 'Don khong ton tai']);
        exit;
    }

    $data = ['trangthai' => $status];
    if ($status == 'DA_HUY') {
        $data['tho_id'] = 0;
    }

    $update = $DMH->update("dat_lich", $data, "`id` = '$id'");

    if ($update) {
        $textMap = ['CHO_XU_LY' => 'Cho xu ly', 'DANG_XU_LY' => 'Dang xu ly', 'HOAN_THANH' => 'Hoan thanh', 'DA_HUY' => 'Da huy'];
        echo json_encode(['status' => 'success', 'msg' => 'Da cap nhat: ' . $textMap[$status]]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Cap nhat that bai']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'msg' => 'Loi he thong: ' . $e->getMessage()]);
}
