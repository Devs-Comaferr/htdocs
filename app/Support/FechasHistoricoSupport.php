<?php

/**
 * Funcion para formatear una fecha en "Mes Año" en español.
 * Si la extension intl esta disponible, se usa IntlDateFormatter; de lo contrario, un mapeo manual.
 */
if (!function_exists('formatearMesAno')) {
    if (!class_exists('IntlDateFormatter')) {
        function formatearMesAno(string $fecha): string
        {
            try {
                $date = new DateTime($fecha);
            } catch (Exception $e) {
                return $fecha;
            }
            $month = $date->format('F');
            $year = $date->format('Y');
            $meses = [
                'January' => 'Enero',
                'February' => 'Febrero',
                'March' => 'Marzo',
                'April' => 'Abril',
                'May' => 'Mayo',
                'June' => 'Junio',
                'July' => 'Julio',
                'August' => 'Agosto',
                'September' => 'Septiembre',
                'October' => 'Octubre',
                'November' => 'Noviembre',
                'December' => 'Diciembre',
            ];
            $mes = $meses[$month] ?? $month;
            return $mes . ' ' . $year;
        }
    } else {
        function formatearMesAno(string $fecha): string
        {
            try {
                $date = new DateTime($fecha);
            } catch (Exception $e) {
                return $fecha;
            }
            $formatter = new IntlDateFormatter(
                'es_ES',
                IntlDateFormatter::LONG,
                IntlDateFormatter::NONE,
                'Europe/Madrid',
                IntlDateFormatter::GREGORIAN,
                "MMMM yyyy"
            );
            return ucfirst($formatter->format($date));
        }
    }
}

if (!function_exists('construir_filtros_nuevo')) {
    function construir_filtros_nuevo(): string
    {
        global $fecha_desde, $fecha_hasta, $cod_articulo, $descripcion;
        $filtros = "";
        if ($fecha_desde) {
            $filtros .= " AND fecha_venta >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120)";
        }
        if ($fecha_hasta) {
            $filtros .= " AND fecha_venta <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120)";
        }
        if ($cod_articulo) {
            $filtros .= " AND cod_articulo LIKE '%" . addslashes($cod_articulo) . "%'";
        }
        if ($descripcion) {
            $filtros .= " AND descripcion LIKE '%" . addslashes($descripcion) . "%'";
        }
        return $filtros;
    }
}

if (!function_exists('construir_filtros_antiguo')) {
    function construir_filtros_antiguo(): string
    {
        global $fecha_desde, $fecha_hasta, $cod_articulo, $descripcion;
        $f = "";
        if ($fecha_desde) {
            $f .= " AND m.fecha >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120)";
        }
        if ($fecha_hasta) {
            $f .= " AND m.fecha <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120)";
        }
        if ($cod_articulo) {
            $f .= " AND m.referencia LIKE '%" . addslashes($cod_articulo) . "%'";
        }
        if ($descripcion) {
            $f .= " AND ad.descripcion LIKE '%" . addslashes($descripcion) . "%'";
        }
        return $f;
    }
}
