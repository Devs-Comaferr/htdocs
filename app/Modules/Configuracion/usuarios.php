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

$pageTitle = 'Configuracion - Usuarios';

$sql = "SELECT email, nombre, cod_vendedor, rol, tipo_plan,
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

$ordenRoles = ['admin', 'comercial', 'compras', 'administracion'];
$titulosRoles = [
    'admin' => 'Admin',
    'comercial' => 'Comercial',
    'compras' => 'Compras',
    'administracion' => 'Administracion',
];
$usuariosPorRol = [
    'admin' => [],
    'comercial' => [],
    'compras' => [],
    'administracion' => [],
];

foreach ($usuarios as $usuario) {
    $rolClave = strtolower(trim((string)($usuario['rol'] ?? '')));
    if (!array_key_exists($rolClave, $usuariosPorRol)) {
        $rolClave = 'comercial';
    }
    $usuariosPorRol[$rolClave][] = $usuario;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
  <style>
    :root {
      --bg: #eef2f6;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --line: #d8e0ea;
      --primary: #1570ef;
      --primary-dark: #0f5cc0;
      --danger: #dc3545;
      --danger-dark: #b42318;
      --success: #1f9d55;
      --violet: #6f42c1;
      --shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
      color: var(--text);
    }
    .container {
      padding: 84px 16px 36px 16px;
      max-width: 1380px;
      margin: 0 auto;
    }
    .flash {
      padding: 14px 16px;
      border-radius: 10px;
      margin-bottom: 18px;
      text-align: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.08);
    }
    .flash.ok {
      background: #d4edda;
      color: #155724;
    }
    .flash.error {
      background: #f8d7da;
      color: #721c24;
    }
    .section-header {
      margin-bottom: 18px;
    }
    .section-header h1,
    .section-header h2 {
      margin: 0 0 6px 0;
    }
    .section-header p {
      margin: 0;
      color: var(--muted);
    }
    .users-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 320px));
      gap: 16px;
      margin-bottom: 28px;
      align-items: stretch;
      justify-content: start;
    }
    .role-group {
      margin-bottom: 30px;
    }
    .role-group-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--line);
    }
    .role-group-header h2 {
      margin: 0;
      font-size: 22px;
    }
    .role-count {
      color: var(--muted);
      font-size: 14px;
      white-space: nowrap;
    }
    .user-card,
    .create-card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 18px;
      box-shadow: var(--shadow);
    }
    .user-card {
      display: flex;
      flex-direction: column;
      min-height: 100%;
    }
    .user-top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 14px;
    }
    .user-top-right {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }
    .vendor-code {
      min-width: 40px;
      height: 40px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0 10px;
      border-radius: 999px;
      background: #eef4ff;
      color: var(--primary-dark);
      font-size: 16px;
      font-weight: bold;
      line-height: 1;
    }
    .user-top h3 {
      margin: 0 0 4px 0;
      font-size: 20px;
    }
    .user-name-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 4px;
    }
    .status-icon {
      font-size: 18px;
      line-height: 1;
    }
    .status-icon.off {
      color: #dc3545;
    }
    .plan-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: bold;
    }
    .plan-chip.premium {
      background: #fff3cd;
      color: #8a6116;
    }
    .status-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 9px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: bold;
      white-space: nowrap;
      background: #fee2e2;
      color: #991b1b;
    }
    .user-email {
      color: var(--muted);
      word-break: break-word;
      font-size: 14px;
    }
    .user-meta {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px 14px;
      margin-bottom: 14px;
    }
    .meta-item {
      background: #f8fafc;
      border: 1px solid #e8edf3;
      border-radius: 10px;
      padding: 10px 12px;
    }
    .meta-item label {
      display: block;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--muted);
      margin-bottom: 4px;
    }
    .meta-item strong {
      font-size: 15px;
    }
    .permissions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 16px;
    }
    .perm-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      border-radius: 999px;
      background: #eef4ff;
      color: #37517e;
      font-size: 14px;
    }
    .perm-pill.off {
      background: #f3f4f6;
      color: #6b7280;
    }
    .card-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: auto;
      justify-content: flex-end;
    }
    .btn-icon,
    .btn-submit,
    .btn-cancel {
      border: none;
      border-radius: 10px;
      padding: 10px 14px;
      color: #fff;
      cursor: pointer;
      font-weight: bold;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
    }
    .btn-icon { min-width: 48px; }
    .btn-edit {
      background: #d1fae5;
      color: #047857;
    }
    .btn-edit:hover {
      background: #a7f3d0;
    }
    .btn-password {
      background: #fef3c7;
      color: #b45309;
    }
    .btn-password:hover {
      background: #fde68a;
    }
    .btn-delete {
      background: #fee2e2;
      color: #b91c1c;
    }
    .btn-delete:hover {
      background: #fecaca;
    }
    .section-separator {
      margin: 30px 0 18px 0;
      padding-top: 18px;
      border-top: 2px dashed #cfd8dc;
    }
    .create-card .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
      margin-bottom: 12px;
    }
    .field { margin-bottom: 10px; font-size: 14px; }
    .field label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
    }
    .field input,
    .field select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #cfd8dc;
      border-radius: 10px;
      background: #fff;
    }
    .switch-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px 16px;
      margin-bottom: 14px;
    }
    .switch-group {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      background: #f8fafc;
      border: 1px solid #e8edf3;
      border-radius: 10px;
    }
    .btn-submit {
      background: var(--success);
      width: 100%;
    }
    .btn-submit:hover {
      background: #187a43;
    }
    .modal-backdrop-custom {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.52);
      z-index: 1100;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .modal-backdrop-custom.is-open {
      display: flex;
    }
    .modal-card {
      width: 100%;
      max-width: 560px;
      max-height: calc(100vh - 32px);
      overflow: auto;
      background: #fff;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.18);
    }
    .modal-card h3 {
      margin: 0 0 8px 0;
    }
    .modal-card p {
      margin: 0 0 16px 0;
      color: var(--muted);
      font-size: 14px;
    }
    .modal-actions {
      display: flex;
      gap: 10px;
      margin-top: 12px;
    }
    .modal-actions button {
      flex: 1;
    }
    .btn-cancel {
      background: #6c757d;
    }
    .btn-cancel:hover {
      background: #59636c;
    }
  </style>
</head>
<body>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

  <div class="container">
    <?php if (isset($_SESSION['mensaje_ok'])): ?>
      <?php $mensajeOk = (string)$_SESSION['mensaje_ok']; ?>
      <?php $mensajeTipo = (string)($_SESSION['mensaje_tipo'] ?? 'ok'); ?>
      <div class="flash <?= $mensajeTipo === 'error' ? 'error' : 'ok' ?>">
        <i class="fas <?= $mensajeTipo === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
        <?= htmlspecialchars($mensajeOk, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php unset($_SESSION['mensaje_ok'], $_SESSION['mensaje_tipo']); ?>
    <?php endif; ?>

    <div class="section-header">
      <h1>Usuarios</h1>
      <p>Gestion centralizada de accesos, permisos y credenciales.</p>
    </div>

    <?php foreach ($ordenRoles as $rolGrupo): ?>
      <?php if (empty($usuariosPorRol[$rolGrupo])) { continue; } ?>
      <section class="role-group">
        <div class="role-group-header">
          <h2><?= htmlspecialchars($titulosRoles[$rolGrupo], ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="role-count"><?= count($usuariosPorRol[$rolGrupo]) ?> usuario(s)</div>
        </div>
        <div class="users-grid">
          <?php foreach ($usuariosPorRol[$rolGrupo] as $u): ?>
            <?php
              $emailUsuario = (string)($u['email'] ?? '');
              $nombreUsuario = (string)($u['nombre'] ?? '');
              $rolUsuario = (string)($u['rol'] ?? '');
              $codVendedorUsuario = (string)($u['cod_vendedor'] ?? '');
              $tipoPlanUsuario = (string)($u['tipo_plan'] ?? 'free');
              $activoUsuario = !empty($u['activo']);
              $permProductos = !empty($u['perm_productos']);
              $permEstadisticas = !empty($u['perm_estadisticas']);
              $permPlanificador = !empty($u['perm_planificador']);
            ?>
            <article
              class="user-card"
              data-user-email="<?= htmlspecialchars($emailUsuario, ENT_QUOTES, 'UTF-8') ?>"
              data-user-nombre="<?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?>"
              data-user-rol="<?= htmlspecialchars($rolUsuario, ENT_QUOTES, 'UTF-8') ?>"
              data-user-cod-vendedor="<?= htmlspecialchars($codVendedorUsuario, ENT_QUOTES, 'UTF-8') ?>"
              data-user-tipo-plan="<?= htmlspecialchars($tipoPlanUsuario, ENT_QUOTES, 'UTF-8') ?>"
              data-user-activo="<?= $activoUsuario ? '1' : '0' ?>"
              data-user-perm-productos="<?= $permProductos ? '1' : '0' ?>"
              data-user-perm-estadisticas="<?= $permEstadisticas ? '1' : '0' ?>"
              data-user-perm-planificador="<?= $permPlanificador ? '1' : '0' ?>"
            >
              <div class="user-top">
                <div>
                  <div class="user-name-row">
                    <?php if (!$activoUsuario): ?>
                      <i class="fas fa-circle-xmark status-icon off"></i>
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if ($tipoPlanUsuario === 'premium'): ?>
                      <span class="plan-chip premium">
                        <i class="fas fa-crown"></i>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="user-email"><?= htmlspecialchars($emailUsuario, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if ($codVendedorUsuario !== ''): ?>
                  <div class="user-top-right">
                    <span class="vendor-code"><?= htmlspecialchars($codVendedorUsuario, ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="user-meta">
              </div>

              <div class="permissions">
                <?php if ($permProductos): ?>
                  <span class="perm-pill" title="Productos" aria-label="Productos"><i class="fa fa-cubes" title="Productos" aria-hidden="true"></i></span>
                <?php endif; ?>
                <?php if ($permEstadisticas): ?>
                  <span class="perm-pill" title="Estadisticas" aria-label="Estadisticas"><i class="fa fa-chart-line" title="Estadisticas" aria-hidden="true"></i></span>
                <?php endif; ?>
                <?php if ($permPlanificador): ?>
                  <span class="perm-pill" title="Planificador" aria-label="Planificador"><i class="fas fa-calendar-alt" title="Planificador" aria-hidden="true"></i></span>
                <?php endif; ?>
              </div>
              <div class="card-actions">
                <button type="button" class="btn-icon btn-edit" data-edit-trigger="1" title="Editar usuario" aria-label="Editar usuario">
                  <i class="fas fa-pen"></i>
                </button>
                <button type="button" class="btn-icon btn-password" data-password-trigger="1" title="Cambiar contrasena" aria-label="Cambiar contrasena">
                  <i class="fas fa-key"></i>
                </button>
                <form method="post" action="guardar_usuario.php" onsubmit="return confirm('Seguro que deseas eliminar este usuario?');">
                  <?= csrfInput() ?>
                  <input type="hidden" name="action" value="eliminar">
                  <input type="hidden" name="email" value="<?= htmlspecialchars($emailUsuario, ENT_QUOTES, 'UTF-8') ?>">
                  <button type="submit" class="btn-icon btn-delete" title="Eliminar usuario" aria-label="Eliminar usuario">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="section-separator">
      <div class="section-header">
        <h2>Nuevo usuario</h2>
        <p>Alta manual de usuarios con password segura y permisos iniciales.</p>
      </div>
    </div>

    <div class="create-card">
      <form method="post" action="guardar_usuario.php">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="crear">
        <div class="form-grid">
          <div class="field">
            <label for="nombre_nuevo">Nombre</label>
            <input type="text" id="nombre_nuevo" name="nombre" required>
          </div>
          <div class="field">
            <label for="email_nuevo">Email</label>
            <input type="email" id="email_nuevo" name="email" required>
          </div>
          <div class="field">
            <label for="cod_vendedor_nuevo">Cod. vendedor</label>
            <input type="number" id="cod_vendedor_nuevo" name="cod_vendedor" min="1">
          </div>
          <div class="field">
            <label for="rol_nuevo">Rol</label>
            <select id="rol_nuevo" name="rol">
              <option value="comercial">Comercial</option>
              <option value="admin">Admin</option>
              <option value="compras">Compras</option>
              <option value="administracion">Administracion</option>
            </select>
          </div>
          <div class="field">
            <label for="tipo_plan_nuevo">Plan</label>
            <select id="tipo_plan_nuevo" name="tipo_plan">
              <option value="free">Free</option>
              <option value="premium">Premium</option>
            </select>
          </div>
          <div class="field">
            <label for="password_nuevo">Contrasena</label>
            <input type="password" id="password_nuevo" name="password" minlength="8" required>
          </div>
          <div class="field">
            <label for="password_confirm_nuevo">Confirmar contrasena</label>
            <input type="password" id="password_confirm_nuevo" name="password_confirm" minlength="8" required>
          </div>
        </div>

        <div class="switch-grid">
          <label class="switch-group"><input type="checkbox" name="perm_productos" value="1"><span>Productos</span></label>
          <label class="switch-group"><input type="checkbox" name="perm_estadisticas" value="1"><span>Estadisticas</span></label>
          <label class="switch-group"><input type="checkbox" name="perm_planificador" value="1"><span>Planificador</span></label>
          <label class="switch-group"><input type="checkbox" name="activo" value="1" checked><span>Activo</span></label>
        </div>

        <button type="submit" class="btn-submit"><i class="fas fa-user-plus"></i> Crear usuario</button>
      </form>
    </div>
  </div>

  <div class="modal-backdrop-custom" id="editUserModal">
    <div class="modal-card">
      <h3>Editar usuario</h3>
      <p id="editUserModalText"></p>
      <form method="post" action="guardar_usuario.php">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="actualizar">
        <input type="hidden" name="email" id="edit_email" value="">

        <div class="form-grid">
          <div class="field">
            <label for="edit_nombre">Nombre</label>
            <input type="text" id="edit_nombre" value="" disabled>
          </div>
          <div class="field">
            <label for="edit_email_visible">Email</label>
            <input type="email" id="edit_email_visible" value="" disabled>
          </div>
          <div class="field">
            <label for="edit_rol">Rol</label>
            <input type="text" id="edit_rol" value="" disabled>
          </div>
          <div class="field">
            <label for="edit_cod_vendedor">Cod. vendedor</label>
            <input type="text" id="edit_cod_vendedor" value="" disabled>
          </div>
          <div class="field">
            <label for="edit_tipo_plan">Plan</label>
            <select id="edit_tipo_plan" name="tipo_plan">
              <option value="free">Free</option>
              <option value="premium">Premium</option>
            </select>
          </div>
        </div>

        <div class="switch-grid">
          <label class="switch-group"><input type="checkbox" id="edit_perm_productos" name="perm_productos" value="1"><span>Productos</span></label>
          <label class="switch-group"><input type="checkbox" id="edit_perm_estadisticas" name="perm_estadisticas" value="1"><span>Estadisticas</span></label>
          <label class="switch-group"><input type="checkbox" id="edit_perm_planificador" name="perm_planificador" value="1"><span>Planificador</span></label>
          <label class="switch-group"><input type="checkbox" id="edit_activo" name="activo" value="1"><span>Activo</span></label>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-cancel" id="editUserModalCancel">Cancelar</button>
          <button type="submit" class="btn-submit">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop-custom" id="passwordModal">
    <div class="modal-card">
      <h3>Cambiar contrasena</h3>
      <p id="passwordModalText"></p>
      <form method="post" action="guardar_usuario.php">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="cambiar_password">
        <input type="hidden" name="email" id="passwordModalEmail" value="">
        <div class="field">
          <label for="password_modal">Nueva contrasena</label>
          <input type="password" id="password_modal" name="password" minlength="8" required>
        </div>
        <div class="field">
          <label for="password_confirm_modal">Confirmar contrasena</label>
          <input type="password" id="password_confirm_modal" name="password_confirm" minlength="8" required>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" id="passwordModalCancel">Cancelar</button>
          <button type="submit" class="btn-password btn-submit">Guardar password</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var editModal = document.getElementById('editUserModal');
      var editModalText = document.getElementById('editUserModalText');
      var editEmail = document.getElementById('edit_email');
      var editNombre = document.getElementById('edit_nombre');
      var editEmailVisible = document.getElementById('edit_email_visible');
      var editRol = document.getElementById('edit_rol');
      var editCodVendedor = document.getElementById('edit_cod_vendedor');
      var editTipoPlan = document.getElementById('edit_tipo_plan');
      var editPermProductos = document.getElementById('edit_perm_productos');
      var editPermEstadisticas = document.getElementById('edit_perm_estadisticas');
      var editPermPlanificador = document.getElementById('edit_perm_planificador');
      var editActivo = document.getElementById('edit_activo');
      var editCancel = document.getElementById('editUserModalCancel');

      var passwordModal = document.getElementById('passwordModal');
      var passwordModalText = document.getElementById('passwordModalText');
      var passwordModalEmail = document.getElementById('passwordModalEmail');
      var passwordInput = document.getElementById('password_modal');
      var passwordConfirmInput = document.getElementById('password_confirm_modal');
      var passwordCancel = document.getElementById('passwordModalCancel');

      function openModal(modal) {
        modal.classList.add('is-open');
      }

      function closeModal(modal) {
        modal.classList.remove('is-open');
      }

      document.querySelectorAll('[data-edit-trigger="1"]').forEach(function (button) {
        button.addEventListener('click', function () {
          var card = button.closest('.user-card');
          if (!card) {
            return;
          }

          var nombre = card.getAttribute('data-user-nombre') || '';
          var email = card.getAttribute('data-user-email') || '';
          var rol = card.getAttribute('data-user-rol') || '';
          var codVendedor = card.getAttribute('data-user-cod-vendedor') || '';
          var tipoPlan = card.getAttribute('data-user-tipo-plan') || 'free';

          editModalText.textContent = 'Ajusta plan, permisos y estado de ' + nombre + '.';
          editEmail.value = email;
          editNombre.value = nombre;
          editEmailVisible.value = email;
          editRol.value = rol;
          editCodVendedor.value = codVendedor;
          editTipoPlan.value = tipoPlan;
          editPermProductos.checked = card.getAttribute('data-user-perm-productos') === '1';
          editPermEstadisticas.checked = card.getAttribute('data-user-perm-estadisticas') === '1';
          editPermPlanificador.checked = card.getAttribute('data-user-perm-planificador') === '1';
          editActivo.checked = card.getAttribute('data-user-activo') === '1';

          openModal(editModal);
        });
      });

      document.querySelectorAll('[data-password-trigger="1"]').forEach(function (button) {
        button.addEventListener('click', function () {
          var card = button.closest('.user-card');
          if (!card) {
            return;
          }

          var nombre = card.getAttribute('data-user-nombre') || '';
          var email = card.getAttribute('data-user-email') || '';
          passwordModalEmail.value = email;
          passwordModalText.textContent = 'Actualizar password para ' + nombre + '.';
          passwordInput.value = '';
          passwordConfirmInput.value = '';
          openModal(passwordModal);
          window.setTimeout(function () { passwordInput.focus(); }, 0);
        });
      });

      editCancel.addEventListener('click', function () {
        closeModal(editModal);
      });

      passwordCancel.addEventListener('click', function () {
        closeModal(passwordModal);
      });

      [editModal, passwordModal].forEach(function (modal) {
        modal.addEventListener('click', function (event) {
          if (event.target === modal) {
            closeModal(modal);
          }
        });
      });

      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
          return;
        }

        closeModal(editModal);
        closeModal(passwordModal);
      });
    });
  </script>

</body>
</html>
