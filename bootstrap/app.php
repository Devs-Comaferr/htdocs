<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

$projectRoot = dirname(__DIR__);

$appConfigPath = $projectRoot . '/config/app_config.php';
if (is_file($appConfigPath)) {
    require_once $appConfigPath;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ((string)ini_get('date.timezone') === '') {
    date_default_timezone_set('Europe/Madrid');
}

$securityPath = $projectRoot . '/app/Support/security.php';
if (is_file($securityPath)) {
    require_once $securityPath;
}

require_once __DIR__ . '/../app/Support/functions.php';
