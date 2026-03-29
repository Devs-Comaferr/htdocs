<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';

if (!esAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Configuración';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
    }
    .config-container {
      padding: 80px 15px 20px 15px;
    }
    .config-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 15px;
    }
    .config-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      text-decoration: none;
      color: #333;
      box-shadow: 0 4px 8px rgba(0,0,0,0.08);
      transition: 0.2s ease;
    }
    .config-card:hover {
      transform: translateY(-3px);
    }
    .config-card i {
      font-size: 32px;
      margin-bottom: 10px;
    }
    .config-card h3 {
      margin: 0;
      font-size: 18px;
    }
    .card-usuarios { border-left: 6px solid #007bff; }
    .card-planificador { border-left: 6px solid #28a745; }
    .card-estadisticas { border-left: 6px solid #3fb8af; }
    .card-seguridad { border-left: 6px solid #dc3545; }
    .card-aplicacion { border-left: 6px solid #6f42c1; }

    @media (min-width: 600px) {
      .config-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (min-width: 1024px) {
      .config-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }
  </style>
</head>
<body>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

  <div class="config-container">
    <div class="config-grid">
      <a href="usuarios.php" class="config-card card-usuarios">
        <i class="fas fa-users"></i>
        <h3>Usuarios</h3>
      </a>

      <a href="#" class="config-card card-planificador">
        <i class="fas fa-calendar-alt"></i>
        <h3>Planificador</h3>
      </a>

      <a href="#" class="config-card card-estadisticas">
        <i class="fas fa-chart-bar"></i>
        <h3>Estadsticas</h3>
      </a>

      <a href="#" class="config-card card-seguridad">
        <i class="fas fa-shield-alt"></i>
        <h3>Seguridad</h3>
      </a>

      <a href="aplicacion.php" class="config-card card-aplicacion">
        <i class="fas fa-palette"></i>
        <h3>Aplicaci&oacute;n</h3>
        <p style="margin:8px 0 0 0; color:#666; font-size:14px;">Personalizaci&oacute;n del sistema</p>
      </a>
    </div>
  </div>
</body>
</html>
