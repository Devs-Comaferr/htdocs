<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Support/HeaderBadgesSupport.php';

$ui_version = 'bs5';
$ui_requires_jquery = isset($ui_requires_jquery) ? (bool)$ui_requires_jquery : false;

if (!defined('MOBILE_APPBAR_RENDERED')) {
    define('MOBILE_APPBAR_RENDERED', true);
}

// Asume que la sesión ya está iniciada y que $pageTitle existe (y si es index, $fechaConsulta).
$isIndex = (basename($_SERVER['PHP_SELF']) === 'index.php');

$conn = $conn ?? (function_exists('db') ? db() : null);

$config = function_exists('obtenerConfiguracionApp')
    ? obtenerConfiguracionApp($conn)
    : [
        'nombre_sistema' => 'COMAFERR',
        'color_primary' => '#2563eb',
        'logo_path' => '/imagenes/logo.png',
    ];

$systemName = (string)($config['nombre_sistema'] ?? 'COMAFERR');
$logoPath = trim((string)($config['logo_path'] ?? (BASE_URL . '/imagenes/logo.png')));
if ($logoPath === '') {
    $logoPath = BASE_URL . '/imagenes/logo.png';
} elseif (strpos($logoPath, '/imagenes/') === 0 || strpos($logoPath, '/assets/') === 0) {
    $logoPath = BASE_URL . $logoPath;
}

$pageTitleHeader = isset($pageTitle) ? (string)$pageTitle : '';
$headerClass = 'header';
if ($isIndex) {
    $headerClass .= ' index-header';
}

$sessionEmail = $_SESSION['email'] ?? '';
$esAdminBar = (function_exists('esAdmin') && esAdmin()) || (isset($_SESSION['es_admin']) && (int)$_SESSION['es_admin'] === 1);
$esPremiumBar = isset($_SESSION['tipo_plan']) && $_SESSION['tipo_plan'] === 'premium';
$puedeVerProductosBar = $esAdminBar || (isset($_SESSION['perm_productos']) && (int)$_SESSION['perm_productos'] === 1);
$puedeVerPlanificadorBar = $esAdminBar || ($esPremiumBar && (isset($_SESSION['perm_planificador']) && (int)$_SESSION['perm_planificador'] === 1));
$puedeVerEstadisticasBar = $esAdminBar || (isset($_SESSION['perm_estadisticas']) && (int)$_SESSION['perm_estadisticas'] === 1);
$codigoSesionBar = (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '') ? (int)$_SESSION['codigo'] : null;
$badgeCerrados = isset($count_pedidos_cerrados_70) ? (int)$count_pedidos_cerrados_70 : null;
$badgeAbiertos = isset($count_pedidos_abiertos) ? (int)$count_pedidos_abiertos : null;
$badgeSinVisita = isset($count_pedidos_sin_visita) ? (int)$count_pedidos_sin_visita : null;

if ($badgeCerrados === null || $badgeAbiertos === null || $badgeSinVisita === null) {
    $headerBadges = obtenerHeaderBadges($conn, $codigoSesionBar, $badgeCerrados, $badgeAbiertos, $badgeSinVisita);
    $badgeCerrados = $headerBadges['badgeCerrados'];
    $badgeAbiertos = $headerBadges['badgeAbiertos'];
    $badgeSinVisita = $headerBadges['badgeSinVisita'];
}
