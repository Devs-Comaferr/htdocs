<?php
header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/bootstrap/init.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['codigo']) && empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(
        array(
            'data' => array(),
            'pedidos' => array(),
            'totalPages' => 0,
            'totalRecords' => 0,
            'error' => 'unauthorized',
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Modules/Pedidos/services/PedidosTodosService.php';

$params = array(
    'cliente' => isset($_GET['cliente']) ? (string)$_GET['cliente'] : '',
    'orden' => isset($_GET['orden']) ? (string)$_GET['orden'] : null,
    'direccion' => isset($_GET['direccion']) ? (string)$_GET['direccion'] : null,
    'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
);

$resultadoPedidosTodos = obtenerPedidosTodos($params);

$payload = array(
    'data' => $resultadoPedidosTodos['pedidos'],
    'pedidos' => $resultadoPedidosTodos['pedidos'],
    'totalPages' => $resultadoPedidosTodos['totalPages'],
    'totalRecords' => $resultadoPedidosTodos['totalRecords'],
);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

if (!empty($resultadoPedidosTodos['conn'])) {
    odbc_close($resultadoPedidosTodos['conn']);
}

exit;
