<?php
require_once dirname(__DIR__) . '/bootstrap/init.php';

$action = $_GET['action'] ?? null;

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

require_once BASE_PATH . '/app/Modules/Visitas/controllers/visitas.php';
