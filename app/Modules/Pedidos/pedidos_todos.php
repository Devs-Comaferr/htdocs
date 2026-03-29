<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $pageTitle; ?></title>
  <!-- Bootstrap CSS -->
  <link href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
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
    .table-container table {
      min-width: 980px;
      margin-bottom: 0;
    }
    .mobile-items {
      display: none;
    }
    
    /* CSS para pedidos históricos: gris, tachado e itálico */
    .historico, .historico * {
      color: #d3d3d3 !important;
      text-decoration: line-through !important;
      font-style: italic !important;
    }

    
    /* Aseguramos que las filas con .text-danger muestren su texto en rojo */
    .text-danger, .text-danger td {
      color: #dc3545 !important;
    }

    @media (max-width: 1024px) {
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
        margin-top: 10px;
        gap: 8px;
        align-items: center;
      }
      .mobile-sort-controls .form-control {
        width: 100%;
        margin-top: 0;
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
      .mobile-item.mobile-item-high-pending {
        background: #fff4d6;
        border-left-color: #ff9800;
      }
      .mobile-item.mobile-item-high-disponible {
        background: #e9f9ee;
        border-left-color: #28a745;
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
      .pagination {
        flex-wrap: wrap;
        gap: 4px;
      }
      .pagination a, .pagination span, .pagination strong {
        padding: 5px 8px;
      }
    }
  </style>
  <script type="text/javascript">
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
    <!-- Formulario de Filtros -->
    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="filtrosForm" class="mb-4">
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
                <div class="mobile-sort-controls d-md-none">
          <input type="hidden" name="direccion" value="<?php echo htmlspecialchars($direccion); ?>">
          <select name="orden" class="form-control" onchange="submitMobileSort(this.form)">
            <option value="Pedido" <?php echo ($orden === 'Pedido') ? 'selected' : ''; ?>>Ordenar por Pedido</option>
            <option value="Fecha_Pedido" <?php echo ($orden === 'Fecha_Pedido') ? 'selected' : ''; ?>>Ordenar por Fecha</option>
            <option value="Cliente" <?php echo ($orden === 'Cliente') ? 'selected' : ''; ?>>Ordenar por Cliente</option>
            <option value="Importe" <?php echo ($orden === 'Importe') ? 'selected' : ''; ?>>Ordenar por Importe</option>
            <option value="Articulos_Pendientes" <?php echo ($orden === 'Articulos_Pendientes') ? 'selected' : ''; ?>>Ordenar por Lineas Pdtes.</option>
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
      <table class="table table-bordered">
        <thead>
          <tr>
            <!-- Columna para el icono del camión -->
            <th></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Pedido', 'direccion' => $direccion_invertida))); ?>">Pedido</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Fecha_Pedido', 'direccion' => $direccion_invertida))); ?>">Fecha</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Cod_Cliente', 'direccion' => $direccion_invertida))); ?>">Código Cliente</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Cliente', 'direccion' => $direccion_invertida))); ?>">Nombre Cliente</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe', 'direccion' => $direccion_invertida))); ?>">Importe Pedido</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Articulos_Pendientes', 'direccion' => $direccion_invertida))); ?>">Líneas Pdtes.</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe_Pendiente', 'direccion' => $direccion_invertida))); ?>">Importe Pdte.</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe_Disponible', 'direccion' => $direccion_invertida))); ?>">Importe Disponible</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, array('orden' => 'Importe_Pdte_Recibir', 'direccion' => $direccion_invertida))); ?>">Importe Pdte. Recibir</a></th>
          </tr>
        </thead>
        <tbody id="pedidosTableBody">
          <tr>
            <td colspan="10" style="text-align: center;">Cargando pedidos...</td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Paginación -->
    <div class="mobile-items" id="pedidosMobileItems">
      <p id="pedidosMobileLoading">Cargando pedidos...</p>
    </div>

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
  <script type="text/javascript">
    (function () {
      var apiUrl = '<?= BASE_URL ?>/api/pedidos_todos.php';
      var tableBody = document.getElementById('pedidosTableBody');
      var mobileItems = document.getElementById('pedidosMobileItems');
      var mobileLoading = document.getElementById('pedidosMobileLoading');

      function escapeHtml(value) {
        if (value === null || value === undefined) {
          return '';
        }

        return String(value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function formatNumberEs(value, decimals) {
        var number = Number(value || 0);
        return number.toLocaleString('es-ES', {
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals
        }) + ' ';
      }

      function formatDateEs(value) {
        if (!value) {
          return '';
        }

        var date = new Date(value);
        if (isNaN(date.getTime())) {
          return escapeHtml(value);
        }

        return date.toLocaleDateString('es-ES');
      }

      function buildPedidoUrl(pedido) {
        var params = new URLSearchParams();
        params.set('cod_cliente', pedido.cod_cliente || '');
        params.set('pedido', pedido.Pedido || '');

        if (pedido.Cod_Seccion) {
          params.set('cod_seccion', pedido.Cod_Seccion);
        }

        return 'pedido.php?' + params.toString();
      }

      function buildMobileItemClass(pedido) {
        var className = 'mobile-item';
        var rowClass = pedido.rowClass || '';

        if (rowClass.indexOf('high-pending-row') !== -1) {
          className += ' mobile-item-high-pending';
        }
        if (rowClass.indexOf('high-disponible-row') !== -1) {
          className += ' mobile-item-high-disponible';
        }
        if (rowClass.indexOf('text-danger') !== -1) {
          className += ' text-danger';
        }
        if (pedido.isHistorico) {
          className += ' historico';
        }

        return className;
      }

      function buildNombreCliente(pedido) {
        var nombre = pedido.Cliente || '';

        if (pedido.Cod_Seccion) {
          nombre += ' - ' + (pedido.Seccion || '');
        }

        return nombre;
      }

      function renderDesktop(pedidos) {
        if (!pedidos.length) {
          tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center;">No se encontraron pedidos pendientes.</td></tr>';
          return;
        }

        tableBody.innerHTML = pedidos.map(function (pedido) {
          var pedidoUrl = buildPedidoUrl(pedido);
          var nombreCliente = buildNombreCliente(pedido);
          var observacion = pedido.ObservacionInterna
            ? '<br><span style="color: grey; font-style: italic; font-size: 0.85em;">' + escapeHtml(pedido.ObservacionInterna) + '</span>'
            : '';

          return ''
            + '<tr class="' + escapeHtml(pedido.rowClass || '') + '" data-href="' + escapeHtml(pedidoUrl) + '" onclick="window.location.href=this.getAttribute(\'data-href\')">'
            + '<td style="text-align: center;">' + (pedido.camionIcon || '') + '</td>'
            + '<td><a href="' + escapeHtml(pedidoUrl) + '">' + escapeHtml(pedido.Pedido) + '</a></td>'
            + '<td>' + formatDateEs(pedido.Fecha_Pedido) + '</td>'
            + '<td><a href="' + escapeHtml(pedidoUrl) + '">' + escapeHtml(pedido.cod_cliente) + '</a></td>'
            + '<td>' + escapeHtml(nombreCliente) + observacion + '</td>'
            + '<td style="text-align: center;">' + formatNumberEs(pedido.Importe, 2) + '</td>'
            + '<td style="text-align: center;">' + formatNumberEs(pedido.Articulos_Pendientes, 0) + '</td>'
            + '<td style="text-align: right;">' + formatNumberEs(pedido.Importe_Pendiente, 2) + '</td>'
            + '<td style="text-align: right;">' + formatNumberEs(pedido.importeDisponibleTotal, 2) + '</td>'
            + '<td style="text-align: right;">' + formatNumberEs(pedido.importePdteRecibirTotal, 2) + '</td>'
            + '</tr>';
        }).join('');
      }

      function renderMobile(pedidos) {
        if (!pedidos.length) {
          mobileItems.innerHTML = '<p>No se encontraron pedidos pendientes.</p>';
          return;
        }

        mobileItems.innerHTML = pedidos.map(function (pedido) {
          var pedidoUrl = buildPedidoUrl(pedido);
          var nombreCliente = buildNombreCliente(pedido);
          var observacion = pedido.ObservacionInterna
            ? '<div class="mobile-item-line obs-int">' + escapeHtml(pedido.ObservacionInterna) + '</div>'
            : '';

          return ''
            + '<div class="' + escapeHtml(buildMobileItemClass(pedido)) + '" data-href="' + escapeHtml(pedidoUrl) + '" onclick="window.location.href=this.getAttribute(\'data-href\')">'
            + '<div class="mobile-item-title">' + (pedido.camionIcon || '') + ' <a href="' + escapeHtml(pedidoUrl) + '">Pedido #' + escapeHtml(pedido.Pedido) + '</a></div>'
            + '<div class="mobile-item-line"><strong>Fecha:</strong> ' + formatDateEs(pedido.Fecha_Pedido) + '</div>'
            + '<div class="mobile-item-line"><strong>Cliente:</strong> <a href="' + escapeHtml(pedidoUrl) + '">' + escapeHtml(pedido.cod_cliente) + ' - ' + escapeHtml(nombreCliente) + '</a></div>'
            + observacion
            + '<div class="mobile-item-line"><strong>Importe Pedido:</strong> ' + formatNumberEs(pedido.Importe, 2) + '</div>'
            + '<div class="mobile-item-line"><strong>Líneas Pdtes.:</strong> ' + formatNumberEs(pedido.Articulos_Pendientes, 0) + '</div>'
            + '<div class="mobile-item-line"><strong>Importe Pdte.:</strong> ' + formatNumberEs(pedido.Importe_Pendiente, 2) + '</div>'
            + '<div class="mobile-item-line"><strong>Importe Disponible:</strong> ' + formatNumberEs(pedido.importeDisponibleTotal, 2) + '</div>'
            + '<div class="mobile-item-line"><strong>Importe Pdte. Recibir:</strong> ' + formatNumberEs(pedido.importePdteRecibirTotal, 2) + '</div>'
            + '</div>';
        }).join('');
      }

      function renderError() {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center;">No se pudieron cargar los pedidos.</td></tr>';
        mobileItems.innerHTML = '<p>No se pudieron cargar los pedidos.</p>';
      }

      function cargarPedidos() {
        var url = new URL(apiUrl, window.location.origin);
        var currentParams = new URLSearchParams(window.location.search);

        currentParams.forEach(function (value, key) {
          url.searchParams.set(key, value);
        });

        fetch(url.toString(), {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
          .then(function (response) {
            if (!response.ok) {
              throw new Error('HTTP ' + response.status);
            }
            return response.json();
          })
          .then(function (data) {
            var pedidos = Array.isArray(data.pedidos) ? data.pedidos : [];
            renderDesktop(pedidos);
            renderMobile(pedidos);
          })
          .catch(function () {
            renderError();
          });
      }

      if (mobileLoading) {
        mobileLoading.textContent = 'Cargando pedidos...';
      }

      cargarPedidos();
    })();
  </script>
  <!-- Bootstrap JS (opcional) -->
  <script src="<?= BASE_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app-navigation.js"></script>
</body>
</html>
