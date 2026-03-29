<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';
requierePermiso('perm_planificador');

$conn = db();

$pageTitle = "Relacionar Pedidos con Visitas";
include(BASE_PATH . '/resources/views/layouts/header.php'); // Asegúrate de que header.php solo contiene el header sin etiquetas HTML


// Obtener el cÃ³digo del vendedor
$codigo_vendedor = 0;
if (isset($_SESSION['codigo'])) {
  $codigo_vendedor = intval($_SESSION['codigo']);
}

// Definir la fecha mÃ­nima
$fecha_minima = '2025-01-01';

// Construir la consulta modificada para incluir tiempo_promedio_visita
$sql_pedidos = "
SELECT 
    h.cod_venta,
    h.cod_cliente,
    h.cod_seccion,
    c.nombre_comercial,
    h.nombre_seccion,
    h.fecha_venta,
    h.hora_venta,
    h.importe,
    a.observacion_interna,
    h.cod_pedido_web,
    h.cod_vendedor,
    h.nombre_vendedor,
    h.cod_comisionista,
    cazc.tiempo_promedio_visita
FROM hist_ventas_cabecera h
JOIN clientes c ON h.cod_cliente = c.cod_cliente
LEFT JOIN anexo_ventas_cabecera a ON h.cod_anexo = a.cod_anexo
LEFT JOIN cmf_visita_pedidos vp ON h.cod_venta = vp.cod_venta
LEFT JOIN cmf_asignacion_zonas_clientes cazc 
    ON h.cod_cliente = cazc.cod_cliente AND h.cod_seccion = cazc.cod_seccion
WHERE vp.cod_venta IS NULL
  AND h.cod_comisionista = $codigo_vendedor
  AND h.tipo_venta = 1
  AND h.fecha_venta >= '$fecha_minima'
ORDER BY 
    h.fecha_venta ASC,
    h.hora_venta ASC
";

// Ejecutar la consulta
$res_pedidos = odbc_exec($conn, $sql_pedidos);
if (!$res_pedidos) {
  die("Error al ejecutar la consulta de pedidos: " . odbc_errormsg($conn));
}

$pedidos = array();
$cod_ventas = array(); // Para almacenar los cod_venta

while ($row = odbc_fetch_array($res_pedidos)) {
  // Verificar y convertir tiempo_promedio_visita
  if (empty($row['tiempo_promedio_visita'])) {
    $tiempo_promedio_min = 60; // Valor predeterminado en minutos
  } else {
    // Convertir horas decimales a minutos
    $tiempo_promedio_min = floatval($row['tiempo_promedio_visita']) * 60;
  }

  // AÃ±adir al array de pedidos con tiempo_promedio_min
  // Dado que PHP 5.2.3 no soporta array_merge con arrays asociativos de la misma manera, realizamos una combinaciÃ³n manual
  $row['tiempo_promedio_min'] = $tiempo_promedio_min;
  $pedidos[] = $row;
  $cod_ventas[] = $row['cod_venta'];
}

odbc_free_result($res_pedidos);

// Si hay pedidos, obtener el conteo de lÃ­neas
$numero_lineas_map = array();

if (!empty($cod_ventas)) {
  // Escapar cada cod_venta para seguridad
  $cod_ventas_esc = array();
  foreach ($cod_ventas as $cv) {
    $cod_ventas_esc[] = intval($cv);
  }

  // Crear una lista de cod_venta separados por comas
  $cod_ventas_str = implode(',', $cod_ventas_esc);

  // Construir la segunda consulta
  $sql_lineas = "
    SELECT cod_venta, COUNT(*) AS numero_lineas
    FROM hist_ventas_linea
    WHERE tipo_venta = 1
      AND cod_venta IN ($cod_ventas_str)
    GROUP BY cod_venta
    ";

  // Ejecutar la segunda consulta
  $res_lineas = odbc_exec($conn, $sql_lineas);
  if (!$res_lineas) {
    die("Error al ejecutar la consulta de lÃ­neas: " . odbc_errormsg($conn));
  }

  // Construir el mapa de cod_venta a numero_lineas
  while ($row = odbc_fetch_array($res_lineas)) {
    $numero_lineas_map[$row['cod_venta']] = $row['numero_lineas'];
  }

  odbc_free_result($res_lineas);
}


// Funciones para formatear fecha/hora al estÃ¡ndar HTML (YYYY-MM-DD y HH:MM)
function formatoFecha($fechaSql)
{
  return date('Y-m-d', strtotime($fechaSql));
}
function formatoHora($horaSql)
{
  return date('H:i', strtotime($horaSql));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 3.3.7 -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
  <!-- Font Awesome 4.7 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">

  <style>
    /* Tu CSS aqu */
    body {
      padding-top: 80px; /* En escritorio dejamos 80px (header + margen) */
      background-color: #f8f9fa;
    }

    .content-wrapper {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
    }

    .calendario-container,
    .pedidos-container {
      box-sizing: border-box;
    }

    @media (min-width: 992px) {
      .calendario-container {
        width: 55%;
        padding-right: 10px;
      }

      .pedidos-container {
        width: 40%;
      }
    }

    @media (max-width: 991px) {

      .calendario-container,
      .pedidos-container {
        width: 100%;
        margin-bottom: 20px;
      }
    }

    .pedido-item {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
      cursor: pointer;
    }

    .nombre-comercial {
      font-size: 1.3em;
      font-weight: bold;
      margin-bottom: 5px;
      text-transform: uppercase;
    }

    .nombre-seccion {
      font-size: 1em;
      font-style: italic;
      margin-bottom: 5px;
      text-transform: uppercase;
    }

    .pedido-info {
      display: flex;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }

    .pedido-info>div {
      margin-right: 20px;
      margin-bottom: 5px;
    }

    .pedido-buttons {
      display: flex;
      gap: 10px;
    }

    .btn-circle {
      border-radius: 50%;
      width: 45px;
      height: 45px;
      font-size: 18px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 5px;
    }

    .btn-visita {
      background-color: #28a745;
      color: #fff;
    }

    .btn-telefono {
      background-color: #ffc107;
      color: #fff;
    }

    .btn-whatsapp {
      background-color: #25D366;
      color: #fff;
    }

    .btn-email {
      background-color: #17a2b8;
      color: #fff;
    }

    .btn-web {
      background-color: #007bff;
      color: #fff;
    }

    /* Estilos para el nombre del vendedor */
    .nombre-vendedor {
      font-style: italic;
      color: #6c757d;
      /* Color gris */
      margin-top: 5px;
    }

    .nombre-vendedor i {
      margin-right: 5px;
    }

    /* Estilos para los mensajes de error en los modales */
    .error-message {
      color: red;
      font-style: italic;
      margin-top: 10px;
    }

    /* Estilos para los mensajes informativos en los modales */
    .info-message {
      margin-top: 10px;
    }
  </style>
  <script type="text/javascript">
    // Funciones para manejar la apertura y cierre de modales
// Funciones de ayuda para la validaciÃ³n de tiempos
function enforceTimeDifference(startSelector, endSelector, minMinutes, maxMinutes) {
      var isAdjusting = false;

      $(startSelector).on('change', function() {
        if (isAdjusting) return;
        isAdjusting = true;

        var startTime = $(this).val();
        var endTime = $(endSelector).val();

        if (startTime && endTime) {
          var start = parseTime(startTime);
          var end = parseTime(endTime);
          var diff = end - start;

          if (diff < minMinutes) {
            end = start + minMinutes;
            $(endSelector).val(formatTime(end));
          } else if (diff > maxMinutes) {
            end = start + maxMinutes;
            $(endSelector).val(formatTime(end));
          }
        }

        isAdjusting = false;
      });

      $(endSelector).on('change', function() {
        if (isAdjusting) return;
        isAdjusting = true;

        var endTime = $(this).val();
        var startTime = $(startSelector).val();

        if (startTime && endTime) {
          var start = parseTime(startTime);
          var end = parseTime(endTime);
          var diff = end - start;

          if (diff < minMinutes) {
            start = end - minMinutes;
            $(startSelector).val(formatTime(start));
          } else if (diff > maxMinutes) {
            start = end - maxMinutes;
            $(startSelector).val(formatTime(start));
          }
        }

        isAdjusting = false;
      });
    }
  </script>
</head>

<body>
  <div class="container">

    <!-- Mostrar mensajes de Ã©xito o error -->
    <?php
    $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
    if ($msg == 'web_ok') {
      echo '<div class="alert alert-success">Pedido Web registrado exitosamente.</div>';
    } elseif ($msg == 'visita_ok') {
      echo '<div class="alert alert-success">Visita registrada exitosamente.</div>';
    } elseif ($msg == 'tel_ok') {
      echo '<div class="alert alert-success">TelÃ©fono registrado exitosamente.</div>';
    } elseif ($msg == 'whatsapp_ok') {
      echo '<div class="alert alert-success">WhatsApp registrado exitosamente.</div>';
    } elseif ($msg == 'email_ok') {
      echo '<div class="alert alert-success">Email registrado exitosamente.</div>';
    } elseif ($msg == 'error') {
      echo '<div class="alert alert-danger">OcurriÃ³ un error al registrar.</div>';
    } elseif ($msg == 'error_ampliacion') {
      echo '<div class="alert alert-danger">No existe una visita previa en los Ãºltimos 5 dÃ­as para realizar una ampliaciÃ³n.</div>';
    } elseif ($msg == 'error_formato_fecha') {
      echo '<div class="alert alert-danger">Formato de fecha invÃ¡lido.</div>';
    } elseif ($msg == 'error_formato_hora') {
      echo '<div class="alert alert-danger">Formato de hora invÃ¡lido.</div>';
    } elseif ($msg == 'error_min_tiempo') {
      echo '<div class="alert alert-danger">La diferencia entre la hora de inicio y la hora de fin debe ser de al menos 15 minutos y no exceder las 5 horas.</div>';
    }
    ?>

    <hr>
    <div class="content-wrapper">
      <!-- Calendario -->
      <div class="calendario-container">
      <iframe src="calendario.php?view=timeGridDay&origen=pedidos_visitas" width="100%" height="480" style="border:none;"></iframe>
      </div>

      <!-- Pedidos Pendientes -->
      <div class="pedidos-container">
        <h3>Pedidos Pendientes (<?php echo count($pedidos); ?>)</h3>
        <?php if (!empty($pedidos)) { ?>
          <?php foreach ($pedidos as $pedido) {
            // Fecha y hora formateadas para inputs type="date" y type="time"
            $fechaInput = formatoFecha($pedido['fecha_venta']);
            $horaVenta  = formatoHora($pedido['hora_venta']);

            // Obtener el nÃºmero de lÃ­neas desde el mapa, o 0 si no existe
            $numero_lineas = isset($numero_lineas_map[$pedido['cod_venta']]) ? $numero_lineas_map[$pedido['cod_venta']] : 0;
          ?>
            <div class="pedido-item"
              data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
              data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>">
              <div class="nombre-comercial">
                <?php echo htmlspecialchars($pedido['nombre_comercial']); ?>
              </div>

              <div class="nombre-seccion">
                <?php if (!empty($pedido['nombre_seccion'])) {
                  echo ' ' . htmlspecialchars($pedido['nombre_seccion']);
                } ?>
              </div>

              <div class="pedido-info">
                <div>
                  <?php
                  if (!empty($pedido['cod_pedido_web'])) {
                    echo ' ';
                  } else {
                    echo ' ';
                  }
                  echo htmlspecialchars($pedido['cod_venta']);
                  ?>
                </div>
                <div>
                   <?php echo date("d/m/Y", strtotime($pedido['fecha_venta'])); ?>
                </div>
                <div>
                   <?php echo date("H:i", strtotime($pedido['hora_venta'])); ?>
                </div>
                <div>
                   <?php echo number_format($pedido['importe'], 2, ',', '.') . " "; ?>
                </div>
                <div>
                   <?php echo intval($numero_lineas) . " lÃ­neas"; ?>
                </div>
              </div>
              <?php if (!empty($pedido['observacion_interna'])) { ?>
                <div style="font-style:italic; color:#007bff;">
                  <i class="fa fa-pencil"></i>
                  <?php echo htmlspecialchars($pedido['observacion_interna']); ?><br><br>
                </div>
              <?php } ?>

              <!-- Mostrar nombre_vendedor si cod_vendedor es distinto de cod_comisionista -->
              <?php if ($pedido['cod_vendedor'] != $pedido['cod_comisionista']) { ?>
                <div class="nombre-vendedor">
                  <i class="fa fa-user"></i> <?php echo htmlspecialchars($pedido['nombre_vendedor']); ?><br><br>
                </div>
              <?php } ?>

              <div class="pedido-buttons">
                <?php if (!empty($pedido['cod_pedido_web'])) { ?>
                  <!-- BotÃ³n Web -->
                  <button class="btn btn-circle btn-web"
                    data-toggle="modal"
                    data-target="#webModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>">
                    <i class="fa fa-globe"></i>
                  </button>
                <?php } else { ?>
                  <!-- BotÃ³n Visita -->
                  <button class="btn btn-circle btn-visita"
                    data-toggle="modal"
                    data-target="#visitaModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>"
                    data-tiempo-promedio="<?php echo intval($pedido['tiempo_promedio_min']); ?>">
                    <i class="fa fa-calendar-check-o"></i>
                  </button>
                  <!-- BotÃ³n TelÃ©fono -->
                  <button class="btn btn-circle btn-telefono"
                    data-toggle="modal"
                    data-target="#telefonoModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>">
                    <i class="fa fa-phone"></i>
                  </button>
                  <!-- BotÃ³n WhatsApp -->
                  <button class="btn btn-circle btn-whatsapp"
                    data-toggle="modal"
                    data-target="#whatsappModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>">
                    <i class="fa fa-whatsapp"></i>
                  </button>
                  <!-- BotÃ³n Email -->
                  <button class="btn btn-circle btn-email"
                    data-toggle="modal"
                    data-target="#emailModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>">
                    <i class="fa fa-envelope"></i>
                  </button>
                <?php } ?>
              </div>
            </div>
          <?php } ?>
        <?php } else { ?>
          <div class="alert alert-info text-center">No hay pedidos pendientes.</div>
        <?php } ?>

      </div>

    </div>
  </div>

  <!-- Modales aquÃ­ -->

  <div class="modal fade" id="pedidoDetalleModal" tabindex="-1" role="dialog" aria-labelledby="pedidoDetalleModalLabel">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          <h4 class="modal-title" id="pedidoDetalleModalLabel">Detalle del Pedido</h4>
        </div>
        <div class="modal-body" id="pedidoDetalleModalBody">
          Cargando...
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Visita -->
  <div class="modal fade" id="visitaModal" tabindex="-1" role="dialog" aria-labelledby="visitaModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="<?= BASE_URL ?>/registrar_visita.php" method="POST">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            <h4 class="modal-title" id="visitaModalLabel">Registrar Visita</h4>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="v_cod_venta">
            <input type="hidden" name="cod_cliente" id="v_cod_cliente">
            <input type="hidden" name="cod_seccion" id="v_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="v_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="form-group">
              <label>Fecha de la Visita</label>
              <input type="date" class="form-control" name="fecha_visita" id="v_fecha_visita" required>
            </div>
            <div class="form-group">
              <label>Hora de Inicio de la Visita</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="v_hora_inicio_visita" required>
            </div>
            <div class="form-group">
              <label>Hora de FinalizaciÃ³n de la Visita</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="v_hora_fin_visita" required>
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliaciÃ³n (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="checkbox">
              <label>
                <input type="checkbox" name="ampliacion" id="v_ampliacion" value="1">
                Es ampliaciÃ³n de una visita previa en 5 dÃ­as?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliaciÃ³n -->
            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-primary">Registrar Visita</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal TelÃ©fono -->
  <div class="modal fade" id="telefonoModal" tabindex="-1" role="dialog" aria-labelledby="telefonoModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="<?= BASE_URL ?>/registrar_telefono.php" method="POST">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            <h4 class="modal-title" id="telefonoModalLabel">Registrar Llamada TelefÃ³nica</h4>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="t_cod_venta">
            <input type="hidden" name="cod_cliente" id="t_cod_cliente">
            <input type="hidden" name="cod_seccion" id="t_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="t_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="form-group">
              <label>Fecha de la Llamada</label>
              <input type="date" class="form-control" name="fecha_visita" id="t_fecha_visita_input" required>
            </div>
            <div class="form-group">
              <label>Hora de Inicio de la Llamada</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="t_hora_inicio_visita" required>
            </div>
            <div class="form-group">
              <label>Hora de FinalizaciÃ³n de la Llamada</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="t_hora_fin_visita" required>
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliaciÃ³n (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="checkbox">
              <label>
                <input type="checkbox" name="ampliacion" id="t_ampliacion" value="1">
                Es ampliaciÃ³n de una visita previa en 5 dÃ­as?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliaciÃ³n -->
            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-warning">Registrar TelÃ©fono</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal WhatsApp -->
  <div class="modal fade" id="whatsappModal" tabindex="-1" role="dialog" aria-labelledby="whatsappModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="<?= BASE_URL ?>/registrar_whatsapp.php" method="POST">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            <h4 class="modal-title" id="whatsappModalLabel">Registrar Pedido WhatsApp</h4>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="w_cod_venta">
            <input type="hidden" name="cod_cliente" id="w_cod_cliente">
            <input type="hidden" name="cod_seccion" id="w_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="w_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="form-group">
              <label>Fecha del Pedido WhatsApp</label>
              <input type="date" class="form-control" name="fecha_visita" id="w_fecha_visita_input" required>
            </div>
            <div class="form-group">
              <label>Hora de Inicio del Pedido WhatsApp</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="w_hora_inicio_visita" required>
            </div>
            <div class="form-group">
              <label>Hora de FinalizaciÃ³n del Pedido WhatsApp</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="w_hora_fin_visita" required>
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliaciÃ³n (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="checkbox">
              <label>
                <input type="checkbox" name="ampliacion" id="w_ampliacion" value="1">
                Es ampliaciÃ³n de una visita previa en 5 dÃ­as?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliaciÃ³n -->
            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-success">Registrar WhatsApp</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Email -->
  <div class="modal fade" id="emailModal" tabindex="-1" role="dialog" aria-labelledby="emailModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="<?= BASE_URL ?>/registrar_email.php" method="POST">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            <h4 class="modal-title" id="emailModalLabel">Registrar Pedido Email</h4>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="e_cod_venta">
            <input type="hidden" name="cod_cliente" id="e_cod_cliente">
            <input type="hidden" name="cod_seccion" id="e_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="e_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="form-group">
              <label>Fecha del Pedido Email</label>
              <input type="date" class="form-control" name="fecha_visita" id="e_fecha_visita_input" required>
            </div>
            <div class="form-group">
              <label>Hora de Inicio del Pedido Email</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="e_hora_inicio_visita" required>
            </div>
            <div class="form-group">
              <label>Hora de FinalizaciÃ³n del Pedido Email</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="e_hora_fin_visita" required>
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliaciÃ³n (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="checkbox">
              <label>
                <input type="checkbox" name="ampliacion" id="e_ampliacion" value="1">
                Es ampliaciÃ³n de una visita previa en 5 dÃ­as?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliaciÃ³n -->
            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-info">Registrar Email</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Web -->
  <div class="modal fade" id="webModal" tabindex="-1" role="dialog" aria-labelledby="webModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="<?= BASE_URL ?>/registrar_web.php" method="POST">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            <h4 class="modal-title" id="webModalLabel">Registrar Pedido Web</h4>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="w_cod_venta_web">
            <input type="hidden" name="cod_cliente" id="w_cod_cliente_web">
            <input type="hidden" name="cod_seccion" id="w_cod_seccion_web">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="w_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="form-group">
              <label>Fecha del Pedido Web</label>
              <input type="date" class="form-control" name="fecha_visita" id="w_fecha_visita_input_web" required>
            </div>
            <div class="form-group">
              <label>Hora de Inicio del Pedido Web</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="w_hora_inicio_visita_web" required>
            </div>
            <div class="form-group">
              <label>Hora de FinalizaciÃ³n del Pedido Web</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="w_hora_fin_visita_web" required>
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliaciÃ³n -->
            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-primary">Registrar Web</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- jQuery + Bootstrap JS -->
  <script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.11.3.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/js/bootstrap.min.js"></script>

  <script>
    // Funciones de ayuda para la validaciÃ³n de tiempos
function enforceTimeDifference(startSelector, endSelector, minMinutes, maxMinutes) {
      var isAdjusting = false;

      $(startSelector).on('change', function() {
        if (isAdjusting) return;
        isAdjusting = true;

        var startTime = $(this).val();
        var endTime = $(endSelector).val();

        if (startTime && endTime) {
          var start = parseTime(startTime);
          var end = parseTime(endTime);
          var diff = end - start;

          if (diff < minMinutes) {
            end = start + minMinutes;
            $(endSelector).val(formatTime(end));
          } else if (diff > maxMinutes) {
            end = start + maxMinutes;
            $(endSelector).val(formatTime(end));
          }
        }

        isAdjusting = false;
      });

      $(endSelector).on('change', function() {
        if (isAdjusting) return;
        isAdjusting = true;

        var endTime = $(this).val();
        var startTime = $(startSelector).val();

        if (startTime && endTime) {
          var start = parseTime(startTime);
          var end = parseTime(endTime);
          var diff = end - start;

          if (diff < minMinutes) {
            start = end - minMinutes;
            $(startSelector).val(formatTime(start));
          } else if (diff > maxMinutes) {
            start = end - maxMinutes;
            $(startSelector).val(formatTime(start));
          }
        }

        isAdjusting = false;
      });
    }

    $(document).ready(function() {
      // Abrir detalle al pulsar la tarjeta (excepto en los botones de accion).
      $('.pedido-item').on('click', function(event) {
        if ($(event.target).closest('.pedido-buttons').length > 0) {
          return;
        }

        var codVenta = $(this).data('cod_venta');
        if (!codVenta) {
          return;
        }

        $('#pedidoDetalleModalLabel').text('Detalle del Pedido #' + codVenta);
        $('#pedidoDetalleModalBody').html('Cargando...');
        $('#pedidoDetalleModal').modal('show');

        $.get('<?= BASE_URL ?>/ajax/detalle_pedido.php', { cod_venta: codVenta }, function(resp) {
          $('#pedidoDetalleModalBody').html(resp);
        }).fail(function() {
          $('#pedidoDetalleModalBody').html('<div class="alert alert-danger">Error al cargar el detalle del pedido.</div>');
        });
      });

      // Aplicar la funciÃ³n de validaciÃ³n a cada par de campos de hora en los modales
      enforceTimeDifference('#v_hora_inicio_visita', '#v_hora_fin_visita', 15, 300); // Visita Modal
      enforceTimeDifference('#t_hora_inicio_visita', '#t_hora_fin_visita', 15, 300); // TelÃ©fono Modal
      enforceTimeDifference('#w_hora_inicio_visita', '#w_hora_fin_visita', 15, 300); // WhatsApp Modal
      enforceTimeDifference('#e_hora_inicio_visita', '#e_hora_fin_visita', 15, 300); // Email Modal
      enforceTimeDifference('#w_hora_inicio_visita_web', '#w_hora_fin_visita_web', 15, 300); // Web Modal

      // FunciÃ³n para realizar la comprobaciÃ³n de visitas previas utilizando la fecha del input del modal
      function checkVisitaPreviaGeneral(modal, inputSelector, cod_cliente, cod_seccion, fecha, checkboxSelector, previousIdSelector) {
        var cod_seccion_val = cod_seccion !== '' ? cod_seccion : '';
        $.ajax({
          url: '<?= BASE_URL ?>/check_visita_previa.php',
          type: 'POST',
          data: {
            cod_cliente: cod_cliente,
            fecha_visita: fecha,
            cod_seccion: cod_seccion_val
          },
          dataType: 'text',
          success: function(resp) {
            console.log('Respuesta AJAX:', resp);
            modal.find('.modal-body #ampliacionMensaje').remove();
            if (resp.indexOf("error:") === 0) {
              var errorMsg = resp.substring(6);
              modal.find('.modal-body').append('<div id="ampliacionMensaje" class="alert alert-danger">' + errorMsg + '</div>');
              return;
            }
            if (resp.indexOf("yes:") === 0) {
              var id_visita = resp.substring(4);
              // Se activa (checked) automÃ¡ticamente la casilla si hay visita previa
              modal.find(checkboxSelector).prop('disabled', false).prop('checked', true);
              modal.find(previousIdSelector).val(id_visita);
              modal.find('.modal-body').append('<div id="ampliacionMensaje" class="alert alert-success info-message">Hay visitas previas en los Ãºltimos 5 dÃ­as.</div>');
            } else if (resp === "no") {
              modal.find(checkboxSelector).prop('disabled', true).prop('checked', false);
              modal.find(previousIdSelector).val('');
              modal.find('.modal-body').append('<div id="ampliacionMensaje" class="alert alert-info info-message">No hay visitas previas en los Ãºltimos 5 dÃ­as.</div>');
            } else {
              modal.find('.modal-body').append('<div id="ampliacionMensaje" class="alert alert-danger">Respuesta inesperada del servidor: ' + resp + '</div>');
            }
          },
          error: function(xhr, status, error) {
            console.error("Error en AJAX:", status, error);
            modal.find('.modal-body #ampliacionMensaje').remove();
            modal.find('.modal-body').append('<div id="ampliacionMensaje" class="alert alert-danger">Error al verificar visitas previas.</div>');
          }
        });
      }

      // Modal Visita
      $('#visitaModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget); // BotÃ³n que abriÃ³ el modal
        var cod_venta = button.data('cod_venta');
        var cod_cliente = button.data('cod_cliente');
        var cod_seccion = button.data('cod_seccion');
        var fecha_visita = button.data('fecha_visita'); // YYYY-MM-DD
        var hora_visita = button.data('hora_visita'); // HH:MM
        var tiempo_promedio_min = parseInt(button.data('tiempo-promedio')); // en minutos

        // Validar tiempo_promedio_min
        if (isNaN(tiempo_promedio_min) || tiempo_promedio_min <= 0) {
          tiempo_promedio_min = 60; // Valor predeterminado en minutos
        }

        // Convertir hora_visita a minutos
        var parts = hora_visita.split(':');
        var hora = parseInt(parts[0], 10);
        var minuto = parseInt(parts[1], 10);
        var hora_visita_min = hora * 60 + minuto;

        // Calcular hora_inicio_visita = hora_visita_min - tiempo_promedio_min
        var hora_inicio_visita_min = hora_visita_min - tiempo_promedio_min;

        // Manejar desbordamientos de hora
        if (hora_inicio_visita_min < 0) {
          hora_inicio_visita_min += 24 * 60;
        }

        var hora_inicio_visita = formatTime(hora_inicio_visita_min);
        var hora_fin_visita = hora_visita;

        var modal = $(this);
        modal.find('#v_cod_venta').val(cod_venta);
        modal.find('#v_cod_cliente').val(cod_cliente);
        modal.find('#v_cod_seccion').val(cod_seccion);
        modal.find('#v_fecha_visita').val(fecha_visita);
        modal.find('#v_hora_inicio_visita').val(hora_inicio_visita);
        modal.find('#v_hora_fin_visita').val(hora_fin_visita);

        // Asignar valores ocultos y limpiar checkbox y mensajes previos
        modal.find('#v_previous_id_visita').val('');
        modal.find('#v_ampliacion').prop('checked', false).prop('disabled', true);
        modal.find('.modal-body #ampliacionMensaje').remove();

        // Usar el valor actual del input de fecha del modal para la comprobaciÃ³n
        var currentFecha = modal.find('#v_fecha_visita').val();
        checkVisitaPreviaGeneral(modal, '#v_fecha_visita', cod_cliente, cod_seccion, currentFecha, '#v_ampliacion', '#v_previous_id_visita');

        // Si el usuario cambia la fecha, se vuelve a hacer la comprobaciÃ³n
        modal.find('#v_fecha_visita').off('change').on('change', function() {
          var nuevaFecha = $(this).val();
          modal.find('.modal-body #ampliacionMensaje').remove();
          checkVisitaPreviaGeneral(modal, '#v_fecha_visita', cod_cliente, cod_seccion, nuevaFecha, '#v_ampliacion', '#v_previous_id_visita');
        });
      });

      // FunciÃ³n para manejar los modales con tiempo estimado fijo de 15 minutos (TelÃ©fono, WhatsApp, Email)
      function manejarModal(modalId, prefix) {
        $('#' + modalId).on('show.bs.modal', function(event) {
          var button = $(event.relatedTarget); // BotÃ³n que abriÃ³ el modal
          var cod_venta = button.data('cod_venta');
          var cod_cliente = button.data('cod_cliente');
          var cod_seccion = button.data('cod_seccion');
          var fecha_visita = button.data('fecha_visita'); // YYYY-MM-DD
          var hora_visita = button.data('hora_visita'); // HH:MM

          // Convertir hora_visita a minutos
          var parts = hora_visita.split(':');
          var hora = parseInt(parts[0], 10);
          var minuto = parseInt(parts[1], 10);
          var hora_visita_min = hora * 60 + minuto;

          // Calcular hora_inicio_visita = hora_visita_min - 15 minutos
          var hora_inicio_visita_min = hora_visita_min - 15;

          // Manejar desbordamientos de hora
          if (hora_inicio_visita_min < 0) {
            hora_inicio_visita_min += 24 * 60;
          }

          var hora_inicio_visita = formatTime(hora_inicio_visita_min);
          var hora_fin_visita = hora_visita;

          var modal = $(this);
          modal.find('#' + prefix + '_cod_venta').val(cod_venta);
          modal.find('#' + prefix + '_cod_cliente').val(cod_cliente);
          modal.find('#' + prefix + '_cod_seccion').val(cod_seccion);
          modal.find('#' + prefix + '_fecha_visita_input').val(fecha_visita);
          modal.find('#' + prefix + '_hora_inicio_visita').val(hora_inicio_visita);
          modal.find('#' + prefix + '_hora_fin_visita').val(hora_fin_visita);

          // Asignar valores ocultos y limpiar checkbox y mensajes previos
          modal.find('#' + prefix + '_previous_id_visita').val('');
          modal.find('#' + prefix + '_ampliacion').prop('checked', false).prop('disabled', true);
          modal.find('.modal-body #ampliacionMensaje').remove();

          // Usar el valor actual del input de fecha para la comprobaciÃ³n
          var currentFecha = modal.find('#' + prefix + '_fecha_visita_input').val();
          checkVisitaPreviaGeneral(modal, '#' + prefix + '_fecha_visita_input', cod_cliente, cod_seccion, currentFecha, '#' + prefix + '_ampliacion', '#' + prefix + '_previous_id_visita');

          // Si el usuario cambia la fecha, se vuelve a hacer la comprobaciÃ³n
          modal.find('#' + prefix + '_fecha_visita_input').off('change').on('change', function() {
            var nuevaFecha = $(this).val();
            modal.find('.modal-body #ampliacionMensaje').remove();
            checkVisitaPreviaGeneral(modal, '#' + prefix + '_fecha_visita_input', cod_cliente, cod_seccion, nuevaFecha, '#' + prefix + '_ampliacion', '#' + prefix + '_previous_id_visita');
          });
        });
      }

      // Manejar Modal TelÃ©fono (tiempo_estimado = 15 minutos)
      manejarModal('telefonoModal', 't');

      // Manejar Modal WhatsApp (tiempo_estimado = 15 minutos)
      manejarModal('whatsappModal', 'w');

      // Manejar Modal Email (tiempo_estimado = 15 minutos)
      manejarModal('emailModal', 'e');

      // Manejar Modal Web (tiempo_estimado = 15 minutos, editable; sin verificaciÃ³n AJAX de ampliaciÃ³n)
      $('#webModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget); // BotÃ³n que abriÃ³ el modal
        var cod_venta = button.data('cod_venta');
        var cod_cliente = button.data('cod_cliente');
        var cod_seccion = button.data('cod_seccion');
        var fecha_visita = button.data('fecha_visita'); // YYYY-MM-DD
        var hora_visita = button.data('hora_visita'); // HH:MM

        // Convertir hora_visita a minutos
        var parts = hora_visita.split(':');
        var hora = parseInt(parts[0], 10);
        var minuto = parseInt(parts[1], 10);
        var hora_visita_min = hora * 60 + minuto;

        // Calcular hora_inicio_visita = hora_visita_min - 15 minutos
        var hora_inicio_visita_min = hora_visita_min - 15;

        // Manejar desbordamientos de hora
        if (hora_inicio_visita_min < 0) {
          hora_inicio_visita_min += 24 * 60;
        }

        var hora_inicio_visita = formatTime(hora_inicio_visita_min);
        var hora_fin_visita = hora_visita;

        var modal = $(this);
        modal.find('#w_cod_venta_web').val(cod_venta);
        modal.find('#w_cod_cliente_web').val(cod_cliente);
        modal.find('#w_cod_seccion_web').val(cod_seccion);
        modal.find('#w_fecha_visita_input_web').val(fecha_visita);
        modal.find('#w_hora_inicio_visita_web').val(hora_inicio_visita);
        modal.find('#w_hora_fin_visita_web').val(hora_fin_visita);

        // Asignar valores ocultos y limpiar checkbox (no aplica para Web)
        modal.find('#w_previous_id_visita').val('');
        modal.find('#w_ampliacion').prop('checked', false).prop('disabled', true);
        modal.find('.modal-body #ampliacionMensaje').remove();
      });

    });
  </script>
<script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app-utils.js"></script>
</body>

</html>


