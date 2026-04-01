<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
// check_visita_previa.php

// Deshabilitar la visualización de errores en producción
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Establecer el tipo de contenido como texto plano
header('Content-Type: text/plain');

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';

$conn = db();

// Función de validación de fecha (compatible con PHP 5.2.3)
function is_valid_date($date) {
    $parts = explode('-', $date);
    if (count($parts) != 3) {
        return false;
    }
    $year = intval($parts[0]);
    $month = intval($parts[1]);
    $day = intval($parts[2]);
    return checkdate($month, $day, $year);
}

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "error:Solicitud inválida.";
    exit();
}

// Obtener y sanitizar los datos
$cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;
$fecha_visita = isset($_POST['fecha_visita']) ? $_POST['fecha_visita'] : '';
$cod_seccion = isset($_POST['cod_seccion']) ? $_POST['cod_seccion'] : null;

// Convertir cod_seccion a NULL si está vacío
if ($cod_seccion === '' || strtoupper($cod_seccion) === 'NULL') {
    $cod_seccion = null;
}

// Validar datos
if ($cod_cliente <= 0 || empty($fecha_visita)) {
    echo "error:Datos incompletos.";
    exit();
}

// Validar formato de fecha (YYYY-MM-DD)
if (!is_valid_date($fecha_visita)) {
    echo "error:Formato de fecha inválido.";
    exit();
}

// Calcular la fecha límite (5 días antes de fecha_visita)
$fecha_limite = date('Y-m-d', strtotime($fecha_visita . ' -5 days'));

// Consulta para verificar si existe una visita previa en los últimos 5 días
$sql = "
SELECT TOP 1 id_visita
FROM [integral].[dbo].[cmf_visitas_comerciales]
WHERE cod_cliente = ?
  AND fecha_visita BETWEEN ? AND ?
  AND (
        (? IS NULL AND cod_seccion IS NULL)
        OR (cod_seccion = ?)
      )
ORDER BY fecha_visita DESC
";

// Preparar la consulta
$stmt = odbc_prepare($conn, $sql);
if (!$stmt) {
    echo "error:Error al preparar la consulta.";
    exit();
}

// Ajustar valores NULL para odbc_execute
$params = array(
    $cod_cliente,
    $fecha_limite,
    $fecha_visita,
    ($cod_seccion === null ? null : $cod_seccion),
    ($cod_seccion === null ? null : $cod_seccion)
);

// Ejecutar la consulta con los parámetros
$exec = odbc_execute($stmt, $params);
if (!$exec) {
    echo "error:Error al ejecutar la consulta.";
    odbc_free_result($stmt);
    exit();
}

// Obtener el id_visita si existe
$id_visita = null;
if ($row = odbc_fetch_array($stmt)) {
    $id_visita = intval($row['id_visita']);
}

odbc_free_result($stmt);

// Respuesta
if ($id_visita !== null && $id_visita > 0) {
    echo "yes:" . $id_visita;
} else {
    echo "no";
}

exit();
