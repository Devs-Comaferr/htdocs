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
require_once BASE_PATH . '/app/Support/db.php';

include_once BASE_PATH . '/app/Modules/Planificacion/funciones_planificacion_rutas.php';

if (!isset($_SESSION['codigo'], $_POST['cod_cliente'], $_POST['cod_zona'], $_POST['cod_seccion'])) {
    appExitTextError('Acceso no autorizado o datos incompletos.', 400);
}

$cod_cliente = intval($_POST['cod_cliente']);
$cod_zona = intval($_POST['cod_zona']);
$cod_seccion = ($_POST['cod_seccion'] === '' ? 'NULL' : intval($_POST['cod_seccion']));

try {
    $conn = db();

    $query = "DELETE FROM cmf_asignacion_zonas_clientes
              WHERE cod_cliente = $cod_cliente
              AND zona_principal = $cod_zona
              AND cod_seccion " . ($cod_seccion === 'NULL' ? 'IS NULL' : "= $cod_seccion");

    $resultado = odbc_exec($conn, $query);
    if (!$resultado) {
        throw new Exception('Error al eliminar la asignacion.');
    }

    header("Location: asignacion_clientes_zonas.php?cod_zona=$cod_zona&mensaje=Eliminado con exito");
    exit();
} catch (Exception $e) {
    appExitTextError('No se pudo eliminar la asignacion.', 500, 'borrar_asignacion', $e->getMessage());
}
