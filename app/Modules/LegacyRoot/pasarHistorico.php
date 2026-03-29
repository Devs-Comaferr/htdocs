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

require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

if (!isset($_GET['pedido']) || intval($_GET['pedido']) <= 0) {
    appExitTextError("El parametro 'pedido' es obligatorio y debe ser valido.", 400);
}

$pedido = intval($_GET['pedido']);
$cod_cliente = isset($_GET['cod_cliente']) ? $_GET['cod_cliente'] : '';
$cod_seccion = isset($_GET['cod_seccion']) ? $_GET['cod_seccion'] : '';

$sql_check = "SELECT TOP 1 id_solicitud FROM cmf_solicitudes_pedido WHERE cod_venta = '" . addslashes($pedido) . "' AND tipo_solicitud = 'Historico'";
$rs_check = odbc_exec($conn, $sql_check);
if ($rs_check && odbc_fetch_row($rs_check)) {
    header('Location: pedidos_todos.php?cod_cliente=' . urlencode($cod_cliente) . ($cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : ''));
    exit();
}

$solicitante = $_SESSION['nombre'];
$tipo_solicitud = 'Historico';
$sql_insert = "INSERT INTO cmf_solicitudes_pedido (solicitante, cod_venta, tipo_solicitud) VALUES ('" . addslashes($solicitante) . "', '" . addslashes($pedido) . "', '" . addslashes($tipo_solicitud) . "')";
$rs_insert = odbc_exec($conn, $sql_insert);
if ($rs_insert) {
    header('Location: pedidos_todos.php?cod_cliente=' . urlencode($cod_cliente) . ($cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : ''));
    exit();
}

appExitTextError('No se pudo registrar la solicitud.', 500, 'pasarHistorico', odbc_errormsg($conn));
