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

if (!esAdmin() || !appDebugAccessAllowed()) {
    http_response_code(403);
    exit('Acceso restringido.');
}

if ($conn) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Conexion Exitosa</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; background-color: #f0f2f5; }
            .message { display: inline-block; padding: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; }
            .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
            .back-button:hover { background-color: #5a6268; }
        </style>
    </head>
    <body>
        <div class='message'>Conexion a la base de datos exitosa.</div><br>
        <a href='planificacion_rutas.php' class='back-button'>Volver al Inicio</a>
    </body>
    </html>";
} else {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error de Conexion</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; background-color: #f0f2f5; }
            .message { display: inline-block; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; }
            .back-button { margin-top: 20px; padding: 10px 20px; font-size: 18px; background-color: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
            .back-button:hover { background-color: #5a6268; }
        </style>
    </head>
    <body>
        <div class='message'>Error al conectar a la base de datos.</div><br>
        <a href='planificacion_rutas.php' class='back-button'>Volver al Inicio</a>
    </body>
    </html>";
}
