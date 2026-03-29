<?php
// ⚠️ ARCHIVO LEGACY
// Este archivo ya no debe usarse directamente.
// Se mantiene por compatibilidad.
// Usar /visitas.php?action=crear|editar|eliminar

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Visitas/RegistrarVisita.php';
requierePermiso('perm_planificador');

$conn = db();

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;

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
include(BASE_PATH . '/resources/views/layouts/header.php');

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
            <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
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
                            $link = "registrar_visita_manual.php?cod_cliente=" . $cliente['cod_cliente'] . "&cod_seccion=" . $cliente['cod_seccion'];
                        } else {
                            $link = "registrar_visita_manual.php?cod_cliente=" . $cliente['cod_cliente'];
                        }
                        echo "<a href='$link' class='list-group-item'>$displayName</a>";
                    }
                    if (!$hayResultados) {
                        echo "<p class='text-center'>No se encontraron clientes que cumplan con la búsqueda.</p>";
                    }
                    ?>
                </div>
                <a href="registrar_visita_manual.php" class="back-link">Realizar nueva búsqueda</a>
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
            <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
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
                <form method="get" action="<?= BASE_URL ?>/visitas.php">
                    <input type="hidden" name="action" value="crear">
                    <div class="form-group">
                        <label for="buscar">Nombre del Cliente:</label>
                        <input type="text" name="buscar" id="buscar" class="form-control" placeholder="Ingrese el nombre del cliente" required>
                    </div>
                    <input type="submit" class="btn btn-primary btn-block" value="Buscar">
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
$requiereConfirmacion = false;
// Forzar formato de fecha a YYYY-MM-DD
$fecha_visita = isset($_POST['fecha_visita']) ? date('Y-m-d', strtotime(trim($_POST['fecha_visita']))) : "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha_visita = date('Y-m-d', strtotime(trim($_POST['fecha_visita'])));
    $hora_inicio_visita = trim($_POST['hora_inicio_visita']);
    $hora_fin_visita = trim($_POST['hora_fin_visita']);
    $zona_seleccionada = intval($_POST['zona_seleccionada']);
    $estado_visita = isset($_POST['estado_visita']) ? normalizarEstadoVisita(trim($_POST['estado_visita'])) : 'Planificada';
    $observaciones = trim($_POST['observaciones']);

    if (empty($fecha_visita) || empty($hora_inicio_visita) || empty($hora_fin_visita)) {
        $error = "Por favor, complete la fecha y las horas de la visita.";
    } else {
        if (strtotime($hora_inicio_visita) >= strtotime($hora_fin_visita)) {
            $error = "La hora de inicio debe ser anterior a la de fin.";
        } else {
            // Determinar la franja (slot) en la que se encuentra la visita
            $slot = '';
            if (!empty($hora_inicio_manana) && !empty($hora_fin_manana)) {
                $morning_start = strtotime($hora_inicio_manana);
                $morning_end = strtotime($hora_fin_manana);
                if (strtotime($hora_inicio_visita) >= $morning_start && strtotime($hora_inicio_visita) < $morning_end) {
                    $slot = 'morning';
                }
            }
            if (empty($slot) && !empty($hora_inicio_tarde) && !empty($hora_fin_tarde)) {
                $afternoon_start = strtotime($hora_inicio_tarde);
                $afternoon_end = strtotime($hora_fin_tarde);
                if (strtotime($hora_inicio_visita) >= $afternoon_start && strtotime($hora_inicio_visita) < $afternoon_end) {
                    $slot = 'afternoon';
                }
            }
            if (empty($slot)) {
                $error = "La hora de inicio de la visita no se encuentra dentro de las franjas de disponibilidad del cliente.";
            } else {
                // Validar que la hora de fin no supere el cierre del slot
                if ($slot == 'morning' && strtotime($hora_fin_visita) > strtotime($hora_fin_manana)) {
                    $error = "La hora de fin de la visita no puede ser posterior a la hora de cierre de la mañana ($hora_fin_manana).";
                } elseif ($slot == 'afternoon' && strtotime($hora_fin_visita) > strtotime($hora_fin_tarde)) {
                    $error = "La hora de fin de la visita no puede ser posterior a la hora de cierre de la tarde ($hora_fin_tarde).";
                } else {
                    // Validación adicional: No se debe solapar la nueva visita con ninguna ya registrada para este vendedor en el mismo día
                    $overlap = false;
                    $overlapDetails = "";
                    $sql_overlap = "SELECT * FROM [integral].[dbo].[cmf_visitas_comerciales]
                                    WHERE cod_vendedor = ?
                                      AND CONVERT(varchar(10), fecha_visita, 120) = ?
                                      AND LOWER(estado_visita) IN ('planificada','pendiente')";
                    $result_overlap = registrarVisitaManualPrepareExecute($conn, $sql_overlap, [$codigo_vendedor, $fecha_visita]);
                    if ($result_overlap) {
                        while ($row = odbc_fetch_array($result_overlap)) {
                            $existing_start = strtotime($row['hora_inicio_visita']);
                            $existing_end = strtotime($row['hora_fin_visita']);
                            $new_start = strtotime($hora_inicio_visita);
                            $new_end = strtotime($hora_fin_visita);
                            if ($new_start < $existing_end && $new_end > $existing_start) {
                                $overlap = true;
                                // Recuperar el nombre del cliente para la visita solapada
                                $overlapCliente = "";
                                $sql_cliente_overlap = "SELECT nombre_comercial FROM [integral].[dbo].[clientes] WHERE cod_cliente = ?";
                                $result_cliente_overlap = registrarVisitaManualPrepareExecute($conn, $sql_cliente_overlap, [$row['cod_cliente']]);
                                if ($result_cliente_overlap) {
                                    $data_cliente_overlap = odbc_fetch_array($result_cliente_overlap);
                                    $overlapCliente = $data_cliente_overlap ? $data_cliente_overlap['nombre_comercial'] : "";
                                }
                                // Si la visita solapada tiene asignada una sección, se recupera su nombre
                                $overlapSeccion = "";
                                if ($row['cod_seccion'] !== null) {
                                    $overlap_seccion = intval($row['cod_seccion']);
                                    $sql_overlap_seccion = "SELECT nombre FROM [integral].[dbo].[secciones_cliente] WHERE cod_cliente = ? AND cod_seccion = ?";
                                    $result_overlap_seccion = registrarVisitaManualPrepareExecute($conn, $sql_overlap_seccion, [$row['cod_cliente'], $overlap_seccion]);
                                    if ($result_overlap_seccion) {
                                        $data_overlap_seccion = odbc_fetch_array($result_overlap_seccion);
                                        $overlapSeccion = $data_overlap_seccion ? $data_overlap_seccion['nombre'] : "";
                                    }
                                }
                                $overlapDetails = " " . $overlapCliente;
                                if (!empty($overlapSeccion)) {
                                    $overlapDetails .= " - " . $overlapSeccion;
                                }
                                $overlapDetails .= " de " . date("H:i", $existing_start) . " a " . date("H:i", $existing_end);
                                break;
                            }
                        }
                    }
                    if ($overlap) {
                        $error = "Existe una visita programada que se solapa con la visita que intenta registrar:" . $overlapDetails . ".";
                    } else {
                        // Consulta para calcular los minutos ya usados en el slot
                        $sql_visitas_slot = "SELECT hora_inicio_visita, hora_fin_visita 
                            FROM [integral].[dbo].[cmf_visitas_comerciales] 
                            WHERE cod_vendedor = ? 
                              AND CONVERT(varchar(10), fecha_visita, 120) = ?
                              AND LOWER(estado_visita) IN ('planificada', 'pendiente')";
                        $result_visitas_slot = registrarVisitaManualPrepareExecute($conn, $sql_visitas_slot, [$codigo_vendedor, $fecha_visita]);
                        if (!$result_visitas_slot) {
                            $error = "Error al consultar visitas programadas.";
                        } else {
                            $used_minutes = 0;
                            if ($slot == 'morning') {
                                while ($row = odbc_fetch_array($result_visitas_slot)) {
                                    $visita_start = strtotime($row['hora_inicio_visita']);
                                    if ($visita_start >= strtotime($hora_inicio_manana) && $visita_start < strtotime($hora_fin_manana)) {
                                        $dur = (strtotime($row['hora_fin_visita']) - strtotime($row['hora_inicio_visita'])) / 60;
                                        $used_minutes += $dur;
                                    }
                                }
                                $total_slot_minutes = (strtotime($hora_fin_manana) - strtotime($hora_inicio_manana)) / 60;
                            } elseif ($slot == 'afternoon') {
                                while ($row = odbc_fetch_array($result_visitas_slot)) {
                                    $visita_start = strtotime($row['hora_inicio_visita']);
                                    if ($visita_start >= strtotime($hora_inicio_tarde) && $visita_start < strtotime($hora_fin_tarde)) {
                                        $dur = (strtotime($row['hora_fin_visita']) - strtotime($row['hora_inicio_visita'])) / 60;
                                        $used_minutes += $dur;
                                    }
                                }
                                $total_slot_minutes = (strtotime($hora_fin_tarde) - strtotime($hora_inicio_tarde)) / 60;
                            }
                            $free_minutes = $total_slot_minutes - $used_minutes;
                            if ($free_minutes < 0) {
                                $free_minutes = 0;
                            }

                            if ($free_minutes < $tiempo_promedio_minutes) {
                                $error = "No hay suficiente tiempo libre en la franja seleccionada. Tiempo libre: $free_minutes minutos, se requiere al menos $tiempo_promedio_minutes minutos.";
                            } else {
                                $result_insert = registrarVisitaManual([
                                    'cod_cliente' => $cod_cliente,
                                    'cod_seccion' => $cod_seccion,
                                    'cod_vendedor' => $codigo_vendedor,
                                    'fecha_visita' => $fecha_visita,
                                    'hora_inicio_visita' => $hora_inicio_visita,
                                    'hora_fin_visita' => $hora_fin_visita,
                                    'estado_visita' => $estado_visita,
                                    'cod_zona_visita' => $zona_seleccionada,
                                    'observaciones' => $observaciones,
                                ], isset($_POST['forzar']));
                                if (!($result_insert['ok'] ?? false)) {
                                    $conflictos = $result_insert['conflictos'] ?? [];
                                    $requiereConfirmacion = !empty($result_insert['requiere_confirmacion']);
                                    $mensajesConflicto = [];
                                    if (!empty($conflictos['solape'])) {
                                        $mensajesConflicto[] = "Conflicto detectado: solape de horario";
                                    }
                                    if (!empty($conflictos['cliente_duplicado'])) {
                                        $mensajesConflicto[] = "Conflicto detectado: ya existe una visita para este cliente en ese dia";
                                    }
                                    if (!empty($conflictos['no_cabe'])) {
                                        $mensajesConflicto[] = "La visita no cabe en el horario disponible";
                                    }
                                    if (!empty($conflictos['exceso_tiempo'])) {
                                        $mensajesConflicto[] = "Estas superando el tiempo recomendado del dia";
                                    }
                                    if (!empty($mensajesConflicto)) {
                                        $error = implode(". ", $mensajesConflicto);
                                    } else {
                                        $error = "Error al insertar la visita.";
                                    }
                                } else {
                                    echo "<script>window.location.href='" . BASE_URL . "/mostrar_calendario.php';</script>";
                                    exit();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registrar Visita Manual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- jQuery -->
    <script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.12.4.min.js"></script>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
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

        .form-group {
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
        .btn-block {
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
    <script>
        $(document).ready(function() {
            // Si el campo de fecha est vaco, mostramos la alerta estilizada
            if ($("#fecha_visita").val() === "") {
                $("#visitas_del_dia").html("<div class='alert alert-info'>Seleccione una fecha para ver las visitas programadas.</div>");
            }
            // Calcular automticamente la hora de fin a partir de la hora de inicio y el tiempo promedio
            $("#hora_inicio_visita").change(function() {
                var startTime = $(this).val();
                var promedio = <?php echo $tiempo_promedio_minutes; ?>; // Tiempo promedio en minutos
                if (startTime) {
                    var parts = startTime.split(":");
                    var date = new Date();
                    date.setHours(parseInt(parts[0], 10));
                    date.setMinutes(parseInt(parts[1], 10) + promedio);
                    var endHours = date.getHours();
                    var endMinutes = date.getMinutes();
                    if (endHours < 10) {
                        endHours = "0" + endHours;
                    }
                    if (endMinutes < 10) {
                        endMinutes = "0" + endMinutes;
                    }
                    var endTime = endHours + ":" + endMinutes;
                    $("#hora_fin_visita").val(endTime);
                }
            });
            $("#btnDefinirHorario").click(function() {
                $("#modalDefinirHorario").fadeIn();
            });
            $("#modalDefinirHorario .close").click(function() {
                $("#modalDefinirHorario").fadeOut();
            });
            // Cerrar el modal si se hace clic fuera del contenido
            $(document).mouseup(function(e) {
                var modal = $("#modalDefinirHorario");
                if (modal.is(":visible") && !$(e.target).closest(".modal-content").length) {
                    modal.fadeOut();
                }
            });
            $("#formDefinirHorario").submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: "<?= BASE_URL ?>/definir_horario.php",
                    type: "POST",
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.indexOf("OK") === 0) {
                            alert("Horario guardado correctamente.");
                            location.reload();
                        } else {
                            alert("Error: " + response);
                        }
                    },
                    error: function() {
                        alert("Error en la petición AJAX.");
                    }
                });
            });

            $("#fecha_visita").change(function() {
                var fecha = $(this).val();
                if (fecha !== "") {
                    $.ajax({
                        url: "<?= BASE_URL ?>/get_visitas.php",
                        type: "GET",
                        data: {
                            fecha: fecha
                        },
                        success: function(data) {
                            $("#visitas_del_dia").html(data);
                        },
                        error: function() {
                            $("#visitas_del_dia").html("<div class='alert alert-danger'>Error al cargar las visitas.</div>");
                        }
                    });
                } else {
                    $("#visitas_del_dia").html("<div class='alert alert-info'>Seleccione una fecha para ver las visitas programadas.</div>");
                }
            });
        });
    </script>
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
                $alertClass = ($estado == 'planificada') ? 'alert-info' : (($estado == 'pendiente') ? 'alert-warning' : 'alert-default');
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
                    <div class="form-group">
                        <label for="hora_inicio_manana">Hora Inicio Mañana:</label>
                        <input type="time" class="form-control" name="hora_inicio_manana" id="hora_inicio_manana" value="<?php echo !empty($hora_inicio_manana) ? $hora_inicio_manana : ''; ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="hora_fin_manana">Hora Fin Mañana:</label>
                        <input type="time" class="form-control" name="hora_fin_manana" id="hora_fin_manana" value="<?php echo !empty($hora_fin_manana) ? $hora_fin_manana : ''; ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="hora_inicio_tarde">Hora Inicio Tarde:</label>
                        <input type="time" class="form-control" name="hora_inicio_tarde" id="hora_inicio_tarde" value="<?php echo !empty($hora_inicio_tarde) ? $hora_inicio_tarde : ''; ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="hora_fin_tarde">Hora Fin Tarde:</label>
                        <input type="time" class="form-control" name="hora_fin_tarde" id="hora_fin_tarde" value="<?php echo !empty($hora_fin_tarde) ? $hora_fin_tarde : ''; ?>" required />
                    </div>
                    <button type="submit" class="btn btn-success btn-block">Guardar Horario</button>
                </form>
            </div>
        </div>

        <!-- Formulario para registrar visita manual -->
        <form action="<?= BASE_URL ?>/visitas.php?action=crear&amp;cod_cliente=<?php echo $cod_cliente; ?><?php echo ($cod_seccion !== null ? "&amp;cod_seccion=" . $cod_seccion : ""); ?>" method="post" class="form">
            <?php if ($requiereConfirmacion): ?>
                <input type="hidden" name="forzar" value="1" />
            <?php endif; ?>
            <div class="form-group">
                <label for="fecha_visita">Fecha de la Visita:</label>
                <input type="date" class="form-control" name="fecha_visita" id="fecha_visita" value="<?php echo isset($_POST['fecha_visita']) ? htmlspecialchars($_POST['fecha_visita']) : ''; ?>" />
            </div>
            <div class="form-group">
                <label for="hora_inicio_visita">Hora de Inicio:</label>
                <input type="time" class="form-control" name="hora_inicio_visita" id="hora_inicio_visita" value="<?php echo isset($_POST['hora_inicio_visita']) ? htmlspecialchars($_POST['hora_inicio_visita']) : ''; ?>" />
            </div>
            <div class="form-group">
                <label for="hora_fin_visita">Hora de Fin:</label>
                <input type="time" class="form-control" name="hora_fin_visita" id="hora_fin_visita" value="<?php echo isset($_POST['hora_fin_visita']) ? htmlspecialchars($_POST['hora_fin_visita']) : ''; ?>" />
            </div>
            <div class="form-group">
                <label for="zona_seleccionada">Zona de Visita:</label>
                <select name="zona_seleccionada" id="zona_seleccionada" class="form-control">
                    <?php foreach ($zonas as $zona): ?>
                        <option value="<?php echo $zona['codigo']; ?>" <?php if (isset($_POST['zona_seleccionada']) && $_POST['zona_seleccionada'] == $zona['codigo']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($zona['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="estado_visita">Estado de la Visita:</label>
                <select name="estado_visita" id="estado_visita" class="form-control">
                    <option value="Pendiente" <?php if (isset($_POST['estado_visita']) && normalizarEstadoVisita($_POST['estado_visita']) == 'Pendiente') echo 'selected'; ?>>Pendiente</option>
                    <option value="Planificada" <?php if (isset($_POST['estado_visita']) && normalizarEstadoVisita($_POST['estado_visita']) == 'Planificada') echo 'selected'; ?>>Planificada</option>
                    <option value="Realizada" <?php if (isset($_POST['estado_visita']) && normalizarEstadoVisita($_POST['estado_visita']) == 'Realizada') echo 'selected'; ?>>Realizada</option>
                    <option value="No atendida" <?php if (isset($_POST['estado_visita']) && normalizarEstadoVisita($_POST['estado_visita']) == 'No atendida') echo 'selected'; ?>>No atendida</option>
                    <option value="Descartada" <?php if (isset($_POST['estado_visita']) && normalizarEstadoVisita($_POST['estado_visita']) == 'Descartada') echo 'selected'; ?>>Descartada</option>
                </select>
            </div>
            <div class="form-group">
                <label for="observaciones">Observaciones (opcional):</label>
                <textarea name="observaciones" id="observaciones" class="form-control" rows="4"><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
            </div>
            <?php if ($requiereConfirmacion): ?>
                <button type="submit" class="btn btn-warning btn-block">Guardar de todas formas</button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary btn-block">Registrar Visita Manual</button>
            <?php endif; ?>
        </form>

        <!-- Div para mostrar las visitas del da seleccionado -->
        <div id="visitas_del_dia">
            <div class="alert alert-info">Seleccione una fecha para ver las visitas programadas.</div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            // Cuando se cambia la fecha, se carga el div con las visitas programadas
            $("#fecha_visita").change(function() {
                var fecha = $(this).val();
                if (fecha !== "") {
                    $.ajax({
                        url: "<?= BASE_URL ?>/get_visitas.php",
                        type: "GET",
                        data: {
                            fecha: fecha
                        },
                        success: function(data) {
                            $("#visitas_del_dia").html(data);
                        },
                        error: function() {
                            $("#visitas_del_dia").html("<div class='alert alert-danger'>Error al cargar las visitas.</div>");
                        }
                    });
                } else {
                    $("#visitas_del_dia").html("<div class='alert alert-info'>Seleccione una fecha para ver las visitas programadas.</div>");
                }
            });

            // Calcular automticamente la hora de fin a partir de la hora de inicio y el tiempo promedio
            $("#hora_inicio_visita").change(function() {
                var startTime = $(this).val();
                var promedio = <?php echo $tiempo_promedio_minutes; ?>; // Tiempo promedio en minutos
                if (startTime) {
                    var parts = startTime.split(":");
                    var date = new Date();
                    date.setHours(parseInt(parts[0], 10));
                    date.setMinutes(parseInt(parts[1], 10) + promedio);
                    var endHours = date.getHours();
                    var endMinutes = date.getMinutes();
                    if (endHours < 10) {
                        endHours = "0" + endHours;
                    }
                    if (endMinutes < 10) {
                        endMinutes = "0" + endMinutes;
                    }
                    var endTime = endHours + ":" + endMinutes;
                    $("#hora_fin_visita").val(endTime);
                }
            });
        });
    </script>
</body>

</html>
<?php
?>



