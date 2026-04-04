<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Clientes/services/alta_cliente_service.php';

header('Content-Type: application/json; charset=utf-8');

$tipoCliente = trim((string)($_GET['tipo_cliente'] ?? ''));
$formas = altaClienteObtenerFormasLiquidacionPorTipo(db(), $tipoCliente !== '' ? $tipoCliente : null);

echo json_encode([
    'ok' => true,
    'formas' => $formas,
], JSON_UNESCAPED_UNICODE);
