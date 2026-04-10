<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';

// Verificar sesiÃ³n


// Encabezado UTF-8
header('Content-Type: text/html; charset=utf-8');
$pageTitle = "Clientes";

$ui_version = 'bs5';
$ui_requires_jquery = false;

// Incluir conexiÃ³n y funciones
require_once BASE_PATH . '/app/Support/functions.php'; // AquÃ­ debe existir toUTF8($data)

require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Support/HorariosVisitasSupport.php';

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

// AquÃ­ definimos una funciÃ³n para convertir el tÃ©rmino buscado a CP1252:
function toCP1252(string $data): string {
    // Convierte desde UTF-8 (lo que envÃ­a el navegador) a Windows-1252
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

function obtenerIndicadorFrecuenciaCliente(array $frecuenciasPorSeccion): ?array {
    if (empty($frecuenciasPorSeccion)) {
        return null;
    }

    $frecuenciasNormalizadas = [];
    foreach ($frecuenciasPorSeccion as $frecuencia) {
        $frecuenciasNormalizadas[] = normalizarFrecuenciaPlanificacion((string)$frecuencia);
    }
    $frecuenciasNormalizadas = array_values(array_unique($frecuenciasNormalizadas));

    if (count($frecuenciasNormalizadas) === 1) {
        $frecuencia = $frecuenciasNormalizadas[0];
        return match ($frecuencia) {
            'TODOS' => [
                'class' => 'freq-todos',
                'short' => 'T',
                'label' => 'Visita todos los ciclos',
            ],
            'CADA2' => [
                'class' => 'freq-cada2',
                'short' => '2',
                'label' => 'Visita cada 2 ciclos',
            ],
            'CADA3' => [
                'class' => 'freq-cada3',
                'short' => '3',
                'label' => 'Visita cada 3 ciclos',
            ],
            'NUNCA' => [
                'class' => 'freq-nunca',
                'short' => 'N',
                'label' => 'Sin visitas planificadas',
            ],
            default => null,
        };
    }

    sort($frecuenciasNormalizadas);
    return [
        'class' => 'freq-mixta',
        'short' => 'M',
        'label' => 'Frecuencia mixta: ' . implode(', ', $frecuenciasNormalizadas),
    ];
}

function obtenerColorTextoContraste(string $backgroundColor): string
{
    $hex = ltrim(trim($backgroundColor), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return '#ffffff';
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    return $luminance >= 160 ? '#111827' : '#ffffff';
}

function renderFechaUltimaVisita(string $fecha, ?string $estadoVisita = null, ?string $origenVisita = null): string
{
    if ($fecha === '' || strtolower($fecha) === 'sin visitas') {
        return htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8');
    }

    $backgroundColor = function_exists('determinarColorVisita')
        ? determinarColorVisita((string)$estadoVisita, (string)$origenVisita)
        : '#6c757d';
    $textColor = obtenerColorTextoContraste($backgroundColor);

    return '<span class="ultima-visita-fecha-badge" style="background-color: '
        . htmlspecialchars($backgroundColor, ENT_QUOTES, 'UTF-8')
        . ';color: '
        . htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8')
        . ';">'
        . htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

function obtenerIconoOrigenPedido(?string $origenPedido, bool $esPedidoWeb = false): string
{
    if ($esPedidoWeb) {
        return '<i class="fa fa-globe" aria-hidden="true" title="Pedido web"></i>';
    }

    $origen = strtolower(trim((string)$origenPedido));
    return match ($origen) {
        'telefono', 'teléfono' => '<i class="fa fa-phone" aria-hidden="true" title="Telefono"></i>',
        'visita' => '<i class="fa fa-briefcase" aria-hidden="true" title="Visita"></i>',
        'whatsapp' => '<i class="fa-brands fa-whatsapp" aria-hidden="true" title="WhatsApp"></i>',
        'email' => '<i class="fa fa-envelope" aria-hidden="true" title="Email"></i>',
        'pedido web' => '<i class="fa fa-globe" aria-hidden="true" title="Pedido web"></i>',
        default => '<i class="fa fa-info-circle" aria-hidden="true" title="Origen no identificado"></i>',
    };
}

// Leer variables GET (en UTF-8), luego convertiremos a CP1252 solo para usarlas en la query
$cod_cliente_utf8      = filter_input(INPUT_GET, 'cod_cliente', FILTER_UNSAFE_RAW) ?? '';
$nombre_comercial_utf8 = filter_input(INPUT_GET, 'nombre_comercial', FILTER_UNSAFE_RAW) ?? '';
$provincia_utf8        = filter_input(INPUT_GET, 'provincia', FILTER_UNSAFE_RAW) ?? '';
$poblacion_utf8        = filter_input(INPUT_GET, 'poblacion', FILTER_UNSAFE_RAW) ?? '';
$filtro_vendedor_utf8  = '';
$solo_zona_actual_utf8 = filter_input(INPUT_GET, 'solo_zona_actual', FILTER_UNSAFE_RAW) ?? '';

if (is_null($codigo_vendedor)) {
    $filtro_vendedor_utf8 = filter_input(INPUT_GET, 'vendedor', FILTER_UNSAFE_RAW) ?? '';
}
define('FILTRO_SIN_VENDEDOR', '__sin_vendedor__');

// Convertir a CP1252 las cadenas que podrÃ­as comparar con LIKE
$cod_cliente      = toCP1252($cod_cliente_utf8);
$nombre_comercial = toCP1252($nombre_comercial_utf8);
$provincia        = toCP1252($provincia_utf8);
$poblacion        = toCP1252($poblacion_utf8);
$filtro_vendedor  = toCP1252($filtro_vendedor_utf8);
$tienePermisoPlanificador = isset($_SESSION['perm_planificador']) && (int)$_SESSION['perm_planificador'] === 1;
$soloZonaActual = $tienePermisoPlanificador && in_array(strtolower(trim((string)$solo_zona_actual_utf8)), ['1', 'on', 'true', 'si'], true);
$currentYear = date('Y');
$year1 = $currentYear - 1;
$year2 = $currentYear - 2;
$mostrarUltimaVisita = (
    isset($_SESSION['tipo_plan']) &&
    $_SESSION['tipo_plan'] === 'premium' &&
    $tienePermisoPlanificador
);
$zonaActualFiltro = 0;
if ($soloZonaActual && !is_null($codigo_vendedor) && is_numeric((string)$codigo_vendedor) && function_exists('obtenerZonaActivaPorFecha')) {
    $contextoZonaFiltro = obtenerZonaActivaPorFecha($conn, (int)$codigo_vendedor, date('Y-m-d'));
    $zonaActualFiltro = (int)($contextoZonaFiltro['zona_actual'] ?? 0);
    if ($zonaActualFiltro <= 0) {
        $soloZonaActual = false;
    }
}

// Orden
$order_by_utf8 = filter_input(INPUT_GET, 'order_by', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'ultima_fecha_venta';
$order_dir_utf8 = filter_input(INPUT_GET, 'order_dir', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'desc';
$order_dir = strtolower($order_dir_utf8);
if ($order_dir !== 'asc' && $order_dir !== 'desc') {
    $order_dir = 'asc';
}
$orderByWhitelist = [
    'cli.cod_cliente' => 'cli.cod_cliente',
    'cli.nombre_comercial' => 'cli.nombre_comercial',
    'cli.provincia' => 'cli.provincia',
    'cli.poblacion' => 'poblacion_orden',
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

$sqlPoblacionEfectiva = "
    COALESCE(
        NULLIF(
            CASE
                WHEN secinfo.total_secciones = 1 THEN secinfo.poblacion_seccion_unica
                ELSE ''
            END,
            ''
        ),
        cli.poblacion
    )
";

$sqlJoinPoblacionEfectiva = "
    OUTER APPLY (
        SELECT
            COUNT(*) AS total_secciones,
            MAX(LTRIM(RTRIM(ISNULL(sc.poblacion, '')))) AS poblacion_seccion_unica
        FROM secciones_cliente sc
        WHERE sc.cod_cliente = cli.cod_cliente
    ) secinfo
";

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
    $whereProvincias[] = $sqlPoblacionEfectiva . ' = ?';
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
    {$sqlJoinPoblacionEfectiva}
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
$wherePoblaciones[] = $sqlPoblacionEfectiva . ' IS NOT NULL';
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
    SELECT DISTINCT {$sqlPoblacionEfectiva} AS poblacion
    FROM clientes cli
    {$sqlJoinPoblacionEfectiva}
    WHERE " . implode("\n      AND ", $wherePoblaciones) . "
    ORDER BY poblacion
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
        $whereVendedores[] = $sqlPoblacionEfectiva . ' = ?';
        $paramsVendedores[] = $poblacion;
    }
    $sql_vendedores = "
        SELECT DISTINCT ven.cod_vendedor, ven.nombre AS nombre_vendedor
        FROM clientes cli
        {$sqlJoinPoblacionEfectiva}
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

// CondiciÃ³n + subfiltro
$escapeSqlValue = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$whereClientes = ['1=1'];
$joinFiltroComisionista = '';
$whereFiltroComisionistaUltimoPedido = '';
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

// Filtro por comisionista (si es AgustÃ­n Castro con cÃ³digo ''30'')
if (!is_null($codigo_vendedor) && $codigo_vendedor === '30') {
    $joinFiltroComisionista = ' AND vent.cod_comisionista = ' . (int)$codigo_vendedor;
    $whereFiltroComisionistaUltimoPedido = ' AND h.cod_comisionista = ' . (int)$codigo_vendedor;
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
    $whereClientes[] = $sqlPoblacionEfectiva . ' = ' . $escapeSqlValue($poblacion);
}
if ($soloZonaActual && $zonaActualFiltro > 0) {
    $whereClientes[] = "EXISTS (
        SELECT 1
        FROM cmf_comerciales_clientes_zona cz
        WHERE cz.cod_cliente = cli.cod_cliente
          AND (cz.zona_principal = {$zonaActualFiltro} OR cz.zona_secundaria = {$zonaActualFiltro})
    )";
}

// Armar SQL principal
$sql = "
SELECT " . (is_null($codigo_vendedor) ? "cli.cod_vendedor AS vendedor," : "") . "
       cli.cod_cliente,
       cli.nombre_comercial,
       cli.fecha_baja,
       cli.provincia,
       {$sqlPoblacionEfectiva} AS poblacion_mostrar,
       {$sqlPoblacionEfectiva} AS poblacion_orden,
       MAX(op.ultima_fecha_venta) AS ultima_fecha_venta,
       MAX(op.origen_ultimo_pedido) AS origen_ultimo_pedido,
       MAX(op.es_pedido_web) AS es_pedido_web,
       " . ($mostrarUltimaVisita ? "MAX(uv.ultima_fecha_visita) AS ultima_fecha_visita,
       MAX(uv.estado_ultima_visita) AS estado_ultima_visita,
       MAX(uv.origen_ultima_visita) AS origen_ultima_visita," : "") . "
       " . subConsultaImporteAnual('importe_'.$currentYear, $currentYear) . ",
       " . subConsultaImporteAnual('importe_'.$year1, $year1) . ",
       " . subConsultaImporteAnual('importe_'.$year2, $year2) . "
FROM clientes cli
{$sqlJoinPoblacionEfectiva}
LEFT JOIN hist_ventas_cabecera vent
       ON vent.cod_cliente = cli.cod_cliente
      AND vent.tipo_venta = 1
      {$joinFiltroComisionista}
OUTER APPLY (
       SELECT TOP 1
              h.fecha_venta AS ultima_fecha_venta,
              CASE
                  WHEN h.cod_pedido_web IS NOT NULL AND LTRIM(RTRIM(h.cod_pedido_web)) <> '' THEN 'pedido web'
                  ELSE COALESCE((
                      SELECT TOP 1 LOWER(vp.origen)
                      FROM cmf_comerciales_visitas_pedidos vp
                      WHERE vp.cod_venta = h.cod_venta
                      ORDER BY vp.id_visita_pedido DESC
                  ), '')
              END AS origen_ultimo_pedido,
              CASE
                  WHEN h.cod_pedido_web IS NOT NULL AND LTRIM(RTRIM(h.cod_pedido_web)) <> '' THEN 1
                  ELSE 0
              END AS es_pedido_web
       FROM hist_ventas_cabecera h
       WHERE h.cod_cliente = cli.cod_cliente
         AND h.tipo_venta = 1
         {$whereFiltroComisionistaUltimoPedido}
       ORDER BY h.fecha_venta DESC, h.cod_venta DESC
) op
" . ($mostrarUltimaVisita ? "OUTER APPLY (
       SELECT TOP 1
              v.fecha_visita AS ultima_fecha_visita,
              LOWER(v.estado_visita) AS estado_ultima_visita,
              CASE
                  WHEN EXISTS (
                      SELECT 1
                      FROM cmf_comerciales_visitas_pedidos vp
                      WHERE vp.id_visita = v.id_visita
                        AND LOWER(vp.origen) = 'visita'
                  ) THEN 'visita'
                  ELSE COALESCE((
                      SELECT TOP 1 LOWER(vp.origen)
                      FROM cmf_comerciales_visitas_pedidos vp
                      WHERE vp.id_visita = v.id_visita
                      ORDER BY vp.id_visita_pedido DESC
                  ), '')
              END AS origen_ultima_visita
       FROM cmf_comerciales_visitas v
       WHERE v.cod_cliente = cli.cod_cliente
         AND (
               LOWER(v.estado_visita) = 'no atendida'
               OR (
                   LOWER(v.estado_visita) = 'realizada'
                   AND (
                       EXISTS (
                           SELECT 1
                           FROM cmf_comerciales_visitas_pedidos vp
                           WHERE vp.id_visita = v.id_visita
                             AND LOWER(vp.origen) = 'visita'
                       )
                       OR NOT EXISTS (
                           SELECT 1
                           FROM cmf_comerciales_visitas_pedidos vp
                           WHERE vp.id_visita = v.id_visita
                       )
                   )
               )
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
    cli.fecha_baja,
    cli.provincia,
    {$sqlPoblacionEfectiva}
ORDER BY {$order_by} {$order_dir}
";

$resCli = odbc_exec($conn, $sql);
if (!$resCli) {
    exit('ODBC ERROR: ' . odbc_errormsg($conn));
}

// Recoger filas (aÃºn en CP1252)
$clientes = [];
while ($resCli && ($fila = odbc_fetch_array($resCli))) {
    $clientes[] = $fila;  // Lo convertiremos luego al mostrar
}
$numRegistros = count($clientes);

/* =============================================================================
   4) PaginaciÃ³n
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
$seccionesPorCliente = [];
$nombresSeccionPorCliente = [];
$poblacionesSeccionPorCliente = [];
$frecuenciaPorClienteSeccion = [];

if ($mostrarUltimaVisita && !empty($clientesPaginados)) {
    $codigosCliente = [];
    foreach ($clientesPaginados as $filaCli) {
        if (isset($filaCli['cod_cliente']) && $filaCli['cod_cliente'] !== '') {
            $codigosCliente[] = (int)$filaCli['cod_cliente'];
        }
    }
    $codigosCliente = array_values(array_unique($codigosCliente));

    if (!empty($codigosCliente)) {
        $listaCodigosClienteSql = implode(', ', array_map(
            static fn (int $codigo): string => (string)$codigo,
            $codigosCliente
        ));

        // Secciones existentes por cliente (la secciÃ³n 0 tambiÃ©n es vÃ¡lida).
        $sqlSeccionesCliente = "
            SELECT
                sc.cod_cliente,
                sc.cod_seccion AS cod_seccion,
                COALESCE(sc.nombre, '') AS nombre_seccion,
                COALESCE(sc.poblacion, '') AS poblacion_seccion
            FROM secciones_cliente sc
            WHERE sc.cod_cliente IN ($listaCodigosClienteSql)
        ";
        $resSeccionesCliente = odbc_exec($conn, $sqlSeccionesCliente);
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
                if (!isset($poblacionesSeccionPorCliente[$codCliSec])) {
                    $poblacionesSeccionPorCliente[$codCliSec] = [];
                }
                $codSec = normalizarClaveSeccionVisita($filaSec['cod_seccion'] ?? null);
                if (!in_array($codSec, $seccionesPorCliente[$codCliSec], true)) {
                    $seccionesPorCliente[$codCliSec][] = $codSec;
                }
                $nombreSec = trim((string)($filaSec['nombre_seccion'] ?? ''));
                if ($nombreSec !== '') {
                    $nombresSeccionPorCliente[$codCliSec][$codSec] = $nombreSec;
                }
                $poblacionSec = trim((string)($filaSec['poblacion_seccion'] ?? ''));
                if ($poblacionSec !== '') {
                    $poblacionesSeccionPorCliente[$codCliSec][$codSec] = $poblacionSec;
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
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM cmf_comerciales_visitas_pedidos vp
                            WHERE vp.id_visita = v.id_visita
                              AND LOWER(vp.origen) = 'visita'
                        ) THEN 'visita'
                        ELSE COALESCE((
                            SELECT TOP 1 LOWER(vp.origen)
                            FROM cmf_comerciales_visitas_pedidos vp
                            WHERE vp.id_visita = v.id_visita
                            ORDER BY vp.id_visita_pedido DESC
                        ), '')
                    END AS origen_visita,
                    ROW_NUMBER() OVER (
                        PARTITION BY v.cod_cliente, v.cod_seccion
                        ORDER BY v.fecha_visita DESC, v.id_visita DESC
                    ) AS rn
                FROM cmf_comerciales_visitas v
                WHERE v.cod_cliente IN ($listaCodigosClienteSql)
                  AND (
                       LOWER(v.estado_visita) = 'no atendida'
                       OR (
                           LOWER(v.estado_visita) = 'realizada'
                           AND (
                               EXISTS (
                                   SELECT 1
                                   FROM cmf_comerciales_visitas_pedidos vp2
                                   WHERE vp2.id_visita = v.id_visita
                                     AND LOWER(vp2.origen) = 'visita'
                               )
                               OR NOT EXISTS (
                                   SELECT 1
                                   FROM cmf_comerciales_visitas_pedidos vp2
                                   WHERE vp2.id_visita = v.id_visita
                               )
                           )
                       )
                  )
            )
            SELECT cod_cliente, cod_seccion, fecha_visita, estado_visita, origen_visita
            FROM visitas_validas
            WHERE rn = 1
            ORDER BY cod_cliente, cod_seccion
        ";

        $resVisitasSeccion = odbc_exec($conn, $sqlVisitasSeccion);
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

        // Frecuencia de visita por cliente/secciÃ³n desde asignaciÃ³n de zonas.
        $sqlFrecuencias = "
            SELECT
                azc.cod_cliente,
                azc.cod_seccion,
                UPPER(COALESCE(azc.frecuencia_visita, 'Todos')) AS frecuencia_visita
            FROM cmf_comerciales_clientes_zona azc
            WHERE azc.cod_cliente IN ($listaCodigosClienteSql)
        ";
        $resFrecuencias = odbc_exec($conn, $sqlFrecuencias);
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
            }
        }

        // Si un cliente no tiene secciones en tabla, tratarlo como secciÃ³n 0.
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
    'solo_zona_actual' => $soloZonaActual ? '1' : '',
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
  <!-- Para mÃ³viles y tablets -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <!-- Bootstrap 5 CSS (local via Composer) -->
  
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
    /* En mÃ³vil, si quieres header NO fijo, quita estas lÃ­neas
       o cambia la media query. 
       Actual: Header es NO fijo, se deja normal. 
       Si lo quieres fijo en mÃ³vil, hazlo y deja margin-top. */

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
    .filter-switch {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      min-height: 42px;
      padding: 8px 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: #fff;
      color: #334155;
      cursor: pointer;
      user-select: none;
    }
    .filter-switch input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    .filter-switch-track {
      position: relative;
      width: 42px;
      height: 24px;
      border-radius: 999px;
      background: #cbd5e1;
      transition: background-color 0.2s ease;
      flex: 0 0 auto;
    }
    .filter-switch-track::after {
      content: '';
      position: absolute;
      top: 3px;
      left: 3px;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
      transition: transform 0.2s ease;
    }
    .filter-switch input:checked + .filter-switch-track {
      background: #28a745;
    }
    .filter-switch input:checked + .filter-switch-track::after {
      transform: translateX(18px);
    }
    .filter-switch-text {
      font-size: 14px;
      font-weight: 600;
      white-space: nowrap;
    }
    .cliente-nombre-wrap {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .cliente-baja-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      position: relative;
      width: 18px;
      height: 18px;
      color: #7c8796;
      flex: 0 0 auto;
    }
    .cliente-baja-badge .fa-user {
      font-size: 15px;
      line-height: 1;
    }
    .cliente-baja-badge .cliente-baja-cross {
      position: absolute;
      right: -2px;
      bottom: -2px;
      color: #dc2626;
      font-size: 11px;
      font-weight: 800;
      line-height: 1;
      background: #fff;
      border-radius: 999px;
    }
    .frecuencia-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 26px;
      height: 26px;
      border-radius: 999px;
      padding: 0 8px;
      font-size: 12px;
      font-weight: 800;
      line-height: 1;
      flex: 0 0 auto;
      border: 1px solid transparent;
    }
    .frecuencia-badge.freq-todos {
      background: rgba(22, 163, 74, 0.15);
      color: #15803d;
      border-color: rgba(22, 163, 74, 0.25);
    }
    .frecuencia-badge.freq-cada2 {
      background: rgba(37, 99, 235, 0.15);
      color: #1d4ed8;
      border-color: rgba(37, 99, 235, 0.25);
    }
    .frecuencia-badge.freq-cada3 {
      background: rgba(245, 158, 11, 0.18);
      color: #b45309;
      border-color: rgba(245, 158, 11, 0.28);
    }
    .frecuencia-badge.freq-nunca {
      background: rgba(239, 68, 68, 0.16);
      color: #b91c1c;
      border-color: rgba(239, 68, 68, 0.24);
    }
    .frecuencia-badge.freq-mixta {
      background: rgba(100, 116, 139, 0.16);
      color: #475569;
      border-color: rgba(100, 116, 139, 0.24);
    }

    /* ============= Tabla y paginaciÃ³n ============= */
    .ultima-visita-fecha-badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
      line-height: 1.2;
      vertical-align: middle;
    }
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
      .filter-switch {
        width: 100%;
        justify-content: space-between;
      }
      table {
        font-size: 15px;
      }
      /* Si quieres header fijo tambiÃ©n en mÃ³vil, pon position:fixed y margin-top */
      /* header, .header { ... } .page-content { margin-top: ... } */
    }
    :root {
      --clientes-bg: #eef4f7;
      --clientes-panel: rgba(255, 255, 255, 0.92);
      --clientes-panel-strong: #ffffff;
      --clientes-border: rgba(15, 23, 42, 0.1);
      --clientes-text: #102132;
      --clientes-muted: #5f7082;
      --clientes-accent: #0f766e;
      --clientes-shadow: 0 22px 48px rgba(15, 23, 42, 0.08);
      --clientes-shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.05);
    }
    html, body {
      font-family: "Trebuchet MS", "Segoe UI Variable Text", "Segoe UI", sans-serif;
      color: var(--clientes-text);
      background:
        radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 26%),
        radial-gradient(circle at top right, rgba(217, 119, 6, 0.12), transparent 24%),
        linear-gradient(180deg, #f7fbfc 0%, var(--clientes-bg) 100%);
    }
    header, .header {
      background-color: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
    }
    .page-content {
      max-width: 1600px;
      padding: 20px 18px 28px;
    }
    .clientes-shell {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    .filter-card,
    .results-card {
      border: 1px solid var(--clientes-border);
      border-radius: 22px;
      background: var(--clientes-panel);
      box-shadow: var(--clientes-shadow-soft);
      backdrop-filter: blur(10px);
    }
    .filter-card {
      padding: 18px;
    }
    .filter-card-header,
    .results-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 16px;
    }
    .section-title {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .section-title-icon {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(15, 118, 110, 0.18), rgba(14, 116, 144, 0.16));
      color: var(--clientes-accent);
      font-size: 18px;
    }
    .section-title h2,
    .section-title h3 {
      margin: 0;
      font-size: 19px;
      letter-spacing: -0.02em;
    }
    .section-title p {
      margin: 4px 0 0;
      color: var(--clientes-muted);
      font-size: 13px;
    }
    .results-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: flex-end;
    }
    .results-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,0.82);
      border: 1px solid var(--clientes-border);
      color: var(--clientes-text);
      font-size: 13px;
      font-weight: 700;
    }
    .results-chip strong {
      font-size: 15px;
      font-weight: 800;
    }
    .filter-form {
      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 12px;
      background: transparent;
      padding: 0;
      border: 0;
      box-shadow: none;
      margin-bottom: 0;
    }
    .filter-form input,
    .filter-form select {
      width: 100%;
      min-width: 0;
      min-height: 48px;
      padding: 12px 14px;
      border: 1px solid rgba(148, 163, 184, 0.38);
      border-radius: 14px;
      background: rgba(255,255,255,0.95);
      color: var(--clientes-text);
      font-size: 15px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }
    .filter-form input:focus,
    .filter-form select:focus {
      border-color: rgba(15, 118, 110, 0.55);
      box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
      transform: translateY(-1px);
      outline: none;
    }
    .filter-form input[name="cod_cliente"] { grid-column: span 2; }
    .filter-form input[name="nombre_comercial"] { grid-column: span 3; }
    .filter-form select[name="provincia"] { grid-column: span 2; }
    .filter-form select[name="poblacion"] { grid-column: span 2; }
    .filter-form select[name="vendedor"] { grid-column: span 2; }
    .filter-form .filter-switch { grid-column: span 2; }
    .filter-form .btn-search,
    .filter-form .btn-clear { grid-column: span 1; }
    .filter-form button {
      min-height: 48px;
      border-radius: 14px;
      font-size: 15px;
      font-weight: 700;
      justify-content: center;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .filter-form .btn-search {
      background: linear-gradient(135deg, var(--clientes-accent) 0%, #0e7490 100%);
      box-shadow: 0 16px 28px rgba(15, 118, 110, 0.24);
    }
    .filter-form .btn-clear {
      background: #fff8f5;
      color: #c2410c;
      border: 1px solid rgba(194, 65, 12, 0.18);
    }
    .filter-switch {
      min-height: 48px;
      border: 1px solid rgba(148, 163, 184, 0.34);
      border-radius: 14px;
      background: rgba(255,255,255,0.95);
      justify-content: space-between;
    }
    .filter-switch input:checked + .filter-switch-track {
      background: var(--clientes-accent);
    }
    .results-card {
      padding: 18px;
    }
    .table-container {
      border: 1px solid rgba(148, 163, 184, 0.18);
      border-radius: 20px;
      background: var(--clientes-panel-strong);
      box-shadow: none;
    }
    table {
      border-collapse: separate;
      border-spacing: 0;
      font-size: 15px;
    }
    th, td {
      padding: 14px 16px;
      vertical-align: top;
      border: 0;
      border-bottom: 1px solid rgba(148, 163, 184, 0.14);
    }
    tbody tr:last-child td {
      border-bottom: 0;
    }
    th {
      position: sticky;
      top: 0;
      z-index: 2;
      background: #f7fbfc;
      color: #385168;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }
    tr.clickable-row {
      transition: background-color 0.18s ease;
    }
    tr.clickable-row:hover td {
      background: rgba(15, 118, 110, 0.045);
    }
    .cliente-nombre-wrap {
      font-weight: 700;
    }
    .ultima-visita-fecha-badge {
      padding: 4px 9px;
      box-shadow: inset 0 -1px 0 rgba(255,255,255,0.18);
    }
    .empty-state {
      padding: 40px 18px;
      text-align: center;
      color: var(--clientes-muted);
      font-size: 15px;
    }
    .pagination {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 8px;
      margin-top: 18px;
    }
    .pagination a, .pagination span {
      min-width: 42px;
      padding: 10px 14px;
      border-radius: 12px;
      background: rgba(255,255,255,0.86);
      color: var(--clientes-text);
      border-color: rgba(148, 163, 184, 0.24);
      font-weight: 700;
    }
    .pagination a:hover {
      background: rgba(15, 118, 110, 0.08);
      border-color: rgba(15, 118, 110, 0.24);
    }
    .pagination .current {
      background: linear-gradient(135deg, var(--clientes-accent) 0%, #0e7490 100%);
      color: #fff;
      border-color: transparent;
      box-shadow: 0 14px 24px rgba(15, 118, 110, 0.22);
    }
    .results-footer {
      margin-top: 14px;
      text-align: right;
      color: var(--clientes-muted);
      font-size: 13px;
      font-weight: 600;
    }
    @media (max-width: 1180px) {
      .filter-form input[name="cod_cliente"],
      .filter-form input[name="nombre_comercial"],
      .filter-form select[name="provincia"],
      .filter-form select[name="poblacion"],
      .filter-form select[name="vendedor"],
      .filter-form .filter-switch,
      .filter-form .btn-search,
      .filter-form .btn-clear {
        grid-column: span 3;
      }
      .filter-card-header,
      .results-header {
        flex-direction: column;
        align-items: stretch;
      }
      .results-summary {
        justify-content: flex-start;
      }
    }
    @media (max-width: 860px) {
      .page-content {
        padding: 14px 12px 24px;
      }
      .filter-card,
      .results-card {
        border-radius: 20px;
      }
      .filter-form {
        grid-template-columns: 1fr;
      }
      .filter-form input[name="cod_cliente"],
      .filter-form input[name="nombre_comercial"],
      .filter-form select[name="provincia"],
      .filter-form select[name="poblacion"],
      .filter-form select[name="vendedor"],
      .filter-form .filter-switch,
      .filter-form .btn-search,
      .filter-form .btn-clear {
        grid-column: auto;
      }
      .filter-form input,
      .filter-form select,
      .filter-form button {
        font-size: 17px;
      }
      table {
        font-size: 14px;
      }
      th, td {
        padding: 12px 12px;
      }
      .results-footer {
        text-align: center;
      }
    }
  </style>
</head>
<body>
<?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

<div class="page-content">
  <div class="clientes-shell">
  <section class="filter-card">
    <div class="filter-card-header">
      <div class="section-title">
        <span class="section-title-icon"><i class="fa-solid fa-sliders"></i></span>
        <div>
          <h2>Filtros</h2>
          <p>Ajusta la b&uacute;squeda para encontrar clientes m&aacute;s r&aacute;pido.</p>
        </div>
      </div>
    </div>
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

    <?php if ($tienePermisoPlanificador && !is_null($codigo_vendedor)) { ?>
      <label class="filter-switch" title="Filtrar solo clientes de la zona actual">
        <input type="checkbox" name="solo_zona_actual" value="1" <?php if ($soloZonaActual) echo 'checked'; ?> onchange="this.form.submit()">
        <span class="filter-switch-track" aria-hidden="true"></span>
        <span class="filter-switch-text">Solo zona actual</span>
      </label>
    <?php } ?>
    
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
  </section>
  
  <section class="results-card">
    <div class="results-header">
      <div class="section-title">
        <span class="section-title-icon"><i class="fa-solid fa-table-list"></i></span>
        <div>
          <h3>Resultados</h3>
          <p>Listado ordenable con actividad comercial, visitas y ventas.</p>
        </div>
      </div>
      <div class="results-summary">
        <span class="results-chip"><i class="fa-solid fa-database"></i> <strong><?php echo number_format($numRegistros, 0, ',', '.'); ?></strong> registros</span>
        <span class="results-chip"><i class="fa-solid fa-layer-group"></i> <strong><?php echo number_format(count($clientesPaginados), 0, ',', '.'); ?></strong> visibles</span>
      </div>
    </div>
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
          echo '<tr><td colspan="' . $colspan . '" class="empty-state">No se encontraron registros con los filtros actuales.</td></tr>';
      } else {
          // Ranking
          $rankingAct = rankingPorAnio($clientes, 'importe_'.$currentYear);
          $rankingY1  = rankingPorAnio($clientes, 'importe_'.$year1);
          $rankingY2  = rankingPorAnio($clientes, 'importe_'.$year2);
          foreach ($clientesPaginados as $row) {
              $codCliCP1252 = $row['cod_cliente'] ?? '';
              $nomComCP1252 = $row['nombre_comercial'] ?? '';
              $provCP1252   = $row['provincia'] ?? '';
              $pobCP1252    = $row['poblacion_mostrar'] ?? $row['poblacion'] ?? '';
              $vendedorCP1252 = (isset($row['vendedor'])) ? $row['vendedor'] : '';

              $ultimaFecha = !empty($row['ultima_fecha_venta'])
                             ? date("d/m/Y", strtotime($row['ultima_fecha_venta']))
                             : "Sin ventas";
              $origenUltimoPedido = isset($row['origen_ultimo_pedido']) ? trim((string)$row['origen_ultimo_pedido']) : '';
              $esPedidoWeb = isset($row['es_pedido_web']) && (int)$row['es_pedido_web'] === 1;
              $fechaBaja = isset($row['fecha_baja']) ? trim((string)$row['fecha_baja']) : '';
              $clienteDadoDeBaja = $fechaBaja !== '';
              $ultimaVisita = !empty($row['ultima_fecha_visita'])
                              ? date("d/m/Y", strtotime($row['ultima_fecha_visita']))
                              : "Sin visitas";
              $visitasPorSeccion = $ultimasVisitasPorClienteSeccion[(string)$codCliCP1252] ?? [];
              $seccionesCliente = $seccionesPorCliente[(string)$codCliCP1252] ?? ['NULL'];
              $nombresSeccionCliente = $nombresSeccionPorCliente[(string)$codCliCP1252] ?? [];
              $poblacionesSeccionCliente = $poblacionesSeccionPorCliente[(string)$codCliCP1252] ?? [];
              $seccionesClienteVisita = [];
              foreach ($seccionesCliente as $codSecListado) {
                  $claveSeccion = normalizarClaveSeccionVisita($codSecListado);
                  $nombreSecLabel = trim((string)($nombresSeccionCliente[$claveSeccion] ?? ''));
                  if ($nombreSecLabel === '') {
                      $nombreSecLabel = ($claveSeccion === 'NULL') ? 'Sin secciÃ³n' : ('SecciÃ³n ' . (string)$claveSeccion);
                  }
                  $seccionesClienteVisita[$claveSeccion] = $nombreSecLabel;
              }
              $indicadorFrecuencia = $tienePermisoPlanificador
                  ? obtenerIndicadorFrecuenciaCliente($frecuenciaPorClienteSeccion[(string)$codCliCP1252] ?? [])
                  : null;

              // Convertir a float (en CP1252 no afecta, pero por seguridad)
              $importeAct = (float) $row['importe_'.$currentYear];
              $importeY1  = (float) $row['importe_'.$year1];
              $importeY2  = (float) $row['importe_'.$year2];

              // Ranking
              $posAct = $rankingAct[$codCliCP1252] ?? 0;
              $posY1  = $rankingY1[$codCliCP1252]  ?? 0;
              $posY2  = $rankingY2[$codCliCP1252]  ?? 0;

              $detalleHref = 'cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252);
              echo '<tr class="clickable-row" data-href="' . htmlspecialchars($detalleHref) . '">';
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
              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '"><span class="cliente-nombre-wrap">'
                   . htmlspecialchars(toUTF8($nomComCP1252));
              if ($clienteDadoDeBaja) {
                  echo '<span class="cliente-baja-badge" title="Cliente dado de baja" aria-label="Cliente dado de baja">'
                       . '<i class="fas fa-user" aria-hidden="true"></i>'
                       . '<span class="cliente-baja-cross" aria-hidden="true">&times;</span>'
                       . '</span>';
              }
              echo ''
                   . '</span></a></td>';

              // Provincia
              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                   . htmlspecialchars(toUTF8($provCP1252))
                   . '</a></td>';

              // PoblaciÃ³n
              $poblacionMostrar = $pobCP1252;
              if (count($seccionesCliente) === 1) {
                  $clavePoblacionSeccion = array_key_first($seccionesClienteVisita);
                  if ($clavePoblacionSeccion !== null && isset($poblacionesSeccionCliente[$clavePoblacionSeccion]) && trim((string)$poblacionesSeccionCliente[$clavePoblacionSeccion]) !== '') {
                      $poblacionMostrar = (string)$poblacionesSeccionCliente[$clavePoblacionSeccion];
                  }
              }
              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">'
                   . htmlspecialchars(toUTF8($poblacionMostrar))
                   . '</a></td>';

              // Ãšltima visita (una por secciÃ³n, incluyendo secciÃ³n 0)
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

                      // Si ninguna secciÃ³n tiene visita, no desglosar por secciÃ³n.
                      foreach ($clavesSeccion as $codSecListado) {
                              $claveVisitaSeccion = normalizarClaveSeccionVisita($codSecListado);
                              $vsec = $visitasPorSeccion[$claveVisitaSeccion] ?? null;
                              if ($vsec === null && $claveVisitaSeccion === 0) {
                                  $vsec = $visitasPorSeccion['NULL'] ?? null;
                              }
                              if ($vsec === null && $claveVisitaSeccion === 'NULL') {
                                  $vsec = $visitasPorSeccion[0] ?? null;
                              }
                              $fechaSec = ($vsec && !empty($vsec['fecha_visita']))
                                  ? date("d/m/Y", strtotime($vsec['fecha_visita']))
                                  : "Sin visitas";
                              $estiloSec = 'display:block;margin:2px 0;padding:2px 6px;border-radius:4px;color:inherit;text-decoration:none;';
                              $frecuenciaSeccion = $frecuenciaPorClienteSeccion[(string)$codCliCP1252][$codSecListado]
                                  ?? $frecuenciaPorClienteSeccion[(string)$codCliCP1252][normalizarClaveSeccionVisita($codSecListado)]
                                  ?? '';
                              $indicadorFrecuenciaSeccion = $tienePermisoPlanificador && $frecuenciaSeccion !== ''
                                  ? obtenerIndicadorFrecuenciaCliente([$frecuenciaSeccion])
                                  : null;
                              $nombreSecLabel = $seccionesClienteVisita[$codSecListado] ?? '';
                              if ($nombreSecLabel === '') {
                                  $nombreSecLabel = ($codSecListado === 0) ? 'Sin secciÃ³n' : ('SecciÃ³n ' . (string)$codSecListado);
                              }
                              if ($codSecListado === 'NULL') {
                                  $nombreSecLabel = 'Sin secciÃ³n';
                              }
                              $fechaSecHtml = renderFechaUltimaVisita(
                                  $fechaSec,
                                  (string)($vsec['estado_visita'] ?? ''),
                                  (string)($vsec['origen_visita'] ?? '')
                              );
                          echo '<a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '" style="' . $estiloSec . '">';
                          if ($indicadorFrecuenciaSeccion !== null) {
                              echo '<span class="frecuencia-badge ' . htmlspecialchars((string)$indicadorFrecuenciaSeccion['class'], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars((string)$indicadorFrecuenciaSeccion['label'], ENT_QUOTES, 'UTF-8') . '" style="margin-right:6px;">'
                                   . htmlspecialchars((string)$indicadorFrecuenciaSeccion['short'], ENT_QUOTES, 'UTF-8')
                                   . '</span>';
                          }
                          echo $fechaSecHtml
                               . '<span style="margin-left:8px;">&rarr; '
                               . htmlspecialchars(toUTF8($nombreSecLabel), ENT_QUOTES, 'UTF-8')
                               . '</span>'
                               . '</a>';
                          }
                  } else {
                      $claveUnica = array_key_first($seccionesClienteVisita);
                      $vsec = null;
                      if ($claveUnica !== null) {
                          $claveVisitaUnica = normalizarClaveSeccionVisita($claveUnica);
                          $vsec = $visitasPorSeccion[$claveVisitaUnica] ?? null;
                          if ($vsec === null && $claveVisitaUnica === 0) {
                              $vsec = $visitasPorSeccion['NULL'] ?? null;
                          }
                          if ($vsec === null && $claveVisitaUnica === 'NULL') {
                              $vsec = $visitasPorSeccion[0] ?? null;
                          }
                      }
                      if ($vsec) {
                          $fechaSec = !empty($vsec['fecha_visita']) ? date("d/m/Y", strtotime($vsec['fecha_visita'])) : "Sin visitas";
                          $styleSimple = 'display:block;margin:2px 0;padding:2px 6px;border-radius:4px;color:inherit;text-decoration:none;';
                          $fechaSecHtml = renderFechaUltimaVisita(
                              $fechaSec,
                              (string)($vsec['estado_visita'] ?? ''),
                              (string)($vsec['origen_visita'] ?? '')
                          );
                          echo '<a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '" style="' . $styleSimple . '">';
                          if ($indicadorFrecuencia !== null) {
                              echo '<span class="frecuencia-badge ' . htmlspecialchars((string)$indicadorFrecuencia['class'], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars((string)$indicadorFrecuencia['label'], ENT_QUOTES, 'UTF-8') . '" style="margin-right:6px;">'
                                   . htmlspecialchars((string)$indicadorFrecuencia['short'], ENT_QUOTES, 'UTF-8')
                                   . '</span>';
                          }
                          echo $fechaSecHtml
                               . '</a>';
                      } else {
                          echo '<a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '" style="display:block;margin:2px 0;padding:2px 6px;border-radius:4px;color:inherit;text-decoration:none;">';
                          if ($indicadorFrecuencia !== null) {
                              echo '<span class="frecuencia-badge ' . htmlspecialchars((string)$indicadorFrecuencia['class'], ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars((string)$indicadorFrecuencia['label'], ENT_QUOTES, 'UTF-8') . '" style="margin-right:6px;">'
                                   . htmlspecialchars((string)$indicadorFrecuencia['short'], ENT_QUOTES, 'UTF-8')
                                   . '</span>';
                          }
                          echo ''
                               . htmlspecialchars($ultimaVisita)
                               . '</a>';
                      }
                  }
                  echo '</td>';
              }

              echo '<td><a href="cliente_detalles.php?cod_cliente=' . urlencode($codCliCP1252) . '">';
              if ($ultimaFecha !== 'Sin ventas') {
                  $iconoOrigenUltimoPedido = function_exists('iconoDeOrigenOpcional')
                      ? iconoDeOrigenOpcional($origenUltimoPedido, $esPedidoWeb)
                      : '';
                  echo '<span style="display:inline-flex;align-items:center;gap:8px;">'
                       . '<span>' . htmlspecialchars($ultimaFecha, ENT_QUOTES, 'UTF-8') . '</span>';
                  if ($iconoOrigenUltimoPedido !== '') {
                      echo $iconoOrigenUltimoPedido;
                  }
                  echo ''
                       . '</span>';
              } else {
                  echo htmlspecialchars($ultimaFecha, ENT_QUOTES, 'UTF-8');
              }
              echo '</a></td>';

              // Columna aÃ±o actual (triÃ¡ngulo vs. expected)
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

              // AÃ±o pasado vs hace 2
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

              // Hace 2 aÃ±os
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

    <div class="results-footer">Total de registros: <?php echo number_format($numRegistros, 0, ',', '.'); ?></div>
  </section>
</div>
</div> <!-- /.page-content -->

<!-- Bootstrap 5 JS Bundle (local via Composer) -->
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



