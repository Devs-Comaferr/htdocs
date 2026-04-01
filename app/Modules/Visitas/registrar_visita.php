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

function insertarVisitaBase($conn, int $cod_cliente, $cod_seccion, int $cod_vendedor, string $fecha_visita, string $hora_inicio_visita, ?string $hora_fin_visita, ?string $observaciones): int
{
    return crearVisitaRealizada(
        $conn,
        $cod_cliente,
        $cod_seccion,
        $cod_vendedor,
        $fecha_visita,
        $hora_inicio_visita,
        $hora_fin_visita ?? $hora_inicio_visita,
        $observaciones
    );
}

function horaVisitaATotalMinutos(string $hora): int
{
    $partes = explode(':', substr($hora, 0, 5));
    return ((int)($partes[0] ?? 0) * 60) + (int)($partes[1] ?? 0);
}

function validarHorarioClienteVisita($conn, int $cod_cliente, $cod_seccion, int $inicioMinutos, int $finMinutos): bool
{
    $sql = "SELECT TOP 1 hora_inicio_manana, hora_fin_manana, hora_inicio_tarde, hora_fin_tarde
            FROM [integral].[dbo].[cmf_asignacion_zonas_clientes]
            WHERE cod_cliente = ?
              AND activo = 1";
    $params = [$cod_cliente];

    if ($cod_seccion !== null) {
        $sql .= " AND cod_seccion = ?";
        $params[] = $cod_seccion;
    } else {
        $sql .= " AND cod_seccion IS NULL";
    }

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt || !odbc_execute($stmt, $params)) {
        throw new Exception('Error al validar horario del cliente: ' . odbc_errormsg($conn));
    }

    $fila = odbc_fetch_array($stmt);
    if (!$fila) {
        return true;
    }

    $mananaInicio = !empty($fila['hora_inicio_manana']) ? horaVisitaATotalMinutos((string)$fila['hora_inicio_manana']) : null;
    $mananaFin = !empty($fila['hora_fin_manana']) ? horaVisitaATotalMinutos((string)$fila['hora_fin_manana']) : null;
    $tardeInicio = !empty($fila['hora_inicio_tarde']) ? horaVisitaATotalMinutos((string)$fila['hora_inicio_tarde']) : null;
    $tardeFin = !empty($fila['hora_fin_tarde']) ? horaVisitaATotalMinutos((string)$fila['hora_fin_tarde']) : null;

    $encajaManana = $mananaInicio !== null && $mananaFin !== null && $inicioMinutos >= $mananaInicio && $finMinutos <= $mananaFin;
    $encajaTarde = $tardeInicio !== null && $tardeFin !== null && $inicioMinutos >= $tardeInicio && $finMinutos <= $tardeFin;

    return $encajaManana || $encajaTarde;
}

function validarSolapeAgendaVisita($conn, int $cod_vendedor, string $fecha_visita, int $inicioMinutos, int $finMinutos, int $idVisitaExcluir = 0): void
{
    $sql = "SELECT hora_inicio_visita, hora_fin_visita
            FROM [integral].[dbo].[cmf_visitas_comerciales]
            WHERE cod_vendedor = ?
              AND fecha_visita = ?
              AND estado_visita IN ('Planificada','Pendiente')";
    $params = [$cod_vendedor, $fecha_visita];

    if ($idVisitaExcluir > 0) {
        $sql .= " AND id_visita <> ?";
        $params[] = $idVisitaExcluir;
    }

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt || !odbc_execute($stmt, $params)) {
        throw new Exception('Error al validar agenda del día: ' . odbc_errormsg($conn));
    }

    while ($fila = odbc_fetch_array($stmt)) {
        $inicioExistente = horaVisitaATotalMinutos((string)($fila['hora_inicio_visita'] ?? '00:00'));
        $finExistente = horaVisitaATotalMinutos((string)($fila['hora_fin_visita'] ?? '00:00'));

        if ($inicioMinutos < $finExistente && $finMinutos > $inicioExistente) {
            throw new Exception('La visita se solapa con otra existente');
        }
    }
}

$cod_venta = isset($_POST['cod_venta']) ? (int)$_POST['cod_venta'] : 0;
$cod_cliente = isset($_POST['cod_cliente']) ? (int)$_POST['cod_cliente'] : 0;
$cod_seccion = (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') ? (int)$_POST['cod_seccion'] : null;
$cod_vendedor = isset($_SESSION['codigo']) ? (int)$_SESSION['codigo'] : 0;
$fecha_visita = isset($_POST['fecha_visita']) ? (string)$_POST['fecha_visita'] : '';
$hora_inicio_visita = isset($_POST['hora_inicio_visita']) ? (string)$_POST['hora_inicio_visita'] : '';
$hora_fin_visita = (isset($_POST['hora_fin_visita']) && $_POST['hora_fin_visita'] !== '') ? (string)$_POST['hora_fin_visita'] : null;
$estado_visita = normalizarEstadoVisita((string)($_POST['estado_visita'] ?? 'Realizada'));
$observaciones = isset($_POST['observaciones']) ? escape_string_visita((string)$_POST['observaciones']) : null;
$ampliacion = (isset($_POST['ampliacion']) && (string)$_POST['ampliacion'] === '1') ? 1 : 0;
$previous_id_visita = isset($_POST['previous_id_visita']) ? (int)$_POST['previous_id_visita'] : 0;
$esVisitaManual = ($cod_venta <= 0);

if ($cod_cliente <= 0 || $cod_vendedor <= 0 || $fecha_visita === '' || $hora_inicio_visita === '') {
    header('Location: pedidos_visitas.php?msg=error_formato_fecha');
    exit();
}

if ($observaciones !== null && strlen($observaciones) > 500) {
    $observaciones = substr($observaciones, 0, 500);
}

$horaFinValidacion = $hora_fin_visita ?? $hora_inicio_visita;
$inicioMinutos = horaVisitaATotalMinutos($hora_inicio_visita);
$finMinutos = horaVisitaATotalMinutos($horaFinValidacion);

if ($finMinutos <= $inicioMinutos) {
    header('Location: pedidos_visitas.php?msg=error');
    exit();
}

try {
    odbc_autocommit($conn, false);

    if ($esVisitaManual) {
        if ($ampliacion === 1 && $previous_id_visita > 0) {
            validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos, $previous_id_visita);
            if (
                !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
            ) {
                throw new Exception('La visita está fuera del horario del cliente');
            }

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
            validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos);
            if (
                !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
            ) {
                throw new Exception('La visita está fuera del horario del cliente');
            }

            insertarVisitaBase(
                $conn,
                $cod_cliente,
                $cod_seccion,
                $cod_vendedor,
                $fecha_visita,
                $hora_inicio_visita,
                $hora_fin_visita,
                $observaciones
            );
        }
    } else {
        if ($ampliacion === 1 && $previous_id_visita > 0) {
            validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos, $previous_id_visita);
            if (
                !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
            ) {
                throw new Exception('La visita está fuera del horario del cliente');
            }

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
            validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos);
            if (
                !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
            ) {
                throw new Exception('La visita está fuera del horario del cliente');
            }

            $idVisita = insertarVisitaBase(
                $conn,
                $cod_cliente,
                $cod_seccion,
                $cod_vendedor,
                $fecha_visita,
                $hora_inicio_visita,
                $hora_fin_visita,
                $observaciones
            );

            asegurarRelacionVisitaPedido($conn, $cod_venta, 'Visita', [
                'id_visita' => $idVisita,
                'cod_cliente' => $cod_cliente,
                'cod_seccion' => $cod_seccion
            ]);
        }
    }

    recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
    odbc_commit($conn);

    header('Location: pedidos_visitas.php?msg=visita_ok');
    exit();
} catch (Exception $e) {
    odbc_rollback($conn);
    appLogTechnicalError('registrar_visita', $e->getMessage());
    header('Location: pedidos_visitas.php?msg=' . urlencode($e->getMessage()));
    exit();
} finally {
}
