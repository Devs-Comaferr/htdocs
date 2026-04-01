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

require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appExitTextError('Metodo no permitido.', 405);
}

csrfValidateRequest('planificador.eliminar_ruta_zona');

$cod_zona = isset($_POST['cod_zona']) ? intval($_POST['cod_zona']) : 0;
$cod_ruta = isset($_POST['cod_ruta']) ? intval($_POST['cod_ruta']) : 0;

if ($cod_zona <= 0 || $cod_ruta <= 0) {
    appExitTextError('Datos invalidos para eliminar la ruta.', 400);
}

$resultado = eliminarRutaZonaSeguraService($cod_zona, $cod_ruta);
$estado = !empty($resultado['ok']) ? 'ok' : 'error';
$mensaje = isset($resultado['message']) ? (string)$resultado['message'] : 'Operacion no completada.';

header('Location: zonas_rutas.php?cod_zona=' . urlencode((string)$cod_zona) . '&estado=' . urlencode($estado) . '&mensaje=' . urlencode($mensaje));
exit;
