<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_estadisticas');
require_once BASE_PATH . '/app/Support/statistics.php';
require_once BASE_PATH . '/includes/funciones_estadisticas.php';

header('Content-Type: application/json; charset=UTF-8');

$queryEntrada = $_GET;

$fDesde = normalizarFechaIso($queryEntrada['f_desde'] ?? '', date('Y-01-01'));
$fHasta = normalizarFechaIso($queryEntrada['f_hasta'] ?? '', date('Y-m-d'));

if ($fDesde > $fHasta) {
    [$fDesde, $fHasta] = [$fHasta, $fDesde];
}

$queryNormalizada = $queryEntrada;
$queryNormalizada['f_desde'] = $fDesde;
$queryNormalizada['f_hasta'] = $fHasta;
$queryNormalizada['cod_comisionista'] = trim((string)($queryEntrada['cod_comisionista'] ?? ''));

$contexto = resolverContextoFiltros($conn, $_SESSION, $queryNormalizada);

$datasetServicio = obtenerDatasetServicioLineas($conn, $contexto);

$totalPedido = 0.0;
$totalServido = 0.0;
$sumaDiasPrimeraEntrega = 0.0;
$importePrimeraEntrega = 0.0;
$sumaDiasUltimaEntrega = 0.0;
$importeUltimaEntrega = 0.0;

foreach ($datasetServicio as $row) {
    $cantidadPedida = (float)($row['cantidad_pedida'] ?? 0);
    $cantidadServida = (float)($row['cantidad_servida'] ?? 0);
    $importeLinea = (float)($row['importe_linea'] ?? 0);
    if ($importeLinea <= 0 || $cantidadPedida <= 0) {
        continue;
    }

    $servicioLinea = $cantidadServida / $cantidadPedida;
    if ($servicioLinea < 0) {
        $servicioLinea = 0.0;
    } elseif ($servicioLinea > 1) {
        $servicioLinea = 1.0;
    }

    $importeServido = $importeLinea * $servicioLinea;
    $totalPedido += $importeLinea;
    $totalServido += $importeServido;

    $importePrimeraEntregaLinea = (float)($row['importe_primera_entrega'] ?? 0);
    $diasPrimeraEntrega = $row['dias_primera_entrega'] ?? null;
    if (
        $importePrimeraEntregaLinea > 0
        && $diasPrimeraEntrega !== null
        && is_numeric($diasPrimeraEntrega)
        && (float)$diasPrimeraEntrega >= 0
    ) {
        $diasPrimeraEntrega = (float)$diasPrimeraEntrega;
        $sumaDiasPrimeraEntrega += $diasPrimeraEntrega * $importePrimeraEntregaLinea;
        $importePrimeraEntrega += $importePrimeraEntregaLinea;
    }

    $importeUltimaEntregaLinea = (float)($row['importe_entregas_posteriores'] ?? 0);
    $diasUltimaEntrega = $row['dias_ultima_entrega'] ?? null;
    if (
        $importeUltimaEntregaLinea > 0
        && $diasUltimaEntrega !== null
        && is_numeric($diasUltimaEntrega)
        && (float)$diasUltimaEntrega >= 0
    ) {
        $diasUltimaEntrega = (float)$diasUltimaEntrega;
        $sumaDiasUltimaEntrega += $diasUltimaEntrega * $importeUltimaEntregaLinea;
        $importeUltimaEntrega += $importeUltimaEntregaLinea;
    }
}

$servicioPct = $totalPedido > 0 ? min(1.0, ($totalServido / $totalPedido)) : 0.0;
$primeraEntregaDias = $importePrimeraEntrega > 0 ? ($sumaDiasPrimeraEntrega / $importePrimeraEntrega) : null;
$ultimaEntregaDias = $importeUltimaEntrega > 0 ? ($sumaDiasUltimaEntrega / $importeUltimaEntrega) : null;

$response = [
    'servicio_pct' => $servicioPct,
    'importe_pedido' => $totalPedido,
    'importe_servido' => $totalServido,
    'primera_entrega_dias' => $primeraEntregaDias,
    'primera_entrega_importe' => $importePrimeraEntrega,
    'ultima_entrega_dias' => $ultimaEntregaDias,
    'ultima_entrega_importe' => $importeUltimaEntrega,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

error_log('[ESTADISTICAS] servicio_kpis_calculados ' . json_encode([
    'total_lineas' => count($datasetServicio)
]));

if (isset($conn)) {
    odbc_close($conn);
}
