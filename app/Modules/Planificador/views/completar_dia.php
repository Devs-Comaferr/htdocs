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
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';

$ui_version = 'bs5';
$ui_requires_jquery = false;

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
// Por defecto, la fecha es la actual. Si se pasa por GET, se usa esa fecha.
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$completarDiaData = obtenerDatosCompletarDia($codigo_vendedor, $fecha);
$visitas = $completarDiaData['visitas'];
$factor = $completarDiaData['factor'];
$esLaborable = (bool)($completarDiaData['es_laborable'] ?? true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista de Da - <?php echo date('d/m/Y', strtotime($fecha)); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
    <style>
      body { 
          background-color: #f4f4f4; 
          font-family: Arial, sans-serif; 
          padding: 20px; 
      }
      .container { 
          max-width: 600px; 
          margin: 0 auto; 
          background: #fff; 
          padding: 20px; 
          border-radius: 8px; 
      }
      h2 { 
          text-align: center; 
          margin-bottom: 20px; 
      }
      .timeline-container {
          position: relative;
          width: 100%;
          border: 1px solid #ddd;
          background: #fff;
          height: 480px; /* Altura ajustada */
          margin-top: 20px;
          overflow-y: auto;
      }
      .time-scale {
          position: absolute;
          left: 0;
          top: 0;
          width: 50px;
          border-right: 1px solid #ddd;
      }
      .time-scale div {
          position: absolute;
          width: 45px;
          font-size: 10px;
          color: #666;
          text-align: right;
      }
      .events-container {
          position: absolute;
          left: 60px;
          right: 10px;
          top: 0;
          bottom: 0;
      }
      .event {
          position: absolute;
          left: 5px;
          right: 5px;
          background: #007bff;
          color: #fff;
          padding: 2px 5px;
          border-radius: 4px;
          font-size: 12px;
          overflow: hidden;
      }
      .event.completed { background: #28a745; }
      .event.pending { background: #ffc107; color: #333; }
      /* Ajustes para dispositivos mÃ³viles */
      @media (max-width: 1024px) {
          .timeline-container { height: 400px; }
      }
      /* Estilo para el formulario de fecha inline y compacto */
      .fecha-form {
          text-align: center;
          margin-bottom: 20px;
      }
      .fecha-form label {
          margin-right: 5px;
          font-size: 14px;
      }
      .fecha-form input[type="date"] {
          width: 150px;
          padding: 4px 6px;
          font-size: 14px;
          display: inline-block;
      }
    </style>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
          var fechaInput = document.getElementById('fecha');
          if (!fechaInput) {
              return;
          }

          fechaInput.addEventListener('change', function () {
              var form = fechaInput.closest('form');
              if (form) {
                  form.submit();
              }
          });
      });
    </script>
</head>
<body>
<div class="container">
    <h2 class="text-center">Vista de Da - <?php echo date('d/m/Y', strtotime($fecha)); ?></h2>
    <!-- Formulario inline y compacto para cambiar la fecha -->
    <form method="GET" action="completar_dia.php" class="fecha-form d-inline-block">
        <div class="d-inline-flex align-items-center gap-2">
            <label class="mb-0" for="fecha">Cambiar Fecha:</label>
            <input type="date" name="fecha" id="fecha" class="form-control" value="<?php echo htmlspecialchars($fecha); ?>" required>
        </div>
    </form>

    <?php if (!$esLaborable): ?>
        <div class="alert alert-warning text-center" role="alert">
            El d&iacute;a seleccionado est&aacute; marcado como no laborable para este comercial.
        </div>
    <?php endif; ?>
    
    <div class="timeline-container" id="timeline">
        <!-- Escala de tiempo en la izquierda (08:00 a 20:00) -->
        <div class="time-scale">
            <?php
            $inicioTimeline = strtotime("08:00");
            for ($h = 8; $h <= 20; $h++) {
                $pos = ($h - 8) * 60 * $factor;
                echo "<div style='top: {$pos}px;'>" . str_pad($h, 2, "0", STR_PAD_LEFT) . ":00</div>";
            }
            ?>
        </div>
        <!-- Contenedor de eventos -->
        <div class="events-container">
            <?php
            for ($i = 0; $i < count($visitas); $i++) {
                $visita = $visitas[$i];
                $horaInicio = substr($visita['hora_inicio_visita'], 0, 5);
                $horaFin = substr($visita['hora_fin_visita'], 0, 5);
                $inicioVisita = strtotime($horaInicio);
                $finVisita = strtotime($horaFin);
                $offset = (($inicioVisita - $inicioTimeline) / 60) * $factor;
                $duracion = (($finVisita - $inicioVisita) / 60) * $factor;
                $estado = normalizarEstadoVisitaClave($visita['estado_visita']);
                $clase = "event";
                if ($estado == "realizada") {
                    $clase .= " completed";
                } elseif ($estado == "pendiente") {
                    $clase .= " pending";
                }
                echo "<div class='$clase' style='top: {$offset}px; height: {$duracion}px;'>";
                echo htmlspecialchars($visita['cliente']) . "<br>" . htmlspecialchars($horaInicio) . " - " . htmlspecialchars($horaFin);
                echo "</div>";
            }
            ?>
        </div>
    </div>
    <br>
    <a href="planificador_menu.php" class="btn btn-secondary">Volver al Panel</a>
</div>
</body>
</html>
<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

?>

