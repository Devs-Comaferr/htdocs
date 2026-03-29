<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/statistics.php';
require_once BASE_PATH . '/includes/funciones_estadisticas.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Support/config_estados_linea_venta.php';

$conn = db();

$estadosLineaVenta = require BASE_PATH . '/app/Support/config_estados_linea_venta.php';

header('Content-Type: application/json; charset=UTF-8');

function obtenerIconoEstadoCI(string $estado): string
{
    switch ($estado) {
        case 'PP':
            return BASE_URL . '/assets/icons/ci_estados/pedir.png';
        case 'PU':
            return BASE_URL . '/assets/icons/ci_estados/pedir_urgente.png';
        case 'PC':
            return BASE_URL . '/assets/icons/ci_estados/pedir_central.png';
        case 'NP':
            return BASE_URL . '/assets/icons/ci_estados/no_pedir.png';
        case 'PR':
            return BASE_URL . '/assets/icons/ci_estados/pendiente_recibir.png';
        case 'PM':
            return BASE_URL . '/assets/icons/ci_estados/pendiente_manual.png';
        case 'R':
            return BASE_URL . '/assets/icons/ci_estados/recibido.png';
        case 'RP':
            return BASE_URL . '/assets/icons/ci_estados/recibido_parcial.png';
        case 'RM':
            return BASE_URL . '/assets/icons/ci_estados/recibido_manual.png';
        default:
            return '';
    }
}

function responderDocumento(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $conn = db();
    if (isset($conn)) {
        odbc_close($conn);
    }
    exit;
}

$tipoVenta = trim((string)($_GET['tipo_venta'] ?? ''));
$codEmpresa = trim((string)($_GET['cod_empresa'] ?? ''));
$codCaja = trim((string)($_GET['cod_caja'] ?? ''));
$codVenta = trim((string)($_GET['cod_venta'] ?? ''));

if (
    $tipoVenta === '' || $codEmpresa === '' || $codCaja === '' || $codVenta === ''
    || !ctype_digit($tipoVenta) || !ctype_digit($codEmpresa) || !ctype_digit($codCaja) || !ctype_digit($codVenta)
) {
    responderDocumento([
        'cabecera' => null,
        'lineas' => [],
        'error' => 'Parámetros incompletos o no válidos.'
    ], 400);
}

$sqlCabecera = "
    SELECT TOP 1
        hvc.tipo_venta,
        hvc.cod_venta,
        hvc.cod_cliente,
        hvc.fecha_venta AS fecha,
        COALESCE(
            NULLIF(LTRIM(RTRIM(ISNULL(c.nombre_comercial, ''))), ''),
            NULLIF(LTRIM(RTRIM(ISNULL(hvc.cod_cliente, ''))), ''),
            ''
        ) AS cliente,
        ISNULL(hvc.importe, 0) AS importe
    FROM hist_ventas_cabecera hvc
    LEFT JOIN clientes c
        ON c.cod_cliente = hvc.cod_cliente
    WHERE hvc.tipo_venta = ?
      AND hvc.cod_empresa = ?
      AND hvc.cod_caja = ?
      AND hvc.cod_venta = ?
";
$paramsCabecera = [$tipoVenta, $codEmpresa, $codCaja, $codVenta];
$rsCabecera = estadisticasOdbcExec($conn, $sqlCabecera, $paramsCabecera);

if (!$rsCabecera) {
    registrarErrorSqlEstadisticas('detalle_documento.cabecera', $conn, $sqlCabecera, $paramsCabecera);
    responderDocumento([
        'cabecera' => null,
        'lineas' => [],
        'error' => 'No se pudo cargar la cabecera del documento.'
    ], 500);
}

$cabecera = odbc_fetch_array_utf8($rsCabecera);
if (!$cabecera) {
    responderDocumento([
        'cabecera' => null,
        'lineas' => [],
        'error' => 'No se encontró el documento solicitado.'
    ], 404);
}

$fecha = trim((string)($cabecera['fecha'] ?? ''));
$fechaSalida = $fecha;
if ($fecha !== '') {
    try {
        $fechaSalida = (new DateTimeImmutable($fecha))->format('d-m-Y H:i');
    } catch (Throwable $e) {
        $fechaSalida = $fecha;
    }
}

$cabeceraSalida = [
    'tipo_venta' => (int)($cabecera['tipo_venta'] ?? 0),
    'cod_venta' => trim((string)($cabecera['cod_venta'] ?? '')),
    'fecha' => $fechaSalida,
    'cliente' => toUTF8(trim((string)($cabecera['cliente'] ?? ''))),
    'importe' => (float)($cabecera['importe'] ?? 0),
];

$codClienteDocumento = trim((string)($cabecera['cod_cliente'] ?? ''));

$lineas = [];
$sqlLineas = "
    SELECT
        hvl.linea,
        hvl.cod_articulo,
        hvl.estado_venta,
        COALESCE(
            NULLIF(LTRIM(RTRIM(ISNULL(hvl.descripcion, ''))), ''),
            NULLIF(LTRIM(RTRIM(ISNULL(ad.descripcion, ''))), ''),
            ''
        ) AS descripcion,
        ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1) AS cantidad,
        ISNULL(TRY_CAST(hvl.precio AS FLOAT), 0) AS precio,
        ISNULL(TRY_CAST(hvl.importe AS FLOAT), 0) AS importe,
        ISNULL(elv.cantidad_servida, 0) AS cantidad_servida,
        ISNULL(alb.albaranes, '') AS albaranes,
        ISNULL(alb.albaranes_detalle, '') AS albaranes_detalle,
        ISNULL(ped.cantidad_pedida, 0) AS cantidad_pedida_origen,
        ISNULL(ped.total_servido_pedido, 0) AS total_servido_pedido,
        ISNULL(ped.pedidos, '') AS pedidos,
        (
            ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1)
            - ISNULL(elv.cantidad_servida, 0)
        ) AS cantidad_pendiente,
        CASE
            WHEN (ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1)) = 0 THEN 0
            ELSE (
                ISNULL(elv.cantidad_servida, 0)
                / (ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1))
            ) * 100
        END AS porcentaje_servicio
    FROM hist_ventas_linea hvl
    LEFT JOIN integral.dbo.articulo_descripcion ad
        ON ad.cod_articulo = hvl.cod_articulo
       AND ad.cod_idioma = 'ES'
    LEFT JOIN (
        SELECT
            cod_empresa_origen,
            cod_caja_origen,
            cod_venta_origen,
            linea_origen,
            SUM(cantidad) AS cantidad_servida
        FROM entrega_lineas_venta
        GROUP BY
            cod_empresa_origen,
            cod_caja_origen,
            cod_venta_origen,
            linea_origen
    ) elv
        ON elv.cod_empresa_origen = hvl.cod_empresa
       AND elv.cod_caja_origen = hvl.cod_caja
       AND elv.cod_venta_origen = hvl.cod_venta
       AND elv.linea_origen = hvl.linea
    LEFT JOIN (
        SELECT
            base.cod_empresa_origen,
            base.cod_caja_origen,
            base.cod_venta_origen,
            base.linea_origen,
            STUFF((
                SELECT ', ' +
                    CAST(base2.cod_venta_destino AS VARCHAR) +
                    ' (' +
                    CONVERT(VARCHAR(10), base2.fecha_albaran, 103) +
                    ')'
                FROM (
                    SELECT
                        elv2.cod_empresa_origen,
                        elv2.cod_caja_origen,
                        elv2.cod_venta_origen,
                        elv2.linea_origen,
                        elv2.cod_venta_destino,
                        MAX(hvc2.fecha_venta) AS fecha_albaran,
                        SUM(ISNULL(TRY_CAST(elv2.cantidad AS FLOAT), 0)) AS cantidad_albaran
                    FROM entrega_lineas_venta elv2
                    INNER JOIN hist_ventas_cabecera hvc2
                        ON hvc2.cod_empresa = elv2.cod_empresa_destino
                       AND hvc2.cod_caja = elv2.cod_caja_destino
                       AND hvc2.cod_venta = elv2.cod_venta_destino
                    WHERE elv2.tipo_venta_destino = 2
                    GROUP BY
                        elv2.cod_empresa_origen,
                        elv2.cod_caja_origen,
                        elv2.cod_venta_origen,
                        elv2.linea_origen,
                        elv2.cod_venta_destino
                ) base2
                WHERE base2.cod_empresa_origen = base.cod_empresa_origen
                  AND base2.cod_caja_origen = base.cod_caja_origen
                  AND base2.cod_venta_origen = base.cod_venta_origen
                  AND base2.linea_origen = base.linea_origen
                FOR XML PATH(''), TYPE
            ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS albaranes,
            STUFF((
                SELECT ', ' +
                    CAST(base2.cod_venta_destino AS VARCHAR) +
                    ' (' +
                    CONVERT(VARCHAR(10), base2.fecha_albaran, 103) +
                    ')||' +
                    CAST(base2.cantidad_albaran AS VARCHAR(50))
                FROM (
                    SELECT
                        elv2.cod_empresa_origen,
                        elv2.cod_caja_origen,
                        elv2.cod_venta_origen,
                        elv2.linea_origen,
                        elv2.cod_venta_destino,
                        MAX(hvc2.fecha_venta) AS fecha_albaran,
                        SUM(ISNULL(TRY_CAST(elv2.cantidad AS FLOAT), 0)) AS cantidad_albaran
                    FROM entrega_lineas_venta elv2
                    INNER JOIN hist_ventas_cabecera hvc2
                        ON hvc2.cod_empresa = elv2.cod_empresa_destino
                       AND hvc2.cod_caja = elv2.cod_caja_destino
                       AND hvc2.cod_venta = elv2.cod_venta_destino
                    WHERE elv2.tipo_venta_destino = 2
                    GROUP BY
                        elv2.cod_empresa_origen,
                        elv2.cod_caja_origen,
                        elv2.cod_venta_origen,
                        elv2.linea_origen,
                        elv2.cod_venta_destino
                ) base2
                WHERE base2.cod_empresa_origen = base.cod_empresa_origen
                  AND base2.cod_caja_origen = base.cod_caja_origen
                  AND base2.cod_venta_origen = base.cod_venta_origen
                  AND base2.linea_origen = base.linea_origen
                FOR XML PATH(''), TYPE
            ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS albaranes_detalle
        FROM (
            SELECT
                elv.cod_empresa_origen,
                elv.cod_caja_origen,
                elv.cod_venta_origen,
                elv.linea_origen
            FROM entrega_lineas_venta elv
            WHERE elv.tipo_venta_destino = 2
            GROUP BY
                elv.cod_empresa_origen,
                elv.cod_caja_origen,
                elv.cod_venta_origen,
                elv.linea_origen
        ) base
        GROUP BY
            base.cod_empresa_origen,
            base.cod_caja_origen,
            base.cod_venta_origen,
            base.linea_origen
    ) alb
        ON alb.cod_empresa_origen = hvl.cod_empresa
       AND alb.cod_caja_origen = hvl.cod_caja
       AND alb.cod_venta_origen = hvl.cod_venta
       AND alb.linea_origen = hvl.linea
    LEFT JOIN (
        SELECT
            base.cod_empresa_destino,
            base.cod_caja_destino,
            base.cod_venta_destino,
            base.linea_destino,
            SUM(base.cantidad_pedida) AS cantidad_pedida,
            SUM(base.total_servido_pedido) AS total_servido_pedido,
            STUFF((
                SELECT ', ' +
                    CAST(elv2.cod_venta_origen AS VARCHAR) +
                    ' (' +
                    CONVERT(VARCHAR(10), hvco2.fecha_venta, 103) +
                    ')'
                FROM entrega_lineas_venta elv2
                INNER JOIN hist_ventas_cabecera hvco2
                    ON hvco2.cod_empresa = elv2.cod_empresa_origen
                   AND hvco2.cod_caja = elv2.cod_caja_origen
                   AND hvco2.cod_venta = elv2.cod_venta_origen
                   AND hvco2.tipo_venta = elv2.tipo_venta_origen
                WHERE elv2.cod_empresa_destino = base.cod_empresa_destino
                  AND elv2.cod_caja_destino = base.cod_caja_destino
                  AND elv2.cod_venta_destino = base.cod_venta_destino
                  AND elv2.linea_destino = base.linea_destino
                  AND elv2.tipo_venta_origen = 1
                FOR XML PATH(''), TYPE
            ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS pedidos
        FROM (
            SELECT DISTINCT
                elv.cod_empresa_destino,
                elv.cod_caja_destino,
                elv.cod_venta_destino,
                elv.linea_destino,
                elv.cod_empresa_origen,
                elv.cod_caja_origen,
                elv.cod_venta_origen,
                elv.linea_origen,
                ISNULL(TRY_CAST(hvlo.cantidad AS FLOAT), 0)
                    * ISNULL(TRY_CAST(hvlo.unidades_venta AS FLOAT), 1) AS cantidad_pedida,
                ISNULL(tsp.total_servido_pedido, 0) AS total_servido_pedido
            FROM entrega_lineas_venta elv
            INNER JOIN hist_ventas_linea hvlo
                ON hvlo.cod_empresa = elv.cod_empresa_origen
               AND hvlo.cod_caja = elv.cod_caja_origen
               AND hvlo.cod_venta = elv.cod_venta_origen
               AND hvlo.tipo_venta = elv.tipo_venta_origen
               AND hvlo.linea = elv.linea_origen
            LEFT JOIN (
                SELECT
                    cod_empresa_origen,
                    cod_caja_origen,
                    cod_venta_origen,
                    linea_origen,
                    SUM(cantidad) AS total_servido_pedido
                FROM entrega_lineas_venta
                GROUP BY
                    cod_empresa_origen,
                    cod_caja_origen,
                    cod_venta_origen,
                    linea_origen
            ) tsp
                ON tsp.cod_empresa_origen = elv.cod_empresa_origen
               AND tsp.cod_caja_origen = elv.cod_caja_origen
               AND tsp.cod_venta_origen = elv.cod_venta_origen
               AND tsp.linea_origen = elv.linea_origen
            WHERE elv.tipo_venta_origen = 1
        ) base
        GROUP BY
            base.cod_empresa_destino,
            base.cod_caja_destino,
            base.cod_venta_destino,
            base.linea_destino
    ) ped
        ON ped.cod_empresa_destino = hvl.cod_empresa
       AND ped.cod_caja_destino = hvl.cod_caja
       AND ped.cod_venta_destino = hvl.cod_venta
       AND ped.linea_destino = hvl.linea
    WHERE hvl.tipo_venta = ?
      AND hvl.cod_empresa = ?
      AND hvl.cod_caja = ?
      AND hvl.cod_venta = ?
    ORDER BY hvl.linea
";
$paramsLineas = [$tipoVenta, $codEmpresa, $codCaja, $codVenta];
$rsLineas = estadisticasOdbcExec($conn, $sqlLineas, $paramsLineas);

if (!$rsLineas) {
    registrarErrorSqlEstadisticas('detalle_documento.lineas', $conn, $sqlLineas, $paramsLineas);
    responderDocumento([
        'cabecera' => $cabeceraSalida,
        'lineas' => [],
        'error' => 'No se pudieron cargar las líneas del documento.'
    ], 500);
}

while ($row = odbc_fetch_array_utf8($rsLineas)) {
    $estadoLinea = trim((string)($row['estado_venta'] ?? ''));
    if ($estadoLinea === '') {
        $estadoLinea = 'NULL';
    }
    $estadoInfo = $estadosLineaVenta[$estadoLinea] ?? $estadosLineaVenta['NULL'];

    $importe = (float)($row['importe'] ?? 0);
    $cantidadDocumento = (float)($row['cantidad'] ?? 0);
    $cantidadPedidaOrigen = (float)($row['cantidad_pedida_origen'] ?? 0);
    $cantidadServida = (float)($row['cantidad_servida'] ?? 0);
    $totalServidoPedido = (float)($row['total_servido_pedido'] ?? 0);
    $cantidadPendiente = (float)($row['cantidad_pendiente'] ?? 0);
    $porcentajeServicio = (float)($row['porcentaje_servicio'] ?? 0);
    $pedidosRelacionados = toUTF8(trim((string)($row['pedidos'] ?? '')));
    $albaranesDetalle = toUTF8(trim((string)($row['albaranes_detalle'] ?? '')));
    $tienePedidoRelacionado = $pedidosRelacionados !== '';

    if ((int)$tipoVenta === 2) {
        if ($tienePedidoRelacionado) {
            $cantidadServida = $totalServidoPedido;
            $cantidadPendiente = null;
            $porcentajeServicio = $cantidadPedidaOrigen > 0
                ? (($cantidadServida / $cantidadPedidaOrigen) * 100)
                : null;
        } else {
            $cantidadPedidaOrigen = null;
            $cantidadServida = null;
            $cantidadPendiente = null;
            $porcentajeServicio = null;
        }
    }

    $importeServido = $porcentajeServicio !== null ? ($importe * ($porcentajeServicio / 100)) : null;
    $importePendiente = $importeServido !== null ? ($importe - $importeServido) : null;

    $albaranesTexto = toUTF8(trim((string)($row['albaranes'] ?? '')));
    if ($albaranesDetalle !== '') {
        $partesAlbaran = array_values(array_filter(array_map('trim', explode(', ', $albaranesDetalle)), static function (string $valor): bool {
            return $valor !== '';
        }));
        $mostrarCantidadAlbaran = count($partesAlbaran) > 1 || abs($cantidadServida - $cantidadDocumento) > 0.00001;
        $albaranesFormateados = [];

        foreach ($partesAlbaran as $parteAlbaran) {
            $detalleAlbaran = explode('||', $parteAlbaran, 2);
            $textoAlbaran = trim((string)($detalleAlbaran[0] ?? ''));
            $cantidadAlbaran = isset($detalleAlbaran[1]) ? (float)$detalleAlbaran[1] : null;

            if ($textoAlbaran === '') {
                continue;
            }

            if ($mostrarCantidadAlbaran && $cantidadAlbaran !== null) {
                $cantidadAlbaranTexto = rtrim(rtrim(number_format($cantidadAlbaran, 2, ',', '.'), '0'), ',');
                $textoAlbaran .= ' â†’ ' . $cantidadAlbaranTexto;
            }

            $albaranesFormateados[] = $textoAlbaran;
        }

        if (!empty($albaranesFormateados)) {
            $albaranesTexto = implode(', ', $albaranesFormateados);
        }
    }

    $lineas[] = [
        'linea' => (int)($row['linea'] ?? 0),
        'cod_articulo' => toUTF8(trim((string)($row['cod_articulo'] ?? ''))),
        'descripcion' => toUTF8(trim((string)($row['descripcion'] ?? ''))),
        'estado_linea' => $estadoLinea,
        'estado_linea_texto' => (string)($estadoInfo['texto'] ?? ''),
        'estado_linea_icono' => obtenerIconoEstadoCI($estadoLinea),
        'cantidad' => $cantidadDocumento,
        'cantidad_pedida' => $cantidadPedidaOrigen,
        'precio' => (float)($row['precio'] ?? 0),
        'importe' => $importe,
        'albaranes' => $albaranesTexto,
        'pedidos' => $pedidosRelacionados,
        'tiene_pedido_relacionado' => $tienePedidoRelacionado,
        'cantidad_servida' => $cantidadServida,
        'cantidad_pendiente' => $cantidadPendiente,
        'porcentaje_servicio' => $porcentajeServicio,
        'importe_servido' => $importeServido,
        'importe_pendiente' => $importePendiente,
        'estado_servicio' => '',
    ];
}

$totalImportePedido = 0.0;
$totalImporteServido = 0.0;
$importePendienteTotal = 0.0;
foreach ($lineas as $lineaTotal) {
    $importe = (float)($lineaTotal['importe'] ?? 0);
    $importeServido = $lineaTotal['importe_servido'];
    $importePendiente = $lineaTotal['importe_pendiente'];

    $totalImportePedido += $importe;
    if ($importeServido !== null) {
        $totalImporteServido += (float)$importeServido;
    }
    if ($importePendiente !== null) {
        $importePendienteTotal += (float)$importePendiente;
    }
}

$porcentajeServicioTotal = 0.0;
if ($totalImportePedido > 0) {
    $porcentajeServicioTotal = ($totalImporteServido / $totalImportePedido) * 100;
}

$cabeceraSalida['porcentaje_servicio_total'] = round($porcentajeServicioTotal, 1);
$cabeceraSalida['importe_servido_total'] = round($totalImporteServido, 2);
$cabeceraSalida['importe_pendiente_total'] = round($importePendienteTotal, 2);

if ((int)$tipoVenta === 1) {
    $pendientes = [];
    $parciales = [];
    $servidas = [];

    foreach ($lineas as $lineaAgrupada) {
        $porcentaje = (float)($lineaAgrupada['porcentaje_servicio'] ?? 0);

        if ($porcentaje === 0.0) {
            $lineaAgrupada['grupo_servicio'] = 'pendientes';
            $pendientes[] = $lineaAgrupada;
        } elseif ($porcentaje === 100.0) {
            $lineaAgrupada['grupo_servicio'] = 'servidas';
            $servidas[] = $lineaAgrupada;
        } else {
            $lineaAgrupada['grupo_servicio'] = 'parciales';
            $parciales[] = $lineaAgrupada;
        }
    }

    usort($pendientes, static function (array $a, array $b): int {
        return (float)($b['importe_pendiente'] ?? 0) <=> (float)($a['importe_pendiente'] ?? 0);
    });

    usort($parciales, static function (array $a, array $b): int {
        return (float)($b['importe_pendiente'] ?? 0) <=> (float)($a['importe_pendiente'] ?? 0);
    });

    usort($servidas, static function (array $a, array $b): int {
        return (float)($b['importe_servido'] ?? 0) <=> (float)($a['importe_servido'] ?? 0);
    });

    $cabeceraSalida['total_lineas_pendientes'] = count($pendientes);
    $cabeceraSalida['total_importe_pendiente'] = round(array_sum(array_map(static function (array $linea): float {
        return (float)($linea['importe_pendiente'] ?? 0);
    }, $pendientes)), 2);
    $cabeceraSalida['total_lineas_parciales'] = count($parciales);
    $cabeceraSalida['total_importe_pendiente_parcial'] = round(array_sum(array_map(static function (array $linea): float {
        return (float)($linea['importe_pendiente'] ?? 0);
    }, $parciales)), 2);
    $cabeceraSalida['total_lineas_servidas'] = count($servidas);
    $cabeceraSalida['total_importe_servido_grupo'] = round(array_sum(array_map(static function (array $linea): float {
        return (float)($linea['importe_servido'] ?? 0);
    }, $servidas)), 2);

    $lineas = array_merge($pendientes, $parciales, $servidas);
}

if ((int)$tipoVenta === 1 && $codClienteDocumento !== '' && !empty($lineas)) {
    $articulosPendientesOp = [];
    foreach ($lineas as $linea) {
        if (($linea['cantidad_servida'] ?? 0) > 0) {
            continue;
        }

        $codArticulo = trim((string)($linea['cod_articulo'] ?? ''));
        if ($codArticulo === '') {
            continue;
        }

        $articulosPendientesOp[$codArticulo] = true;
    }

    $cantidadesOperativasPorArticulo = [];
    if (!empty($articulosPendientesOp)) {
        $codigosArticulo = array_keys($articulosPendientesOp);
        $placeholders = implode(',', array_fill(0, count($codigosArticulo), '?'));
        $sqlOperativo = "
            SELECT
                hvl2.cod_articulo,
                SUM(ISNULL(TRY_CAST(hvl2.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl2.unidades_venta AS FLOAT), 1)) AS cantidad_operativa
            FROM hist_ventas_linea hvl2
            INNER JOIN hist_ventas_cabecera hvc2
                ON hvc2.cod_venta = hvl2.cod_venta
               AND hvc2.tipo_venta = hvl2.tipo_venta
               AND hvc2.cod_empresa = hvl2.cod_empresa
               AND hvc2.cod_caja = hvl2.cod_caja
            WHERE hvc2.tipo_venta = 2
              AND hvc2.cod_cliente = ?
              AND hvc2.fecha_venta >= ?
              AND hvl2.cod_articulo IN ($placeholders)
            GROUP BY hvl2.cod_articulo
        ";
        $paramsOperativo = array_merge([$codClienteDocumento, $fecha], $codigosArticulo);
        $rsOperativo = estadisticasOdbcExec($conn, $sqlOperativo, $paramsOperativo);

        if ($rsOperativo) {
            while ($rowOperativo = odbc_fetch_array_utf8($rsOperativo)) {
                $codArticuloOperativo = trim((string)($rowOperativo['cod_articulo'] ?? ''));
                if ($codArticuloOperativo === '') {
                    continue;
                }

                $cantidadesOperativasPorArticulo[$codArticuloOperativo] = (float)($rowOperativo['cantidad_operativa'] ?? 0);
            }
        }
    }

    foreach ($lineas as &$linea) {
        if (($linea['cantidad_servida'] ?? 0) > 0) {
            $linea['estado_servicio'] = 'OF';
            continue;
        }

        $codArticulo = trim((string)($linea['cod_articulo'] ?? ''));
        $cantidadOperativa = (float)($cantidadesOperativasPorArticulo[$codArticulo] ?? 0);
        $linea['estado_servicio'] = $cantidadOperativa > 0 ? 'OP' : '';
    }
    unset($linea);
} else {
    foreach ($lineas as &$linea) {
        if (($linea['cantidad_servida'] ?? 0) > 0) {
            $linea['estado_servicio'] = 'OF';
        }
    }
    unset($linea);
}

responderDocumento([
    'cabecera' => $cabeceraSalida,
    'lineas' => $lineas
]);
