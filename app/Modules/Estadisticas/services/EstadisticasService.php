<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasHelper.php';
require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasClientesService.php';
require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasVentasService.php';

if (!function_exists('__estadisticas_impl_obtenerKpiServicioPedidos')) {
    function __estadisticas_impl_obtenerKpiServicioPedidos($conn, array $contexto): array
    {
        $resultado = [
            'total_pedido' => 0.0,
            'total_servido' => 0.0,
            'porcentaje' => 0.0,
            'servicio_real' => 0.0,
            'porcentaje_servicio' => 0.0,
        ];
        if (!$conn) {
            return $resultado;
        }

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = ((string)($contexto['tipo_filtro_comercial'] ?? 'todos') === 'cod_comisionista')
            ? trim((string)($contexto['valor_filtro_comercial'] ?? ''))
            : '';
        [$whereCabecera, $params] = buildWhereCabecera('hvc', [
            'tipo_venta' => 1,
            'excluir_anuladas' => true,
            'excluir_comisionista_cero' => true,
            'fecha_desde' => $fDesde,
            'fecha_hasta' => $fHasta,
            'cod_comisionista' => $codComisionista,
        ]);
        $whereArticulos = [];
        $marca = trim((string)($contexto['marca'] ?? ''));

        if ($marca !== '') {
            $whereArticulos[] = "a.marca = ?";
            $params[] = $marca;
        }

        $whereArticulosSql = '';
        if (!empty($whereArticulos)) {
            $whereArticulosSql = " AND " . implode(" AND ", $whereArticulos);
        }

        $sql = str_replace(
            ['__WHERE_CABECERA__', '__WHERE_ARTICULOS__'],
            [
                ($whereCabecera !== '' ? " AND " . $whereCabecera : ''),
                $whereArticulosSql
            ],
            construirSqlBaseServicioPedidosCTE()
        ) . "
            SELECT
                ISNULL(ag.total_pedido, 0) AS total_pedido,
                ISNULL(ag.total_servido, 0) AS total_servido,
                ISNULL(ag.total_servido_bruto, 0) AS total_servido_bruto,
                CASE
                    WHEN ISNULL(ag.total_pedido, 0) > 0
                        THEN ISNULL(ag.total_servido, 0) / ag.total_pedido
                    ELSE 0
                END AS porcentaje
            FROM agregados ag
        ";

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerKpiServicioPedidos.exec', $conn, $sql, $params);
            return $resultado;
        }

        $row = odbc_fetch_array_utf8($rs);
        if (!$row) {
            return $resultado;
        }

        $totalPedido = (float)($row['total_pedido'] ?? 0);
        $totalServido = (float)($row['total_servido'] ?? 0);
        $totalServidoBruto = (float)($row['total_servido_bruto'] ?? 0);
        $porcentaje = (float)($row['porcentaje'] ?? 0);
        $excesoServicio = max(0, $totalServidoBruto - $totalPedido);

        return [
            'total_pedido' => $totalPedido,
            'total_servido' => $totalServido,
            'total_servido_bruto' => $totalServidoBruto,
            'porcentaje' => $porcentaje,
            'servicio_real' => $totalServido,
            'porcentaje_servicio' => $porcentaje,
            'exceso_servicio' => $excesoServicio,
        ];
    }
}


if (!function_exists('__estadisticas_impl_obtenerKpiServicioPedidosAjustado')) {
    function __estadisticas_impl_obtenerKpiServicioPedidosAjustado($conn, array $contexto): array
    {
        $debugActivo = estadisticasDebugActivo();
        $resultado = [
            'total_pedido' => 0.0,
            'total_servido' => 0.0,
            'total_servido_documental' => 0.0,
            'total_servido_bruto' => 0.0,
            'porcentaje' => 0.0,
            'servicio_real' => 0.0,
            'porcentaje_servicio' => 0.0,
            'exceso_servicio' => 0.0,
            'total_huerfanos_importe' => 0.0,
            'total_huerfanos_asignados_importe' => 0.0,
            'total_huerfanos_no_asignables_importe' => 0.0,
            'porcentaje_huerfanos_asignables' => 0.0,
            'servicio_operativo_total' => 0.0,
            'porcentaje_servicio_operativo' => 0.0,
            'detalle_pedidos_servicio' => [],
        ];
        if ($debugActivo) {
            $resultado['debug_lineas_pedido_count'] = 0;
            $resultado['debug_albaranes_sin_relacion_count'] = 0;
            $resultado['debug_albaranes_sin_relacion_sample'] = [];
            $resultado['debug_huerfanos_total_count'] = 0;
            $resultado['debug_huerfanos_asignados_count'] = 0;
            $resultado['debug_huerfanos_no_asignables_count'] = 0;
            $resultado['debug_huerfanos_asignados_detail_count'] = 0;
            $resultado['debug_huerfanos_asignados_sample'] = [];
            $resultado['es_experimental'] = true;
        }
        if (!$conn) {
            return $resultado;
        }

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = ((string)($contexto['tipo_filtro_comercial'] ?? 'todos') === 'cod_comisionista')
            ? trim((string)($contexto['valor_filtro_comercial'] ?? ''))
            : '';
        [$whereCabecera, $params] = buildWhereCabecera('hvc', [
            'tipo_venta' => 1,
            'excluir_anuladas' => true,
            'excluir_comisionista_cero' => true,
            'fecha_desde' => $fDesde,
            'fecha_hasta' => $fHasta,
            'cod_comisionista' => $codComisionista,
        ]);
        $marca = trim((string)($contexto['marca'] ?? ''));
        $whereArticulos = [];
        if ($marca !== '') {
            $whereArticulos[] = "LTRIM(RTRIM(a.marca)) = ?";
            $params[] = $marca;
        }
        $whereArticulosSql = '';
        if (!empty($whereArticulos)) {
            $whereArticulosSql = " AND " . implode(" AND ", $whereArticulos);
        }
        $sql = str_replace(
            ['__WHERE_CABECERA__', '__WHERE_ARTICULOS__'],
            [
                ($whereCabecera !== '' ? " AND " . $whereCabecera : ''),
                $whereArticulosSql,
            ],
            construirSqlBaseServicioPedidosCTE()
        ) . "
            SELECT
                ISNULL(ag.total_pedido, 0) AS total_pedido,
                ISNULL(ag.total_servido, 0) AS total_servido,
                ISNULL(ag.total_servido_bruto, 0) AS total_servido_bruto,
                CASE
                    WHEN ISNULL(ag.total_pedido, 0) > 0
                        THEN ISNULL(ag.total_servido, 0) / ag.total_pedido
                    ELSE 0
                END AS porcentaje
            FROM agregados ag
        ";

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerKpiServicioPedidos.exec', $conn, $sql, $params);
            return $resultado;
        }

        $row = odbc_fetch_array_utf8($rs);
        if (!$row) {
            return $resultado;
        }

        $totalPedido = (float)($row['total_pedido'] ?? 0);
        $totalServido = (float)($row['total_servido'] ?? 0);
        $totalServidoBruto = (float)($row['total_servido_bruto'] ?? 0);
        $porcentaje = (float)($row['porcentaje'] ?? 0);
        $excesoServicio = max(0, $totalServidoBruto - $totalPedido);
        $servicioOperativoTotal = $totalServido;
        $porcentajeServicioOperativo = $porcentaje;

        $resultado['total_pedido'] = $totalPedido;
        $resultado['total_servido'] = $servicioOperativoTotal;
        $resultado['total_servido_documental'] = $totalServido;
        $resultado['total_servido_bruto'] = $totalServidoBruto;
        $resultado['porcentaje'] = $porcentajeServicioOperativo;
        $resultado['servicio_real'] = $servicioOperativoTotal;
        $resultado['porcentaje_servicio'] = $porcentajeServicioOperativo;
        $resultado['exceso_servicio'] = $excesoServicio;
        $resultado['servicio_operativo_total'] = $servicioOperativoTotal;
        $resultado['porcentaje_servicio_operativo'] = $porcentajeServicioOperativo;

        $vistaDetalleContexto = trim((string)($contexto['vista_detalle'] ?? ''));
        $debugContextoRaw = $contexto['debug'] ?? null;
        $debugContextoActivo = $debugContextoRaw === true || (string)$debugContextoRaw === '1';
        $forzarDetalleServicio = (($contexto['forzar_detalle_servicio'] ?? false) === true);
        $calcularDetalleServicio = $forzarDetalleServicio
            || $debugContextoActivo
            || in_array($vistaDetalleContexto, ['servicio', 'servicio_real', 'detalle_servicio'], true);

        if (!$calcularDetalleServicio) {
            return $resultado;
        }

        $lineasPedido = [];
        $joinMarcaLineasPedidoSql = '';
        $whereMarcaLineasPedidoSql = '';
        $paramsLineasPedido = $params;
        if ($marca !== '') {
            $joinMarcaLineasPedidoSql = "
    INNER JOIN articulos a
                    ON a.cod_articulo = hvl.cod_articulo
            ";
            $whereMarcaLineasPedidoSql = "
                  AND LTRIM(RTRIM(a.marca)) = ?
            ";
        }
        $sqlLineasPedido = "
            WITH pedidos_lineas AS (
                SELECT
                    hvl.cod_venta,
                    hvl.tipo_venta,
                    hvl.cod_empresa,
                    hvl.cod_caja,
                    hvl.linea,
                    ISNULL(hvl.importe, 0) AS importe_linea,
                    hvc.cod_cliente,
                    c.nombre_comercial AS nombre_cliente,
                    hvc.cod_comisionista,
                    hvc.cod_seccion,
                    hvl.cod_articulo,
                    hvc.fecha_venta,
                    ISNULL(hvc.historico, 'N') AS historico,
                    ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1) AS cantidad_pedida
                FROM hist_ventas_cabecera hvc
                INNER JOIN hist_ventas_linea hvl
                    ON hvl.cod_empresa = hvc.cod_empresa
                   AND hvl.tipo_venta = hvc.tipo_venta
                   AND hvl.cod_venta = hvc.cod_venta
                   AND hvl.cod_caja = hvc.cod_caja
                " . $joinMarcaLineasPedidoSql . "
                LEFT JOIN integral.dbo.clientes c
                    ON c.cod_cliente = hvc.cod_cliente
                WHERE 1=1
                " . ($whereCabecera !== '' ? " AND " . $whereCabecera : "") . "
                " . $whereMarcaLineasPedidoSql . "
                  AND ISNULL(hvc.importe, 0) >= 0
            ),
            servicio_oficial AS (
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
            )
            SELECT
                pl.cod_venta,
                pl.cod_empresa,
                pl.cod_caja,
                pl.linea,
                pl.cod_cliente,
                pl.nombre_cliente,
                pl.cod_comisionista,
                pl.cod_seccion,
                pl.cod_articulo,
                pl.fecha_venta,
                pl.importe_linea,
                pl.historico,
                pl.cantidad_pedida,
                ISNULL(so.cantidad_servida_oficial, 0) AS cantidad_servida_oficial
            FROM pedidos_lineas pl
            LEFT JOIN servicio_oficial so
                ON so.cod_venta_origen = pl.cod_venta
               AND so.tipo_venta_origen = pl.tipo_venta
               AND so.cod_empresa_origen = pl.cod_empresa
               AND so.cod_caja_origen = pl.cod_caja
               AND so.linea_origen = pl.linea
        ";
        $rsLineasPedido = estadisticasOdbcExec($conn, $sqlLineasPedido, $paramsLineasPedido);
        if ($rsLineasPedido) {
            while ($rowLinea = odbc_fetch_array_utf8($rsLineasPedido)) {
                $lineasPedido[] = [
                    'cod_venta' => (string)($rowLinea['cod_venta'] ?? ''),
                    'cod_empresa' => (string)($rowLinea['cod_empresa'] ?? ''),
                    'cod_caja' => (string)($rowLinea['cod_caja'] ?? ''),
                    'linea' => (string)($rowLinea['linea'] ?? ''),
                    'cod_cliente' => (string)($rowLinea['cod_cliente'] ?? ''),
                    'nombre_cliente' => trim((string)($rowLinea['nombre_cliente'] ?? '')),
                    'cod_comisionista' => (string)($rowLinea['cod_comisionista'] ?? ''),
                    'cod_seccion' => (string)($rowLinea['cod_seccion'] ?? ''),
                    'cod_articulo' => (string)($rowLinea['cod_articulo'] ?? ''),
                    'fecha_venta' => (string)($rowLinea['fecha_venta'] ?? ''),
                    'importe_linea' => (float)($rowLinea['importe_linea'] ?? 0),
                    'historico' => strtoupper(trim((string)($rowLinea['historico'] ?? 'N'))),
                    'cantidad_pedida' => (float)($rowLinea['cantidad_pedida'] ?? 0),
                    'cantidad_servida_oficial' => (float)($rowLinea['cantidad_servida_oficial'] ?? 0),
                ];
            }
        } else {
            registrarErrorSqlEstadisticas('obtenerKpiServicioPedidosAjustado.lineas_pedido', $conn, $sqlLineasPedido, $paramsLineasPedido);
        }

        $detallePedidosMap = [];
        foreach ($lineasPedido as $lineaPedido) {
            $pedidoKey = implode('|', [
                trim((string)($lineaPedido['cod_empresa'] ?? '')),
                trim((string)($lineaPedido['cod_caja'] ?? '')),
                trim((string)($lineaPedido['cod_venta'] ?? '')),
            ]);
            if (!isset($detallePedidosMap[$pedidoKey])) {
                $codClientePedido = trim((string)($lineaPedido['cod_cliente'] ?? ''));
                $nombreClientePedido = trim((string)($lineaPedido['nombre_cliente'] ?? ''));
                $nombreClientePedidoUtf8 = toUTF8($nombreClientePedido);
                $codClientePedidoUtf8 = toUTF8($codClientePedido);
                $detallePedidosMap[$pedidoKey] = [
                    'cod_venta' => trim((string)($lineaPedido['cod_venta'] ?? '')),
                    'fecha' => trim((string)($lineaPedido['fecha_venta'] ?? '')),
                    'cliente' => $nombreClientePedidoUtf8 !== ''
                        ? ($nombreClientePedidoUtf8 . ' (' . $codClientePedidoUtf8 . ')')
                        : $codClientePedidoUtf8,
                    'historico' => strtoupper(trim((string)($lineaPedido['historico'] ?? 'N'))),
                    'importe_pedido' => 0.0,
                    'importe_servido_documental' => 0.0,
                    'importe_asignado_operativo' => 0.0,
                ];
            }

            $importeLineaPedido = (float)($lineaPedido['importe_linea'] ?? 0);
            $cantidadPedidaPedido = (float)($lineaPedido['cantidad_pedida'] ?? 0);
            $cantidadServidaOficialPedido = (float)($lineaPedido['cantidad_servida_oficial'] ?? 0);
            $cantidadServidaCapadaPedido = min(
                $cantidadPedidaPedido,
                max(0.0, $cantidadServidaOficialPedido)
            );
            $importeServidoDocumentalLinea = 0.0;
            if ($cantidadPedidaPedido > 0) {
                $importeServidoDocumentalLinea = ($cantidadServidaCapadaPedido / $cantidadPedidaPedido) * $importeLineaPedido;
            }

            $detallePedidosMap[$pedidoKey]['importe_pedido'] += $importeLineaPedido;
            $detallePedidosMap[$pedidoKey]['importe_servido_documental'] += $importeServidoDocumentalLinea;
        }

        $albaranesSinRelacion = [];
        $sqlAlbaranesSinRelacion = "
            SELECT
                hvc.cod_venta,
                hvc.cod_empresa,
                hvc.cod_caja,
                hvc.cod_cliente,
                hvc.fecha_venta,
                hvc.importe
            FROM hist_ventas_cabecera hvc
            WHERE hvc.tipo_venta = 2
              AND hvc.fecha_venta >= ?
              AND hvc.fecha_venta < ?
              AND hvc.importe >= 0
              " . ($codComisionista !== '' ? "AND hvc.cod_comisionista = ?" : "AND hvc.cod_comisionista > 0") . "
              AND NOT EXISTS (
                    SELECT 1
                    FROM entrega_lineas_venta elv
                    WHERE elv.cod_venta_destino = hvc.cod_venta
                      AND elv.tipo_venta_destino = hvc.tipo_venta
                      AND elv.cod_empresa_destino = hvc.cod_empresa
                      AND elv.cod_caja_destino = hvc.cod_caja
                )
        ";
        $paramsAlbaranSinRelacion = [$fDesde, $fHastaMasUno];
        if ($codComisionista !== '') {
            $paramsAlbaranSinRelacion[] = (int)$codComisionista;
        }
        $rsAlbaranesSinRelacion = estadisticasOdbcExec($conn, $sqlAlbaranesSinRelacion, $paramsAlbaranSinRelacion);
        if ($rsAlbaranesSinRelacion) {
            while ($rowAlbaran = odbc_fetch_array_utf8($rsAlbaranesSinRelacion)) {
                $albaranesSinRelacion[] = [
                    'cod_venta' => (string)($rowAlbaran['cod_venta'] ?? ''),
                    'cod_empresa' => (string)($rowAlbaran['cod_empresa'] ?? ''),
                    'cod_caja' => (string)($rowAlbaran['cod_caja'] ?? ''),
                    'cod_cliente' => (string)($rowAlbaran['cod_cliente'] ?? ''),
                    'fecha_venta' => (string)($rowAlbaran['fecha_venta'] ?? ''),
                    'importe' => (float)($rowAlbaran['importe'] ?? 0),
                ];
            }
        } else {
            registrarErrorSqlEstadisticas('obtenerKpiServicioPedidosAjustado.albaranes_sin_relacion', $conn, $sqlAlbaranesSinRelacion, $paramsAlbaranSinRelacion);
        }

        $totalHuerfanosImporte = 0.0;
        foreach ($albaranesSinRelacion as $albaranSinRelacion) {
            $totalHuerfanosImporte += (float)($albaranSinRelacion['importe'] ?? 0);
        }
        if (!empty($albaranesSinRelacion)) {
            usort($albaranesSinRelacion, static function (array $a, array $b): int {
                $fa = strtotime((string)($a['fecha_venta'] ?? ''));
                $fb = strtotime((string)($b['fecha_venta'] ?? ''));
                $fa = ($fa === false) ? 0 : $fa;
                $fb = ($fb === false) ? 0 : $fb;
                return $fb <=> $fa;
            });
        }
        $debugAlbaranesSinRelacionMuestra = array_slice($albaranesSinRelacion, 0, 25);

        $albaranesSinRelacionLineas = [];
        $sqlAlbaranesSinRelacionLineas = "
            SELECT
                hvc.cod_venta,
                hvc.cod_empresa,
                hvc.cod_caja,
                hvc.cod_cliente,
                hvc.cod_comisionista,
                hvc.cod_seccion,
                hvl.cod_articulo,
                hvc.fecha_venta,
                ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1) AS cantidad,
                ISNULL(hvl.importe, 0) AS importe_linea
            FROM hist_ventas_cabecera hvc
            INNER JOIN hist_ventas_linea hvl
                ON hvl.cod_empresa = hvc.cod_empresa
               AND hvl.tipo_venta = hvc.tipo_venta
               AND hvl.cod_venta = hvc.cod_venta
               AND hvl.cod_caja = hvc.cod_caja
            WHERE hvc.tipo_venta = 2
              AND hvc.fecha_venta >= ?
              AND hvc.fecha_venta < ?
              AND hvc.importe >= 0
              " . ($codComisionista !== '' ? "AND hvc.cod_comisionista = ?" : "AND hvc.cod_comisionista > 0") . "
              AND NOT EXISTS (
                    SELECT 1
                    FROM entrega_lineas_venta elv
                    WHERE elv.cod_venta_destino = hvc.cod_venta
                      AND elv.tipo_venta_destino = hvc.tipo_venta
                      AND elv.cod_empresa_destino = hvc.cod_empresa
                      AND elv.cod_caja_destino = hvc.cod_caja
                )
        ";
        $rsAlbaranesSinRelacionLineas = estadisticasOdbcExec($conn, $sqlAlbaranesSinRelacionLineas, $paramsAlbaranSinRelacion);
        if ($rsAlbaranesSinRelacionLineas) {
            while ($rowAlbaranLinea = odbc_fetch_array_utf8($rsAlbaranesSinRelacionLineas)) {
                $albaranesSinRelacionLineas[] = [
                    'cod_venta' => (string)($rowAlbaranLinea['cod_venta'] ?? ''),
                    'cod_empresa' => (string)($rowAlbaranLinea['cod_empresa'] ?? ''),
                    'cod_caja' => (string)($rowAlbaranLinea['cod_caja'] ?? ''),
                    'cod_cliente' => (string)($rowAlbaranLinea['cod_cliente'] ?? ''),
                    'cod_comisionista' => (string)($rowAlbaranLinea['cod_comisionista'] ?? ''),
                    'cod_seccion' => (string)($rowAlbaranLinea['cod_seccion'] ?? ''),
                    'cod_articulo' => (string)($rowAlbaranLinea['cod_articulo'] ?? ''),
                    'fecha_venta' => (string)($rowAlbaranLinea['fecha_venta'] ?? ''),
                    'cantidad' => (float)($rowAlbaranLinea['cantidad'] ?? 0),
                    'importe_linea' => (float)($rowAlbaranLinea['importe_linea'] ?? 0),
                ];
            }
        } else {
            registrarErrorSqlEstadisticas('obtenerKpiServicioPedidosAjustado.albaranes_sin_relacion_lineas', $conn, $sqlAlbaranesSinRelacionLineas, $paramsAlbaranSinRelacion);
        }

        $parseTs = static function (string $fecha): ?int {
            $fecha = trim($fecha);
            if ($fecha === '') {
                return null;
            }
            $ts = strtotime($fecha);
            return ($ts === false) ? null : $ts;
        };

        $pedidosPendientesPorClave = [];
        foreach ($lineasPedido as $lineaPedido) {
            $cantidadPedida = (float)($lineaPedido['cantidad_pedida'] ?? 0);
            if ($cantidadPedida <= 0) {
                continue;
            }
            $cantidadServidaOficial = (float)($lineaPedido['cantidad_servida_oficial'] ?? 0);
            $cantidadServidaCapada = min($cantidadPedida, max(0.0, $cantidadServidaOficial));
            $cantidadPendiente = max(0.0, $cantidadPedida - $cantidadServidaCapada);
            if ($cantidadPendiente <= 0) {
                continue;
            }

            $clave = implode('|', [
                trim((string)($lineaPedido['cod_cliente'] ?? '')),
                trim((string)($lineaPedido['cod_comisionista'] ?? '')),
                trim((string)($lineaPedido['cod_seccion'] ?? '')),
                trim((string)($lineaPedido['cod_articulo'] ?? '')),
            ]);
            $pedidoTs = $parseTs((string)($lineaPedido['fecha_venta'] ?? ''));
            if ($pedidoTs === null) {
                continue;
            }

            $pedidosPendientesPorClave[$clave][] = [
                'restante' => $cantidadPendiente,
                'fecha_ts' => $pedidoTs,
                'cod_venta' => (string)($lineaPedido['cod_venta'] ?? ''),
                'cod_empresa' => (string)($lineaPedido['cod_empresa'] ?? ''),
                'cod_caja' => (string)($lineaPedido['cod_caja'] ?? ''),
                'linea' => (string)($lineaPedido['linea'] ?? ''),
                'pedido_key' => implode('|', [
                    trim((string)($lineaPedido['cod_empresa'] ?? '')),
                    trim((string)($lineaPedido['cod_caja'] ?? '')),
                    trim((string)($lineaPedido['cod_venta'] ?? '')),
                ]),
                'historico' => strtoupper(trim((string)($lineaPedido['historico'] ?? 'N'))) === 'S' ? 1 : 0,
            ];
        }

        foreach ($pedidosPendientesPorClave as &$pedidosPendientes) {
            usort($pedidosPendientes, static function (array $a, array $b): int {
                if ($a['historico'] !== $b['historico']) {
                    return $a['historico'] <=> $b['historico'];
                }
                if ($a['fecha_ts'] !== $b['fecha_ts']) {
                    return $a['fecha_ts'] <=> $b['fecha_ts'];
                }
                if ($a['cod_venta'] !== $b['cod_venta']) {
                    return strcmp($a['cod_venta'], $b['cod_venta']);
                }
                return strcmp($a['linea'], $b['linea']);
            });
        }
        unset($pedidosPendientes);

        $totalHuerfanosAsignadosImporte = 0.0;
        $debugHuerfanosTotalCount = count($albaranesSinRelacionLineas);
        $debugHuerfanosAsignadosCount = 0;
        $debugHuerfanosAsignadosDetalle = [];
        foreach ($albaranesSinRelacionLineas as $lineaHuerfana) {
            $cantidadHuerfana = (float)($lineaHuerfana['cantidad'] ?? 0);
            $importeHuerfano = (float)($lineaHuerfana['importe_linea'] ?? 0);
            if ($cantidadHuerfana <= 0 || $importeHuerfano <= 0) {
                continue;
            }
            $fechaAlbaranTs = $parseTs((string)($lineaHuerfana['fecha_venta'] ?? ''));
            if ($fechaAlbaranTs === null) {
                continue;
            }
            $fechaMinimaPedidoTs = strtotime('-30 days', $fechaAlbaranTs);
            $clave = implode('|', [
                trim((string)($lineaHuerfana['cod_cliente'] ?? '')),
                trim((string)($lineaHuerfana['cod_comisionista'] ?? '')),
                trim((string)($lineaHuerfana['cod_seccion'] ?? '')),
                trim((string)($lineaHuerfana['cod_articulo'] ?? '')),
            ]);
            if (!isset($pedidosPendientesPorClave[$clave])) {
                continue;
            }

            $cantidadPorAsignar = $cantidadHuerfana;
            foreach ($pedidosPendientesPorClave[$clave] as &$pedidoPendiente) {
                if ($cantidadPorAsignar <= 0) {
                    break;
                }
                if ($pedidoPendiente['restante'] <= 0) {
                    continue;
                }
                if ($pedidoPendiente['fecha_ts'] < $fechaMinimaPedidoTs || $pedidoPendiente['fecha_ts'] > $fechaAlbaranTs) {
                    continue;
                }

                $cantidadAsignada = min($cantidadPorAsignar, $pedidoPendiente['restante']);
                if ($cantidadAsignada <= 0) {
                    continue;
                }
                $pedidoPendiente['restante'] -= $cantidadAsignada;
                $cantidadPorAsignar -= $cantidadAsignada;
                $proporcionAsignadaTramo = $cantidadAsignada / $cantidadHuerfana;
                $importeAsignadoTramo = $importeHuerfano * $proporcionAsignadaTramo;
                if ($importeAsignadoTramo > 0) {
                    $pedidoKeyAsignado = (string)($pedidoPendiente['pedido_key'] ?? '');
                    if ($pedidoKeyAsignado !== '' && isset($detallePedidosMap[$pedidoKeyAsignado])) {
                        $detallePedidosMap[$pedidoKeyAsignado]['importe_asignado_operativo'] += (float)$importeAsignadoTramo;
                    }
                    $debugHuerfanosAsignadosDetalle[] = [
                        'cod_empresa' => (string)($lineaHuerfana['cod_empresa'] ?? ''),
                        'cod_caja' => (string)($lineaHuerfana['cod_caja'] ?? ''),
                        'cod_venta' => (string)($lineaHuerfana['cod_venta'] ?? ''),
                        'fecha_venta' => (string)($lineaHuerfana['fecha_venta'] ?? ''),
                        'cod_cliente' => (string)($lineaHuerfana['cod_cliente'] ?? ''),
                        'cod_articulo' => (string)($lineaHuerfana['cod_articulo'] ?? ''),
                        'importe_asignado' => (float)$importeAsignadoTramo,
                        'pedido_destino' => (string)($pedidoPendiente['cod_venta'] ?? ''),
                    ];
                }
            }
            unset($pedidoPendiente);

            $cantidadAsignadaTotal = max(0.0, $cantidadHuerfana - $cantidadPorAsignar);
            if ($cantidadAsignadaTotal > 0) {
                $debugHuerfanosAsignadosCount++;
            }
        }

        $totalHuerfanosAsignadosImporte = 0.0;
        foreach ($detallePedidosMap as $detallePedido) {
            $totalHuerfanosAsignadosImporte += (float)($detallePedido['importe_asignado_operativo'] ?? 0);
        }

        $totalHuerfanosNoAsignablesImporte = max(0.0, $totalHuerfanosImporte - $totalHuerfanosAsignadosImporte);
        $porcentajeHuerfanosAsignables = ($totalHuerfanosImporte > 0)
            ? ($totalHuerfanosAsignadosImporte / $totalHuerfanosImporte)
            : 0.0;
        $porcentajeHuerfanosAsignables = min(1.0, max(0.0, $porcentajeHuerfanosAsignables));
        $servicioOperativoTotal = $totalServido + $totalHuerfanosAsignadosImporte;
        $porcentajeServicioOperativo = ($totalPedido > 0)
            ? min(1.0, $servicioOperativoTotal / $totalPedido)
            : 0.0;
        $debugHuerfanosNoAsignablesCount = max(0, $debugHuerfanosTotalCount - $debugHuerfanosAsignadosCount);
        if (!empty($debugHuerfanosAsignadosDetalle)) {
            usort($debugHuerfanosAsignadosDetalle, static function (array $a, array $b): int {
                return ((float)($b['importe_asignado'] ?? 0)) <=> ((float)($a['importe_asignado'] ?? 0));
            });
        }
        $debugHuerfanosAsignadosDetalleCount = count($debugHuerfanosAsignadosDetalle);
        $debugHuerfanosAsignadosSample = array_slice($debugHuerfanosAsignadosDetalle, 0, 25);
        $detalleAgregado = agruparDetalleServicioPedidosDesdeMapa($detallePedidosMap);
        $detalleServicioPedidos = (array)($detalleAgregado['detalle_pedidos_servicio'] ?? []);
        $totalServidoRealDetalle = (float)($detalleAgregado['total_servido_real_detalle'] ?? 0.0);
        $servicioOperativoTotal = $totalServidoRealDetalle;
        $porcentajeServicioOperativo = ($totalPedido > 0)
            ? min(1.0, $servicioOperativoTotal / $totalPedido)
            : 0.0;

        $salida = [
            'total_pedido' => $totalPedido,
            'total_servido' => $servicioOperativoTotal,
            'total_servido_documental' => $totalServido,
            'total_servido_bruto' => $totalServidoBruto,
            'porcentaje' => $porcentajeServicioOperativo,
            'servicio_real' => $servicioOperativoTotal,
            'porcentaje_servicio' => $porcentajeServicioOperativo,
            'exceso_servicio' => $excesoServicio,
            'total_huerfanos_importe' => $totalHuerfanosImporte,
            'total_huerfanos_asignados_importe' => $totalHuerfanosAsignadosImporte,
            'total_huerfanos_no_asignables_importe' => $totalHuerfanosNoAsignablesImporte,
            'porcentaje_huerfanos_asignables' => $porcentajeHuerfanosAsignables,
            'servicio_operativo_total' => $servicioOperativoTotal,
            'porcentaje_servicio_operativo' => $porcentajeServicioOperativo,
            'detalle_pedidos_servicio' => $detalleServicioPedidos,
        ];
        if ($debugActivo) {
            $salida['debug_lineas_pedido_count'] = count($lineasPedido);
            $salida['debug_albaranes_sin_relacion_count'] = count($albaranesSinRelacion);
            $salida['debug_albaranes_sin_relacion_sample'] = $debugAlbaranesSinRelacionMuestra;
            $salida['debug_huerfanos_total_count'] = $debugHuerfanosTotalCount;
            $salida['debug_huerfanos_asignados_count'] = $debugHuerfanosAsignadosCount;
            $salida['debug_huerfanos_no_asignables_count'] = $debugHuerfanosNoAsignablesCount;
            $salida['debug_huerfanos_asignados_detail_count'] = $debugHuerfanosAsignadosDetalleCount;
            $salida['debug_huerfanos_asignados_sample'] = $debugHuerfanosAsignadosSample;
            $salida['es_experimental'] = true;
        }
        return $salida;
    }
}


if (!function_exists('__estadisticas_impl_obtenerKpiServicioPedidosUnified')) {
    function __estadisticas_impl_obtenerKpiServicioPedidosUnified($conn, array $contexto, array $opciones = []): array
    {
        $modo = strtolower(trim((string)($opciones['modo'] ?? 'operativo')));
        if ($modo === 'documental') {
            return obtenerKpiServicioPedidos($conn, $contexto);
        }
        return obtenerKpiServicioPedidosAjustado($conn, $contexto);
    }
}


if (!function_exists('__estadisticas_impl_agruparDetalleServicioPedidosDesdeMapa')) {
    function __estadisticas_impl_agruparDetalleServicioPedidosDesdeMapa(array $detallePedidosMap): array
    {
        $detalleServicioPedidos = [];
        $totalServidoRealDetalle = 0.0;

        foreach ($detallePedidosMap as $detallePedido) {
            $importePedidoDetalle = (float)($detallePedido['importe_pedido'] ?? 0);
            $importeServidoDocumentalDetalle = (float)($detallePedido['importe_servido_documental'] ?? 0);
            $importeAsignadoOperativoDetalle = (float)($detallePedido['importe_asignado_operativo'] ?? 0);
            $importeServidoRealDetalle = min(
                $importePedidoDetalle,
                max(0.0, $importeServidoDocumentalDetalle + $importeAsignadoOperativoDetalle)
            );
            $pendienteDetalle = max(0.0, $importePedidoDetalle - $importeServidoRealDetalle);
            $porcentajeServidoDetalle = ($importePedidoDetalle > 0)
                ? (($importeServidoRealDetalle / $importePedidoDetalle) * 100)
                : 0.0;
            $totalServidoRealDetalle += $importeServidoRealDetalle;

            $fechaDetalleRaw = trim((string)($detallePedido['fecha'] ?? ''));
            $fechaDetalle = $fechaDetalleRaw;
            if ($fechaDetalleRaw !== '') {
                try {
                    $fechaDetalle = (new DateTimeImmutable($fechaDetalleRaw))->format('d-m-Y');
                } catch (Throwable $e) {
                    $fechaDetalle = $fechaDetalleRaw;
                }
            }

            $detalleServicioPedidos[] = [
                'cod_venta' => trim((string)($detallePedido['cod_venta'] ?? '')),
                'fecha' => $fechaDetalle,
                'cliente' => trim((string)($detallePedido['cliente'] ?? '')),
                'historico' => strtoupper(trim((string)($detallePedido['historico'] ?? 'N'))),
                'importe_pedido' => $importePedidoDetalle,
                'importe_servido_documental' => $importeServidoDocumentalDetalle,
                'importe_aplicado_operativo' => $importeAsignadoOperativoDetalle,
                'importe_servido_real' => $importeServidoRealDetalle,
                'pendiente' => $pendienteDetalle,
                'porcentaje_servido' => $porcentajeServidoDetalle,
            ];
        }

        return [
            'detalle_pedidos_servicio' => $detalleServicioPedidos,
            'total_servido_real_detalle' => $totalServidoRealDetalle,
        ];
    }
}


if (!function_exists('__estadisticas_impl_obtenerDetalleServicioPedidos')) {
    function __estadisticas_impl_obtenerDetalleServicioPedidos($conn, array $contexto, ?int $limit = null, ?int $offset = null): array
    {
        $resultado = [
            'filas' => [],
            'total_registros' => 0,
        ];
        if (!$conn) {
            return $resultado;
        }

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        $codComisionistaActivo = trim((string)($contexto['cod_comisionista_activo'] ?? ''));
        $codComisionistaFiltro = null;
        if ($codComisionistaActivo !== '' && ctype_digit($codComisionistaActivo) && (int)$codComisionistaActivo > 0) {
            $codComisionistaFiltro = $codComisionistaActivo;
        }

        $filtrosCabecera = [
            'tipo_venta' => 1,
            'excluir_anuladas' => true,
            'excluir_comisionista_cero' => true,
            'fecha_desde' => $fDesde,
            'fecha_hasta' => $fHasta,
        ];
        if ($codComisionistaFiltro !== null) {
            $filtrosCabecera['cod_comisionista'] = $codComisionistaFiltro;
        }
        [$whereCabecera, $paramsBase] = buildWhereCabecera('hvc', $filtrosCabecera);

        $whereSql = $whereCabecera !== '' ? " AND " . $whereCabecera : '';
        $cteSql = "
            WITH pedidos_lineas AS (
                SELECT
                    hvl.cod_venta,
                    hvl.tipo_venta,
                    hvl.cod_empresa,
                    hvl.cod_caja,
                    hvl.linea,
                    ISNULL(hvl.importe, 0) AS importe_linea,
                    hvc.cod_cliente,
                    c.nombre_comercial AS nombre_cliente,
                    hvc.fecha_venta,
                    ISNULL(hvc.historico, 'N') AS historico,
                    ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1) AS cantidad_pedida
                FROM hist_ventas_cabecera hvc
                INNER JOIN hist_ventas_linea hvl
                    ON hvl.cod_empresa = hvc.cod_empresa
                   AND hvl.tipo_venta = hvc.tipo_venta
                   AND hvl.cod_venta = hvc.cod_venta
                   AND hvl.cod_caja = hvc.cod_caja
                LEFT JOIN integral.dbo.clientes c
                    ON c.cod_cliente = hvc.cod_cliente
                WHERE 1=1
                " . $whereSql . "
                  AND ISNULL(hvc.importe, 0) >= 0
            ),
            servicio_oficial AS (
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
            ),
            lineas_calculadas AS (
                SELECT
                    pl.cod_venta,
                    pl.cod_empresa,
                    pl.cod_caja,
                    pl.linea,
                    pl.fecha_venta,
                    pl.cod_cliente,
                    pl.nombre_cliente,
                    pl.historico,
                    pl.importe_linea,
                    pl.cantidad_pedida,
                    CASE
                        WHEN ISNULL(so.cantidad_servida_oficial, 0) < pl.cantidad_pedida
                            THEN ISNULL(so.cantidad_servida_oficial, 0)
                        ELSE pl.cantidad_pedida
                    END AS cantidad_servida_real
                FROM pedidos_lineas pl
                LEFT JOIN servicio_oficial so
                    ON so.cod_venta_origen = pl.cod_venta
                   AND so.tipo_venta_origen = pl.tipo_venta
                   AND so.cod_empresa_origen = pl.cod_empresa
                   AND so.cod_caja_origen = pl.cod_caja
                   AND so.linea_origen = pl.linea
            ),
            pedidos_detalle AS (
                SELECT
                    lc.cod_venta,
                    lc.cod_empresa,
                    lc.cod_caja,
                    MAX(lc.fecha_venta) AS fecha_venta,
                    MAX(lc.cod_cliente) AS cod_cliente,
                    MAX(lc.nombre_cliente) AS nombre_cliente,
                    MAX(lc.historico) AS historico,
                    SUM(lc.importe_linea) AS importe_pedido,
                    SUM(
                        CASE
                            WHEN lc.cantidad_pedida > 0
                                THEN (lc.cantidad_servida_real / lc.cantidad_pedida) * lc.importe_linea
                            ELSE 0
                        END
                    ) AS importe_servido_real
                FROM lineas_calculadas lc
                GROUP BY
                    lc.cod_venta,
                    lc.cod_empresa,
                    lc.cod_caja
            )
        ";

        $sqlCount = $cteSql . "
            SELECT COUNT(1) AS total_registros_detalle
            FROM pedidos_detalle d
            WHERE (ISNULL(d.importe_pedido, 0) - ISNULL(d.importe_servido_real, 0)) > 0.01
        ";
        $rsCount = estadisticasOdbcExec($conn, $sqlCount, $paramsBase);
        if ($rsCount) {
            $rowCount = odbc_fetch_array_utf8($rsCount);
            $resultado['total_registros'] = (int)($rowCount['total_registros_detalle'] ?? 0);
        } else {
            registrarErrorSqlEstadisticas('obtenerDetalleServicioPedidos.count', $conn, $sqlCount, $paramsBase);
        }

        $sqlFilas = $cteSql . "
            SELECT
                d.cod_venta,
                d.cod_empresa,
                d.cod_caja,
                d.fecha_venta AS fecha,
                d.cod_cliente,
                d.nombre_cliente,
                d.historico,
                ISNULL(d.importe_pedido, 0) AS importe_pedido,
                ISNULL(d.importe_servido_real, 0) AS importe_servido_real,
                CAST(0 AS FLOAT) AS importe_aplicado_operativo,
                (ISNULL(d.importe_pedido, 0) - ISNULL(d.importe_servido_real, 0)) AS pendiente,
                CASE
                    WHEN ISNULL(d.importe_pedido, 0) > 0
                        THEN ((ISNULL(d.importe_servido_real, 0) / d.importe_pedido) * 100)
                    ELSE 0
                END AS porcentaje_servido
            FROM pedidos_detalle d
            WHERE (ISNULL(d.importe_pedido, 0) - ISNULL(d.importe_servido_real, 0)) > 0.01
            ORDER BY pendiente DESC, d.fecha_venta DESC, d.cod_venta DESC
        ";

        $paramsFilas = $paramsBase;
        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $offset = max(0, (int)($offset ?? 0));
            $sqlFilas .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
            $paramsFilas[] = $offset;
            $paramsFilas[] = $limit;
        }

        $rsFilas = estadisticasOdbcExec($conn, $sqlFilas, $paramsFilas);
        if (!$rsFilas) {
            registrarErrorSqlEstadisticas('obtenerDetalleServicioPedidos.filas', $conn, $sqlFilas, $paramsFilas);
            return $resultado;
        }

        $filas = [];
        while ($row = odbc_fetch_array_utf8($rsFilas)) {
            $nombreCliente = trim((string)($row['nombre_cliente'] ?? ''));
            $codCliente = trim((string)($row['cod_cliente'] ?? ''));
            $cliente = $nombreCliente !== '' ? ($nombreCliente . ' (' . $codCliente . ')') : $codCliente;

            $filas[] = [
                'cod_venta' => trim((string)($row['cod_venta'] ?? '')),
                'fecha' => (string)($row['fecha'] ?? ''),
                'cod_cliente' => $codCliente,
                'cliente' => $cliente,
                'historico' => strtoupper(trim((string)($row['historico'] ?? 'N'))),
                'importe_pedido' => (float)($row['importe_pedido'] ?? 0),
                'importe_servido_documental' => (float)($row['importe_servido_real'] ?? 0),
                'importe_aplicado_operativo' => (float)($row['importe_aplicado_operativo'] ?? 0),
                'importe_servido_real' => (float)($row['importe_servido_real'] ?? 0),
                'pendiente' => (float)($row['pendiente'] ?? 0),
                'porcentaje_servido' => (float)($row['porcentaje_servido'] ?? 0),
            ];
        }

        $resultado['filas'] = $filas;
        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerDetalleDiferenciaDocumental')) {
    function __estadisticas_impl_obtenerDetalleDiferenciaDocumental($conn, array $contexto): array
    {
        $resultado = [
            'filas' => [],
            'debug_total' => 0,
            'total_diferencia' => 0.0,
        ];
        if (!$conn) {
            return $resultado;
        }

        [$fDesde, $fHastaMasUno] = obtenerRangoFechasContextoSql($contexto);
        // ORDEN PARAMS: desde, hasta, comercial
        $params = [];
        $params[] = $fDesde;
        $params[] = $fHastaMasUno;
        $params[] = $fDesde;
        $params[] = $fHastaMasUno;
        [$condicionComercial, $paramsComercial] = construirCondicionComercialParams('albaran', $contexto);
        $params = array_merge($params, $paramsComercial);

        $sql = "
            SELECT
                t.pedido,
                t.albaran,
                t.cod_cliente,
                t.nombre_cliente,
                SUM(t.importe_pedido) AS importe_pedido,
                SUM(t.importe_albaran) AS importe_albaran,
                SUM(t.importe_albaran - t.importe_pedido) AS diferencia
            FROM (
                SELECT
                    pedido.cod_venta AS pedido,
                    albaran.cod_venta AS albaran,
                    albaran.cod_cliente,
                    c.nombre_comercial AS nombre_cliente,
                    hvlp.cod_articulo,
                    SUM(
                        ISNULL(elv.cantidad, 0) *
                        (
                            ISNULL(hvlp.importe, 0) /
                            NULLIF(ISNULL(hvlp.cantidad, 0), 0)
                        )
                    ) AS importe_pedido,
                    SUM(
                        ISNULL(elv.cantidad, 0) *
                        ISNULL(alb_art.precio_unitario_albaran, 0)
                    ) AS importe_albaran
                FROM entrega_lineas_venta elv
                INNER JOIN hist_ventas_cabecera pedido
                    ON pedido.cod_venta = elv.cod_venta_origen
                   AND pedido.tipo_venta = elv.tipo_venta_origen
                   AND pedido.cod_empresa = elv.cod_empresa_origen
                   AND pedido.cod_caja = elv.cod_caja_origen
                   AND pedido.tipo_venta = 1
                INNER JOIN hist_ventas_cabecera albaran
                    ON albaran.cod_venta = elv.cod_venta_destino
                   AND albaran.tipo_venta = elv.tipo_venta_destino
                   AND albaran.cod_empresa = elv.cod_empresa_destino
                   AND albaran.cod_caja = elv.cod_caja_destino
                   AND albaran.tipo_venta = 2
                INNER JOIN hist_ventas_linea hvlp
                    ON hvlp.cod_venta = elv.cod_venta_origen
                   AND hvlp.tipo_venta = elv.tipo_venta_origen
                   AND hvlp.cod_empresa = elv.cod_empresa_origen
                   AND hvlp.cod_caja = elv.cod_caja_origen
                   AND hvlp.linea = elv.linea_origen
                LEFT JOIN integral.dbo.clientes c
                    ON c.cod_cliente = albaran.cod_cliente
                LEFT JOIN (
                    SELECT
                        hvl.cod_venta,
                        hvl.tipo_venta,
                        hvl.cod_empresa,
                        hvl.cod_caja,
                        hvl.cod_articulo,
                        CASE
                            WHEN SUM(ISNULL(hvl.cantidad, 0)) <> 0
                                THEN SUM(ISNULL(hvl.importe, 0)) / SUM(ISNULL(hvl.cantidad, 0))
                            ELSE AVG(ISNULL(TRY_CAST(hvl.precio AS FLOAT), 0))
                        END AS precio_unitario_albaran
                    FROM hist_ventas_linea hvl
                    WHERE hvl.tipo_venta = 2
                    GROUP BY
                        hvl.cod_venta,
                        hvl.tipo_venta,
                        hvl.cod_empresa,
                        hvl.cod_caja,
                        hvl.cod_articulo
                ) alb_art
                    ON alb_art.cod_venta = elv.cod_venta_destino
                   AND alb_art.tipo_venta = elv.tipo_venta_destino
                   AND alb_art.cod_empresa = elv.cod_empresa_destino
                   AND alb_art.cod_caja = elv.cod_caja_destino
                   AND alb_art.cod_articulo = hvlp.cod_articulo
                WHERE elv.tipo_venta_origen = 1
                  AND elv.tipo_venta_destino = 2
                  AND ISNULL(albaran.anulada, 'N') <> 'S'
                  AND ISNULL(albaran.cod_comisionista, 0) <> 0
                  " . construirRangoFechasSql('pedido.fecha_venta') . "
                  " . construirRangoFechasSql('albaran.fecha_venta') . "
                  AND ISNULL(hvlp.cantidad, 0) > 0
                  " . $condicionComercial . "
                GROUP BY
                    pedido.cod_venta,
                    albaran.cod_venta,
                    albaran.cod_cliente,
                    c.nombre_comercial,
                    hvlp.cod_articulo
            ) t
            WHERE ABS(t.importe_albaran - t.importe_pedido) >= 0.01
            GROUP BY
                t.pedido,
                t.albaran,
                t.cod_cliente,
                t.nombre_cliente
            ORDER BY t.albaran DESC, t.pedido DESC
        ";

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerDetalleDiferenciaDocumental.exec', $conn, $sql, $params);
            return $resultado;
        }

        $filas = [];
        $total = 0.0;
        while ($row = odbc_fetch_array_utf8($rs)) {
            $diferencia = (float)($row['diferencia'] ?? 0);
            $filas[] = [
                'pedido' => trim((string)($row['pedido'] ?? '')),
                'albaran' => trim((string)($row['albaran'] ?? '')),
                'cliente' => trim((string)($row['nombre_cliente'] ?? '')) . ' (' . trim((string)($row['cod_cliente'] ?? '')) . ')',
                'diferencia' => $diferencia,
            ];
            $total += $diferencia;
        }

        $resultado['filas'] = $filas;
        $resultado['debug_total'] = count($filas);
        $resultado['total_diferencia'] = $total;
        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerDetalleSegunVista')) {
    function __estadisticas_impl_obtenerDetalleSegunVista($conn, array $contexto, string $vista): array
    {
        $base = [
            'titulo' => 'Albaranes sin pedido',
            'columnas' => ['cod_venta', 'fecha_venta', 'cod_cliente', 'nombre_cliente', 'cod_comisionista', 'cod_vendedor', 'importe'],
            'filas' => [],
            'debug_total' => 0,
        ];
        if (!$conn) {
            return $base;
        }

        $vista = trim($vista);
        $vistasPermitidas = [
            'pedidos_ventas',
            'pedidos_abonos',
            'albaranes_totales',
            'albaranes_con_pedido',
            'albaranes_sin_pedido',
            'diferencia_documental',
        ];
        if (!in_array($vista, $vistasPermitidas, true)) {
            $vista = 'albaranes_sin_pedido';
        }

        if ($vista === 'diferencia_documental') {
            $detalle = obtenerDetalleDiferenciaDocumental($conn, $contexto);
            return [
                'titulo' => 'Diferencia documental',
                'columnas' => ['pedido', 'albaran', 'cliente', 'diferencia'],
                'filas' => $detalle['filas'],
                'debug_total' => (int)$detalle['debug_total'],
                'total_diferencia' => (float)$detalle['total_diferencia'],
            ];
        }

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = ((string)($contexto['tipo_filtro_comercial'] ?? 'todos') === 'cod_comisionista')
            ? trim((string)($contexto['valor_filtro_comercial'] ?? ''))
            : '';

        $sql = '';
        $contextoError = 'obtenerDetalleSegunVista.exec';

        if ($vista === 'pedidos_ventas' || $vista === 'pedidos_abonos') {
            $filtroImporte = $vista === 'pedidos_ventas'
                ? 'AND d.importe > 0'
                : 'AND d.importe < 0';
            $base['titulo'] = $vista === 'pedidos_ventas' ? 'Pedidos ventas' : 'Pedidos abonos';
            [$whereCabecera, $params] = buildWhereCabecera('hvc', [
                'tipo_venta' => 1,
                'excluir_anuladas' => true,
                'excluir_comisionista_cero' => true,
                'fecha_desde' => $fDesde,
                'fecha_hasta' => $fHasta,
                'cod_comisionista' => $codComisionista,
            ]);
            [$whereLineas, $paramsLineas] = buildWhereLineasDocumentales($contexto, 'a', 'hvl', 'hvc');
            $params = array_merge($params, $paramsLineas);
            $sql = "
                SELECT TOP 100
                    d.cod_venta,
                    d.fecha_venta,
                    d.cod_cliente,
                    c.nombre_comercial AS nombre_cliente,
                    d.cod_comisionista,
                    d.cod_vendedor,
                    d.importe
                FROM (
                    SELECT
                        hvc.cod_venta,
                        hvc.fecha_venta,
                        hvc.cod_cliente,
                        hvc.cod_comisionista,
                        hvc.cod_vendedor,
                        hvc.cod_empresa,
                        hvc.cod_caja,
                        hvc.tipo_venta,
                        SUM(ISNULL(TRY_CAST(hvl.importe AS FLOAT), 0)) AS importe
                    FROM hist_ventas_cabecera hvc
                    INNER JOIN hist_ventas_linea hvl
                        ON hvc.cod_venta = hvl.cod_venta
                       AND hvc.tipo_venta = hvl.tipo_venta
                       AND hvc.cod_empresa = hvl.cod_empresa
                       AND hvc.cod_caja = hvl.cod_caja
    LEFT JOIN articulos a
                        ON a.cod_articulo = hvl.cod_articulo
                    WHERE 1=1
                      " . ($whereCabecera !== '' ? " AND " . $whereCabecera : "") . "
                      " . ($whereLineas !== '' ? " AND " . $whereLineas : "") . "
                    GROUP BY
                        hvc.cod_venta,
                        hvc.fecha_venta,
                        hvc.cod_cliente,
                        hvc.cod_comisionista,
                        hvc.cod_vendedor,
                        hvc.cod_empresa,
                        hvc.cod_caja,
                        hvc.tipo_venta
                ) d
                LEFT JOIN integral.dbo.clientes c
                    ON c.cod_cliente = d.cod_cliente
                WHERE 1=1
                  " . $filtroImporte . "
                ORDER BY d.fecha_venta DESC, d.cod_venta DESC
            ";
        } elseif ($vista === 'albaranes_totales') {
            $base['titulo'] = 'Albaranes totales';
            [$whereCabecera, $params] = buildWhereCabecera('hvc', [
                'tipo_venta' => 2,
                'excluir_anuladas' => true,
                'excluir_comisionista_cero' => true,
                'fecha_desde' => $fDesde,
                'fecha_hasta' => $fHasta,
                'cod_comisionista' => $codComisionista,
            ]);
            [$whereLineas, $paramsLineas] = buildWhereLineasDocumentales($contexto, 'a', 'hvl', 'hvc');
            $params = array_merge($params, $paramsLineas);
            $sql = "
                SELECT TOP 100
                    d.cod_venta,
                    d.fecha_venta,
                    d.cod_cliente,
                    c.nombre_comercial AS nombre_cliente,
                    d.cod_comisionista,
                    d.cod_vendedor,
                    d.importe
                FROM (
                    SELECT
                        hvc.cod_venta,
                        hvc.fecha_venta,
                        hvc.cod_cliente,
                        hvc.cod_comisionista,
                        hvc.cod_vendedor,
                        hvc.cod_empresa,
                        hvc.cod_caja,
                        hvc.tipo_venta,
                        SUM(ISNULL(TRY_CAST(hvl.importe AS FLOAT), 0)) AS importe
                    FROM hist_ventas_cabecera hvc
                    INNER JOIN hist_ventas_linea hvl
                        ON hvc.cod_venta = hvl.cod_venta
                       AND hvc.tipo_venta = hvl.tipo_venta
                       AND hvc.cod_empresa = hvl.cod_empresa
                       AND hvc.cod_caja = hvl.cod_caja
    LEFT JOIN articulos a
                        ON a.cod_articulo = hvl.cod_articulo
                    WHERE 1=1
                      " . ($whereCabecera !== '' ? " AND " . $whereCabecera : "") . "
                      " . ($whereLineas !== '' ? " AND " . $whereLineas : "") . "
                    GROUP BY
                        hvc.cod_venta,
                        hvc.fecha_venta,
                        hvc.cod_cliente,
                        hvc.cod_comisionista,
                        hvc.cod_vendedor,
                        hvc.cod_empresa,
                        hvc.cod_caja,
                        hvc.tipo_venta
                ) d
                LEFT JOIN integral.dbo.clientes c
                    ON c.cod_cliente = d.cod_cliente
                ORDER BY d.fecha_venta DESC, d.cod_venta DESC
            ";
        } elseif ($vista === 'albaranes_con_pedido' || $vista === 'albaranes_sin_pedido') {
            $esOficial = $vista === 'albaranes_con_pedido' ? 1 : 0;
            $base['titulo'] = $vista === 'albaranes_con_pedido'
                ? 'Albaranes con pedido'
                : 'Albaranes sin pedido';
            [$sqlDocsFiltrados, $params] = construirSqlDocsFiltrados($contexto, ['tipo_venta' => 2]);
            $params[] = (int)$esOficial;
            $sql = "
                WITH docs_filtrados AS (
                    " . $sqlDocsFiltrados . "
                )
                SELECT TOP 100
                    t.cod_venta,
                    t.fecha_venta,
                    t.cod_cliente,
                    t.nombre_cliente,
                    t.cod_comisionista,
                    t.cod_vendedor,
                    t.importe
                FROM (
                    SELECT
                        d.cod_venta,
                        d.fecha_venta,
                        d.cod_cliente,
                        c.nombre_comercial AS nombre_cliente,
                        d.cod_comisionista,
                        d.cod_vendedor,
                        d.importe_doc AS importe,
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM entrega_lineas_venta elv
                                WHERE elv.cod_venta_destino = d.cod_venta
                                  AND elv.tipo_venta_destino = d.tipo_venta
                                  AND elv.cod_empresa_destino = d.cod_empresa
                                  AND elv.cod_caja_destino = d.cod_caja
                            ) THEN 1
                            ELSE 0
                        END AS es_oficial
                    FROM docs_filtrados d
                    LEFT JOIN integral.dbo.clientes c
                        ON c.cod_cliente = d.cod_cliente
                ) t
                WHERE t.es_oficial = ?
                ORDER BY t.fecha_venta DESC, t.cod_venta DESC
            ";
        }

        if ($sql === '') {
            return $base;
        }

        $params = isset($params) && is_array($params) ? $params : [];
        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas($contextoError, $conn, $sql, array_merge(['vista' => $vista], $params));
            return $base;
        }

        $filas = [];
        while ($row = odbc_fetch_array_utf8($rs)) {
            $filas[] = [
                'cod_venta' => trim((string)($row['cod_venta'] ?? '')),
                'fecha_venta' => (string)($row['fecha_venta'] ?? ''),
                'cod_cliente' => trim((string)($row['cod_cliente'] ?? '')),
                'nombre_cliente' => trim((string)($row['nombre_cliente'] ?? '')),
                'cod_comisionista' => trim((string)($row['cod_comisionista'] ?? '')),
                'cod_vendedor' => trim((string)($row['cod_vendedor'] ?? '')),
                'importe' => (float)($row['importe'] ?? 0),
            ];
        }

        $base['filas'] = $filas;
        $base['debug_total'] = count($filas);
        return $base;
    }
}
