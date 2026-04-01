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
require_once BASE_PATH . '/app/Modules/Visitas/services/registrar_visita_handler.php';

$ui_version = 'bs5';
$ui_requires_jquery = false;

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarVisitaSimple([
        'cod_vendedor' => $codigo_vendedor,
        'cod_cliente' => isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0,
        'cod_seccion' => isset($_POST['cod_seccion']) ? intval($_POST['cod_seccion']) : 0,
        'fecha_visita' => isset($_POST['fecha_visita']) ? $_POST['fecha_visita'] : '',
        'hora_inicio_visita' => isset($_POST['hora_inicio_visita']) ? $_POST['hora_inicio_visita'] : '',
        'hora_fin_visita' => isset($_POST['hora_fin_visita']) ? $_POST['hora_fin_visita'] : '',
        'observaciones' => isset($_POST['observaciones']) ? $_POST['observaciones'] : '',
    ], 'Pendiente');

    if ($resultado['ok']) {
        header('Location: index.php?msg=visita_programada');
        exit;
    }

    $errors = $resultado['errors'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Programar Visita</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
  <style>
    body { padding-top: 80px; }
    .cliente-autocomplete {
      position: relative;
    }
    .cliente-autocomplete-list {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 20;
      display: none;
      max-height: 240px;
      overflow-y: auto;
      background: #fff;
      border: 1px solid #ced4da;
      border-top: 0;
      border-radius: 0 0 0.375rem 0.375rem;
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .cliente-autocomplete-list.show {
      display: block;
    }
    .cliente-autocomplete-item {
      width: 100%;
      border: 0;
      background: transparent;
      padding: 0.5rem 0.75rem;
      text-align: left;
    }
    .cliente-autocomplete-item:hover,
    .cliente-autocomplete-item:focus {
      background: #f8f9fa;
    }
  </style>
</head>
<body>
<div class="container">
  <h2>Programar Visita</h2>

  <?php
  if (!empty($errors)) {
      echo '<div class="alert alert-danger"><ul>';
      foreach ($errors as $error) {
          echo '<li>' . htmlspecialchars($error) . '</li>';
      }
      echo '</ul></div>';
  }
  ?>

  <form action="programar_visita.php" method="POST">
    <input type="hidden" name="cod_vendedor" value="<?php echo $codigo_vendedor; ?>">

    <div class="mb-3 cliente-autocomplete">
      <label class="form-label" for="nombre_comercial">Buscar Cliente</label>
      <input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial" placeholder="Escribe el nombre comercial del cliente" required autocomplete="off">
      <input type="hidden" id="cod_cliente" name="cod_cliente">
      <div id="cliente_sugerencias" class="cliente-autocomplete-list" role="listbox" aria-label="Clientes sugeridos"></div>
    </div>

    <div class="mb-3" id="seccion_container" style="display: none;">
      <label class="form-label" for="cod_seccion">Seleccionar Seccion</label>
      <select class="form-select" id="cod_seccion" name="cod_seccion" required></select>
    </div>

    <div class="mb-3">
      <label class="form-label" for="fecha_visita">Fecha de la Visita</label>
      <input type="date" class="form-control" id="fecha_visita" name="fecha_visita" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="hora_inicio_visita">Hora de Inicio de la Visita</label>
      <input type="time" class="form-control" id="hora_inicio_visita" name="hora_inicio_visita" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="hora_fin_visita">Hora de Finalizacion de la Visita</label>
      <input type="time" class="form-control" id="hora_fin_visita" name="hora_fin_visita" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="observaciones">Observaciones</label>
      <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Programar Visita</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var nombreInput = document.getElementById('nombre_comercial');
    var codClienteInput = document.getElementById('cod_cliente');
    var seccionContainer = document.getElementById('seccion_container');
    var seccionSelect = document.getElementById('cod_seccion');
    var sugerencias = document.getElementById('cliente_sugerencias');
    var horaInicioInput = document.getElementById('hora_inicio_visita');
    var horaFinInput = document.getElementById('hora_fin_visita');
    var searchTimer = null;
    var clientesSugeridos = [];

    function limpiarSecciones() {
        seccionSelect.innerHTML = '';
        seccionContainer.style.display = 'none';
    }

    function ocultarSugerencias() {
        sugerencias.innerHTML = '';
        sugerencias.classList.remove('show');
    }

    function renderizarSugerencias(clientes) {
        sugerencias.innerHTML = '';
        if (!clientes.length) {
            ocultarSugerencias();
            return;
        }

        clientes.forEach(function (cliente) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'cliente-autocomplete-item';
            boton.textContent = cliente.label || cliente.value || '';
            boton.addEventListener('click', function () {
                nombreInput.value = cliente.value || cliente.label || '';
                codClienteInput.value = cliente.cod_cliente || '';
                ocultarSugerencias();
                cargarSecciones(cliente.cod_cliente);
            });
            sugerencias.appendChild(boton);
        });

        sugerencias.classList.add('show');
    }

    function buscarClientes(term) {
        fetch(BASE_URL + '/buscar_cliente.php?term=' + encodeURIComponent(term), {
            credentials: 'same-origin'
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (clientes) {
            clientesSugeridos = Array.isArray(clientes) ? clientes : [];
            renderizarSugerencias(clientesSugeridos);
        })
        .catch(function (error) {
            console.error('Error al buscar clientes:', error);
            clientesSugeridos = [];
            ocultarSugerencias();
        });
    }

    function cargarSecciones(codCliente) {
        fetch(BASE_URL + '/obtener_secciones_pedidos_visitas.php?cod_cliente=' + encodeURIComponent(codCliente), {
            credentials: 'same-origin'
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (secciones) {
            seccionSelect.innerHTML = '';

            if (Array.isArray(secciones) && secciones.length > 0) {
                var opcionDefault = document.createElement('option');
                opcionDefault.value = '';
                opcionDefault.textContent = 'Selecciona una seccion';
                seccionSelect.appendChild(opcionDefault);

                secciones.forEach(function (seccion) {
                    var opcion = document.createElement('option');
                    opcion.value = seccion.cod_seccion;
                    opcion.textContent = seccion.nombre_seccion;
                    seccionSelect.appendChild(opcion);
                });

                seccionContainer.style.display = 'block';
            } else {
                limpiarSecciones();
            }
        })
        .catch(function (error) {
            console.error('Error al obtener secciones:', error);
            limpiarSecciones();
        });
    }

    nombreInput.addEventListener('input', function () {
        var term = nombreInput.value.trim();
        codClienteInput.value = '';
        limpiarSecciones();

        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        if (term.length < 2) {
            clientesSugeridos = [];
            ocultarSugerencias();
            return;
        }

        searchTimer = setTimeout(function () {
            buscarClientes(term);
        }, 200);
    });

    nombreInput.addEventListener('change', function () {
        var valor = nombreInput.value.trim();
        var clienteSeleccionado = clientesSugeridos.find(function (cliente) {
            return (cliente.value || cliente.label || '') === valor;
        });

        if (clienteSeleccionado) {
            codClienteInput.value = clienteSeleccionado.cod_cliente || '';
            cargarSecciones(clienteSeleccionado.cod_cliente);
        } else {
            codClienteInput.value = '';
            limpiarSecciones();
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.cliente-autocomplete')) {
            ocultarSugerencias();
        }
    });

    [horaInicioInput, horaFinInput].forEach(function (input) {
        input.addEventListener('change', function () {
            var inicio = horaInicioInput.value;
            var fin = horaFinInput.value;

            if (inicio && fin) {
                var inicioTotal = parseTime(inicio);
                var finTotal = parseTime(fin);
                var diff = finTotal - inicioTotal;

                if (diff < 15 || diff > 300) {
                    alert('La diferencia entre la hora de inicio y la hora de fin debe ser de al menos 15 minutos y no exceder las 5 horas.');
                }
            }
        });
    });
});
</script>
<script src="<?= BASE_URL ?>/assets/js/app-utils.js"></script>
</body>
</html>
