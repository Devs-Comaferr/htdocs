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
header('Content-Type: text/html; charset=utf-8');

// Verificar que el usuario est autenticado


// Verificar que se haya enviado el parÃ¡metro combinado
if (!isset($_GET['cod_venta_tipo'])) {
    echo "No se ha especificado un cÃ³digo de venta.";
    exit;
}

// Separar el parÃ¡metro en cod_venta y tipo_venta
list($cod_venta, $tipo_venta) = explode('_', $_GET['cod_venta_tipo'], 2);
$cod_venta = intval($cod_venta);
$tipo_venta = intval($tipo_venta);

// ConexiÃ³n a la base de datos

$conn = db();

// Consulta para obtener los detalles del albarn/ticket, usando el tipo recibido
$sql = "SELECT cod_articulo, descripcion, cantidad, precio, dto1, dto2, importe 
        FROM [integral].[dbo].[hist_ventas_linea] 
        WHERE cod_venta = $cod_venta AND tipo_venta = $tipo_venta";
$result = odbc_exec($conn, $sql);

$rows = array();
$showDto1 = false;
$showDto2 = false;
if ($result) {
    while ($row = odbc_fetch_array($result)) {
        $rows[] = $row;
        if (floatval($row['dto1']) != 0) {
            $showDto1 = true;
        }
        if (floatval($row['dto2']) != 0) {
            $showDto2 = true;
        }
    }
}
?>
<!-- Estilos locales para el detalle -->
<style>
  .detail-container {
      font-family: Arial, sans-serif;
      background-color: #f8f9fa;
      color: #333;
      padding: 10px;
  }
  h2 {
      font-size: 1.8em;
      margin-bottom: 20px;
      color: #007bff;
  }
  table.detail-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
  }
  table.detail-table th,
  table.detail-table td {
      border: 1px solid #ddd;
      padding: 10px;
  }
  .detail-right-align {
      text-align: right;
      white-space: nowrap;
  }
  table.detail-table th {
      background-color: #007bff;
      color: #fff;
      font-weight: bold;
  }
  table.detail-table tr:nth-child(even) {
      background-color: #f9f9f9;
  }
  table.detail-table tr:hover {
      background-color: #f1f1f1;
  }
</style>

<div class="detail-container">
  <?php if (empty($rows)) { ?>
    <p>No se encontraron detalles para el albarn/ticket.</p>
  <?php } else { ?>
    <table class="detail-table">
      <tr>
        <th>CÃ³d. ArtÃ­culo</th>
        <th>DescripciÃ³n</th>
        <th>Cantidad</th>
        <th>Precio</th>
        <?php if ($showDto1) { echo "<th>Dto1</th>"; } ?>
        <?php if ($showDto2) { echo "<th>Dto2</th>"; } ?>
        <th>Importe</th>
      </tr>
      <?php 
      foreach ($rows as $row) {
          $cantidad = number_format(floatval($row['cantidad']), 2, ',', '.');
          $precio = number_format(floatval($row['precio']), 4, ',', '.');
          $importe = number_format(floatval($row['importe']), 2, ',', '.');
          $dto1 = (floatval($row['dto1']) != 0) ? number_format(floatval($row['dto1']), 2, ',', '.') : "";
          $dto2 = (floatval($row['dto2']) != 0) ? number_format(floatval($row['dto2']), 2, ',', '.') : "";
          echo "<tr>";
          echo "<td>" . htmlspecialchars($row['cod_articulo']) . "</td>";
          echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
          echo "<td class='detail-right-align'>" . $cantidad . "</td>";
          echo "<td class='detail-right-align'>" . $precio . " </td>";
          if ($showDto1) {
              echo "<td class='detail-right-align'>" . $dto1 . " %</td>";
          }
          if ($showDto2) {
              echo "<td class='detail-right-align'>" . $dto2 . " %</td>";
          }
          echo "<td class='detail-right-align'>" . $importe . " </td>";
          echo "</tr>";
      }
      ?>
    </table>
  <?php } ?>
</div>
