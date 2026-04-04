<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Clientes/services/alta_cliente_service.php';

header('Content-Type: application/json; charset=UTF-8');

$nif = trim((string)($_GET['nif'] ?? ''));
if ($nif === '') {
    echo json_encode(['ok' => true, 'exists' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

$conn = db();
$cliente = altaClienteBuscarClienteExistentePorNif($conn, $nif);

if (!$cliente) {
    echo json_encode(['ok' => true, 'exists' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

    $codigoSesion = isset($_SESSION['codigo']) ? (string)$_SESSION['codigo'] : '';
    $codVendedorCliente = (string)($cliente['cod_vendedor'] ?? '');
    $esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
    $puedeVerFicha = $esAdmin || ($codigoSesion !== '' && $codigoSesion === $codVendedorCliente);

echo json_encode([
    'ok' => true,
    'exists' => true,
    'cliente' => [
        'cod_cliente' => (string)($cliente['cod_cliente'] ?? ''),
        'cod_vendedor' => $codVendedorCliente,
        'nombre_comercial' => (string)($cliente['nombre_comercial'] ?? ''),
        'cif' => (string)($cliente['cif'] ?? ''),
        'poblacion' => (string)($cliente['poblacion'] ?? ''),
        'provincia' => (string)($cliente['provincia'] ?? ''),
        'can_view' => $puedeVerFicha,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
