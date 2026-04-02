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

$pageTitle = 'Calendario de Eventos';
$ui_version = 'bs5';
$ui_requires_jquery = false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/planner-calendar-shared.css" rel="stylesheet" />
  <style>
    .calendario-panel h2 {
      font-size: 1rem;
      font-weight: 700;
      margin: 0 0 6px;
      color: #0f172a;
    }

    .calendario-panel p {
      margin: 0 0 18px;
      color: #64748b;
      font-size: 0.92rem;
    }

    .calendario-actions {
      display: grid;
      gap: 12px;
    }

    .calendario-action {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 14px;
      text-decoration: none;
      color: #0f172a;
      background: #fff;
      border: 1px solid #e2e8f0;
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }

    .calendario-action:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
      border-color: #cdd7e3;
      color: #0f172a;
    }

    .calendario-action i {
      width: 20px;
      text-align: center;
      font-size: 1rem;
      color: #475569;
    }

    .calendario-action strong {
      display: block;
      font-size: 0.95rem;
      line-height: 1.2;
    }

    .calendario-action span {
      display: block;
      font-size: 0.82rem;
      color: #64748b;
      line-height: 1.2;
      margin-top: 2px;
    }
  </style>
</head>
<body class="planner-page">
  <div class="planner-layout px-3 pb-3">
    <section class="planner-main" data-planner-label="Agenda general">
      <div class="planner-main-card">
        <div id="calendarGrande" class="planner-calendar"></div>
      </div>
    </section>

    <aside class="planner-side planner-side--narrow planner-side--scroll calendario-panel" data-planner-label="Acciones">
      <div class="planner-side-card">
        <h2>Planificador</h2>
        <p>Trabaja la agenda completa desde una sola vista y salta al flujo que necesites.</p>

        <div class="calendario-actions">
          <a href="visita_pedido.php" class="calendario-action">
            <i class="fa-solid fa-pen-to-square"></i>
            <div>
              <strong>Visita por pedidos</strong>
              <span>Relacionar pedidos pendientes con visitas</span>
            </div>
          </a>

          <a href="visita_manual.php" class="calendario-action">
            <i class="fas fa-edit"></i>
            <div>
              <strong>Visita manual</strong>
              <span>Registrar una visita fuera del flujo de pedido</span>
            </div>
          </a>

          <a href="completar_dia.php" class="calendario-action">
            <i class="fas fa-check-circle"></i>
            <div>
              <strong>Completar dia</strong>
              <span>Cerrar pendientes de la jornada</span>
            </div>
          </a>

          <a href="festivo_local.php" class="calendario-action">
            <i class="fas fa-flag"></i>
            <div>
              <strong>Festivo local</strong>
              <span>Configurar excepciones del calendario</span>
            </div>
          </a>

          <a href="registrar_dia_no_laborable.php" class="calendario-action">
            <i class="fas fa-ban"></i>
            <div>
              <strong>No laborable</strong>
              <span>Bloquear dias no operativos</span>
            </div>
          </a>
        </div>
      </div>
    </aside>
  </div>

  <?php require BASE_PATH . '/app/Modules/Visitas/views/partials/editar_visita_modal.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/index.global.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/shared-fullcalendar.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/visita-editor-modal.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var calendar = window.initSharedFullCalendar ? window.initSharedFullCalendar({
        element: '#calendarGrande',
        initialView: 'timeGridWeek',
        height: '100%'
      }) : null;

      var visitaEditor = window.initVisitaEditorModal ? window.initVisitaEditorModal({
        getCalendar: function() {
          return calendar;
        }
      }) : null;

      if (calendar) {
        calendar.setOption('eventClick', function(info) {
          if (visitaEditor) {
            visitaEditor.open(info.event.id);
          }
        });
      }
    });
  </script>
</body>
</html>
