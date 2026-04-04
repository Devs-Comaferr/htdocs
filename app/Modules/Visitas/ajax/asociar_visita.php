<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/security.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    appExitTextError('Metodo no permitido.', 405, 'visitas.asociar_visita.metodo');
}

csrfValidateRequest('visitas.asociar_visita');

$cod_venta = isset($_POST['cod_venta']) ? intval($_POST['cod_venta']) : 0;
$origen = isset($_POST['origen']) ? trim((string)$_POST['origen']) : '';
$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;

if ($cod_venta <= 0 || $origen === '') {
    appExitTextError('Parametros no validos.', 400);
}

if (!in_array($origen, ['visita', 'telefono', 'whatsapp', 'email', 'pedido web'], true)) {
    appExitTextError('Origen no permitido.', 400, 'visitas.asociar_visita.origen');
}

try {
    $resultado = asociarVisitaService($cod_venta, $origen, $codigo_vendedor);
    echo $resultado['message'];
} catch (RuntimeException $e) {
    appExitTextError($e->getMessage(), 500, 'asociar_visita');
}
