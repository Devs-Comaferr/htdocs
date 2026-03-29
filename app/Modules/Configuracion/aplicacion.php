<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';

$codSesion = trim((string)($_SESSION['codigo'] ?? ''));
if (!esAdmin() && $codSesion !== '') {
    error_log('Acceso no autorizado.');
    echo 'Error interno';
    return;
}

require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

$pageTitle = 'Configuracion - Aplicacion';
$mensajeOk = '';

/**
 * Inserta o actualiza una clave de configuracion.
 */
function guardarConfigApp($conn, string $clave, string $valor): bool
{
    $sqlExiste = "SELECT COUNT(*) AS total FROM cmf_configuracion_app WHERE clave = ?";
    $stmtExiste = @odbc_prepare($conn, $sqlExiste);
    if (!$stmtExiste || !@odbc_execute($stmtExiste, [$clave])) {
        return false;
    }

    $existe = false;
    if (@odbc_fetch_row($stmtExiste)) {
        $existe = ((int)@odbc_result($stmtExiste, 'total') > 0);
    }

    if ($existe) {
        $sqlUpdate = "UPDATE cmf_configuracion_app
                      SET valor = ?, fecha_modificacion = GETDATE()
                      WHERE clave = ?";
        $stmtUpdate = @odbc_prepare($conn, $sqlUpdate);
        return $stmtUpdate && @odbc_execute($stmtUpdate, [$valor, $clave]);
    }

    $sqlInsert = "INSERT INTO cmf_configuracion_app (clave, valor, fecha_modificacion)
                  VALUES (?, ?, GETDATE())";
    $stmtInsert = @odbc_prepare($conn, $sqlInsert);
    return $stmtInsert && @odbc_execute($stmtInsert, [$clave, $valor]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreSistema = trim((string)($_POST['nombre_sistema'] ?? ''));
    $colorPrimary = trim((string)($_POST['color_primary'] ?? ''));
    $logoPath = trim((string)($_POST['logo_path'] ?? ''));

    if ($nombreSistema === '') {
        $nombreSistema = 'COMAFERR';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorPrimary)) {
        $colorPrimary = '#2563eb';
    }
    if ($logoPath === '') {
        $logoPath = '/imagenes/logo.png';
    }

    $okNombre = guardarConfigApp($conn, 'nombre_sistema', $nombreSistema);
    $okColor = guardarConfigApp($conn, 'color_primary', $colorPrimary);
    $okLogo = guardarConfigApp($conn, 'logo_path', $logoPath);

    if ($okNombre && $okColor && $okLogo) {
        $mensajeOk = 'Configuracion guardada correctamente.';
    }
}

$config = obtenerConfiguracionApp($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f4f4f4;
    }
    .container {
      max-width: 760px;
      margin: 0 auto;
      padding: 80px 15px 30px;
    }
    .card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      padding: 20px;
    }
    .card h2 {
      margin: 0 0 16px;
      font-size: 20px;
      color: #333;
    }
    .alert-ok {
      background: #d4edda;
      color: #155724;
      border-radius: 8px;
      padding: 10px 12px;
      margin-bottom: 14px;
      text-align: center;
    }
    .field {
      margin-bottom: 14px;
    }
    .field label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
      color: #444;
    }
    .field input[type="text"],
    .field input[type="color"] {
      width: 100%;
      box-sizing: border-box;
      border: 1px solid #d5d5d5;
      border-radius: 8px;
      padding: 9px 10px;
      font-size: 14px;
      background: #fff;
    }
    .field input[type="color"] {
      height: 42px;
      padding: 4px;
      cursor: pointer;
    }
    .actions {
      margin-top: 8px;
    }
    .btn-save {
      border: none;
      border-radius: 8px;
      background: #2563eb;
      color: #fff;
      padding: 10px 16px;
      font-weight: bold;
      cursor: pointer;
    }
    .btn-save:hover {
      opacity: 0.92;
    }
  </style>
</head>
<body>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

  <div class="container">
    <div class="card">
      <h2><i class="fas fa-sliders-h"></i> Configuracion de aplicacion</h2>

      <?php if ($mensajeOk !== ''): ?>
        <div class="alert-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensajeOk, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="field">
          <label for="nombre_sistema">Nombre del sistema</label>
          <input type="text" id="nombre_sistema" name="nombre_sistema" value="<?= htmlspecialchars((string)$config['nombre_sistema'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <label for="color_primary">Color principal</label>
          <input type="color" id="color_primary" name="color_primary" value="<?= htmlspecialchars((string)$config['color_primary'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
          <label for="logo_path">Ruta logo</label>
          <input type="text" id="logo_path" name="logo_path" value="<?= htmlspecialchars((string)$config['logo_path'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="actions">
          <button class="btn-save" type="submit"><i class="fas fa-save"></i> Guardar</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>



