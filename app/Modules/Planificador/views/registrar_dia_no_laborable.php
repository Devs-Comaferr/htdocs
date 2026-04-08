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

requierePermiso('perm_planificador');

$ui_version = 'bs5';
$ui_requires_jquery = false;
$conn = db();
$pageTitle = 'Registrar Dia No Laborable';

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
$error = '';
$success = '';
$anioActual = date('Y');
$fechaHoy = date('Y-m-d');

$tipoEventoSel = '';
$descripcionForm = '';
$fechaInicioForm = '';
$fechaFinForm = '';
$fechaFestivoForm = '';
$repetirAnualmenteForm = false;
$customEventForm = '';
$fechaEventoForm = '';
$horaInicioOtroForm = '';
$horaFinOtroForm = '';

function registrarNoLaborablePrepareExecute($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        appLogTechnicalError('registrar_dia_no_laborable.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    if (!odbc_execute($stmt, $params)) {
        appLogTechnicalError('registrar_dia_no_laborable.execute', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    return $stmt;
}

function prepararDescripcion($desc)
{
    $desc = trim((string)$desc);
    return $desc === '' ? '' : $desc;
}

function construirFechaVistaEvento(string $fechaOriginal, string $tipoEvento, string $anio, bool $repetirAnualmente): string
{
    return obtenerFechaAplicadaNoLaborable($fechaOriginal, $tipoEvento, $repetirAnualmente, $anio . '-01-01');
}

function formatearFechaVista(string $fecha): string
{
    $ts = strtotime($fecha);
    if ($ts === false) {
        return $fecha;
    }

    return date('d/m/Y', $ts);
}

function formatearHoraVista(string $hora): string
{
    $hora = trim($hora);
    if ($hora === '') {
        return '';
    }

    return substr($hora, 0, 5);
}

/**
 * Verifica si una fecha concreta es festiva.
 * 1) Comprueba si existe exactamente fecha = X con tipo_evento = Festivo y repetir_anualmente = 0
 * 2) O si existe un Festivo con repetir_anualmente = 1 que coincida en mes y día
 */
function esFestivo($fechaCheck, $conn)
{
    $sql = "
        SELECT fecha, tipo_evento, repetir_anualmente
        FROM [integral].[dbo].[cmf_comerciales_dias_no_laborables]
        WHERE tipo_evento='Festivo'
    ";
    $stmt = registrarNoLaborablePrepareExecute($conn, $sql);
    if (!$stmt) {
        return false;
    }

    while ($row = odbc_fetch_array($stmt)) {
        if (noLaborableAplicaEnFecha(
            trim((string)($row['fecha'] ?? '')),
            trim((string)($row['tipo_evento'] ?? 'Festivo')),
            intval($row['repetir_anualmente'] ?? 0) === 1,
            (string)$fechaCheck
        )) {
            return true;
        }
    }

    return false;
}

function obtenerDetalleNoLaborablesAnioActual($conn, int $codigoVendedor, string $anio): array
{
    $detalle = [
        'vacaciones' => [],
        'festivos' => [],
        'otros' => [],
    ];

    $sql = "
        SELECT
            id,
            fecha,
            hora_inicio,
            hora_fin,
            tipo_evento,
            descripcion,
            repetir_anualmente
        FROM [integral].[dbo].[cmf_comerciales_dias_no_laborables]
        WHERE cod_vendedor = ?
          AND (
              YEAR(fecha) = ?
              OR (tipo_evento = 'Festivo' AND repetir_anualmente = 1)
          )
        ORDER BY
            DATEPART(MONTH, fecha),
            DATEPART(DAY, fecha),
            hora_inicio,
            id
    ";

    $stmt = registrarNoLaborablePrepareExecute($conn, $sql, [$codigoVendedor, intval($anio)]);
    if (!$stmt) {
        return $detalle;
    }

    while ($fila = odbc_fetch_array($stmt)) {
        $tipoEvento = trim((string)($fila['tipo_evento'] ?? ''));
        $repiteAnual = intval($fila['repetir_anualmente'] ?? 0) === 1 && $tipoEvento === 'Festivo';
        $fechaOriginal = trim((string)($fila['fecha'] ?? ''));
        $fechasAplicadas = obtenerFechasAplicadasNoLaborable($fechaOriginal, $tipoEvento, $repiteAnual, $anio . '-01-01');

        $fechaBaseAplicada = obtenerFechaAplicadaNoLaborable($fechaOriginal, $tipoEvento, $repiteAnual, $anio . '-01-01');
        $esFestivoEnDomingo = esFestivoDomingo($fechaOriginal, $tipoEvento, $repiteAnual, $anio . '-01-01');

        foreach ($fechasAplicadas as $fechaAplicada) {
            $item = [
                'id' => intval($fila['id'] ?? 0),
                'fecha' => $fechaAplicada,
                'fecha_original' => $fechaOriginal,
                'hora_inicio' => trim((string)($fila['hora_inicio'] ?? '')),
                'hora_fin' => trim((string)($fila['hora_fin'] ?? '')),
                'tipo_evento' => $tipoEvento,
                'descripcion' => trim((string)($fila['descripcion'] ?? '')),
                'repetir_anualmente' => $repiteAnual,
                'es_domingo_original' => $esFestivoEnDomingo && $fechaAplicada === $fechaBaseAplicada,
            ];

            if ($tipoEvento === 'Vacaciones') {
                $detalle['vacaciones'][] = $item;
            } elseif ($tipoEvento === 'Festivo') {
                $detalle['festivos'][] = $item;
            } else {
                $detalle['otros'][] = $item;
            }
        }
    }

    foreach ($detalle as $clave => $items) {
        usort($items, function ($a, $b) {
            return strcmp((string)$a['fecha'], (string)$b['fecha']);
        });
        $detalle[$clave] = $items;
    }

    return $detalle;
}

function obtenerResumenNoLaborablesAnioActual($conn, int $codigoVendedor, string $anio): array
{
    $detalle = obtenerDetalleNoLaborablesAnioActual($conn, $codigoVendedor, $anio);

    return [
        'total' => count($detalle['vacaciones']) + count($detalle['festivos']) + count($detalle['otros']),
        'vacaciones' => count($detalle['vacaciones']),
        'festivos' => count($detalle['festivos']),
        'otros' => count($detalle['otros']),
    ];
}

function agruparVacacionesConsecutivas(array $vacaciones): array
{
    if (empty($vacaciones)) {
        return [];
    }

    usort($vacaciones, function ($a, $b) {
        $cmpFecha = strcmp((string)($a['fecha'] ?? ''), (string)($b['fecha'] ?? ''));
        if ($cmpFecha !== 0) {
            return $cmpFecha;
        }

        return strcmp((string)($a['descripcion'] ?? ''), (string)($b['descripcion'] ?? ''));
    });

    $grupos = [];

    foreach ($vacaciones as $item) {
        $fecha = (string)($item['fecha'] ?? '');
        $descripcion = trim((string)($item['descripcion'] ?? ''));

        if (empty($grupos)) {
            $grupos[] = [
                'fecha_inicio' => $fecha,
                'fecha_fin' => $fecha,
                'descripcion' => $descripcion,
                'total_dias' => 1,
            ];
            continue;
        }

        $ultimoIndice = count($grupos) - 1;
        $ultimo = $grupos[$ultimoIndice];
        $tsUltimo = strtotime((string)$ultimo['fecha_fin']);
        $tsActual = strtotime($fecha);
        $esConsecutivo = $tsUltimo !== false && $tsActual !== false && (($tsUltimo + 86400) === $tsActual);

        if ($esConsecutivo && $ultimo['descripcion'] === $descripcion) {
            $grupos[$ultimoIndice]['fecha_fin'] = $fecha;
            $grupos[$ultimoIndice]['total_dias']++;
            continue;
        }

        $grupos[] = [
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'descripcion' => $descripcion,
            'total_dias' => 1,
        ];
    }

    return $grupos;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipoEventoSel = trim((string)($_POST['tipo_evento'] ?? ''));
    $descripcionForm = trim((string)($_POST['descripcion'] ?? ''));
    $fechaInicioForm = trim((string)($_POST['fecha_inicio'] ?? ''));
    $fechaFinForm = trim((string)($_POST['fecha_fin'] ?? ''));
    $fechaFestivoForm = trim((string)($_POST['fecha_festivo'] ?? ''));
    $repetirAnualmenteForm = !empty($_POST['repetir_anualmente']);
    $customEventForm = trim((string)($_POST['custom_event'] ?? ''));
    $fechaEventoForm = trim((string)($_POST['fecha_evento'] ?? ''));
    $horaInicioOtroForm = trim((string)($_POST['hora_inicio_otro'] ?? ''));
    $horaFinOtroForm = trim((string)($_POST['hora_fin_otro'] ?? ''));

    if ($tipoEventoSel === '') {
        $error = 'Selecciona un tipo de evento.';
    } else {
        $descSql = prepararDescripcion($descripcionForm);

        if ($tipoEventoSel === 'Vacaciones') {
            if ($fechaInicioForm === '' || $fechaFinForm === '') {
                $error = 'Para vacaciones debes indicar la fecha de inicio y la fecha de fin.';
            } else {
                $tsInicio = strtotime($fechaInicioForm);
                $tsFin = strtotime($fechaFinForm);

                if ($tsInicio === false || $tsFin === false) {
                    $error = 'El formato de las fechas no es válido.';
                } elseif ($tsInicio > $tsFin) {
                    $error = 'La fecha de inicio no puede ser posterior a la fecha de fin.';
                } else {
                    $diasInsertados = 0;

                    for ($d = $tsInicio; $d <= $tsFin; $d += 86400) {
                        $diaSemana = date('w', $d);
                        if ($diaSemana == 0 || $diaSemana == 6) {
                            continue;
                        }

                        $fechaActual = date('Y-m-d', $d);
                        if (esFestivo($fechaActual, $conn)) {
                            continue;
                        }

                        if ($diaSemana == 1) {
                            $fechaAyer = date('Y-m-d', $d - 86400);
                            if (esFestivo($fechaAyer, $conn)) {
                                continue;
                            }
                        }

                        $sqlInsert = "
                            INSERT INTO [integral].[dbo].[cmf_comerciales_dias_no_laborables]
                                (cod_vendedor, fecha, tipo_evento, descripcion, repetir_anualmente)
                            VALUES (?, ?, 'Vacaciones', ?, 0)
                        ";

                        if (registrarNoLaborablePrepareExecute($conn, $sqlInsert, [intval($codigo_vendedor), $fechaActual, $descSql])) {
                            $diasInsertados++;
                        }
                    }

                    if ($diasInsertados > 0) {
                        $success = 'Vacaciones registradas correctamente para ' . $diasInsertados . ' día(s).';
                    } else {
                        $error = 'No se registraron días de vacaciones. Puede que el rango solo incluya fines de semana o festivos.';
                    }
                }
            }
        } elseif ($tipoEventoSel === 'Festivo') {
            if ($fechaFestivoForm === '') {
                $error = 'Para festivo debes indicar la fecha.';
            } else {
                $sql = "
                    INSERT INTO [integral].[dbo].[cmf_comerciales_dias_no_laborables]
                    (cod_vendedor, fecha, tipo_evento, descripcion, repetir_anualmente)
                    VALUES (?, ?, 'Festivo', ?, ?)
                ";

                if (registrarNoLaborablePrepareExecute($conn, $sql, [intval($codigo_vendedor), $fechaFestivoForm, $descSql, $repetirAnualmenteForm ? 1 : 0])) {
                    $success = 'Festivo registrado correctamente.';
                } else {
                    $error = 'No se pudo registrar el festivo.';
                }
            }
        } elseif ($tipoEventoSel === 'Otro') {
            if ($customEventForm === '' || $fechaEventoForm === '') {
                $error = 'Para un evento personalizado debes indicar el nombre y la fecha.';
            } elseif ($horaInicioOtroForm !== '' && $horaFinOtroForm !== '' && strtotime($horaInicioOtroForm) >= strtotime($horaFinOtroForm)) {
                $error = 'La hora de inicio debe ser anterior a la hora de fin.';
            } else {
                $sql = "
                    INSERT INTO [integral].[dbo].[cmf_comerciales_dias_no_laborables]
                    (cod_vendedor, fecha, hora_inicio, hora_fin, tipo_evento, descripcion, repetir_anualmente)
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ";

                if (registrarNoLaborablePrepareExecute($conn, $sql, [intval($codigo_vendedor), $fechaEventoForm, ($horaInicioOtroForm !== '' ? $horaInicioOtroForm : null), ($horaFinOtroForm !== '' ? $horaFinOtroForm : null), $customEventForm, $descSql])) {
                    $success = 'Evento registrado correctamente.';
                } else {
                    $error = 'No se pudo registrar el evento.';
                }
            }
        }
    }
}

$detalleAnual = obtenerDetalleNoLaborablesAnioActual($conn, intval($codigo_vendedor), $anioActual);
$resumenAnual = obtenerResumenNoLaborablesAnioActual($conn, intval($codigo_vendedor), $anioActual);
$vacacionesAgrupadas = agruparVacacionesConsecutivas($detalleAnual['vacaciones']);
$proximosResumen = [];

foreach ($vacacionesAgrupadas as $grupoVacacion) {
    if (strcmp((string)$grupoVacacion['fecha_fin'], $fechaHoy) >= 0) {
        $proximosResumen[] = [
            'tipo_evento' => 'Vacaciones',
            'descripcion' => (string)$grupoVacacion['descripcion'],
            'fecha_inicio' => (string)$grupoVacacion['fecha_inicio'],
            'fecha_fin' => (string)$grupoVacacion['fecha_fin'],
        ];
    }
}

foreach (array_merge($detalleAnual['festivos'], $detalleAnual['otros']) as $item) {
    if (strcmp((string)($item['fecha'] ?? ''), $fechaHoy) >= 0) {
        $proximosResumen[] = [
            'tipo_evento' => (string)($item['tipo_evento'] ?? ''),
            'descripcion' => (string)($item['descripcion'] ?? ''),
            'fecha_inicio' => (string)($item['fecha'] ?? ''),
            'fecha_fin' => (string)($item['fecha'] ?? ''),
        ];
    }
}

usort($proximosResumen, function ($a, $b) {
    return strcmp((string)$a['fecha_inicio'], (string)$b['fecha_inicio']);
});

$proximosResumen = array_slice($proximosResumen, 0, 5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
    <style>
        body {
            margin: 0;
            padding-top: 76px;
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, 0.10), transparent 28%),
                radial-gradient(circle at top right, rgba(249, 115, 22, 0.10), transparent 24%),
                linear-gradient(180deg, #f8fafc 0%, #eef4f8 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #0f172a;
        }

        .planner-shell { max-width: 1180px; margin: 0 auto; padding: 20px 20px 36px; }
        .layout-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(300px, 0.95fr); gap: 18px; align-items: start; }
        .summary-panel, .form-card { background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 20px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); backdrop-filter: blur(8px); }
        .form-card { padding: 20px 22px 22px; }
        .summary-panel { padding: 16px; }
        .summary-header { margin-bottom: 12px; }
        .summary-header h2 { margin: 0 0 4px; font-size: 20px; font-weight: 800; color: #0f172a; }
        .summary-header p { margin: 0; font-size: 13px; line-height: 1.5; color: #64748b; }
        .summary-cards { display: grid; gap: 10px; }
        .summary-card { width: 100%; padding: 12px 14px; background: #f8fafc; border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; text-align: left; transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; cursor: pointer; }
        .summary-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); border-color: rgba(37, 99, 235, 0.26); }
        .summary-card-content { min-width: 0; }
        .summary-card strong { display: block; font-size: 26px; line-height: 1; font-weight: 800; margin-bottom: 4px; }
        .summary-card span { display: block; font-size: 12px; line-height: 1.4; color: #64748b; }
        .summary-card small { display: block; margin-top: 3px; font-size: 11px; color: #94a3b8; }
        .summary-icon { width: 42px; height: 42px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
        .summary-icon-vacaciones { background: rgba(34, 197, 94, 0.14); color: #15803d; }
        .summary-icon-festivos { background: rgba(249, 115, 22, 0.14); color: #c2410c; }
        .summary-icon-otros { background: rgba(14, 165, 233, 0.14); color: #0369a1; }
        .summary-mini-list { margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(226, 232, 240, 0.9); }
        .summary-mini-list h3 { margin: 0 0 10px; font-size: 14px; font-weight: 800; }
        .summary-mini-item { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px dashed rgba(226, 232, 240, 0.9); }
        .summary-mini-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .summary-mini-item strong, .summary-mini-item span { font-size: 12px; line-height: 1.45; }
        .summary-mini-item span { color: #64748b; }
        .summary-mini-copy { flex: 1 1 auto; min-width: 0; }
        .summary-mini-copy small { display: block; margin-top: 2px; font-size: 11px; line-height: 1.45; color: #94a3b8; }
        .alert-stack { display: grid; gap: 10px; margin-bottom: 18px; }
        .alert-soft { border: 0; border-radius: 16px; padding: 14px 16px; font-size: 14px; }
        .form-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
        .form-card-header h2 { margin: 0 0 4px; font-size: 22px; font-weight: 800; }
        .form-card-header p { margin: 0; color: #64748b; font-size: 14px; line-height: 1.6; max-width: 60ch; }
        .form-badge { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .field-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 16px; }
        .field-span-12 { grid-column: span 12; }
        .field-span-6 { grid-column: span 6; }
        .field-label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 700; color: #334155; }
        .field-help { margin-top: 6px; font-size: 12px; line-height: 1.5; color: #64748b; }
        .field-block { margin-top: 16px; padding: 16px; border-radius: 18px; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border: 1px solid rgba(226, 232, 240, 0.9); }
        .field-block-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .field-icon { width: 42px; height: 42px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; color: #0f172a; }
        .field-icon-vacaciones { background: rgba(34, 197, 94, 0.14); color: #15803d; }
        .field-icon-festivo { background: rgba(249, 115, 22, 0.14); color: #c2410c; }
        .field-icon-otro { background: rgba(14, 165, 233, 0.14); color: #0369a1; }
        .field-block-header h3 { margin: 0 0 2px; font-size: 18px; font-weight: 800; }
        .field-block-header p { margin: 0; font-size: 13px; color: #64748b; }
        .form-control, .form-select { min-height: 48px; border-radius: 14px; border-color: #cbd5e1; box-shadow: none; }
        .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12); }
        textarea.form-control { min-height: 120px; resize: vertical; }
        .toggle-card { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border-radius: 18px; background: #fff7ed; border: 1px solid rgba(251, 146, 60, 0.22); }
        .toggle-card .form-check-input { margin-top: 3px; }
        .toggle-card strong { display: block; font-size: 14px; margin-bottom: 2px; }
        .toggle-card span { display: block; color: #7c2d12; font-size: 13px; line-height: 1.5; }
        .actions-row { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; flex-wrap: wrap; }
        .btn-action { min-width: 180px; min-height: 50px; border-radius: 999px; font-weight: 700; letter-spacing: 0.01em; }
        .btn-primary.btn-action { background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%); border: none; }
        .btn-light.btn-action { border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; }
        .modal-entry { padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        .modal-entry:last-child { border-bottom: 0; padding-bottom: 0; }
        .modal-entry-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 4px; }
        .modal-entry-title { font-size: 15px; font-weight: 700; color: #0f172a; }
        .modal-entry-title.is-sunday { color: #dc2626; }
        .modal-entry-meta { font-size: 12px; color: #64748b; }
        .modal-entry-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #eff6ff; color: #1d4ed8; white-space: nowrap; }
        .modal-entry-desc { margin: 0; font-size: 13px; line-height: 1.5; color: #475569; }
        .modal-backdrop { z-index: 2500 !important; }
        .modal { z-index: 2510 !important; }
        .d-none { display: none !important; }
        @media (max-width: 900px) { .layout-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .planner-shell { padding: 16px 12px 28px; }
            .summary-panel, .form-card { border-radius: 18px; }
            .field-span-6 { grid-column: span 12; }
            .form-card-header { flex-direction: column; }
            .actions-row { flex-direction: column-reverse; }
            .btn-action { width: 100%; }
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var tipoEvento = document.getElementById('tipo_evento');
        var vacaciones = document.getElementById('vacaciones_fields');
        var festivo = document.getElementById('festivo_fields');
        var otro = document.getElementById('otro_fields');
        function actualizarBloques() {
            var selected = tipoEvento ? tipoEvento.value : '';
            if (vacaciones) vacaciones.classList.toggle('d-none', selected !== 'Vacaciones');
            if (festivo) festivo.classList.toggle('d-none', selected !== 'Festivo');
            if (otro) otro.classList.toggle('d-none', selected !== 'Otro');
        }
        actualizarBloques();
        if (tipoEvento) tipoEvento.addEventListener('change', actualizarBloques);
    });
    </script>
</head>
<body>
    <main class="planner-shell">
        <section class="layout-grid">
            <section class="form-card">
                <div class="form-card-header">
                    <div>
                                <h2>Registrar día no laborable</h2>
                        <p>Introduce el evento y deja que la pantalla muestre solo los campos necesarios para ese caso.</p>
                    </div>
                    <div class="form-badge">
                        <i class="fas fa-shield-alt"></i>
                        Gestión planificador
                    </div>
                </div>

                <?php if ($error !== '' || $success !== ''): ?>
                    <div class="alert-stack">
                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger alert-soft mb-0"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($success !== ''): ?>
                            <div class="alert alert-success alert-soft mb-0"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form action="registrar_dia_no_laborable.php" method="POST">
                    <div class="field-grid">
                        <div class="field-span-12">
                            <label for="tipo_evento" class="field-label">Tipo de evento</label>
                            <select name="tipo_evento" id="tipo_evento" class="form-select" required>
                                <option value="">Selecciona una opción...</option>
                                <option value="Vacaciones"<?= $tipoEventoSel === 'Vacaciones' ? ' selected' : '' ?>>Vacaciones</option>
                                <option value="Festivo"<?= $tipoEventoSel === 'Festivo' ? ' selected' : '' ?>>Festivo</option>
                                <option value="Otro"<?= $tipoEventoSel === 'Otro' ? ' selected' : '' ?>>Otro</option>
                            </select>
                            <div class="field-help">El formulario se adapta automáticamente según el tipo seleccionado.</div>
                        </div>
                    </div>

                    <div id="vacaciones_fields" class="field-block<?= $tipoEventoSel === 'Vacaciones' ? '' : ' d-none' ?>">
                        <div class="field-block-header">
                            <span class="field-icon field-icon-vacaciones"><i class="fas fa-umbrella-beach"></i></span>
                            <div>
                                <h3>Vacaciones</h3>
                                <p>Registrar un rango de días laborables para el comercial actual.</p>
                            </div>
                        </div>
                        <div class="field-grid">
                            <div class="field-span-6">
                                <label for="fecha_inicio" class="field-label">Fecha de inicio</label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicioForm, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field-span-6">
                                <label for="fecha_fin" class="field-label">Fecha de fin</label>
                                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFinForm, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <div id="festivo_fields" class="field-block<?= $tipoEventoSel === 'Festivo' ? '' : ' d-none' ?>">
                        <div class="field-block-header">
                            <span class="field-icon field-icon-festivo"><i class="fas fa-flag"></i></span>
                            <div>
                                <h3>Festivo</h3>
                                <p>Marca un cierre puntual o recurrente del calendario.</p>
                            </div>
                        </div>
                        <div class="field-grid">
                            <div class="field-span-6">
                                <label for="fecha_festivo" class="field-label">Fecha del festivo</label>
                                <input type="date" name="fecha_festivo" id="fecha_festivo" class="form-control" value="<?= htmlspecialchars($fechaFestivoForm, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field-span-6">
                                <label class="field-label">Repetición</label>
                                <div class="toggle-card">
                                    <input class="form-check-input" type="checkbox" id="repetir_anualmente" name="repetir_anualmente" value="1"<?= $repetirAnualmenteForm ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="repetir_anualmente">
                                        <strong>Repetir todos los años</strong>
                                        <span>Úsalo solo cuando el festivo sea recurrente y deba aplicarse cada año.</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="otro_fields" class="field-block<?= $tipoEventoSel === 'Otro' ? '' : ' d-none' ?>">
                        <div class="field-block-header">
                            <span class="field-icon field-icon-otro"><i class="fas fa-pen"></i></span>
                            <div>
                                <h3>Evento personalizado</h3>
                                <p>Registra cualquier otro bloqueo con nombre libre y horas opcionales.</p>
                            </div>
                        </div>
                        <div class="field-grid">
                            <div class="field-span-6">
                                <label for="custom_event" class="field-label">Nombre del evento</label>
                                <input type="text" name="custom_event" id="custom_event" class="form-control" value="<?= htmlspecialchars($customEventForm, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. Formacion interna">
                            </div>
                            <div class="field-span-6">
                                <label for="fecha_evento" class="field-label">Fecha</label>
                                <input type="date" name="fecha_evento" id="fecha_evento" class="form-control" value="<?= htmlspecialchars($fechaEventoForm, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field-span-6">
                                <label for="hora_inicio_otro" class="field-label">Hora de inicio</label>
                                <input type="time" name="hora_inicio_otro" id="hora_inicio_otro" class="form-control" value="<?= htmlspecialchars($horaInicioOtroForm, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field-span-6">
                                <label for="hora_fin_otro" class="field-label">Hora de fin</label>
                                <input type="time" name="hora_fin_otro" id="hora_fin_otro" class="form-control" value="<?= htmlspecialchars($horaFinOtroForm, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="field-block">
                        <div class="field-block-header">
                            <span class="field-icon" style="background: rgba(100, 116, 139, 0.12); color: #475569;"><i class="fas fa-note-sticky"></i></span>
                            <div>
                                <h3>Descripción</h3>
                                <p>Añade contexto adicional para que el motivo quede claro al revisar el calendario.</p>
                            </div>
                        </div>
                        <div class="field-grid">
                            <div class="field-span-12">
                                <label for="descripcion" class="field-label">Descripción opcional</label>
                                <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Ej. Cierre por festivo local o ausencia planificada"><?= htmlspecialchars($descripcionForm, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="actions-row">
                        <a href="planificador_menu.php" class="btn btn-light btn-action">Volver al panel</a>
                        <button type="submit" class="btn btn-primary btn-action">Guardar evento</button>
                    </div>
                </form>
            </section>
            <aside class="summary-panel">
                <div class="summary-header">
                    <h2>Resumen</h2>
                    <p>Consulta los registros cargados y revisa los próximos eventos planificados.</p>
                </div>

                <div class="summary-cards">
                    <button type="button" class="summary-card" data-bs-toggle="modal" data-bs-target="#modalVacaciones">
                        <div class="summary-card-content">
                            <strong><?= (int)$resumenAnual['vacaciones'] ?></strong>
                            <span>Vacaciones</span>
                            <small>Toca para ver el detalle</small>
                        </div>
                        <span class="summary-icon summary-icon-vacaciones"><i class="fas fa-umbrella-beach"></i></span>
                    </button>

                    <button type="button" class="summary-card" data-bs-toggle="modal" data-bs-target="#modalFestivos">
                        <div class="summary-card-content">
                            <strong><?= (int)$resumenAnual['festivos'] ?></strong>
                            <span>Festivos</span>
                            <small>Toca para ver el detalle</small>
                        </div>
                        <span class="summary-icon summary-icon-festivos"><i class="fas fa-flag"></i></span>
                    </button>

                    <button type="button" class="summary-card" data-bs-toggle="modal" data-bs-target="#modalOtros">
                        <div class="summary-card-content">
                            <strong><?= (int)$resumenAnual['otros'] ?></strong>
                            <span>Otros eventos</span>
                            <small>Toca para ver el detalle</small>
                        </div>
                        <span class="summary-icon summary-icon-otros"><i class="fas fa-pen"></i></span>
                    </button>
                </div>

                <div class="summary-mini-list">
                    <h3>Próximos registros</h3>
                    <?php if (empty($proximosResumen)): ?>
                        <div class="summary-mini-item">
                            <div class="summary-mini-copy">
                                <strong>Sin registros</strong>
                                <small>No hay eventos cargados para este año.</small>
                            </div>
                            <span>-</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($proximosResumen as $item): ?>
                            <div class="summary-mini-item">
                                <div class="summary-mini-copy">
                                    <strong><?= htmlspecialchars($item['tipo_evento'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= htmlspecialchars($item['descripcion'] !== '' ? $item['descripcion'] : 'Sin descripción', ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <span>
                                    <?= htmlspecialchars(formatearFechaVista($item['fecha_inicio']), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($item['fecha_inicio'] !== $item['fecha_fin']): ?>
                                        <br>a <?= htmlspecialchars(formatearFechaVista($item['fecha_fin']), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </section>
    </main>

    <div class="modal fade" id="modalVacaciones" tabindex="-1" aria-labelledby="modalVacacionesLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVacacionesLabel">Vacaciones <?= htmlspecialchars($anioActual, ENT_QUOTES, 'UTF-8') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($vacacionesAgrupadas)): ?>
                        <p class="mb-0 text-muted">No hay vacaciones registradas para este año.</p>
                    <?php else: ?>
                        <?php foreach ($vacacionesAgrupadas as $item): ?>
                            <div class="modal-entry">
                                <div class="modal-entry-top">
                                    <div>
                                        <div class="modal-entry-title">
                                            <?= htmlspecialchars(formatearFechaVista($item['fecha_inicio']), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($item['fecha_inicio'] !== $item['fecha_fin']): ?>
                                                a <?= htmlspecialchars(formatearFechaVista($item['fecha_fin']), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-entry-meta"><?= (int)$item['total_dias'] ?> día(s)</div>
                                    </div>
                                </div>
                                <?php if ($item['descripcion'] !== ''): ?>
                                    <p class="modal-entry-desc"><?= htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalFestivos" tabindex="-1" aria-labelledby="modalFestivosLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFestivosLabel">Festivos <?= htmlspecialchars($anioActual, ENT_QUOTES, 'UTF-8') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($detalleAnual['festivos'])): ?>
                        <p class="mb-0 text-muted">No hay festivos registrados para este año.</p>
                    <?php else: ?>
                        <?php foreach ($detalleAnual['festivos'] as $item): ?>
                            <div class="modal-entry">
                                <div class="modal-entry-top">
                                    <div>
                                        <div class="modal-entry-title<?= !empty($item['es_domingo_original']) ? ' is-sunday' : '' ?>">
                                            <?= htmlspecialchars(formatearFechaVista($item['fecha']), ENT_QUOTES, 'UTF-8') ?>
                                            (<?= htmlspecialchars(obtenerDiaSemana((string)$item['fecha']), ENT_QUOTES, 'UTF-8') ?>)
                                        </div>
                                        <?php if (!empty($item['repetir_anualmente'])): ?>
                                            <div class="modal-entry-meta">Anual</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['repetir_anualmente']): ?>
                                        <span class="modal-entry-badge"><i class="fas fa-repeat"></i> Anual</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($item['descripcion'] !== ''): ?>
                                    <p class="modal-entry-desc"><?= htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalOtros" tabindex="-1" aria-labelledby="modalOtrosLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalOtrosLabel">Otros eventos <?= htmlspecialchars($anioActual, ENT_QUOTES, 'UTF-8') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($detalleAnual['otros'])): ?>
                        <p class="mb-0 text-muted">No hay otros eventos registrados para este año.</p>
                    <?php else: ?>
                        <?php foreach ($detalleAnual['otros'] as $item): ?>
                            <div class="modal-entry">
                                <div class="modal-entry-top">
                                    <div>
                                        <div class="modal-entry-title"><?= htmlspecialchars($item['tipo_evento'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="modal-entry-meta">
                                            <?= htmlspecialchars(formatearFechaVista($item['fecha']), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($item['hora_inicio'] !== '' || $item['hora_fin'] !== ''): ?>
                                                · <?= htmlspecialchars(trim(formatearHoraVista($item['hora_inicio']) . ($item['hora_fin'] !== '' ? ' - ' . formatearHoraVista($item['hora_fin']) : '')), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($item['descripcion'] !== ''): ?>
                                    <p class="modal-entry-desc"><?= htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
