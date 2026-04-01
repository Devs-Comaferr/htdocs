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
header('Content-Type: text/html; charset=utf-8');
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

$detalle_visita_error = '';
$row = null;
$id_visita = isset($_GET['id_visita']) ? intval($_GET['id_visita']) : 0;

if ($id_visita <= 0) {
    $detalle_visita_error = "No se ha especificado la visita.";
} else {
    $row = obtenerDetalleVisitaPorId($id_visita);
    if (!$row) {
        $detalle_visita_error = "No se encontraron detalles para la visita.";
    }
}

require_once BASE_PATH . '/app/Modules/Visitas/views/detalle_visita.php';
