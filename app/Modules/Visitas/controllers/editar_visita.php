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
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

$ui_version = 'bs5';
$ui_requires_jquery = false;

$id_visita = isset($_GET['id_visita']) ? intval($_GET['id_visita']) : 0;
if ($id_visita <= 0) {
    error_log('ID de visita invÃƒÂ¡lido.');
    echo 'Error interno';
    return;
}

$error = '';
$success = '';
$viewData = obtenerDatosEditarVisita($id_visita);
if ($viewData === null) {
    $error = "No se encontrÃƒÂ³ la visita especificada.";
    error_log("<div class='alert alert-danger'>$error</div>");
    echo 'Error interno';
    return;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $resultadoEdicion = procesarEdicionVisita($id_visita, $_POST, intval($_SESSION['codigo']), (string)($_GET['origen'] ?? ''));
    if ($resultadoEdicion['ok']) {
        header('Location: ' . $resultadoEdicion['redirect']);
        exit();
    }
    $error = $resultadoEdicion['error'];
    $viewData = array_merge($viewData, [
        'fecha_visita' => date('Y-m-d', strtotime(trim((string)$_POST['fecha_visita']))),
        'hora_inicio_visita' => trim((string)$_POST['hora_inicio_visita']),
        'hora_fin_visita' => trim((string)$_POST['hora_fin_visita']),
        'observaciones' => trim((string)$_POST['observaciones']),
        'estado_visita' => normalizarEstadoVisita(trim((string)$_POST['estado_visita'])),
    ]);
} else {
    $viewData['estado_visita'] = normalizarEstadoVisita($viewData['estado_visita']);
}

extract($viewData);

require_once BASE_PATH . '/app/Modules/Visitas/views/editar_visita_handler.php';
