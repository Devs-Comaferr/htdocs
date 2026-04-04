<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/security.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

requierePermiso('perm_planificador');

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrfValidateRequest('visitas.check_visitas_existentes');
}

$source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$codClienteRaw = isset($source['cod_cliente']) ? trim((string)$source['cod_cliente']) : '';
$codSeccionRaw = array_key_exists('cod_seccion', $source) ? trim((string)$source['cod_seccion']) : '';
$fechaVisita = isset($source['fecha_visita']) ? trim((string)$source['fecha_visita']) : '';

$codCliente = (int)$codClienteRaw;
$codSeccion = ($codSeccionRaw !== '') ? (int)$codSeccionRaw : null;

if ($codCliente <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVisita)) {
    echo json_encode(
        ['existe' => false, 'estados' => []],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

try {
    $resultado = obtenerVisitasExistentesService($codCliente, $codSeccion, $fechaVisita);
    echo json_encode(
        $resultado,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Exception $e) {
    appLogTechnicalError('check_visitas_existentes.service', $e->getMessage());
    echo json_encode(
        ['existe' => false, 'estados' => []],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
}
