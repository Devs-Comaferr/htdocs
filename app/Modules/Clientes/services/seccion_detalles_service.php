<?php

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap/init.php';
}

function seccionDetallesSqlLiteral(string $value): string
{
    return str_replace("'", "''", $value);
}

function seccionDetallesObtenerResumenPedido($connLocal, string $codPedido, string $origen = ''): array
{
    $pedido = array(
        'cod_pedido' => $codPedido,
        'origen' => $origen,
        'fecha_venta' => null,
        'hora_venta' => null,
        'importe' => 0.0,
        'numero_lineas' => 0,
        'observacion_interna' => '',
        'pedido_eliminado' => 0,
        'eliminado_por_usuario' => '',
        'eliminado_por_equipo' => '',
        'eliminado_fecha' => null,
        'eliminado_hora' => null,
    );

    $sqlCabElim = "
        SELECT TOP 1 *
        FROM [integral].[dbo].[ventas_cabecera_elim] vce
        WHERE vce.cod_venta = '" . seccionDetallesSqlLiteral($codPedido) . "'
          AND vce.tipo_venta = 1
        ORDER BY vce.fecha_venta DESC, vce.hora_venta DESC
    ";
    $resCabElim = odbc_exec($connLocal, $sqlCabElim);
    $cabElim = $resCabElim ? odbc_fetch_array($resCabElim) : false;

    if ($cabElim) {
        $pedido['fecha_venta'] = $cabElim['fecha_venta'] ?? $cabElim['FECHA_VENTA'] ?? null;
        $pedido['hora_venta'] = $cabElim['hora_venta'] ?? $cabElim['HORA_VENTA'] ?? null;
        $pedido['importe'] = (float) ($cabElim['importe'] ?? $cabElim['IMPORTE'] ?? 0);
        $pedido['pedido_eliminado'] = 1;

        $sqlLogElim = "
            SELECT TOP 1
                la.cod_usuario,
                la.cod_estacion,
                la.fecha,
                la.hora
            FROM [integral].[dbo].[log_acciones] la
            WHERE la.tipo = 'B'
              AND la.categoria = 'V'
              AND la.cod_n3 = '" . seccionDetallesSqlLiteral($codPedido) . "'
            ORDER BY la.fecha DESC, la.hora DESC
        ";
        $resLogElim = odbc_exec($connLocal, $sqlLogElim);
        $logElim = $resLogElim ? odbc_fetch_array($resLogElim) : false;
        if ($logElim) {
            $pedido['eliminado_por_usuario'] = (string) ($logElim['cod_usuario'] ?? $logElim['COD_USUARIO'] ?? '');
            $pedido['eliminado_por_equipo'] = (string) ($logElim['cod_estacion'] ?? $logElim['COD_ESTACION'] ?? '');
            $pedido['eliminado_fecha'] = $logElim['fecha'] ?? $logElim['FECHA'] ?? null;
            $pedido['eliminado_hora'] = $logElim['hora'] ?? $logElim['HORA'] ?? null;
        }
    } else {
        $sqlCabHist = "
            SELECT TOP 1
                hvc.fecha_venta,
                hvc.hora_venta,
                hvc.importe,
                avc.observacion_interna
            FROM [integral].[dbo].[hist_ventas_cabecera] hvc
            LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] avc
              ON hvc.cod_anexo = avc.cod_anexo
            WHERE hvc.cod_venta = '" . seccionDetallesSqlLiteral($codPedido) . "'
              AND hvc.tipo_venta = 1
            ORDER BY hvc.fecha_venta DESC, hvc.hora_venta DESC
        ";
        $resCabHist = odbc_exec($connLocal, $sqlCabHist);
        $cabHist = $resCabHist ? odbc_fetch_array($resCabHist) : false;
        if ($cabHist) {
            $pedido['fecha_venta'] = $cabHist['fecha_venta'] ?? $cabHist['FECHA_VENTA'] ?? null;
            $pedido['hora_venta'] = $cabHist['hora_venta'] ?? $cabHist['HORA_VENTA'] ?? null;
            $pedido['importe'] = (float) ($cabHist['importe'] ?? $cabHist['IMPORTE'] ?? 0);
            $pedido['observacion_interna'] = (string) ($cabHist['observacion_interna'] ?? $cabHist['OBSERVACION_INTERNA'] ?? '');
        }
    }

    if ((int) $pedido['pedido_eliminado'] === 1) {
        $sqlNumLineasElim = "
            SELECT COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[ventas_linea_elim] vle
            WHERE vle.cod_venta = '" . seccionDetallesSqlLiteral($codPedido) . "'
              AND vle.tipo_venta = 1
        ";
        $resNumLineasElim = odbc_exec($connLocal, $sqlNumLineasElim);
        $numElim = $resNumLineasElim ? odbc_fetch_array($resNumLineasElim) : false;
        $pedido['numero_lineas'] = (int) ($numElim['numero_lineas'] ?? $numElim['NUMERO_LINEAS'] ?? 0);
    } else {
        $sqlNumLineasHist = "
            SELECT COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[hist_ventas_linea] hl
            WHERE hl.cod_venta = '" . seccionDetallesSqlLiteral($codPedido) . "'
              AND hl.tipo_venta = 1
        ";
        $resNumLineasHist = odbc_exec($connLocal, $sqlNumLineasHist);
        $numHist = $resNumLineasHist ? odbc_fetch_array($resNumLineasHist) : false;
        $pedido['numero_lineas'] = (int) ($numHist['numero_lineas'] ?? $numHist['NUMERO_LINEAS'] ?? 0);
    }

    return $pedido;
}

function seccionDetallesObtenerNombreSeccion($conn, string $codCliente, string $codSeccion): string
{
    $sql = "
        SELECT nombre
        FROM [integral].[dbo].[secciones_cliente]
        WHERE cod_cliente = '" . seccionDetallesSqlLiteral($codCliente) . "'
          AND cod_seccion = '" . seccionDetallesSqlLiteral($codSeccion) . "'
    ";
    $result = odbc_exec($conn, $sql);
    if (!$result) {
        throw new RuntimeException('Error al consultar la sección: ' . odbc_errormsg($conn));
    }

    $row = odbc_fetch_array($result);
    return $row ? (string) ($row['nombre'] ?? 'Sección desconocida') : 'Sección desconocida';
}

function seccionDetallesObtenerDatosBasicos($conn, string $codCliente, string $codSeccion)
{
    $sql = "
        SELECT
            c.cod_cliente,
            c.nombre_comercial,
            s.direccion1 AS direccion,
            s.telefono,
            s.telefono_comentario,
            s.telefono_movil,
            s.telefono_movil_comentario,
            s.e_mail,
            s.poblacion,
            s.provincia,
            s.nombre_contacto,
            s.cargo_contacto
        FROM [integral].[dbo].[clientes] c
        INNER JOIN [integral].[dbo].[secciones_cliente] s
           ON c.cod_cliente = s.cod_cliente
        WHERE c.cod_cliente = '" . seccionDetallesSqlLiteral($codCliente) . "'
          AND s.cod_seccion = '" . seccionDetallesSqlLiteral($codSeccion) . "'
    ";
    $result = odbc_exec($conn, $sql);
    if (!$result) {
        throw new RuntimeException('Error al ejecutar la consulta de datos de la sección: ' . odbc_errormsg($conn));
    }

    return odbc_fetch_array($result);
}

function seccionDetallesObtenerAsignacion($conn, string $codCliente, string $codSeccion)
{
    $sql = "
        SELECT *
        FROM [integral].[dbo].[cmf_asignacion_zonas_clientes]
        WHERE cod_cliente = '" . seccionDetallesSqlLiteral($codCliente) . "'
          AND cod_seccion = '" . seccionDetallesSqlLiteral($codSeccion) . "'
    ";
    $result = odbc_exec($conn, $sql);
    return $result ? odbc_fetch_array($result) : false;
}

function seccionDetallesObtenerVisitas($connAux, $connAux2, string $codCliente, string $codSeccion): array
{
    $sqlVisitas = "
        SELECT
            v.id_visita,
            v.observaciones,
            v.estado_visita,
            v.fecha_visita,
            v.hora_inicio_visita,
            v.hora_fin_visita
        FROM [integral].[dbo].[cmf_visitas_comerciales] v
        WHERE v.cod_cliente = '" . seccionDetallesSqlLiteral($codCliente) . "'
          AND v.cod_seccion = '" . seccionDetallesSqlLiteral($codSeccion) . "'
        ORDER BY v.fecha_visita DESC
    ";
    $resultVisitas = odbc_exec($connAux, $sqlVisitas);
    if (!$resultVisitas) {
        throw new RuntimeException('Error al ejecutar la consulta de visitas: ' . odbc_errormsg($connAux));
    }

    $visitas = array();
    while ($visita = odbc_fetch_array($resultVisitas)) {
        $visita['id_visita'] = (string) ($visita['id_visita'] ?? $visita['ID_VISITA'] ?? '');
        $visita['estado_visita'] = (string) ($visita['estado_visita'] ?? $visita['ESTADO_VISITA'] ?? '');
        $visita['fecha_visita'] = $visita['fecha_visita'] ?? $visita['FECHA_VISITA'] ?? null;
        $visita['hora_inicio_visita'] = $visita['hora_inicio_visita'] ?? $visita['HORA_INICIO_VISITA'] ?? null;
        $visita['hora_fin_visita'] = $visita['hora_fin_visita'] ?? $visita['HORA_FIN_VISITA'] ?? null;
        $visita['observaciones'] = (string) ($visita['observaciones'] ?? $visita['OBSERVACIONES'] ?? '');

        $sqlPedidoPrincipal = "
            SELECT TOP 1 vp.cod_venta, vp.origen
            FROM [integral].[dbo].[cmf_visita_pedidos] vp
            WHERE vp.id_visita = '" . seccionDetallesSqlLiteral($visita['id_visita']) . "'
            ORDER BY vp.id_visita_pedido ASC
        ";
        $resPedidoPrincipal = odbc_exec($connAux, $sqlPedidoPrincipal);
        $pedidoPrincipal = $resPedidoPrincipal ? odbc_fetch_array($resPedidoPrincipal) : false;
        $origenPrincipal = $pedidoPrincipal ? ($pedidoPrincipal['origen'] ?? $pedidoPrincipal['ORIGEN'] ?? 'otros') : 'otros';
        $visita['color'] = determinarColorVisita($visita['estado_visita'] ?? '', (string) $origenPrincipal);

        $sqlPedidos = "
            SELECT
                vp.cod_venta AS cod_pedido,
                vp.origen
            FROM [integral].[dbo].[cmf_visita_pedidos] vp
            WHERE vp.id_visita = '" . seccionDetallesSqlLiteral($visita['id_visita']) . "'
            ORDER BY vp.id_visita_pedido ASC
        ";
        $resPedidos = odbc_exec($connAux, $sqlPedidos);
        if ($resPedidos) {
            $pedidos = array();
            $importeTotal = 0.0;
            $lineasTotal = 0;

            while ($pedido = odbc_fetch_array($resPedidos)) {
                $pedido['cod_pedido'] = (string) ($pedido['cod_pedido'] ?? $pedido['COD_PEDIDO'] ?? $pedido['cod_venta'] ?? $pedido['COD_VENTA'] ?? '');
                $pedido['origen'] = (string) ($pedido['origen'] ?? $pedido['ORIGEN'] ?? '');
                if ($pedido['cod_pedido'] === '') {
                    continue;
                }

                $pedido = seccionDetallesObtenerResumenPedido($connAux2, $pedido['cod_pedido'], $pedido['origen']);
                $importeTotal += (float) ($pedido['importe'] ?? 0);
                $lineasTotal += (int) ($pedido['numero_lineas'] ?? 0);
                $pedidos[] = $pedido;
            }

            if (count($pedidos) === 0) {
                $sqlPedidosFallback = "
                    SELECT DISTINCT
                        vp.cod_venta AS cod_pedido,
                        vp.origen
                    FROM [integral].[dbo].[cmf_visita_pedidos] vp
                    WHERE vp.id_visita = '" . seccionDetallesSqlLiteral($visita['id_visita']) . "'
                ";
                $resPedidosFallback = odbc_exec($connAux, $sqlPedidosFallback);
                if ($resPedidosFallback) {
                    while ($pedidoFb = odbc_fetch_array($resPedidosFallback)) {
                        $pedidoFbCod = (string) ($pedidoFb['cod_pedido'] ?? $pedidoFb['COD_PEDIDO'] ?? $pedidoFb['cod_venta'] ?? $pedidoFb['COD_VENTA'] ?? '');
                        if ($pedidoFbCod === '') {
                            continue;
                        }
                        $pedido = seccionDetallesObtenerResumenPedido($connAux2, $pedidoFbCod, (string) ($pedidoFb['origen'] ?? $pedidoFb['ORIGEN'] ?? ''));
                        $importeTotal += (float) $pedido['importe'];
                        $lineasTotal += (int) $pedido['numero_lineas'];
                        $pedidos[] = $pedido;
                    }
                }
            }

            $visita['pedidos'] = $pedidos;
            $visita['importe_total'] = $importeTotal;
            $visita['numero_lineas_total'] = $lineasTotal;
        } else {
            $visita['pedidos'] = array();
            $visita['importe_total'] = 0;
            $visita['numero_lineas_total'] = 0;
        }

        $visitas[] = $visita;
    }

    return $visitas;
}

function seccionDetallesPaginarVisitas(array $visitas, array $request): array
{
    $visitasPorPagina = 10;
    $totalVisitas = count($visitas);
    $totalPaginasVisitas = max(1, (int) ceil($totalVisitas / $visitasPorPagina));
    $paginaVisitas = isset($request['pag_visitas']) ? max(1, (int) $request['pag_visitas']) : 1;
    if ($paginaVisitas > $totalPaginasVisitas) {
        $paginaVisitas = $totalPaginasVisitas;
    }

    $offsetVisitas = ($paginaVisitas - 1) * $visitasPorPagina;
    $visitasPaginadas = array_slice($visitas, $offsetVisitas, $visitasPorPagina);

    return array($visitasPorPagina, $totalVisitas, $totalPaginasVisitas, $paginaVisitas, $offsetVisitas, $visitasPaginadas);
}

if (!function_exists('cargarResumenPedido')) {
    function cargarResumenPedido($connLocal, string $codPedido, string $origen = ''): array
    {
        return seccionDetallesObtenerResumenPedido($connLocal, $codPedido, $origen);
    }
}
