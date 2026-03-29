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

$conn = db();

if (!isset($_POST['cod_cliente']) || empty($_POST['cod_cliente'])) {
    appExitTextError('Falta el codigo del cliente.', 400);
}

$cod_cliente = intval($_POST['cod_cliente']);
if (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') {
    $cod_seccion = intval($_POST['cod_seccion']);
} else {
    $cod_seccion = null;
}

$hora_inicio_manana = trim((string)($_POST['hora_inicio_manana'] ?? ''));
$hora_fin_manana = trim((string)($_POST['hora_fin_manana'] ?? ''));
$hora_inicio_tarde = trim((string)($_POST['hora_inicio_tarde'] ?? ''));
$hora_fin_tarde = trim((string)($_POST['hora_fin_tarde'] ?? ''));

$whereClause = "cod_cliente = ?";
$params = [$hora_inicio_manana, $hora_fin_manana, $hora_inicio_tarde, $hora_fin_tarde, $cod_cliente];
if ($cod_seccion !== null) {
    $whereClause .= " AND cod_seccion = ?";
    $params[] = $cod_seccion;
} else {
    $whereClause .= " AND cod_seccion IS NULL";
}

$sql_update = "UPDATE [integral].[dbo].[cmf_asignacion_zonas_clientes]
               SET hora_inicio_manana = ?,
                   hora_fin_manana = ?,
                   hora_inicio_tarde = ?,
                   hora_fin_tarde = ?
               WHERE $whereClause";

$stmt = odbc_prepare($conn, $sql_update);
$result = $stmt ? odbc_execute($stmt, $params) : false;
if (!$result) {
    appExitTextError('No se pudo actualizar el horario.', 500, 'definir_horario', odbc_errormsg($conn) ?: odbc_errormsg());
}

echo 'OK - Horario actualizado';
odbc_close($conn);
