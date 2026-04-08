<?php

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap/init.php';
}

function clienteDetallesExecPrepared($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    if (!odbc_execute($stmt, $params)) {
        return false;
    }
    return $stmt;
}

function clienteDetallesSqlLiteral(string $value): string
{
    return str_replace("'", "''", $value);
}

function clienteDetallesObtenerClienteBase($conn, string $codCliente): array
{
    $sql = "
        SELECT
            c.cod_cliente,
            c.nombre_comercial,
            c.razon_social,
            c.cif,
            c.direccion1,
            c.CP,
            c.poblacion,
            c.provincia,
            c.telefono,
            c.telefono1_comentario,
            c.e_mail,
            c.cod_forma_liquidacion,
            c.advertencia,
            c.moroso,
            c.fecha_alta,
            c.cod_tarifa,
            c.cod_ruta,
            c.telefono2,
            c.telefono2_comentario,
            c.telefono3,
            c.telefono3_comentario,
            fl.descripcion AS forma_pago_descripcion
        FROM [integral].[dbo].[clientes] c
        LEFT JOIN [integral].[dbo].[formas_liquidacion] fl
          ON fl.cod_forma_liquidacion = c.cod_forma_liquidacion
        WHERE cod_cliente = ?
    ";
    $result = clienteDetallesExecPrepared($conn, $sql, [$codCliente]);
    if (!$result) {
        throw new RuntimeException('Error en consulta de cliente: ' . odbc_errormsg($conn));
    }

    $cliente = odbc_fetch_array($result);
    if (!$cliente) {
        throw new RuntimeException('Cliente no encontrado');
    }

    return $cliente;
}

function clienteDetallesObtenerFormaPago($conn, array $cliente): string
{
    $formaPagoDirecta = $cliente['forma_pago_descripcion'] ?? $cliente['FORMA_PAGO_DESCRIPCION'] ?? null;
    if ($formaPagoDirecta !== null && $formaPagoDirecta !== '') {
        return (string) $formaPagoDirecta;
    }

    $codFormaLiquidacion = $cliente['cod_forma_liquidacion'] ?? $cliente['COD_FORMA_LIQUIDACION'] ?? null;
    if ($codFormaLiquidacion === null || $codFormaLiquidacion === '') {
        return 'Desconocida';
    }

    $sql = "
        SELECT descripcion
        FROM [integral].[dbo].[formas_liquidacion]
        WHERE cod_forma_liquidacion = ?
    ";
    $result = clienteDetallesExecPrepared($conn, $sql, [$codFormaLiquidacion]);
    if (!$result) {
        return 'Desconocida';
    }

    $row = odbc_fetch_array($result);
    if (!$row) {
        return 'Desconocida';
    }

    return (string) ($row['descripcion'] ?? $row['DESCRIPCION'] ?? 'Desconocida');
}

function clienteDetallesObtenerTarifa($conn, array $cliente): string
{
    $codTarifa = trim((string)($cliente['cod_tarifa'] ?? $cliente['COD_TARIFA'] ?? ''));
    if ($codTarifa === '') {
        return 'Sin tarifa';
    }

    $sql = "
        SELECT descripcion
        FROM [integral].[dbo].[tarifas_venta_cabecera]
        WHERE cod_tarifa = ?
    ";
    $result = clienteDetallesExecPrepared($conn, $sql, [$codTarifa]);
    if (!$result) {
        return $codTarifa;
    }

    $row = odbc_fetch_array($result);
    if (!$row) {
        return $codTarifa;
    }

    $descripcion = trim((string)($row['descripcion'] ?? $row['DESCRIPCION'] ?? ''));
    if ($descripcion === '') {
        return $codTarifa;
    }

    return function_exists('toUTF8') ? toUTF8($descripcion) : $descripcion;
}

function clienteDetallesObtenerSecciones($conn, string $codCliente): array
{
    $sql = "
        SELECT cod_seccion, nombre
        FROM [integral].[dbo].[secciones_cliente]
        WHERE cod_cliente = ?
    ";
    $result = clienteDetallesExecPrepared($conn, $sql, [$codCliente]);
    if (!$result) {
        throw new RuntimeException('Error en consulta de secciones: ' . odbc_errormsg($conn));
    }

    $secciones = array();
    while ($seccion = odbc_fetch_array($result)) {
        $secciones[] = $seccion;
    }

    return $secciones;
}

function clienteDetallesObtenerContactos($conn, string $codCliente): array
{
    $sql = "
        SELECT TOP (1000)
            cod_contacto,
            nombre,
            departamento,
            cargo,
            telefono,
            fax,
            e_mail,
            www,
            aniversario,
            observaciones,
            autorizacion_venta,
            cif,
            facebook,
            twitter,
            whatsapp,
            telefono_movil,
            telefono_comentario,
            telefono_movil_comentario,
            numero_carne_compra_fitosanitarios,
            fecha_modificacion,
            codigo_ropo
        FROM [integral].[dbo].[contactos_cliente]
        WHERE cod_cliente = ?
    ";
    $result = clienteDetallesExecPrepared($conn, $sql, [$codCliente]);
    if (!$result) {
        return array();
    }

    $contactos = array();
    while ($row = odbc_fetch_array($result)) {
        $contactos[] = $row;
    }

    return $contactos;
}

function clienteDetallesObtenerAsignacion($conn, string $codCliente, ?string $codSeccion)
{
    $sql = "
        SELECT *
        FROM [integral].[dbo].[cmf_comerciales_clientes_zona]
        WHERE cod_cliente = ?
    ";
    $params = [$codCliente];
    if (is_null($codSeccion) || $codSeccion === '') {
        $sql .= " AND (cod_seccion IS NULL OR cod_seccion = '')";
    } else {
        $sql .= " AND cod_seccion = ?";
        $params[] = $codSeccion;
    }

    $result = clienteDetallesExecPrepared($conn, $sql, $params);
    return $result ? odbc_fetch_array($result) : false;
}

function clienteDetallesObtenerResumenPedido($conn, string $codPedido): array
{
    $res = [
        'fecha_venta' => null,
        'hora_venta' => null,
        'importe' => 0.0,
        'observacion_interna' => '',
        'numero_lineas' => 0,
        'pedido_eliminado' => 0,
        'eliminado_por_usuario' => '',
        'eliminado_por_equipo' => '',
        'eliminado_fecha' => null,
        'eliminado_hora' => null,
    ];

    $sqlCabElim = "
        SELECT TOP 1 *
        FROM [integral].[dbo].[ventas_cabecera_elim] vce
        WHERE vce.cod_venta = '" . clienteDetallesSqlLiteral($codPedido) . "'
          AND vce.tipo_venta = 1
        ORDER BY vce.fecha_venta DESC, vce.hora_venta DESC
    ";
    $cabElim = odbc_fetch_array(odbc_exec($conn, $sqlCabElim));

    if ($cabElim) {
        $res['fecha_venta'] = $cabElim['fecha_venta'] ?? $cabElim['FECHA_VENTA'] ?? null;
        $res['hora_venta'] = $cabElim['hora_venta'] ?? $cabElim['HORA_VENTA'] ?? null;
        $res['importe'] = (float) ($cabElim['importe'] ?? $cabElim['IMPORTE'] ?? 0);
        $res['pedido_eliminado'] = 1;

        $sqlLog = "
            SELECT TOP 1 la.cod_usuario, la.cod_estacion, la.fecha, la.hora
            FROM [integral].[dbo].[log_acciones] la
            WHERE la.tipo = 'B'
              AND la.categoria = 'V'
              AND la.cod_n3 = '" . clienteDetallesSqlLiteral($codPedido) . "'
            ORDER BY la.fecha DESC, la.hora DESC
        ";
        $log = odbc_fetch_array(odbc_exec($conn, $sqlLog));
        if ($log) {
            $res['eliminado_por_usuario'] = (string) ($log['cod_usuario'] ?? $log['COD_USUARIO'] ?? '');
            $res['eliminado_por_equipo'] = (string) ($log['cod_estacion'] ?? $log['COD_ESTACION'] ?? '');
            $res['eliminado_fecha'] = $log['fecha'] ?? $log['FECHA'] ?? null;
            $res['eliminado_hora'] = $log['hora'] ?? $log['HORA'] ?? null;
        }

        $sqlLineElim = "
            SELECT COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[ventas_linea_elim] vle
            WHERE vle.cod_venta = '" . clienteDetallesSqlLiteral($codPedido) . "'
              AND vle.tipo_venta = 1
        ";
        $rowLineElim = odbc_fetch_array(odbc_exec($conn, $sqlLineElim));
        $res['numero_lineas'] = (int) ($rowLineElim['numero_lineas'] ?? $rowLineElim['NUMERO_LINEAS'] ?? 0);

        return $res;
    }

    $sqlCabHist = "
        SELECT TOP 1 hvc.fecha_venta, hvc.hora_venta, hvc.importe, avc.observacion_interna
        FROM [integral].[dbo].[hist_ventas_cabecera] hvc
        LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] avc
          ON hvc.cod_anexo = avc.cod_anexo
        WHERE hvc.cod_venta = '" . clienteDetallesSqlLiteral($codPedido) . "'
          AND hvc.tipo_venta = 1
        ORDER BY hvc.fecha_venta DESC, hvc.hora_venta DESC
    ";
    $cabHist = odbc_fetch_array(odbc_exec($conn, $sqlCabHist));
    if ($cabHist) {
        $res['fecha_venta'] = $cabHist['fecha_venta'] ?? $cabHist['FECHA_VENTA'] ?? null;
        $res['hora_venta'] = $cabHist['hora_venta'] ?? $cabHist['HORA_VENTA'] ?? null;
        $res['importe'] = (float) ($cabHist['importe'] ?? $cabHist['IMPORTE'] ?? 0);
        $res['observacion_interna'] = (string) ($cabHist['observacion_interna'] ?? $cabHist['OBSERVACION_INTERNA'] ?? '');
    }

    $sqlLineHist = "
        SELECT COUNT(*) AS numero_lineas
        FROM [integral].[dbo].[hist_ventas_linea] hl
        WHERE hl.cod_venta = '" . clienteDetallesSqlLiteral($codPedido) . "'
          AND hl.tipo_venta = 1
    ";
    $rowLineHist = odbc_fetch_array(odbc_exec($conn, $sqlLineHist));
    $res['numero_lineas'] = (int) ($rowLineHist['numero_lineas'] ?? $rowLineHist['NUMERO_LINEAS'] ?? 0);

    return $res;
}

function clienteDetallesObtenerVisitas($conn, string $codCliente, ?string $codSeccion, ?string $codComercial, array $secciones): array
{
    $mostrarNuevaFuncionalidad = false;
    $visitas = array();

    if (((isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin')
        || (isset($_SESSION['perm_planificador']) && (int) $_SESSION['perm_planificador'] === 1))
        && (is_null($codSeccion) || $codSeccion === '')
        && count($secciones) <= 1
    ) {
        $mostrarNuevaFuncionalidad = true;

        $sqlVisitas = "
            SELECT
                v.id_visita,
                v.observaciones,
                v.estado_visita,
                v.fecha_visita,
                v.hora_inicio_visita,
                v.hora_fin_visita
            FROM [integral].[dbo].[cmf_comerciales_visitas] v
            WHERE v.cod_cliente = ?
            ORDER BY v.fecha_visita DESC
        ";
        $resultVisitas = clienteDetallesExecPrepared($conn, $sqlVisitas, [$codCliente]);
        if (!$resultVisitas) {
            throw new RuntimeException('Error en consulta de visitas: ' . odbc_errormsg($conn));
        }

        while ($visita = odbc_fetch_array($resultVisitas)) {
            $idVisita = (string) $visita['id_visita'];
            $sqlPedidoPrincipal = "
                SELECT TOP 1 vp.cod_venta, vp.origen
                FROM [integral].[dbo].[cmf_comerciales_visitas_pedidos] vp
                WHERE vp.id_visita = ?
                ORDER BY vp.id_visita_pedido ASC
            ";
            $resultPedidoPrincipal = clienteDetallesExecPrepared($conn, $sqlPedidoPrincipal, [$idVisita]);
            $pedidoPrincipal = $resultPedidoPrincipal ? odbc_fetch_array($resultPedidoPrincipal) : false;
            $origenPrincipal = isset($pedidoPrincipal['origen']) ? $pedidoPrincipal['origen'] : 'otros';
            $visita['color'] = determinarColorVisita($visita['estado_visita'], $origenPrincipal);

            $sqlPedidos = "
                SELECT
                    vp.cod_venta AS cod_pedido,
                    vp.origen
                FROM [integral].[dbo].[cmf_comerciales_visitas_pedidos] vp
                WHERE vp.id_visita = ?
                ORDER BY vp.id_visita_pedido ASC
            ";
            $resultPedidos = clienteDetallesExecPrepared($conn, $sqlPedidos, [$idVisita]);
            if ($resultPedidos) {
                $pedidos = array();
                $importeTotalVisita = 0.0;
                $numeroLineasTotalVisita = 0;

                while ($pedido = odbc_fetch_array($resultPedidos)) {
                    $resumenPedido = clienteDetallesObtenerResumenPedido($conn, (string) $pedido['cod_pedido']);
                    $pedido = array_merge($pedido, $resumenPedido);

                    if (empty($pedido['fecha_venta'])) {
                        $pedido['fecha_venta'] = $visita['fecha_visita'] ?? null;
                    }
                    if (empty($pedido['hora_venta'])) {
                        $pedido['hora_venta'] = $visita['hora_inicio_visita'] ?? null;
                    }

                    $importeTotalVisita += (float) ($pedido['importe'] ?? 0);
                    $numeroLineasTotalVisita += (int) ($pedido['numero_lineas'] ?? 0);
                    $pedidos[] = $pedido;
                }

                $visita['pedidos'] = $pedidos;
                $visita['importe_total'] = $importeTotalVisita;
                $visita['numero_lineas_total'] = $numeroLineasTotalVisita;
            } else {
                $visita['pedidos'] = array();
                $visita['importe_total'] = 0;
                $visita['numero_lineas_total'] = 0;
            }

            $visitas[] = $visita;
        }
    }

    return array($mostrarNuevaFuncionalidad, $visitas);
}

function clienteDetallesPaginarVisitas(array $visitas, array $request): array
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

if (!function_exists('execPrepared')) {
    function execPrepared($conn, string $sql, array $params = [])
    {
        return clienteDetallesExecPrepared($conn, $sql, $params);
    }
}

if (!function_exists('sqlLiteral')) {
    function sqlLiteral(string $value): string
    {
        return clienteDetallesSqlLiteral($value);
    }
}

if (!function_exists('obtenerResumenPedidoCliente')) {
    function obtenerResumenPedidoCliente($conn, string $codPedido): array
    {
        return clienteDetallesObtenerResumenPedido($conn, $codPedido);
    }
}
