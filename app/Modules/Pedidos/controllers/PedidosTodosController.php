<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Pedidos/services/PedidosTodosService.php';

$params = array(
    'cliente' => isset($_GET['cliente']) ? (string)$_GET['cliente'] : '',
    'orden' => isset($_GET['orden']) ? (string)$_GET['orden'] : null,
    'direccion' => isset($_GET['direccion']) ? (string)$_GET['direccion'] : null,
    'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
);

$resultadoPedidosTodos = obtenerPedidosTodos($params);
$conn = $resultadoPedidosTodos['conn'];
$cliente_filtro = $resultadoPedidosTodos['cliente_filtro'];
$orden = $resultadoPedidosTodos['orden'];
$direccion = $resultadoPedidosTodos['direccion'];
$direccion_invertida = $resultadoPedidosTodos['direccion_invertida'];
$page = $resultadoPedidosTodos['page'];
$pedidos = $resultadoPedidosTodos['pedidos'];
$totalRecords = $resultadoPedidosTodos['totalRecords'];
$totalPages = $resultadoPedidosTodos['totalPages'];
$pageTitle = 'Pedidos Abiertos de todos los clientes';

if (!function_exists('buildPedidoUrlPedidos')) {
    function buildPedidoUrlPedidos($pedido) {
        $params = array(
            'cod_cliente' => $pedido['cod_cliente'],
            'pedido' => $pedido['Pedido'],
        );

        if (isset($pedido['Cod_Seccion']) && $pedido['Cod_Seccion'] !== '' && $pedido['Cod_Seccion'] !== null) {
            $params['cod_seccion'] = $pedido['Cod_Seccion'];
        }

        return 'pedido.php?' . http_build_query($params);
    }
}

include BASE_PATH . '/resources/views/layouts/header.php';
require BASE_PATH . '/app/Modules/Pedidos/pedidos_todos.php';

if ($conn) {
    odbc_close($conn);
}
