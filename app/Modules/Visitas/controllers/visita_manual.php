<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

$codigo_vendedor = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
$ui_version = 'bs5';
$ui_requires_jquery = false;
$pageTitle = 'Registrar Visita';

$viewData = prepararVistaVisitaManual($_POST, $codigo_vendedor);
extract($viewData);

require_once BASE_PATH . '/app/Modules/Visitas/views/visita_manual.php';
