<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Pedidos/services/faltas_todos_service.php';

$conn = db();

try {
    extract(obtenerDatosFaltasTodos($conn, $_GET), EXTR_OVERWRITE);
} catch (RuntimeException $e) {
    error_log($e->getMessage());
    echo 'Error interno';
    return;
}

$pageTitle = 'Pedidos Cerrados de Todos los Clientes';
include BASE_PATH . '/resources/views/layouts/header.php';
require_once BASE_PATH . '/app/Modules/Pedidos/views/faltas_todos.php';
