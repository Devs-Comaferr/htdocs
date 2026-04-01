<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');

// Incluir el archivo de funciones centralizado
require_once BASE_PATH . '/app/Support/functions.php';

// Si se recibe el parámetro 'origen', se guarda en la sesión.
if (!empty($_GET['origen'])) {
    $_SESSION['origen'] = $_GET['origen'];
}

// Verificar si el usuario ha iniciado sesión


// Verificar que se pase el parámetro `cod_cliente`
if (empty($_GET['cod_cliente'])) {
    error_log("El parámetro 'cod_cliente' es obligatorio.");
    echo 'Error interno';
    return;
}

$cod_cliente = (string) $_GET['cod_cliente'];
$cod_seccion = $_GET['cod_seccion'] ?? null;


$conn = db();

// Inicializar variables
$lineas = [];
$num_lineas = 0;
$suma_total = 0;

// Obtener filtros desde el formulario
$fecha_desde  = $_GET['fecha_desde']  ?? null;
$fecha_hasta  = $_GET['fecha_hasta']  ?? null;
$cod_articulo = $_GET['cod_articulo'] ?? null;
$descripcion  = $_GET['descripcion']  ?? null;

// Validar orden y dirección
$orden_permitido = [
    'Pedido'                     => 'Pedido',
    'Fecha_Venta'                => 'Fecha_Venta',
    'Articulo'                   => 'Articulo',
    'Descripcion'                => 'Descripcion',
    'Cantidad_Pedida'            => 'Cantidad_Pedida',
    'Cantidad_Servida'           => 'Cantidad_Servida',
    'Cantidad_Restante'          => 'Cantidad_Restante',
    'Stock'                      => 'Stock',
    'Cantidad_Pendiente_Recibir' => 'Cantidad_Pendiente_Recibir',
    'Importe_Restante'           => 'Importe_Restante'
];
$orden = (isset($_GET['orden']) && array_key_exists($_GET['orden'], $orden_permitido))
         ? $_GET['orden']
         : 'Fecha_Venta';
$direccion = (isset($_GET['direccion']) && in_array($_GET['direccion'], ['ASC', 'DESC'], true))
           ? $_GET['direccion']
           : 'DESC';
$direccion_invertida = ($direccion === 'ASC') ? 'DESC' : 'ASC';


// -----------------------------------------------------------------------------
// Consulta para registros normales (hvl/hvc)
// -----------------------------------------------------------------------------
$sql_normal = "
SELECT 
    hvc.historico AS Historico,
    hvc.cod_pedido_web AS CodPedidoWeb,
    hvl.cod_venta AS Pedido,
    hvc.fecha_venta AS Fecha_Venta,
    hvl.cod_articulo AS Articulo,
    hvl.descripcion AS Descripcion,
    hvl.observacion AS Observacion,
    hvl.linea AS Linea,
    hvl.cantidad AS Cantidad_Pedida,
    ISNULL(SUM(elv.cantidad), 0) AS Cantidad_Servida,
    (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS Cantidad_Restante,
    hvl.precio AS Precio,
    (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) * hvl.precio AS Importe_Restante,
    hvl.tipo_venta AS Tipo_Venta,
    ISNULL(
        (SELECT TOP 1 s.cantidad_pendiente_recibir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = hvl.cod_articulo
        ), 0
    ) AS Cantidad_Pendiente_Recibir,
    ISNULL(
        (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = hvl.cod_articulo
        ), 0
    ) AS Stock,
    (
        SELECT TOP 1 hvcAlb.fecha_venta
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.hist_ventas_cabecera hvcAlb
            ON hvcAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = hvl.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND hvcAlb.tipo_venta = 2
        ORDER BY hvcAlb.fecha_venta DESC
    ) AS Fecha_Albaran,
    (
        SELECT TOP 1 hvcAlb.nombre_vendedor
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.hist_ventas_cabecera hvcAlb
            ON hvcAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = hvl.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND hvcAlb.tipo_venta = 2
        ORDER BY hvcAlb.fecha_venta DESC
    ) AS NombreVendedorAlbaran,
    'hvc' as tabla
FROM 
    integral.dbo.hist_ventas_linea hvl
    INNER JOIN integral.dbo.hist_ventas_cabecera hvc
        ON hvc.cod_venta = hvl.cod_venta
    LEFT JOIN integral.dbo.entrega_lineas_venta elv
        ON hvl.cod_venta = elv.cod_venta_origen
        AND hvl.linea = elv.linea_origen
WHERE
    hvc.cod_cliente = '" . addslashes($cod_cliente) . "'
    AND hvl.tipo_venta = 1
    AND hvc.tipo_venta = 1

    AND (
        (hvl.cod_articulo = 'LM' AND hvl.cantidad > 0)
        OR
        (
            NOT EXISTS (
                SELECT 1
                FROM integral.dbo.hist_ventas_linea hvl2
                INNER JOIN integral.dbo.hist_ventas_cabecera hvc2
                    ON hvl2.cod_venta = hvc2.cod_venta
                WHERE
                    hvc2.cod_cliente = hvc.cod_cliente
                    AND hvl2.cod_articulo = hvl.cod_articulo
                    AND hvc2.tipo_venta = 1
                    AND hvc2.fecha_venta > hvc.fecha_venta
            )
            AND
            hvl.cantidad > (
                SELECT ISNULL(SUM(hvlAlb.cantidad), 0)
                FROM integral.dbo.hist_ventas_cabecera hvcAlb
                INNER JOIN integral.dbo.hist_ventas_linea hvlAlb
                    ON hvcAlb.cod_venta = hvlAlb.cod_venta
                WHERE
                    hvcAlb.cod_cliente = hvc.cod_cliente
                    AND hvlAlb.cod_articulo = hvl.cod_articulo
                    AND hvcAlb.tipo_venta = 2
                    AND hvcAlb.fecha_venta > hvc.fecha_venta
            )
        )
    )
";
if ($cod_seccion !== null && $cod_seccion !== '') {
    $sql_normal .= " AND hvc.cod_seccion = '" . addslashes($cod_seccion) . "'";
}
if (!empty($_GET['pedido'])) {
    $pedido = $_GET['pedido'];
    $sql_normal .= " AND hvl.cod_venta = '" . addslashes($pedido) . "'";
}
if ($fecha_desde) {
    if (validarFechaSQL($fecha_desde)) {
        $sql_normal .= " AND hvc.fecha_venta >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120) ";
    } else {
        error_log("Formato de fecha 'desde' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($fecha_hasta) {
    if (validarFechaSQL($fecha_hasta)) {
        $sql_normal .= " AND hvc.fecha_venta <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120) ";
    } else {
        error_log("Formato de fecha 'hasta' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($cod_articulo) {
    $sql_normal .= " AND hvl.cod_articulo LIKE '%" . addslashes($cod_articulo) . "%' ";
}
if ($descripcion) {
    $sql_normal .= " AND hvl.descripcion LIKE '%" . addslashes($descripcion) . "%' ";
}

$sql_normal .= "
GROUP BY
    hvc.historico,
    hvc.cod_pedido_web,
    hvl.cod_venta,
    hvc.fecha_venta,
    hvl.cod_articulo,
    hvl.descripcion,
    hvl.observacion,
    hvl.linea,
    hvl.cantidad,
    hvl.precio,
    hvl.tipo_venta
HAVING
    (hvl.cantidad - ISNULL(SUM(elv.cantidad), 0)) > 0
";

// -----------------------------------------------------------------------------
// Consulta para registros eliminados (vlelim/vcelim) con comprobación en las normales
// -----------------------------------------------------------------------------
$sql_elim = "
SELECT 
    vcelim.historico AS Historico,
    vcelim.cod_pedido_web AS CodPedidoWeb,
    vlelim.cod_venta AS Pedido,
    vcelim.fecha_venta AS Fecha_Venta,
    vlelim.cod_articulo AS Articulo,
    vlelim.descripcion AS Descripcion,
    vlelim.observacion AS Observacion,
    vlelim.linea AS Linea,
    vlelim.cantidad AS Cantidad_Pedida,
    ISNULL(SUM(elv.cantidad), 0) AS Cantidad_Servida,
    (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) AS Cantidad_Restante,
    vlelim.precio AS Precio,
    (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) * vlelim.precio AS Importe_Restante,
    vlelim.tipo_venta AS Tipo_Venta,
    ISNULL(
        (SELECT TOP 1 s.cantidad_pendiente_recibir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = vlelim.cod_articulo
        ), 0
    ) AS Cantidad_Pendiente_Recibir,
    ISNULL(
        (SELECT TOP 1 s.existencias - s.cantidad_pendiente_servir
         FROM integral.dbo.stocks s
         WHERE s.cod_articulo = vlelim.cod_articulo
        ), 0
    ) AS Stock,
    (
        SELECT TOP 1 vcelimAlb.fecha_venta
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.ventas_cabecera_elim vcelimAlb
            ON vcelimAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = vlelim.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND vcelimAlb.tipo_venta = 2
        ORDER BY vcelimAlb.fecha_venta DESC
    ) AS Fecha_Albaran,
    (
        SELECT TOP 1 vcelimAlb.nombre_vendedor
        FROM integral.dbo.entrega_lineas_venta elv2
        JOIN integral.dbo.ventas_cabecera_elim vcelimAlb
            ON vcelimAlb.cod_venta = elv2.cod_venta_destino
        WHERE 
            elv2.cod_venta_origen = vlelim.cod_venta
            AND elv2.tipo_venta_origen = 1
            AND elv2.tipo_venta_destino = 2
            AND vcelimAlb.tipo_venta = 2
        ORDER BY vcelimAlb.fecha_venta DESC
    ) AS NombreVendedorAlbaran,
    'vcelim' as tabla
FROM 
    integral.dbo.ventas_linea_elim vlelim
    INNER JOIN integral.dbo.ventas_cabecera_elim vcelim
        ON vcelim.cod_venta = vlelim.cod_venta
    LEFT JOIN integral.dbo.entrega_lineas_venta elv
        ON vlelim.cod_venta = elv.cod_venta_origen
        AND vlelim.linea = elv.linea_origen
WHERE
    vcelim.cod_cliente = '" . addslashes($cod_cliente) . "'
    AND vlelim.tipo_venta = 1
    AND vcelim.tipo_venta = 1
    AND (
        (vlelim.cod_articulo = 'LM' AND vlelim.cantidad > 0)
        OR
        (
            NOT EXISTS (
                SELECT 1
                FROM integral.dbo.ventas_linea_elim vlelim2
                INNER JOIN integral.dbo.ventas_cabecera_elim vcelim2
                    ON vlelim2.cod_venta = vcelim2.cod_venta
                WHERE
                    vcelim2.cod_cliente = vcelim.cod_cliente
                    AND vlelim2.cod_articulo = vlelim.cod_articulo
                    AND vcelim2.tipo_venta = 1
                    AND vcelim2.fecha_venta > vcelim.fecha_venta
            )
            AND
            vlelim.cantidad > (
                SELECT ISNULL(SUM(vlelimAlb.cantidad), 0)
                FROM integral.dbo.ventas_cabecera_elim vcelimAlb
                INNER JOIN integral.dbo.ventas_linea_elim vlelimAlb
                    ON vcelimAlb.cod_venta = vlelimAlb.cod_venta
                WHERE
                    vcelimAlb.cod_cliente = vcelim.cod_cliente
                    AND vlelimAlb.cod_articulo = vlelim.cod_articulo
                    AND vcelimAlb.tipo_venta = 2
                    AND vcelimAlb.fecha_venta > vcelim.fecha_venta
            )
        )
    )
    -- Descarta el registro eliminado si existe una venta normal posterior
    AND NOT EXISTS (
        SELECT 1
        FROM integral.dbo.hist_ventas_linea hvl_norm
        INNER JOIN integral.dbo.hist_ventas_cabecera hvc_norm
            ON hvc_norm.cod_venta = hvl_norm.cod_venta
        WHERE
            hvc_norm.cod_cliente = vcelim.cod_cliente
            AND hvl_norm.cod_articulo = vlelim.cod_articulo
            AND hvc_norm.tipo_venta = 1
            AND hvc_norm.fecha_venta >= vcelim.fecha_venta
    )
";
if ($cod_seccion !== null && $cod_seccion !== '') {
    $sql_elim .= " AND vcelim.cod_seccion = '" . addslashes($cod_seccion) . "'";
}
if (!empty($_GET['pedido'])) {
    $pedido = $_GET['pedido'];
    $sql_elim .= " AND vlelim.cod_venta = '" . addslashes($pedido) . "'";
}
if ($fecha_desde) {
    if (validarFechaSQL($fecha_desde)) {
        $sql_elim .= " AND vcelim.fecha_venta >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120) ";
    } else {
        error_log("Formato de fecha 'desde' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($fecha_hasta) {
    if (validarFechaSQL($fecha_hasta)) {
        $sql_elim .= " AND vcelim.fecha_venta <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120) ";
    } else {
        error_log("Formato de fecha 'hasta' inválido. Debe ser 'YYYY-MM-DD'.");
        echo 'Error interno';
        return;
    }
}
if ($cod_articulo) {
    $sql_elim .= " AND vlelim.cod_articulo LIKE '%" . addslashes($cod_articulo) . "%' ";
}
if ($descripcion) {
    $sql_elim .= " AND vlelim.descripcion LIKE '%" . addslashes($descripcion) . "%' ";
}
$sql_elim .= "
GROUP BY
    vcelim.historico,
    vcelim.cod_pedido_web,
    vlelim.cod_venta,
    vcelim.fecha_venta,
    vlelim.cod_articulo,
    vlelim.descripcion,
    vlelim.observacion,
    vlelim.linea,
    vlelim.cantidad,
    vlelim.precio,
    vlelim.tipo_venta
HAVING
    (vlelim.cantidad - ISNULL(SUM(elv.cantidad), 0)) > 0
";

// -----------------------------------------------------------------------------
// Unir ambas consultas
// -----------------------------------------------------------------------------
$sql_lineas_total = "
    SELECT * FROM (
        $sql_normal
        UNION ALL
        $sql_elim
    ) as combined
    ORDER BY " . $orden_permitido[$orden] . " " . $direccion;
    
$result_lineas = odbc_exec($conn, $sql_lineas_total);
if (!$result_lineas) {
    error_log("Error en la consulta SQL: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
while ($fila = odbc_fetch_array($result_lineas)) {
    $lineas[] = $fila;
}
$num_lineas = count($lineas);

// Calcular suma_total (por ejemplo, sumando Importe_Restante de cada registro)
foreach ($lineas as $fila) {
    $suma_total += isset($fila['Importe_Restante']) ? (float)$fila['Importe_Restante'] : 0;
}

$numero_pedido = $_GET['pedido'] ?? null;

$sql_cliente_seccion = "
    SELECT 
        c.nombre_comercial AS nombre_cliente, 
        COALESCE(s.nombre, 'Sin Sección') AS nombre_seccion
    FROM [integral].[dbo].[clientes] c
    LEFT JOIN [integral].[dbo].[secciones_cliente] s
        ON c.cod_cliente = s.cod_cliente
    WHERE c.cod_cliente = '" . addslashes($cod_cliente) . "'
";
if ($cod_seccion !== null && $cod_seccion !== '') {
    $sql_cliente_seccion .= " AND s.cod_seccion = '" . addslashes($cod_seccion) . "'";
}
$result_cliente_seccion = odbc_exec($conn, $sql_cliente_seccion);
if (!$result_cliente_seccion) {
    error_log("Error al obtener datos del cliente y sección: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$cliente_seccion = odbc_fetch_array($result_cliente_seccion);
if (!$cliente_seccion) {
    $nombre_cliente = "Cliente no encontrado";
    $nombre_seccion = "Sin sección";
} else {
    $nombre_cliente = $cliente_seccion['nombre_cliente'] ?? "Desconocido";
    $nombre_seccion = $cliente_seccion['nombre_seccion'] ?? "Sin sección";
}
$pageTitle = "Faltas de " . $nombre_cliente;
if ($numero_pedido) {
    $pageTitle .= " - Pedido: " . htmlspecialchars($numero_pedido);
}
if ($nombre_seccion !== 'Sin Sección') {
    $pageTitle .= " - " . htmlspecialchars($nombre_seccion);
}
include BASE_PATH . '/resources/views/layouts/header.php';
require_once BASE_PATH . '/app/Modules/Visitas/views/faltas.php';
