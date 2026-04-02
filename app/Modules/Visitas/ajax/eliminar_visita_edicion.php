<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/eliminar_visita_handler.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    appExitJsonError('Metodo no permitido.', 405, 'visitas.eliminar_edicion.method');
}

csrfValidateRequest('visitas.eliminar');

$idVisita = isset($_POST['id_visita']) ? (int) $_POST['id_visita'] : 0;
$codVendedorSesion = (int) ($_SESSION['codigo'] ?? 0);
if ($idVisita <= 0 || $codVendedorSesion <= 0) {
    appExitJsonError('La visita indicada no es valida.', 400, 'visitas.eliminar_edicion.id');
}

if (!puedeEliminarVisita($idVisita, $codVendedorSesion)) {
    appExitJsonError('No se puede eliminar la visita indicada.', 403, 'visitas.eliminar_edicion.forbidden');
}

if (!eliminarVisita($idVisita, $codVendedorSesion)) {
    appExitJsonError('No se pudo eliminar la visita.', 500, 'visitas.eliminar_edicion.delete');
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(
    [
        'ok' => true,
        'message' => 'Visita eliminada correctamente.',
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
exit;
