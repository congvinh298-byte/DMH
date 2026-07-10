<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../controllers/AuthController.php';

use Controllers\AuthController;

$controller = new AuthController();
$controller->guest();
