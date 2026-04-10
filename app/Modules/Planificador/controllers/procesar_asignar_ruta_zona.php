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

// procesar_asignar_ruta_zona.php
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';
if (!function_exists('planificadorRedirectConMensaje')) {
    function planificadorRedirectConMensaje(string $path, string $mensaje, string $estado = 'error', array $extraParams = []): void
    {
        $query = http_build_query(array_merge($extraParams, [
            'estado' => $estado,
            'mensaje' => $mensaje,
        ]));
        header('Location: ' . $path . '?' . $query);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    planificadorRedirectConMensaje('zonas.php', 'Metodo de solicitud no valido.');
}

csrfValidateRequest('planificador.procesar_asignar_ruta_zona');

$cod_zona = isset($_POST['cod_zona']) ? intval($_POST['cod_zona']) : 0;
$cod_ruta = isset($_POST['cod_ruta']) ? intval($_POST['cod_ruta']) : 0;

if ($cod_zona <= 0 || $cod_ruta <= 0) {
    $destino = $cod_zona > 0 ? 'zonas_rutas.php' : 'zonas.php';
    $extraParams = $cod_zona > 0 ? ['cod_zona' => $cod_zona] : [];
    planificadorRedirectConMensaje($destino, 'Datos invalidos para la asignacion de la ruta.', 'error', $extraParams);
}

if (asignarRutaZonaService($cod_zona, $cod_ruta)) {
    planificadorRedirectConMensaje('zonas_rutas.php', 'Ruta asignada exitosamente a la zona.', 'ok', ['cod_zona' => $cod_zona]);
}

planificadorRedirectConMensaje('zonas_rutas.php', 'Error al asignar la ruta a la zona. Intentalo de nuevo.', 'error', ['cod_zona' => $cod_zona]);

