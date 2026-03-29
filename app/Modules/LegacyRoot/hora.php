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


echo "PHP timezone: " . date_default_timezone_get() . "<br>";
echo "Hora PHP: " . date('Y-m-d H:i:s') . "<br>";
echo "Hora timestamp: " . time() . "<br>";
echo "Hora servidor: " . gmdate('Y-m-d H:i:s') . "<br>";
