<?php
// ⚠️ ARCHIVO LEGACY
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

$pageTitle = "Registrar Visita";

/* ---------------------------------------------------------------------------
   Sección de búsqueda de clientes (cuando aún no se ha seleccionado un cliente)
--------------------------------------------------------------------------- */
if (!isset($_GET['cod_cliente']) || empty($_GET['cod_cliente'])) {
    if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
        $busqueda = trim((string)$_GET['buscar']);
        $sql_busqueda = "SELECT cl.cod_cliente, cl.nombre_comercial, sc.cod_seccion, sc.nombre AS nombre_seccion
                         FROM [integral].[dbo].[clientes] cl
                         LEFT JOIN [integral].[dbo].[secciones_cliente] sc 
                           ON cl.cod_cliente = sc.cod_cliente
                         WHERE cl.nombre_comercial LIKE ?
                           AND cl.cod_vendedor = ?";
        $result_busqueda = registrarVisitaManualPrepareExecute($conn, $sql_busqueda, ['%' . $busqueda . '%', $codigo_vendedor]);
        if (!$result_busqueda) {
            error_log("Error en la busqueda.");
            echo 'Error interno';
            return;
        }
?>
        <!DOCTYPE html>
        <html lang="es">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Buscar Cliente para Visita Manual</title>
            <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
                        <style>
                body {
                    background-color: #f8f9fa;
                    padding: 20px;
                    font-family: Arial, sans-serif;
                }

                .container {
                    max-width: 600px;
                    margin: auto;
                    background: #fff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }

                h1 {
                    text-align: center;
                    margin-bottom: 30px;
                    font-size: 28px;
                }

                .list-group-item {
                    font-size: 18px;
                    padding: 15px;
                }

                .back-link {
                    display: block;
                    text-align: center;
                    margin-top: 20px;
                    font-size: 18px;
                    color: #007bff;
                    text-decoration: none;
                }

                @media (max-width: 480px) {
                    .container {
                        padding: 20px;
                    }

                    h1 {
                        font-size: 24px;
                    }

                    .list-group-item,
                    .back-link {
                        font-size: 16px;
                    }
                }
            </style>
        </head>

        <body>
            <div class="container">
                <h1>Resultados de la búsqueda</h1>
                <div class="list-group">
                    <?php
                    $hayResultados = false;
                    while ($cliente = odbc_fetch_array($result_busqueda)) {
                        $hayResultados = true;
                        $displayName = htmlspecialchars($cliente['nombre_comercial']);
                        if ($cliente['cod_seccion'] !== null) {
                            $displayName .= " - " . htmlspecialchars($cliente['nombre_seccion']);
                            $link = BASE_URL . "/registrar_visita_manual.php?cod_cliente=" . $cliente['cod_cliente'] . "&cod_seccion=" . $cliente['cod_seccion'];
                        } else {
                            $link = BASE_URL . "/registrar_visita_manual.php?cod_cliente=" . $cliente['cod_cliente'];
                        }
                        echo "<a href='$link' class='list-group-item'>$displayName</a>";
                    }
                    if (!$hayResultados) {
                        echo "<p class='text-center'>No se encontraron clientes que cumplan con la búsqueda.</p>";
                    }
                    ?>
                </div>
                <a href="<?= BASE_URL ?>/registrar_visita_manual.php" class="back-link">Realizar nueva búsqueda</a>
            </div>
        </body>

        </html>
    <?php
        exit();
    } else {
        // Mostrar formulario de búsqueda
    ?>
        <!DOCTYPE html>
        <html lang="es">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Buscar Cliente para Visita Manual</title>
            <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
                        <style>
                body {
                    background-color: #f8f9fa;
                    padding: 20px;
                    font-family: Arial, sans-serif;
                }

                .container {
                    max-width: 600px;
                    margin: auto;
                    background: #fff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }

                h1 {
                    text-align: center;
                    margin-bottom: 30px;
                    font-size: 28px;
                }

                label {
                    font-size: 18px;
                }

                input[type="text"] {
                    font-size: 18px;
                    padding: 10px;
                }

                input[type="submit"] {
                    font-size: 18px;
                    padding: 10px 20px;
                }

                @media (max-width: 480px) {
                    .container {
                        padding: 20px;
                    }

                    h1,
                    label,
                    input[type="text"],
                    input[type="submit"] {
                        font-size: 16px;
                    }
                }
            </style>
        </head>

        <body>
            <div class="container">
                <h1>Buscar Cliente para Visita Manual</h1>
                <form method="get" action="<?= BASE_URL ?>/registrar_visita_manual.php">
                    <input type="hidden" name="action" value="crear">
                    <div class="mb-3">
                        <label for="buscar">Nombre del Cliente:</label>
                        <input type="text" name="buscar" id="buscar" class="form-control" placeholder="Ingrese el nombre del cliente" required>
                    </div>
                    <input type="submit" class="btn btn-primary w-100 btn-submit" value="Buscar">
                </form>
                <p class="text-center" style="margin-top:20px;">Solo se mostrarn clientes asignados a tu usuario.</p>
            </div>
        </body>

        </html>
<?php
        exit();
    }
}

/* ---------------------------------------------------------------------------
   MODO REGISTRO DE VISITA MANUAL (cliente seleccionado)
--------------------------------------------------------------------------- */
$cod_cliente = intval($_GET['cod_cliente']);
$cod_seccion = (isset($_GET['cod_seccion']) && $_GET['cod_seccion'] !== '') ? intval($_GET['cod_seccion']) : null;

// Recuperar la asignación del cliente filtrando por sección si procede.
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
    error_log("Error en la consulta de asignacion.");
    echo 'Error interno';
    return;
}
$assignment = odbc_fetch_array($result_assignment);
if (!$assignment) {
    error_log("No se encontró asignación para este cliente" . ($cod_seccion !== null ? " (Sección: $cod_seccion)" : ""));
    echo 'Error interno';
    return;
}

// Obtener nombre comercial del cliente
$sql_cliente = "SELECT nombre_comercial FROM [integral].[dbo].[clientes] WHERE cod_cliente = ?";
$result_cliente = registrarVisitaManualPrepareExecute($conn, $sql_cliente, [$cod_cliente]);
$clienteData = odbc_fetch_array($result_cliente);
$nombreCliente = $clienteData ? $clienteData['nombre_comercial'] : $cod_cliente;

// Obtener nombre de la sección, si procede
if ($cod_seccion !== null) {
    $sql_seccion = "SELECT nombre FROM [integral].[dbo].[secciones_cliente] WHERE cod_cliente = ? AND cod_seccion = ?";
    $result_seccion = registrarVisitaManualPrepareExecute($conn, $sql_seccion, [$cod_cliente, $cod_seccion]);
    $seccionData = odbc_fetch_array($result_seccion);
    $nombreSeccion = $seccionData ? $seccionData['nombre'] : $cod_seccion;
} else {
    $nombreSeccion = "";
}

// Opciones de zona
$zonas = array();
if (!empty($assignment['zona_principal'])) {
    $zonas[] = array('codigo' => $assignment['zona_principal'], 'nombre' => 'Zona Principal (' . $assignment['zona_principal'] . ')');
}
if (!empty($assignment['zona_secundaria'])) {
    $zonas[] = array('codigo' => $assignment['zona_secundaria'], 'nombre' => 'Zona Secundaria (' . $assignment['zona_secundaria'] . ')');
}

// Tiempo promedio de visita (en minutos)
// Nota: $assignment['tiempo_promedio_visita'] se entiende que est en horas, por lo que se multiplica por 60.
$tiempo_promedio = floatval($assignment['tiempo_promedio_visita']);
$tiempo_promedio_minutes = $tiempo_promedio * 60;

// Disponibilidad horaria (formato HH:MM)
$hora_inicio_manana = !empty($assignment['hora_inicio_manana']) ? substr($assignment['hora_inicio_manana'], 0, 5) : "";
$hora_fin_manana    = !empty($assignment['hora_fin_manana']) ? substr($assignment['hora_fin_manana'], 0, 5) : "";
$hora_inicio_tarde  = !empty($assignment['hora_inicio_tarde']) ? substr($assignment['hora_inicio_tarde'], 0, 5) : "";
$hora_fin_tarde     = !empty($assignment['hora_fin_tarde']) ? substr($assignment['hora_fin_tarde'], 0, 5) : "";

// Consultar visitas programadas o pendientes para este cliente y sección (o IS NULL)
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
$citas = array();
if ($result_citas) {
    while ($cita = odbc_fetch_array($result_citas)) {
        $citas[] = $cita;
    }
}

$error = '';
$success = '';
$fecha_visita = '';
$hora_inicio_visita = '';
$hora_fin_visita = '';
$zona_seleccionada = $zonas[0]['codigo'] ?? '';
$estado_visita = 'Planificada';
$observaciones = '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registrar Visita Manual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
        <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1,
        h3 {
            color: #333;
        }

        .mb-3 {
            margin-bottom: 15px;
        }

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

        input[type="submit"],
        .btn-submit {
            margin-top: 15px;
            padding: 10px 20px;
        }

        .alert {
            margin-top: 20px;
        }

        /* Modal estilos */
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

        #modalDefinirHorario .close:hover,
        #modalDefinirHorario .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Alertas de citas programadas o pendientes (filtradas por cliente y sección) -->
        <?php if (count($citas) > 0): ?>
            <?php foreach ($citas as $cita):
                $estado = normalizarEstadoVisitaClave($cita['estado_visita']);
                $alertClass = ($estado == 'planificada') ? 'alert-info' : (($estado == 'pendiente') ? 'alert-warning' : 'alert-secondary');
            ?>
                <div class="alert <?php echo $alertClass; ?>">
                    <strong><?php echo htmlspecialchars(normalizarEstadoVisita($cita['estado_visita'])); ?>:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($cita['fecha_visita']))); ?> de <?php echo htmlspecialchars(date("H:i", strtotime($cita['hora_inicio_visita']))); ?> a <?php echo htmlspecialchars(date("H:i", strtotime($cita['hora_fin_visita']))); ?>
                </div>
            <?php endforeach; ?>
        <?php endif;

        if (!empty($assignment['frecuencia_visita']) && strtolower($assignment['frecuencia_visita']) == 'nunca') {
            echo '<div class="alert alert-danger">Atención: Este cliente no se visita habitualmente.</div>';
        }
        ?>

        <h3>Datos del Cliente y Disponibilidad</h3>
        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($nombreCliente); ?></p>
        <?php if (!empty($nombreSeccion)): ?>
            <p><strong>Sección:</strong> <?php echo htmlspecialchars($nombreSeccion); ?></p>
        <?php endif; ?>
        <p><strong>Tiempo Promedio de Visita:</strong>
            <?php
            // Mostrar el tiempo promedio en horas y minutos (si horas es 0, solo minutos)
            if ($tiempo_promedio_minutes >= 60) {
                $hours = floor($tiempo_promedio_minutes / 60);
                $minutes = $tiempo_promedio_minutes % 60;
                if ($hours > 0) {
                    echo $hours . " " . ($hours == 1 ? "hora" : "horas");
                    if ($minutes > 0) {
                        echo " " . $minutes . " minutos";
                    }
                } else {
                    echo $tiempo_promedio_minutes . " minutos";
                }
            } else {
                echo $tiempo_promedio_minutes . " minutos";
            }
            ?>
        </p>
        <p>
            <strong>Disponibilidad Mañana:</strong>
            <?php echo !empty($hora_inicio_manana) ? $hora_inicio_manana : 'No definido'; ?> a
            <?php echo !empty($hora_fin_manana) ? $hora_fin_manana : 'No definido'; ?>
        </p>
        <p>
            <strong>Disponibilidad Tarde:</strong>
            <?php echo !empty($hora_inicio_tarde) ? $hora_inicio_tarde : 'No definido'; ?> a
            <?php echo !empty($hora_fin_tarde) ? $hora_fin_tarde : 'No definido'; ?>
        </p>
        <p>
            <strong>Preferencia Horaria:</strong>
            <?php echo !empty($assignment['preferencia_horaria']) ? ($assignment['preferencia_horaria'] == 'M' ? 'Mañana' : ($assignment['preferencia_horaria'] == 'T' ? 'Tarde' : $assignment['preferencia_horaria'])) : 'No definida'; ?>
        </p>
        <button id="btnDefinirHorario" type="button" class="btn btn-info" style="margin-bottom:15px;">Definir Horario</button>

        <!-- Modal para definir horario (se cierra al hacer clic fuera) -->
        <div id="modalDefinirHorario" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Definir Horario de Disponibilidad</h3>
                <form id="formDefinirHorario">
                    <input type="hidden" name="cod_cliente" value="<?php echo $cod_cliente; ?>" />
                    <?php if ($cod_seccion !== null): ?>
                        <input type="hidden" name="cod_seccion" value="<?php echo $cod_seccion; ?>" />
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="hora_inicio_manana">Hora Inicio Mañana:</label>
                        <input type="time" class="form-control" name="hora_inicio_manana" id="hora_inicio_manana" value="<?php echo !empty($hora_inicio_manana) ? $hora_inicio_manana : ''; ?>" required />
                    </div>
                    <div class="mb-3">
                        <label for="hora_fin_manana">Hora Fin Mañana:</label>
                        <input type="time" class="form-control" name="hora_fin_manana" id="hora_fin_manana" value="<?php echo !empty($hora_fin_manana) ? $hora_fin_manana : ''; ?>" required />
                    </div>
                    <div class="mb-3">
                        <label for="hora_inicio_tarde">Hora Inicio Tarde:</label>
                        <input type="time" class="form-control" name="hora_inicio_tarde" id="hora_inicio_tarde" value="<?php echo !empty($hora_inicio_tarde) ? $hora_inicio_tarde : ''; ?>" required />
                    </div>
                    <div class="mb-3">
                        <label for="hora_fin_tarde">Hora Fin Tarde:</label>
                        <input type="time" class="form-control" name="hora_fin_tarde" id="hora_fin_tarde" value="<?php echo !empty($hora_fin_tarde) ? $hora_fin_tarde : ''; ?>" required />
                    </div>
                    <button type="submit" class="btn btn-success w-100 btn-submit">Guardar Horario</button>
                </form>
            </div>
        </div>

        <!-- Formulario para registrar visita manual -->
        <form method="POST" action="<?= BASE_URL ?>/visitas.php?action=crear" class="form">
            <input type="hidden" name="origen" value="manual">
            <input type="hidden" name="cod_cliente" value="<?php echo $cod_cliente; ?>">
            <?php if ($cod_seccion !== null): ?>
                <input type="hidden" name="cod_seccion" value="<?php echo $cod_seccion; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="fecha_visita">Fecha de la Visita:</label>
                <input type="date" class="form-control" name="fecha_visita" id="fecha_visita" value="<?php echo htmlspecialchars($fecha_visita); ?>" />
            </div>
            <div class="mb-3">
                <label for="hora_inicio_visita">Hora de Inicio:</label>
                <input type="time" class="form-control" name="hora_inicio_visita" id="hora_inicio_visita" value="<?php echo htmlspecialchars($hora_inicio_visita); ?>" />
            </div>
            <div class="mb-3">
                <label for="hora_fin_visita">Hora de Fin:</label>
                <input type="time" class="form-control" name="hora_fin_visita" id="hora_fin_visita" value="<?php echo htmlspecialchars($hora_fin_visita); ?>" />
            </div>
            <div class="mb-3">
                <label for="zona_seleccionada">Zona de Visita:</label>
                <select name="zona_seleccionada" id="zona_seleccionada" class="form-select">
                    <?php foreach ($zonas as $zona): ?>
                        <option value="<?php echo $zona['codigo']; ?>" <?php if ((string)$zona_seleccionada === (string)$zona['codigo']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($zona['nombre']); ?>
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
            <button type="submit" class="btn btn-primary w-100 btn-submit">Registrar Visita Manual</button>
        </form>

        <!-- Div para mostrar las visitas del da seleccionado -->
        <div id="visitas_del_dia">
            <div class="alert alert-info">Seleccione una fecha para ver las visitas programadas.</div>
        </div>
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
            var formDefinirHorario = document.querySelector('#formDefinirHorario');
            var promedio = <?php echo $tiempo_promedio_minutes; ?>;
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
                date.setMinutes(parseInt(parts[1], 10) + promedio);

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

            if (fechaInput && fechaInput.value === '') {
                setVisitasContent(emptyStateHtml);
            }

            if (horaInicioInput) {
                horaInicioInput.addEventListener('change', calcularHoraFin);
            }

            if (fechaInput) {
                fechaInput.addEventListener('change', cargarVisitasDelDia);
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

            if (formDefinirHorario) {
                formDefinirHorario.addEventListener('submit', function(event) {
                    event.preventDefault();

                    var formData = new FormData(formDefinirHorario);
                    var payload = new URLSearchParams();
                    formData.forEach(function(value, key) {
                        payload.append(key, value);
                    });

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
                            alert('Error en la petici�n.');
                        });
                });
            }
        });
    </script>
</body>

</html>
<?php
?>



