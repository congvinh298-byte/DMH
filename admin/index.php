<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../controllers/AdminController.php';

use Controllers\AdminController;

$controller = new AdminController();
$controller->index();
