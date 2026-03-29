<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Support/statistics.php';

if (!function_exists('obtenerCodVendedorUsuario')) {
    function obtenerCodVendedorUsuario($conn, string $email): ?string
    {
        $email = trim($email);
        if ($email === '' || !$conn) {
            return null;
        }

        $sql = "
            SELECT TOP 1 cod_vendedor
            FROM cmf_vendedores_user
            WHERE email = ?
        ";
        $params = [$email];
        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerCodVendedorUsuario.exec', $conn, $sql, $params);
            return null;
        }

        $row = odbc_fetch_array_utf8($rs);
        if (!$row) {
            return null;
        }

        $codRaw = null;
        foreach ($row as $k => $v) {
            if (strtolower((string)$k) === 'cod_vendedor') {
                $codRaw = $v;
                break;
            }
        }
        if ($codRaw === null) {
            return null;
        }

        $cod = trim((string)$codRaw);
        if ($cod === '' || $cod === '0') {
            return null;
        }
        return $cod;
    }
}

if (!function_exists('existeComisionistaEnSistema')) {
    function existeComisionistaEnSistema($conn, string $codigo): bool
    {
        $codigo = trim($codigo);
        if ($codigo === '' || !$conn) {
            return false;
        }

        $sql = "
            SELECT TOP 1 1 AS existe
            FROM vendedores
            WHERE CAST(cod_vendedor AS VARCHAR(50)) = ?
        ";
        $params = [$codigo];
        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('existeComisionistaEnSistema.exec', $conn, $sql, $params);
            return false;
        }
        return (bool)odbc_fetch_array_utf8($rs);
    }
}

if (!function_exists('resolverContextoFiltros')) {
    function resolverContextoFiltros($conn, array $session, array $query): array
    {
        $hoy = date('Y-m-d');
        $inicioAnio = date('Y') . '-01-01';

        $desde = normalizarFechaIso((string)($query['f_desde'] ?? ''), $inicioAnio);
        $hasta = normalizarFechaIso((string)($query['f_hasta'] ?? ''), $hoy);
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $emailSesion = trim((string)($session['email'] ?? ''));
        $codigoSesion = trim((string)($session['codigo'] ?? ''));
        $esAdmin = function_exists('esAdmin') ? (bool)esAdmin() : false;
        $codVendedorUsuario = obtenerCodVendedorUsuario($conn, $emailSesion);
        if ($codVendedorUsuario !== null && !existeComisionistaEnSistema($conn, $codVendedorUsuario)) {
            $codVendedorUsuario = null;
        }
        $usuarioConVendedorFijo = !$esAdmin;
        $puedeElegirComercial = !$usuarioConVendedorFijo;

        $codComisionistaSesion = null;
        if ($codigoSesion !== '' && ctype_digit($codigoSesion) && existeComisionistaEnSistema($conn, $codigoSesion)) {
            $codComisionistaSesion = $codigoSesion;
        }

        $codComisionistaActivo = null;
        $tipoFiltroComercial = 'todos';
        $valorFiltroComercial = '';
        $desdeSql = $desde;
        $hastaMasUnoSql = sumarDiasFechaIso($hasta, 1);

        if (!$puedeElegirComercial) {
            $codComisionistaActivo = $codComisionistaSesion;
            if ($codComisionistaActivo !== null && $codComisionistaActivo !== '' && ctype_digit($codComisionistaActivo)) {
                $tipoFiltroComercial = 'cod_comisionista';
                $valorFiltroComercial = $codComisionistaActivo;
            }
        } else {
            $codComisionistaGet = trim((string)($query['cod_comisionista'] ?? ''));
            if ($codComisionistaGet !== '' && ctype_digit($codComisionistaGet) && existeComisionistaEnSistema($conn, $codComisionistaGet)) {
                $codComisionistaActivo = $codComisionistaGet;
                $tipoFiltroComercial = 'cod_comisionista';
                $valorFiltroComercial = $codComisionistaGet;
            }
        }

        $contexto = [
            'es_admin' => $esAdmin,
            'puede_elegir_comercial' => $puedeElegirComercial,
            'cod_comisionista_activo' => $codComisionistaActivo,
            'cod_comisionista' => $codComisionistaActivo,
            'cod_vendedor_usuario' => $codVendedorUsuario,
            'tipo_filtro_comercial' => $tipoFiltroComercial,
            'valor_filtro_comercial' => $valorFiltroComercial,
            'f_desde' => $desde,
            'f_hasta' => $hasta,
            'f_desde_sql' => $desdeSql,
            'f_hasta_sql' => $hasta,
            'f_hasta_mas_uno_sql' => $hastaMasUnoSql,
        ];

        $marca = trim((string)($query['filtro_marca'] ?? ($query['marca'] ?? '')));

        if ($marca !== '') {
            $contexto['marca'] = $marca;
            $contexto['filtro_marca'] = $marca;
        } else {
            $contexto['marca'] = null;
            $contexto['filtro_marca'] = null;
        }

        $familia = trim((string)($query['filtro_familia'] ?? ($query['familia'] ?? '')));
        $contexto['familia'] = ($familia !== '') ? $familia : null;
        $contexto['filtro_familia'] = ($familia !== '') ? $familia : null;

        $subfamilia = trim((string)($query['filtro_subfamilia'] ?? ($query['subfamilia'] ?? '')));
        $contexto['subfamilia'] = ($subfamilia !== '') ? $subfamilia : null;
        $contexto['filtro_subfamilia'] = ($subfamilia !== '') ? $subfamilia : null;

        $articulo = trim((string)($query['filtro_articulo'] ?? ($query['articulo'] ?? ($query['cod_articulo'] ?? ''))));
        $contexto['articulo'] = ($articulo !== '') ? $articulo : null;
        $contexto['filtro_articulo'] = ($articulo !== '') ? $articulo : null;

        $cliente = trim((string)($query['filtro_cliente'] ?? ($query['cliente'] ?? ($query['cod_cliente'] ?? ''))));
        $contexto['cliente'] = ($cliente !== '') ? $cliente : null;
        $contexto['filtro_cliente'] = ($cliente !== '') ? $cliente : null;

        return $contexto;
    }
}

if (!function_exists('obtenerOpcionesFiltroVentas')) {
    function obtenerOpcionesFiltroVentas($conn, array $contexto, string $filtro): array
    {
        if (!$conn) {
            return [];
        }

        $filtro = strtolower(trim($filtro));
        $definiciones = obtenerDefinicionCamposFiltrosVentas();
        if (!isset($definiciones[$filtro])) {
            return [];
        }

        $def = $definiciones[$filtro];
        $campoSalida = (string)$def['output_field'];
        $orderSql = (string)$def['order_sql'];

        [$sqlBase, $paramsBase] = construirSqlBaseFiltrosVentas($contexto);
        [$whereCruce, $paramsCruce] = construirWhereFiltrosVentas($contexto, $filtro);

        if ($filtro === 'familia') {
            $sql = "
                SELECT DISTINCT
                    base_fcv.familia AS valor,
                    base_fcv.familia_nombre AS texto
                FROM (
                    " . $sqlBase . "
                ) base_fcv
                WHERE 1=1
                  AND base_fcv.familia IS NOT NULL
                  AND LTRIM(RTRIM(base_fcv.familia)) <> ''
            ";
        } elseif ($filtro === 'subfamilia') {
            $sql = "
                SELECT DISTINCT
                    base_fcv.subfamilia AS valor,
                    base_fcv.subfamilia_nombre AS texto
                FROM (
                    " . $sqlBase . "
                ) base_fcv
                WHERE 1=1
                  AND base_fcv.subfamilia IS NOT NULL
                  AND LTRIM(RTRIM(base_fcv.subfamilia)) <> ''
            ";
        } else {
            $sql = "
                SELECT DISTINCT
                    base_fcv." . $campoSalida . " AS valor
                FROM (
                    " . $sqlBase . "
                ) base_fcv
                WHERE 1=1
                  AND base_fcv." . $campoSalida . " IS NOT NULL
                  AND LTRIM(RTRIM(base_fcv." . $campoSalida . ")) <> ''
            ";
        }
        if ($whereCruce !== '') {
            $sql .= "
              AND " . $whereCruce . "
            ";
        }
        $sql .= "
            ORDER BY " . $orderSql . "
        ";

        $params = array_merge($paramsBase, $paramsCruce);
        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerOpcionesFiltroVentas.exec', $conn, $sql, $params);
            return [];
        }

        $opciones = [];
        while ($row = odbc_fetch_array_utf8($rs)) {
            $valor = trim((string)($row['valor'] ?? ''));
            if ($valor === '') {
                continue;
            }
            if ($filtro === 'familia' || $filtro === 'subfamilia') {
                $texto = trim((string)($row['texto'] ?? ''));
                $opciones[] = [
                    'valor' => $valor,
                    'texto' => ($texto !== '' ? $texto : $valor),
                ];
            } else {
                $opciones[] = $valor;
            }
        }

        return $opciones;
    }
}

/*
DATASET AGREGADO POR PEDIDO

Granularidad:
1 fila = 1 pedido.

Usado únicamente para
resúmenes o análisis por pedido.
*/
/*
DATASET DE SERVICIO POR PEDIDO

Granularidad:
1 fila = 1 pedido.

Clave logica:
cod_empresa + cod_caja + cod_venta.

Uso:
dataset orientado a KPIs agregados por pedido.
El dataset base por linea es obtenerDatasetServicioLineas().
*/
if (!function_exists('obtenerDatasetServicioPedidos')) {
    function obtenerDatasetServicioPedidos($conn, array $contexto): array
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

if (!function_exists('obtenerDatasetLineasPendientes')) {
    function obtenerDatasetLineasPendientes($conn, array $contexto): array
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

/*
DATASET BASE DE SERVICIO

Granularidad:
1 fila = 1 línea de pedido.

Este dataset es la fuente de verdad para
todos los KPIs de servicio del panel.
*/
/*
Contrato tecnico base:
- granularidad real: 1 fila por linea de pedido.
- clave logica de negocio para analitica: cod_cliente + cod_seccion + cod_articulo.
- pendiente (binario) se mantiene por compatibilidad legacy.
- cantidad_pendiente se expone para KPIs futuros.
*/
if (!function_exists('obtenerDatasetServicioLineas')) {
    function obtenerDatasetServicioLineas($conn, array $contexto): array
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

/* DOCUMENTOS */
if (!function_exists('obtenerResumenDocumentosSeparados')) {
    function obtenerResumenDocumentosSeparados($conn, array $contexto): array
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

if (!function_exists('construirSqlDocsBase')) {
    function construirSqlDocsBase(array $contexto, array $opts = []): array
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

if (!function_exists('construirSqlDocsFiltrados')) {
    function construirSqlDocsFiltrados(array $contexto, array $opts = []): array
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

if (!function_exists('obtenerResumenAlbaranesVentasConYSinPedido')) {
    function obtenerResumenAlbaranesVentasConYSinPedido($conn, array $contexto): array
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

if (!function_exists('obtenerResumenAlbaranesAbonoConYSinPedido')) {
    function obtenerResumenAlbaranesAbonoConYSinPedido($conn, array $contexto): array
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

if (!function_exists('obtenerCheckCabeceraVsLineasAB')) {
    function obtenerCheckCabeceraVsLineasAB($conn, array $contexto, array $opts = []): array
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

if (!function_exists('obtenerForenseDocumentoPedidoDebug')) {
    function obtenerForenseDocumentoPedidoDebug($conn, array $contexto, string $codVenta): array
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

if (!function_exists('obtenerDescuadreCabeceraVsLineas')) {
    function obtenerDescuadreCabeceraVsLineas($conn, $codComisionista, $fechaDesde, $fechaHasta, $opts = []): array
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

if (!function_exists('obtenerKpiServicioPedidos')) {
    function construirSqlBaseServicioPedidosCTE(): string
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

    function obtenerKpiServicioPedidos($conn, array $contexto): array
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

if (!function_exists('obtenerKpiServicioPedidosAjustado')) {
    function obtenerKpiServicioPedidosAjustado($conn, array $contexto): array
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

if (!function_exists('obtenerKpiServicioPedidosUnified')) {
    function obtenerKpiServicioPedidosUnified($conn, array $contexto, array $opciones = []): array
    {
        $modo = strtolower(trim((string)($opciones['modo'] ?? 'operativo')));
        if ($modo === 'documental') {
            return obtenerKpiServicioPedidos($conn, $contexto);
        }
        return obtenerKpiServicioPedidosAjustado($conn, $contexto);
    }
}

if (!function_exists('agruparDetalleServicioPedidosDesdeMapa')) {
    function agruparDetalleServicioPedidosDesdeMapa(array $detallePedidosMap): array
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

if (!function_exists('obtenerDetalleServicioPedidos')) {
    function obtenerDetalleServicioPedidos($conn, array $contexto, ?int $limit = null, ?int $offset = null): array
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

if (!function_exists('obtenerOpcionesComercialesVentas')) {
    function obtenerOpcionesComercialesVentas($conn, array $contexto): array
    {
        if (!$conn) {
            return [];
        }

        $fDesde = trim((string)($contexto['f_desde_sql'] ?? ''));
        $fHastaMasUno = trim((string)($contexto['f_hasta_mas_uno_sql'] ?? ''));
        if (
            $fDesde === '' ||
            $fHastaMasUno === '' ||
            preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $fDesde) !== 1 ||
            preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $fHastaMasUno) !== 1
        ) {
            [$fDesde, $fHastaMasUno] = obtenerRangoFechasContextoSql($contexto);
        }

        $params = [$fDesde, $fHastaMasUno];

        $marca = trim((string)($contexto['filtro_marca'] ?? ($contexto['marca'] ?? '')));
        $whereMarcaSql = '';
        if ($marca !== '') {
            $whereMarcaSql = "
              AND EXISTS (
                    SELECT 1
                    FROM hist_ventas_linea l
    INNER JOIN articulos a
                        ON a.cod_articulo = l.cod_articulo
                    WHERE l.cod_venta = c.cod_venta
                      AND l.tipo_venta = c.tipo_venta
                      AND l.cod_empresa = c.cod_empresa
                      AND l.cod_caja = c.cod_caja
                      AND LTRIM(RTRIM(a.marca)) = ?
                )";
            $params[] = $marca;
        }

        $sql = "
            SELECT DISTINCT
                CAST(c.cod_comisionista AS VARCHAR(50)) AS cod_comisionista,
                CASE
                    WHEN v.nombre IS NOT NULL AND LTRIM(RTRIM(v.nombre)) <> ''
                        THEN v.nombre
                    ELSE CAST(c.cod_comisionista AS VARCHAR(50))
                END AS nombre
            FROM hist_ventas_cabecera c
            LEFT JOIN vendedores v
                ON v.cod_vendedor = c.cod_comisionista
            WHERE
              c.cod_comisionista <> 0
              AND c.tipo_venta IN (1,2)
              AND c.fecha_venta >= ?
              AND c.fecha_venta < ?
              " . $whereMarcaSql . "
            ORDER BY nombre
        ";

        $rs = estadisticasOdbcExec($conn, $sql, $params);
        if (!$rs) {
            registrarErrorSqlEstadisticas('obtenerOpcionesComercialesVentas.exec', $conn, $sql, $params);
            return [];
        }

        $resultado = [];
        while ($row = odbc_fetch_array_utf8($rs)) {
            $cod = trim((string)($row['cod_comisionista'] ?? ''));
            if ($cod === '') {
                continue;
            }
            $resultado[] = [
                'cod_comisionista' => $cod,
                'nombre' => trim((string)($row['nombre'] ?? $cod)),
            ];
        }
        error_log('[DEBUG] obtenerOpcionesComercialesVentas RESULTADO: ' . json_encode($resultado));
        return $resultado;
    }
}

if (!function_exists('obtenerOpcionesMarcaVentas')) {
    function obtenerOpcionesMarcaVentas($conn, array $contexto): array
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

if (!function_exists('obtenerDetalleDiferenciaDocumental')) {
    function obtenerDetalleDiferenciaDocumental($conn, array $contexto): array
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

if (!function_exists('obtenerDetalleSegunVista')) {
    function obtenerDetalleSegunVista($conn, array $contexto, string $vista): array
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
