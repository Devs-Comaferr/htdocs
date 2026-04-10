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

if (!function_exists('planificadorRedirectZonasClientes')) {
    function planificadorRedirectZonasClientes(int $cod_zona, string $mensaje, string $estado = 'error'): void
    {
        $params = [
            'estado' => $estado,
            'mensaje' => $mensaje,
        ];

        if ($cod_zona > 0) {
            $params['cod_zona'] = $cod_zona;
        }

        header('Location: zonas_clientes.php?' . http_build_query($params));
        exit;
    }
}

$cod_zona = isset($_POST['cod_zona']) ? intval($_POST['cod_zona']) : 0;

if (!isset($_SESSION['codigo'], $_POST['cod_cliente'], $_POST['cod_zona'], $_POST['cod_seccion'])) {
    planificadorRedirectZonasClientes($cod_zona, 'Acceso no autorizado o datos incompletos.');
}

csrfValidateRequest('planificador.borrar_asignacion');

$cod_cliente = intval($_POST['cod_cliente']);
$cod_seccion = ($_POST['cod_seccion'] === '' ? 'NULL' : intval($_POST['cod_seccion']));
if (borrarAsignacionService($cod_cliente, $cod_zona, $cod_seccion)) {
    planificadorRedirectZonasClientes($cod_zona, 'Eliminado con exito.', 'ok');
}

planificadorRedirectZonasClientes($cod_zona, 'No se pudo eliminar la asignacion.');

