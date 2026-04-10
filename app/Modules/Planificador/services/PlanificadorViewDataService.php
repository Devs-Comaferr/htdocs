<?php

require_once __DIR__ . '/PlanificadorViewsDataService.php';

if (!function_exists('planificadorViewObtenerDatosZonas')) {
    function planificadorViewObtenerDatosZonas(): array
    {
        return planificadorViewsDataObtenerDatosZonas();
    }
}

if (!function_exists('planificadorViewObtenerDatosZonasClientes')) {
    function planificadorViewObtenerDatosZonasClientes($cod_zona = null): array
    {
        return planificadorViewsDataObtenerDatosZonasClientes($cod_zona);
    }
}

if (!function_exists('planificadorViewObtenerDatosZonasRutas')) {
    function planificadorViewObtenerDatosZonasRutas($cod_zona = null, $cod_ruta_seleccionada = 0): array
    {
        return planificadorViewsDataObtenerDatosZonasRutas($cod_zona, $cod_ruta_seleccionada);
    }
}

if (!function_exists('planificadorViewObtenerDatosCompletarDia')) {
    function planificadorViewObtenerDatosCompletarDia($codigo_vendedor, $fecha): array
    {
        return planificadorViewsDataObtenerDatosCompletarDia($codigo_vendedor, $fecha);
    }
}
