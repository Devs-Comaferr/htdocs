<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Pedidos/services/pedido_service.php';

if (isset($_GET['origen']) && !empty($_GET['origen'])) {
    $_SESSION['origen'] = $_GET['origen'];
}

$conn = db();

try {
    extract(obtenerDatosPedidoDetalle($conn, $_GET), EXTR_OVERWRITE);
} catch (RuntimeException $e) {
    error_log($e->getMessage());
    echo 'Error interno';
    return;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(toUTF8($pageTitle)); ?></title>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.css" />

  <style>
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
    .row-yellow td {
      background-color: #fff7a3 !important;
    }
    td.stock-ok {
      background-color: green !important;
      color: white !important;
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
    .button-group form {
      margin: 0;
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
    <?php if (!empty($lineas) && isset($lineas[0]['Historico']) && $lineas[0]['Historico'] === 'N'): ?>
      <div class="button-group">
      <?php if (!$existeHistorico): ?>
        <a href="pasar_historico.php?pedido=<?php echo urlencode($numero_pedido); ?>&cod_cliente=<?php echo urlencode($cod_cliente); ?><?php echo isset($cod_seccion) ? '&cod_seccion=' . urlencode($cod_seccion) : ''; ?>" class="btn btn-pasar" aria-label="Pasar a Historico">
          <i class="fa-solid fa-xmark" style="font-size: 40px;"></i>
          <span>Historico</span>
        </a>
      <?php else: ?>
        <button class="btn btn-disabled" disabled aria-label="Pedido a historico ya ingresado">
          <i class="fa-solid fa-xmark" style="font-size: 40px;"></i>
          <span>Historico Solicitado</span>
        </button>
      <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="table-container">
      <?php if (!empty($lineas)): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th><a href="<?php echo $base_url . '&orden=Articulo&direccion=' . $direccion_invertida; ?>">Articulo</a></th>
              <th><a href="<?php echo $base_url . '&orden=Descripcion&direccion=' . $direccion_invertida; ?>">Descripcion</a></th>
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
              $rowClass = ($linea['Cantidad_Pedida'] < 0) ? 'text-danger' : (((float) $linea['Cantidad_Servida'] > 0) ? 'row-yellow' : '');
            ?>
              <tr class="<?php echo $rowClass; ?>">
                <td><?php echo htmlspecialchars($linea['Articulo']); ?></td>
                <td>
                  <?php echo htmlspecialchars(toUTF8($linea['Descripcion'])); ?>
                  <?php if (!empty($linea['Observacion'])): ?>
                    <br><small style="color: blue;">(<?php echo htmlspecialchars($linea['Observacion']); ?>)</small>
                  <?php endif; ?>
                </td>
                <td><?php echo number_format((float) $linea['Cantidad_Pedida'], 2, ',', '.'); ?></td>
                <td><?php echo number_format((float) $linea['Cantidad_Servida'], 2, ',', '.'); ?></td>
                <td><?php echo number_format((float) $linea['Cantidad_Restante'], 2, ',', '.'); ?></td>
                <?php
                  $stockBase = (float) $linea['Stock'];
                  $cantRest = (float) $linea['Cantidad_Restante'];
                  if ($tabla_param !== 'elim') {
                      if (isset($linea['Historico']) && $linea['Historico'] !== 'S') {
                          $stockBase += $cantRest;
                      }
                  }
                ?>
                <?php
                  $stockBackground = 'transparent';
                  $stockColor = 'black';
                  $stockClass = '';
                  if ($stockBase >= $cantRest) {
                      $stockBackground = 'green';
                      $stockColor = 'white';
                      $stockClass = 'stock-ok';
                  }
                ?>
                <td class="<?php echo $stockClass; ?>" style="background-color: <?php echo $stockBackground; ?>; color: <?php echo $stockColor; ?>;">
                  <?php echo number_format($stockBase, 2, ',', '.'); ?>
                </td>
                <td <?php if ((float) $linea['Cantidad_Pendiente_Recibir'] > 0) { echo 'style="background-color: #ccffcc; color: #006600;"'; } ?>>
                  <?php echo number_format((float) $linea['Cantidad_Pendiente_Recibir'], 2, ',', '.'); ?>
                </td>
                <td><?php echo number_format((float) $linea['Importe_Restante'], 2, ',', '.'); ?> </td>
                <td>
                  <?php if ($tabla_param === 'vcelim'): ?>
                    <i class="fas fa-trash-alt text-danger" title="Eliminado"></i>
                  <?php else: ?>
                    <?php if (!empty($linea['Historico']) && $linea['Historico'] === 'S'): ?>
                      <i class="fas fa-lock" title="Historico (S)"></i>
                    <?php else: ?>
                      <i class="fas fa-lock-open" title="No Historico (N)"></i>
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
          Numero de lineas: <?php echo $num_lineas; ?><br>
          Suma total: <?php echo number_format((float) $suma_total, 2, ',', '.'); ?>
        </div>
      <?php else: ?>
        <p>No se encontraron pedido de entrega para este cliente y seccion.</p>
      <?php endif; ?>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var botonHistorico = document.querySelector('a.btn-pasar[href*="pasar_historico.php"]');
      if (!botonHistorico) {
        return;
      }

      botonHistorico.addEventListener('click', function (event) {
        event.preventDefault();

        if (!window.confirm('Seguro que deseas solicitar pasar este pedido a historico?')) {
          return;
        }

        var url = new URL(botonHistorico.href, window.location.origin);
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'pasar_historico.php';
        form.style.display = 'none';

        var params = url.searchParams;
        ['pedido', 'cod_cliente', 'cod_seccion'].forEach(function (name) {
          if (!params.has(name)) {
            return;
          }

          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = params.get(name) || '';
          form.appendChild(input);
        });

        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_csrf_token';
        csrf.value = <?php echo json_encode(csrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        form.appendChild(csrf);

        document.body.appendChild(form);
        form.submit();
      });
    });
  </script>
</body>
</html>
