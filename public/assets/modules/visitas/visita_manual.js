document.addEventListener('DOMContentLoaded', function() {
    var visitaManualConfig = window.visitaManualConfig || {};
    var csrfTokenVisitas = visitaManualConfig.csrfToken || '';
    var fechaInput = document.querySelector('#fecha_visita');
    var horaInicioInput = document.querySelector('#hora_inicio_visita');
    var horaFinInput = document.querySelector('#hora_fin_visita');
    var visitasDelDia = document.querySelector('#visitas_del_dia');
    var btnDefinirHorario = document.querySelector('#horario_btnDefinirHorario');
    var modalDefinirHorario = document.querySelector('#horario_modalDefinirHorario');
    var modalClose = modalDefinirHorario ? modalDefinirHorario.querySelector('.close') : null;
    var guardarHorarioBtn = document.querySelector('#horario_guardarHorarioBtn');
    var flujoVisitaManual = document.querySelector('#flujoVisitaManual');
    var submitRegistrarBtn = flujoVisitaManual ? flujoVisitaManual.querySelector('button[type="submit"][value="registrar"]') : null;
    var estadoVisitaInput = document.querySelector('#estado_visita');
    var clienteInput = document.querySelector('#cod_cliente');
    var seccionInput = document.querySelector('#cod_seccion');
    var warningVisitasExistentes = document.querySelector('#warning_visitas_existentes');
    var promedio = visitaManualConfig.promedio || 0;
    var horarioCliente = visitaManualConfig.horarioCliente || {
        mananaInicio: '',
        mananaFin: '',
        tardeInicio: '',
        tardeFin: ''
    };
    var emptyStateHtml = visitaManualConfig.emptyStateHtml || "<div class='alert alert-info'>Seleccione una fecha para ver las visitas programadas.</div>";
    var errorStateHtml = visitaManualConfig.errorStateHtml || "<div class='alert alert-danger'>Error al cargar las visitas.</div>";
    var checkVisitasExistentesUrl = visitaManualConfig.checkVisitasExistentesUrl || '';
    var getVisitasUrl = visitaManualConfig.getVisitasUrl || '';
    var definirHorarioUrl = visitaManualConfig.definirHorarioUrl || '';
    var usuarioTocoHoraInicio = !!(horaInicioInput && horaInicioInput.value);
    var comprobacionVisitasRequestId = 0;
    var horarioAutoCompletado = false;

    function setVisitasContent(html) {
        if (visitasDelDia) {
            visitasDelDia.innerHTML = html;
        }
    }

    function actualizarEstadoBotonSubmit() {
        if (!submitRegistrarBtn) {
            return;
        }

        submitRegistrarBtn.disabled = !horarioAutoCompletado;
    }

    function setHorarioAutoCompletado(valor) {
        horarioAutoCompletado = valor;
        actualizarEstadoBotonSubmit();
    }

    function ocultarWarningVisitasExistentes() {
        if (!warningVisitasExistentes) {
            return;
        }

        warningVisitasExistentes.textContent = '';
        warningVisitasExistentes.classList.add('d-none');
    }

    function mostrarWarningVisitasExistentes(texto) {
        if (!warningVisitasExistentes) {
            return;
        }

        warningVisitasExistentes.innerHTML = '&#9888; ' + texto;
        warningVisitasExistentes.classList.remove('d-none');
    }

    function obtenerPayloadComprobacionVisitas() {
        var codCliente = clienteInput ? clienteInput.value.trim() : '';
        var codSeccion = seccionInput ? seccionInput.value.trim() : '';
        var fecha = fechaInput ? fechaInput.value.trim() : '';

        if (!codCliente || !fecha) {
            return null;
        }

        return {
            cod_cliente: codCliente,
            cod_seccion: codSeccion,
            fecha_visita: fecha
        };
    }

    function resolverMensajeVisitasExistentes(estados) {
        if (!Array.isArray(estados) || estados.length === 0) {
            return '';
        }

        var estadosNormalizados = estados.map(function(estado) {
            return String(estado || '').trim().toLowerCase();
        });

        if (estadosNormalizados.indexOf('realizada') !== -1) {
            return 'Ya existe una visita REALIZADA para este cliente en este dÃ­a.';
        }

        if (
            estadosNormalizados.indexOf('planificada') !== -1 ||
            estadosNormalizados.indexOf('pendiente') !== -1
        ) {
            return 'Ya existe una visita PLANIFICADA o PENDIENTE para este cliente en este dÃ­a.';
        }

        return '';
    }

    function comprobarVisitasExistentes() {
        var payload = obtenerPayloadComprobacionVisitas();
        var requestId = comprobacionVisitasRequestId + 1;
        comprobacionVisitasRequestId = requestId;

        if (!payload || !checkVisitasExistentesUrl) {
            ocultarWarningVisitasExistentes();
            return;
        }

        var params = new URLSearchParams();
        params.set('cod_cliente', payload.cod_cliente);
        params.set('cod_seccion', payload.cod_seccion);
        params.set('fecha_visita', payload.fecha_visita);

        fetch(checkVisitasExistentesUrl + '?' + params.toString(), {
            credentials: 'same-origin'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (requestId !== comprobacionVisitasRequestId) {
                    return;
                }

                var mensaje = (data && data.existe) ? resolverMensajeVisitasExistentes(data.estados) : '';
                if (mensaje) {
                    mostrarWarningVisitasExistentes(mensaje);
                    return;
                }

                ocultarWarningVisitasExistentes();
            })
            .catch(function(error) {
                if (requestId !== comprobacionVisitasRequestId) {
                    return;
                }

                console.error('Error comprobando visitas existentes:', error);
                ocultarWarningVisitasExistentes();
            });
    }

    function mostrarMensaje(tipo, texto) {
        var contenedor = document.getElementById('mensajes_validacion');
        if (!contenedor) {
            return;
        }

        var clase = 'alert ';
        if (tipo === 'error') clase += 'alert-danger';
        if (tipo === 'warning') clase += 'alert-warning';
        if (tipo === 'info') clase += 'alert-info';

        contenedor.innerHTML = '<div class="' + clase + '">' + texto + '</div>';
        contenedor.scrollIntoView({ behavior: 'smooth' });
    }

    function limpiarMensajes() {
        var contenedor = document.getElementById('mensajes_validacion');
        if (contenedor) {
            contenedor.innerHTML = '';
        }
    }

    function marcarCampoError(input, hayError) {
        if (!input) {
            return;
        }

        input.style.border = hayError ? '2px solid red' : '';
    }

    function horaToMin(hora) {
        if (!hora || hora.indexOf(':') === -1) {
            return null;
        }

        var partes = hora.split(':');
        return (parseInt(partes[0], 10) * 60) + parseInt(partes[1], 10);
    }

    function minToHora(minutos) {
        var horas = Math.floor(minutos / 60);
        var mins = minutos % 60;
        return String(horas).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
    }

    function parsearVisitasDesdeHtml() {
        if (!visitasDelDia) {
            return [];
        }

        var horas = visitasDelDia.querySelectorAll('.visita-horas');
        return Array.prototype.map.call(horas, function(item) {
            var texto = (item.textContent || '').trim();
            var coincidencia = texto.match(/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/);
            if (!coincidencia) {
                return null;
            }

            return {
                inicio: coincidencia[1],
                fin: coincidencia[2]
            };
        }).filter(function(visita) {
            return visita !== null;
        });
    }

    function calcularSiguienteHueco(visitas, duracionMinutos) {
        var inicioJornada = horaToMin('09:00');
        var finJornada = horaToMin('20:00');
        var cursor = inicioJornada;

        if (!Array.isArray(visitas) || visitas.length === 0) {
            return {
                inicio: minToHora(inicioJornada),
                fin: minToHora(Math.min(inicioJornada + duracionMinutos, finJornada))
            };
        }

        var visitasOrdenadas = visitas.slice().sort(function(a, b) {
            return horaToMin(a.inicio) - horaToMin(b.inicio);
        });

        for (var i = 0; i < visitasOrdenadas.length; i += 1) {
            var visita = visitasOrdenadas[i];
            var inicioVisita = horaToMin(visita.inicio);
            var finVisita = horaToMin(visita.fin);

            if (inicioVisita !== null && inicioVisita - cursor >= duracionMinutos) {
                return {
                    inicio: minToHora(cursor),
                    fin: minToHora(cursor + duracionMinutos)
                };
            }

            if (finVisita !== null) {
                cursor = Math.max(cursor, finVisita);
            }
        }

        if (cursor + duracionMinutos <= finJornada) {
            return {
                inicio: minToHora(cursor),
                fin: minToHora(cursor + duracionMinutos)
            };
        }

        return {
            inicio: minToHora(cursor),
            fin: minToHora(cursor + duracionMinutos)
        };
    }

    function aplicarSiguienteHuecoAutomatico() {
        if (usuarioTocoHoraInicio || !horaInicioInput || !horaFinInput) {
            setHorarioAutoCompletado(true);
            return;
        }

        var duracionMinutos = parseInt(promedio, 10);
        if (!duracionMinutos || duracionMinutos <= 0) {
            setHorarioAutoCompletado(true);
            return;
        }

        var visitas = parsearVisitasDesdeHtml();
        var hueco = calcularSiguienteHueco(visitas, duracionMinutos);
        if (!hueco) {
            setHorarioAutoCompletado(true);
            return;
        }

        horaInicioInput.value = hueco.inicio;
        horaFinInput.value = hueco.fin;
        setHorarioAutoCompletado(true);
    }

    function haySolapeConVisitasExistentes(nuevoInicio, nuevoFin, visitas) {
        return visitas.some(function(visita) {
            var visitaInicio = horaToMin(visita.inicio);
            var visitaFin = horaToMin(visita.fin);

            if (visitaInicio === null || visitaFin === null) {
                return false;
            }

            return nuevoInicio < visitaFin && nuevoFin > visitaInicio;
        });
    }

    function estaDentroHorarioCliente(nuevoInicio, nuevoFin) {
        var mananaInicio = horaToMin(horarioCliente.mananaInicio);
        var mananaFin = horaToMin(horarioCliente.mananaFin);
        var tardeInicio = horaToMin(horarioCliente.tardeInicio);
        var tardeFin = horaToMin(horarioCliente.tardeFin);

        var encajaManana = mananaInicio !== null && mananaFin !== null && nuevoInicio >= mananaInicio && nuevoFin <= mananaFin;
        var encajaTarde = tardeInicio !== null && tardeFin !== null && nuevoInicio >= tardeInicio && nuevoFin <= tardeFin;

        return encajaManana || encajaTarde;
    }

    function existeHuecoManualValido(nuevoInicio, nuevoFin, visitas) {
        var duracionMinutos = nuevoFin - nuevoInicio;
        var hueco = calcularSiguienteHueco(visitas, duracionMinutos);
        if (!hueco) {
            return false;
        }

        var huecoInicio = horaToMin(hueco.inicio);
        var huecoFin = horaToMin(hueco.fin);
        if (huecoInicio === null || huecoFin === null) {
            return false;
        }

        if (nuevoInicio < huecoInicio || nuevoFin > huecoFin) {
            return !haySolapeConVisitasExistentes(nuevoInicio, nuevoFin, visitas);
        }

        return true;
    }

    function evaluarValidacionesHorario() {
        limpiarMensajes();
        marcarCampoError(fechaInput, false);
        marcarCampoError(horaInicioInput, false);
        marcarCampoError(horaFinInput, false);

        if (!clienteInput || !clienteInput.value) {
            return { ok: false, tipo: 'error', mensaje: 'Debes seleccionar un cliente.', campos: [] };
        }

        if (!fechaInput || !fechaInput.value) {
            marcarCampoError(fechaInput, true);
            return { ok: false, tipo: 'error', mensaje: 'La fecha de la visita es obligatoria.', campos: [fechaInput] };
        }

        if (!horaInicioInput || !horaInicioInput.value) {
            marcarCampoError(horaInicioInput, true);
            return { ok: false, tipo: 'error', mensaje: 'La hora de inicio de la visita es obligatoria.', campos: [horaInicioInput] };
        }

        if (!horaFinInput || !horaFinInput.value) {
            marcarCampoError(horaFinInput, true);
            return { ok: false, tipo: 'error', mensaje: 'La hora de fin de la visita es obligatoria.', campos: [horaFinInput] };
        }

        if (horaInicioInput.value >= horaFinInput.value) {
            marcarCampoError(horaInicioInput, true);
            marcarCampoError(horaFinInput, true);
            return { ok: false, tipo: 'error', mensaje: 'La hora de inicio debe ser anterior a la hora de fin.', campos: [horaInicioInput, horaFinInput] };
        }

        var nuevoInicio = horaToMin(horaInicioInput.value);
        var nuevoFin = horaToMin(horaFinInput.value);
        var visitas = parsearVisitasDesdeHtml();

        if (haySolapeConVisitasExistentes(nuevoInicio, nuevoFin, visitas)) {
            marcarCampoError(horaInicioInput, true);
            marcarCampoError(horaFinInput, true);
            return { ok: false, tipo: 'error', mensaje: 'No hay tiempo disponible para esta visita en el horario seleccionado', campos: [horaInicioInput, horaFinInput] };
        }

        if (!estaDentroHorarioCliente(nuevoInicio, nuevoFin)) {
            marcarCampoError(horaInicioInput, true);
            marcarCampoError(horaFinInput, true);

            var estado = estadoVisitaInput ? estadoVisitaInput.value : '';
            if (estado === 'Planificada' || estado === 'Pendiente') {
                return { ok: false, tipo: 'error', mensaje: 'La visita estÃ¡ fuera del horario del cliente', campos: [horaInicioInput, horaFinInput] };
            }

            if (estado === 'Realizada' || estado === 'No atendida' || estado === 'Descartada') {
                return { ok: false, tipo: 'warning', mensaje: 'La visita estÃ¡ fuera del horario del cliente', permiteContinuar: true, campos: [horaInicioInput, horaFinInput] };
            }
        }

        if (usuarioTocoHoraInicio && !existeHuecoManualValido(nuevoInicio, nuevoFin, visitas)) {
            marcarCampoError(horaInicioInput, true);
            marcarCampoError(horaFinInput, true);
            return { ok: false, tipo: 'error', mensaje: 'No hay tiempo disponible para esta visita en el horario seleccionado', campos: [horaInicioInput, horaFinInput] };
        }

        return { ok: true };
    }

    function validarEnTiempoReal() {
        if (!horarioAutoCompletado) {
            return false;
        }

        var resultado = evaluarValidacionesHorario();
        if (resultado.ok) {
            limpiarMensajes();
            return true;
        }

        if (resultado.tipo === 'error') {
            mostrarMensaje('error', resultado.mensaje);
        } else if (resultado.tipo === 'warning') {
            mostrarMensaje('warning', 'âš ï¸ ' + resultado.mensaje + ' (puedes continuar)');
        }

        return false;
    }

    function calcularHoraFin() {
        if (!horaInicioInput || !horaFinInput || !horaInicioInput.value) {
            return;
        }

        var parts = horaInicioInput.value.split(':');
        var date = new Date();
        date.setHours(parseInt(parts[0], 10));
        date.setMinutes(parseInt(parts[1], 10) + parseInt(promedio, 10));

        var endHours = String(date.getHours()).padStart(2, '0');
        var endMinutes = String(date.getMinutes()).padStart(2, '0');
        horaFinInput.value = endHours + ':' + endMinutes;
    }

    function cargarVisitasDelDia() {
        if (!fechaInput) {
            setHorarioAutoCompletado(true);
            return;
        }

        var fecha = fechaInput.value;
        if (!fecha) {
            setHorarioAutoCompletado(true);
            setVisitasContent(emptyStateHtml);
            return;
        }

        if (!getVisitasUrl) {
            setHorarioAutoCompletado(true);
            return;
        }

        var url = new URL(getVisitasUrl, window.location.origin);
        url.searchParams.set('fecha', fecha);

        fetch(url.toString(), {
            credentials: 'same-origin'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(data) {
                setVisitasContent(data);
                aplicarSiguienteHuecoAutomatico();
            })
            .catch(function(error) {
                console.error('Error cargando visitas:', error);
                setVisitasContent(errorStateHtml);
                setHorarioAutoCompletado(true);
            });
    }

    function abrirModalHorario() {
        if (modalDefinirHorario) {
            modalDefinirHorario.style.display = 'block';
        }
    }

    function cerrarModalHorario() {
        if (modalDefinirHorario) {
            modalDefinirHorario.style.display = 'none';
        }
    }

    if (horaInicioInput) {
        horaInicioInput.addEventListener('input', function() {
            usuarioTocoHoraInicio = true;
        });
        horaInicioInput.addEventListener('change', calcularHoraFin);
        horaInicioInput.addEventListener('change', validarEnTiempoReal);
    }

    if (fechaInput) {
        fechaInput.addEventListener('change', function() {
            setHorarioAutoCompletado(false);
            cargarVisitasDelDia();
        });
        fechaInput.addEventListener('change', comprobarVisitasExistentes);
        fechaInput.addEventListener('change', validarEnTiempoReal);
        if (fechaInput.value !== '') {
            setHorarioAutoCompletado(false);
            cargarVisitasDelDia();
            comprobarVisitasExistentes();
        }
    }

    actualizarEstadoBotonSubmit();

    if (clienteInput) {
        clienteInput.addEventListener('change', comprobarVisitasExistentes);
    }

    if (seccionInput) {
        seccionInput.addEventListener('change', comprobarVisitasExistentes);
    }

    if (horaFinInput) {
        horaFinInput.addEventListener('change', validarEnTiempoReal);
    }

    if (btnDefinirHorario) {
        btnDefinirHorario.addEventListener('click', abrirModalHorario);
    }

    if (modalClose) {
        modalClose.addEventListener('click', cerrarModalHorario);
    }

    document.addEventListener('mouseup', function(event) {
        if (!modalDefinirHorario || modalDefinirHorario.style.display !== 'block') {
            return;
        }

        var modalContent = modalDefinirHorario.querySelector('.modal-content');
        if (modalContent && !modalContent.contains(event.target)) {
            cerrarModalHorario();
        }
    });

    if (guardarHorarioBtn) {
        guardarHorarioBtn.addEventListener('click', function() {
            var payload = new URLSearchParams();
            payload.append('cod_cliente', document.querySelector('#horario_modal_cod_cliente').value);
            payload.append('cod_seccion', document.querySelector('#horario_modal_cod_seccion').value);
            payload.append('hora_inicio_manana', document.querySelector('#horario_inicio_manana').value);
            payload.append('hora_fin_manana', document.querySelector('#horario_fin_manana').value);
            payload.append('hora_inicio_tarde', document.querySelector('#horario_inicio_tarde').value);
            payload.append('hora_fin_tarde', document.querySelector('#horario_fin_tarde').value);
            payload.append('_csrf_token', csrfTokenVisitas);

            fetch(definirHorarioUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload.toString()
            })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.text();
                })
                .then(function(responseText) {
                    if (responseText.indexOf('OK') === 0) {
                        mostrarMensaje('info', 'Horario guardado correctamente.');
                        window.location.reload();
                    } else {
                        mostrarMensaje('error', 'Error: ' + responseText);
                    }
                })
                .catch(function(error) {
                    console.error('Error guardando horario:', error);
                    mostrarMensaje('error', 'Error en la peticiÃ³n.');
                });
        });
    }

    if (flujoVisitaManual) {
        flujoVisitaManual.addEventListener('submit', function(event) {
            var submitter = event.submitter;
            if (!submitter || submitter.value !== 'registrar') {
                return;
            }

            if (!horarioAutoCompletado) {
                event.preventDefault();
                mostrarMensaje('info', 'â³ Calculando horario automÃ¡tico, espera un momento...');
                return;
            }

            var resultado = evaluarValidacionesHorario();
            if (resultado.ok) {
                limpiarMensajes();
                return;
            }

            event.preventDefault();

            if (resultado.tipo === 'warning' && resultado.permiteContinuar) {
                mostrarMensaje('warning', 'âš ï¸ ' + resultado.mensaje + ' (puedes continuar)');
                var contenedor = document.getElementById('mensajes_validacion');
                if (contenedor) {
                    contenedor.innerHTML += '<button id="confirmarContinuar" class="btn btn-warning mt-2">Continuar igualmente</button>';
                    var botonContinuar = document.getElementById('confirmarContinuar');
                    if (botonContinuar) {
                        botonContinuar.addEventListener('click', function() {
                            limpiarMensajes();
                            flujoVisitaManual.submit();
                        }, { once: true });
                    }
                }
                return;
            }

            mostrarMensaje('error', resultado.mensaje);
        });
    }
});
