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
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';

requierePermiso('perm_planificador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appExitTextError('Metodo no permitido.', 405);
}

csrfValidateRequest('planificador.procesar_reiniciar_ciclos');

$fechaInicioCiclo = trim((string)($_POST['fecha_inicio_ciclo'] ?? ''));
$ordenesRaw = $_POST['ordenes'] ?? [];
$ordenes = [];

if (!is_array($ordenesRaw)) {
    $ordenesRaw = [];
}

foreach ($ordenesRaw as $codZona => $orden) {
    $ordenes[(int)$codZona] = (int)$orden;
}

$resultado = reiniciarCiclosZonasService($ordenes, $fechaInicioCiclo);
$estado = !empty($resultado['ok']) ? 'ok' : 'error';
$mensaje = isset($resultado['message']) ? (string)$resultado['message'] : 'Operacion no completada.';

$query = 'estado=' . urlencode($estado) . '&mensaje=' . urlencode($mensaje);
if ($estado !== 'ok') {
    $query .= '&modal=reiniciar_ciclos';
}

header('Location: planificador_menu.php?' . $query);
exit;
