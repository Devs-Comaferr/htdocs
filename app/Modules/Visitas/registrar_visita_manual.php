<?php
// ARCHIVO LEGACY
// Este archivo ya no debe usarse directamente.
// Se mantiene por compatibilidad.
// Usar /visitas.php?action=crear|editar|eliminar

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';
requierePermiso('perm_planificador');

$conn = db();

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
$ui_version = 'bs5';
$ui_requires_jquery = false;
$pageTitle = 'Registrar Visita';

function registrarVisitaManualPrepareExecute($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        appLogTechnicalError('registrar_visita_manual.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    if (!odbc_execute($stmt, $params)) {
        appLogTechnicalError('registrar_visita_manual.execute', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    return $stmt;
}

$accion = $_POST['accion'] ?? null;
$busqueda = trim((string)($_POST['buscar'] ?? ''));
$cod_cliente = isset($_POST['cod_cliente']) ? intval($_POST['cod_cliente']) : 0;
$cod_seccion = (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') ? intval($_POST['cod_seccion']) : null;
$fecha_visita = isset($_POST['fecha_visita']) ? trim((string)$_POST['fecha_visita']) : '';
$hora_inicio_visita = isset($_POST['hora_inicio_visita']) ? trim((string)$_POST['hora_inicio_visita']) : '';
$hora_fin_visita = isset($_POST['hora_fin_visita']) ? trim((string)$_POST['hora_fin_visita']) : '';
$zona_seleccionada = isset($_POST['zona_seleccionada']) ? trim((string)$_POST['zona_seleccionada']) : '';
$estado_visita = isset($_POST['estado_visita']) ? trim((string)$_POST['estado_visita']) : 'Planificada';
$observaciones = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : '';

$error = '';
$resultadosBusqueda = [];
$mostrarResultados = false;
$mostrarFormulario = false;
$assignment = null;
$nombreCliente = '';
$nombreSeccion = '';
$zonas = [];
$tiempo_promedio_minutes = 0.0;
$hora_inicio_manana = '';
$hora_fin_manana = '';
$hora_inicio_tarde = '';
$hora_fin_tarde = '';
$citas = [];

if ($accion === 'registrar') {
    $_GET['action'] = 'crear';
    $_POST['origen'] = 'manual';
    require BASE_PATH . '/public/visitas.php';
}

if ($accion === 'buscar') {
    $mostrarResultados = true;

    if ($busqueda !== '') {
        $sql_busqueda = "SELECT cl.cod_cliente, cl.nombre_comercial, sc.cod_seccion, sc.nombre AS nombre_seccion
                         FROM [integral].[dbo].[clientes] cl
                         LEFT JOIN [integral].[dbo].[secciones_cliente] sc
                           ON cl.cod_cliente = sc.cod_cliente
                         WHERE cl.nombre_comercial LIKE ?
                           AND cl.cod_vendedor = ?";
        $result_busqueda = registrarVisitaManualPrepareExecute($conn, $sql_busqueda, ['%' . $busqueda . '%', $codigo_vendedor]);
        if (!$result_busqueda) {
            $error = 'Error interno';
        } else {
            while ($cliente = odbc_fetch_array($result_busqueda)) {
                $resultadosBusqueda[] = $cliente;
            }
        }
    } else {
        $error = 'Introduce un nombre para buscar.';
    }
}

if ($accion === 'seleccionar_cliente' && $cod_cliente > 0) {
    $mostrarFormulario = true;
}

if ($mostrarFormulario) {
    $sql_assignment = "SELECT * FROM [integral].[dbo].[cmf_asignacion_zonas_clientes] WHERE cod_cliente = ? AND activo = 1";
    $assignmentParams = [$cod_cliente];
    if ($cod_seccion !== null) {
        $sql_assignment .= " AND cod_seccion = ?";
        $assignmentParams[] = $cod_seccion;
    } else {
        $sql_assignment .= " AND cod_seccion IS NULL";
    }
    $result_assignment = registrarVisitaManualPrepareExecute($conn, $sql_assignment, $assignmentParams);
    if (!$result_assignment) {
        $error = 'Error interno';
        $mostrarFormulario = false;
    } else {
        $assignment = odbc_fetch_array($result_assignment);
        if (!$assignment) {
            $error = 'Error interno';
            $mostrarFormulario = false;
        }
    }
}

if ($mostrarFormulario && $assignment) {
    $sql_cliente = "SELECT nombre_comercial FROM [integral].[dbo].[clientes] WHERE cod_cliente = ?";
    $result_cliente = registrarVisitaManualPrepareExecute($conn, $sql_cliente, [$cod_cliente]);
    $clienteData = $result_cliente ? odbc_fetch_array($result_cliente) : false;
    $nombreCliente = $clienteData ? (string)$clienteData['nombre_comercial'] : (string)$cod_cliente;

    if ($cod_seccion !== null) {
        $sql_seccion = "SELECT nombre FROM [integral].[dbo].[secciones_cliente] WHERE cod_cliente = ? AND cod_seccion = ?";
        $result_seccion = registrarVisitaManualPrepareExecute($conn, $sql_seccion, [$cod_cliente, $cod_seccion]);
        $seccionData = $result_seccion ? odbc_fetch_array($result_seccion) : false;
        $nombreSeccion = $seccionData ? (string)$seccionData['nombre'] : (string)$cod_seccion;
    }

    if (!empty($assignment['zona_principal'])) {
        $zonas[] = ['codigo' => $assignment['zona_principal'], 'nombre' => 'Zona Principal (' . $assignment['zona_principal'] . ')'];
    }
    if (!empty($assignment['zona_secundaria'])) {
        $zonas[] = ['codigo' => $assignment['zona_secundaria'], 'nombre' => 'Zona Secundaria (' . $assignment['zona_secundaria'] . ')'];
    }

    if ($zona_seleccionada === '' && !empty($zonas)) {
        $zona_seleccionada = (string)$zonas[0]['codigo'];
    }

    $tiempo_promedio_minutes = floatval($assignment['tiempo_promedio_visita']) * 60;
    $hora_inicio_manana = !empty($assignment['hora_inicio_manana']) ? substr((string)$assignment['hora_inicio_manana'], 0, 5) : '';
    $hora_fin_manana = !empty($assignment['hora_fin_manana']) ? substr((string)$assignment['hora_fin_manana'], 0, 5) : '';
    $hora_inicio_tarde = !empty($assignment['hora_inicio_tarde']) ? substr((string)$assignment['hora_inicio_tarde'], 0, 5) : '';
    $hora_fin_tarde = !empty($assignment['hora_fin_tarde']) ? substr((string)$assignment['hora_fin_tarde'], 0, 5) : '';

    $currentDate = date('Y-m-d');
    $sql_citas = "SELECT fecha_visita, hora_inicio_visita, hora_fin_visita, estado_visita
                  FROM [integral].[dbo].[cmf_visitas_comerciales]
                  WHERE cod_cliente = ? ";
    $citasParams = [$cod_cliente];
    if ($cod_seccion !== null) {
        $sql_citas .= "AND cod_seccion = ? ";
        $citasParams[] = $cod_seccion;
    } else {
        $sql_citas .= "AND cod_seccion IS NULL ";
    }
    $sql_citas .= "AND LOWER(estado_visita) IN ('planificada','pendiente')
                  AND fecha_visita >= ?
                  ORDER BY fecha_visita ASC";
    $citasParams[] = $currentDate;
    $result_citas = registrarVisitaManualPrepareExecute($conn, $sql_citas, $citasParams);
    if ($result_citas) {
        while ($cita = odbc_fetch_array($result_citas)) {
            $citas[] = $cita;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Visita Manual</title>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        h1, h3 {
            color: #333;
        }

        .mb-3 {
            margin-bottom: 15px;
        }

        .result-button {
            display: block;
            width: 100%;
            text-align: left;
            font-size: 18px;
            padding: 15px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        input[type="text"],
        input[type="date"],
        input[type="time"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-submit {
            margin-top: 15px;
            padding: 10px 20px;
        }

        #modalDefinirHorario {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
        }

        #modalDefinirHorario .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        #modalDefinirHorario .close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h1>Registrar Visita Manual</h1>

        <form method="POST" action="<?= BASE_URL ?>/registrar_visita_manual.php" class="form" id="flujoVisitaManual">
            <input type="hidden" name="accion" id="accion" value="<?php echo htmlspecialchars((string)$accion); ?>">
            <input type="hidden" name="origen" value="manual">
            <input type="hidden" name="cod_cliente" id="cod_cliente" value="<?php echo $cod_cliente > 0 ? htmlspecialchars((string)$cod_cliente) : ''; ?>">
            <input type="hidden" name="cod_seccion" id="cod_seccion" value="<?php echo $cod_seccion !== null ? htmlspecialchars((string)$cod_seccion) : ''; ?>">

            <div class="mb-3">
                <label for="buscar">Nombre del Cliente:</label>
                <input type="text" name="buscar" id="buscar" class="form-control" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Ingrese el nombre del cliente">
                <button type="submit" name="accion" value="buscar" class="btn btn-primary w-100 btn-submit">Buscar</button>
                <p class="text-center" style="margin-top:20px;">Solo se mostraran clientes asignados a tu usuario.</p>
            </div>

            <?php if ($mostrarResultados): ?>
                <hr>
                <h3>Resultados de la busqueda</h3>
                <?php if (empty($resultadosBusqueda)): ?>
                    <p class="text-center">No se encontraron clientes que cumplan con la busqueda.</p>
                <?php else: ?>
                    <?php foreach ($resultadosBusqueda as $cliente): ?>
                        <?php
                        $displayName = htmlspecialchars((string)$cliente['nombre_comercial']);
                        $clienteCod = (int)$cliente['cod_cliente'];
                        $clienteSeccion = ($cliente['cod_seccion'] === null || $cliente['cod_seccion'] === '') ? '' : (string)$cliente['cod_seccion'];
                        if ($clienteSeccion !== '') {
                            $displayName .= ' - ' . htmlspecialchars((string)$cliente['nombre_seccion']);
                        }
                        ?>
                        <button
                            type="submit"
                            name="accion"
                            value="seleccionar_cliente"
                            class="result-button"
                            onclick="document.getElementById('cod_cliente').value='<?php echo htmlspecialchars((string)$clienteCod, ENT_QUOTES, 'UTF-8'); ?>'; document.getElementById('cod_seccion').value='<?php echo htmlspecialchars($clienteSeccion, ENT_QUOTES, 'UTF-8'); ?>';"
                        >
                            <?php echo $displayName; ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($mostrarFormulario && $assignment): ?>
                <hr>

                <?php if (count($citas) > 0): ?>
                    <?php foreach ($citas as $cita): ?>
                        <?php
                        $estado = strtolower(trim((string)$cita['estado_visita']));
                        $alertClass = $estado === 'planificada' ? 'alert-info' : ($estado === 'pendiente' ? 'alert-warning' : 'alert-secondary');
                        ?>
                        <div class="alert <?php echo $alertClass; ?>">
                            <strong><?php echo htmlspecialchars((string)$cita['estado_visita']); ?>:</strong>
                            <?php echo htmlspecialchars(date('d/m/Y', strtotime((string)$cita['fecha_visita']))); ?>
                            de <?php echo htmlspecialchars(date('H:i', strtotime((string)$cita['hora_inicio_visita']))); ?>
                            a <?php echo htmlspecialchars(date('H:i', strtotime((string)$cita['hora_fin_visita']))); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($assignment['frecuencia_visita']) && strtolower((string)$assignment['frecuencia_visita']) === 'nunca'): ?>
                    <div class="alert alert-danger">Atencion: Este cliente no se visita habitualmente.</div>
                <?php endif; ?>

                <h3>Datos del Cliente y Disponibilidad</h3>
                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($nombreCliente); ?></p>
                <?php if ($nombreSeccion !== ''): ?>
                    <p><strong>Seccion:</strong> <?php echo htmlspecialchars($nombreSeccion); ?></p>
                <?php endif; ?>
                <p><strong>Tiempo Promedio de Visita:</strong>
                    <?php
                    if ($tiempo_promedio_minutes >= 60) {
                        $hours = floor($tiempo_promedio_minutes / 60);
                        $minutes = $tiempo_promedio_minutes % 60;
                        echo $hours . ' ' . ($hours == 1 ? 'hora' : 'horas');
                        if ($minutes > 0) {
                            echo ' ' . $minutes . ' minutos';
                        }
                    } else {
                        echo $tiempo_promedio_minutes . ' minutos';
                    }
                    ?>
                </p>
                <p><strong>Disponibilidad Manana:</strong> <?php echo $hora_inicio_manana !== '' ? htmlspecialchars($hora_inicio_manana) : 'No definido'; ?> a <?php echo $hora_fin_manana !== '' ? htmlspecialchars($hora_fin_manana) : 'No definido'; ?></p>
                <p><strong>Disponibilidad Tarde:</strong> <?php echo $hora_inicio_tarde !== '' ? htmlspecialchars($hora_inicio_tarde) : 'No definido'; ?> a <?php echo $hora_fin_tarde !== '' ? htmlspecialchars($hora_fin_tarde) : 'No definido'; ?></p>
                <p><strong>Preferencia Horaria:</strong> <?php echo !empty($assignment['preferencia_horaria']) ? htmlspecialchars((string)$assignment['preferencia_horaria']) : 'No definida'; ?></p>

                <button id="btnDefinirHorario" type="button" class="btn btn-info" style="margin-bottom:15px;">Definir Horario</button>

                <div id="modalDefinirHorario">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h3>Definir Horario de Disponibilidad</h3>
                        <input type="hidden" id="modal_cod_cliente" value="<?php echo htmlspecialchars((string)$cod_cliente); ?>">
                        <input type="hidden" id="modal_cod_seccion" value="<?php echo $cod_seccion !== null ? htmlspecialchars((string)$cod_seccion) : ''; ?>">
                        <div class="mb-3">
                            <label for="hora_inicio_manana">Hora Inicio Manana:</label>
                            <input type="time" class="form-control" id="hora_inicio_manana" value="<?php echo htmlspecialchars($hora_inicio_manana); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="hora_fin_manana">Hora Fin Manana:</label>
                            <input type="time" class="form-control" id="hora_fin_manana" value="<?php echo htmlspecialchars($hora_fin_manana); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="hora_inicio_tarde">Hora Inicio Tarde:</label>
                            <input type="time" class="form-control" id="hora_inicio_tarde" value="<?php echo htmlspecialchars($hora_inicio_tarde); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="hora_fin_tarde">Hora Fin Tarde:</label>
                            <input type="time" class="form-control" id="hora_fin_tarde" value="<?php echo htmlspecialchars($hora_fin_tarde); ?>" required>
                        </div>
                        <button type="button" id="guardarHorarioBtn" class="btn btn-success w-100 btn-submit">Guardar Horario</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="fecha_visita">Fecha de la Visita:</label>
                    <input type="date" class="form-control" name="fecha_visita" id="fecha_visita" value="<?php echo htmlspecialchars($fecha_visita); ?>">
                </div>
                <div class="mb-3">
                    <label for="hora_inicio_visita">Hora de Inicio:</label>
                    <input type="time" class="form-control" name="hora_inicio_visita" id="hora_inicio_visita" value="<?php echo htmlspecialchars($hora_inicio_visita); ?>">
                </div>
                <div class="mb-3">
                    <label for="hora_fin_visita">Hora de Fin:</label>
                    <input type="time" class="form-control" name="hora_fin_visita" id="hora_fin_visita" value="<?php echo htmlspecialchars($hora_fin_visita); ?>">
                </div>
                <div class="mb-3">
                    <label for="zona_seleccionada">Zona de Visita:</label>
                    <select name="zona_seleccionada" id="zona_seleccionada" class="form-select">
                        <?php foreach ($zonas as $zona): ?>
                            <option value="<?php echo htmlspecialchars((string)$zona['codigo']); ?>" <?php if ((string)$zona_seleccionada === (string)$zona['codigo']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars((string)$zona['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="estado_visita">Estado de la Visita:</label>
                    <select name="estado_visita" id="estado_visita" class="form-select">
                        <option value="Pendiente" <?php if ($estado_visita === 'Pendiente') echo 'selected'; ?>>Pendiente</option>
                        <option value="Planificada" <?php if ($estado_visita === 'Planificada') echo 'selected'; ?>>Planificada</option>
                        <option value="Realizada" <?php if ($estado_visita === 'Realizada') echo 'selected'; ?>>Realizada</option>
                        <option value="No atendida" <?php if ($estado_visita === 'No atendida') echo 'selected'; ?>>No atendida</option>
                        <option value="Descartada" <?php if ($estado_visita === 'Descartada') echo 'selected'; ?>>Descartada</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="observaciones">Observaciones (opcional):</label>
                    <textarea name="observaciones" id="observaciones" class="form-control" rows="4"><?php echo htmlspecialchars($observaciones); ?></textarea>
                </div>
                <button type="submit" name="accion" value="registrar" class="btn btn-primary w-100 btn-submit">Registrar Visita Manual</button>

                <div id="visitas_del_dia" style="margin-top:20px;">
                    <div class="alert alert-info">Seleccione una fecha para ver las visitas programadas.</div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var fechaInput = document.querySelector('#fecha_visita');
            var horaInicioInput = document.querySelector('#hora_inicio_visita');
            var horaFinInput = document.querySelector('#hora_fin_visita');
            var visitasDelDia = document.querySelector('#visitas_del_dia');
            var btnDefinirHorario = document.querySelector('#btnDefinirHorario');
            var modalDefinirHorario = document.querySelector('#modalDefinirHorario');
            var modalClose = modalDefinirHorario ? modalDefinirHorario.querySelector('.close') : null;
            var guardarHorarioBtn = document.querySelector('#guardarHorarioBtn');
            var promedio = <?php echo json_encode($tiempo_promedio_minutes); ?>;
            var emptyStateHtml = "<div class='alert alert-info'>Seleccione una fecha para ver las visitas programadas.</div>";
            var errorStateHtml = "<div class='alert alert-danger'>Error al cargar las visitas.</div>";

            function setVisitasContent(html) {
                if (visitasDelDia) {
                    visitasDelDia.innerHTML = html;
                }
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
                    return;
                }

                var fecha = fechaInput.value;
                if (!fecha) {
                    setVisitasContent(emptyStateHtml);
                    return;
                }

                var url = new URL("<?= BASE_URL ?>/get_visitas.php", window.location.origin);
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
                    })
                    .catch(function(error) {
                        console.error('Error cargando visitas:', error);
                        setVisitasContent(errorStateHtml);
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
                horaInicioInput.addEventListener('change', calcularHoraFin);
            }

            if (fechaInput) {
                fechaInput.addEventListener('change', cargarVisitasDelDia);
                if (fechaInput.value !== '') {
                    cargarVisitasDelDia();
                }
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
                    payload.append('cod_cliente', document.querySelector('#modal_cod_cliente').value);
                    payload.append('cod_seccion', document.querySelector('#modal_cod_seccion').value);
                    payload.append('hora_inicio_manana', document.querySelector('#hora_inicio_manana').value);
                    payload.append('hora_fin_manana', document.querySelector('#hora_fin_manana').value);
                    payload.append('hora_inicio_tarde', document.querySelector('#hora_inicio_tarde').value);
                    payload.append('hora_fin_tarde', document.querySelector('#hora_fin_tarde').value);

                    fetch("<?= BASE_URL ?>/definir_horario.php", {
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
                                alert('Horario guardado correctamente.');
                                window.location.reload();
                            } else {
                                alert('Error: ' + responseText);
                            }
                        })
                        .catch(function(error) {
                            console.error('Error guardando horario:', error);
                            alert('Error en la peticion.');
                        });
                });
            }
        });
    </script>
</body>
</html>
