<?php
declare(strict_types=1);

if (!function_exists('__estadisticas_impl_parsearFechaIsoEstricto')) {
    function __estadisticas_impl_parsearFechaIsoEstricto(string $valor): ?DateTimeImmutable
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$dt) {
            return null;
        }
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }
        return $dt;
    }
}


if (!function_exists('__estadisticas_impl_sumarDiasFechaIso')) {
    function __estadisticas_impl_sumarDiasFechaIso(string $fechaIso, int $dias): string
    {
        $dt = parsearFechaIsoEstricto($fechaIso);
        if (!$dt) {
            return $fechaIso;
        }
        $intervalo = ($dias >= 0 ? '+' : '') . $dias . ' day';
        return $dt->modify($intervalo)->format('Y-m-d');
    }
}


if (!function_exists('__estadisticas_impl_construirRangoFechasSql')) {
    function __estadisticas_impl_construirRangoFechasSql(string $campoFecha): string
    {
        return "
           AND $campoFecha >= ?
           AND $campoFecha <  ?
       ";
    }
}


if (!function_exists('__estadisticas_impl_normalizarFechaIso')) {
    function __estadisticas_impl_normalizarFechaIso(string $valor, string $fallback): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return $fallback;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m) === 1) {
            $dia = (int)$m[1];
            $mes = (int)$m[2];
            $anio = (int)$m[3];
            if (checkdate($mes, $dia, $anio)) {
                $valor = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            } else {
                return $fallback;
            }
        }
        $dt = parsearFechaIsoEstricto($valor);
        if (!$dt) {
            return $fallback;
        }
        $fecha = $dt->format('Y-m-d');
        if ($fecha < '1900-01-01' || $fecha > '2079-06-06') {
            return $fallback;
        }
        return $fecha;
    }
}


if (!function_exists('__estadisticas_impl_normalizarFechaIsoFlexible')) {
    function __estadisticas_impl_normalizarFechaIsoFlexible(string $valor, string $fallback): string
    {
        $valor = trim($valor);
        if (preg_match('/^([0-9]{2})[\/-]([0-9]{2})[\/-]([0-9]{4})$/', $valor, $m) === 1) {
            $valor = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^[0-9]{8}$/', $valor) === 1) {
            $valor = substr($valor, 0, 4) . '-' . substr($valor, 4, 2) . '-' . substr($valor, 6, 2);
        }
        return normalizarFechaIso($valor, $fallback);
    }
}


if (!function_exists('__estadisticas_impl_obtenerRangoFechasContextoSql')) {
    function __estadisticas_impl_obtenerRangoFechasContextoSql(array $contexto): array
    {
        $hoy = date('Y-m-d');
        $inicioAnio = date('Y') . '-01-01';

        $desdeRaw = (string)($contexto['f_desde_sql'] ?? ($contexto['f_desde'] ?? ''));
        $hastaRaw = (string)($contexto['f_hasta_sql'] ?? ($contexto['f_hasta'] ?? ''));

        $desde = normalizarFechaIsoFlexible($desdeRaw, $inicioAnio);
        $hasta = normalizarFechaIsoFlexible($hastaRaw, $hoy);
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, sumarDiasFechaIso($hasta, 1), $hasta];
    }
}


if (!function_exists('__estadisticas_impl_calcularRiesgoLineaServicio')) {
    function __estadisticas_impl_calcularRiesgoLineaServicio(array $linea): array
    {
        $dias = (int)($linea['dias'] ?? 0);
        $historicoRaw = strtoupper(trim((string)($linea['historico'] ?? 'N')));
        $esHistorico = $historicoRaw === 'S';
        $importePendiente = (float)($linea['pendiente'] ?? 0);
        $porcentajeServido = (float)($linea['porcentaje_servicio'] ?? ($linea['porcentaje_servido'] ?? 0));

        if ($dias < 3 && !$esHistorico) {
            return [
                'nivel' => 'blanco',
                'motivos' => [],
            ];
        }

        $hayRojo = false;
        $hayAmarillo = false;
        $motivos = [];

        if ($importePendiente > 150) {
            $hayRojo = true;
            $motivos[] = 'importe_pendiente_mayor_150';
        }

        if ($porcentajeServido < 80) {
            $hayRojo = true;
            $motivos[] = 'porcentaje_servicio_menor_80';
        }

        if ($porcentajeServido >= 80 && $porcentajeServido < 90) {
            $hayAmarillo = true;
            $motivos[] = 'porcentaje_servicio_entre_80_y_90';
        } elseif ($porcentajeServido >= 90) {
            $motivos[] = 'porcentaje_servicio_mayor_90';
        }

        if ($dias >= 3 && $dias <= 5) {
            $hayAmarillo = true;
            $motivos[] = 'dias_entre_3_y_5';
        }

        if ($dias > 5 && !$esHistorico) {
            $hayRojo = true;
            $motivos[] = 'dias_mayor_5_no_historico';
        }

        $nivel = 'verde';
        if ($hayRojo) {
            $nivel = 'rojo';
        } elseif ($hayAmarillo) {
            $nivel = 'amarillo';
        }

        return [
            'nivel' => $nivel,
            'motivos' => array_values(array_unique($motivos)),
        ];
    }
}


if (!function_exists('__estadisticas_impl_estadisticasDisplayErrorsActivo')) {
    function __estadisticas_impl_estadisticasDisplayErrorsActivo(): bool
    {
        $valor = strtolower((string)ini_get('display_errors'));
        return in_array($valor, ['1', 'on', 'yes', 'true'], true);
    }
}


if (!function_exists('__estadisticas_impl_estadisticasDebugActivo')) {
    function __estadisticas_impl_estadisticasDebugActivo(): bool
    {
        return (
            (isset($_GET['debug']) && (string)$_GET['debug'] === '1')
            || (defined('APP_DEBUG') && APP_DEBUG === true)
        );
    }
}


if (!function_exists('__estadisticas_impl_estadisticasDebugLog')) {
    function __estadisticas_impl_estadisticasDebugLog(string $mensaje, array $contexto = []): void
    {
        if (!estadisticasDebugActivo()) {
            return;
        }
        error_log('[ESTADISTICAS] ' . $mensaje . ' ' . json_encode($contexto));
    }
}


if (!function_exists('__estadisticas_impl_registrarErrorSqlEstadisticas')) {
    function __estadisticas_impl_registrarErrorSqlEstadisticas(string $contexto, $conn, string $sql, array $params = []): void
    {
        $detalle = odbc_errormsg($conn);
        estadisticasDebugLog(
            'sql_error.' . $contexto,
            ['detalle' => $detalle, 'sql' => $sql, 'params' => $params]
        );
        if (estadisticasDisplayErrorsActivo()) {
            if (!isset($GLOBALS['estadisticas_sql_errors']) || !is_array($GLOBALS['estadisticas_sql_errors'])) {
                $GLOBALS['estadisticas_sql_errors'] = [];
            }
            $GLOBALS['estadisticas_sql_errors'][] = 'Error SQL (' . $contexto . '): ' . $detalle;
        }
    }
}


if (!function_exists('__estadisticas_impl_obtenerErroresSqlEstadisticas')) {
    function __estadisticas_impl_obtenerErroresSqlEstadisticas(): array
    {
        $errores = $GLOBALS['estadisticas_sql_errors'] ?? [];
        return is_array($errores) ? $errores : [];
    }
}


if (!function_exists('__estadisticas_impl_estadisticasOdbcExec')) {
    function __estadisticas_impl_estadisticasOdbcExec($conn, string $sql, array $params = [])
    {
        if (!$conn) {
            return false;
        }

        preg_match_all('/\?/', $sql, $matches);
        $numPlaceholders = count($matches[0]);
        $numParams = count($params);

        if ($numPlaceholders === 0) {
            return @odbc_exec($conn, $sql);
        }

        if ($numPlaceholders !== $numParams) {
            estadisticasDebugLog(
                'odbc_placeholder_mismatch',
                [
                    'placeholders' => $numPlaceholders,
                    'params_count' => $numParams,
                    'sql' => $sql,
                    'params' => $params,
                ]
            );
            return false;
        }

        $stmt = @odbc_prepare($conn, $sql);
        if ($stmt && @odbc_execute($stmt, $params)) {
            return $stmt;
        }

        $sqlInterpolado = estadisticasInterpolarSql($sql, $params);
        if ($sqlInterpolado === null) {
            return false;
        }
        return @odbc_exec($conn, $sqlInterpolado);
    }
}


if (!function_exists('__estadisticas_impl_estadisticasInterpolarSql')) {
    function __estadisticas_impl_estadisticasInterpolarSql(string $sql, array $params): ?string
    {
        $out = '';
        $len = strlen($sql);
        $inString = false;
        $idx = 0;
        $total = count($params);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch === "'") {
                $out .= $ch;
                if ($inString && $i + 1 < $len && $sql[$i + 1] === "'") {
                    $out .= "'";
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }

            if ($ch === '?' && !$inString) {
                if ($idx >= $total) {
                    return null;
                }
                $out .= estadisticasSqlLiteral($params[$idx]);
                $idx++;
                continue;
            }

            $out .= $ch;
        }

        return $idx === $total ? $out : null;
    }
}


if (!function_exists('__estadisticas_impl_estadisticasSqlLiteral')) {
    function __estadisticas_impl_estadisticasSqlLiteral($valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        if (is_int($valor) || is_float($valor)) {
            return (string)$valor;
        }
        $txt = str_replace("'", "''", (string)$valor);
        return "'" . $txt . "'";
    }
}


if (!function_exists('__estadisticas_impl_buildWhereCabecera')) {
    function __estadisticas_impl_buildWhereCabecera(string $alias, array $filtros): array
    {
        $alias = trim($alias);
        $prefijo = $alias !== '' ? $alias . '.' : '';
        $where = [];
        $params = [];

        if (isset($filtros['tipo_venta'])) {
            $where[] = $prefijo . "tipo_venta = ?";
            $params[] = (int)$filtros['tipo_venta'];
        }
        if (!empty($filtros['excluir_anuladas'])) {
            $where[] = "ISNULL(" . $prefijo . "anulada, 'N') <> 'S'";
        }
        if (!empty($filtros['excluir_comisionista_cero'])) {
            $where[] = "ISNULL(" . $prefijo . "cod_comisionista, 0) <> 0";
        }
        if (!empty($filtros['fecha_desde']) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string)$filtros['fecha_desde'])) {
            $fDesdeSql = (new DateTimeImmutable((string)$filtros['fecha_desde']))->format('Ymd');
            $where[] = $prefijo . "fecha_venta >= CONVERT(smalldatetime, ?, 112)";
            $params[] = $fDesdeSql;
        }
        if (!empty($filtros['fecha_hasta']) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string)$filtros['fecha_hasta'])) {
            $fHastaMasUno = (new DateTimeImmutable((string)$filtros['fecha_hasta']))
                ->modify('+1 day')
                ->format('Ymd');
            $where[] = $prefijo . "fecha_venta < CONVERT(smalldatetime, ?, 112)";
            $params[] = $fHastaMasUno;
        } elseif (!empty($filtros['fecha_hasta_exclusiva']) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string)$filtros['fecha_hasta_exclusiva'])) {
            $fHastaExclusivaSql = (new DateTimeImmutable((string)$filtros['fecha_hasta_exclusiva']))->format('Ymd');
            $where[] = $prefijo . "fecha_venta < CONVERT(smalldatetime, ?, 112)";
            $params[] = $fHastaExclusivaSql;
        }
        if (isset($filtros['cod_comisionista']) && $filtros['cod_comisionista'] !== null && ctype_digit((string)$filtros['cod_comisionista'])) {
            $where[] = $prefijo . "cod_comisionista = ?";
            $params[] = (int)$filtros['cod_comisionista'];
        }
        if (!empty($filtros['importe_positivo'])) {
            $where[] = "ISNULL(TRY_CAST(" . $prefijo . "importe AS FLOAT), 0) >= 0";
        }
        if (!empty($filtros['importe_negativo'])) {
            $where[] = "ISNULL(TRY_CAST(" . $prefijo . "importe AS FLOAT), 0) < 0";
        }

        return [implode("\n              AND ", $where), $params];
    }
}


if (!function_exists('__estadisticas_impl_construirCondicionComercialParams')) {
    function __estadisticas_impl_construirCondicionComercialParams(string $alias, array $contexto): array
    {
        $alias = trim($alias);
        $prefijo = $alias !== '' ? $alias . '.' : '';
        $tipoFiltro = (string)($contexto['tipo_filtro_comercial'] ?? 'todos');
        $valor = trim((string)($contexto['cod_comisionista'] ?? ($contexto['valor_filtro_comercial'] ?? '')));

        if ($tipoFiltro === 'todos' || $valor === '' || !ctype_digit($valor)) {
            return ['', []];
        }

        return [" AND CAST(" . $prefijo . "cod_comisionista AS VARCHAR(50)) = ?", [$valor]];
    }
}


if (!function_exists('__estadisticas_impl_construirBaseLineasDocumentalesSql')) {
    function __estadisticas_impl_construirBaseLineasDocumentalesSql(array $filtros = []): array
    {
        $where = [
            "c.cod_comisionista <> 0",
            "c.tipo_venta IN (1,2)",
        ];
        $params = [];

        $fechaDesde = trim((string)($filtros['fecha_desde'] ?? ''));
        if ($fechaDesde !== '' && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $fechaDesde) === 1) {
            $where[] = "c.fecha_venta >= ?";
            $params[] = $fechaDesde;
        }

        $fechaHasta = trim((string)($filtros['fecha_hasta'] ?? ''));
        if ($fechaHasta !== '' && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $fechaHasta) === 1) {
            $where[] = "c.fecha_venta < ?";
            $params[] = (new DateTimeImmutable($fechaHasta))->modify('+1 day')->format('Y-m-d');
        }

        $codComisionista = trim((string)($filtros['cod_comisionista'] ?? ''));
        if ($codComisionista !== '' && ctype_digit($codComisionista)) {
            $where[] = "c.cod_comisionista = ?";
            $params[] = (int)$codComisionista;
        }

        $sql = "
            SELECT
                c.cod_comisionista,
                c.cod_cliente,
                c.fecha_venta,
                c.tipo_venta,
                l.cod_articulo,
                l.importe,
                a.marca
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera c
                ON c.cod_venta   = l.cod_venta
               AND c.tipo_venta  = l.tipo_venta
               AND c.cod_empresa = l.cod_empresa
               AND c.cod_caja    = l.cod_caja
    LEFT JOIN articulos a
                ON a.cod_articulo = l.cod_articulo
            WHERE " . implode("\n              AND ", $where) . "
        ";

        return [$sql, $params];
    }
}


if (!function_exists('__estadisticas_impl_buildWhereLineasDocumentales')) {
    function __estadisticas_impl_buildWhereLineasDocumentales(
        array $contexto,
        string $aliasArticulo = 'a',
        string $aliasLinea = 'hvl',
        string $aliasCabecera = 'hvc'
    ): array {
        $where = [];
        $params = [];

        $marca = trim((string)($contexto['filtro_marca'] ?? ($contexto['marca'] ?? '')));
        if ($marca !== '') {
            $where[] = "LTRIM(RTRIM(" . $aliasArticulo . ".marca)) = ?";
            $params[] = $marca;
        }

        $familia = trim((string)($contexto['filtro_familia'] ?? ($contexto['familia'] ?? '')));
        if ($familia !== '') {
            $where[] = "CAST(" . $aliasArticulo . ".cod_familia AS VARCHAR(50)) = ?";
            $params[] = $familia;
        }

        $articulo = trim((string)($contexto['filtro_articulo'] ?? ($contexto['articulo'] ?? ($contexto['cod_articulo'] ?? ''))));
        if ($articulo !== '') {
            $where[] = "CAST(" . $aliasLinea . ".cod_articulo AS VARCHAR(50)) = ?";
            $params[] = $articulo;
        }

        $cliente = trim((string)($contexto['filtro_cliente'] ?? ($contexto['cliente'] ?? ($contexto['cod_cliente'] ?? ''))));
        if ($cliente !== '' && ctype_digit($cliente)) {
            $where[] = "CAST(" . $aliasCabecera . ".cod_cliente AS VARCHAR(50)) = ?";
            $params[] = $cliente;
        }

        return [implode("\n              AND ", $where), $params];
    }
}


if (!function_exists('__estadisticas_impl_construirFiltroArticulosSql')) {
    function __estadisticas_impl_construirFiltroArticulosSql(array $contexto, array &$params): string
    {
        $condiciones = [];

        $marca = trim((string)($contexto['filtro_marca'] ?? ($contexto['marca'] ?? '')));
        if ($marca !== '') {
            $condiciones[] = "a.marca = ?";
            $params[] = $marca;
        }

        $familia = trim((string)($contexto['filtro_familia'] ?? ($contexto['familia'] ?? '')));
        if ($familia !== '') {
            $condiciones[] = "a.familia = ?";
            $params[] = $familia;
        }

        $subfamilia = trim((string)($contexto['filtro_subfamilia'] ?? ($contexto['subfamilia'] ?? '')));
        if ($subfamilia !== '') {
            $condiciones[] = "a.subfamilia = ?";
            $params[] = $subfamilia;
        }

        $articulo = trim((string)($contexto['filtro_articulo'] ?? ($contexto['articulo'] ?? ($contexto['cod_articulo'] ?? ''))));
        if ($articulo !== '') {
            $condiciones[] = "a.cod_articulo = ?";
            $params[] = $articulo;
        }

        if (empty($condiciones)) {
            return '';
        }

        return "
              AND EXISTS (
                    SELECT 1
    FROM integral.dbo.articulos a
                    WHERE a.cod_articulo = vl.cod_articulo
                      AND " . implode("
                      AND ", $condiciones) . "
                )";
    }
}


if (!function_exists('__estadisticas_impl_obtenerDefinicionCamposFiltrosVentas')) {
    function __estadisticas_impl_obtenerDefinicionCamposFiltrosVentas(): array
    {
        return [
            'marca' => [
                'select_expr' => "LTRIM(RTRIM(mfv_a.marca))",
                'where_expr' => "LTRIM(RTRIM(base_fcv.marca))",
                'output_field' => 'marca',
                'order_sql' => "base_fcv.marca",
                'requires_digit' => false,
                'etiqueta' => 'Marca',
            ],
            'familia' => [
                'select_expr' => "CAST(mfv_a.cod_familia AS VARCHAR(50))",
                'where_expr' => "CAST(base_fcv.familia AS VARCHAR(50))",
                'output_field' => 'familia',
                'order_sql' => "base_fcv.familia",
                'requires_digit' => false,
                'etiqueta' => 'Familia',
            ],
            'subfamilia' => [
                'select_expr' => "CAST(mfv_a.cod_subfamilia AS VARCHAR(50))",
                'where_expr' => "CAST(base_fcv.subfamilia AS VARCHAR(50))",
                'output_field' => 'subfamilia',
                'order_sql' => "base_fcv.subfamilia",
                'requires_digit' => false,
                'etiqueta' => 'Subfamilia',
            ],
            'articulo' => [
                'select_expr' => "CAST(mfv_hvl.cod_articulo AS VARCHAR(50))",
                'where_expr' => "CAST(base_fcv.articulo AS VARCHAR(50))",
                'output_field' => 'articulo',
                'order_sql' => "base_fcv.articulo",
                'requires_digit' => false,
                'etiqueta' => 'Articulo',
            ],
            'provincia' => [
                'select_expr' => "LTRIM(RTRIM(mfv_c.provincia))",
                'where_expr' => "LTRIM(RTRIM(base_fcv.provincia))",
                'output_field' => 'provincia',
                'order_sql' => "base_fcv.provincia",
                'requires_digit' => false,
                'etiqueta' => 'Provincia',
            ],
            'ruta' => [
                'select_expr' => "CAST(mfv_hvc.cod_ruta AS VARCHAR(50))",
                'where_expr' => "CAST(base_fcv.ruta AS VARCHAR(50))",
                'output_field' => 'ruta',
                'order_sql' => "base_fcv.ruta",
                'requires_digit' => true,
                'etiqueta' => 'Ruta',
            ],
            'cliente' => [
                'select_expr' => "CAST(mfv_hvc.cod_cliente AS VARCHAR(50))",
                'where_expr' => "CAST(base_fcv.cliente AS VARCHAR(50))",
                'output_field' => 'cliente',
                'order_sql' => "base_fcv.cliente",
                'requires_digit' => true,
                'etiqueta' => 'Cliente',
            ],
        ];
    }
}


if (!function_exists('__estadisticas_impl_construirSqlBaseFiltrosVentas')) {
    function __estadisticas_impl_construirSqlBaseFiltrosVentas(array $contexto, array $opciones = []): array
    {
        [$fDesde, $fHastaMasUno] = obtenerRangoFechasContextoSql($contexto);
        $codComisionista = trim((string)($contexto['cod_comisionista'] ?? ($contexto['cod_comisionista_activo'] ?? '')));

        $filtrosCabecera = [
            'excluir_anuladas' => true,
            'excluir_comisionista_cero' => true,
            'fecha_desde' => $fDesde,
            'fecha_hasta_exclusiva' => $fHastaMasUno,
        ];
        if ($codComisionista !== '' && ctype_digit($codComisionista)) {
            $filtrosCabecera['cod_comisionista'] = $codComisionista;
        }
        [$whereCabecera, $params] = buildWhereCabecera('mfv_hvc', $filtrosCabecera);

        $sql = "
            SELECT
                LTRIM(RTRIM(mfv_a.marca)) AS marca,
                CAST(mfv_a.cod_familia AS VARCHAR(50)) AS familia,
                LTRIM(RTRIM(mfv_f.descripcion)) AS familia_nombre,
                CAST(mfv_a.cod_subfamilia AS VARCHAR(50)) AS subfamilia,
                LTRIM(RTRIM(mfv_sf.descripcion)) AS subfamilia_nombre,
                CAST(mfv_hvl.cod_articulo AS VARCHAR(50)) AS articulo,
                LTRIM(RTRIM(mfv_c.provincia)) AS provincia,
                CAST(mfv_hvc.cod_ruta AS VARCHAR(50)) AS ruta,
                CAST(mfv_hvc.cod_cliente AS VARCHAR(50)) AS cliente,
                CAST(mfv_hvc.cod_cliente AS VARCHAR(50)) AS cliente_codigo,
                LTRIM(RTRIM(mfv_c.nombre_comercial)) AS cliente_nombre
            FROM hist_ventas_cabecera mfv_hvc
            INNER JOIN hist_ventas_linea mfv_hvl
                ON mfv_hvc.cod_venta = mfv_hvl.cod_venta
               AND mfv_hvc.tipo_venta = mfv_hvl.tipo_venta
               AND mfv_hvc.cod_empresa = mfv_hvl.cod_empresa
               AND mfv_hvc.cod_caja = mfv_hvl.cod_caja
    LEFT JOIN articulos mfv_a
                ON mfv_a.cod_articulo = mfv_hvl.cod_articulo
            LEFT JOIN familias mfv_f
                ON mfv_f.cod_familia = mfv_a.cod_familia
            LEFT JOIN subfamilias mfv_sf
                ON mfv_sf.cod_familia = mfv_a.cod_familia
               AND mfv_sf.cod_subfamilia = mfv_a.cod_subfamilia
            LEFT JOIN clientes mfv_c
                ON mfv_c.cod_cliente = mfv_hvc.cod_cliente
            WHERE 1=1
              AND mfv_hvc.tipo_venta IN (1,2)
              AND " . $whereCabecera . "
        ";

        return [$sql, $params];
    }
}


if (!function_exists('__estadisticas_impl_construirWhereFiltrosVentas')) {
    function __estadisticas_impl_construirWhereFiltrosVentas(array $contexto, ?string $filtroObjetivo = null): array
    {
        $definiciones = obtenerDefinicionCamposFiltrosVentas();
        $objetivo = strtolower(trim((string)$filtroObjetivo));
        $where = [];
        $params = [];

        $valores = [
            'marca' => trim((string)($contexto['filtro_marca'] ?? ($contexto['marca'] ?? ''))),
            'familia' => trim((string)($contexto['filtro_familia'] ?? ($contexto['familia'] ?? ''))),
            'subfamilia' => trim((string)($contexto['filtro_subfamilia'] ?? ($contexto['subfamilia'] ?? ''))),
            'articulo' => trim((string)($contexto['filtro_articulo'] ?? ($contexto['articulo'] ?? ($contexto['cod_articulo'] ?? '')))),
            'provincia' => trim((string)($contexto['filtro_provincia'] ?? ($contexto['provincia'] ?? ''))),
            'ruta' => trim((string)($contexto['filtro_ruta'] ?? ($contexto['ruta'] ?? ($contexto['cod_ruta'] ?? '')))),
            'cliente' => trim((string)($contexto['filtro_cliente'] ?? ($contexto['cliente'] ?? ($contexto['cod_cliente'] ?? '')))),
        ];

        foreach ($valores as $clave => $valor) {
            if ($valor === '' || $clave === $objetivo) {
                continue;
            }
            if (!isset($definiciones[$clave])) {
                continue;
            }
            $def = $definiciones[$clave];
            if (!empty($def['requires_digit']) && !ctype_digit($valor)) {
                continue;
            }
            $where[] = $def['where_expr'] . " = ?";
            $params[] = $valor;
        }

        return [implode("\n              AND ", $where), $params];
    }
}
