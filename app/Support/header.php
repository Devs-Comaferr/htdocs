<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';

if (!defined('MOBILE_APPBAR_RENDERED')) {
    define('MOBILE_APPBAR_RENDERED', true);
}

// Asume que la sesiÃƒÂ³n ya estÃƒÂ¡ iniciada y que $pageTitle existe (y si es index, $fechaConsulta).
$isIndex = (basename($_SERVER['PHP_SELF']) === 'index.php');

$conn = $conn ?? (function_exists('db') ? db() : null);

$config = function_exists('obtenerConfiguracionApp')
    ? obtenerConfiguracionApp($conn)
    : [
        'nombre_sistema' => 'COMAFERR',
        'color_primary' => '#2563eb',
        'logo_path' => '/imagenes/logo.png',
    ];

$systemName = (string)($config['nombre_sistema'] ?? 'COMAFERR');
$logoPath = trim((string)($config['logo_path'] ?? (BASE_URL . '/imagenes/logo.png')));
if ($logoPath === '') {
    $logoPath = BASE_URL . '/imagenes/logo.png';
} elseif (strpos($logoPath, '/imagenes/') === 0 || strpos($logoPath, '/assets/') === 0) {
    $logoPath = BASE_URL . $logoPath;
}

$pageTitleHeader = isset($pageTitle) ? (string)$pageTitle : '';
$headerClass = 'header';
if ($isIndex) {
    $headerClass .= ' index-header';
}

$sessionEmail = $_SESSION['email'] ?? '';
$esAdminBar = (function_exists('esAdmin') && esAdmin()) || (isset($_SESSION['es_admin']) && (int)$_SESSION['es_admin'] === 1);
$esPremiumBar = isset($_SESSION['tipo_plan']) && $_SESSION['tipo_plan'] === 'premium';
$puedeVerProductosBar = $esAdminBar || (isset($_SESSION['perm_productos']) && (int)$_SESSION['perm_productos'] === 1);
$puedeVerPlanificadorBar = $esAdminBar || ($esPremiumBar && (isset($_SESSION['perm_planificador']) && (int)$_SESSION['perm_planificador'] === 1));
$puedeVerEstadisticasBar = $esAdminBar || (isset($_SESSION['perm_estadisticas']) && (int)$_SESSION['perm_estadisticas'] === 1);
$codigoSesionBar = (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '') ? (int)$_SESSION['codigo'] : null;
$badgeCerrados = isset($count_pedidos_cerrados_70) ? (int)$count_pedidos_cerrados_70 : null;
$badgeAbiertos = isset($count_pedidos_abiertos) ? (int)$count_pedidos_abiertos : null;
$badgeSinVisita = isset($count_pedidos_sin_visita) ? (int)$count_pedidos_sin_visita : null;

if ($badgeCerrados === null || $badgeAbiertos === null || $badgeSinVisita === null) {
    if (isset($conn)) {
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
            $badgeAbiertos = ($rAbiertos && @odbc_fetch_row($rAbiertos)) ? (int)@odbc_result($rAbiertos, 'total') : 0;
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
            $badgeCerrados = ($rCerrados && @odbc_fetch_row($rCerrados)) ? (int)@odbc_result($rCerrados, 'total') : 0;
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
            $badgeSinVisita = ($rSinVisita && @odbc_fetch_row($rSinVisita)) ? (int)@odbc_result($rSinVisita, 'total') : 0;
        }
    } else {
        $badgeCerrados = $badgeCerrados ?? 0;
        $badgeAbiertos = $badgeAbiertos ?? 0;
        $badgeSinVisita = $badgeSinVisita ?? 0;
    }
}
