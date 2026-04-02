<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    appExitJsonError('Metodo no permitido.', 405, 'visitas.guardar_edicion.method');
}

csrfValidateRequest('visitas.editar');

$idVisita = isset($_POST['id_visita']) ? (int) $_POST['id_visita'] : 0;
if ($idVisita <= 0) {
    appExitJsonError('La visita indicada no es valida.', 400, 'visitas.guardar_edicion.id');
}

$resultado = procesarEdicionVisita($idVisita, $_POST, (int) ($_SESSION['codigo'] ?? 0), 'visita_pedido');
if (!$resultado['ok']) {
    appExitJsonError((string) ($resultado['error'] ?? 'No se pudo actualizar la visita.'), 422, 'visitas.guardar_edicion');
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(
    [
        'ok' => true,
        'message' => 'Visita actualizada correctamente.',
        'redirect' => (string) ($resultado['redirect'] ?? ''),
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
exit;
