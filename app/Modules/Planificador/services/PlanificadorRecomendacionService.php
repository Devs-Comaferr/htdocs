<?php

if (!function_exists('planificadorRecomendacionObtenerZonaActivaHoy')) {
    function planificadorRecomendacionObtenerZonaActivaHoy($cod_vendedor = null)
    {
        $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : obtenerCodVendedorPlanificacionService();
        $conn = db();

        if ($cod_vendedor <= 0) {
            return null;
        }
        $contextoZona = obtenerZonaActivaPorFecha($conn, $cod_vendedor, date('Y-m-d'));
        if ($contextoZona === null) {
            return null;
        }

        $zonaActual = $contextoZona['zona_actual_detalle'] ?? null;
        if (!is_array($zonaActual)) {
            return null;
        }

        if (!isset($zonaActual['nombre']) || trim((string)$zonaActual['nombre']) === '') {
            $zonaActual['nombre'] = trim((string)($zonaActual['nombre_zona'] ?? ''));
        }

        return $zonaActual;
    }
}

if (!function_exists('planificadorRecomendacionConstruirClienteDesdeFila')) {
    function planificadorRecomendacionConstruirClienteDesdeFila(array $fila, string $origenRecomendacion)
    {
        $nombre = trim((string)($fila['nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        $ultimaVisita = trim((string)($fila['ultima_visita'] ?? ''));
        $motivo = 'Nunca visitado';

        if ($ultimaVisita !== '') {
            $ultimaVisitaTs = strtotime(substr($ultimaVisita, 0, 19));
            $hoyTs = strtotime(date('Y-m-d') . ' 00:00:00');

            if ($ultimaVisitaTs !== false && $hoyTs !== false) {
                $diasDesdeUltimaVisita = max(0, (int)floor(($hoyTs - $ultimaVisitaTs) / (24 * 60 * 60)));

                if ($diasDesdeUltimaVisita > 30) {
                    $motivo = 'No visitado en ' . $diasDesdeUltimaVisita . ' dias';
                } elseif ($diasDesdeUltimaVisita >= 7) {
                    $motivo = 'Seguimiento recomendado';
                } else {
                    $motivo = 'Visita reciente';
                }
            }
        }

        return array(
            'cod_cliente' => intval($fila['cod_cliente'] ?? 0),
            'nombre' => $nombre,
            'motivo' => $motivo,
            'origen_recomendacion' => $origenRecomendacion,
        );
    }
}

if (!function_exists('planificadorRecomendacionCalcularTocaVisita')) {
    function planificadorRecomendacionCalcularTocaVisita($frecuenciaVisita, int $iteracionZona): int
    {
        $frecuencia = strtoupper(trim((string)$frecuenciaVisita));
        $iteracionZonaReal = $iteracionZona + 1;

        if ($frecuencia === 'TODOS') {
            return 1;
        }

        if ($frecuencia === 'CADA2') {
            return ($iteracionZonaReal % 2) === 0 ? 1 : 0;
        }

        if ($frecuencia === 'CADA3') {
            return ($iteracionZonaReal % 3) === 0 ? 1 : 0;
        }

        return 0;
    }
}

if (!function_exists('planificadorRecomendacionObtenerUniversoCandidatos')) {
    function planificadorRecomendacionObtenerUniversoCandidatos($conn, string $query, ?int $iteracionZona = null)
    {
        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener el cliente recomendado del planificador: ' . odbc_errormsg($conn));
            return null;
        }

        $clientes = array();
        while ($fila = odbc_fetch_array($resultado)) {
            if (!isset($fila['score']) || $fila['score'] === null) {
                $fila['score'] = 0;
            }

            if ($iteracionZona !== null) {
                $fila['toca_visita'] = planificadorRecomendacionCalcularTocaVisita($fila['frecuencia_visita'] ?? '', $iteracionZona);
                $fila['iteracion_zona'] = $iteracionZona;
            }

            $clientes[] = $fila;
        }

        return $clientes;
    }
}

if (!function_exists('planificadorRecomendacionClienteEsLaborable')) {
    function planificadorRecomendacionClienteEsLaborable(array $cliente, int $codVendedor, ?string $fecha = null): bool
    {
        $fecha = $fecha !== null && validarFechaSQL($fecha) ? $fecha : date('Y-m-d');
        $clienteCalendario = [
            'poblacion' => trim((string)($cliente['poblacion'] ?? '')),
            'provincia' => trim((string)($cliente['provincia'] ?? '')),
            'cod_municipio_ine' => trim((string)($cliente['cod_municipio_ine'] ?? '')),
        ];

        return esDiaLaborable($fecha, $clienteCalendario, $codVendedor);
    }
}

if (!function_exists('planificadorRecomendacionFiltrarElegibles')) {
    function planificadorRecomendacionFiltrarElegibles($clientes, ?int $iteracionZona = null, ?int $codVendedor = null, ?string $fecha = null)
    {
        if (!is_array($clientes)) {
            return null;
        }

        if (empty($clientes)) {
            return array();
        }

        if ($iteracionZona !== null) {
            $clientes = array_values(array_filter($clientes, function ($cliente) {
                return (int)($cliente['toca_visita'] ?? 0) === 1;
            }));
        }

        if ($codVendedor !== null && $codVendedor > 0) {
            $clientes = array_values(array_filter($clientes, function ($cliente) use ($codVendedor, $fecha) {
                return planificadorRecomendacionClienteEsLaborable((array)$cliente, $codVendedor, $fecha);
            }));
        }

        $clientes = array_values(array_filter($clientes, function ($cliente) {
            $frecuencia = strtoupper(trim((string)($cliente['frecuencia_visita'] ?? '')));
            return $frecuencia !== 'NUNCA';
        }));

        return $clientes;
    }
}

if (!function_exists('planificadorRecomendacionCalcularScoreClientes')) {
    function planificadorRecomendacionCalcularScoreClientes($clientes)
    {
        if (!is_array($clientes)) {
            return null;
        }

        foreach ($clientes as &$c) {
            $score = 0;

            if (empty($c['ultima_visita'])) {
                $score += 1000;
                $c['motivo_score'] = 'Nunca visitado';
            } else {
                $ultima = strtotime((string)$c['ultima_visita']);
                $hoy = strtotime(date('Y-m-d'));
                if ($ultima !== false && $hoy !== false) {
                    $dias = ($hoy - $ultima) / 86400;
                    $score += min($dias, 365);
                    $c['motivo_score'] = 'Ultima visita';
                } else {
                    $c['motivo_score'] = 'Sin fecha valida';
                }
            }

            $c['score'] = $score;
        }
        unset($c);

        usort($clientes, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $clientes;
    }
}

if (!function_exists('planificadorRecomendacionSeleccionarMejorCliente')) {
    function planificadorRecomendacionSeleccionarMejorCliente($clientes, string $origenRecomendacion)
    {
        if (!is_array($clientes)) {
            return null;
        }

        if (empty($clientes)) {
            return null;
        }

        $clienteElegido = $clientes[0] ?? null;

        if ($clienteElegido !== null) {
            $cliente = planificadorRecomendacionConstruirClienteDesdeFila($clienteElegido, $origenRecomendacion);
            if ($cliente !== null) {
                return $cliente;
            }
        }

        foreach ($clientes as $candidato) {
            $cliente = planificadorRecomendacionConstruirClienteDesdeFila($candidato, $origenRecomendacion);
            if ($cliente !== null) {
                return $cliente;
            }
        }

        return array(
            'cod_cliente' => null,
            'nombre' => '',
            'motivo' => 'Sin clientes disponibles',
            'origen_recomendacion' => $origenRecomendacion,
        );
    }
}

if (!function_exists('planificadorRecomendacionObtenerClientePorQuery')) {
    function planificadorRecomendacionObtenerClientePorQuery($conn, string $query, string $origenRecomendacion, ?int $iteracionZona = null)
    {
        planificadorConfigurarDebugLog();

        $universo = planificadorRecomendacionObtenerUniversoCandidatos($conn, $query, $iteracionZona);
        if ($universo === null) {
            return null;
        }

        $elegibles = planificadorRecomendacionFiltrarElegibles($universo, $iteracionZona);
        if (empty($elegibles)) {
            return null;
        }

        $scored = planificadorRecomendacionCalcularScoreClientes($elegibles);

        return planificadorRecomendacionSeleccionarMejorCliente($scored, $origenRecomendacion);
    }
}

if (!function_exists('planificadorRecomendacionObtenerSiguienteCliente')) {
    function planificadorRecomendacionObtenerSiguienteCliente($zonaActivaId = 0, $codVendedor = null)
    {
        $codZona = intval($zonaActivaId);
        $codVendedor = $codVendedor !== null ? intval($codVendedor) : obtenerCodVendedorPlanificacionService();
        $conn = db();
        $iteracionZona = 0;
        $iteracionZonaReal = 1;
        $fechaInicioCiclo = '';
        $fechaHoy = date('Y-m-d');

        if ($codVendedor <= 0) {
            return [];
        }

        if (!esDiaLaborable($fechaHoy, null, $codVendedor)) {
            return [];
        }

        if ($codZona > 0) {
            $queryZonaActiva = "
                SELECT fecha_inicio_ciclo, duracion_semanas, orden
                FROM cmf_comerciales_zonas
                WHERE cod_zona = '$codZona'
            ";
            $resultadoZonaActiva = odbc_exec($conn, $queryZonaActiva);
            $filaZonaActiva = $resultadoZonaActiva ? odbc_fetch_array($resultadoZonaActiva) : false;

            $fechaInicioCiclo = trim((string)($filaZonaActiva['fecha_inicio_ciclo'] ?? ''));
            if ($fechaInicioCiclo !== '') {
                $hoy = date('Y-m-d', strtotime(date('Y-m-d') . ' monday this week'));
                $fechaInicioSemana = date('Y-m-d', strtotime(substr($fechaInicioCiclo, 0, 10) . ' monday this week'));
                $semanasTranscurridas = calcularSemanasNaturalesEntreFechas($fechaInicioSemana, $hoy);

                $queryDuracionTotal = "
                    SELECT SUM(duracion_semanas) AS duracion_total_semanas
                    FROM cmf_comerciales_zonas
                ";
                $resultadoDuracionTotal = odbc_exec($conn, $queryDuracionTotal);
                $filaDuracionTotal = $resultadoDuracionTotal ? odbc_fetch_array($resultadoDuracionTotal) : false;
                $duracionTotalSemanas = (int)($filaDuracionTotal['duracion_total_semanas'] ?? 0);

                if ($duracionTotalSemanas > 0) {
                    $iteracionZona = (int)floor($semanasTranscurridas / $duracionTotalSemanas);
                }
            }
        }

        $iteracionZonaReal = $iteracionZona + 1;

        $filtroVisitasCicloActual = '';
        if ($fechaInicioCiclo !== '') {
            $filtroVisitasCicloActual = "
                AND v.fecha_visita >= '" . substr($fechaInicioCiclo, 0, 10) . "'
            ";
        }

        $selectZonaBase = "
            SELECT
                c.cod_cliente,
                c.nombre_comercial AS nombre,
                ISNULL(c.provincia, '') AS provincia,
                ISNULL(c.poblacion, '') AS poblacion,
                '' AS cod_municipio_ine,
                MAX(v_all.fecha_visita) AS ultima_visita_real,
                MAX(v_ciclo.fecha_visita) AS ultima_visita_ciclo,
                MAX(
                    CASE
                        WHEN v_all.id_visita IS NOT NULL
                         AND (
                            vp.id_visita IS NULL
                            OR vp.origen = 'Visita'
                         )
                        THEN 1
                        ELSE 0
                    END
                ) AS visita_real,
                z.frecuencia_visita,
                CASE
                    WHEN z.frecuencia_visita = 'TODOS' THEN 1
                    WHEN z.frecuencia_visita = 'CADA2' THEN 1
                    WHEN z.frecuencia_visita = 'CADA3' THEN 1
                    ELSE 0
                END AS toca_visita
            FROM clientes c
            LEFT JOIN cmf_comerciales_clientes_zona z
                ON z.cod_cliente = c.cod_cliente
            LEFT JOIN cmf_comerciales_visitas v_all
                ON v_all.cod_cliente = c.cod_cliente
                AND v_all.cod_vendedor = c.cod_vendedor
                AND v_all.estado_visita = 'Realizada'
            LEFT JOIN cmf_comerciales_visitas v_ciclo
                ON v_ciclo.cod_cliente = c.cod_cliente
                AND v_ciclo.cod_vendedor = c.cod_vendedor
                AND v_ciclo.estado_visita = 'Realizada'
                AND v_ciclo.fecha_visita >= '" . substr($fechaInicioCiclo, 0, 10) . "'
            LEFT JOIN cmf_comerciales_visitas_pedidos vp
                ON vp.id_visita = v_all.id_visita
        ";

        $orderByBase = "
            ORDER BY
                CASE WHEN MAX(v_all.fecha_visita) IS NULL THEN 0 ELSE 1 END ASC,
                MAX(v_all.fecha_visita) ASC
        ";

        $filtrosOperativos = "
              AND c.fecha_alta < DATEADD(DAY, -7, GETDATE())
        ";

        if ($codZona > 0) {
            $whereZona = "
                WHERE c.cod_vendedor = '$codVendedor'
                  AND z.cod_cliente IS NOT NULL
                  AND ISNULL(z.frecuencia_visita, '') <> 'NUNCA'
                  AND (
                        z.zona_principal = '$codZona'
                        OR z.zona_secundaria = '$codZona'
                  )
                  $filtrosOperativos
            ";

            $groupByZona = "
                GROUP BY c.cod_cliente, c.nombre_comercial, c.provincia, c.poblacion, z.frecuencia_visita
            ";

            $queryZonaNivel1 = $selectZonaBase
                . $whereZona
                . $groupByZona
                . " HAVING MAX(
                        CASE
                            WHEN v_all.id_visita IS NOT NULL
                             AND (
                                vp.id_visita IS NULL
                                OR vp.origen = 'Visita'
                             )
                            THEN 1
                            ELSE 0
                        END
                    ) = 0 "
                . $orderByBase;

            $universo = planificadorRecomendacionObtenerUniversoCandidatos($conn, $queryZonaNivel1, $iteracionZona);
            $elegibles = planificadorRecomendacionFiltrarElegibles($universo, $iteracionZona, $codVendedor, $fechaHoy);
            $scored = planificadorRecomendacionCalcularScoreClientes($elegibles);
            $clienteZona = planificadorRecomendacionSeleccionarMejorCliente($scored, 'zona');
            if (!empty($clienteZona)) {
                return $clienteZona;
            }

            $queryZonaNivel2 = $selectZonaBase
                . $whereZona
                . $groupByZona
                . " HAVING MAX(
                        CASE
                            WHEN v_all.id_visita IS NOT NULL
                             AND (
                                vp.id_visita IS NULL
                                OR vp.origen = 'Visita'
                             )
                            THEN 1
                            ELSE 0
                        END
                    ) = 0 "
                . $orderByBase;

            $universo = planificadorRecomendacionObtenerUniversoCandidatos($conn, $queryZonaNivel2, $iteracionZona);
            $elegibles = planificadorRecomendacionFiltrarElegibles($universo, $iteracionZona, $codVendedor, $fechaHoy);
            $scored = planificadorRecomendacionCalcularScoreClientes($elegibles);
            $clienteZona = planificadorRecomendacionSeleccionarMejorCliente($scored, 'zona');
            if (!empty($clienteZona)) {
                return $clienteZona;
            }
        }

        $queryGlobal = "
            SELECT
                c.cod_cliente,
                c.nombre_comercial AS nombre,
                ISNULL(c.provincia, '') AS provincia,
                ISNULL(c.poblacion, '') AS poblacion,
                '' AS cod_municipio_ine,
                MAX(v.fecha_visita) AS ultima_visita,
                MAX(ISNULL(z.frecuencia_visita, '')) AS frecuencia_visita,
                0 AS toca_visita
            FROM clientes c
            LEFT JOIN cmf_comerciales_clientes_zona z
                ON z.cod_cliente = c.cod_cliente
                AND z.activo = 1
            LEFT JOIN cmf_comerciales_visitas v
                ON v.cod_cliente = c.cod_cliente
                AND v.cod_vendedor = c.cod_vendedor
            WHERE c.cod_vendedor = '$codVendedor'
              $filtrosOperativos
            GROUP BY c.cod_cliente, c.nombre_comercial, c.provincia, c.poblacion
            HAVING MAX(CASE WHEN UPPER(ISNULL(z.frecuencia_visita, '')) = 'NUNCA' THEN 1 ELSE 0 END) = 0
            ORDER BY
                CASE WHEN MAX(v.fecha_visita) IS NULL THEN 0 ELSE 1 END ASC,
                MAX(v.fecha_visita) ASC
        ";

        $universo = planificadorRecomendacionObtenerUniversoCandidatos($conn, $queryGlobal);
        $elegibles = planificadorRecomendacionFiltrarElegibles($universo, null, $codVendedor, $fechaHoy);
        $scored = planificadorRecomendacionCalcularScoreClientes($elegibles);
        $clienteGlobal = planificadorRecomendacionSeleccionarMejorCliente($scored, 'global');
        if (!empty($clienteGlobal)) {
            return $clienteGlobal;
        }

        $queryFallback = "
            SELECT
                c.cod_cliente,
                c.nombre_comercial AS nombre,
                ISNULL(c.provincia, '') AS provincia,
                ISNULL(c.poblacion, '') AS poblacion,
                '' AS cod_municipio_ine,
                NULL AS ultima_visita,
                MAX(ISNULL(z.frecuencia_visita, '')) AS frecuencia_visita,
                0 AS toca_visita
            FROM clientes c
            LEFT JOIN cmf_comerciales_clientes_zona z
                ON z.cod_cliente = c.cod_cliente
                AND z.activo = 1
            WHERE c.cod_vendedor = '$codVendedor'
              $filtrosOperativos
            GROUP BY c.cod_cliente, c.nombre_comercial, c.provincia, c.poblacion
            HAVING MAX(CASE WHEN UPPER(ISNULL(z.frecuencia_visita, '')) = 'NUNCA' THEN 1 ELSE 0 END) = 0
            ORDER BY
                c.nombre_comercial ASC
        ";

        $universo = planificadorRecomendacionObtenerUniversoCandidatos($conn, $queryFallback);
        $elegibles = planificadorRecomendacionFiltrarElegibles($universo, null, $codVendedor, $fechaHoy);
        $scored = planificadorRecomendacionCalcularScoreClientes($elegibles);
        $clienteFallback = planificadorRecomendacionSeleccionarMejorCliente($scored, 'fallback');
        if (!empty($clienteFallback)) {
            return $clienteFallback;
        }

        return [];
    }
}
