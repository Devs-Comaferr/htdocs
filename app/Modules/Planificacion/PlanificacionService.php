<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

require_once BASE_PATH . '/app/Modules/Planificacion/funciones_planificacion_rutas.php';

function crearZonaVisitaService($nombre_zona, $descripcion, $duracion_semanas, $orden) {
    return crearZonaVisita($nombre_zona, $descripcion, $duracion_semanas, $orden);
}

function obtenerZonasVisitaService() {
    return obtenerZonasVisita();
}

function obtenerRutasPorZonaService($cod_zona) {
    return obtenerRutasPorZona($cod_zona);
}

function asignarClienteZonaService($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones = '') {
    return asignarClienteZona($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones);
}

function asignarRutaZonaService($cod_zona, $cod_ruta) {
    return asignarRutaZona($cod_zona, $cod_ruta);
}
