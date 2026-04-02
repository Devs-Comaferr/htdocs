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
$isEmbedded = (string)($_GET['origen'] ?? '') === 'visita_pedido';

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
    csrfValidateRequest('visitas.editar');
    $resultadoEdicion = procesarEdicionVisita($id_visita, $_POST, intval($_SESSION['codigo']), (string)($_GET['origen'] ?? ''));
    if ($resultadoEdicion['ok']) {
        if ($isEmbedded) {
            $redirect = json_encode($resultadoEdicion['redirect'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Guardado</title></head><body>';
            echo '<script>';
            echo 'if (window.parent && typeof window.parent.onEmbeddedVisitaSaved === "function") {';
            echo 'window.parent.onEmbeddedVisitaSaved(' . $redirect . ');';
            echo '} else if (window.parent && window.parent !== window) {';
            echo 'window.parent.location.href = ' . $redirect . ';';
            echo '} else {';
            echo 'window.location.href = ' . $redirect . ';';
            echo '}';
            echo '</script>';
            echo '</body></html>';
            exit();
        }
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
