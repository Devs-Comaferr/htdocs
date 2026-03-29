<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

if (!isset($_POST['cod_pedido']) || !isset($_POST['origen'])) {
    echo 'ERROR: Faltan parametros (cod_pedido y origen).';
    exit;
}

$cod_pedido = (int)$_POST['cod_pedido'];
$nuevo_origen = trim((string)$_POST['origen']);

if ($cod_pedido <= 0) {
    echo 'ERROR: Codigo de pedido invalido.';
    exit;
}
if ($nuevo_origen === '') {
    echo 'ERROR: Origen invalido.';
    exit;
}

try {
    odbc_autocommit($conn, false);

    $sqlExiste = "SELECT TOP 1 id_visita_pedido FROM [integral].[dbo].[cmf_visita_pedidos] WHERE cod_venta = ?";
    $stmtExiste = odbc_prepare($conn, $sqlExiste);
    if (!$stmtExiste) {
        throw new Exception('Error al preparar validacion de relacion visita-pedido: ' . odbc_errormsg($conn));
    }
    if (!odbc_execute($stmtExiste, [$cod_pedido])) {
        throw new Exception('Error al validar relacion visita-pedido: ' . odbc_errormsg($conn));
    }
    $existeRelacion = odbc_fetch_array($stmtExiste);
    if (!$existeRelacion) {
        throw new Exception('No se encontro relacion visita-pedido para ese codigo. Haz el cambio desde el planificador.');
    }

    $ctx = asegurarRelacionVisitaPedido($conn, $cod_pedido, $nuevo_origen);

    $origen_nuevo_lc = (string)$ctx['origen_nuevo'];
    $origen_anterior = (string)$ctx['origen_anterior'];
    $cod_cliente = (int)$ctx['cod_cliente'];
    $cod_seccion = $ctx['cod_seccion'];

    if ($cod_cliente > 0 && ($origen_nuevo_lc === 'visita' || $origen_anterior === 'visita')) {
        recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
    }

    odbc_commit($conn);
    echo 'OK';
} catch (Exception $e) {
    odbc_rollback($conn);
    echo 'ERROR: ' . $e->getMessage();
}

