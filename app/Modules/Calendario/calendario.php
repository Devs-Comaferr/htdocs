<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
// Leer el parámetro "origen" y normalizarlo a minúsculas
$origen = isset($_GET['origen']) ? strtolower($_GET['origen']) : '';

// Si "origen" es "semana", forzamos la vista semanal
if ($origen === 'semana') {
    $defaultView = 'timeGridWeek';
} elseif (isset($_GET['view']) && in_array($_GET['view'], ['dayGridMonth', 'timeGridWeek', 'timeGridDay'])) {
    $defaultView = $_GET['view'];
} else {
    // Vista por defecto si no se especifica otra
    $defaultView = 'dayGridMonth';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Calendario de Eventos</title>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css" rel="stylesheet" />
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
    }
    #calendar {
      margin: 0 auto;
    }
    .fc-header-toolbar {
      padding: 1px 3px !important;
      font-size: 0.8em !important;
      height: auto;
    }
    .fc-toolbar-title {
      font-size: 0.9em !important;
      padding: 0 3px !important;
    }
    .fc-button {
      padding: 5px 10px !important;
      font-size: 1em !important;
      height: auto;
    }
    .fc-timegrid-slot {
      height: 30px !important;
      background-color: #fff !important;
    }
    .fc-timegrid-slot-label {
      padding: 2px 3px !important;
      font-size: 0.7em !important;
    }
  </style>
</head>
<body>
  <div id="calendar"></div>
  
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/index.global.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');
      var initialView = '<?php echo htmlspecialchars($defaultView, ENT_QUOTES, 'UTF-8'); ?>';
      var origen = '<?php echo htmlspecialchars($origen, ENT_QUOTES, 'UTF-8'); ?>';
      
      var calendarOptions = {
        initialView: initialView,
        firstDay: 1,
        locale: 'es',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
          prev: "Anterior",
          next: "Siguiente",
          today: "Hoy",
          month: "Mes",
          week: "Semana",
          day: "Da"
        },
        hiddenDays: [0,6], // En este ejemplo, ocultamos sbado y domingo por defecto
        displayEventTime: false,
        dayMaxEvents: true,
        height: 480,
        events: '<?= BASE_URL ?>/ajax/get_eventos.php',
        eventClick: function(info) {
          var url = "editar_visita.php?id_visita=" + info.event.id;
          if (origen !== "") {
            url += "&origen=" + encodeURIComponent(origen);
          }
          window.location.href = url;
        }
      };

      // Si la vista es timeGrid, configuramos scrollTime e intervalos de 1 hora
      if (initialView === 'timeGridWeek' || initialView === 'timeGridDay') {
        calendarOptions.scrollTime = '09:00:00';
        calendarOptions.slotDuration = '01:00:00';
        calendarOptions.slotLabelInterval = '01:00:00';
      }
      
      var calendar = new FullCalendar.Calendar(calendarEl, calendarOptions);
      calendar.render();
    });
  </script>
</body>
</html>
