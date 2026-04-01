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
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

$ui_version = 'bs5';
$ui_requires_jquery = false;

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarVisitaSimple([
        'cod_vendedor' => $codigo_vendedor,
        'cod_cliente' => isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0,
        'cod_seccion' => isset($_POST['cod_seccion']) ? intval($_POST['cod_seccion']) : 0,
        'fecha_visita' => isset($_POST['fecha_visita']) ? $_POST['fecha_visita'] : '',
        'hora_inicio_visita' => isset($_POST['hora_inicio_visita']) ? $_POST['hora_inicio_visita'] : '',
        'hora_fin_visita' => isset($_POST['hora_fin_visita']) ? $_POST['hora_fin_visita'] : '',
        'observaciones' => isset($_POST['observaciones']) ? $_POST['observaciones'] : '',
    ], 'Pendiente');

    if ($resultado['ok']) {
        header('Location: index.php?msg=visita_programada');
        exit;
    }

    $errors = $resultado['errors'];
}

require_once BASE_PATH . '/app/Modules/Visitas/views/programar_visita.php';
