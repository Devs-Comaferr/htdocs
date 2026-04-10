(function () {
    var config = window.zonasClientesConfig || {};
    var obtenerSeccionesUrl = config.obtenerSeccionesUrl || 'obtener_secciones.php';
    var mostrarNuncaCheckbox = document.getElementById('mostrar-nunca');

    if (mostrarNuncaCheckbox) {
        mostrarNuncaCheckbox.addEventListener('change', function () {
            var mostrar = this.checked;
            var filas = document.querySelectorAll('.frecuencia-nunca');

            filas.forEach(function (fila) {
                fila.style.display = mostrar ? '' : 'none';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var checkbox = document.getElementById('mostrar-nunca');
        var filas = document.querySelectorAll('.frecuencia-nunca');

        if (checkbox) {
            filas.forEach(function (fila) {
                fila.style.display = checkbox.checked ? '' : 'none';
            });
        }
    });

    function verificarSecciones(cod_cliente) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', obtenerSeccionesUrl + '?cod_cliente=' + cod_cliente, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                try {
                    var secciones = JSON.parse(xhr.responseText);
                    var seccionContainer = document.getElementById('seccion-container');
                    var seccionSelect = document.getElementById('cod_seccion');

                    if (!seccionContainer || !seccionSelect) {
                        return;
                    }

                    seccionSelect.innerHTML = '<option value="">--Selecciona una Sección--</option>';

                    if (secciones.length > 0) {
                        for (var i = 0; i < secciones.length; i++) {
                            var seccion_cod = parseInt(secciones[i].cod_seccion, 10);
                            var seccion_nombre = secciones[i].nombre;
                            var option = document.createElement('option');

                            option.value = seccion_cod;
                            option.text = seccion_nombre;
                            seccionSelect.appendChild(option);
                        }
                        seccionContainer.classList.remove('d-none');
                        seccionSelect.required = true;
                    } else {
                        seccionContainer.classList.add('d-none');
                        seccionSelect.required = false;
                        seccionSelect.value = '';
                    }
                } catch (e) {
                    console.error('Error al parsear JSON:', e);
                }
            }
        };
        xhr.send();
    }

    var clienteSelect = document.getElementById('cod_cliente');
    if (clienteSelect) {
        clienteSelect.addEventListener('change', function () {
            var cod_cliente = this.value;

            if (cod_cliente) {
                verificarSecciones(cod_cliente);
            } else {
                document.getElementById('seccion-container').classList.add('d-none');
                document.getElementById('cod_seccion').required = false;
                document.getElementById('cod_seccion').value = '';
            }
        });
    }

    var assignForm = document.getElementById('assign-form');
    if (assignForm) {
        assignForm.addEventListener('submit', function (event) {
            var errorMessage = document.getElementById('error-message');
            var cod_cliente = document.getElementById('cod_cliente').value;
            var seccionContainer = document.getElementById('seccion-container');
            var cod_seccion = document.getElementById('cod_seccion').value;
            var frecuencia_visita = document.getElementById('frecuencia_visita').value;

            errorMessage.classList.add('d-none');
            errorMessage.textContent = 'Por favor, completa todos los campos obligatorios.';

            if (cod_cliente === '') {
                errorMessage.classList.remove('d-none');
                errorMessage.textContent = 'Debes seleccionar un cliente.';
                event.preventDefault();
                return;
            }

            if (!seccionContainer.classList.contains('d-none') && cod_seccion === '') {
                errorMessage.classList.remove('d-none');
                errorMessage.textContent = 'Debes seleccionar una sección.';
                event.preventDefault();
                return;
            }

            if (frecuencia_visita === '') {
                errorMessage.style.display = 'block';
                errorMessage.textContent = 'Debes seleccionar una frecuencia de visita.';
                event.preventDefault();
            }
        });
    }

    document.querySelectorAll('.js-edit-asignacion').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('modal-cod-cliente').value = this.dataset.codCliente || '';
            document.getElementById('modal-cod-zona').value = this.dataset.codZona || '';
            document.getElementById('modal-cod-seccion').value = this.dataset.codSeccion || 'NULL';
            document.getElementById('modal-nombre-cliente').value = this.dataset.nombreCliente || '';
            document.getElementById('modal-nombre-seccion').value = this.dataset.nombreSeccion || 'Sin Sección';
            document.getElementById('modal-nombre-zona').value = this.dataset.nombreZona || '';
            document.getElementById('modal-zona-secundaria').value = this.dataset.zonaSecundaria || '';
            document.getElementById('modal-tiempo-promedio-visita').value = this.dataset.tiempoPromedioVisita || '';
            document.getElementById('modal-preferencia-horaria').value = this.dataset.preferenciaHoraria || '';
            document.getElementById('modal-frecuencia-visita').value = this.dataset.frecuenciaVisita || '';
            document.getElementById('modal-observaciones').value = this.dataset.observaciones || '';
        });
    });
})();
