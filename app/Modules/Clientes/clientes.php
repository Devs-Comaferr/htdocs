<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';

// Verificar sesión


// Encabezado UTF-8
header('Content-Type: text/html; charset=utf-8');
$pageTitle = "Clientes";

// Incluir conexión y funciones
require_once BASE_PATH . '/app/Support/functions.php'; // Aquí debe existir toUTF8($data)

require_once BASE_PATH . '/app/Support/db.php';

$conn = db();

/* =============================================================================
   1) Capturar filtros GET
   ============================================================================= */
$codigo_vendedor = $_SESSION['codigo'] ?? null;
if (
    $codigo_vendedor === '' ||
    $codigo_vendedor === 0 ||
    $codigo_vendedor === '0' ||
    (is_string($codigo_vendedor) && strtoupper(trim($codigo_vendedor)) === 'NULL')
) {
    $codigo_vendedor = null;
}

// Aquí definimos una función para convertir el término buscado a CP1252:
function toCP1252(string $data): string {
    // Convierte desde UTF-8 (lo que envía el navegador) a Windows-1252
    return mb_convert_encoding($data, 'Windows-1252', 'UTF-8');
}

function executePreparedQuery($conn, string $sql, array $params = []): mixed {
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        error_log('clientes.php: error preparando consulta ODBC: ' . odbc_errormsg($conn));
        return false;
    }

    if (!odbc_execute($stmt, $params)) {
        error_log('clientes.php: error ejecutando consulta ODBC: ' . odbc_errormsg($conn));
        return false;
    }

    return $stmt;
}

function buildInClausePlaceholders(array $values): string {
    return implode(', ', array_fill(0, count($values), '?'));
}

function normalizarClaveSeccionVisita(mixed $codSeccion): string|int {
    if ($codSeccion === null || $codSeccion === 'NULL') {
        return 'NULL';
    }

    return (int)$codSeccion;
}

function normalizarFrecuenciaPlanificacion(string $frecuencia): string {
    $frecuencia = strtoupper(trim($frecuencia));
    return match ($frecuencia) {
        'NUNCA', 'TODOS', 'CADA2', 'CADA3' => $frecuencia,
        default => 'TODOS',
    };
}

function construirContextoCicloPlanificacion(array $zonasCicloVendedor, int $hoyTs): ?array {
    if (empty($zonasCicloVendedor)) {
        return null;
    }

    usort($zonasCicloVendedor, static function (array $a, array $b): int {
        $ordenA = (int)($a['orden'] ?? 0);
        $ordenB = (int)($b['orden'] ?? 0);
        if ($ordenA === $ordenB) {
            return (int)($a['cod_zona'] ?? 0) <=> (int)($b['cod_zona'] ?? 0);
        }
        return $ordenA <=> $ordenB;
    });

    $fechaInicioCiclo = '';
    foreach ($zonasCicloVendedor as $zonaCiclo) {
        $fechaInicioTmp = trim((string)($zonaCiclo['fecha_inicio_ciclo'] ?? ''));
        if ($fechaInicioTmp !== '') {
            $fechaInicioCiclo = $fechaInicioTmp;
            break;
        }
    }
    if ($fechaInicioCiclo === '') {
        return null;
    }

    $inicioCicloTs = strtotime(substr($fechaInicioCiclo, 0, 10) . ' 00:00:00');
    if ($inicioCicloTs === false) {
        return null;
    }

    $zonasOrdenadas = [];
    $cicloTotalSemanas = 0;
    foreach ($zonasCicloVendedor as $zonaCiclo) {
        $duracion = max(0, (int)($zonaCiclo['duracion_semanas'] ?? 0));
        if ($duracion <= 0) {
            continue;
        }
        $zonasOrdenadas[] = [
            'cod_zona' => (int)($zonaCiclo['cod_zona'] ?? 0),
            'duracion_semanas' => $duracion,
            'orden' => (int)($zonaCiclo['orden'] ?? 0),
        ];
        $cicloTotalSemanas += $duracion;
    }
    if ($cicloTotalSemanas <= 0 || empty($zonasOrdenadas)) {
        return null;
    }

    $segundosSemana = 7 * 24 * 60 * 60;
    $diferenciaSemanas = ($hoyTs >= $inicioCicloTs)
        ? (int)floor(($hoyTs - $inicioCicloTs) / $segundosSemana)
        : 0;
    $indiceCicloActual = (int)floor($diferenciaSemanas / $cicloTotalSemanas);
    $semanaCiclo = ($diferenciaSemanas % $cicloTotalSemanas) + 1;

    $zonaActual = null;
    $indiceZonaActual = 0;
    $semanaAcumulada = 0;
    foreach ($zonasOrdenadas as $indiceZona => $zonaCiclo) {
        $semanaAcumulada += $zonaCiclo['duracion_semanas'];
        if ($semanaCiclo <= $semanaAcumulada) {
            $zonaActual = (int)$zonaCiclo['cod_zona'];
            $indiceZonaActual = $indiceZona;
            break;
        }
    }
    if ($zonaActual === null) {
        return null;
    }

    $cicloActualInicioTs = $inicioCicloTs + ($indiceCicloActual * $cicloTotalSemanas * $segundosSemana);
    $cicloActualFinTs = $cicloActualInicioTs + ($cicloTotalSemanas * $segundosSemana);

    return [
        'inicio_ciclo_ts' => $inicioCicloTs,
        'ciclo_total_semanas' => $cicloTotalSemanas,
        'indice_ciclo_actual' => $indiceCicloActual,
        'numero_ciclo_actual' => $indiceCicloActual + 1,
        'semana_ciclo' => $semanaCiclo,
        'ciclo_actual_inicio_ts' => $cicloActualInicioTs,
        'ciclo_actual_fin_ts' => $cicloActualFinTs,
        'zona_actual' => $zonaActual,
        'zona_siguiente' => (int)($zonasOrdenadas[($indiceZonaActual + 1) % count($zonasOrdenadas)]['cod_zona'] ?? 0),
    ];
}

function calcularNumeroCicloParaFecha(int $fechaTs, array $contextoCiclo): ?int {
    $inicioCicloTs = (int)($contextoCiclo['inicio_ciclo_ts'] ?? 0);
    $cicloTotalSemanas = (int)($contextoCiclo['ciclo_total_semanas'] ?? 0);
    if ($inicioCicloTs <= 0 || $cicloTotalSemanas <= 0 || $fechaTs < $inicioCicloTs) {
        return null;
    }

    $segundosSemana = 7 * 24 * 60 * 60;
    $diferenciaSemanas = (int)floor(($fechaTs - $inicioCicloTs) / $segundosSemana);
    return (int)floor($diferenciaSemanas / $cicloTotalSemanas) + 1;
}

function calcularClaseEstadoVisualVisita(
    string $codCliente,
    array $visitasPlanificacionPorClienteSeccion,
    array $seccionesValidasCliente,
    array $zonasCicloVendedor,
    array $zonaPrincipalClienteSeccion,
    array $frecuenciaPorClienteSeccion,
    int $hoyTs
): string {
    $seccionesValidasCliente = array_map(
        fn($s) => normalizarClaveSeccionVisita($s),
        $seccionesValidasCliente
    );
    $seccionesValidasCliente = array_values(array_unique($seccionesValidasCliente));
    if (empty($seccionesValidasCliente)) {
        return '';
    }

    $contextoCiclo = construirContextoCicloPlanificacion($zonasCicloVendedor, $hoyTs);
    if ($contextoCiclo === null) {
        return '';
    }

    $cicloActualInicioTs = (int)$contextoCiclo['ciclo_actual_inicio_ts'];
    $cicloActualFinTs = (int)$contextoCiclo['ciclo_actual_fin_ts'];
    $numeroCicloActual = (int)$contextoCiclo['numero_ciclo_actual'];
    $zonaActual = (int)$contextoCiclo['zona_actual'];
    $hayPlanificada = false;
    $hayCritica = false;
    $hayVencida = false;
    $hayCorrecta = false;

    foreach ($seccionesValidasCliente as $codSeccion) {
        $zonaCliente = (int)($zonaPrincipalClienteSeccion[$codSeccion] ?? 0);
        $frecuencia = normalizarFrecuenciaPlanificacion((string)($frecuenciaPorClienteSeccion[$codSeccion] ?? 'TODOS'));
        $claveSeccion = normalizarClaveSeccionVisita($codSeccion);
        $visitasSeccion = $visitasPlanificacionPorClienteSeccion[$codCliente][$claveSeccion] ?? [];

        if ($zonaCliente !== $zonaActual) {
            foreach ($visitasSeccion as $visitaSeccion) {
                $fechaVisita = trim((string)($visitaSeccion['fecha_visita'] ?? ''));
                if ($fechaVisita === '') {
                    continue;
                }
                $tsVisita = strtotime($fechaVisita);
                if ($tsVisita !== false && $tsVisita >= $hoyTs) {
                    $hayPlanificada = true;
                    break;
                }
            }
            continue;
        }

        if ($frecuencia === 'NUNCA') {
            continue;
        }

        $ultimoCicloRealizado = null;
        $tieneRealizadaEnCicloActual = false;

        foreach ($visitasSeccion as $visitaSeccion) {
            $fechaVisita = trim((string)($visitaSeccion['fecha_visita'] ?? ''));
            if ($fechaVisita !== '') {
                $tsVisita = strtotime($fechaVisita);
                if ($tsVisita !== false && $tsVisita >= $hoyTs) {
                    $hayPlanificada = true;
                }
            }

            $estadoVisita = normalizarEstadoVisitaClave((string)($visitaSeccion['estado_visita'] ?? ''));
            if ($estadoVisita !== 'realizada') {
                continue;
            }

            if ($fechaVisita === '') {
                continue;
            }
            if ($tsVisita === false) {
                continue;
            }

            if ($tsVisita >= $cicloActualInicioTs && $tsVisita < $cicloActualFinTs) {
                $tieneRealizadaEnCicloActual = true;
            }

            $numeroCicloVisita = calcularNumeroCicloParaFecha($tsVisita, $contextoCiclo);
            if ($numeroCicloVisita !== null && ($ultimoCicloRealizado === null || $numeroCicloVisita > $ultimoCicloRealizado)) {
                $ultimoCicloRealizado = $numeroCicloVisita;
            }
        }

        if ($tieneRealizadaEnCicloActual) {
            $hayCorrecta = true;
            continue;
        }

        $diferenciaCiclos = ($ultimoCicloRealizado === null) ? null : ($numeroCicloActual - $ultimoCicloRealizado);
        $limiteCritico = match ($frecuencia) {
            'TODOS' => 2,
            'CADA2' => 4,
            'CADA3' => 6,
            default => null,
        };
        if ($limiteCritico !== null && $diferenciaCiclos !== null && $diferenciaCiclos >= $limiteCritico) {
            $hayCritica = true;
        }
        $tocaEnCicloActual = match ($frecuencia) {
            'TODOS' => $diferenciaCiclos === null || $diferenciaCiclos >= 1,
            'CADA2' => $diferenciaCiclos === null || $diferenciaCiclos >= 2,
            'CADA3' => $diferenciaCiclos === null || $diferenciaCiclos >= 3,
            default => false,
        };

        if (!$tocaEnCicloActual) {
            continue;
        }

        if (!$tieneRealizadaEnCicloActual) {
            $hayVencida = true;
            continue;
        }
    }

    if ($hayPlanificada) {
        return 'plan-visita-planificada';
    }

    if ($hayCritica) {
        return 'plan-visita-critica';
    }

    if ($hayVencida) {
        return 'plan-visita-vencida';
    }

    if ($hayCorrecta) {
        return 'plan-visita-correcta';
    }

    if ($haySeccionZonaSiguiente) {
        return 'plan-visita-proxima';
    }

    return '';
}

// Leer variables GET (en UTF-8), luego convertiremos a CP1252 solo para usarlas en la query
$cod_cliente_utf8      = filter_input(INPUT_GET, 'cod_cliente', FILTER_UNSAFE_RAW) ?? '';
$nombre_comercial_utf8 = filter_input(INPUT_GET, 'nombre_comercial', FILTER_UNSAFE_RAW) ?? '';
$provincia_utf8        = filter_input(INPUT_GET, 'provincia', FILTER_UNSAFE_RAW) ?? '';
$poblacion_utf8        = filter_input(INPUT_GET, 'poblacion', FILTER_UNSAFE_RAW) ?? '';
$filtro_vendedor_utf8  = '';

if (is_null($codigo_vendedor)) {
    $filtro_vendedor_utf8 = filter_input(INPUT_GET, 'vendedor', FILTER_UNSAFE_RAW) ?? '';
}
define('FILTRO_SIN_VENDEDOR', '__sin_vendedor__');

// Convertir a CP1252 las cadenas que podrías comparar con LIKE
$cod_cliente      = toCP1252($cod_cliente_utf8);
$nombre_comercial = toCP1252($nombre_comercial_utf8);
$provincia        = toCP1252($provincia_utf8);
$poblacion        = toCP1252($poblacion_utf8);
$filtro_vendedor  = toCP1252($filtro_vendedor_utf8);
$currentYear = date('Y');
$year1 = $currentYear - 1;
$year2 = $currentYear - 2;
$mostrarUltimaVisita = (
    isset($_SESSION['tipo_plan']) &&
    $_SESSION['tipo_plan'] === 'premium' &&
    isset($_SESSION['perm_planificador']) &&
    (int)$_SESSION['perm_planificador'] === 1
);
$esPremiumPlanificador = $mostrarUltimaVisita;

// Orden
$order_by_utf8 = filter_input(INPUT_GET, 'order_by', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'cli.nombre_comercial';
$order_dir_utf8 = filter_input(INPUT_GET, 'order_dir', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'asc';
$order_dir = strtolower($order_dir_utf8);
if ($order_dir !== 'asc' && $order_dir !== 'desc') {
    $order_dir = 'asc';
}
$orderByWhitelist = [
    'cli.cod_cliente' => 'cli.cod_cliente',
    'cli.nombre_comercial' => 'cli.nombre_comercial',
    'cli.provincia' => 'cli.provincia',
    'cli.poblacion' => 'cli.poblacion',
    'ultima_fecha_venta' => 'ultima_fecha_venta',
    'importe_' . $currentYear => 'importe_' . $currentYear,
    'importe_' . $year1 => 'importe_' . $year1,
    'importe_' . $year2 => 'importe_' . $year2,
];
if (is_null($codigo_vendedor)) {
    $orderByWhitelist['cli.cod_vendedor'] = 'cli.cod_vendedor';
}
if ($mostrarUltimaVisita) {
    $orderByWhitelist['ultima_fecha_visita'] = 'ultima_fecha_visita';
}
$order_by = $orderByWhitelist[$order_by_utf8] ?? 'cli.nombre_comercial';

/* =============================================================================
   2) Construir listas (Provincias, Poblaciones, Vendedores) en CP1252
   ============================================================================= */
$whereVendedorBase = ['1=1'];
$paramsVendedorBase = [];
if (!is_null($codigo_vendedor)) {
    $whereVendedorBase[] = 'cli.cod_vendedor = ?';
    $paramsVendedorBase[] = (int)$codigo_vendedor;
}

// PROVINCIAS
$whereProvincias = $whereVendedorBase;
$paramsProvincias = $paramsVendedorBase;
$whereProvincias[] = 'cli.provincia IS NOT NULL';
if ($poblacion !== '') {
    $whereProvincias[] = 'cli.poblacion = ?';
    $paramsProvincias[] = $poblacion;
}
if ($filtro_vendedor !== '' && is_null($codigo_vendedor)) {
    if ($filtro_vendedor_utf8 === FILTRO_SIN_VENDEDOR) {
        $whereProvincias[] = '(cli.cod_vendedor IS NULL OR cli.cod_vendedor = 0)';
    } else {
        $whereProvincias[] = 'cli.cod_vendedor = ?';
        $paramsProvincias[] = $filtro_vendedor;
    }
}
$sql_provincias = "
    SELECT DISTINCT cli.provincia
    FROM clientes cli
    WHERE " . implode("\n      AND ", $whereProvincias) . "
    ORDER BY cli.provincia
";
$resProv = executePreparedQuery($conn, $sql_provincias, $paramsProvincias);
$provincias = [];
while ($resProv && ($row = odbc_fetch_array($resProv))) {
    // Convertimos a UTF-8 para mostrar en <option>
    $provCP1252 = $row['provincia'] ?? '';
    $provincias[] = toUTF8($provCP1252);
}

// POBLACIONES
$wherePoblaciones = $whereVendedorBase;
$paramsPoblaciones = $paramsVendedorBase;
$wherePoblaciones[] = 'cli.poblacion IS NOT NULL';
if ($provincia !== '') {
    $wherePoblaciones[] = 'cli.provincia = ?';
    $paramsPoblaciones[] = $provincia;
}
if ($filtro_vendedor !== '' && is_null($codigo_vendedor)) {
    if ($filtro_vendedor_utf8 === FILTRO_SIN_VENDEDOR) {
        $wherePoblaciones[] = '(cli.cod_vendedor IS NULL OR cli.cod_vendedor = 0)';
    } else {
        $wherePoblaciones[] = 'cli.cod_vendedor = ?';
        $paramsPoblaciones[] = $filtro_vendedor;
    }
}
$sql_poblaciones = "
    SELECT DISTINCT cli.poblacion
    FROM clientes cli
    WHERE " . implode("\n      AND ", $wherePoblaciones) . "
    ORDER BY cli.poblacion
";
$resPob = executePreparedQuery($conn, $sql_poblaciones, $paramsPoblaciones);
$poblaciones = [];
while ($resPob && ($row = odbc_fetch_array($resPob))) {
    $pobCP1252 = $row['poblacion'] ?? '';
    $poblaciones[] = toUTF8($pobCP1252);
}

// VENDEDORES (solo si admin)
$vendedores = [];
if (is_null($codigo_vendedor)) {
    $whereVendedores = [
        'cli.cod_vendedor IS NOT NULL',
        'cli.cod_vendedor != 0',
    ];
    $paramsVendedores = [];
    if ($provincia !== '') {
        $whereVendedores[] = 'cli.provincia = ?';
        $paramsVendedores[] = $provincia;
    }
    if ($poblacion !== '') {
        $whereVendedores[] = 'cli.poblacion = ?';
        $paramsVendedores[] = $poblacion;
    }
    $sql_vendedores = "
        SELECT DISTINCT ven.cod_vendedor, ven.nombre AS nombre_vendedor
        FROM clientes cli
        JOIN vendedores ven ON cli.cod_vendedor = ven.cod_vendedor
        WHERE " . implode("\n          AND ", $whereVendedores) . "
        ORDER BY ven.nombre
    ";

    $resVend = executePreparedQuery($conn, $sql_vendedores, $paramsVendedores);
    while ($resVend && ($row = odbc_fetch_array($resVend))) {
        // Convertir a UTF-8 para mostrar
        $vendedores[] = [
            'cod_vendedor'    => $row['cod_vendedor'],
            'nombre_vendedor' => toUTF8($row['nombre_vendedor'])
        ];
    }
}

/* =============================================================================
   3) Consulta principal (Clientes) en CP1252
   ============================================================================= */

// Condición + subfiltro
$escapeSqlValue = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$whereClientes = ['1=1'];
$joinFiltroComisionista = '';
if (!is_null($codigo_vendedor)) {
    $whereClientes[] = 'cli.cod_vendedor = ' . (int)$codigo_vendedor;
}
if (is_null($codigo_vendedor) && $filtro_vendedor !== '') {
    if ($filtro_vendedor_utf8 === FILTRO_SIN_VENDEDOR) {
        $whereClientes[] = '(cli.cod_vendedor IS NULL OR cli.cod_vendedor = 0)';
    } else {
        $whereClientes[] = 'cli.cod_vendedor = ' . $escapeSqlValue((string)$filtro_vendedor);
    }
}

// Filtro por comisionista (si es Agustín Castro con código ''30'')
if (!is_null($codigo_vendedor) && $codigo_vendedor === '30') {
    $joinFiltroComisionista = ' AND vent.cod_comisionista = ' . (int)$codigo_vendedor;
}

$whereClientes[] = "cli.nombre_comercial NOT LIKE '** CLIENTE NUEVO%'";
$whereClientes[] = "cli.cod_cliente != '99998'";
if ($nombre_comercial !== '') {
    $whereClientes[] = 'cli.nombre_comercial LIKE ' . $escapeSqlValue('%' . $nombre_comercial . '%');
}
if ($cod_cliente !== '') {
    $whereClientes[] = 'cli.cod_cliente = ' . $escapeSqlValue($cod_cliente);
}
if ($provincia !== '') {
    $whereClientes[] = 'cli.provincia = ' . $escapeSqlValue($provincia);
}
if ($poblacion !== '') {
    $whereClientes[] = 'cli.poblacion = ' . $escapeSqlValue($poblacion);
}

// Armar SQL principal
$sql = "
SELECT " . (is_null($codigo_vendedor) ? "cli.cod_vendedor AS vendedor," : "") . "
       cli.cod_cliente,
       cli.nombre_comercial,
       cli.provincia,
       cli.poblacion,
       MAX(vent.fecha_venta) AS ultima_fecha_venta,
       " . ($mostrarUltimaVisita ? "MAX(uv.ultima_fecha_visita) AS ultima_fecha_visita,
       MAX(uv.estado_ultima_visita) AS estado_ultima_visita,
       MAX(uv.origen_ultima_visita) AS origen_ultima_visita," : "") . "
       " . subConsultaImporteAnual('importe_'.$currentYear, $currentYear) . ",
       " . subConsultaImporteAnual('importe_'.$year1, $year1) . ",
       " . subConsultaImporteAnual('importe_'.$year2, $year2) . "
FROM clientes cli
LEFT JOIN hist_ventas_cabecera vent
       ON vent.cod_cliente = cli.cod_cliente
      AND vent.tipo_venta = 1
      {$joinFiltroComisionista}
" . ($mostrarUltimaVisita ? "OUTER APPLY (
       SELECT TOP 1
              v.fecha_visita AS ultima_fecha_visita,
              LOWER(v.estado_visita) AS estado_ultima_visita,
              COALESCE((
                  SELECT TOP 1 LOWER(vp.origen)
                  FROM cmf_visita_pedidos vp
                  WHERE vp.id_visita = v.id_visita
                  ORDER BY vp.id_visita_pedido DESC
              ), '') AS origen_ultima_visita
       FROM cmf_visitas_comerciales v
       WHERE v.cod_cliente = cli.cod_cliente
         AND (
               (
                   LOWER(v.estado_visita) = 'realizada'
                   AND EXISTS (
                       SELECT 1
                       FROM cmf_visita_pedidos vp
                       WHERE vp.id_visita = v.id_visita
                         AND LOWER(vp.origen) = 'visita'
                   )
               )
               OR LOWER(v.estado_visita) IN ('planificada', 'descartada')
         )
       ORDER BY v.fecha_visita DESC, v.id_visita DESC
) uv" : "") . "
WHERE " . implode("\n  AND ", $whereClientes) . "
";

// Orden final
$sql .= "
GROUP BY
    " . (is_null($codigo_vendedor) ? "cli.cod_vendedor," : "") . "
    cli.cod_cliente,
    cli.nombre_comercial,
    cli.provincia,
    cli.poblacion
ORDER BY {$order_by} {$order_dir}
";

$resCli = odbc_exec($conn, $sql);
if (!$resCli) {
    exit('ODBC ERROR: ' . odbc_errormsg($conn));
}

// Recoger filas (aún en CP1252)
$clientes = [];
while ($resCli && ($fila = odbc_fetch_array($resCli))) {
    $clientes[] = $fila;  // Lo convertiremos luego al mostrar
}
$numRegistros = count($clientes);

/* =============================================================================
   4) Paginación
   ============================================================================= */
$limit = 100;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;
$totalPaginas = (int) ceil($numRegistros / $limit);
$clientesPaginados = array_slice($clientes, $offset, $limit);
$ultimasVisitasPorClienteSeccion = [];
$visitasPlanificacionPorClienteSeccion = [];
$seccionesPorCliente = [];
$nombresSeccionPorCliente = [];
$frecuenciaPorClienteSeccion = [];
$zonasPorVendedor = [];
$zonaPrincipalPorClienteSeccion = [];
$seccionesValidasPlanificacionPorCliente = [];

if ($mostrarUltimaVisita && !empty($clientesPaginados)) {
    $codigosVendedor = [];
    foreach ($clientesPaginados as $filaCli) {
        if (!is_null($codigo_vendedor) && is_numeric((string)$codigo_vendedor)) {
            $codigosVendedor[] = (int)$codigo_vendedor;
            continue;
        }
        if (isset($filaCli['vendedor']) && is_numeric((string)$filaCli['vendedor'])) {
            $codVend = (int)$filaCli['vendedor'];
            if ($codVend > 0) {
                $codigosVendedor[] = $codVend;
            }
        }
    }
    $codigosVendedor = array_values(array_unique($codigosVendedor));

    $codigosCliente = [];
    foreach ($clientesPaginados as $filaCli) {
        if (isset($filaCli['cod_cliente']) && $filaCli['cod_cliente'] !== '') {
            $codigosCliente[] = (int)$filaCli['cod_cliente'];
        }
    }
    $codigosCliente = array_values(array_unique($codigosCliente));

    if (!empty($codigosCliente)) {
        $placeholdersClientes = buildInClausePlaceholders($codigosCliente);
        if (!empty($codigosVendedor)) {
            $placeholdersVendedores = buildInClausePlaceholders($codigosVendedor);
            $sqlZonasVendedor = "
                SELECT cod_vendedor, cod_zona, duracion_semanas, orden, fecha_inicio_ciclo
                FROM cmf_zonas_visita
                WHERE cod_vendedor IN ($placeholdersVendedores)
                ORDER BY cod_vendedor, orden, cod_zona
            ";
            $resZonasVendedor = executePreparedQuery($conn, $sqlZonasVendedor, $codigosVendedor);
            if ($resZonasVendedor) {
                while ($filaZona = odbc_fetch_array($resZonasVendedor)) {
                    $codVendZona = (int)($filaZona['cod_vendedor'] ?? 0);
                    if ($codVendZona <= 0) {
                        continue;
                    }
                    if (!isset($zonasPorVendedor[$codVendZona])) {
                        $zonasPorVendedor[$codVendZona] = [];
                    }
                    $zonasPorVendedor[$codVendZona][] = [
                        'cod_zona' => (int)($filaZona['cod_zona'] ?? 0),
                        'duracion_semanas' => (int)($filaZona['duracion_semanas'] ?? 0),
                        'orden' => (int)($filaZona['orden'] ?? 0),
                        'fecha_inicio_ciclo' => (string)($filaZona['fecha_inicio_ciclo'] ?? ''),
                    ];
                }
            }
        }

        // Secciones existentes por cliente (la sección 0 también es válida).
        $sqlSeccionesCliente = "
            SELECT
                sc.cod_cliente,
                sc.cod_seccion AS cod_seccion,
                COALESCE(sc.nombre, '') AS nombre_seccion
            FROM secciones_cliente sc
            WHERE sc.cod_cliente IN ($placeholdersClientes)
        ";
        $resSeccionesCliente = executePreparedQuery($conn, $sqlSeccionesCliente, $codigosCliente);
        if ($resSeccionesCliente) {
            while ($filaSec = odbc_fetch_array($resSeccionesCliente)) {
                $codCliSec = (string)($filaSec['cod_cliente'] ?? '');
                if ($codCliSec === '') {
                    continue;
                }
                if (!isset($seccionesPorCliente[$codCliSec])) {
                    $seccionesPorCliente[$codCliSec] = [];
                }
                if (!isset($nombresSeccionPorCliente[$codCliSec])) {
                    $nombresSeccionPorCliente[$codCliSec] = [];
                }
                $codSec = (int)($filaSec['cod_seccion'] ?? 0);
                if (!in_array($codSec, $seccionesPorCliente[$codCliSec], true)) {
                    $seccionesPorCliente[$codCliSec][] = $codSec;
                }
                $nombreSec = trim((string)($filaSec['nombre_seccion'] ?? ''));
                if ($nombreSec !== '') {
                    $nombresSeccionPorCliente[$codCliSec][$codSec] = $nombreSec;
                }
            }
        }

        $sqlVisitasSeccion = "
            WITH visitas_validas AS (
                SELECT
                    v.cod_cliente,
                    v.cod_seccion AS cod_seccion,
                    v.fecha_visita,
                    LOWER(v.estado_visita) AS estado_visita,
                    COALESCE((
                        SELECT TOP 1 LOWER(vp.origen)
                        FROM cmf_visita_pedidos vp
                        WHERE vp.id_visita = v.id_visita
                        ORDER BY vp.id_visita_pedido DESC
                    ), '') AS origen_visita,
                    ROW_NUMBER() OVER (
                        PARTITION BY v.cod_cliente, v.cod_seccion
                        ORDER BY v.fecha_visita DESC, v.id_visita DESC
                    ) AS rn
                FROM cmf_visitas_comerciales v
                WHERE v.cod_cliente IN ($placeholdersClientes)
            )
            SELECT cod_cliente, cod_seccion, fecha_visita, estado_visita, origen_visita
            FROM visitas_validas
            WHERE rn = 1
            ORDER BY cod_cliente, cod_seccion
        ";

        $resVisitasSeccion = executePreparedQuery($conn, $sqlVisitasSeccion, $codigosCliente);
        if ($resVisitasSeccion) {
            while ($filaVis = odbc_fetch_array($resVisitasSeccion)) {
                $codCli = (string)($filaVis['cod_cliente'] ?? '');
                if ($codCli === '') {
                    continue;
                }
                if (!isset($ultimasVisitasPorClienteSeccion[$codCli])) {
                    $ultimasVisitasPorClienteSeccion[$codCli] = [];
                }
                $codSec = normalizarClaveSeccionVisita($filaVis['cod_seccion'] ?? null);
                $ultimasVisitasPorClienteSeccion[$codCli][$codSec] = [
                    'cod_seccion' => $codSec,
                    'fecha_visita' => (string)($filaVis['fecha_visita'] ?? ''),
                    'estado_visita' => normalizarEstadoVisitaClave((string)($filaVis['estado_visita'] ?? '')),
                    'origen_visita' => strtolower(trim((string)($filaVis['origen_visita'] ?? '')))
                ];
            }
        }

        // Frecuencia de visita por cliente/sección desde asignación de zonas.
        $sqlFrecuencias = "
            SELECT
                azc.cod_cliente,
                azc.cod_seccion,
                UPPER(COALESCE(azc.frecuencia_visita, 'Todos')) AS frecuencia_visita
            FROM cmf_asignacion_zonas_clientes azc
            WHERE azc.cod_cliente IN ($placeholdersClientes)
        ";
        $resFrecuencias = executePreparedQuery($conn, $sqlFrecuencias, $codigosCliente);
        if ($resFrecuencias) {
            while ($filaFreq = odbc_fetch_array($resFrecuencias)) {
                $codCliFreq = (string)($filaFreq['cod_cliente'] ?? '');
                if ($codCliFreq === '') {
                    continue;
                }
                if (!isset($frecuenciaPorClienteSeccion[$codCliFreq])) {
                    $frecuenciaPorClienteSeccion[$codCliFreq] = [];
                }
                $codSecFreq = normalizarClaveSeccionVisita($filaFreq['cod_seccion'] ?? null);
                $freq = strtoupper(trim((string)($filaFreq['frecuencia_visita'] ?? 'TODOS')));
                if ($freq === '') {
                    $freq = 'TODOS';
                }
                $frecuenciaPorClienteSeccion[$codCliFreq][$codSecFreq] = $freq;
                if (!isset($seccionesValidasPlanificacionPorCliente[$codCliFreq])) {
                    $seccionesValidasPlanificacionPorCliente[$codCliFreq] = [];
                }
                if (!in_array($codSecFreq, $seccionesValidasPlanificacionPorCliente[$codCliFreq], true)) {
                    $seccionesValidasPlanificacionPorCliente[$codCliFreq][] = $codSecFreq;
                }
            }
        }

        $sqlZonasCliente = "
            SELECT
                azc.cod_cliente,
                azc.cod_seccion,
                azc.zona_principal
            FROM cmf_asignacion_zonas_clientes azc
            WHERE azc.cod_cliente IN ($placeholdersClientes)
        ";
        $resZonasCliente = executePreparedQuery($conn, $sqlZonasCliente, $codigosCliente);
        if ($resZonasCliente) {
            while ($filaZonaCli = odbc_fetch_array($resZonasCliente)) {
                $codCliZona = (string)($filaZonaCli['cod_cliente'] ?? '');
                if ($codCliZona === '') {
                    continue;
                }
                if (!isset($zonaPrincipalPorClienteSeccion[$codCliZona])) {
                    $zonaPrincipalPorClienteSeccion[$codCliZona] = [];
                }
                $codSeccionZona = normalizarClaveSeccionVisita($filaZonaCli['cod_seccion'] ?? null);
                $zonaPrincipalPorClienteSeccion[$codCliZona][$codSeccionZona] = (int)($filaZonaCli['zona_principal'] ?? 0);
                if (!isset($seccionesValidasPlanificacionPorCliente[$codCliZona])) {
                    $seccionesValidasPlanificacionPorCliente[$codCliZona] = [];
                }
                if (!in_array($codSeccionZona, $seccionesValidasPlanificacionPorCliente[$codCliZona], true)) {
                    $seccionesValidasPlanificacionPorCliente[$codCliZona][] = $codSeccionZona;
                }
            }
        }

        $sqlVisitasPlanificacion = "
            SELECT
                v.cod_cliente,
                v.cod_seccion,
                v.fecha_visita,
                LOWER(v.estado_visita) AS estado_visita
            FROM cmf_visitas_comerciales v
            WHERE v.cod_cliente IN ($placeholdersClientes)
              AND LOWER(v.estado_visita) = 'realizada'
            ORDER BY v.cod_cliente, v.cod_seccion, v.fecha_visita DESC, v.id_visita DESC
        ";
        $resVisitasPlanificacion = executePreparedQuery($conn, $sqlVisitasPlanificacion, $codigosCliente);
        if ($resVisitasPlanificacion) {
            while ($filaVisPlan = odbc_fetch_array($resVisitasPlanificacion)) {
                $codCliVisPlan = (string)($filaVisPlan['cod_cliente'] ?? '');
                if ($codCliVisPlan === '') {
                    continue;
                }
                if (!isset($visitasPlanificacionPorClienteSeccion[$codCliVisPlan])) {
                    $visitasPlanificacionPorClienteSeccion[$codCliVisPlan] = [];
                }
                $codSec = normalizarClaveSeccionVisita($filaVisPlan['cod_seccion'] ?? null);
                if (!isset($visitasPlanificacionPorClienteSeccion[$codCliVisPlan][$codSec])) {
                    $visitasPlanificacionPorClienteSeccion[$codCliVisPlan][$codSec] = [];
                }
                $visitasPlanificacionPorClienteSeccion[$codCliVisPlan][$codSec][] = [
                    'fecha_visita' => (string)($filaVisPlan['fecha_visita'] ?? ''),
                    'estado_visita' => normalizarEstadoVisitaClave((string)($filaVisPlan['estado_visita'] ?? '')),
                ];
            }
        }

        // Si un cliente no tiene secciones en tabla, tratarlo como sección 0.
        foreach ($codigosCliente as $codCliNum) {
            $codCliKey = (string)$codCliNum;
            if (!isset($seccionesPorCliente[$codCliKey]) || empty($seccionesPorCliente[$codCliKey])) {
                $seccionesPorCliente[$codCliKey] = ['NULL'];
            }
            if (!isset($nombresSeccionPorCliente[$codCliKey])) {
                $nombresSeccionPorCliente[$codCliKey] = [];
            }
        }
    }
}

// Preparar query_string en UTF-8 (para que no se rompa en la URL):
$params = [
    'cod_cliente'      => $cod_cliente_utf8,
    'nombre_comercial' => $nombre_comercial_utf8,
    'provincia'        => $provincia_utf8,
    'poblacion'        => $poblacion_utf8,
    'order_by'         => $order_by_utf8,
    'order_dir'        => $order_dir
];
if (is_null($codigo_vendedor)) {
    $params['vendedor'] = $filtro_vendedor_utf8;
}
$query_string = http_build_query($params);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <!-- Para móviles y tablets -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <!-- Bootstrap 5 CSS (local via Composer) -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
  
  <!-- Font Awesome CSS (local via Composer) -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">

  <style>
    /* ============= Estilos del HEADER (fijo en escritorio, etc.) ============= */
    * {
      box-sizing: border-box;
    }
    html, body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
    }
    header, .header {
      background-color: #fff;
    }
    .page-content {
      margin: 0 auto;
      margin-top: 60px;
      padding: 10px;
    }
    @media (min-width: 1025px) {
      header, .header {
        position: fixed;
        top: 0; left: 0; right: 0;
        height: 60px; /* Ajusta a gusto */
        z-index: 9999;
        border-bottom: 1px solid #ddd;
      }
      .page-content {
        margin-top: 60px;
      }
    }
    /* En móvil, si quieres header NO fijo, quita estas líneas
       o cambia la media query. 
       Actual: Header es NO fijo, se deja normal. 
       Si lo quieres fijo en móvil, hazlo y deja margin-top. */

    /* ============= Formulario de filtros ============= */
    .filter-form {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      background-color: #fff;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    .filter-form select,
    .filter-form input {
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 16px;
      min-width: 150px;
    }
    .filter-form button {
      padding: 8px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .filter-form .btn-search {
      background-color: #007BFF;
      color: #fff;
    }
    .filter-form .btn-search:hover {
      background-color: #0056b3;
    }
    .filter-form .btn-clear {
      background-color: #FF4D4D;
      color: #fff;
    }
    .filter-form .btn-clear:hover {
      background-color: #cc0000;
    }

    /* ============= Tabla y paginación ============= */
    .table-container {
      width: 100%;
      overflow-x: auto;
      margin-top: 0;
      border-radius: 8px;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 16px;
      border-radius: 8px;
      overflow: hidden;
    }
    th, td {
      border: 1px solid #ddd;
      padding: 15px;
      text-align: left;
    }
    th {
      background-color: #007BFF;
      color: #fff;
      cursor: pointer;
      white-space: nowrap;
    }
    th a {
      color: #fff;
      text-decoration: none;
    }
    td a {
      color: #000;
      text-decoration: none;
    }
    td a:hover {
      color: #007BFF;
    }
    .year-column {
      white-space: nowrap;
      text-align: right;
      position: relative;
    }
    tr.plan-visita-critica td {
      background: #f1aeb5;
      color: #000000;
    }
    tr.plan-visita-vencida td {
      background: #f5b5b9;
      color: #000000;
    }
    tr.plan-visita-proxima td {
      background: #ffe08a;
      color: #000000;
    }
    tr.plan-visita-correcta td {
      background: #9fd5a6;
      color: #000000;
    }
    tr.plan-visita-planificada td {
      background: #9ec5fe;
      color: #000000;
    }
    tr.clickable-row {
      cursor: pointer;
    }
    .triangle {
      float: left;
    }
    .pagination {
      margin-top: 20px;
      text-align: center;
    }
    .pagination a, .pagination span {
      margin: 0 5px;
      padding: 8px 12px;
      border: 1px solid #ddd;
      color: #007BFF;
      text-decoration: none;
      border-radius: 4px;
    }
    .pagination a:hover {
      background-color: #f0f0f0;
    }
    .pagination .current {
      background-color: #007BFF;
      color: #fff;
      border-color: #007BFF;
    }
    @media (max-width: 1024px) {
      .filter-form {
        flex-direction: column;
      }
      .filter-form select,
      .filter-form input,
      .filter-form button {
        width: 100%;
        font-size: 18px;
      }
      table {
        font-size: 15px;
      }
      /* Si quieres header fijo también en móvil, pon position:fixed y margin-top */
      /* header, .header { ... } .page-content { margin-top: ... } */
    }
  </style>
</head>
<body>
<?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

<div class="page-content">
  <!-- FILTROS -->
  <form class="filter-form" method="GET" action="clientes.php">
    <input type="text" name="cod_cliente" maxlength="6"
           value="<?php echo htmlspecialchars($cod_cliente_utf8); ?>"
           placeholder="Codigo Cliente">
    <input type="text" name="nombre_comercial"
           value="<?php echo htmlspecialchars($nombre_comercial_utf8); ?>"
           placeholder="Nombre Comercial">
    
    <select name="provincia" onchange="this.form.submit()">
      <option value="" <?php if($provincia_utf8 === '') echo 'selected'; ?>>-- Seleccione Provincia --</option>
      <?php foreach($provincias as $prov) { ?>
        <option value="<?php echo htmlspecialchars($prov); ?>"
                <?php if($provincia_utf8 === $prov) echo 'selected'; ?>>
          <?php echo htmlspecialchars($prov); ?>
        </option>
      <?php } ?>
    </select>
    
    <select name="poblacion" onchange="this.form.submit()">
      <option value="" <?php if($poblacion_utf8 === '') echo 'selected'; ?>>-- Seleccione Poblacion --</option>
      <?php foreach($poblaciones as $pobl) { ?>
        <option value="<?php echo htmlspecialchars($pobl); ?>"
                <?php if($poblacion_utf8 === $pobl) echo 'selected'; ?>>
          <?php echo htmlspecialchars($pobl); ?>
        </option>
      <?php } ?>
    </select>
    
    <?php if (is_null($codigo_vendedor)) { ?>
      <select name="vendedor" onchange="this.form.submit()">
        <option value="" <?php if($filtro_vendedor_utf8 === '') echo 'selected'; ?>>-- Seleccione Vendedor --</option>
        <option value="<?= FILTRO_SIN_VENDEDOR ?>" <?php if($filtro_vendedor_utf8 === FILTRO_SIN_VENDEDOR) echo 'selected'; ?>>Sin vendedor</option>
        <?php foreach($vendedores as $vend) { ?>
          <option value="<?php echo htmlspecialchars($vend['cod_vendedor']); ?>"
                  <?php if($filtro_vendedor_utf8 === $vend['cod_vendedor']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($vend['nombre_vendedor']); ?>
          </option>
        <?php } ?>
      </select>
    <?php } ?>
    
    <button type="submit" class="btn-search">
      <i class="fas fa-search"></i> Buscar
    </button>
    <button type="button" class="btn-clear"
            onclick="window.location.href='clientes.php';">
      <i class="fas fa-trash-alt"></i> Limpiar
    </button>
  </form>
  
  <!-- TABLA DE RESULTADOS -->
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <?php if (is_null($codigo_vendedor)) { ?>
            <th>
              <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=cli.cod_vendedor&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
                Vendedor
              </a>
            </th>
          <?php } ?>
          <th>
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=cli.cod_cliente&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              ID
            </a>
          </th>
          <th>
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=cli.nombre_comercial&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              Nombre Comercial
            </a>
          </th>
          <th>
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=cli.provincia&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              Provincia
            </a>
          </th>
          <th>
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=cli.poblacion&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              Poblacion
            </a>
          </th>
          <?php if ($mostrarUltimaVisita): ?>
          <th>
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=ultima_fecha_visita&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              Ultima Visita
            </a>
          </th>
          <?php endif; ?>
          <th>
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=ultima_fecha_venta&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              Ultimo Pedido
            </a>
          </th>
          <th class="year-column">
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=importe_<?php echo $currentYear; ?>&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              <?php echo $currentYear; ?>
            </a>
          </th>
          <th class="year-column">
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=importe_<?php echo $year1; ?>&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              <?php echo $year1; ?>
            </a>
          </th>
          <th class="year-column">
            <a href="?<?php echo $query_string; ?>&page=<?php echo $page; ?>&order_by=importe_<?php echo $year2; ?>&order_dir=<?php echo ($order_dir==='asc'?'desc':'asc'); ?>">
              <?php echo $year2; ?>
            </a>
          </th>
        </tr>
      </thead>
      <tbody>
      <?php
      $colspan = 8 + (is_null($codigo_vendedor) ? 1 : 0) + ($mostrarUltimaVisita ? 1 : 0);
      if (empty($clientesPaginados)) {
          echo '<tr><td colspan="' . $colspan . '">No se encontraron registros</td></tr>';
      } else {
          // Ranking
          $rankingAct = rankingPorAnio($clientes, 'importe_'.$currentYear);
          $rankingY1  = rankingPorAnio($clientes, 'importe_'.$year1);
          $rankingY2  = rankingPorAnio($clientes, 'importe_'.$year2);
          $hoyInicioTs = strtotime(date('Y-m-d')) ?: time();

          foreach ($clientesPaginados as $row) {
              $codCliCP1252 = $row['cod_cliente'] ?? '';
              $nomComCP1252 = $row['nombre_comercial'] ?? '';
              $provCP1252   = $row['provincia'] ?? '';
              $pobCP1252    = $row['poblacion'] ?? '';
              $vendedorCP1252 = (isset($row['vendedor'])) ? $row['vendedor'] : '';

              $ultimaFecha = !empty($row['ultima_fecha_venta'])
                             ? date("d/m/Y", strtotime($row['ultima_fecha_venta']))
                             : "Sin ventas";
              $ultimaVisita = !empty($row['ultima_fecha_visita'])
                              ? date("d/m/Y", strtotime($row['ultima_fecha_visita']))
                              : "Sin visitas";
              $visitasPorSeccion = $ultimasVisitasPorClienteSeccion[(string)$codCliCP1252] ?? [];
              $seccionesCliente = $seccionesPorCliente[(string)$codCliCP1252] ?? ['NULL'];
              $nombresSeccionCliente = $nombresSeccionPorCliente[(string)$codCliCP1252] ?? [];
              $seccionesClienteVisita = [];
              foreach ($seccionesCliente as $codSecListado) {
                  $claveSeccion = normalizarClaveSeccionVisita($codSecListado);
                  $nombreSecLabel = trim((string)($nombresSeccionCliente[$claveSeccion] ?? ''));
                  if ($nombreSecLabel === '') {
                      $nombreSecLabel = ($claveSeccion === 'NULL') ? 'Sin sección' : ('Sección ' . (string)$claveSeccion);
                  }
                  $seccionesClienteVisita[$claveSeccion] = $nombreSecLabel;
              }
              $visitasPlanificacionCliente = $visitasPlanificacionPorClienteSeccion[(string)$codCliCP1252] ?? [];
              $seccionesValidasPlanificacion = $seccionesValidasPlanificacionPorCliente[(string)$codCliCP1252] ?? [];

              // Convertir a float (en CP1252 no afecta, pero por seguridad)
              $importeAct = (float) $row['importe_'.$currentYear];
              $importeY1  = (float) $row['importe_'.$year1];
              $importeY2  = (float) $row['importe_'.$year2];

              // Ranking
              $posAct = $rankingAct[$codCliCP1252] ?? 0;
              $posY1  = $rankingY1[$codCliCP1252]  ?? 0;
              $posY2  = $rankingY2[$codCliCP1252]  ?? 0;

              $vendedorFila = null;
              if (!is_null($codigo_vendedor) && is_numeric((string)$codigo_vendedor)) {
                  $vendedorFila = (int)$codigo_vendedor;
              } elseif (isset($row['vendedor']) && is_numeric((string)$row['vendedor'])) {
                  $vendedorFila = (int)$row['vendedor'];
              }

              $rowClasses = 'clickable-row';
              if ($esPremiumPlanificador) {
                  $claseEstadoVisualVisita = calcularClaseEstadoVisualVisita(
                      (string)$codCliCP1252,
                      $visitasPlanificacionPorClienteSeccion,
                      $seccionesValidasPlanificacion,
                      ($vendedorFila !== null) ? ($zonasPorVendedor[$vendedorFila] ?? []) : [],
                      $zonaPrincipalPorClienteSeccion[(string)$codCliCP1252] ?? [],
                      $frecuenciaPorClienteSeccion[(string)$codCliCP1252] ?? [],
                      $hoyInicioTs
                  );
                  if ($claseEstadoVisualVisita !== '') {
                      $rowClasses .= ' ' . $claseEstadoVisualVisita;
                  }
              }
              $detalleHref = 'cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252);
              echo '<tr class="' . $rowClasses . '" data-href="' . htmlspecialchars($detalleHref) . '">';
              if (is_null($codigo_vendedor)) {
                  $vendedorMostrar = trim((string)$vendedorCP1252);
                  if ($vendedorMostrar === '0') {
                      $vendedorMostrar = '';
                  }
                  echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                       . htmlspecialchars(toUTF8($vendedorMostrar))
                       . '</a></td>';
              }

              // ID
              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                   . htmlspecialchars(toUTF8($codCliCP1252))
                   . '</a></td>';

              // Nombre Comercial
              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                   . htmlspecialchars(toUTF8($nomComCP1252))
                   . '</a></td>';

              // Provincia
              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                   . htmlspecialchars(toUTF8($provCP1252))
                   . '</a></td>';

              // Población
              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                   . htmlspecialchars(toUTF8($pobCP1252))
                   . '</a></td>';

              // Última visita (una por sección, incluyendo sección 0)
              if ($mostrarUltimaVisita) {
                  echo '<td>';
                  if (count($seccionesClienteVisita) > 1) {
                      $clavesSeccion = array_keys($seccionesClienteVisita);
                      usort($clavesSeccion, static function (string|int $a, string|int $b): int {
                          if ($a === 'NULL') {
                              return -1;
                          }
                          if ($b === 'NULL') {
                              return 1;
                          }
                          return (int)$a <=> (int)$b;
                      });

                      // Si ninguna sección tiene visita, no desglosar por sección.
                      $hayAlgunaVisita = false;
                      foreach ($clavesSeccion as $codSecListadoTmp) {
                          if (isset($visitasPorSeccion[$codSecListadoTmp])) {
                              $hayAlgunaVisita = true;
                              break;
                          }
                      }
                      if (!$hayAlgunaVisita) {
                          echo '<a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '" style="display:block;margin:2px 0;color:inherit;text-decoration:none;text-align:center;">Sin visitas</a>';
                      } else {

                          foreach ($clavesSeccion as $codSecListado) {
                              $vsec = $visitasPorSeccion[$codSecListado] ?? null;
                              if ($vsec) {
                                  $fechaSec = !empty($vsec['fecha_visita']) ? date("d/m/Y", strtotime($vsec['fecha_visita'])) : "Sin visitas";
                                  $estadoSec = normalizarEstadoVisitaClave((string)($vsec['estado_visita'] ?? ''));
                                  $origenSec = strtolower(trim((string)($vsec['origen_visita'] ?? '')));
                                  $colorSec = determinarColorVisita($estadoSec, $origenSec);
                                  $estiloSec = 'display:block;margin:2px 0;padding:2px 6px;border-radius:4px;background-color:' . htmlspecialchars($colorSec) . ';color:#fff;text-decoration:none;';
                              } else {
                                  $fechaSec = "Sin visitas";
                                  $estiloSec = 'display:block;margin:2px 0;padding:2px 6px;border-radius:4px;color:inherit;text-decoration:none;';
                              }
                              $nombreSecLabel = $seccionesClienteVisita[$codSecListado] ?? '';
                              if ($nombreSecLabel === '') {
                                  $nombreSecLabel = ($codSecListado === 0) ? 'Sin sección' : ('Sección ' . (string)$codSecListado);
                              }
                              if ($codSecListado === 'NULL') {
                                  $nombreSecLabel = 'Sin sección';
                              }
                              $labelSec = $nombreSecLabel . ': ' . $fechaSec;
                              echo '<a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '" style="' . $estiloSec . '">'
                                   . htmlspecialchars(toUTF8($labelSec))
                                   . '</a>';
                          }
                      }
                  } else {
                      $claveUnica = array_key_first($seccionesClienteVisita);
                      $vsec = ($claveUnica !== null) ? ($visitasPorSeccion[$claveUnica] ?? null) : null;
                      if ($vsec) {
                          $fechaSec = !empty($vsec['fecha_visita']) ? date("d/m/Y", strtotime($vsec['fecha_visita'])) : "Sin visitas";
                          $estadoSec = normalizarEstadoVisitaClave((string)($vsec['estado_visita'] ?? ''));
                          $origenSec = strtolower(trim((string)($vsec['origen_visita'] ?? '')));
                          $colorSec = determinarColorVisita($estadoSec, $origenSec);
                          $styleSimple = ($colorSec !== '')
                              ? 'display:block;margin:2px 0;padding:2px 6px;border-radius:4px;background-color:' . htmlspecialchars($colorSec) . ';color:#fff;text-decoration:none;text-align:center;'
                              : 'display:block;margin:2px 0;color:inherit;text-decoration:none;text-align:center;';
                          echo '<a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '" style="' . $styleSimple . '">'
                               . htmlspecialchars($fechaSec)
                               . '</a>';
                      } else {
                          echo '<a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '" style="display:block;margin:2px 0;color:inherit;text-decoration:none;text-align:center;">'
                               . htmlspecialchars($ultimaVisita)
                               . '</a>';
                      }
                  }
                  echo '</td>';
              }

              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                   . htmlspecialchars($ultimaFecha)
                   . '</a></td>';

              // Columna año actual (triángulo vs. expected)
              echo '<td class="year-column">';
              $start = strtotime($currentYear.'-01-01');
              $end   = strtotime($currentYear.'-12-31');
              $yearLength = (($end - $start)/86400) + 1;
              $now        = time();
              $daysPassed = ($now - $start)/86400;
              $fraction   = ($yearLength > 0) ? ($daysPassed/$yearLength) : 0;
              $expected   = $importeY1 * $fraction;

              $triangle = '';
              if ($expected > 0) {
                  if ($importeAct >= $expected*1.05) {
                      $triangle = '<span class="triangle" style="color:green;">&#9650;</span>';
                  } elseif ($importeAct <= $expected*0.95) {
                      $triangle = '<span class="triangle" style="color:red;">&#9660;</span>';
                  }
              }
              echo $triangle;
              echo number_format($importeAct, 2, ',', '.') . '  ';
              echo iconoMedalla($posAct); 
              echo '</td>';

              // Año pasado vs hace 2
              echo '<td class="year-column">';
              $triangleY1 = '';
              if ($importeY2 > 0) {
                  $ratio = $importeY1 / $importeY2;
                  if ($ratio >= 1.05) {
                      $triangleY1 = '<span class="triangle" style="color:green;">&#9650;</span>';
                  } elseif ($ratio <= 0.95) {
                      $triangleY1 = '<span class="triangle" style="color:red;">&#9660;</span>';
                  }
              }
              echo $triangleY1;
              echo number_format($importeY1, 2, ',', '.') . '  ';
              echo iconoMedalla($posY1);
              echo '</td>';

              // Hace 2 años
              echo '<td class="year-column">';
              echo number_format($importeY2, 2, ',', '.') . '  ';
              echo iconoMedalla($posY2);
              echo '</td>';

              echo '</tr>';
          }
      }
      ?>
      </tbody>
    </table>
  </div>

  <!-- PAGINACION -->
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?php echo $query_string; ?>&page=<?php echo $page - 1; ?>">Anterior</a>
    <?php else: ?>
      <span>Anterior</span>
    <?php endif; ?>

    <?php
    for ($p = 1; $p <= $totalPaginas; $p++) {
        if ($p === $page) {
            echo '<span class="current">'.$p.'</span>';
        } else {
            echo '<a href="?'.$query_string.'&page='.$p.'">'.$p.'</a>';
        }
    }
    ?>

    <?php if ($page < $totalPaginas): ?>
      <a href="?<?php echo $query_string; ?>&page=<?php echo $page + 1; ?>">Siguiente</a>
    <?php else: ?>
      <span>Siguiente</span>
    <?php endif; ?>
  </div>

  <p>Total de registros: <?php echo $numRegistros; ?></p>
</div> <!-- /.page-content -->

<!-- Bootstrap 5 JS Bundle (local via Composer) -->
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('tr.clickable-row[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, select, textarea, label')) return;
      window.location.href = row.getAttribute('data-href');
    });
  });
});
</script>

<?php
?>
</body>
</html>


