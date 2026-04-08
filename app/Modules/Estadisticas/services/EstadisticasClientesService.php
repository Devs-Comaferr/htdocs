<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasHelper.php';

if (!function_exists('__estadisticas_impl_obtenerKpiClientesConBacklog')) {
    function __estadisticas_impl_obtenerKpiClientesConBacklog(array $dataset): array
    {
        $clientesConBacklog = [];

        foreach ($dataset as $fila) {
            $cantidadPendiente = (float)($fila['cantidad_pendiente'] ?? 0);
            if ($cantidadPendiente <= 0) {
                continue;
            }

            $codCliente = trim((string)($fila['cod_cliente'] ?? ''));
            if ($codCliente === '') {
                continue;
            }
            $clientesConBacklog[$codCliente] = true;
        }

        return [
            'clientes_con_backlog' => count($clientesConBacklog),
        ];
    }
}


if (!function_exists('__estadisticas_impl_obtenerCodVendedorUsuario')) {
    function __estadisticas_impl_obtenerCodVendedorUsuario($conn, string $email): ?string
    {
        $email = trim($email);
        if ($email === '' || !$conn) {
            return null;
        }

        $sql = "
            SELECT TOP 1 cod_vendedor
            FROM cmf_comerciales_app_usuarios
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


if (!function_exists('__estadisticas_impl_existeComisionistaEnSistema')) {
    function __estadisticas_impl_existeComisionistaEnSistema($conn, string $codigo): bool
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


if (!function_exists('__estadisticas_impl_resolverContextoFiltros')) {
    function __estadisticas_impl_resolverContextoFiltros($conn, array $session, array $query): array
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


if (!function_exists('__estadisticas_impl_obtenerOpcionesFiltroVentas')) {
    function __estadisticas_impl_obtenerOpcionesFiltroVentas($conn, array $contexto, string $filtro): array
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


if (!function_exists('__estadisticas_impl_obtenerOpcionesComercialesVentas')) {
    function __estadisticas_impl_obtenerOpcionesComercialesVentas($conn, array $contexto): array
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
