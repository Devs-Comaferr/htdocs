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

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';

requierePermiso('perm_planificador');

$pageTitle = 'Agenda comercial';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
</head>
<body>
<div class="container py-5" style="max-width: 760px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <h1 class="h3 mb-3">Pantalla legacy retirada</h1>
            <p class="text-muted mb-3">
                El registro antiguo de d&iacute;as no laborables ya no est&aacute; disponible.
            </p>
            <p class="mb-4">
                La planificaci&oacute;n debe basarse en <code>esDiaLaborable()</code>, festivos del calendario y bloqueos de agenda del comercial.
            </p>
            <a href="planificador_menu.php" class="btn btn-primary">Volver al planificador</a>
        </div>
    </div>
</div>
</body>
</html>
