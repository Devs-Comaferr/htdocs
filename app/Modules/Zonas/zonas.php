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
// zonas.php
require_once BASE_PATH . '/app/Modules/Planificacion/PlanificacionService.php';
require_once BASE_PATH . '/app/Support/functions.php';
$pageTitle = 'Gestión de Zonas';
include BASE_PATH . '/resources/views/layouts/header.php';
$zonas = obtenerZonasVisitaService();

// Verificar si el usuario ha iniciado sesión

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Zonas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 3.3.7 -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
    <!-- Font Awesome para los conos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
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
        .buttons-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-top: 60px;
            margin-bottom: 50px;
        }
        .btn-zona {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 200px;
            height: 200px;
            padding: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #fff;
            background-color: #17a2b8;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .btn-zona i {
            font-size: 50px;
            margin-bottom: 15px;
        }
        .btn-zona:hover {
            background-color: #117a8b;
            transform: translateY(-5px);
        }
        .btn-zona:active {
            background-color: #0f6674;
            transform: translateY(0);
            box-shadow: none;
        }
        .create-form {
            max-width: 600px;
            margin: 0 auto 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .create-form input, .create-form textarea, .create-form select {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .create-form input[type="submit"] {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 20px;
            transition: background-color 0.3s;
        }
        .create-form input[type="submit"]:hover {
            background-color: #218838;
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
            margin-top: 30px;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
        /* Estilos para dispositivos móviles */
        @media (max-width: 1024px) {
            .btn-zona {
                width: 150px;
                height: 150px;
                font-size: 16px;
            }
            .btn-zona i {
                font-size: 40px;
                margin-bottom: 10px;
            }
        }
        @media (max-width: 480px) {
            .btn-zona {
                width: 120px;
                height: 120px;
                font-size: 14px;
            }
            .btn-zona i {
                font-size: 30px;
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        
        <!-- Sección 1: Listado de Zonas como Botones -->
        
        <?php if (!empty($zonas)): ?>
            <div class="buttons-container">
                <?php foreach ($zonas as $zona): ?>
                    <a href="zonas_rutas.php?cod_zona=<?php echo htmlspecialchars($zona['cod_zona']); ?>" class="btn-zona">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($zona['nombre_zona']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">No tienes zonas disponibles.</div>
        <?php endif; ?>
        
        <!-- Sección 2: Formulario para Crear Nueva Zona -->
        <div class="create-form">
            <h2>Crear Nueva Zona</h2>
            <form action="procesar_crear_zona.php" method="post">
                <label for="nombre_zona">Nombre de la Zona:</label>
                <input type="text" id="nombre_zona" name="nombre_zona" required>
                
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion"></textarea>
                
                <label for="duracion_semanas">Duración (semanas):</label>
                <input type="number" id="duracion_semanas" name="duracion_semanas" min="1" required>
                
                <label for="orden">Orden en el Ciclo:</label>
                <input type="number" id="orden" name="orden" min="1" required>
                
                <input type="submit" value="Crear Zona">
            </form>
        </div>
        
        <a href="planificacion_rutas.php" class="back-button">Volver al Planificador de Visitas</a>
    </div>
    
    <!-- jQuery + Bootstrap JS -->
    <script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.12.4.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/js/bootstrap.min.js"></script>
</body>
</html>

