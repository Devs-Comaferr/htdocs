<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Clientes/services/alta_cliente_service.php';

header('Content-Type: application/json; charset=utf-8');

$telefono = trim((string)($_GET['telefono'] ?? ''));
$email = trim((string)($_GET['email'] ?? ''));

$conn = db();
$matchesTelefono = $telefono !== '' ? altaClienteBuscarCoincidenciasTelefono($conn, $telefono) : [];
$matchesEmail = $email !== '' ? altaClienteBuscarCoincidenciasEmail($conn, $email) : [];

echo json_encode([
    'ok' => true,
    'telefono' => [
        'exists' => $matchesTelefono !== [],
        'matches' => $matchesTelefono,
    ],
    'email' => [
        'exists' => $matchesEmail !== [],
        'matches' => $matchesEmail,
    ],
], JSON_UNESCAPED_UNICODE);
