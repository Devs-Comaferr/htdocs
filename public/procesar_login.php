<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/bootstrap/init.php';

try {
    require_once BASE_PATH . '/app/Modules/Auth/procesar_login.php';
} catch (Throwable $e) {
    echo '<pre>';
    echo 'ERROR CAPTURADO:' . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
    echo '</pre>';
    exit;
}
