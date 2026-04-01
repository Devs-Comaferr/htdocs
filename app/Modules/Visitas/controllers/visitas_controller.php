<?php

class VisitasController
{
    public function handle()
    {
        require_once BASE_PATH . '/bootstrap/auth.php';
        require_once BASE_PATH . '/app/Modules/Visitas/services/registrar_visita_handler.php';
        require_once BASE_PATH . '/app/Modules/Visitas/services/eliminar_visita_handler.php';

        $action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
        $params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        unset($params['action']);

        switch ($action) {
            case 'crear':
                require_once BASE_PATH . '/app/Modules/Visitas/views/visita_manual.php';
                exit;

            case 'editar':
                require_once BASE_PATH . '/app/Modules/Visitas/views/editar_visita_handler.php';
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
    }
}
