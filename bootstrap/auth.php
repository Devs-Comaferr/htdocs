<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$projectRoot = dirname(__DIR__);

$controlAccesoPath = $projectRoot . '/app/Support/control_acceso.php';
if (is_file($controlAccesoPath)) {
    require_once $controlAccesoPath;
}

$permisosPath = $projectRoot . '/includes/permisos.php';
if (is_file($permisosPath)) {
    require_once $permisosPath;
}

if (function_exists('requiereLogin')) {
    requiereLogin();
}

if (function_exists('requiereActivo')) {
    requiereActivo();
}
