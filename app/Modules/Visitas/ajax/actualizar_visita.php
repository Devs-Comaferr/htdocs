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
require_once BASE_PATH . '/app/Support/security.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appExitTextError('Metodo no permitido.', 405);
}

csrfValidateRequest('visitas.actualizar_visita');

$id_visita = intval($_POST['id_visita'] ?? 0);
$fecha_visita = (string)($_POST['fecha_visita'] ?? '');
$hora_inicio_visita = (string)($_POST['hora_inicio_visita'] ?? '');
$hora_fin_visita = (string)($_POST['hora_fin_visita'] ?? '');
$observaciones = trim((string)($_POST['observaciones'] ?? ''));
$estado_visita = normalizarEstadoVisita((string)($_POST['estado_visita'] ?? ''));

if ($id_visita <= 0) {
    appExitTextError('Identificador de visita no valido.', 400, 'visitas.actualizar_visita.id');
}

if (!validarFechaSQL($fecha_visita)) {
    appExitTextError('Fecha de visita no valida.', 400, 'visitas.actualizar_visita.fecha');
}

$horaValida = static function (string $hora): bool {
    return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora);
};

if (!$horaValida($hora_inicio_visita) || !$horaValida($hora_fin_visita)) {
    appExitTextError('Horario de visita no valido.', 400, 'visitas.actualizar_visita.hora');
}

if ($hora_inicio_visita >= $hora_fin_visita) {
    appExitTextError('La hora de inicio debe ser anterior a la hora de fin.', 400, 'visitas.actualizar_visita.franja');
}

if ($estado_visita === '') {
    appExitTextError('Estado de visita no valido.', 400, 'visitas.actualizar_visita.estado');
}

if (strlen($observaciones) > 1000) {
    $observaciones = substr($observaciones, 0, 1000);
}

if (!actualizarVisitaService($id_visita, $fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $estado_visita)) {
    appExitTextError('No se pudo actualizar la visita.', 500, 'actualizar_visita');
}

echo 'OK';
