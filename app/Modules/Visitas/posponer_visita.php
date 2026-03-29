<?php
// ⚠️ ARCHIVO LEGACY
// Este archivo ya no debe usarse directamente.
// Se mantiene por compatibilidad.
// Usar /visitas.php?action=crear|editar|eliminar

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

// Este archivo ha sido desactivado.
// La accion de "posponer" se considera creacion de nueva visita.
// Se mantiene solo por compatibilidad legacy.

$query = [];

if (!empty($_GET) && is_array($_GET)) {
    $query = $_GET;
} elseif (!empty($_POST) && is_array($_POST)) {
    $query = $_POST;
}

$url = 'registrar_visita_manual.php';
if (!empty($query)) {
    $url .= '?' . http_build_query($query);
}

header('Location: ' . $url);
exit;
