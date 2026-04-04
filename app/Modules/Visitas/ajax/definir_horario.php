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
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Support/security.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    appExitTextError('Metodo no permitido.', 405, 'visitas.definir_horario.metodo');
}

csrfValidateRequest('visitas.definir_horario');

if (!isset($_POST['cod_cliente']) || empty($_POST['cod_cliente'])) {
    appExitTextError('Falta el codigo del cliente.', 400);
}

$cod_cliente = intval($_POST['cod_cliente']);
$cod_seccion = (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') ? intval($_POST['cod_seccion']) : null;
$hora_inicio_manana = trim((string)($_POST['hora_inicio_manana'] ?? ''));
$hora_fin_manana = trim((string)($_POST['hora_fin_manana'] ?? ''));
$hora_inicio_tarde = trim((string)($_POST['hora_inicio_tarde'] ?? ''));
$hora_fin_tarde = trim((string)($_POST['hora_fin_tarde'] ?? ''));

if ($cod_cliente <= 0) {
    appExitTextError('Codigo de cliente invalido.', 400, 'visitas.definir_horario.cliente');
}

if ($cod_seccion !== null && $cod_seccion <= 0) {
    appExitTextError('Codigo de seccion invalido.', 400, 'visitas.definir_horario.seccion');
}

$horaValida = static function (string $hora): bool {
    return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora);
};

if (
    !$horaValida($hora_inicio_manana)
    || !$horaValida($hora_fin_manana)
    || !$horaValida($hora_inicio_tarde)
    || !$horaValida($hora_fin_tarde)
) {
    appExitTextError('Horario no valido.', 400, 'visitas.definir_horario.horas');
}

if ($hora_inicio_manana >= $hora_fin_manana || $hora_inicio_tarde >= $hora_fin_tarde) {
    appExitTextError('Las franjas horarias no son validas.', 400, 'visitas.definir_horario.franjas');
}

if (!actualizarHorarioVisitaService($cod_cliente, $cod_seccion, $hora_inicio_manana, $hora_fin_manana, $hora_inicio_tarde, $hora_fin_tarde)) {
    appExitTextError('No se pudo actualizar el horario.', 500, 'definir_horario');
}

echo 'OK - Horario actualizado';
