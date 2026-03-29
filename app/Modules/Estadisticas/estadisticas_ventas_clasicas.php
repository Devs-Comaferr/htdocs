<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_estadisticas');

// Guardar "showComisiones" en sesión si se envía por GET
if (isset($_GET['showComisiones'])) {
    $_SESSION['showComisiones'] = $_GET['showComisiones'];
}
$showComisiones = isset($_SESSION['showComisiones']) ? $_SESSION['showComisiones'] : '0';



// Abrir la conexión ODBC (se incluye solo una vez para todo el script)

$pageTitle = "Estadisticas de " . $_SESSION['nombre'];
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

include(BASE_PATH . '/resources/views/layouts/header.php');

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
 * Convertir un array a UTF-8 (asumiendo CP1252).
 */
function convertRowToUtf8($row) {
    foreach ($row as $key => $value) {
        if (is_string($value)) {
            $row[$key] = iconv('CP1252', 'UTF-8', $value);
        }
    }
    return $row;
}

/**
 * Calcula el número de meses (con fracción) entre dos fechas (sin usar DateTime::diff()).
 */
if (!function_exists('calcularMeses')) {
    function calcularMeses($start_date, $end_date) {
        $startTimestamp = strtotime($start_date);
        $endTimestamp = strtotime($end_date);
        $startYear = (int)date('Y', $startTimestamp);
        $startMonth = (int)date('m', $startTimestamp);
        $startDay = (int)date('d', $startTimestamp);
    
        $endYear = (int)date('Y', $endTimestamp);
        $endMonth = (int)date('m', $endTimestamp);
        $endDay = (int)date('d', $endTimestamp);
    
        if ($startYear == $endYear && $startMonth == $endMonth) {
            $totalDays = cal_days_in_month(CAL_GREGORIAN, $startMonth, $startYear);
            $days = $endDay - $startDay + 1;
            return $days / $totalDays;
        }
    
        $daysInStart = cal_days_in_month(CAL_GREGORIAN, $startMonth, $startYear);
        $firstFraction = ($startDay == 1) ? 1 : (($daysInStart - $startDay + 1) / $daysInStart);
    
        $daysInEnd = cal_days_in_month(CAL_GREGORIAN, $endMonth, $endYear);
        $lastFraction = ($endDay == $daysInEnd) ? 1 : ($endDay / $daysInEnd);
    
        $startTotalMonths = $startYear * 12 + $startMonth;
        $endTotalMonths = $endYear * 12 + $endMonth;
        $fullMonths = max(0, $endTotalMonths - $startTotalMonths - 1);
    
        return $firstFraction + $fullMonths + $lastFraction;
    }
}

if (!function_exists('calcularComision')) {
    function calcularComision($media) {
        if ($media < 45000) {
            return 1;
        } elseif ($media < 50000) {
            return 1.5;
        } elseif ($media < 55000) {
            return 2;
        } else {
            return 2.5;
        }
    }
}

// Parámetros de fecha (por defecto, inicio del año en curso)
$defaultStart = date('Y') . "-01-01";
$start_date_iso = isset($_GET['start_date']) ? $_GET['start_date'] : $defaultStart;
$end_date_iso   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$start_date_full = $start_date_iso . " 00:00:00";
$end_date_full   = $end_date_iso . " 23:59:59";

// Seleccionar comisionista según la sesión o el parámetro GET
if (isset($_SESSION['codigo'])) {
    $selected_comercial = $_SESSION['codigo'];
} else {
    $selected_comercial = isset($_GET['comercial']) ? $_GET['comercial'] : "";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Estadsticas de Ventas</title>
  <link href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.css" />
  <script type="text/javascript">
    function toggleDetails(rowId, url, linkElement) {
      var row = document.getElementById(rowId);
      if (!row) return;
      if (row.style.display === "none") {
        if (!row.getAttribute("data-loaded")) {
          var xhr = new XMLHttpRequest();
          xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
              if (xhr.status === 200) {
                if (row.cells.length > 0) {
                  row.cells[0].innerHTML = "<div style='display:block; width:100%;'>" + xhr.responseText + "</div>";
                } else {
                  row.innerHTML = "<td colspan='4'><div style='display:block; width:100%;'>" + xhr.responseText + "</div></td>";
                }
                row.setAttribute("data-loaded", "true");
              } else {
                row.innerHTML = "<td colspan='4'><div style='color:red;'>Error " + xhr.status + "</div></td>";
              }
            }
          };
          xhr.open("GET", url, true);
          xhr.send();
        }
        row.style.display = "table-row";
        linkElement.innerHTML = "[-]";
      } else {
        row.style.display = "none";
        linkElement.innerHTML = "[+]";
      }
    }
    
    function toggleGroupDetails(cliente, seccion, group, url, linkElement) {
      var groups = ["marca", "familia", "producto"];
      groups.forEach(function(g) {
        var rowId = "details_client_" + encodeURIComponent(cliente) + "_" + encodeURIComponent(seccion) + "_" + g;
        var row = document.getElementById(rowId);
        if (row) {
          if (g === group) {
            if (row.style.display === "none") {
              if (!row.getAttribute("data-loaded")) {
                var xhr = new XMLHttpRequest();
                xhr.onreadystatechange = function() {
                  if (xhr.readyState === 4 && xhr.status === 200) {
                    row.cells[0].innerHTML = "<div style='display:block; width:100%;'>" + xhr.responseText + "</div>";
                    row.setAttribute("data-loaded", "true");
                  }
                };
                xhr.open("GET", url, true);
                xhr.send();
              }
              row.style.display = "table-row";
            } else {
              row.style.display = "none";
            }
          } else {
            row.style.display = "none";
          }
        }
      });
    }
    
    function toggleSessionCheckbox(checkbox) {
      var estado = checkbox.checked ? '1' : '0';
      var params = new URLSearchParams(window.location.search);
      params.set("showComisiones", estado);
      window.location.search = params.toString();
    }
  </script>
</head>
<body>
<div class="container my-5">
  <h1 class="mb-4"><br>Estadsticas de Ventas</h1>
  
  <?php if ((isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') || (isset($_SESSION['perm_comisiones']) && (int)$_SESSION['perm_comisiones'] === 1)): ?>
    <div class="mb-3">
      <label>
        <input type="checkbox" id="chkComisiones" onchange="toggleSessionCheckbox(this)" <?php echo ($showComisiones === '1') ? 'checked' : ''; ?>>
        Mostrar Comisiones
      </label>
    </div>
  <?php endif; ?>
  
  <form method="GET" action="estadisticas_ventas_clasicas.php" id="filtrosForm">
    <div class="row mb-3">
      <div class="col-12">
          <label for="date-slider" class="form-label">Rango de fechas:</label>
          <div id="date-slider"></div>
          <input type="hidden" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date_iso); ?>">
          <input type="hidden" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date_iso); ?>">
      </div>
    </div>
    <?php if (empty($_SESSION['codigo'])) { ?>
      <div class="row">
        <div class="col-12">
          <label for="comercial" class="form-label">Comisionista:</label>
          <select name="comercial" id="comercial" class="form-select" onchange="this.form.submit();">
            <option value="">-- Todos --</option>
            <?php 
                $query = "SELECT DISTINCT v.cod_vendedor, v.nombre 
                          FROM hist_ventas_cabecera h
                          JOIN vendedores v ON h.cod_comisionista = v.cod_vendedor
                          WHERE h.fecha_venta >= CONVERT(smalldatetime, '$start_date_full', 120)
                            AND h.fecha_venta <= CONVERT(smalldatetime, '$end_date_full', 120)
                          ORDER BY v.nombre";
                $res = odbc_exec($conn, $query);
                if (!$res) {
                    error_log("Error en comisionistas: " . odbc_errormsg($conn));
                    echo 'Error interno';
                    return;
                }
                while ($row = odbc_fetch_array($res)) {
                    $code = $row['cod_vendedor'];
                    $name = $row['nombre'];
                    $sel = ($selected_comercial == $code) ? "selected" : "";
                    echo "<option value='" . htmlspecialchars($code) . "' $sel>" . htmlspecialchars($name) . "</option>";
                }
            ?>
          </select>
        </div>
      </div>
    <?php } ?>
  </form>
  <hr class="my-4">
  
  <?php
  // NIVEL 0: Comerciales
  $where = "WHERE fecha_venta >= CONVERT(smalldatetime, '$start_date_full', 120)
            AND fecha_venta <= CONVERT(smalldatetime, '$end_date_full', 120)";
  if ($selected_comercial !== "" && is_numeric($selected_comercial)) {
      $where .= " AND cod_comisionista = " . intval($selected_comercial);
  }
  $query = "SELECT v.cod_vendedor, v.nombre AS comercial, 
             SUM(CASE WHEN tipo_venta = 1 THEN importe ELSE 0 END) AS pedidos,
             SUM(CASE WHEN tipo_venta = 2 THEN importe ELSE 0 END) AS albaranes
            FROM hist_ventas_cabecera h
            JOIN vendedores v ON h.cod_comisionista = v.cod_vendedor
            $where
            GROUP BY v.cod_vendedor, v.nombre
            ORDER BY pedidos DESC";
  $res = odbc_exec($conn, $query);
  if (!$res) {
    error_log("Error en nivel 0: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
  }
  
  echo "<div class='table-responsive'>";
  echo "<table class='table table-striped table-bordered table-hover'>";
  echo "<thead class='table-dark'><tr><th>Comercial</th><th>Pedidos</th><th>Albaranes</th><th>% Servicio</th></tr></thead>";
  echo "<tbody>";
  while ($row = odbc_fetch_array($res)) {
      $codVendedor = $row['cod_vendedor'];
      $comercial = $row['comercial'];
      $url = "drilldown.php?level=1&comercial=" . urlencode($codVendedor ?? '') .
             "&start_date=" . urlencode($start_date_iso ?? '') .
             "&end_date=" . urlencode($end_date_iso ?? '');
      echo "<tr>";
      echo "<td>";
      echo "<a onclick=\"toggleDetails('details_" . $codVendedor . "', '" . $url . "', this);\" class='btn btn-sm btn-outline-secondary me-2'>[+]</a>";
      echo htmlspecialchars($comercial);
      echo "</td>";
      $percentage = ($row['pedidos'] > 0) ? ($row['albaranes'] / $row['pedidos'] * 100) : 0;
      echo "<td>" . format_importe($row['pedidos']) . " &euro;</td>";
      echo "<td>" . format_importe($row['albaranes']) . " &euro;</td>";
      echo "<td>" . number_format($percentage, 2, ',', '.') . " %</td>";
      echo "</tr>";
      echo "<tr id='details_" . $codVendedor . "' style='display:none;' class='detail-row'><td colspan='4'></td></tr>";
  }
  echo "</tbody>";
  echo "</table>";
  echo "</div>";
  ?>
  
  <?php
  // TABLA: Ventas por Mes
  $query3 = "SELECT CONVERT(varchar(7), fecha_venta, 120) AS mes,
                    SUM(CASE WHEN tipo_venta = 1 THEN importe ELSE 0 END) AS pedidos,
                    SUM(CASE WHEN tipo_venta = 2 THEN importe ELSE 0 END) AS albaranes
             FROM hist_ventas_cabecera
             $where
             GROUP BY CONVERT(varchar(7), fecha_venta, 120)
             ORDER BY mes";
  $res3 = odbc_exec($conn, $query3);
  if (!$res3) {
    error_log("Error en ventas por meses: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
  }
  echo "<div class='table-responsive mt-4'>";
  echo "<h4>Ventas por Mes</h4>";
  
  echo "<table class='table table-bordered'>";
  echo "<thead><tr>";
  echo "<th>Mes</th>";
  echo "<th>Pedidos</th>";
  echo "<th>Albaranes</th>";
  echo "<th>% Servicio</th>";
  echo "<th class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>Comisin Cobrada</th>";
  echo "</tr></thead><tbody>";
  while ($row3 = odbc_fetch_array($res3)) {
      $row3 = convertRowToUtf8($row3);
      $mes = htmlspecialchars($row3['mes'], ENT_QUOTES, 'UTF-8');
      $pedidos = $row3['pedidos'];
      $albaranes = $row3['albaranes'];
      $porcServicio = ($pedidos > 0) ? ($albaranes / $pedidos * 100) : 0;
      $comision_cobrada = $albaranes * 0.01;
      echo "<tr>";
      echo "<td>" . $mes . "</td>";
      echo "<td>" . format_importe($pedidos) . " &euro;</td>";
      echo "<td>" . format_importe($albaranes) . " &euro;</td>";
      echo "<td>" . number_format($porcServicio, 2, ',', '.') . " %</td>";
      echo "<td class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>" . format_importe($comision_cobrada) . " &euro;</td>";
      echo "</tr>";
  }
  echo "</tbody></table>";
  echo "</div>";
  ?>
  
  <?php
      $months = calcularMeses($start_date_iso, $end_date_iso);
      
      $query2 = "SELECT 
                   SUM(CASE WHEN tipo_venta = 1 THEN importe ELSE 0 END) AS total_pedidos,
                   SUM(CASE WHEN tipo_venta = 2 THEN importe ELSE 0 END) AS total_albaranes
                 FROM hist_ventas_cabecera
                 $where";
      $res2 = odbc_exec($conn, $query2);
      if (!$res2) {
        error_log("Error en consulta media: " . odbc_errormsg($conn));
        echo 'Error interno';
        return;
      }
      $row2 = odbc_fetch_array($res2);
      $total_pedidos = $row2['total_pedidos'];
      $total_albaranes = $row2['total_albaranes'];
      
      $media_pedidos = ($months > 0) ? ($total_pedidos / $months) : 0;
      $media_albaranes = ($months > 0) ? ($total_albaranes / $months) : 0;
  
      $escalated_commission_pedidos = calcularComision($media_pedidos);
      $escalated_commission_albaranes = calcularComision($media_albaranes);
      
      $comision_cobrada_pedidos = $media_pedidos * 0.01 * $months;
      $comision_cobrada_albaranes = $media_albaranes * 0.01 * $months;
      
      $complemento_total_pedidos = $media_pedidos * (($escalated_commission_pedidos - 1) / 100) * $months;
      $complemento_total_albaranes = $media_albaranes * (($escalated_commission_albaranes - 1) / 100) * $months;
      
      echo "<div class='mt-4'>";
      echo "<h4>Resumen de Media Mensual y Comisin (del perodo seleccionado)</h4>";
      echo "<table class='table table-bordered'>";
      echo "<thead><tr><th>Concepto</th><th>Media Mensual</th><th class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>Comisin Cobrada</th><th class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>Comisin Escalonada</th><th class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>Complemento Total</th></tr></thead>";
      echo "<tbody>";
      echo "<tr><td>Pedidos</td><td>" . format_importe($media_pedidos) . " &euro;</td><td class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>" . format_importe($comision_cobrada_pedidos) . " &euro;</td><td class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>" . $escalated_commission_pedidos . " %</td><td class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>" . format_importe($complemento_total_pedidos) . " &euro;</td></tr>";
      echo "<tr><td>Albaranes</td><td>" . format_importe($media_albaranes) . " &euro;</td><td class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>" . format_importe($comision_cobrada_albaranes) . " &euro;</td><td class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>" . $escalated_commission_albaranes . " %</td><td class='comisiones' style='display:" . ($showComisiones=='1' ? 'table-cell' : 'none') . ";'>" . format_importe($complemento_total_albaranes) . " &euro;</td></tr>";
      echo "</tbody>";
      echo "</table>";
      echo "</div>";
  ?>
  
</div>

<script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.12.4.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/wnumb/wNumb.min.js"></script>
<script type="text/javascript">
  function timestampToSpanish(ts) {
      var date = new Date(ts * 1000);
      var day = ('0' + date.getDate()).slice(-2);
      var month = ('0' + (date.getMonth() + 1)).slice(-2);
      var year = date.getFullYear();
      return day + '/' + month + '/' + year;
  }
  function timestampToISO(ts) {
      var date = new Date(ts * 1000);
      var day = ('0' + date.getDate()).slice(-2);
      var month = ('0' + (date.getMonth() + 1)).slice(-2);
      var year = date.getFullYear();
      return year + '-' + month + '-' + day;
  }
  
  var slider = document.getElementById('date-slider');
  var minTimestamp = Date.parse("2024-10-01") / 1000;
  var maxTimestamp = Date.now() / 1000;
  var initialStart = Date.parse(document.getElementById('start_date').value) / 1000;
  var initialEnd = Date.parse(document.getElementById('end_date').value) / 1000;
  
  noUiSlider.create(slider, {
      start: [initialStart, initialEnd],
      connect: true,
      range: {
          'min': minTimestamp,
          'max': maxTimestamp
      },
      step: 86400,
      tooltips: [wNumb({ decimals: 0, edit: timestampToSpanish }), wNumb({ decimals: 0, edit: timestampToSpanish })]
  });
  
  slider.noUiSlider.on('change', function(values, handle) {
      var startDate = timestampToISO(parseFloat(values[0]));
      var endDate = timestampToISO(parseFloat(values[1]));
      document.getElementById('start_date').value = startDate;
      document.getElementById('end_date').value = endDate;
      document.getElementById('filtrosForm').submit();
  });
  
  function toggleDetails(rowId, url, linkElement) {
      var row = document.getElementById(rowId);
      if (!row) return;
      if (row.style.display === "none") {
          if (!row.getAttribute("data-loaded")) {
              var xhr = new XMLHttpRequest();
              xhr.onreadystatechange = function() {
                  if (xhr.readyState === 4) {
                      if (xhr.status === 200) {
                          if (row.cells.length > 0) {
                              row.cells[0].innerHTML = xhr.responseText;
                          } else {
                              row.innerHTML = "<td colspan='4'>" + xhr.responseText + "</td>";
                          }
                          row.setAttribute("data-loaded", "true");
                      } else {
                          row.innerHTML = "<td colspan='4' style='color:red;'>Error " + xhr.status + "</td>";
                      }
                  }
              };
              xhr.open("GET", url, true);
              xhr.send();
          }
          row.style.display = "table-row";
          linkElement.innerHTML = "[-]";
      } else {
          row.style.display = "none";
          linkElement.innerHTML = "[+]";
      }
  }
  
  function toggleGroupDetails(cliente, seccion, group, url, linkElement) {
      var groups = ["marca", "familia", "producto"];
      groups.forEach(function(g) {
          var rowId = "details_client_" + encodeURIComponent(cliente) + "_" + encodeURIComponent(seccion) + "_" + g;
          var row = document.getElementById(rowId);
          if (row) {
              if (g === group) {
                  if (row.style.display === "none") {
                      if (!row.getAttribute("data-loaded")) {
                          var xhr = new XMLHttpRequest();
                          xhr.onreadystatechange = function() {
                              if (xhr.readyState === 4 && xhr.status === 200) {
                                  row.cells[0].innerHTML = "<div style='display:block; width:100%;'>" + xhr.responseText + "</div>";
                                  row.setAttribute("data-loaded", "true");
                              }
                          };
                          xhr.open("GET", url, true);
                          xhr.send();
                      }
                      row.style.display = "table-row";
                  } else {
                      row.style.display = "none";
                  }
              } else {
                  row.style.display = "none";
              }
          }
      });
  }
</script>
<script src="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/js/bootstrap.min.js"></script>
</body>
</html>
<?php
// Cerrar la conexión al final del script
?>

