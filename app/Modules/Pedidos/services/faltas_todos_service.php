<?php

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap/init.php';
}

function faltasTodosResolverCodVendedor($conn): ?string
{
    if (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '') {
        return (string) $_SESSION['codigo'];
    }

    $sql = "
        SELECT cod_vendedor
        FROM cmf_comerciales_app_usuarios
        WHERE email = '" . addslashes((string) ($_SESSION['email'] ?? '')) . "'
    ";
    $result = odbc_exec($conn, $sql);
    if (!$result || !odbc_fetch_row($result)) {
        return null;
    }

    return (string) odbc_result($result, 'cod_vendedor');
}

function faltasTodosBuildWhereConditions(?string $codVendedor, string $startDate, string $endDate, string $clienteFiltro): array
{
    $whereConditionsNormal = array(
        "hvl.tipo_venta = 1",
        "hvc.tipo_venta = 1",
        "hvc.historico = 'S'",
        "(hvl.cantidad > ISNULL(elv.cantidad_servida, 0))",
    );

    $whereConditionsElim = array(
        "vlelim.tipo_venta = 1",
        "vcelim.tipo_venta = 1",
        "(vlelim.cantidad > ISNULL(elv.cantidad_servida, 0))",
    );

    if (!is_null($codVendedor)) {
        $whereConditionsNormal[] = "c.cod_vendedor = '" . addslashes($codVendedor) . "'";
        $whereConditionsElim[] = "c.cod_vendedor = '" . addslashes($codVendedor) . "'";
    }

    $whereConditionsNormal[] = "hvc.fecha_venta >= CONVERT(DATETIME, '" . addslashes($startDate) . "', 120)";
    $whereConditionsNormal[] = "hvc.fecha_venta <= CONVERT(DATETIME, '" . addslashes($endDate) . "', 120)";
    $whereConditionsElim[] = "vcelim.fecha_venta >= CONVERT(DATETIME, '" . addslashes($startDate) . "', 120)";
    $whereConditionsElim[] = "vcelim.fecha_venta <= CONVERT(DATETIME, '" . addslashes($endDate) . "', 120)";

    if ($clienteFiltro !== '') {
        $clienteFiltroEsc = addslashes($clienteFiltro);
        $clienteCondition = "(c.cod_cliente LIKE '%{$clienteFiltroEsc}%' OR c.nombre_comercial LIKE '%{$clienteFiltroEsc}%')";
        $whereConditionsNormal[] = $clienteCondition;
        $whereConditionsElim[] = $clienteCondition;
    }

    return array($whereConditionsNormal, $whereConditionsElim);
}

function faltasTodosBuildBaseQueries(array $whereConditionsNormal, array $whereConditionsElim): array
{
    $sqlNormal = "
        SELECT
            hvl.cod_venta AS Pedido,
            hvc.fecha_venta AS Fecha_Pedido,
            c.cod_cliente AS cod_cliente,
            c.nombre_comercial AS Cliente,
            hvc.importe AS Importe,
            hvc.cod_seccion AS Cod_Seccion,
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
            'hvc' as tabla
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
        WHERE " . implode(" AND ", $whereConditionsNormal) . "
        GROUP BY
            hvl.cod_venta,
            hvc.fecha_venta,
            c.cod_cliente,
            c.nombre_comercial,
            hvc.importe,
            hvc.cod_seccion,
            s.nombre,
            hvc.cod_anexo,
            avc.observacion_interna
    ";

    $sqlElim = "
        SELECT
            vlelim.cod_venta AS Pedido,
            vcelim.fecha_venta AS Fecha_Pedido,
            c.cod_cliente AS cod_cliente,
            c.nombre_comercial AS Cliente,
            vcelim.importe AS Importe,
            vcelim.cod_seccion AS Cod_Seccion,
            COALESCE(s.nombre, '') AS Seccion,
            COUNT(vlelim.cod_articulo) AS Articulos_Pendientes,
            SUM(
                CASE
                    WHEN elv.cod_venta_origen IS NULL THEN vlelim.cantidad * vlelim.precio
                    ELSE (vlelim.cantidad - elv.cantidad_servida) * vlelim.precio
                END
            ) AS Importe_Pendiente,
            vcelim.cod_anexo,
            avc.observacion_interna AS ObservacionInterna,
            'vcelim' as tabla
        FROM
            integral.dbo.ventas_linea_elim vlelim
        INNER JOIN
            integral.dbo.ventas_cabecera_elim vcelim ON vcelim.cod_venta = vlelim.cod_venta
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
        ) elv ON vlelim.cod_venta = elv.cod_venta_origen AND vlelim.linea = elv.linea_origen
        LEFT JOIN
            integral.dbo.clientes c ON vcelim.cod_cliente = c.cod_cliente
        LEFT JOIN
            integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = vcelim.cod_seccion
        LEFT JOIN
            integral.dbo.anexo_ventas_cabecera avc ON vcelim.cod_anexo = avc.cod_anexo
        WHERE " . implode(" AND ", $whereConditionsElim) . "
        GROUP BY
            vlelim.cod_venta,
            vcelim.fecha_venta,
            c.cod_cliente,
            c.nombre_comercial,
            vcelim.importe,
            vcelim.cod_seccion,
            s.nombre,
            vcelim.cod_anexo,
            avc.observacion_interna
    ";

    return array($sqlNormal, $sqlElim);
}

function faltasTodosCalcularImportesPedido($conn, string $pedidoId): array
{
    $pedidoId = addslashes($pedidoId);
    $importeDisponibleTotal = 0.0;
    $importePdteRecibirTotal = 0.0;

    $sqlLineas = "
        SELECT
            hvl.cantidad AS Cantidad_Pedida,
            (hvl.cantidad - ISNULL(SUM(elv.cantidad),0)) AS Cantidad_Restante,
            hvl.precio AS Precio,
            ISNULL(
              (SELECT TOP 1 s.existencias
               FROM integral.dbo.stocks s
               WHERE s.cod_articulo = hvl.cod_articulo), 0
            ) AS Stock,
            ISNULL(
              (SELECT TOP 1 s.cantidad_pendiente_recibir
               FROM integral.dbo.stocks s
               WHERE s.cod_articulo = hvl.cod_articulo), 0
            ) AS PdteRecibir
        FROM integral.dbo.hist_ventas_linea hvl
        LEFT JOIN integral.dbo.entrega_lineas_venta elv
            ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
        WHERE hvl.cod_venta = '$pedidoId' AND hvl.tipo_venta = 1
        GROUP BY hvl.cantidad, hvl.precio, hvl.cod_articulo
    ";
    $resultLineas = odbc_exec($conn, $sqlLineas);
    if ($resultLineas) {
        while ($linea = odbc_fetch_array($resultLineas)) {
            $cantidadRestante = (float) $linea['Cantidad_Restante'];
            $precio = (float) $linea['Precio'];

            $importeRestanteLinea = $cantidadRestante * $precio;
            $priceUnit = $cantidadRestante > 0 ? ($importeRestanteLinea / $cantidadRestante) : 0;

            $stockDisponible = max((float) $linea['Stock'], 0);
            $servibleStock = min($stockDisponible, $cantidadRestante);
            $importeDisponibleLinea = $servibleStock * $priceUnit;

            $resto = $cantidadRestante - $servibleStock;
            $pdteRecibirValor = (float) $linea['PdteRecibir'];
            $pdteRecibirDisponible = min($resto, $pdteRecibirValor);
            $importePdteRecibirLinea = $pdteRecibirDisponible * $priceUnit;

            $importeDisponibleTotal += $importeDisponibleLinea;
            $importePdteRecibirTotal += $importePdteRecibirLinea;
        }
    }

    return array($importeDisponibleTotal, $importePdteRecibirTotal);
}

function faltasTodosResolverEstadoIcono($conn, array $pedido): string
{
    if (($pedido['tabla'] ?? '') === 'vcelim') {
        return '<i class="fas fa-trash-alt text-danger"></i>';
    }

    $queryEntrega = "
        SELECT TOP 1 cod_venta_origen
        FROM integral.dbo.entrega_lineas_venta
        WHERE cod_venta_origen = '" . addslashes((string) $pedido['Pedido']) . "'
          AND tipo_venta_origen = 1
    ";
    $rsEntrega = odbc_exec($conn, $queryEntrega);
    if ($rsEntrega && odbc_fetch_row($rsEntrega)) {
        return '<i class="fas fa-truck text-success"></i>';
    }

    return '';
}

function faltasTodosBuildPedidoUrl(array $pedido): string
{
    $params = array(
        'cod_cliente' => $pedido['cod_cliente'],
        'pedido' => $pedido['Pedido'],
    );

    if (isset($pedido['Cod_Seccion']) && $pedido['Cod_Seccion'] !== '' && $pedido['Cod_Seccion'] !== null) {
        $params['cod_seccion'] = $pedido['Cod_Seccion'];
    }

    if (($pedido['tabla'] ?? '') === 'vcelim') {
        $params['tabla'] = 'vcelim';
    }

    return 'pedido.php?' . http_build_query($params);
}

function obtenerDatosFaltasTodos($conn, array $request): array
{
    $codVendedor = faltasTodosResolverCodVendedor($conn);
    if ($codVendedor === null) {
        throw new RuntimeException('No se pudo determinar el código de vendedor.');
    }

    $defaultEnd = date('Y-m-d');
    $defaultStart = date('Y-m-d', strtotime('-15 days'));
    $startDate = isset($request['start_date']) ? (string) $request['start_date'] : $defaultStart;
    $endDate = isset($request['end_date']) ? (string) $request['end_date'] : $defaultEnd;
    $clienteFiltro = isset($request['cliente']) ? mb_convert_encoding((string) $request['cliente'], 'Windows-1252', 'UTF-8') : '';

    $ordenPermitido = array(
        'Pedido'               => 'Pedido',
        'Fecha_Pedido'         => 'Fecha_Pedido',
        'Cliente'              => 'Cliente',
        'Importe'              => 'Importe',
        'Articulos_Pendientes' => 'Articulos_Pendientes',
        'Importe_Pendiente'    => 'Importe_Pendiente',
        'Importe_Disponible'   => 'Importe_Disponible',
        'Importe_Pdte_Recibir' => 'Importe_Pdte_Recibir',
    );
    $orden = (isset($request['orden']) && isset($ordenPermitido[$request['orden']])) ? (string) $request['orden'] : 'Pedido';
    $direccion = (isset($request['direccion']) && ($request['direccion'] === 'ASC' || $request['direccion'] === 'DESC')) ? (string) $request['direccion'] : 'DESC';
    $direccionInvertida = ($direccion === 'ASC') ? 'DESC' : 'ASC';
    $sqlOrder = in_array($orden, array('Importe_Disponible', 'Importe_Pdte_Recibir'), true) ? 'Pedido' : $orden;

    $resultsPerPage = 30;
    $page = isset($request['page']) ? (int) $request['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    $offset = ($page - 1) * $resultsPerPage;

    list($whereConditionsNormal, $whereConditionsElim) = faltasTodosBuildWhereConditions($codVendedor, $startDate, $endDate, $clienteFiltro);
    list($sqlNormal, $sqlElim) = faltasTodosBuildBaseQueries($whereConditionsNormal, $whereConditionsElim);

    $sqlPedidos = "
        SELECT * FROM (
            $sqlNormal
            UNION ALL
            $sqlElim
        ) as combined
        ORDER BY $sqlOrder $direccion
        OFFSET $offset ROWS FETCH NEXT $resultsPerPage ROWS ONLY
    ";

    $resultPedidos = odbc_exec($conn, $sqlPedidos);
    if (!$resultPedidos) {
        throw new RuntimeException('Error en la consulta de faltas: ' . odbc_errormsg($conn));
    }

    $pedidos = array();
    while ($row = odbc_fetch_array($resultPedidos)) {
        $pedidos[] = $row;
    }

    foreach ($pedidos as &$pedido) {
        list($importeDisponible, $importePdteRecibir) = faltasTodosCalcularImportesPedido($conn, (string) $pedido['Pedido']);
        $pedido['Importe_Disponible'] = $importeDisponible;
        $pedido['Importe_Pdte_Recibir'] = $importePdteRecibir;
        $pedido['pedido_url'] = faltasTodosBuildPedidoUrl($pedido);
        $pedido['estado_icon'] = faltasTodosResolverEstadoIcono($conn, $pedido);
        $pedido['row_class'] = trim(
            ((float) $pedido['Importe_Pendiente'] > 70 ? ' high-pending-row' : '') .
            ($importeDisponible > 70 ? ' high-disponible-row' : '')
        );
        $pedido['mobile_item_class'] = trim(
            'mobile-item' .
            ((float) $pedido['Importe_Pendiente'] > 70 ? ' mobile-item-high-pending' : '') .
            ($importeDisponible > 70 ? ' mobile-item-high-disponible' : '')
        );
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

    $sqlCountNormal = "
        SELECT hvl.cod_venta
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
        WHERE " . implode(" AND ", $whereConditionsNormal) . "
        GROUP BY
            hvl.cod_venta,
            hvc.fecha_venta,
            c.cod_cliente,
            c.nombre_comercial,
            hvc.cod_seccion,
            s.nombre
    ";
    $sqlCountElim = "
        SELECT vlelim.cod_venta
        FROM
            integral.dbo.ventas_linea_elim vlelim
        INNER JOIN
            integral.dbo.ventas_cabecera_elim vcelim ON vcelim.cod_venta = vlelim.cod_venta
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
        ) elv ON vlelim.cod_venta = elv.cod_venta_origen AND vlelim.linea = elv.linea_origen
        LEFT JOIN
            integral.dbo.clientes c ON vcelim.cod_cliente = c.cod_cliente
        LEFT JOIN
            integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = vcelim.cod_seccion
        WHERE " . implode(" AND ", $whereConditionsElim) . "
        GROUP BY
            vlelim.cod_venta,
            vcelim.fecha_venta,
            c.cod_cliente,
            c.nombre_comercial,
            vcelim.cod_seccion,
            s.nombre
    ";
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM (
            $sqlCountNormal
            UNION ALL
            $sqlCountElim
        ) as TotalQuery
    ";
    $resultCount = odbc_exec($conn, $sqlCount);
    if (!$resultCount || !odbc_fetch_row($resultCount)) {
        throw new RuntimeException('Error en la consulta de conteo: ' . odbc_errormsg($conn));
    }

    $totalRecords = (int) odbc_result($resultCount, 'total');
    $totalPages = (int) ceil($totalRecords / $resultsPerPage);

    return array(
        'pedidos' => $pedidos,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'cliente_filtro' => $clienteFiltro,
        'orden' => $orden,
        'direccion' => $direccion,
        'direccion_invertida' => $direccionInvertida,
        'resultsPerPage' => $resultsPerPage,
        'page' => $page,
        'totalRecords' => $totalRecords,
        'totalPages' => $totalPages,
    );
}
