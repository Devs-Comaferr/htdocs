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

header('Content-Type: text/html; charset=utf-8');

$conn = db();

function loginRedirectError(string $code = 'invalid_credentials'): void
{
    header('Location: ' . BASE_URL . '/login.php?error=' . urlencode($code));
    exit();
}

$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if ($email === '' || $password === '') {
    loginRedirectError('invalid_credentials');
}

$sql = "
    SELECT
        cod_vendedor,
        nombre,
        email,
        clave,
        rol,
        tipo_plan,
        activo,
        perm_productos,
        perm_costes,
        perm_estadisticas,
        perm_comisiones,
        perm_planificador
    FROM cmf_comerciales_app_usuarios
    WHERE email = ?
";

$stmt = odbc_prepare($conn, $sql);
if (!$stmt) {
    error_log('Login ODBC prepare error: ' . odbc_errormsg($conn));
    loginRedirectError('login_unavailable');
}

$execResult = odbc_execute($stmt, [$email]);
if (!$execResult) {
    error_log('Login ODBC execute error: ' . odbc_errormsg($conn));
    loginRedirectError('login_unavailable');
}

$row = odbc_fetch_array($stmt);
if (!$row) {
    loginRedirectError('invalid_credentials');
}

$claveGuardada = isset($row['clave']) ? trim((string)$row['clave']) : '';
$passwordOk = false;
$rehashPendiente = false;

if ($claveGuardada !== '' && password_verify($password, $claveGuardada)) {
    $passwordOk = true;
    $rehashPendiente = password_needs_rehash($claveGuardada, PASSWORD_DEFAULT);
}

if (!$passwordOk) {
    loginRedirectError('invalid_credentials');
}

if ($rehashPendiente) {
    $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
    if (is_string($nuevoHash) && $nuevoHash !== '') {
        $sqlUpdateClave = "UPDATE cmf_comerciales_app_usuarios SET clave = ? WHERE email = ?";
        $stmtUpdateClave = odbc_prepare($conn, $sqlUpdateClave);
        if ($stmtUpdateClave && odbc_execute($stmtUpdateClave, [$nuevoHash, $email])) {
            error_log('Login password rehash aplicado para: ' . $email . ' [needs_rehash]');
        } else {
            error_log('Login password rehash fallo para: ' . $email . ' - ' . odbc_errormsg($conn));
        }
    }
}

$_SESSION['email'] = isset($row['email']) ? (string)$row['email'] : $email;
$_SESSION['nombre'] = isset($row['nombre']) ? (string)$row['nombre'] : '';
$_SESSION['codigo'] = isset($row['cod_vendedor']) ? (string)$row['cod_vendedor'] : '';
$_SESSION['rol'] = isset($row['rol']) ? (string)$row['rol'] : '';
$_SESSION['activo'] = isset($row['activo']) ? (string)$row['activo'] : '1';
$_SESSION['tipo_plan'] = isset($row['tipo_plan']) ? (string)$row['tipo_plan'] : 'free';
$_SESSION['perm_productos'] = isset($row['perm_productos']) ? (int)$row['perm_productos'] : 0;
$_SESSION['perm_estadisticas'] = isset($row['perm_estadisticas']) ? (int)$row['perm_estadisticas'] : 0;
$_SESSION['perm_planificador'] = isset($row['perm_planificador']) ? (int)$row['perm_planificador'] : 0;
$_SESSION['perm_costes'] = isset($row['perm_costes']) ? (int)$row['perm_costes'] : 0;
$_SESSION['perm_comisiones'] = isset($row['perm_comisiones']) ? (int)$row['perm_comisiones'] : 0;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

header('Location: ' . BASE_URL . '/index.php');
exit();
