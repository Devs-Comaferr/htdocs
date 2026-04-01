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
require_once BASE_PATH . '/app/Modules/Planificador/planificador_service.php';
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
    if (!$fila || !isset($fila['total'])) {
        return null;
    }

    return (int)$fila['total'];
}

$totalVisitasHoy = obtenerConteoPlanificadorMenu(
    $conn,
    "SELECT COUNT(*) AS total
     FROM cmf_visitas_cliente
     WHERE cod_vendedor = ?
       AND CONVERT(date, fecha_visita) = ?
       AND UPPER(ISNULL(estado_visita, '')) <> 'DESCARTADA'",
    [$codVendedorMenu, $fechaHoyMenu]
);

$totalPendientesHoy = obtenerConteoPlanificadorMenu(
    $conn,
    "SELECT COUNT(*) AS total
     FROM cmf_visitas_cliente
     WHERE cod_vendedor = ?
       AND CONVERT(date, fecha_visita) = ?
       AND UPPER(ISNULL(estado_visita, '')) IN ('PENDIENTE', 'PLANIFICADA')",
    [$codVendedorMenu, $fechaHoyMenu]
);

$totalPedidosSinAsignar = obtenerConteoPlanificadorMenu(
    $conn,
    "SELECT COUNT(*) AS total
     FROM hist_ventas_cabecera h
     LEFT JOIN cmf_visita_pedidos vp ON h.cod_venta = vp.cod_venta
     WHERE vp.cod_venta IS NULL
       AND h.tipo_venta = 1
       AND h.fecha_venta >= '2025-01-01'
       AND h.cod_comisionista = ?",
    [$codVendedorMenu]
);

$totalZonasActivas = count(obtenerZonasVisita() ?? []);
$zonaActiva = obtenerZonaActivaHoyService();
$nombreZonaActiva = trim((string)($zonaActiva['nombre'] ?? ''));
if ($nombreZonaActiva === '') {
    $nombreZonaActiva = 'No definida';
}
$clienteRecomendado = obtenerSiguienteClienteRecomendadoService();
$nombreClienteRecomendado = trim((string)($clienteRecomendado['nombre'] ?? ''));
if ($nombreClienteRecomendado === '') {
    $nombreClienteRecomendado = 'Sin recomendaciones';
}
$motivoClienteRecomendado = trim((string)($clienteRecomendado['motivo'] ?? ''));

$pedidosSinAsignarCriticos = ($totalPedidosSinAsignar ?? 0) > 0;
$pendientesHoy = $totalPendientesHoy ?? 0;
$visitasHoy = $totalVisitasHoy ?? 0;

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
        'metric_value' => $totalVisitasHoy !== null ? (string)$visitasHoy : null,
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
        'metric_value' => $totalPendientesHoy !== null ? (string)$pendientesHoy : null,
        'metric_label' => 'sugerencias',
        'metric_class' => $pendientesHoy > 0 ? 'metric-warning' : 'metric-ok',
        'status_text' => null,
        'status_class' => '',
        'card_class' => $pendientesHoy > 0 ? 'card-warning' : 'card-ok',
        'importance' => 1,
    ],
    [
        'key' => 'orders',
        'href' => 'pedidos_visitas.php',
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
        'href' => 'registrar_visita_manual.php',
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
        'key' => 'closed',
        'href' => 'registrar_dia_no_laborable.php',
        'label' => 'No laborable',
        'icon_class' => 'fas fa-ban',
        'icon_wrapper' => 'icon-slate',
        'title' => 'No laborable',
        'description' => 'Bloquea dias no operativos dentro de la planificacion.',
        'cta' => 'Bloquear dia',
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
        'href' => 'asignacion_clientes_zonas.php',
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
];

$cardsByKey = [];
foreach ($cards as $card) {
    $cardsByKey[$card['key']] = $card;
}

$ordenBase = $pedidosSinAsignarCriticos
    ? ['orders', 'manual', 'calendar', 'complete', 'zones', 'assign', 'holiday', 'closed']
    : ['manual', 'calendar', 'complete', 'orders', 'zones', 'assign', 'holiday', 'closed'];

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
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

    <div class="routes-container">
        <div class="dashboard-top">
            <div class="dashboard-box">
                <div class="dashboard-title">Hoy</div>
                <div class="dashboard-summary">
                    <div class="dashboard-item">
                        <div class="dashboard-label">Zona activa</div>
                        <div class="dashboard-metric"><?= htmlspecialchars($nombreZonaActiva, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="dashboard-item">
                        <div class="dashboard-label">Visitas hoy</div>
                        <div class="dashboard-metric"><?= htmlspecialchars((string)$visitasHoy, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="dashboard-item">
                        <div class="dashboard-label">Pendientes</div>
                        <div class="dashboard-metric"><?= htmlspecialchars((string)$pendientesHoy, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="dashboard-item">
                        <div class="dashboard-label">Pedidos sin asignar</div>
                        <div class="dashboard-metric"><?= htmlspecialchars((string)$totalPedidosSinAsignar, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>

            <div class="dashboard-box">
                <div class="dashboard-title">Siguiente accion</div>
                <div class="dashboard-label">Cliente recomendado</div>
                <p class="dashboard-note"><?= htmlspecialchars($nombreClienteRecomendado, ENT_QUOTES, 'UTF-8') ?></p>
                <span class="dashboard-subtext"><?= htmlspecialchars($motivoClienteRecomendado, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="routes-grid container-cards">
            <?php foreach ($cards as $card): ?>
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
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
