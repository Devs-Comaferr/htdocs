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
require_once BASE_PATH . '/app/Modules/Planificador/planificacion_service.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y sanitizar los datos del formulario
    $cod_zona = isset($_POST['cod_zona']) ? intval($_POST['cod_zona']) : 0;
    $cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;
    
    // Verificar si se ha seleccionado una secciÃ³n
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
    
    // Validar que se haya seleccionado un cliente
    if (empty($cod_cliente)) {
        error_log('No se ha seleccionado ningn cliente.');
        echo 'Error interno';
        return;
    }
    
    // Verificar si el cliente tiene secciones disponibles (si cod_seccion != NULL)
    if ($cod_seccion !== 'NULL') {
        // Verificar si la secciÃ³n estÃ¡ disponible
        $query_verificar = "SELECT COUNT(*) AS total FROM cmf_asignacion_zonas_clientes 
                            WHERE cod_cliente = '$cod_cliente' AND cod_seccion = '$cod_seccion'";
        $resultado_verificar = odbc_exec($conn, $query_verificar);
        if (!$resultado_verificar) {
            error_log('Error al verificar la disponibilidad de la secciÃ³n: ' . odbc_errormsg($conn));
            echo 'Error interno';
            return;
        }
        $fila_verificar = odbc_fetch_array($resultado_verificar);
        if ($fila_verificar['total'] > 0) {
            error_log('La secciÃ³n seleccionada ya estÃ¡ asignada a una zona.');
            echo 'Error interno';
            return;
        }
    }
    
    // Asignar el cliente a la zona
    $resultado = asignarClienteZonaService($cod_cliente, $cod_seccion, $cod_zona, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones);
    
    if ($resultado) {
        // Redirigir de vuelta a la pÃ¡gina de asignaciÃ³n con un mensaje de Ã©xito
        header("Location: asignacion_clientes_zonas.php?cod_zona=$cod_zona&mensaje=asignado");
        exit();
    } else {
        error_log('Error al asignar el cliente a la zona.');
        echo 'Error interno';
        return;
    }
} else {
    error_log('Mtodo de solicitud no vlido.');
    echo 'Error interno';
    return;
}
?>




