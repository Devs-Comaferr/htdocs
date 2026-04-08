<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasHelper.php';
require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasClientesService.php';
require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasVentasService.php';
require_once BASE_PATH . '/app/Modules/Estadisticas/services/EstadisticasService.php';

if (!function_exists('parsearFechaIsoEstricto')) {
    function parsearFechaIsoEstricto(string $valor): ?DateTimeImmutable
    {
        return __estadisticas_impl_parsearFechaIsoEstricto($valor);
    }
}


if (!function_exists('sumarDiasFechaIso')) {
    function sumarDiasFechaIso(string $fechaIso, int $dias): string
    {
        return __estadisticas_impl_sumarDiasFechaIso($fechaIso, $dias);
    }
}


if (!function_exists('construirRangoFechasSql')) {
    function construirRangoFechasSql(string $campoFecha): string
    {
        return __estadisticas_impl_construirRangoFechasSql($campoFecha);
    }
}


if (!function_exists('normalizarFechaIso')) {
    function normalizarFechaIso(string $valor, string $fallback): string
    {
        return __estadisticas_impl_normalizarFechaIso($valor, $fallback);
    }
}


if (!function_exists('normalizarFechaIsoFlexible')) {
    function normalizarFechaIsoFlexible(string $valor, string $fallback): string
    {
        return __estadisticas_impl_normalizarFechaIsoFlexible($valor, $fallback);
    }
}


if (!function_exists('obtenerRangoFechasContextoSql')) {
    function obtenerRangoFechasContextoSql(array $contexto): array
    {
        return __estadisticas_impl_obtenerRangoFechasContextoSql($contexto);
    }
}


if (!function_exists('calcularRiesgoLineaServicio')) {
    function calcularRiesgoLineaServicio(array $linea): array
    {
        return __estadisticas_impl_calcularRiesgoLineaServicio($linea);
    }
}


if (!function_exists('estadisticasDisplayErrorsActivo')) {
    function estadisticasDisplayErrorsActivo(): bool
    {
        return __estadisticas_impl_estadisticasDisplayErrorsActivo();
    }
}


if (!function_exists('estadisticasDebugActivo')) {
    function estadisticasDebugActivo(): bool
    {
        return __estadisticas_impl_estadisticasDebugActivo();
    }
}


if (!function_exists('estadisticasDebugLog')) {
    function estadisticasDebugLog(string $mensaje, array $contexto = []): void
    {
        __estadisticas_impl_estadisticasDebugLog($mensaje, $contexto);
    }
}


if (!function_exists('registrarErrorSqlEstadisticas')) {
    function registrarErrorSqlEstadisticas(string $contexto, $conn, string $sql, array $params = []): void
    {
        __estadisticas_impl_registrarErrorSqlEstadisticas($contexto, $conn, $sql, $params);
    }
}


if (!function_exists('obtenerErroresSqlEstadisticas')) {
    function obtenerErroresSqlEstadisticas(): array
    {
        return __estadisticas_impl_obtenerErroresSqlEstadisticas();
    }
}


if (!function_exists('estadisticasOdbcExec')) {
    function estadisticasOdbcExec($conn, string $sql, array $params = [])
    {
        return __estadisticas_impl_estadisticasOdbcExec($conn, $sql, $params);
    }
}


if (!function_exists('estadisticasInterpolarSql')) {
    function estadisticasInterpolarSql(string $sql, array $params): ?string
    {
        return __estadisticas_impl_estadisticasInterpolarSql($sql, $params);
    }
}


if (!function_exists('estadisticasSqlLiteral')) {
    function estadisticasSqlLiteral($valor): string
    {
        return __estadisticas_impl_estadisticasSqlLiteral($valor);
    }
}


if (!function_exists('buildWhereCabecera')) {
    function buildWhereCabecera(string $alias, array $filtros): array
    {
        return __estadisticas_impl_buildWhereCabecera($alias, $filtros);
    }
}


if (!function_exists('construirCondicionComercialParams')) {
    function construirCondicionComercialParams(string $alias, array $contexto): array
    {
        return __estadisticas_impl_construirCondicionComercialParams($alias, $contexto);
    }
}


if (!function_exists('construirBaseLineasDocumentalesSql')) {
    function construirBaseLineasDocumentalesSql(array $filtros = []): array
    {
        return __estadisticas_impl_construirBaseLineasDocumentalesSql($filtros);
    }
}


if (!function_exists('buildWhereLineasDocumentales')) {
    function buildWhereLineasDocumentales(
        array $contexto,
        string $aliasArticulo = 'a',
        string $aliasLinea = 'hvl',
        string $aliasCabecera = 'hvc'
    ): array {
        return __estadisticas_impl_buildWhereLineasDocumentales($contexto, $aliasArticulo, $aliasLinea, $aliasCabecera);
    }
}


if (!function_exists('construirFiltroArticulosSql')) {
    function construirFiltroArticulosSql(array $contexto, array &$params): string
    {
        return __estadisticas_impl_construirFiltroArticulosSql($contexto, $params);
    }
}


if (!function_exists('obtenerDefinicionCamposFiltrosVentas')) {
    function obtenerDefinicionCamposFiltrosVentas(): array
    {
        return __estadisticas_impl_obtenerDefinicionCamposFiltrosVentas();
    }
}


if (!function_exists('construirSqlBaseFiltrosVentas')) {
    function construirSqlBaseFiltrosVentas(array $contexto, array $opciones = []): array
    {
        return __estadisticas_impl_construirSqlBaseFiltrosVentas($contexto, $opciones);
    }
}


if (!function_exists('construirWhereFiltrosVentas')) {
    function construirWhereFiltrosVentas(array $contexto, ?string $filtroObjetivo = null): array
    {
        return __estadisticas_impl_construirWhereFiltrosVentas($contexto, $filtroObjetivo);
    }
}


if (!function_exists('obtenerKpiPedidosPendientes')) {
    function obtenerKpiPedidosPendientes(array $dataset): array
    {
        return __estadisticas_impl_obtenerKpiPedidosPendientes($dataset);
    }
}


if (!function_exists('obtenerKpiBacklogImporte')) {
    function obtenerKpiBacklogImporte(array $dataset): array
    {
        return __estadisticas_impl_obtenerKpiBacklogImporte($dataset);
    }
}


if (!function_exists('obtenerKpiClientesConBacklog')) {
    function obtenerKpiClientesConBacklog(array $dataset): array
    {
        return __estadisticas_impl_obtenerKpiClientesConBacklog($dataset);
    }
}


if (!function_exists('obtenerKpiLineasCriticas')) {
    function obtenerKpiLineasCriticas(array $dataset): array
    {
        return __estadisticas_impl_obtenerKpiLineasCriticas($dataset);
    }
}


if (!function_exists('obtenerKpiVelocidadServicio')) {
    function obtenerKpiVelocidadServicio(array $dataset): array
    {
        return __estadisticas_impl_obtenerKpiVelocidadServicio($dataset);
    }
}


if (!function_exists('obtenerKpiLineasPendientes')) {
    function obtenerKpiLineasPendientes(array $dataset): array
    {
        return __estadisticas_impl_obtenerKpiLineasPendientes($dataset);
    }
}


if (!function_exists('obtenerCodVendedorUsuario')) {
    function obtenerCodVendedorUsuario($conn, string $email): ?string
    {
        return __estadisticas_impl_obtenerCodVendedorUsuario($conn, $email);
    }
}


if (!function_exists('existeComisionistaEnSistema')) {
    function existeComisionistaEnSistema($conn, string $codigo): bool
    {
        return __estadisticas_impl_existeComisionistaEnSistema($conn, $codigo);
    }
}


if (!function_exists('resolverContextoFiltros')) {
    function resolverContextoFiltros($conn, array $session, array $query): array
    {
        return __estadisticas_impl_resolverContextoFiltros($conn, $session, $query);
    }
}


if (!function_exists('obtenerOpcionesFiltroVentas')) {
    function obtenerOpcionesFiltroVentas($conn, array $contexto, string $filtro): array
    {
        return __estadisticas_impl_obtenerOpcionesFiltroVentas($conn, $contexto, $filtro);
    }
}


if (!function_exists('obtenerDatasetServicioPedidos')) {
    function obtenerDatasetServicioPedidos($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerDatasetServicioPedidos($conn, $contexto);
    }
}


if (!function_exists('obtenerDatasetLineasPendientes')) {
    function obtenerDatasetLineasPendientes($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerDatasetLineasPendientes($conn, $contexto);
    }
}


if (!function_exists('obtenerDatasetServicioLineas')) {
    function obtenerDatasetServicioLineas($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerDatasetServicioLineas($conn, $contexto);
    }
}


if (!function_exists('obtenerResumenDocumentosSeparados')) {
    function obtenerResumenDocumentosSeparados($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerResumenDocumentosSeparados($conn, $contexto);
    }
}


if (!function_exists('construirSqlDocsBase')) {
    function construirSqlDocsBase(array $contexto, array $opts = []): array
    {
        return __estadisticas_impl_construirSqlDocsBase($contexto, $opts);
    }
}


if (!function_exists('construirSqlDocsFiltrados')) {
    function construirSqlDocsFiltrados(array $contexto, array $opts = []): array
    {
        return __estadisticas_impl_construirSqlDocsFiltrados($contexto, $opts);
    }
}


if (!function_exists('obtenerResumenAlbaranesVentasConYSinPedido')) {
    function obtenerResumenAlbaranesVentasConYSinPedido($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerResumenAlbaranesVentasConYSinPedido($conn, $contexto);
    }
}


if (!function_exists('obtenerResumenAlbaranesAbonoConYSinPedido')) {
    function obtenerResumenAlbaranesAbonoConYSinPedido($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerResumenAlbaranesAbonoConYSinPedido($conn, $contexto);
    }
}


if (!function_exists('obtenerCheckCabeceraVsLineasAB')) {
    function obtenerCheckCabeceraVsLineasAB($conn, array $contexto, array $opts = []): array
    {
        return __estadisticas_impl_obtenerCheckCabeceraVsLineasAB($conn, $contexto, $opts);
    }
}


if (!function_exists('obtenerForenseDocumentoPedidoDebug')) {
    function obtenerForenseDocumentoPedidoDebug($conn, array $contexto, string $codVenta): array
    {
        return __estadisticas_impl_obtenerForenseDocumentoPedidoDebug($conn, $contexto, $codVenta);
    }
}


if (!function_exists('obtenerDescuadreCabeceraVsLineas')) {
    function obtenerDescuadreCabeceraVsLineas($conn, $codComisionista, $fechaDesde, $fechaHasta, $opts = []): array
    {
        return __estadisticas_impl_obtenerDescuadreCabeceraVsLineas($conn, $codComisionista, $fechaDesde, $fechaHasta, $opts);
    }
}


if (!function_exists('construirSqlBaseServicioPedidosCTE')) {
    function construirSqlBaseServicioPedidosCTE(): string
    {
        return __estadisticas_impl_construirSqlBaseServicioPedidosCTE();
    }
}


if (!function_exists('obtenerKpiServicioPedidos')) {
    function obtenerKpiServicioPedidos($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerKpiServicioPedidos($conn, $contexto);
    }
}


if (!function_exists('obtenerKpiServicioPedidosAjustado')) {
    function obtenerKpiServicioPedidosAjustado($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerKpiServicioPedidosAjustado($conn, $contexto);
    }
}


if (!function_exists('obtenerKpiServicioPedidosUnified')) {
    function obtenerKpiServicioPedidosUnified($conn, array $contexto, array $opciones = []): array
    {
        return __estadisticas_impl_obtenerKpiServicioPedidosUnified($conn, $contexto, $opciones);
    }
}


if (!function_exists('agruparDetalleServicioPedidosDesdeMapa')) {
    function agruparDetalleServicioPedidosDesdeMapa(array $detallePedidosMap): array
    {
        return __estadisticas_impl_agruparDetalleServicioPedidosDesdeMapa($detallePedidosMap);
    }
}


if (!function_exists('obtenerDetalleServicioPedidos')) {
    function obtenerDetalleServicioPedidos($conn, array $contexto, ?int $limit = null, ?int $offset = null): array
    {
        return __estadisticas_impl_obtenerDetalleServicioPedidos($conn, $contexto, $limit, $offset);
    }
}


if (!function_exists('obtenerOpcionesComercialesVentas')) {
    function obtenerOpcionesComercialesVentas($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerOpcionesComercialesVentas($conn, $contexto);
    }
}


if (!function_exists('obtenerOpcionesMarcaVentas')) {
    function obtenerOpcionesMarcaVentas($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerOpcionesMarcaVentas($conn, $contexto);
    }
}


if (!function_exists('obtenerDetalleDiferenciaDocumental')) {
    function obtenerDetalleDiferenciaDocumental($conn, array $contexto): array
    {
        return __estadisticas_impl_obtenerDetalleDiferenciaDocumental($conn, $contexto);
    }
}


if (!function_exists('obtenerDetalleSegunVista')) {
    function obtenerDetalleSegunVista($conn, array $contexto, string $vista): array
    {
        return __estadisticas_impl_obtenerDetalleSegunVista($conn, $contexto, $vista);
    }
}
