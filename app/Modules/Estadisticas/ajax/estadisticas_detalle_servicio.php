<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_estadisticas');
require_once BASE_PATH . '/app/Support/statistics.php';
require_once BASE_PATH . '/app/Support/statistics.php';

$fDesde = normalizarFechaIso((string)($_GET['f_desde'] ?? ''), date('Y-01-01'));
$fHasta = normalizarFechaIso((string)($_GET['f_hasta'] ?? ''), date('Y-m-d'));
if ($fDesde > $fHasta) {
    [$fDesde, $fHasta] = [$fHasta, $fDesde];
}

$queryNormalizada = $_GET;
$queryNormalizada['f_desde'] = $fDesde;
$queryNormalizada['f_hasta'] = $fHasta;

$codComisionistaGet = trim((string)($_GET['cod_comisionista'] ?? ''));
if ($codComisionistaGet !== '' && ctype_digit($codComisionistaGet)) {
    $queryNormalizada['cod_comisionista'] = $codComisionistaGet;
}

$contexto = resolverContextoFiltros($conn, $_SESSION, $queryNormalizada);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$debugFlag = (trim((string)($_GET['debug'] ?? '')) === '1');

$contextoKpi = $contexto;
unset($contextoKpi['forzar_detalle_servicio']);
$contextoKpi['forzar_detalle_servicio'] = false;
$contextoKpi['vista_detalle'] = '';

$resumenDocumentos = obtenerResumenDocumentosSeparados($conn, $contextoKpi);
$kpiServicioAjustado = obtenerKpiServicioPedidosAjustado(
    $conn,
    $contextoKpi,
    (float)($resumenDocumentos['pedidos_ventas_importe'] ?? 0)
);

$contextoDetalle = $contexto;
$contextoDetalle['forzar_detalle_servicio'] = true;
$contextoDetalle['vista_detalle'] = 'servicio_real';
$detalle = obtenerDetalleServicioPedidos(
    $conn,
    $contextoDetalle,
    $limit,
    $offset
);
$totalRegistros = (int)($detalle['total_registros'] ?? 0);
$totalPaginas = (int)ceil($totalRegistros / $limit);
$vistaDetalleDebug = trim((string)($contextoDetalle['vista_detalle'] ?? ($contexto['vista_detalle'] ?? '')));

$totalPedido = (float)($kpiServicioAjustado['total_pedido'] ?? 0);
$totalServidoReal = (float)($kpiServicioAjustado['servicio_operativo_total'] ?? ($kpiServicioAjustado['servicio_real'] ?? 0));
$pendiente = max(0.0, $totalPedido - $totalServidoReal);
$porcentajeReal = ($totalPedido > 0) ? min(1.0, $totalServidoReal / $totalPedido) : 0.0;

function calcularDiasLaborables($fechaInicio, $fechaFin): int
{
    $inicio = strtotime((string)$fechaInicio);
    $fin = strtotime((string)$fechaFin);

    if (!$inicio || !$fin || $inicio > $fin) {
        return 0;
    }

    $dias = 0;
    while ($inicio <= $fin) {
        $diaSemana = (int)date('N', $inicio);
        if ($diaSemana < 6) {
            $dias++;
        }
        $inicio = strtotime('+1 day', $inicio);
    }

    return $dias;
}

$baseParams = $_GET;
$buildPageUrl = static function (int $pageNum) use ($baseParams): string {
    $params = $baseParams;
    $params['page'] = $pageNum;
    return BASE_URL . '/ajax/estadisticas_detalle_servicio.php?' . http_build_query($params);
};

$renderPaginacion = static function (int $pageActual, int $total, callable $urlBuilder): string {
    if ($total <= 1) {
        return '';
    }

    $paginas = [1];
    $desde = max(2, $pageActual - 2);
    $hasta = min($total - 1, $pageActual + 2);
    for ($p = $desde; $p <= $hasta; $p++) {
        $paginas[] = $p;
    }
    if ($total > 1) {
        $paginas[] = $total;
    }
    $paginas = array_values(array_unique($paginas));
    sort($paginas);

    $html = '<nav class="drawer-pagination" aria-label="Paginacion detalle servicio">';
    if ($pageActual > 1) {
        $html .= '<a class="pager-chip pager-nav" href="' . htmlspecialchars($urlBuilder($pageActual - 1), ENT_QUOTES, 'UTF-8') . '">Anterior</a>';
    } else {
        $html .= '<span class="pager-chip pager-nav is-disabled">Anterior</span>';
    }

    $prev = null;
    foreach ($paginas as $p) {
        if ($prev !== null && $p > $prev + 1) {
            $html .= '<span class="pager-ellipsis">&hellip;</span>';
        }
        if ($p === $pageActual) {
            $html .= '<span class="pager-chip is-active">' . $p . '</span>';
        } else {
            $html .= '<a class="pager-chip" href="' . htmlspecialchars($urlBuilder($p), ENT_QUOTES, 'UTF-8') . '">' . $p . '</a>';
        }
        $prev = $p;
    }

    if ($pageActual < $total) {
        $html .= '<a class="pager-chip pager-nav" href="' . htmlspecialchars($urlBuilder($pageActual + 1), ENT_QUOTES, 'UTF-8') . '">Siguiente</a>';
    } else {
        $html .= '<span class="pager-chip pager-nav is-disabled">Siguiente</span>';
    }
    $html .= '</nav>';
    return $html;
};

?>
<div class="drawer-servicio-detalle" style="font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;color:#334155;">
    <?php if ($debugFlag): ?>
    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#7c2d12;">
        <strong>Debug resumen</strong>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 14px;margin-top:6px;">
            <div>vista_detalle: <strong><?= htmlspecialchars($vistaDetalleDebug !== '' ? $vistaDetalleDebug : '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div>total_filas: <strong><?= number_format($totalRegistros, 0, ',', '.') ?></strong></div>
            <div>pagina: <strong><?= number_format($page, 0, ',', '.') ?></strong></div>
            <div>page_size: <strong><?= number_format($limit, 0, ',', '.') ?></strong></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="resumen-principal">
        <div class="bloque">
            <div>Total pedido</div>
            <div class="valor-grande"><?= number_format($totalPedido, 2, ',', '.') ?> &euro;</div>
        </div>
        <div class="bloque">
            <div>Total servido real</div>
            <div class="valor-grande"><?= number_format($totalServidoReal, 2, ',', '.') ?> &euro;</div>
        </div>
        <div class="bloque">
            <div>Pendiente</div>
            <div class="valor-grande"><?= number_format($pendiente, 2, ',', '.') ?> &euro;</div>
        </div>
        <div class="bloque">
            <div>% servicio real</div>
            <div class="valor-grande"><?= number_format($porcentajeReal * 100, 2, ',', '.') ?>%</div>
        </div>
    </div>


    <?php
    $pedidosPendientes = array_values(array_filter(
        (array)($detalle['filas'] ?? []),
        static function (array $p): bool {
            return (float)($p['pendiente'] ?? 0) > 0;
        }
    ));
    usort($pedidosPendientes, static function (array $a, array $b): int {
        return ((float)($b['pendiente'] ?? 0)) <=> ((float)($a['pendiente'] ?? 0));
    });
    $pedidosPendientes = array_map(static function (array $p): array {
        if (!array_key_exists('porcentaje_servicio', $p)) {
            $p['porcentaje_servicio'] = (float)($p['porcentaje_servido'] ?? 0);
        }
        return $p;
    }, $pedidosPendientes);
    $fechaFinResumen = date('Y-m-d', strtotime('-1 day'));
    $pedidosPendientes = array_map(static function (array $p) use ($fechaFinResumen): array {
        $dias = calcularDiasLaborables((string)($p['fecha'] ?? ''), $fechaFinResumen);
        $riesgo = calcularRiesgoLineaServicio([
            'pendiente' => (float)($p['pendiente'] ?? 0),
            'porcentaje_servicio' => (float)($p['porcentaje_servicio'] ?? ($p['porcentaje_servido'] ?? 0)),
            'dias' => $dias,
            'historico' => (string)($p['historico'] ?? 'N'),
        ]);
        $nivelRiesgo = (string)($riesgo['nivel'] ?? 'verde');
        $claseRiesgo = '';
        switch ($nivelRiesgo) {
            case 'rojo':
                $claseRiesgo = 'riesgo-rojo';
                break;
            case 'amarillo':
                $claseRiesgo = 'riesgo-amarillo';
                break;
            case 'verde':
                $claseRiesgo = 'riesgo-verde';
                break;
            case 'blanco':
            default:
                $claseRiesgo = '';
                break;
        }
        $p['dias_laborables'] = $dias;
        $p['riesgo_nivel'] = $nivelRiesgo;
        $p['riesgo_motivos'] = is_array($riesgo['motivos'] ?? null) ? $riesgo['motivos'] : [];
        $p['clase_riesgo'] = $claseRiesgo;
        return $p;
    }, $pedidosPendientes);
    $totalPendienteImporte = 0;
    $totalPedidos = count($pedidosPendientes);
    $totalRiesgoAlto = 0;
    $totalRiesgoMedio = 0;

    foreach ($pedidosPendientes as $p) {
        $totalPendienteImporte += (float)($p['pendiente'] ?? 0);

        if (!empty($p['clase_riesgo']) && ($p['clase_riesgo'] === 'riesgo-alto' || $p['clase_riesgo'] === 'riesgo-rojo')) {
            $totalRiesgoAlto++;
        } elseif (!empty($p['clase_riesgo']) && ($p['clase_riesgo'] === 'riesgo-medio' || $p['clase_riesgo'] === 'riesgo-amarillo')) {
            $totalRiesgoMedio++;
        }
    }
    ?>

    <div class="mini-resumen-servicio">
        <div>
            <strong><?= $totalPedidos ?></strong> pedidos pendientes
        </div>

        <div>
             <strong><?= number_format($totalPendienteImporte, 2, ',', '.') ?> </strong> pendientes
        </div>

        <div>
             <?= $totalRiesgoAlto ?> en riesgo alto
        </div>

        <div>
             <?= $totalRiesgoMedio ?> en riesgo medio
        </div>
    </div>

    <style>
    .resumen-principal {
        display:flex;
        justify-content:space-between;
        gap:40px;
        padding-bottom:15px;
        border-bottom:1px solid #e5e7eb;
    }

    .resumen-principal .bloque {
        flex:1;
    }

    .resumen-principal .valor-grande {
        font-size:18px;
        font-weight:600;
    }

    .mini-resumen-servicio {
        display:flex;
        gap:35px;
        padding:14px 18px;
        margin-top:14px;
        background:#f8fafc;
        border-top:1px solid #e5e7eb;
        border-bottom:1px solid #e5e7eb;
        font-size:14px;
        align-items:center;
    }

    .mini-resumen-servicio strong {
        font-weight:600;
    }

    .tabla-detalle-servicio {
        width:100%;
        border-collapse:collapse;
        font-size:13px;
    }

    .tabla-detalle-servicio th {
        background:#f3f4f6;
        text-align:left;
        padding:8px;
        border-bottom:1px solid #e5e7eb;
    }

    .tabla-detalle-servicio td {
        padding:8px;
        border-bottom:1px solid #f1f1f1;
    }

    .tabla-detalle-servicio td:nth-child(7) {
        font-weight: 600;
    }

    .fila-roja {
        background:#fef2f2;
    }

    .tabla-detalle-servicio tbody tr:hover {
        background:#f9fafb;
    }

    .riesgo-alto {
        background-color:#fee2e2;
        border-left: 4px solid #dc2626;
    }

    .riesgo-medio {
        background-color:#fef3c7;
        border-left: 4px solid #d97706;
    }

    .riesgo-verde {
        background-color:#e6f7ec;
        color:#1e7e34;
        border-left:4px solid #28a745;
    }

    .riesgo-amarillo {
        background-color:#fff8e1;
        color:#8a6d3b;
        border-left:4px solid #ffc107;
    }

    .riesgo-rojo {
        background-color:#fdecea;
        color:#a71d2a;
        border-left:4px solid #dc3545;
    }

    .link-pedido,
    .link-cliente {
        color: #1f2937;
        text-decoration: underline;
        cursor: pointer;
    }

    .link-pedido:hover,
    .link-cliente:hover {
        color: #111827;
    }

    .drawer-pagination {
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        align-items:center;
        margin:12px 0;
    }

    .pager-chip {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:34px;
        height:32px;
        padding:0 10px;
        border:1px solid #d1d5db;
        border-radius:8px;
        background:#ffffff;
        color:#334155;
        text-decoration:none;
        font-size:13px;
        font-weight:600;
        transition:background-color .15s ease,border-color .15s ease,color .15s ease;
    }

    .pager-chip:hover {
        background:#f8fafc;
        border-color:#94a3b8;
        color:#0f172a;
    }

    .pager-chip.is-active {
        background:#1f3c88;
        border-color:#1f3c88;
        color:#ffffff;
    }

    .pager-chip.is-disabled {
        background:#f8fafc;
        border-color:#e5e7eb;
        color:#94a3b8;
        pointer-events:none;
    }

    .pager-nav {
        padding:0 12px;
    }

    .pager-ellipsis {
        color:#64748b;
        padding:0 2px;
        font-weight:700;
    }
    </style>

    <?php if (!empty($pedidosPendientes)): ?>

    <div class="tabla-wrapper">
        <h3 style="margin-top:20px;margin-bottom:10px;">
            Pedidos con importe pendiente
        </h3>
        <?= $renderPaginacion($page, $totalPaginas, $buildPageUrl) ?>

        <table class="tabla-detalle-servicio">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th style="text-align:right;">D&iacute;as</th>
                    <th>N&ordm; Pedido</th>
                    <th>Cliente</th>
                    <th style="text-align:right;">Importe pedido</th>
                    <th style="text-align:right;">Importe servido</th>
                    <th style="text-align:right;">Pendiente &euro;</th>
                    <th style="text-align:right;">% servido</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pedidosPendientes as $p): ?>
                <?php
                $fechaFin = date('Y-m-d', strtotime('-1 day'));
                $dias = (int)($p['dias_laborables'] ?? 0);
                if ($dias <= 0) {
                    $dias = calcularDiasLaborables((string)($p['fecha'] ?? ''), $fechaFin);
                }
                $claseRiesgo = (string)($p['clase_riesgo'] ?? '');
                if ($claseRiesgo === '') {
                    $riesgo = calcularRiesgoLineaServicio([
                        'pendiente' => (float)($p['pendiente'] ?? 0),
                        'porcentaje_servicio' => (float)($p['porcentaje_servicio'] ?? ($p['porcentaje_servido'] ?? 0)),
                        'dias' => $dias,
                        'historico' => (string)($p['historico'] ?? 'N'),
                    ]);
                    $nivelRiesgo = (string)($riesgo['nivel'] ?? 'verde');
                    switch ($nivelRiesgo) {
                        case 'rojo':
                            $claseRiesgo = 'riesgo-rojo';
                            break;
                        case 'amarillo':
                            $claseRiesgo = 'riesgo-amarillo';
                            break;
                        case 'verde':
                            $claseRiesgo = 'riesgo-verde';
                            break;
                        case 'blanco':
                        default:
                            $claseRiesgo = '';
                            break;
                    }
                }
                ?>
                <tr class="<?= $claseRiesgo ?>">
                    <td><?= htmlspecialchars((string)$p['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:right;font-weight:600;">
                        <?= (int)$dias ?>
                    </td>
                    <td>
                        <a href="#"
                           class="link-pedido"
                           data-pedido="<?= htmlspecialchars((string)($p['cod_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                           <?= htmlspecialchars((string)($p['cod_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </td>
                    <td>
                        <a href="#"
                           class="link-cliente"
                           data-cliente="<?= htmlspecialchars((string)($p['cod_cliente'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                           <?= htmlspecialchars((string)$p['cliente'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </td>
                    <td style="text-align:right;">
                        <?= number_format((float)$p['importe_pedido'], 2, ',', '.') ?> &euro;
                    </td>
                    <td style="text-align:right;">
                        <?= number_format((float)($p['importe_servido_real'] ?? 0), 2, ',', '.') ?> &euro;
                    </td>
                    <td style="text-align:right;font-weight:600;">
                        <?= number_format((float)$p['pendiente'], 2, ',', '.') ?> &euro;
                    </td>
                    <td style="text-align:right;">
                        <?= number_format((float)($p['porcentaje_servicio'] ?? 0), 2, ',', '.') ?> %
                    </td>
                    <td>
                        <?= ((string)$p['historico'] === 'S') ? 'Hist&oacute;rico' : 'Activo' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= $renderPaginacion($page, $totalPaginas, $buildPageUrl) ?>
    </div>

    <?php else: ?>
        <p style="margin-top:20px;color:#6b7280;">
            No hay pedidos pendientes en este rango.
        </p>
    <?php endif; ?>

</div>
<?php
if (isset($conn)) {
    odbc_close($conn);
}
?>
