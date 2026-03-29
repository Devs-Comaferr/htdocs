<?php
require_once dirname(__DIR__) . '/bootstrap/init.php';

$params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$params['action'] = 'editar';

$url = 'visitas.php';
if (!empty($params)) {
    $url .= '?' . http_build_query($params);
}

header('Location: ' . $url);
exit;
