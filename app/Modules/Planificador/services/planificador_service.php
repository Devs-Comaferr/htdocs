<?php

// ========================================
// PLANIFICADOR SERVICE - INDICE
// ========================================
//
// DATOS:
// - crearZonaVisita
// - obtenerZonasVisita
// - obtenerZonaPorCodigo
// - obtenerRutasPorZona
// - obtenerTodasRutas
// - asignarRutaZona
// - obtenerSeccionesPorCliente
// - obtenerClientesDisponiblesParaAsignar
// - asignarClienteZona
// - obtenerClientesPorZona
// - obtenerClientesPorZonaYRuta
// - obtenerNombreCliente
// - obtenerZonaPorCodigoEditar
// - obtenerAsignacion
// - actualizarAsignacion
//
// MOTOR:
// - obtenerZonaActivaHoy
// - construirClienteRecomendadoDesdeFila
// - calcularTocaVisitaPlanificador
// - registrarDebugClientesRecomendador
// - obtenerSiguienteClienteRecomendado
// - pipeline:
//     - obtenerUniversoCandidatosPlanificador
//     - filtrarClientesElegiblesPlanificador
//     - calcularScoreClientesPlanificador
//     - seleccionarMejorClientePlanificador
//
// VIEW HELPERS:
// - obtenerDatosZonasView
// - obtenerDatosZonasClientesView
// - obtenerDatosZonasRutasView
// - obtenerDatosCompletarDia
//
// COMPATIBILIDAD:
// - obtenerClienteRecomendadoPorQuery
// - *Service wrappers
//
// ========================================
if (!function_exists('planificadorConfigurarDebugLog')) {
    function planificadorConfigurarDebugLog() {
        if (defined('BASE_PATH')) {
            @ini_set('log_errors', '1');
            @ini_set('error_log', BASE_PATH . '/storage/logs/php_debug.log');
        }
    }
}

require_once __DIR__ . '/PlanificadorZonasRepository.php';
require_once __DIR__ . '/PlanificadorAsignacionesRepository.php';
require_once __DIR__ . '/PlanificadorViewDataService.php';

function obtenerCodVendedorPlanificacionService() {
    return planificadorRepoObtenerCodVendedor();
}

/**
 * Crear una nueva zona de visita
 */

// ==========================
// DATOS: acceso a zonas, rutas, clientes y asignaciones
// ==========================

function crearZonaVisita($nombre_zona, $descripcion, $duracion_semanas, $orden, $cod_vendedor = null) {
    return planificadorRepoCrearZonaVisita($nombre_zona, $descripcion, $duracion_semanas, $orden, $cod_vendedor);
}

/**
 * Obtener todas las zonas de visita asignadas al vendedor
 */

function obtenerZonasVisita($cod_vendedor = null) {
    return planificadorRepoObtenerZonasVisita($cod_vendedor);
}

/**
 * Obtener la zona activa del vendedor segun el ciclo configurado.
 *
 * @return array|null ['cod_zona' => int, 'nombre' => string, 'orden' => int, 'duracion_semanas' => int]
 */

function obtenerZonaPorCodigo($cod_zona, $cod_vendedor = null) {
    return planificadorRepoObtenerZonaPorCodigo($cod_zona, $cod_vendedor);
}

/**
 * Obtener rutas asignadas a una zona especfica
 */

function obtenerRutasPorZona($cod_zona) {
    return planificadorRepoObtenerRutasPorZona($cod_zona);
}

/**
 * Obtener todas las rutas disponibles
 */

function obtenerTodasRutas() {
    return planificadorRepoObtenerTodasRutas();
}

/**
 * Obtener secciones de un cliente especÃ­fico que no estÃ¡n asignadas a ninguna zona
 */
/**
 * Asignar una ruta a una zona.
 */

function asignarRutaZona($cod_zona, $cod_ruta) {
    return planificadorRepoAsignarRutaZona($cod_zona, $cod_ruta);
}

function zonaTieneRutas($cod_zona): bool {
    return planificadorRepoZonaTieneRutas($cod_zona);
}

function zonaTieneClientesAsignados($cod_zona): bool {
    return planificadorRepoZonaTieneClientesAsignados($cod_zona);
}

function rutaZonaTieneClientesAsignados($cod_zona, $cod_ruta): bool {
    return planificadorRepoRutaZonaTieneClientesAsignados($cod_zona, $cod_ruta);
}

function eliminarRutaZona($cod_zona, $cod_ruta): bool {
    return planificadorRepoEliminarRutaZona($cod_zona, $cod_ruta);
}

function eliminarRutaZonaSegura($cod_zona, $cod_ruta): array {
    return planificadorRepoEliminarRutaZonaSegura($cod_zona, $cod_ruta);
}

function eliminarZonaSegura($cod_zona, $cod_vendedor = null): array {
    return planificadorRepoEliminarZonaSegura($cod_zona, $cod_vendedor);
}

function obtenerSeccionesPorCliente($cod_cliente) {
    return planificadorRepoObtenerSeccionesPorCliente($cod_cliente);
}

/**
 * Obtener clientes disponibles para asignar a una zona especfica
 */

function obtenerClientesDisponiblesParaAsignar($cod_zona, $rutas_asignadas, $cod_vendedor = null) {
    return planificadorRepoObtenerClientesDisponiblesParaAsignar($cod_zona, $rutas_asignadas, $cod_vendedor);
}

/**
 * FunciÃ³n de comparaciÃ³n para ordenar clientes por nombre_comercial.
 *
 * @param array $a Primer cliente a comparar.
 * @param array $b Segundo cliente a comparar.
 * @return int Resultado de la comparaciÃ³n.
 */

function compararNombreCliente($a, $b) {
    return planificadorRepoCompararNombreCliente($a, $b);
}




/**
 * Asignar un cliente y su secciÃ³n a una zona
 */

function asignarClienteZona($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones = '') {
    return planificadorRepoAsignarClienteZona($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones);
}

/**
 * Obtener clientes asignados a una zona especfica, tanto principales como secundarios.
 *
 * @param int $cod_zona CÃ³digo de la zona.
 * @return array Lista de clientes con detalles de asignaciÃ³n.
 */

function obtenerClientesPorZona($cod_zona) {
    return planificadorRepoObtenerClientesPorZona($cod_zona);
}

/**
 * Obtener clientes de una ruta concreta que no pertenecen al vendedor en sesiÃ³n.
 *
 * @param int $cod_zona CÃ³digo de la zona.
 * @param int $cod_ruta CÃ³digo de la ruta.
 * @return array Lista de clientes de la ruta fuera del vendedor en sesiÃ³n.
 */

function obtenerClientesPorZonaYRuta($cod_zona, $cod_ruta, $cod_vendedor = null) {
    return planificadorRepoObtenerClientesPorZonaYRuta($cod_zona, $cod_ruta, $cod_vendedor);
}



/**
 * FunciÃ³n para comparar clientes por nombre_comercial y nombre_seccion.
 */

function compararNombreClienteYSeccion($a, $b) {
    return planificadorRepoCompararNombreClienteYSeccion($a, $b);
}

/**
 * Obtener el nombre comercial de un cliente.
 *
 * @param int $cod_cliente CÃ³digo del cliente.
 * @return array|null Nombre comercial del cliente.
 */

function obtenerNombreCliente($cod_cliente) {
    return planificadorRepoObtenerNombreCliente($cod_cliente);
}

/**
 * Obtener informaciÃ³n de una zona por su cÃ³digo.
 *
 * @param int $cod_zona CÃ³digo de la zona.
 * @return array|null InformaciÃ³n de la zona.
 */

function obtenerZonaPorCodigoEditar($cod_zona) {
    return planificadorRepoObtenerZonaPorCodigoEditar($cod_zona);
}

/**
 * Obtener una asignaciÃ³n especÃ­fica.
 *
 * @param int $cod_cliente CÃ³digo del cliente.
 * @param int $cod_zona CÃ³digo de la zona.
 * @param int|null $cod_seccion CÃ³digo de la secciÃ³n (puede ser NULL).
 * @return array|null AsignaciÃ³n encontrada.
 */

function obtenerAsignacion($cod_cliente, $cod_zona, $cod_seccion = null) {
    return planificadorRepoObtenerAsignacion($cod_cliente, $cod_zona, $cod_seccion);
}




/**
 * Actualizar una asignaciÃ³n en la base de datos.
 *
 * @param int $cod_cliente CÃ³digo del cliente.
 * @param int $cod_zona CÃ³digo de la zona principal.
 * @param int $cod_seccion CÃ³digo de la secciÃ³n.
 * @param int|null $zona_secundaria CÃ³digo de la zona secundaria (puede ser NULL).
 * @param float|null $tiempo_promedio_visita Tiempo promedio de visita.
 * @param string|null $preferencia_horaria Preferencia horaria ('M' para maÃ±ana, 'T' para tarde).
 * @param string|null $frecuencia_visita Frecuencia de visita ('Todos', 'Cada2', 'Cada3', 'Nunca').
 * @param string|null $observaciones Observaciones.
 *
 * @return bool True si la actualizaciÃ³n fue exitosa, false en caso contrario.
 */

function actualizarAsignacion($cod_cliente, $cod_zona, $cod_seccion, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones) {
    return planificadorRepoActualizarAsignacion($cod_cliente, $cod_zona, $cod_seccion, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones);
}



if (!function_exists('crearZonaVisitaService')) {

// ==========================
// MOTOR: seleccion de cliente y reglas de ciclo
// ==========================

function obtenerZonaActivaHoy($cod_vendedor = null) {
    $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : obtenerCodVendedorPlanificacionService();
    $conn = db();

    if ($cod_vendedor <= 0) {
        return null;
    }

    $query = "SELECT cod_zona, nombre_zona, duracion_semanas, orden, fecha_inicio_ciclo
              FROM cmf_comerciales_zonas
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

// === MOTOR: trazas del recomendador ===
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

// === MOTOR: pipeline de decision ===
function obtenerUniversoCandidatosPlanificador($conn, string $query, ?int $iteracionZona = null) {
    planificadorConfigurarDebugLog();

    error_log('RECOMENDADOR SQL:');
    error_log($query);
    error_log('PARAMS:');
    error_log(print_r(array(), true));

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

    return $clientes;
}

function planificadorClienteEsLaborable(array $cliente, int $codVendedor, ?string $fecha = null): bool {
    $fecha = $fecha !== null && validarFechaSQL($fecha) ? $fecha : date('Y-m-d');
    $clienteCalendario = [
        'poblacion' => trim((string)($cliente['poblacion'] ?? '')),
        'provincia' => trim((string)($cliente['provincia'] ?? '')),
        'cod_municipio_ine' => trim((string)($cliente['cod_municipio_ine'] ?? '')),
    ];

    return esDiaLaborable($fecha, $clienteCalendario, $codVendedor);
}

function filtrarClientesElegiblesPlanificador($clientes, ?int $iteracionZona = null, ?int $codVendedor = null, ?string $fecha = null) {
    if (!is_array($clientes)) {
        return null;
    }

    error_log('Clientes candidatos: ' . count($clientes));

    if (empty($clientes)) {
        return array();
    }

    if ($iteracionZona !== null) {
        $clientes = array_values(array_filter($clientes, function ($cliente) {
            return (int)($cliente['toca_visita'] ?? 0) === 1;
        }));

        error_log('Clientes candidatos tras filtro de frecuencia: ' . count($clientes));
    }

    if ($codVendedor !== null && $codVendedor > 0) {
        $clientes = array_values(array_filter($clientes, function ($cliente) use ($codVendedor, $fecha) {
            return planificadorClienteEsLaborable((array)$cliente, $codVendedor, $fecha);
        }));

        error_log('Clientes candidatos tras filtro de calendario: ' . count($clientes));
    }

    return $clientes;
}

function calcularScoreClientesPlanificador($clientes) {
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

function seleccionarMejorClientePlanificador($clientes, string $origenRecomendacion) {
    if (!is_array($clientes)) {
        return null;
    }

    if (empty($clientes)) {
        return null;
    }

    $top5Resumen = array_map(function ($cliente) {
        return array(
            'cod_cliente' => $cliente['cod_cliente'] ?? null,
            'nombre' => $cliente['nombre'] ?? '',
            'frecuencia_visita' => $cliente['frecuencia_visita'] ?? null,
            'iteracion_zona' => $cliente['iteracion_zona'] ?? null,
            'toca_visita' => $cliente['toca_visita'] ?? null,
            'score' => $cliente['score'] ?? 0,
            'motivo_score' => $cliente['motivo_score'] ?? '',
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

function obtenerUniversoCandidatos($conn, string $query, ?int $iteracionZona = null) {
    return obtenerUniversoCandidatosPlanificador($conn, $query, $iteracionZona);
}

function filtrarElegibles($clientes, ?int $iteracionZona = null) {
    return filtrarClientesElegiblesPlanificador($clientes, $iteracionZona);
}

function calcularScoreClientes($clientes) {
    return calcularScoreClientesPlanificador($clientes);
}

function seleccionarMejorCliente($clientes, string $origenRecomendacion) {
    return seleccionarMejorClientePlanificador($clientes, $origenRecomendacion);
}

// === MOTOR: compatibilidad del pipeline anterior ===
function obtenerClienteRecomendadoPorQuery($conn, string $query, string $origenRecomendacion, ?int $iteracionZona = null) {
    planificadorConfigurarDebugLog();

    $universo = obtenerUniversoCandidatosPlanificador($conn, $query, $iteracionZona);
    if ($universo === null) {
        return null;
    }

    $elegibles = filtrarClientesElegiblesPlanificador($universo, $iteracionZona);
    if (empty($elegibles)) {
        return null;
    }

    $scored = calcularScoreClientesPlanificador($elegibles);

    return seleccionarMejorClientePlanificador($scored, $origenRecomendacion);
}

// === MOTOR: orquestacion final del recomendador ===
function obtenerSiguienteClienteRecomendado($zonaActivaId = 0, $codVendedor = null) {
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
            $hoy = date('Y-m-d');
            $dias = (strtotime($hoy) - strtotime(substr($fechaInicioCiclo, 0, 10))) / 86400;
            $semanasTranscurridas = (int)floor($dias / 7);

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
    error_log('DEBUG HOY: ' . $fechaHoy);

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
        $queryZonaDebug = "
            SELECT TOP 50
                c.cod_cliente,
                c.nombre_comercial AS nombre,
                ISNULL(c.provincia, '') AS provincia,
                ISNULL(c.poblacion, '') AS poblacion,
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
            LEFT JOIN cmf_comerciales_clientes_zona z
                ON z.cod_cliente = c.cod_cliente
            LEFT JOIN cmf_comerciales_visitas v
                ON v.cod_cliente = c.cod_cliente
                AND v.cod_vendedor = c.cod_vendedor
                $filtroVisitasCicloActual
                AND v.estado_visita = 'Realizada'
            LEFT JOIN cmf_comerciales_visitas_pedidos vp
                ON vp.id_visita = v.id_visita
            WHERE c.cod_vendedor = '$codVendedor'
              $filtrosOperativos
            GROUP BY
                c.cod_cliente,
                c.nombre_comercial,
                c.provincia,
                c.poblacion,
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

        $universo = obtenerUniversoCandidatosPlanificador($conn, $queryZonaNivel1, $iteracionZona);
        $elegibles = filtrarClientesElegiblesPlanificador($universo, $iteracionZona, $codVendedor, $fechaHoy);
        $scored = calcularScoreClientesPlanificador($elegibles);
        $clienteZona = seleccionarMejorClientePlanificador($scored, 'zona');
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

        $universo = obtenerUniversoCandidatosPlanificador($conn, $queryZonaNivel2, $iteracionZona);
        $elegibles = filtrarClientesElegiblesPlanificador($universo, $iteracionZona, $codVendedor, $fechaHoy);
        $scored = calcularScoreClientesPlanificador($elegibles);
        $clienteZona = seleccionarMejorClientePlanificador($scored, 'zona');
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
            NULL AS frecuencia_visita,
            0 AS toca_visita
        FROM clientes c
        LEFT JOIN cmf_comerciales_visitas v
            ON v.cod_cliente = c.cod_cliente
            AND v.cod_vendedor = c.cod_vendedor
        WHERE c.cod_vendedor = '$codVendedor'
          $filtrosOperativos
        GROUP BY c.cod_cliente, c.nombre_comercial, c.provincia, c.poblacion
        ORDER BY
            CASE WHEN MAX(v.fecha_visita) IS NULL THEN 0 ELSE 1 END ASC,
            MAX(v.fecha_visita) ASC
    ";

    $universo = obtenerUniversoCandidatosPlanificador($conn, $queryGlobal);
    $elegibles = filtrarClientesElegiblesPlanificador($universo, null, $codVendedor, $fechaHoy);
    $scored = calcularScoreClientesPlanificador($elegibles);
    $clienteGlobal = seleccionarMejorClientePlanificador($scored, 'global');
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
            NULL AS frecuencia_visita,
            0 AS toca_visita
        FROM clientes c
        WHERE c.cod_vendedor = '$codVendedor'
          $filtrosOperativos
        ORDER BY
            c.nombre_comercial ASC
    ";

    $universo = obtenerUniversoCandidatosPlanificador($conn, $queryFallback);
    $elegibles = filtrarClientesElegiblesPlanificador($universo, null, $codVendedor, $fechaHoy);
    $scored = calcularScoreClientesPlanificador($elegibles);
    $clienteFallback = seleccionarMejorClientePlanificador($scored, 'fallback');
    if (!empty($clienteFallback)) {
        return $clienteFallback;
    }

    return [];
}

/**
 * Obtener informaciÃ³n de una zona por su cÃ³digo
 */

// ==========================
// VIEW: preparacion de vistas del modulo
if (!function_exists('obtenerDatosZonasView')) {
    function obtenerDatosZonasView() {
        return planificadorViewObtenerDatosZonas();
    }
}
if (!function_exists('obtenerDatosZonasClientesView')) {
    function obtenerDatosZonasClientesView($cod_zona = null) {
        return planificadorViewObtenerDatosZonasClientes($cod_zona);
    }
}
if (!function_exists('obtenerDatosZonasRutasView')) {
    function obtenerDatosZonasRutasView($cod_zona = null, $cod_ruta_seleccionada = 0) {
        return planificadorViewObtenerDatosZonasRutas($cod_zona, $cod_ruta_seleccionada);
    }
}
if (!function_exists('obtenerDatosCompletarDia')) {
    function obtenerDatosCompletarDia($codigo_vendedor, $fecha) {
        return planificadorViewObtenerDatosCompletarDia($codigo_vendedor, $fecha);
    }
}

// ==========================
// COMPATIBILIDAD: wrappers usados por views y controllers legacy
// ==========================

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

if (!function_exists('eliminarRutaZonaService')) {
    function eliminarRutaZonaService($cod_zona, $cod_ruta) {
        return eliminarRutaZona($cod_zona, $cod_ruta);
    }
}

if (!function_exists('eliminarRutaZonaSeguraService')) {
    function eliminarRutaZonaSeguraService($cod_zona, $cod_ruta) {
        return eliminarRutaZonaSegura($cod_zona, $cod_ruta);
    }
}

if (!function_exists('eliminarZonaSeguraService')) {
    function eliminarZonaSeguraService($cod_zona) {
        return eliminarZonaSegura($cod_zona);
    }
}

}


