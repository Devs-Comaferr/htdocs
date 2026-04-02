(function () {
  function parseTime(value) {
    if (!value || value.indexOf(':') === -1) return 0;
    var parts = value.split(':');
    return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
  }

  function formatTime(totalMinutes) {
    var minutes = ((totalMinutes % (24 * 60)) + (24 * 60)) % (24 * 60);
    var hours = Math.floor(minutes / 60);
    var mins = minutes % 60;
    return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
  }

  window.initVisitaEditorModal = function initVisitaEditorModal(options) {
    var config = options || {};
    var modalElement = document.getElementById('editarVisitaModal');
    if (!modalElement || !window.bootstrap) return null;

    var modal = new bootstrap.Modal(modalElement);
    var form = document.getElementById('editarVisitaForm');
    var feedback = document.getElementById('editarVisitaFeedback');
    var idField = document.getElementById('editarVisitaId');
    var resumen = document.getElementById('editarVisitaResumen');
    var bloqueadaIcon = document.getElementById('editarVisitaBloqueadaIcon');
    var fechaField = document.getElementById('editarVisitaFecha');
    var inicioField = document.getElementById('editarVisitaHoraInicio');
    var finField = document.getElementById('editarVisitaHoraFin');
    var estadoField = document.getElementById('editarVisitaEstado');
    var estadoHint = document.getElementById('editarVisitaEstadoHint');
    var estadoSoloLectura = document.getElementById('editarVisitaEstadoSoloLectura');
    var observacionesField = document.getElementById('editarVisitaObservaciones');
    var guardarButton = document.getElementById('editarVisitaGuardar');
    var eliminarButton = document.getElementById('editarVisitaEliminar');
    var fichaClienteLink = document.getElementById('editarVisitaFichaCliente');
    var promedioMinutos = 0;

    function refetchCalendar() {
      if (typeof config.getCalendar === 'function') {
        var calendar = config.getCalendar();
        if (calendar && typeof calendar.refetchEvents === 'function') {
          calendar.refetchEvents();
        }
      }
    }

    function runCallback(name, payload) {
      if (typeof config[name] === 'function') {
        config[name](payload || {});
      }
    }

    function showFeedback(message, type) {
      if (!feedback) return;
      feedback.className = 'alert alert-' + type;
      feedback.textContent = message;
    }

    function hideFeedback() {
      if (!feedback) return;
      feedback.className = 'alert d-none';
      feedback.textContent = '';
    }

    function setLoadingState(isLoading) {
      if (guardarButton) {
        guardarButton.disabled = isLoading;
        guardarButton.textContent = isLoading ? 'Guardando...' : 'Guardar cambios';
      }
      if (eliminarButton) {
        eliminarButton.disabled = isLoading;
      }
    }

    function buildFichaClienteUrl(codCliente, codSeccion) {
      if (!codCliente) return '';
      var url = (window.APP_BASE_URL || '/public') + '/cliente_detalles.php?cod_cliente=' + encodeURIComponent(codCliente);
      if (codSeccion !== null && codSeccion !== '' && codSeccion !== undefined) {
        url += '&cod_seccion=' + encodeURIComponent(codSeccion);
      }
      return url;
    }

    function resetForm() {
      if (form) form.reset();
      if (idField) idField.value = '';
      if (resumen) resumen.textContent = '';
      if (bloqueadaIcon) bloqueadaIcon.classList.add('d-none');
      if (fichaClienteLink) {
        fichaClienteLink.classList.add('d-none');
        fichaClienteLink.href = '#';
      }
      if (estadoField) {
        estadoField.disabled = false;
        estadoField.classList.remove('d-none');
      }
      if (estadoHint) {
        estadoHint.className = 'form-text d-none text-muted';
        estadoHint.textContent = '';
      }
      if (estadoSoloLectura) {
        estadoSoloLectura.classList.add('d-none');
      }
      promedioMinutos = 0;
      hideFeedback();
    }

    async function open(idVisita) {
      resetForm();
      showFeedback('Cargando visita...', 'info');
      modal.show();

      try {
        var response = await fetch((window.APP_BASE_URL || '/public') + '/ajax/obtener_visita_edicion.php?id_visita=' + encodeURIComponent(idVisita), {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        });
        var data = await response.json();
        if (!response.ok || !data.ok) {
          throw new Error((data && data.message) ? data.message : 'No se pudo cargar la visita.');
        }

        var visita = data.data || {};
        if (idField) idField.value = visita.id_visita || '';

        var resumenText = visita.nombre_comercial || '';
        if (visita.cod_seccion !== null && visita.cod_seccion !== '' && visita.cod_seccion !== undefined) {
          resumenText += ' - ' + visita.cod_seccion;
        }
        if (resumen) resumen.textContent = resumenText;
        if (bloqueadaIcon && visita.bloquear_cambio_estado) {
          bloqueadaIcon.classList.remove('d-none');
        }

        if (fechaField) fechaField.value = visita.fecha_visita || '';
        if (inicioField) inicioField.value = visita.hora_inicio_visita || '';
        if (finField) finField.value = visita.hora_fin_visita || '';
        if (estadoField) estadoField.value = visita.estado_visita || 'Planificada';
        if (observacionesField) observacionesField.value = visita.observaciones || '';
        promedioMinutos = parseInt(visita.tiempo_promedio_minutes || 0, 10) || 0;

        if (visita.bloquear_cambio_estado) {
          if (estadoField) {
            estadoField.value = 'Realizada';
            estadoField.classList.add('d-none');
          }
          if (estadoSoloLectura) {
            estadoSoloLectura.classList.remove('d-none');
          }
        }

        var fichaUrl = buildFichaClienteUrl(visita.cod_cliente, visita.cod_seccion);
        if (fichaUrl && fichaClienteLink) {
          fichaClienteLink.href = fichaUrl;
          fichaClienteLink.classList.remove('d-none');
        }

        hideFeedback();
      } catch (error) {
        showFeedback(error.message || 'No se pudo cargar la visita.', 'danger');
      }
    }

    if (inicioField && finField) {
      inicioField.addEventListener('change', function () {
        if (!inicioField.value || !finField.value || promedioMinutos <= 0) return;
        var start = parseTime(inicioField.value);
        var end = parseTime(finField.value);
        if (end <= start) {
          finField.value = formatTime(start + promedioMinutos);
        }
      });
    }

    if (form) {
      form.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideFeedback();
        setLoadingState(true);
        try {
          var formData = new FormData(form);
          var response = await fetch((window.APP_BASE_URL || '/public') + '/ajax/guardar_visita_edicion.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });
          var data = await response.json();
          if (!response.ok || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'No se pudo actualizar la visita.');
          }
          modal.hide();
          refetchCalendar();
          runCallback('onSaved', { idVisita: idField ? idField.value : '' });
        } catch (error) {
          showFeedback(error.message || 'No se pudo actualizar la visita.', 'danger');
        } finally {
          setLoadingState(false);
        }
      });
    }

    if (eliminarButton) {
      eliminarButton.addEventListener('click', async function () {
        if (!idField || !idField.value) return;
        if (!window.confirm('¿Seguro que deseas eliminar esta visita?')) return;
        hideFeedback();
        setLoadingState(true);
        try {
          var deleteData = new FormData();
          deleteData.append('id_visita', idField.value);
          var tokenField = form ? form.querySelector('input[name="_csrf_token"]') : null;
          if (tokenField && tokenField.value) {
            deleteData.append('_csrf_token', tokenField.value);
          }
          var response = await fetch((window.APP_BASE_URL || '/public') + '/ajax/eliminar_visita_edicion.php', {
            method: 'POST',
            body: deleteData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });
          var data = await response.json();
          if (!response.ok || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'No se pudo eliminar la visita.');
          }
          var deletedId = idField.value;
          modal.hide();
          refetchCalendar();
          runCallback('onDeleted', { idVisita: deletedId });
        } catch (error) {
          showFeedback(error.message || 'No se pudo eliminar la visita.', 'danger');
        } finally {
          setLoadingState(false);
        }
      });
    }

    modalElement.addEventListener('hidden.bs.modal', function () {
      resetForm();
      refetchCalendar();
    });

    return {
      open: open,
      modal: modal,
    };
  };
})();
