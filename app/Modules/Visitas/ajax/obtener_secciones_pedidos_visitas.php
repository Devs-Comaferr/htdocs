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
require_once BASE_PATH . '/app/Modules/Visitas/services/registrar_visita_handler.php';
require_once BASE_PATH . '/app/Modules/Planificador/planificador_service.php';

$cod_cliente = 0;
if (isset($_GET['cod_cliente'])) {
    $cod_cliente = intval($_GET['cod_cliente']);
} elseif (isset($_POST['cod_cliente'])) {
    $cod_cliente = intval($_POST['cod_cliente']);
}

if ($cod_cliente > 0) {
    try {
        $secciones = obtenerSeccionesDisponiblesVisita($cod_cliente, true, true);
        echo visitasJsonEncodeCustom($secciones);
    } catch (Exception $e) {
        error_log("Error en obtener_secciones_pedidos_visitas.php: " . $e->getMessage());
        echo visitasJsonEncodeCustom(array());
        exit();
    }
} else {
    echo visitasJsonEncodeCustom(array());
}
?>
