<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
// zonas_rutas.php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Modules/Planificacion/PlanificacionService.php';
require_once BASE_PATH . '/app/Support/functions.php';
$pageTitle = "Gestionar Rutas de la Zona";
include BASE_PATH . '/resources/views/layouts/header.php';
// Iniciar sesión si no está ya iniciada


// Verificar si el usuario ha iniciado sesión


// Verificar si el usuario est autenticado
if (!isset($_SESSION['codigo'])) {
    error_log('Acceso no autorizado.');
    echo 'Error interno';
}
    return;

// Obtener el código del vendedor desde la sesión
$cod_vendedor = intval($_SESSION['codigo']);

// Verificar si 'cod_zona' est presente en la URL
if (isset($_GET['cod_zona'])) {
    $cod_zona = intval($_GET['cod_zona']);
    
    // Obtener información de la zona
    $zonas = obtenerZonasVisitaService();
    $zona_actual = null;
    foreach ($zonas as $zona) {
        if ($zona['cod_zona'] == $cod_zona) {
            $zona_actual = $zona;
            break;
        }
    }
    
    if (!$zona_actual) {
        error_log('Zona no encontrada.');
        echo 'Error interno';
        return;
    }
    
    // Obtener rutas asignadas a la zona
    $rutas_asignadas = obtenerRutasPorZonaService($cod_zona);
    
    // Obtener todas las rutas disponibles
    $todas_rutas = obtenerTodasRutas();
    
} else {
    // Si 'cod_zona' no est presente, mostrar la lista de zonas disponibles
    $zonas_disponibles = obtenerZonasVisitaService();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($cod_zona) ? "Gestionar Rutas de la Zona: " . htmlspecialchars($zona_actual['nombre_zona']) : "Zonas Disponibles"; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 3.3.7 -->
        
    <style>
        /* Estilos mejorados para dispositivos tctiles */
        body {
            font-family: Arial, sans-serif;
            padding-top: 20px;
            background-color: #f0f2f5;
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        .assign-form, .back-button, .zonas-list {
            max-width: 800px;
            margin: 0 auto 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .assign-form select, .zonas-list .zona-item {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .assign-form input[type="submit"], .zonas-list .zona-item a {
            width: 100%;
            padding: 15px;
            background-color: #17a2b8;
            color: #fff;
            border: none;
            font-size: 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .assign-form input[type="submit"]:hover, .zonas-list .zona-item a:hover {
            background-color: #117a8b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        th, td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            text-align: center;
            font-size: 16px;
        }
        th {
            background-color: #e9ecef;
            color: #333;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #777;
        }
        .action-link {
            padding: 10px 20px;
            background-color: #dc3545;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .action-link:hover {
            background-color: #c82333;
        }
        .back-button {
            display: block;
            width: 100%;
            padding: 15px;
            font-size: 18px;
            background-color: #6c757d;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
            text-align: center;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
        /* Estilos para la lista de zonas */
        .zonas-list .zona-item {
            margin-bottom: 15px;
        }
        .zonas-list .zona-item a {
            background-color: #28a745; /* Verde */
        }
        .zonas-list .zona-item a:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($cod_zona)): ?>
            <!-- Mostrar información de la zona específica -->
            <h1><?php echo htmlspecialchars($zona_actual['nombre_zona']); ?></h1>
            
            <h2>Rutas Asignadas</h2>
            <table>
                <tr>
                    <th>Código de Ruta</th>
                    <th>Nombre de Ruta</th>
                </tr>
                <?php if (!empty($rutas_asignadas)): ?>
                    <?php foreach ($rutas_asignadas as $ruta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ruta['cod_ruta']); ?></td>
                            <td><?php echo htmlspecialchars($ruta['nombre_ruta']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="no-data">No hay rutas asignadas a esta zona.</td>
                    </tr>
                <?php endif; ?>
            </table>
            
            <div class="assign-form">
                <h2>Asignar Nueva Ruta a la Zona</h2>
                <form action="procesar_asignar_ruta_zona.php" method="post">
                    <input type="hidden" name="cod_zona" value="<?php echo $cod_zona; ?>">
                    
                    <label for="cod_ruta">Selecciona la Ruta:</label>
                    <select id="cod_ruta" name="cod_ruta" required>
                        <option value="">--Selecciona una Ruta--</option>
                        <?php foreach ($todas_rutas as $ruta): ?>
                            <?php
                            // Verificar si la ruta ya est asignada
                            $ya_asignada = false;
                            foreach ($rutas_asignadas as $ra) {
                                if ($ra['cod_ruta'] == $ruta['cod_ruta']) {
                                    $ya_asignada = true;
                                    break;
                                }
                            }
                            if (!$ya_asignada):
                            ?>
                                <option value="<?php echo htmlspecialchars($ruta['cod_ruta']); ?>">
                                    <?php echo htmlspecialchars($ruta['nombre_ruta']) . " - (" . $ruta['cod_ruta'] .")"; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="submit" value="Asignar Ruta">
                </form>
            </div>
            
            <a href="zonas.php" class="back-button">Volver a Zonas</a>
        
        <?php else: ?>
            <!-- Mostrar lista de zonas disponibles -->
            <h1>Zonas Disponibles</h1>
            
            <?php if (!empty($zonas_disponibles)): ?>
                <div class="zonas-list">
                    <?php foreach ($zonas_disponibles as $zona): ?>
                        <div class="zona-item">
                            <a href="zonas_rutas.php?cod_zona=<?php echo htmlspecialchars($zona['cod_zona']); ?>" class="action-link">
                                <?php echo htmlspecialchars($zona['nombre_zona']); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">No tienes zonas disponibles.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- jQuery + Bootstrap JS -->
        </body>
</html>
