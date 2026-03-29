<?php
require_once dirname(__DIR__) . '/bootstrap/init.php';
require_once BASE_PATH . '/app/Modules/Visitas/visitas_controller.php';

$controller = new VisitasController();
$controller->handle();
