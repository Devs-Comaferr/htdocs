<?php
// Pantalla unificada para registro manual de visitas (flujo completo en un solo archivo)

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
$mostrarResultados = ($accion === 'buscar');
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

if ($accion === 'buscar') {
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

if ($cod_cliente > 0) {
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
    } else {
        $assignment = odbc_fetch_array($result_assignment);
        if (!$assignment) {
            $error = 'Error interno';
        }
    }
}

if ($cod_cliente > 0 && $assignment) {
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
    $sql_citas .= "AND estado_visita IN ('Planificada','Pendiente')
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

        #horario_modalDefinirHorario {
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

        #horario_modalDefinirHorario .modal-content {
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

        #horario_modalDefinirHorario .close {
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

        <div class="mb-3">
            <span class="badge <?php echo $cod_cliente == 0 ? 'bg-primary' : 'bg-secondary'; ?>">Paso 1: Buscar cliente</span>
            <?php if ($cod_cliente > 0): ?>
                <span class="badge bg-primary ms-1">Paso 2: Cliente seleccionado</span>
                <span class="badge bg-primary ms-1">Paso 3: Registrar visita</span>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/visitas.php?action=crear" class="form" id="flujoVisitaManual">
            <input type="hidden" name="origen" value="manual">
            <input type="hidden" name="cod_cliente" id="cod_cliente" value="<?php echo $cod_cliente > 0 ? htmlspecialchars((string)$cod_cliente) : ''; ?>">
            <input type="hidden" name="cod_seccion" id="cod_seccion" value="<?php echo $cod_seccion !== null ? htmlspecialchars((string)$cod_seccion) : ''; ?>">

            <?php if ($cod_cliente == 0): ?>
                <div class="mb-3">
                    <label for="buscar">Nombre del Cliente:</label>
                    <input type="text" name="buscar" id="buscar" class="form-control" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Introduce el nombre del cliente">
                    <button type="submit" name="accion" value="buscar" formaction="<?= BASE_URL ?>/registrar_visita_manual.php" class="btn btn-primary w-100 btn-submit">Buscar</button>
                    <p class="text-center" style="margin-top:20px;">Solo se mostrarán clientes asignados a tu usuario.</p>
                </div>
            <?php endif; ?>

            <?php if ($mostrarResultados && $cod_cliente == 0): ?>
                <hr>
                <h3>Resultados de la búsqueda</h3>
                <?php if (empty($resultadosBusqueda)): ?>
                    <p class="text-center">No se encontraron clientes que cumplan con la búsqueda.</p>
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
                            formaction="<?= BASE_URL ?>/registrar_visita_manual.php"
                            class="result-button"
                            onclick="document.getElementById('cod_cliente').value='<?php echo htmlspecialchars((string)$clienteCod, ENT_QUOTES, 'UTF-8'); ?>'; document.getElementById('cod_seccion').value='<?php echo htmlspecialchars($clienteSeccion, ENT_QUOTES, 'UTF-8'); ?>';"
                        >
                            <?php echo $displayName; ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($cod_cliente > 0 && $assignment): ?>
                <hr>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="alert alert-primary mb-0 py-2 px-3">
                        <strong>Cliente seleccionado:</strong>
                        <?php echo htmlspecialchars($nombreCliente); ?>
                        <span class="badge bg-light text-dark ms-2">#<?php echo htmlspecialchars((string)$cod_cliente); ?></span>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="window.location='<?= BASE_URL ?>/registrar_visita_manual.php'">Cambiar cliente</button>
                </div>

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
                    <div class="alert alert-danger">Atención: Este cliente no se visita habitualmente.</div>
                <?php endif; ?>

                <h3>Datos del Cliente y Disponibilidad</h3>
                <?php if ($nombreSeccion !== ''): ?>
                    <p><strong>Sección:</strong> <?php echo htmlspecialchars($nombreSeccion); ?></p>
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
                <p><strong>Disponibilidad Mañana:</strong> <?php echo $hora_inicio_manana !== '' ? htmlspecialchars($hora_inicio_manana) : 'No definido'; ?> a <?php echo $hora_fin_manana !== '' ? htmlspecialchars($hora_fin_manana) : 'No definido'; ?></p>
                <p><strong>Disponibilidad Tarde:</strong> <?php echo $hora_inicio_tarde !== '' ? htmlspecialchars($hora_inicio_tarde) : 'No definido'; ?> a <?php echo $hora_fin_tarde !== '' ? htmlspecialchars($hora_fin_tarde) : 'No definido'; ?></p>
                <p><strong>Preferencia Horaria:</strong> <?php echo !empty($assignment['preferencia_horaria']) ? htmlspecialchars((string)$assignment['preferencia_horaria']) : 'No definida'; ?></p>

                <button id="horario_btnDefinirHorario" type="button" class="btn btn-info" style="margin-bottom:15px;">Definir Horario</button>

                <div id="horario_modalDefinirHorario">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h3>Definir Horario de Disponibilidad</h3>
                        <form id="formDefinirHorario">
                            <input type="hidden" id="horario_modal_cod_cliente" value="<?php echo htmlspecialchars((string)$cod_cliente); ?>">
                            <input type="hidden" id="horario_modal_cod_seccion" value="<?php echo $cod_seccion !== null ? htmlspecialchars((string)$cod_seccion) : ''; ?>">
                            <div class="mb-3">
                                <label for="horario_inicio_manana">Hora Inicio Mañana:</label>
                                <input type="time" class="form-control" id="horario_inicio_manana" value="<?php echo htmlspecialchars($hora_inicio_manana); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="horario_fin_manana">Hora Fin Mañana:</label>
                                <input type="time" class="form-control" id="horario_fin_manana" value="<?php echo htmlspecialchars($hora_fin_manana); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="horario_inicio_tarde">Hora Inicio Tarde:</label>
                                <input type="time" class="form-control" id="horario_inicio_tarde" value="<?php echo htmlspecialchars($hora_inicio_tarde); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="horario_fin_tarde">Hora Fin Tarde:</label>
                                <input type="time" class="form-control" id="horario_fin_tarde" value="<?php echo htmlspecialchars($hora_fin_tarde); ?>" required>
                            </div>
                            <button type="button" id="horario_guardarHorarioBtn" class="btn btn-success w-100 btn-submit">Guardar Horario</button>
                        </form>
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
                <div id="mensajes_validacion"></div>
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
            var btnDefinirHorario = document.querySelector('#horario_btnDefinirHorario');
            var modalDefinirHorario = document.querySelector('#horario_modalDefinirHorario');
            var modalClose = modalDefinirHorario ? modalDefinirHorario.querySelector('.close') : null;
            var guardarHorarioBtn = document.querySelector('#horario_guardarHorarioBtn');
            var flujoVisitaManual = document.querySelector('#flujoVisitaManual');
            var formDefinirHorario = document.querySelector('#formDefinirHorario');
            var estadoVisitaInput = document.querySelector('#estado_visita');
            var promedio = <?php echo json_encode($tiempo_promedio_minutes); ?>;
            var horarioCliente = {
                mananaInicio: <?php echo json_encode($hora_inicio_manana); ?>,
                mananaFin: <?php echo json_encode($hora_fin_manana); ?>,
                tardeInicio: <?php echo json_encode($hora_inicio_tarde); ?>,
                tardeFin: <?php echo json_encode($hora_fin_tarde); ?>
            };
            var emptyStateHtml = "<div class='alert alert-info'>Seleccione una fecha para ver las visitas programadas.</div>";
            var errorStateHtml = "<div class='alert alert-danger'>Error al cargar las visitas.</div>";
            var usuarioTocoHoraInicio = !!(horaInicioInput && horaInicioInput.value);

            function setVisitasContent(html) {
                if (visitasDelDia) {
                    visitasDelDia.innerHTML = html;
                }
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
                    return;
                }

                var duracionMinutos = parseInt(promedio, 10);
                if (!duracionMinutos || duracionMinutos <= 0) {
                    return;
                }

                var visitas = parsearVisitasDesdeHtml();
                var hueco = calcularSiguienteHueco(visitas, duracionMinutos);
                if (!hueco) {
                    return;
                }

                horaInicioInput.value = hueco.inicio;
                horaFinInput.value = hueco.fin;
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

                var clienteInput = document.querySelector('#cod_cliente');
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
                    return { ok: false, tipo: 'error', mensaje: 'Ya tienes una visita en ese horario', campos: [horaInicioInput, horaFinInput] };
                }

                if (!estaDentroHorarioCliente(nuevoInicio, nuevoFin)) {
                    marcarCampoError(horaInicioInput, true);
                    marcarCampoError(horaFinInput, true);

                    var estado = estadoVisitaInput ? estadoVisitaInput.value : '';
                    if (estado === 'Planificada' || estado === 'Pendiente') {
                        return { ok: false, tipo: 'error', mensaje: 'La visita está fuera del horario del cliente', campos: [horaInicioInput, horaFinInput] };
                    }

                    if (estado === 'Realizada' || estado === 'No atendida' || estado === 'Descartada') {
                        return { ok: false, tipo: 'warning', mensaje: 'La visita está fuera del horario del cliente', permiteContinuar: true, campos: [horaInicioInput, horaFinInput] };
                    }
                }

                if (usuarioTocoHoraInicio && !existeHuecoManualValido(nuevoInicio, nuevoFin, visitas)) {
                    marcarCampoError(horaInicioInput, true);
                    marcarCampoError(horaFinInput, true);
                    return { ok: false, tipo: 'error', mensaje: 'La hora no encaja en un hueco disponible', campos: [horaInicioInput, horaFinInput] };
                }

                return { ok: true };
            }

            function validarEnTiempoReal() {
                var resultado = evaluarValidacionesHorario();
                if (resultado.ok) {
                    limpiarMensajes();
                    return true;
                }

                if (resultado.tipo === 'error') {
                    mostrarMensaje('error', resultado.mensaje);
                } else if (resultado.tipo === 'warning') {
                    mostrarMensaje('warning', '⚠️ ' + resultado.mensaje + ' (puedes continuar)');
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
                        aplicarSiguienteHuecoAutomatico();
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
                horaInicioInput.addEventListener('input', function() {
                    usuarioTocoHoraInicio = true;
                });
                horaInicioInput.addEventListener('change', calcularHoraFin);
                horaInicioInput.addEventListener('change', validarEnTiempoReal);
            }

            if (fechaInput) {
                fechaInput.addEventListener('change', cargarVisitasDelDia);
                fechaInput.addEventListener('change', validarEnTiempoReal);
                if (fechaInput.value !== '') {
                    cargarVisitasDelDia();
                }
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
                    payload.append('horario_inicio_manana', document.querySelector('#horario_inicio_manana').value);
                    payload.append('horario_fin_manana', document.querySelector('#horario_fin_manana').value);
                    payload.append('horario_inicio_tarde', document.querySelector('#horario_inicio_tarde').value);
                    payload.append('horario_fin_tarde', document.querySelector('#horario_fin_tarde').value);

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
                                mostrarMensaje('info', 'Horario guardado correctamente.');
                                window.location.reload();
                            } else {
                                mostrarMensaje('error', 'Error: ' + responseText);
                            }
                        })
                        .catch(function(error) {
                            console.error('Error guardando horario:', error);
                            mostrarMensaje('error', 'Error en la petición.');
                        });
                });
            }

            if (flujoVisitaManual) {
                flujoVisitaManual.addEventListener('submit', function(event) {
                    var submitter = event.submitter;
                    if (!submitter || submitter.value !== 'registrar') {
                        return;
                    }

                    var resultado = evaluarValidacionesHorario();
                    if (resultado.ok) {
                        limpiarMensajes();
                        return;
                    }

                    event.preventDefault();

                    if (resultado.tipo === 'warning' && resultado.permiteContinuar) {
                        mostrarMensaje('warning', '⚠️ ' + resultado.mensaje + ' (puedes continuar)');
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
    </script>
</body>
</html>
