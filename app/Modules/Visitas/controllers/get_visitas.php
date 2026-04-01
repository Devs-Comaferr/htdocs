<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasValidationService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasAjaxService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasQueryService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasCalendarioService.php';
require_once BASE_PATH . '/app/Modules/Visitas/services/VisitasService.php';

$ui_version = 'bs5';
$ui_requires_jquery = false;

if (!isset($_GET['fecha']) || empty($_GET['fecha'])) {
    appExitTextError('Fecha no especificada.', 400);
}

$visitasData = obtenerVisitasDiaData((string)$_GET['fecha']);
$fecha = $visitasData['fecha'];
$visitas = $visitasData['visitas'];

require_once BASE_PATH . '/app/Modules/Visitas/views/get_visitas.php';
