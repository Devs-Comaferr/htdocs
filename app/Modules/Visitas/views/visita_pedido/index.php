<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/planner-calendar-shared.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/app/Modules/Visitas/views/visita_pedido/css/visita_pedido.css" rel="stylesheet" />
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
    window.visitaPedidoConfig = {
      baseUrl: <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      csrfToken: <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
  </script>
  <script src="<?= BASE_URL ?>/app/Modules/Visitas/views/visita_pedido/js/visita_pedido.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app-utils.js"></script>
</body>

</html>
