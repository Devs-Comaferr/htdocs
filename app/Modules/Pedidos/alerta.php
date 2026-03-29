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

// Verificar si el usuario ha iniciado sesión


require_once BASE_PATH . '/app/Support/functions.php'; // Se asume que incluye la función toUTF8

// Obtener el código de vendedor asociado al usuario actual
$conn = db();
if (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '') {
    $cod_vendedor = (string)$_SESSION['codigo'];
} else {
$sql_cod_vendedor = "
    SELECT cod_vendedor 
    FROM cmf_vendedores_user 
    WHERE email = '" . addslashes($_SESSION['email']) . "'
";
$result_vendedor = odbc_exec($conn, $sql_cod_vendedor);
if (!$result_vendedor || !odbc_fetch_row($result_vendedor)) {
    error_log("Error: No se pudo determinar el código de vendedor.");
    echo 'Error interno';
    return;
}
$cod_vendedor = odbc_result($result_vendedor, "cod_vendedor");
}

// Pedidos con fecha anterior a 15 das de hoy (para SQL Server)
$condicionFecha = "CONVERT(date, hvc.fecha_venta) < CONVERT(date, DATEADD(day, -15, GETDATE()))";

// Filtro de cliente (si se desea)
$cliente_filtro = isset($_GET['cliente']) ? mb_convert_encoding($_GET['cliente'], 'Windows-1252', 'UTF-8') : '';

// Se hará una consulta sin paginación para traer TODOS los pedidos pendientes
$whereConditions = array();
$whereConditions[] = "hvl.tipo_venta = 1";
$whereConditions[] = "hvc.tipo_venta = 1";
$whereConditions[] = "hvc.historico = 'N'";
$whereConditions[] = "(hvl.cantidad > ISNULL(elv.cantidad_servida, 0))";
$whereConditions[] = $condicionFecha;
if (!is_null($cod_vendedor)) {
    $whereConditions[] = "c.cod_vendedor = '" . addslashes($cod_vendedor) . "'";
}
if (!empty($cliente_filtro)) {
    $cliente_filtro_esc = addslashes($cliente_filtro);
    $whereConditions[] = "(c.cod_cliente LIKE '%{$cliente_filtro_esc}%' OR c.nombre_comercial LIKE '%{$cliente_filtro_esc}%')";
}

$sql_pedidos = "
    SELECT 
        hvl.cod_venta AS Pedido,
        hvc.fecha_venta AS Fecha_Pedido,
        c.cod_cliente AS cod_cliente,
        c.nombre_comercial AS Cliente,
        hvc.cod_seccion AS Cod_Seccion,
        hvc.importe AS Importe,
        COALESCE(s.nombre, '') AS Seccion,
        COUNT(hvl.cod_articulo) AS Articulos_Pendientes,
        SUM(
            CASE 
                WHEN elv.cod_venta_origen IS NULL THEN hvl.cantidad * hvl.precio 
                ELSE (hvl.cantidad - elv.cantidad_servida) * hvl.precio 
            END
        ) AS Importe_Pendiente,
        hvc.cod_anexo,
        avc.observacion_interna AS ObservacionInterna
    FROM 
        integral.dbo.hist_ventas_linea hvl
    INNER JOIN 
        integral.dbo.hist_ventas_cabecera hvc ON hvc.cod_venta = hvl.cod_venta
    LEFT JOIN (
        SELECT 
            cod_venta_origen, 
            linea_origen, 
            SUM(cantidad) AS cantidad_servida
        FROM 
            integral.dbo.entrega_lineas_venta
        WHERE 
            tipo_venta_origen = 1
        GROUP BY 
            cod_venta_origen, linea_origen
    ) elv ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
    LEFT JOIN 
        integral.dbo.clientes c ON hvc.cod_cliente = c.cod_cliente
    LEFT JOIN 
        integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = hvc.cod_seccion
    LEFT JOIN 
        integral.dbo.anexo_ventas_cabecera avc ON hvc.cod_anexo = avc.cod_anexo
    WHERE " . implode(" AND ", $whereConditions) . "
    GROUP BY 
        hvl.cod_venta, 
        hvc.fecha_venta, 
        c.cod_cliente, 
        c.nombre_comercial, 
        hvc.cod_seccion, 
        hvc.importe,
        s.nombre,
        hvc.cod_anexo,
        avc.observacion_interna
    ORDER BY hvc.fecha_venta ASC
";

$result_pedidos = odbc_exec($conn, $sql_pedidos);
if (!$result_pedidos) {
    error_log("Error en la consulta SQL: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$pedidos = array();
while ($row = odbc_fetch_array($result_pedidos)) {
    $pedidos[] = $row;
}

$numero_pedidos = count($pedidos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pgina de Ejemplo</title>
  <!-- Bootstrap CSS -->
  <link href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css" />
  <style>
    body { 
      font-family: Arial, sans-serif; 
      background-color: #f7f7f7;
      padding: 20px;
    }
    .table-container {
      overflow-x: auto;
    }
    /* Forzar cursor pointer en filas dentro del modal */
    .modal .table tbody tr {
      cursor: pointer;
    }
  </style>
  <script type="text/javascript">
    // Función para redirigir al hacer clic en una fila
url += "&pedido=" + encodeURIComponent(pedido);
      window.location.href = url;
    }
  </script>
</head>
<body>
  <div class="container">
    <h1>Pgina de Ejemplo</h1>
    <p>Este es el fondo de la pgina. Aqu puedes tener cualquier contenido.</p>
  </div>
  
  <!-- Modal: Tabla sin paginación con pedidos abiertos -->
  <?php if (!empty($pedidos)): ?>
    <div class="modal fade" id="pedidosAbiertosModal" tabindex="-1" aria-labelledby="pedidosAbiertosModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="pedidosAbiertosModalLabel">
              Hay <?php echo $numero_pedidos; ?> Pedidos Abiertos de hace ms de 15 das.
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="table-container">
              <table class="table table-bordered">
                <thead class="table-dark">
                  <tr>
                    <th></th>
                    <th>Pedido</th>
                    <th>Fecha</th>
                    <th>Código Cliente</th>
                    <th>Nombre Cliente</th>
                    <th>Importe Pedido</th>
                    <th>Líneas Pdtes.</th>
                    <th>Importe Pdte.</th>
                    <th>Importe Disponible</th>
                    <th>Importe Pdte. Recibir</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pedidos as $pedido): ?>
                    <?php
                      // Recalcular importes para visualización
                      $pedidoId = addslashes($pedido['Pedido']);
                      $importeDisponibleTotal = 0;
                      $importePdteRecibirTotal = 0;
                      $sql_lineas = "
                          SELECT 
                              hvl.cantidad AS Cantidad_Pedida,
                              (hvl.cantidad - ISNULL(SUM(elv.cantidad),0)) AS Cantidad_Restante,
                              hvl.precio AS Precio,
                              ISNULL(
                                (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
                                 FROM integral.dbo.stocks s 
                                 WHERE s.cod_articulo = hvl.cod_articulo), 0
                              ) AS Stock,
                              ISNULL(
                                (SELECT TOP 1 s.cantidad_pendiente_recibir
                                 FROM integral.dbo.stocks s 
                                 WHERE s.cod_articulo = hvl.cod_articulo), 0
                              ) AS PdteRecibir
                          FROM integral.dbo.hist_ventas_linea hvl
                          LEFT JOIN integral.dbo.entrega_lineas_venta elv 
                              ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
                          WHERE hvl.cod_venta = '$pedidoId' AND hvl.tipo_venta = 1
                          GROUP BY hvl.cantidad, hvl.precio, hvl.cod_articulo
                      ";
                      $result_lineas = odbc_exec($conn, $sql_lineas);
                      if ($result_lineas) {
                          while ($linea = odbc_fetch_array($result_lineas)) {
                              $cantidadPedida = (float)$linea['Cantidad_Pedida'];
                              $cantidadRestante = (float)$linea['Cantidad_Restante'];
                              $precio = (float)$linea['Precio'];
                              $importeRestanteLinea = $cantidadRestante * $precio;
                              $price_unit = ($cantidadRestante > 0 && $importeRestanteLinea > 0) ? $importeRestanteLinea / $cantidadRestante : 0;
                              $stockDisponible = (float)$linea['Stock'] + (float)$linea['Cantidad_Restante'];
                              if ($stockDisponible < 0) { $stockDisponible = 0; }
                              $servibleStock = min($stockDisponible, $cantidadRestante);
                              $importeDisponibleLinea = $servibleStock * $price_unit;
                              $resto = $cantidadRestante - $servibleStock;
                              $pdteRecibirValor = (float)$linea['PdteRecibir'];
                              $pdteRecibirDisponible = ($pdteRecibirValor >= $resto) ? $resto : 0;
                              $importePdteRecibirLinea = $pdteRecibirDisponible * $price_unit;
                              
                              $importeDisponibleTotal += $importeDisponibleLinea;
                              $importePdteRecibirTotal += $importePdteRecibirLinea;
                          }
                      }
                      $impDisponible_formatted = number_format($importeDisponibleTotal, 2, ',', '.') . " ";
                      $impPdteRecibir_formatted = number_format($importePdteRecibirTotal, 2, ',', '.') . " ";
                    ?>
                    <!-- Agrego inline style "cursor: pointer;" a la fila -->
                    <tr style="cursor: pointer;" onclick='navigateTopedido(<?php echo json_encode($pedido["cod_cliente"]); ?>, <?php echo json_encode($pedido["Cod_Seccion"]); ?>, <?php echo json_encode($pedido["Pedido"]); ?>)'>
                      <?php
                        $queryEntrega = "
                            SELECT TOP 1 cod_venta_origen 
                            FROM integral.dbo.entrega_lineas_venta 
                            WHERE cod_venta_origen = '" . addslashes($pedido['Pedido']) . "' 
                              AND tipo_venta_origen = 1
                        ";
                        $rsEntrega = odbc_exec($conn, $queryEntrega);
                        $camionIcon = "";
                        if ($rsEntrega && odbc_fetch_row($rsEntrega)) {
                            $camionIcon = '<i class="fas fa-truck text-success"></i>';
                        }
                      ?>
                      <td style="text-align: center;"><?php echo $camionIcon; ?></td>
                      <td><?php echo htmlspecialchars($pedido['Pedido']); ?></td>
                      <td><?php echo date("d/m/Y", strtotime($pedido['Fecha_Pedido'])); ?></td>
                      <td><?php echo htmlspecialchars($pedido['cod_cliente']); ?></td>
                      <td>
                        <?php echo htmlspecialchars(toUTF8($pedido['Cliente'])); ?>
                        <?php if (!empty($pedido['ObservacionInterna'])): ?>
                          <br>
                          <span style="color: grey; font-style: italic; font-size: 0.85em;">
                            <?php echo htmlspecialchars(toUTF8($pedido['ObservacionInterna'])); ?>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td style="text-align: center;"><?php echo number_format($pedido['Importe'], 2, ',', '.') . " "; ?></td>
                      <td style="text-align: center;"><?php echo number_format($pedido['Articulos_Pendientes'], 2, ',', '.'); ?></td>
                      <td style="text-align: right;"><?php echo number_format($pedido['Importe_Pendiente'], 2, ',', '.') . " "; ?></td>
                      <td style="text-align: right;"><?php echo $impDisponible_formatted; ?></td>
                      <td style="text-align: right;"><?php echo $impPdteRecibir_formatted; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
  
  <!-- Bootstrap JS Bundle -->
  <script src="<?= BASE_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    // Si existen pedidos pendientes, se muestra el modal al cargar la pgina
    <?php if (!empty($pedidos)): ?>
      document.addEventListener("DOMContentLoaded", function() {
        var modal = new bootstrap.Modal(document.getElementById('pedidosAbiertosModal'));
        modal.show();
      });
    <?php endif; ?>
  </script>
<script src="<?= BASE_URL ?>/assets/js/app-navigation.js"></script>
</body>
</html>
<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

if ($conn) {
}
?>



