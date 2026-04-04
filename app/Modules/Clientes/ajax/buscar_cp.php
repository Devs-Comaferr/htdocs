<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Clientes/services/alta_cliente_service.php';

header('Content-Type: application/json; charset=utf-8');

$cp = trim((string)($_GET['cp'] ?? ''));
if ($cp === '') {
    echo json_encode([
        'ok' => false,
        'found' => false,
        'ambiguous' => false,
        'options' => [],
        'message' => 'Codigo postal vacio.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$resultado = altaClienteBuscarLocalidadPorCp(db(), $cp);
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
