<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if (!isset($_POST['cod_pedido'])) {
    echo "ERROR: Falta el parametro 'cod_pedido'.";
    exit;
}

$cod_pedido = (int)$_POST['cod_pedido'];
if ($cod_pedido <= 0) {
    echo 'ERROR: Codigo de pedido invalido.';
    exit;
}

$resultado = quitarPedidoVisitaService($cod_pedido);
echo $resultado['ok'] ? 'OK' : ('ERROR: ' . $resultado['error']);
