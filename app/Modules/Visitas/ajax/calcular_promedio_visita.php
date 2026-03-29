<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

if (!isset($_GET['cod_cliente']) || $_GET['cod_cliente'] === '') {
    die('Codigo de cliente no especificado.');
}

$cod_cliente = (int)$_GET['cod_cliente'];
if ($cod_cliente <= 0) {
    die('Codigo de cliente invalido.');
}

$cod_seccion = null;
if (array_key_exists('cod_seccion', $_GET)) {
    $rawSeccion = $_GET['cod_seccion'];
    if ($rawSeccion !== '' && $rawSeccion !== null) {
        $cod_seccion = (int)$rawSeccion;
    }
}

try {
    $promedio_minutes = recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
} catch (Exception $e) {
    die('Error al recalcular promedio: ' . $e->getMessage());
}

$horas = floor($promedio_minutes / 60);
$minutos = round($promedio_minutes - ($horas * 60));

if ($horas == 0) {
    $formatted = $minutos . ' minutos';
} else {
    $formatted = $horas . ' horas ' . $minutos . ' minutos';
}

echo $formatted;
