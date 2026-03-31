<?php

if (!function_exists('generarBadgesPedido')) {
    function generarBadgesPedido($observacion)
    {
        $obsOriginal = $observacion ?? '';
        $obs = strtoupper($obsOriginal);

        $badges = [];

        if (
            strpos($obs, 'URGENTE') !== false ||
            strpos($obs, 'URGE') !== false ||
            strpos($obs, 'HOY') !== false ||
            strpos($obs, 'MAÑANA') !== false
        ) {
            $badges[] = [
                'texto' => 'URGENTE',
                'clase' => 'bg-danger',
            ];
        }

        if (strpos($obs, 'ABONO') !== false) {
            $badges[] = [
                'texto' => 'ABONO',
                'clase' => 'bg-purple',
            ];
        }

        if (
            strpos($obs, 'AMPLIACION') !== false ||
            strpos($obs, 'AMPLIACIÓN') !== false
        ) {
            $badges[] = [
                'texto' => 'AMPLIACIÓN',
                'clase' => 'bg-primary',
            ];
        }

        if (
            strpos($obs, 'OJO') !== false ||
            strpos($obs, 'IMPORTANTE') !== false
        ) {
            $badges[] = [
                'texto' => 'ATENCIÓN',
                'clase' => 'bg-warning text-dark',
            ];
        }

        if (preg_match('/\b\d{1,3}\/\d{1,3}\b/', $obsOriginal, $matches)) {
            $badges[] = [
                'texto' => $matches[0],
                'clase' => 'badge-xy',
            ];
        }

        return $badges;
    }
}
