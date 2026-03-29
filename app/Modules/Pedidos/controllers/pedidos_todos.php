<?php
require_once BASE_PATH . '/app/Modules/Pedidos/controllers/pedidos_todos_controller.php';

$controller = new PedidosTodosController();
$controller->handlePage();
