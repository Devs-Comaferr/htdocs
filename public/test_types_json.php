<?php

$json = file_get_contents(__DIR__ . '/dataset-work-calendar.json');
$data = json_decode($json, true);

$types = [];

foreach ($data as $item) {
    $type = trim($item['type'] ?? '');

    if ($type !== '') {
        $types[$type] = true;
    }
}

echo "Tipos encontrados:\n\n";

foreach (array_keys($types) as $type) {
    echo "- $type\n";
}