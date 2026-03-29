<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');

// Incluir el archivo de funciones centralizado
require_once BASE_PATH . '/app/Support/functions.php';

// Si se recibe el parámetro 'origen', se guarda en la sesión.
if (!empty($_GET['origen'])) {
    $_SESSION['origen'] = $_GET['origen'];
}

// Verificar si el usuario ha iniciado sesión


// Verificar que se pase el parámetro `cod_cliente`
if (empty($_GET['cod_cliente'])) {
    error_log("El parámetro 'cod_cliente' es obligatorio.");
    echo 'Error interno';
    return;
}

$cod_cliente = (string) $_GET['cod_cliente'];
$cod_seccion = $_GET['cod_seccion'] ?? null;


$conn = db();

// Inicializar variables
$lineas = [];
$num_lineas = 0;
$suma_total = 0;

// Obtener filtros desde el formulario
$fecha_desde  = $_GET['fecha_desde']  ?? null;
$fecha_hasta  = $_GET['fecha_hasta']  ?? null;
$cod_articulo = $_GET['cod_articulo'] ?? null;
$descripcion  = $_GET['descripcion']  ?? null;

// Validar orden y dirección
$orden_permitido = [
    'Pedido'                     => 'Pedido',
    'Fecha_Venta'                => 'Fecha_Venta',
    'Articulo'                   => 'Articulo',
    'Descripcion'                => 'Descripcion',
    'Cantidad_Pedida'            => 'Cantidad_Pedida',
    'Cantidad_Servida'           => 'Cantidad_Servida',
    'Cantidad_Restante'          => 'Cantidad_Restante',
    'Stock'                      => 'Stock',
    'Cantidad_Pendiente_Recibir' => 'Cantidad_Pendiente_Recibir',
    'Importe_Restante'           => 'Importe_Restante'
];
$orden = (isset($_GET['orden']) && array_key_exists($_GET['orden'], $orden_permitido))
         ? $_GET['orden']
         : 'Fecha_Venta';
$direccion = (isset($_GET['direccion']) && in_array($_GET['direccion'], ['ASC', 'DESC'], true))
           ? $_GET['direccion']
           : 'DESC';
$direccion_invertida = ($direccion === 'ASC') ? 'DESC' : 'ASC';


// -----------------------------------------------------------------------------
// Consulta para registros normales (hvl/hvc)
// -----------------------------------------------------------------------------
$sql_normal = "
SELECT 
    hvc.historico AS Historico,
    hvc.cod_pedido_web AS CodPedidoWeb,
    hvl.cod_venta AS Pedido,
    hvc.fecha_venta AS Fecha_Venta,
    hvl.cod_articulo AS Articulo,
    hvl.descripcion AS Descripcion,
    hvl.observacion AS Observacion,
    hvl.linea AS Linea,
    hvl.cantidad AS Cantidad_Pedida,
    ISNULL(SUM(elv.cantidad), 0) AS Cantidad_Servida,
    (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS Cantidad_Restante,
    hvl.precio AS Precio,
    (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) * hvl.precio AS Importe_Restante,
    hvl.tipo_venta AS Tipo_Venta,
    ISNULL(
        (SELECT TOP 1 s.cantidad_pendiente_recibir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = hvl.cod_articulo
        ), 0
    ) AS Cantidad_Pendiente_Recibir,
    ISNULL(
        (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = hvl.cod_articulo
        ), 0
    ) AS Stock,
    (
        SELECT TOP 1 hvcAlb.fecha_venta
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.hist_ventas_cabecera hvcAlb
            ON hvcAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = hvl.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND hvcAlb.tipo_venta = 2
        ORDER BY hvcAlb.fecha_venta DESC
    ) AS Fecha_Albaran,
    (
        SELECT TOP 1 hvcAlb.nombre_vendedor
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.hist_ventas_cabecera hvcAlb
            ON hvcAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = hvl.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND hvcAlb.tipo_venta = 2
        ORDER BY hvcAlb.fecha_venta DESC
    ) AS NombreVendedorAlbaran,
    'hvc' as tabla
FROM 
    integral.dbo.hist_ventas_linea hvl
    INNER JOIN integral.dbo.hist_ventas_cabecera hvc
        ON hvc.cod_venta = hvl.cod_venta
    LEFT JOIN integral.dbo.entrega_lineas_venta elv
        ON hvl.cod_venta = elv.cod_venta_origen
        AND hvl.linea = elv.linea_origen
WHERE
    hvc.cod_cliente = '" . addslashes($cod_cliente) . "'
    AND hvl.tipo_venta = 1
    AND hvc.tipo_venta = 1

    AND (
        (hvl.cod_articulo = 'LM' AND hvl.cantidad > 0)
        OR
        (
            NOT EXISTS (
                SELECT 1
                FROM integral.dbo.hist_ventas_linea hvl2
                INNER JOIN integral.dbo.hist_ventas_cabecera hvc2
                    ON hvl2.cod_venta = hvc2.cod_venta
                WHERE
                    hvc2.cod_cliente = hvc.cod_cliente
                    AND hvl2.cod_articulo = hvl.cod_articulo
                    AND hvc2.tipo_venta = 1
                    AND hvc2.fecha_venta > hvc.fecha_venta
            )
            AND
            hvl.cantidad > (
                SELECT ISNULL(SUM(hvlAlb.cantidad), 0)
                FROM integral.dbo.hist_ventas_cabecera hvcAlb
                INNER JOIN integral.dbo.hist_ventas_linea hvlAlb
                    ON hvcAlb.cod_venta = hvlAlb.cod_venta
                WHERE
                    hvcAlb.cod_cliente = hvc.cod_cliente
                    AND hvlAlb.cod_articulo = hvl.cod_articulo
                    AND hvcAlb.tipo_venta = 2
                    AND hvcAlb.fecha_venta > hvc.fecha_venta
            )
        )
    )
";
if ($cod_seccion !== null && $cod_seccion !== '') {
    $sql_normal .= " AND hvc.cod_seccion = '" . addslashes($cod_seccion) . "'";
}
if (!empty($_GET['pedido'])) {
    $pedido = $_GET['pedido'];
    $sql_normal .= " AND hvl.cod_venta = '" . addslashes($pedido) . "'";
}
if ($fecha_desde) {
    if (validarFechaSQL($fecha_desde)) {
        $sql_normal .= " AND hvc.fecha_venta >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120) ";
    } else {
        error_log("Formato de fecha 'desde' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($fecha_hasta) {
    if (validarFechaSQL($fecha_hasta)) {
        $sql_normal .= " AND hvc.fecha_venta <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120) ";
    } else {
        error_log("Formato de fecha 'hasta' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($cod_articulo) {
    $sql_normal .= " AND hvl.cod_articulo LIKE '%" . addslashes($cod_articulo) . "%' ";
}
if ($descripcion) {
    $sql_normal .= " AND hvl.descripcion LIKE '%" . addslashes($descripcion) . "%' ";
}

$sql_normal .= "
GROUP BY
    hvc.historico,
    hvc.cod_pedido_web,
    hvl.cod_venta,
    hvc.fecha_venta,
    hvl.cod_articulo,
    hvl.descripcion,
    hvl.observacion,
    hvl.linea,
    hvl.cantidad,
    hvl.precio,
    hvl.tipo_venta
HAVING
    (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) > 0
";

// -----------------------------------------------------------------------------
// Consulta para registros eliminados (vlelim/vcelim) con comprobación en las normales
// -----------------------------------------------------------------------------
$sql_elim = "
SELECT 
    vcelim.historico AS Historico,
    vcelim.cod_pedido_web AS CodPedidoWeb,
    vlelim.cod_venta AS Pedido,
    vcelim.fecha_venta AS Fecha_Venta,
    vlelim.cod_articulo AS Articulo,
    vlelim.descripcion AS Descripcion,
    vlelim.observacion AS Observacion,
    vlelim.linea AS Linea,
    vlelim.cantidad AS Cantidad_Pedida,
    ISNULL(SUM(elv.cantidad), 0) AS Cantidad_Servida,
    (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS Cantidad_Restante,
    vlelim.precio AS Precio,
    (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) * vlelim.precio AS Importe_Restante,
    vlelim.tipo_venta AS Tipo_Venta,
    ISNULL(
        (SELECT TOP 1 s.cantidad_pendiente_recibir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = vlelim.cod_articulo
        ), 0
    ) AS Cantidad_Pendiente_Recibir,
    ISNULL(
        (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = vlelim.cod_articulo
        ), 0
    ) AS Stock,
    (
        SELECT TOP 1 vcelimAlb.fecha_venta
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.ventas_cabecera_elim vcelimAlb
            ON vcelimAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = vlelim.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND vcelimAlb.tipo_venta = 2
        ORDER BY vcelimAlb.fecha_venta DESC
    ) AS Fecha_Albaran,
    (
        SELECT TOP 1 vcelimAlb.nombre_vendedor
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.ventas_cabecera_elim vcelimAlb
            ON vcelimAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = vlelim.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND vcelimAlb.tipo_venta = 2
        ORDER BY vcelimAlb.fecha_venta DESC
    ) AS NombreVendedorAlbaran,
    'vcelim' as tabla
FROM 
    integral.dbo.ventas_linea_elim vlelim
    INNER JOIN integral.dbo.ventas_cabecera_elim vcelim
        ON vcelim.cod_venta = vlelim.cod_venta
    LEFT JOIN integral.dbo.entrega_lineas_venta elv
        ON vlelim.cod_venta = elv.cod_venta_origen
        AND vlelim.linea = elv.linea_origen
WHERE
    vcelim.cod_cliente = '" . addslashes($cod_cliente) . "'
    AND vlelim.tipo_venta = 1
    AND vcelim.tipo_venta = 1
    AND (
        (vlelim.cod_articulo = 'LM' AND vlelim.cantidad > 0)
        OR
        (
            NOT EXISTS (
                SELECT 1
                FROM integral.dbo.ventas_linea_elim vlelim2
                INNER JOIN integral.dbo.ventas_cabecera_elim vcelim2
                    ON vlelim2.cod_venta = vcelim2.cod_venta
                WHERE
                    vcelim2.cod_cliente = vcelim.cod_cliente
                    AND vlelim2.cod_articulo = vlelim.cod_articulo
                    AND vcelim2.tipo_venta = 1
                    AND vcelim2.fecha_venta > vcelim.fecha_venta
            )
            AND
            vlelim.cantidad > (
                SELECT ISNULL(SUM(vlelimAlb.cantidad), 0)
                FROM integral.dbo.ventas_cabecera_elim vcelimAlb
                INNER JOIN integral.dbo.ventas_linea_elim vlelimAlb
                    ON vcelimAlb.cod_venta = vlelimAlb.cod_venta
                WHERE
                    vcelimAlb.cod_cliente = vcelim.cod_cliente
                    AND vlelimAlb.cod_articulo = vlelim.cod_articulo
                    AND vcelimAlb.tipo_venta = 2
                    AND vcelimAlb.fecha_venta > vcelim.fecha_venta
            )
        )
    )
    -- Descarta el registro eliminado si existe una venta normal posterior
    AND NOT EXISTS (
        SELECT 1
        FROM integral.dbo.hist_ventas_linea hvl_norm
        INNER JOIN integral.dbo.hist_ventas_cabecera hvc_norm
            ON hvc_norm.cod_venta = hvl_norm.cod_venta
        WHERE
            hvc_norm.cod_cliente = vcelim.cod_cliente
            AND hvl_norm.cod_articulo = vlelim.cod_articulo
            AND hvc_norm.tipo_venta = 1
            AND hvc_norm.fecha_venta >= vcelim.fecha_venta
    )
";
if ($cod_seccion !== null && $cod_seccion !== '') {
    $sql_elim .= " AND vcelim.cod_seccion = '" . addslashes($cod_seccion) . "'";
}
if (!empty($_GET['pedido'])) {
    $pedido = $_GET['pedido'];
    $sql_elim .= " AND vlelim.cod_venta = '" . addslashes($pedido) . "'";
}
if ($fecha_desde) {
    if (validarFechaSQL($fecha_desde)) {
        $sql_elim .= " AND vcelim.fecha_venta >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120) ";
    } else {
        error_log("Formato de fecha 'desde' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($fecha_hasta) {
    if (validarFechaSQL($fecha_hasta)) {
        $sql_elim .= " AND vcelim.fecha_venta <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120) ";
    } else {
        error_log("Formato de fecha 'hasta' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($cod_articulo) {
    $sql_elim .= " AND vlelim.cod_articulo LIKE '%" . addslashes($cod_articulo) . "%' ";
}
if ($descripcion) {
    $sql_elim .= " AND vlelim.descripcion LIKE '%" . addslashes($descripcion) . "%' ";
}
$sql_elim .= "
GROUP BY
    vcelim.historico,
    vcelim.cod_pedido_web,
    vlelim.cod_venta,
    vcelim.fecha_venta,
    vlelim.cod_articulo,
    vlelim.descripcion,
    vlelim.observacion,
    vlelim.linea,
    vlelim.cantidad,
    vlelim.precio,
    vlelim.tipo_venta
HAVING
    (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) > 0
";

// -----------------------------------------------------------------------------
// Unir ambas consultas
// -----------------------------------------------------------------------------
$sql_lineas_total = "
    SELECT * FROM (
        $sql_normal
        UNION ALL
        $sql_elim
    ) as combined
    ORDER BY " . $orden_permitido[$orden] . " " . $direccion;
    
$result_lineas = odbc_exec($conn, $sql_lineas_total);
if (!$result_lineas) {
    error_log("Error en la consulta SQL: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
while ($fila = odbc_fetch_array($result_lineas)) {
    $lineas[] = $fila;
}
$num_lineas = count($lineas);

// Calcular suma_total (por ejemplo, sumando Importe_Restante de cada registro)
foreach ($lineas as $fila) {
    $suma_total += isset($fila['Importe_Restante']) ? (float)$fila['Importe_Restante'] : 0;
}

$numero_pedido = $_GET['pedido'] ?? null;

$sql_cliente_seccion = "
    SELECT 
        c.nombre_comercial AS nombre_cliente, 
        COALESCE(s.nombre, 'Sin Sección') AS nombre_seccion
    FROM [integral].[dbo].[clientes] c
    LEFT JOIN [integral].[dbo].[secciones_cliente] s
        ON c.cod_cliente = s.cod_cliente
    WHERE c.cod_cliente = '" . addslashes($cod_cliente) . "'
";
if ($cod_seccion !== null && $cod_seccion !== '') {
    $sql_cliente_seccion .= " AND s.cod_seccion = '" . addslashes($cod_seccion) . "'";
}
$result_cliente_seccion = odbc_exec($conn, $sql_cliente_seccion);
if (!$result_cliente_seccion) {
    error_log("Error al obtener datos del cliente y sección: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$cliente_seccion = odbc_fetch_array($result_cliente_seccion);
if (!$cliente_seccion) {
    $nombre_cliente = "Cliente no encontrado";
    $nombre_seccion = "Sin sección";
} else {
    $nombre_cliente = $cliente_seccion['nombre_cliente'] ?? "Desconocido";
    $nombre_seccion = $cliente_seccion['nombre_seccion'] ?? "Sin sección";
}
$pageTitle = "Faltas de " . $nombre_cliente;
if ($numero_pedido) {
    $pageTitle .= " - Pedido: " . htmlspecialchars($numero_pedido);
}
if ($nombre_seccion !== 'Sin Sección') {
    $pageTitle .= " - " . htmlspecialchars($nombre_seccion);
}
include BASE_PATH . '/resources/views/layouts/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Faltas de Venta</title>
  
   <!-- Bootstrap CSS -->
     <!-- noUiSlider CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.css" />
  
  <!-- Estilos propios -->
  <style>
    html, body {
      -webkit-text-size-adjust: none; 
      -moz-text-size-adjust: none; 
      -ms-text-size-adjust: none; 
      text-size-adjust: none;
    }
    .summary-global { font-size: 18px; }
    .summary { font-size: 14px; }
    .row-yellow { background-color: #fff7a3; }
    .row-negative { background-color: #ffcccc; } /* Se usar para pedidos con importe negativo */
    body {
      font-family: Arial, sans-serif;
      background-color: #f7f7f7;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 100%;
      margin: 0 auto;
      background-color: #fff;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-top: 60px;
    }
    .header { text-align: center; margin-bottom: 20px; }
    .header h1 { font-size: 20px; margin: 0; }

    /* Contenedor de filtros */
    .filters {
      display: flex;
      flex-direction: column;
      gap: 15px;
      margin-bottom: 20px;
      padding: 10px;
      background-color: #f4f4f4;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    /* Cada fila de filtros */
    .filter-row {
      display: flex;
      align-items: center;
      gap: 15px;
      flex-wrap: wrap;
    }

    /* Fila completa (por ejemplo, el slider) */
    .full-width {
      flex: 1 1 100%;
      min-width: 100%;
    }

    /* Columnas de la fila de Código, Descripción y Botones */
    .code {
      flex: 0 0 150px;
    }
    .desc {
      flex: 1;
    }
    .buttons {
      flex: 0 0 auto;
      display: flex;
      gap: 10px;
    }

    /* Ajuste en la etiqueta e inputs de cada grupo */
    .filter-group {
      display: flex;
      flex-direction: column;
    }
    .filters label {
      font-size: 14px;
      margin-bottom: 5px;
      font-weight: bold;
    }
    .filters input,
    .filters button {
      padding: 10px;
      font-size: 14px;
      border: 1px solid #ddd;
      border-radius: 5px;
      box-sizing: border-box;
    }
    .filters input:focus,
    .filters button:focus {
      outline: none;
      border-color: #007BFF;
    }
    .filter-button,
    .clear-button {
      background-color: #007BFF;
      color: white;
      cursor: pointer;
      border: 1px solid #ddd;
      border-radius: 5px;
      transition: background-color 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .filter-button:hover { background-color: #0056b3; }
    .clear-button {
      background-color: #dc3545;
    }
    .clear-button:hover { background-color: #b02a37; }

    .table-container {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      font-size: 14px;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
    }
    th {
      background-color: #343a40;
      color: white;
      font-weight: bold;
    }
    th a {
      color: white;
      text-decoration: none;
    }
    .back-button {
      background-color: #007BFF;
      color: white;
      padding: 10px 20px;
      border-radius: 5px;
      text-decoration: none;
      font-size: 16px;
      display: inline-block;
      text-align: center;
      cursor: pointer;
    }
    .back-button:hover { background-color: #0056b3; }

    /* Estilos para el slider */
    #date-range-slider {
      margin: 10px 0;
    }
  </style>
  
  <!-- Script de noUiSlider -->
  <script src="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.js"></script>
</head>
<body>
<div class="container">
  <!-- Filtros -->
  <form method="GET" class="filters">
    <!-- Fila completa para el slider -->
    <div class="filter-row full-width">
      <div class="filter-group full-width">
        <label for="date-range-slider">Rango de Fechas:</label>
        <div id="date-range-slider"></div>
        <!-- Inputs ocultos para enviar las fechas -->
        <input type="hidden" name="fecha_desde" id="fecha_desde_hidden">
        <input type="hidden" name="fecha_hasta" id="fecha_hasta_hidden">
      </div>
    </div>

    <!-- Fila para Código, Descripción y Botones en línea -->
    <div class="filter-row">
      <div class="filter-group code">
        <label for="cod_articulo">Código:</label>
        <input type="text" id="cod_articulo" name="cod_articulo"
               value="<?php echo htmlspecialchars($cod_articulo ?? ''); ?>"
               placeholder="Buscar código...">
      </div>
      <div class="filter-group desc">
        <label for="descripcion">Descripción:</label>
        <input type="text" id="descripcion" name="descripcion"
               value="<?php echo htmlspecialchars($descripcion ?? ''); ?>"
               placeholder="Buscar descripción...">
      </div>
      <div class="filter-group buttons">
        <input type="hidden" name="cod_cliente" value="<?php echo htmlspecialchars($cod_cliente ?? ''); ?>">
        <?php if ($cod_seccion): ?>
          <input type="hidden" name="cod_seccion" value="<?php echo htmlspecialchars($cod_seccion ?? ''); ?>">
        <?php endif; ?>
        <button type="submit" class="filter-button"><i class="fas fa-search"></i> Filtrar</button>
        <button type="button" class="clear-button"
                onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF'] ?? ''); ?>?cod_cliente=<?php echo urlencode($cod_cliente ?? ''); ?><?php echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : ''; ?>';">
          <i class="fas fa-trash-alt"></i> Limpiar
        </button>
      </div>
    </div>
  </form>

  <?php if (!empty($lineas)): ?>
    <div class="table-container">
      <?php
      // Agrupar las líneas por mes
      $lineas_por_mes = [];
      foreach ($lineas as $linea) {
          $mes = date("Y-m", strtotime($linea['Fecha_Venta']));
          if (!isset($lineas_por_mes[$mes])) {
              $lineas_por_mes[$mes] = [];
          }
          $lineas_por_mes[$mes][] = $linea;
      }
      krsort($lineas_por_mes);
      foreach ($lineas_por_mes as $mes => $lineas_mes):
          $mes_formateado = formatearMesAno($mes . "-01");
          $base_url_mes = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['mes' => $mes, 'orden' => null, 'direccion' => null]));
      ?>
        <h3><br><br><hr><?php echo htmlspecialchars($mes_formateado ?? ''); ?></h3>
        <table>
          <thead>
            <tr>
              <th><a href="<?php echo $base_url_mes . '&orden=Pedido&direccion=' . $direccion_invertida; ?>">Código Pedido</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Fecha_Venta&direccion=' . $direccion_invertida; ?>">Fecha Pedido</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Fecha_Albaran&direccion=' . $direccion_invertida; ?>">Albarán</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Articulo&direccion=' . $direccion_invertida; ?>">Artículo</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Descripcion&direccion=' . $direccion_invertida; ?>">Descripción</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Cantidad_Pedida&direccion=' . $direccion_invertida; ?>">Pedido</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Cantidad_Servida&direccion=' . $direccion_invertida; ?>">Servido</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Cantidad_Restante&direccion=' . $direccion_invertida; ?>">Resto</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Stock&direccion=' . $direccion_invertida; ?>">Stock</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Cantidad_Pendiente_Recibir&direccion=' . $direccion_invertida; ?>">Pdte. Recibir</a></th>
              <th><a href="<?php echo $base_url_mes . '&orden=Importe_Restante&direccion=' . $direccion_invertida; ?>">Importe Restante</a></th>
              <th>-</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lineas_por_mes[$mes] as $linea):
              // Si el Importe_Restante es negativo, se asigna la clase "row-negative".
              // Si no, se usa "row-yellow" si hay cantidad servida.
              $rowClass = ((float)$linea['Importe_Restante'] < 0) ? 'row-negative' : (((float)$linea['Cantidad_Servida'] > 0) ? 'row-yellow' : '');
            ?>
              <tr class="<?php echo $rowClass; ?>">
                <td><?php echo htmlspecialchars($linea['Pedido'] ?? ''); ?></td>
                <td><?php echo date("d/m/Y", strtotime($linea['Fecha_Venta'])); ?></td>
                <td>
                  <?php 
                      if (!empty($linea['Fecha_Albaran'])) {
                          echo date("d/m/Y", strtotime($linea['Fecha_Albaran']));
                      } else {
                          echo "-";
                      }
                      echo " - ";
                      echo !empty($linea['NombreVendedorAlbaran']) ? htmlspecialchars($linea['NombreVendedorAlbaran'] ?? '') : "-";
                  ?>
                </td>
                <td><?php echo htmlspecialchars($linea['Articulo'] ?? ''); ?></td>
                <td>
                  <?php echo htmlspecialchars(toUTF8($linea['Descripcion'] ?? '')); ?>
                  <?php if (!empty($linea['Observacion'])): ?>
                    <br><small style="color: blue;">(<?php echo htmlspecialchars($linea['Observacion'] ?? ''); ?>)</small>
                  <?php endif; ?>
                </td>
                <td><?php echo number_format((float)$linea['Cantidad_Pedida'], 2, ',', '.'); ?></td>
                <td><?php echo number_format((float)$linea['Cantidad_Servida'], 2, ',', '.'); ?></td>
                <td><?php echo number_format((float)$linea['Cantidad_Restante'], 2, ',', '.'); ?></td>
                <?php
                  $stockBase = (float)$linea['Stock'];
                  $cantRest = (float)$linea['Cantidad_Restante'];
                  if (isset($linea['Historico']) && $linea['Historico'] !== 'S') {
                      $stockBase += $cantRest;
                  }
                  $bgColor = ($stockBase >= $cantRest) ? 'green' : '';
                  $textColor = ($stockBase >= $cantRest) ? 'white' : 'black';
                ?>
                <td style="background-color: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>;">
                  <?php echo number_format($stockBase, 2, ',', '.'); ?>
                </td>
                <td <?php if ((float)$linea['Cantidad_Pendiente_Recibir'] >= (float)$linea['Cantidad_Restante']): ?>style="background-color: #ccffcc;"<?php endif; ?>>
                  <?php echo number_format((float)$linea['Cantidad_Pendiente_Recibir'], 2, ',', '.'); ?>
                </td>
                <td><?php echo number_format((float)$linea['Importe_Restante'], 2, ',', '.'); ?> </td>
                <td style="text-align: center;">
                  <?php
                    if (isset($linea['tabla']) && $linea['tabla'] === 'vcelim') {
                        echo '<i class="fas fa-trash-alt text-danger" title="Eliminado"></i>';
                    } else {
                        if (!empty($linea['Historico']) && $linea['Historico'] === 'S') {
                            echo '<i class="fas fa-lock" title="Histórico (S)"></i>';
                        } else {
                            echo '<i class="fas fa-lock-open" title="No Histórico (N)"></i>';
                        }
                        if (!empty($linea['CodPedidoWeb'])) {
                            echo '&nbsp;<i class="fas fa-globe" title="Pedido Web"></i>';
                        }
                    }
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="summary">
          <strong>Resumen del Mes:</strong><br>
          Número de líneas: <?php echo count($lineas_por_mes[$mes]); ?><br>
          Importe total: <?php 
            $suma_mes = 0;
            foreach ($lineas_por_mes[$mes] as $l) {
                $suma_mes += (float)$l['Importe_Restante'];
            }
            echo number_format($suma_mes, 2, ',', '.');
          ?> 
        </div>
      <?php endforeach; ?>
      <div class="summary-global">
        <br><br>
        <hr>
        <strong>Resumen Global:</strong><br>
        Número total de líneas: <?php echo $num_lineas; ?><br>
        Importe total: <?php echo number_format($suma_total, 2, ',', '.'); ?> 
        <hr><br><br>
      </div>
    </div>
  <?php else: ?>
    <p>No se encontraron faltas de entrega para este cliente y sección.</p>
  <?php endif; ?>
  <?php 
    $origen = $_SESSION['origen'] ?? 'index.php';
    echo '<a href="' . htmlspecialchars($origen) . '" class="back-button"> Volver</a>';
  ?>
</div>

<!-- Script para inicializar el slider de fechas con tooltips -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Definir fechas lmite
  var minDate = new Date(2024, 9, 1); // Octubre 1, 2024 (mes: 0-indexado)
  var maxDate = new Date(); // Hoy
  
  // Obtener los valores enviados en GET (si existen)
  var fechaDesdePHP = <?php echo $fecha_desde ? json_encode($fecha_desde) : 'null'; ?>;
  var fechaHastaPHP = <?php echo $fecha_hasta ? json_encode($fecha_hasta) : 'null'; ?>;
  
  // Función para crear un objeto Date a partir de "YYYY-MM-DD"
  function parseLocalDate(str) {
    var parts = str.split('-');
    return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), 12, 0, 0);
  }
  
  var defaultStart, defaultEnd;
  if (fechaDesdePHP && fechaHastaPHP) {
    defaultStart = parseLocalDate(fechaDesdePHP);
    defaultEnd = parseLocalDate(fechaHastaPHP);
  } else {
    defaultEnd = new Date();
    defaultStart = new Date(defaultEnd.getTime() - (30 * 24 * 60 * 60 * 1000));
  }
  
  var slider = document.getElementById('date-range-slider');
  noUiSlider.create(slider, {
    start: [defaultStart.getTime(), defaultEnd.getTime()],
    connect: true,
    range: {
      'min': minDate.getTime(),
      'max': maxDate.getTime()
    },
    step: 24 * 60 * 60 * 1000,
    tooltips: [
      {
        to: function(value) {
          var date = new Date(value);
          var dd = String(date.getDate()).padStart(2, '0');
          var mm = String(date.getMonth() + 1).padStart(2, '0');
          var yyyy = date.getFullYear();
          return dd + '/' + mm + '/' + yyyy;
        }
      },
      {
        to: function(value) {
          var date = new Date(value);
          var dd = String(date.getDate()).padStart(2, '0');
          var mm = String(date.getMonth() + 1).padStart(2, '0');
          var yyyy = date.getFullYear();
          return dd + '/' + mm + '/' + yyyy;
        }
      }
    ]
  });
  
  // Actualizar los inputs hidden en cada movimiento del slider
  slider.noUiSlider.on('update', function(values, handle) {
    var rawValues = slider.noUiSlider.get(true);
    var startDateObj = new Date(rawValues[0]);
    var endDateObj = new Date(rawValues[1]);
    
    function formatDateISO(date) {
      var dd = String(date.getDate()).padStart(2, '0');
      var mm = String(date.getMonth() + 1).padStart(2, '0');
      var yyyy = date.getFullYear();
      return yyyy + '-' + mm + '-' + dd;
    }
    document.getElementById('fecha_desde_hidden').value = formatDateISO(startDateObj);
    document.getElementById('fecha_hasta_hidden').value = formatDateISO(endDateObj);
  });
  
  // Al soltar el slider, hacemos submit automtico
  slider.noUiSlider.on('set', function() {
    document.querySelector('form.filters').submit();
  });
  
  // Si no hay valores de fecha en GET, se hace submit automtico
  if (!fechaDesdePHP && !fechaHastaPHP) {
    document.querySelector('form.filters').submit();
  }
});
</script>
</body>
</html>
<?php
if ($conn) {
}
?>



