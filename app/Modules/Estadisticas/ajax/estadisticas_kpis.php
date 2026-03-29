<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_estadisticas');
require_once BASE_PATH . '/app/Support/statistics.php';
require_once BASE_PATH . '/app/Support/statistics.php';

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

$resumenDocumentos = obtenerResumenDocumentosSeparados($conn, $contexto);

$resumenAlbaranesConSinPedido =
    obtenerResumenAlbaranesVentasConYSinPedido($conn, $contexto);

$resumenAlbaranesAbonoConSinPedido =
    obtenerResumenAlbaranesAbonoConYSinPedido($conn, $contexto);

$datasetServicio = obtenerDatasetServicioLineas($conn, $contexto);

$kpiPedidosPendientes = obtenerKpiPedidosPendientes($datasetServicio);

$kpiVelocidadServicio = obtenerKpiVelocidadServicio($datasetServicio);

$kpiLineasPendientes = obtenerKpiLineasPendientes($datasetServicio);

$kpiBacklogImporte = obtenerKpiBacklogImporte($datasetServicio);

$kpiClientesBacklog = obtenerKpiClientesConBacklog($datasetServicio);

$kpiLineasCriticas = obtenerKpiLineasCriticas($datasetServicio);

$kpiServicio = obtenerKpiServicioPedidosUnified(
    $conn,
    $contexto,
    ['modo' => 'operativo']
);

$response = [

    'documentos' => $resumenDocumentos,

    'albaranes_ventas' => $resumenAlbaranesConSinPedido,

    'albaranes_abonos' => $resumenAlbaranesAbonoConSinPedido,

    'servicio' => $kpiServicio,

    'pedidos_pendientes' => $kpiPedidosPendientes,

    'velocidad_servicio' => $kpiVelocidadServicio,

    'lineas_pendientes' => $kpiLineasPendientes,

    'backlog_importe' => $kpiBacklogImporte,

    'clientes_backlog' => $kpiClientesBacklog,

    'lineas_criticas' => $kpiLineasCriticas
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($conn)) {
    odbc_close($conn);
}
