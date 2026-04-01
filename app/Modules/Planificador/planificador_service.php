<?php

if (!function_exists('planificadorConfigurarDebugLog')) {
    function planificadorConfigurarDebugLog() {
        if (defined('BASE_PATH')) {
            @ini_set('log_errors', '1');
            @ini_set('error_log', BASE_PATH . '/storage/logs/php_debug.log');
        }
    }
}

function obtenerCodVendedorPlanificacionService() {
    return isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
}

/**
 * Crear una nueva zona de visita
 */
function crearZonaVisita($nombre_zona, $descripcion, $duracion_semanas, $orden) {
    $cod_vendedor = obtenerCodVendedorPlanificacionService();
    $conn = db();
    
    // Sanitizar entradas
    $nombre_zona = addslashes($nombre_zona);
    $descripcion = addslashes($descripcion);
    $duracion_semanas = intval($duracion_semanas);
    $orden = intval($orden);
    
    $query = "INSERT INTO cmf_zonas_visita (cod_vendedor, nombre_zona, descripcion, duracion_semanas, orden)
              VALUES ('$cod_vendedor', '$nombre_zona', '$descripcion', '$duracion_semanas', '$orden')";
              
    $result = odbc_exec($conn, $query);
    if (!$result) {
        error_log('Error al crear la zona de visita: ' . odbc_errormsg($conn));
        return;
    }
    return true;
}

/**
 * Obtener todas las zonas de visita asignadas al vendedor
 */
function obtenerZonasVisita() {
    $cod_vendedor = obtenerCodVendedorPlanificacionService();
    $conn = db();
    
    $query = "SELECT cod_zona, nombre_zona, descripcion, duracion_semanas, orden 
              FROM cmf_zonas_visita 
              WHERE cod_vendedor = '$cod_vendedor' 
              ORDER BY orden ASC";
              
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        error_log('Error al obtener zonas de visita: ' . odbc_errormsg($conn));
        return;
    }
    
    $zonas = array();
    while ($fila = odbc_fetch_array($resultado)) {
        $zonas[] = $fila;
    }
    
    return $zonas;
}

/**
 * Obtener la zona activa del vendedor segun el ciclo configurado.
 *
 * @return array|null ['cod_zona' => int, 'nombre' => string, 'orden' => int, 'duracion_semanas' => int]
 */
function obtenerZonaActivaHoy() {
    $cod_vendedor = obtenerCodVendedorPlanificacionService();
    $conn = db();

    if ($cod_vendedor <= 0) {
        return null;
    }

    $query = "SELECT cod_zona, nombre_zona, duracion_semanas, orden, fecha_inicio_ciclo
              FROM cmf_zonas_visita
              WHERE cod_vendedor = '$cod_vendedor'
              ORDER BY orden ASC, cod_zona ASC";

    $resultado = odbc_exec($conn, $query);
    if (!$resultado) {
        error_log('Error al obtener la zona activa del ciclo: ' . odbc_errormsg($conn));
        return null;
    }

    $zonas = array();
    $fechaInicioCiclo = '';

    while ($fila = odbc_fetch_array($resultado)) {
        $duracion = max(0, intval($fila['duracion_semanas'] ?? 0));
        if ($duracion <= 0) {
            continue;
        }

        $fechaFila = trim((string)($fila['fecha_inicio_ciclo'] ?? ''));
        if ($fechaInicioCiclo === '' && $fechaFila !== '') {
            $fechaInicioCiclo = $fechaFila;
        }

        $zonas[] = array(
            'cod_zona' => intval($fila['cod_zona'] ?? 0),
            'nombre' => trim((string)($fila['nombre_zona'] ?? '')),
            'orden' => intval($fila['orden'] ?? 0),
            'duracion_semanas' => $duracion,
        );
    }

    if (empty($zonas) || $fechaInicioCiclo === '') {
        return null;
    }

    $inicioCicloTs = strtotime(substr($fechaInicioCiclo, 0, 10) . ' 00:00:00');
    if ($inicioCicloTs === false) {
        return null;
    }

    $hoyTs = strtotime(date('Y-m-d') . ' 00:00:00');
    if ($hoyTs === false) {
        return null;
    }

    $totalSemanasCiclo = 0;
    foreach ($zonas as $zona) {
        $totalSemanasCiclo += (int)$zona['duracion_semanas'];
    }

    if ($totalSemanasCiclo <= 0) {
        return null;
    }

    $segundosSemana = 7 * 24 * 60 * 60;
    $semanasTranscurridas = $hoyTs >= $inicioCicloTs
        ? (int)floor(($hoyTs - $inicioCicloTs) / $segundosSemana)
        : 0;
    $posicionSemana = $semanasTranscurridas % $totalSemanasCiclo;

    $acumuladoSemanas = 0;
    foreach ($zonas as $zona) {
        $acumuladoSemanas += (int)$zona['duracion_semanas'];
        if ($posicionSemana < $acumuladoSemanas) {
            return $zona;
        }
    }

    return null;
}

/**
 * Obtener un cliente recomendado de la zona activa priorizando clientes sin visita hoy
 * y, despues, por antiguedad de su ultima visita.
 *
 * @return array|null ['cod_cliente' => int, 'nombre' => string, 'motivo' => string, 'origen_recomendacion' => string]
 */
function construirClienteRecomendadoDesdeFila(array $fila, string $origenRecomendacion) {
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

function calcularTocaVisitaPlanificador($frecuenciaVisita, int $iteracionZona): int {
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

function registrarDebugClientesRecomendador($conn, string $queryDebug): void {
    planificadorConfigurarDebugLog();

    error_log('DEBUG RECOMENDADOR SQL:');
    error_log($queryDebug);

    $resultadoDebug = odbc_exec($conn, $queryDebug);
    if (!$resultadoDebug) {
        error_log('Error en debug del recomendador: ' . odbc_errormsg($conn));
        return;
    }

    while ($row = odbc_fetch_array($resultadoDebug)) {
        $codCliente = (int)($row['cod_cliente'] ?? 0);
        $nombre = trim((string)($row['nombre'] ?? ''));
        $zonaOk = (int)($row['zona_ok'] ?? 0);
        $frecuenciaVisita = trim((string)($row['frecuencia_visita'] ?? ''));
        $frecuenciaNormalizada = strtoupper($frecuenciaVisita);
        $iteracionReal = (int)($row['iteracion_real'] ?? 0);
        $tocaVisita = (int)($row['toca_visita'] ?? 0);
        $visitaReal = (int)($row['visita_real'] ?? 0);
        $visitadoHoy = (int)($row['visitado_hoy'] ?? 0);
        $ultimaVisita = trim((string)($row['ultima_visita'] ?? ''));
        $motivo = array();

        if ($zonaOk !== 1) {
            $motivo[] = 'FUERA_ZONA';
        }
        if ($frecuenciaNormalizada === 'NUNCA') {
            $motivo[] = 'FREC_NUNCA';
        }
        if ($tocaVisita !== 1) {
            $motivo[] = 'NO_TOCA';
        }
        if ($visitaReal === 1) {
            $motivo[] = 'YA_VISITADO_CICLO';
        }
        if ($visitadoHoy === 1) {
            $motivo[] = 'YA_HOY';
        }
        if (empty($motivo)) {
            $motivo[] = 'ENTRA';
        }

        error_log("DEBUG CLIENTE {$codCliente} - {$nombre}");
        error_log("  zona_ok: {$zonaOk}");
        error_log("  frecuencia: {$frecuenciaVisita}");
        error_log("  iteracion: {$iteracionReal}");
        error_log("  toca: {$tocaVisita}");
        error_log("  visita_real: {$visitaReal}");
        error_log("  visitado_hoy: {$visitadoHoy}");
        error_log("  ultima_visita: {$ultimaVisita}");
        error_log('  MOTIVO: ' . implode(', ', $motivo));
    }
}

function obtenerClienteRecomendadoPorQuery($conn, string $query, string $origenRecomendacion, ?int $iteracionZona = null) {
    planificadorConfigurarDebugLog();

    $sql = $query;
    $params = array();

    error_log('RECOMENDADOR SQL:');
    error_log($sql);
    error_log('PARAMS:');
    error_log(print_r($params ?? array(), true));

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
            $fila['toca_visita'] = calcularTocaVisitaPlanificador($fila['frecuencia_visita'] ?? '', $iteracionZona);
            $fila['iteracion_zona'] = $iteracionZona;
            error_log(
                'FRECUENCIA candidato: ' . trim((string)($fila['frecuencia_visita'] ?? ''))
                . ' | iteracionZona: ' . $iteracionZona
                . ' | toca_visita: ' . (int)($fila['toca_visita'] ?? 0)
            );
        }

        $clientes[] = $fila;
    }

    error_log('RESULTADO RECOMENDADOR:');
    error_log(print_r($clientes, true));
    error_log('TOTAL FILAS: ' . count($clientes));

    error_log('Clientes candidatos: ' . count($clientes));

    if (empty($clientes)) {
        return null;
    }

    if ($iteracionZona !== null) {
        $clientes = array_values(array_filter($clientes, function ($cliente) {
            return (int)($cliente['toca_visita'] ?? 0) === 1;
        }));

        error_log('Clientes candidatos tras filtro de frecuencia: ' . count($clientes));

        if (empty($clientes)) {
            return null;
        }
    }

    foreach ($clientes as &$c) {
        $score = 0;

        if (empty($c['ultima_visita'])) {
            $score += 1000;
        } else {
            $ultima = strtotime((string)$c['ultima_visita']);
            $hoy = strtotime(date('Y-m-d'));
            if ($ultima !== false && $hoy !== false) {
                $dias = ($hoy - $ultima) / 86400;
                $score += min($dias, 365);
            }
        }

        $c['score'] = $score;
    }
    unset($c);

    usort($clientes, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $top5Resumen = array_map(function ($cliente) {
        return array(
            'cod_cliente' => $cliente['cod_cliente'] ?? null,
            'nombre' => $cliente['nombre'] ?? '',
            'frecuencia_visita' => $cliente['frecuencia_visita'] ?? null,
            'iteracion_zona' => $cliente['iteracion_zona'] ?? null,
            'toca_visita' => $cliente['toca_visita'] ?? null,
            'score' => $cliente['score'] ?? 0,
        );
    }, array_slice($clientes, 0, 5));

    error_log('Top 5 recomendador:');
    error_log(print_r(array_slice($clientes, 0, 5), true));
    error_log('Top 5 recomendador resumen:');
    error_log(print_r($top5Resumen, true));

    $clienteElegido = $clientes[0] ?? null;

    if ($clienteElegido !== null) {
        $cliente = construirClienteRecomendadoDesdeFila($clienteElegido, $origenRecomendacion);
        if ($cliente !== null) {
            error_log('Cliente elegido: ' . ($cliente['cod_cliente'] ?? 'NULL'));
            return $cliente;
        }
    }

    foreach ($clientes as $candidato) {
        $cliente = construirClienteRecomendadoDesdeFila($candidato, $origenRecomendacion);
        if ($cliente !== null) {
            error_log('Cliente elegido: ' . ($cliente['cod_cliente'] ?? 'NULL'));
            return $cliente;
        }
    }

    error_log('Cliente elegido: NULL');

    return array(
        'cod_cliente' => null,
        'nombre' => '',
        'motivo' => 'Sin clientes disponibles',
        'origen_recomendacion' => $origenRecomendacion,
    );
}

function obtenerSiguienteClienteRecomendado($zonaActivaId = 0) {
    $codZona = intval($zonaActivaId);
    $codVendedor = obtenerCodVendedorPlanificacionService();
    $conn = db();
    $iteracionZona = 0;
    $iteracionZonaReal = 1;
    $fechaInicioCiclo = '';

    if ($codVendedor <= 0) {
        return [];
    }

    if ($codZona > 0) {
        $queryZonaActiva = "
            SELECT fecha_inicio_ciclo, duracion_semanas, orden
            FROM cmf_zonas_visita
            WHERE cod_zona = '$codZona'
        ";
        $resultadoZonaActiva = odbc_exec($conn, $queryZonaActiva);
        $filaZonaActiva = $resultadoZonaActiva ? odbc_fetch_array($resultadoZonaActiva) : false;

        $fechaInicioCiclo = trim((string)($filaZonaActiva['fecha_inicio_ciclo'] ?? ''));
        if ($fechaInicioCiclo !== '') {
            $hoy = date('Y-m-d');
            $dias = (strtotime($hoy) - strtotime(substr($fechaInicioCiclo, 0, 10))) / 86400;
            $semanasTranscurridas = (int)floor($dias / 7);

            $queryDuracionTotal = "
                SELECT SUM(duracion_semanas) AS duracion_total_semanas
                FROM cmf_zonas_visita
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

    error_log('ZONA ACTIVA ID: ' . $codZona);
    error_log('ITERACION ZONA: ' . $iteracionZona);
    error_log('ITERACION REAL: ' . $iteracionZonaReal);

    $filtroVisitasCicloActual = '';
    if ($fechaInicioCiclo !== '') {
        $filtroVisitasCicloActual = "
            AND v.fecha_visita >= '" . substr($fechaInicioCiclo, 0, 10) . "'
        ";
    }

    error_log('DEBUG fechaInicioCiclo: ' . $fechaInicioCiclo);
    error_log('DEBUG HOY: ' . date('Y-m-d'));

    $selectZonaBase = "
        SELECT
            c.cod_cliente,
            c.nombre_comercial AS nombre,
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
        LEFT JOIN cmf_asignacion_zonas_clientes z
            ON z.cod_cliente = c.cod_cliente
        LEFT JOIN cmf_visitas_comerciales v_all
            ON v_all.cod_cliente = c.cod_cliente
            AND v_all.cod_vendedor = c.cod_vendedor
            AND v_all.estado_visita = 'Realizada'
        LEFT JOIN cmf_visitas_comerciales v_ciclo
            ON v_ciclo.cod_cliente = c.cod_cliente
            AND v_ciclo.cod_vendedor = c.cod_vendedor
            AND v_ciclo.estado_visita = 'Realizada'
            AND v_ciclo.fecha_visita >= '" . substr($fechaInicioCiclo, 0, 10) . "'
        LEFT JOIN cmf_visita_pedidos vp
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
        $queryZonaDebug = "
            SELECT TOP 50
                c.cod_cliente,
                c.nombre_comercial AS nombre,
                CASE
                    WHEN z.zona_principal = '$codZona'
                      OR z.zona_secundaria = '$codZona'
                    THEN 1 ELSE 0
                END AS zona_ok,
                z.frecuencia_visita,
                $iteracionZonaReal AS iteracion_real,
                CASE
                    WHEN UPPER(ISNULL(z.frecuencia_visita, '')) = 'TODOS' THEN 1
                    WHEN UPPER(ISNULL(z.frecuencia_visita, '')) = 'CADA2' AND ($iteracionZonaReal % 2) = 0 THEN 1
                    WHEN UPPER(ISNULL(z.frecuencia_visita, '')) = 'CADA3' AND ($iteracionZonaReal % 3) = 0 THEN 1
                    ELSE 0
                END AS toca_visita,
                MAX(CASE
                    WHEN CONVERT(date, v.fecha_visita) = CONVERT(date, GETDATE())
                    THEN 1 ELSE 0 END) AS visitado_hoy,
                MAX(
                    CASE
                        WHEN v.id_visita IS NOT NULL
                         AND (
                            vp.id_visita IS NULL
                            OR vp.origen = 'Visita'
                         )
                        THEN 1
                        ELSE 0
                    END
                ) AS visita_real,
                MAX(v.fecha_visita) AS ultima_visita
            FROM clientes c
            LEFT JOIN cmf_asignacion_zonas_clientes z
                ON z.cod_cliente = c.cod_cliente
            LEFT JOIN cmf_visitas_comerciales v
                ON v.cod_cliente = c.cod_cliente
                AND v.cod_vendedor = c.cod_vendedor
                $filtroVisitasCicloActual
                AND v.estado_visita = 'Realizada'
            LEFT JOIN cmf_visita_pedidos vp
                ON vp.id_visita = v.id_visita
            WHERE c.cod_vendedor = '$codVendedor'
              $filtrosOperativos
            GROUP BY
                c.cod_cliente,
                c.nombre_comercial,
                z.zona_principal,
                z.zona_secundaria,
                z.frecuencia_visita
            ORDER BY c.nombre_comercial ASC
        ";
        registrarDebugClientesRecomendador($conn, $queryZonaDebug);

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
            GROUP BY c.cod_cliente, c.nombre_comercial, z.frecuencia_visita
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

        $clienteZona = obtenerClienteRecomendadoPorQuery($conn, $queryZonaNivel1, 'zona', $iteracionZona);
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

        $clienteZona = obtenerClienteRecomendadoPorQuery($conn, $queryZonaNivel2, 'zona', $iteracionZona);
        if (!empty($clienteZona)) {
            return $clienteZona;
        }
    }

    $queryGlobal = "
        SELECT
            c.cod_cliente,
            c.nombre_comercial AS nombre,
            MAX(v.fecha_visita) AS ultima_visita,
            NULL AS frecuencia_visita,
            0 AS toca_visita
        FROM clientes c
        LEFT JOIN cmf_visitas_comerciales v
            ON v.cod_cliente = c.cod_cliente
            AND v.cod_vendedor = c.cod_vendedor
        WHERE c.cod_vendedor = '$codVendedor'
          $filtrosOperativos
        GROUP BY c.cod_cliente, c.nombre_comercial
        ORDER BY
            CASE WHEN MAX(v.fecha_visita) IS NULL THEN 0 ELSE 1 END ASC,
            MAX(v.fecha_visita) ASC
    ";

    $clienteGlobal = obtenerClienteRecomendadoPorQuery($conn, $queryGlobal, 'global');
    if (!empty($clienteGlobal)) {
        return $clienteGlobal;
    }

    $queryFallback = "
        SELECT
            c.cod_cliente,
            c.nombre_comercial AS nombre,
            NULL AS ultima_visita,
            NULL AS frecuencia_visita,
            0 AS toca_visita
        FROM clientes c
        WHERE c.cod_vendedor = '$codVendedor'
          $filtrosOperativos
        ORDER BY
            c.nombre_comercial ASC
    ";

    $clienteFallback = obtenerClienteRecomendadoPorQuery($conn, $queryFallback, 'fallback');
    if (!empty($clienteFallback)) {
        return $clienteFallback;
    }

    return [];
}

/**
 * Obtener información de una zona por su código
 */
function obtenerZonaPorCodigo($cod_zona) {
    $cod_vendedor = obtenerCodVendedorPlanificacionService();
    $conn = db();
    
    // Sanitizar la entrada
    $cod_zona = intval($cod_zona);
    
    $query = "SELECT * FROM cmf_zonas_visita WHERE cod_zona = $cod_zona AND cod_vendedor = '$cod_vendedor'";
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        error_log('Error al obtener la zona: ' . odbc_errormsg($conn));
        return;
    }
    
    $zona = odbc_fetch_array($resultado);
    
    return $zona ? $zona : null;
}

/**
 * Obtener rutas asignadas a una zona especfica
 */
function obtenerRutasPorZona($cod_zona) {
    $conn = db();
    
    // Sanitizar la entrada para prevenir inyeccin SQL
    $cod_zona = intval($cod_zona);
    
    // Consulta con JOIN para obtener las rutas asociadas a la zona
    $query = "SELECT r.cod_ruta, COALESCE(r.descripcion, 'Sin Descripción') AS nombre_ruta 
              FROM cmf_zonas_rutas czr 
              JOIN rutas r ON czr.cod_ruta = r.cod_ruta 
              WHERE czr.cod_zona = $cod_zona
              ORDER BY r.descripcion ASC";
              
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        error_log('Error al obtener rutas: ' . odbc_errormsg($conn));
        return;
    }
    
    $rutas = array();
    while ($fila = odbc_fetch_array($resultado)) {
        $rutas[] = $fila;
    }
    
    return $rutas;
}

/**
 * Obtener todas las rutas disponibles
 */
function obtenerTodasRutas() {
    $conn = db();
    
    $query = "SELECT DISTINCT r.cod_ruta, COALESCE(r.descripcion, 'Sin Descripción') AS nombre_ruta 
              FROM rutas r 
              ORDER BY nombre_ruta ASC";
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        error_log('Error al obtener todas las rutas: ' . odbc_errormsg($conn));
        return;
    }
    
    $rutas = array();
    while ($fila = odbc_fetch_array($resultado)) {
        $rutas[] = $fila;
    }
    
    return $rutas;
}

/**
 * Obtener secciones de un cliente específico que no están asignadas a ninguna zona
 */
/**
 * Asignar una ruta a una zona.
 */
function asignarRutaZona($cod_zona, $cod_ruta) {
    $conn = db();
    
    $cod_zona = intval($cod_zona);
    $cod_ruta = intval($cod_ruta);
    
    if ($cod_zona <= 0 || $cod_ruta <= 0) {
        return false;
    }
    
    $query_check = "SELECT COUNT(*) AS total
                    FROM cmf_zonas_rutas
                    WHERE cod_zona = '$cod_zona' AND cod_ruta = '$cod_ruta'";
    $resultado_check = odbc_exec($conn, $query_check);
    
    if (!$resultado_check) {
        return false;
    }
    
    $fila_check = odbc_fetch_array($resultado_check);
    if ($fila_check && intval($fila_check['total']) > 0) {
        return false;
    }
    
    $query = "INSERT INTO cmf_zonas_rutas (cod_zona, cod_ruta)
              VALUES ('$cod_zona', '$cod_ruta')";
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        return false;
    }
    
    return true;
}

function obtenerSeccionesPorCliente($cod_cliente) {
    $conn = db();
    
    // Sanitizar la entrada
    $cod_cliente = intval($cod_cliente);
    
    // Obtener secciones que no están asignadas a ninguna zona
    $query = "SELECT sc.cod_seccion, sc.nombre 
              FROM secciones_cliente sc
              WHERE sc.cod_cliente = '$cod_cliente' 
                AND sc.cod_seccion NOT IN (
                    SELECT azc.cod_seccion 
                    FROM cmf_asignacion_zonas_clientes azc 
                    WHERE azc.cod_cliente = sc.cod_cliente
                )
              ORDER BY sc.cod_seccion ASC";
              
    $resultado = odbc_exec($conn, $query);
    
    if (!$resultado) {
        error_log('Error al obtener secciones: ' . odbc_errormsg($conn));
        return;
    }
    
    $secciones = array();
    while ($fila = odbc_fetch_array($resultado)) {
        $secciones[] = $fila;
    }
    
    return $secciones;
}

/**
 * Obtener clientes disponibles para asignar a una zona especfica
 */
function obtenerClientesDisponiblesParaAsignar($cod_zona, $rutas_asignadas) {
    $cod_vendedor = obtenerCodVendedorPlanificacionService();
    $conn = db();
    
    if (empty($rutas_asignadas)) {
        return array();
    }
    
    // Crear un array de cod_ruta
    $cod_rutas = array();
    foreach ($rutas_asignadas as $ruta) {
        $cod_rutas[] = intval($ruta['cod_ruta']);
    }
    
    if (empty($cod_rutas)) {
        return array();
    }
    
    // Convertir el array de cod_ruta a una cadena separada por comas
    $cod_rutas_str = implode(',', $cod_rutas);
    
    // Consulta para obtener clientes con al menos una sección sin asignar
    $query_secciones_disponibles = "
        SELECT DISTINCT sc.cod_cliente
        FROM secciones_cliente sc
        WHERE sc.cod_cliente IN (
            SELECT c.cod_cliente
            FROM clientes c
            WHERE c.cod_ruta IN ($cod_rutas_str)
              AND c.cod_vendedor = '$cod_vendedor'
        )
        AND NOT EXISTS (
            SELECT 1 FROM cmf_asignacion_zonas_clientes azc
            WHERE azc.cod_cliente = sc.cod_cliente
              AND azc.cod_seccion = sc.cod_seccion
        )
    ";
    
    // Ejecutar la consulta para obtener clientes con secciones sin asignar
    $resultado_secciones = odbc_exec($conn, $query_secciones_disponibles);
    
    if (!$resultado_secciones) {
        error_log('Error al obtener secciones disponibles: ' . odbc_errormsg($conn));
        return;
    }
    
    $clientes_con_secciones_disponibles = array();
    while ($fila = odbc_fetch_array($resultado_secciones)) {
        $clientes_con_secciones_disponibles[] = $fila['cod_cliente'];
    }
    
    // Consulta para obtener clientes sin secciones y no asignados a ninguna zona
    $query_clientes_sin_secciones = "
        SELECT DISTINCT c.cod_cliente, c.nombre_comercial AS nombre_cliente
        FROM clientes c
        WHERE c.cod_ruta IN ($cod_rutas_str)
          AND c.cod_vendedor = '$cod_vendedor'
          AND NOT EXISTS (
              SELECT 1 FROM secciones_cliente sc
              WHERE sc.cod_cliente = c.cod_cliente
          )
          AND NOT EXISTS (
              SELECT 1 FROM cmf_asignacion_zonas_clientes azc
              WHERE azc.cod_cliente = c.cod_cliente
          )
    ";
    
    // Ejecutar la consulta para obtener clientes sin secciones y no asignados
    $resultado_clientes_sin_secciones = odbc_exec($conn, $query_clientes_sin_secciones);
    
    if (!$resultado_clientes_sin_secciones) {
        error_log('Error al obtener clientes sin secciones: ' . odbc_errormsg($conn));
        return;
    }
    
    $clientes_sin_secciones_disponibles = array();
    while ($fila = odbc_fetch_array($resultado_clientes_sin_secciones)) {
        $clientes_sin_secciones_disponibles[] = array(
            'cod_cliente' => $fila['cod_cliente'],
            'nombre_cliente' => $fila['nombre_cliente']
        );
    }
    
    // Obtener detalles de clientes con secciones disponibles
    $clientes_con_secciones_disponibles_detalles = array();
    if (!empty($clientes_con_secciones_disponibles)) {
        // Eliminar duplicados y convertir a cadena
        $clientes_con_secciones_disponibles = array_unique($clientes_con_secciones_disponibles);
        $cod_clientes_str = implode(',', $clientes_con_secciones_disponibles);
        
        // Consulta para obtener los nombres comerciales de estos clientes
        $query_clientes_con_secciones = "
            SELECT DISTINCT c.cod_cliente, c.nombre_comercial AS nombre_cliente
            FROM clientes c
            WHERE c.cod_cliente IN ($cod_clientes_str)
              AND c.cod_vendedor = '$cod_vendedor'
        ";
        
        $resultado_clientes_con_secciones = odbc_exec($conn, $query_clientes_con_secciones);
        
        if (!$resultado_clientes_con_secciones) {
            error_log('Error al obtener detalles de clientes con secciones disponibles: ' . odbc_errormsg($conn));
            return;
        }
        
        while ($fila = odbc_fetch_array($resultado_clientes_con_secciones)) {
            $clientes_con_secciones_disponibles_detalles[] = array(
                'cod_cliente' => $fila['cod_cliente'],
                'nombre_cliente' => $fila['nombre_cliente']
            );
        }
    }
    
    // Combinar ambas listas de clientes disponibles
    $clientes_disponibles = array_merge($clientes_con_secciones_disponibles_detalles, $clientes_sin_secciones_disponibles);
    
    // Ordenar la lista final por 'nombre_cliente' (nombre_comercial)
    usort($clientes_disponibles, 'compararNombreCliente');
    
    return $clientes_disponibles;
}

/**
 * Función de comparación para ordenar clientes por nombre_comercial.
 *
 * @param array $a Primer cliente a comparar.
 * @param array $b Segundo cliente a comparar.
 * @return int Resultado de la comparación.
 */
function compararNombreCliente($a, $b) {
    return strcmp($a['nombre_cliente'], $b['nombre_cliente']);
}




/**
 * Asignar un cliente y su sección a una zona
 */
function asignarClienteZona($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones = '') {
    $conn = db();
    
    // Sanitizar entradas
    $cod_cliente = intval($cod_cliente);
    $zona_principal = intval($zona_principal);
    $zona_secundaria = ($zona_secundaria !== 'NULL') ? intval($zona_secundaria) : 'NULL';
    $tiempo_promedio_visita = floatval($tiempo_promedio_visita);
    $preferencia_horaria = addslashes($preferencia_horaria);
    $frecuencia_visita = addslashes($frecuencia_visita);
    $observaciones = addslashes($observaciones);
    
    // Manejar cod_seccion: si es NULL, no se incluye en comillas
    if ($cod_seccion !== 'NULL') {
        $cod_seccion = intval($cod_seccion);
    }
    
    // Verificar si la asignación ya existe
    $query_check = "SELECT COUNT(*) AS total FROM cmf_asignacion_zonas_clientes 
                   WHERE cod_cliente = '$cod_cliente' 
                     AND (cod_seccion = " . ($cod_seccion === 'NULL' ? "NULL" : "'$cod_seccion'") . ")
                     AND zona_principal = '$zona_principal'";
    $resultado_check = odbc_exec($conn, $query_check);
    if (!$resultado_check) {
        error_log('Error al verificar asignación existente: ' . odbc_errormsg($conn));
        return;
    }
    $fila_check = odbc_fetch_array($resultado_check);
    
    if ($fila_check['total'] > 0) {
        // Asignación ya existe
        return false;
    }
    
    // Insertar la nueva asignación
    $query = "INSERT INTO cmf_asignacion_zonas_clientes 
              (cod_cliente, cod_seccion, zona_principal, zona_secundaria, tiempo_promedio_visita, preferencia_horaria, frecuencia_visita, observaciones)
              VALUES 
              ('$cod_cliente', " . 
              ($cod_seccion === 'NULL' ? "NULL" : "'$cod_seccion'") . ", 
              '$zona_principal', 
              " . 
              ($zona_secundaria === 'NULL' ? 'NULL' : "'$zona_secundaria'") . ", 
              '$tiempo_promedio_visita', 
              '$preferencia_horaria', 
              '$frecuencia_visita', 
              '$observaciones')";
              
    $result = odbc_exec($conn, $query);
    if (!$result) {
        error_log('Error al asignar el cliente a la zona: ' . odbc_errormsg($conn));
        return;
    }
    return true;
}

/**
 * Obtener clientes asignados a una zona especfica, tanto principales como secundarios.
 *
 * @param int $cod_zona Código de la zona.
 * @return array Lista de clientes con detalles de asignación.
 */
function obtenerClientesPorZona($cod_zona) {
    $conn = db();
    
    // Sanitizar la entrada
    $cod_zona = intval($cod_zona);
    
    // Consulta SQL para obtener asignaciones principales y secundarias
    $query_clientes_asignados = "
        SELECT 
            c.cod_cliente, 
            c.nombre_comercial AS nombre_cliente, 
            c.poblacion AS poblacion_cliente, -- Población del cliente
            sc.cod_seccion, 
            sc.nombre AS nombre_seccion,
            sc.poblacion AS poblacion_seccion, -- Población de la sección
            azc.frecuencia_visita,
            azc.observaciones,
            'primaria' AS tipo_asignacion
        FROM cmf_asignacion_zonas_clientes azc
        JOIN clientes c ON azc.cod_cliente = c.cod_cliente
        LEFT JOIN secciones_cliente sc 
            ON azc.cod_cliente = sc.cod_cliente 
            AND azc.cod_seccion = sc.cod_seccion
        WHERE azc.zona_principal = '$cod_zona'
        
        UNION ALL
        
        SELECT 
            c.cod_cliente, 
            c.nombre_comercial AS nombre_cliente, 
            c.poblacion AS poblacion_cliente, -- Población del cliente
            sc.cod_seccion, 
            sc.nombre AS nombre_seccion,
            sc.poblacion AS poblacion_seccion, -- Población de la sección
            azc.frecuencia_visita,
            azc.observaciones,
            'secundaria' AS tipo_asignacion
        FROM cmf_asignacion_zonas_clientes azc
        JOIN clientes c ON azc.cod_cliente = c.cod_cliente
        LEFT JOIN secciones_cliente sc 
            ON azc.cod_cliente = sc.cod_cliente 
            AND azc.cod_seccion = sc.cod_seccion
        WHERE azc.zona_secundaria = '$cod_zona'
    ";
    
    // Ejecutar la consulta
    $resultado_asignados = odbc_exec($conn, $query_clientes_asignados);

    if (!$resultado_asignados) {
        error_log('Error al obtener los clientes asignados: ' . odbc_errormsg($conn));
        return;
    }

    $clientes_asignados = array();
    while ($fila = odbc_fetch_array($resultado_asignados)) {
        // Transformar frecuencia_visita
        if ($fila['frecuencia_visita'] == 'todos_ciclos') {
            $fila['frecuencia_visita'] = 'Todos';
        } elseif ($fila['frecuencia_visita'] == 'uno_no') {
            $fila['frecuencia_visita'] = 'Cada 2';
        }
        $clientes_asignados[] = $fila;
    }

    // Ordenar la lista final por 'nombre_cliente' y 'nombre_seccion'
    usort($clientes_asignados, 'compararNombreClienteYSeccion');

    return $clientes_asignados;
}

/**
 * Obtener clientes de una ruta concreta que no pertenecen al vendedor en sesión.
 *
 * @param int $cod_zona Código de la zona.
 * @param int $cod_ruta Código de la ruta.
 * @return array Lista de clientes de la ruta fuera del vendedor en sesión.
 */
function obtenerClientesPorZonaYRuta($cod_zona, $cod_ruta) {
    $conn = db();
    $cod_vendedor = obtenerCodVendedorPlanificacionService();

    $cod_zona = intval($cod_zona);
    $cod_ruta = intval($cod_ruta);

    if ($cod_zona <= 0 || $cod_ruta <= 0 || $cod_vendedor <= 0) {
        return array();
    }

    $query = "
        SELECT
            c.cod_cliente,
            c.nombre_comercial AS nombre_cliente,
            c.poblacion AS poblacion_cliente,
            c.cod_ruta,
            NULL AS cod_seccion,
            '' AS nombre_seccion,
            '' AS poblacion_seccion,
            '' AS frecuencia_visita,
            '' AS observaciones,
            '' AS tipo_asignacion
        FROM clientes c
        LEFT JOIN secciones_cliente sc
            ON c.cod_cliente = sc.cod_cliente
        WHERE (c.cod_vendedor IS NULL OR c.cod_vendedor <> '$cod_vendedor')
          AND c.cod_ruta = '$cod_ruta'
          AND c.fecha_baja IS NULL
        GROUP BY
            c.cod_cliente,
            c.nombre_comercial,
            c.poblacion,
            c.cod_ruta
        ORDER BY c.nombre_comercial ASC
    ";

    $resultado = odbc_exec($conn, $query);
    if (!$resultado) {
        error_log('Error al obtener clientes por zona y ruta: ' . odbc_errormsg($conn));
        return array();
    }

    $clientes = array();
    while ($fila = odbc_fetch_array($resultado)) {
        $clientes[] = $fila;
    }

    return $clientes;
}



/**
 * Función para comparar clientes por nombre_comercial y nombre_seccion.
 */
function compararNombreClienteYSeccion($a, $b) {
    $resultado = strcmp($a['nombre_cliente'], $b['nombre_cliente']);
    if ($resultado === 0) {
        $resultado = strcmp($a['nombre_seccion'], $b['nombre_seccion']);
    }
    return $resultado;
}

/**
 * Obtener el nombre comercial de un cliente.
 *
 * @param int $cod_cliente Código del cliente.
 * @return array|null Nombre comercial del cliente.
 */
function obtenerNombreCliente($cod_cliente) {
    $conn = db();

    $cod_cliente = intval($cod_cliente);
    $query = "SELECT nombre_comercial FROM clientes WHERE cod_cliente = $cod_cliente";
    $resultado = odbc_exec($conn, $query);

    if (!$resultado) {
        error_log('Error al obtener el nombre del cliente: ' . odbc_errormsg($conn));
        return;
    }

    $cliente = odbc_fetch_array($resultado);
    return $cliente ? $cliente : null;
}

/**
 * Obtener información de una zona por su código.
 *
 * @param int $cod_zona Código de la zona.
 * @return array|null Información de la zona.
 */
function obtenerZonaPorCodigoEditar($cod_zona) {
    $conn = db();

    $cod_zona = intval($cod_zona);
    $query = "SELECT nombre_zona FROM cmf_zonas_visita WHERE cod_zona = $cod_zona";
    $resultado = odbc_exec($conn, $query);

    if (!$resultado) {
        error_log('Error al obtener la zona: ' . odbc_errormsg($conn));
        return;
    }

    $zona = odbc_fetch_array($resultado);
    return $zona ? $zona : null;
}

/**
 * Obtener una asignación específica.
 *
 * @param int $cod_cliente Código del cliente.
 * @param int $cod_zona Código de la zona.
 * @param int|null $cod_seccion Código de la sección (puede ser NULL).
 * @return array|null Asignación encontrada.
 */
function obtenerAsignacion($cod_cliente, $cod_zona, $cod_seccion = null) {
    $conn = db();

    $cod_cliente = intval($cod_cliente);
    $cod_zona = intval($cod_zona);

    // Construcción de la condición para manejar NULL en cod_seccion
    $condicion_seccion = $cod_seccion === null ? "azc.cod_seccion IS NULL" : "azc.cod_seccion = " . intval($cod_seccion);

    $query = "
        SELECT azc.*, 
               c.nombre_comercial, 
               COALESCE(sc.nombre, 'Sin Sección') AS nombre_seccion, 
               z.nombre_zona
        FROM cmf_asignacion_zonas_clientes azc
        JOIN clientes c ON azc.cod_cliente = c.cod_cliente
        LEFT JOIN secciones_cliente sc 
            ON azc.cod_cliente = sc.cod_cliente 
            AND azc.cod_seccion = sc.cod_seccion
        JOIN cmf_zonas_visita z ON azc.zona_principal = z.cod_zona
        WHERE azc.cod_cliente = $cod_cliente
          AND azc.zona_principal = $cod_zona
          AND $condicion_seccion";

    $resultado = odbc_exec($conn, $query);

    if (!$resultado) {
        error_log('Error al obtener la asignación: ' . odbc_errormsg($conn));
        return;
    }

    $asignacion = odbc_fetch_array($resultado);
    return $asignacion ? $asignacion : null;
}




/**
 * Actualizar una asignación en la base de datos.
 *
 * @param int $cod_cliente Código del cliente.
 * @param int $cod_zona Código de la zona principal.
 * @param int $cod_seccion Código de la sección.
 * @param int|null $zona_secundaria Código de la zona secundaria (puede ser NULL).
 * @param float|null $tiempo_promedio_visita Tiempo promedio de visita.
 * @param string|null $preferencia_horaria Preferencia horaria ('M' para mañana, 'T' para tarde).
 * @param string|null $frecuencia_visita Frecuencia de visita ('Todos', 'Cada2', 'Cada3', 'Nunca').
 * @param string|null $observaciones Observaciones.
 *
 * @return bool True si la actualización fue exitosa, false en caso contrario.
 */
function actualizarAsignacion($cod_cliente, $cod_zona, $cod_seccion, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones) {
    $conn = db();

    // Sanitizar las entradas
    $cod_cliente = intval($cod_cliente);
    $cod_zona = intval($cod_zona);
    $condicionCodSeccion = is_null($cod_seccion) ? 'IS NULL' : '= ' . intval($cod_seccion);
    $zona_secundaria = is_null($zona_secundaria) ? 'NULL' : intval($zona_secundaria);
    $tiempo_promedio_visita = is_null($tiempo_promedio_visita) ? 'NULL' : floatval($tiempo_promedio_visita);
    $preferencia_horaria = is_null($preferencia_horaria) ? 'NULL' : "'" . addslashes($preferencia_horaria) . "'";
    $frecuencia_visita = is_null($frecuencia_visita) ? 'NULL' : "'" . addslashes($frecuencia_visita) . "'";
    $observaciones = is_null($observaciones) ? 'NULL' : "'" . addslashes($observaciones) . "'";

    // Construir la consulta de actualización
    $query = "
        UPDATE cmf_asignacion_zonas_clientes
        SET
            zona_secundaria = $zona_secundaria,
            tiempo_promedio_visita = $tiempo_promedio_visita,
            preferencia_horaria = $preferencia_horaria,
            frecuencia_visita = $frecuencia_visita,
            observaciones = $observaciones
        WHERE
            cod_cliente = $cod_cliente
            AND zona_principal = $cod_zona
            AND cod_seccion $condicionCodSeccion
    ";

    // Ejecutar la consulta
    $resultado = odbc_exec($conn, $query);

    if (!$resultado) {
        error_log('Error al actualizar la asignación: ' . odbc_errormsg($conn));
        return;
    }

    return true;
}



if (!function_exists('crearZonaVisitaService')) {
    function crearZonaVisitaService($nombre_zona, $descripcion, $duracion_semanas, $orden) {
        return crearZonaVisita($nombre_zona, $descripcion, $duracion_semanas, $orden);
    }
}

if (!function_exists('obtenerZonasVisitaService')) {
    function obtenerZonasVisitaService() {
        return obtenerZonasVisita();
    }
}

if (!function_exists('obtenerZonaActivaHoyService')) {
    function obtenerZonaActivaHoyService() {
        return obtenerZonaActivaHoy();
    }
}

if (!function_exists('obtenerSiguienteClienteRecomendadoService')) {
    function obtenerSiguienteClienteRecomendadoService($zonaActivaId = 0) {
        return obtenerSiguienteClienteRecomendado($zonaActivaId);
    }
}

if (!function_exists('obtenerRutasPorZonaService')) {
    function obtenerRutasPorZonaService($cod_zona) {
        return obtenerRutasPorZona($cod_zona);
    }
}

if (!function_exists('obtenerClientesPorZonaYRutaService')) {
    function obtenerClientesPorZonaYRutaService($cod_zona, $cod_ruta) {
        return obtenerClientesPorZonaYRuta($cod_zona, $cod_ruta);
    }
}

if (!function_exists('asignarClienteZonaService')) {
    function asignarClienteZonaService($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones = '') {
        return asignarClienteZona($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones);
    }
}

if (!function_exists('asignarRutaZonaService')) {
    function asignarRutaZonaService($cod_zona, $cod_ruta) {
        return asignarRutaZona($cod_zona, $cod_ruta);
    }
}





