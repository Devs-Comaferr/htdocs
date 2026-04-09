<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';

if (!esAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

header('Content-Type: text/html; charset=utf-8');
$pageTitle = 'Configuracion';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
  <style>
    :root {
      --bg: #eef2f6;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --line: #d8e0ea;
      --shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
      --violet: #6f42c1;
      --violet-soft: #f4ecff;
      --blue: #1570ef;
      --blue-soft: #eaf2ff;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
      color: var(--text);
    }
    .config-container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 88px 16px 32px 16px;
    }
    .hero {
      margin-bottom: 22px;
    }
    .hero h1 {
      margin: 0 0 8px 0;
      font-size: 30px;
    }
    .hero p {
      margin: 0;
      color: var(--muted);
      font-size: 15px;
    }
    .config-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 320px));
      gap: 18px;
      justify-content: start;
    }
    .config-card {
      display: block;
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 22px 20px;
      text-decoration: none;
      color: var(--text);
      box-shadow: var(--shadow);
      transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    }
    .config-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12);
      border-color: #c6d3e1;
    }
    .card-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 18px;
    }
    .card-icon {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      flex-shrink: 0;
    }
    .card-icon.app {
      background: var(--violet-soft);
      color: var(--violet);
    }
    .card-icon.users {
      background: var(--blue-soft);
      color: var(--blue);
    }
    .card-icon.import {
      background: #ecfccb;
      color: #3f6212;
    }
    .card-arrow {
      color: #94a3b8;
      font-size: 18px;
    }
    .config-card h3 {
      margin: 0 0 8px 0;
      font-size: 22px;
    }
    .config-card p {
      margin: 0;
      color: var(--muted);
      line-height: 1.5;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

  <div class="config-container">
    <div class="hero">
      <h1>Configuracion</h1>
      <p>Accesos activos del panel de administracion. Iremos ampliando este indice conforme se cierren modulos reales.</p>
    </div>

    <div class="config-grid">
      <a href="aplicacion.php" class="config-card">
        <div class="card-top">
          <span class="card-icon app"><i class="fas fa-palette"></i></span>
          <span class="card-arrow"><i class="fas fa-arrow-right"></i></span>
        </div>
        <h3>Aplicacion</h3>
        <p>Personalizacion visual y parametros basicos del sistema.</p>
      </a>

      <a href="usuarios.php" class="config-card">
        <div class="card-top">
          <span class="card-icon users"><i class="fas fa-users"></i></span>
          <span class="card-arrow"><i class="fas fa-arrow-right"></i></span>
        </div>
        <h3>Usuarios</h3>
        <p>Gestion de accesos, permisos, passwords y altas/bajas de usuarios.</p>
      </a>

      <a href="importar_festivos.php" class="config-card">
        <div class="card-top">
          <span class="card-icon import"><i class="fas fa-file-import"></i></span>
          <span class="card-arrow"><i class="fas fa-arrow-right"></i></span>
        </div>
        <h3>Importar festivos</h3>
        <p>Carga el JSON de Andalucia con seguimiento visual del progreso y del resultado por bloques.</p>
      </a>
    </div>
  </div>
</body>
</html>
