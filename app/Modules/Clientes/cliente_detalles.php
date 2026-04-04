<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
unset($_SESSION['origen']);


// Obtener el código del cliente desde la URL
if (!isset($_GET['cod_cliente']) || empty($_GET['cod_cliente'])) {
    header("Location: clientes.php");
    exit();
}

require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Clientes/services/cliente_detalles_service.php';

$ui_version = 'bs5';
$ui_requires_jquery = false;

$conn = db();

$cod_cliente = trim((string) $_GET['cod_cliente']);
$cod_seccion = isset($_GET['cod_seccion']) ? trim((string) $_GET['cod_seccion']) : null;
// Código del comisionista (para filtrar datos a las operaciones realizadas por él)
$cod_comercial = $_SESSION['codigo'] ?? null;

try {
    $cliente = clienteDetallesObtenerClienteBase($conn, $cod_cliente);
} catch (RuntimeException $e) {
    error_log($e->getMessage());
    if ($e->getMessage() === 'Cliente no encontrado') {
        header("Location: clientes.php");
        exit();
    }
    echo 'Error interno';
    return;
}

$forma_pago = clienteDetallesObtenerFormaPago($conn, $cliente);
$tarifa_nombre = clienteDetallesObtenerTarifa($conn, $cliente);
$secciones = clienteDetallesObtenerSecciones($conn, $cod_cliente);
$contactos = clienteDetallesObtenerContactos($conn, $cod_cliente);
$asignacion = clienteDetallesObtenerAsignacion($conn, $cod_cliente, $cod_seccion);
list($mostrar_nueva_funcionalidad, $visitas) = clienteDetallesObtenerVisitas($conn, $cod_cliente, $cod_seccion, $cod_comercial, $secciones);
list($visitasPorPagina, $totalVisitas, $totalPaginasVisitas, $paginaVisitas, $offsetVisitas, $visitasPaginadas) = clienteDetallesPaginarVisitas($visitas, $_GET);

$pageTitle = toUTF8($cliente['nombre_comercial']);

$sqlGraficoLineas = "
    SELECT 
        YEAR(hvc.fecha_venta) AS anio,
        MONTH(hvc.fecha_venta) AS mes,
        SUM(CASE WHEN hvc.tipo_venta = 1 THEN hvc.importe ELSE 0 END) AS total_pedidos,
        SUM(CASE WHEN hvc.tipo_venta = 2 THEN hvc.importe ELSE 0 END) AS total_albaranes
    FROM [integral].[dbo].[hist_ventas_cabecera] hvc
    WHERE hvc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hvc.fecha_venta >= '2024-10-01'
      AND hvc.fecha_venta <= GETDATE()
    GROUP BY YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta)
    ORDER BY YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta)
";
$resultGraficoLineas = null;
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlGraficoLineas = str_replace("AND hvc.fecha_venta <= GETDATE()", "AND hvc.fecha_venta <= GETDATE()\n      AND hvc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'", $sqlGraficoLineas);
}
$resultGraficoLineas = odbc_exec($conn, $sqlGraficoLineas);

// Guardamos los resultados en un array asociativo con clave 'YYYY-MM'
$datosDict = [];
while ($rowG = odbc_fetch_array($resultGraficoLineas)) {
    $anio  = (int)$rowG['anio'];
    $mes   = (int)$rowG['mes'];
    $periodo = sprintf('%04d-%02d', $anio, $mes);

    $datosDict[$periodo] = [
        'pedidos'   => (float)$rowG['total_pedidos'],
        'albaranes' => (float)$rowG['total_albaranes']
    ];
}

// Rellenar con 0 los meses en los que no hay datos
$fechaInicio = new DateTime('2024-10-01');
$fechaFin    = new DateTime();
$fechaFin->modify('last day of this month'); // Hasta el final del mes actual
$intervalo = new DateInterval('P1M');
$periodo   = new DatePeriod($fechaInicio, $intervalo, $fechaFin);

$datosMensuales = [];
foreach ($periodo as $dt) {
    $mesClave = $dt->format('Y-m');
    
    if (isset($datosDict[$mesClave])) {
        $pedidos   = $datosDict[$mesClave]['pedidos'];
        $albaranes = $datosDict[$mesClave]['albaranes'];
    } else {
        $pedidos   = 0;
        $albaranes = 0;
    }

    $datosMensuales[] = [
        'periodo'   => $mesClave,
        'pedidos'   => $pedidos,
        'albaranes' => $albaranes
    ];
}

$datosMensualesJson = json_encode($datosMensuales);

// Desglose comparativo por mes y articulo (pedido vs albaran) para el modal de barras
$sqlDetalleBarras = "
    SELECT
        YEAR(hvc.fecha_venta) AS anio,
        MONTH(hvc.fecha_venta) AS mes,
        hvc.tipo_venta,
        hl.cod_articulo,
        COALESCE(ad.descripcion, hl.descripcion) AS descripcion,
        SUM(hl.cantidad) AS cantidad,
        SUM(hl.importe) AS importe
    FROM [integral].[dbo].[hist_ventas_linea] hl
    INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc
        ON hl.cod_venta = hvc.cod_venta
       AND hl.tipo_venta = hvc.tipo_venta
    LEFT JOIN [integral].[dbo].[articulo_descripcion] ad
        ON ad.cod_articulo = hl.cod_articulo
       AND ad.cod_idioma = 'ES'
    WHERE hvc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hvc.fecha_venta >= '2024-10-01'
      AND hvc.fecha_venta <= GETDATE()
      AND hvc.tipo_venta IN (1, 2)
    GROUP BY
        YEAR(hvc.fecha_venta),
        MONTH(hvc.fecha_venta),
        hvc.tipo_venta,
        hl.cod_articulo,
        COALESCE(ad.descripcion, hl.descripcion)
    ORDER BY
        YEAR(hvc.fecha_venta),
        MONTH(hvc.fecha_venta),
        hvc.tipo_venta,
        SUM(hl.importe) DESC
";
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlDetalleBarras = str_replace(
        "AND hvc.tipo_venta IN (1, 2)",
        "AND hvc.tipo_venta IN (1, 2)\n      AND hvc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'",
        $sqlDetalleBarras
    );
}

$resDetalleBarras = odbc_exec($conn, $sqlDetalleBarras);
$detalleBarras = [];
if ($resDetalleBarras) {
    while ($rowD = odbc_fetch_array($resDetalleBarras)) {
        $anio = (int)$rowD['anio'];
        $mes = (int)$rowD['mes'];
        $tipo = (int)$rowD['tipo_venta'];
        $keyMes = sprintf('%04d-%02d', $anio, $mes);
        $codArticulo = (string)$rowD['cod_articulo'];
        $descripcion = toUTF8((string)$rowD['descripcion']);
        $keyArticulo = $codArticulo . '|' . $descripcion;

        if (!isset($detalleBarras[$keyMes])) {
            $detalleBarras[$keyMes] = [];
        }
        if (!isset($detalleBarras[$keyMes][$keyArticulo])) {
            $detalleBarras[$keyMes][$keyArticulo] = [
                'cod_articulo' => $codArticulo,
                'descripcion' => $descripcion,
                'cantidad_pedido' => 0.0,
                'importe_pedido' => 0.0,
                'cantidad_albaran' => 0.0,
                'importe_albaran' => 0.0
            ];
        }

        if ($tipo === 1) {
            $detalleBarras[$keyMes][$keyArticulo]['cantidad_pedido'] += (float)$rowD['cantidad'];
            $detalleBarras[$keyMes][$keyArticulo]['importe_pedido'] += (float)$rowD['importe'];
        } elseif ($tipo === 2) {
            $detalleBarras[$keyMes][$keyArticulo]['cantidad_albaran'] += (float)$rowD['cantidad'];
            $detalleBarras[$keyMes][$keyArticulo]['importe_albaran'] += (float)$rowD['importe'];
        }
    }

    foreach ($detalleBarras as $mesKey => $items) {
        $lista = array_values($items);
        usort($lista, function($a, $b) {
            $totalA = (float)$a['importe_pedido'] + (float)$a['importe_albaran'];
            $totalB = (float)$b['importe_pedido'] + (float)$b['importe_albaran'];
            return $totalB <=> $totalA;
        });
        $detalleBarras[$mesKey] = $lista;
    }
}
$detalleBarrasJson = json_encode($detalleBarras, JSON_UNESCAPED_UNICODE);

$sqlGraficoFamilia = "
    SELECT
        YEAR(hc.fecha_venta) AS anio,
        MONTH(hc.fecha_venta) AS mes,
        fam.cod_familia,
        fam.descripcion AS familia,
        SUM(hl.importe) AS total_familia
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc
      ON hl.cod_venta = hc.cod_venta
    JOIN [integral].[dbo].[articulos] art
      ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[familias] fam
      ON art.cod_familia = fam.cod_familia
    WHERE hc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) AND $cod_comercial === '30') {
    $sqlGraficoFamilia .= " AND hc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'";
}
$sqlGraficoFamilia .= "
    GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), fam.cod_familia, fam.descripcion
    ORDER BY anio, mes
";

$resultFamilia = odbc_exec($conn, $sqlGraficoFamilia);
$datosFamiliaMensual = [];
if ($resultFamilia) {
    while ($rowF = odbc_fetch_array($resultFamilia)) {
        $datosFamiliaMensual[] = [
            'anio'        => (int)$rowF['anio'],
            'mes'         => (int)$rowF['mes'],
            'cod_familia' => (string)$rowF['cod_familia'],
            'familia'     => toUTF8((string)$rowF['familia']),
            'importe'     => (float)$rowF['total_familia']
        ];
    }
}
$datosFamiliaMensualJson = json_encode($datosFamiliaMensual);

$sqlGraficoMarca = "
    SELECT
        YEAR(hc.fecha_venta) AS anio,
        MONTH(hc.fecha_venta) AS mes,
        mar.descripcion AS marca,
        art.cod_familia,
        SUM(hl.importe) AS total_marca
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc
      ON hl.cod_venta = hc.cod_venta
    JOIN [integral].[dbo].[articulos] art
      ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[web_marcas] mar
      ON art.cod_marca_web = mar.cod_marca
    WHERE hc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) AND $cod_comercial === '30') {
    $sqlGraficoMarca .= " AND hc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'";
}
$sqlGraficoMarca .= "
    GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), mar.descripcion, art.cod_familia
    ORDER BY anio, mes
";

$resultMarca = odbc_exec($conn, $sqlGraficoMarca);
$datosMarcaMensual = [];
if ($resultMarca) {
    while ($rowM = odbc_fetch_array($resultMarca)) {
        $datosMarcaMensual[] = [
            'anio'        => (int)$rowM['anio'],
            'mes'         => (int)$rowM['mes'],
            'marca'       => toUTF8((string)$rowM['marca']),
            'cod_familia' => (string)$rowM['cod_familia'],
            'importe'     => (float)$rowM['total_marca']
        ];
    }
}
$datosMarcaMensualJson = json_encode($datosMarcaMensual);

$sqlGraficoArticulos = "
    SELECT
        YEAR(hc.fecha_venta) AS anio,
        MONTH(hc.fecha_venta) AS mes,
        ad.descripcion AS articulo,
        art.cod_familia,
        SUM(hl.importe) AS total_articulo
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc
      ON hl.cod_venta = hc.cod_venta
    JOIN [integral].[dbo].[articulos] art
      ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[articulo_descripcion] ad
      ON ad.cod_articulo = art.cod_articulo
     AND ad.cod_idioma = 'ES'
    WHERE hc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) AND $cod_comercial === '30') {
    $sqlGraficoArticulos .= " AND hc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'";
}
$sqlGraficoArticulos .= "
    GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), ad.descripcion, art.cod_familia
    ORDER BY anio, mes
";

$resultArt = odbc_exec($conn, $sqlGraficoArticulos);
$datosArticulosMensual = [];
if ($resultArt) {
    while ($rowA = odbc_fetch_array($resultArt)) {
        $datosArticulosMensual[] = [
            'anio'        => (int)$rowA['anio'],
            'mes'         => (int)$rowA['mes'],
            'articulo'    => toUTF8((string)$rowA['articulo']),
            'cod_familia' => (string)$rowA['cod_familia'],
            'importe'     => (float)$rowA['total_articulo']
        ];
    }
}
$datosArticulosMensualJson = json_encode($datosArticulosMensual);
$pageTitle = toUTF8($cliente['nombre_comercial']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles del Cliente - <?= htmlspecialchars((string)$pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap 5 CSS (local via Composer assets) -->

    <!-- Se asume que en header.php se incluye Font Awesome 6.4 -->
    <style>
        body { padding-top: 20px; background: linear-gradient(180deg, #f4f7fb 0%, #eef3f8 100%); font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #16324a; }
        .container { max-width: 1240px; margin: 20px auto; background-color: #fff; padding: 30px; border-radius: 18px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
        th, td { padding: 12px; text-align: left; border: 1px solid #dde5ee; }
        th { background-color: #f4f7fb; color: #36506a; font-weight: 700; }
        td { background-color: #fbfcfe; }
        a { text-decoration: none; color: #0f5cab; }
        a:hover { text-decoration: underline; }
        .back-button { background-color: #0f5cab; color: white; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-size: 15px; font-weight: 700; margin-top: 20px; display: inline-block; box-shadow: 0 10px 22px rgba(15, 92, 171, 0.18); }
        .back-button:hover { background-color: #0056b3; }
        .faltas-button, .historico-button { display: inline-block; padding: 10px 20px; border-radius: 999px; font-size: 14px; font-weight: bold; text-align: center; text-decoration: none; transition: all 0.3s ease; margin-right: 10px; }
        .faltas-button { background-color: #ff4d4d; color: white; }
        .faltas-button:hover { background-color: #e63939; transform: translateY(-2px); }
        .historico-button { background-color: #28a745; color: white; }
        .historico-button:hover { background-color: #218838; transform: translateY(-2px); }
        .button-container { text-align: center; margin-top: 18px; }
        .moroso { margin: 0 0 14px; padding: 10px 14px; border-radius: 12px; background: #fff1f0; border: 1px solid #f7c6c1; color: #b42318; font-weight: 800; text-align: center; font-size: 15px; }
        .cliente-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
        .cliente-head-copy h1 { margin: 0 0 4px; color: #12344d; font-size: 28px; }
        .cliente-head-copy p { margin: 0; color: #62748a; font-size: 15px; }
        .cliente-eyebrow { margin-bottom: 6px; font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7c93; }
        .cliente-head-badges { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .cliente-badge { display: inline-flex; align-items: center; padding: 7px 11px; border-radius: 999px; background: #eef5ff; color: #0f5cab; font-size: 12px; font-weight: 700; }
        .cliente-badge-soft { background: #f4f7fb; color: #506273; }
        .section-card { margin-top: 16px; padding: 16px 18px; border: 1px solid #e3eaf2; border-radius: 16px; background: #ffffff; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04); }
        .section-card h2,
        .section-card h3 { margin: 0 0 4px; color: #16324a; }
        .section-card > p.section-intro { margin: 0 0 10px; color: #6b7c93; font-size: 13px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .summary-item { padding: 11px 13px; border-radius: 14px; background: linear-gradient(180deg, #fbfcfe 0%, #f5f8fc 100%); border: 1px solid #e1e8f0; box-shadow: inset 0 1px 0 rgba(255,255,255,0.8); min-height: 62px; }
        .summary-item-wide { grid-column: 1 / -1; }
        .summary-item-span-2 { grid-column: span 2; }
        .summary-label { display: block; margin-bottom: 4px; font-size: 10px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #6b7c93; }
        .summary-value { color: #17324d; font-size: 14px; font-weight: 700; line-height: 1.3; word-break: break-word; }
        .summary-value-soft { font-weight: 600; }
        .summary-value a { color: #0f5cab; text-decoration: none; }
        .summary-value a:hover { text-decoration: underline; }
        .contact-lines { display: grid; gap: 6px; }
        .contact-line { display: flex; align-items: center; gap: 8px; }
        .contact-line-icon { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 999px; background: #eef5ff; color: #0f5cab; font-size: 11px; flex: 0 0 auto; }
        .contact-line-body { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .contact-line-comment { color: #6b7c93; font-size: 12px; font-weight: 500; }
        .summary-alert { background: linear-gradient(180deg, #fff4e8 0%, #fff0df 100%); border-color: #f4d1a6; }
        .planning-panel { padding: 12px 14px; border-radius: 14px; background: linear-gradient(180deg, #fbfcfe 0%, #f5f8fc 100%); border: 1px solid #e1e8f0; box-shadow: inset 0 1px 0 rgba(255,255,255,0.8); }
        .planning-row { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: 14px; padding: 9px 0; }
        .planning-row + .planning-row { border-top: 1px solid #e3eaf2; }
        .planning-block { min-width: 0; }
        .planning-block-label { display: block; margin-bottom: 4px; font-size: 10px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #6b7c93; }
        .planning-block-value { color: #17324d; font-size: 14px; font-weight: 700; line-height: 1.3; }
        .planning-block-value-soft { color: #486581; font-weight: 600; }
        .planning-kpi { display: flex; flex-direction: column; gap: 6px; }
        .planning-kpi-value { color: #0f5cab; font-size: 24px; font-weight: 800; line-height: 1; letter-spacing: -0.02em; }
        .planning-kpi-note { color: #6b7c93; font-size: 12px; font-weight: 600; }
        .planning-slots { display: flex; flex-wrap: wrap; gap: 6px; }
        .planning-slot { display: inline-flex; align-items: center; gap: 8px; padding: 6px 9px; border-radius: 999px; background: #eef3f8; border: 1px solid #d7e1eb; color: #486581; font-size: 12px; font-weight: 700; }
        .planning-slot strong { color: #17324d; }
        .planning-slot.is-active-morning { background: #fff4cc; border-color: #f4d26c; color: #815b00; }
        .planning-slot.is-active-afternoon { background: #e8f1ff; border-color: #bcd2f7; color: #0f5cab; }
        .planning-inline-action { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 6px; }
        .planning-zones { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .planning-zone-tag { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 999px; background: #eef5ff; color: #0f5cab; font-size: 10px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
        .planning-zone-name { color: #17324d; font-size: 14px; font-weight: 700; }
        .planning-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .planning-meta-pill { display: inline-flex; align-items: center; padding: 6px 9px; border-radius: 999px; background: #f4f7fb; border: 1px solid #dfe7f0; color: #486581; font-size: 12px; font-weight: 700; }
        .planning-meta-pill strong { color: #17324d; margin-right: 6px; }
        .planning-inline-separator { color: #9aa9b8; font-weight: 700; }
        .planning-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 8px 14px; border-radius: 12px; border: 1px solid transparent; text-decoration: none; font-size: 13px; font-weight: 700; line-height: 1; transition: all 0.2s ease; }
        .planning-btn:hover { transform: translateY(-1px); text-decoration: none; }
        .planning-btn-secondary { background: #f4f7fb; border-color: #dfe7f0; color: #17324d; }
        .planning-btn-secondary:hover { background: #edf3fa; color: #0f5cab; }
        .planning-btn-primary { background: #0f5cab; border-color: #0f5cab; color: #ffffff; box-shadow: 0 8px 18px rgba(15, 92, 171, 0.18); }
        .planning-btn-primary:hover { background: #0c4f93; color: #ffffff; }
        .table-scroll { margin-top: 10px; overflow-x: auto; border-radius: 14px; border: 1px solid #dfe7f0; background: #ffffff; }
        .detail-table { margin-top: 0; border-collapse: separate; border-spacing: 0; min-width: 680px; }
        .detail-table th, .detail-table td { padding: 9px 11px; }
        .detail-table th { width: 18%; background: #f5f8fc; color: #486581; }
        .detail-table td { background: #ffffff; color: #17324d; }
        .detail-table tr:nth-child(even) td { background: #fbfcfe; }
        .detail-table th:first-child { border-top-left-radius: 12px; }
        .detail-table tr:first-child td:last-child { border-top-right-radius: 12px; }
        .detail-table tr:last-child th:first-child { border-bottom-left-radius: 12px; }
        .detail-table tr:last-child td:last-child { border-bottom-right-radius: 12px; }
        .detail-table a { font-weight: 700; }
        .detail-table a.section-link {
            color: #17324d;
            text-decoration: none;
            font-weight: 700;
        }
        .detail-table a.section-link:hover {
            color: #0f5cab;
            text-decoration: none;
        }
        .detail-table td .fa-brands.fa-whatsapp { margin-left: 8px; color: #1f9d55; }
        .empty-state { margin: 0; padding: 13px 15px; border-radius: 12px; background: #f4f7fb; border: 1px solid #e2e8f0; color: #506273; }
        .section-card.section-chart { padding-bottom: 20px; }
        .chart-toolbar { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-bottom:10px; }
        .chart-grid { margin-top: 18px; }

        .section-card.section-visitas { margin-top: 20px; }
        .visitas-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .visitas-header h2 { margin: 0 0 4px; }
        .visitas-counter { white-space: nowrap; }
        .visitas-container { margin-top: 0; }
        .visita-item { padding: 13px 15px; margin-bottom: 12px; border-radius: 14px; color: #fff; display: block; transition: background-color 0.3s ease, transform 0.2s; cursor: pointer; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08); }
        .visita-item:hover { opacity: 0.9; transform: scale(1.01); }
        .visita-linea { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .visita-linea span { margin-right: 8px; margin-bottom: 6px; font-size: 13px; font-weight: 700; padding: 6px 9px; border-radius: 999px; background: rgba(255,255,255,0.16); backdrop-filter: blur(2px); }
        .visita-observaciones { display: block; margin-top: 6px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.18); font-style: italic; color: #ffffff; }
        @media screen and (max-width: 1024px) {
            .visita-linea { flex-direction: column; align-items: flex-start; }
            .visita-linea span { margin-right: 0; }
        }
        .pedido-item { position: relative; background: #fff; padding: 12px 16px; margin-left: 22px; margin-bottom: 11px; border-radius: 14px; border: 1px solid #e6edf5; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); transition: transform 0.2s; cursor: pointer; }
        .pedido-item:hover { transform: scale(1.02); }
        .pedido-item::before { content: ""; position: absolute; left: 0; top: 0; width: 8px; height: 100%; border-radius: 14px 0 0 14px; background-color: #6c757d; }
        .pedido-content { margin-left: 8px; }
        .pedido-info { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 6px; }
        .pedido-info > div { margin-right: 0; margin-bottom: 0; padding: 6px 9px; border-radius: 999px; background: #f4f7fb; color: #17324d; font-size: 12px; font-weight: 700; }
        .pedido-observaciones { margin-top: 6px; padding-top: 8px; border-top: 1px solid #edf2f7; font-style: italic; color: #0f5cab; }
        .label { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 700; line-height: 1.2; color: #fff; vertical-align: middle; }
        .label-warning { background: #f0ad4e; }
        .pedido-actions { position: absolute; top: 10px; right: 10px; z-index: 10; }
        .btn-circle { border-radius: 50%; width: 40px; height: 40px; font-size: 16px; padding: 0; display: inline-flex; align-items: center; justify-content: center; margin-left: 4px; border: none; outline: none; }
        .btn-visita   { background-color: #28a745; color: #fff; }
        .btn-telefono { background-color: #ffc107; color: #fff; }
        .btn-whatsapp { background-color: #25D366; color: #fff; }
        .btn-email    { background-color: #17a2b8; color: #fff; }
        .btn-eliminar { background-color: #dc3545; color: #fff; }
        .btn[disabled] { background-color: grey !important; color: #fff !important; cursor: not-allowed; }
        @media screen and (max-width: 1024px) { .pedido-actions { right: 70px; } }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 4% auto; padding: 18px 20px; border: 1px solid #d9e2ec; width: 90%; max-width: 1200px; border-radius: 18px; position: relative; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18); }
        .modal-content h3 { margin: 0 0 10px; color: #16324a; }
        .modal-content h4 { margin: 16px 0 10px; color: #36506a; }
        .modal-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px; margin-bottom: 10px; }
        .modal-summary-item { padding: 9px 10px; border-radius: 12px; background: #f4f7fb; border: 1px solid #e2e8f0; color: #17324d; }
        .modal-summary-item strong { display: block; margin-bottom: 4px; color: #486581; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        .modal-note { margin-top: 10px; padding-top: 10px; border-top: 1px solid #edf2f7; color: #0f5cab; font-style: italic; }
        .page-nav { display:flex; flex-wrap:wrap; justify-content:center; gap:8px; margin-top: 14px; }
        .page-nav .back-button { margin-top: 0; margin-right: 0 !important; padding: 9px 16px; font-size: 14px; }
        .close { color: #6b7c93; position: absolute; top: 14px; right: 20px; font-size: 30px; font-weight: bold; cursor: pointer; z-index: 9999; }
        .close:hover, .close:focus { color: #12344d; text-decoration: none; cursor: pointer; }
        .modal-table-container { width: 100%; overflow-x: auto; }
        .modal-table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 13px; }
        .modal-table th, .modal-table td { padding: 8px; text-align: left; border: 1px solid #ddd; vertical-align: top; }
        .modal-table th { background-color: #f4f4f4; }
        .modal-table tr.diff-cantidad-pedido-mayor td { background-color: #ffe5e5 !important; }
        .modal-table tr.diff-cantidad-albaran-mayor td { background-color: #e7f7ea !important; }
        .modal-table tr.diff-importe-pedido-mayor td { background-color: #fff6d6 !important; }
        .modal-table tr.diff-importe-albaran-mayor td { background-color: #e8f1ff !important; }
        .leyenda-diff { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0 10px; font-size: 12px; }
        .leyenda-item { display: inline-flex; align-items: center; gap: 6px; padding: 3px 7px; border: 1px solid #ddd; border-radius: 14px; background: #fff; }
        .leyenda-color { width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(0,0,0,.15); }
        .descripcion-con-observacion { position: relative; }
        .descripcion-con-observacion .observacion { display: block; color: #007bff; font-style: italic; margin-top: 5px; }
        .section-card .button-container { margin-top: 12px; }

        @media (max-width: 1100px) {
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .summary-item-span-2 { grid-column: 1 / -1; }
            .planning-row { grid-template-columns: 1fr; gap: 10px; }
        }

        @media (max-width: 767px) {
            .cliente-head { flex-direction: column; }
            .cliente-head-badges { justify-content: flex-start; }
            .chart-col { margin-bottom: 22px; }
            .container { padding: 22px 16px; border-radius: 0; margin: 0; }
            .section-card { padding: 14px; border-radius: 14px; }
            .summary-grid { grid-template-columns: 1fr; }
            .summary-item-wide { grid-column: auto; }
            .summary-item-span-2 { grid-column: auto; }
            .pedido-item { margin-left: 0; }
            .visitas-header { flex-direction: column; }
            .visitas-counter { white-space: normal; }
        }
    </style>

    <!-- Chart.js (local via Composer assets) -->
    <script src="<?= BASE_URL ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
    <!-- ChartDataLabels plugin (local via Composer assets) -->
    <script src="<?= BASE_URL ?>/assets/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js"></script>
    <script>
        // Registramos el plugin de DataLabels
        Chart.register(ChartDataLabels);

        const colorFamilias = {
            'A': '#F39C12',
            'B': '#8B5A2B',  // Madera
            'C': '#F1C40F',  // Electricidad
            'D': '#E74C3C',  // Herramientas
            'E': '#3498DB',
            'F': '#E84393',  // Cocina
            'G': '#27AE60',
            'H': '#95A5A6',
            'I': '#8E44AD',  // Pinturas
            'J': '#FFC0CB',
            'K': '#D2B48C',  // Mobiliario
            'L': '#00BCD4',
            '1': '#000000',  // No tangibles
            '99': '#424242', // Varios sin clasificar
            '2': '#B6C002'   // Nuevos de Cooperativa
        };

        // Datos en JSON (desde PHP)
        var datosMensuales = <?php echo $datosMensualesJson; ?>;
        var detalleBarrasMap = <?php echo $detalleBarrasJson; ?>;
        var datosFamiliaMensual = <?php echo $datosFamiliaMensualJson; ?>;
        var datosMarcaMensual = <?php echo $datosMarcaMensualJson; ?>;
        var datosArticulosMensual = <?php echo $datosArticulosMensualJson; ?>;

        // Modal
window.onclick = function(event) {
            var modals = document.getElementsByClassName('modal');
            for(var i=0; i<modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }

        // Asignar eventos a visita-item y pedido-item
        function asignarEventos() {
            var visitas = document.getElementsByClassName('visita-item');
            for (var i = 0; i < visitas.length; i++) {
                visitas[i].onclick = function() {
                    var id_visita = this.getAttribute('data-id-visita');
                    abrirModal('modal-visita-' + id_visita);
                };
            }
            var pedidos = document.getElementsByClassName('pedido-item');
            for (var i = 0; i < pedidos.length; i++) {
                pedidos[i].onclick = function(event) {
                    if (event.target.classList.contains('observacion')) return;
                    var cod_pedido = this.getAttribute('data-cod-pedido');
                    abrirModal('modal-pedido-' + cod_pedido);
                };
            }
        }

        function cargarPaginaVisitas(url, pushState) {
            var container = document.querySelector('.visitas-container');
            if (!container) return;

            var xhr = null;
            if (window.XMLHttpRequest) {
                xhr = new XMLHttpRequest();
            } else if (window.ActiveXObject) {
                xhr = new ActiveXObject("Microsoft.XMLHTTP");
            }
            if (xhr == null) {
                window.location.href = url;
                return;
            }

            container.style.opacity = '0.6';
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) return;
                container.style.opacity = '';
                if (xhr.status !== 200) {
                    window.location.href = url;
                    return;
                }

                if (typeof DOMParser === 'undefined') {
                    window.location.href = url;
                    return;
                }

                var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
                var nuevoContainer = doc.querySelector('.visitas-container');
                if (!nuevoContainer) {
                    window.location.href = url;
                    return;
                }

                container.innerHTML = nuevoContainer.innerHTML;
                asignarEventos();

                if (pushState !== false && window.history && window.history.pushState) {
                    window.history.pushState({ pagVisitas: true }, '', url);
                }
            };
            xhr.send(null);
        }

        function activarPaginacionVisitasAjax() {
            var container = document.querySelector('.visitas-container');
            if (!container) return;

            container.addEventListener('click', function(event) {
                var target = event.target;
                var link = null;
                if (target && typeof target.closest === 'function') {
                    link = target.closest('a[href*="pag_visitas="]');
                }
                if (!link) return;

                event.preventDefault();
                cargarPaginaVisitas(link.href, true);
            });

            if (window.history && window.history.pushState) {
                window.addEventListener('popstate', function() {
                    cargarPaginaVisitas(window.location.href, false);
                });
            }
        }

        // Función para quitar pedido y actualizar origen
        var csrfTokenVisitas = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function quitarPedido(codPedido, e) {
    e.stopPropagation();
    if (!confirm("¿Deseas quitar este pedido de la visita?")) {
        return;
    }
    fetch('<?= BASE_URL ?>/ajax/quitar_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: 'cod_pedido=' + encodeURIComponent(codPedido) + '&_csrf_token=' + encodeURIComponent(csrfTokenVisitas)
    })
        .then(function(response) {
            return response.text();
        })
        .then(function(responseText) {
            if (responseText.indexOf("OK") === 0) {
                location.reload();
            } else {
                alert("Error al eliminar el pedido: " + responseText);
            }
        })
        .catch(function() {
            alert("Error al eliminar el pedido (AJAX).");
        });
}

function actualizarOrigen(codPedido, nuevoOrigen, e) {
    e.stopPropagation();
    fetch('<?= BASE_URL ?>/ajax/actualizar_origen.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: 'cod_pedido=' + encodeURIComponent(codPedido) + '&origen=' + encodeURIComponent(nuevoOrigen) + '&_csrf_token=' + encodeURIComponent(csrfTokenVisitas)
    })
        .then(function(response) {
            return response.text();
        })
        .then(function(responseText) {
            if (responseText.indexOf("OK") === 0) {
                location.reload();
            } else {
                alert("Error al actualizar el origen: " + responseText);
            }
        })
        .catch(function() {
            alert("Error al actualizar el origen (AJAX).");
        });
}

        var graficoComparativa = null;
        var graficoFamilia = null;
        var graficoMarca = null;
        var graficoArticulos = null;

        function escapeHtml(valor) {
            return String(valor)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function mostrarDetalleBarra(periodoTxt, items) {
            var titulo = document.getElementById('detalleBarrasTitulo');
            var contenido = document.getElementById('detalleBarrasContenido');
            if (!titulo || !contenido) return;

            titulo.innerHTML = 'Detalle mensual (Pedido vs Albarán) - ' + escapeHtml(periodoTxt);

            if (!Array.isArray(items) || items.length === 0) {
                contenido.innerHTML = '<p>No hay líneas para ese período.</p>';
                abrirModal('modal-detalle-barras');
                return;
            }

            var filas = '';
            var totalCantPedido = 0;
            var totalImpPedido = 0;
            var totalCantAlbaran = 0;
            var totalImpAlbaran = 0;
            var EPS = 0.0001;
            items.forEach(function(item) {
                var cantidadPedido = Number(item.cantidad_pedido || 0);
                var importePedido = Number(item.importe_pedido || 0);
                var cantidadAlbaran = Number(item.cantidad_albaran || 0);
                var importeAlbaran = Number(item.importe_albaran || 0);

                totalCantPedido += cantidadPedido;
                totalImpPedido += importePedido;
                totalCantAlbaran += cantidadAlbaran;
                totalImpAlbaran += importeAlbaran;

                var claseFila = '';
                if (Math.abs(cantidadPedido - cantidadAlbaran) > EPS) {
                    if (cantidadPedido > cantidadAlbaran) {
                        claseFila = ' class="diff-cantidad-pedido-mayor"';
                    } else {
                        claseFila = ' class="diff-cantidad-albaran-mayor"';
                    }
                } else if (Math.abs(importePedido - importeAlbaran) > EPS) {
                    if (importePedido > importeAlbaran) {
                        claseFila = ' class="diff-importe-pedido-mayor"';
                    } else {
                        claseFila = ' class="diff-importe-albaran-mayor"';
                    }
                }

                filas += '<tr' + claseFila + '>' +
                    '<td>' + escapeHtml(item.cod_articulo || '') + '</td>' +
                    '<td>' + escapeHtml(item.descripcion || '') + '</td>' +
                    '<td>' + cantidadPedido.toFixed(2) + '</td>' +
                    '<td>' + importePedido.toFixed(2) + ' &euro;</td>' +
                    '<td>' + cantidadAlbaran.toFixed(2) + '</td>' +
                    '<td>' + importeAlbaran.toFixed(2) + ' &euro;</td>' +
                    '</tr>';
            });

            contenido.innerHTML =
                '<p><strong>Total Pedidos:</strong> ' + totalImpPedido.toFixed(2) + ' &euro; | ' +
                '<strong>Total Albaranes:</strong> ' + totalImpAlbaran.toFixed(2) + ' &euro;</p>' +
                '<div class="leyenda-diff">' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#ffe5e5;"></span>Cantidad: Pedido &gt; Albarán</span>' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#e7f7ea;"></span>Cantidad: Albarán &gt; Pedido</span>' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#fff6d6;"></span>Importe: Pedido &gt; Albarán (misma cantidad)</span>' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#e8f1ff;"></span>Importe: Albarán &gt; Pedido (misma cantidad)</span>' +
                '</div>' +
                '<div class="modal-table-container">' +
                '<table class="modal-table">' +
                '<thead><tr>' +
                '<th>Artículo</th><th>Descripción</th>' +
                '<th>Cant. Pedido</th><th>Importe Pedido</th>' +
                '<th>Cant. Albarán</th><th>Importe Albarán</th>' +
                '</tr></thead>' +
                '<tbody>' + filas +
                '<tr>' +
                '<td colspan="2"><strong>Totales</strong></td>' +
                '<td><strong>' + totalCantPedido.toFixed(2) + '</strong></td>' +
                '<td><strong>' + totalImpPedido.toFixed(2) + ' &euro;</strong></td>' +
                '<td><strong>' + totalCantAlbaran.toFixed(2) + '</strong></td>' +
                '<td><strong>' + totalImpAlbaran.toFixed(2) + ' &euro;</strong></td>' +
                '</tr>' +
                '</tbody>' +
                '</table>' +
                '</div>';

            abrirModal('modal-detalle-barras');
        }

        function obtenerAnosSeleccionados() {
            var selector = document.getElementById('yearsWindow');
            var yearsWindow = selector ? selector.value : '2';
            var years = [];

            if (!Array.isArray(datosMensuales)) return years;
            datosMensuales.forEach(function(item) {
                var p = String(item.periodo || '');
                var parts = p.split('-');
                if (parts.length !== 2) return;
                var anio = parseInt(parts[0], 10);
                if (!isNaN(anio) && years.indexOf(anio) === -1) {
                    years.push(anio);
                }
            });
            years.sort(function(a, b) { return a - b; });

            if (yearsWindow !== 'all') {
                var n = parseInt(yearsWindow, 10);
                if (!isNaN(n) && n > 0 && years.length > n) {
                    years = years.slice(years.length - n);
                }
            }
            return years;
        }

        // GRAFICO DE BARRAS COMPARATIVO
        function dibujarGraficoLineas() {
            if (!Array.isArray(datosMensuales) || datosMensuales.length === 0) return;
            var etiquetas = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            var yearsMap = {};

            datosMensuales.forEach(function(item) {
                var p = String(item.periodo || '');
                var parts = p.split('-');
                if (parts.length !== 2) return;
                var anio = parseInt(parts[0], 10);
                var mes = parseInt(parts[1], 10);
                if (isNaN(anio) || isNaN(mes) || mes < 1 || mes > 12) return;
                if (!yearsMap[anio]) {
                    yearsMap[anio] = { pedidos: Array(12).fill(0), albaranes: Array(12).fill(0) };
                }
                yearsMap[anio].pedidos[mes - 1] = Number(item.pedidos || 0);
                yearsMap[anio].albaranes[mes - 1] = Number(item.albaranes || 0);
            });

            var years = obtenerAnosSeleccionados();
            var paleta = [
                { pedidos: '#0B5FFF', albaranes: '#7FAAFF' },
                { pedidos: '#E85D04', albaranes: '#FFB380' },
                { pedidos: '#2B9348', albaranes: '#95D5B2' },
                { pedidos: '#6A4C93', albaranes: '#CDB4DB' },
                { pedidos: '#B02A37', albaranes: '#F1AEB5' }
            ];

            var datasets = [];
            years.forEach(function(anio, idx) {
                if (!yearsMap[anio]) return;
                var colores = paleta[idx % paleta.length];
                datasets.push({ label: anio + ' - Pedidos', data: yearsMap[anio].pedidos, backgroundColor: colores.pedidos });
                datasets.push({ label: anio + ' - Albaranes', data: yearsMap[anio].albaranes, backgroundColor: colores.albaranes });
            });

            var ctx = document.getElementById('graficoLineas').getContext('2d');
            if (graficoComparativa) {
                graficoComparativa.destroy();
            }
            graficoComparativa = new Chart(ctx, {
                type: 'bar',
                data: { labels: etiquetas, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { display: true, text: 'Comparativa mensual por año (Pedidos vs Albaranes)' },
                        datalabels: { display: false }
                    },
                    onClick: function(evt) {
                        var points = graficoComparativa.getElementsAtEventForMode(
                            evt,
                            'nearest',
                            { intersect: true },
                            true
                        );
                        if (!points || points.length === 0) return;

                        var p = points[0];
                        var dataset = graficoComparativa.data.datasets[p.datasetIndex];
                        var monthIndex = p.index;
                        var monthNum = String(monthIndex + 1).padStart(2, '0');
                        var monthName = graficoComparativa.data.labels[monthIndex];
                        var rawLabel = String(dataset.label || '');
                        var parts = rawLabel.split(' - ');
                        if (parts.length < 2) return;

                        var anio = parts[0].trim();
                        var keyMes = anio + '-' + monthNum;
                        var items = Array.isArray(detalleBarrasMap[keyMes]) ? detalleBarrasMap[keyMes] : [];

                        mostrarDetalleBarra(monthName + ' ' + anio, items);
                    },
                    scales: {
                        x: { title: { display: true, text: 'Mes' } },
                        y: { title: { display: true, text: 'Importe (EUR)' } }
                    }
                }
            });
        }

        // GRAFICO DE FAMILIA (pastel)
        function dibujarGraficoFamilia() {
            if (!Array.isArray(datosFamiliaMensual)) return;
            var years = obtenerAnosSeleccionados();
            var yearsSet = {};
            years.forEach(function(y) { yearsSet[y] = true; });

            var agrupado = {};
            datosFamiliaMensual.forEach(function(item) {
                var anio = parseInt(item.anio, 10);
                if (!yearsSet[anio]) return;
                var cod = String(item.cod_familia || '');
                if (!agrupado[cod]) {
                    agrupado[cod] = { familia: String(item.familia || cod), importe: 0, cod_familia: cod };
                }
                agrupado[cod].importe += Number(item.importe || 0);
            });

            var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; });
            var etiquetas = lista.map(function(x) { return x.familia; });
            var valores = lista.map(function(x) { return x.importe; });
            var bgColors = lista.map(function(x) { return colorFamilias[x.cod_familia] || '#CCCCCC'; });

            var ctx = document.getElementById('graficoFamilia').getContext('2d');
            if (graficoFamilia) {
                graficoFamilia.destroy();
            }
            graficoFamilia = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: etiquetas,
                    datasets: [{ data: valores, backgroundColor: bgColors }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Ventas por Familia' },
                        datalabels: {
                            formatter: function (value, context) {
                                var idx = context.dataIndex;
                                if (idx < 3) {
                                    var label = context.chart.data.labels[idx];
                                    return label + '\n' + value.toFixed(2) + ' \u20AC';
                                }
                                return '';
                            },
                            color: '#FFFFFF',
                            align: 'center',
                            anchor: 'center',
                            font: { weight: 'bold', size: 12 }
                        }
                    }
                }
            });
        }

        // GRAFICO DE MARCA (Top 10)
        function dibujarGraficoMarca() {
            if (!Array.isArray(datosMarcaMensual)) return;
            var years = obtenerAnosSeleccionados();
            var yearsSet = {};
            years.forEach(function(y) { yearsSet[y] = true; });

            var agrupado = {};
            datosMarcaMensual.forEach(function(item) {
                var anio = parseInt(item.anio, 10);
                if (!yearsSet[anio]) return;
                var marca = String(item.marca || 'SIN MARCA');
                if (!agrupado[marca]) {
                    agrupado[marca] = { marca: marca, importe: 0, familias: {} };
                }
                var importe = Number(item.importe || 0);
                var cod = String(item.cod_familia || '');
                agrupado[marca].importe += importe;
                agrupado[marca].familias[cod] = (agrupado[marca].familias[cod] || 0) + importe;
            });

            var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; }).slice(0, 10);
            var etiquetas = [];
            var valores = [];
            var bgColors = [];

            lista.forEach(function(item) {
                etiquetas.push(item.marca);
                valores.push(item.importe);
                var codDominante = '';
                var maxFam = -1;
                Object.keys(item.familias).forEach(function(cod) {
                    if (item.familias[cod] > maxFam) {
                        maxFam = item.familias[cod];
                        codDominante = cod;
                    }
                });
                bgColors.push(colorFamilias[codDominante] || '#CCCCCC');
            });

            var ctx = document.getElementById('graficoMarca').getContext('2d');
            if (graficoMarca) {
                graficoMarca.destroy();
            }
            graficoMarca = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: etiquetas,
                    datasets: [{ data: valores, backgroundColor: bgColors }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Marcas (Top 10)' },
                        datalabels: {
                            formatter: function (value, context) {
                                var idx = context.dataIndex;
                                if (idx < 3) {
                                    var label = context.chart.data.labels[idx];
                                    return label + '\n' + value.toFixed(2) + ' \u20AC';
                                }
                                return '';
                            },
                            color: '#FFFFFF',
                            align: 'center',
                            anchor: 'center',
                            font: { weight: 'bold', size: 12 }
                        }
                    }
                }
            });
        }

        function dibujarGraficoArticulos() {
            if (!Array.isArray(datosArticulosMensual)) return;
            var years = obtenerAnosSeleccionados();
            var yearsSet = {};
            years.forEach(function(y) { yearsSet[y] = true; });

            var agrupado = {};
            datosArticulosMensual.forEach(function(item) {
                var anio = parseInt(item.anio, 10);
                if (!yearsSet[anio]) return;
                var articulo = String(item.articulo || 'SIN ARTICULO');
                if (!agrupado[articulo]) {
                    agrupado[articulo] = { articulo: articulo, importe: 0, familias: {} };
                }
                var importe = Number(item.importe || 0);
                var cod = String(item.cod_familia || '');
                agrupado[articulo].importe += importe;
                agrupado[articulo].familias[cod] = (agrupado[articulo].familias[cod] || 0) + importe;
            });

            var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; }).slice(0, 10);
            var etiquetas = [];
            var valores = [];
            var bgColors = [];

            lista.forEach(function(item) {
                etiquetas.push(item.articulo);
                valores.push(item.importe);
                var codDominante = '';
                var maxFam = -1;
                Object.keys(item.familias).forEach(function(cod) {
                    if (item.familias[cod] > maxFam) {
                        maxFam = item.familias[cod];
                        codDominante = cod;
                    }
                });
                bgColors.push(colorFamilias[codDominante] || '#CCCCCC');
            });

            var ctx = document.getElementById('graficoArticulos').getContext('2d');
            if (graficoArticulos) {
                graficoArticulos.destroy();
            }
            graficoArticulos = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: etiquetas,
                    datasets: [{ data: valores, backgroundColor: bgColors }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Artículos (Top 10)' },
                        datalabels: {
                            formatter: function (value, context) {
                                var idx = context.dataIndex;
                                if (idx < 3) {
                                    var label = context.chart.data.labels[idx];
                                    var maxLen = 15;
                                    if (label.length > maxLen) {
                                        label = label.substring(0, maxLen) + '...';
                                    }
                                    return label + '\n' + value.toFixed(2) + ' \u20AC';
                                }
                                return '';
                            },
                            color: '#FFFFFF',
                            align: 'center',
                            anchor: 'center',
                            font: { weight: 'bold', size: 12 }
                        }
                    }
                }
            });
        }

        function calcularPromedioVisita(cod_cliente, cod_seccion) {
            var xhr = null;
            if (window.XMLHttpRequest) {
                xhr = new XMLHttpRequest();
            } else if (window.ActiveXObject) {
                xhr = new ActiveXObject("Microsoft.XMLHTTP");
            }
            if (xhr == null) {
                return;
            }

            var url = '<?= BASE_URL ?>/ajax/calcular_promedio_visita.php?cod_cliente=' + encodeURIComponent(cod_cliente) +
                      '&cod_seccion=' + encodeURIComponent(cod_seccion || '');
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var elem = document.getElementById("promedio_valor");
                    if (elem) {
                        elem.innerHTML = xhr.responseText;
                    }
                }
            };
            xhr.send(null);
        }

        window.onload = function() {
            asignarEventos();
            activarPaginacionVisitasAjax();
            var selector = document.getElementById('yearsWindow');
            if (selector) {
                selector.addEventListener('change', function() {
                    dibujarGraficoLineas();
                    dibujarGraficoFamilia();
                    dibujarGraficoMarca();
                    dibujarGraficoArticulos();
                });
            }
            dibujarGraficoLineas();
            dibujarGraficoFamilia();
            dibujarGraficoMarca();
            dibujarGraficoArticulos();
        };
    </script>
</head>
<body>
<?php include_once BASE_PATH . '/resources/views/layouts/header.php'; ?>
<div class="container">

    <div class="cliente-head">
        <div class="cliente-head-copy">
            <div class="cliente-eyebrow">Ficha de cliente</div>
            <h1><?= htmlspecialchars((string)$pageTitle) ?></h1>
            <p>Información comercial, contactos, secciones y actividad reciente del cliente.</p>
        </div>
        <div class="cliente-head-badges">
            <span class="cliente-badge">Código <?= htmlspecialchars((string)$cliente['cod_cliente']) ?></span>
            <?php if (!empty($cliente['cod_tarifa'])): ?>
                <span class="cliente-badge cliente-badge-soft">Tarifa <?= htmlspecialchars((string)$tarifa_nombre) ?></span>
            <?php endif; ?>
            <?php if (!empty($cliente['cif'])): ?>
                <span class="cliente-badge cliente-badge-soft"><?= htmlspecialchars((string)$cliente['cif']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($cliente['moroso'] === 'S'): ?>
        <p class="moroso">CLIENTE BLOQUEADO</p>
    <?php endif; ?>

    <section class="section-card">
    <h2>Datos del cliente</h2>
    <p class="section-intro">Resumen principal de la ficha comercial y de facturación.</p>
    <?php
        $telefonos = [];
        $mapaTelefonos = [
            ['numero' => 'telefono', 'comentario' => 'telefono1_comentario'],
            ['numero' => 'telefono2', 'comentario' => 'telefono2_comentario'],
            ['numero' => 'telefono3', 'comentario' => 'telefono3_comentario'],
        ];
        foreach ($mapaTelefonos as $telefonoConfig) {
            $numero = trim((string)($cliente[$telefonoConfig['numero']] ?? ''));
            if ($numero === '') {
                continue;
            }
            $comentarioTelefono = trim((string)($cliente[$telefonoConfig['comentario']] ?? ''));
            $numeroNormalizado = preg_replace('/\D+/', '', $numero) ?? '';
            $prefijoTelefono = $numeroNormalizado !== '' ? substr($numeroNormalizado, 0, 1) : '';
            $iconoTelefono = ($prefijoTelefono === '6' || $prefijoTelefono === '7') ? '&#128241;' : '&#9742;';
            $telefonos[] = [
                'numero' => htmlspecialchars($numero),
                'comentario' => $comentarioTelefono !== '' ? htmlspecialchars($comentarioTelefono) : '',
                'icono' => $iconoTelefono,
            ];
        }
        $telefonosHtml = 'Sin teléfonos';
        if ($telefonos !== []) {
            $telefonosHtml = '<div class="contact-lines">';
            foreach ($telefonos as $telefono) {
                $telefonosHtml .= '<div class="contact-line"><span class="contact-line-icon">' . $telefono['icono'] . '</span><div class="contact-line-body"><span>' . $telefono['numero'] . '</span>';
                if ($telefono['comentario'] !== '') {
                    $telefonosHtml .= '<span class="contact-line-comment">' . $telefono['comentario'] . '</span>';
                }
                $telefonosHtml .= '</div></div>';
            }
            $telefonosHtml .= '</div>';
        }
        $emailCliente = trim((string)($cliente['e_mail'] ?? ''));
        $poblacionCompleta = trim((string)($cliente['CP'] ?? '')) !== ''
            ? htmlspecialchars((string)$cliente['CP']) . ' - ' . htmlspecialchars((string)$cliente['poblacion'])
            : htmlspecialchars((string)$cliente['poblacion']);
        $direccionPartes = [];
        $direccion1 = trim((string)($cliente['direccion1'] ?? ''));
        if ($direccion1 !== '') {
            $direccionPartes[] = htmlspecialchars($direccion1);
        }
        if (trim((string)($cliente['poblacion'] ?? '')) !== '' || trim((string)($cliente['provincia'] ?? '')) !== '') {
            $ubicacion = $poblacionCompleta;
            if (trim((string)($cliente['provincia'] ?? '')) !== '') {
                $ubicacion .= ($ubicacion !== '' ? ' · ' : '') . htmlspecialchars((string)$cliente['provincia']);
            }
            if ($ubicacion !== '') {
                $direccionPartes[] = $ubicacion;
            }
        }
        $direccionCompleta = $direccionPartes !== [] ? implode('<br>', $direccionPartes) : 'Sin dirección';
        $documentoCliente = trim((string)($cliente['cif'] ?? ''));
        $documentoLabel = 'NIF';
        if ($documentoCliente !== '' && preg_match('/^[A-W][0-9]/i', $documentoCliente) === 1) {
            $documentoLabel = 'CIF';
        }
    ?>
    <div class="summary-grid">
        <div class="summary-item">
            <span class="summary-label">Código</span>
            <div class="summary-value"><?= htmlspecialchars((string)$cliente['cod_cliente']) ?></div>
        </div>
        <div class="summary-item">
            <span class="summary-label"><?= $documentoLabel ?></span>
            <div class="summary-value"><?= htmlspecialchars($documentoCliente) ?></div>
        </div>
        <div class="summary-item">
            <span class="summary-label">Tarifa</span>
            <div class="summary-value"><?= htmlspecialchars((string)$tarifa_nombre) ?></div>
        </div>
        <div class="summary-item">
            <span class="summary-label">Forma de pago</span>
            <div class="summary-value summary-value-soft"><?= htmlspecialchars((string)$forma_pago) ?></div>
        </div>
        <div class="summary-item summary-item-span-2">
            <span class="summary-label">Razón social</span>
            <div class="summary-value"><?= htmlspecialchars(toUTF8((string)$cliente['razon_social'])) ?></div>
        </div>
        <div class="summary-item summary-item-span-2">
            <span class="summary-label">Dirección</span>
            <div class="summary-value summary-value-soft"><?= $direccionCompleta ?></div>
        </div>
        <div class="summary-item summary-item-span-2">
            <span class="summary-label">Teléfonos</span>
            <div class="summary-value summary-value-soft"><?= $telefonosHtml ?></div>
        </div>
        <div class="summary-item summary-item-span-2">
            <span class="summary-label">Email</span>
            <div class="summary-value summary-value-soft">
                <?php if ($emailCliente !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($emailCliente) ?>"><?= htmlspecialchars($emailCliente) ?></a>
                <?php else: ?>
                    Sin email
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($cliente['advertencia'])): ?>
            <div class="summary-item summary-item-wide summary-alert">
                <span class="summary-label">Advertencia</span>
                <div class="summary-value summary-value-soft"><?= htmlspecialchars((string)$cliente['advertencia']) ?></div>
            </div>
        <?php endif; ?>
    </div>
    </section>

    <?php
    $campos = [
        'nombre'           => 'Nombre',
        'cargo'            => 'Cargo',
        'telefono'         => 'Teléfono',
        'telefono_movil'   => 'Móvil',
        'e_mail'           => 'Email',
        'observaciones'    => 'Observaciones'
    ];
    $camposConDatos = [];
    foreach ($campos as $campo => $titulo) {
        foreach ($contactos as $contacto) {
            if (!empty($contacto[$campo])) {
                $camposConDatos[$campo] = $titulo;
                break;
            }
        }
    }
    ?>
    <?php if (count($camposConDatos) > 0): ?>
        <section class="section-card">
        <h3>Contactos</h3>
        <p class="section-intro">Personas de referencia registradas dentro del cliente.</p>
        <div class="table-scroll">
        <table class="detail-table">
            <thead>
                <tr>
                    <?php foreach ($camposConDatos as $titulo): ?>
                        <th><?= htmlspecialchars($titulo) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contactos as $contacto): ?>
                    <tr>
                        <?php foreach ($camposConDatos as $campo => $titulo): ?>
                            <td>
                                <?php
                                $valor = toUTF8($contacto[$campo] ?? '');
                                if ($campo === 'telefono_movil' && !empty($valor)) {
                                    $numeroWhatsapp = preg_replace('/\D/', '', $valor);
                                    if (substr($numeroWhatsapp, 0, 2) !== "34") {
                                        $numeroWhatsapp = "34" . $numeroWhatsapp;
                                    }
                                    echo htmlspecialchars($valor) . ' ';
                                    echo '<a href="https://wa.me/' . htmlspecialchars($numeroWhatsapp) . '" target="_blank">';
                                    echo '<i class="fa-brands fa-whatsapp"></i>';
                                    echo '</a>';
                                } else {
                                    echo htmlspecialchars($valor);
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </section>
    <?php endif; ?>

    <?php if ($asignacion && count($secciones) <= 1): ?>
        <?php
        $nombreZonaPrincipal = '';
        if (!empty($asignacion['zona_principal'])) {
            $resZonaP = execPrepared(
                $conn,
                "SELECT nombre_zona FROM [integral].[dbo].[cmf_zonas_visita] WHERE cod_zona = ?",
                [(string)$asignacion['zona_principal']]
            );
            $zonaP = $resZonaP ? odbc_fetch_array($resZonaP) : false;
            $nombreZonaPrincipal = $zonaP ? (string)($zonaP['nombre_zona'] ?? '') : (string)$asignacion['zona_principal'];
        }

        $nombreZonaSecundaria = '';
        if (!empty($asignacion['zona_secundaria'])) {
            $resZonaS = execPrepared(
                $conn,
                "SELECT nombre_zona FROM [integral].[dbo].[cmf_zonas_visita] WHERE cod_zona = ?",
                [(string)$asignacion['zona_secundaria']]
            );
            $zonaS = $resZonaS ? odbc_fetch_array($resZonaS) : false;
            $nombreZonaSecundaria = $zonaS ? (string)($zonaS['nombre_zona'] ?? '') : (string)$asignacion['zona_secundaria'];
        }

        $freq = strtolower((string)($asignacion['frecuencia_visita'] ?? ''));
        switch ($freq) {
            case 'todos':
                $frecuenciaTexto = 'Todos los meses';
                break;
            case 'cada2':
                $frecuenciaTexto = 'Cada 2 meses';
                break;
            case 'cada3':
                $frecuenciaTexto = 'Cada 3 meses';
                break;
            case 'nunca':
                $frecuenciaTexto = 'No se visita normalmente';
                break;
            default:
                $frecuenciaTexto = htmlspecialchars((string)($asignacion['frecuencia_visita'] ?? ''));
                break;
        }

        $pref = strtolower((string)($asignacion['preferencia_horaria'] ?? ''));
        $prefiereManana = ($pref === 'm' || $pref === 'mañana' || $pref === 'manana');
        $prefiereTarde  = ($pref === 't' || $pref === 'tarde');

        $tp = (float)($asignacion['tiempo_promedio_visita'] ?? 0);
        $horas = floor($tp);
        $minutos = round(($tp - $horas) * 60);
        if ($horas == 0 && $minutos > 0) {
            $tiempoPromedioTexto = $minutos . ' minutos';
        } elseif ($horas > 0) {
            $tiempoPromedioTexto = $horas . ' horas ' . $minutos . ' minutos';
        } else {
            $tiempoPromedioTexto = '0 minutos';
        }

        $horaInicioManana = substr((string)($asignacion['hora_inicio_manana'] ?? ''), 0, 5);
        $horaFinManana = substr((string)($asignacion['hora_fin_manana'] ?? ''), 0, 5);
        $horaInicioTarde = substr((string)($asignacion['hora_inicio_tarde'] ?? ''), 0, 5);
        $horaFinTarde = substr((string)($asignacion['hora_fin_tarde'] ?? ''), 0, 5);
        $observacionesAsign = trim((string)($asignacion['observaciones'] ?? ''));
        ?>
        <section class="section-card">
        <h2>Planificaci&oacute;n comercial</h2>
        <p class="section-intro">Asignaci&oacute;n operativa de visita para este cliente.</p>
        <div class="planning-panel">
            <div class="planning-row">
                <div class="planning-block">
                    <span class="planning-block-label">Ritmo de visita</span>
                    <div class="planning-meta">
                        <span class="planning-meta-pill"><strong>Frecuencia</strong><?= htmlspecialchars($frecuenciaTexto) ?></span>
                        <span class="planning-meta-pill">
                            <strong>Preferencia</strong>
                            <?php if ($prefiereManana): ?>
                                Mañana
                            <?php elseif ($prefiereTarde): ?>
                                Tarde
                            <?php else: ?>
                                Sin preferencia
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="planning-inline-action">
                        <div class="planning-slots">
                            <span class="planning-slot <?= $prefiereManana ? 'is-active-morning' : '' ?>">
                                <strong>Mañana</strong>
                                <span><?= htmlspecialchars($horaInicioManana) ?> - <?= htmlspecialchars($horaFinManana) ?></span>
                            </span>
                            <span class="planning-slot <?= $prefiereTarde ? 'is-active-afternoon' : '' ?>">
                                <strong>Tarde</strong>
                                <span><?= htmlspecialchars($horaInicioTarde) ?> - <?= htmlspecialchars($horaFinTarde) ?></span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="planning-block">
                    <span class="planning-block-label">Tiempo promedio</span>
                    <div class="planning-kpi">
                        <div class="planning-kpi-value" id="promedio_valor"><?= htmlspecialchars($tiempoPromedioTexto) ?></div>
                        <div class="planning-kpi-note">Duraci&oacute;n media estimada de visita</div>
                    </div>
                    <div class="planning-inline-action">
                        <button type="button" class="planning-btn planning-btn-secondary" onclick="calcularPromedioVisita('<?= addslashes((string)$cod_cliente) ?>','<?= addslashes((string)($cod_seccion ?? '')) ?>')">Calcular</button>
                        <a href="visita_manual.php?cod_cliente=<?= urlencode((string)$cod_cliente) ?>&cod_seccion=<?= urlencode((string)($cod_seccion ?? '')) ?>" class="planning-btn planning-btn-primary">Registrar visita manual</a>
                    </div>
                </div>
            </div>
            <div class="planning-row">
                <div class="planning-block" style="grid-column: 1 / -1;">
                    <span class="planning-block-label">Zonas de visita</span>
                    <div class="planning-zones">
                        <span class="planning-zone-tag">Principal</span>
                        <span class="planning-zone-name"><?= htmlspecialchars($nombreZonaPrincipal) ?></span>
                        <?php if ($nombreZonaSecundaria !== ''): ?>
                            <span class="planning-inline-separator">/</span>
                            <span class="planning-zone-tag">Secundaria</span>
                            <span class="planning-zone-name"><?= htmlspecialchars($nombreZonaSecundaria) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($observacionesAsign !== ''): ?>
            <div class="planning-row">
                <div class="planning-block" style="grid-column: 1 / -1;">
                    <span class="planning-block-label">Observaciones</span>
                    <div class="planning-block-value planning-block-value-soft"><?= htmlspecialchars($observacionesAsign) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        </section>
    <?php endif; ?>

    <?php
    // Contactos: ver qué campos tienen algún dato
    $campos = [
        'nombre'           => 'Nombre',
        'cargo'            => 'Cargo',
        'telefono'         => 'Teléfono',
        'telefono_movil'   => 'Móvil',
        'e_mail'           => 'Email',
        'observaciones'    => 'Observaciones'
    ];
    $camposConDatos = [];
    foreach ($campos as $campo => $titulo) {
        foreach ($contactos as $contacto) {
            if (!empty($contacto[$campo])) {
                $camposConDatos[$campo] = $titulo;
                break;
            }
        }
    }
    ?>
    

    <!-- Secciones -->
    <?php if (count($secciones) > 0): ?>
        <section class="section-card">
        <h3>Secciones</h3>
        <p class="section-intro">Accesos r&aacute;pidos a faltas e hist&oacute;rico por secci&oacute;n.</p>
        <div class="table-scroll">
        <table class="detail-table">
            <tr>
                <th>Secci&oacute;n</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($secciones as $sec): ?>
                <tr>
                    <td>
                        <a class="section-link" href="seccion_detalles.php?cod_cliente=<?= urlencode($cod_cliente) ?>&cod_seccion=<?= urlencode($sec['cod_seccion']) ?>">
                            <?= htmlspecialchars((string)$sec['nombre']) ?>
                        </a>
                    </td>
                    <td>
                        <a href="faltas.php?origen=cliente_detalles.php&cod_cliente=<?= urlencode($cod_cliente) ?>&cod_seccion=<?= urlencode($sec['cod_seccion']) ?>" class="faltas-button">
                           Faltas
                        </a>
                        <a href="historico.php?cod_cliente=<?= urlencode($cod_cliente) ?>&cod_seccion=<?= urlencode($sec['cod_seccion']) ?>" class="historico-button">
                           Hist&oacute;rico de Ventas
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
        </section>
    <?php else: ?>
        <section class="section-card">
        <h3>Acciones</h3>
        <p class="section-intro">Accesos r&aacute;pidos para este cliente.</p>
        <div class="button-container">
            <a href="faltas.php?origen=cliente_detalles.php&cod_cliente=<?= urlencode($cod_cliente) ?>" class="faltas-button">
               Faltas
            </a>
            <a href="historico.php?cod_cliente=<?= urlencode($cod_cliente) ?>" class="historico-button">
               Hist&oacute;rico de Ventas
            </a>
        </div>
        </section>
    <?php endif; ?>


    <!-- GRAFICO COMPARATIVO MENSUAL -->
    <section class="section-card section-chart">
        <h2>Actividad comercial</h2>
        <p class="section-intro">Evoluci&oacute;n reciente y distribuci&oacute;n de ventas del cliente.</p>
        <div class="chart-toolbar">
            <label for="yearsWindow" style="font-weight:600;">A&ntilde;os:</label>
            <select id="yearsWindow" class="form-select form-select-sm" style="width:auto;">
                <option value="2" selected>Últimos 2</option>
                <option value="3">Últimos 3</option>
                <option value="4">Últimos 4</option>
                <option value="all">Todos</option>
            </select>
        </div>
        <div style="position:relative;height:420px;">
            <canvas id="graficoLineas"></canvas>
        </div>
        <div class="row chart-grid">
            <div class="col-12 col-md-4 chart-col">
                <h4 style="text-align:center;"><br></h4>
                <canvas id="graficoFamilia" width="300" height="300"></canvas>
            </div>

            <div class="col-12 col-md-4 chart-col">
                <h4 style="text-align:center;"><br></h4>
                <canvas id="graficoMarca" width="300" height="300"></canvas>
            </div>

            <div class="col-12 col-md-4 chart-col">
                <h4 style="text-align:center;"><br></h4>
                <canvas id="graficoArticulos" width="300" height="300"></canvas>
            </div>
        </div>
    </section>
    <div id="modal-detalle-barras" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modal-detalle-barras')">&times;</span>
            <h3 id="detalleBarrasTitulo">Detalle</h3>
            <div id="detalleBarrasContenido"></div>
        </div>
    </div>

    <div class="button-container">
        <a href="clientes.php" class="back-button">&larr; Volver a la lista de clientes</a>
    </div>

    <!-- VISITAS y PEDIDOS (funcionalidad extra, con modales) -->
    <?php if ($mostrar_nueva_funcionalidad): ?>
        <section class="section-card section-visitas">
        <div class="visitas-header">
            <div>
                <h2>Visitas del cliente</h2>
                <p class="section-intro">Histórico de visitas comerciales y pedidos asociados a cada una.</p>
            </div>
            <span class="cliente-badge cliente-badge-soft visitas-counter"><?= (int)$totalVisitas ?> visitas</span>
        </div>
        <div class="visitas-container">
            <?php if ($totalVisitas > 0): ?>
                <?php foreach ($visitasPaginadas as $visita): ?>
                    <div class="visita-item" style="background-color: <?= htmlspecialchars((string)$visita['color']) ?>;" data-id-visita="<?= htmlspecialchars((string)$visita['id_visita']) ?>">
                        <div class="visita-linea">
                            <span class="visita-fecha">
                                &#128197; <?= htmlspecialchars(date("d/m/Y", strtotime((string)$visita['fecha_visita']))) ?>
                                (<?= obtenerDiaSemana((string)$visita['fecha_visita']) ?>)
                            </span>
                            <span class="visita-horas">
                                &#9200; <?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_inicio_visita']))) ?>
                                - <?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_fin_visita']))) ?>
                            </span>
                            <span class="visita-importe">
                                &#128176; <?= number_format((float)$visita['importe_total'], 2, ',', '.') ?> &euro;
                            </span>
                            <span class="visita-lineas">
                                &#128221; <?= htmlspecialchars((string)$visita['numero_lineas_total']) ?>
                            </span>
                        </div>
                        <?php if (!empty($visita['observaciones'])): ?>
                            <br>
                            <span class="visita-observaciones">
                                &#9999; <?= htmlspecialchars((string)$visita['observaciones']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Modal de Visita -->
                    <div id="modal-visita-<?= htmlspecialchars((string)$visita['id_visita']) ?>" class="modal">
                        <div class="modal-content">
                            <span class="close" onclick="cerrarModal('modal-visita-<?= htmlspecialchars((string)$visita['id_visita']) ?>')">&times;</span>
                            <h3>Detalles de la visita <?= htmlspecialchars((string)$visita['id_visita']) ?></h3>
                            <div class="modal-summary">
                                <div class="modal-summary-item"><strong>Fecha</strong><?= htmlspecialchars(date("d/m/Y", strtotime((string)$visita['fecha_visita']))) ?> (<?= obtenerDiaSemana((string)$visita['fecha_visita']) ?>)</div>
                                <div class="modal-summary-item"><strong>Hora de inicio</strong><?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_inicio_visita']))) ?></div>
                                <div class="modal-summary-item"><strong>Hora de fin</strong><?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_fin_visita']))) ?></div>
                                <div class="modal-summary-item"><strong>Importe total</strong><?= number_format((float)$visita['importe_total'], 2, ',', '.') ?> &euro;</div>
                                <div class="modal-summary-item"><strong>Número de líneas</strong><?= htmlspecialchars((string)$visita['numero_lineas_total']) ?></div>
                            </div>
                            <?php if (!empty($visita['observaciones'])): ?>
                                <div class="modal-note"><?= htmlspecialchars((string)$visita['observaciones']) ?></div>
                            <?php endif; ?>
                            
                            <h4>L&iacute;neas de Pedidos Asociados</h4>
                            <?php
                            $sql_lineas_visita = "
                                SELECT 
                                    hl.cod_articulo,
                                    hl.descripcion,
                                    hl.precio AS precio,
                                    hl.cantidad,
                                    hl.dto1,
                                    hl.dto2,
                                    hl.importe,
                                    elv.cantidad AS cantidad_servida,
                                    hvc_dest.fecha_venta AS fecha_entrega
                                FROM [integral].[dbo].[hist_ventas_linea] hl
                                INNER JOIN [integral].[dbo].[cmf_visita_pedidos] vp
                                   ON hl.cod_venta = vp.cod_venta
                                INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc 
                                   ON hl.cod_venta = hvc.cod_venta
                                LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
                                   ON hl.cod_venta = elv.cod_venta_origen 
                                  AND hl.linea = elv.linea_origen
                                LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
                                   ON elv.cod_venta_destino = hvc_dest.cod_venta 
                                  AND elv.tipo_venta_destino = hvc_dest.tipo_venta
                                WHERE vp.id_visita = '" . sqlLiteral((string)$visita['id_visita']) . "'
                                  AND hl.tipo_venta = 1
                                ORDER BY hl.descripcion
                            ";
                            $result_lineas_visita = odbc_exec($conn, $sql_lineas_visita);
                            if ($result_lineas_visita):
                            ?>
                                <div class="modal-table-container">
                                    <table class="modal-table">
                                        <thead>
                                            <tr>
                                                <th>Art&iacute;culo</th>
                                                <th>Descripci&oacute;n</th>
                                                <th>Cantidad</th>
                                                <th>Cantidad Servida</th>
                                                <th>Precio (&euro;)</th>
                                                <th>Dto1 (%)</th>
                                                <th>Dto2 (%)</th>
                                                <th>Importe (&euro;)</th>
                                                <th>Fecha de Entrega</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $linea_ids = [];
                                            while ($linea = odbc_fetch_array($result_lineas_visita)):
                                                $unique_id = $linea['cod_articulo'].'-'.$linea['descripcion'].'-'.$linea['cantidad'];
                                                if (in_array($unique_id, $linea_ids)) continue;
                                                $linea_ids[] = $unique_id;
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string)$linea['cod_articulo']) ?></td>
                                                    <td class="descripcion-con-observacion">
                                                        <?= htmlspecialchars((string)$linea['descripcion']) ?>
                                                    </td>
                                                    <td><?= number_format((float)$linea['cantidad'], 2, ',', '.') ?></td>
                                                    <td style="<?= ((float)$linea['cantidad_servida'] != (float)$linea['cantidad']) ? 'color: red;' : '' ?>">
                                                        <?= number_format((float)$linea['cantidad_servida'], 2, ',', '.') ?>
                                                    </td>
                                                    <td><?= number_format((float)$linea['precio'], 2, ',', '.') . " &euro;" ?></td>
                                                    <td><?= ((float)$linea['dto1'] != 0) ? htmlspecialchars((string)$linea['dto1']) . " %" : "-" ?></td>
                                                    <td><?= ((float)$linea['dto2'] != 0) ? htmlspecialchars((string)$linea['dto2']) . " %" : "-" ?></td>
                                                    <td><?= number_format((float)$linea['importe'], 2, ',', '.') . " &euro;" ?></td>
                                                    <td><?= !empty($linea['fecha_entrega']) ? htmlspecialchars(date("d/m/Y", strtotime((string)$linea['fecha_entrega']))) : "-" ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No hay l&iacute;neas asociadas a esta visita.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pedidos Asociados -->
                    <?php if (isset($visita['pedidos']) && count($visita['pedidos']) > 0): ?>
                        <?php foreach ($visita['pedidos'] as $pedido): ?>
                            <div class="pedido-item" style="border-left: 8px solid <?= htmlspecialchars((string)determinarColorPedido($pedido['origen'])) ?>;" data-cod-pedido="<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>">
                                <div class="pedido-content">
                                    <div class="pedido-info">
                                        <div>
                                            <?= iconoDeOrigen((string)$pedido['origen']) ?>
                                            <?= htmlspecialchars((string)$pedido['cod_pedido']) ?>
                                            <?php if (((int)($pedido['pedido_eliminado'] ?? 0)) === 1): ?>
                                                <span class="label label-warning" style="margin-left:8px;">Eliminado</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong>&#128197;</strong> <?= htmlspecialchars(date("d/m/Y", strtotime((string)$pedido['fecha_venta']))) ?> (<?= obtenerDiaSemana((string)$pedido['fecha_venta']) ?>)
                                        </div>
                                        <div>
                                            <strong>&#9200;</strong> <?= htmlspecialchars(date("H:i", strtotime((string)$pedido['hora_venta']))) ?>
                                        </div>
                                        <div>
                                            <strong>&#128176;</strong> <?= number_format((float)$pedido['importe'], 2, ',', '.') . " &euro;" ?>
                                        </div>
                                        <div>
                                            <strong>&#128221;</strong> <?= htmlspecialchars((string)$pedido['numero_lineas']) ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($pedido['observacion_interna'])): ?>
                                        <div class="pedido-observaciones">
                                            &#9999; <?= htmlspecialchars((string)$pedido['observacion_interna']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Modal de Pedido -->
                            <div id="modal-pedido-<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>" class="modal">
                                <div class="modal-content">
                                    <span class="close" onclick="cerrarModal('modal-pedido-<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>')">&times;</span>
                                    <div class="pedido-actions">
                                        <button class="btn btn-circle btn-eliminar" title="Quitar pedido de la visita" onclick="quitarPedido('<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>', event)">
                                            <i class="fa fa-calendar-times"></i>
                                        </button>
                                        <?php 
                                        $origen_actual = strtolower((string)$pedido['origen']);
                                        $opciones = [
                                            'visita'    => 'btn-visita',
                                            'telefono'  => 'btn-telefono',
                                            'whatsapp'  => 'btn-whatsapp',
                                            'email'     => 'btn-email'
                                        ];
                                        foreach ($opciones as $opcion => $btn_class):
                                            $disabled = ($origen_actual === $opcion) ? 'disabled style="background-color: grey;"' : '';
                                        ?>
                                            <button class="btn btn-circle <?= $btn_class ?>" title="Cambiar origen a <?= ucfirst($opcion) ?>" onclick="actualizarOrigen('<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>', '<?= $opcion ?>', event)" <?= $disabled; ?>>
                                                <?php 
                                                $iconos = [
                                                    'visita'   => 'fa-solid fa-calendar',
                                                    'telefono' => 'fa-solid fa-phone',
                                                    'whatsapp' => 'fa-brands fa-whatsapp',
                                                    'email'    => 'fa-solid fa-envelope'
                                                ];
                                                echo '<i class="'.$iconos[$opcion].'"></i>';
                                                ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <h3>Detalles del pedido <?= htmlspecialchars((string)$pedido['cod_pedido']) ?></h3>
                                    <?php if (((int)($pedido['pedido_eliminado'] ?? 0)) === 1): ?>
                                        <p><span class="label label-warning">Pedido eliminado</span></p>
                                    <?php endif; ?>
                                    <div class="modal-summary">
                                        <div class="modal-summary-item"><strong>Fecha de venta</strong><?= htmlspecialchars(date("d/m/Y", strtotime((string)$pedido['fecha_venta']))) ?> (<?= obtenerDiaSemana((string)$pedido['fecha_venta']) ?>)</div>
                                        <div class="modal-summary-item"><strong>Hora de venta</strong><?= htmlspecialchars(date("H:i", strtotime((string)$pedido['hora_venta']))) ?></div>
                                        <div class="modal-summary-item"><strong>Importe</strong><?= number_format((float)$pedido['importe'], 2, ',', '.') . " &euro;" ?></div>
                                        <div class="modal-summary-item"><strong>Número de líneas</strong><?= htmlspecialchars((string)$pedido['numero_lineas']) ?></div>
                                    </div>
                                    <?php if (!empty($pedido['observacion_interna'])): ?>
                                        <div class="modal-note"><?= htmlspecialchars((string)$pedido['observacion_interna']) ?></div>
                                    <?php endif; ?>

                                    <h4>L&iacute;neas del Pedido</h4>
                                    <?php
                                    $pedidoEliminado = ((int)($pedido['pedido_eliminado'] ?? 0) === 1);
                                    if ($pedidoEliminado) {
                                        $sql_lineas_pedido = "
                                            SELECT 
                                                vle.cod_articulo,
                                                vle.descripcion,
                                                vle.precio,
                                                vle.cantidad,
                                                vle.dto1,
                                                vle.dto2,
                                                vle.importe,
                                                NULL AS cantidad_servida,
                                                NULL AS fecha_entrega
                                            FROM [integral].[dbo].[ventas_linea_elim] vle
                                            WHERE vle.cod_venta = '" . sqlLiteral((string)$pedido['cod_pedido']) . "'
                                              AND vle.tipo_venta = 1
                                            ORDER BY vle.descripcion
                                        ";
                                    } else {
                                        $sql_lineas_pedido = "
                                            SELECT 
                                                hl.cod_articulo,
                                                hl.descripcion,
                                                hl.precio AS precio,
                                                hl.cantidad,
                                                hl.dto1,
                                                hl.dto2,
                                                hl.importe,
                                                elv.cantidad AS cantidad_servida,
                                                hvc_dest.fecha_venta AS fecha_entrega
                                            FROM [integral].[dbo].[hist_ventas_linea] hl
                                            LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
                                               ON hl.cod_venta = elv.cod_venta_origen 
                                              AND hl.linea     = elv.linea_origen
                                            LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
                                               ON elv.cod_venta_destino = hvc_dest.cod_venta 
                                              AND elv.tipo_venta_destino = hvc_dest.tipo_venta
                                            WHERE hl.cod_venta = '" . sqlLiteral((string)$pedido['cod_pedido']) . "'
                                              AND hl.tipo_venta = 1
                                            ORDER BY hl.descripcion
                                        ";
                                    }
                                    $result_lineas_pedido = odbc_exec($conn, $sql_lineas_pedido);
                                    if ($result_lineas_pedido):
                                    ?>
                                        <div class="modal-table-container">
                                            <table class="modal-table">
                                                <thead>
                                                    <tr>
                                                        <th>Art&iacute;culo</th>
                                                        <th>Descripci&oacute;n</th>
                                                        <th>Cantidad</th>
                                                        <th>Cantidad Servida</th>
                                                        <th>Precio (&euro;)</th>
                                                        <th>Dto1 (%)</th>
                                                        <th>Dto2 (%)</th>
                                                        <th>Importe (&euro;)</th>
                                                        <th>Fecha de Entrega</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $linea_ids_pedido = [];
                                                    while ($linea = odbc_fetch_array($result_lineas_pedido)):
                                                        $unique_id_pedido = $linea['cod_articulo'].'-'.$linea['descripcion'].'-'.$linea['cantidad'];
                                                        if (in_array($unique_id_pedido, $linea_ids_pedido)) continue;
                                                        $linea_ids_pedido[] = $unique_id_pedido;
                                                    ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string)$linea['cod_articulo']) ?></td>
                                                            <td class="descripcion-con-observacion">
                                                                <?= htmlspecialchars((string)$linea['descripcion']) ?>
                                                            </td>
                                                            <td><?= number_format((float)$linea['cantidad'], 2, ',', '.') ?></td>
                                                            <td style="<?= (!$pedidoEliminado && (float)$linea['cantidad_servida'] != (float)$linea['cantidad']) ? 'color: red;' : '' ?>">
                                                                <?= $pedidoEliminado ? '-' : number_format((float)$linea['cantidad_servida'], 2, ',', '.') ?>
                                                            </td>
                                                            <td><?= number_format((float)$linea['precio'], 2, ',', '.') . " &euro;" ?></td>
                                                            <td><?= ((float)$linea['dto1'] != 0) ? htmlspecialchars((string)$linea['dto1']) . " %" : "-" ?></td>
                                                            <td><?= ((float)$linea['dto2'] != 0) ? htmlspecialchars((string)$linea['dto2']) . " %" : "-" ?></td>
                                                            <td><?= number_format((float)$linea['importe'], 2, ',', '.') . " &euro;" ?></td>
                                                            <td><?= !$pedidoEliminado && !empty($linea['fecha_entrega']) ? htmlspecialchars(date("d/m/Y", strtotime((string)$linea['fecha_entrega']))) : "-" ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p>No hay l&iacute;neas asociadas a este pedido.</p>
                                    <?php endif; ?>
                                    <?php if ($pedidoEliminado): ?>
                                        <p style="margin-top:10px;color:#b91c1c;font-weight:600;">
                                            VENTA ELIMINADA
                                            <?= !empty($pedido['eliminado_por_usuario']) ? ' POR ' . htmlspecialchars((string)$pedido['eliminado_por_usuario']) : '' ?>
                                            <?= !empty($pedido['eliminado_por_equipo']) ? ' | EQUIPO: ' . htmlspecialchars((string)$pedido['eliminado_por_equipo']) : '' ?>
                                            <?= !empty($pedido['eliminado_fecha']) ? ' | FECHA: ' . htmlspecialchars(date("d/m/Y", strtotime((string)$pedido['eliminado_fecha']))) . ' (' . obtenerDiaSemana((string)$pedido['eliminado_fecha']) . ')' : '' ?>
                                            <?= !empty($pedido['eliminado_hora']) ? ' | HORA: ' . htmlspecialchars(date("H:i", strtotime((string)$pedido['eliminado_hora']))) : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($totalPaginasVisitas > 1): ?>
                    <?php
                    $queryVisitas = $_GET;
                    unset($queryVisitas['pag_visitas']);
                    $baseVisitas = basename((string)($_SERVER['PHP_SELF'] ?? 'cliente_detalles.php'));
                    ?>
                    <div class="page-nav">
                        <?php if ($paginaVisitas > 1): ?>
                            <a class="back-button" style="margin-right:8px;" href="<?= htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, ['pag_visitas' => $paginaVisitas - 1]))) ?>">&larr; Anterior</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPaginasVisitas; $p++): ?>
                            <?php if ($p === $paginaVisitas): ?>
                                <span class="back-button" style="background:#6c757d;cursor:default;margin-right:8px;"><?= $p ?></span>
                            <?php else: ?>
                                <a class="back-button" style="margin-right:8px;" href="<?= htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, ['pag_visitas' => $p]))) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($paginaVisitas < $totalPaginasVisitas): ?>
                            <a class="back-button" href="<?= htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, ['pag_visitas' => $paginaVisitas + 1]))) ?>">Siguiente &rarr;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="button-container">
                    <a href="clientes.php" class="back-button">&larr; Volver a la lista de clientes</a>
                </div>
            <?php else: ?>
                <p class="empty-state">No hay visitas registradas para este cliente.</p>
            <?php endif; ?>
        </div>
        </section>
    <?php endif; ?>
</div>

<!-- Bootstrap 5 JS Bundle (includes Popper, local via Composer assets) -->
<script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
</body>
</html>





