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
// check_visita_previa.php

// Iniciar el buffer de salida para controlar la salida
ob_start();

// Deshabilitar la visualizaciÃƒÂ³n de errores en producciÃƒÂ³n
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Establecer el tipo de contenido como texto plano
header('Content-Type: text/plain');

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';

$conn = db();

// Ruta al archivo de log
$log_file = 'logs/check_visita_previa.log';

// FunciÃƒÂ³n para registrar mensajes en el log
function log_message($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "$timestamp - $message\n", FILE_APPEND);
}

// FunciÃƒÂ³n de validaciÃƒÂ³n de fecha (compatible con PHP 5.2.3)
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
    log_message("Error: Solicitud invÃƒÂ¡lida.", $log_file);
    echo "error:Solicitud invÃƒÂ¡lida.";
    ob_end_clean(); // Limpiar el buffer
    exit();
}

// Obtener y sanitizar los datos
$cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;
$fecha_visita = isset($_POST['fecha_visita']) ? $_POST['fecha_visita'] : '';
$cod_seccion = isset($_POST['cod_seccion']) ? $_POST['cod_seccion'] : null;

// Convertir cod_seccion a NULL si estÃƒÂ¡ vacÃƒÂ­o
if ($cod_seccion === '' || strtoupper($cod_seccion) === 'NULL') {
    $cod_seccion = null;
}

log_message("Recibidos: cod_cliente=$cod_cliente, fecha_visita=$fecha_visita, cod_seccion=" . ($cod_seccion === null ? 'NULL' : $cod_seccion), $log_file);

// Validar datos
if ($cod_cliente <= 0 || empty($fecha_visita)) {
    log_message("Error: Datos incompletos.", $log_file);
    echo "error:Datos incompletos.";
    ob_end_clean();
    exit();
}

// Validar formato de fecha (YYYY-MM-DD)
if (!is_valid_date($fecha_visita)) {
    log_message("Error: Formato de fecha invÃƒÂ¡lido.", $log_file);
    echo "error:Formato de fecha invÃƒÂ¡lido.";
    ob_end_clean();
    exit();
}

// Calcular la fecha lÃƒÂ­mite (5 dÃƒÂ­as antes de fecha_visita)
$fecha_limite = date('Y-m-d', strtotime($fecha_visita . ' -5 days'));
log_message("Fecha lÃƒÂ­mite calculada: $fecha_limite", $log_file);

// Consulta para verificar si existe una visita previa en los ÃƒÂºltimos 5 dÃƒÂ­as
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

// Loguear la consulta y los parÃƒÂ¡metros
log_message("Consulta SQL: $sql", $log_file);
log_message("ParÃƒÂ¡metros: cod_cliente=$cod_cliente, fecha_limite=$fecha_limite, fecha_visita=$fecha_visita, cod_seccion=" . ($cod_seccion === null ? 'NULL' : $cod_seccion), $log_file);

// Preparar la consulta
$stmt = odbc_prepare($conn, $sql);
if (!$stmt) {
    log_message("Error: No se pudo preparar la consulta. Error: " . odbc_errormsg($conn), $log_file);
    echo "error:Error al preparar la consulta.";
    ob_end_clean();
    exit();
}

// Ajustar valores NULL para odbc_execute
$params = array(
    $cod_cliente,
    $fecha_limite,
    $fecha_visita,
    ($cod_seccion === null ? null : $cod_seccion), // NULL explÃƒÂ­cito
    ($cod_seccion === null ? null : $cod_seccion)  // NULL explÃƒÂ­cito
);

// Ejecutar la consulta con los parÃƒÂ¡metros
$exec = odbc_execute($stmt, $params);
if (!$exec) {
    log_message("Error: No se pudo ejecutar la consulta. Error: " . odbc_errormsg($conn), $log_file);
    echo "error:Error al ejecutar la consulta.";
    odbc_free_result($stmt);
    ob_end_clean();
    exit();
}

// Obtener el id_visita si existe
$id_visita = null;
if ($row = odbc_fetch_array($stmt)) {
    $id_visita = intval($row['id_visita']);
    log_message("Visita encontrada: id_visita=$id_visita", $log_file);
} else {
    log_message("No se encontrÃƒÂ³ visita previa.", $log_file);
}

odbc_free_result($stmt);
odbc_close($conn);

// Limpiar cualquier otro output
ob_end_clean();

// Respuesta
if ($id_visita !== null && $id_visita > 0) {
    echo "yes:" . $id_visita;
} else {
    echo "no";
}

exit();
