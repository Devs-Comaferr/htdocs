<?php
// Dispatcher legacy mantenido por compatibilidad.
// Las rutas canónicas viven ya en endpoints explícitos y AJAX dedicados.
require_once dirname(__DIR__) . '/bootstrap/init.php';

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'crear') {
    $origen = $_POST['origen'] ?? 'visita';

    switch ($origen) {
        case 'telefono':
            require BASE_PATH . '/app/Modules/Clientes/registrar_telefono.php';
            break;

        case 'email':
            require BASE_PATH . '/app/Modules/Clientes/registrar_email.php';
            break;

        case 'whatsapp':
            require BASE_PATH . '/app/Modules/Clientes/registrar_whatsapp.php';
            break;

        case 'web':
            require BASE_PATH . '/app/Modules/Clientes/registrar_web.php';
            break;

        case 'visita':
        default:
            require BASE_PATH . '/app/Modules/Visitas/controllers/registrar_visita.php';
            break;
    }

    exit;
}

if ($action === 'editar') {
    require_once BASE_PATH . '/app/Modules/Visitas/controllers/editar_visita.php';
    exit;
}

if ($action === 'eliminar') {
    require_once BASE_PATH . '/app/Modules/Visitas/controllers/eliminar_visita.php';
    exit;
}

header('Location: ' . BASE_URL . '/index.php');
exit;
