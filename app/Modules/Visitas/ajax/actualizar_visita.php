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
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appExitTextError('Metodo no permitido.', 405);
}

$id_visita = intval($_POST['id_visita'] ?? 0);
$fecha_visita = (string)($_POST['fecha_visita'] ?? '');
$hora_inicio_visita = (string)($_POST['hora_inicio_visita'] ?? '');
$hora_fin_visita = (string)($_POST['hora_fin_visita'] ?? '');
$observaciones = (string)($_POST['observaciones'] ?? '');
$estado_visita = normalizarEstadoVisita((string)($_POST['estado_visita'] ?? ''));

if (!actualizarVisitaService($id_visita, $fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $estado_visita)) {
    appExitTextError('No se pudo actualizar la visita.', 500, 'actualizar_visita');
}

echo 'OK';
