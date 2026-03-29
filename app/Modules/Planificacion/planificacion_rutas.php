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

// Incluir funciones.php donde se define toUTF8()

$pageTitle = "Planificación de Rutas";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    <style>
        /* Estilos generales de la pgina */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            padding-top: 60px; /* Evita que el contenido quede tapado por el header fijo */
        }
        h2 {
            text-align: center;
            color: #333;
            margin-top: 30px;
        }
        .buttons-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 50px;
            flex-wrap: wrap;
        }
        .btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            width: 150px;
            height: 150px;
            text-align: center;
        }
        .btn i {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .btn:hover {
            transform: translateY(-3px);
        }
        .btn:active {
            transform: translateY(0);
            box-shadow: none;
        }
        /* Botones especficos */
        .btn-calendario { background-color: #007bff; }
        .btn-calendario:hover { background-color: #0069d9; }
        .btn-pedidos-visitas { background-color: #28a745; }
        .btn-pedidos-visitas:hover { background-color: #1e7e34; }
        .btn-manual { background-color: #ffc107; }
        .btn-manual:hover { background-color: #e0a800; }
        .btn-completar { background-color: #dc3545; }
        .btn-completar:hover { background-color: #bd2130; }
        .btn-festivo { background-color: #FF5722; }
        .btn-festivo:hover { background-color: #E64A19; }
        .btn-no-laborable { background-color: #2c3e50; }
        .btn-no-laborable:hover { background-color: #1a242f; }
        .btn-zonas { background-color: #8e44ad; }
        .btn-zonas:hover { background-color: #7d3c98; }
        .btn-asignar-clientes { background-color: #17a2b8; }
        .btn-asignar-clientes:hover { background-color: #138496; }
        /* Estilos para dispositivos móviles */
        @media (max-width: 1024px) {
            .buttons-container {
                gap: 20px;
                margin-top: 30px;
            }
            .btn {
                width: 120px;
                height: 120px;
                font-size: 14px;
            }
            .btn i { font-size: 30px; }
        }
        @media (max-width: 480px) {
            h2 { font-size: 20px; }
            .btn {
                width: 100px;
                height: 100px;
                font-size: 12px;
            }
            .btn i { font-size: 25px; }
            .buttons-container { gap: 15px; }
        }
    </style>
</head>
<body>
    <?php include(BASE_PATH . '/resources/views/layouts/header.php'); ?>

    
    <div class="buttons-container">
        <a href="mostrar_calendario.php" class="btn btn-calendario" aria-label="Calendario">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendario</span>
        </a>
        <a href="pedidos_visitas.php" class="btn btn-pedidos-visitas" aria-label="Visita por Pedidos">
            <i class="fa-solid fa-pen-to-square"></i>
            <span>Visita por Pedidos</span>
        </a>
        <a href="registrar_visita_manual.php" class="btn btn-manual" aria-label="Registrar Visita Manual">
            <i class="fas fa-edit"></i>
            <span>Visita Manual</span>
        </a>
        <a href="completar_dia.php" class="btn btn-completar" aria-label="Completar Da">
            <i class="fas fa-check-circle"></i>
            <span>Completar Da</span>
        </a>
        <a href="festivo_local.php" class="btn btn-festivo" aria-label="Festivo Local">
            <i class="fas fa-flag"></i>
            <span>Festivo Local</span>
        </a>
        <a href="registrar_dia_no_laborable.php" class="btn btn-no-laborable" aria-label="No Laborable">
            <i class="fas fa-ban"></i>
            <span>No Laborable</span>
        </a>
        <a href="zonas.php" class="btn btn-zonas" aria-label="Zonas">
            <i class="fas fa-route"></i>
            <span>Zonas</span>
        </a>
        <a href="asignacion_clientes_zonas.php" class="btn btn-asignar-clientes" aria-label="Asignar Clientes a Zonas">
            <i class="fas fa-user-plus"></i>
            <span>Asignar Clientes a Zonas</span>
        </a>
    </div>
</body>
</html>

