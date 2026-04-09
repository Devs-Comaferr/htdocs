<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';

if (!esAdmin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'total' => 0,
            'offset' => 0,
            'procesados' => 0,
            'insertados' => 0,
            'duplicados' => 0,
            'errores' => ['Acceso no autorizado.'],
            'done' => true,
            'next_offset' => 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Modules/Configuracion/services/ImportadorFestivosService.php';

$urlApiFestivos = defined('URL_IMPORTACION_FESTIVOS_ANDALUCIA')
    ? (string)URL_IMPORTACION_FESTIVOS_ANDALUCIA
    : 'https://datos.juntadeandalucia.es/api/v0/work-calendar/all?format=json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfValidateRequest('configuracion.importar_festivos');

    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $limite = max(1, min(500, (int)($_POST['limite'] ?? 100)));

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        importarFestivosAndaluciaDesdeApiPorLotes($urlApiFestivos, $offset, $limite),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$pageTitle = 'Configuracion - Importar festivos';
$totalInicial = 0;
$erroresIniciales = [];
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
      --bg: #f4f7fb;
      --card: rgba(255,255,255,0.96);
      --line: rgba(148,163,184,0.22);
      --text: #0f172a;
      --muted: #64748b;
      --shadow: 0 22px 50px rgba(15, 23, 42, 0.12);
      --primary: #0f766e;
      --primary-soft: #ccfbf1;
      --warn: #b45309;
      --warn-soft: #fef3c7;
      --ok: #166534;
      --ok-soft: #dcfce7;
      --danger: #b91c1c;
      --danger-soft: #fee2e2;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at top left, rgba(15, 118, 110, 0.14), transparent 26%),
        radial-gradient(circle at top right, rgba(234, 179, 8, 0.12), transparent 24%),
        linear-gradient(180deg, #f8fbfd 0%, var(--bg) 100%);
    }
    .page {
      max-width: 1080px;
      margin: 0 auto;
      padding: 88px 16px 40px;
    }
    .hero {
      margin-bottom: 22px;
    }
    .hero h1 {
      margin: 0 0 10px;
      font-size: 32px;
      line-height: 1.1;
    }
    .hero p {
      margin: 0;
      color: var(--muted);
      max-width: 70ch;
      line-height: 1.55;
    }
    .layout {
      display: grid;
      grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.85fr);
      gap: 18px;
      align-items: start;
    }
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 24px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(8px);
    }
    .main-card {
      padding: 24px;
    }
    .side-card {
      padding: 20px;
    }
    .pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: var(--primary-soft);
      color: var(--primary);
      font-size: 13px;
      font-weight: 700;
      margin-bottom: 16px;
    }
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      margin: 18px 0 20px;
    }
    .summary-box {
      padding: 16px 14px;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: #fff;
    }
    .summary-box strong {
      display: block;
      font-size: 26px;
      margin-bottom: 5px;
    }
    .summary-box span {
      color: var(--muted);
      font-size: 13px;
    }
    .progress-shell {
      padding: 16px;
      border-radius: 20px;
      background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid var(--line);
    }
    .progress-top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      margin-bottom: 10px;
    }
    .progress-label {
      font-weight: 700;
    }
    .progress-percent {
      color: var(--muted);
      font-size: 14px;
      white-space: nowrap;
    }
    .progress-bar {
      height: 16px;
      border-radius: 999px;
      background: #e5edf5;
      overflow: hidden;
    }
    .progress-value {
      width: 0%;
      height: 100%;
      border-radius: 999px;
      background: linear-gradient(90deg, #0f766e 0%, #14b8a6 100%);
      transition: width 0.25s ease;
    }
    .progress-help {
      margin-top: 10px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.5;
    }
    .actions {
      display: flex;
      gap: 12px;
      margin-top: 18px;
      flex-wrap: wrap;
    }
    .btn {
      border: none;
      border-radius: 14px;
      padding: 12px 18px;
      font-weight: 700;
      cursor: pointer;
      font-size: 14px;
      transition: transform 0.18s ease, opacity 0.18s ease;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
    .btn-primary {
      background: #0f766e;
      color: #fff;
    }
    .btn-light {
      background: #fff;
      color: var(--text);
      border: 1px solid var(--line);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .status {
      margin-top: 18px;
      padding: 14px 16px;
      border-radius: 18px;
      font-size: 14px;
      line-height: 1.5;
      display: none;
    }
    .status.show { display: block; }
    .status.info {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }
    .status.ok {
      background: var(--ok-soft);
      color: var(--ok);
      border: 1px solid #bbf7d0;
    }
    .status.error {
      background: var(--danger-soft);
      color: var(--danger);
      border: 1px solid #fecaca;
    }
    .meta-list {
      display: grid;
      gap: 12px;
    }
    .meta-item {
      padding: 14px;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: #fff;
    }
    .meta-item strong {
      display: block;
      margin-bottom: 6px;
      font-size: 14px;
    }
    .meta-item span {
      color: var(--muted);
      font-size: 13px;
      word-break: break-word;
      line-height: 1.5;
    }
    .log-card {
      margin-top: 18px;
      padding: 16px;
      border-radius: 20px;
      border: 1px solid var(--line);
      background: #fff;
    }
    .log-card h3 {
      margin: 0 0 10px;
      font-size: 16px;
    }
    .log-list {
      margin: 0;
      padding: 0;
      list-style: none;
      max-height: 320px;
      overflow: auto;
      display: grid;
      gap: 8px;
    }
    .log-list li {
      padding: 10px 12px;
      border-radius: 14px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      font-size: 13px;
      line-height: 1.45;
    }
    .initial-error {
      margin-top: 18px;
      padding: 14px 16px;
      border-radius: 18px;
      background: var(--danger-soft);
      color: var(--danger);
      border: 1px solid #fecaca;
      line-height: 1.5;
    }
    @media (max-width: 920px) {
      .layout {
        grid-template-columns: 1fr;
      }
      .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
  </style>
</head>
<body>
  <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

  <div class="page">
    <div class="hero">
      <h1>Importar festivos</h1>
      <p>Esta pantalla consulta la API oficial de la Junta de Andaluc&iacute;a por bloques para que podamos ver el avance real, detectar errores y evitar que la importaci&oacute;n termine en una p&aacute;gina en blanco.</p>
    </div>

    <div class="layout">
      <section class="card main-card">
        <span class="pill"><i class="fas fa-file-import"></i> Junta de Andaluc&iacute;a</span>
        <h2 style="margin:0 0 8px;font-size:24px;">Carga guiada de festivos</h2>
        <p style="margin:0;color:#64748b;line-height:1.6;">El sistema consultar&aacute; la API oficial, procesar&aacute; bloques consecutivos y te mostrar&aacute; cu&aacute;ntos registros se han insertado, cu&aacute;ntos ya exist&iacute;an y si aparece alg&uacute;n error durante el recorrido.</p>

        <div class="summary-grid">
          <div class="summary-box">
            <strong id="totalValue"><?= (int)$totalInicial ?></strong>
            <span>Total detectado</span>
          </div>
          <div class="summary-box">
            <strong id="processedValue">0</strong>
            <span>Procesados</span>
          </div>
          <div class="summary-box">
            <strong id="insertedValue">0</strong>
            <span>Insertados</span>
          </div>
          <div class="summary-box">
            <strong id="duplicateValue">0</strong>
            <span>Duplicados</span>
          </div>
        </div>

        <div class="progress-shell">
          <div class="progress-top">
            <div class="progress-label" id="progressLabel">Esperando inicio</div>
            <div class="progress-percent" id="progressPercent">0%</div>
          </div>
          <div class="progress-bar">
            <div class="progress-value" id="progressValue"></div>
          </div>
          <div class="progress-help" id="progressHelp">El importador trabajar&aacute; en bloques de 100 registros para mantener la respuesta controlada y visible.</div>
        </div>

        <div class="actions">
          <button type="button" class="btn btn-primary" id="startImportBtn">
            <i class="fas fa-play"></i> Iniciar importaci&oacute;n
          </button>
          <a href="index.php" class="btn btn-light"><i class="fas fa-arrow-left"></i>&nbsp;Volver a configuraci&oacute;n</a>
        </div>

        <div class="status" id="statusBox"></div>

        <?php if (!empty($erroresIniciales)): ?>
          <div class="initial-error">
            <?= htmlspecialchars(implode(' ', $erroresIniciales), ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <div class="log-card">
          <h3>Actividad</h3>
          <ul class="log-list" id="logList">
            <li>La importaci&oacute;n todav&iacute;a no ha comenzado.</li>
          </ul>
        </div>
      </section>

      <aside class="card side-card">
        <div class="meta-list">
          <div class="meta-item">
            <strong>Fuente oficial</strong>
            <span><?= htmlspecialchars($urlApiFestivos, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="meta-item">
            <strong>Tama&ntilde;o del lote</strong>
            <span>100 registros por petici&oacute;n.</span>
          </div>
          <div class="meta-item">
            <strong>Resultado esperado</strong>
            <span>La pantalla seguir&aacute; activa hasta completar el recorrido completo o mostrar el error exacto si la API o alg&uacute;n bloque fallan.</span>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <script>
    (function () {
      const startBtn = document.getElementById('startImportBtn');
      const totalValue = document.getElementById('totalValue');
      const processedValue = document.getElementById('processedValue');
      const insertedValue = document.getElementById('insertedValue');
      const duplicateValue = document.getElementById('duplicateValue');
      const progressLabel = document.getElementById('progressLabel');
      const progressPercent = document.getElementById('progressPercent');
      const progressValue = document.getElementById('progressValue');
      const progressHelp = document.getElementById('progressHelp');
      const statusBox = document.getElementById('statusBox');
      const logList = document.getElementById('logList');

      const csrfToken = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const lote = 100;
      let total = <?= (int)$totalInicial ?>;
      let offset = 0;
      let procesados = 0;
      let insertados = 0;
      let duplicados = 0;
      let ejecutando = false;

      function setStatus(type, text) {
        statusBox.className = 'status show ' + type;
        statusBox.textContent = text;
      }

      function addLog(text) {
        if (!logList) {
          return;
        }

        const empty = logList.querySelector('[data-empty-log="1"]');
        if (empty) {
          empty.remove();
        }

        const li = document.createElement('li');
        li.textContent = text;
        logList.prepend(li);
      }

      function updateProgress() {
        totalValue.textContent = String(total);
        processedValue.textContent = String(procesados);
        insertedValue.textContent = String(insertados);
        duplicateValue.textContent = String(duplicados);

        const percent = total > 0 ? Math.min(100, Math.round((procesados / total) * 100)) : 0;
        progressPercent.textContent = percent + '%';
        progressValue.style.width = percent + '%';
        progressLabel.textContent = ejecutando
          ? 'Procesando bloque ' + (Math.floor(offset / lote) + 1)
          : (procesados > 0 ? 'Importaci\u00f3n finalizada' : 'Esperando inicio');
        progressHelp.textContent = total > 0
          ? ('Recorrido actual: ' + procesados + ' de ' + total + ' registros.')
          : 'No se han detectado registros para importar.';
      }

      async function procesarSiguienteBloque() {
        const body = new URLSearchParams();
        body.set('_csrf_token', csrfToken);
        body.set('offset', String(offset));
        body.set('limite', String(lote));

        const response = await fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString()
        });

        const data = await response.json();
        if (!response.ok || !data.ok) {
          throw new Error((data.errores && data.errores[0]) ? data.errores[0] : 'Error durante la importaci\u00f3n.');
        }

        total = Number(data.total || total);
        offset = Number(data.next_offset || 0);
        procesados += Number(data.procesados || 0);
        insertados += Number(data.insertados || 0);
        duplicados += Number(data.duplicados || 0);

        updateProgress();

        addLog(
          'Bloque completado. Procesados: ' + data.procesados +
          ', insertados: ' + data.insertados +
          ', duplicados: ' + data.duplicados +
          (data.errores && data.errores.length ? ', errores: ' + data.errores.length : '')
        );

        if (Array.isArray(data.errores) && data.errores.length) {
          data.errores.slice(0, 5).forEach(function (errorText) {
            addLog('Error: ' + errorText);
          });
        }

        if (data.done) {
          ejecutando = false;
          startBtn.disabled = false;
          updateProgress();
          setStatus('ok', 'Importaci\u00f3n finalizada. Insertados: ' + insertados + '. Duplicados: ' + duplicados + '.');
          return;
        }

        await procesarSiguienteBloque();
      }

      if (startBtn) {
        startBtn.addEventListener('click', async function () {
          if (ejecutando) {
            return;
          }

          ejecutando = true;
          offset = 0;
          procesados = 0;
          insertados = 0;
          duplicados = 0;
          logList.innerHTML = '';
          startBtn.disabled = true;
          setStatus('info', 'Importaci\u00f3n en curso. No cierres esta pantalla hasta que termine.');
          addLog('Inicio de importaci\u00f3n.');
          updateProgress();

          try {
            await procesarSiguienteBloque();
          } catch (error) {
            ejecutando = false;
            startBtn.disabled = false;
            updateProgress();
            setStatus('error', error && error.message ? error.message : 'Se ha producido un error inesperado.');
            addLog('Fallo de importaci\u00f3n: ' + (error && error.message ? error.message : 'Error inesperado.'));
          }
        });
      }

      updateProgress();
    })();
  </script>
</body>
</html>
