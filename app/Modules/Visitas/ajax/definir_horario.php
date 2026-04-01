<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/registrar_visita_handler.php';

if (!isset($_POST['cod_cliente']) || empty($_POST['cod_cliente'])) {
    appExitTextError('Falta el codigo del cliente.', 400);
}

$cod_cliente = intval($_POST['cod_cliente']);
$cod_seccion = (isset($_POST['cod_seccion']) && $_POST['cod_seccion'] !== '') ? intval($_POST['cod_seccion']) : null;
$hora_inicio_manana = trim((string)($_POST['hora_inicio_manana'] ?? ''));
$hora_fin_manana = trim((string)($_POST['hora_fin_manana'] ?? ''));
$hora_inicio_tarde = trim((string)($_POST['hora_inicio_tarde'] ?? ''));
$hora_fin_tarde = trim((string)($_POST['hora_fin_tarde'] ?? ''));

if (!actualizarHorarioVisitaService($cod_cliente, $cod_seccion, $hora_inicio_manana, $hora_fin_manana, $hora_inicio_tarde, $hora_fin_tarde)) {
    appExitTextError('No se pudo actualizar el horario.', 500, 'definir_horario');
}

echo 'OK - Horario actualizado';
