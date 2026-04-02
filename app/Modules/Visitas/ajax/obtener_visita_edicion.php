<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';

$idVisita = isset($_GET['id_visita']) ? (int) $_GET['id_visita'] : 0;
if ($idVisita <= 0) {
    appExitJsonError('La visita indicada no es valida.', 400, 'visitas.obtener_edicion.id');
}

$data = obtenerDatosEditarVisita($idVisita);
if ($data === null) {
    appExitJsonError('No se encontro la visita indicada.', 404, 'visitas.obtener_edicion.not_found');
}

$payload = [
    'id_visita' => (int) $data['id_visita'],
    'cod_cliente' => (int) $data['cod_cliente'],
    'nombre_comercial' => function_exists('toUTF8') ? toUTF8((string) $data['nombre_comercial']) : (string) $data['nombre_comercial'],
    'cod_seccion' => $data['cod_seccion'],
    'fecha_visita' => (string) $data['fecha_visita'],
    'hora_inicio_visita' => (string) $data['hora_inicio_visita'],
    'hora_fin_visita' => (string) $data['hora_fin_visita'],
    'observaciones' => function_exists('toUTF8') ? toUTF8((string) $data['observaciones']) : (string) $data['observaciones'],
    'estado_visita' => normalizarEstadoVisita((string) $data['estado_visita']),
    'tiempo_promedio_minutes' => (float) ($data['tiempo_promedio_minutes'] ?? 0),
    'tiene_pedidos_asociados' => !empty($data['tiene_pedidos_asociados']),
    'bloquear_cambio_estado' => !empty($data['bloquear_cambio_estado']),
];

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
