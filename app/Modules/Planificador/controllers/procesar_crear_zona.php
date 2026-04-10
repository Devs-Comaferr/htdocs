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

// procesar_crear_zona.php
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';
if (!function_exists('planificadorRedirectConMensaje')) {
    function planificadorRedirectConMensaje(string $path, string $mensaje, string $estado = 'error'): void
    {
        $query = http_build_query([
            'estado' => $estado,
            'mensaje' => $mensaje,
        ]);
        header('Location: ' . $path . '?' . $query);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    planificadorRedirectConMensaje('zonas.php', 'Metodo de solicitud no valido.');
}

csrfValidateRequest('planificador.procesar_crear_zona');

$nombre_zona = isset($_POST['nombre_zona']) ? trim($_POST['nombre_zona']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$duracion_semanas = isset($_POST['duracion_semanas']) ? intval($_POST['duracion_semanas']) : 0;
$orden = isset($_POST['orden']) ? intval($_POST['orden']) : 0;

if (empty($nombre_zona) || $duracion_semanas <= 0 || $orden <= 0) {
    planificadorRedirectConMensaje('zonas.php', 'Por favor, completa todos los campos obligatorios correctamente.');
}

if (crearZonaVisitaService($nombre_zona, $descripcion, $duracion_semanas, $orden)) {
    planificadorRedirectConMensaje('zonas.php', 'Zona de visita creada exitosamente.', 'ok');
}

planificadorRedirectConMensaje('zonas.php', 'Error al crear la zona de visita. Intentalo de nuevo.');

