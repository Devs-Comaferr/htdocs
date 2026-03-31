<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

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
require_once BASE_PATH . '/app/Support/functions.php';

$ui_version = 'bs5';
$ui_requires_jquery = false;

$conn = db();

function editarVisitaModernPrepareExecute($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        appLogTechnicalError('editar_visita.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    if (!odbc_execute($stmt, $params)) {
        appLogTechnicalError('editar_visita.execute', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    return $stmt;
}

$id_visita = isset($_GET['id_visita']) ? intval($_GET['id_visita']) : 0;
if ($id_visita <= 0) {
    error_log('ID de visita inválido.');
    echo 'Error interno';
    return;
}

$error = '';
$success = '';

$sql = "
    SELECT 
        cvc.id_visita,
        cvc.fecha_visita,
        cvc.hora_inicio_visita,
        cvc.hora_fin_visita,
        cvc.observaciones,
        cvc.estado_visita,
        cl.cod_cliente,
        cl.nombre_comercial,
        cvc.cod_seccion
    FROM [integral].[dbo].[cmf_visitas_comerciales] cvc
    JOIN [integral].[dbo].[clientes] cl ON cvc.cod_cliente = cl.cod_cliente
    WHERE cvc.id_visita = ?
";
$result = editarVisitaModernPrepareExecute($conn, $sql, [$id_visita]);
if (!$result || !odbc_fetch_row($result)) {
    $error = "No se encontró la visita especificada.";
    error_log("<div class='alert alert-danger'>$error</div>");
    echo 'Error interno';
    return;
}

$cod_cliente = odbc_result($result, 'cod_cliente');
$nombre_comercial = odbc_result($result, 'nombre_comercial');
$cod_seccion = odbc_result($result, 'cod_seccion');
$fecha_visita_db = odbc_result($result, 'fecha_visita');
$hora_inicio_visita_db = odbc_result($result, 'hora_inicio_visita');
$hora_fin_visita_db = odbc_result($result, 'hora_fin_visita');
$observaciones_db = odbc_result($result, 'observaciones');
$estado_visita_db = odbc_result($result, 'estado_visita');

$hora_inicio_visita_db = $hora_inicio_visita_db ? substr($hora_inicio_visita_db, 0, 5) : '';
$hora_fin_visita_db = $hora_fin_visita_db ? substr($hora_fin_visita_db, 0, 5) : '';

$sql_assignment = "SELECT * FROM [integral].[dbo].[cmf_asignacion_zonas_clientes] WHERE cod_cliente = ? AND activo = 1";
$assignmentParams = [$cod_cliente];
if (!is_null($cod_seccion)) {
    $sql_assignment .= " AND cod_seccion = ?";
    $assignmentParams[] = $cod_seccion;
} else {
    $sql_assignment .= " AND cod_seccion IS NULL";
}
$result_assignment = editarVisitaModernPrepareExecute($conn, $sql_assignment, $assignmentParams);
$assignment = odbc_fetch_array($result_assignment);

$hora_inicio_manana = !empty($assignment['hora_inicio_manana']) ? substr($assignment['hora_inicio_manana'], 0, 5) : '';
$hora_fin_manana = !empty($assignment['hora_fin_manana']) ? substr($assignment['hora_fin_manana'], 0, 5) : '';
$hora_inicio_tarde = !empty($assignment['hora_inicio_tarde']) ? substr($assignment['hora_inicio_tarde'], 0, 5) : '';
$hora_fin_tarde = !empty($assignment['hora_fin_tarde']) ? substr($assignment['hora_fin_tarde'], 0, 5) : '';
$tiempo_promedio = floatval($assignment['tiempo_promedio_visita']);
$tiempo_promedio_minutes = $tiempo_promedio * 60;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha_visita = date('Y-m-d', strtotime(trim($_POST['fecha_visita'])));
    $hora_inicio_visita = trim((string)$_POST['hora_inicio_visita']);
    $hora_fin_visita = trim((string)$_POST['hora_fin_visita']);
    $observaciones = trim((string)$_POST['observaciones']);
    $estado_visita = normalizarEstadoVisita(trim((string)$_POST['estado_visita']));

    if (empty($fecha_visita) || empty($hora_inicio_visita) || empty($hora_fin_visita)) {
        $error = 'Por favor, complete la fecha y las horas de la visita.';
    } elseif (strtotime($hora_inicio_visita) >= strtotime($hora_fin_visita)) {
        $error = 'La hora de inicio debe ser anterior a la de fin.';
    } else {
        if (estadoVisitaRequiereFranja($estado_visita)) {
            $slot = '';
            if (!empty($hora_inicio_manana) && !empty($hora_fin_manana)) {
                $morning_start = strtotime($hora_inicio_manana);
                $morning_end = strtotime($hora_fin_manana);
                if (strtotime($hora_inicio_visita) < $morning_start) {
                    $error = "La hora de inicio de la visita no puede ser anterior a la apertura de la mañana ($hora_inicio_manana).";
                } elseif (strtotime($hora_inicio_visita) >= $morning_start && strtotime($hora_inicio_visita) < $morning_end) {
                    $slot = 'morning';
                    if (strtotime($hora_fin_visita) > $morning_end) {
                        $error = "La hora de fin de la visita no puede ser posterior a la hora de cierre de la mañana ($hora_fin_manana).";
                    }
                }
            }
            if (empty($slot) && empty($error) && !empty($hora_inicio_tarde) && !empty($hora_fin_tarde)) {
                $afternoon_start = strtotime($hora_inicio_tarde);
                $afternoon_end = strtotime($hora_fin_tarde);
                if (strtotime($hora_inicio_visita) < $afternoon_start) {
                    $error = "La hora de inicio de la visita no puede ser anterior a la apertura de la tarde ($hora_inicio_tarde).";
                } elseif (strtotime($hora_inicio_visita) >= $afternoon_start && strtotime($hora_inicio_visita) < $afternoon_end) {
                    $slot = 'afternoon';
                    if (strtotime($hora_fin_visita) > $afternoon_end) {
                        $error = "La hora de fin de la visita no puede ser posterior a la hora de cierre de la tarde ($hora_fin_tarde).";
                    }
                }
            }
            if (empty($slot) && empty($error)) {
                $horarios = '';
                if (!empty($hora_inicio_manana) && !empty($hora_fin_manana)) {
                    $horarios .= "Mañana: $hora_inicio_manana a $hora_fin_manana. ";
                }
                if (!empty($hora_inicio_tarde) && !empty($hora_fin_tarde)) {
                    $horarios .= "Tarde: $hora_inicio_tarde a $hora_fin_tarde.";
                }
                $error = 'La hora de inicio de la visita no se encuentra dentro de las franjas de disponibilidad del cliente. Horarios: ' . $horarios;
            }
        }

        if (empty($error)) {
            $estadoLower = normalizarEstadoVisitaClave($estado_visita);
            if ($estadoLower == 'descartada') {
                $skipOverlap = true;
            } elseif ($estadoLower == 'realizada') {
                $sql_order = "SELECT TOP 1 origen FROM [integral].[dbo].[cmf_visita_pedidos] 
                              WHERE id_visita = ? AND LOWER(origen) = 'visita'";
                $result_order = editarVisitaModernPrepareExecute($conn, $sql_order, [$id_visita]);
                if ($result_order && odbc_fetch_row($result_order)) {
                    $skipOverlap = false;
                } else {
                    $skipOverlap = true;
                }
            } else {
                $skipOverlap = false;
            }

            if (!$skipOverlap) {
                $sql_overlap = "SELECT * FROM [integral].[dbo].[cmf_visitas_comerciales]
                                WHERE id_visita <> ?
                                  AND cod_vendedor = ?
                                  AND CONVERT(varchar(10), fecha_visita, 120) = ?
                                  AND LOWER(estado_visita) IN ('planificada','pendiente','realizada','no atendida')";
                $result_overlap = editarVisitaModernPrepareExecute($conn, $sql_overlap, [$id_visita, intval($_SESSION['codigo']), $fecha_visita]);
                $overlap = false;
                $overlapDetails = '';
                if ($result_overlap) {
                    while ($row = odbc_fetch_array($result_overlap)) {
                        $existing_start = strtotime($row['hora_inicio_visita']);
                        $existing_end = strtotime($row['hora_fin_visita']);
                        $new_start = strtotime($hora_inicio_visita);
                        $new_end = strtotime($hora_fin_visita);
                        if ($new_start < $existing_end && $new_end > $existing_start) {
                            $overlap = true;
                            $sql_cliente_overlap = "SELECT nombre_comercial FROM [integral].[dbo].[clientes] WHERE cod_cliente = ?";
                            $result_cliente_overlap = editarVisitaModernPrepareExecute($conn, $sql_cliente_overlap, [$row['cod_cliente']]);
                            $overlapCliente = '';
                            if ($result_cliente_overlap && odbc_fetch_row($result_cliente_overlap)) {
                                $overlapCliente = odbc_result($result_cliente_overlap, 'nombre_comercial');
                            }
                            $overlapSeccion = '';
                            if (!empty($row['cod_seccion'])) {
                                $overlap_seccion = intval($row['cod_seccion']);
                                $sql_overlap_seccion = "SELECT nombre FROM [integral].[dbo].[secciones_cliente] 
                                                        WHERE cod_cliente = ? AND cod_seccion = ?";
                                $result_overlap_seccion = editarVisitaModernPrepareExecute($conn, $sql_overlap_seccion, [$row['cod_cliente'], $overlap_seccion]);
                                if ($result_overlap_seccion && odbc_fetch_row($result_overlap_seccion)) {
                                    $overlapSeccion = odbc_result($result_overlap_seccion, 'nombre');
                                }
                            }
                            $overlapDetails = ' ' . $overlapCliente;
                            if (!empty($overlapSeccion)) {
                                $overlapDetails .= ' - ' . $overlapSeccion;
                            }
                            $overlapDetails .= ' de ' . date('H:i', $existing_start) . ' a ' . date('H:i', $existing_end);
                            break;
                        }
                    }
                }
                if ($overlap) {
                    $error = 'Existe una visita programada que se solapa con la visita que intenta actualizar:' . $overlapDetails . '.';
                }
            }
        }
    }

    if (empty($error)) {
        $sql_update = "
            UPDATE [integral].[dbo].[cmf_visitas_comerciales]
            SET 
                fecha_visita = ?,
                hora_inicio_visita = ?,
                hora_fin_visita = ?,
                observaciones = ?,
                estado_visita = ?
            WHERE id_visita = ?
        ";
        if (!editarVisitaModernPrepareExecute($conn, $sql_update, [$fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $estado_visita, $id_visita])) {
            $error = 'Error al actualizar la visita.';
        } else {
            if (isset($_GET['origen']) && $_GET['origen'] === 'pedidos_visitas') {
                header('Location: calendario.php?view=timeGridDay&msg=visita_actualizada');
            } else {
                header('Location: calendario.php?msg=visita_actualizada');
            }
            exit();
        }
    }
} else {
    $fecha_visita = $fecha_visita_db;
    $hora_inicio_visita = $hora_inicio_visita_db;
    $hora_fin_visita = $hora_fin_visita_db;
    $observaciones = $observaciones_db;
    $estado_visita = normalizarEstadoVisita($estado_visita_db);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Visita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
    <style>
      body { padding-top: 80px; }
      .boton-derecha { float: right; margin-left: 10px; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var horaInicioInput = document.getElementById('hora_inicio_visita');
        var horaFinInput = document.getElementById('hora_fin_visita');
        var promedio = <?php echo $tiempo_promedio_minutes; ?>;

        if (!horaInicioInput || !horaFinInput) {
            return;
        }

        horaInicioInput.addEventListener('change', function () {
            var startTime = horaInicioInput.value;
            if (startTime) {
                var parts = startTime.split(':');
                var date = new Date();
                date.setHours(parseInt(parts[0], 10));
                date.setMinutes(parseInt(parts[1], 10) + promedio);
                var endHours = date.getHours();
                var endMinutes = date.getMinutes();
                if (endHours < 10) { endHours = '0' + endHours; }
                if (endMinutes < 10) { endMinutes = '0' + endMinutes; }
                horaFinInput.value = endHours + ':' + endMinutes;
            }
        });
    });
    </script>
</head>
<body>
<div class="container">
    <h2 class="text-center">Editar Visita</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form action="<?= BASE_URL ?>/visitas.php?action=editar&amp;id_visita=<?php echo $id_visita; ?><?php echo isset($_GET['origen']) ? '&amp;origen=' . $_GET['origen'] : ''; ?>" method="POST">
        <input type="hidden" name="id_visita" value="<?php echo $id_visita; ?>">

        <div class="mb-3">
            <label>Nombre Comercial:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($nombre_comercial); ?>" readonly>
        </div>

        <?php if (!is_null($cod_seccion)) { ?>
        <div class="mb-3">
            <label>Sección:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($cod_seccion); ?>" readonly>
        </div>
        <?php } ?>

        <div class="mb-3">
            <label>Fecha de Visita:</label>
            <input type="date" class="form-control" name="fecha_visita" value="<?php echo htmlspecialchars($fecha_visita); ?>" required>
        </div>

        <div class="mb-3">
            <label>Hora de Inicio:</label>
            <input type="time" class="form-control" name="hora_inicio_visita" id="hora_inicio_visita" value="<?php echo htmlspecialchars($hora_inicio_visita); ?>" required>
        </div>

        <div class="mb-3">
            <label>Hora de Fin:</label>
            <input type="time" class="form-control" name="hora_fin_visita" id="hora_fin_visita" value="<?php echo htmlspecialchars($hora_fin_visita); ?>">
        </div>

        <div class="mb-3">
            <label>Observaciones:</label>
            <textarea class="form-control" name="observaciones"><?php echo htmlspecialchars($observaciones); ?></textarea>
        </div>

        <div class="mb-3">
            <label>Estado de Visita:</label>
            <select class="form-control" name="estado_visita">
                <option value="Pendiente" <?php if (normalizarEstadoVisita($estado_visita) == 'Pendiente') echo 'selected'; ?>>Pendiente</option>
                <option value="Planificada" <?php if (normalizarEstadoVisita($estado_visita) == 'Planificada') echo 'selected'; ?>>Planificada</option>
                <option value="Realizada" <?php if (normalizarEstadoVisita($estado_visita) == 'Realizada') echo 'selected'; ?>>Realizada</option>
                <option value="No atendida" <?php if (normalizarEstadoVisita($estado_visita) == 'No atendida') echo 'selected'; ?>>No atendida</option>
                <option value="Descartada" <?php if (normalizarEstadoVisita($estado_visita) == 'Descartada') echo 'selected'; ?>>Descartada</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        <?php
            if (isset($_GET['origen']) && $_GET['origen'] === 'pedidos_visitas') {
                $cancelUrl = 'calendario.php?view=timeGridDay';
            } else {
                $cancelUrl = 'calendario.php';
            }
        ?>
        <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancelar</a>

        <a href="<?= BASE_URL ?>/visitas.php?action=eliminar&amp;id_visita=<?php echo $id_visita; ?>" class="btn btn-danger boton-derecha">
            Eliminar Visita
        </a>

        <a href="cliente_detalles.php?cod_cliente=<?php echo urlencode($cod_cliente); ?><?php echo tieneValor($cod_seccion) ? '&cod_seccion=' . urlencode($cod_seccion) : ''; ?>" class="btn btn-warning boton-derecha">
            Ficha de Cliente
        </a>
        <div style="clear: both;"></div>
    </form>
</div>
</body>
</html>
<?php
?>



