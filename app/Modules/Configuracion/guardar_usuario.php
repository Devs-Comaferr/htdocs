<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';

$conn = db();

if (!esAdmin()) {
    appExitTextError('Acceso no autorizado.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appExitTextError('Metodo no permitido.', 405);
}

csrfValidateRequest('configuracion.guardar_usuario');

$action = trim((string)($_POST['action'] ?? 'actualizar'));
$email = trim((string)($_POST['email'] ?? ''));
$tipo_plan = trim((string)($_POST['tipo_plan'] ?? 'free'));
$perm_productos = isset($_POST['perm_productos']) ? 1 : 0;
$perm_estadisticas = isset($_POST['perm_estadisticas']) ? 1 : 0;
$perm_planificador = isset($_POST['perm_planificador']) ? 1 : 0;
$activo = isset($_POST['activo']) ? 1 : 0;

function redirectUsuarios(string $mensaje, string $tipo = 'ok'): void
{
    $_SESSION['mensaje_ok'] = $mensaje;
    $_SESSION['mensaje_tipo'] = $tipo;
    header('Location: usuarios.php');
    exit;
}

if ($action === 'crear') {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
    $rol = trim((string)($_POST['rol'] ?? 'comercial'));
    $codVendedorRaw = trim((string)($_POST['cod_vendedor'] ?? ''));
    $codVendedor = $codVendedorRaw !== '' ? intval($codVendedorRaw) : null;

    if ($nombre === '' || $email === '' || $password === '') {
        redirectUsuarios('No se pudo crear el usuario: faltan datos obligatorios.', 'error');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectUsuarios('No se pudo crear el usuario: el email no es valido.', 'error');
    }

    if ($password !== $passwordConfirm) {
        redirectUsuarios('No se pudo crear el usuario: las contrasenas no coinciden.', 'error');
    }

    if (strlen($password) < 8) {
        redirectUsuarios('No se pudo crear el usuario: la contrasena debe tener al menos 8 caracteres.', 'error');
    }

    $rolesPermitidos = ['admin', 'comercial', 'compras', 'administracion'];
    if (!in_array($rol, $rolesPermitidos, true)) {
        $rol = 'comercial';
    }

    $sqlExiste = "SELECT TOP 1 email FROM cmf_comerciales_app_usuarios WHERE email = ?";
    $stmtExiste = odbc_prepare($conn, $sqlExiste);
    $existe = $stmtExiste && odbc_execute($stmtExiste, [$email]) && odbc_fetch_row($stmtExiste);
    if ($existe) {
        redirectUsuarios('No se pudo crear el usuario: el email ya existe.', 'error');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        redirectUsuarios('No se pudo crear el usuario.', 'error');
    }

    $sqlInsert = "
        INSERT INTO cmf_comerciales_app_usuarios
            (cod_vendedor, nombre, email, clave, rol, tipo_plan, activo, perm_productos, perm_estadisticas, perm_planificador)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmtInsert = odbc_prepare($conn, $sqlInsert);
    $ok = $stmtInsert && odbc_execute($stmtInsert, [
        $codVendedor,
        $nombre,
        $email,
        $hash,
        $rol,
        $tipo_plan,
        $activo,
        $perm_productos,
        $perm_estadisticas,
        $perm_planificador,
    ]);

    if ($ok) {
        redirectUsuarios('Usuario creado correctamente.');
    }

    redirectUsuarios('Error al crear el usuario.', 'error');
}

if ($action === 'eliminar') {
    if ($email === '') {
        redirectUsuarios('No se pudo eliminar: falta el email del usuario.', 'error');
    }

    if (isset($_SESSION['email']) && strcasecmp((string)$_SESSION['email'], $email) === 0) {
        redirectUsuarios('No puedes eliminar tu propio usuario mientras estas conectado.', 'error');
    }

    $sqlDelete = "DELETE FROM cmf_comerciales_app_usuarios WHERE email = ?";
    $stmtDelete = odbc_prepare($conn, $sqlDelete);
    $ok = $stmtDelete && odbc_execute($stmtDelete, [$email]);

    if ($ok) {
        redirectUsuarios('Usuario eliminado correctamente.');
    }

    redirectUsuarios('Error al eliminar el usuario.', 'error');
}

if ($action === 'cambiar_password') {
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($email === '') {
        redirectUsuarios('No se pudo cambiar la contrasena: falta el email del usuario.', 'error');
    }

    if ($password === '') {
        redirectUsuarios('No se pudo cambiar la contrasena: falta la nueva contrasena.', 'error');
    }

    if ($password !== $passwordConfirm) {
        redirectUsuarios('No se pudo cambiar la contrasena: las contrasenas no coinciden.', 'error');
    }

    if (strlen($password) < 8) {
        redirectUsuarios('No se pudo cambiar la contrasena: la contrasena debe tener al menos 8 caracteres.', 'error');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        redirectUsuarios('No se pudo cambiar la contrasena.', 'error');
    }

    $sqlPassword = "UPDATE cmf_comerciales_app_usuarios SET clave = ? WHERE email = ?";
    $stmtPassword = odbc_prepare($conn, $sqlPassword);
    $ok = $stmtPassword && odbc_execute($stmtPassword, [$hash, $email]);

    if ($ok) {
        redirectUsuarios('Contrasena actualizada correctamente.');
    }

    redirectUsuarios('Error al cambiar la contrasena.', 'error');
}

if ($email === '') {
    redirectUsuarios('No se pudo guardar: falta el email del usuario.', 'error');
}

$sql = "UPDATE cmf_comerciales_app_usuarios
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
    if (isset($_SESSION['email']) && strcasecmp((string)$_SESSION['email'], $email) === 0) {
        $_SESSION['tipo_plan'] = $tipo_plan;
        $_SESSION['perm_productos'] = $perm_productos;
        $_SESSION['perm_estadisticas'] = $perm_estadisticas;
        $_SESSION['perm_planificador'] = $perm_planificador;
        $_SESSION['activo'] = (string)$activo;
    }
    redirectUsuarios('Usuario guardado correctamente.');
}

redirectUsuarios('Error al actualizar el usuario.', 'error');
