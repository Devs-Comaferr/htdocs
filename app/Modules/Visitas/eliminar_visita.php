<?php
// Ã¢Å¡Â Ã¯Â¸Â ARCHIVO LEGACY
// Este archivo ya no debe usarse directamente.
// Se mantiene por compatibilidad.
// Usar /visitas.php?action=crear|editar|eliminar

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
require_once BASE_PATH . '/app/Modules/Visitas/EliminarVisita.php';
requierePermiso('perm_planificador');

$conn = db();

function eliminarVisitaRedirectError(): void
{
    header('Location: index.php?msg=error');
    exit;
}

$rawIdVisita = $_POST['id_visita'] ?? $_GET['id_visita'] ?? '';
$idVisita = filter_var($rawIdVisita, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$codVendedorSesion = isset($_SESSION['codigo']) ? (int)$_SESSION['codigo'] : 0;

if ($idVisita === false || $codVendedorSesion <= 0) {
    eliminarVisitaRedirectError();
}

if (!puedeEliminarVisita($idVisita, $codVendedorSesion)) {
    eliminarVisitaRedirectError();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar']) && $_POST['confirmar'] === '1') {
    if (eliminarVisita($idVisita, $codVendedorSesion)) {
        header('Location: index.php?msg=visita_eliminada');
        exit;
    }

    eliminarVisitaRedirectError();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Visita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
    <style>
        body { padding-top: 80px; }
        .confirm-card { max-width: 720px; margin: 0 auto; }
        .confirm-actions { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="panel panel-danger confirm-card">
        <div class="panel-heading">
            <h2 class="panel-title">Eliminar visita</h2>
        </div>
        <div class="panel-body">
            <p>Ãƒâ€šÃ‚Â¿Seguro que quieres eliminar esta visita? Se eliminarÃƒÆ’Ã‚Â¡n tambiÃƒÆ’Ã‚Â©n los pedidos asociados.</p>
            <form action="eliminar_visita.php" method="POST" class="confirm-actions">
                <input type="hidden" name="id_visita" value="<?php echo htmlspecialchars((string)$idVisita, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="confirmar" value="1">
                <button type="submit" class="btn btn-danger">Confirmar</button>
                <a href="index.php" class="btn btn-default">Cancelar</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php
?>
