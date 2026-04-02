(function () {
  window.initSharedFullCalendar = function initSharedFullCalendar(options) {
    if (!window.FullCalendar) {
      return null;
    }

    var config = options || {};
    var element = typeof config.element === 'string'
      ? document.querySelector(config.element)
      : config.element;

    if (!element) {
      return null;
    }

    var calendar = new FullCalendar.Calendar(element, {
      initialView: config.initialView || 'dayGridMonth',
      firstDay: 1,
      locale: 'es',
      headerToolbar: config.headerToolbar || {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      buttonText: config.buttonText || {
        prev: 'Anterior',
        next: 'Siguiente',
        today: 'Hoy',
        month: 'Mes',
        week: 'Semana',
        day: 'Dia'
      },
      hiddenDays: Array.isArray(config.hiddenDays) ? config.hiddenDays : [0, 6],
      displayEventTime: config.displayEventTime === true,
      dayMaxEvents: config.dayMaxEvents !== false,
      height: config.height || '100%',
      scrollTime: config.scrollTime || '09:00:00',
      slotDuration: config.slotDuration || '01:00:00',
      slotLabelInterval: config.slotLabelInterval || '01:00:00',
      events: config.eventsUrl || ((window.APP_BASE_URL || '/public') + '/ajax/get_eventos.php'),
      eventClick: function (info) {
        if (typeof config.onEventClick === 'function') {
          config.onEventClick(info, calendar);
        }
      }
    });

    calendar.render();

    if (config.autoResize !== false) {
      window.addEventListener('resize', function () {
        calendar.updateSize();
      });
    }

    return calendar;
  };
})();
