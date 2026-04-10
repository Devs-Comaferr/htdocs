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
require_once __DIR__ . '/PlanificadorViewsDataService.php';
require_once __DIR__ . '/PlanificadorRecomendacionService.php';

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

function seccionDisponibleParaAsignacion($cod_cliente, $cod_seccion): ?bool {
    return planificadorRepoSeccionDisponibleParaAsignacion($cod_cliente, $cod_seccion);
}

function borrarAsignacion($cod_cliente, $cod_zona, $cod_seccion): bool {
    return planificadorRepoBorrarAsignacion($cod_cliente, $cod_zona, $cod_seccion);
}



if (!function_exists('crearZonaVisitaService')) {

// ==========================
// MOTOR: seleccion de cliente y reglas de ciclo
// ==========================

function obtenerZonaActivaHoy($cod_vendedor = null) {
    return planificadorRecomendacionObtenerZonaActivaHoy($cod_vendedor);
}

/**
 * Obtener un cliente recomendado de la zona activa priorizando clientes sin visita hoy
 * y, despues, por antiguedad de su ultima visita.
 *
 * @return array|null ['cod_cliente' => int, 'nombre' => string, 'motivo' => string, 'origen_recomendacion' => string]
 */

function construirClienteRecomendadoDesdeFila(array $fila, string $origenRecomendacion) {
    return planificadorRecomendacionConstruirClienteDesdeFila($fila, $origenRecomendacion);
}

function calcularTocaVisitaPlanificador($frecuenciaVisita, int $iteracionZona): int {
    return planificadorRecomendacionCalcularTocaVisita($frecuenciaVisita, $iteracionZona);
}

// === MOTOR: pipeline de decision ===
function obtenerUniversoCandidatosPlanificador($conn, string $query, ?int $iteracionZona = null) {
    return planificadorRecomendacionObtenerUniversoCandidatos($conn, $query, $iteracionZona);
}

function planificadorClienteEsLaborable(array $cliente, int $codVendedor, ?string $fecha = null): bool {
    return planificadorRecomendacionClienteEsLaborable($cliente, $codVendedor, $fecha);
}

function filtrarClientesElegiblesPlanificador($clientes, ?int $iteracionZona = null, ?int $codVendedor = null, ?string $fecha = null) {
    return planificadorRecomendacionFiltrarElegibles($clientes, $iteracionZona, $codVendedor, $fecha);
}

function calcularScoreClientesPlanificador($clientes) {
    return planificadorRecomendacionCalcularScoreClientes($clientes);
}

function seleccionarMejorClientePlanificador($clientes, string $origenRecomendacion) {
    return planificadorRecomendacionSeleccionarMejorCliente($clientes, $origenRecomendacion);
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
    return planificadorRecomendacionObtenerClientePorQuery($conn, $query, $origenRecomendacion, $iteracionZona);
}

// === MOTOR: orquestacion final del recomendador ===
function obtenerSiguienteClienteRecomendado($zonaActivaId = 0, $codVendedor = null) {
    return planificadorRecomendacionObtenerSiguienteCliente($zonaActivaId, $codVendedor);
}

/**
 * Obtener informaciÃ³n de una zona por su cÃ³digo
 */

// ==========================
// VIEW: preparacion de vistas del modulo
if (!function_exists('obtenerDatosZonasView')) {
    function obtenerDatosZonasView() {
        return planificadorViewsDataObtenerDatosZonas();
    }
}
if (!function_exists('obtenerDatosZonasClientesView')) {
    function obtenerDatosZonasClientesView($cod_zona = null) {
        return planificadorViewsDataObtenerDatosZonasClientes($cod_zona);
    }
}
if (!function_exists('obtenerDatosZonasRutasView')) {
    function obtenerDatosZonasRutasView($cod_zona = null, $cod_ruta_seleccionada = 0) {
        return planificadorViewsDataObtenerDatosZonasRutas($cod_zona, $cod_ruta_seleccionada);
    }
}
if (!function_exists('obtenerDatosCompletarDia')) {
    function obtenerDatosCompletarDia($codigo_vendedor, $fecha) {
        return planificadorViewsDataObtenerDatosCompletarDia($codigo_vendedor, $fecha);
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

if (!function_exists('seccionDisponibleParaAsignacionService')) {
    function seccionDisponibleParaAsignacionService($cod_cliente, $cod_seccion): ?bool {
        return seccionDisponibleParaAsignacion($cod_cliente, $cod_seccion);
    }
}

if (!function_exists('borrarAsignacionService')) {
    function borrarAsignacionService($cod_cliente, $cod_zona, $cod_seccion): bool {
        return borrarAsignacion($cod_cliente, $cod_zona, $cod_seccion);
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

if (!function_exists('reiniciarCiclosZonasService')) {
    function reiniciarCiclosZonasService(array $ordenesPorZona, $fecha_inicio_ciclo) {
        return planificadorRepoReiniciarCiclosZonas($ordenesPorZona, $fecha_inicio_ciclo);
    }
}

}


