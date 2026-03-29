<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_estadisticas');
require_once BASE_PATH . '/app/Support/statistics.php';
require_once BASE_PATH . '/app/Support/statistics.php';

header('Content-Type: text/html; charset=UTF-8');

$pageTitle = 'Estadisticas Ventas Comerciales';

/* ===============================
   FILTROS
================================= */

$queryEntrada = $_GET;
$vistaDetalle = trim((string)($queryEntrada['vista_detalle'] ?? ''));
$debugFlag = estadisticasDebugActivo();
$profilerEnabled = $debugFlag;
$profilerMs = [];
$profileRun = static function (string $bloque, callable $fn) use (&$profilerMs, $profilerEnabled) {
    $t0 = microtime(true);
    $res = $fn();
    if ($profilerEnabled) {
        $profilerMs[$bloque] = round((microtime(true) - $t0) * 1000, 2);
    }
    return $res;
};

$fDesde = normalizarFechaIso($queryEntrada['f_desde'] ?? '', date('Y-01-01'));
$fHasta = normalizarFechaIso($queryEntrada['f_hasta'] ?? '', date('Y-m-d'));
if ($fDesde > $fHasta) {
    [$fDesde, $fHasta] = [$fHasta, $fDesde];
}

$queryNormalizada = $queryEntrada;
$queryNormalizada['f_desde'] = $fDesde;
$queryNormalizada['f_hasta'] = $fHasta;
$queryNormalizada['cod_comisionista'] = trim((string)($queryEntrada['cod_comisionista'] ?? ''));

$contexto = resolverContextoFiltros($conn, $_SESSION, $queryNormalizada);
error_log('[DEBUG CONTEXTO FILTROS] ' . json_encode($contexto));
$opcionesMarca = obtenerOpcionesFiltroVentas($conn, $contexto, 'marca');
$opcionesFamilia = [];
try {
    $opcionesFamilia = obtenerOpcionesFiltroVentas($conn, $contexto, 'familia');
} catch (Throwable $e) {
    error_log('[FILTROS] Error cargando familias: ' . $e->getMessage());
    $opcionesFamilia = [];
}
$familiaSeleccionada = trim((string)($queryEntrada['familia'] ?? ''));
$opcionesSubfamilia = [];
if ($familiaSeleccionada !== '') {
    try {
        $opcionesSubfamilia = obtenerOpcionesFiltroVentas($conn, $contexto, 'subfamilia');
    } catch (Throwable $e) {
        $opcionesSubfamilia = [];
    }
}
$compararActivo = ((string)($_GET['comparar'] ?? '') === '1');
$contextoAnterior = null;
if ($compararActivo) {
    $contextoAnterior = $contexto;
    try {
        $desdeAnterior = (new DateTimeImmutable((string)$contexto['f_desde']))->modify('-1 year')->format('Y-m-d');
        $hastaAnterior = (new DateTimeImmutable((string)$contexto['f_hasta']))->modify('-1 year')->format('Y-m-d');
        $hastaMasUnoAnterior = (new DateTimeImmutable($hastaAnterior))->modify('+1 day')->format('Y-m-d');

        $contextoAnterior['f_desde'] = $desdeAnterior;
        $contextoAnterior['f_hasta'] = $hastaAnterior;
        $contextoAnterior['f_desde_sql'] = $desdeAnterior;
        $contextoAnterior['f_hasta_sql'] = $hastaAnterior;
        $contextoAnterior['f_hasta_mas_uno_sql'] = $hastaMasUnoAnterior;
    } catch (Throwable $e) {
        $contextoAnterior = $contexto;
    }
}
$fDesde = (string)$contexto['f_desde'];
$fHasta = (string)$contexto['f_hasta'];
$contexto['f_desde_sql'] = (string)$contexto['f_desde_sql'];
$contexto['f_hasta_sql'] = (string)$contexto['f_hasta_sql'];
$contexto['f_hasta_mas_uno_sql'] = (string)$contexto['f_hasta_mas_uno_sql'];

$codComisionistaActivo = (string)$contexto['cod_comisionista_activo'];
$nombreComercialActivo = 'Todos';

/* ===============================
   DATOS KPI
================================= */
$resumenDocumentos = [
    'pedidos_ventas_num' => 0,
    'pedidos_ventas_importe' => 0.0,
    'pedidos_abono_num' => 0,
    'pedidos_abono_importe' => 0.0,
    'albaranes_ventas_num' => 0,
    'albaranes_ventas_importe' => 0.0,
    'albaranes_abono_num' => 0,
    'albaranes_abono_importe' => 0.0,
    'porcentaje_devolucion_importe' => 0.0,
];
$resumenAlbaranesConSinPedido = [
    'con_pedido_num' => 0,
    'con_pedido_importe' => 0.0,
    'sin_pedido_num' => 0,
    'sin_pedido_importe' => 0.0,
];
$resumenAlbaranesAbonoConSinPedido = [
    'con_pedido_num' => 0,
    'con_pedido_importe' => 0.0,
    'sin_pedido_num' => 0,
    'sin_pedido_importe' => 0.0,
];
$resumenDocumentosAnterior = null;
$resumenAlbaranesConSinPedidoAnterior = null;
$resumenAlbaranesAbonoConSinPedidoAnterior = null;
$kpiServicioAjustado = [
    'total_pedido' => 0.0,
    'servicio_real' => 0.0,
    'porcentaje_servicio' => 0.0,
    'servicio_operativo_total' => 0.0,
    'porcentaje_servicio_operativo' => 0.0,
    'total_huerfanos_importe' => 0.0,
    'total_huerfanos_asignados_importe' => 0.0,
    'total_huerfanos_no_asignables_importe' => 0.0,
    'porcentaje_huerfanos_asignables' => 0.0,
    'total_servido_documental' => 0.0,
    'es_experimental' => false,
];
$kpiServicioDocumentalDebug = null;
$kpiServicioAnterior = null;
$kpis = [
    'pedidos_ventas_num' => 0,
    'pedidos_ventas_importe' => 0.0,
    'pedidos_abono_num' => 0,
    'pedidos_abono_importe' => 0.0,
    'albaranes_ventas_num' => 0,
    'albaranes_ventas_importe' => 0.0,
    'albaranes_abono_num' => 0,
    'albaranes_abono_importe' => 0.0,
    'porcentaje_devolucion_importe' => 0.0,
    'total_pedido' => 0.0,
    'servicio_real' => 0.0,
    'porcentaje_servicio' => 0.0,
];
$albaranesConPedidoNum = 0;
$albaranesConPedidoImporte = 0.0;
$albaranesSinPedidoNum = 0;
$albaranesSinPedidoImporte = 0.0;
$albaranesAbonoConPedidoNum = 0;
$albaranesAbonoConPedidoImporte = 0.0;
$albaranesAbonoSinPedidoNum = 0;
$albaranesAbonoSinPedidoImporte = 0.0;
$kpiPedidosPendientes = ['pedidos_pendientes' => 0];
$kpiVelocidadServicio = ['dias_media' => 0.0, 'lineas_servidas' => 0];
$kpiLineasPendientes = ['lineas_pendientes' => 0, 'dias_media_pendiente' => 0.0];
$kpiBacklogImporte = ['backlog_importe' => 0.0];
$kpiClientesBacklog = ['clientes_con_backlog' => 0];
$kpiLineasCriticas = ['lineas_criticas' => 0];

$debugActivo = false;
$debugMotivo = trim((string)($_GET['debug_motivo'] ?? ''));
$debugZona = trim((string)($_GET['debug_zona'] ?? ''));
$debugDoc = trim((string)($_GET['debug_doc'] ?? ''));
$debugCheckAB = [];
$debugForenseDoc = [];
$debugDiagnostico = [];
$debugDeltaModelo1 = 0.0;
$debugDocsAfectadosModelo1 = 0;
$debugTieneFallo = false;
$debugMostrarBloques = false;
$comerciales = (bool)$contexto['puede_elegir_comercial']
    ? $profileRun('kpi_comerciales', static function () use ($conn, $contexto) {
        return obtenerOpcionesComercialesVentas($conn, $contexto);
    })
    : [];
if ($codComisionistaActivo !== '') {
    foreach ($comerciales as $c) {
        if ($c['cod_comisionista'] === $codComisionistaActivo) {
            $nombreComercialActivo = toUTF8($c['nombre']);
            break;
        }
    }
}

$vistasPermitidas = [
    'pedidos_ventas',
    'pedidos_abonos',
    'albaranes_totales',
    'albaranes_con_pedido',
    'albaranes_sin_pedido',
];

if ($vistaDetalle === '' || !in_array($vistaDetalle, $vistasPermitidas, true)) {
    $vistaDetalle = '';
}

$detalleVista = [];

$baseQuery = [
    'f_desde' => $fDesde,
    'f_hasta' => $fHasta,
    'cod_comisionista' => $codComisionistaActivo,
    'vista_detalle' => $vistaDetalle,
];

$buildUrl = static function (array $override = []) use ($baseQuery): string {
    $q = array_merge($baseQuery, $override);
    return 'estadisticas_ventas_comerciales.php?' . http_build_query($q);
};

$esVistaActiva = static function (string $vista) use ($vistaDetalle): bool {
    return $vistaDetalle === $vista;
};

$marcaSeleccionada = trim((string)($queryEntrada['marca'] ?? ''));
$familiaSeleccionada = trim((string)($queryEntrada['familia'] ?? ''));
$subfamiliaSeleccionada = trim((string)($queryEntrada['subfamilia'] ?? ''));

$marcaLabel = ($marcaSeleccionada !== '') ? $marcaSeleccionada : 'Todas';
$comercialLabel = ($nombreComercialActivo !== '') ? $nombreComercialActivo : 'Todos';
$familiaLabel = 'Todas las familias';
foreach ($opcionesFamilia as $familiaOpt) {
    $valorOpt = trim((string)($familiaOpt['valor'] ?? ''));
    if ($valorOpt !== '' && $valorOpt === $familiaSeleccionada) {
        $familiaLabel = trim((string)($familiaOpt['texto'] ?? $valorOpt));
        break;
    }
}
$subfamiliaLabel = 'Todas las subfamilias';
foreach ($opcionesSubfamilia as $subfamiliaOpt) {
    $valorOpt = trim((string)($subfamiliaOpt['valor'] ?? ''));
    if ($valorOpt !== '' && $valorOpt === $subfamiliaSeleccionada) {
        $subfamiliaLabel = trim((string)($subfamiliaOpt['texto'] ?? $valorOpt));
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

<style>
body {
    margin: 0;
    padding-top: 76px;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f6f8;
    color: #2f3b4a;
}

.stats-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 22px 16px 30px;
}

.stats-wrap h1 {
    margin: 0 0 16px;
    font-size: 27px;
    color: #2f3b4a;
}

.filters {
    margin-bottom: 18px;
}

.filters-card {
    background:#ffffff;
    border-radius:12px;
    padding:20px 24px;
    padding-bottom:20px;
    box-shadow:0 4px 14px rgba(0,0,0,0.05);
    margin-bottom:24px;
}

.filters-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:14px;
}

.filters-title {
    font-weight:700;
    font-size:14px;
    color:#6b7280;
    letter-spacing:0.5px;
}

.filters-compare {
    display:flex;
    align-items:center;
    gap:10px;
}

.filters-compare-label {
    display:flex;
    align-items:center;
    gap:6px;
    font-size:13px;
    color:#6b7280;
    font-weight:600;
}

.filters-row {
    display:flex;
    align-items:center;
    gap:24px;
    flex-wrap:wrap;
}

.filters-row .form-group {
    margin-bottom: 0;
}

.filters-row .form-group > label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
}

.filter-inline {
    display:flex;
    align-items:center;
    gap:8px;
}

.filter-inline i {
    color:#6b7280;
    font-size:14px;
}

.date-arrow {
    color:#9ca3af;
}

.filters-row-premium {
    display:flex;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
}

.filter-pill {
    min-height:48px;
    width:100%;
    display:flex;
    align-items:center;
    gap:10px;
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:0 12px;
    box-shadow:0 2px 6px rgba(0,0,0,0.04);
    transition:all .15s ease;
    box-sizing:border-box;
    position:relative;
    margin-bottom:14px;
    overflow:hidden;
}

.filter-pill:focus-within{
    z-index:20;
}

.filter-pill:hover {
    border-color:#1f3c88;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}

.filter-pill select,
.filter-pill input {
    border:none;
    outline:none;
    background:transparent;
    font-size:14px;
    padding:0;
}

.filter-pill input{
    border:none;
    outline:none;
    background:transparent;
    font-size:14px;
    margin:0;
    padding:0;
}

.filters-row-premium input[type="date"],
.filter-pill input[type="date"]{
    height:34px;
    border:none;
    outline:none;
    background:transparent;
    font-size:14px;
    margin:0;
    padding:0;
}

.filter-pill i {
    color:#6b7280;
    font-size:14px;
}

.filter-pill select:disabled{
    opacity:0.5;
    cursor:not-allowed;
}

.date-separator i {
    color:#9ca3af;
    font-size:13px;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.field label {
    display: block;
    margin-bottom: 0;
    font-weight: 600;
    font-size: 14px;
    color: #415466;
}

.field {
    display:flex;
    flex-direction:column;
    gap:6px;
}

.field input,
.field select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #cdd7e0;
    border-radius: 8px;
    padding: 9px 10px;
    background: #fff;
    font-size: 14px;
}

.toggle-container {
    display:flex;
    flex-direction:column;
    gap:6px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 22px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    transition: .3s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

.switch input:checked + .slider {
    background-color: #1f3c88;
}

.switch input:checked + .slider:before {
    transform: translateX(20px);
}

.kpi-section-title {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 700;
}

.kpi-block {
    margin-bottom: 26px;
}

.kpi-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

.kpi-grid-documental {
    display: grid;
    gap: 14px;
}

.kpi-card {
    background: #fff;
    border: 1px solid #e6edf3;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.04);
    padding: 18px;
    min-height: 154px;
}
.kpi-value.kpi-verde {
    color: #27ae60;
}

.kpi-value.kpi-amarillo {
    color: #f39c12;
}

.kpi-value.kpi-rojo {
    color: #e74c3c;
}

.kpi-doc-count {
    margin: 8px 0 2px;
    font-size: 24px;
    line-height: 1.1;
    font-weight: 800;
    color: #1f4f74;
}

.kpi-title {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: 16px;
}

.kpi-value {
    font-size: 32px;
    font-weight: 700;
    line-height: 1.1;
    margin: 10px 0 4px;
    color: #1f4f74;
}

.kpi-service-card {
    min-height: 0;
    border-top: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.kpi-service-metrics {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    margin-top: 8px;
}

.kpi-service-metric {
    font-size: 14px;
    color: #415466;
}

.kpi-service-compact {
    position: relative;
    cursor: pointer;
    user-select: none;
    max-height: 150px;
    overflow: visible;
}

.kpi-service-compact .kpi-value {
    margin: 10px 0 4px;
}

.kpi-service-summary {
    margin-top: 8px;
    font-size: 14px;
    color: #4b5563;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-service-tooltip {
    position: absolute;
    left: 14px;
    top: calc(100% + 8px);
    width: min(320px, calc(100vw - 44px));
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
    padding: 10px 12px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(6px);
    transition: opacity 150ms ease, transform 150ms ease, visibility 150ms ease;
    pointer-events: none;
    z-index: 20;
}

.kpi-service-tooltip p {
    margin: 2px 0;
    font-size: 12px;
    color: #4b5563;
    line-height: 1.35;
}

.kpi-service-compact:hover .kpi-service-tooltip,
.kpi-service-compact:focus-within .kpi-service-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.kpi-card > .card-body {
    padding: 0;
    min-height: 100%;
}

.comparacion-wrapper {
    margin-top: 16px;
    padding-top: 10px;
    border-top: 1px solid #eef2f6;
}

.comparacion-wrapper .small {
    font-size: 12px;
    color: #8a94a3;
    font-weight: 400;
    line-height: 1.25;
}



.comparacion-wrapper .text-success,
.comparacion-wrapper .text-danger {
    font-weight: 600;
    font-size: 13px;
}

.comparacion-wrapper .text-success {
    color: #16a34a;
}

.comparacion-wrapper .text-danger {
    color: #dc2626;
}

.kpi-mini {
    font-size: 13px;
    color: #6a7785;
    margin: 4px 0;
}

.kpi-compare-sub {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.kpi-compare-var {
    font-size: 13px;
    font-weight: 700;
    margin-top: 2px;
}

.comparacion-placeholder {
    display: block;
    min-height: 1.2em;
}

.card-doc {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-radius: 14px;
    padding: 24px 26px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    border-top: none;
}

.card-doc-inner {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.card-doc-inner .flex-grow-1 {
    flex-grow: 1;
}

.contenido-principal {
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    margin-bottom: 0;
}

.card-doc:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 35px rgba(0, 0, 0, 0.18);
}

@media (hover: hover) and (pointer: fine) {
    .kpi-service-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 35px rgba(0, 0, 0, 0.12);
    }
}

.card-doc .card-title {
    font-size: 15px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-doc .card-doc-amount {
    font-size: 32px;
    font-weight: 700;
    letter-spacing: -0.5px;
    color: #1f3c88;
    margin-bottom: 14px;
    display: inline-flex;
    align-items: baseline;
    flex-wrap: nowrap;
    white-space: nowrap;
}

.card-doc .card-doc-sub {
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
}

.card-doc-breakdown {
    font-size: 13px;
    color: #6b7280;
    margin-top: 8px;
}

.card-doc-breakdown span {
    font-weight: 500;
}

.card-doc-percent-inline {
    font-size: 18px;
    font-weight: 700;
    margin-left: 8px;
    color: #dc2626;
    opacity: 0.95;
    white-space: nowrap;
    display: inline-block;
}

.card-doc.red .card-title,
.card-doc.red .card-doc-amount {
    color: #b91c1c;
}

.card-doc.red {
    border-top: none;
}

.card-neta-real {
    grid-column: 1 / -1;
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    box-shadow: none;
    text-align: center;
}

.card-neta-real-title {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: #6b7280;
    letter-spacing: 0.6px;
    text-transform: uppercase;
}

.card-neta-real-periodo {
    margin: 6px 0 0;
    font-size: 13px;
    color: #9ca3af;
}

.card-neta-real-amount {
    margin: 12px 0 8px;
    font-size: clamp(32px, 5vw, 52px);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.5px;
    color: #1f3b57;
}

.card-neta-real-compare {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #6b7280;
}

.card-neta-real-compare.positivo {
    color: #2f855a;
}

.card-neta-real-compare.negativo {
    color: #b91c1c;
}

.kpi-divider {
    height: 1px;
    background: #e7edf3;
    margin: 10px 0;
}

.kpi-progress,
.progress {
    margin-top: 12px;
    width: 100%;
    height: 12px;
    border-radius: 6px;
    background: #e9ecef;
    overflow: hidden;
}

.kpi-progress-fill,
.progress-bar {
    height: 100%;
    border-radius: 6px;
    background: #1f9d57;
}

.progress-bar.barra-verde {
    background-color: #198754;
}

.progress-bar.barra-amarilla {
    background-color: #e0a800;
}

.progress-bar.barra-roja {
    background-color: #dc3545;
}

.porcentaje.barra-verde {
    color: #198754;
}

.porcentaje.barra-amarilla {
    color: #e0a800;
}

.porcentaje.barra-roja {
    color: #dc3545;
}

.porcentaje {
    font-size: 28px;
    font-weight: 700;
}

.card-servicio {
    background: #fff;
}

.kpi-links {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.kpi-link-chip {
    display: inline-block;
    padding: 4px 9px;
    border: 1px solid #d5e1ec;
    border-radius: 999px;
    font-size: 12px;
    text-decoration: none;
    background: #f8fbff;
    color: #415466;
}

.kpi-link-chip.is-active {
    border-color: #7ea7c9;
    background: #eaf4fc;
    font-weight: 600;
}

.table-wrap {
    background: #fff;
    border: 1px solid #dbe2e8;
    border-radius: 10px;
    overflow: auto;
    margin-bottom: 16px;
}

.debug-wrap {
    margin: 0 0 14px;
    background: #fff;
    border: 1px solid #dbe2e8;
    border-radius: 10px;
    overflow: hidden;
}

.debug-wrap summary {
    cursor: pointer;
    list-style: none;
    font-weight: 700;
    padding: 12px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #e7edf3;
}

.debug-wrap summary::-webkit-details-marker {
    display: none;
}

.debug-body {
    padding: 12px 14px;
}

.debug-kpi {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 12px;
    font-size: 13px;
}

.debug-kpi .item {
    background: #f8fafc;
    border: 1px solid #e7edf3;
    border-radius: 8px;
    padding: 8px 10px;
}

.debug-filters {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}

.debug-filters input,
.debug-filters select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #cdd7e0;
    border-radius: 8px;
    padding: 7px 8px;
    font-size: 13px;
    background: #fff;
}

.debug-subtitle {
    margin: 12px 0 8px;
    font-size: 14px;
    color: #415466;
    font-weight: 700;
}

.debug-warning {
    margin: 8px 0 12px;
    padding: 8px 10px;
    border: 1px solid #f3c06b;
    border-radius: 8px;
    background: #fff4df;
    color: #6a4b16;
    font-size: 13px;
}

.debug-list {
    margin: 0;
    padding-left: 18px;
    font-size: 13px;
    color: #2f3b4a;
}

.debug-mini-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-bottom: 10px;
}

.debug-mini-table th,
.debug-mini-table td {
    border-bottom: 1px solid #e7edf3;
    padding: 7px 8px;
    text-align: left;
}

.debug-mini-table th {
    background: #f8fafc;
    color: #415466;
    font-weight: 700;
}

.debug-ab-title {
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 700;
    color: #2f3b4a;
}

.table-title {
    margin: 0;
    padding: 12px 14px;
    font-size: 15px;
    border-bottom: 1px solid #e7edf3;
    background: #f8fafc;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #e7edf3;
    text-align: left;
    font-size: 14px;
}

th {
    font-weight: 700;
}

@media (min-width: 900px) {
    .filter-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .kpi-grid-documental {
        grid-template-columns: repeat(4, 1fr);
    }
    .kpi-service-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 640px) and (max-width: 899px) {
    .kpi-grid-documental {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 900px) {
    .debug-kpi,
    .debug-filters {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .card-neta-real {
        padding: 20px 16px;
    }
    .card-neta-real-compare {
        font-size: 13px;
    }
}

#loader-estadisticas{
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:#ffffff;
display:flex;
align-items:center;
justify-content:center;
z-index:9999;
}

.loader-contenido{
text-align:center;
}

.spinner{
width:40px;
height:40px;
border:4px solid #e5e7eb;
border-top:4px solid #1f3a5f;
border-radius:50%;
animation:spin 1s linear infinite;
margin:0 auto 10px auto;
}

.loader-texto{
font-size:14px;
color:#334155;
}

@keyframes spin{
0%{transform:rotate(0deg);}
100%{transform:rotate(360deg);}
}

/* =========================
FILTROS - ESTILO UNIFICADO
========================= */

.filtros-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(220px,1fr));
    column-gap:16px;
    row-gap:32px;
    align-items:start;
}

.filtros-grid .filter-pill{
    width:100%;
    min-width:0;
    box-sizing:border-box;
}

.filtro-fecha{
    display:flex;
    align-items:center;
    gap:8px;
    width:100%;
}

.filtro-fecha input[type="date"]{
    flex:1;
    min-width:0;
    height:34px;
}

.filtros-grid .filter-pill.filtro-fecha{
    width:100%;
}

.filtro-placeholder{
    visibility:hidden;
    pointer-events:none;
}

.filtro-hidden {
    display:none;
}

.filter-pill.filter-dropdown{
    position:relative;
    overflow:visible;
}

.filtro-boton{
    width:100%;
    min-height:46px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:0;
    background:transparent;
    font-size:14px;
    cursor:pointer;
    box-sizing:border-box;
    border:none;
}

.filtro-boton-titulo{
    flex:0 0 auto;
    color:#2f3b4a;
    white-space:nowrap;
}

.filtro-boton-valor{
    flex:1 1 auto;
    min-width:0;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    color:#2f3b4a;
}

.filtro-boton-flecha{
    flex:0 0 auto;
    margin-left:8px;
    font-size:11px;
    color:#2f3b4a;
    line-height:1;
}

.panel-filtro{
    display:none;
    position:absolute;
    margin-top:6px;
    top:100%;
    left:0;
    width:max-content;
    min-width:340px;
    max-width:420px;
    background:white;
    border:1px solid #ddd;
    border-radius:10px;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
    max-height:320px;
    overflow:auto;
    padding:10px;
    z-index:20;
}

.filtro-buscador{
    width:100%;
    padding:6px;
    margin-bottom:8px;
    box-sizing:border-box;
}

.lista-opciones div{
    padding:6px;
    cursor:pointer;
}

.panel-filtro .opcion{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    padding:8px 12px;
}

.panel-filtro .lista-opciones div{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    padding:8px 12px;
}

.lista-opciones div:hover{
    background:#f0f6ff;
}
</style>
</head>
<body>
<div id="loader-estadisticas">
    <div class="loader-contenido">
        <div class="spinner"></div>
        <div class="loader-texto">Cargando estad&iacute;sticas...</div>
    </div>
</div>
<?php
ob_flush();
flush();
?>
<?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

<main class="stats-wrap">

<form class="filters" method="get" action="estadisticas_ventas_comerciales.php">
<input type="hidden" name="vista_detalle" value="<?= htmlspecialchars($vistaDetalle, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="comparar" value="<?= $compararActivo ? '1' : '0' ?>" data-comparar-hidden>
<?php if ($debugFlag): ?>
<input type="hidden" name="debug" value="1">
<input type="hidden" name="debug_motivo" value="<?= htmlspecialchars($debugMotivo, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="debug_zona" value="<?= htmlspecialchars($debugZona, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="debug_doc" value="<?= htmlspecialchars($debugDoc, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>

<div class="filters-card filtros-container">
<div class="filters-header">
    <span class="filters-title">FILTROS</span>
    <div class="filters-compare">
        <span class="filters-compare-label">
            <i class="fas fa-chart-line"></i> Comparar a&ntilde;o anterior
        </span>
        <label class="switch">
            <input type="checkbox" id="comparar" value="1"
                <?= $compararActivo ? 'checked' : '' ?>>
            <span class="slider"></span>
        </label>
    </div>
</div>

<div class="filters-row-premium filtros-grid">
<div class="filter-pill filtro-item filtro-fecha">
        <i class="fas fa-calendar-alt"></i>
        <input type="date"
               name="f_desde"
               value="<?= htmlspecialchars($fDesde ?? '') ?>">
        <span class="date-separator">
                <i class="fas fa-arrow-right text-muted"></i>
        </span>
        <input type="date"
               name="f_hasta"
               value="<?= htmlspecialchars($fHasta ?? '') ?>">
</div>

<?php if ((bool)$contexto['puede_elegir_comercial']): ?>
<div class="filter-pill filter-dropdown">
        <i class="fas fa-user-tie"></i>
        <div class="filtro-boton" id="btn-filtro-comercial">
            <span class="filtro-boton-titulo">Comercial:</span>
            <span class="filtro-boton-valor" id="filtro-comercial-label"><?= htmlspecialchars($comercialLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="filtro-boton-flecha">&#9662;</span>
        </div>
        <div class="panel-filtro" id="panel-filtro-comercial">
            <input type="text"
                   id="buscar-comercial"
                   placeholder="Buscar comercial..."
                   class="filtro-buscador">
            <div class="lista-opciones" id="lista-comerciales">
                <div data-value="">-- Todos --</div>
                <?php foreach ($comerciales as $comercial): ?>
                    <div data-value="<?= htmlspecialchars($comercial['cod_comisionista'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(toUTF8($comercial['nombre']), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <select name="cod_comisionista" id="filtro-comercial" class="filtro-hidden">
            <option value="">-- Todos --</option>
            <?php foreach ($comerciales as $comercial): ?>
            <option value="<?= htmlspecialchars($comercial['cod_comisionista'], ENT_QUOTES, 'UTF-8') ?>"
            <?= $codComisionistaActivo === $comercial['cod_comisionista'] ? 'selected' : '' ?>>
            <?= htmlspecialchars(toUTF8($comercial['nombre']), ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
        </select>
</div>
<?php else: ?>
<div class="filter-pill filtro-placeholder" aria-hidden="true"></div>
<?php endif; ?>
</div>

<div class="filters-row-premium filtros-grid">
<div class="filter-pill filter-dropdown">
    <i class="fas fa-tag"></i>
    <div class="filtro-boton" id="btn-filtro-marca">
        <span class="filtro-boton-titulo">Marca:</span>
        <span class="filtro-boton-valor" id="filtro-marca-label"><?= htmlspecialchars($marcaLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="filtro-boton-flecha">&#9662;</span>
    </div>

    <div class="panel-filtro" id="panel-filtro-marca">
        <input type="text"
               id="buscar-marca"
               placeholder="Buscar marca..."
               class="filtro-buscador">

        <div class="lista-opciones" id="lista-marcas">
            <div data-value="">Todas las marcas</div>

            <?php foreach ($opcionesMarca as $marca): ?>
                <div data-value="<?= htmlspecialchars($marca, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($marca, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <select name="marca" id="filtro-marca" class="filtro-hidden">
        <option value="">Todas las marcas</option>

        <?php foreach ($opcionesMarca as $marca): ?>
            <option
                value="<?= htmlspecialchars($marca, ENT_QUOTES, 'UTF-8') ?>"
                <?= (($queryEntrada['marca'] ?? '') === $marca) ? 'selected' : '' ?>>
                <?= htmlspecialchars($marca, ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>

    </select>
</div>

<div class="filter-pill filter-dropdown">
    <i class="fas fa-layer-group"></i>
    <div class="filtro-boton" id="btn-filtro-familia">
        <span class="filtro-boton-titulo">Familia:</span>
        <span class="filtro-boton-valor" id="filtro-familia-label"><?= htmlspecialchars($familiaLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="filtro-boton-flecha">&#9662;</span>
    </div>
    <div class="panel-filtro" id="panel-filtro-familia">
        <input type="text"
               id="buscar-familia"
               placeholder="Buscar familia..."
               class="filtro-buscador">
        <div class="lista-opciones" id="lista-familias">
            <div data-value="">Todas las familias</div>
            <?php foreach ($opcionesFamilia as $familia): ?>
                <?php $familiaValor = (string)($familia['valor'] ?? ''); ?>
                <?php $familiaTexto = (string)($familia['texto'] ?? $familiaValor); ?>
                <div data-value="<?= htmlspecialchars($familiaValor, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($familiaTexto, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <select name="familia" id="filtro-familia" class="filtro-hidden">
        <option value="">Todas las familias</option>

        <?php foreach ($opcionesFamilia as $familia): ?>
            <?php $familiaValor = (string)($familia['valor'] ?? ''); ?>
            <?php $familiaTexto = (string)($familia['texto'] ?? $familiaValor); ?>
            <option
                value="<?= htmlspecialchars($familiaValor, ENT_QUOTES, 'UTF-8') ?>"
                <?= (($queryEntrada['familia'] ?? '') === $familiaValor) ? 'selected' : '' ?>>
                <?= htmlspecialchars($familiaTexto, ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>

    </select>
</div>
<?php if ($familiaSeleccionada !== ''): ?>
<div class="filter-pill filter-dropdown">
    <i class="fas fa-sitemap"></i>
    <div class="filtro-boton" id="btn-filtro-subfamilia">
        <span class="filtro-boton-titulo">Subfamilia:</span>
        <span class="filtro-boton-valor" id="filtro-subfamilia-label"><?= htmlspecialchars($subfamiliaLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="filtro-boton-flecha">&#9662;</span>
    </div>
    <div class="panel-filtro" id="panel-filtro-subfamilia">
        <input type="text"
               id="buscar-subfamilia"
               placeholder="Buscar subfamilia..."
               class="filtro-buscador">
        <div class="lista-opciones" id="lista-subfamilias">
            <div data-value="">Todas las subfamilias</div>
            <?php foreach ($opcionesSubfamilia as $subfamilia): ?>
                <div data-value="<?= htmlspecialchars($subfamilia['valor'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($subfamilia['texto'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <select name="subfamilia" id="filtro-subfamilia" class="filtro-select filtro-hidden" <?php if ($familiaSeleccionada === '') echo 'disabled'; ?>>
        <option value="">Todas las subfamilias</option>

        <?php foreach ($opcionesSubfamilia as $subfamilia): ?>
            <option
                value="<?= htmlspecialchars($subfamilia['valor'], ENT_QUOTES, 'UTF-8') ?>"
                <?= (($queryEntrada['subfamilia'] ?? '') === $subfamilia['valor']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($subfamilia['texto'], ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>

    </select>
</div>
<?php endif; ?>
</div>
</div>

</form>

<?php
$desdeFormateado = '';
$hastaFormateado = '';

if (!empty($contexto['f_desde'])) {
    $desdeFormateado = date('d-m-Y', strtotime($contexto['f_desde']));
}

if (!empty($contexto['f_hasta'])) {
    $hastaFormateado = date('d-m-Y', strtotime($contexto['f_hasta']));
}
?>
<div class="contexto-activo-bar">
    <strong>Comercial:</strong>
    <?= htmlspecialchars($nombreComercialActivo, ENT_QUOTES, 'UTF-8') ?>

    &nbsp;&nbsp;|&nbsp;&nbsp;

    <strong>Periodo:</strong>
    <?= htmlspecialchars($desdeFormateado) ?>
    →
    <?= htmlspecialchars($hastaFormateado) ?>

    &nbsp;&nbsp;|&nbsp;&nbsp;

    <strong>Marca:</strong>
    <?= htmlspecialchars((string)($contexto['marca'] ?? 'Todas'), ENT_QUOTES, 'UTF-8') ?>
</div>

<?php if ($debugActivo && $debugMostrarBloques): ?>
<?php
    $abCampos = is_array($debugCheckAB['line_fields_disponibles'] ?? null) ? $debugCheckAB['line_fields_disponibles'] : [];
    $abCabecera = (float)($debugCheckAB['total_cabecera'] ?? 0);
    $abModelo1 = is_array($debugCheckAB['modelos_1'] ?? null) ? $debugCheckAB['modelos_1'] : [];
    $debugTotales = $debugDiagnostico['totales'] ?? ['total_diferencia_rango' => 0, 'docs_afectados' => 0];
    $debugFilas = is_array($debugDiagnostico['filas'] ?? null) ? $debugDiagnostico['filas'] : [];
    $debugMotivos = is_array($debugDiagnostico['motivos'] ?? null) ? $debugDiagnostico['motivos'] : [];
    $debugZonaResumen = is_array($debugDiagnostico['zona_resumen'] ?? null) ? $debugDiagnostico['zona_resumen'] : [];
    $debugZonaClientes = is_array($debugDiagnostico['zona_top_clientes'] ?? null) ? $debugDiagnostico['zona_top_clientes'] : [];
?>
<div class="debug-wrap">
    <div class="debug-body">
        <p class="debug-ab-title">CHECK CABECERA vs L&Iacute;NEAS (MODELO 1)</p>
        <form method="get" action="estadisticas_ventas_comerciales.php" class="debug-filters">
            <input type="hidden" name="debug" value="1">
            <input type="hidden" name="f_desde" value="<?= htmlspecialchars($fDesde, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="f_hasta" value="<?= htmlspecialchars($fHasta, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="vista_detalle" value="<?= htmlspecialchars($vistaDetalle, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="cod_comisionista" value="<?= htmlspecialchars($codComisionistaActivo, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="debug_motivo" value="<?= htmlspecialchars($debugMotivo, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="debug_zona" value="<?= htmlspecialchars($debugZona, ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="debug_doc" value="<?= htmlspecialchars($debugDoc, ENT_QUOTES, 'UTF-8') ?>" placeholder="debug_doc (cod_venta)">
            <input type="text" value="Forense por documento (opcional)" readonly>
            <button type="submit">Ver documento</button>
        </form>
        <div class="debug-kpi">
            <div class="item"><strong>A) TOTAL_CABECERA</strong><br><?= number_format($abCabecera, 2, ',', '.') ?> &euro;</div>
            <div class="item"><strong>Campos l&iacute;nea detectados</strong><br><?= htmlspecialchars(implode(', ', $abCampos), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="debug-subtitle">B) TOTAL_LINEAS_MODELO_1 (join doc: empresa,tipo,venta)</div>
        <table class="debug-mini-table">
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Total l&iacute;neas</th>
                    <th>Delta (cabecera - l&iacute;neas)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($abModelo1 as $m1): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($m1['campo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float)($m1['total_lineas'] ?? 0), 2, ',', '.') ?> &euro;</td>
                    <td><?= number_format((float)($m1['delta'] ?? 0), 2, ',', '.') ?> &euro;</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php foreach ($abModelo1 as $m1): ?>
            <?php if (abs((float)($m1['delta'] ?? 0)) > 0.0001): ?>
            <div class="debug-subtitle">TOP 10 docs con diferencia (Modelo 1 - <?= htmlspecialchars((string)($m1['campo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</div>
            <table class="debug-mini-table">
                <thead>
                    <tr>
                        <th>Cod venta</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Cabecera</th>
                        <th>Sum l&iacute;neas</th>
                        <th>Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array)($m1['top_docs'] ?? []) as $doc): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($doc['cod_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($doc['fecha_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(trim((string)($doc['cod_cliente'] ?? '')) . ' ' . trim((string)($doc['nombre_cliente'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float)($doc['importe_cabecera'] ?? 0), 2, ',', '.') ?></td>
                        <td><?= number_format((float)($doc['sum_lineas'] ?? 0), 2, ',', '.') ?></td>
                        <td><?= number_format((float)($doc['diferencia'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($debugDoc !== ''): ?>
            <?php
                $cabRows = is_array($debugForenseDoc['cabeceras'] ?? null) ? $debugForenseDoc['cabeceras'] : [];
                $lineRowsM1 = is_array($debugForenseDoc['lineas_modelo_1'] ?? null) ? $debugForenseDoc['lineas_modelo_1'] : [];
                $sumasForense = is_array($debugForenseDoc['sumas'] ?? null) ? $debugForenseDoc['sumas'] : [];
                $sumM1 = is_array($sumasForense['modelo_1'] ?? null) ? $sumasForense['modelo_1'] : [];
                $cabTotalForense = (float)($sumasForense['cabecera_importe_total'] ?? 0);
            ?>
            <div class="debug-subtitle">Forense doc <?= htmlspecialchars($debugDoc, ENT_QUOTES, 'UTF-8') ?></div>

            <?php if (!empty($cabRows)): ?>
            <div class="debug-subtitle">1) Cabecera (hvc)</div>
            <table class="debug-mini-table">
                <thead>
                    <tr>
                        <th>cod_empresa</th>
                        <th>tipo_venta</th>
                        <th>cod_venta</th>
                        <th>cod_caja</th>
                        <th>fecha_venta</th>
                        <th>cod_cliente</th>
                        <th>cod_comisionista</th>
                        <th>importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cabRows as $cab): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($cab['cod_empresa'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($cab['tipo_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($cab['cod_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($cab['cod_caja'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($cab['fecha_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($cab['cod_cliente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($cab['cod_comisionista'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float)($cab['__importe_cast'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="item">No hay cabeceras para ese cod_venta con los filtros actuales.</div>
            <?php endif; ?>

            <div class="debug-subtitle">2) L&iacute;neas (Modelo 1)</div>
            <table class="debug-mini-table">
                <thead>
                    <tr>
                        <th>cod_caja</th>
                        <th>linea</th>
                        <th>articulo</th>
                        <th>cantidad</th>
                        <?php foreach ($abCampos as $campo): ?>
                        <th><?= htmlspecialchars($campo, ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                        <th>hvc_key</th>
                        <th>hvl_key</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineRowsM1 as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($l['cod_caja'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($l['linea'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($l['cod_articulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float)($l['cantidad'] ?? 0), 2, ',', '.') ?></td>
                        <?php foreach ($abCampos as $campo): ?>
                        <td><?= number_format((float)($l[$campo] ?? 0), 2, ',', '.') ?></td>
                        <?php endforeach; ?>
                        <td><?= htmlspecialchars((string)($l['hvc_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($l['hvl_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="debug-subtitle">3) SUM l&iacute;neas vs cabecera</div>
            <table class="debug-mini-table">
                <thead>
                    <tr>
                        <th>M&eacute;trica</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cabecera total</td>
                        <td><?= number_format($cabTotalForense, 2, ',', '.') ?></td>
                    </tr>
                    <?php foreach ($sumM1 as $campo => $valor): ?>
                    <tr>
                        <td>Modelo 1 SUM(<?= htmlspecialchars((string)$campo, ENT_QUOTES, 'UTF-8') ?>)</td>
                        <td><?= number_format((float)$valor, 2, ',', '.') ?> (delta: <?= number_format($cabTotalForense - (float)$valor, 2, ',', '.') ?>)</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<details class="debug-wrap" open>
    <summary>Depuraci&oacute;n cabecera vs l&iacute;neas</summary>
    <div class="debug-body">
        <form method="get" action="estadisticas_ventas_comerciales.php" class="debug-filters">
            <input type="hidden" name="debug" value="1">
            <input type="hidden" name="f_desde" value="<?= htmlspecialchars($fDesde, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="f_hasta" value="<?= htmlspecialchars($fHasta, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="vista_detalle" value="<?= htmlspecialchars($vistaDetalle, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="cod_comisionista" value="<?= htmlspecialchars($codComisionistaActivo, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="debug_doc" value="<?= htmlspecialchars($debugDoc, ENT_QUOTES, 'UTF-8') ?>">
            <select name="debug_motivo" onchange="this.form.submit()">
                <option value="">Motivo: todos</option>
                <?php foreach ($debugMotivos as $m): ?>
                <?php $mot = (string)($m['motivo'] ?? ''); ?>
                <option value="<?= htmlspecialchars($mot, ENT_QUOTES, 'UTF-8') ?>" <?= $debugMotivo === $mot ? 'selected' : '' ?>>
                    <?= htmlspecialchars($mot, ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="debug_zona" value="<?= htmlspecialchars($debugZona, ENT_QUOTES, 'UTF-8') ?>" placeholder="Filtro zona (ej. 10)" onchange="this.form.submit()">
            <input type="text" value="Rango: <?= htmlspecialchars($fDesde, ENT_QUOTES, 'UTF-8') ?> a <?= htmlspecialchars($fHasta, ENT_QUOTES, 'UTF-8') ?>" readonly>
        </form>

        <div class="debug-kpi">
            <div class="item"><strong>Total diferencia rango:</strong> <?= number_format((float)($debugTotales['total_diferencia_rango'] ?? 0), 2, ',', '.') ?> &euro;</div>
            <div class="item"><strong>Docs afectados:</strong> <?= (int)($debugTotales['docs_afectados'] ?? 0) ?></div>
        </div>

        <div class="debug-subtitle">Motivos (conteo)</div>
        <ul class="debug-list">
            <?php if (!empty($debugMotivos)): ?>
                <?php foreach ($debugMotivos as $m): ?>
                <li>
                    <?= htmlspecialchars((string)($m['motivo'] ?? 'DESCONOCIDO'), ENT_QUOTES, 'UTF-8') ?>:
                    <?= (int)($m['cantidad'] ?? 0) ?> docs,
                    <?= number_format((float)($m['total_diferencia'] ?? 0), 2, ',', '.') ?> &euro;
                </li>
                <?php endforeach; ?>
            <?php else: ?>
            <li>Sin documentos con descuadre en el filtro actual.</li>
            <?php endif; ?>
        </ul>

        <?php if (!empty($debugZonaResumen)): ?>
        <div class="debug-subtitle">Top zonas</div>
        <table class="debug-mini-table">
            <thead>
                <tr>
                    <th>Zona</th>
                    <th>Docs</th>
                    <th>Total diferencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($debugZonaResumen, 0, 10) as $z): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($z['zona'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)($z['docs_afectados'] ?? 0) ?></td>
                    <td><?= number_format((float)($z['total_diferencia'] ?? 0), 2, ',', '.') ?> &euro;</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($debugZonaClientes)): ?>
        <div class="debug-subtitle">Top clientes zona 10</div>
        <table class="debug-mini-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Nombre</th>
                    <th>Docs</th>
                    <th>Total diferencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($debugZonaClientes as $zc): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($zc['cod_cliente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($zc['nombre_cliente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)($zc['docs_afectados'] ?? 0) ?></td>
                    <td><?= number_format((float)($zc['total_diferencia'] ?? 0), 2, ',', '.') ?> &euro;</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="debug-subtitle">Detalle documentos (top 50)</div>
        <div class="table-wrap">
            <table class="debug-mini-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Documento</th>
                        <th>Cliente</th>
                        <th>Zona</th>
                        <th>Cabecera</th>
                        <th>Suma l&iacute;neas</th>
                        <th>Diferencia</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($debugFilas)): ?>
                        <?php foreach ($debugFilas as $filaDebug): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($filaDebug['fecha_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($filaDebug['cod_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($filaDebug['cod_cliente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($filaDebug['zona'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format((float)($filaDebug['importe_cabecera'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= number_format((float)($filaDebug['sum_importe_lineas'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= number_format((float)($filaDebug['diferencia'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars((string)($filaDebug['motivo'] ?? 'DESCONOCIDO'), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="8">Sin documentos con descuadre en el filtro actual.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>
<?php endif; ?>

<?php
$servicioPercent = (float)$kpis['porcentaje_servicio'] * 100;
$servicioPercent = max(0.0, $servicioPercent);
$servicioPercentBar = min(100.0, $servicioPercent);
$barraClase = 'barra-roja';
if ((float)$kpis['porcentaje_servicio'] >= 0.90) {
    $barraClase = 'barra-verde';
} elseif ((float)$kpis['porcentaje_servicio'] >= 0.80) {
    $barraClase = 'barra-amarilla';
}

$servicioPendiente = max(
    0.0,
    (float)$kpis['total_pedido'] - (float)$kpis['servicio_real']
);
$esExperimentalDebug = $debugFlag ? (($kpiServicioAjustado['es_experimental'] ?? false) === true) : false;
$servicioOperativoTotal = (float)($kpiServicioAjustado['servicio_operativo_total'] ?? (float)$kpis['servicio_real']);
$porcentajeServicioOperativo = min(
    100.0,
    max(0.0, (float)($kpiServicioAjustado['porcentaje_servicio_operativo'] ?? (float)$kpis['porcentaje_servicio']) * 100)
);
$totalHuerfanosImporte = (float)($kpiServicioAjustado['total_huerfanos_importe'] ?? 0);
$totalHuerfanosAsignadosImporte = (float)($kpiServicioAjustado['total_huerfanos_asignados_importe'] ?? 0);
$totalHuerfanosNoAsignablesImporte = (float)($kpiServicioAjustado['total_huerfanos_no_asignables_importe'] ?? 0);
$porcentajeHuerfanosAsignables = min(
    100.0,
    max(0.0, (float)($kpiServicioAjustado['porcentaje_huerfanos_asignables'] ?? 0) * 100)
);
$servicioDocumentalTotal = (float)($kpiServicioAjustado['total_servido_documental'] ?? $servicioOperativoTotal);
$servicioDocumentalPercent = ((float)$kpis['total_pedido'] > 0)
    ? min(100.0, max(0.0, ($servicioDocumentalTotal / (float)$kpis['total_pedido']) * 100))
    : 0.0;
$servicioRealPercent = $porcentajeServicioOperativo;
$servicioRealPercent = min(100.0, max(0.0, $servicioRealPercent));
$servicioRealPercentBar = $servicioRealPercent;
$barraClaseServicioReal = 'barra-roja';
if ($servicioRealPercent >= 90.0) {
    $barraClaseServicioReal = 'barra-verde';
} elseif ($servicioRealPercent >= 80.0) {
    $barraClaseServicioReal = 'barra-amarilla';
}
$claseBordeServicioReal = 'kpi-service-border-roja';
if ($barraClaseServicioReal === 'barra-verde') {
    $claseBordeServicioReal = 'kpi-service-border-verde';
} elseif ($barraClaseServicioReal === 'barra-amarilla') {
    $claseBordeServicioReal = 'kpi-service-border-amarilla';
}
$servicioRealTotal = $servicioOperativoTotal;
$servicioRealPendiente = max(
    0.0,
    (float)$kpis['total_pedido'] - $servicioRealTotal
);
$ajusteOperativoImporte = max(0.0, $servicioOperativoTotal - $servicioDocumentalTotal);
$tooltipAjusteOperativo = "Incluye entregas registradas sin relaci³n documental directa,
asignadas autom¡ticamente a pedidos pendientes mediante criterio FIFO
por cliente y art­culo. Este ajuste mejora la representaci³n del
servicio real al cliente.";
$servicioDetalleUrl = BASE_URL . '/ajax/estadisticas_detalle_servicio.php?' . http_build_query([
    'vista' => 'servicio_real',
    'cod_comisionista' => $codComisionistaActivo,
    'f_desde' => $fDesde,
    'f_hasta' => $fHasta,
]);
$snapshotCodComisionista = $codComisionistaActivo !== '' ? $codComisionistaActivo : 'Todos';
$snapshotTotalPedidosVentas = (float)$kpis['pedidos_ventas_importe'];
$snapshotTotalAlbaranesVentas = (float)$kpis['albaranes_ventas_importe'];
$snapshotKpiServicioActual = (float)$servicioRealTotal;
$snapshotPorcentajeServicioActual = (float)$servicioRealPercent;
$snapshotKpiServicioDocumentalDebug = is_array($kpiServicioDocumentalDebug)
    ? (float)($kpiServicioDocumentalDebug['servicio_real'] ?? 0)
    : null;
$snapshotPorcentajeServicioDocumentalDebug = is_array($kpiServicioDocumentalDebug)
    ? (float)($kpiServicioDocumentalDebug['porcentaje_servicio'] ?? 0) * 100
    : null;
$snapshotDeltaServicioDebug = ($snapshotKpiServicioDocumentalDebug !== null)
    ? ($snapshotKpiServicioActual - $snapshotKpiServicioDocumentalDebug)
    : null;
$pedidosPendientesTotal = (int)($kpiPedidosPendientes['pedidos_pendientes'] ?? 0);
$velocidadDiasMedia = (float)($kpiVelocidadServicio['dias_media'] ?? 0);
$velocidadLineasServidas = (int)($kpiVelocidadServicio['lineas_servidas'] ?? 0);
$lineasPendientesTotal = (int)($kpiLineasPendientes['lineas_pendientes'] ?? 0);
$lineasPendientesDiasMedia = (float)($kpiLineasPendientes['dias_media_pendiente'] ?? 0);
$backlogImporteTotal = (float)($kpiBacklogImporte['backlog_importe'] ?? 0);
$clientesConBacklogTotal = (int)($kpiClientesBacklog['clientes_con_backlog'] ?? 0);
$lineasCriticasTotal = (int)($kpiLineasCriticas['lineas_criticas'] ?? 0);

$porcentajeServicio = (float)$servicioRealPercent;
if ($porcentajeServicio >= 90) {
    $claseServicio = 'kpi-verde';
} elseif ($porcentajeServicio >= 80) {
    $claseServicio = 'kpi-amarillo';
} else {
    $claseServicio = 'kpi-rojo';
}

if ($kpiVelocidadServicio['dias_media'] < 2) {
    $claseVelocidad = 'kpi-verde';
} elseif ($kpiVelocidadServicio['dias_media'] <= 4) {
    $claseVelocidad = 'kpi-amarillo';
} else {
    $claseVelocidad = 'kpi-rojo';
}

if ($kpiLineasPendientes['lineas_pendientes'] < 20) {
    $claseLineas = 'kpi-verde';
} elseif ($kpiLineasPendientes['lineas_pendientes'] <= 50) {
    $claseLineas = 'kpi-amarillo';
} else {
    $claseLineas = 'kpi-rojo';
}
$anioAnterior = (int)date('Y', strtotime((string)$contexto['f_desde'])) - 1;

$renderComparacionAnual = static function (
    float $actual,
    ?float $anterior,
    int $decimalesValor = 2,
    string $sufijo = ' &euro;',
    int $anioAnterior = 0,
    bool $invertirLogica = false
): string {
    if ($anterior === null) {
        return '';
    }

    if (abs($anterior) > 0.00001) {
        $variacion = (($actual - $anterior) / abs($anterior)) * 100;
    } elseif (abs($actual) > 0.00001) {
        $variacion = 100.0;
    } else {
        $variacion = 0.0;
    }
    if ($invertirLogica) {
        // Para abonos mostramos diferencia en magnitud real
        $diferenciaReal = abs($actual) - abs($anterior);
    } else {
        $diferenciaReal = $actual - $anterior;
    }

    if ($invertirLogica) {
        // Para KPIs donde menos es mejor (ej: abonos)
        $mejora = abs($actual) < abs($anterior);
    } else {
        // Para KPIs normales (m¡s es mejor)
        $mejora = $actual > $anterior;
    }

    $clase = 'text-muted';
    $flecha = '&#8211;';

    if ($actual !== $anterior) {

        // Direcci³n del valor
        if ($invertirLogica) {
            // En abonos usamos magnitud real
            $direccionArriba = abs($actual) > abs($anterior);
        } else {
            $direccionArriba = $actual > $anterior;
        }

        // Color segºn mejora/empeora
        if ($mejora) {
            $clase = 'text-success';
        } else {
            $clase = 'text-danger';
        }

        // Flecha segºn direcci³n real
        if ($direccionArriba) {
            $flecha = '&#9650;'; // ¢¢â¬â²
        } else {
            $flecha = '&#9660;'; // ¢¢â¬â¼
        }
    }

    $anteriorFmt = number_format($anterior, $decimalesValor, ',', '.');
    $variacionFmt = number_format(abs($variacion), 1, ',', '.');
    $diferenciaHtml = '';

    if ($sufijo === ' &euro;') {
        $signo = $diferenciaReal > 0 ? '+' : '';
        $diferenciaFmt = number_format($diferenciaReal, 2, ',', '.');
        $diferenciaHtml = ' (' . $signo . $diferenciaFmt . ' &euro;)';
    }

    return '<div class="comparacion-wrapper">'
        . '<div class="small">vs ' . (int)$anioAnterior . ' &middot; ' . $anteriorFmt . $sufijo . '</div>'
        . '<div class="' . $clase . ' small">'
        . $flecha . ' ' . $variacionFmt . '%'
        . $diferenciaHtml
        . '</div>'
        . '</div>';
};

$ventaNetaRealActual = (float)$kpis['albaranes_ventas_importe'] + (float)$kpis['albaranes_abono_importe'];
$ventaNetaRealAnterior = null;
if ($compararActivo && is_array($resumenDocumentosAnterior)) {
    $ventaNetaRealAnterior = (float)($resumenDocumentosAnterior['albaranes_ventas_importe'] ?? 0)
        + (float)($resumenDocumentosAnterior['albaranes_abono_importe'] ?? 0);
}
$ventaNetaRealVarPct = null;
$ventaNetaRealDif = null;
$ventaNetaRealClase = '';
$ventaNetaRealFlecha = '&#8211;';
if ($ventaNetaRealAnterior !== null) {
    if (abs($ventaNetaRealAnterior) > 0.00001) {
        $ventaNetaRealVarPct = (($ventaNetaRealActual - $ventaNetaRealAnterior) / abs($ventaNetaRealAnterior)) * 100;
    } elseif (abs($ventaNetaRealActual) > 0.00001) {
        $ventaNetaRealVarPct = 100.0;
    } else {
        $ventaNetaRealVarPct = 0.0;
    }
    $ventaNetaRealDif = $ventaNetaRealActual - $ventaNetaRealAnterior;
    if ($ventaNetaRealDif > 0) {
        $ventaNetaRealClase = 'positivo';
        $ventaNetaRealFlecha = '&#9650;';
    } elseif ($ventaNetaRealDif < 0) {
        $ventaNetaRealClase = 'negativo';
        $ventaNetaRealFlecha = '&#9660;';
    }
}
?>

<div class="kpi-block">
<?php if ($debugFlag): ?>
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#7c2d12;">
    <strong>Snapshot debug</strong>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 14px;margin-top:6px;">
        <div>cod_comisionista: <strong><?= htmlspecialchars((string)$snapshotCodComisionista, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div>f_desde: <strong><?= htmlspecialchars((string)$fDesde, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div>f_hasta: <strong><?= htmlspecialchars((string)$fHasta, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div>total_pedidos_ventas: <strong><?= number_format($snapshotTotalPedidosVentas, 2, ',', '.') ?> &euro;</strong></div>
        <div>total_albaranes_ventas: <strong><?= number_format($snapshotTotalAlbaranesVentas, 2, ',', '.') ?> &euro;</strong></div>
        <div>kpi_servicio_actual_ajustado: <strong><?= number_format($snapshotKpiServicioActual, 2, ',', '.') ?> &euro;</strong></div>
        <div>porcentaje_servicio_actual: <strong><?= number_format($snapshotPorcentajeServicioActual, 2, ',', '.') ?>%</strong></div>
        <?php if ($snapshotKpiServicioDocumentalDebug !== null): ?>
        <div>kpi_servicio_documental_debug: <strong><?= number_format($snapshotKpiServicioDocumentalDebug, 2, ',', '.') ?> &euro;</strong></div>
        <div>porcentaje_servicio_documental_debug: <strong><?= number_format((float)$snapshotPorcentajeServicioDocumentalDebug, 2, ',', '.') ?>%</strong></div>
        <div>delta_operativo_vs_documental: <strong><?= number_format((float)$snapshotDeltaServicioDebug, 2, ',', '.') ?> &euro;</strong></div>
        <div>es_experimental_debug: <strong><?= $esExperimentalDebug ? '1' : '0' ?></strong></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<section class="kpi-grid kpi-grid-documental">
<div class="card-neta-real">
<div class="card-neta-real-amount" id="kpi-venta-neta-real-amount"><?= number_format($ventaNetaRealActual, 2, ',', '.') ?> &euro;</div>
<?php if ($compararActivo && $ventaNetaRealVarPct !== null && $ventaNetaRealDif !== null): ?>
<p class="card-neta-real-compare <?= $ventaNetaRealClase ?>">
<?= $ventaNetaRealFlecha ?>
<?= $ventaNetaRealDif > 0 ? '+' : '' ?><?= number_format(abs($ventaNetaRealVarPct), 1, ',', '.') ?>% vs <?= (int)$anioAnterior ?>
&middot;
<?= $ventaNetaRealDif > 0 ? '+' : '' ?><?= number_format($ventaNetaRealDif, 2, ',', '.') ?> &euro;
</p>
<?php endif; ?>
</div>

<div class="card-doc">
<div class="card-doc-inner">
<div class="contenido-principal">
<div class="card-title"><span id="kpi-pedidos-ventas-num"><?= (int)$kpis['pedidos_ventas_num'] ?></span> PEDIDOS DE VENTA</div>
<div class="card-doc-amount" id="kpi-pedidos-ventas-importe"><?= number_format((float)$kpis['pedidos_ventas_importe'], 2, ',', '.') ?> &euro;</div>
</div>
<div class="flex-grow-1"></div>
<?php if ($compararActivo): ?>
<?= $renderComparacionAnual(
    (float)$kpis['pedidos_ventas_importe'],
    is_array($resumenDocumentosAnterior) ? (float)($resumenDocumentosAnterior['pedidos_ventas_importe'] ?? 0) : null,
    2,
    ' &euro;',
    $anioAnterior,
    false
) ?>
<?php endif; ?>
</div>
</div>

<div class="card-doc">
<div class="card-doc-inner">
<div class="contenido-principal">
<div class="card-title"><span id="kpi-albaranes-ventas-num"><?= (int)$kpis['albaranes_ventas_num'] ?></span> ALBARANES DE VENTA</div>
<div class="card-doc-amount" id="kpi-albaranes-ventas-importe"><?= number_format((float)$kpis['albaranes_ventas_importe'], 2, ',', '.') ?> &euro;</div>
<div class="card-doc-breakdown" id="kpi-albaranes-ventas-breakdown">
<span><?= $albaranesConPedidoNum ?> CON PEDIDO - <?= number_format($albaranesConPedidoImporte, 2, ',', '.') ?> &euro;</span><br>
<span><?= $albaranesSinPedidoNum ?> SIN PEDIDO - <?= number_format($albaranesSinPedidoImporte, 2, ',', '.') ?> &euro;</span>
</div>
</div>
<div class="flex-grow-1"></div>
<?php if ($compararActivo): ?>
<?= $renderComparacionAnual(
    (float)$kpis['albaranes_ventas_importe'],
    is_array($resumenDocumentosAnterior) ? (float)($resumenDocumentosAnterior['albaranes_ventas_importe'] ?? 0) : null,
    2,
    ' &euro;',
    $anioAnterior,
    false
) ?>
<?php endif; ?>
</div>
</div>

<div class="card-doc red">
<div class="card-doc-inner">
<div class="contenido-principal">
<div class="card-title"><span id="kpi-pedidos-abono-num"><?= (int)$kpis['pedidos_abono_num'] ?></span> PEDIDOS DE ABONO</div>
<div class="card-doc-amount" id="kpi-pedidos-abono-importe"><?= number_format((float)$kpis['pedidos_abono_importe'], 2, ',', '.') ?> &euro;</div>
</div>
<div class="flex-grow-1"></div>
<?php if ($compararActivo): ?>
<?= $renderComparacionAnual(
    (float)$kpis['pedidos_abono_importe'],
    is_array($resumenDocumentosAnterior) ? (float)($resumenDocumentosAnterior['pedidos_abono_importe'] ?? 0) : null,
    2,
    ' &euro;',
    $anioAnterior,
    true
) ?>
<?php endif; ?>
</div>
</div>

<div class="card-doc red">
<div class="card-doc-inner">
<div class="contenido-principal">
<div class="card-title"><span id="kpi-albaranes-abono-num"><?= (int)$kpis['albaranes_abono_num'] ?></span> ALBARANES DE ABONO</div>
<div class="card-doc-amount">
<span id="kpi-albaranes-abono-importe"><?= number_format((float)$kpis['albaranes_abono_importe'], 2, ',', '.') ?> &euro;</span>
<span class="card-doc-percent-inline">(<?= number_format((float)$kpis['porcentaje_devolucion_importe'] * 100, 2, ',', '.') ?>%)</span>
</div>
<div class="card-doc-breakdown" id="kpi-albaranes-abono-breakdown">
<span><?= $albaranesAbonoConPedidoNum ?> CON PEDIDO - <?= number_format($albaranesAbonoConPedidoImporte, 2, ',', '.') ?> &euro;</span><br>
<span><?= $albaranesAbonoSinPedidoNum ?> SIN PEDIDO - <?= number_format($albaranesAbonoSinPedidoImporte, 2, ',', '.') ?> &euro;</span>
</div>
</div>
<div class="flex-grow-1"></div>
<?php if ($compararActivo): ?>
<?= $renderComparacionAnual(
    (float)$kpis['albaranes_abono_importe'],
    is_array($resumenDocumentosAnterior) ? (float)($resumenDocumentosAnterior['albaranes_abono_importe'] ?? 0) : null,
    2,
    ' &euro;',
    $anioAnterior,
    true
) ?>
<?php endif; ?>
</div>
</div>
</section>
</div>

<div class="kpi-block">
<h2 class="kpi-section-title">KPIS SERVICIO</h2>
<section class="kpi-grid" style="grid-template-columns: repeat(3, 1fr);">
<div
    class="kpi-card kpi-service-card card-servicio kpi-service-compact"
>
<div class="card-body d-flex flex-column">
<div>
<p class="kpi-title">Servicio %</p>
<p class="kpi-value <?= $claseServicio ?>" id="kpi-servicio-porcentaje"><?= number_format($servicioRealPercent, 2, ',', '.') ?>%</p>
<p class="kpi-service-summary" id="kpi-servicio-summary">
    <?= number_format((float)$kpis['total_pedido'], 2, ',', '.') ?> &euro; &rarr; <?= number_format($servicioRealTotal, 2, ',', '.') ?> &euro;
</p>
</div>
</div>
</div>

<div class="kpi-card">
<div class="card-body d-flex flex-column">
<p class="kpi-title">Primera entrega</p>
<p class="kpi-value" id="kpi-velocidad-reaccion">0,00 d&iacute;as</p>
<p class="kpi-service-summary" id="kpi-primera-entrega-importe">0,00 &euro;</p>
</div>
</div>

<div class="kpi-card">
<div class="card-body d-flex flex-column">
<p class="kpi-title">&Uacute;ltima entrega</p>
<p class="kpi-value <?= $claseVelocidad ?>" id="kpi-velocidad-servicio"><?= number_format($velocidadDiasMedia, 2, ',', '.') ?> d&iacute;as</p>
<p class="kpi-service-summary" id="kpi-ultima-entrega-importe">0,00 &euro;</p>
</div>
</div>
</section>
</div>
<?php if ($vistaDetalle !== '' && !empty($detalleVista['filas'])): ?>

<div class="table-wrap">
<h2 class="table-title">
Detalle: <?= htmlspecialchars($detalleVista['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</h2>

<table>
<thead>
<tr>
<th>Documento</th>
<th>Fecha</th>
<th>Cliente</th>
<th>Nombre</th>
<th>Comercial</th>
<th>Importe</th>
</tr>
</thead>
<tbody>

<?php foreach ($detalleVista['filas'] as $fila): ?>
<?php
$fechaVentaTxt = (string)($fila['fecha_venta'] ?? '');
$fechaVentaFmt = $fechaVentaTxt;
if ($fechaVentaTxt !== '') {
    try {
        $fechaVentaFmt = (new DateTimeImmutable($fechaVentaTxt))->format('d-m-Y');
    } catch (Throwable $e) {
        $fechaVentaFmt = $fechaVentaTxt;
    }
}
?>
<tr>
<td><?= htmlspecialchars($fila['cod_venta'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($fechaVentaFmt, ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($fila['cod_cliente'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars(toUTF8($fila['nombre_cliente']), ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($fila['cod_comisionista'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= number_format((float)$fila['importe'], 2, ',', '.') ?></td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<?php endif; ?>

<?php if ($profilerEnabled && !empty($profilerMs)): ?>
<div class="table-wrap">
<h2 class="table-title">Micro-profiler (debug=1)</h2>
<table>
<thead>
<tr>
<th>Bloque</th>
<th>Tiempo (ms)</th>
</tr>
</thead>
<tbody>
<?php foreach ($profilerMs as $bloque => $ms): ?>
<tr>
<td><?= htmlspecialchars((string)$bloque, ENT_QUOTES, 'UTF-8') ?></td>
<td><?= number_format((float)$ms, 2, ',', '.') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

</main>

<script>
(function () {
    var form = document.querySelector('.filters');
    if (!form) return;

    var selects = form.querySelectorAll('select');
    var toggles = form.querySelectorAll('input[type="checkbox"]');
    var dateInputs = form.querySelectorAll('input[type="date"]');
    var timer = null;
    var submitInFlight = false;
    var compararToggle = form.querySelector('#comparar');
    var compararHidden = form.querySelector('input[name="comparar"][data-comparar-hidden]');

    function clearPendingSubmit() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function submitNow() {
        clearPendingSubmit();
        if (compararHidden && compararToggle) {
            compararHidden.value = compararToggle.checked ? '1' : '0';
        }
        if (submitInFlight) return;
        submitInFlight = true;
        form.submit();
        window.setTimeout(function () {
            submitInFlight = false;
        }, 1500);
    }

    // Selects -> submit inmediato
    selects.forEach(function (el) {
        el.addEventListener('change', function () {
            submitNow();
        });
    });

    // Toggle -> submit inmediato
    toggles.forEach(function (el) {
        el.addEventListener('change', function () {
            submitNow();
        });
    });

    function isValidIsoDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        var y = parseInt(value.slice(0, 4), 10);
        var m = parseInt(value.slice(5, 7), 10);
        var d = parseInt(value.slice(8, 10), 10);
        var dt = new Date(Date.UTC(y, m - 1, d));
        return dt.getUTCFullYear() === y &&
               (dt.getUTCMonth() + 1) === m &&
               dt.getUTCDate() === d;
    }

    function submitDatesIfValid() {
        var desdeInput = form.querySelector('input[name="f_desde"]');
        var hastaInput = form.querySelector('input[name="f_hasta"]');
        if (!desdeInput || !hastaInput) return;

        var desde = (desdeInput.value || '').trim();
        var hasta = (hastaInput.value || '').trim();

        if (!isValidIsoDate(desde) || !isValidIsoDate(hasta)) return;

        if (desde > hasta) {
            var tmp = desde;
            desde = hasta;
            hasta = tmp;
            desdeInput.value = desde;
            hastaInput.value = hasta;
        }

        submitNow();
    }

    function scheduleDateSubmit() {
        clearPendingSubmit();
        timer = setTimeout(function () {
            submitDatesIfValid();
        }, 1000);
    }

    dateInputs.forEach(function (el) {
        // Escritura/edicion -> debounce 1000ms
        el.addEventListener('input', function () {
            scheduleDateSubmit();
        });

        // Compatibilidad extra de teclado
        el.addEventListener('keyup', function (e) {
            if (e.key === 'Enter') {
                submitNow();
                return;
            }
            scheduleDateSubmit();
        });

        // Date picker mobile/desktop
        el.addEventListener('change', function () {
            scheduleDateSubmit();
        });
    });
})();

</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function initFiltroCustom(cfg) {
        const btn = document.getElementById(cfg.buttonId);
        const panel = document.getElementById(cfg.panelId);
        const buscador = document.getElementById(cfg.searchId);
        const lista = document.getElementById(cfg.listId);
        const label = document.getElementById(cfg.labelId);
        const select = document.getElementById(cfg.selectId);
        if (!btn || !panel || !buscador || !lista || !label || !select) {
            return;
        }

        btn.addEventListener('click', function () {
            const isOpen = panel.style.display === 'block';
            document.querySelectorAll('.panel-filtro').forEach(function (p) {
                p.style.display = 'none';
            });
            panel.style.display = isOpen ? 'none' : 'block';
            if (!isOpen) {
                buscador.focus();
            }
        });

        lista.querySelectorAll('div[data-value]').forEach(function (item) {
            item.addEventListener('click', function () {
                const value = item.dataset.value || '';
                select.value = value;
                label.innerText = item.innerText.trim();
                panel.style.display = 'none';
                if (select.form) {
                    select.form.submit();
                }
            });
        });

        buscador.addEventListener('input', function () {
            const texto = buscador.value.toLowerCase();
            lista.querySelectorAll('div[data-value]').forEach(function (item) {
                item.style.display = item.innerText.toLowerCase().includes(texto) ? 'block' : 'none';
            });
        });
    }

    initFiltroCustom({
        buttonId: 'btn-filtro-marca',
        panelId: 'panel-filtro-marca',
        searchId: 'buscar-marca',
        listId: 'lista-marcas',
        labelId: 'filtro-marca-label',
        selectId: 'filtro-marca'
    });

    initFiltroCustom({
        buttonId: 'btn-filtro-familia',
        panelId: 'panel-filtro-familia',
        searchId: 'buscar-familia',
        listId: 'lista-familias',
        labelId: 'filtro-familia-label',
        selectId: 'filtro-familia'
    });

    initFiltroCustom({
        buttonId: 'btn-filtro-subfamilia',
        panelId: 'panel-filtro-subfamilia',
        searchId: 'buscar-subfamilia',
        listId: 'lista-subfamilias',
        labelId: 'filtro-subfamilia-label',
        selectId: 'filtro-subfamilia'
    });

    initFiltroCustom({
        buttonId: 'btn-filtro-comercial',
        panelId: 'panel-filtro-comercial',
        searchId: 'buscar-comercial',
        listId: 'lista-comerciales',
        labelId: 'filtro-comercial-label',
        selectId: 'filtro-comercial'
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.filter-dropdown')) {
            document.querySelectorAll('.panel-filtro').forEach(function (p) {
                p.style.display = 'none';
            });
        }
    });
});
</script>
<script>
function formatearNumero(valor, decimales = 2) {
    const numero = Number(valor) || 0;
    return new Intl.NumberFormat('es-ES', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales
    }).format(numero);
}

function formatearMoneda(valor) {
    return formatearNumero(valor, 2) + ' ';
}

function formatearEntero(valor) {
    return formatearNumero(valor, 0);
}

function setText(id, texto) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = texto;
    }
}

function setHtml(id, html) {
    const el = document.getElementById(id);
    if (el) {
        el.innerHTML = html;
    }
}

function setKpiColorClass(id, clase) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('kpi-verde', 'kpi-amarillo', 'kpi-rojo');
    el.classList.add(clase);
}

function construirBreakdownAlbaranesVentas(data) {
    const conPedidoNum = formatearEntero(data?.con_pedido_num ?? 0);
    const conPedidoImporte = formatearMoneda(data?.con_pedido_importe ?? 0);
    const sinPedidoNum = formatearEntero(data?.sin_pedido_num ?? 0);
    const sinPedidoImporte = formatearMoneda(data?.sin_pedido_importe ?? 0);

    return conPedidoNum + ' CON PEDIDO - ' + conPedidoImporte
        + '<br>'
        + sinPedidoNum + ' SIN PEDIDO - ' + sinPedidoImporte;
}

function construirBreakdownAlbaranesAbono(data) {
    const conPedidoNum = formatearEntero(data?.con_pedido_num ?? 0);
    const conPedidoImporte = formatearMoneda(data?.con_pedido_importe ?? 0);
    const sinPedidoNum = formatearEntero(data?.sin_pedido_num ?? 0);
    const sinPedidoImporte = formatearMoneda(data?.sin_pedido_importe ?? 0);

    return conPedidoNum + ' CON PEDIDO - ' + conPedidoImporte
        + '<br>'
        + sinPedidoNum + ' SIN PEDIDO - ' + sinPedidoImporte;
}

function construirResumenServicio(servicio) {
    return formatearMoneda(servicio?.total_pedido ?? 0)
        + ' \u2192 '
        + formatearMoneda(servicio?.servicio_real ?? 0);
}

function construirTooltipServicio(servicio) {
    const totalPedido = Number(servicio?.total_pedido ?? 0);
    const servicioReal = Number(servicio?.servicio_real ?? 0);
    const pendiente = Math.max(0, totalPedido - servicioReal);
    const servicioDocumental = Number(servicio?.total_servido_documental ?? servicioReal);
    const porcentajeDocumental = totalPedido > 0 ? (servicioDocumental / totalPedido * 100) : 0;
    const ajusteOperativo = Math.max(0, Number(servicio?.servicio_operativo_total ?? servicioReal) - servicioDocumental);
    const huerfanosAsignados = Number(servicio?.total_huerfanos_asignados_importe ?? 0);

    setHtml('kpi-servicio-tooltip', [
        '<p>Total pedido: ' + formatearMoneda(totalPedido) + '</p>',
        '<p>Total servido real: ' + formatearMoneda(servicioReal) + '</p>',
        '<p>Pendiente: ' + formatearMoneda(pendiente) + '</p>',
        '<p>Servicio documental: ' + formatearMoneda(servicioDocumental) + ' (' + formatearNumero(porcentajeDocumental, 2) + '%)</p>',
        '<p>Ajuste operativo aplicado: ' + formatearMoneda(ajusteOperativo) + '</p>',
        '<p>Huerfanos asignados: ' + formatearMoneda(huerfanosAsignados) + '</p>'
    ].join(''));
}

function renderKpisDocumentos(data) {
    const documentos = data?.documentos ?? {};
    const albaranesVentas = data?.albaranes_ventas ?? {};
    const albaranesAbonos = data?.albaranes_abonos ?? {};

    setText('kpi-pedidos-ventas-num', formatearEntero(documentos?.pedidos_ventas_num ?? 0));
    setText('kpi-pedidos-ventas-importe', formatearMoneda(documentos?.pedidos_ventas_importe ?? 0));
    setText('kpi-albaranes-ventas-num', formatearEntero(documentos?.albaranes_ventas_num ?? 0));
    setText('kpi-albaranes-ventas-importe', formatearMoneda(documentos?.albaranes_ventas_importe ?? 0));
    setHtml('kpi-albaranes-ventas-breakdown', construirBreakdownAlbaranesVentas(albaranesVentas));
    setText('kpi-pedidos-abono-num', formatearEntero(documentos?.pedidos_abono_num ?? 0));
    setText('kpi-pedidos-abono-importe', formatearMoneda(documentos?.pedidos_abono_importe ?? 0));
    setText('kpi-albaranes-abono-num', formatearEntero(documentos?.albaranes_abono_num ?? 0));
    setText('kpi-albaranes-abono-importe', formatearMoneda(documentos?.albaranes_abono_importe ?? 0));
    setHtml('kpi-albaranes-abono-breakdown', construirBreakdownAlbaranesAbono(albaranesAbonos));

    const ventaNetaReal = Number(documentos?.albaranes_ventas_importe ?? 0) + Number(documentos?.albaranes_abono_importe ?? 0);
    setText('kpi-venta-neta-real-amount', formatearMoneda(ventaNetaReal));
}

function renderKpisServicio(data) {
    const servicioPorcentaje = Number(data?.servicio_pct ?? 0) * 100;
    const importePedido = Number(data?.importe_pedido ?? 0);
    const importeServido = Number(data?.importe_servido ?? 0);
    const primeraEntregaDias = data?.primera_entrega_dias === null ? null : Number(data?.primera_entrega_dias);
    const primeraEntregaImporte = Number(data?.primera_entrega_importe ?? 0);
    const ultimaEntregaDias = data?.ultima_entrega_dias === null ? null : Number(data?.ultima_entrega_dias);
    const ultimaEntregaImporte = Number(data?.ultima_entrega_importe ?? 0);

    setText('kpi-servicio-porcentaje', formatearNumero(servicioPorcentaje, 2) + '%');
    setText('kpi-servicio-summary', formatearMoneda(importePedido) + ' \u2192 ' + formatearMoneda(importeServido));
    setText(
        'kpi-velocidad-reaccion',
        primeraEntregaDias === null ? 'N/D' : (formatearNumero(primeraEntregaDias, 2) + ' d\u00EDas')
    );
    setText('kpi-primera-entrega-importe', formatearMoneda(primeraEntregaImporte));
    setText(
        'kpi-velocidad-servicio',
        ultimaEntregaDias === null ? 'N/D' : (formatearNumero(ultimaEntregaDias, 2) + ' d\u00EDas')
    );
    setText('kpi-ultima-entrega-importe', formatearMoneda(ultimaEntregaImporte));

    const claseServicio = servicioPorcentaje >= 90 ? 'kpi-verde' : (servicioPorcentaje >= 80 ? 'kpi-amarillo' : 'kpi-rojo');
    const claseReaccion = (primeraEntregaDias !== null && primeraEntregaDias < 2)
        ? 'kpi-verde'
        : ((primeraEntregaDias !== null && primeraEntregaDias <= 4) ? 'kpi-amarillo' : 'kpi-rojo');
    const claseVelocidad = (ultimaEntregaDias !== null && ultimaEntregaDias < 2)
        ? 'kpi-verde'
        : ((ultimaEntregaDias !== null && ultimaEntregaDias <= 4) ? 'kpi-amarillo' : 'kpi-rojo');

    setKpiColorClass('kpi-servicio-porcentaje', claseServicio);
    setKpiColorClass('kpi-velocidad-reaccion', claseReaccion);
    setKpiColorClass('kpi-velocidad-servicio', claseVelocidad);
}

function mostrarLoader() {
    const loader = document.getElementById('loader-estadisticas');
    if (loader) {
        loader.style.display = 'flex';
    }
}

function ocultarLoader() {
    const loader = document.getElementById('loader-estadisticas');
    if (loader) {
        loader.style.display = 'none';
    }
}

function cargarKpis() {
    mostrarLoader();

    const params = new URLSearchParams(window.location.search);
    try {
        Promise.all([
                    fetch('<?= BASE_URL ?>/ajax/estadisticas_documentos.php?' + params.toString(), {
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            }),
                    fetch('<?= BASE_URL ?>/ajax/estadisticas_servicio.php?' + params.toString(), {
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
        ])
        .then(function (results) {
            const docData = results[0];
            const servicioData = results[1];

            renderKpisDocumentos(docData);
            renderKpisServicio(servicioData);
            ocultarLoader();
        })
        .catch(function (err) {
            console.error('Error cargando KPIs', err);
            ocultarLoader();
        });
    } catch (err) {
        console.error('Error cargando KPIs', err);
        ocultarLoader();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    cargarKpis();
});
</script>

</body>
</html>

<?php
if (isset($conn)) {
}
?>










