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
$pageTitle = "Calendario de Eventos";
// Incluir funciones y header (ajusta las rutas segn tu proyecto)
require_once BASE_PATH . '/app/Support/functions.php';
include(BASE_PATH . '/resources/views/layouts/header.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    /* Altura total del viewport */
    html, body {
      margin: 0;
      padding: 0;
      height: 100vh;
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
    }
    /* Contenedor principal, descontando el header fijo de 60px */
    .main-container {
      margin-top: 60px;
      height: calc(80vh - 60px);
      display: flex;
      flex-direction: row;
      gap: 10px;
      box-sizing: border-box;
    }
    /* rea izquierda: 80% para el calendario con margen interno */
    .calendar-container {
      flex: 0 0 80%;
      padding: 10px;  /* Margen interno solo para el calendario */
      box-sizing: border-box;
      background-color: #fff;
      border-radius: 8px;
    }
    /* rea derecha: 20% para la columna de botones */
    .buttons-container {
      flex: 0 0 20%;
      background: #f1f1f1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 15px;
      padding: 10px;
      box-sizing: border-box;
    }
    iframe {
      width: 100%;
      height: 100%;
      border: none;
      border-radius: 4px;
    }
    /* Estilos para los botones */
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
    /* Colores segn los ejemplos */
    .btn-pedidos-visitas { background-color: #28a745; }
    .btn-pedidos-visitas:hover { background-color: #1e7e34; }
    .btn-manual { background-color: #ffc107; }
    .btn-manual:hover { background-color: #e0a800; }
    .btn-completar { background-color: #dc3545; }
    .btn-completar:hover { background-color: #bd2130; }
    .btn-festivo { background-color: #FF5722; }
    .btn-festivo:hover { background-color: #E64A19; }
    .btn-no-laborable { background-color: #2c3e50; }
    .btn-no-laborable:hover { background-color: #1a242f; }
    /* Responsividad: para pantallas pequeas */
    @media (max-width: 1024px) {
      .main-container {
        flex-direction: column;
      }
      .calendar-container, .buttons-container {
        flex: 0 0 auto;
        width: 100%;
      }
      .buttons-container {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
      }
      .btn {
        width: 80px;
        height: 80px;
        font-size: 12px;
      }
      .btn i { font-size: 20px; }
    }
  </style>
</head>
<body>
  <div class="main-container">
    <!-- rea izquierda: iframe del calendario -->
    <div class="calendar-container">
      <iframe src="calendario.php?view=timeGridWeek&origen=semana" id="calendarioIframe"></iframe>
    </div>
    <!-- rea derecha: columna de botones -->
    <div class="buttons-container">
      <a href="visita_pedido.php" class="btn btn-pedidos-visitas" aria-label="Visita por Pedidos">
        <i class="fa-solid fa-pen-to-square"></i>
        <span>Visita por Pedidos</span>
      </a>
      <a href="visita_manual.php" class="btn btn-manual" aria-label="Visita Manual">
        <i class="fas fa-edit"></i>
        <span>Visita Manual</span>
      </a>
      <a href="completar_dia.php" class="btn btn-completar" aria-label="Completar Da">
        <i class="fas fa-check-circle"></i>
        <span>Completar Da</span>
      </a>
      <a href="festivo_local.php" class="btn btn-festivo" aria-label="Festivo Local">
        <i class="fas fa-flag"></i>
        <span>Festivo Local</span>
      </a>
      <a href="registrar_dia_no_laborable.php" class="btn btn-no-laborable" aria-label="No Laborable">
        <i class="fas fa-ban"></i>
        <span>No Laborable</span>
      </a>
    </div>
  </div>
  <script>
    function cambiarVista(vista) {
      document.getElementById('calendarioIframe').src = 'calendario.php?view=' + vista + '&origen=visita_pedido';
    }
  </script>
</body>
</html>


