<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';

$conn = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pedidos_visitas.php?msg=error');
    exit();
}

function escape_string_visita(string $str): string
{
    return str_replace("'", "''", $str);
}

$cod_venta = isset($_POST['cod_venta']) ? (int)$_POST['cod_venta'] : 0;
$cod_cliente = isset($_POST['cod_cliente']) ? (int)$_POST['cod_cliente'] : 0;
$cod_seccion = (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') ? (int)$_POST['cod_seccion'] : null;
$cod_vendedor = isset($_POST['cod_vendedor']) ? (int)$_POST['cod_vendedor'] : 0;
$fecha_visita = isset($_POST['fecha_visita']) ? (string)$_POST['fecha_visita'] : '';
$hora_inicio_visita = isset($_POST['hora_inicio_visita']) ? (string)$_POST['hora_inicio_visita'] : '';
$hora_fin_visita = (isset($_POST['hora_fin_visita']) && $_POST['hora_fin_visita'] !== '') ? (string)$_POST['hora_fin_visita'] : null;
$observaciones = isset($_POST['observaciones']) ? escape_string_visita((string)$_POST['observaciones']) : null;
$ampliacion = (isset($_POST['ampliacion']) && (string)$_POST['ampliacion'] === '1') ? 1 : 0;
$previous_id_visita = isset($_POST['previous_id_visita']) ? (int)$_POST['previous_id_visita'] : 0;

if ($cod_venta <= 0 || $cod_cliente <= 0 || $cod_vendedor <= 0 || $fecha_visita === '' || $hora_inicio_visita === '') {
    header('Location: pedidos_visitas.php?msg=error_formato_fecha');
    exit();
}

if ($observaciones !== null && strlen($observaciones) > 500) {
    $observaciones = substr($observaciones, 0, 500);
}

try {
    odbc_autocommit($conn, false);

    if ($ampliacion === 1 && $previous_id_visita > 0) {
        asegurarRelacionVisitaPedido($conn, $cod_venta, 'Visita', [
            'id_visita' => $previous_id_visita,
            'cod_cliente' => $cod_cliente,
            'cod_seccion' => $cod_seccion
        ]);

        $sqlUpdate = "
            UPDATE [integral].[dbo].[cmf_visitas_comerciales]
            SET
                estado_visita = 'Realizada',
                fecha_visita = ?,
                hora_inicio_visita = ?,
                hora_fin_visita = ?,
                observaciones = ?
            WHERE
                id_visita = ?
                AND LOWER(estado_visita) IN ('pendiente', 'planificada')
        ";
        $stmtUpdate = odbc_prepare($conn, $sqlUpdate);
        if (!$stmtUpdate) {
            throw new Exception('Error al preparar update de visita: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtUpdate, [$fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $previous_id_visita])) {
            throw new Exception('Error al actualizar visita: ' . odbc_errormsg($conn));
        }
    } else {
        asegurarRelacionVisitaPedido($conn, $cod_venta, 'Visita', [
            'cod_cliente' => $cod_cliente,
            'cod_seccion' => $cod_seccion,
            'cod_vendedor' => $cod_vendedor,
            'fecha_visita' => $fecha_visita,
            'hora_inicio_visita' => $hora_inicio_visita,
            'hora_fin_visita' => $hora_fin_visita,
            'observaciones' => $observaciones
        ]);
    }

    recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
    odbc_commit($conn);

    header('Location: pedidos_visitas.php?msg=visita_ok');
    exit();
} catch (Exception $e) {
    odbc_rollback($conn);
    appLogTechnicalError('registrar_visita', $e->getMessage());
    header('Location: pedidos_visitas.php?msg=error');
    exit();
} finally {
}
