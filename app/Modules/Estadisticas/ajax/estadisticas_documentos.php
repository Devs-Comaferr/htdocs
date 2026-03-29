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

$response = [
    'documentos' => $resumenDocumentos,
    'albaranes_ventas' => $resumenAlbaranesConSinPedido,
    'albaranes_abonos' => $resumenAlbaranesAbonoConSinPedido
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($conn)) {
}
