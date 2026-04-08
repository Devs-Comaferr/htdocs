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

$ui_version = 'bs5';
$ui_requires_jquery = false;

$conn = db();

$pageTitle = "Editar Evento No Laborable";

// Obtener ID del no laborable a editar
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    error_log("ID no vlido.");
    echo 'Error interno';
    return;
}

$error = "";
$success = "";

function editarNoLaborablePrepareExecute($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        appLogTechnicalError('editar_no_laborable.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    if (!odbc_execute($stmt, $params)) {
        appLogTechnicalError('editar_no_laborable.execute', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    return $stmt;
}

// Procesar formulario al enviar (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha = trim($_POST['fecha']);
    $hora_inicio = trim($_POST['hora_inicio']);
    $hora_fin = trim($_POST['hora_fin']);
    $tipo_evento = trim($_POST['tipo_evento']);
    $descripcion = trim($_POST['descripcion']);

    // Validacion basica
    if (empty($fecha) || empty($tipo_evento)) {
        $error = "La fecha y el tipo de evento son obligatorios.";
    } else {
        // Validar si se definen horas, que sean coherentes
        if (!empty($hora_inicio) && !empty($hora_fin) && (strtotime($hora_inicio) >= strtotime($hora_fin))) {
            $error = "La hora de inicio debe ser anterior a la hora de fin.";
        } else {
            $sql_update = "
                UPDATE [integral].[dbo].[cmf_comerciales_dias_no_laborables]
                SET 
                    fecha = ?,
                    hora_inicio = ?,
                    hora_fin = ?,
                    tipo_evento = ?,
                    descripcion = ?
                WHERE id = ?
            ";
            
            $result_update = editarNoLaborablePrepareExecute(
                $conn,
                $sql_update,
                [$fecha, ($hora_inicio !== '' ? $hora_inicio : null), ($hora_fin !== '' ? $hora_fin : null), $tipo_evento, $descripcion, $id]
            );
            if (!$result_update) {
                $error = "Error al actualizar el registro.";
            } else {
                // Redirigir a calendario
                header("Location: mostrar_calendario.php");
                exit();
            }
        }
    }
}

// Cargar datos actuales del registro
$sql_select = "SELECT * FROM [integral].[dbo].[cmf_comerciales_dias_no_laborables] WHERE id = ?";
$result_select = editarNoLaborablePrepareExecute($conn, $sql_select, [$id]);
if (!$result_select) {
    error_log("Error al consultar el registro.");
    echo 'Error interno';
    return;
}
$row = odbc_fetch_array($result_select);
if (!$row) {
    error_log("No se encontr el evento con ID: $id");
    echo 'Error interno';
    return;
}

// Tomar los valores de la BD
$fecha_db = $row['fecha'];
$hora_inicio_db = trim($row['hora_inicio']);
$hora_fin_db = trim($row['hora_fin']);
$tipo_evento_db = trim($row['tipo_evento']);
$descripcion_db = trim($row['descripcion']);

// Cerrar la conexion si lo deseas aqui (depende de tu flujo)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
</head>
<body>
<div class="container" style="max-width:500px; margin-top:30px;">
    <h2 class="text-center">Editar Evento No Laborable</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="editar_no_laborable.php?id=<?php echo intval($id); ?>">
        <div class="mb-3">
            <label>Fecha:</label>
            <input type="date" name="fecha" class="form-control" 
                   value="<?php echo htmlspecialchars($fecha_db); ?>">
        </div>
        <div class="mb-3">
            <label>Hora Inicio (opcional):</label>
            <input type="time" name="hora_inicio" class="form-control" 
                   value="<?php echo htmlspecialchars(substr($hora_inicio_db, 0, 5)); ?>">
        </div>
        <div class="mb-3">
            <label>Hora Fin (opcional):</label>
            <input type="time" name="hora_fin" class="form-control" 
                   value="<?php echo htmlspecialchars(substr($hora_fin_db, 0, 5)); ?>">
        </div>
        <div class="mb-3">
            <label>Tipo de Evento:</label>
            <input type="text" name="tipo_evento" class="form-control" 
                   value="<?php echo htmlspecialchars($tipo_evento_db); ?>" required>
        </div>
        <div class="mb-3">
            <label>Descripcion (opcional):</label>
            <textarea name="descripcion" class="form-control" rows="3"><?php 
                echo htmlspecialchars($descripcion_db); 
            ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100">Guardar Cambios</button>
        <a href="mostrar_calendario.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
    </form>
</div>
</body>
</html>



