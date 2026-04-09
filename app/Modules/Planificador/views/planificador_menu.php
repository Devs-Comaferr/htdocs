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
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';
requierePermiso('perm_planificador');

$pageTitle = 'Planificacion de Rutas';

$conn = db();
$codVendedorMenu = obtenerCodVendedorPlanificacionService();
$fechaHoyMenu = date('Y-m-d');

function obtenerConteoPlanificadorMenu($conn, string $sql, array $params = []): ?int
{
    $stmt = @odbc_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    if (!@odbc_execute($stmt, $params)) {
        return null;
    }

    $fila = @odbc_fetch_array($stmt);
    if (!$fila) {
        return null;
    }

    $total = $fila['total'] ?? $fila['TOTAL'] ?? null;
    if ($total === null) {
        return null;
    }

    return (int)$total;
}

function obtenerResumenVisitasPlanificador($conn, int $codVendedor, string $fechaInicio, string $fechaFinExclusiva, int $codZona = 0): array
{
    if ($codVendedor <= 0 || $fechaInicio === '' || $fechaFinExclusiva === '') {
        return ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
    }

    $sql = "
        SELECT
            v.id_visita,
            v.cod_cliente,
            v.estado_visita
        FROM cmf_comerciales_visitas v
        WHERE v.cod_vendedor = ?
          AND CONVERT(varchar(10), v.fecha_visita, 120) >= ?
          AND CONVERT(varchar(10), v.fecha_visita, 120) < ?
    ";

    $params = [$codVendedor, $fechaInicio, $fechaFinExclusiva];

    if ($codZona > 0) {
        $sql .= "
          AND (
                v.cod_zona_visita = ?
                OR EXISTS (
                    SELECT 1
                    FROM cmf_comerciales_clientes_zona cz
                    WHERE cz.cod_cliente = v.cod_cliente
                      AND (cz.zona_principal = ? OR cz.zona_secundaria = ?)
                )
          )
        ";
        $params[] = $codZona;
        $params[] = $codZona;
        $params[] = $codZona;
    }

    $stmt = @odbc_prepare($conn, $sql);
    if (!$stmt || !@odbc_execute($stmt, $params)) {
        return ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
    }

    $visitas = [];
    $idsVisita = [];
    while ($fila = @odbc_fetch_array($stmt)) {
        $idVisita = (int)($fila['id_visita'] ?? $fila['ID_VISITA'] ?? 0);
        if ($idVisita <= 0) {
            continue;
        }

        $estado = strtolower(trim((string)($fila['estado_visita'] ?? $fila['ESTADO_VISITA'] ?? '')));
        $visitas[$idVisita] = [
            'estado' => $estado,
            'tiene_visita_pedido' => false,
        ];
        $idsVisita[] = $idVisita;
    }

    if (empty($visitas)) {
        return ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
    }

    $idsSql = implode(',', array_map('intval', array_unique($idsVisita)));
    $sqlPedidos = "
        SELECT DISTINCT vp.id_visita
        FROM cmf_comerciales_visitas_pedidos vp
        WHERE vp.id_visita IN ($idsSql)
          AND LOWER(LTRIM(RTRIM(ISNULL(vp.origen, '')))) = 'visita'
    ";
    $resultPedidos = @odbc_exec($conn, $sqlPedidos);
    if ($resultPedidos) {
        while ($filaPedido = @odbc_fetch_array($resultPedidos)) {
            $idVisita = (int)($filaPedido['id_visita'] ?? $filaPedido['ID_VISITA'] ?? 0);
            if ($idVisita > 0 && isset($visitas[$idVisita])) {
                $visitas[$idVisita]['tiene_visita_pedido'] = true;
            }
        }
    }

    $resumen = ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
    foreach ($visitas as $visita) {
        $estado = $visita['estado'];
        $tieneVisitaPedido = !empty($visita['tiene_visita_pedido']);

        $esTotal = ($estado === 'realizada' && $tieneVisitaPedido)
            || in_array($estado, ['planificada', 'pendiente', 'no atendida'], true);
        $esRealizada = ($estado === 'realizada' && $tieneVisitaPedido)
            || $estado === 'no atendida';
        $esPendiente = in_array($estado, ['planificada', 'pendiente'], true);

        if ($esTotal) {
            $resumen['total']++;
        }
        if ($esRealizada) {
            $resumen['realizadas']++;
        }
        if ($esPendiente) {
            $resumen['pendientes']++;
        }
    }

    return $resumen;
}

$resumenHoy = obtenerResumenVisitasPlanificador(
    $conn,
    $codVendedorMenu,
    $fechaHoyMenu,
    date('Y-m-d', strtotime($fechaHoyMenu . ' +1 day'))
);

$totalPedidosSinAsignar = obtenerConteoPlanificadorMenu(
    $conn,
    "SELECT COUNT(*) AS total
     FROM hist_ventas_cabecera h
     LEFT JOIN cmf_comerciales_visitas_pedidos vp ON h.cod_venta = vp.cod_venta
     WHERE vp.cod_venta IS NULL
       AND h.tipo_venta = 1
       AND h.fecha_venta >= '2025-01-01'
       AND h.cod_comisionista = ?",
    [$codVendedorMenu]
);

$totalZonasActivas = count(obtenerZonasVisita() ?? []);
$zonasCiclo = obtenerZonasVisita() ?? [];
$zonaActiva = obtenerZonaActivaHoyService();
$zonaActivaId = (int)($zonaActiva['cod_zona'] ?? 0);
$nombreZonaActiva = trim((string)($zonaActiva['nombre'] ?? ''));
if ($nombreZonaActiva === '') {
    $nombreZonaActiva = 'No definida';
}
$clienteRecomendado = obtenerSiguienteClienteRecomendadoService($zonaActivaId);
$nombreClienteRecomendado = trim((string)toUTF8($clienteRecomendado['nombre'] ?? ''));
$motivoClienteRecomendado = trim((string)($clienteRecomendado['motivo'] ?? ''));

$contextoZonaActiva = function_exists('obtenerZonaActivaPorFecha')
    ? obtenerZonaActivaPorFecha($conn, $codVendedorMenu, $fechaHoyMenu)
    : null;
$inicioCicloActual = !empty($contextoZonaActiva['ciclo_actual_inicio_ts'])
    ? date('Y-m-d', (int)$contextoZonaActiva['ciclo_actual_inicio_ts'])
    : '';
$finCicloActual = !empty($contextoZonaActiva['ciclo_actual_fin_ts'])
    ? date('Y-m-d', (int)$contextoZonaActiva['ciclo_actual_fin_ts'])
    : '';
$resumenCicloActual = ($inicioCicloActual !== '' && $finCicloActual !== '')
    ? obtenerResumenVisitasPlanificador($conn, $codVendedorMenu, $inicioCicloActual, $finCicloActual)
    : ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];

$pedidosSinAsignarCriticos = ($totalPedidosSinAsignar ?? 0) > 0;
$pendientesHoy = (int)($resumenHoy['pendientes'] ?? 0);
$visitasHoy = (int)($resumenHoy['total'] ?? 0);
$realizadasHoy = (int)($resumenHoy['realizadas'] ?? 0);
$progresoHoy = (int)(
    ($visitasHoy > 0)
        ? ($realizadasHoy / $visitasHoy) * 100
        : 0
);

$visitasZonaActual = (int)($resumenCicloActual['total'] ?? 0);
$realizadasZonaActual = (int)($resumenCicloActual['realizadas'] ?? 0);
$pendientesZonaActual = (int)($resumenCicloActual['pendientes'] ?? 0);
$progresoZonaActual = (int)(
    ($visitasZonaActual > 0)
        ? ($realizadasZonaActual / $visitasZonaActual) * 100
        : 0
);
$rangoCicloActualLabel = ($inicioCicloActual !== '' && $finCicloActual !== '')
    ? date('d/m', strtotime($inicioCicloActual)) . ' - ' . date('d/m', strtotime($finCicloActual . ' -1 day'))
    : 'Ciclo no definido';

$cards = [
    [
        'key' => 'calendar',
        'href' => 'mostrar_calendario.php',
        'label' => 'Calendario',
        'icon_class' => 'fas fa-calendar-alt',
        'icon_wrapper' => 'icon-blue',
        'title' => 'Calendario',
        'description' => 'Consulta la agenda diaria y revisa la planificacion operativa.',
        'cta' => 'Abrir vista',
        'metric_value' => (string)$visitasHoy,
        'metric_label' => 'visitas hoy',
        'metric_class' => $visitasHoy > 0 ? 'metric-ok' : '',
        'status_text' => null,
        'status_class' => '',
        'card_class' => 'card-ok',
        'importance' => 2,
    ],
    [
        'key' => 'complete',
        'href' => 'completar_dia.php',
        'label' => 'Completar dia',
        'icon_class' => 'fas fa-check-circle',
        'icon_wrapper' => 'icon-red',
        'title' => 'Completar dia',
        'description' => 'Cierra la jornada y valida el trabajo pendiente.',
        'cta' => 'Finalizar jornada',
        'metric_value' => (string)$pendientesHoy,
        'metric_label' => 'sugerencias',
        'metric_class' => $pendientesHoy > 0 ? 'metric-warning' : 'metric-ok',
        'status_text' => null,
        'status_class' => '',
        'card_class' => $pendientesHoy > 0 ? 'card-warning' : 'card-ok',
        'importance' => 1,
    ],
    [
        'key' => 'orders',
        'href' => 'visita_pedido.php',
        'label' => 'Visita por pedidos',
        'icon_class' => 'fa-solid fa-pen-to-square',
        'icon_wrapper' => 'icon-green',
        'title' => 'Visita por pedidos',
        'description' => 'Relaciona pedidos con visitas desde el flujo comercial.',
        'cta' => 'Gestionar pedidos',
        'metric_value' => $totalPedidosSinAsignar !== null ? (string)$totalPedidosSinAsignar : null,
        'metric_label' => 'pedidos',
        'metric_class' => $pedidosSinAsignarCriticos ? 'metric-critical' : 'metric-ok',
        'status_text' => null,
        'status_class' => '',
        'card_class' => $pedidosSinAsignarCriticos ? 'card-critical' : 'card-ok',
        'importance' => $pedidosSinAsignarCriticos ? 1 : 3,
    ],
    [
        'key' => 'manual',
        'href' => 'visita_manual.php',
        'label' => 'Registrar visita manual',
        'icon_class' => 'fas fa-edit',
        'icon_wrapper' => 'icon-yellow',
        'title' => 'Visita manual',
        'description' => 'Crea visitas puntuales fuera del flujo automatico.',
        'cta' => 'Registrar visita',
        'metric_value' => null,
        'metric_label' => null,
        'metric_class' => '',
        'status_text' => null,
        'status_class' => '',
        'card_class' => 'card-secondary',
        'importance' => 1,
    ],
    [
        'key' => 'holiday',
        'href' => 'festivo_local.php',
        'label' => 'Festivo local',
        'icon_class' => 'fas fa-flag',
        'icon_wrapper' => 'icon-orange',
        'title' => 'Festivo local',
        'description' => 'Configura excepciones del calendario por zona.',
        'cta' => 'Configurar festivo',
        'metric_value' => null,
        'metric_label' => null,
        'metric_class' => '',
        'status_text' => null,
        'status_class' => '',
        'card_class' => 'card-tertiary card-low',
        'importance' => 3,
    ],
    [
        'key' => 'zones',
        'href' => 'zonas.php',
        'label' => 'Zonas',
        'icon_class' => 'fas fa-route',
        'icon_wrapper' => 'icon-purple',
        'title' => 'Zonas',
        'description' => 'Organiza la estructura comercial por zonas.',
        'cta' => 'Ver zonas',
        'metric_value' => $totalZonasActivas > 0 ? (string)$totalZonasActivas : null,
        'metric_label' => 'zonas',
        'metric_class' => $totalZonasActivas > 0 ? 'metric-ok' : '',
        'status_text' => null,
        'status_class' => '',
        'card_class' => 'card-secondary',
        'importance' => 3,
    ],
    [
        'key' => 'assign',
        'href' => 'zonas_clientes.php',
        'label' => 'Asignar clientes a zonas',
        'icon_class' => 'fas fa-user-plus',
        'icon_wrapper' => 'icon-cyan',
        'title' => 'Asignar clientes',
        'description' => 'Relaciona clientes y zonas para preparar rutas.',
        'cta' => 'Asignar clientes',
        'metric_value' => null,
        'metric_label' => null,
        'metric_class' => '',
        'status_text' => null,
        'status_class' => '',
        'card_class' => 'card-tertiary',
        'importance' => 3,
    ],
    [
        'key' => 'reset-cycle',
        'href' => '#',
        'label' => 'Reiniciar ciclos',
        'icon_class' => 'fas fa-rotate-left',
        'icon_wrapper' => 'icon-slate',
        'title' => 'Reiniciar ciclos',
        'description' => 'Ajusta fecha comun y orden real de zonas tras vacaciones o cambios de ruta.',
        'cta' => 'Configurar ciclo',
        'metric_value' => null,
        'metric_label' => null,
        'metric_class' => '',
        'status_text' => null,
        'status_class' => '',
        'card_class' => 'card-tertiary card-low',
        'importance' => 3,
        'modal_target' => '#reiniciarCiclosModal',
    ],
];

$cardsByKey = [];
foreach ($cards as $card) {
    $cardsByKey[$card['key']] = $card;
}

$ordenBase = $pedidosSinAsignarCriticos
    ? ['orders', 'manual', 'calendar', 'complete', 'zones', 'assign', 'holiday', 'reset-cycle', 'closed']
    : ['manual', 'calendar', 'complete', 'orders', 'zones', 'assign', 'holiday', 'reset-cycle', 'closed'];

$cardsOrdenadas = [];
foreach ($ordenBase as $key) {
    if (isset($cardsByKey[$key])) {
        $cardsOrdenadas[] = $cardsByKey[$key];
        unset($cardsByKey[$key]);
    }
}
foreach ($cardsByKey as $cardRestante) {
    $cardsOrdenadas[] = $cardRestante;
}
$cards = $cardsOrdenadas;

foreach ($cards as &$card) {
    $importance = (int)($card['importance'] ?? 3);

    if ($importance <= 1) {
        $card['span_class'] = 'card-large';
        $card['compact'] = false;
    } elseif ($importance === 2) {
        $card['span_class'] = 'card-medium';
        $card['compact'] = false;
    } else {
        $card['span_class'] = 'card-mini';
        $card['compact'] = true;
    }
}
unset($card);

$flashMensaje = trim((string)($_GET['mensaje'] ?? ''));
$flashEstado = trim((string)($_GET['estado'] ?? ''));
$abrirModalReiniciar = trim((string)($_GET['modal'] ?? '')) === 'reiniciar_ciclos';

usort($zonasCiclo, static function ($a, $b) {
    $ordenA = (int)($a['orden'] ?? 0);
    $ordenB = (int)($b['orden'] ?? 0);

    if ($ordenA === $ordenB) {
        return strcmp((string)($a['nombre_zona'] ?? ''), (string)($b['nombre_zona'] ?? ''));
    }

    return $ordenA <=> $ordenB;
});
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
            background: linear-gradient(to bottom, #f8fafc, #eef2f7);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .routes-container {
            max-width: 1100px;
            margin: 16px auto 24px;
            padding: 0 24px;
            width: 100%;
            box-sizing: border-box;
        }

        .container-cards,
        .routes-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
        }

        .dashboard-top {
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
        }

        .dashboard-box {
            grid-column: span 6;
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            box-sizing: border-box;
        }

        .dashboard-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #111827;
        }

        .dashboard-metric {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .dashboard-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .dashboard-item {
            padding: 12px;
            border-radius: 14px;
            background: #f8fafc;
        }

        .dashboard-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }

        .dashboard-note {
            font-size: 14px;
            color: #475569;
            margin: 0 0 8px;
        }

        .dashboard-subtext {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .card,
        .route-card {
            background: #ffffff;
            border: none;
            border-radius: 16px;
            padding: 12px 14px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            transition: all 0.25s cubic-bezier(.4,0,.2,1);
            cursor: pointer;
            text-decoration: none;
            color: #1f2937;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            text-align: left;
            will-change: transform;
            box-sizing: border-box;
        }

        .card-large {
            grid-column: span 6;
        }

        .card-medium {
            grid-column: span 3;
        }

        .card-mini {
            grid-column: span 2;
            padding: 6px 8px;
        }

        .card:hover,
        .route-card:hover {
            color: #1f2937;
            transform: translateY(-6px) scale(1.01);
            box-shadow: 0 18px 40px rgba(0,0,0,0.12);
        }

        .hover-shadow:hover {
            transform: translateY(-2px);
            transition: 0.2s ease;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important;
        }

        .card-critical {
            border: 1px solid rgba(220,38,38,0.2);
            box-shadow: 0 20px 50px rgba(220,38,38,0.15);
            background: linear-gradient(180deg, #ffffff 0%, #fff7f7 100%);
        }

        .icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .icon-wrapper i,
        .route-icon i {
            font-size: 20px;
        }

        .card-large .icon-wrapper {
            width: 52px;
            height: 52px;
            margin-bottom: 12px;
        }

        .card-large .icon-wrapper i,
        .card-large .route-icon i {
            font-size: 24px;
        }

        .card-mini .icon-wrapper {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .card-mini .icon-wrapper i,
        .card-mini .route-icon i {
            font-size: 16px;
        }

        .icon-blue { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
        .icon-green { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
        .icon-yellow { background: rgba(234, 179, 8, 0.12); color: #ca8a04; }
        .icon-red { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
        .icon-orange { background: rgba(249, 115, 22, 0.12); color: #f97316; }
        .icon-slate { background: rgba(71, 85, 105, 0.12); color: #475569; }
        .icon-purple { background: rgba(147, 51, 234, 0.12); color: #9333ea; }
        .icon-cyan { background: rgba(6, 182, 212, 0.12); color: #0891b2; }

        .card h3,
        .route-card h3 {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 4px;
        }

        .card p,
        .route-card p {
            font-size: 13px;
            color: #6b7280;
            margin: 0 0 12px;
            line-height: 1.5;
        }

        .card .cta,
        .route-card .cta {
            margin-top: auto;
            font-size: 13px;
            font-weight: 600;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .card:hover .cta,
        .route-card:hover .cta {
            transform: translateX(4px);
        }

        .card-metrics {
            margin-top: 10px;
            margin-bottom: 12px;
        }

        .card-metric-block {
            margin-top: 12px;
            margin-bottom: 14px;
        }

        .metric-value {
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
            color: #111827;
        }

        .metric-label {
            margin-top: 2px;
            font-size: 11px;
            color: #6b7280;
        }

        .card-mini p,
        .card-mini .cta {
            display: none;
        }

        .card-low {
            opacity: 0.7;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 999px;
            font-weight: 600;
            margin-top: 2px;
            margin-bottom: 10px;
        }

        .status-warning {
            background: rgba(234, 179, 8, 0.15);
            color: #92400e;
        }

        .status-success {
            background: rgba(34, 197, 94, 0.15);
            color: #065f46;
        }

        .status-critical {
            background: rgba(220, 38, 38, 0.14);
            color: #991b1b;
        }

        .card-secondary {
            background: #ffffff;
        }

        .card-tertiary {
            background: #f9fafb;
        }

        .card-warning {
            border: 1px solid rgba(234,179,8,0.2);
        }

        .card-ok {
            border: 1px solid rgba(34,197,94,0.2);
        }

        .metric-critical {
            color: #dc2626;
        }

        .metric-warning {
            color: #ca8a04;
        }

        .metric-ok {
            color: #16a34a;
        }

        .flash-message {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
        }

        .flash-message.ok {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .flash-message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .card-button {
            width: 100%;
            border: none;
            cursor: pointer;
        }

        .card-button:focus-visible {
            outline: 3px solid rgba(37, 99, 235, 0.35);
            outline-offset: 2px;
        }

        .cycle-form-note {
            margin-bottom: 16px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .cycle-date-group {
            margin-bottom: 18px;
        }

        .cycle-date-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .cycle-date-group input[type="date"] {
            width: 100%;
            max-width: 240px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 15px;
        }

        .cycle-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .cycle-item {
            display: grid;
            grid-template-columns: 54px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 12px 14px;
            background: #f8fafc;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
            touch-action: none;
        }

        .cycle-item.dragging {
            opacity: 0.8;
            transform: scale(1.01);
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .cycle-order-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(37, 99, 235, 0.12);
            color: #1d4ed8;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .cycle-zone-name {
            font-weight: 700;
            color: #111827;
        }

        .cycle-zone-meta {
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .cycle-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cycle-move-button {
            width: 40px;
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .cycle-drag-handle {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #e2e8f0;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: grab;
        }

        .cycle-drag-handle:active {
            cursor: grabbing;
        }

        .cycle-empty {
            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 1100px) {
            .routes-container {
                margin-top: 16px;
                padding: 0 18px;
            }

            .container-cards,
            .routes-grid {
                grid-template-columns: repeat(12, minmax(0, 1fr));
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-box {
                grid-column: span 12;
            }

            .container-cards,
            .routes-grid {
                grid-template-columns: repeat(12, minmax(0, 1fr));
                gap: 10px;
            }

            .routes-container {
                margin-bottom: 28px;
            }

            .card-large {
                grid-column: span 4;
            }

            .card-medium {
                grid-column: span 3;
            }

            .card-mini {
                grid-column: span 2;
            }
        }

        @media (max-width: 520px) {
            body {
                padding-top: 70px;
            }

            .routes-container {
                padding: 0 12px;
            }

            .dashboard-summary {
                grid-template-columns: 1fr;
            }

            .container-cards,
            .routes-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .card,
            .route-card {
                padding: 14px 12px;
            }

            .card-large,
            .card-medium {
                grid-column: span 2;
            }

            .card-mini {
                grid-column: span 1;
            }

            .card-medium .cta {
                display: inline-flex;
            }

            .cycle-item {
                grid-template-columns: 46px minmax(0, 1fr);
            }

            .cycle-controls {
                grid-column: span 2;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

    <div class="routes-container">
        <?php if ($flashMensaje !== ''): ?>
            <div class="flash-message <?= $flashEstado === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($flashMensaje, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="container-fluid mb-4 px-0">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <div class="card shadow-sm h-100 border-0">
                                <div class="card-body">
                                    <div class="text-muted small mb-1">HOY</div>
                                    <h5 class="fw-bold mb-3">
                                        <?= htmlspecialchars($nombreZonaActiva ?? 'Sin zona', ENT_QUOTES, 'UTF-8') ?>
                                    </h5>

                                    <div>
                                        <div class="fw-semibold fs-5">
                                            <?= $visitasHoy ?> visitas
                                        </div>

                                        <div class="text-muted small">
                                            <?= $realizadasHoy ?> realizadas &middot;
                                            <span class="text-warning"><?= $pendientesHoy ?> pendientes</span>
                                        </div>

                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar" style="width: <?= $progresoHoy ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <div class="card shadow-sm h-100 border-0">
                                <div class="card-body">
                                    <div class="text-muted small mb-1">ZONA ACTUAL</div>
                                    <h5 class="fw-bold mb-2">
                                        <?= htmlspecialchars($rangoCicloActualLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </h5>

                                    <div>
                                        <div class="fw-semibold fs-5">
                                            <?= $visitasZonaActual ?> visitas
                                        </div>

                                        <div class="text-muted small">
                                            <?= $realizadasZonaActual ?> realizadas &middot;
                                            <span class="text-warning"><?= $pendientesZonaActual ?> pendientes</span>
                                        </div>

                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?= $progresoZonaActual ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body">
                            <div class="text-muted small mb-1">SIGUIENTE ACCI&Oacute;N</div>
                            <?php if (!empty($nombreClienteRecomendado)): ?>
                                <h5 class="fw-bold mb-2">
                                    <?= htmlspecialchars($nombreClienteRecomendado, ENT_QUOTES, 'UTF-8') ?>
                                </h5>

                                <div class="text-muted small">
                                    <?= htmlspecialchars($motivoClienteRecomendado ?: 'No hay datos disponibles', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php else: ?>
                                <h5 class="fw-bold mb-2">Dia sin urgencias</h5>

                                <div class="text-muted small">
                                    No hay clientes prioritarios ahora mismo.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="routes-grid container-cards">
            <?php foreach ($cards as $card): ?>
                <?php if (!empty($card['modal_target'])): ?>
                    <button
                        type="button"
                        class="route-card card card-button <?= htmlspecialchars($card['card_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['span_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['key'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?>"
                        data-bs-toggle="modal"
                        data-bs-target="<?= htmlspecialchars($card['modal_target'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <div class="route-icon icon-wrapper <?= htmlspecialchars($card['icon_wrapper'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="<?= htmlspecialchars($card['icon_class'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <h3><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (!$card['compact']): ?>
                            <p><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <span class="route-link cta"><?= htmlspecialchars($card['cta'], ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-arrow-right"></i></span>
                    </button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($card['href'], ENT_QUOTES, 'UTF-8') ?>"
                       class="route-card card <?= htmlspecialchars($card['card_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['span_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['key'], ENT_QUOTES, 'UTF-8') ?>"
                       aria-label="<?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="route-icon icon-wrapper <?= htmlspecialchars($card['icon_wrapper'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="<?= htmlspecialchars($card['icon_class'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <h3><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (!$card['compact']): ?>
                            <p><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($card['metric_value'] !== null && $card['metric_label'] !== null): ?>
                            <div class="card-metric-block">
                                <div class="metric-value <?= htmlspecialchars($card['metric_class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($card['metric_value'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="metric-label"><?= htmlspecialchars($card['metric_label'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($card['status_text']) && !empty($card['status_class'])): ?>
                            <div class="status-badge <?= htmlspecialchars($card['status_class'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($card['status_text'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <span class="route-link cta"><?= htmlspecialchars($card['cta'], ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal fade" id="reiniciarCiclosModal" tabindex="-1" aria-labelledby="reiniciarCiclosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="procesar_reiniciar_ciclos.php" id="reiniciarCiclosForm">
                    <?= csrfInput() ?>
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-bold" id="reiniciarCiclosModalLabel">Reiniciar ciclos</h5>
                            <div class="text-muted small">Fecha comun para todas las zonas y orden ajustado de forma tactil.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <p class="cycle-form-note">Arrastra las zonas para colocarlas en el orden correcto. Si en algun movil el arrastre no resulta comodo, tambien puedes subir o bajar cada zona con las flechas.</p>

                        <div class="cycle-date-group">
                            <label for="fecha_inicio_ciclo_modal">Fecha de inicio del ciclo</label>
                            <input type="date" id="fecha_inicio_ciclo_modal" name="fecha_inicio_ciclo" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <?php if (!empty($zonasCiclo)): ?>
                            <ul class="cycle-list" id="cycleList">
                                <?php foreach ($zonasCiclo as $zona): ?>
                                    <?php $codZona = (int)($zona['cod_zona'] ?? 0); ?>
                                    <li class="cycle-item" data-cod-zona="<?= htmlspecialchars((string)$codZona, ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="cycle-order-badge">1</div>
                                        <div>
                                            <div class="cycle-zone-name"><?= htmlspecialchars(toUTF8((string)($zona['nombre_zona'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="cycle-zone-meta">
                                                <?= (int)($zona['duracion_semanas'] ?? 0) ?> semana(s) · Orden actual <?= (int)($zona['orden'] ?? 0) ?>
                                            </div>
                                        </div>
                                        <div class="cycle-controls">
                                            <button type="button" class="cycle-move-button" data-direction="up" aria-label="Subir zona">
                                                <i class="fas fa-chevron-up"></i>
                                            </button>
                                            <button type="button" class="cycle-move-button" data-direction="down" aria-label="Bajar zona">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                            <span class="cycle-drag-handle" aria-hidden="true">
                                                <i class="fas fa-grip-vertical"></i>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div id="cycleHiddenInputs"></div>
                        <?php else: ?>
                            <div class="cycle-empty">No hay zonas disponibles para este comercial.</div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" <?= empty($zonasCiclo) ? 'disabled' : '' ?>>Guardar ciclo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var list = document.getElementById('cycleList');
            var hiddenInputsContainer = document.getElementById('cycleHiddenInputs');
            var form = document.getElementById('reiniciarCiclosForm');
            var modalElement = document.getElementById('reiniciarCiclosModal');
            var draggedItem = null;

            if (!list || !hiddenInputsContainer || !form) {
                return;
            }

            function updateCycleOrder() {
                var items = list.querySelectorAll('.cycle-item');
                hiddenInputsContainer.innerHTML = '';

                items.forEach(function (item, index) {
                    var order = index + 1;
                    var badge = item.querySelector('.cycle-order-badge');
                    var codZona = item.getAttribute('data-cod-zona');
                    var input = document.createElement('input');

                    if (badge) {
                        badge.textContent = order;
                    }

                    input.type = 'hidden';
                    input.name = 'ordenes[' + codZona + ']';
                    input.value = order;
                    hiddenInputsContainer.appendChild(input);
                });
            }

            function moveItem(item, direction) {
                if (!item) {
                    return;
                }

                var sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
                if (!sibling) {
                    return;
                }

                if (direction === 'up') {
                    list.insertBefore(item, sibling);
                } else {
                    list.insertBefore(sibling, item);
                }

                updateCycleOrder();
            }

            list.querySelectorAll('.cycle-move-button').forEach(function (button) {
                button.addEventListener('click', function () {
                    moveItem(button.closest('.cycle-item'), button.getAttribute('data-direction'));
                });
            });

            list.querySelectorAll('.cycle-item').forEach(function (item) {
                item.draggable = true;

                item.addEventListener('dragstart', function () {
                    draggedItem = item;
                    item.classList.add('dragging');
                });

                item.addEventListener('dragend', function () {
                    item.classList.remove('dragging');
                    draggedItem = null;
                    updateCycleOrder();
                });

                item.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    if (!draggedItem || draggedItem === item) {
                        return;
                    }

                    var rect = item.getBoundingClientRect();
                    var before = event.clientY < rect.top + (rect.height / 2);
                    list.insertBefore(draggedItem, before ? item : item.nextElementSibling);
                });
            });

            var pointerState = {
                item: null
            };

            list.querySelectorAll('.cycle-drag-handle').forEach(function (handle) {
                handle.addEventListener('pointerdown', function (event) {
                    var item = handle.closest('.cycle-item');
                    if (!item) {
                        return;
                    }

                    pointerState.item = item;
                    item.classList.add('dragging');
                    handle.setPointerCapture(event.pointerId);
                    event.preventDefault();
                });

                handle.addEventListener('pointermove', function (event) {
                    var item = pointerState.item;
                    if (!item) {
                        return;
                    }

                    var target = document.elementFromPoint(event.clientX, event.clientY);
                    if (!target) {
                        return;
                    }

                    var overItem = target.closest('.cycle-item');
                    if (!overItem || overItem === item || overItem.parentElement !== list) {
                        return;
                    }

                    var rect = overItem.getBoundingClientRect();
                    var before = event.clientY < rect.top + (rect.height / 2);
                    list.insertBefore(item, before ? overItem : overItem.nextElementSibling);
                    updateCycleOrder();
                });

                function releasePointer(event) {
                    var item = pointerState.item;
                    pointerState.item = null;
                    if (item) {
                        item.classList.remove('dragging');
                    }
                    if (handle.hasPointerCapture && handle.hasPointerCapture(event.pointerId)) {
                        handle.releasePointerCapture(event.pointerId);
                    }
                    updateCycleOrder();
                }

                handle.addEventListener('pointerup', releasePointer);
                handle.addEventListener('pointercancel', releasePointer);
            });

            form.addEventListener('submit', updateCycleOrder);
            updateCycleOrder();

            <?php if ($abrirModalReiniciar): ?>
            if (modalElement && window.bootstrap && window.bootstrap.Modal) {
                window.addEventListener('load', function () {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                });
            }
            <?php endif; ?>
        })();
    </script>
</body>
</html>
