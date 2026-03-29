<?php
require_once dirname(__DIR__) . '/bootstrap/init.php';
require_once BASE_PATH . '/app/Modules/Pedidos/controllers/pedidos_todos_controller.php';

$controller = new PedidosTodosController();
$controller->handlePage();
