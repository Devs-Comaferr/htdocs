<?php

if (!function_exists('planificadorBuildZonasClientesViewData')) {
    function planificadorBuildZonasClientesViewData(): array
    {
        $cod_zona = isset($_GET['cod_zona']) ? intval($_GET['cod_zona']) : null;
        $flashMensaje = trim((string)($_GET['mensaje'] ?? ''));
        $flashEstado = trim((string)($_GET['estado'] ?? ''));

        $zonasClientesViewData = obtenerDatosZonasClientesView($cod_zona);
        $zonas = $zonasClientesViewData['zonas'] ?? [];
        $zonas_alertas = $zonasClientesViewData['zonas_alertas'] ?? [];
        $clientes_desalineados = $zonasClientesViewData['clientes_desalineados'] ?? [];
        $asignaciones_por_zona = $zonasClientesViewData['asignaciones_por_zona'] ?? [];
        $zona_actual = $zonasClientesViewData['zona_actual'] ?? null;
        $rutas_asignadas = $zonasClientesViewData['rutas_asignadas'] ?? [];
        $clientes_disponibles = $zonasClientesViewData['clientes_disponibles'] ?? [];
        $asignaciones_actuales = $zonasClientesViewData['asignaciones_actuales'] ?? [];
        $cod_zona = $zonasClientesViewData['cod_zona'] ?? $cod_zona;
        $numSeccionesPorCliente = $zonasClientesViewData['numSeccionesPorCliente'] ?? [];

        $zonasBotones = [];
        foreach ($zonas as $zona) {
            $codZona = (string)($zona['cod_zona'] ?? '');
            $zonasBotones[] = [
                'cod_zona' => $zona['cod_zona'] ?? '',
                'nombre_zona' => toUTF8((string)($zona['nombre_zona'] ?? '')),
                'total_desalineados' => (int)($zonas_alertas[$codZona] ?? 0),
            ];
        }

        $clientesDisponiblesOptions = [];
        foreach ($clientes_disponibles as $cliente) {
            $clientesDisponiblesOptions[] = [
                'cod_cliente' => $cliente['cod_cliente'] ?? '',
                'nombre_cliente' => toUTF8((string)($cliente['nombre_cliente'] ?? '')),
            ];
        }

        $zonasSecundariasOptions = [];
        foreach ($zonas as $zona) {
            if (($zona['cod_zona'] ?? null) != $cod_zona) {
                $zonasSecundariasOptions[] = [
                    'cod_zona' => $zona['cod_zona'] ?? '',
                    'nombre_zona' => toUTF8((string)($zona['nombre_zona'] ?? '')),
                ];
            }
        }

        $asignacionesPreparadas = [];
        foreach ($asignaciones_actuales as $asignacion) {
            $clase = '';
            if (($asignacion['tipo_asignacion'] ?? '') === 'secundaria') {
                $clase = 'asignacion-secundaria';
            }
            if (($asignacion['frecuencia_visita'] ?? '') === 'Nunca') {
                $clase = 'frecuencia-nunca';
            }
            if (isset($asignacion['cod_cliente']) && isset($clientes_desalineados[(string)$asignacion['cod_cliente']])) {
                $clase = trim($clase . ' cliente-desalineado');
            }

            $poblacionCliente = trim((string)($asignacion['poblacion_cliente'] ?? ''));
            $poblacionSeccion = trim((string)($asignacion['poblacion_seccion'] ?? ''));
            $municipioLineaPrincipal = !empty($asignacion['nombre_seccion'])
                ? ($poblacionSeccion !== '' ? $poblacionSeccion : $poblacionCliente)
                : $poblacionCliente;

            $codCliFila = (string)($asignacion['cod_cliente'] ?? '');
            $tieneVariasSecciones = $codCliFila !== '' && isset($numSeccionesPorCliente[$codCliFila]) && count($numSeccionesPorCliente[$codCliFila]) > 1;

            $asignacionesPreparadas[] = [
                'row_class' => $clase,
                'nombre_linea_principal' => toUTF8((string)($asignacion['nombre_cliente'] ?? '')) . ' - ' . toUTF8((string)$municipioLineaPrincipal),
                'mostrar_seccion' => $tieneVariasSecciones && !empty($asignacion['nombre_seccion']),
                'nombre_seccion' => toUTF8((string)($asignacion['nombre_seccion'] ?? '')),
                'observaciones' => toUTF8((string)($asignacion['observaciones'] ?? '')),
                'frecuencia_label' => ucfirst((string)($asignacion['frecuencia_visita'] ?? '')),
                'permitir_acciones' => ($asignacion['tipo_asignacion'] ?? '') === 'primaria',
                'cod_cliente' => (string)($asignacion['cod_cliente'] ?? ''),
                'cod_seccion_hidden' => (string)($asignacion['cod_seccion'] ?? ''),
                'cod_seccion_data' => isset($asignacion['cod_seccion']) ? (string)$asignacion['cod_seccion'] : 'NULL',
                'nombre_cliente_data' => toUTF8((string)($asignacion['nombre_cliente'] ?? '')),
                'nombre_seccion_data' => toUTF8((string)($asignacion['nombre_seccion'] ?? '')),
                'nombre_zona_data' => toUTF8((string)($zona_actual['nombre_zona'] ?? '')),
                'zona_secundaria_data' => (string)($asignacion['zona_secundaria'] ?? ''),
                'tiempo_promedio_visita_data' => (string)($asignacion['tiempo_promedio_visita'] ?? ''),
                'preferencia_horaria_data' => (string)($asignacion['preferencia_horaria'] ?? ''),
                'frecuencia_visita_data' => (string)($asignacion['frecuencia_visita'] ?? ''),
                'observaciones_data' => toUTF8((string)($asignacion['observaciones'] ?? '')),
            ];
        }

        return [
            'pageTitle' => 'Asignar Clientes a Zonas',
            'flashMensaje' => $flashMensaje,
            'flashEstado' => $flashEstado,
            'zonasClientesViewData' => $zonasClientesViewData,
            'zonas' => $zonas,
            'zonas_alertas' => $zonas_alertas,
            'clientes_desalineados' => $clientes_desalineados,
            'asignaciones_por_zona' => $asignaciones_por_zona,
            'zona_actual' => $zona_actual,
            'rutas_asignadas' => $rutas_asignadas,
            'clientes_disponibles' => $clientes_disponibles,
            'asignaciones_actuales' => $asignaciones_actuales,
            'cod_zona' => $cod_zona,
            'numSeccionesPorCliente' => $numSeccionesPorCliente,
            'zonasBotones' => $zonasBotones,
            'clientesDisponiblesOptions' => $clientesDisponiblesOptions,
            'zonasSecundariasOptions' => $zonasSecundariasOptions,
            'asignacionesPreparadas' => $asignacionesPreparadas,
            'zonaActualNombre' => toUTF8((string)($zona_actual['nombre_zona'] ?? '')),
        ];
    }
}
