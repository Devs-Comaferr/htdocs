<?php

function obtenerHeaderBadges($conn, ?int $codigoSesionBar, ?int $badgeCerrados = null, ?int $badgeAbiertos = null, ?int $badgeSinVisita = null): array
{
    static $cache = array();

    $cacheKey = ($codigoSesionBar === null ? 'null' : (string) $codigoSesionBar) . '|' .
        ($badgeCerrados === null ? 'n' : (string) $badgeCerrados) . '|' .
        ($badgeAbiertos === null ? 'n' : (string) $badgeAbiertos) . '|' .
        ($badgeSinVisita === null ? 'n' : (string) $badgeSinVisita);

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if (!isset($conn)) {
        $result = array(
            'badgeCerrados' => $badgeCerrados ?? 0,
            'badgeAbiertos' => $badgeAbiertos ?? 0,
            'badgeSinVisita' => $badgeSinVisita ?? 0,
        );
        $cache[$cacheKey] = $result;
        return $result;
    }

    $whereCodComisionista = '';
    $whereCodComisionistaElim = '';
    $whereCodComisionistaPlan = '';
    if ($codigoSesionBar !== null) {
        $whereCodComisionista = " AND cod_comisionista = $codigoSesionBar";
        $whereCodComisionistaElim = " AND vcelim.cod_comisionista = $codigoSesionBar";
        $whereCodComisionistaPlan = " AND h.cod_comisionista = $codigoSesionBar";
    }

    if ($badgeAbiertos === null) {
        $qAbiertos = "
            SELECT COUNT(*) AS total
            FROM hist_ventas_cabecera
            WHERE tipo_venta = 1
              AND historico = 'N'
              AND CONVERT(date, fecha_venta) <= DATEADD(day, -7, GETDATE())
              $whereCodComisionista
              AND cod_venta NOT IN (
                  SELECT cod_venta
                  FROM cmf_solicitudes_pedido
                  WHERE tipo_solicitud = 'Historico'
              )
        ";
        $rAbiertos = @odbc_exec($conn, $qAbiertos);
        $badgeAbiertos = ($rAbiertos && @odbc_fetch_row($rAbiertos)) ? (int) @odbc_result($rAbiertos, 'total') : 0;
    }

    if ($badgeCerrados === null) {
        $qCerrados = "
            SELECT COUNT(*) AS total
            FROM (
                SELECT hvl.cod_venta
                FROM hist_ventas_linea hvl
                INNER JOIN hist_ventas_cabecera hvc
                    ON hvc.cod_venta = hvl.cod_venta
                   AND hvc.tipo_venta = 1
                LEFT JOIN (
                    SELECT cod_venta_origen, linea_origen, SUM(cantidad) AS cantidad_servida
                    FROM entrega_lineas_venta
                    WHERE tipo_venta_origen = 1
                    GROUP BY cod_venta_origen, linea_origen
                ) elv
                    ON hvl.cod_venta = elv.cod_venta_origen
                   AND hvl.linea = elv.linea_origen
                WHERE hvl.tipo_venta = 1
                  AND hvc.historico = 'S'
                  AND (hvl.cantidad > ISNULL(elv.cantidad_servida, 0))
                  AND CONVERT(date, hvc.fecha_venta) >= DATEADD(day, -15, CONVERT(date, GETDATE()))
                  AND CONVERT(date, hvc.fecha_venta) <= CONVERT(date, GETDATE())
                  $whereCodComisionista
                GROUP BY hvl.cod_venta
                HAVING SUM(
                    CASE
                        WHEN elv.cod_venta_origen IS NULL THEN hvl.cantidad * hvl.precio
                        ELSE (hvl.cantidad - ISNULL(elv.cantidad_servida, 0)) * hvl.precio
                    END
                ) > 70
                UNION
                SELECT vlelim.cod_venta
                FROM ventas_linea_elim vlelim
                INNER JOIN ventas_cabecera_elim vcelim
                    ON vcelim.cod_venta = vlelim.cod_venta
                   AND vcelim.tipo_venta = 1
                LEFT JOIN (
                    SELECT cod_venta_origen, linea_origen, SUM(cantidad) AS cantidad_servida
                    FROM entrega_lineas_venta
                    WHERE tipo_venta_origen = 1
                    GROUP BY cod_venta_origen, linea_origen
                ) elv2
                    ON vlelim.cod_venta = elv2.cod_venta_origen
                   AND vlelim.linea = elv2.linea_origen
                WHERE vlelim.tipo_venta = 1
                  AND (vlelim.cantidad > ISNULL(elv2.cantidad_servida, 0))
                  AND CONVERT(date, vcelim.fecha_venta) >= DATEADD(day, -15, CONVERT(date, GETDATE()))
                  AND CONVERT(date, vcelim.fecha_venta) <= CONVERT(date, GETDATE())
                  $whereCodComisionistaElim
                GROUP BY vlelim.cod_venta
                HAVING SUM(
                    CASE
                        WHEN elv2.cod_venta_origen IS NULL THEN vlelim.cantidad * vlelim.precio
                        ELSE (vlelim.cantidad - ISNULL(elv2.cantidad_servida, 0)) * vlelim.precio
                    END
                ) > 70
            ) t
        ";
        $rCerrados = @odbc_exec($conn, $qCerrados);
        $badgeCerrados = ($rCerrados && @odbc_fetch_row($rCerrados)) ? (int) @odbc_result($rCerrados, 'total') : 0;
    }

    if ($badgeSinVisita === null) {
        $qSinVisita = "
            SELECT COUNT(*) AS total
            FROM hist_ventas_cabecera h
            LEFT JOIN cmf_visita_pedidos vp ON h.cod_venta = vp.cod_venta
            WHERE vp.cod_venta IS NULL
              AND h.tipo_venta = 1
              AND h.fecha_venta >= '2025-01-01'
              $whereCodComisionistaPlan
        ";
        $rSinVisita = @odbc_exec($conn, $qSinVisita);
        $badgeSinVisita = ($rSinVisita && @odbc_fetch_row($rSinVisita)) ? (int) @odbc_result($rSinVisita, 'total') : 0;
    }

    $result = array(
        'badgeCerrados' => $badgeCerrados ?? 0,
        'badgeAbiertos' => $badgeAbiertos ?? 0,
        'badgeSinVisita' => $badgeSinVisita ?? 0,
    );
    $cache[$cacheKey] = $result;

    return $result;
}
