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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba de Font Awesome</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 50px;
            background-color: #f4f4f4;
        }
        .icon-test {
            font-size: 50px;
            color: #007BFF;
            margin: 20px;
        }
        h1 {
            color: #333;
        }
    </style>
</head>
<body>
    <h1>Prueba de Font Awesome</h1>
    <i class="fas fa-calendar-alt icon-test"></i>
    <i class="fas fa-map-marked-alt icon-test"></i>
    <i class="fas fa-route icon-test"></i>
    <i class="fas fa-user-plus icon-test"></i>
</body>
</html>
