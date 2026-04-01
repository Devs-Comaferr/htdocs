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

$resultado = procesarRegistroVisitaLegacy([
    'nombre_comercial' => isset($_POST['nombre_comercial']) ? trim($_POST['nombre_comercial']) : '',
    'cod_cliente' => isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0,
    'seccion_visita' => isset($_POST['seccion_visita']) ? intval($_POST['seccion_visita']) : 0,
    'cod_vendedor' => isset($_POST['cod_vendedor']) ? intval($_POST['cod_vendedor']) : 0,
    'fecha_visita' => isset($_POST['fecha_visita']) ? trim($_POST['fecha_visita']) : '',
    'hora_inicio_visita' => isset($_POST['hora_inicio_visita']) ? trim($_POST['hora_inicio_visita']) : '',
    'hora_fin_visita' => isset($_POST['hora_fin_visita']) ? trim($_POST['hora_fin_visita']) : '',
    'observaciones' => isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '',
    'estado_visita' => isset($_POST['estado_visita']) ? normalizarEstadoVisita(trim($_POST['estado_visita'])) : '',
    'tipo_visita' => isset($_POST['tipo_visita']) ? trim($_POST['tipo_visita']) : '',
    'ampliacion' => isset($_POST['ampliacion']) ? 1 : 0,
    'previous_id_visita' => isset($_POST['previous_id_visita']) ? intval($_POST['previous_id_visita']) : 0,
]);

header('Location: ' . $resultado['redirect']);
exit();
