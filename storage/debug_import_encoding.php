<?php
require_once __DIR__ . '/bootstrap/init.php';
require_once BASE_PATH . '/app/Modules/Configuracion/services/ImportadorFestivosService.php';
$raw = file_get_contents(BASE_PATH . '/storage/imports/festivos_andalucia.json');
$data = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
$first = $data[0] ?? null;
echo 'raw_has='. (strpos($raw, 'ANDALUC') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'decoded=' . ($first['description'] ?? '') . PHP_EOL;
echo 'helper=' . importadorFestivosTextoUtf8($first['description'] ?? '') . PHP_EOL;
echo 'norm=' . importadorFestivosNormalizarTexto($first['description'] ?? '') . PHP_EOL;
