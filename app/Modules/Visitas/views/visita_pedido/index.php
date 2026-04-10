<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/planner-calendar-shared.css" rel="stylesheet" />

  <style>
    .container {
      max-width: 100%;
    }

    .pedido-item {
      background: #fff;
      padding: 18px;
      border-radius: 18px;
      margin-bottom: 16px;
      border: 1px solid #e8edf3;
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.07);
      cursor: pointer;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    .pedido-item:hover {
      transform: translateY(-1px);
      box-shadow: 0 18px 34px rgba(15, 23, 42, 0.11);
      border-color: #d4dde8;
    }

    .nombre-comercial {
      font-size: 1.15rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      margin-bottom: 4px;
      text-transform: uppercase;
      color: #0f172a;
    }

    .nombre-seccion {
      font-size: 0.95rem;
      font-style: italic;
      margin-bottom: 8px;
      text-transform: uppercase;
      color: #64748b;
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

    .meta-pedido {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 0.88rem;
      color: #64748b;
    }

    .meta-pedido span {
      display: flex;
      align-items: center;
    }

    .meta-pedido i {
      color: #6c757d;
      opacity: 0.8;
    }

    .meta-pedido .importe {
      font-weight: 600;
    }

    .importe-positivo {
      color: #198754;
    }

    .importe-negativo {
      color: #dc3545;
    }

    .importe-cero {
      color: #6c757d;
    }

    .meta-pedido .importe i {
      opacity: 1;
    }

    .meta-pedido .lineas {
      opacity: 0.7;
      font-size: 0.85rem;
    }

    .card h5 {
      margin-bottom: 6px;
    }

    .pedido-buttons {
      display: flex;
      gap: 10px;
    }

    .acciones-pedido {
      margin-top: 14px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
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
      box-shadow: 0 10px 18px rgba(15, 23, 42, 0.14);
      transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
    }

    .btn-circle:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 24px rgba(15, 23, 42, 0.18);
      filter: brightness(1.02);
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

    .nota-pedido {
      margin-top: 6px;
      padding: 6px 8px;
      border-radius: 4px;
      font-size: 0.9rem;
      background: #f8f9fa;
      border-left: 3px solid #dee2e6;
      display: flex;
      align-items: flex-start;
      gap: 6px;
      flex-wrap: wrap;
    }

    .nota-pedido i {
      margin-top: 2px;
    }

    .nota-pedido .badge {
      display: inline-block !important;
      width: auto !important;
      flex: 0 0 auto !important;
      white-space: nowrap;
    }

    .badges-pedido {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      margin-bottom: 4px;
    }

    .badges-pedido .badge {
      font-size: 0.7rem;
      padding: 3px 6px;
      border-radius: 4px;
    }

    .bg-purple {
      background-color: #6f42c1;
      color: #fff;
    }

    .badge-xy {
      background-color: #495057;
      color: #fff;
    }

    .nota-pedido span {
      flex: 1;
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

<body class="planner-page">
  <div class="container">
    <?php require_once __DIR__ . '/bloques/header.php'; ?>

    <?php require_once __DIR__ . '/bloques/formulario.php'; ?>

    <?php require_once __DIR__ . '/bloques/timeline.php'; ?>
  </div>

  <?php require_once __DIR__ . '/bloques/modales.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/index.global.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/shared-fullcalendar.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/visita-editor-modal.js"></script>
  <script>
<?php require __DIR__ . '/js/visita_pedido.js'; ?>
  </script>
<script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app-utils.js"></script>
</body>

</html>
