<?php

if (!function_exists('planificadorViewObtenerDatosZonas')) {
    function planificadorViewObtenerDatosZonas(): array
    {
        return [
            'zonas' => obtenerZonasVisita(),
        ];
    }
}

if (!function_exists('planificadorViewObtenerDatosZonasClientes')) {
    function planificadorViewObtenerDatosZonasClientes($cod_zona = null): array
    {
        $conn = db();
        $codigoSesion = obtenerCodVendedorPlanificacionService();
        $zonas = obtenerZonasVisita($codigoSesion);
        $zonas_alertas = [];
        $clientes_desalineados = [];
        $asignaciones_por_zona = [];
        $zona_actual = null;
        $rutas_asignadas = [];
        $clientes_disponibles = [];
        $asignaciones_actuales = [];
        $cod_zona = $cod_zona !== null ? intval($cod_zona) : null;

        if ($cod_zona) {
            $zona_actual = obtenerZonaPorCodigo($cod_zona, $codigoSesion);

            if (!$zona_actual) {
                return [
                    'error' => 'Error interno',
                    'zonas' => $zonas,
                    'cod_zona' => $cod_zona,
                ];
            }

            $rutas_asignadas = obtenerRutasPorZona($cod_zona);
            $clientes_disponibles = obtenerClientesDisponiblesParaAsignar($cod_zona, $rutas_asignadas, $codigoSesion);
            $asignaciones_actuales = obtenerClientesPorZona($cod_zona);

            if ($codigoSesion > 0 && !empty($asignaciones_actuales)) {
                $codigosClientes = [];
                foreach ($asignaciones_actuales as $asig) {
                    if (isset($asig['cod_cliente']) && $asig['cod_cliente'] !== '') {
                        $codigosClientes[] = intval($asig['cod_cliente']);
                    }
                }
                $codigosClientes = array_values(array_unique($codigosClientes));

                if (!empty($codigosClientes)) {
                    $inClientes = implode(',', $codigosClientes);
                    $sql_desalineados = "
                        SELECT DISTINCT c.cod_cliente
                        FROM clientes c
                        WHERE c.cod_cliente IN ($inClientes)
                          AND (c.cod_vendedor IS NULL OR c.cod_vendedor <> $codigoSesion)
                    ";
                    $res_desalineados = odbc_exec($conn, $sql_desalineados);
                    if ($res_desalineados) {
                        while ($fila_desalineada = odbc_fetch_array($res_desalineados)) {
                            $codClienteDesalineado = (string)($fila_desalineada['cod_cliente'] ?? '');
                            if ($codClienteDesalineado !== '') {
                                $clientes_desalineados[$codClienteDesalineado] = true;
                            }
                        }
                    }
                }
            }
        } else {
            if ($codigoSesion > 0) {
                $sql_alertas = "
                    SELECT
                        z.cod_zona,
                        COUNT(DISTINCT azc.cod_cliente) AS total_desalineados
                    FROM cmf_comerciales_zonas z
                    LEFT JOIN cmf_comerciales_clientes_zona azc
                        ON (azc.zona_principal = z.cod_zona OR azc.zona_secundaria = z.cod_zona)
                    LEFT JOIN clientes c
                        ON c.cod_cliente = azc.cod_cliente
                    WHERE z.cod_vendedor = $codigoSesion
                      AND (c.cod_vendedor IS NULL OR c.cod_vendedor <> $codigoSesion)
                    GROUP BY z.cod_zona
                ";
                $res_alertas = odbc_exec($conn, $sql_alertas);
                if ($res_alertas) {
                    while ($fila_alerta = odbc_fetch_array($res_alertas)) {
                        $codZonaAlerta = (string)($fila_alerta['cod_zona'] ?? '');
                        if ($codZonaAlerta !== '') {
                            $zonas_alertas[$codZonaAlerta] = (int)($fila_alerta['total_desalineados'] ?? 0);
                        }
                    }
                }
            }

            foreach ($zonas as $zona) {
                $asignaciones_por_zona[$zona['cod_zona']] = obtenerClientesPorZona($zona['cod_zona']);
            }
        }

        $numSeccionesPorCliente = [];
        foreach ($asignaciones_actuales as $asigCount) {
            $codCliCount = (string)($asigCount['cod_cliente'] ?? '');
            if ($codCliCount === '') {
                continue;
            }
            if (!isset($numSeccionesPorCliente[$codCliCount])) {
                $numSeccionesPorCliente[$codCliCount] = [];
            }
            $nombreSecKey = trim((string)($asigCount['nombre_seccion'] ?? ''));
            if ($nombreSecKey !== '') {
                $numSeccionesPorCliente[$codCliCount][$nombreSecKey] = true;
            }
        }

        return [
            'error' => '',
            'zonas' => $zonas,
            'zonas_alertas' => $zonas_alertas,
            'clientes_desalineados' => $clientes_desalineados,
            'asignaciones_por_zona' => $asignaciones_por_zona,
            'zona_actual' => $zona_actual,
            'rutas_asignadas' => $rutas_asignadas,
            'clientes_disponibles' => $clientes_disponibles,
            'asignaciones_actuales' => $asignaciones_actuales,
            'cod_zona' => $cod_zona,
            'numSeccionesPorCliente' => $numSeccionesPorCliente,
        ];
    }
}

if (!function_exists('planificadorViewObtenerDatosZonasRutas')) {
    function planificadorViewObtenerDatosZonasRutas($cod_zona = null, $cod_ruta_seleccionada = 0): array
    {
        $codigoSesion = obtenerCodVendedorPlanificacionService();
        $zonas = obtenerZonasVisita($codigoSesion);
        $zona_actual = null;
        $rutas_asignadas = [];
        $clientes_ruta = [];
        $ruta_actual = null;
        $todas_rutas_disponibles = [];
        $zonas_disponibles = [];

        if ($codigoSesion <= 0) {
            return [
                'error' => 'Error interno',
                'cod_zona' => $cod_zona,
            ];
        }

        if ($cod_zona !== null) {
            $cod_zona = intval($cod_zona);
            $cod_ruta_seleccionada = intval($cod_ruta_seleccionada);

            foreach ($zonas as $zona) {
                if ($zona['cod_zona'] == $cod_zona) {
                    $zona_actual = $zona;
                    break;
                }
            }

            if (!$zona_actual) {
                return [
                    'error' => 'Error interno',
                    'cod_zona' => $cod_zona,
                ];
            }

            $rutas_asignadas = obtenerRutasPorZona($cod_zona);
            if ($cod_ruta_seleccionada > 0) {
                foreach ($rutas_asignadas as $rutaAsignada) {
                    if ((int)($rutaAsignada['cod_ruta'] ?? 0) === $cod_ruta_seleccionada) {
                        $ruta_actual = $rutaAsignada;
                        break;
                    }
                }
                if ($ruta_actual !== null) {
                    $clientes_ruta = obtenerClientesPorZonaYRuta($cod_zona, $cod_ruta_seleccionada, $codigoSesion);
                }
            }

            $todas_rutas = obtenerTodasRutas();
            foreach ($todas_rutas as $ruta) {
                $ya_asignada = false;
                foreach ($rutas_asignadas as $ra) {
                    if ($ra['cod_ruta'] == $ruta['cod_ruta']) {
                        $ya_asignada = true;
                        break;
                    }
                }
                if (!$ya_asignada) {
                    $todas_rutas_disponibles[] = $ruta;
                }
            }
        } else {
            $zonas_disponibles = $zonas;
        }

        return [
            'error' => '',
            'cod_zona' => $cod_zona,
            'cod_ruta_seleccionada' => intval($cod_ruta_seleccionada),
            'zonas' => $zonas,
            'zona_actual' => $zona_actual,
            'rutas_asignadas' => $rutas_asignadas,
            'clientes_ruta' => $clientes_ruta,
            'ruta_actual' => $ruta_actual,
            'todas_rutas_disponibles' => $todas_rutas_disponibles,
            'zonas_disponibles' => $zonas_disponibles,
        ];
    }
}

if (!function_exists('planificadorViewObtenerDatosCompletarDia')) {
    function planificadorViewObtenerDatosCompletarDia($codigo_vendedor, $fecha): array
    {
        $codigo_vendedor = intval($codigo_vendedor);
        $fecha = validarFechaSQL((string)$fecha) ? (string)$fecha : date('Y-m-d');
        $esLaborable = $codigo_vendedor > 0 ? esDiaLaborable($fecha, null, $codigo_vendedor) : true;
        $conn = db();
        $sql = "SELECT
                    id_visita,
                    fecha_visita,
                    hora_inicio_visita,
                    hora_fin_visita,
                    estado_visita,
                    (SELECT nombre_comercial FROM [integral].[dbo].[clientes]
                     WHERE cod_cliente = cmf_comerciales_visitas.cod_cliente) AS cliente
                FROM [integral].[dbo].[cmf_comerciales_visitas]
                WHERE cod_vendedor = $codigo_vendedor
                  AND CONVERT(varchar(10), fecha_visita, 120) = '$fecha'
                ORDER BY hora_inicio_visita";
        $result = odbc_exec($conn, $sql);
        $visitas = [];
        if ($result) {
            while ($row = odbc_fetch_array($result)) {
                $visitas[] = $row;
            }
        }

        return [
            'codigo_vendedor' => $codigo_vendedor,
            'fecha' => $fecha,
            'es_laborable' => $esLaborable,
            'visitas' => $visitas,
            'factor' => 480 / 720,
        ];
    }
}
