<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';

// Si se envía el parámetro 'origen' se guarda en sesión (se mantiene por compatibilidad)
if (isset($_GET['origen']) && !empty($_GET['origen'])) {
    $_SESSION['origen'] = $_GET['origen'];
}

// Leer el parámetro GET "tabla"
$tabla_param = isset($_GET['tabla']) ? $_GET['tabla'] : '';

// Verificar si el usuario ha iniciado sesión


// Verificar que se pase el parámetro `cod_cliente`
if (!isset($_GET['cod_cliente']) || $_GET['cod_cliente'] === '') {
    error_log("El parámetro 'cod_cliente' es obligatorio.");
    echo 'Error interno';
    return;
}

$cod_cliente = $_GET['cod_cliente'];
$cod_seccion = isset($_GET['cod_seccion']) ? $_GET['cod_seccion'] : null;

// Inicializar variables
$lineas = array();
$num_lineas = 0;
$suma_total = 0;

// Validar orden y dirección
$orden_permitido = array(
  'pedido'                     => 'pedido',
  'Fecha_Venta'                => 'Fecha_Venta',
  'Articulo'                   => 'Articulo',
  'Descripcion'                => 'Descripcion',
  'Cantidad_Pedida'            => 'Cantidad_Pedida',
  'Cantidad_Servida'           => 'Cantidad_Servida',
  'Cantidad_Restante'          => 'Cantidad_Restante',
  'Stock'                      => 'Stock',
  'Cantidad_Pendiente_Recibir' => 'Cantidad_Pendiente_Recibir',
  'Importe_Restante'           => 'Importe_Restante'
);

$orden     = isset($_GET['orden']) && array_key_exists($_GET['orden'], $orden_permitido) ? $_GET['orden'] : 'Fecha_Venta';
$direccion = isset($_GET['direccion']) && in_array($_GET['direccion'], array('ASC', 'DESC')) ? $_GET['direccion'] : 'DESC';

// Alternar dirección para las cabeceras
$direccion_invertida = ($direccion == 'ASC') ? 'DESC' : 'ASC';

require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';

$conn = db();

// -------------------------------------------------------------------------
// Construir la consulta principal según el valor del parámetro GET "tabla"
// -------------------------------------------------------------------------
if ($tabla_param === 'vcelim') {
    // Consulta para datos de las tablas eliminadas (ventas_linea_elim / ventas_cabecera_elim)
    $sql_lineas = "
    SELECT 
        vlelim.cod_venta AS pedido,
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
             WHERE s.cod_articulo = vlelim.cod_articulo), 0
        ) AS Cantidad_Pendiente_Recibir,
        ISNULL(
            (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
             FROM integral.dbo.stocks s 
             WHERE s.cod_articulo = vlelim.cod_articulo), 0
        ) AS Stock,
        
        vcelim.cod_pedido_web AS CodPedidoWeb
    FROM 
        integral.dbo.ventas_linea_elim vlelim
    INNER JOIN 
        integral.dbo.ventas_cabecera_elim vcelim ON vcelim.cod_venta = vlelim.cod_venta
    LEFT JOIN 
        integral.dbo.entrega_lineas_venta elv ON vlelim.cod_venta = elv.cod_venta_origen 
        AND vlelim.linea = elv.linea_origen
    WHERE 
        vcelim.cod_cliente = '" . addslashes($cod_cliente) . "' 
        AND vlelim.tipo_venta = 1  
        AND vcelim.tipo_venta = 1";
    if ($cod_seccion) {
        $sql_lineas .= " AND vcelim.cod_seccion = '" . addslashes($cod_seccion) . "'";
    }
    if (isset($_GET['pedido']) && !empty($_GET['pedido'])) {
        $pedido = addslashes($_GET['pedido']);
        $sql_lineas .= " AND vlelim.cod_venta = '$pedido'";
    } else {
        error_log("El parámetro 'pedido' es obligatorio.");
        echo 'Error interno';
        return;
    }
    // Se modifica el HAVING para incluir abonos (cantidad negativa)
    $sql_lineas .= "
    GROUP BY 
        vlelim.cod_venta, 
        vcelim.fecha_venta, 
        vlelim.cod_articulo, 
        vlelim.descripcion, 
        vlelim.observacion,
        vlelim.linea, 
        vlelim.cantidad, 
        vlelim.precio, 
        vlelim.tipo_venta,
        vcelim.cod_pedido_web
    HAVING 
        (
            (vlelim.cantidad > ISNULL(SUM(elv.cantidad), 0))
            OR
            (vlelim.cantidad < 0 AND ABS(vlelim.cantidad) > ABS(ISNULL(SUM(elv.cantidad), 0)))
        )";
} else {
    // Consulta para datos de las tablas originales (hist_ventas_linea / hist_ventas_cabecera)
    $sql_lineas = "
    SELECT 
        hvl.cod_venta AS pedido,
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
             WHERE s.cod_articulo = hvl.cod_articulo), 0
        ) AS Cantidad_Pendiente_Recibir,
        ISNULL(
            (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
             FROM integral.dbo.stocks s 
             WHERE s.cod_articulo = hvl.cod_articulo), 0
        ) AS Stock,
        hvc.historico AS Historico,
        hvc.cod_pedido_web AS CodPedidoWeb
    FROM 
        integral.dbo.hist_ventas_linea hvl
    INNER JOIN 
        integral.dbo.hist_ventas_cabecera hvc ON hvc.cod_venta = hvl.cod_venta
    LEFT JOIN 
        integral.dbo.entrega_lineas_venta elv ON hvl.cod_venta = elv.cod_venta_origen 
        AND hvl.linea = elv.linea_origen
    WHERE 
        hvc.cod_cliente = '" . addslashes($cod_cliente) . "' 
        AND hvl.tipo_venta = 1  
        AND hvc.tipo_venta = 1";
    if ($cod_seccion) {
        $sql_lineas .= " AND hvc.cod_seccion = '" . addslashes($cod_seccion) . "'";
    }
    if (isset($_GET['pedido']) && !empty($_GET['pedido'])) {
        $pedido = addslashes($_GET['pedido']);
        $sql_lineas .= " AND hvl.cod_venta = '$pedido'";
    } else {
        error_log("El parámetro 'pedido' es obligatorio.");
        echo 'Error interno';
        return;
    }
    // Se modifica el HAVING para incluir abonos (cantidad negativa)
    $sql_lineas .= "
    GROUP BY 
        hvl.cod_venta, 
        hvc.fecha_venta, 
        hvl.cod_articulo, 
        hvl.descripcion, 
        hvl.observacion,
        hvl.linea, 
        hvl.cantidad, 
        hvl.precio, 
        hvl.tipo_venta,
        hvc.historico,
        hvc.cod_pedido_web
    HAVING 
        (
            (hvl.cantidad > ISNULL(SUM(elv.cantidad), 0))
            OR
            (hvl.cantidad < 0 AND ABS(hvl.cantidad) > ABS(ISNULL(SUM(elv.cantidad), 0)))
        )";
}

// Agregar la clusula ORDER BY
$sql_lineas .= " ORDER BY " . $orden_permitido[$orden] . " " . $direccion;

// Ejecutar la consulta
$result_lineas = odbc_exec($conn, $sql_lineas);
if (!$result_lineas) {
    error_log("Error en la consulta SQL: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}

// Procesar resultados
while ($linea = odbc_fetch_array($result_lineas)) {
    $lineas[] = $linea;
    $suma_total += isset($linea['Importe_Restante']) ? (float)$linea['Importe_Restante'] : 0;
}
$num_lineas = count($lineas);

// Obtener el número de pedido si está presente
$numero_pedido = isset($_GET['pedido']) && $_GET['pedido'] !== '' ? $_GET['pedido'] : null;

// Definir la variable base_url para los enlaces de ordenación
$base_url = basename($_SERVER['PHP_SELF']) . '?cod_cliente=' . urlencode($cod_cliente) . '&pedido=' . urlencode($numero_pedido);
if ($cod_seccion) {
    $base_url .= '&cod_seccion=' . urlencode($cod_seccion);
}

// Obtener datos del cliente y sección
$sql_cliente_seccion = "
    SELECT 
        c.nombre_comercial AS nombre_cliente, 
        COALESCE(s.nombre, 'Sin Sección') AS nombre_seccion
    FROM [integral].[dbo].[clientes] c
    LEFT JOIN [integral].[dbo].[secciones_cliente] s
        ON c.cod_cliente = s.cod_cliente
    WHERE c.cod_cliente = '" . addslashes($cod_cliente) . "'
";
if ($cod_seccion !== null) {
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
    $nombre_cliente = isset($cliente_seccion['nombre_cliente']) ? $cliente_seccion['nombre_cliente'] : "Desconocido";
    $nombre_seccion = isset($cliente_seccion['nombre_seccion']) ? $cliente_seccion['nombre_seccion'] : "Sin sección";
}

// Generar el ttulo para la pgina
$pageTitle = $nombre_cliente;
if ($nombre_seccion != 'Sin Sección') {
    $pageTitle .= " - " . $nombre_seccion;
}
if ($numero_pedido) {
    $pageTitle .= " - #" . htmlspecialchars($numero_pedido);
}

$ui_version = 'bs5';
$ui_requires_jquery = false;

$url_faltas = 'faltas.php?cod_cliente=' . urlencode($cod_cliente);
// FIX cod_seccion: 0 es valor válido, no usar empty()
if (isset($cod_seccion) && tieneValor($cod_seccion)) {
    $url_faltas .= '&cod_seccion=' . urlencode($cod_seccion);
}

$headerButton = '<button type="button" style="background-color: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;" onclick="window.location.href=\'' . $url_faltas . '\'">
    <i class="fas fa-book" style="margin-right: 5px;"></i> Faltas Reales
</button>' . "\n";

// Consultar si ya existe un registro en cmf_solicitudes_pedido para este pedido (tipo Historico)
$existeHistorico = false;
if ($numero_pedido) {
    $sql_sol = "SELECT TOP 1 id_solicitud FROM cmf_solicitudes_pedido WHERE cod_venta = '" . addslashes($numero_pedido) . "' AND tipo_solicitud = 'Historico'";
    $rs_sol = odbc_exec($conn, $sql_sol);
    if ($rs_sol && odbc_fetch_row($rs_sol)) {
         $existeHistorico = true;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(toUTF8($pageTitle)); ?></title>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
     <!-- noUiSlider CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.css" />

  <style>
    /* Diseo existente */
    body {
      font-family: Arial, sans-serif;
      background-color: #f7f7f7;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background-color: #fff;
      padding: 20px;
      border-radius: 5px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-top: 60px;
    }
    .header {
      text-align: center;
      margin-bottom: 20px;
    }
    .header h1 {
      font-size: 20px;
      margin: 0;
    }
    .filters {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-bottom: 20px;
      padding: 10px;
      background-color: #f4f4f4;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      justify-content: space-between;
    }
    .filters label {
      display: block;
      font-size: 14px;
      margin-bottom: 5px;
      font-weight: bold;
    }
    .filters input, .filters button {
      padding: 10px;
      font-size: 14px;
      border: 1px solid #ddd;
      border-radius: 5px;
      width: 100%;
      box-sizing: border-box;
    }
    .filters input:focus, .filters button:focus {
      outline: none;
      border-color: #007BFF;
    }
    .filters .filter-group {
      flex: 1 1 calc(25% - 15px);
      min-width: 200px;
    }
    .filters button {
      background-color: #007BFF;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .filters button:hover {
      background-color: #0056b3;
    }
    .filters .clear-button {
      background-color: #dc3545;
    }
    .filters .clear-button:hover {
      background-color: #b02a37;
    }
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
      background-color: #343a40 !important;
      color: #fff;
      font-weight: bold;
    }
    th a {
      color: #fff;
      text-decoration: none;
      pointer-events: auto;
    }
    .row-yellow {
      background-color: #fff7a3;
    }
    .text-danger, .text-danger td {
      color: #dc3545 !important;
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
    .back-button:hover {
      background-color: #0056b3;
    }
    @media (max-width: 1024px) {
      .filters {
        flex-direction: column;
      }
      .filters .filter-group {
        flex: 1 1 100%;
        min-width: auto;
      }
      .filters button {
        width: 100%;
        margin-top: 10px;
      }
    }
    /* NUEVOS ESTILOS PARA LOS BOTONES (inspirados en la pgina de Calendario de Eventos) */
    .button-group {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: right;
    gap: 15px;
    margin-bottom: 20px;
  }
  .btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px;
    font-size: 14px;
    font-weight: bold;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    color: white;
    transition: all 0.3s ease;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
    text-decoration: none;
    width: 100px;
    height: 80px;
    text-align: center;
  }
  .btn i {
    font-size: 25px;
    margin-bottom: 5px;
  }
  .btn:hover {
    transform: translateY(-2px);
  }
  .btn:active {
    transform: translateY(0);
    box-shadow: none;
  }
  .btn-pasar { background-color: red; }
  .btn-pasar:hover { background-color: #bd2130; }
  .btn-whatsapp { background-color: #25D366; }
  .btn-whatsapp:hover { background-color: #1e8e57; }
  .btn-disabled { background-color: grey; }
  </style>
</head>
<body>
  <div class="container">
    <!-- Grupo de botones: Se muestran solo si el pedido es NO histórico -->
    <?php if (!empty($lineas) && isset($lineas[0]['Historico']) && $lineas[0]['Historico'] === 'N'): ?>
      <div class="button-group">
      <?php if (!$existeHistorico): ?>
  <a href="pasarHistorico.php?pedido=<?php echo urlencode($numero_pedido); ?>&cod_cliente=<?php echo urlencode($cod_cliente); ?><?php echo isset($cod_seccion) ? '&cod_seccion=' . urlencode($cod_seccion) : ''; ?>" class="btn btn-pasar" aria-label="Pasar a Histórico">
    <i class="fa-solid fa-xmark" style="font-size: 40px;"></i>
    <span>Histórico</span>
  </a>
<?php else: ?>
  <button class="btn btn-disabled" disabled aria-label="Pedido a histórico ya ingresado">
    <i class="fa-solid fa-xmark" style="font-size: 40px;"></i>
    <span>Histórico Solicitado</span>
  </button>
<?php endif; ?>

 <!--  <button class="btn btn-whatsapp" aria-label="WhatsApp 1">
    <i class="fa-brands fa-whatsapp"></i>
    <span>WhatsApp 1</span>
  </button>
  <button class="btn btn-whatsapp" aria-label="WhatsApp 2">
    <i class="fa-brands fa-whatsapp"></i>
    <span>WhatsApp 2</span>
  </button>
  <button class="btn btn-whatsapp" aria-label="WhatsApp 3">
    <i class="fa-brands fa-whatsapp"></i>
    <span>WhatsApp 3</span>
  </button>
  <button class="btn btn-whatsapp" aria-label="WhatsApp 4">
    <i class="fa-brands fa-whatsapp"></i>
    <span>WhatsApp 4</span>
  </button> -->
</div>
    <?php endif; ?>
    <!-- Tabla de Resultados -->
    <div class="table-container">
      <?php if (!empty($lineas)): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th><a href="<?php echo $base_url . '&orden=Articulo&direccion=' . $direccion_invertida; ?>">Artículo</a></th>
              <th><a href="<?php echo $base_url . '&orden=Descripcion&direccion=' . $direccion_invertida; ?>">Descripción</a></th>
              <th><a href="<?php echo $base_url . '&orden=Cantidad_Pedida&direccion=' . $direccion_invertida; ?>">Cantidad Pedida</a></th>
              <th><a href="<?php echo $base_url . '&orden=Cantidad_Servida&direccion=' . $direccion_invertida; ?>">Cantidad Servida</a></th>
              <th><a href="<?php echo $base_url . '&orden=Cantidad_Restante&direccion=' . $direccion_invertida; ?>">Cantidad Restante</a></th>
              <th><a href="<?php echo $base_url . '&orden=Stock&direccion=' . $direccion_invertida; ?>">Stock</a></th>
              <th><a href="<?php echo $base_url . '&orden=Cantidad_Pendiente_Recibir&direccion=' . $direccion_invertida; ?>">Pdte. Recibir</a></th>
              <th><a href="<?php echo $base_url . '&orden=Importe_Restante&direccion=' . $direccion_invertida; ?>">Importe Restante</a></th>
              <th> </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lineas as $linea): 
              // Si se trata de un abono, la cantidad pedida ser negativa.
              // Se asigna 'text-danger' si es abono; de lo contrario, si tiene cantidad servida se asigna 'row-yellow'
              $rowClass = ($linea['Cantidad_Pedida'] < 0) ? 'text-danger' : (($linea['Cantidad_Servida'] > 0) ? 'row-yellow' : '');
            ?>
              <tr class="<?php echo $rowClass; ?>">
                <td><?php echo htmlspecialchars($linea['Articulo']); ?></td>
                <td>
                  <?php echo htmlspecialchars(toUTF8($linea['Descripcion'])); ?>
                  <?php if (!empty($linea['Observacion'])): ?>
                    <br><small style="color: blue;">(<?php echo htmlspecialchars($linea['Observacion']); ?>)</small>
                  <?php endif; ?>
                </td>
                <td><?php echo number_format($linea['Cantidad_Pedida'], 2, ',', '.'); ?></td>
                <td><?php echo number_format($linea['Cantidad_Servida'], 2, ',', '.'); ?></td>
                <td><?php echo number_format($linea['Cantidad_Restante'], 2, ',', '.'); ?></td>
                <?php
                  // Clculo del stock:
                  $stockBase = (float)$linea['Stock'];
                  $cantRest  = (float)$linea['Cantidad_Restante'];
                  if ($tabla_param !== 'elim') {
                      if (isset($linea['Historico']) && $linea['Historico'] !== 'S') {
                          $stockBase += $cantRest;
                      }
                  }
                ?>
                <td style="background-color: <?php echo ($stockBase >= $cantRest) ? 'green' : 'transparent'; ?>; color: <?php echo ($stockBase >= $cantRest) ? 'white' : 'black'; ?>;">
                  <?php echo number_format($stockBase, 2, ',', '.'); ?>
                </td>
                <td <?php if ((float)$linea['Cantidad_Pendiente_Recibir'] > 0) { echo 'style="background-color: #ccffcc; color: #006600;"'; } ?>>
                  <?php echo number_format((float)$linea['Cantidad_Pendiente_Recibir'], 2, ',', '.'); ?>
                </td>
                <td><?php echo number_format($linea['Importe_Restante'], 2, ',', '.'); ?> </td>
                <!-- Celda con los iconos -->
                <td>
                  <?php if ($tabla_param === 'vcelim'): ?>
                    <i class="fas fa-trash-alt text-danger" title="Eliminado"></i>
                  <?php else: ?>
                    <?php if (!empty($linea['Historico']) && $linea['Historico'] === 'S'): ?>
                      <i class="fas fa-lock" title="Histórico (S)"></i>
                    <?php else: ?>
                      <i class="fas fa-lock-open" title="No Histórico (N)"></i>
                    <?php endif; ?>
                    <?php if (!empty($linea['CodPedidoWeb'])): ?>
                      &nbsp;<i class="fas fa-globe" title="Pedido Web"></i>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="summary">
          Número de líneas: <?php echo $num_lineas; ?><br>
          Suma total: <?php echo number_format($suma_total, 2, ',', '.'); ?> 
        </div>
      <?php else: ?>
        <p>No se encontraron pedido de entrega para este cliente y sección.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
<?php
if ($conn) {
}
?>



