<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

if (!isset($_POST['cod_pedido'])) {
    echo "ERROR: Falta el parametro 'cod_pedido'.";
    exit;
}

$cod_pedido = (int)$_POST['cod_pedido'];
if ($cod_pedido <= 0) {
    echo 'ERROR: Codigo de pedido invalido.';
    exit;
}

try {
    odbc_autocommit($conn, false);

    $cod_cliente = null;
    $cod_seccion = null;

    $sqlContexto = "
        SELECT TOP 1 vc.cod_cliente, vc.cod_seccion
        FROM [integral].[dbo].[cmf_visita_pedidos] vp
        INNER JOIN [integral].[dbo].[cmf_visitas_comerciales] vc ON vc.id_visita = vp.id_visita
        WHERE vp.cod_venta = ?
        ORDER BY vp.id_visita_pedido ASC
    ";

    $stmtContexto = odbc_prepare($conn, $sqlContexto);
    if (!$stmtContexto) {
        throw new Exception('Error al preparar contexto: ' . odbc_errormsg($conn));
    }
    if (!odbc_execute($stmtContexto, [$cod_pedido])) {
        throw new Exception('Error al obtener contexto: ' . odbc_errormsg($conn));
    }

    $ctx = odbc_fetch_array($stmtContexto);
    if ($ctx) {
        $cod_cliente = isset($ctx['cod_cliente']) ? (int)$ctx['cod_cliente'] : null;
        $cod_seccion = (array_key_exists('cod_seccion', $ctx) && $ctx['cod_seccion'] !== null && $ctx['cod_seccion'] !== '')
            ? (int)$ctx['cod_seccion']
            : null;
    }

    $sqlDelete = "
        DELETE FROM [integral].[dbo].[cmf_visita_pedidos]
        WHERE cod_venta = ?
    ";

    $stmtDelete = odbc_prepare($conn, $sqlDelete);
    if (!$stmtDelete) {
        throw new Exception('Error al preparar borrado: ' . odbc_errormsg($conn));
    }
    if (!odbc_execute($stmtDelete, [$cod_pedido])) {
        throw new Exception('Error al borrar pedido de visita: ' . odbc_errormsg($conn));
    }

    if (!is_null($cod_cliente) && $cod_cliente > 0) {
        recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
    }

    odbc_commit($conn);
    echo 'OK';
} catch (Exception $e) {
    odbc_rollback($conn);
    echo 'ERROR: ' . $e->getMessage();
}

odbc_close($conn);
