<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: visita_pedido.php?msg=error');
    exit();
}

$resultado = procesarRegistroVisita([
    'cod_venta' => isset($_POST['cod_venta']) ? (int)$_POST['cod_venta'] : 0,
    'cod_cliente' => isset($_POST['cod_cliente']) ? (int)$_POST['cod_cliente'] : 0,
    'cod_seccion' => (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') ? (int)$_POST['cod_seccion'] : null,
    'cod_vendedor' => isset($_SESSION['codigo']) ? (int)$_SESSION['codigo'] : 0,
    'fecha_visita' => isset($_POST['fecha_visita']) ? (string)$_POST['fecha_visita'] : '',
    'hora_inicio_visita' => isset($_POST['hora_inicio_visita']) ? (string)$_POST['hora_inicio_visita'] : '',
    'hora_fin_visita' => (isset($_POST['hora_fin_visita']) && $_POST['hora_fin_visita'] !== '') ? (string)$_POST['hora_fin_visita'] : null,
    'estado_visita' => normalizarEstadoVisita((string)($_POST['estado_visita'] ?? 'Realizada')),
    'observaciones' => isset($_POST['observaciones']) ? escape_string_visita((string)$_POST['observaciones']) : null,
    'ampliacion' => (isset($_POST['ampliacion']) && (string)$_POST['ampliacion'] === '1') ? 1 : 0,
    'previous_id_visita' => isset($_POST['previous_id_visita']) ? (int)$_POST['previous_id_visita'] : 0,
]);

header('Location: ' . $resultado['redirect']);
exit();
