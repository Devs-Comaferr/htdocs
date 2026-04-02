<?php
require_once dirname(__DIR__) . '/bootstrap/init.php';
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = BASE_URL . '/mostrar_calendario.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;
