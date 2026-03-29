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
require_once BASE_PATH . '/app/Modules/Pedidos/services/pedidos_todos_service.php';

class PedidosTodosController
{
    private function buildParams(): array
    {
        return array(
            'cliente' => isset($_GET['cliente']) ? (string)$_GET['cliente'] : '',
            'orden' => isset($_GET['orden']) ? (string)$_GET['orden'] : null,
            'direccion' => isset($_GET['direccion']) ? (string)$_GET['direccion'] : null,
            'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
        );
    }

    private function loadPedidosTodos(): array
    {
        return obtenerPedidosTodos($this->buildParams());
    }

    public function handlePage(): void
    {
        $resultadoPedidosTodos = $this->loadPedidosTodos();

        $cliente_filtro = $resultadoPedidosTodos['cliente_filtro'];
        $orden = $resultadoPedidosTodos['orden'];
        $direccion = $resultadoPedidosTodos['direccion'];
        $direccion_invertida = $resultadoPedidosTodos['direccion_invertida'];
        $page = $resultadoPedidosTodos['page'];
        $pedidos = $resultadoPedidosTodos['pedidos'];
        $totalRecords = $resultadoPedidosTodos['totalRecords'];
        $totalPages = $resultadoPedidosTodos['totalPages'];
        $pageTitle = 'Pedidos Abiertos de todos los clientes';

        require BASE_PATH . '/app/Modules/Pedidos/pedidos_todos.php';
    }

    public function handle(): void
    {
        header('Content-Type: application/json');

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

        $resultadoPedidosTodos = $this->loadPedidosTodos();

        echo json_encode(
            array(
                'data' => $resultadoPedidosTodos['pedidos'],
                'pedidos' => $resultadoPedidosTodos['pedidos'],
                'totalPages' => $resultadoPedidosTodos['totalPages'],
                'totalRecords' => $resultadoPedidosTodos['totalRecords'],
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        exit;
    }
}
