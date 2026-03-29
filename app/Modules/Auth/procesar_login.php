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

$sql = "SELECT * FROM cmf_vendedores_user WHERE email = ?";
$stmt = odbc_prepare($conn, $sql);
$execResult = odbc_execute($stmt, array($email));

if (!$execResult) {
    die('Error al ejecutar la consulta: ' . odbc_errormsg($conn));
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
        session_start();
        $_SESSION['email'] = isset($row['email']) ? (string)$row['email'] : $email;
        $_SESSION['nombre'] = isset($row['nombre']) ? (string)$row['nombre'] : '';
        $_SESSION['codigo'] = isset($row['cod_vendedor']) ? (string)$row['cod_vendedor'] : '';
        $_SESSION['es_admin'] = isset($row['es_admin']) && (int)$row['es_admin'] === 1;
        $_SESSION['rol'] = isset($row['rol']) ? (string)$row['rol'] : '';
        $_SESSION['activo'] = isset($row['activo']) ? (string)$row['activo'] : '1';
        $_SESSION['tipo_plan'] = isset($row['tipo_plan']) ? (string)$row['tipo_plan'] : 'free';
        $_SESSION['perm_productos'] = isset($row['perm_productos']) ? (int)$row['perm_productos'] : 0;
        $_SESSION['perm_estadisticas'] = isset($row['perm_estadisticas']) ? (int)$row['perm_estadisticas'] : 0;
        $_SESSION['perm_planificador'] = isset($row['perm_planificador']) ? (int)$row['perm_planificador'] : 0;
        $_SESSION['perm_costes'] = isset($row['perm_costes']) ? (int)$row['perm_costes'] : 0;
        $_SESSION['perm_comisiones'] = isset($row['perm_comisiones']) ? (int)$row['perm_comisiones'] : 0;

        header('Location: index.php');
        exit();
    }

    echo 'Contrasea incorrecta.';
} else {
    echo 'Correo electrnico no encontrado.';
}

odbc_close($conn);
?>
