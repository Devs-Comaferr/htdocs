<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
// visita_sin_venta.php


$conn = db();

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;

// Manejar el envo del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estado_visita = 'Realizada';
    $cod_vendedor = $codigo_vendedor;
    $cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;
    $cod_seccion = isset($_POST['cod_seccion']) ? intval($_POST['cod_seccion']) : 0;
    $fecha_visita = isset($_POST['fecha_visita']) ? $_POST['fecha_visita'] : '';
    $hora_inicio_visita = isset($_POST['hora_inicio_visita']) ? $_POST['hora_inicio_visita'] : '';
    $hora_fin_visita = isset($_POST['hora_fin_visita']) ? $_POST['hora_fin_visita'] : '';
    $observaciones = isset($_POST['observaciones']) ? $_POST['observaciones'] : '';
    
    // Validar datos
    $errors = array();
    if ($cod_cliente === 0 || $cod_seccion === 0 || empty($fecha_visita) || empty($hora_inicio_visita) || empty($hora_fin_visita)) {
        $errors[] = "Todos los campos son obligatorios.";
    }
    
    // Validar formatos
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_visita)) {
        $errors[] = "Formato de fecha invÃƒÆ’Ã‚Â¡lido.";
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $hora_inicio_visita) || !preg_match('/^\d{2}:\d{2}$/', $hora_fin_visita)) {
        $errors[] = "Formato de hora invÃƒÆ’Ã‚Â¡lido.";
    }
    
    // Validar diferencia de tiempo
    if (!empty($hora_inicio_visita) && !empty($hora_fin_visita)) {
        list($inicio_h, $inicio_m) = explode(':', $hora_inicio_visita);
        list($fin_h, $fin_m) = explode(':', $hora_fin_visita);
    
        $inicio_total = intval($inicio_h) * 60 + intval($inicio_m);
        $fin_total = intval($fin_h) * 60 + intval($fin_m);
        $diff = $fin_total - $inicio_total;
    
        if ($diff < 15 || $diff > 300) { // 15 minutos y 300 minutos (5 horas)
            $errors[] = "La diferencia entre la hora de inicio y la hora de fin debe ser de al menos 15 minutos y no exceder las 5 horas.";
        }
    }
    
    if (empty($errors)) {
        // Insertar en la tabla cmf_visitas_comerciales
        $sql = "INSERT INTO cmf_visitas_comerciales 
                (estado_visita, cod_vendedor, cod_cliente, cod_seccion, fecha_visita, hora_inicio_visita, hora_fin_visita, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = odbc_prepare($conn, $sql);
        if (odbc_execute($stmt, array($estado_visita, $cod_vendedor, $cod_cliente, $cod_seccion, $fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones))) {
            // Redireccionar con mensaje de xito
            header("Location: index.php?msg=visita_realizada");
            exit;
        } else {
            $errors[] = "Ocurri un error al registrar la visita.";
        }
        odbc_free_result($stmt);
    }
    odbc_close($conn);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Visita sin Venta</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 3.3.7 -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
  <!-- Font Awesome 4.7 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
</head>
<body>
<div class="container">
  <h2>Visita sin Venta</h2>
  
  <?php
  if (!empty($errors)) {
      echo '<div class="alert alert-danger"><ul>';
      foreach ($errors as $error) {
          echo '<li>' . htmlspecialchars($error) . '</li>';
      }
      echo '</ul></div>';
  }
  ?>
  
  <form action="visita_sin_venta.php" method="POST">
    <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">
    
    <!-- Campo de BÃƒÆ’Ã‚Âºsqueda de Cliente -->
    <div class="form-group">
      <label for="nombre_comercial">Buscar Cliente</label>
      <input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial" placeholder="Escribe el nombre comercial del cliente" required>
      <input type="hidden" id="cod_cliente" name="cod_cliente">
    </div>

    <!-- Lista Desplegable de Secciones (se cargar dinmicamente) -->
    <div class="form-group" id="seccion_container" style="display: none;">
      <label for="cod_seccion">Seleccionar SecciÃƒÆ’Ã‚Â³n</label>
      <select class="form-control" id="cod_seccion" name="cod_seccion" required>
        <!-- Opciones se cargarÃƒÆ’Ã‚Â¡n vÃƒÆ’Ã‚Â­a AJAX -->
      </select>
    </div>
    
    <!-- Campos de Fecha y Hora -->
    <div class="form-group">
      <label for="fecha_visita">Fecha de la Visita</label>
      <input type="date" class="form-control" id="fecha_visita" name="fecha_visita" required>
    </div>
    <div class="form-group">
      <label for="hora_inicio_visita">Hora de Inicio de la Visita</label>
      <input type="time" class="form-control" id="hora_inicio_visita" name="hora_inicio_visita" required>
    </div>
    <div class="form-group">
      <label for="hora_fin_visita">Hora de FinalizaciÃƒÆ’Ã‚Â³n de la Visita</label>
      <input type="time" class="form-control" id="hora_fin_visita" name="hora_fin_visita" required>
    </div>
    <div class="form-group">
      <label for="observaciones">Observaciones</label>
      <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
    </div>
    
    <button type="submit" class="btn btn-danger">Registrar Visita sin Venta</button>
    <a href="index.php" class="btn btn-default">Cancelar</a>
  </form>
</div>

<!-- jQuery + Bootstrap JS -->
<script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.11.3.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/js/bootstrap.min.js"></script>

<!-- Scripts para Autocompletado y ValidaciÃƒÆ’Ã‚Â³n -->
<script>
$(document).ready(function() {
    // Autocompletado para Buscar Cliente
    $("#nombre_comercial").autocomplete({
        source: "buscar_cliente.php",
        minLength: 2,
        select: function(event, ui) {
            $("#cod_cliente").val(ui.item.cod_cliente);
            cargarSecciones(ui.item.cod_cliente);
        },
        change: function(event, ui) {
            if (!ui.item) {
                $("#cod_cliente").val('');
                $("#seccion_container").hide();
                $("#cod_seccion").html('');
            }
        }
    });

    function cargarSecciones(cod_cliente) {
        $.ajax({
            url: '<?= BASE_URL ?>/obtener_secciones_pedidos_visitas.php', // Usar tu archivo existente
            type: 'GET', // Segn cmo est implementado tu archivo
            dataType: 'json',
            data: { cod_cliente: cod_cliente },
            success: function(secciones) {
                if (secciones.length > 0) {
                    var opciones = '<option value="">Selecciona una secciÃƒÆ’Ã‚Â³n</option>';
                    $.each(secciones, function(index, seccion) {
                        opciones += '<option value="' + seccion.cod_seccion + '">' + seccion.nombre_seccion + '</option>';
                    });
                    $("#cod_seccion").html(opciones);
                    $("#seccion_container").show();
                } else {
                    $("#seccion_container").hide();
                    $("#cod_seccion").html('');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al obtener secciones:", error);
                $("#seccion_container").hide();
                $("#cod_seccion").html('');
            }
        });
    }

    // Validar diferencia de tiempo
$("#hora_inicio_visita, #hora_fin_visita").on('change', function() {
        var inicio = $("#hora_inicio_visita").val();
        var fin = $("#hora_fin_visita").val();
        if (inicio && fin) {
            var inicio_total = parseTime(inicio);
            var fin_total = parseTime(fin);
            var diff = fin_total - inicio_total;
            if (diff < 15 || diff > 300) {
                alert("La diferencia entre la hora de inicio y la hora de fin debe ser de al menos 15 minutos y no exceder las 5 horas.");
                // Opcional: Resetear las horas o ajustarlas automÃƒÆ’Ã‚Â¡ticamente
            }
        }
    });
});
</script>
<script src="<?= BASE_URL ?>/assets/js/app-utils.js"></script>
</body>
</html>
