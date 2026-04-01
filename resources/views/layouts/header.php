<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Support/header.php';
?>
<!-- Frontend policy: el stack UI global es Bootstrap 5. Las vistas activas deben apoyarse en este header y usar assets locales de /public/assets/vendor/. -->
<!-- Font Awesome local (instalado vÃ­a Composer/npm-asset) -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
<link rel="icon" href="<?= BASE_URL ?>/imagenes/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/imagenes/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/imagenes/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>/imagenes/apple-touch-icon.png">
<link rel="manifest" href="/public/manifest.json">
<meta name="theme-color" content="#000000">
<style>
  :root {
    --primary: <?= htmlspecialchars((string)($config['color_primary'] ?? '#2563eb'), ENT_QUOTES, 'UTF-8') ?>;
  }
</style>
<script>
  const BASE_URL = "<?= BASE_URL ?>";
  window.APP_BASE_URL = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
<?php if ($ui_requires_jquery): ?>
<script src="<?= BASE_URL ?>/assets/vendor/jquery/jquery.min.js"></script>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<style>
  /* HEADER fijo siempre */
  #globalHeader.header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    display: flex;
    align-items: center;
    background: #e7e7e7;
    padding: 0 20px;
    margin: 0;
    z-index: 2000;
  }

  /* Secciones a la izquierda/derecha y al centro */
  .header-left {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 15px;
    min-width: 0;
  }
  .header-right {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
  }
  .header-center {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    overflow: hidden;
  }

  /* Iconos izq/der */
  .header-left a,
  .header-right a {
    font-size: 24px;
    color: #7b7b7b;
    text-decoration: none;
  }

  .header-home-link {
    display: flex;
    align-items: center;
    gap: 15px;
    color: inherit;
    text-decoration: none;
    min-width: 0;
  }
  .header-logo {
    height: 38px;
    width: auto;
    display: block;
    object-fit: contain;
  }
  .header-brand {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
    min-width: 0;
  }
  .header-system-name {
    font-weight: 600;
    font-size: 15px;
    color: #7b7b7b;
    margin: 0;
  }
  .header-page-title {
    font-size: 13px;
    opacity: 0.75;
    color: #7b7b7b;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Input date en index */
  .header-center input[type="date"] {
    padding: 4px 6px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
  }
  #globalMobileAppbar.mobile-appbar {
    display: none;
  }

  /* MÃ³vil: en index-header, ocultar el pageTitle */
  @media (max-width: 1024px) {
    html, body {
      overflow-x: hidden;
    }
    .index-header .header-page-title {
      display: none !important;
    }
    #globalHeader.header {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      display: flex !important;
      visibility: visible !important;
      opacity: 1 !important;
      -webkit-transform: translateZ(0);
      transform: translateZ(0);
      -webkit-backface-visibility: hidden;
      backface-visibility: hidden;
      will-change: transform;
    }
    #globalMobileAppbar.mobile-appbar {
      position: fixed !important;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 1500;
      display: flex !important;
      visibility: visible !important;
      opacity: 1 !important;
      justify-content: space-around;
      align-items: center;
      gap: 4px;
      padding: 4px 4px calc(4px + env(safe-area-inset-bottom));
      background: #fff;
      border-top: 1px solid #ddd;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      -webkit-transform: translateZ(0);
      transform: translateZ(0);
      -webkit-backface-visibility: hidden;
      backface-visibility: hidden;
      will-change: transform;
    }
    #globalMobileAppbar.mobile-appbar .app-btn {
      min-width: 48px;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      color: #fff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      position: relative;
      flex: 0 0 auto;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    #globalMobileAppbar.mobile-appbar .app-btn i {
      font-size: 18px;
    }
    #globalMobileAppbar.mobile-appbar .app-btn .badge {
      position: absolute;
      top: -4px;
      right: -4px;
      background: #dc3545;
      color: #fff;
      border-radius: 999px;
      font-size: 10px;
      line-height: 1;
      padding: 3px 5px;
      min-width: 16px;
      text-align: center;
      border: 1px solid #fff;
    }
    #globalMobileAppbar.mobile-appbar .app-btn.app-productos    { background-color: #0374ff; }
    #globalMobileAppbar.mobile-appbar .app-btn.app-clientes     { background-color: #6c757d; }
    #globalMobileAppbar.mobile-appbar .app-btn.app-cerrados     { background-color: #dc3545; }
    #globalMobileAppbar.mobile-appbar .app-btn.app-abiertos     { background-color: #ffc107; }
    #globalMobileAppbar.mobile-appbar .app-btn.app-planificador { background-color: #28a745; }
    #globalMobileAppbar.mobile-appbar .app-btn.app-estadisticas { background-color: #3fb8af; }
    #globalMobileAppbar.mobile-appbar .app-btn.app-nuevo        { background-color: #2980b9; }
  }
</style>

<div id="globalHeader" class="<?= $headerClass ?>">
  <div class="header-left">
    <a href="<?= BASE_URL ?>/index.php" class="header-home-link">
      <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" class="header-logo" alt="Logo">
      <div class="header-brand">
        <div class="header-system-name"><?= htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="header-page-title"><?= htmlspecialchars(function_exists('toUTF8') ? toUTF8($pageTitleHeader) : $pageTitleHeader, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </a>
  </div>
  <div class="header-center">
    <?php
    if (isset($headerButton)) {
        echo $headerButton;
    }
    ?>
  </div>
  <div class="header-right">
    <?php if (esAdmin()): ?>
      <a href="<?= BASE_URL ?>/configuracion/index.php" title="Configuraci&oacute;n"><i class="fas fa-cog"></i></a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<div id="globalMobileAppbar" class="mobile-appbar">
  <?php if ($puedeVerProductosBar): ?>
    <a href="<?= BASE_URL ?>/productos.php" class="app-btn app-productos" title="Productos"><i class="fa fa-cubes"></i></a>
  <?php endif; ?>
  <a href="<?= BASE_URL ?>/clientes.php?cod_cliente=&nombre_comercial=&provincia=&poblacion=&order_by=ultima_fecha_venta&order_dir=desc" class="app-btn app-clientes" title="Clientes">
    <i class="fa fa-user"></i>
  </a>
  <a href="<?= BASE_URL ?>/faltas_todos.php" class="app-btn app-cerrados" title="Pedidos cerrados">
    <?php if ($badgeCerrados > 0): ?><span class="badge"><?= $badgeCerrados ?></span><?php endif; ?>
    <i class="fa fa-lock"></i>
  </a>
  <a href="<?= BASE_URL ?>/pedidos_todos.php" class="app-btn app-abiertos" title="Pedidos abiertos">
    <?php if ($badgeAbiertos > 0): ?><span class="badge"><?= $badgeAbiertos ?></span><?php endif; ?>
    <i class="fa fa-unlock"></i>
  </a>
  <?php if ($puedeVerPlanificadorBar): ?>
    <a href="<?= BASE_URL ?>/planificador_menu.php" class="app-btn app-planificador" title="Planificador">
      <?php if ($badgeSinVisita > 0): ?><span class="badge"><?= $badgeSinVisita ?></span><?php endif; ?>
      <i class="fa fa-calendar"></i>
    </a>
  <?php endif; ?>
  <?php if ($puedeVerEstadisticasBar): ?>
    <a href="<?= BASE_URL ?>/estadisticas.php" class="app-btn app-estadisticas" title="EstadÃ­sticas">
      <i class="fa fa-bar-chart"></i>
    </a>
  <?php endif; ?>
  <a href="<?= BASE_URL ?>/altaClientes/alta_cliente.php" class="app-btn app-nuevo" title="AÃ±adir cliente">
    <i class="fa fa-user-plus"></i>
  </a>
</div>

<script>
function cambiarFecha() {
  const fecha = document.getElementById('fechaSelect').value;
  window.location.href = window.location.pathname + '?fecha=' + fecha;
}

window.addEventListener('load', adaptTitleFont);
window.addEventListener('resize', adaptTitleFont);

function adaptTitleFont() {
  const title = document.querySelector('.header-page-title');
  if (!title) return;
  const container = title.parentElement;
  let fontSize = 20;
  title.style.fontSize = fontSize + 'px';
  while (title.scrollWidth > container.clientWidth && fontSize > 8) {
    fontSize--;
    title.style.fontSize = fontSize + 'px';
  }
}

function syncGlobalBars() {
  const isMobile = window.matchMedia('(max-width: 1024px)').matches;
  const appbar = document.getElementById('globalMobileAppbar');
  const header = document.getElementById('globalHeader');
  if (!appbar || !header) return;
  header.style.display = 'flex';
  if (isMobile) {
    appbar.style.display = 'flex';
  } else {
    appbar.style.display = 'none';
  }
  adjustBodyPaddingForMobileAppbar();
}

function adjustBodyPaddingForMobileAppbar() {
  const isMobile = window.matchMedia('(max-width: 1024px)').matches;
  const appbar = document.getElementById('globalMobileAppbar');
  if (!appbar) return;

  if (isMobile) {
    const appbarHeight = appbar.offsetHeight || 0;
    document.body.style.paddingBottom = appbarHeight > 0 ? (appbarHeight + 'px') : '';
  } else {
    document.body.style.paddingBottom = '';
  }
}
window.addEventListener('load', syncGlobalBars);
window.addEventListener('resize', syncGlobalBars);
window.addEventListener('orientationchange', syncGlobalBars);
</script>


