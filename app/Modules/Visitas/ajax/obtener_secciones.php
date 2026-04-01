<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';

if (isset($_GET['cod_cliente'])) {
    $cod_cliente = intval($_GET['cod_cliente']);

    try {
        $secciones = obtenerSeccionesDisponiblesVisita($cod_cliente, false, false);
        echo json_encode($secciones);
    } catch (Exception $e) {
        error_log('Error al obtener secciones: ' . $e->getMessage());
        echo 'Error interno';
    }
} else {
    echo json_encode(array());
}
?>
