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
requierePermiso('perm_planificador');
// registrar_visita_otro_dia.php
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();


// Obtener datos del formulario
$nombre_comercial = isset($_POST['nombre_comercial']) ? trim($_POST['nombre_comercial']) : '';
$cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;
$seccion_visita = isset($_POST['seccion_visita']) ? intval($_POST['seccion_visita']) : 0;
$cod_vendedor = isset($_POST['cod_vendedor']) ? intval($_POST['cod_vendedor']) : 0;
$fecha_visita = isset($_POST['fecha_visita']) ? trim($_POST['fecha_visita']) : '';
$hora_inicio_visita = isset($_POST['hora_inicio_visita']) ? trim($_POST['hora_inicio_visita']) : '';
$hora_fin_visita = isset($_POST['hora_fin_visita']) ? trim($_POST['hora_fin_visita']) : '';
$observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
$estado_visita = isset($_POST['estado_visita']) ? normalizarEstadoVisita(trim($_POST['estado_visita'])) : '';
$tipo_visita = isset($_POST['tipo_visita']) ? trim($_POST['tipo_visita']) : '';
$ampliacion = isset($_POST['ampliacion']) ? 1 : 0;
$previous_id_visita = isset($_POST['previous_id_visita']) ? intval($_POST['previous_id_visita']) : 0;

// Validar que los campos obligatorios no estÃƒÂ¡n vacÃƒÂ­os
if (empty($nombre_comercial) || $cod_cliente <= 0 || $seccion_visita <= 0 || $cod_vendedor <= 0 || empty($fecha_visita) || empty($hora_inicio_visita) || empty($hora_fin_visita)) {
    header("Location: pedidos_visitas.php?msg=error");
    exit();
}

// Validar formatos de fecha y hora
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_visita) || 
    !preg_match("/^\d{2}:\d{2}$/", $hora_inicio_visita) || 
    !preg_match("/^\d{2}:\d{2}$/", $hora_fin_visita)) {
    header("Location: pedidos_visitas.php?msg=error_formato_fecha");
    exit();
}

// Calcular la diferencia en minutos entre inicio y fin
$inicio = DateTime::createFromFormat('H:i', $hora_inicio_visita);
$fin = DateTime::createFromFormat('H:i', $hora_fin_visita);

if (!$inicio || !$fin) {
    header("Location: pedidos_visitas.php?msg=error_formato_hora");
    exit();
}

$diff = $fin->getTimestamp() - $inicio->getTimestamp();
$diff_min = $diff / 60;

// Validar diferencia mÃƒÂ­nima y mÃƒÂ¡xima
if ($diff_min < 15 || $diff_min > 300) {
    header("Location: pedidos_visitas.php?msg=error_min_tiempo");
    exit();
}

// Insertar la nueva visita en cmf_visitas_comerciales
$sql_insert_visita = "
    INSERT INTO cmf_visitas_comerciales 
    (cod_cliente, cod_seccion, cod_vendedor, fecha_visita, hora_inicio_visita, hora_fin_visita, observaciones, estado_visita, tipo_visita, event_id, cod_zona_visita)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)
";

$stmt_insert = odbc_prepare($conn, $sql_insert_visita);
if (!$stmt_insert) {
    header("Location: pedidos_visitas.php?msg=error");
    exit();
}

$params_insert = array($cod_cliente, $seccion_visita, $cod_vendedor, $fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $estado_visita, $tipo_visita);
$result_insert = odbc_execute($stmt_insert, $params_insert);

if ($result_insert) {
    header("Location: pedidos_visitas.php?msg=visita_ok");
} else {
    header("Location: pedidos_visitas.php?msg=error");
}
exit();
?>
