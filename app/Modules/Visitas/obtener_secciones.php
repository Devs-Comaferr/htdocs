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
require_once BASE_PATH . '/app/Support/db.php';

// obtener_secciones.php
require_once BASE_PATH . '/app/Modules/Planificacion/PlanificacionService.php';

if (isset($_GET['cod_cliente'])) {
    $cod_cliente = intval($_GET['cod_cliente']);
    
    $conn = db();
    
    // Obtener secciones que no están asignadas a ninguna zona
    $query = "SELECT sc.cod_seccion, sc.nombre 
              FROM secciones_cliente sc
              WHERE sc.cod_cliente = '$cod_cliente' 
                AND sc.cod_seccion NOT IN (
                    SELECT azc.cod_seccion 
                    FROM cmf_asignacion_zonas_clientes azc 
                    WHERE azc.cod_cliente = sc.cod_cliente
                )
              ORDER BY sc.cod_seccion ASC";
              
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        error_log('Error al obtener secciones: ' . odbc_errormsg($conn));
        echo 'Error interno';
        return;
    }
    
    $secciones = array();
    while ($fila = odbc_fetch_array($resultado)) {
        $secciones[] = $fila;
    }
    
    echo json_encode($secciones);
} else {
    echo json_encode(array());
}
?>



