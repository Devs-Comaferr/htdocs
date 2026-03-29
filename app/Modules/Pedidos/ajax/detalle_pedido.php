<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_estadisticas');
require_once BASE_PATH . '/app/Support/statistics.php';
require_once BASE_PATH . '/app/Support/statistics.php';
require_once BASE_PATH . '/app/Support/db.php';

$conn = db();

$codVenta = trim((string)($_GET['cod_venta'] ?? ($_GET['numero'] ?? '')));
$tipoVenta = 1;

if ($codVenta === '') {
    echo '<p style="margin:0;color:#6b7280;">Pedido no especificado.</p>';
    if (isset($conn)) {
        odbc_close($conn);
    }
    return;
}

$sqlCabecera = "
    SELECT TOP 1
        hvc.cod_venta,
        hvc.tipo_venta,
        hvc.cod_empresa,
        hvc.cod_caja,
        hvc.cod_cliente,
        c.nombre_comercial AS nombre_cliente,
        hvc.fecha_venta,
        ISNULL(hvc.importe, 0) AS importe_total,
        ISNULL(hvc.historico, 'N') AS historico
    FROM hist_ventas_cabecera hvc
    LEFT JOIN integral.dbo.clientes c
        ON c.cod_cliente = hvc.cod_cliente
    WHERE hvc.cod_venta = ?
      AND hvc.tipo_venta = ?
    ORDER BY hvc.fecha_venta DESC
";
$paramsCabecera = [$codVenta, $tipoVenta];
$rsCabecera = estadisticasOdbcExec($conn, $sqlCabecera, $paramsCabecera);

if (!$rsCabecera) {
    registrarErrorSqlEstadisticas('detalle_pedido.cabecera', $conn, $sqlCabecera, $paramsCabecera);
    echo '<p style="margin:0;color:#b91c1c;">No se pudo cargar la cabecera del pedido.</p>';
    if (isset($conn)) {
        odbc_close($conn);
    }
    return;
}

$cabecera = odbc_fetch_array_utf8($rsCabecera);
if (!$cabecera) {
    echo '<p style="margin:0;color:#6b7280;">No se encontraron datos para el pedido solicitado.</p>';
    if (isset($conn)) {
        odbc_close($conn);
    }
    return;
}

$codEmpresa = trim((string)($cabecera['cod_empresa'] ?? ''));
$codCaja = trim((string)($cabecera['cod_caja'] ?? ''));
$codVentaOut = trim((string)($cabecera['cod_venta'] ?? $codVenta));
$tipoVentaOut = 1;
$codCliente = trim((string)($cabecera['cod_cliente'] ?? ''));
$nombreCliente = trim((string)($cabecera['nombre_cliente'] ?? ''));
$clienteTexto = $nombreCliente !== ''
    ? toUTF8($nombreCliente) . ' (' . toUTF8($codCliente) . ')'
    : toUTF8($codCliente);
$fechaVenta = trim((string)($cabecera['fecha_venta'] ?? ''));
$fechaMostrar = $fechaVenta;
if ($fechaVenta !== '') {
    try {
        $dtFecha = new DateTimeImmutable($fechaVenta);
        $horaMin = $dtFecha->format('H:i');
        $fechaMostrar = ($horaMin === '00:00')
            ? $dtFecha->format('d-m-Y')
            : $dtFecha->format('d-m-Y H:i');
    } catch (Throwable $e) {
        $fechaMostrar = $fechaVenta;
    }
}
$importeTotal = (float)($cabecera['importe_total'] ?? 0);
$historico = strtoupper(trim((string)($cabecera['historico'] ?? 'N')));

$lineas = [];
$sqlLineas = "
    SELECT
        hvl.cod_articulo,
        hvl.linea,
        COALESCE(
            NULLIF(LTRIM(RTRIM(ISNULL(hvl.descripcion, ''))), ''),
            NULLIF(LTRIM(RTRIM(ISNULL(ad.descripcion, ''))), ''),
            ''
        ) AS nombre_articulo,
        ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1) AS cantidad_pedida,
        ISNULL(so.cantidad_servida_oficial, 0) AS cantidad_servida_oficial,
        ISNULL(hvl.importe, 0) AS importe_linea
    FROM hist_ventas_linea hvl
    LEFT JOIN integral.dbo.articulo_descripcion ad
        ON ad.cod_articulo = hvl.cod_articulo
       AND ad.cod_idioma = 'ES'
    LEFT JOIN (
        SELECT
            elv.cod_venta_origen,
            elv.tipo_venta_origen,
            elv.cod_empresa_origen,
            elv.cod_caja_origen,
            elv.linea_origen,
            SUM(ISNULL(TRY_CAST(elv.cantidad AS FLOAT), 0)) AS cantidad_servida_oficial
        FROM entrega_lineas_venta elv
        WHERE elv.tipo_venta_destino = 2
        GROUP BY
            elv.cod_venta_origen,
            elv.tipo_venta_origen,
            elv.cod_empresa_origen,
            elv.cod_caja_origen,
            elv.linea_origen
    ) so
        ON so.cod_venta_origen = hvl.cod_venta
       AND so.tipo_venta_origen = hvl.tipo_venta
       AND so.cod_empresa_origen = hvl.cod_empresa
       AND so.cod_caja_origen = hvl.cod_caja
       AND so.linea_origen = hvl.linea
    WHERE hvl.cod_venta = ?
      AND hvl.tipo_venta = ?
      AND hvl.cod_empresa = ?
      AND hvl.cod_caja = ?
    ORDER BY hvl.linea
";
$paramsLineas = [$codVentaOut, $tipoVentaOut, $codEmpresa, $codCaja];
$rsLineas = estadisticasOdbcExec($conn, $sqlLineas, $paramsLineas);

if (!$rsLineas) {
    registrarErrorSqlEstadisticas('detalle_pedido.lineas', $conn, $sqlLineas, $paramsLineas);
} else {
    while ($rowLinea = odbc_fetch_array_utf8($rsLineas)) {
        $cantidadPedida = (float)($rowLinea['cantidad_pedida'] ?? 0);
        $cantidadServidaOficial = (float)($rowLinea['cantidad_servida_oficial'] ?? 0);
        $cantidadServidaOperativa = max(0.0, (float)($rowLinea['cantidad_servida_operativa'] ?? 0));
        $cantidadServidaTotal = min(
            $cantidadPedida,
            max(0.0, $cantidadServidaOficial + $cantidadServidaOperativa)
        );
        $pendienteLinea = max(0.0, $cantidadPedida - $cantidadServidaTotal);
        $porcentajeServidoLinea = ($cantidadPedida > 0)
            ? (($cantidadServidaTotal / $cantidadPedida) * 100)
            : 0.0;
        $tieneServicioOperativo = (
            $cantidadServidaOperativa > 0
            && $cantidadServidaOficial == 0.0
        );

        $claseLinea = 'linea-pendiente';
        if ($porcentajeServidoLinea >= 100.0) {
            $claseLinea = 'linea-ok';
        } elseif ($porcentajeServidoLinea > 0.0) {
            $claseLinea = 'linea-parcial';
        }

        $codArticulo = toUTF8(trim((string)($rowLinea['cod_articulo'] ?? '')));
        $nombreArticulo = toUTF8(trim((string)($rowLinea['nombre_articulo'] ?? '')));
        $articuloDisplay = $codArticulo;
        if ($nombreArticulo !== '') {
            $articuloDisplay .= ' - ' . $nombreArticulo;
        }

        $lineas[] = [
            'cod_articulo' => $codArticulo,
            'nombre_articulo' => $nombreArticulo,
            'cantidad_pedida' => $cantidadPedida,
            'cantidad_servida_oficial' => $cantidadServidaOficial,
            'cantidad_servida_operativa' => $cantidadServidaOperativa,
            'cantidad_servida_total' => $cantidadServidaTotal,
            'porcentaje_servido_linea' => $porcentajeServidoLinea,
            'pendiente_linea' => $pendienteLinea,
            'importe_linea' => (float)($rowLinea['importe_linea'] ?? 0),
            'tiene_servicio_operativo' => $tieneServicioOperativo,
            'clase_linea' => $claseLinea,
        ];
    }
}

$albaranesAplicados = [];
$sqlAlbaranesAplicados = "
    SELECT
        elv.cod_venta_destino AS cod_albaran,
        elv.tipo_venta_destino AS tipo_albaran,
        elv.cod_empresa_destino AS cod_empresa_albaran,
        elv.cod_caja_destino AS cod_caja_albaran,
        MAX(hvcd.fecha_venta) AS fecha_albaran,
        MAX(ISNULL(hvcd.importe, 0)) AS importe_total_albaran,
        SUM(
            CASE
                WHEN ISNULL(ped.cantidad_pedida, 0) > 0
                    THEN (ISNULL(TRY_CAST(elv.cantidad AS FLOAT), 0) / ped.cantidad_pedida) * ISNULL(ped.importe_linea, 0)
                ELSE 0
            END
        ) AS importe_aplicado_pedido
    FROM entrega_lineas_venta elv
    LEFT JOIN hist_ventas_cabecera hvcd
        ON hvcd.cod_venta = elv.cod_venta_destino
       AND hvcd.tipo_venta = elv.tipo_venta_destino
       AND hvcd.cod_empresa = elv.cod_empresa_destino
       AND hvcd.cod_caja = elv.cod_caja_destino
    LEFT JOIN (
        SELECT
            hvl.cod_venta,
            hvl.tipo_venta,
            hvl.cod_empresa,
            hvl.cod_caja,
            hvl.linea,
            ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1) AS cantidad_pedida,
            ISNULL(hvl.importe, 0) AS importe_linea
        FROM hist_ventas_linea hvl
    ) ped
        ON ped.cod_venta = elv.cod_venta_origen
       AND ped.tipo_venta = elv.tipo_venta_origen
       AND ped.cod_empresa = elv.cod_empresa_origen
       AND ped.cod_caja = elv.cod_caja_origen
       AND ped.linea = elv.linea_origen
    WHERE elv.cod_venta_origen = ?
      AND elv.tipo_venta_origen = 1
      AND elv.cod_empresa_origen = ?
      AND elv.cod_caja_origen = ?
      AND elv.tipo_venta_destino = 2
    GROUP BY
        elv.cod_venta_destino,
        elv.tipo_venta_destino,
        elv.cod_empresa_destino,
        elv.cod_caja_destino
    ORDER BY
        MAX(hvcd.fecha_venta) DESC,
        elv.cod_venta_destino DESC
";
$paramsAlbaranesAplicados = [$codVentaOut, $codEmpresa, $codCaja];
$rsAlbaranesAplicados = estadisticasOdbcExec($conn, $sqlAlbaranesAplicados, $paramsAlbaranesAplicados);

if (!$rsAlbaranesAplicados) {
    registrarErrorSqlEstadisticas('detalle_pedido.albaranes_aplicados', $conn, $sqlAlbaranesAplicados, $paramsAlbaranesAplicados);
} else {
    while ($rowAlb = odbc_fetch_array_utf8($rsAlbaranesAplicados)) {
        $fechaAlbRaw = trim((string)($rowAlb['fecha_albaran'] ?? ''));
        $fechaAlb = $fechaAlbRaw;
        if ($fechaAlbRaw !== '') {
            try {
                $dtAlb = new DateTimeImmutable($fechaAlbRaw);
                $horaAlb = $dtAlb->format('H:i');
                $fechaAlb = ($horaAlb === '00:00')
                    ? $dtAlb->format('d-m-Y')
                    : $dtAlb->format('d-m-Y H:i');
            } catch (Throwable $e) {
                $fechaAlb = $fechaAlbRaw;
            }
        }

        $albaranesAplicados[] = [
            'cod_albaran' => trim((string)($rowAlb['cod_albaran'] ?? '')),
            'tipo_albaran' => (int)($rowAlb['tipo_albaran'] ?? 2),
            'fecha_albaran' => $fechaAlb,
            'importe_total_albaran' => (float)($rowAlb['importe_total_albaran'] ?? 0),
            'importe_aplicado_pedido' => (float)($rowAlb['importe_aplicado_pedido'] ?? 0),
        ];
    }
}
?>
<div style="font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;color:#334155;">
    <style>
    .dp-badge {
        display:inline-block;
        padding:2px 8px;
        border-radius:999px;
        font-size:12px;
        font-weight:600;
        line-height:1.4;
    }

    .badge-historico {
        background:#e5e7eb;
        color:#374151;
    }

    .badge-activo {
        background:#dcfce7;
        color:#166534;
    }

    .dp-tabla {
        width:100%;
        border-collapse:collapse;
        font-size:13px;
    }

    .dp-tabla th {
        text-align:left;
        padding:6px 8px;
        background:#f3f4f6;
        border-bottom:1px solid #e5e7eb;
    }

    .dp-tabla td {
        padding:6px 8px;
        border-bottom:1px solid #f1f1f1;
    }

    .dp-tabla tbody tr:nth-child(even) {
        background:#fcfcfd;
    }

    .dp-tabla tbody tr:hover {
        background:#f9fafb;
    }

    .dp-tabla tr.linea-ok {
        background:#ecfdf5;
    }

    .dp-tabla tr.linea-parcial {
        background:#fffbeb;
    }

    .dp-tabla tr.linea-pendiente {
        background:#fef2f2;
    }

    .link-albaran {
        color:#1f2937;
        text-decoration:underline;
        cursor:pointer;
    }

    .link-albaran:hover {
        color:#111827;
    }

    .badge-op {
        font-size:11px;
        background:#e0f2fe;
        color:#0369a1;
        padding:2px 6px;
        border-radius:10px;
        margin-left:6px;
    }
    </style>

    <h3 style="margin:0 0 12px;font-size:20px;color:#0f172a;">
        Pedido <?= htmlspecialchars(toUTF8($codVentaOut), ENT_QUOTES, 'UTF-8') ?>
    </h3>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px;margin-bottom:12px;">
        <div><strong>Cliente:</strong> <?= htmlspecialchars($clienteTexto, ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Fecha:</strong> <?= htmlspecialchars((string)$fechaMostrar, ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Importe total:</strong> <span style="font-size:18px;font-weight:600;"><?= number_format($importeTotal, 2, ',', '.') ?> &euro;</span></div>
        <div><strong>Hist&oacute;rico:</strong>
            <?php if ($historico === 'S'): ?>
                <span class="dp-badge badge-historico">Hist&oacute;rico</span>
            <?php else: ?>
                <span class="dp-badge badge-activo">Activo</span>
            <?php endif; ?>
        </div>
    </div>

    <table class="dp-tabla">
        <?php
        usort($lineas, static function (array $a, array $b): int {
            $pendienteA = (float)($a['pendiente'] ?? $a['pendiente_linea'] ?? 0);
            $pendienteB = (float)($b['pendiente'] ?? $b['pendiente_linea'] ?? 0);

            if ($pendienteA !== $pendienteB) {
                return $pendienteB <=> $pendienteA;
            }

            $importeA = (float)($a['importe_linea'] ?? 0);
            $importeB = (float)($b['importe_linea'] ?? 0);

            if ($importeA !== $importeB) {
                return $importeB <=> $importeA;
            }

            return (float)($b['cantidad_pedida'] ?? 0)
                <=> (float)($a['cantidad_pedida'] ?? 0);
        });

        $tieneAlgunaLineaOP = false;
        foreach ($lineas as $lineaTmp) {
            if ((float)($lineaTmp['cantidad_servida_operativa'] ?? 0) > 0) {
                $tieneAlgunaLineaOP = true;
                break;
            }
        }
        ?>
        <thead>
            <tr>
                <th>C&oacute;digo</th>
                <th>Nombre art&iacute;culo</th>
                <th style="text-align:right;">Cantidad pedida</th>
                <th style="text-align:right;">Servido oficial</th>
                <?php if ($tieneAlgunaLineaOP): ?>
                    <th style="text-align:right;">Servido OP</th>
                <?php endif; ?>
                <th style="text-align:right;">Cantidad servida</th>
                <th style="text-align:right;">% servido</th>
                <th style="text-align:right;">Pendiente</th>
                <th style="text-align:right;">Importe l&iacute;nea</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($lineas)): ?>
                <?php foreach ($lineas as $linea): ?>
                    <tr class="<?= htmlspecialchars((string)($linea['clase_linea'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <td><?= htmlspecialchars((string)($linea['cod_articulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($linea['nombre_articulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="text-align:right;"><?= number_format((float)$linea['cantidad_pedida'], 2, ',', '.') ?></td>
                        <td style="text-align:right;"><?= number_format((float)$linea['cantidad_servida_oficial'], 2, ',', '.') ?></td>
                        <?php if ($tieneAlgunaLineaOP): ?>
                            <td style="text-align:right;"><?= number_format((float)$linea['cantidad_servida_operativa'], 2, ',', '.') ?></td>
                        <?php endif; ?>
                        <td style="text-align:right;"><?= number_format((float)$linea['cantidad_servida_total'], 2, ',', '.') ?></td>
                        <td style="text-align:right;">
                            <?= number_format((float)$linea['porcentaje_servido_linea'], 2, ',', '.') ?> %
                            <?php if (!empty($linea['tiene_servicio_operativo'])): ?>
                                <span class="badge-op">OP</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;"><?= number_format((float)$linea['pendiente_linea'], 2, ',', '.') ?></td>
                        <td style="text-align:right;"><?= number_format((float)$linea['importe_linea'], 2, ',', '.') ?> &euro;</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $tieneAlgunaLineaOP ? '9' : '8' ?>" style="padding:6px 8px;color:#6b7280;">No hay l&iacute;neas para este pedido.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h4 style="margin:14px 0 8px;font-size:15px;color:#0f172a;">Albaranes que ejecutan este pedido</h4>
    <table class="dp-tabla">
        <thead>
            <tr>
                <th>N&ordm; Albar&aacute;n</th>
                <th>Fecha</th>
                <th style="text-align:right;">Importe total albar&aacute;n</th>
                <th style="text-align:right;">Importe aplicado a este pedido</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($albaranesAplicados)): ?>
                <?php foreach ($albaranesAplicados as $alb): ?>
                    <tr>
                        <td>
                            <span
                                class="link-albaran"
                            >
                                <?= htmlspecialchars((string)($alb['cod_albaran'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars((string)($alb['fecha_albaran'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="text-align:right;"><?= number_format((float)($alb['importe_total_albaran'] ?? 0), 2, ',', '.') ?> &euro;</td>
                        <td style="text-align:right;"><?= number_format((float)($alb['importe_aplicado_pedido'] ?? 0), 2, ',', '.') ?> &euro;</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="padding:6px 8px;color:#6b7280;">No hay albaranes relacionados para este pedido.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
if (isset($conn)) {
    odbc_close($conn);
}
?>
