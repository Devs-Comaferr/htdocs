<?php

date_default_timezone_set('Europe/Madrid');

$start = microtime(true);

// Definir BASE_PATH global del proyecto
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

require_once BASE_PATH . '/bootstrap/app.php';

error_log('BOOTSTRAP TIME: ' . (microtime(true) - $start));
