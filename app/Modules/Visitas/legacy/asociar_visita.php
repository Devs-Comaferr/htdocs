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


$conn = db();

$cod_venta = isset($_POST['cod_venta']) ? intval($_POST['cod_venta']) : 0;
$origen = isset($_POST['origen']) ? (string)$_POST['origen'] : '';
$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;

if ($cod_venta <= 0 || $origen === '') {
    appExitTextError('Parametros no validos.', 400);
}

$sql_visita = "
SELECT v.cod_visita
FROM cmf_visitas_comerciales v
JOIN hist_ventas_cabecera h ON v.cod_cliente = h.cod_cliente
WHERE h.cod_venta = ? AND v.fecha_visita IS NOT NULL
ORDER BY v.fecha_visita DESC
LIMIT 1
";

$stmt = odbc_prepare($conn, $sql_visita);
if (!$stmt) {
    appExitTextError('No se pudo consultar la visita.', 500, 'asociar_visita.sql_visita.prepare', odbc_errormsg($conn));
}

if (!odbc_execute($stmt, [$cod_venta])) {
    appExitTextError('No se pudo consultar la visita.', 500, 'asociar_visita.sql_visita.execute', odbc_errormsg($conn));
}

$visita = odbc_fetch_array($stmt);
odbc_free_result($stmt);

if ($visita) {
    $sql_asociar = "
    INSERT INTO cmf_visita_pedidos (cod_visita, cod_venta, origen)
    VALUES (?, ?, ?)
    ";

    $stmt = odbc_prepare($conn, $sql_asociar);
    if (!$stmt) {
        appExitTextError('No se pudo asociar el pedido.', 500, 'asociar_visita.asociar_existente.prepare', odbc_errormsg($conn));
    }

    if (!odbc_execute($stmt, [$visita['cod_visita'], $cod_venta, $origen])) {
        appExitTextError('No se pudo asociar el pedido.', 500, 'asociar_visita.asociar_existente.execute', odbc_errormsg($conn));
    }

    echo 'Pedido asociado a visita existente: ' . $origen;
} else {
    $fecha_visita = date('Y-m-d H:i:s');
    $sql_crear_visita = "
    INSERT INTO cmf_visitas_comerciales (cod_cliente, cod_vendedor, origen, fecha_visita)
    VALUES (
        (SELECT cod_cliente FROM hist_ventas_cabecera WHERE cod_venta = ? LIMIT 1),
        ?, ?, ?
    )";

    $stmt = odbc_prepare($conn, $sql_crear_visita);
    if (!$stmt) {
        appExitTextError('No se pudo crear la visita.', 500, 'asociar_visita.crear_visita.prepare', odbc_errormsg($conn));
    }

    if (!odbc_execute($stmt, [$cod_venta, $codigo_vendedor, $origen, $fecha_visita])) {
        appExitTextError('No se pudo crear la visita.', 500, 'asociar_visita.crear_visita.execute', odbc_errormsg($conn));
    }

    $cod_visita = odbc_insert_id($conn);

    $sql_asociar = "
    INSERT INTO cmf_visita_pedidos (cod_visita, cod_venta, origen)
    VALUES (?, ?, ?)
    ";

    $stmt = odbc_prepare($conn, $sql_asociar);
    if (!$stmt) {
        appExitTextError('No se pudo asociar el pedido.', 500, 'asociar_visita.asociar_nueva.prepare', odbc_errormsg($conn));
    }

    if (!odbc_execute($stmt, [$cod_visita, $cod_venta, $origen])) {
        appExitTextError('No se pudo asociar el pedido.', 500, 'asociar_visita.asociar_nueva.execute', odbc_errormsg($conn));
    }

    echo 'Nueva visita creada y asociada al pedido: ' . $origen;
}

