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

// procesar_asignar_cliente_zona.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Metodo de solicitud no valido.');
    planificadorRedirectZonasClientes(0, 'Metodo de solicitud no valido.');
}

csrfValidateRequest('planificador.procesar_asignar_cliente_zona');

$conn = db();
$cod_zona = isset($_POST['cod_zona']) ? intval($_POST['cod_zona']) : 0;
$cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;

if (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') {
    $cod_seccion = intval($_POST['cod_seccion']);
} else {
    $cod_seccion = 'NULL';
}

$zona_secundaria = (isset($_POST['zona_secundaria']) && $_POST['zona_secundaria'] !== '') ? intval($_POST['zona_secundaria']) : 'NULL';
$tiempo_promedio_visita = isset($_POST['tiempo_promedio_visita']) ? floatval($_POST['tiempo_promedio_visita']) : 0.0;
$preferencia_horaria = isset($_POST['preferencia_horaria']) ? addslashes($_POST['preferencia_horaria']) : '';
$frecuencia_visita = isset($_POST['frecuencia_visita']) ? addslashes($_POST['frecuencia_visita']) : '';
$observaciones = isset($_POST['observaciones']) ? addslashes($_POST['observaciones']) : '';

if (empty($cod_cliente)) {
    error_log('No se ha seleccionado ningun cliente.');
    planificadorRedirectZonasClientes($cod_zona, 'Debes seleccionar un cliente.');
}

if ($cod_seccion !== 'NULL') {
    $query_verificar = "SELECT COUNT(*) AS total FROM cmf_comerciales_clientes_zona
                        WHERE cod_cliente = '$cod_cliente' AND cod_seccion = '$cod_seccion'";
    $resultado_verificar = odbc_exec($conn, $query_verificar);
    if (!$resultado_verificar) {
        error_log('Error al verificar la disponibilidad de la seccion: ' . odbc_errormsg($conn));
        planificadorRedirectZonasClientes($cod_zona, 'No se pudo verificar la disponibilidad de la seccion.');
    }

    $fila_verificar = odbc_fetch_array($resultado_verificar);
    if (($fila_verificar['total'] ?? 0) > 0) {
        error_log('La seccion seleccionada ya esta asignada a una zona.');
        planificadorRedirectZonasClientes($cod_zona, 'La seccion seleccionada ya esta asignada a una zona.');
    }
}

$resultado = asignarClienteZonaService($cod_cliente, $cod_seccion, $cod_zona, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones);

if ($resultado) {
    planificadorRedirectZonasClientes($cod_zona, 'Cliente asignado correctamente a la zona.', 'ok');
}

error_log('Error al asignar el cliente a la zona.');
planificadorRedirectZonasClientes($cod_zona, 'Error al asignar el cliente a la zona.');




