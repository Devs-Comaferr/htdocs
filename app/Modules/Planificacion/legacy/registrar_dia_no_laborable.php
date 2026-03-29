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
$conn = db();
$pageTitle = "Registrar DÃƒÆ’Ã‚Â­a No Laborable";
include BASE_PATH . '/resources/views/layouts/header.php';

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
$error = "";
$success = "";

function registrarNoLaborablePrepareExecute($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        appLogTechnicalError('registrar_dia_no_laborable.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    if (!odbc_execute($stmt, $params)) {
        appLogTechnicalError('registrar_dia_no_laborable.execute', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    return $stmt;
}

// FunciÃƒÆ’Ã‚Â³n para preparar la descripciÃƒÆ’Ã‚Â³n: si estÃƒÆ’Ã‚Â¡ vacÃƒÆ’Ã‚Â­a, retorna cadena vacÃƒÆ’Ã‚Â­a; si no, la escapada
function prepararDescripcion($desc) {
    $desc = trim($desc);
    if ($desc == "") {
        return "";
    } else {
        return $desc;
    }
}

/**
 * Verifica si una fecha concreta es festiva.
 *  1) Mira si existe EXACTAMENTE "fecha = X AND tipo_evento='Festivo' AND repetir_anualmente=0"
 *  2) O bien si hay un registro con "tipo_evento='Festivo' AND repetir_anualmente=1"
 *     donde coincidan mes/dÃƒÆ’Ã‚Â­a con $fechaCheck (ignorando aÃƒÆ’Ã‚Â±o).
 */
function esFestivo($fechaCheck, $conn) {
    $ts = strtotime($fechaCheck);
    if ($ts === false) {
        return false;
    }
    $yyyy = (int) date('Y', $ts);
    $mm   = (int) date('m', $ts);
    $dd   = (int) date('d', $ts);

    // 1) Comprueba si hay un festivo exacto (repetir_anualmente=0)
    $sqlExacto = "
        SELECT COUNT(*) AS c
        FROM [integral].[dbo].[cmf_dias_no_laborables]
        WHERE tipo_evento='Festivo'
          AND repetir_anualmente=0
          AND fecha = ?
    ";
    $resExacto = registrarNoLaborablePrepareExecute($conn, $sqlExacto, [$fechaCheck]);
    if ($resExacto) {
        $rowE = odbc_fetch_array($resExacto);
        if ($rowE && intval($rowE['c']) > 0) {
            return true; // ya es festivo
        }
    }

    // 2) Comprueba si hay un festivo repetible (repetir_anualmente=1) con mes/dÃƒÆ’Ã‚Â­a
    $sqlAnual = "
        SELECT COUNT(*) AS c
        FROM [integral].[dbo].[cmf_dias_no_laborables]
        WHERE tipo_evento='Festivo'
          AND repetir_anualmente=1
          -- ignoramos el aÃƒÆ’Ã‚Â±o, comparamos mes/dÃƒÆ’Ã‚Â­a
          AND (
              DATEPART(MONTH, fecha) = $mm
              AND DATEPART(DAY,   fecha) = $dd
          )
    ";
    $resAnual = registrarNoLaborablePrepareExecute($conn, $sqlAnual);
    if ($resAnual) {
        $rowA = odbc_fetch_array($resAnual);
        if ($rowA && intval($rowA['c']) > 0) {
            return true; // coincide con un festivo anual
        }
    }
    return false;
}

// Procesar el formulario cuando se envÃƒÆ’Ã‚Â­a
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_evento_sel = trim($_POST['tipo_evento']);
    
    if (empty($tipo_evento_sel)) {
        $error = "Seleccione un tipo de evento.";
    } else {
        // Usamos el campo de descripciÃƒÆ’Ã‚Â³n enviado en el formulario
        $desc_sql = prepararDescripcion($_POST['descripcion']);

        // --------------------------------------------------------------------
        // 1. BLOQUE PARA "VACACIONES"
        // --------------------------------------------------------------------
        if ($tipo_evento_sel == "Vacaciones") {
            $fecha_inicio = trim($_POST['fecha_inicio']);
            $fecha_fin    = trim($_POST['fecha_fin']);
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                $error = "Para Vacaciones, ingrese la fecha de inicio y la fecha de fin.";
            } else {
                $ts_inicio = strtotime($fecha_inicio);
                $ts_fin    = strtotime($fecha_fin);
                if ($ts_inicio === false || $ts_fin === false) {
                    $error = "Formato de fecha incorrecto.";
                } elseif ($ts_inicio > $ts_fin) {
                    $error = "La fecha de inicio no puede ser posterior a la fecha de fin.";
                } else {
                    $dias_insertados = 0;
                    for ($d = $ts_inicio; $d <= $ts_fin; $d += 86400) {
                        $dia_semana = date('w', $d); // 0=domingo, 6=sÃƒÆ’Ã‚Â¡bado
                        // Omitir sÃƒÆ’Ã‚Â¡bados y domingos
                        if ($dia_semana == 0 || $dia_semana == 6) {
                            continue;
                        }
                        $fecha_actual = date("Y-m-d", $d);

                        // Comprobar si YA es Festivo
                        if (esFestivo($fecha_actual, $conn)) {
                            // Omitir
                            continue;
                        }
                        // Comprobar si el dÃƒÆ’Ã‚Â­a es LUNES y el dÃƒÆ’Ã‚Â­a anterior (domingo) fue Festivo
                        if ($dia_semana == 1) {
                            // Lunes => ver si el domingo fue festivo
                            // (domingo = $fecha_actual - 1 dÃƒÆ’Ã‚Â­a)
                            $ayerTs = $d - 86400;
                            $fecha_ayer = date("Y-m-d", $ayerTs);
                            // si $fecha_ayer es festivo => skip
                            if (esFestivo($fecha_ayer, $conn)) {
                                continue;
                            }
                        }

                        // Si pasa todos los filtros => insertamos
                        $sqlInsert = "
                            INSERT INTO [integral].[dbo].[cmf_dias_no_laborables]
                                (cod_vendedor, fecha, tipo_evento, descripcion, repetir_anualmente)
                            VALUES (?, ?, 'Vacaciones', ?, 0)
                        ";
                        if (registrarNoLaborablePrepareExecute($conn, $sqlInsert, [intval($codigo_vendedor), $fecha_actual, $desc_sql])) {
                            $dias_insertados++;
                        }
                    }
                    if ($dias_insertados > 0) {
                        $success = "Vacaciones registradas correctamente para $dias_insertados dÃƒÆ’Ã‚Â­a(s).";
                    } else {
                        $error = "No se registraron dÃƒÆ’Ã‚Â­as de vacaciones (puede que haya elegido fines de semana/festivos).";
                    }
                }
            }
        }

        // --------------------------------------------------------------------
        // 2. BLOQUE PARA "FESTIVO" (aquÃƒÆ’Ã‚Â­ sÃƒÆ’Ã‚Â­ puede ser sÃƒÆ’Ã‚Â¡bado/domingo, si quieres)
        //    si NO quieres omitir sab/dom, lo dejas
        // --------------------------------------------------------------------
        else if ($tipo_evento_sel == "Festivo") {
            $fecha_festivo = trim($_POST['fecha_festivo']);
            $repetir_anualmente = !empty($_POST['repetir_anualmente']) ? 1 : 0;

            if (empty($fecha_festivo)) {
                $error = "Para Festivo, ingrese la fecha del festivo.";
            } else {
                $sql = "
                    INSERT INTO [integral].[dbo].[cmf_dias_no_laborables]
                    (cod_vendedor, fecha, tipo_evento, descripcion, repetir_anualmente)
                    VALUES (?, ?, 'Festivo', ?, ?)
                ";
                if (registrarNoLaborablePrepareExecute($conn, $sql, [intval($codigo_vendedor), $fecha_festivo, $desc_sql, intval($repetir_anualmente)])) {
                    $success = "Festivo registrado correctamente.";
                } else {
                    $error = "Error al registrar el festivo.";
                }
            }
        }

        // --------------------------------------------------------------------
        // 3. BLOQUE PARA "OTRO"
        // --------------------------------------------------------------------
        else if ($tipo_evento_sel == "Otro") {
            $custom_event     = trim($_POST['custom_event']);
            $fecha_evento     = trim($_POST['fecha_evento']);
            $hora_inicio_otro = trim($_POST['hora_inicio_otro']);
            $hora_fin_otro    = trim($_POST['hora_fin_otro']);

            if (empty($custom_event) || empty($fecha_evento)) {
                $error = "Para un evento personalizado, ingrese el tipo de evento y la fecha.";
            } else {
                if (!empty($hora_inicio_otro) && !empty($hora_fin_otro) 
                    && strtotime($hora_inicio_otro) >= strtotime($hora_fin_otro)) {
                    $error = "La hora de inicio debe ser anterior a la hora de fin.";
                } else {
                    // AquÃƒÆ’Ã‚Â­ podrÃƒÆ’Ã‚Â­as tambiÃƒÆ’Ã‚Â©n omitir sÃƒÆ’Ã‚Â¡b./dom. o Festivo si lo deseas
                    $sql = "
                        INSERT INTO [integral].[dbo].[cmf_dias_no_laborables]
                        (cod_vendedor, fecha, hora_inicio, hora_fin, tipo_evento, descripcion, repetir_anualmente)
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ";
                    if (registrarNoLaborablePrepareExecute($conn, $sql, [intval($codigo_vendedor), $fecha_evento, (!empty($hora_inicio_otro) ? $hora_inicio_otro : null), (!empty($hora_fin_otro) ? $hora_fin_otro : null), $custom_event, $desc_sql])) {
                        $success = "Evento registrado correctamente.";
                    } else {
                        $error = "Error al registrar el evento.";
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
    <title>Registrar DÃƒÆ’Ã‚Â­a No Laborable</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS (compatible con PHP 5.2.3) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group label {
            font-size: 16px;
        }
        .form-control {
            font-size: 16px;
        }
        .btn {
            font-size: 16px;
            padding: 10px 20px;
        }
        .alert {
            margin-top: 15px;
        }
        .hidden {
            display: none;
        }
    </style>
    <script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.12.4.min.js"></script>
    <script>
    $(document).ready(function(){
        // Ocultar inicialmente todos los bloques
        $("#vacaciones_fields").addClass("hidden");
        $("#festivo_fields").addClass("hidden");
        $("#otro_fields").addClass("hidden");
        
        $("#tipo_evento").change(function(){
            var selected = $(this).val();
            if(selected === "Vacaciones") {
                $("#vacaciones_fields").removeClass("hidden");
                $("#festivo_fields").addClass("hidden");
                $("#otro_fields").addClass("hidden");
            } else if(selected === "Festivo") {
                $("#festivo_fields").removeClass("hidden");
                $("#vacaciones_fields").addClass("hidden");
                $("#otro_fields").addClass("hidden");
            } else if(selected === "Otro") {
                $("#otro_fields").removeClass("hidden");
                $("#vacaciones_fields").addClass("hidden");
                $("#festivo_fields").addClass("hidden");
            } else {
                $("#vacaciones_fields").addClass("hidden");
                $("#festivo_fields").addClass("hidden");
                $("#otro_fields").addClass("hidden");
            }
        });
    });
    </script>
</head>
<body>
<div class="container">
    <h2>Registrar DÃƒÆ’Ã‚Â­a No Laborable</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <form action="registrar_dia_no_laborable.php" method="POST">
        <!-- SelecciÃƒÆ’Ã‚Â³n del Tipo de Evento -->
        <div class="form-group">
            <label for="tipo_evento">Tipo de Evento:</label>
            <select name="tipo_evento" id="tipo_evento" class="form-control" required>
                <option value="">Seleccione...</option>
                <option value="Vacaciones">Vacaciones</option>
                <option value="Festivo">Festivo</option>
                <option value="Otro">Otro</option>
            </select>
        </div>
        <!-- Bloque para Vacaciones -->
        <div id="vacaciones_fields" class="hidden">
            <div class="form-group">
                <label for="fecha_inicio">Fecha de Inicio:</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
            </div>
            <div class="form-group">
                <label for="fecha_fin">Fecha de Fin:</label>
                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
            </div>
        </div>
        <!-- Bloque para Festivo -->
        <div id="festivo_fields" class="hidden">
            <div class="form-group">
                <label for="fecha_festivo">Fecha del Festivo:</label>
                <input type="date" name="fecha_festivo" id="fecha_festivo" class="form-control">
            </div>
            <!-- Checkbox para repetir anualmente -->
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="repetir_anualmente" value="1"> Repetir todos los aos
                </label>
            </div>
        </div>
        <!-- Bloque para Otro -->
        <div id="otro_fields" class="hidden">
            <div class="form-group">
                <label for="custom_event">Tipo de Evento (escriba):</label>
                <input type="text" name="custom_event" id="custom_event" class="form-control">
            </div>
            <div class="form-group">
                <label for="fecha_evento">Fecha:</label>
                <input type="date" name="fecha_evento" id="fecha_evento" class="form-control">
            </div>
            <div class="form-group">
                <label for="hora_inicio_otro">Hora de Inicio:</label>
                <input type="time" name="hora_inicio_otro" id="hora_inicio_otro" class="form-control">
            </div>
            <div class="form-group">
                <label for="hora_fin_otro">Hora de Fin:</label>
                <input type="time" name="hora_fin_otro" id="hora_fin_otro" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">DescripciÃƒÆ’Ã‚Â³n (opcional):</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="3" placeholder="Detalles adicionales"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Registrar</button>
    </form>
    
    <br>
    <a href="planificacion_rutas.php" class="btn btn-default btn-block">Volver al Panel</a>
</div>
</body>
</html>
<?php
odbc_close($conn);
?>
