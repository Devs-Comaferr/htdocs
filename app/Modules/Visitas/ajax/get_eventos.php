<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/registrar_visita_handler.php';

try {
    $events = obtenerEventosVisitasService();
    header('Content-Type: application/json; charset=UTF-8');
    $json = json_encode($events);
    if ($json === false) {
        appLogTechnicalError('get_eventos.json', json_last_error_msg());
        echo '[]';
        exit;
    }

    echo $json;
} catch (Exception $e) {
    appLogTechnicalError('get_eventos.service', $e->getMessage());
    header('Content-Type: application/json; charset=UTF-8');
    echo '[]';
    exit;
}
