<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/registrar_visita_handler.php';

if (!isset($_POST['cod_pedido']) || !isset($_POST['origen'])) {
    echo 'ERROR: Faltan parametros (cod_pedido y origen).';
    exit;
}

$cod_pedido = (int)$_POST['cod_pedido'];
$nuevo_origen = trim((string)$_POST['origen']);

if ($cod_pedido <= 0) {
    echo 'ERROR: Codigo de pedido invalido.';
    exit;
}
if ($nuevo_origen === '') {
    echo 'ERROR: Origen invalido.';
    exit;
}

$resultado = actualizarOrigenVisitaService($cod_pedido, $nuevo_origen);
echo $resultado['ok'] ? 'OK' : ('ERROR: ' . $resultado['error']);
