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

// procesar_crear_zona.php
include_once BASE_PATH . '/app/Modules/Planificacion/funciones_planificacion_rutas.php';
header('Content-Type: text/html; charset=UTF-8');

// Verificar que se ha enviado el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_zona = isset($_POST['nombre_zona']) ? trim($_POST['nombre_zona']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $duracion_semanas = isset($_POST['duracion_semanas']) ? intval($_POST['duracion_semanas']) : 0;
    $orden = isset($_POST['orden']) ? intval($_POST['orden']) : 0;
    
    // Validar entradas
    if (empty($nombre_zona) || $duracion_semanas <= 0 || $orden <= 0) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error al Crear Zona</title>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <!-- Bootstrap 3.3.7 -->
            <link rel='stylesheet' href='<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
                .message { display: inline-block; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; }
                .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
                .back-button:hover { background-color: #5a6268; }
            </style>
        </head>
        <body>
            <div class='message'>Por favor, completa todos los campos obligatorios correctamente.</div><br>
            <a href='zonas.php' class='back-button'>Volver</a>
        </body>
        </html>";
        exit;
    }
    
    // Crear la zona
    if (crearZonaVisita($nombre_zona, $descripcion, $duracion_semanas, $orden)) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Zona Creada</title>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <!-- Bootstrap 3.3.7 -->
            <link rel='stylesheet' href='<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
                .message { display: inline-block; padding: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; }
                .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
                .back-button:hover { background-color: #5a6268; }
            </style>
        </head>
        <body>
            <div class='message'>Zona de visita creada exitosamente.</div><br>
            <a href='zonas.php' class='back-button'>Volver</a>
        </body>
        </html>";
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error al Crear Zona</title>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <!-- Bootstrap 3.3.7 -->
            <link rel='stylesheet' href='<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
                .message { display: inline-block; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; }
                .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
                .back-button:hover { background-color: #5a6268; }
            </style>
        </head>
        <body>
            <div class='message'>Error al crear la zona de visita. IntÃƒÂ©ntalo de nuevo.</div><br>
            <a href='zonas.php' class='back-button'>Volver</a>
        </body>
        </html>";
    }
} else {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>MÃƒÂ©todo InvÃƒÂ¡lido</title>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <!-- Bootstrap 3.3.7 -->
        <link rel='stylesheet' href='<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css'>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
            .message { display: inline-block; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; }
            .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
            .back-button:hover { background-color: #5a6268; }
        </style>
    </head>
    <body>
        <div class='message'>MÃƒÂ©todo de solicitud no vÃƒÂ¡lido.</div><br>
        <a href='zonas.php' class='back-button'>Volver</a>
    </body>
    </html>";
}
?>
