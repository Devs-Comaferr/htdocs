<?php

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap/init.php';
}

function pedidoObtenerFiltros(array $request): array
{
    if (!isset($request['cod_cliente']) || $request['cod_cliente'] === '') {
        throw new RuntimeException("El parámetro 'cod_cliente' es obligatorio.");
    }

    if (!isset($request['pedido']) || $request['pedido'] === '') {
        throw new RuntimeException("El parámetro 'pedido' es obligatorio.");
    }

    $ordenPermitido = array(
        'pedido'                     => 'pedido',
        'Fecha_Venta'                => 'Fecha_Venta',
        'Articulo'                   => 'Articulo',
        'Descripcion'                => 'Descripcion',
        'Cantidad_Pedida'            => 'Cantidad_Pedida',
        'Cantidad_Servida'           => 'Cantidad_Servida',
        'Cantidad_Restante'          => 'Cantidad_Restante',
        'Stock'                      => 'Stock',
        'Cantidad_Pendiente_Recibir' => 'Cantidad_Pendiente_Recibir',
        'Importe_Restante'           => 'Importe_Restante',
    );

    $orden = isset($request['orden']) && array_key_exists($request['orden'], $ordenPermitido) ? $request['orden'] : 'Fecha_Venta';
    $direccion = isset($request['direccion']) && in_array($request['direccion'], array('ASC', 'DESC'), true) ? $request['direccion'] : 'DESC';

    return array(
        'tabla_param' => isset($request['tabla']) ? (string) $request['tabla'] : '',
        'cod_cliente' => (string) $request['cod_cliente'],
        'cod_seccion' => isset($request['cod_seccion']) ? (string) $request['cod_seccion'] : null,
        'numero_pedido' => (string) $request['pedido'],
        'orden' => $orden,
        'direccion' => $direccion,
        'direccion_invertida' => ($direccion === 'ASC') ? 'DESC' : 'ASC',
        'orden_permitido' => $ordenPermitido,
    );
}

function pedidoConstruirSqlLineas(array $filtros): string
{
    $tablaParam = $filtros['tabla_param'];
    $codCliente = addslashes($filtros['cod_cliente']);
    $codSeccion = $filtros['cod_seccion'];
    $numeroPedido = addslashes($filtros['numero_pedido']);

    if ($tablaParam === 'vcelim') {
        $sql = "
        SELECT
            vlelim.cod_venta AS pedido,
            vcelim.fecha_venta AS Fecha_Venta,
            vlelim.cod_articulo AS Articulo,
            vlelim.descripcion AS Descripcion,
            vlelim.observacion AS Observacion,
            vlelim.linea AS Linea,
            vlelim.cantidad AS Cantidad_Pedida,
            ISNULL(SUM(elv.cantidad), 0) AS Cantidad_Servida,
            (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS Cantidad_Restante,
            vlelim.precio AS Precio,
            (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) * vlelim.precio AS Importe_Restante,
            vlelim.tipo_venta AS Tipo_Venta,
            ISNULL(
                (SELECT TOP 1 s.cantidad_pendiente_recibir
                 FROM integral.dbo.stocks s
                 WHERE s.cod_articulo = vlelim.cod_articulo), 0
            ) AS Cantidad_Pendiente_Recibir,
            ISNULL(
                (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
                 FROM integral.dbo.stocks s
                 WHERE s.cod_articulo = vlelim.cod_articulo), 0
            ) AS Stock,
            vcelim.cod_pedido_web AS CodPedidoWeb
        FROM
            integral.dbo.ventas_linea_elim vlelim
        INNER JOIN
            integral.dbo.ventas_cabecera_elim vcelim ON vcelim.cod_venta = vlelim.cod_venta
        LEFT JOIN
            integral.dbo.entrega_lineas_venta elv ON vlelim.cod_venta = elv.cod_venta_origen
            AND vlelim.linea = elv.linea_origen
        WHERE
            vcelim.cod_cliente = '{$codCliente}'
            AND vlelim.tipo_venta = 1
            AND vcelim.tipo_venta = 1";

        if ($codSeccion) {
            $sql .= " AND vcelim.cod_seccion = '" . addslashes($codSeccion) . "'";
        }

        $sql .= " AND vlelim.cod_venta = '{$numeroPedido}'
        GROUP BY
            vlelim.cod_venta,
            vcelim.fecha_venta,
            vlelim.cod_articulo,
            vlelim.descripcion,
            vlelim.observacion,
            vlelim.linea,
            vlelim.cantidad,
            vlelim.precio,
            vlelim.tipo_venta,
            vcelim.cod_pedido_web
        HAVING
            (
                (vlelim.cantidad > ISNULL(SUM(elv.cantidad), 0))
                OR
                (vlelim.cantidad < 0 AND ABS(vlelim.cantidad) > ABS(ISNULL(SUM(elv.cantidad), 0)))
            )";

        return $sql;
    }

    $sql = "
    SELECT
        hvl.cod_venta AS pedido,
        hvc.fecha_venta AS Fecha_Venta,
        hvl.cod_articulo AS Articulo,
        hvl.descripcion AS Descripcion,
        hvl.observacion AS Observacion,
        hvl.linea AS Linea,
        hvl.cantidad AS Cantidad_Pedida,
        ISNULL(SUM(elv.cantidad), 0) AS Cantidad_Servida,
        (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS Cantidad_Restante,
        hvl.precio AS Precio,
        (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) * hvl.precio AS Importe_Restante,
        hvl.tipo_venta AS Tipo_Venta,
        ISNULL(
            (SELECT TOP 1 s.cantidad_pendiente_recibir
             FROM integral.dbo.stocks s
             WHERE s.cod_articulo = hvl.cod_articulo), 0
        ) AS Cantidad_Pendiente_Recibir,
        ISNULL(
            (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
             FROM integral.dbo.stocks s
             WHERE s.cod_articulo = hvl.cod_articulo), 0
        ) AS Stock,
        hvc.historico AS Historico,
        hvc.cod_pedido_web AS CodPedidoWeb
    FROM
        integral.dbo.hist_ventas_linea hvl
    INNER JOIN
        integral.dbo.hist_ventas_cabecera hvc ON hvc.cod_venta = hvl.cod_venta
    LEFT JOIN
        integral.dbo.entrega_lineas_venta elv ON hvl.cod_venta = elv.cod_venta_origen
        AND hvl.linea = elv.linea_origen
    WHERE
        hvc.cod_cliente = '{$codCliente}'
        AND hvl.tipo_venta = 1
        AND hvc.tipo_venta = 1";

    if ($codSeccion) {
        $sql .= " AND hvc.cod_seccion = '" . addslashes($codSeccion) . "'";
    }

    $sql .= " AND hvl.cod_venta = '{$numeroPedido}'
    GROUP BY
        hvl.cod_venta,
        hvc.fecha_venta,
        hvl.cod_articulo,
        hvl.descripcion,
        hvl.observacion,
        hvl.linea,
        hvl.cantidad,
        hvl.precio,
        hvl.tipo_venta,
        hvc.historico,
        hvc.cod_pedido_web
    HAVING
        (
            (hvl.cantidad > ISNULL(SUM(elv.cantidad), 0))
            OR
            (hvl.cantidad < 0 AND ABS(hvl.cantidad) > ABS(ISNULL(SUM(elv.cantidad), 0)))
        )";

    return $sql;
}

function pedidoObtenerLineas($conn, array $filtros): array
{
    $sqlLineas = pedidoConstruirSqlLineas($filtros);
    $sqlLineas .= " ORDER BY " . $filtros['orden_permitido'][$filtros['orden']] . " " . $filtros['direccion'];

    $resultLineas = odbc_exec($conn, $sqlLineas);
    if (!$resultLineas) {
        throw new RuntimeException('Error en la consulta SQL: ' . odbc_errormsg($conn));
    }

    $lineas = array();
    $sumaTotal = 0.0;
    while ($linea = odbc_fetch_array($resultLineas)) {
        $lineas[] = $linea;
        $sumaTotal += isset($linea['Importe_Restante']) ? (float) $linea['Importe_Restante'] : 0.0;
    }

    return array($lineas, count($lineas), $sumaTotal);
}

function pedidoObtenerClienteSeccion($conn, string $codCliente, ?string $codSeccion): array
{
    $sql = "
        SELECT
            c.nombre_comercial AS nombre_cliente,
            COALESCE(s.nombre, 'Sin Sección') AS nombre_seccion
        FROM [integral].[dbo].[clientes] c
        LEFT JOIN [integral].[dbo].[secciones_cliente] s
            ON c.cod_cliente = s.cod_cliente
        WHERE c.cod_cliente = '" . addslashes($codCliente) . "'
    ";
    if ($codSeccion !== null) {
        $sql .= " AND s.cod_seccion = '" . addslashes($codSeccion) . "'";
    }

    $result = odbc_exec($conn, $sql);
    if (!$result) {
        throw new RuntimeException('Error al obtener datos del cliente y sección: ' . odbc_errormsg($conn));
    }

    $clienteSeccion = odbc_fetch_array($result);
    if (!$clienteSeccion) {
        return array('Cliente no encontrado', 'Sin sección');
    }

    return array(
        $clienteSeccion['nombre_cliente'] ?? 'Desconocido',
        $clienteSeccion['nombre_seccion'] ?? 'Sin sección',
    );
}

function pedidoExisteSolicitudHistorico($conn, string $numeroPedido): bool
{
    $sql = "SELECT TOP 1 id_solicitud FROM cmf_solicitudes_pedido WHERE cod_venta = '" . addslashes($numeroPedido) . "' AND tipo_solicitud = 'Historico'";
    $result = odbc_exec($conn, $sql);
    return (bool) ($result && odbc_fetch_row($result));
}

function obtenerDatosPedidoDetalle($conn, array $request): array
{
    $filtros = pedidoObtenerFiltros($request);
    list($lineas, $numLineas, $sumaTotal) = pedidoObtenerLineas($conn, $filtros);
    list($nombreCliente, $nombreSeccion) = pedidoObtenerClienteSeccion($conn, $filtros['cod_cliente'], $filtros['cod_seccion']);

    $pageTitle = $nombreCliente;
    if ($nombreSeccion !== 'Sin sección') {
        $pageTitle .= ' - ' . $nombreSeccion;
    }
    if ($filtros['numero_pedido'] !== '') {
        $pageTitle .= ' - #' . htmlspecialchars($filtros['numero_pedido']);
    }

    $baseUrl = basename($_SERVER['PHP_SELF']) . '?cod_cliente=' . urlencode($filtros['cod_cliente']) . '&pedido=' . urlencode($filtros['numero_pedido']);
    if ($filtros['cod_seccion']) {
        $baseUrl .= '&cod_seccion=' . urlencode($filtros['cod_seccion']);
    }
    if ($filtros['tabla_param'] === 'vcelim') {
        $baseUrl .= '&tabla=vcelim';
    }

    $urlFaltas = 'faltas.php?cod_cliente=' . urlencode($filtros['cod_cliente']);
    if (isset($filtros['cod_seccion']) && tieneValor($filtros['cod_seccion'])) {
        $urlFaltas .= '&cod_seccion=' . urlencode($filtros['cod_seccion']);
    }

    $headerButton = '<button type="button" style="background-color: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;" onclick="window.location.href=\'' . $urlFaltas . '\'">
        <i class="fas fa-book" style="margin-right: 5px;"></i> Faltas Reales
    </button>' . "\n";

    return array_merge($filtros, array(
        'lineas' => $lineas,
        'num_lineas' => $numLineas,
        'suma_total' => $sumaTotal,
        'nombre_cliente' => $nombreCliente,
        'nombre_seccion' => $nombreSeccion,
        'pageTitle' => $pageTitle,
        'base_url' => $baseUrl,
        'headerButton' => $headerButton,
        'ui_version' => 'bs5',
        'ui_requires_jquery' => false,
        'existeHistorico' => pedidoExisteSolicitudHistorico($conn, $filtros['numero_pedido']),
    ));
}
