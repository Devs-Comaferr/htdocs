<?php
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

// obtener_secciones_pedidos_visitas.php
require_once BASE_PATH . '/app/Modules/Planificador/planificador_service.php';

// FunciÃ³n personalizada de JSON encoding para PHP 5.2.3
function json_encode_custom($data) {
    if (is_null($data)) {
        return 'null';
    }
    if ($data === false) {
        return 'false';
    }
    if ($data === true) {
        return 'true';
    }
    if (is_scalar($data)) {
        if (is_float($data)) {
            // Siempre usar "." para floats.
            return floatval(str_replace(",", ".", strval($data)));
        }

        if (is_string($data)) {
            static $json_replaces = array(
                "\\", "\"", "\n", "\t", "\r", "\b", "\f"
            );
            static $json_escape = array(
                '\\\\', '\\"', '\\n', '\\t', '\\r', '\\b', '\\f'
            );
            return '"' . str_replace($json_replaces, $json_escape, $data) . '"';
        } else {
            return $data;
        }
    }
    $isList = true;

    foreach ($data as $key => $value) {
        if (!is_numeric($key)) {
            $isList = false;
            break;
        }
    }

    $result = array();
    if ($isList) {
        foreach ($data as $value) {
            $result[] = json_encode_custom($value);
        }
        return '[' . join(',', $result) . ']';
    } else {
        foreach ($data as $key => $value) {
            $result[] = json_encode_custom(strval($key)) . ':' . json_encode_custom($value);
        }
        return '{' . join(',', $result) . '}';
    }
}

// Obtener cod_cliente de GET o POST
$cod_cliente = 0;
if (isset($_GET['cod_cliente'])) {
    $cod_cliente = intval($_GET['cod_cliente']);
} elseif (isset($_POST['cod_cliente'])) {
    $cod_cliente = intval($_POST['cod_cliente']);
}

if ($cod_cliente > 0) {
    $conn = db();
    
    // Obtener secciones que no estÃ¡n asignadas a ninguna zona
    $query = "
        SELECT TOP 20 sc.cod_seccion, sc.nombre 
        FROM secciones_cliente sc
        WHERE sc.cod_cliente = '$cod_cliente' 
          AND sc.cod_seccion NOT IN (
              SELECT azc.cod_seccion 
              FROM cmf_asignacion_zonas_clientes azc 
              WHERE azc.cod_cliente = sc.cod_cliente
          )
        ORDER BY sc.cod_seccion ASC
    ";
      
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        // Registrar el error para depuraciÃ³n (opcional)
        error_log("Error en obtener_secciones_pedidos_visitas.php: " . odbc_errormsg($conn));
        echo json_encode_custom(array());
        exit();
    }
    
    $secciones = array();
    while ($fila = odbc_fetch_array($resultado)) {
        $secciones[] = array(
            'cod_seccion' => $fila['cod_seccion'],
            'nombre_seccion' => $fila['nombre']
        );
    }
    
    echo json_encode_custom($secciones);
} else {
    echo json_encode_custom(array());
}
?>

