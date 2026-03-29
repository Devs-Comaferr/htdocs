<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
// Cabecera para que el navegador use UTF-8
header("Content-Type: text/html; charset=utf-8");

// Abrir la conexión ODBC
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

/**
 * Escapar comillas simples para consultas ODBC.
 */
function odbc_escape($str) {
    return str_replace("'", "''", $str);
}

/**
 * Formatear importes con 2 decimales.
 */
function format_importe($importe) {
    return number_format($importe, 2, ',', '.');
}

/**
 * Convertir los datos a UTF-8 (asumiendo CP1252).
 */
function convertRowToUtf8($row) {
    foreach ($row as $key => $value) {
        if (is_string($value)) {
            $row[$key] = iconv('CP1252', 'UTF-8', $value);
        }
    }
    return $row;
}

// Parámetros de fecha
$start_date_iso  = isset($_GET['start_date']) ? $_GET['start_date'] : "";
$end_date_iso    = isset($_GET['end_date'])   ? $_GET['end_date']   : "";
$start_date_full = $start_date_iso . " 00:00:00";
$end_date_full   = $end_date_iso . " 23:59:59";

// Nivel de drilldown (1, 2, 3 o 4)
$level = isset($_GET['level']) ? intval($_GET['level']) : 0;

if ($level == 1) {
    // ------------------------------------------------------------
    // NIVEL 1: Provincias (para un comisionista)
    // ------------------------------------------------------------
    if (!isset($_GET['comercial'])) {
        echo "Parámetros insuficientes para nivel 1.";
        exit;
    }
    $comercial = $_GET['comercial'];

    $where = "WHERE h.fecha_venta >= CONVERT(smalldatetime, '$start_date_full', 120)
              AND h.fecha_venta <= CONVERT(smalldatetime, '$end_date_full', 120)
              AND h.cod_comisionista = '" . odbc_escape($comercial) . "'
              AND h.tipo_venta IN (1,2)";

    $query = "SELECT 
                COALESCE(sc.provincia, c.provincia, 'Sin Provincia') AS provincia,
                SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
                SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes
              FROM hist_ventas_cabecera h
              JOIN clientes c ON h.cod_cliente = c.cod_cliente
              LEFT JOIN secciones_cliente sc 
                ON h.cod_cliente = sc.cod_cliente AND h.cod_seccion = sc.cod_seccion
              $where
              GROUP BY COALESCE(sc.provincia, c.provincia, 'Sin Provincia')
              ORDER BY pedidos DESC";
    $res = odbc_exec($conn, $query);
    if (!$res) {
        error_log("Error en nivel 1: " . odbc_errormsg($conn));
        echo 'Error interno';
        return;
    }

    $output = "<div class='table-responsive'>
                 <table class='table table-striped table-bordered table-hover'>
                   <thead class='table-dark'>
                     <tr>
                       <th>Provincia</th>
                       <th>Pedidos</th>
                       <th>Albaranes</th>
                       <th>% Servicio</th>
                     </tr>
                   </thead>
                   <tbody>";
    while ($row = odbc_fetch_array($res)) {
        $row = convertRowToUtf8($row);
        $provincia = htmlspecialchars($row['provincia'], ENT_QUOTES, 'UTF-8');
        $ped = $row['pedidos'];
        $alb = $row['albaranes'];
        $percentage = ($ped > 0) ? ($alb / $ped * 100) : 0;
        $stylePed = ($ped > $alb) ? "background-color: lightcoral;" : "";
        $styleAlb = ($alb > $ped) ? "background-color: lightgreen;" : "";

        $url = "drilldown.php?level=2&comercial=" . urlencode($comercial ?? '') .
               "&provincia=" . urlencode($provincia ?? '') .
               "&start_date=" . urlencode($start_date_iso ?? '') .
               "&end_date=" . urlencode($end_date_iso ?? '');
        $output .= "<tr>
                      <td>
                        <a onclick=\"toggleDetails('details_" . $comercial . "_" . urlencode($provincia ?? '') . "', '" . $url . "', this);\"
                           class='btn btn-sm btn-outline-secondary me-2'>[+]</a>
                        $provincia
                      </td>
                      <td style='$stylePed'>" . format_importe($ped) . " </td>
                      <td style='$styleAlb'>" . format_importe($alb) . " </td>
                      <td>" . number_format($percentage, 2, ',', '.') . " %</td>
                    </tr>
                    <tr id='details_" . $comercial . "_" . urlencode($provincia ?? '') . "' style='display:none;'><td colspan='4'></td></tr>";
    }
    $output .= "</tbody></table></div>";
    echo $output;

} elseif ($level == 2) {
    // ------------------------------------------------------------
    // NIVEL 2: Clientes (para la provincia seleccionada)
    // ------------------------------------------------------------
    if (!isset($_GET['comercial']) || !isset($_GET['provincia'])) {
        echo "Parámetros insuficientes para nivel 2.";
        exit;
    }
    $comercial = $_GET['comercial'];
    $provinciaSel = $_GET['provincia'];

    $whereBase = "WHERE h.fecha_venta >= CONVERT(smalldatetime, '$start_date_full', 120)
                  AND h.fecha_venta <= CONVERT(smalldatetime, '$end_date_full', 120)
                  AND h.cod_comisionista = '" . odbc_escape($comercial) . "'
                  AND h.tipo_venta IN (1,2)";
    $whereProv = "AND COALESCE(sc.provincia, c.provincia, 'Sin Provincia') = '" . odbc_escape($provinciaSel) . "'";
    $where = "$whereBase $whereProv";

    $query = "SELECT 
                h.cod_cliente,
                h.cod_seccion,
                CASE 
                  WHEN sc.nombre IS NOT NULL AND sc.nombre <> ''
                    THEN c.nombre_comercial + ' - ' + sc.nombre
                  WHEN h.cod_seccion IS NOT NULL AND h.cod_seccion <> ''
                    THEN c.nombre_comercial + ' - ' + CAST(h.cod_seccion AS VARCHAR(10))
                  ELSE c.nombre_comercial
                END AS cliente,
                SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
                SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes
              FROM hist_ventas_cabecera h
              LEFT JOIN clientes c ON h.cod_cliente = c.cod_cliente
              LEFT JOIN secciones_cliente sc ON h.cod_cliente = sc.cod_cliente 
                                             AND h.cod_seccion = sc.cod_seccion
              $where
              GROUP BY h.cod_cliente, h.cod_seccion, c.nombre_comercial, sc.nombre
              ORDER BY pedidos DESC";
    $res = odbc_exec($conn, $query);
    if (!$res) {
        error_log("Error en nivel 2: " . odbc_errormsg($conn));
        echo 'Error interno';
        return;
    }

    $output = "<div class='table-responsive'>
                 <table class='table table-striped table-bordered table-hover'>
                   <thead class='table-dark'>
                     <tr>
                       <th>Mostrar datos por:</th>
                        <th>Cliente - Sección</th>
                       <th>Pedidos</th>
                       <th>Albaranes</th>
                       <th>% Servicio</th>
                     </tr>
                   </thead>
                   <tbody>";
    while ($row = odbc_fetch_array($res)) {
        $row = convertRowToUtf8($row);
        $cod_cliente = $row['cod_cliente'];
        $cod_seccion = $row['cod_seccion'];
        $clienteStr = htmlspecialchars($row['cliente'], ENT_QUOTES, 'UTF-8');
        $ped = $row['pedidos'];
        $alb = $row['albaranes'];
        $percentage = ($ped > 0) ? ($alb / $ped * 100) : 0;
        $stylePed = ($ped > $alb) ? "background-color: lightcoral;" : "";
        $styleAlb = ($alb > $ped) ? "background-color: lightgreen;" : "";

        $urlBase = "drilldown.php?level=3&cod_cliente=" . urlencode($cod_cliente ?? '') .
                   "&cod_seccion=" . urlencode($cod_seccion ?? '') .
                   "&start_date=" . urlencode($start_date_iso ?? '') .
                   "&end_date=" . urlencode($end_date_iso ?? '');
        $linkMarca = "<a onclick=\"toggleGroupDetails('$cod_cliente','$cod_seccion','marca','$urlBase&group=marca',this);\" 
                         class='btn btn-sm btn-outline-secondary me-1'>Marca</a>";
        $linkFamilia = "<a onclick=\"toggleGroupDetails('$cod_cliente','$cod_seccion','familia','$urlBase&group=familia',this);\" 
                           class='btn btn-sm btn-outline-secondary me-1'>Familia</a>";
        $linkProducto = "<a onclick=\"toggleGroupDetails('$cod_cliente','$cod_seccion','producto','$urlBase&group=producto',this);\" 
                           class='btn btn-sm btn-outline-secondary me-1'>Producto</a>";

        $output .= "<tr>
                      <td>$linkMarca $linkFamilia $linkProducto</td>
                      <td>$clienteStr</td>
                      <td style='$stylePed'>" . format_importe($ped) . " </td>
                      <td style='$styleAlb'>" . format_importe($alb) . " </td>
                      <td>" . number_format($percentage, 2, ',', '.') . " %</td>
                    </tr>";
        $output .= "<tr id='details_client_" . urlencode($cod_cliente ?? '') . "_" . urlencode($cod_seccion ?? '') . "_marca' style='display:none;'><td colspan='5'></td></tr>";
        $output .= "<tr id='details_client_" . urlencode($cod_cliente ?? '') . "_" . urlencode($cod_seccion ?? '') . "_familia' style='display:none;'><td colspan='5'></td></tr>";
        $output .= "<tr id='details_client_" . urlencode($cod_cliente ?? '') . "_" . urlencode($cod_seccion ?? '') . "_producto' style='display:none;'><td colspan='5'></td></tr>";
    }
    $output .= "</tbody></table></div>";
    echo $output;

} elseif ($level == 3) {
    // ------------------------------------------------------------
    // NIVEL 3: Detalle por grupo (Marca, Familia o Producto) para un cliente
    // ------------------------------------------------------------
    if (!isset($_GET['cod_cliente'])) {
        echo "Parámetro insuficiente para nivel 3.";
        exit;
    }
    $cliente = $_GET['cod_cliente'];
    $cod_seccion = isset($_GET['cod_seccion']) ? $_GET['cod_seccion'] : "";
    $group = isset($_GET['group']) ? $_GET['group'] : "";
    if ($group === "") {
        echo "Parámetro de agrupación no especificado.";
        exit;
    }
    $where = "WHERE hc.fecha_venta >= CONVERT(smalldatetime, '$start_date_full', 120)
              AND hc.fecha_venta <= CONVERT(smalldatetime, '$end_date_full', 120)
              AND hc.cod_cliente = '" . odbc_escape($cliente) . "'
              AND h.tipo_venta = hc.tipo_venta
              AND h.tipo_venta IN (1,2)";
    // FIX cod_seccion: 0 es valor válido, no usar empty()
    if (tieneValor($cod_seccion)) {
        $where .= " AND hc.cod_seccion = '" . odbc_escape($cod_seccion) . "'";
    }

    if ($group == "marca") {
        $query = "SELECT 
                    a.cod_marca_web,
                    ISNULL(wm.descripcion, 'Sin Marca') AS marca,
                    SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
                    SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes
                  FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
                  JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta AND h.tipo_venta = hc.tipo_venta
                  LEFT JOIN [integral].[dbo].[web_marcas] wm ON a.cod_marca_web = wm.cod_marca
                  $where
                  GROUP BY a.cod_marca_web, ISNULL(wm.descripcion, 'Sin Marca')
                  ORDER BY pedidos DESC";
    } elseif ($group == "familia") {
        $query = "SELECT
                    a.cod_familia,
                    f.descripcion AS descripcion,
                    SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
                    SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes
                  FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
                  JOIN [integral].[dbo].[familias] f ON a.cod_familia = f.cod_familia
                  JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta AND h.tipo_venta = hc.tipo_venta
                  $where
                  GROUP BY a.cod_familia, f.descripcion
                  ORDER BY pedidos DESC";
    } elseif ($group == "producto") {
        // Para el grupo "producto" se usa una consulta UNION ALL:
        // - Para el Artículo LM se agrupa por cod_articulo y h.descripcion.
        // - Para el resto se agrupa solo por cod_articulo.
        $query = "(
                    SELECT 
                      a.cod_articulo,
                      h.descripcion AS producto,
                      SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
                      SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes,
                      SUM(CASE WHEN h.tipo_venta = 1 THEN h.cantidad ELSE 0 END) AS cantidad_pedidos,
                      SUM(CASE WHEN h.tipo_venta = 2 THEN h.cantidad ELSE 0 END) AS cantidad_albaranes
                    FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
                    JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta AND h.tipo_venta = hc.tipo_venta
                    $where AND a.cod_articulo = 'LM'
                    GROUP BY a.cod_articulo, h.descripcion
                  )
                  UNION ALL
                  (
                    SELECT 
                      a.cod_articulo,
                      MAX(h.descripcion) AS producto,
                      SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
                      SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes,
                      SUM(CASE WHEN h.tipo_venta = 1 THEN h.cantidad ELSE 0 END) AS cantidad_pedidos,
                      SUM(CASE WHEN h.tipo_venta = 2 THEN h.cantidad ELSE 0 END) AS cantidad_albaranes
                    FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
                    JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta AND h.tipo_venta = hc.tipo_venta
                    $where AND a.cod_articulo <> 'LM'
                    GROUP BY a.cod_articulo
                  )
                  ORDER BY pedidos DESC";
    } else {
        echo "Parámetro de agrupación no válido.";
        exit;
    }
    $res = odbc_exec($conn, $query);
    if (!$res) {
        error_log("Error en nivel 3: " . odbc_errormsg($conn));
        echo 'Error interno';
        return;
    }

    $output = "<div class='mt-3 table-responsive'>";
    if ($group == "marca") {
        $output .= "<table class='table table-striped table-bordered table-hover'>
                      <thead class='table-dark'>
                        <tr>
                          <th>Marca</th>
                          <th>Pedidos</th>
                          <th>Albaranes</th>
                          <th>% Servicio</th>
                        </tr>
                      </thead>
                      <tbody>";
        while ($row = odbc_fetch_array($res)) {
            $row = convertRowToUtf8($row);
            $cod_marca = $row['cod_marca_web'];
            $marcaDesc = htmlspecialchars($row['marca'], ENT_QUOTES, 'UTF-8');
            $ped = $row['pedidos'];
            $alb = $row['albaranes'];
            $percentage = ($ped > 0) ? ($alb / $ped * 100) : 0;
            $stylePed = ($ped > $alb) ? "background-color: lightcoral;" : "";
            $styleAlb = ($alb > $ped) ? "background-color: lightgreen;" : "";
            $url = "drilldown.php?level=4&cod_cliente=" . urlencode($cliente ?? '') .
                   "&marca=" . urlencode($cod_marca ?? '') .
                   "&cod_seccion=" . urlencode($cod_seccion ?? '') .
                   "&start_date=" . urlencode($start_date_iso ?? '') .
                   "&end_date=" . urlencode($end_date_iso ?? '');
            $output .= "<tr>
                          <td>
                            <a onclick=\"toggleDetails('details_" . $cliente . "_marca_" . urlencode($cod_marca ?? '') . "', '" . $url . "', this);\"
                               class='btn btn-sm btn-outline-secondary me-2'>[+]</a>
                            $marcaDesc
                          </td>
                          <td style='$stylePed'>" . format_importe($ped) . " </td>
                          <td style='$styleAlb'>" . format_importe($alb) . " </td>
                          <td>" . number_format($percentage, 2, ',', '.') . " %</td>
                        </tr>
                        <tr id='details_" . $cliente . "_marca_" . urlencode($cod_marca ?? '') . "' style='display:none;'><td colspan='4'></td></tr>";
        }
        $output .= "</tbody></table>";
    } elseif ($group == "familia") {
        $output .= "<table class='table table-striped table-bordered table-hover'>
                      <thead class='table-dark'>
                        <tr>
                          <th>Familia</th>
                          <th>Descripción</th>
                          <th>Pedidos</th>
                          <th>Albaranes</th>
                          <th>% Servicio</th>
                        </tr>
                      </thead>
                      <tbody>";
        while ($row = odbc_fetch_array($res)) {
            $row = convertRowToUtf8($row);
            $familia = htmlspecialchars($row['cod_familia'], ENT_QUOTES, 'UTF-8');
            $descripcion = htmlspecialchars($row['descripcion'], ENT_QUOTES, 'UTF-8');
            $ped = $row['pedidos'];
            $alb = $row['albaranes'];
            $percentage = ($ped > 0) ? ($alb / $ped * 100) : 0;
            $stylePed = ($ped > $alb) ? "background-color: lightcoral;" : "";
            $styleAlb = ($alb > $ped) ? "background-color: lightgreen;" : "";
            $url = "drilldown.php?level=4&cod_cliente=" . urlencode($cliente ?? '') .
                   "&cod_familia=" . urlencode($familia ?? '') .
                   "&cod_seccion=" . urlencode($cod_seccion ?? '') .
                   "&start_date=" . urlencode($start_date_iso ?? '') .
                   "&end_date=" . urlencode($end_date_iso ?? '');
            $output .= "<tr>
                          <td>
                            <a onclick=\"toggleDetails('details_" . $cliente . "_familia_" . urlencode($familia ?? '') . "', '" . $url . "', this);\"
                               class='btn btn-sm btn-outline-secondary me-2'>[+]</a>
                            $familia
                          </td>
                          <td>$descripcion</td>
                          <td style='$stylePed'>" . format_importe($ped) . " </td>
                          <td style='$styleAlb'>" . format_importe($alb) . " </td>
                          <td>" . number_format($percentage, 2, ',', '.') . " %</td>
                        </tr>
                        <tr id='details_" . $cliente . "_familia_" . urlencode($familia ?? '') . "' style='display:none;'><td colspan='6'></td></tr>";
        }
        $output .= "</tbody></table>";
    } elseif ($group == "producto") {
        $output .= "<table class='table table-striped table-bordered table-hover'>
                      <thead class='table-dark'>
                        <tr>
                          <th>Producto</th>
                          <th>Descripción</th>
                          <th>Pedidos</th>
                          <th>Albaranes</th>
                          <th>% Servicio</th>
                        </tr>
                      </thead>
                      <tbody>";
        while ($row = odbc_fetch_array($res)) {
            $row = convertRowToUtf8($row);
            $codArticulo = htmlspecialchars($row['cod_articulo'], ENT_QUOTES, 'UTF-8');
            $producto = htmlspecialchars($row['producto'], ENT_QUOTES, 'UTF-8');
            $ped = $row['pedidos'];
            $alb = $row['albaranes'];
            $cantPed = $row['cantidad_pedidos'];
            $cantAlb = $row['cantidad_albaranes'];
            $percentage = ($ped > 0) ? ($alb / $ped * 100) : 0;
            $stylePed = ($ped > $alb) ? "background-color: lightcoral;" : "";
            $styleAlb = ($alb > $ped) ? "background-color: lightgreen;" : "";
            $output .= "<tr>
                          <td>$codArticulo</td>
                          <td>$producto</td>
                          <td style='$stylePed'>" . format_importe($ped) . "  (" . format_importe($cantPed) . ")</td>
                          <td style='$styleAlb'>" . format_importe($alb) . "  (" . format_importe($cantAlb) . ")</td>
                          <td>" . number_format($percentage, 2, ',', '.') . " %</td>
                        </tr>";
        }
        $output .= "</tbody></table>";
    }
    $output .= "</div>";
    echo $output;

} elseif ($level == 4) {
    // ------------------------------------------------------------
    // NIVEL 4: Detalle final para Marca o Familia (detalle de artículos)
    // ------------------------------------------------------------
    if (!isset($_GET['cod_cliente'])) {
        echo "Falta indicar parámetros para nivel 4.";
        exit;
    }
    $cliente = $_GET['cod_cliente'];
    $cod_seccion = isset($_GET['cod_seccion']) ? $_GET['cod_seccion'] : "";

    if (isset($_GET['marca']) && !isset($_GET['cod_familia'])) {
        $marca = $_GET['marca'];
        if ($marca == "") {
            $marcaCondition = "(a.cod_marca_web IS NULL OR a.cod_marca_web = '')";
        } else {
            $marcaCondition = "a.cod_marca_web = '" . odbc_escape($marca) . "'";
        }
        $where = "WHERE hc.fecha_venta >= CONVERT(smalldatetime, '$start_date_full', 120)
                  AND hc.fecha_venta <= CONVERT(smalldatetime, '$end_date_full', 120)
                  AND hc.cod_cliente = '" . odbc_escape($cliente) . "'
                  AND h.tipo_venta = hc.tipo_venta
                  AND $marcaCondition
                  AND h.tipo_venta IN (1,2)";
        // FIX cod_seccion: 0 es valor válido, no usar empty()
        if (tieneValor($cod_seccion)) {
            $where .= " AND hc.cod_seccion = '" . odbc_escape($cod_seccion) . "'";
        }
        $query = "(
          SELECT 
            a.cod_articulo,
            h.descripcion AS producto,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.cantidad ELSE 0 END) AS cantidad_pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.cantidad ELSE 0 END) AS cantidad_albaranes
          FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
          JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta AND h.tipo_venta = hc.tipo_venta
          LEFT JOIN [integral].[dbo].[web_marcas] wm ON a.cod_marca_web = wm.cod_marca
          $where AND a.cod_articulo = 'LM'
          GROUP BY a.cod_articulo, h.descripcion
      )
      UNION ALL
      (
          SELECT 
            a.cod_articulo,
            MAX(h.descripcion) AS producto,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.cantidad ELSE 0 END) AS cantidad_pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.cantidad ELSE 0 END) AS cantidad_albaranes
          FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
          JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta AND h.tipo_venta = hc.tipo_venta
          LEFT JOIN [integral].[dbo].[web_marcas] wm ON a.cod_marca_web = wm.cod_marca
          $where AND a.cod_articulo <> 'LM'
          GROUP BY a.cod_articulo
      )
      ORDER BY pedidos DESC";
      
    } elseif (isset($_GET['cod_familia'])) {
        $familia = $_GET['cod_familia'];
        $where = "WHERE hc.fecha_venta >= CONVERT(smalldatetime, '$start_date_full', 120)
                  AND hc.fecha_venta <= CONVERT(smalldatetime, '$end_date_full', 120)
                  AND hc.cod_cliente = '" . odbc_escape($cliente) . "'
                  AND h.tipo_venta = hc.tipo_venta
                  AND a.cod_familia = '" . odbc_escape($familia) . "'
                  AND h.tipo_venta IN (1,2)";
        // FIX cod_seccion: 0 es valor válido, no usar empty()
        if (tieneValor($cod_seccion)) {
            $where .= " AND hc.cod_seccion = '" . odbc_escape($cod_seccion) . "'";
        }
        $query = "(
          SELECT 
            a.cod_articulo,
            h.descripcion AS producto,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.cantidad ELSE 0 END) AS cantidad_pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.cantidad ELSE 0 END) AS cantidad_albaranes
          FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
          JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta 
             AND h.tipo_venta = hc.tipo_venta
          $where AND a.cod_articulo = 'LM'
          GROUP BY a.cod_articulo, h.descripcion
      )
      UNION ALL
      (
          SELECT 
            a.cod_articulo,
            MAX(h.descripcion) AS producto,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.importe ELSE 0 END) AS pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.importe ELSE 0 END) AS albaranes,
            SUM(CASE WHEN h.tipo_venta = 1 THEN h.cantidad ELSE 0 END) AS cantidad_pedidos,
            SUM(CASE WHEN h.tipo_venta = 2 THEN h.cantidad ELSE 0 END) AS cantidad_albaranes
          FROM hist_ventas_linea h
JOIN articulos a ON h.cod_articulo = a.cod_articulo
          JOIN hist_ventas_cabecera hc ON h.cod_venta = hc.cod_venta 
             AND h.tipo_venta = hc.tipo_venta
          $where AND a.cod_articulo <> 'LM'
          GROUP BY a.cod_articulo
      )
      ORDER BY pedidos DESC";
      
    } else {
        echo "Falta indicar grupo para Nivel 4.";
        exit;
    }
    $res = odbc_exec($conn, $query);
    if (!$res) {
        error_log("Error en nivel 4: " . odbc_errormsg($conn));
        echo 'Error interno';
        return;
    }
    $output = "<div class='table-responsive'>
                 <table class='table table-striped table-bordered table-hover'>
                   <thead class='table-dark'>
                     <tr>
                       <th>Producto</th>
                        <th>Descripción</th>
                       <th>Pedidos</th>
                       <th>Albaranes</th>
                       <th>% Servicio</th>
                     </tr>
                   </thead>
                   <tbody>";
    while ($row = odbc_fetch_array($res)) {
        $row = convertRowToUtf8($row);
        $codArticulo = htmlspecialchars($row['cod_articulo'], ENT_QUOTES, 'UTF-8');
        $producto = htmlspecialchars($row['producto'], ENT_QUOTES, 'UTF-8');
        $ped = $row['pedidos'];
        $alb = $row['albaranes'];
        $cantPed = $row['cantidad_pedidos'];
        $cantAlb = $row['cantidad_albaranes'];
        $percentage = ($ped > 0) ? ($alb / $ped * 100) : 0;
        $stylePed = ($ped > $alb) ? "background-color: lightcoral;" : "";
        $styleAlb = ($alb > $ped) ? "background-color: lightgreen;" : "";
        $output .= "<tr>
                      <td>$codArticulo</td>
                      <td>$producto</td>
                      <td style='$stylePed'>" . format_importe($ped) . "  (" . format_importe($cantPed) . ")</td>
                      <td style='$styleAlb'>" . format_importe($alb) . "  (" . format_importe($cantAlb) . ")</td>
                      <td>" . number_format($percentage, 2, ',', '.') . " %</td>
                    </tr>";
    }
    $output .= "</tbody></table></div>";
    echo $output;

} else {
    echo "Nivel de drilldown no implementado o parámetros insuficientes.";
}

?>
