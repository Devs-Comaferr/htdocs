<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';

$conn = db();

$codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
$descripcion = isset($_GET['descripcion']) ? trim($_GET['descripcion']) : '';

$params = [];
$conditions = [];

if ($codigo !== '') {
    $codeLen = strlen($codigo);
    $conditions[] = "(SUBSTRING(a.cod_articulo, 1, $codeLen) = ? OR mca.codigo = ?)";
    $params[] = $codigo;
    $params[] = $codigo;
}

if ($descripcion !== '') {
    $palabras = preg_split('/\s+/', $descripcion, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($palabras as $palabra) {
        $conditions[] = "ad.descripcion LIKE ?";
        $params[] = '%' . $palabra . '%';
    }
}

$sql = "SELECT DISTINCT wm.cod_marca, wm.descripcion 
FROM [integral].[dbo].[articulos] a
        LEFT JOIN [integral].[dbo].[articulo_descripcion] ad 
            ON a.cod_articulo = ad.cod_articulo AND ad.cod_idioma = 'ES'
        LEFT JOIN [integral].[dbo].[multicodigo_articulo] mca 
            ON a.cod_articulo = mca.cod_articulo
        LEFT JOIN [integral].[dbo].[web_marcas] wm 
            ON a.cod_marca_web = wm.cod_marca";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = odbc_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode([]);
    exit;
}
$exec = odbc_execute($stmt, $params);
if (!$exec) {
    echo json_encode([]);
    exit;
}

$brands = [];
while ($row = odbc_fetch_array($stmt)) {
    if (!isset($row['descripcion']) || trim($row['descripcion']) === '') {
        $row['descripcion'] = 'Sin marca rellenada';
    }
    $brands[] = $row;
}

$uniqueBrands = [];
$hasSinMarca = false;
foreach ($brands as $brand) {
    if (trim($brand['descripcion']) === 'Sin marca rellenada') {
        if (!$hasSinMarca) {
            $uniqueBrands[] = $brand;
            $hasSinMarca = true;
        }
    } else {
        $uniqueBrands[] = $brand;
    }
}
$brands = $uniqueBrands;

header('Content-Type: application/json');
echo json_encode($brands);
