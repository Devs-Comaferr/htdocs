<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasHelper.php';

if (!function_exists('__estadisticas_impl_obtenerKpiPedidosPendientes')) {
    function __estadisticas_impl_obtenerKpiPedidosPendientes(array $dataset): array
    {
        $resultado = [
            'pedidos_pendientes' => 0,
        ];
        $pedidosPendientes = [];
        foreach ($dataset as $linea) {
            $cantidadPendiente = (float)($linea['cantidad_pendiente'] ?? 0);
            if ($cantidadPendiente <= 0) {
                continue;
            }

            $codVenta = trim((string)($linea['cod_venta'] ?? ''));
            if ($codVenta === '') {
                continue;
            }
            $pedidosPendientes[$codVenta] = true;
        }

        $resultado['pedidos_pendientes'] = count($pedidosPendientes);

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerKpiBacklogImporte')) {
    function __estadisticas_impl_obtenerKpiBacklogImporte(array $dataset): array
    {
        $totalBacklog = 0.0;

        foreach ($dataset as $fila) {
            $cantidadPendiente = (float)($fila['cantidad_pendiente'] ?? 0);
            $cantidadPedida = (float)($fila['cantidad_pedida'] ?? 0);
            if ($cantidadPendiente <= 0 || $cantidadPedida <= 0) {
                continue;
            }

            $importeLinea = (float)($fila['importe_linea'] ?? 0);
            $importePendiente = $importeLinea * ($cantidadPendiente / $cantidadPedida);
            $totalBacklog += $importePendiente;
        }

        return [
            'backlog_importe' => round($totalBacklog, 2),
        ];
    }
}


if (!function_exists('__estadisticas_impl_obtenerKpiLineasCriticas')) {
    function __estadisticas_impl_obtenerKpiLineasCriticas(array $dataset): array
    {
        $lineasCriticas = 0;

        foreach ($dataset as $fila) {
            $cantidadPendiente = (float)($fila['cantidad_pendiente'] ?? 0);
            $diasDesdePedido = (int)($fila['dias_desde_pedido'] ?? 0);
            if ($cantidadPendiente > 0 && $diasDesdePedido >= 5) {
                $lineasCriticas++;
            }
        }

        return [
            'lineas_criticas' => $lineasCriticas,
        ];
    }
}


if (!function_exists('__estadisticas_impl_obtenerKpiVelocidadServicio')) {
    function __estadisticas_impl_obtenerKpiVelocidadServicio(array $dataset): array
    {
        $resultado = [
            'lineas_servidas' => 0,
            'dias_media' => 0.0,
        ];

        $totalLineas = 0;
        $sumaDias = 0.0;
        foreach ($dataset as $linea) {
            $tieneEntrega = (int)($linea['tiene_entrega'] ?? 0);
            $diasPrimeraEntrega = $linea['dias_primera_entrega'] ?? null;
            if ($tieneEntrega === 1 && is_numeric($diasPrimeraEntrega)) {
                $totalLineas++;
                $sumaDias += (float)$diasPrimeraEntrega;
            }
        }

        $resultado['lineas_servidas'] = $totalLineas;
        $resultado['dias_media'] = $totalLineas > 0
            ? round($sumaDias / $totalLineas, 2)
            : 0.0;

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerKpiLineasPendientes')) {
    function __estadisticas_impl_obtenerKpiLineasPendientes(array $dataset): array
    {
        $resultado = [
            'lineas_pendientes' => 0,
            'dias_media_pendiente' => 0.0,
        ];
        $totalLineasPendientes = 0;
        $sumaDiasPendientes = 0.0;

        foreach ($dataset as $linea) {
            $cantidadPendiente = (float)($linea['cantidad_pendiente'] ?? 0);
            if ($cantidadPendiente > 0) {
                $totalLineasPendientes++;
                $sumaDiasPendientes += (float)($linea['dias_desde_pedido'] ?? 0);
            }
        }

        $resultado['lineas_pendientes'] = $totalLineasPendientes;
        $resultado['dias_media_pendiente'] = $totalLineasPendientes > 0
            ? round($sumaDiasPendientes / $totalLineasPendientes, 2)
            : 0.0;

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerDatasetServicioPedidos')) {
    function __estadisticas_impl_obtenerDatasetServicioPedidos($conn, array $contexto): array
    {
        if (!$conn) {
            return [];
        }

        [$fDesde, $fHastaMasUno] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = (int)($contexto['cod_comisionista_activo'] ?? 0);
        $params = [];
        $filtroComercialSql = '';
        if ($codComisionista > 0) {
            $filtroComercialSql = " AND vc.cod_comisionista = ?";
        }
        if ($codComisionista > 0) {
            $params[] = $codComisionista;
        }
        $params[] = $fDesde;
        $params[] = $fHastaMasUno;

        $sql = "
            WITH entregas_agrupadas AS (
                SELECT
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen,
                    SUM(ISNULL(elv.cantidad, 0)) AS cantidad_servida,
                    MIN(vc2.fecha_venta) AS fecha_primer_albaran,
                    MAX(vc2.fecha_venta) AS fecha_ultimo_albaran
                FROM integral.dbo.entrega_lineas_venta elv
                INNER JOIN integral.dbo.hist_ventas_cabecera vc2
                    ON vc2.cod_venta = elv.cod_venta_destino
                   AND vc2.tipo_venta = elv.tipo_venta_destino
                   AND vc2.cod_empresa = elv.cod_empresa_destino
                   AND vc2.cod_caja = elv.cod_caja_destino
                   AND vc2.tipo_venta = 2
                GROUP BY
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen
            )
            SELECT
                vc.cod_empresa,
                vc.cod_caja,
                vc.cod_venta,
                SUM(ISNULL(vl.importe, 0)) AS importe_total,
                SUM(vl.cantidad) AS cantidad_pedida,
                vc.fecha_venta AS fecha_pedido,
                MIN(ea.fecha_primer_albaran) AS fecha_primer_albaran,
                MAX(ea.fecha_ultimo_albaran) AS fecha_ultimo_albaran,
                SUM(ISNULL(ea.cantidad_servida, 0)) AS cantidad_servida,
                DATEDIFF(day, vc.fecha_venta, GETDATE()) AS dias_desde_pedido,
                CASE
                    WHEN MIN(ea.fecha_primer_albaran) IS NULL THEN 0
                    ELSE 1
                END AS tiene_entrega,
                CASE
                    WHEN MIN(ea.fecha_primer_albaran) IS NULL THEN NULL
                    ELSE DATEDIFF(day, vc.fecha_venta, MIN(ea.fecha_primer_albaran))
                END AS dias_primera_entrega
            FROM integral.dbo.hist_ventas_cabecera vc
            INNER JOIN integral.dbo.hist_ventas_linea vl
                ON vc.cod_venta = vl.cod_venta
               AND vc.tipo_venta = vl.tipo_venta
               AND vc.cod_empresa = vl.cod_empresa
               AND vc.cod_caja = vl.cod_caja
            LEFT JOIN entregas_agrupadas ea
                ON ea.cod_venta_origen = vl.cod_venta
               AND ea.cod_empresa_origen = vl.cod_empresa
               AND ea.cod_caja_origen = vl.cod_caja
               AND ea.linea_origen = vl.linea
            WHERE 1=1
              AND vc.tipo_venta = 1
              AND vl.tipo_venta = 1
              AND ISNULL(vc.cod_comisionista, 0) <> 0
              " . $filtroComercialSql . "
              AND ISNULL(vc.anulada, 'N') = 'N'
              " . construirRangoFechasSql('vc.fecha_venta') . "
              " . construirFiltroArticulosSql($contexto, $params) . "
            GROUP BY
                vc.cod_empresa,
                vc.cod_caja,
                vc.cod_venta,
                vc.fecha_venta
        ";

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerDatasetServicioPedidos.exec', $conn, $sql, $params);
            return [];
        }

        $filas = [];
        while ($row = odbc_fetch_array_utf8($rs)) {
            $cantidadPedida = (float)($row['cantidad_pedida'] ?? 0);
            $cantidadServida = (float)($row['cantidad_servida'] ?? 0);
            $cantidadPendiente = max(0.0, $cantidadPedida - $cantidadServida);
            $porcentajeServido = $cantidadPedida > 0
                ? round(($cantidadServida / $cantidadPedida) * 100, 2)
                : 0.0;
            $fechaPedido = trim((string)($row['fecha_pedido'] ?? ''));
            $diasDesdePedido = (int)($row['dias_desde_pedido'] ?? 0);
            if ($diasDesdePedido < 0) {
                $diasDesdePedido = 0;
            }
            if ($porcentajeServido < 0) {
                $porcentajeServido = 0.0;
            } elseif ($porcentajeServido > 100) {
                $porcentajeServido = 100.0;
            }

            $filas[] = [
                'cod_empresa' => trim((string)($row['cod_empresa'] ?? '')),
                'cod_caja' => trim((string)($row['cod_caja'] ?? '')),
                'cod_venta' => trim((string)($row['cod_venta'] ?? '')),
                'importe_pedido' => (float)($row['importe_total'] ?? 0),
                'cantidad_pedida' => $cantidadPedida,
                'cantidad_servida' => $cantidadServida,
                'cantidad_pendiente' => (float)$cantidadPendiente,
                'porcentaje_servido' => (float)$porcentajeServido,
                'fecha_pedido' => $fechaPedido,
                'dias_desde_pedido' => $diasDesdePedido,
                'fecha_primer_albaran' => (string)($row['fecha_primer_albaran'] ?? ''),
                'fecha_ultimo_albaran' => (string)($row['fecha_ultimo_albaran'] ?? ''),
                'tiene_entrega' => (int)($row['tiene_entrega'] ?? 0),
                'dias_primera_entrega' => isset($row['dias_primera_entrega']) && $row['dias_primera_entrega'] !== null
                    ? (float)$row['dias_primera_entrega']
                    : null,
            ];
        }

        return $filas;
    }
}


if (!function_exists('__estadisticas_impl_obtenerDatasetLineasPendientes')) {
    function __estadisticas_impl_obtenerDatasetLineasPendientes($conn, array $contexto): array
    {
        if (!$conn) {
            return [];
        }

        [$fDesde, $fHastaMasUno] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = (int)($contexto['cod_comisionista_activo'] ?? 0);
        $params = [];
        $filtroComercialSql = '';
        if ($codComisionista > 0) {
            $filtroComercialSql = " AND vc.cod_comisionista = ?";
            $params[] = $codComisionista;
        }
        $params[] = $fDesde;
        $params[] = $fHastaMasUno;

        $sql = "
            WITH entregas_agrupadas AS (
                SELECT
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen,
                    SUM(ISNULL(elv.cantidad, 0)) AS cantidad_servida
                FROM integral.dbo.entrega_lineas_venta elv
                INNER JOIN integral.dbo.hist_ventas_cabecera vc2
                    ON vc2.cod_venta = elv.cod_venta_destino
                   AND vc2.tipo_venta = elv.tipo_venta_destino
                   AND vc2.cod_empresa = elv.cod_empresa_destino
                   AND vc2.cod_caja = elv.cod_caja_destino
                   AND vc2.tipo_venta = 2
                GROUP BY
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen
            ),
            entregas_detalle AS (
                SELECT
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen,
                    vc2.fecha_venta AS fecha_albaran,
                    ROW_NUMBER() OVER (
                        PARTITION BY
                            elv.cod_empresa_origen,
                            elv.cod_caja_origen,
                            elv.cod_venta_origen,
                            elv.linea_origen
                        ORDER BY vc2.fecha_venta
                    ) AS orden_entrega,
                    CASE
                        WHEN ISNULL(vl_origen.cantidad, 0) = 0 THEN 0
                        ELSE ISNULL(vl_origen.importe, 0) * (ISNULL(elv.cantidad, 0) / vl_origen.cantidad)
                    END AS importe_entrega
                FROM integral.dbo.entrega_lineas_venta elv
                INNER JOIN integral.dbo.hist_ventas_cabecera vc2
                    ON vc2.cod_venta = elv.cod_venta_destino
                   AND vc2.tipo_venta = elv.tipo_venta_destino
                   AND vc2.cod_empresa = elv.cod_empresa_destino
                   AND vc2.cod_caja = elv.cod_caja_destino
                   AND vc2.tipo_venta = 2
                INNER JOIN integral.dbo.hist_ventas_linea vl_origen
                    ON vl_origen.cod_venta = elv.cod_venta_origen
                   AND vl_origen.tipo_venta = elv.tipo_venta_origen
                   AND vl_origen.cod_empresa = elv.cod_empresa_origen
                   AND vl_origen.cod_caja = elv.cod_caja_origen
                   AND vl_origen.linea = elv.linea_origen
                   AND vl_origen.tipo_venta = 1
            ),
            entregas_importe_agrupadas AS (
                SELECT
                    cod_empresa_origen,
                    cod_caja_origen,
                    cod_venta_origen,
                    linea_origen,
                    SUM(
                        CASE
                            WHEN orden_entrega = 1 THEN importe_entrega
                            ELSE 0
                        END
                    ) AS importe_primera_entrega,
                    SUM(
                        CASE
                            WHEN orden_entrega > 1 THEN importe_entrega
                            ELSE 0
                        END
                    ) AS importe_entregas_posteriores
                FROM entregas_detalle
                GROUP BY
                    cod_empresa_origen,
                    cod_caja_origen,
                    cod_venta_origen,
                    linea_origen
            )
            SELECT
                vc.fecha_venta AS fecha_pedido,
                vl.cantidad AS cantidad_pedida,
                ISNULL(ea.cantidad_servida, 0) AS cantidad_servida
            FROM integral.dbo.hist_ventas_cabecera vc
            INNER JOIN integral.dbo.hist_ventas_linea vl
                ON vc.cod_venta = vl.cod_venta
               AND vc.tipo_venta = vl.tipo_venta
               AND vc.cod_empresa = vl.cod_empresa
               AND vc.cod_caja = vl.cod_caja
            LEFT JOIN entregas_agrupadas ea
                ON ea.cod_venta_origen = vl.cod_venta
               AND ea.cod_empresa_origen = vl.cod_empresa
               AND ea.cod_caja_origen = vl.cod_caja
               AND ea.linea_origen = vl.linea
            WHERE
                vc.tipo_venta = 1
                AND vl.tipo_venta = 1
                AND ISNULL(vc.cod_comisionista, 0) <> 0
                " . $filtroComercialSql . "
                AND ISNULL(vc.anulada, 'N') = 'N'
                AND vl.cantidad > ISNULL(ea.cantidad_servida, 0)
                " . construirRangoFechasSql('vc.fecha_venta') . "
                " . construirFiltroArticulosSql($contexto, $params) . "
        ";

        $debugSql = estadisticasInterpolarSql($sql, $params);
        estadisticasDebugLog('dataset_lineas_pendientes_sql', [
            'sql' => $debugSql,
            'params' => $params,
            'cod_comisionista_activo' => $contexto['cod_comisionista_activo'] ?? null,
            'filtro_marca' => $contexto['filtro_marca'] ?? null,
            'filtro_familia' => $contexto['filtro_familia'] ?? null,
            'filtro_subfamilia' => $contexto['filtro_subfamilia'] ?? null,
            'filtro_articulo' => $contexto['filtro_articulo'] ?? null
        ]);

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerDatasetLineasPendientes.exec', $conn, $sql, $params);
            return [];
        }

        $filas = [];
        while ($row = odbc_fetch_array_utf8($rs)) {
            $filas[] = [
                'cantidad_pedida' => (float)($row['cantidad_pedida'] ?? 0),
                'cantidad_servida' => (float)($row['cantidad_servida'] ?? 0),
                'fecha_pedido' => trim((string)($row['fecha_pedido'] ?? '')),
            ];
        }

        estadisticasDebugLog('dataset_lineas_pendientes_count', [
            'filas' => count($filas),
            'cod_comisionista_activo' => $contexto['cod_comisionista_activo'] ?? null
        ]);

        return $filas;
    }
}


if (!function_exists('__estadisticas_impl_obtenerDatasetServicioLineas')) {
    function __estadisticas_impl_obtenerDatasetServicioLineas($conn, array $contexto): array
    {
        if (!$conn) {
            return [];
        }

        [$fDesde, $fHastaMasUno] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = (int)($contexto['cod_comisionista_activo'] ?? 0);
        $params = [];
        $filtroComercialSql = '';
        if ($codComisionista > 0) {
            $filtroComercialSql = " AND vc.cod_comisionista = ?";
            $params[] = $codComisionista;
        }
        $params[] = $fDesde;
        $params[] = $fHastaMasUno;

        $sql = "
            WITH entregas_agrupadas AS (
                SELECT
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen,
                    SUM(ISNULL(elv.cantidad,0)) AS cantidad_servida,
                    MIN(vc2.fecha_venta) AS fecha_primera_entrega,
                    MAX(vc2.fecha_venta) AS fecha_ultima_entrega
                FROM integral.dbo.entrega_lineas_venta elv
                INNER JOIN integral.dbo.hist_ventas_cabecera vc2
                    ON vc2.cod_venta = elv.cod_venta_destino
                   AND vc2.tipo_venta = elv.tipo_venta_destino
                   AND vc2.cod_empresa = elv.cod_empresa_destino
                   AND vc2.cod_caja = elv.cod_caja_destino
                   AND vc2.tipo_venta = 2
                GROUP BY
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen
            ),
            entregas_detalle AS (
                SELECT
                    elv.cod_empresa_origen,
                    elv.cod_caja_origen,
                    elv.cod_venta_origen,
                    elv.linea_origen,
                    vc2.fecha_venta AS fecha_albaran,
                    elv.cantidad,
                    ROW_NUMBER() OVER (
                        PARTITION BY
                            elv.cod_empresa_origen,
                            elv.cod_caja_origen,
                            elv.cod_venta_origen,
                            elv.linea_origen
                        ORDER BY vc2.fecha_venta
                    ) AS orden_entrega
                FROM integral.dbo.entrega_lineas_venta elv
                INNER JOIN integral.dbo.hist_ventas_cabecera vc2
                    ON vc2.cod_venta = elv.cod_venta_destino
                   AND vc2.tipo_venta = elv.tipo_venta_destino
                   AND vc2.cod_empresa = elv.cod_empresa_destino
                   AND vc2.cod_caja = elv.cod_caja_destino
                   AND vc2.tipo_venta = 2
            ),
            entregas_importe_agrupadas AS (
                SELECT
                    ed.cod_empresa_origen,
                    ed.cod_caja_origen,
                    ed.cod_venta_origen,
                    ed.linea_origen,
                    SUM(
                        CASE
                            WHEN ed.orden_entrega = 1
                            THEN (vl.importe * (ed.cantidad / NULLIF(vl.cantidad,0)))
                            ELSE 0
                        END
                    ) AS importe_primera_entrega,
                    SUM(
                        CASE
                            WHEN ed.orden_entrega > 1
                            THEN (vl.importe * (ed.cantidad / NULLIF(vl.cantidad,0)))
                            ELSE 0
                        END
                    ) AS importe_entregas_posteriores
                FROM entregas_detalle ed
                INNER JOIN integral.dbo.hist_ventas_linea vl
                    ON vl.cod_empresa = ed.cod_empresa_origen
                   AND vl.cod_caja = ed.cod_caja_origen
                   AND vl.cod_venta = ed.cod_venta_origen
                   AND vl.linea = ed.linea_origen
                   AND vl.tipo_venta = 1
                GROUP BY
                    ed.cod_empresa_origen,
                    ed.cod_caja_origen,
                    ed.cod_venta_origen,
                    ed.linea_origen
            )
            SELECT
                vc.fecha_venta AS fecha_pedido,
                vl.cantidad AS cantidad_pedida,
                ISNULL(ea.cantidad_servida,0) AS cantidad_servida,
                vc.cod_empresa,
                vc.cod_caja,
                vc.cod_venta,
                vl.linea,
                vc.cod_cliente,
                vc.cod_seccion AS cod_seccion,
                vl.cod_articulo,
                vl.importe AS importe_linea,
                ISNULL(eia.importe_primera_entrega,0) AS importe_primera_entrega,
                ISNULL(eia.importe_entregas_posteriores,0) AS importe_entregas_posteriores,
                ea.fecha_primera_entrega,
                ea.fecha_ultima_entrega,
                DATEDIFF(day, vc.fecha_venta, ea.fecha_primera_entrega) AS dias_primera_entrega,
                DATEDIFF(day, vc.fecha_venta, ea.fecha_ultima_entrega) AS dias_ultima_entrega,
                ISNULL(ea.cantidad_servida,0) AS cantidad_servida_oficial,
                CASE
                    WHEN vl.cantidad - ISNULL(ea.cantidad_servida,0) > 0
                    THEN vl.cantidad - ISNULL(ea.cantidad_servida,0)
                    ELSE 0
                END AS cantidad_pendiente,
                CASE
                    WHEN ISNULL(ea.cantidad_servida,0) > 0
                    THEN 1
                    ELSE 0
                END AS tiene_entrega,
                CASE
                    WHEN vl.cantidad > ISNULL(ea.cantidad_servida,0)
                    THEN 1
                    ELSE 0
                END AS pendiente,
                DATEDIFF(
                    day,
                    vc.fecha_venta,
                    GETDATE()
                ) AS dias_desde_pedido
            FROM integral.dbo.hist_ventas_cabecera vc
            INNER JOIN integral.dbo.hist_ventas_linea vl
                ON vc.cod_venta = vl.cod_venta
               AND vc.tipo_venta = vl.tipo_venta
               AND vc.cod_empresa = vl.cod_empresa
               AND vc.cod_caja = vl.cod_caja
            LEFT JOIN entregas_agrupadas ea
                ON ea.cod_venta_origen = vl.cod_venta
               AND ea.cod_empresa_origen = vl.cod_empresa
               AND ea.cod_caja_origen = vl.cod_caja
               AND ea.linea_origen = vl.linea
            LEFT JOIN entregas_importe_agrupadas eia
                ON eia.cod_venta_origen = vl.cod_venta
               AND eia.cod_empresa_origen = vl.cod_empresa
               AND eia.cod_caja_origen = vl.cod_caja
               AND eia.linea_origen = vl.linea
            WHERE
                vc.tipo_venta = 1
                AND vl.tipo_venta = 1
                AND vl.importe > 0
                AND ISNULL(vc.cod_comisionista,0) <> 0
                " . $filtroComercialSql . "
                AND ISNULL(vc.anulada,'N') = 'N'
                " . construirRangoFechasSql('vc.fecha_venta') . "
                " . construirFiltroArticulosSql($contexto, $params) . "
        ";

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerDatasetServicioLineas.exec', $conn, $sql, $params);
            return [];
        }

        $rows = [];
        while ($row = odbc_fetch_array_utf8($rs)) {
            $rows[] = [
                'fecha_pedido' => trim((string)($row['fecha_pedido'] ?? '')),
                'cantidad_pedida' => (float)($row['cantidad_pedida'] ?? 0),
                'cantidad_servida' => (float)($row['cantidad_servida'] ?? 0),
                'cod_empresa' => trim((string)($row['cod_empresa'] ?? '')),
                'cod_caja' => trim((string)($row['cod_caja'] ?? '')),
                'cod_venta' => trim((string)($row['cod_venta'] ?? '')),
                'linea' => trim((string)($row['linea'] ?? '')),
                'cod_cliente' => trim((string)($row['cod_cliente'] ?? '')),
                'cod_seccion' => $row['cod_seccion'] === null ? null : (int)$row['cod_seccion'],
                'cod_articulo' => trim((string)($row['cod_articulo'] ?? '')),
                'importe_linea' => (float)($row['importe_linea'] ?? 0),
                'importe_primera_entrega' => (float)($row['importe_primera_entrega'] ?? 0),
                'importe_entregas_posteriores' => (float)($row['importe_entregas_posteriores'] ?? 0),
                'fecha_primera_entrega' => trim((string)($row['fecha_primera_entrega'] ?? '')),
                'fecha_ultima_entrega' => trim((string)($row['fecha_ultima_entrega'] ?? '')),
                'dias_primera_entrega' => $row['dias_primera_entrega'] === null ? null : (int)$row['dias_primera_entrega'],
                'dias_ultima_entrega' => $row['dias_ultima_entrega'] === null ? null : (int)$row['dias_ultima_entrega'],
                'cantidad_servida_oficial' => (float)($row['cantidad_servida_oficial'] ?? 0),
                'cantidad_pendiente' => (float)($row['cantidad_pendiente'] ?? 0),
                'tiene_entrega' => (int)($row['tiene_entrega'] ?? 0),
                'pendiente' => (int)($row['pendiente'] ?? 0),
                'dias_desde_pedido' => (int)($row['dias_desde_pedido'] ?? 0),
            ];
        }

        error_log('[ESTADISTICAS] dataset_servicio_master_count ' . json_encode([
            'filas' => count($rows),
            'cod_comisionista_activo' => $contexto['cod_comisionista_activo'] ?? null
        ]));

        return $rows;
    }
}


if (!function_exists('__estadisticas_impl_obtenerResumenDocumentosSeparados')) {
    function __estadisticas_impl_obtenerResumenDocumentosSeparados($conn, array $contexto): array
    {
        $resultado = [
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
        if (!$conn) {
            return $resultado;
        }

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = ((string)($contexto['tipo_filtro_comercial'] ?? 'todos') === 'cod_comisionista')
            ? trim((string)($contexto['valor_filtro_comercial'] ?? ''))
            : '';
        [$whereCabecera, $params] = buildWhereCabecera('hvc', [
            'excluir_anuladas' => true,
            'excluir_comisionista_cero' => true,
            'fecha_desde' => $fDesde,
            'fecha_hasta' => $fHasta,
            'cod_comisionista' => $codComisionista,
        ]);
        [$whereLineas, $paramsLineas] = buildWhereLineasDocumentales($contexto, 'a', 'hvl', 'hvc');
        $params = array_merge($params, $paramsLineas);

        $sql = "
            WITH docs_filtrados AS (
                SELECT
                    hvc.cod_venta,
                    hvc.tipo_venta,
                    hvc.cod_empresa,
                    hvc.cod_caja,
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
                  AND hvc.tipo_venta IN (1,2)
                  " . ($whereLineas !== '' ? " AND " . $whereLineas : "") . "
                GROUP BY
                    hvc.cod_venta,
                    hvc.tipo_venta,
                    hvc.cod_empresa,
                    hvc.cod_caja
            )
            SELECT
                SUM(CASE WHEN d.tipo_venta = 1 AND d.importe > 0 THEN 1 ELSE 0 END) AS pedidos_ventas_num,
                SUM(CASE WHEN d.tipo_venta = 1 AND d.importe > 0 THEN d.importe ELSE 0 END) AS pedidos_ventas_importe,
                SUM(CASE WHEN d.tipo_venta = 1 AND d.importe < 0 THEN 1 ELSE 0 END) AS pedidos_abono_num,
                SUM(CASE WHEN d.tipo_venta = 1 AND d.importe < 0 THEN d.importe ELSE 0 END) AS pedidos_abono_importe,
                SUM(CASE WHEN d.tipo_venta = 2 AND d.importe > 0 THEN 1 ELSE 0 END) AS albaranes_ventas_num,
                SUM(CASE WHEN d.tipo_venta = 2 AND d.importe > 0 THEN d.importe ELSE 0 END) AS albaranes_ventas_importe,
                SUM(CASE WHEN d.tipo_venta = 2 AND d.importe < 0 THEN 1 ELSE 0 END) AS albaranes_abono_num,
                SUM(CASE WHEN d.tipo_venta = 2 AND d.importe < 0 THEN d.importe ELSE 0 END) AS albaranes_abono_importe
            FROM docs_filtrados d
        ";

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerResumenDocumentosSeparados.exec', $conn, $sql, $params);
            return $resultado;
        }

        $row = odbc_fetch_array_utf8($rs);
        if (!$row) {
            return $resultado;
        }

        $resultado['pedidos_ventas_num'] = (int)($row['pedidos_ventas_num'] ?? 0);
        $resultado['pedidos_ventas_importe'] = (float)($row['pedidos_ventas_importe'] ?? 0);
        $resultado['pedidos_abono_num'] = (int)($row['pedidos_abono_num'] ?? 0);
        $resultado['pedidos_abono_importe'] = (float)($row['pedidos_abono_importe'] ?? 0);
        $resultado['albaranes_ventas_num'] = (int)($row['albaranes_ventas_num'] ?? 0);
        $resultado['albaranes_ventas_importe'] = (float)($row['albaranes_ventas_importe'] ?? 0);
        $resultado['albaranes_abono_num'] = (int)($row['albaranes_abono_num'] ?? 0);
        $resultado['albaranes_abono_importe'] = (float)($row['albaranes_abono_importe'] ?? 0);

        $resultado['porcentaje_devolucion_importe'] = $resultado['albaranes_ventas_importe'] > 0
            ? abs($resultado['albaranes_abono_importe']) / $resultado['albaranes_ventas_importe']
            : 0.0;

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_construirSqlDocsBase')) {
    function __estadisticas_impl_construirSqlDocsBase(array $contexto, array $opts = []): array
    {
        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = ((string)($contexto['tipo_filtro_comercial'] ?? 'todos') === 'cod_comisionista')
            ? trim((string)($contexto['valor_filtro_comercial'] ?? ''))
            : '';

        $filtrosCabecera = [
            'excluir_anuladas' => true,
            'excluir_comisionista_cero' => true,
            'fecha_desde' => $fDesde,
            'fecha_hasta' => $fHasta,
            'cod_comisionista' => $codComisionista,
        ];
        if (isset($opts['tipo_venta'])) {
            $filtrosCabecera['tipo_venta'] = (int)$opts['tipo_venta'];
        }

        [$whereCabecera, $params] = buildWhereCabecera('hvc', $filtrosCabecera);

        [$whereLineas, $paramsLineas] = buildWhereLineasDocumentales($contexto, 'a', 'hvl_f', 'hvc');
        $params = array_merge($params, $paramsLineas);
        $whereLineasSql = '';
        if ($whereLineas !== '') {
            $whereLineasSql = "
              AND EXISTS (
                    SELECT 1
                    FROM hist_ventas_linea hvl_f
    LEFT JOIN articulos a
                        ON a.cod_articulo = hvl_f.cod_articulo
                    WHERE hvl_f.cod_empresa = hvc.cod_empresa
                      AND hvl_f.cod_caja = hvc.cod_caja
                      AND hvl_f.tipo_venta = hvc.tipo_venta
                      AND hvl_f.cod_venta = hvc.cod_venta
                      AND " . $whereLineas . "
                )
            ";
        }

        $sql = "
            SELECT
                hvc.cod_empresa,
                hvc.cod_caja,
                hvc.tipo_venta,
                hvc.cod_venta,
                hvc.fecha_venta,
                hvc.cod_cliente,
                hvc.cod_comisionista,
                hvc.historico,
                ISNULL(imp.importe_doc, 0) AS importe_doc,
                CASE WHEN hvc.tipo_venta = 1 THEN 1 ELSE 0 END AS es_pedido,
                CASE WHEN hvc.tipo_venta = 2 THEN 1 ELSE 0 END AS es_albaran,
                CASE
                    WHEN hvc.tipo_venta = 2 AND ISNULL(imp.importe_doc, 0) < 0 THEN 1
                    ELSE 0
                END AS es_abono,
                CASE
                    WHEN hvc.tipo_venta = 2 AND ISNULL(imp.importe_doc, 0) >= 0 THEN 1
                    ELSE 0
                END AS es_venta,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM entrega_lineas_venta elv
                        WHERE elv.cod_empresa_destino = hvc.cod_empresa
                          AND elv.cod_caja_destino = hvc.cod_caja
                          AND elv.tipo_venta_destino = hvc.tipo_venta
                          AND elv.cod_venta_destino = hvc.cod_venta
                          AND elv.tipo_venta_origen = 1
                    ) THEN 1
                    ELSE 0
                END AS tiene_pedido_origen
            FROM hist_ventas_cabecera hvc
            OUTER APPLY (
                SELECT SUM(ISNULL(TRY_CAST(hvl.importe AS FLOAT), 0)) AS importe_doc
                FROM hist_ventas_linea hvl
                WHERE hvl.cod_empresa = hvc.cod_empresa
                  AND hvl.cod_caja = hvc.cod_caja
                  AND hvl.tipo_venta = hvc.tipo_venta
                  AND hvl.cod_venta = hvc.cod_venta
            ) imp
            WHERE 1=1
              " . ($whereCabecera !== '' ? " AND " . $whereCabecera : "") . "
              " . $whereLineasSql . "
        ";

        return [$sql, $params];
    }
}


if (!function_exists('__estadisticas_impl_construirSqlDocsFiltrados')) {
    function __estadisticas_impl_construirSqlDocsFiltrados(array $contexto, array $opts = []): array
    {
        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = ((string)($contexto['tipo_filtro_comercial'] ?? 'todos') === 'cod_comisionista')
            ? trim((string)($contexto['valor_filtro_comercial'] ?? ''))
            : '';

        $filtrosCabecera = [
            'excluir_anuladas' => true,
            'excluir_comisionista_cero' => true,
            'fecha_desde' => $fDesde,
            'fecha_hasta' => $fHasta,
            'cod_comisionista' => $codComisionista,
        ];

        if (isset($opts['tipo_venta'])) {
            $filtrosCabecera['tipo_venta'] = (int)$opts['tipo_venta'];
        }

        [$whereCabecera, $paramsCabecera] = buildWhereCabecera('hvc', $filtrosCabecera);
        [$whereLineas, $paramsLineas] = buildWhereLineasDocumentales($contexto, 'a', 'hvl', 'hvc');
        $params = array_merge($paramsCabecera, $paramsLineas);

        $sql = "
            SELECT
                hvc.cod_empresa,
                hvc.cod_caja,
                hvc.tipo_venta,
                hvc.cod_venta,
                MAX(hvc.cod_cliente) AS cod_cliente,
                MAX(hvc.fecha_venta) AS fecha_venta,
                MAX(hvc.cod_comisionista) AS cod_comisionista,
                MAX(hvc.cod_vendedor) AS cod_vendedor,
                MAX(hvc.importe) AS importe_cabecera,
                SUM(ISNULL(TRY_CAST(hvl.importe AS FLOAT), 0)) AS importe_doc
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
                hvc.cod_empresa,
                hvc.cod_caja,
                hvc.tipo_venta,
                hvc.cod_venta
        ";

        return [$sql, $params];
    }
}


if (!function_exists('__estadisticas_impl_obtenerResumenAlbaranesVentasConYSinPedido')) {
    function __estadisticas_impl_obtenerResumenAlbaranesVentasConYSinPedido($conn, array $contexto): array
    {
        $resultado = [
            'con_pedido_num' => 0,
            'con_pedido_importe' => 0.0,
            'sin_pedido_num' => 0,
            'sin_pedido_importe' => 0.0,
        ];
        if (!$conn) {
            return $resultado;
        }

        [$sqlDocsBase, $params] = construirSqlDocsBase($contexto, ['tipo_venta' => 2]);

        $sql = "
            WITH docs_base AS (
                " . $sqlDocsBase . "
            )
            SELECT
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 1 THEN 1 ELSE 0 END),0) AS con_pedido_num,
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 1 THEN d.importe_doc ELSE 0 END),0) AS con_pedido_importe,
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 0 THEN 1 ELSE 0 END),0) AS sin_pedido_num,
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 0 THEN d.importe_doc ELSE 0 END),0) AS sin_pedido_importe
            FROM docs_base d
            WHERE d.es_venta = 1
        ";
        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerResumenAlbaranesVentasConYSinPedido.exec', $conn, $sql, $params);
            return $resultado;
        }

        $row = odbc_fetch_array_utf8($rs);
        if (!$row) {
            return $resultado;
        }

        $resultado['con_pedido_num'] = (int)($row['con_pedido_num'] ?? 0);
        $resultado['con_pedido_importe'] = (float)($row['con_pedido_importe'] ?? 0);
        $resultado['sin_pedido_num'] = (int)($row['sin_pedido_num'] ?? 0);
        $resultado['sin_pedido_importe'] = (float)($row['sin_pedido_importe'] ?? 0);

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerResumenAlbaranesAbonoConYSinPedido')) {
    function __estadisticas_impl_obtenerResumenAlbaranesAbonoConYSinPedido($conn, array $contexto): array
    {
        $resultado = [
            'con_pedido_num' => 0,
            'con_pedido_importe' => 0.0,
            'sin_pedido_num' => 0,
            'sin_pedido_importe' => 0.0,
        ];
        if (!$conn) {
            return $resultado;
        }

        [$sqlDocsBase, $params] = construirSqlDocsBase($contexto, ['tipo_venta' => 2]);

        $sql = "
            WITH docs_base AS (
                " . $sqlDocsBase . "
            )
            SELECT
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 1 THEN 1 ELSE 0 END),0) AS con_pedido_num,
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 1 THEN d.importe_doc ELSE 0 END),0) AS con_pedido_importe,
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 0 THEN 1 ELSE 0 END),0) AS sin_pedido_num,
                ISNULL(SUM(CASE WHEN d.tiene_pedido_origen = 0 THEN d.importe_doc ELSE 0 END),0) AS sin_pedido_importe
            FROM docs_base d
            WHERE d.es_abono = 1
        ";
        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerResumenAlbaranesAbonoConYSinPedido.exec', $conn, $sql, $params);
            return $resultado;
        }

        $row = odbc_fetch_array_utf8($rs);
        if (!$row) {
            return $resultado;
        }

        $resultado['con_pedido_num'] = (int)($row['con_pedido_num'] ?? 0);
        $resultado['con_pedido_importe'] = (float)($row['con_pedido_importe'] ?? 0);
        $resultado['sin_pedido_num'] = (int)($row['sin_pedido_num'] ?? 0);
        $resultado['sin_pedido_importe'] = (float)($row['sin_pedido_importe'] ?? 0);

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerCheckCabeceraVsLineasAB')) {
    function __estadisticas_impl_obtenerCheckCabeceraVsLineasAB($conn, array $contexto, array $opts = []): array
    {
        $resultado = [
            'line_fields_disponibles' => [],
            'total_cabecera' => 0.0,
            'modelos_1' => [],
        ];
        if (!$conn) {
            return $resultado;
        }

        $topDocs = (int)($opts['top_docs'] ?? 10);
        if ($topDocs <= 0) {
            $topDocs = 10;
        }
        if ($topDocs > 50) {
            $topDocs = 50;
        }

        $sqlCols = "
            SELECT LOWER(COLUMN_NAME) AS nombre
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE LOWER(TABLE_NAME) = 'hist_ventas_linea'
        ";
        $rsCols = @estadisticasOdbcExec($conn, $sqlCols);
        $cols = [];
        if ($rsCols) {
            while ($rowCol = odbc_fetch_array_utf8($rsCols)) {
                $nombre = trim((string)($rowCol['nombre'] ?? ''));
                if ($nombre !== '') {
                    $cols[$nombre] = true;
                }
            }
        }

        $candidatas = ['importe', 'importe_neto', 'total'];
        $lineFields = [];
        foreach ($candidatas as $campo) {
            if (isset($cols[$campo])) {
                $lineFields[] = $campo;
            }
        }
        if (empty($lineFields)) {
            $lineFields[] = 'importe';
        }
        $resultado['line_fields_disponibles'] = $lineFields;

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        // ORDEN PARAMS: desde, hasta, comercial
        $paramsBase = [];
        $paramsBase[] = $fDesde;
        $paramsBase[] = $fHastaMasUno;
        $whereCabecera = "
            hvc.tipo_venta = 1
            AND ISNULL(hvc.importe, 0) >= 0
            AND ISNULL(hvc.anulada, 'N') <> 'S'
            AND ISNULL(hvc.cod_comisionista, 0) <> 0
            " . construirRangoFechasSql('hvc.fecha_venta') . "
        ";
        [$sqlComercial, $paramsComercial] = construirCondicionComercialParams('hvc', $contexto);
        $whereCabecera .= $sqlComercial;
        $paramsBase = array_merge($paramsBase, $paramsComercial);

        $sqlCabecera = "
            SELECT SUM(ISNULL(hvc.importe, 0)) AS total_cabecera
            FROM hist_ventas_cabecera hvc
            WHERE " . $whereCabecera . "
        ";
        $rsCabecera = estadisticasOdbcExec($conn, $sqlCabecera, $paramsBase);
        if ($rsCabecera) {
            $rowCabecera = odbc_fetch_array_utf8($rsCabecera);
            $resultado['total_cabecera'] = (float)($rowCabecera['total_cabecera'] ?? 0);
        } else {
            registrarErrorSqlEstadisticas('obtenerCheckCabeceraVsLineasAB.total_cabecera', $conn, $sqlCabecera);
        }

        foreach ($lineFields as $campoLinea) {
            $sqlModelo1 = "
                SELECT
                    SUM(ISNULL(TRY_CAST(hvl." . $campoLinea . " AS FLOAT), 0)) AS total_lineas
                FROM hist_ventas_linea hvl
                INNER JOIN hist_ventas_cabecera hvc
                    ON hvc.cod_empresa = hvl.cod_empresa
                   AND hvc.tipo_venta = hvl.tipo_venta
                   AND hvc.cod_venta = hvl.cod_venta
                WHERE " . $whereCabecera . "
            ";
            $totalModelo1 = 0.0;
            $rsModelo1 = estadisticasOdbcExec($conn, $sqlModelo1, $paramsBase);
            if ($rsModelo1) {
                $rowModelo1 = odbc_fetch_array_utf8($rsModelo1);
                $totalModelo1 = (float)($rowModelo1['total_lineas'] ?? 0);
            } else {
                registrarErrorSqlEstadisticas('obtenerCheckCabeceraVsLineasAB.modelo1.total', $conn, $sqlModelo1, ['campo' => $campoLinea]);
            }

            $deltaModelo1 = (float)$resultado['total_cabecera'] - $totalModelo1;
            $itemModelo1 = [
                'campo' => $campoLinea,
                'total_lineas' => $totalModelo1,
                'delta' => $deltaModelo1,
                'top_docs' => [],
            ];

            if (abs($deltaModelo1) > 0.0001) {
                $sqlTopModelo1 = "
                    SELECT TOP " . $topDocs . "
                        hvc.cod_venta,
                        hvc.fecha_venta,
                        hvc.cod_cliente,
                        c.nombre_comercial AS nombre_cliente,
                        ISNULL(hvc.importe, 0) AS importe_cabecera,
                        SUM(ISNULL(TRY_CAST(hvl." . $campoLinea . " AS FLOAT), 0)) AS sum_lineas,
                        ISNULL(hvc.importe, 0) - SUM(ISNULL(TRY_CAST(hvl." . $campoLinea . " AS FLOAT), 0)) AS diferencia
                    FROM hist_ventas_cabecera hvc
                    LEFT JOIN hist_ventas_linea hvl
                        ON hvc.cod_empresa = hvl.cod_empresa
                       AND hvc.tipo_venta = hvl.tipo_venta
                       AND hvc.cod_venta = hvl.cod_venta
                    LEFT JOIN integral.dbo.clientes c
                        ON c.cod_cliente = hvc.cod_cliente
                    WHERE " . $whereCabecera . "
                    GROUP BY
                        hvc.cod_empresa,
                        hvc.tipo_venta,
                        hvc.cod_venta,
                        hvc.fecha_venta,
                        hvc.cod_cliente,
                        c.nombre_comercial,
                        hvc.importe
                    HAVING ABS(ISNULL(hvc.importe, 0) - SUM(ISNULL(TRY_CAST(hvl." . $campoLinea . " AS FLOAT), 0))) > 0.01
                    ORDER BY ABS(ISNULL(hvc.importe, 0) - SUM(ISNULL(TRY_CAST(hvl." . $campoLinea . " AS FLOAT), 0))) DESC
                ";
                $rsTopModelo1 = estadisticasOdbcExec($conn, $sqlTopModelo1, $paramsBase);
                if ($rsTopModelo1) {
                    while ($rowTop = odbc_fetch_array_utf8($rsTopModelo1)) {
                        $itemModelo1['top_docs'][] = [
                            'cod_venta' => trim((string)($rowTop['cod_venta'] ?? '')),
                            'fecha_venta' => (string)($rowTop['fecha_venta'] ?? ''),
                            'cod_cliente' => trim((string)($rowTop['cod_cliente'] ?? '')),
                            'nombre_cliente' => trim((string)($rowTop['nombre_cliente'] ?? '')),
                            'importe_cabecera' => (float)($rowTop['importe_cabecera'] ?? 0),
                            'sum_lineas' => (float)($rowTop['sum_lineas'] ?? 0),
                            'diferencia' => (float)($rowTop['diferencia'] ?? 0),
                        ];
                    }
                } else {
                    registrarErrorSqlEstadisticas('obtenerCheckCabeceraVsLineasAB.modelo1.top', $conn, $sqlTopModelo1, ['campo' => $campoLinea]);
                }
            }
            $resultado['modelos_1'][] = $itemModelo1;
        }

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerForenseDocumentoPedidoDebug')) {
    function __estadisticas_impl_obtenerForenseDocumentoPedidoDebug($conn, array $contexto, string $codVenta): array
    {
        $resultado = [
            'doc_input' => trim($codVenta),
            'line_fields_disponibles' => [],
            'cabeceras' => [],
            'lineas_modelo_1' => [],
            'conteos' => [
                'cabeceras' => 0,
                'modelo_1_filas' => 0,
            ],
            'sumas' => [
                'cabecera_importe_total' => 0.0,
                'modelo_1' => [],
            ],
        ];
        if (!$conn) {
            return $resultado;
        }

        $codVenta = trim($codVenta);
        if ($codVenta === '') {
            return $resultado;
        }

        $toLowerRow = static function (array $row): array {
            $out = [];
            foreach ($row as $k => $v) {
                $out[strtolower((string)$k)] = $v;
            }
            return $out;
        };

        $sqlCols = "
            SELECT LOWER(COLUMN_NAME) AS nombre
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE LOWER(TABLE_NAME) = 'hist_ventas_linea'
        ";
        $rsCols = @estadisticasOdbcExec($conn, $sqlCols);
        $cols = [];
        if ($rsCols) {
            while ($rowCol = odbc_fetch_array_utf8($rsCols)) {
                $nombre = strtolower(trim((string)($rowCol['nombre'] ?? '')));
                if ($nombre !== '') {
                    $cols[$nombre] = true;
                }
            }
        }

        $lineFields = [];
        foreach (['importe', 'importe_neto', 'total', 'total_linea', 'importe_linea'] as $campo) {
            if (isset($cols[$campo])) {
                $lineFields[] = $campo;
            }
        }
        if (empty($lineFields)) {
            $lineFields[] = 'importe';
        }
        $resultado['line_fields_disponibles'] = $lineFields;

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contexto);
        // ORDEN PARAMS: desde, hasta, comercial
        $paramsBase = [];
        $paramsBase[] = $fDesde;
        $paramsBase[] = $fHastaMasUno;
        $paramsBase[] = $codVenta;
        $whereCabecera = "
            hvc.tipo_venta = 1
            AND ISNULL(hvc.importe, 0) >= 0
            AND ISNULL(hvc.anulada, 'N') <> 'S'
            AND ISNULL(hvc.cod_comisionista, 0) <> 0
            " . construirRangoFechasSql('hvc.fecha_venta') . "
            AND CAST(hvc.cod_venta AS VARCHAR(50)) = ?
        ";
        [$sqlComercial, $paramsComercial] = construirCondicionComercialParams('hvc', $contexto);
        $whereCabecera .= $sqlComercial;
        $paramsBase = array_merge($paramsBase, $paramsComercial);

        $sqlCabeceras = "
            SELECT
                hvc.*,
                ISNULL(hvc.importe, 0) AS __importe_cast
            FROM hist_ventas_cabecera hvc
            WHERE " . $whereCabecera . "
            ORDER BY hvc.fecha_venta DESC, hvc.cod_empresa, hvc.cod_caja
        ";
        $rsCabeceras = estadisticasOdbcExec($conn, $sqlCabeceras, $paramsBase);
        if (!$rsCabeceras) {
            registrarErrorSqlEstadisticas('obtenerForenseDocumentoPedidoDebug.cabeceras', $conn, $sqlCabeceras, $paramsBase);
            return $resultado;
        }

        $cabeceras = [];
        $cabeceraTotal = 0.0;
        while ($row = odbc_fetch_array_utf8($rsCabeceras)) {
            $rowLow = $toLowerRow($row);
            $cabeceras[] = $rowLow;
            $cabeceraTotal += (float)($rowLow['__importe_cast'] ?? 0);
        }
        $resultado['cabeceras'] = $cabeceras;
        $resultado['conteos']['cabeceras'] = count($cabeceras);
        $resultado['sumas']['cabecera_importe_total'] = $cabeceraTotal;

        if (empty($cabeceras)) {
            return $resultado;
        }

        $selectLineFields = [];
        foreach ($lineFields as $campo) {
            $alias = 'line_' . $campo;
            $selectLineFields[] = "ISNULL(TRY_CAST(hvl." . $campo . " AS FLOAT), 0) AS " . $alias;
        }
        $selectLineFieldsSql = implode(",\n                    ", $selectLineFields);

        $sqlLineasM1 = "
            SELECT
                hvc.cod_empresa AS hvc_cod_empresa,
                hvc.tipo_venta AS hvc_tipo_venta,
                hvc.cod_venta AS hvc_cod_venta,
                hvc.cod_caja AS hvc_cod_caja,
                hvl.cod_empresa AS hvl_cod_empresa,
                hvl.tipo_venta AS hvl_tipo_venta,
                hvl.cod_venta AS hvl_cod_venta,
                hvl.cod_caja AS hvl_cod_caja,
                hvl.linea AS hvl_linea,
                hvl.cod_articulo AS hvl_cod_articulo,
                hvl.descripcion AS hvl_descripcion,
                ISNULL(hvl.cantidad, 0) AS hvl_cantidad,
                " . $selectLineFieldsSql . "
            FROM hist_ventas_linea hvl
            INNER JOIN hist_ventas_cabecera hvc
                ON hvc.cod_empresa = hvl.cod_empresa
               AND hvc.tipo_venta = hvl.tipo_venta
               AND hvc.cod_venta = hvl.cod_venta
            WHERE " . $whereCabecera . "
            ORDER BY hvl.cod_caja, hvl.linea
        ";
        $rsLineasM1 = estadisticasOdbcExec($conn, $sqlLineasM1, $paramsBase);
        if (!$rsLineasM1) {
            registrarErrorSqlEstadisticas('obtenerForenseDocumentoPedidoDebug.modelo1', $conn, $sqlLineasM1, $paramsBase);
            return $resultado;
        }

        $sumasM1 = [];
        foreach ($lineFields as $campo) {
            $sumasM1[$campo] = 0.0;
        }
        while ($row = odbc_fetch_array_utf8($rsLineasM1)) {
            $r = $toLowerRow($row);
            $item = [
                'hvc_key' => trim((string)($r['hvc_cod_empresa'] ?? '')) . '|' . trim((string)($r['hvc_tipo_venta'] ?? '')) . '|' . trim((string)($r['hvc_cod_venta'] ?? '')) . '|' . trim((string)($r['hvc_cod_caja'] ?? '')),
                'hvl_key' => trim((string)($r['hvl_cod_empresa'] ?? '')) . '|' . trim((string)($r['hvl_tipo_venta'] ?? '')) . '|' . trim((string)($r['hvl_cod_venta'] ?? '')) . '|' . trim((string)($r['hvl_cod_caja'] ?? '')) . '|' . trim((string)($r['hvl_linea'] ?? '')),
                'cod_empresa' => trim((string)($r['hvl_cod_empresa'] ?? '')),
                'tipo_venta' => trim((string)($r['hvl_tipo_venta'] ?? '')),
                'cod_venta' => trim((string)($r['hvl_cod_venta'] ?? '')),
                'cod_caja' => trim((string)($r['hvl_cod_caja'] ?? '')),
                'linea' => trim((string)($r['hvl_linea'] ?? '')),
                'cod_articulo' => trim((string)($r['hvl_cod_articulo'] ?? '')),
                'descripcion' => trim((string)($r['hvl_descripcion'] ?? '')),
                'cantidad' => (float)($r['hvl_cantidad'] ?? 0),
            ];
            foreach ($lineFields as $campo) {
                $k = 'line_' . $campo;
                $item[$campo] = (float)($r[$k] ?? 0);
                $sumasM1[$campo] += $item[$campo];
            }
            $resultado['lineas_modelo_1'][] = $item;
        }
        $resultado['conteos']['modelo_1_filas'] = count($resultado['lineas_modelo_1']);
        $resultado['sumas']['modelo_1'] = $sumasM1;

        return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_obtenerDescuadreCabeceraVsLineas')) {
    function __estadisticas_impl_obtenerDescuadreCabeceraVsLineas($conn, $codComisionista, $fechaDesde, $fechaHasta, $opts = []): array
    {
            $resultado = [
                'totales' => [
                    'total_diferencia_rango' => 0.0,
                    'docs_afectados' => 0,
                ],
                'motivos' => [],
                'filas' => [],
                'zona_resumen' => [],
                'zona_top_clientes' => [],
                'meta' => [
                    'line_importe_columna' => '',
                    'cabecera_ajustes_detectados' => [],
                    'zona_cliente_columna' => '',
                    'zona_cabecera_columna' => '',
                ],
            ];
            if (!$conn) {
                return $resultado;
            }

            $codComisionista = trim((string)$codComisionista);
            $fechaDesde = trim((string)$fechaDesde);
            $fechaHasta = trim((string)$fechaHasta);
            $motivoFiltro = trim((string)($opts['motivo'] ?? ''));
            $zonaFiltro = trim((string)($opts['zona'] ?? ''));
            $zonaObjetivo = trim((string)($opts['zona_objetivo'] ?? '10'));
            $limit = (int)($opts['limit'] ?? 50);
            if ($limit <= 0) {
                $limit = 50;
            }
            if ($limit > 500) {
                $limit = 500;
            }

            $fechaDesde = normalizarFechaIso($fechaDesde, date('Y') . '-01-01');
            $fechaHasta = normalizarFechaIso($fechaHasta, date('Y-m-d'));
            if ($fechaDesde > $fechaHasta) {
                [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
            }
            $desdeSql = $fechaDesde;
            $hastaMasUnoSql = sumarDiasFechaIso($fechaHasta, 1);

            $tablaCabecera = 'hist_ventas_cabecera';
            $tablaLinea = 'hist_ventas_linea';

            $obtenerColumnasTabla = static function ($connLocal, string $tabla): array {
                $sqlCols = "
                    SELECT LOWER(COLUMN_NAME) AS nombre
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE LOWER(TABLE_NAME) = LOWER('" . addslashes($tabla) . "')
                ";
                $rsCols = @estadisticasOdbcExec($connLocal, $sqlCols);
                if (!$rsCols) {
                    return [];
                }
                $cols = [];
                while ($rowCol = odbc_fetch_array_utf8($rsCols)) {
                    $n = strtolower(trim((string)($rowCol['nombre'] ?? '')));
                    if ($n !== '') {
                        $cols[$n] = true;
                    }
                }
                return $cols;
            };

            $escogerPrimeraColumna = static function (array $columnasDisponibles, array $candidatas): string {
                foreach ($candidatas as $c) {
                    $lc = strtolower((string)$c);
                    if (isset($columnasDisponibles[$lc])) {
                        return $c;
                    }
                }
                return '';
            };

            $colsCabecera = $obtenerColumnasTabla($conn, $tablaCabecera);
            $colsLinea = $obtenerColumnasTabla($conn, $tablaLinea);

            $lineImporteCol = $escogerPrimeraColumna($colsLinea, [
                'importe_linea',
                'importe',
                'total_linea',
                'total',
                'importe_neto',
                'base_imponible',
            ]);
            if ($lineImporteCol === '') {
                // Fallback razonable para CI.
                $lineImporteCol = 'importe';
            }

            $zonaCabeceraCol = $escogerPrimeraColumna($colsCabecera, ['cod_zona', 'zona', 'zona_venta']);
            $zonaClienteCol = 'cod_zona';

            $ajustesMap = [
                'descuento_global' => ['descuento_global', 'dto_global', 'descuento', 'descuento_cabecera'],
                'portes' => ['portes', 'gastos_envio', 'gastos_porte'],
                'gastos' => ['gastos', 'gastos_varios', 'otros_gastos'],
                'recargo' => ['recargo_financiero', 'recargo', 'recargo_cabecera'],
                'redondeo' => ['redondeo', 'ajuste_redondeo'],
                'pronto_pago' => ['pronto_pago', 'dto_pronto_pago'],
                'iva' => ['importe_iva', 'iva', 'cuota_iva'],
                'base_imponible' => ['base_imponible', 'subtotal', 'importe_base'],
            ];

            $ajustesCols = [];
            foreach ($ajustesMap as $alias => $candidatas) {
                $col = $escogerPrimeraColumna($colsCabecera, $candidatas);
                $ajustesCols[$alias] = $col;
                if ($col !== '') {
                    $resultado['meta']['cabecera_ajustes_detectados'][] = $alias . ':' . $col;
                }
            }

            $resultado['meta']['line_importe_columna'] = $lineImporteCol;
            $resultado['meta']['zona_cliente_columna'] = $zonaClienteCol;
            $resultado['meta']['zona_cabecera_columna'] = $zonaCabeceraCol;

            $exprZonaCabecera = $zonaCabeceraCol !== ''
                ? "CAST(hvc." . $zonaCabeceraCol . " AS VARCHAR(50))"
                : "NULL";

            $exprAjuste = static function (string $alias, array $cols): string {
                $col = $cols[$alias] ?? '';
                if ($col === '') {
                    return "CAST(0 AS FLOAT) AS " . $alias;
                }
                return "ISNULL(TRY_CAST(hvc." . $col . " AS FLOAT), 0) AS " . $alias;
            };

            $sqlCondComisionista = '';
            // ORDEN PARAMS: desde, hasta, comercial
            $paramsBase = [];
            $paramsBase[] = $desdeSql;
            $paramsBase[] = $hastaMasUnoSql;
            if ($codComisionista !== '') {
                $sqlCondComisionista = " AND CAST(ISNULL(hvc.cod_comisionista, 0) AS VARCHAR(50)) = ?";
                $paramsBase[] = $codComisionista;
            }

            $baseCte = "
                WITH docs AS (
                    SELECT
                        hvc.cod_empresa,
                        hvc.tipo_venta,
                        hvc.cod_venta,
                        hvc.fecha_venta,
                        hvc.cod_cliente,
                        CAST(ISNULL(hvc.cod_comisionista, 0) AS VARCHAR(50)) AS cod_comisionista,
                        ISNULL(hvc.importe, 0) AS importe_cabecera,
                        " . $exprZonaCabecera . " AS zona_cabecera,
                        " . $exprAjuste('descuento_global', $ajustesCols) . ",
                        " . $exprAjuste('portes', $ajustesCols) . ",
                        " . $exprAjuste('gastos', $ajustesCols) . ",
                        " . $exprAjuste('recargo', $ajustesCols) . ",
                        " . $exprAjuste('redondeo', $ajustesCols) . ",
                        " . $exprAjuste('pronto_pago', $ajustesCols) . ",
                        " . $exprAjuste('iva', $ajustesCols) . ",
                        " . $exprAjuste('base_imponible', $ajustesCols) . "
                    FROM hist_ventas_cabecera hvc
                    WHERE hvc.tipo_venta = 1
                      AND ISNULL(hvc.importe, 0) >= 0
                      AND ISNULL(hvc.anulada, 'N') <> 'S'
                      AND ISNULL(hvc.cod_comisionista, 0) <> 0
                      " . construirRangoFechasSql('hvc.fecha_venta') . "
                      " . $sqlCondComisionista . "
                ),
                lineas AS (
                    SELECT
                        d.cod_empresa,
                        d.tipo_venta,
                        d.cod_venta,
                        SUM(ISNULL(TRY_CAST(hvl." . $lineImporteCol . " AS FLOAT), 0)) AS sum_importe_lineas,
                        COUNT(1) AS num_lineas
                    FROM docs d
                    LEFT JOIN hist_ventas_linea hvl
                        ON hvl.cod_empresa = d.cod_empresa
                       AND hvl.tipo_venta = d.tipo_venta
                       AND hvl.cod_venta = d.cod_venta
                    GROUP BY
                        d.cod_empresa,
                        d.tipo_venta,
                        d.cod_venta
                ),
                diag AS (
                    SELECT
                        d.*,
                        ISNULL(TRY_CAST(l.sum_importe_lineas AS FLOAT), 0) AS sum_importe_lineas,
                        CAST(ISNULL(l.num_lineas, 0) AS INT) AS num_lineas,
                        CAST(d.importe_cabecera - ISNULL(l.sum_importe_lineas, 0) AS FLOAT) AS diferencia
                    FROM docs d
                    LEFT JOIN lineas l
                        ON l.cod_empresa = d.cod_empresa
                       AND l.tipo_venta = d.tipo_venta
                       AND l.cod_venta = d.cod_venta
                )
            ";

            $motivoExpr = "
                CASE
                    WHEN ABS(d.diferencia) < 0.05 THEN 'REDONDEO'
                    WHEN ABS(ABS(d.diferencia) - ABS(ISNULL(d.descuento_global, 0))) <= 0.05 AND ABS(ISNULL(d.descuento_global, 0)) > 0.01 THEN 'DESCUENTO_CABECERA'
                    WHEN ABS(ABS(d.diferencia) - ABS(ISNULL(d.portes, 0) + ISNULL(d.gastos, 0))) <= 0.05
                         AND ABS(ISNULL(d.portes, 0) + ISNULL(d.gastos, 0)) > 0.01 THEN 'GASTOS_CABECERA'
                    WHEN ABS(ABS(d.diferencia) - ABS(ISNULL(d.recargo, 0))) <= 0.05 AND ABS(ISNULL(d.recargo, 0)) > 0.01 THEN 'RECARGO_CABECERA'
                    WHEN ABS(ABS(d.diferencia) - ABS(ISNULL(d.redondeo, 0))) <= 0.05 AND ABS(ISNULL(d.redondeo, 0)) > 0.01 THEN 'REDONDEO'
                    WHEN ABS(ABS(d.diferencia) - ABS(ISNULL(d.iva, 0))) <= 0.05 AND ABS(ISNULL(d.iva, 0)) > 0.01 THEN 'BRUTO_VS_NETO'
                    WHEN ABS(ABS(d.diferencia) - ABS(ISNULL(d.importe_cabecera, 0) - ISNULL(d.base_imponible, 0))) <= 0.05
                         AND ABS(ISNULL(d.importe_cabecera, 0) - ISNULL(d.base_imponible, 0)) > 0.01 THEN 'BRUTO_VS_NETO'
                    ELSE 'DESCONOCIDO'
                END
            ";

            $whereDiag = " WHERE ABS(d.diferencia) > 0.01 ";
            $paramsWhere = [];
            if ($zonaFiltro !== '') {
                $whereDiag .= " AND CAST(COALESCE(d.zona_cabecera, cli." . $zonaClienteCol . ") AS VARCHAR(50)) = ?";
                $paramsWhere[] = $zonaFiltro;
            }
            if ($motivoFiltro !== '') {
                $whereDiag .= " AND " . $motivoExpr . " = ?";
                $paramsWhere[] = $motivoFiltro;
            }
            $paramsConFiltros = array_merge($paramsBase, $paramsWhere);

            $sqlTotales = $baseCte . "
                SELECT
                    COUNT(1) AS docs_afectados,
                    SUM(d.diferencia) AS total_diferencia
                FROM diag d
                LEFT JOIN integral.dbo.clientes cli
                    ON cli.cod_cliente = d.cod_cliente
                " . $whereDiag . "
            ";
            $rsTotales = estadisticasOdbcExec($conn, $sqlTotales, $paramsConFiltros);
            if ($rsTotales) {
                $rowTotales = odbc_fetch_array_utf8($rsTotales);
                if ($rowTotales) {
                    $resultado['totales']['docs_afectados'] = (int)($rowTotales['docs_afectados'] ?? 0);
                    $resultado['totales']['total_diferencia_rango'] = (float)($rowTotales['total_diferencia'] ?? 0);
                }
            } else {
                registrarErrorSqlEstadisticas('obtenerDescuadreCabeceraVsLineas.totales', $conn, $sqlTotales, $paramsConFiltros);
            }

            $sqlMotivos = $baseCte . "
                SELECT
                    " . $motivoExpr . " AS motivo,
                    COUNT(1) AS cantidad,
                    SUM(d.diferencia) AS total_diferencia
                FROM diag d
                LEFT JOIN integral.dbo.clientes cli
                    ON cli.cod_cliente = d.cod_cliente
                " . $whereDiag . "
                GROUP BY " . $motivoExpr . "
                ORDER BY COUNT(1) DESC, ABS(SUM(d.diferencia)) DESC
            ";
            $rsMotivos = estadisticasOdbcExec($conn, $sqlMotivos, $paramsConFiltros);
            if ($rsMotivos) {
                while ($row = odbc_fetch_array_utf8($rsMotivos)) {
                    $resultado['motivos'][] = [
                        'motivo' => trim((string)($row['motivo'] ?? 'DESCONOCIDO')),
                        'cantidad' => (int)($row['cantidad'] ?? 0),
                        'total_diferencia' => (float)($row['total_diferencia'] ?? 0),
                    ];
                }
            } else {
                registrarErrorSqlEstadisticas('obtenerDescuadreCabeceraVsLineas.motivos', $conn, $sqlMotivos, $paramsConFiltros);
            }

            $sqlFilas = $baseCte . "
                SELECT TOP " . $limit . "
                    d.fecha_venta,
                    d.cod_empresa,
                    d.tipo_venta,
                    d.cod_venta,
                    d.cod_cliente,
                    CAST(COALESCE(d.zona_cabecera, cli." . $zonaClienteCol . ") AS VARCHAR(50)) AS zona,
                    d.cod_comisionista,
                    d.importe_cabecera,
                    d.sum_importe_lineas,
                    d.num_lineas,
                    d.diferencia,
                    " . $motivoExpr . " AS motivo
                FROM diag d
                LEFT JOIN integral.dbo.clientes cli
                    ON cli.cod_cliente = d.cod_cliente
                " . $whereDiag . "
                ORDER BY ABS(d.diferencia) DESC, d.fecha_venta DESC, d.cod_venta DESC
            ";

            $rsFilas = estadisticasOdbcExec($conn, $sqlFilas, $paramsConFiltros);
            if (!$rsFilas) {
                registrarErrorSqlEstadisticas('obtenerDescuadreCabeceraVsLineas.filas', $conn, $sqlFilas, $paramsConFiltros);
                return $resultado;
            }

            $filas = [];
            while ($row = odbc_fetch_array_utf8($rsFilas)) {
                $motivo = trim((string)($row['motivo'] ?? 'DESCONOCIDO'));
                $dif = (float)($row['diferencia'] ?? 0);

                $filas[] = [
                    'fecha_venta' => (string)($row['fecha_venta'] ?? ''),
                    'cod_empresa' => trim((string)($row['cod_empresa'] ?? '')),
                    'tipo_venta' => trim((string)($row['tipo_venta'] ?? '')),
                    'cod_venta' => trim((string)($row['cod_venta'] ?? '')),
                    'cod_cliente' => trim((string)($row['cod_cliente'] ?? '')),
                    'zona' => trim((string)($row['zona'] ?? '')),
                    'cod_comisionista' => trim((string)($row['cod_comisionista'] ?? '')),
                    'importe_cabecera' => (float)($row['importe_cabecera'] ?? 0),
                    'sum_importe_lineas' => (float)($row['sum_importe_lineas'] ?? 0),
                    'num_lineas' => (int)($row['num_lineas'] ?? 0),
                    'diferencia' => $dif,
                    'motivo' => $motivo,
                ];
            }

            $resultado['filas'] = $filas;

            $sqlZonaResumen = $baseCte . "
                SELECT
                    CAST(COALESCE(d.zona_cabecera, cli." . $zonaClienteCol . ") AS VARCHAR(50)) AS zona,
                    COUNT(1) AS docs_afectados,
                    SUM(d.diferencia) AS total_diferencia
                FROM diag d
                LEFT JOIN integral.dbo.clientes cli
                    ON cli.cod_cliente = d.cod_cliente
                WHERE ABS(d.diferencia) > 0.01
                GROUP BY CAST(COALESCE(d.zona_cabecera, cli." . $zonaClienteCol . ") AS VARCHAR(50))
                ORDER BY ABS(SUM(d.diferencia)) DESC
            ";
            $rsZona = estadisticasOdbcExec($conn, $sqlZonaResumen, $paramsBase);
            if ($rsZona) {
                while ($row = odbc_fetch_array_utf8($rsZona)) {
                    $resultado['zona_resumen'][] = [
                        'zona' => trim((string)($row['zona'] ?? '')),
                        'docs_afectados' => (int)($row['docs_afectados'] ?? 0),
                        'total_diferencia' => (float)($row['total_diferencia'] ?? 0),
                    ];
                }
            } else {
                registrarErrorSqlEstadisticas('obtenerDescuadreCabeceraVsLineas.zona_resumen', $conn, $sqlZonaResumen, $paramsBase);
            }

            if ($zonaObjetivo !== '') {
                $sqlZonaClientes = $baseCte . "
                    SELECT TOP 20
                        d.cod_cliente,
                        cli.nombre_comercial AS nombre_cliente,
                        COUNT(1) AS docs_afectados,
                        SUM(d.diferencia) AS total_diferencia
                    FROM diag d
                    LEFT JOIN integral.dbo.clientes cli
                        ON cli.cod_cliente = d.cod_cliente
                    WHERE ABS(d.diferencia) > 0.01
                      AND CAST(COALESCE(d.zona_cabecera, cli." . $zonaClienteCol . ") AS VARCHAR(50)) = ?
                    GROUP BY
                        d.cod_cliente,
                        cli.nombre_comercial
                    ORDER BY ABS(SUM(d.diferencia)) DESC
                ";
                $paramsZonaClientes = array_merge($paramsBase, [$zonaObjetivo]);
                $rsZonaClientes = estadisticasOdbcExec($conn, $sqlZonaClientes, $paramsZonaClientes);
                if ($rsZonaClientes) {
                    while ($row = odbc_fetch_array_utf8($rsZonaClientes)) {
                        $resultado['zona_top_clientes'][] = [
                            'cod_cliente' => trim((string)($row['cod_cliente'] ?? '')),
                            'nombre_cliente' => trim((string)($row['nombre_cliente'] ?? '')),
                            'docs_afectados' => (int)($row['docs_afectados'] ?? 0),
                            'total_diferencia' => (float)($row['total_diferencia'] ?? 0),
                        ];
                    }
                } else {
                    registrarErrorSqlEstadisticas('obtenerDescuadreCabeceraVsLineas.zona_top_clientes', $conn, $sqlZonaClientes, $paramsZonaClientes);
                }
            }

            return $resultado;
    }
}


if (!function_exists('__estadisticas_impl_construirSqlBaseServicioPedidosCTE')) {
    function __estadisticas_impl_construirSqlBaseServicioPedidosCTE(): string
    {
        return "
            WITH pedidos_lineas AS (
                SELECT
                    hvl.cod_venta,
                    hvl.tipo_venta,
                    hvl.cod_empresa,
                    hvl.cod_caja,
                    hvl.linea,
                    ISNULL(hvl.importe, 0) AS importe_linea,
                    ISNULL(TRY_CAST(hvl.cantidad AS FLOAT), 0) * ISNULL(TRY_CAST(hvl.unidades_venta AS FLOAT), 1) AS cantidad_pedida
                FROM hist_ventas_cabecera hvc
                INNER JOIN hist_ventas_linea hvl
                    ON hvl.cod_empresa = hvc.cod_empresa
                   AND hvl.tipo_venta = hvc.tipo_venta
                   AND hvl.cod_venta = hvc.cod_venta
                   AND hvl.cod_caja = hvc.cod_caja
    INNER JOIN articulos a
                    ON a.cod_articulo = hvl.cod_articulo
                WHERE 1=1
                __WHERE_CABECERA__
                __WHERE_ARTICULOS__
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
                    pl.importe_linea,
                    pl.cantidad_pedida,
                    ISNULL(so.cantidad_servida_oficial, 0) AS cantidad_servida_oficial,
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
            agregados AS (
                SELECT
                    SUM(lc.importe_linea) AS total_pedido,
                    SUM(
                        CASE
                            WHEN lc.cantidad_pedida > 0
                                THEN (lc.cantidad_servida_real / lc.cantidad_pedida) * lc.importe_linea
                            ELSE 0
                        END
                    ) AS total_servido,
                    SUM(
                        CASE
                            WHEN lc.cantidad_pedida > 0
                                THEN (lc.cantidad_servida_oficial / lc.cantidad_pedida) * lc.importe_linea
                            ELSE 0
                        END
                    ) AS total_servido_bruto
                FROM lineas_calculadas lc
            )
        ";
    }
}


if (!function_exists('__estadisticas_impl_obtenerOpcionesMarcaVentas')) {
    function __estadisticas_impl_obtenerOpcionesMarcaVentas($conn, array $contexto): array
    {
        if (!$conn) {
            return [];
        }

        $contextoTmp = $contexto;
        $contextoTmp['marca'] = null;
        $contextoTmp['filtro_marca'] = null;

        [$fDesde, $fHastaMasUno, $fHasta] = obtenerRangoFechasContextoSql($contextoTmp);
        $codComisionista = trim((string)($contextoTmp['cod_comisionista'] ?? ''));
        if ($codComisionista === '') {
            $codComisionista = trim((string)($contextoTmp['cod_comisionista_activo'] ?? ''));
        }
        [$sqlBaseLineas, $params] = construirBaseLineasDocumentalesSql([
            'fecha_desde' => $fDesde,
            'fecha_hasta' => $fHasta,
            'cod_comisionista' => $codComisionista,
        ]);

        $sql = "
            SELECT DISTINCT
                LTRIM(RTRIM(base.marca)) AS marca
            FROM (
                " . $sqlBaseLineas . "
            ) base
            WHERE 1=1
            AND base.marca IS NOT NULL
            AND LTRIM(RTRIM(base.marca)) <> ''
            ORDER BY marca
        ";

        $conn = db();
        $stmt = odbc_prepare($conn, $sql);
        odbc_execute($stmt, $params);

        $rows = [];
        while ($row = odbc_fetch_array($stmt)) {
            $rows[] = $row;
        }
        $resultado = [];
        foreach ($rows as $row) {
            $marca = trim((string)($row['marca'] ?? ''));
            if ($marca === '') {
                continue;
            }
            $resultado[] = $marca;
        }
        return $resultado;
    }
}
