<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';

if (!esAdmin()) {
    error_log('Acceso no autorizado.');
    echo 'Error interno';
    return;
}

require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

$pageTitle = 'Configuración - Usuarios';

$sql = "SELECT email, nombre, cod_vendedor, tipo_plan,
               perm_productos, perm_estadisticas, perm_planificador,
               activo
        FROM cmf_vendedores_user
        ORDER BY nombre ASC";

$result = odbc_exec($conn, $sql);
$usuarios = [];
if ($result) {
    while ($row = odbc_fetch_array($result)) {
        $usuarios[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .container { padding: 80px 15px 30px 15px; }
    .alert-ok {
      background: #d4edda;
      color: #155724;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 15px;
      font-weight: normal;
      text-align: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.08);
    }
    .user-card {
      background: #fff;
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 15px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    .user-card h3 { margin: 0 0 10px 0; font-size: 18px; }
    .field { margin-bottom: 10px; font-size: 14px; }
    .field label { font-weight: bold; display: block; margin-bottom: 4px; }
    .switch-group {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      font-size: 14px;
    }
    button {
      width: 100%;
      padding: 8px;
      border: none;
      background: #28a745;
      color: white;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
    }
    button:hover { opacity: 0.9; }
    .table-wrapper { display: none; }

    @media (min-width: 992px) {
      .cards-wrapper { display: none; }
      .table-wrapper { display: block; }
      table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
      }
      th, td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: center;
        font-size: 14px;
      }
      th {
        background: #007BFF;
        color: #fff;
      }
      button { width: auto; }
    }
  </style>
</head>
<body>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

  <div class="container">
    <?php if (isset($_SESSION['mensaje_ok'])): ?>
      <?php $mensajeOk = (string)$_SESSION['mensaje_ok']; ?>
      <div class="alert-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensajeOk) ?></div>
      <?php unset($_SESSION['mensaje_ok']); ?>
    <?php endif; ?>

    <div class="cards-wrapper">
      <?php foreach ($usuarios as $u): ?>
        <form method="post" action="guardar_usuario.php">
          <?= csrfInput() ?>
          <div class="user-card">
            <h3><?= htmlspecialchars((string)$u['nombre']) ?></h3>
            <div class="field">
              <label>Email</label>
              <?= htmlspecialchars((string)$u['email']) ?>
              <input type="hidden" name="email" value="<?= htmlspecialchars((string)$u['email']) ?>">
            </div>

            <div class="field">
              <label>Plan</label>
              <select name="tipo_plan">
                <option value="free" <?= ($u['tipo_plan'] === 'free') ? 'selected' : '' ?>>Free</option>
                <option value="premium" <?= ($u['tipo_plan'] === 'premium') ? 'selected' : '' ?>>Premium</option>
              </select>
            </div>

            <div class="switch-group"><span>Productos</span><input type="checkbox" name="perm_productos" value="1" <?= !empty($u['perm_productos']) ? 'checked' : '' ?>></div>
            <div class="switch-group"><span>Estadsticas</span><input type="checkbox" name="perm_estadisticas" value="1" <?= !empty($u['perm_estadisticas']) ? 'checked' : '' ?>></div>
            <div class="switch-group"><span>Planificador</span><input type="checkbox" name="perm_planificador" value="1" <?= !empty($u['perm_planificador']) ? 'checked' : '' ?>></div>
            <div class="switch-group"><span>Activo</span><input type="checkbox" name="activo" value="1" <?= !empty($u['activo']) ? 'checked' : '' ?>></div>

            <button type="submit"><i class="fas fa-save"></i> Guardar</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>

    <div class="table-wrapper">
      <table>
        <tr>
          <th>Nombre</th>
          <th>Email</th>
          <th>Plan</th>
          <th>Productos</th>
          <th>Estadsticas</th>
          <th>Planificador</th>
          <th>Activo</th>
          <th>Guardar</th>
        </tr>
        <?php foreach ($usuarios as $u): ?>
          <form method="post" action="guardar_usuario.php">
            <?= csrfInput() ?>
            <tr>
              <td><?= htmlspecialchars((string)$u['nombre']) ?></td>
              <td>
                <?= htmlspecialchars((string)$u['email']) ?>
                <input type="hidden" name="email" value="<?= htmlspecialchars((string)$u['email']) ?>">
              </td>
              <td>
                <select name="tipo_plan">
                  <option value="free" <?= ($u['tipo_plan'] === 'free') ? 'selected' : '' ?>>Free</option>
                  <option value="premium" <?= ($u['tipo_plan'] === 'premium') ? 'selected' : '' ?>>Premium</option>
                </select>
              </td>
              <td><input type="checkbox" name="perm_productos" value="1" <?= !empty($u['perm_productos']) ? 'checked' : '' ?>></td>
              <td><input type="checkbox" name="perm_estadisticas" value="1" <?= !empty($u['perm_estadisticas']) ? 'checked' : '' ?>></td>
              <td><input type="checkbox" name="perm_planificador" value="1" <?= !empty($u['perm_planificador']) ? 'checked' : '' ?>></td>
              <td><input type="checkbox" name="activo" value="1" <?= !empty($u['activo']) ? 'checked' : '' ?>></td>
              <td><button type="submit">Guardar</button></td>
            </tr>
          </form>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

</body>
</html>



