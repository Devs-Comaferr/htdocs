<?php
if (!defined('BASE_PATH')) {
    exit;
}

function obtenerPedidosTodos($params) {
    $conn = db();
    $cliente_filtro = isset($params['cliente']) ? mb_convert_encoding((string)$params['cliente'], 'Windows-1252', 'UTF-8') : '';

    $orden_permitido = array('Pedido', 'Fecha_Pedido', 'Cliente', 'Importe', 'Articulos_Pendientes', 'Importe_Pendiente', 'Importe_Disponible', 'Importe_Pdte_Recibir');
    $orden = (isset($params['orden']) && in_array($params['orden'], $orden_permitido, true)) ? $params['orden'] : 'Pedido';
    $direccion = (isset($params['direccion']) && ($params['direccion'] === 'ASC' || $params['direccion'] === 'DESC')) ? $params['direccion'] : 'ASC';
    $direccion_invertida = ($direccion === 'ASC') ? 'DESC' : 'ASC';

    $resultsPerPage = 30;
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    $offset = ($page - 1) * $resultsPerPage;

    $cod_vendedor = pedidosTodosObtenerCodVendedor($conn);
    $whereConditions = pedidosTodosConstruirWhereConditions($cod_vendedor, $cliente_filtro);
    $sql_order = in_array($orden, array('Importe_Disponible', 'Importe_Pdte_Recibir'), true) ? 'Pedido' : $orden;

    $sql_pedidos = "
        SELECT 
            hvl.cod_venta AS Pedido,
            hvc.fecha_venta AS Fecha_Pedido,
            c.cod_cliente AS cod_cliente,
            c.nombre_comercial AS Cliente,
            hvc.cod_seccion AS Cod_Seccion,
            hvc.importe AS Importe,
            COALESCE(s.nombre, '') AS Seccion,
            COUNT(hvl.cod_articulo) AS Articulos_Pendientes,
            SUM(
                CASE 
                    WHEN elv.cod_venta_origen IS NULL THEN hvl.cantidad * hvl.precio 
                    ELSE (hvl.cantidad - elv.cantidad_servida) * hvl.precio 
                END
            ) AS Importe_Pendiente,
            hvc.cod_anexo,
            avc.observacion_interna AS ObservacionInterna,
            SUM(hvl.cantidad) AS Total_Cantidad
        FROM 
            integral.dbo.hist_ventas_linea hvl
        INNER JOIN 
            integral.dbo.hist_ventas_cabecera hvc ON hvc.cod_venta = hvl.cod_venta
        LEFT JOIN (
            SELECT 
                cod_venta_origen, 
                linea_origen, 
                SUM(cantidad) AS cantidad_servida
            FROM 
                integral.dbo.entrega_lineas_venta
            WHERE 
                tipo_venta_origen = 1
            GROUP BY 
                cod_venta_origen, linea_origen
        ) elv ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
        LEFT JOIN 
            integral.dbo.clientes c ON hvc.cod_cliente = c.cod_cliente
        LEFT JOIN 
            integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = hvc.cod_seccion
        LEFT JOIN 
            integral.dbo.anexo_ventas_cabecera avc ON hvc.cod_anexo = avc.cod_anexo
        WHERE 
            " . implode(" AND ", $whereConditions) . "
        GROUP BY 
            hvl.cod_venta, 
            hvc.fecha_venta, 
            c.cod_cliente, 
            c.nombre_comercial, 
            hvc.cod_seccion, 
            hvc.importe,
            s.nombre,
            hvc.cod_anexo,
            avc.observacion_interna
        ORDER BY 
            $sql_order $direccion
        OFFSET $offset ROWS FETCH NEXT $resultsPerPage ROWS ONLY
    ";

    $result_pedidos = odbc_exec($conn, $sql_pedidos);
    if (!$result_pedidos) {
        die("Error en la consulta SQL: " . odbc_errormsg($conn));
    }

    $pedidos = array();
    while ($row = odbc_fetch_array($result_pedidos)) {
        $pedidos[] = $row;
    }

    $pedidoIds = pedidosTodosExtraerIds($pedidos);
    $historicos = pedidosTodosCargarHistoricos($conn, $pedidoIds);
    $entregas = pedidosTodosCargarEntregas($conn, $pedidoIds);
    $importes = pedidosTodosCargarImportes($conn, $pedidoIds);

    foreach ($pedidos as &$pedido) {
        $pedido = pedidosTodosAdjuntarResumen($pedido, $historicos, $entregas, $importes);
    }
    unset($pedido);

    if (in_array($orden, array('Importe_Disponible', 'Importe_Pdte_Recibir'), true)) {
        usort($pedidos, function ($a, $b) use ($orden, $direccion) {
            if ($a[$orden] == $b[$orden]) {
                return 0;
            }
            if ($direccion === 'ASC') {
                return ($a[$orden] < $b[$orden]) ? -1 : 1;
            }

            return ($a[$orden] > $b[$orden]) ? -1 : 1;
        });
    }

    $sql_count = "
        SELECT COUNT(*) as total
        FROM (
            SELECT 
                hvl.cod_venta
            FROM 
                integral.dbo.hist_ventas_linea hvl
            INNER JOIN 
                integral.dbo.hist_ventas_cabecera hvc ON hvc.cod_venta = hvl.cod_venta
            LEFT JOIN (
                SELECT 
                    cod_venta_origen, 
                    linea_origen, 
                    SUM(cantidad) AS cantidad_servida
                FROM 
                    integral.dbo.entrega_lineas_venta
                WHERE 
                    tipo_venta_origen = 1
                GROUP BY 
                    cod_venta_origen, linea_origen
            ) elv ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
            LEFT JOIN 
                integral.dbo.clientes c ON hvc.cod_cliente = c.cod_cliente
            LEFT JOIN 
                integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = hvc.cod_seccion
            WHERE 
                " . implode(" AND ", $whereConditions) . "
            GROUP BY 
                hvl.cod_venta, 
                hvc.fecha_venta, 
                c.cod_cliente, 
                c.nombre_comercial, 
                hvc.cod_seccion, 
                s.nombre
        ) as TotalQuery
    ";
    $result_count = odbc_exec($conn, $sql_count);
    if (!$result_count || !odbc_fetch_row($result_count)) {
        die("Error en la consulta de conteo: " . odbc_errormsg($conn));
    }

    $totalRecords = (int)odbc_result($result_count, "total");
    $totalPages = (int)ceil($totalRecords / $resultsPerPage);

    return array(
        'conn' => $conn,
        'cliente_filtro' => $cliente_filtro,
        'orden' => $orden,
        'direccion' => $direccion,
        'direccion_invertida' => $direccion_invertida,
        'page' => $page,
        'pedidos' => $pedidos,
        'totalRecords' => $totalRecords,
        'totalPages' => $totalPages,
    );
}

function pedidosTodosObtenerCodVendedor($conn) {
    if (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '') {
        return (string)$_SESSION['codigo'];
    }

    $sql_cod_vendedor = "
        SELECT cod_vendedor 
        FROM cmf_vendedores_user 
        WHERE email = '" . addslashes($_SESSION['email']) . "'
    ";
    $result_vendedor = odbc_exec($conn, $sql_cod_vendedor);
    if (!$result_vendedor || !odbc_fetch_row($result_vendedor)) {
        die("Error: No se pudo determinar el codigo de vendedor.");
    }

    return odbc_result($result_vendedor, "cod_vendedor");
}

function pedidosTodosConstruirWhereConditions($cod_vendedor, $cliente_filtro) {
    $whereConditions = array();
    $whereConditions[] = "hvl.tipo_venta = 1";
    $whereConditions[] = "hvc.tipo_venta = 1";
    $whereConditions[] = "hvc.historico = 'N'";
    $whereConditions[] = "((hvc.importe >= 0 AND hvl.cantidad > ISNULL(elv.cantidad_servida, 0)) OR (hvc.importe < 0 AND hvl.cantidad < 0 AND ABS(hvl.cantidad) > ABS(ISNULL(elv.cantidad_servida, 0))))";

    if (!is_null($cod_vendedor)) {
        $whereConditions[] = "c.cod_vendedor = '" . addslashes($cod_vendedor) . "'";
    }
    if ($cliente_filtro !== '') {
        $cliente_filtro_esc = addslashes($cliente_filtro);
        $whereConditions[] = "(c.cod_cliente LIKE '%{$cliente_filtro_esc}%' OR c.nombre_comercial LIKE '%{$cliente_filtro_esc}%')";
    }

    return $whereConditions;
}

function pedidosTodosAdjuntarResumen($pedido, $historicos, $entregas, $importes) {
    $pedidoId = (string)$pedido['Pedido'];
    $importePedido = isset($importes[$pedidoId]) ? $importes[$pedidoId] : array(
        'Importe_Disponible' => 0.0,
        'Importe_Pdte_Recibir' => 0.0,
    );

    $pedido['Importe_Disponible'] = $importePedido['Importe_Disponible'];
    $pedido['Importe_Pdte_Recibir'] = $importePedido['Importe_Pdte_Recibir'];
    $pedido['importeDisponibleTotal'] = $importePedido['Importe_Disponible'];
    $pedido['importePdteRecibirTotal'] = $importePedido['Importe_Pdte_Recibir'];
    $pedido['EsHistorico'] = !empty($historicos[$pedidoId]);
    $pedido['isHistorico'] = $pedido['EsHistorico'];
    $pedido['TieneEntrega'] = !empty($entregas[$pedidoId]);
    $pedido['camionIcon'] = $pedido['TieneEntrega'] ? '<i class="fas fa-truck text-success"></i>' : '';
    $pedido['rowClass'] = pedidosTodosConstruirRowClass($pedido);

    return $pedido;
}

function pedidosTodosExtraerIds($pedidos) {
    $ids = array();
    foreach ($pedidos as $pedido) {
        if (!isset($pedido['Pedido'])) {
            continue;
        }
        $ids[] = (string)$pedido['Pedido'];
    }

    return array_values(array_unique($ids));
}

function pedidosTodosConstruirListaIdsSql($pedidoIds) {
    if (empty($pedidoIds)) {
        return '';
    }

    $ids = array();
    foreach ($pedidoIds as $pedidoId) {
        $ids[] = "'" . addslashes((string)$pedidoId) . "'";
    }

    return implode(',', $ids);
}

function pedidosTodosCargarHistoricos($conn, $pedidoIds) {
    if (empty($pedidoIds)) {
        return array();
    }

    $idsSql = pedidosTodosConstruirListaIdsSql($pedidoIds);
    $sql = "
        SELECT cod_venta
        FROM cmf_solicitudes_pedido
        WHERE tipo_solicitud = 'Historico'
          AND cod_venta IN ($idsSql)
    ";
    $result = odbc_exec($conn, $sql);
    if (!$result) {
        die("Error al obtener historicos: " . odbc_errormsg($conn));
    }

    $historicos = array();
    while ($row = odbc_fetch_array($result)) {
        $historicos[(string)$row['cod_venta']] = true;
    }

    return $historicos;
}

function pedidosTodosCargarEntregas($conn, $pedidoIds) {
    if (empty($pedidoIds)) {
        return array();
    }

    $idsSql = pedidosTodosConstruirListaIdsSql($pedidoIds);
    $sql = "
        SELECT DISTINCT cod_venta_origen
        FROM integral.dbo.entrega_lineas_venta
        WHERE tipo_venta_origen = 1
          AND cod_venta_origen IN ($idsSql)
    ";
    $result = odbc_exec($conn, $sql);
    if (!$result) {
        die("Error al obtener entregas: " . odbc_errormsg($conn));
    }

    $entregas = array();
    while ($row = odbc_fetch_array($result)) {
        $entregas[(string)$row['cod_venta_origen']] = true;
    }

    return $entregas;
}

function pedidosTodosCargarImportes($conn, $pedidoIds) {
    if (empty($pedidoIds)) {
        return array();
    }

    $idsSql = pedidosTodosConstruirListaIdsSql($pedidoIds);
    $sql = "
        SELECT
            q.cod_venta,
            SUM(q.importe_disponible_linea) AS Importe_Disponible,
            SUM(q.importe_pdte_recibir_linea) AS Importe_Pdte_Recibir
        FROM (
            SELECT
                l.cod_venta,
                l.cantidad_restante,
                l.price_unit,
                l.pdte_recibir,
                CASE
                    WHEN l.stock_disponible < 0 THEN 0
                    ELSE l.stock_disponible
                END AS stock_ajustado,
                CASE
                    WHEN (
                        CASE
                            WHEN l.stock_disponible < 0 THEN 0
                            ELSE l.stock_disponible
                        END
                    ) < l.cantidad_restante THEN (
                        CASE
                            WHEN l.stock_disponible < 0 THEN 0
                            ELSE l.stock_disponible
                        END
                    )
                    ELSE l.cantidad_restante
                END * l.price_unit AS importe_disponible_linea,
                CASE
                    WHEN l.pdte_recibir >= (
                        l.cantidad_restante - CASE
                            WHEN (
                                CASE
                                    WHEN l.stock_disponible < 0 THEN 0
                                    ELSE l.stock_disponible
                                END
                            ) < l.cantidad_restante THEN (
                                CASE
                                    WHEN l.stock_disponible < 0 THEN 0
                                    ELSE l.stock_disponible
                                END
                            )
                            ELSE l.cantidad_restante
                        END
                    ) THEN (
                        l.cantidad_restante - CASE
                            WHEN (
                                CASE
                                    WHEN l.stock_disponible < 0 THEN 0
                                    ELSE l.stock_disponible
                                END
                            ) < l.cantidad_restante THEN (
                                CASE
                                    WHEN l.stock_disponible < 0 THEN 0
                                    ELSE l.stock_disponible
                                END
                            )
                            ELSE l.cantidad_restante
                        END
                    ) * l.price_unit
                    ELSE 0
                END AS importe_pdte_recibir_linea
            FROM (
                SELECT
                    hvl.cod_venta,
                    (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS cantidad_restante,
                    hvl.precio,
                    ISNULL(
                        (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
                         FROM integral.dbo.stocks s
                         WHERE s.cod_articulo = hvl.cod_articulo),
                        0
                    ) + (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS stock_disponible,
                    ISNULL(
                        (SELECT TOP 1 s.cantidad_pendiente_recibir
                         FROM integral.dbo.stocks s
                         WHERE s.cod_articulo = hvl.cod_articulo),
                        0
                    ) AS pdte_recibir,
                    CASE
                        WHEN (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) > 0
                             AND ((hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) * hvl.precio) > 0
                            THEN (((hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) * hvl.precio) / (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)))
                        ELSE 0
                    END AS price_unit
                FROM integral.dbo.hist_ventas_linea hvl
                LEFT JOIN integral.dbo.entrega_lineas_venta elv
                    ON hvl.cod_venta = elv.cod_venta_origen
                   AND hvl.linea = elv.linea_origen
                WHERE hvl.tipo_venta = 1
                  AND hvl.cod_venta IN ($idsSql)
                GROUP BY hvl.cod_venta, hvl.cantidad, hvl.precio, hvl.cod_articulo
            ) l
        ) q
        GROUP BY q.cod_venta
    ";
    $result = odbc_exec($conn, $sql);
    if (!$result) {
        die("Error al obtener importes agrupados: " . odbc_errormsg($conn));
    }

    $importes = array();
    while ($row = odbc_fetch_array($result)) {
        $importes[(string)$row['cod_venta']] = array(
            'Importe_Disponible' => isset($row['Importe_Disponible']) ? (float)$row['Importe_Disponible'] : 0.0,
            'Importe_Pdte_Recibir' => isset($row['Importe_Pdte_Recibir']) ? (float)$row['Importe_Pdte_Recibir'] : 0.0,
        );
    }

    return $importes;
}

function pedidosTodosConstruirRowClass($pedido) {
    $rowClass = ((float)$pedido['Importe'] < 0) ? 'text-danger' : '';

    if (!empty($pedido['EsHistorico'])) {
        $rowClass .= ($rowClass ? ' ' : '') . 'historico';
    }
    if ((float)$pedido['Importe_Pendiente'] > 70) {
        $rowClass .= ($rowClass ? ' ' : '') . 'high-pending-row';
    }
    if ((float)$pedido['Importe_Disponible'] > 70) {
        $rowClass .= ($rowClass ? ' ' : '') . 'high-disponible-row';
    }

    return $rowClass;
}
