<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';
requierePermiso('perm_planificador');

$conn = db();

$pageTitle = "Relacionar Pedidos con Visitas";

$ui_version = 'bs5';
$ui_requires_jquery = false;


// Obtener el código del vendedor
$codigo_vendedor = 0;
if (isset($_SESSION['codigo'])) {
  $codigo_vendedor = intval($_SESSION['codigo']);
}

// Definir la fecha mínima
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
  error_log("Error al ejecutar la consulta de pedidos: " . odbc_errormsg($conn));
  echo 'Error interno';
  return;
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

  // Añadir al array de pedidos con tiempo_promedio_min
  // Dado que PHP 5.2.3 no soporta array_merge con arrays asociativos de la misma manera, realizamos una combinación manual
  $row['tiempo_promedio_min'] = $tiempo_promedio_min;
  $pedidos[] = $row;
  $cod_ventas[] = $row['cod_venta'];
}

odbc_free_result($res_pedidos);

// Si hay pedidos, obtener el conteo de líneas
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
    error_log("Error al ejecutar la consulta de líneas: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
  }

  // Construir el mapa de cod_venta a numero_lineas
  while ($row = odbc_fetch_array($res_lineas)) {
    $numero_lineas_map[$row['cod_venta']] = $row['numero_lineas'];
  }

  odbc_free_result($res_lineas);
}


// Funciones para formatear fecha/hora al estándar HTML (YYYY-MM-DD y HH:MM)
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
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

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
  </head>

<body>
  <div class="container">

    <!-- Mostrar mensajes de éxito o error -->
    <?php
    $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
    if ($msg == 'web_ok') {
      echo '<div class="alert alert-success">Pedido Web registrado exitosamente.</div>';
    } elseif ($msg == 'visita_ok') {
      echo '<div class="alert alert-success">Visita registrada exitosamente.</div>';
    } elseif ($msg == 'tel_ok') {
      echo '<div class="alert alert-success">Teléfono registrado exitosamente.</div>';
    } elseif ($msg == 'whatsapp_ok') {
      echo '<div class="alert alert-success">WhatsApp registrado exitosamente.</div>';
    } elseif ($msg == 'email_ok') {
      echo '<div class="alert alert-success">Email registrado exitosamente.</div>';
    } elseif ($msg == 'error') {
      echo '<div class="alert alert-danger">Ocurrió un error al registrar.</div>';
    } elseif ($msg == 'error_ampliacion') {
      echo '<div class="alert alert-danger">No existe una visita previa en los últimos 5 días para realizar una ampliación.</div>';
    } elseif ($msg == 'error_formato_fecha') {
      echo '<div class="alert alert-danger">Formato de fecha inválido.</div>';
    } elseif ($msg == 'error_formato_hora') {
      echo '<div class="alert alert-danger">Formato de hora inválido.</div>';
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

            // Obtener el número de líneas desde el mapa, o 0 si no existe
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
                   <?php echo intval($numero_lineas) . " líneas"; ?>
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
                  <!-- Botón Web -->
                  <button class="btn btn-circle btn-web"
                    data-bs-toggle="modal"
                    data-bs-target="#webModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>">
                    <i class="fa fa-globe"></i>
                  </button>
                <?php } else { ?>
                  <!-- Botón Visita -->
                  <button class="btn btn-circle btn-visita"
                    data-bs-toggle="modal"
                    data-bs-target="#visitaModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>"
                    data-tiempo-promedio="<?php echo intval($pedido['tiempo_promedio_min']); ?>">
                    <i class="fa fa-calendar-check-o"></i>
                  </button>
                  <!-- Botón Teléfono -->
                  <button class="btn btn-circle btn-telefono"
                    data-bs-toggle="modal"
                    data-bs-target="#telefonoModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>">
                    <i class="fa fa-phone"></i>
                  </button>
                  <!-- Botón WhatsApp -->
                  <button class="btn btn-circle btn-whatsapp"
                    data-bs-toggle="modal"
                    data-bs-target="#whatsappModal"
                    data-cod_venta="<?php echo htmlspecialchars($pedido['cod_venta']); ?>"
                    data-cod_cliente="<?php echo htmlspecialchars($pedido['cod_cliente']); ?>"
                    data-cod_seccion="<?php echo htmlspecialchars($pedido['cod_seccion']); ?>"
                    data-fecha_visita="<?php echo $fechaInput; ?>"
                    data-hora_visita="<?php echo $horaVenta; ?>">
                    <i class="fa fa-whatsapp"></i>
                  </button>
                  <!-- Botón Email -->
                  <button class="btn btn-circle btn-email"
                    data-bs-toggle="modal"
                    data-bs-target="#emailModal"
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

  <!-- Modales aquí -->

  <div class="modal fade" id="pedidoDetalleModal" tabindex="-1" role="dialog" aria-labelledby="pedidoDetalleModalLabel">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          <h5 class="modal-title" id="pedidoDetalleModalLabel">Detalle del Pedido</h5>
        </div>
        <div class="modal-body" id="pedidoDetalleModalBody">
          Cargando...
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <h5 class="modal-title" id="visitaModalLabel">Registrar Visita</h5>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="v_cod_venta">
            <input type="hidden" name="cod_cliente" id="v_cod_cliente">
            <input type="hidden" name="cod_seccion" id="v_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="v_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="mb-3">
              <label class="form-label">Fecha de la Visita</label>
              <input type="date" class="form-control" name="fecha_visita" id="v_fecha_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Inicio de la Visita</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="v_hora_inicio_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Finalización de la Visita</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="v_hora_fin_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliación (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="form-check">
              <label class="form-label">
                <input type="checkbox" name="ampliacion" id="v_ampliacion" value="1">
                Es ampliación de una visita previa en 5 días?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliación -->
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-primary">Registrar Visita</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Teléfono -->
  <div class="modal fade" id="telefonoModal" tabindex="-1" role="dialog" aria-labelledby="telefonoModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="<?= BASE_URL ?>/registrar_telefono.php" method="POST">
          <div class="modal-header">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <h5 class="modal-title" id="telefonoModalLabel">Registrar Llamada Telefónica</h5>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="t_cod_venta">
            <input type="hidden" name="cod_cliente" id="t_cod_cliente">
            <input type="hidden" name="cod_seccion" id="t_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="t_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="mb-3">
              <label class="form-label">Fecha de la Llamada</label>
              <input type="date" class="form-control" name="fecha_visita" id="t_fecha_visita_input" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Inicio de la Llamada</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="t_hora_inicio_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Finalización de la Llamada</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="t_hora_fin_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliación (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="form-check">
              <label class="form-label">
                <input type="checkbox" name="ampliacion" id="t_ampliacion" value="1">
                Es ampliación de una visita previa en 5 días?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliación -->
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-warning">Registrar Teléfono</button>
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
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <h5 class="modal-title" id="whatsappModalLabel">Registrar Pedido WhatsApp</h5>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="w_cod_venta">
            <input type="hidden" name="cod_cliente" id="w_cod_cliente">
            <input type="hidden" name="cod_seccion" id="w_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="w_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="mb-3">
              <label class="form-label">Fecha del Pedido WhatsApp</label>
              <input type="date" class="form-control" name="fecha_visita" id="w_fecha_visita_input" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Inicio del Pedido WhatsApp</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="w_hora_inicio_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Finalización del Pedido WhatsApp</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="w_hora_fin_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliación (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="form-check">
              <label class="form-label">
                <input type="checkbox" name="ampliacion" id="w_ampliacion" value="1">
                Es ampliación de una visita previa en 5 días?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliación -->
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <h5 class="modal-title" id="emailModalLabel">Registrar Pedido Email</h5>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="e_cod_venta">
            <input type="hidden" name="cod_cliente" id="e_cod_cliente">
            <input type="hidden" name="cod_seccion" id="e_cod_seccion">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="e_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="mb-3">
              <label class="form-label">Fecha del Pedido Email</label>
              <input type="date" class="form-control" name="fecha_visita" id="e_fecha_visita_input" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Inicio del Pedido Email</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="e_hora_inicio_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Finalización del Pedido Email</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="e_hora_fin_visita" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>

            <!-- Checkbox ampliación (deshabilitado por defecto, habilitado por AJAX) -->
            <div class="form-check">
              <label class="form-label">
                <input type="checkbox" name="ampliacion" id="e_ampliacion" value="1">
                Es ampliación de una visita previa en 5 días?
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliación -->
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <h5 class="modal-title" id="webModalLabel">Registrar Pedido Web</h5>
          </div>
          <div class="modal-body">
            <input type="hidden" name="cod_venta" id="w_cod_venta_web">
            <input type="hidden" name="cod_cliente" id="w_cod_cliente_web">
            <input type="hidden" name="cod_seccion" id="w_cod_seccion_web">
            <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
            <input type="hidden" name="previous_id_visita" id="w_previous_id_visita"> <!-- Nuevo campo oculto -->

            <div class="mb-3">
              <label class="form-label">Fecha del Pedido Web</label>
              <input type="date" class="form-control" name="fecha_visita" id="w_fecha_visita_input_web" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Inicio del Pedido Web</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="w_hora_inicio_visita_web" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hora de Finalización del Pedido Web</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="w_hora_fin_visita_web" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <div id="ampliacionMensaje" class="info-message"></div> <!-- Contenedor para mensajes de ampliación -->
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-primary">Registrar Web</button>
          </div>
        </form>
      </div>
    </div>
  </div>  <script>
    function enforceTimeDifference(startSelector, endSelector, minMinutes, maxMinutes) {
      var isAdjusting = false;
      var startInput = document.querySelector(startSelector);
      var endInput = document.querySelector(endSelector);

      if (!startInput || !endInput) {
        return;
      }

      startInput.addEventListener('change', function() {
        if (isAdjusting) return;
        isAdjusting = true;

        var startTime = startInput.value;
        var endTime = endInput.value;

        if (startTime && endTime) {
          var start = parseTime(startTime);
          var end = parseTime(endTime);
          var diff = end - start;

          if (diff < minMinutes) {
            endInput.value = formatTime(start + minMinutes);
          } else if (diff > maxMinutes) {
            endInput.value = formatTime(start + maxMinutes);
          }
        }

        isAdjusting = false;
      });

      endInput.addEventListener('change', function() {
        if (isAdjusting) return;
        isAdjusting = true;

        var endTime = endInput.value;
        var startTime = startInput.value;

        if (startTime && endTime) {
          var start = parseTime(startTime);
          var end = parseTime(endTime);
          var diff = end - start;

          if (diff < minMinutes) {
            startInput.value = formatTime(end - minMinutes);
          } else if (diff > maxMinutes) {
            startInput.value = formatTime(end - maxMinutes);
          }
        }

        isAdjusting = false;
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      function getData(element, name) {
        return element ? (element.getAttribute('data-' + name) || '') : '';
      }

      function removeAmpliacionMensaje(modal) {
        var existing = modal.querySelector('.modal-body #ampliacionMensaje');
        if (existing) {
          existing.remove();
        }
      }

      function appendAmpliacionMensaje(modal, className, message) {
        removeAmpliacionMensaje(modal);
        var body = modal.querySelector('.modal-body');
        if (!body) return;
        body.insertAdjacentHTML('beforeend', '<div id="ampliacionMensaje" class="alert ' + className + '">' + message + '</div>');
      }

      function setFieldValue(modal, selector, value) {
        var element = modal.querySelector(selector);
        if (element) {
          element.value = value;
        }
      }

      function resetAmpliacion(modal, checkboxSelector, previousIdSelector) {
        var checkbox = modal.querySelector(checkboxSelector);
        var previousId = modal.querySelector(previousIdSelector);
        if (checkbox) {
          checkbox.checked = false;
          checkbox.disabled = true;
        }
        if (previousId) {
          previousId.value = '';
        }
        removeAmpliacionMensaje(modal);
      }

      document.querySelectorAll('.pedido-item').forEach(function(item) {
        item.addEventListener('click', function(event) {
          if (event.target.closest('.pedido-buttons')) {
            return;
          }

          var codVenta = getData(item, 'cod_venta');
          if (!codVenta) {
            return;
          }

          var detalleModal = document.getElementById('pedidoDetalleModal');
          var detalleLabel = document.getElementById('pedidoDetalleModalLabel');
          var detalleBody = document.getElementById('pedidoDetalleModalBody');
          if (!detalleModal || !detalleLabel || !detalleBody) {
            return;
          }

          detalleLabel.textContent = 'Detalle del Pedido #' + codVenta;
          detalleBody.innerHTML = 'Cargando...';
          bootstrap.Modal.getOrCreateInstance(detalleModal).show();

          var detailUrl = new URL('<?= BASE_URL ?>/ajax/detalle_pedido.php', window.location.origin);
          detailUrl.searchParams.set('cod_venta', codVenta);

          fetch(detailUrl.toString(), {
            credentials: 'same-origin'
          })
            .then(function(response) {
              if (!response.ok) {
                throw new Error('HTTP ' + response.status);
              }
              return response.text();
            })
            .then(function(resp) {
              detalleBody.innerHTML = resp;
            })
            .catch(function() {
              detalleBody.innerHTML = '<div class="alert alert-danger">Error al cargar el detalle del pedido.</div>';
            });
        });
      });

      enforceTimeDifference('#v_hora_inicio_visita', '#v_hora_fin_visita', 15, 300);
      enforceTimeDifference('#t_hora_inicio_visita', '#t_hora_fin_visita', 15, 300);
      enforceTimeDifference('#w_hora_inicio_visita', '#w_hora_fin_visita', 15, 300);
      enforceTimeDifference('#e_hora_inicio_visita', '#e_hora_fin_visita', 15, 300);
      enforceTimeDifference('#w_hora_inicio_visita_web', '#w_hora_fin_visita_web', 15, 300);

      function checkVisitaPreviaGeneral(modal, cod_cliente, cod_seccion, fecha, checkboxSelector, previousIdSelector) {
        var payload = new URLSearchParams();
        payload.set('cod_cliente', cod_cliente);
        payload.set('fecha_visita', fecha);
        payload.set('cod_seccion', cod_seccion !== '' ? cod_seccion : '');

        fetch('<?= BASE_URL ?>/check_visita_previa.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })
          .then(function(response) {
            if (!response.ok) {
              throw new Error('HTTP ' + response.status);
            }
            return response.text();
          })
          .then(function(resp) {
            var checkbox = modal.querySelector(checkboxSelector);
            var previousId = modal.querySelector(previousIdSelector);
            removeAmpliacionMensaje(modal);

            if (resp.indexOf('error:') === 0) {
              appendAmpliacionMensaje(modal, 'alert-danger', resp.substring(6));
              return;
            }

            if (resp.indexOf('yes:') === 0) {
              if (checkbox) {
                checkbox.disabled = false;
                checkbox.checked = true;
              }
              if (previousId) {
                previousId.value = resp.substring(4);
              }
              appendAmpliacionMensaje(modal, 'alert-success info-message', 'Hay visitas previas en los ?ltimos 5 d?as.');
              return;
            }

            if (resp === 'no') {
              if (checkbox) {
                checkbox.disabled = true;
                checkbox.checked = false;
              }
              if (previousId) {
                previousId.value = '';
              }
              appendAmpliacionMensaje(modal, 'alert-info info-message', 'No hay visitas previas en los ?ltimos 5 d?as.');
              return;
            }

            appendAmpliacionMensaje(modal, 'alert-danger', 'Respuesta inesperada del servidor: ' + resp);
          })
          .catch(function(error) {
            console.error('Error en fetch:', error);
            appendAmpliacionMensaje(modal, 'alert-danger', 'Error al verificar visitas previas.');
          });
      }

      var visitaModal = document.getElementById('visitaModal');
      if (visitaModal) {
        visitaModal.addEventListener('show.bs.modal', function(event) {
          var button = event.relatedTarget;
          if (!button) return;

          var cod_venta = getData(button, 'cod_venta');
          var cod_cliente = getData(button, 'cod_cliente');
          var cod_seccion = getData(button, 'cod_seccion');
          var fecha_visita = getData(button, 'fecha_visita');
          var hora_visita = getData(button, 'hora_visita');
          var tiempo_promedio_min = parseInt(getData(button, 'tiempo-promedio'), 10);
          if (isNaN(tiempo_promedio_min) || tiempo_promedio_min <= 0) {
            tiempo_promedio_min = 60;
          }

          var parts = hora_visita.split(':');
          var hora_visita_min = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
          var hora_inicio_visita_min = hora_visita_min - tiempo_promedio_min;
          if (hora_inicio_visita_min < 0) {
            hora_inicio_visita_min += 24 * 60;
          }

          setFieldValue(visitaModal, '#v_cod_venta', cod_venta);
          setFieldValue(visitaModal, '#v_cod_cliente', cod_cliente);
          setFieldValue(visitaModal, '#v_cod_seccion', cod_seccion);
          setFieldValue(visitaModal, '#v_fecha_visita', fecha_visita);
          setFieldValue(visitaModal, '#v_hora_inicio_visita', formatTime(hora_inicio_visita_min));
          setFieldValue(visitaModal, '#v_hora_fin_visita', hora_visita);
          resetAmpliacion(visitaModal, '#v_ampliacion', '#v_previous_id_visita');

          var fechaInput = visitaModal.querySelector('#v_fecha_visita');
          if (fechaInput) {
            checkVisitaPreviaGeneral(visitaModal, cod_cliente, cod_seccion, fechaInput.value, '#v_ampliacion', '#v_previous_id_visita');
            fechaInput.onchange = function() {
              checkVisitaPreviaGeneral(visitaModal, cod_cliente, cod_seccion, fechaInput.value, '#v_ampliacion', '#v_previous_id_visita');
            };
          }
        });
      }

      function manejarModal(modalId, prefix) {
        var modal = document.getElementById(modalId);
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function(event) {
          var button = event.relatedTarget;
          if (!button) return;

          var cod_venta = getData(button, 'cod_venta');
          var cod_cliente = getData(button, 'cod_cliente');
          var cod_seccion = getData(button, 'cod_seccion');
          var fecha_visita = getData(button, 'fecha_visita');
          var hora_visita = getData(button, 'hora_visita');

          var parts = hora_visita.split(':');
          var hora_visita_min = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
          var hora_inicio_visita_min = hora_visita_min - 15;
          if (hora_inicio_visita_min < 0) {
            hora_inicio_visita_min += 24 * 60;
          }

          setFieldValue(modal, '#' + prefix + '_cod_venta', cod_venta);
          setFieldValue(modal, '#' + prefix + '_cod_cliente', cod_cliente);
          setFieldValue(modal, '#' + prefix + '_cod_seccion', cod_seccion);
          setFieldValue(modal, '#' + prefix + '_fecha_visita_input', fecha_visita);
          setFieldValue(modal, '#' + prefix + '_hora_inicio_visita', formatTime(hora_inicio_visita_min));
          setFieldValue(modal, '#' + prefix + '_hora_fin_visita', hora_visita);
          resetAmpliacion(modal, '#' + prefix + '_ampliacion', '#' + prefix + '_previous_id_visita');

          var fechaInput = modal.querySelector('#' + prefix + '_fecha_visita_input');
          if (fechaInput) {
            checkVisitaPreviaGeneral(modal, cod_cliente, cod_seccion, fechaInput.value, '#' + prefix + '_ampliacion', '#' + prefix + '_previous_id_visita');
            fechaInput.onchange = function() {
              checkVisitaPreviaGeneral(modal, cod_cliente, cod_seccion, fechaInput.value, '#' + prefix + '_ampliacion', '#' + prefix + '_previous_id_visita');
            };
          }
        });
      }

      manejarModal('telefonoModal', 't');
      manejarModal('whatsappModal', 'w');
      manejarModal('emailModal', 'e');

      var webModal = document.getElementById('webModal');
      if (webModal) {
        webModal.addEventListener('show.bs.modal', function(event) {
          var button = event.relatedTarget;
          if (!button) return;

          var cod_venta = getData(button, 'cod_venta');
          var cod_cliente = getData(button, 'cod_cliente');
          var cod_seccion = getData(button, 'cod_seccion');
          var fecha_visita = getData(button, 'fecha_visita');
          var hora_visita = getData(button, 'hora_visita');

          var parts = hora_visita.split(':');
          var hora_visita_min = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
          var hora_inicio_visita_min = hora_visita_min - 15;
          if (hora_inicio_visita_min < 0) {
            hora_inicio_visita_min += 24 * 60;
          }

          setFieldValue(webModal, '#w_cod_venta_web', cod_venta);
          setFieldValue(webModal, '#w_cod_cliente_web', cod_cliente);
          setFieldValue(webModal, '#w_cod_seccion_web', cod_seccion);
          setFieldValue(webModal, '#w_fecha_visita_input_web', fecha_visita);
          setFieldValue(webModal, '#w_hora_inicio_visita_web', formatTime(hora_inicio_visita_min));
          setFieldValue(webModal, '#w_hora_fin_visita_web', hora_visita);
          resetAmpliacion(webModal, '#w_ampliacion', '#w_previous_id_visita');
        });
      }
    });
  </script>
<script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app-utils.js"></script>
</body>

</html>





