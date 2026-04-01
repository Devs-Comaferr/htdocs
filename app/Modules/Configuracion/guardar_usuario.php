<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/logs.php';

$conn = db();


if (!esAdmin()) {
    appExitTextError('Acceso no autorizado.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appExitTextError('Metodo no permitido.', 405);
}

csrfValidateRequest('configuracion.guardar_usuario');

$email = trim((string)($_POST['email'] ?? ''));
$tipo_plan = trim((string)($_POST['tipo_plan'] ?? 'free'));
$perm_productos = isset($_POST['perm_productos']) ? 1 : 0;
$perm_estadisticas = isset($_POST['perm_estadisticas']) ? 1 : 0;
$perm_planificador = isset($_POST['perm_planificador']) ? 1 : 0;
$activo = isset($_POST['activo']) ? 1 : 0;

if ($email === '') {
    $_SESSION['mensaje_ok'] = 'No se pudo guardar: falta el email del usuario.';
    header('Location: usuarios.php');
    exit;
}

$sql = "UPDATE cmf_vendedores_user
        SET tipo_plan = ?,
            perm_productos = ?,
            perm_estadisticas = ?,
            perm_planificador = ?,
            activo = ?
        WHERE email = ?";

$stmt = odbc_prepare($conn, $sql);
$ok = $stmt && odbc_execute($stmt, [
    $tipo_plan,
    $perm_productos,
    $perm_estadisticas,
    $perm_planificador,
    $activo,
    $email,
]);

if ($ok) {
    registrarLog(
        'Modificacin usuario',
        "Usuario modificado: $email | Plan: $tipo_plan | Productos:$perm_productos | Estadisticas:$perm_estadisticas | Planificador:$perm_planificador | Activo:$activo"
    );
    $_SESSION['mensaje_ok'] = 'Usuario guardado correctamente.';
    if (isset($_SESSION['email']) && strcasecmp((string)$_SESSION['email'], $email) === 0) {
        $_SESSION['tipo_plan'] = $tipo_plan;
        $_SESSION['perm_productos'] = $perm_productos;
        $_SESSION['perm_estadisticas'] = $perm_estadisticas;
        $_SESSION['perm_planificador'] = $perm_planificador;
        $_SESSION['activo'] = (string)$activo;
    }
    header('Location: usuarios.php');
    exit;
}

$_SESSION['mensaje_ok'] = 'Error al actualizar el usuario.';
header('Location: usuarios.php');
exit;
