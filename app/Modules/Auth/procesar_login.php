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

$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if ($email === '' || $password === '') {
    echo 'Credenciales incompletas.';
    exit();
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
    FROM cmf_vendedores_user
    WHERE email = ?
";
$stmt = odbc_prepare($conn, $sql);
if (!$stmt) {
    error_log('Login ODBC prepare error: ' . odbc_errormsg($conn));
    echo 'No se pudo iniciar sesión. Inténtalo de nuevo.';
    exit();
}

$execResult = odbc_execute($stmt, array($email));

if (!$execResult) {
    error_log('Login ODBC execute error: ' . odbc_errormsg($conn));
    echo 'No se pudo iniciar sesión. Inténtalo de nuevo.';
    exit();
}

if ($row = odbc_fetch_array($stmt)) {
    $claveGuardada = isset($row['clave']) ? trim((string)$row['clave']) : '';
    $passwordOk = false;

    // Compatibilidad: hash moderno o texto plano legado.
    if ($claveGuardada !== '') {
        if (password_verify($password, $claveGuardada)) {
            $passwordOk = true;
        } elseif (hash_equals($claveGuardada, $password)) {
            $passwordOk = true;
        }
    }

    if ($passwordOk) {
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

        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }

    echo 'Contraseña incorrecta.';
} else {
    echo 'Correo electrónico no encontrado.';
}
?>
