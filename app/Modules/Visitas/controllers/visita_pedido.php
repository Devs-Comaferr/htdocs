<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/pedidos_badges.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
requierePermiso('perm_planificador');

$pageTitle = "Relacionar Pedidos con Visitas";

$ui_version = 'bs5';
$ui_requires_jquery = false;

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
$fecha_minima = '2025-01-01';

try {
    $visitaPedidoData = obtenerDatosVisitaPedido($codigo_vendedor, $fecha_minima);
    $pedidos = $visitaPedidoData['pedidos'];
    $numero_lineas_map = $visitaPedidoData['numero_lineas_map'];
} catch (RuntimeException $e) {
    echo 'Error interno';
    return;
}

function formatoFecha($fechaSql)
{
    return date('Y-m-d', strtotime($fechaSql));
}

function formatoHora($horaSql)
{
    return date('H:i', strtotime($horaSql));
}

require_once BASE_PATH . '/app/Modules/Visitas/views/visita_pedido/index.php';
