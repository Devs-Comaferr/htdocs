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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appExitTextError('Metodo no permitido.', 405);
}

csrfValidateRequest('clientes.pasar_historico');

$pedido = isset($_POST['pedido']) ? intval($_POST['pedido']) : 0;
$cod_cliente = isset($_POST['cod_cliente']) ? trim((string)$_POST['cod_cliente']) : '';
$cod_seccion = isset($_POST['cod_seccion']) ? trim((string)$_POST['cod_seccion']) : '';

if ($pedido <= 0) {
    appExitTextError("El parametro 'pedido' es obligatorio y debe ser valido.", 400);
}

$sqlCheck = "SELECT TOP 1 id_solicitud FROM cmf_solicitudes_pedido WHERE cod_venta = ? AND tipo_solicitud = 'Historico'";
$stmtCheck = odbc_prepare($conn, $sqlCheck);
if (!$stmtCheck || !odbc_execute($stmtCheck, [$pedido])) {
    appExitTextError('No se pudo validar la solicitud existente.', 500, 'pasarHistorico.check', odbc_errormsg($conn));
}

if (odbc_fetch_row($stmtCheck)) {
    header('Location: pedidos_todos.php?cod_cliente=' . urlencode($cod_cliente) . ($cod_seccion !== '' ? '&cod_seccion=' . urlencode($cod_seccion) : ''));
    exit();
}

$solicitante = isset($_SESSION['nombre']) ? (string)$_SESSION['nombre'] : '';
$tipo_solicitud = 'Historico';
$sqlInsert = "INSERT INTO cmf_solicitudes_pedido (solicitante, cod_venta, tipo_solicitud) VALUES (?, ?, ?)";
$stmtInsert = odbc_prepare($conn, $sqlInsert);

if ($stmtInsert && odbc_execute($stmtInsert, [$solicitante, $pedido, $tipo_solicitud])) {
    header('Location: pedidos_todos.php?cod_cliente=' . urlencode($cod_cliente) . ($cod_seccion !== '' ? '&cod_seccion=' . urlencode($cod_seccion) : ''));
    exit();
}

appExitTextError('No se pudo registrar la solicitud.', 500, 'pasarHistorico.insert', odbc_errormsg($conn));
