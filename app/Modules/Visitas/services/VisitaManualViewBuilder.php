<?php

require_once __DIR__ . '/VisitasQueryService.php';

if (!function_exists('visitasBuildManualViewData')) {
    function visitasBuildManualViewData(array $input, int $codigo_vendedor): array
    {
        $viewData = prepararVistaVisitaManual($input, $codigo_vendedor);

        $cod_cliente = (int)($viewData['cod_cliente'] ?? 0);
        $cod_seccion = $viewData['cod_seccion'] ?? null;
        $resultadosBusqueda = $viewData['resultadosBusqueda'] ?? [];
        $citas = $viewData['citas'] ?? [];
        $assignment = $viewData['assignment'] ?? null;
        $tiempo_promedio_minutes = (float)($viewData['tiempo_promedio_minutes'] ?? 0);
        $hora_inicio_manana = (string)($viewData['hora_inicio_manana'] ?? '');
        $hora_fin_manana = (string)($viewData['hora_fin_manana'] ?? '');
        $hora_inicio_tarde = (string)($viewData['hora_inicio_tarde'] ?? '');
        $hora_fin_tarde = (string)($viewData['hora_fin_tarde'] ?? '');

        return $viewData + [
            'paso1BadgeClass' => $cod_cliente === 0 ? 'bg-primary' : 'bg-secondary',
            'mostrarPasosSeleccion' => $cod_cliente > 0,
            'mostrarFormularioRegistro' => $cod_cliente > 0 && !empty($assignment),
            'mostrarAvisoNunca' => !empty($assignment['frecuencia_visita']) && strtolower((string)$assignment['frecuencia_visita']) === 'nunca',
            'tiempoPromedioLabel' => visitasManualFormatearTiempoPromedio($tiempo_promedio_minutes),
            'disponibilidadMananaLabel' => visitasManualFormatearDisponibilidad($hora_inicio_manana, $hora_fin_manana),
            'disponibilidadTardeLabel' => visitasManualFormatearDisponibilidad($hora_inicio_tarde, $hora_fin_tarde),
            'preferenciaHorariaLabel' => !empty($assignment['preferencia_horaria']) ? (string)$assignment['preferencia_horaria'] : 'No definida',
            'codClienteHiddenValue' => $cod_cliente > 0 ? (string)$cod_cliente : '',
            'codSeccionHiddenValue' => $cod_seccion !== null ? (string)$cod_seccion : '',
            'resultadosBusquedaRender' => array_map('visitasManualPrepararResultadoBusqueda', $resultadosBusqueda),
            'citasRender' => array_map('visitasManualPrepararCita', $citas),
        ];
    }
}

if (!function_exists('visitasManualPrepararResultadoBusqueda')) {
    function visitasManualPrepararResultadoBusqueda(array $cliente): array
    {
        $clienteSeccion = ($cliente['cod_seccion'] === null || $cliente['cod_seccion'] === '')
            ? ''
            : (string)$cliente['cod_seccion'];

        $displayName = (string)($cliente['nombre_comercial'] ?? '');
        if ($clienteSeccion !== '') {
            $displayName .= ' - ' . (string)($cliente['nombre_seccion'] ?? '');
        }

        return [
            'display_name' => $displayName,
            'cod_cliente' => (string)((int)($cliente['cod_cliente'] ?? 0)),
            'cod_seccion' => $clienteSeccion,
        ];
    }
}

if (!function_exists('visitasManualPrepararCita')) {
    function visitasManualPrepararCita(array $cita): array
    {
        $estado = strtolower(trim((string)($cita['estado_visita'] ?? '')));

        return [
            'alert_class' => $estado === 'planificada' ? 'alert-info' : ($estado === 'pendiente' ? 'alert-warning' : 'alert-secondary'),
            'estado_label' => (string)($cita['estado_visita'] ?? ''),
            'fecha_label' => !empty($cita['fecha_visita']) ? date('d/m/Y', strtotime((string)$cita['fecha_visita'])) : '',
            'hora_inicio_label' => !empty($cita['hora_inicio_visita']) ? date('H:i', strtotime((string)$cita['hora_inicio_visita'])) : '',
            'hora_fin_label' => !empty($cita['hora_fin_visita']) ? date('H:i', strtotime((string)$cita['hora_fin_visita'])) : '',
        ];
    }
}

if (!function_exists('visitasManualFormatearTiempoPromedio')) {
    function visitasManualFormatearTiempoPromedio(float $tiempo_promedio_minutes): string
    {
        if ($tiempo_promedio_minutes >= 60) {
            $hours = (int)floor($tiempo_promedio_minutes / 60);
            $minutes = (int)$tiempo_promedio_minutes % 60;
            $label = $hours . ' ' . ($hours === 1 ? 'hora' : 'horas');
            if ($minutes > 0) {
                $label .= ' ' . $minutes . ' minutos';
            }
            return $label;
        }

        return (string)((int)$tiempo_promedio_minutes) . ' minutos';
    }
}

if (!function_exists('visitasManualFormatearDisponibilidad')) {
    function visitasManualFormatearDisponibilidad(string $inicio, string $fin): string
    {
        return ($inicio !== '' ? $inicio : 'No definido') . ' a ' . ($fin !== '' ? $fin : 'No definido');
    }
}
