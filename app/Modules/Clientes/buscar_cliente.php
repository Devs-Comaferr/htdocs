<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';

$conn = db();

// buscar_cliente.php
header('Content-Type: application/json');

// Función personalizada de JSON encoding para PHP 5.2.3
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

$term = isset($_GET['term']) ? $_GET['term'] : '';

if ($term === '') {
    echo json_encode_custom(array());
    exit();
}

// Prepara y ejecuta la consulta de búsqueda
// Utilizar consultas preparadas para prevenir inyección SQL
$sql = "SELECT cod_cliente, nombre_comercial FROM clientes WHERE nombre_comercial LIKE ? ORDER BY nombre_comercial ASC";
$stmt = odbc_prepare($conn, $sql);
$searchTerm = "%" . $term . "%";
if (odbc_execute($stmt, array($searchTerm))) {
    $result = array();
    while ($row = odbc_fetch_array($stmt)) {
        $result[] = array(
            'label' => $row['nombre_comercial'],
            'value' => $row['nombre_comercial'],
            'cod_cliente' => $row['cod_cliente']
        );
    }
    echo json_encode_custom($result);
} else {
    echo json_encode_custom(array());
}

odbc_free_result($stmt);
odbc_close($conn);
?>
