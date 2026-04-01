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

// procesar_asignar_ruta_zona.php
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';
header('Content-Type: text/html; charset=UTF-8');

$ui_version = 'bs5';
$ui_requires_jquery = false;
ob_start();
include BASE_PATH . '/resources/views/layouts/header.php';
$globalHeaderHead = ob_get_clean();

// Verificar que se ha enviado el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cod_zona = isset($_POST['cod_zona']) ? intval($_POST['cod_zona']) : 0;
    $cod_ruta = isset($_POST['cod_ruta']) ? intval($_POST['cod_ruta']) : 0;
    
    // Validar entradas
    if ($cod_zona <= 0 || $cod_ruta <= 0) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error al Asignar Ruta</title>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            {$globalHeaderHead}
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
                .message { display: inline-block; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; }
                .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
                .back-button:hover { background-color: #5a6268; }
            </style>
        </head>
        <body>
            <div class='message'>Datos invÃ¡lidos para la asignaciÃ³n de la ruta.</div><br>
            <a href='zonas_rutas.php?cod_zona=$cod_zona' class='back-button'>Volver</a>
        </body>
        </html>";
        exit;
    }
    
    // Asignar la ruta a la zona
    if (asignarRutaZonaService($cod_zona, $cod_ruta)) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Ruta Asignada</title>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            {$globalHeaderHead}
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
                .message { display: inline-block; padding: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; }
                .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
                .back-button:hover { background-color: #5a6268; }
            </style>
        </head>
        <body>
            <div class='message'>Ruta asignada exitosamente a la zona.</div><br>
            <a href='zonas_rutas.php?cod_zona=$cod_zona' class='back-button'>Volver</a>
        </body>
        </html>";
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error al Asignar Ruta</title>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            {$globalHeaderHead}
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
                .message { display: inline-block; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; }
                .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
                .back-button:hover { background-color: #5a6268; }
            </style>
        </head>
        <body>
            <div class='message'>Error al asignar la ruta a la zona. IntÃ©ntalo de nuevo.</div><br>
            <a href='zonas_rutas.php?cod_zona=$cod_zona' class='back-button'>Volver</a>
        </body>
        </html>";
    }
} else {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>MÃ©todo InvÃ¡lido</title>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        {$globalHeaderHead}
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
            .message { display: inline-block; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; }
            .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
            .back-button:hover { background-color: #5a6268; }
        </style>
    </head>
    <body>
        <div class='message'>MÃ©todo de solicitud no vÃ¡lido.</div><br>
        <a href='zonas.php' class='back-button'>Volver</a>
    </body>
    </html>";
}
?>

