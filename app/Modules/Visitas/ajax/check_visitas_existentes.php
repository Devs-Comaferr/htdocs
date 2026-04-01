<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';

requierePermiso('perm_planificador');

header('Content-Type: application/json; charset=UTF-8');

$conn = db();

if (!$conn) {
    appLogTechnicalError('check_visitas_existentes.conn', odbc_errormsg());
    echo json_encode(
        ['existe' => false, 'estados' => []],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
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

$sql = "
    SELECT estado_visita
    FROM [integral].[dbo].[cmf_visitas_comerciales]
    WHERE cod_cliente = ?
";
$params = [$codCliente];

if ($codSeccion !== null) {
    $sql .= " AND cod_seccion = ?";
    $params[] = $codSeccion;
} else {
    $sql .= " AND cod_seccion IS NULL";
}

$sql .= " AND fecha_visita = ?";
$params[] = $fechaVisita;

$stmt = odbc_prepare($conn, $sql);
if (!$stmt) {
    appLogTechnicalError('check_visitas_existentes.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
    echo json_encode(
        ['existe' => false, 'estados' => []],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

if (!odbc_execute($stmt, $params)) {
    appLogTechnicalError('check_visitas_existentes.execute', odbc_errormsg($conn) ?: odbc_errormsg());
    echo json_encode(
        ['existe' => false, 'estados' => []],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

$estados = [];
while ($row = odbc_fetch_array($stmt)) {
    $estado = isset($row['estado_visita']) ? trim((string)$row['estado_visita']) : '';
    if ($estado !== '') {
        $estados[] = $estado;
    }
}

echo json_encode(
    [
        'existe' => count($estados) > 0,
        'estados' => $estados,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);
