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

require_once BASE_PATH . '/app/Modules/Planificacion/PlanificacionService.php';

if (!isset($_POST['cod_cliente'], $_POST['cod_zona'], $_POST['cod_seccion'], $_POST['frecuencia_visita'], $_POST['tiempo_promedio_visita'], $_POST['preferencia_horaria'])) {
    appExitTextError('Error: Datos insuficientes para actualizar la asignacion.', 400);
}

$cod_cliente = intval($_POST['cod_cliente']);
$cod_zona = intval($_POST['cod_zona']);
$cod_seccion = isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== 'NULL' ? intval($_POST['cod_seccion']) : null;
$zona_secundaria = !empty($_POST['zona_secundaria']) ? intval($_POST['zona_secundaria']) : null;
$frecuencia_visita = (string)$_POST['frecuencia_visita'];
$tiempo_promedio_visita = floatval($_POST['tiempo_promedio_visita']);
$preferencia_horaria = (string)$_POST['preferencia_horaria'];
$observaciones = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : null;

if (empty($frecuencia_visita)) {
    appExitTextError('Error: Datos invalidos para actualizar la asignacion.', 400);
}

$query = "
    UPDATE cmf_asignacion_zonas_clientes
    SET
        zona_secundaria = ?,
        frecuencia_visita = ?,
        tiempo_promedio_visita = ?,
        preferencia_horaria = ?,
        observaciones = ?
    WHERE cod_cliente = ?
    AND zona_principal = ?
    AND cod_seccion " . ($cod_seccion !== null ? "= ?" : "IS NULL");

$params = [$zona_secundaria, $frecuencia_visita, $tiempo_promedio_visita, $preferencia_horaria, $observaciones, $cod_cliente, $cod_zona];
if ($cod_seccion !== null) {
    $params[] = $cod_seccion;
}

$stmt = odbc_prepare($conn, $query);
$resultado = $stmt ? odbc_execute($stmt, $params) : false;

if (!$resultado) {
    appExitTextError('No se pudo actualizar la asignacion.', 500, 'actualizar_asignacion', odbc_errormsg($conn));
}

header("Location: asignacion_clientes_zonas.php?cod_zona=$cod_zona");
exit;
