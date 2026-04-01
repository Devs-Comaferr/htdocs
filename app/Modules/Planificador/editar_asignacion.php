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
// editar_asignacion.php
require_once BASE_PATH . '/app/Modules/Planificador/planificador_service.php';
$pageTitle = "Editar AsignaciÃ³n";
include BASE_PATH . '/resources/views/layouts/header.php';

// Verificar si los datos requeridos estÃ¡n en la URL
if (!isset($_GET['cod_cliente'], $_GET['cod_zona'])) {
    error_log("Error: Datos insuficientes para editar la asignaciÃ³n.");
    echo 'Error interno';
    return;
}

// Obtener los parÃ¡metros de la URL
$cod_cliente = intval($_GET['cod_cliente']);
$cod_zona = intval($_GET['cod_zona']);
$cod_seccion = isset($_GET['cod_seccion']) && $_GET['cod_seccion'] !== 'NULL' ? intval($_GET['cod_seccion']) : null;

// Llamar a obtenerAsignacion con el parÃ¡metro correcto
$asignacion = obtenerAsignacion($cod_cliente, $cod_zona, $cod_seccion);

if (!$asignacion) {
    error_log("Error: No se encontrÃ³ la asignaciÃ³n solicitada.");
    echo 'Error interno';
    return;
}


// Obtener el nombre del cliente y de la zona
$cliente = obtenerNombreCliente($cod_cliente);
$zona = obtenerZonaPorCodigoEditar($cod_zona);

// Obtener todas las zonas disponibles (excepto la actual)
$zonas = obtenerZonasVisitaService();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #343a40;
        }
        label {
            font-weight: bold;
            margin-top: 10px;
        }
        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
            width: 100%;
            margin-top: 15px;
        }
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            width: 100%;
            margin-top: 10px;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        textarea, select, input[type="text"], input[type="number"] {
            border-radius: 5px;
            border: 1px solid #ced4da;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
            padding: 10px;
            width: 100%;
        }
        textarea {
            resize: none;
            height: 100px;
        }
    </style>
</head>
<body>
<div class="container">
    
    <form action="actualizar_asignacion.php" method="post">
        <!-- Cliente (no editable) -->
        <label>Nombre del Cliente:</label>
        <input type="text" value="<?php echo htmlspecialchars($cliente['nombre_comercial']); ?>" disabled>
        <input type="hidden" name="cod_cliente" value="<?php echo htmlspecialchars($cod_cliente); ?>">

        <!-- SecciÃ³n -->
        <?php if ($cod_seccion): ?>
            <label>SecciÃ³n:</label>
            <input type="text" value="<?php echo htmlspecialchars($asignacion['nombre_seccion']); ?>" disabled>
            <input type="hidden" name="cod_seccion" value="<?php echo htmlspecialchars($cod_seccion); ?>">
        <?php else: ?>
            <label>SecciÃ³n:</label>
            <input type="text" value="Sin SecciÃ³n" disabled>
            <input type="hidden" name="cod_seccion" value="NULL">
        <?php endif; ?>

        <!-- Zona Principal -->
        <label>Zona Principal:</label>
        <input type="text" value="<?php echo htmlspecialchars($zona['nombre_zona']); ?>" disabled>
        <input type="hidden" name="cod_zona" value="<?php echo htmlspecialchars($cod_zona); ?>">

        <!-- Zona Secundaria -->
        <label for="zona_secundaria">Zona Secundaria (Opcional):</label>
        <select name="zona_secundaria" id="zona_secundaria">
            <option value="">--Selecciona una Zona--</option>
            <?php foreach ($zonas as $z): ?>
                <?php if ($z['cod_zona'] != $cod_zona): ?>
                    <option value="<?php echo htmlspecialchars($z['cod_zona']); ?>" 
                        <?php echo $asignacion['zona_secundaria'] == $z['cod_zona'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($z['nombre_zona']); ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>

        <!-- Tiempo promedio de visita -->
        <label for="tiempo_promedio_visita">Tiempo Promedio de Visita (horas):</label>
        <input type="number" name="tiempo_promedio_visita" id="tiempo_promedio_visita" step="0.5" 
               value="<?php echo htmlspecialchars($asignacion['tiempo_promedio_visita']); ?>">

        <!-- Preferencia horaria -->
        <label for="preferencia_horaria">Preferencia Horaria:</label>
        <select name="preferencia_horaria" id="preferencia_horaria">
            <option value="">--Selecciona una Preferencia--</option>
            <option value="M" <?php echo $asignacion['preferencia_horaria'] == 'M' ? 'selected' : ''; ?>>MaÃ±ana</option>
            <option value="T" <?php echo $asignacion['preferencia_horaria'] == 'T' ? 'selected' : ''; ?>>Tarde</option>
        </select>

        <!-- Frecuencia de visita -->
        <label for="frecuencia_visita">Frecuencia de Visita:</label>
        <select name="frecuencia_visita" id="frecuencia_visita" required>
            <option value="">--Selecciona una Frecuencia--</option>
            <option value="Todos" <?php echo $asignacion['frecuencia_visita'] == 'Todos' ? 'selected' : ''; ?>>Todos los meses</option>
            <option value="Cada2" <?php echo $asignacion['frecuencia_visita'] == 'Cada2' ? 'selected' : ''; ?>>Cada 2 meses</option>
            <option value="Cada3" <?php echo $asignacion['frecuencia_visita'] == 'Cada3' ? 'selected' : ''; ?>>Cada 3 meses</option>
            <option value="Nunca" <?php echo $asignacion['frecuencia_visita'] == 'Nunca' ? 'selected' : ''; ?>>Nunca</option>
        </select>

        <!-- Observaciones -->
        <label for="observaciones">Observaciones:</label>
        <textarea name="observaciones" id="observaciones"><?php echo htmlspecialchars($asignacion['observaciones']); ?></textarea>

        <!-- Botones -->
        <button type="submit" class="btn btn-primary">Actualizar AsignaciÃ³n</button>
        <a href="asignacion_clientes_zonas.php?cod_zona=<?php echo htmlspecialchars($cod_zona); ?>" class="btn btn-danger">Cancelar</a>
    </form>
</div>
</body>
</html>




