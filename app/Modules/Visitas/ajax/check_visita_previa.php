<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
header('Content-Type: text/plain');

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "error:Solicitud invalida.";
    exit();
}

$cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;
$fecha_visita = isset($_POST['fecha_visita']) ? $_POST['fecha_visita'] : '';
$cod_seccion = isset($_POST['cod_seccion']) ? $_POST['cod_seccion'] : null;

if ($cod_seccion === '' || strtoupper((string)$cod_seccion) === 'NULL') {
    $cod_seccion = null;
}

if ($cod_cliente <= 0 || empty($fecha_visita)) {
    echo "error:Datos incompletos.";
    exit();
}

if (!is_valid_date($fecha_visita)) {
    echo "error:Formato de fecha invalido.";
    exit();
}

$resultado = verificarVisitaPreviaService($cod_cliente, $fecha_visita, $cod_seccion);
if (!$resultado['ok']) {
    echo "error:" . $resultado['error'];
} elseif ($resultado['id_visita'] !== null && $resultado['id_visita'] > 0) {
    echo "yes:" . $resultado['id_visita'];
} else {
    echo "no";
}

exit();
