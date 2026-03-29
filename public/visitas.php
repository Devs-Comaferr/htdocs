<?php
require_once dirname(__DIR__) . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Modules/Visitas/RegistrarVisita.php';
require_once BASE_PATH . '/app/Modules/Visitas/EliminarVisita.php';

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
unset($params['action']);

switch ($action) {
    case 'crear':
        require_once BASE_PATH . '/app/Modules/Visitas/registrar_visita_manual.php';
        exit;

    case 'editar':
        require_once BASE_PATH . '/app/Modules/Visitas/EditarVisita.php';
        exit;

    case 'eliminar':
        $url = BASE_URL . '/eliminar_visita.php';
        break;

    default:
        header('Location: ' . BASE_URL . '/index.php');
        exit;
}

if (!empty($params)) {
    $url .= '?' . http_build_query($params);
}

header('Location: ' . $url);
exit;
