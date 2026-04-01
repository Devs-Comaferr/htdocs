<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $pageTitle; ?></title>
  <!-- Bootstrap CSS -->
    <!-- noUiSlider CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.css" />
  
  <style type="text/css">
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
    form { margin-bottom: 20px; }
    form label { font-weight: bold; margin-bottom: 5px; display: block; }
    .d-flex > *:not(:last-child) { margin-right: 10px; }
    .btn i { vertical-align: middle; margin-right: 5px; }
    .pagination {
      display: flex;
      justify-content: center;
      margin-top: 20px;
    }
    .pagination a, .pagination span, .pagination strong {
      margin: 0 3px;
      text-decoration: none;
      padding: 5px 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      color: #007BFF;
    }
    .pagination strong { background-color: #007BFF; color: #fff; }
    .pagination a:hover { background-color: #0056b3; color: #fff; }
    .pagination .dots { padding: 5px 10px; color: #555; }
    table thead th {
      background-color: #343a40 !important;
      color: #fff !important;
      text-align: center !important;
    }
    table thead th a {
      color: #fff !important;
      text-decoration: none !important;
      pointer-events: auto;
    }
    table tbody td:nth-child(1),
    table tbody td:nth-child(2),
    table tbody td:nth-child(3),
    table tbody td:nth-child(4) { text-align: center; }
    table tbody td:nth-child(6),
    table tbody td:nth-child(7),
    table tbody td:nth-child(8),
    table tbody td:nth-child(9) { text-align: right; }
    table tbody tr { cursor: pointer; }
    table tbody tr a,
    .mobile-item a {
      color: inherit;
      text-decoration: none;
    }
    table tbody tr a:hover,
    .mobile-item a:hover {
      color: inherit;
      text-decoration: none;
    }
    table tbody tr:hover { background-color: #f0f0f0; }
    table tbody tr.high-pending-row td {
      background-color: #fff4d6 !important;
    }
    table tbody tr.high-pending-row:hover td {
      background-color: #ffe8b3 !important;
    }
    table tbody tr.high-disponible-row td {
      background-color: #e9f9ee !important;
    }
    table tbody tr.high-disponible-row:hover td {
      background-color: #d7f3df !important;
    }
    .table-container {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    .mobile-items {
      display: none;
    }
    .table-container table {
      min-width: 980px;
      margin-bottom: 0;
    }
    #globalHeader,
    #globalMobileAppbar {
      -webkit-transform: translateZ(0);
      transform: translateZ(0);
      -webkit-backface-visibility: hidden;
      backface-visibility: hidden;
      will-change: transform;
    }

    @media (max-width: 1024px) {
      html, body {
        overflow-x: hidden;
      }
      body {
        padding: 10px 10px 78px !important;
      }
      .container {
        margin-top: 70px;
        padding: 12px;
      }
      #filtrosForm .d-flex {
        display: block !important;
      }
      #filtrosForm .d-flex .form-control {
        width: 100%;
      }
      #filtrosForm .d-flex .btn {
        width: 100%;
        margin-left: 0 !important;
        margin-top: 8px;
        justify-content: center;
      }
      .mobile-sort-controls {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        align-items: center;
      }
      .mobile-sort-controls .form-control {
        width: 100%;
      }
      .mobile-sort-controls .mobile-direction-btn {
        width: 42px;
        height: 38px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
      }
      .desktop-table {
        display: none;
      }
      .mobile-items {
        display: block;
      }
      .mobile-item {
        background: #fff;
        border: 1px solid #ddd;
        border-left: 5px solid #343a40;
        border-radius: 8px;
        padding: 10px;
        margin-bottom: 10px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        cursor: pointer;
      }
      .mobile-item.mobile-item-high-disponible {
        background: #e9f9ee;
        border-left-color: #28a745;
      }
      .mobile-item.mobile-item-high-pending {
        background: #fff4d6;
        border-left-color: #ff9800;
      }
      .mobile-item-title {
        font-weight: 700;
        font-size: 14px;
        margin-bottom: 6px;
      }
      .mobile-item-line {
        font-size: 12px;
        line-height: 1.35;
        margin-bottom: 3px;
      }
      .mobile-item-line strong {
        color: #495057;
      }
      .mobile-item .obs-int {
        color: #6c757d;
        font-style: italic;
      }
      .table thead th,
      .table tbody td {
        padding: 6px 8px;
        font-size: 12px;
        white-space: nowrap;
      }
      .pagination {
        flex-wrap: wrap;
        gap: 4px;
      }
      .pagination a, .pagination span, .pagination strong {
        padding: 5px 8px;
      }
    }
  </style>
  
  <!-- jQuery, noUiSlider y wNumb -->
    <script src="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/vendor/wnumb/wNumb.min.js"></script>
  
  <script type="text/javascript">
    function timestampToISO(ts) {
      var date = new Date(ts * 1000);
      var day = ('0' + date.getDate()).slice(-2);
      var month = ('0' + (date.getMonth() + 1)).slice(-2);
      var year = date.getFullYear();
      return year + '-' + month + '-' + day;
    }
    function timestampToSpanish(ts) {
      var date = new Date(ts * 1000);
      var day = ('0' + date.getDate()).slice(-2);
      var month = ('0' + (date.getMonth() + 1)).slice(-2);
      var year = date.getFullYear();
      return day + '/' + month + '/' + year;
    }
    $(function(){
      var slider = document.getElementById('date-slider');
      var minTimestamp = Date.parse("2024-10-01") / 1000;
      var maxTimestamp = Date.now() / 1000;
      var initialStart = Date.parse($("#start_date").val()) / 1000;
      var initialEnd   = Date.parse($("#end_date").val()) / 1000;
      
      noUiSlider.create(slider, {
          start: [initialStart, initialEnd],
          connect: true,
          range: { 'min': minTimestamp, 'max': maxTimestamp },
          step: 86400,
          tooltips: [wNumb({ decimals: 0, edit: timestampToSpanish }), wNumb({ decimals: 0, edit: timestampToSpanish })]
      });
      
      slider.noUiSlider.on('change', function(values, handle) {
          var startDate = timestampToISO(parseFloat(values[0]));
          var endDate   = timestampToISO(parseFloat(values[1]));
          $("#start_date").val(startDate);
          $("#end_date").val(endDate);
          $("#filtrosForm").submit();
      });
    });
    
    function submitMobileSort(form) {
      if (!form) return;
      form.submit();
    }

    function toggleMobileDirection(form) {
      if (!form) return;
      var input = form.querySelector('input[name="direccion"]');
      if (!input) return;
      input.value = (input.value === 'ASC') ? 'DESC' : 'ASC';
      form.submit();
    }
  </script>
</head>
<body>
  <div class="container">
    <!-- Formulario de Filtros con Slider -->
    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="filtrosForm" class="mb-4">
      <div class="mb-3">
        <label for="date-slider" class="form-label">Rango de Fechas:</label>
        <div id="date-slider"></div>
        <input type="hidden" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        <input type="hidden" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
      </div>
      <div class="mb-3">
        <div class="d-flex align-items-center">
          <input type="text" id="cliente" name="cliente" class="form-control" value="<?php echo htmlspecialchars($cliente_filtro); ?>" placeholder="Buscar por código o nombre del cliente..." />
          <button type="submit" class="btn btn-primary ms-2 d-flex align-items-center">
            <i class="fas fa-search"></i> <span class="ms-1">Filtrar</span>
          </button>
          <button type="button" class="btn btn-danger ms-2 d-flex align-items-center" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>';">
            <i class="fas fa-trash-alt"></i> <span class="ms-1">Limpiar</span>
          </button>
        </div>
      </div>
            <div class="mb-3 d-md-none">
        <div class="mobile-sort-controls">
          <input type="hidden" name="direccion" value="<?php echo htmlspecialchars($direccion); ?>">
          <select name="orden" class="form-control" onchange="submitMobileSort(this.form)">
            <option value="Pedido" <?php echo ($orden === 'Pedido') ? 'selected' : ''; ?>>Ordenar por Pedido</option>
            <option value="Fecha_Pedido" <?php echo ($orden === 'Fecha_Pedido') ? 'selected' : ''; ?>>Ordenar por Fecha</option>
            <option value="Cliente" <?php echo ($orden === 'Cliente') ? 'selected' : ''; ?>>Ordenar por Cliente</option>
            <option value="Importe" <?php echo ($orden === 'Importe') ? 'selected' : ''; ?>>Ordenar por Importe</option>
            <option value="Articulos_Pendientes" <?php echo ($orden === 'Articulos_Pendientes') ? 'selected' : ''; ?>>Ordenar por Líneas Pdtes.</option>
            <option value="Importe_Pendiente" <?php echo ($orden === 'Importe_Pendiente') ? 'selected' : ''; ?>>Ordenar por Importe Pdte.</option>
            <option value="Importe_Disponible" <?php echo ($orden === 'Importe_Disponible') ? 'selected' : ''; ?>>Ordenar por Importe Disponible</option>
            <option value="Importe_Pdte_Recibir" <?php echo ($orden === 'Importe_Pdte_Recibir') ? 'selected' : ''; ?>>Ordenar por Importe Pdte. Recibir</option>
          </select>
          <button type="button" class="btn btn-outline-secondary mobile-direction-btn" onclick="toggleMobileDirection(this.form)" aria-label="Cambiar direccion de orden"><?php echo ($direccion === 'ASC') ? '&uarr;' : '&darr;'; ?></button>
        </div>
      </div>
    </form>
    
    <!-- Tabla de Resultados -->
    <div class="table-container desktop-table">
      <?php if (!empty($pedidos)): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <!-- Columna para el icono (camión o eliminado) -->
              <th></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Pedido', 'direccion' => $direccion_invertida))); ?>">Pedido</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Fecha_Pedido', 'direccion' => $direccion_invertida))); ?>">Fecha</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Cod_Cliente', 'direccion' => $direccion_invertida))); ?>">Código Cliente</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Cliente', 'direccion' => $direccion_invertida))); ?>">Nombre Cliente</a></th>
              <!-- Nueva columna Importe -->
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe', 'direccion' => $direccion_invertida))); ?>">Importe del Pedido</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Articulos_Pendientes', 'direccion' => $direccion_invertida))); ?>">Líneas Pdtes.</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe_Pendiente', 'direccion' => $direccion_invertida))); ?>">Importe Pdte.</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe_Disponible', 'direccion' => $direccion_invertida))); ?>">Importe Disponible</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe_Pdte_Recibir', 'direccion' => $direccion_invertida))); ?>">Importe Pdte. Recibir</a></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pedidos as $pedido): ?>
              <?php
                $pedidoUrl = buildPedidoUrlFaltas($pedido);
                // Para cada pedido, recalcular los importes para visualización
                $pedidoId = addslashes($pedido['Pedido']);
                $importeDisponibleTotal = 0;
                $importePdteRecibirTotal = 0;
                $sql_lineas = "
                    SELECT 
                        hvl.cantidad AS Cantidad_Pedida,
                        (hvl.cantidad - ISNULL(SUM(elv.cantidad),0)) AS Cantidad_Restante,
                        hvl.precio AS Precio,
                        ISNULL(
                          (SELECT TOP 1 s.existencias 
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
                        $cantidadPedida   = (float)$linea['Cantidad_Pedida'];
                        $cantidadRestante = (float)$linea['Cantidad_Restante'];
                        $precio           = (float)$linea['Precio'];
                        
                        $importeRestanteLinea = $cantidadRestante * $precio;
                        $price_unit = ($cantidadRestante > 0 && $importeRestanteLinea > 0) ? $importeRestanteLinea / $cantidadRestante : 0;
                        
                        $stockDisponible = (float)$linea['Stock'];
                        if ($stockDisponible < 0) {
                            $stockDisponible = 0;
                        }
                        
                        $servibleStock = min($stockDisponible, $cantidadRestante);
                        $importeDisponibleLinea = $servibleStock * $price_unit;
                        
                        $resto = $cantidadRestante - $servibleStock;
                        $pdteRecibirValor = (float)$linea['PdteRecibir'];
                        $pdteRecibirDisponible = min($resto, $pdteRecibirValor);
                        $importePdteRecibirLinea = $pdteRecibirDisponible * $price_unit;
                        
                        $importeDisponibleTotal += $importeDisponibleLinea;
                        $importePdteRecibirTotal += $importePdteRecibirLinea;
                    }
                }
                
                $impDisponible_formatted = number_format($importeDisponibleTotal, 2, ',', '.') . " ";
                $impPdteRecibir_formatted = number_format($importePdteRecibirTotal, 2, ',', '.') . " ";
              ?>
              <?php
                $rowClass = '';
                if ((float)$pedido['Importe_Pendiente'] > 70) {
                    $rowClass .= ' high-pending-row';
                }
                if ($importeDisponibleTotal > 70) {
                    $rowClass .= ' high-disponible-row';
                }
              ?>
              <tr class="<?php echo trim($rowClass); ?>" onclick="window.location.href=<?php echo htmlspecialchars(json_encode($pedidoUrl), ENT_QUOTES); ?>">
                <?php
                  // Mostrar icono según el valor de la columna 'tabla'
                  if ($pedido['tabla'] == 'vcelim') {
                      $camionIcon = '<i class="fas fa-trash-alt text-danger"></i>';
                  } else {
                      // Icono del camión si existe registro en entrega_lineas_venta
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
                  }
                ?>
                <td style="text-align: center;"><?php echo $camionIcon; ?></td>
                <td><a href="<?php echo htmlspecialchars($pedidoUrl); ?>"><?php echo htmlspecialchars($pedido['Pedido']); ?></a></td>
                <td><?php echo date("d/m/Y", strtotime($pedido['Fecha_Pedido'])); ?></td>
                <td><a href="<?php echo htmlspecialchars($pedidoUrl); ?>"><?php echo htmlspecialchars($pedido['cod_cliente']); ?></a></td>
                <td>
                  <a href="<?php echo htmlspecialchars($pedidoUrl); ?>"><?php echo htmlspecialchars(toUTF8($pedido['Cliente'])); ?></a>
                  <?php if (!empty($pedido['ObservacionInterna'])): ?>
                    <br>
                    <span style="color: grey; font-style: italic; font-size: 0.85em;">
                      <?php echo htmlspecialchars(toUTF8($pedido['ObservacionInterna'])); ?>
                    </span>
                  <?php endif; ?>
                </td>
                <!-- Nueva columna Importe -->
                <td style="text-align: right;"><?php echo number_format($pedido['Importe'], 2, ',', '.') . " "; ?></td>
                <td style="text-align: center;"><?php echo number_format((float)$pedido['Articulos_Pendientes'], 0, ',', '.'); ?></td>
                <td style="text-align: right;"><?php echo number_format($pedido['Importe_Pendiente'], 2, ',', '.') . " "; ?></td>
                <td style="text-align: right;"><?php echo $impDisponible_formatted; ?></td>
                <td style="text-align: right;"><?php echo $impPdteRecibir_formatted; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No se encontraron pedidos pendientes.</p>
      <?php endif; ?>
    </div>

    <!-- Vista móvil en formato item/card con todos los campos -->
    <div class="mobile-items">
      <?php if (!empty($pedidos)): ?>
        <?php foreach ($pedidos as $pedido): ?>
          <?php
            $pedidoUrl = buildPedidoUrlFaltas($pedido);
            $pedidoId = addslashes($pedido['Pedido']);
            $importeDisponibleTotal = 0;
            $importePdteRecibirTotal = 0;
            $sql_lineas_mobile = "
                SELECT 
                    hvl.cantidad AS Cantidad_Pedida,
                    (hvl.cantidad - ISNULL(SUM(elv.cantidad),0)) AS Cantidad_Restante,
                    hvl.precio AS Precio,
                    ISNULL(
                      (SELECT TOP 1 s.existencias 
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
            $result_lineas_mobile = odbc_exec($conn, $sql_lineas_mobile);
            if ($result_lineas_mobile) {
                while ($linea = odbc_fetch_array($result_lineas_mobile)) {
                    $cantidadRestante = (float)$linea['Cantidad_Restante'];
                    $precio           = (float)$linea['Precio'];

                    $importeRestanteLinea = $cantidadRestante * $precio;
                    $price_unit = ($cantidadRestante > 0 && $importeRestanteLinea > 0) ? $importeRestanteLinea / $cantidadRestante : 0;

                    $stockDisponible = (float)$linea['Stock'];
                    if ($stockDisponible < 0) {
                        $stockDisponible = 0;
                    }

                    $servibleStock = min($stockDisponible, $cantidadRestante);
                    $importeDisponibleLinea = $servibleStock * $price_unit;

                    $resto = $cantidadRestante - $servibleStock;
                    $pdteRecibirValor = (float)$linea['PdteRecibir'];
                    $pdteRecibirDisponible = min($resto, $pdteRecibirValor);
                    $importePdteRecibirLinea = $pdteRecibirDisponible * $price_unit;

                    $importeDisponibleTotal += $importeDisponibleLinea;
                    $importePdteRecibirTotal += $importePdteRecibirLinea;
                }
            }

            $impDisponible_formatted = number_format($importeDisponibleTotal, 2, ',', '.') . " ";
            $impPdteRecibir_formatted = number_format($importePdteRecibirTotal, 2, ',', '.') . " ";

            if ($pedido['tabla'] == 'vcelim') {
                $estadoIcon = '<i class="fas fa-trash-alt text-danger"></i>';
            } else {
                $queryEntrega = "
                    SELECT TOP 1 cod_venta_origen 
                    FROM integral.dbo.entrega_lineas_venta 
                    WHERE cod_venta_origen = '" . addslashes($pedido['Pedido']) . "' 
                      AND tipo_venta_origen = 1
                ";
                $rsEntrega = odbc_exec($conn, $queryEntrega);
                $estadoIcon = ($rsEntrega && odbc_fetch_row($rsEntrega))
                    ? '<i class="fas fa-truck text-success"></i>'
                    : '';
            }
          ?>
          <?php
            $mobileItemClass = 'mobile-item';
            if ((float)$pedido['Importe_Pendiente'] > 70) {
                $mobileItemClass .= ' mobile-item-high-pending';
            }
            if ($importeDisponibleTotal > 70) {
                $mobileItemClass .= ' mobile-item-high-disponible';
            }
          ?>
          <div class="<?php echo $mobileItemClass; ?>" onclick="window.location.href=<?php echo htmlspecialchars(json_encode($pedidoUrl), ENT_QUOTES); ?>">
            <div class="mobile-item-title">
              <?php echo $estadoIcon; ?> <a href="<?php echo htmlspecialchars($pedidoUrl); ?>">Pedido #<?php echo htmlspecialchars($pedido['Pedido']); ?></a>
            </div>
            <div class="mobile-item-line"><strong>Fecha:</strong> <?php echo date("d/m/Y", strtotime($pedido['Fecha_Pedido'])); ?></div>
            <div class="mobile-item-line"><strong>Cliente:</strong> <a href="<?php echo htmlspecialchars($pedidoUrl); ?>"><?php echo htmlspecialchars($pedido['cod_cliente']); ?> - <?php echo htmlspecialchars(toUTF8($pedido['Cliente'])); ?></a></div>
            <?php if (!empty($pedido['ObservacionInterna'])): ?>
              <div class="mobile-item-line obs-int"><?php echo htmlspecialchars(toUTF8($pedido['ObservacionInterna'])); ?></div>
            <?php endif; ?>
            <div class="mobile-item-line"><strong>Importe Pedido:</strong> <?php echo number_format($pedido['Importe'], 2, ',', '.') . " "; ?></div>
            <div class="mobile-item-line"><strong>Líneas Pdtes.:</strong> <?php echo number_format((float)$pedido['Articulos_Pendientes'], 0, ',', '.'); ?></div>
            <div class="mobile-item-line"><strong>Importe Pdte.:</strong> <?php echo number_format($pedido['Importe_Pendiente'], 2, ',', '.') . " "; ?></div>
            <div class="mobile-item-line"><strong>Importe Disponible:</strong> <?php echo $impDisponible_formatted; ?></div>
            <div class="mobile-item-line"><strong>Importe Pdte. Recibir:</strong> <?php echo $impPdteRecibir_formatted; ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No se encontraron pedidos pendientes.</p>
      <?php endif; ?>
    </div>
    
    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
      <div class="d-flex justify-content-center">
        <div class="pagination">
          <?php
          if ($page > 1) {
              $prevQuery = http_build_query(array_merge($_GET, array('page' => $page - 1)));
              echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $prevQuery . '">&laquo; Anterior</a>';
          }
          
          $adjacents = 2;
          if ($totalPages <= (1 + ($adjacents * 2))) {
              for ($p = 1; $p <= $totalPages; $p++) {
                  if ($p == $page) {
                      echo '<strong>' . $p . '</strong>';
                  } else {
                      $pageQuery = http_build_query(array_merge($_GET, array('page' => $p)));
                      echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $pageQuery . '">' . $p . '</a>';
                  }
              }
          } else {
              if ($page > ($adjacents + 1)) {
                  $firstQuery = http_build_query(array_merge($_GET, array('page' => 1)));
                  echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $firstQuery . '">1</a>';
                  if ($page > ($adjacents + 2)) {
                      echo '<span class="dots">...</span>';
                  }
              }
              $start = max(1, $page - $adjacents);
              $end = min($totalPages, $page + $adjacents);
              for ($p = $start; $p <= $end; $p++) {
                  if ($p == $page) {
                      echo '<strong>' . $p . '</strong>';
                  } else {
                      $pageQuery = http_build_query(array_merge($_GET, array('page' => $p)));
                      echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $pageQuery . '">' . $p . '</a>';
                  }
              }
              if ($page < ($totalPages - $adjacents)) {
                  if ($page < ($totalPages - $adjacents - 1)) {
                      echo '<span class="dots">...</span>';
                  }
                  $lastQuery = http_build_query(array_merge($_GET, array('page' => $totalPages)));
                  echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $lastQuery . '">' . $totalPages . '</a>';
              }
          }
          if ($page < $totalPages) {
              $nextQuery = http_build_query(array_merge($_GET, array('page' => $page + 1)));
              echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $nextQuery . '">Siguiente &raquo;</a>';
          }
          ?>
        </div>
      </div>
    <?php endif; ?>
    
  </div>
  <!-- Bootstrap JS (opcional) -->
  <script src="<?= BASE_URL ?>/assets/js/app-navigation.js"></script>
</body>
</html>
<?php
if ($conn) {
}
?>




