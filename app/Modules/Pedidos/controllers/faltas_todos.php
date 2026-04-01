<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';

// Verificar si el usuario ha iniciado sesión


require_once BASE_PATH . '/app/Support/functions.php'; // Se asume que incluye la función toUTF8

// Obtener el código de vendedor asociado al usuario actual
$conn = db();
if (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '') {
    $cod_vendedor = (string)$_SESSION['codigo'];
} else {
$sql_cod_vendedor = "
    SELECT cod_vendedor 
    FROM cmf_vendedores_user 
    WHERE email = '" . addslashes($_SESSION['email']) . "'
";
$result_vendedor = odbc_exec($conn, $sql_cod_vendedor);
if (!$result_vendedor || !odbc_fetch_row($result_vendedor)) {
    error_log("Error: No se pudo determinar el código de vendedor.");
    echo 'Error interno';
    return;
}
$cod_vendedor = odbc_result($result_vendedor, "cod_vendedor");
}

// Filtro de fechas
$defaultEnd   = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-15 days'));
$start_date   = isset($_GET['start_date']) ? $_GET['start_date'] : $defaultStart;
$end_date     = isset($_GET['end_date']) ? $_GET['end_date'] : $defaultEnd;

// Filtro de cliente: se busca tanto por código como por nombre
$cliente_filtro = isset($_GET['cliente']) ? mb_convert_encoding($_GET['cliente'], 'Windows-1252', 'UTF-8') : '';

// Parámetros de ordenación
// Se agregó "Importe" para poder ordenar por ese campo.
$orden_permitido = array(
    'Pedido'               => 'Pedido',
    'Fecha_Pedido'         => 'Fecha_Pedido',
    'Cliente'              => 'Cliente',
    'Importe'              => 'Importe',
    'Articulos_Pendientes' => 'Articulos_Pendientes',
    'Importe_Pendiente'    => 'Importe_Pendiente',
    'Importe_Disponible'   => 'Importe_Disponible',
    'Importe_Pdte_Recibir' => 'Importe_Pdte_Recibir'
);
$orden     = (isset($_GET['orden']) && in_array($_GET['orden'], array_keys($orden_permitido))) ? $_GET['orden'] : 'Pedido';
$direccion = (isset($_GET['direccion']) && ($_GET['direccion'] === 'ASC' || $_GET['direccion'] === 'DESC')) ? $_GET['direccion'] : 'DESC';
$direccion_invertida = ($direccion === 'ASC') ? 'DESC' : 'ASC';

// Para la consulta SQL, si se solicita ordenar por una columna calculada, usamos un campo existente.
if (in_array($orden, array('Importe_Disponible', 'Importe_Pdte_Recibir'))) {
    $sql_order = 'Pedido';
} else {
    $sql_order = $orden;
}

// Parámetros de paginación
$resultsPerPage = 30;
$page           = (isset($_GET['page'])) ? (int) $_GET['page'] : 1;
if ($page < 1) { 
    $page = 1; 
}
$offset = ($page - 1) * $resultsPerPage;

// -----------------------------------------------------------------------------
// Definir condiciones para cada una de las consultas (normal y eliminados)
// -----------------------------------------------------------------------------
$whereConditionsNormal = array();
$whereConditionsNormal[] = "hvl.tipo_venta = 1";
$whereConditionsNormal[] = "hvc.tipo_venta = 1";
// Pedidos históricos (faltas)
$whereConditionsNormal[] = "hvc.historico = 'S'";
$whereConditionsNormal[] = "(hvl.cantidad > ISNULL(elv.cantidad_servida, 0))";
if (!is_null($cod_vendedor)) {
    $whereConditionsNormal[] = "c.cod_vendedor = '" . addslashes($cod_vendedor) . "'";
}
$whereConditionsNormal[] = "hvc.fecha_venta >= CONVERT(DATETIME, '" . addslashes($start_date) . "', 120)";
$whereConditionsNormal[] = "hvc.fecha_venta <= CONVERT(DATETIME, '" . addslashes($end_date) . "', 120)";
if (!empty($cliente_filtro)) {
    $cliente_filtro_esc = addslashes($cliente_filtro);
    $whereConditionsNormal[] = "(c.cod_cliente LIKE '%{$cliente_filtro_esc}%' OR c.nombre_comercial LIKE '%{$cliente_filtro_esc}%')";
}

$whereConditionsElim = array();
$whereConditionsElim[] = "vlelim.tipo_venta = 1";
$whereConditionsElim[] = "vcelim.tipo_venta = 1";
$whereConditionsElim[] = "(vlelim.cantidad > ISNULL(elv.cantidad_servida, 0))";
if (!is_null($cod_vendedor)) {
    $whereConditionsElim[] = "c.cod_vendedor = '" . addslashes($cod_vendedor) . "'";
}
$whereConditionsElim[] = "vcelim.fecha_venta >= CONVERT(DATETIME, '" . addslashes($start_date) . "', 120)";
$whereConditionsElim[] = "vcelim.fecha_venta <= CONVERT(DATETIME, '" . addslashes($end_date) . "', 120)";
if (!empty($cliente_filtro)) {
    $cliente_filtro_esc = addslashes($cliente_filtro);
    $whereConditionsElim[] = "(c.cod_cliente LIKE '%{$cliente_filtro_esc}%' OR c.nombre_comercial LIKE '%{$cliente_filtro_esc}%')";
}

// -----------------------------------------------------------------------------
// Construir la consulta unificada con UNION ALL
// -----------------------------------------------------------------------------

// Consulta normal: se agrega hvc.importe AS Importe
$sql_normal = "
    SELECT 
        hvl.cod_venta AS Pedido,
        hvc.fecha_venta AS Fecha_Pedido,
        c.cod_cliente AS cod_cliente,
        c.nombre_comercial AS Cliente,
        hvc.importe AS Importe,
        hvc.cod_seccion AS Cod_Seccion,
        COALESCE(s.nombre, '') AS Seccion,
        COUNT(hvl.cod_articulo) AS Articulos_Pendientes,
        SUM(
            CASE 
                WHEN elv.cod_venta_origen IS NULL THEN hvl.cantidad * hvl.precio 
                ELSE (hvl.cantidad - elv.cantidad_servida) * hvl.precio 
            END
        ) AS Importe_Pendiente,
        hvc.cod_anexo,
        avc.observacion_interna AS ObservacionInterna,
        'hvc' as tabla
    FROM 
        integral.dbo.hist_ventas_linea hvl
    INNER JOIN 
        integral.dbo.hist_ventas_cabecera hvc ON hvc.cod_venta = hvl.cod_venta
    LEFT JOIN (
        SELECT 
            cod_venta_origen, 
            linea_origen, 
            SUM(cantidad) AS cantidad_servida
        FROM 
            integral.dbo.entrega_lineas_venta
        WHERE 
            tipo_venta_origen = 1
        GROUP BY 
            cod_venta_origen, linea_origen
    ) elv ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
    LEFT JOIN 
        integral.dbo.clientes c ON hvc.cod_cliente = c.cod_cliente
    LEFT JOIN 
        integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = hvc.cod_seccion
    LEFT JOIN 
        integral.dbo.anexo_ventas_cabecera avc ON hvc.cod_anexo = avc.cod_anexo
    WHERE " . implode(" AND ", $whereConditionsNormal) . "
    GROUP BY 
        hvl.cod_venta, 
        hvc.fecha_venta, 
        c.cod_cliente, 
        c.nombre_comercial, 
        hvc.importe,
        hvc.cod_seccion, 
        s.nombre,
        hvc.cod_anexo,
        avc.observacion_interna
";

// Consulta de eliminados: se agrega vcelim.importe AS Importe
$sql_elim = "
    SELECT 
        vlelim.cod_venta AS Pedido,
        vcelim.fecha_venta AS Fecha_Pedido,
        c.cod_cliente AS cod_cliente,
        c.nombre_comercial AS Cliente,
        vcelim.importe AS Importe,
        vcelim.cod_seccion AS Cod_Seccion,
        COALESCE(s.nombre, '') AS Seccion,
        COUNT(vlelim.cod_articulo) AS Articulos_Pendientes,
        SUM(
            CASE 
                WHEN elv.cod_venta_origen IS NULL THEN vlelim.cantidad * vlelim.precio 
                ELSE (vlelim.cantidad - elv.cantidad_servida) * vlelim.precio 
            END
        ) AS Importe_Pendiente,
        vcelim.cod_anexo,
        avc.observacion_interna AS ObservacionInterna,
        'vcelim' as tabla
    FROM 
        integral.dbo.ventas_linea_elim vlelim
    INNER JOIN 
        integral.dbo.ventas_cabecera_elim vcelim ON vcelim.cod_venta = vlelim.cod_venta
    LEFT JOIN (
        SELECT 
            cod_venta_origen, 
            linea_origen, 
            SUM(cantidad) AS cantidad_servida
        FROM 
            integral.dbo.entrega_lineas_venta
        WHERE 
            tipo_venta_origen = 1
        GROUP BY 
            cod_venta_origen, linea_origen
    ) elv ON vlelim.cod_venta = elv.cod_venta_origen AND vlelim.linea = elv.linea_origen
    LEFT JOIN 
        integral.dbo.clientes c ON vcelim.cod_cliente = c.cod_cliente
    LEFT JOIN 
        integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = vcelim.cod_seccion
    LEFT JOIN 
        integral.dbo.anexo_ventas_cabecera avc ON vcelim.cod_anexo = avc.cod_anexo
    WHERE " . implode(" AND ", $whereConditionsElim) . "
    GROUP BY 
        vlelim.cod_venta, 
        vcelim.fecha_venta, 
        c.cod_cliente, 
        c.nombre_comercial, 
        vcelim.importe,
        vcelim.cod_seccion, 
        s.nombre,
        vcelim.cod_anexo,
        avc.observacion_interna
";

$sql_pedidos = "
    SELECT * FROM (
        $sql_normal
        UNION ALL
        $sql_elim
    ) as combined
    ORDER BY $sql_order $direccion
    OFFSET $offset ROWS FETCH NEXT $resultsPerPage ROWS ONLY
";

// Ejecutar consulta de pedidos
$result_pedidos = odbc_exec($conn, $sql_pedidos);
if (!$result_pedidos) {
    error_log("Error en la consulta SQL: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$pedidos = array();
while ($row = odbc_fetch_array($result_pedidos)) {
    $pedidos[] = $row;
}

// Cálculo de importes (adaptado para faltas)
if (in_array($orden, array('Importe_Disponible', 'Importe_Pdte_Recibir'))) {
    foreach ($pedidos as &$pedido) {
        $pedidoId = addslashes($pedido['Pedido']);
        $importeDisponibleTotal = 0;
        $importePdteRecibirTotal = 0;
        // Consulta para obtener las líneas del pedido (se asume misma estructura para ambos orígenes)
        $sql_lineas = "
            SELECT 
                hvl.cantidad AS Cantidad_Pedida,
                (hvl.cantidad - ISNULL(SUM(elv.cantidad),0)) AS Cantidad_Restante,
                hvl.precio AS Precio,
                ISNULL(
                  (SELECT TOP 1 s.existencias 
                   FROM integral.dbo.stocks s 
                   WHERE s.cod_articulo = hvl.cod_articulo), 0
                ) AS Stock,
                ISNULL(
                  (SELECT TOP 1 s.cantidad_pendiente_recibir
                   FROM integral.dbo.stocks s 
                   WHERE s.cod_articulo = hvl.cod_articulo), 0
                ) AS PdteRecibir
            FROM integral.dbo.hist_ventas_linea hvl
            LEFT JOIN integral.dbo.entrega_lineas_venta elv 
                ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
            WHERE hvl.cod_venta = '$pedidoId' AND hvl.tipo_venta = 1
            GROUP BY hvl.cantidad, hvl.precio, hvl.cod_articulo
        ";
        $result_lineas = odbc_exec($conn, $sql_lineas);
        if ($result_lineas) {
            while ($linea = odbc_fetch_array($result_lineas)) {
                $cantidadPedida   = (float)$linea['Cantidad_Pedida'];
                $cantidadRestante = (float)$linea['Cantidad_Restante'];
                $precio           = (float)$linea['Precio'];
                
                $importeRestanteLinea = $cantidadRestante * $precio;
                $price_unit = ($cantidadRestante > 0) ? ($importeRestanteLinea / $cantidadRestante) : 0;
                
                $stockDisponible = (float)$linea['Stock'];
                if ($stockDisponible < 0) {
                    $stockDisponible = 0;
                }
                
                $servibleStock = min($stockDisponible, $cantidadRestante);
                $importeDisponibleLinea = $servibleStock * $price_unit;
                
                $resto = $cantidadRestante - $servibleStock;
                $pdteRecibirValor = (float)$linea['PdteRecibir'];
                $pdteRecibirDisponible = min($resto, $pdteRecibirValor);
                $importePdteRecibirLinea = $pdteRecibirDisponible * $price_unit;
                
                $importeDisponibleTotal += $importeDisponibleLinea;
                $importePdteRecibirTotal += $importePdteRecibirLinea;
            }
        }
        $pedido['Importe_Disponible'] = $importeDisponibleTotal;
        $pedido['Importe_Pdte_Recibir'] = $importePdteRecibirTotal;
    }
    // Ordenar según la columna calculada
    usort($pedidos, function($a, $b) use ($orden, $direccion) {
        if ($a[$orden] == $b[$orden]) return 0;
        if ($direccion === 'ASC') {
            return ($a[$orden] < $b[$orden]) ? -1 : 1;
        } else {
            return ($a[$orden] > $b[$orden]) ? -1 : 1;
        }
    });
}

// Consulta para el total de registros (para paginación)
$sql_count_normal = "
    SELECT hvl.cod_venta
    FROM 
        integral.dbo.hist_ventas_linea hvl
    INNER JOIN 
        integral.dbo.hist_ventas_cabecera hvc ON hvc.cod_venta = hvl.cod_venta
    LEFT JOIN (
        SELECT 
            cod_venta_origen, 
            linea_origen, 
            SUM(cantidad) AS cantidad_servida
        FROM 
            integral.dbo.entrega_lineas_venta
        WHERE 
            tipo_venta_origen = 1
        GROUP BY 
            cod_venta_origen, linea_origen
    ) elv ON hvl.cod_venta = elv.cod_venta_origen AND hvl.linea = elv.linea_origen
    LEFT JOIN 
        integral.dbo.clientes c ON hvc.cod_cliente = c.cod_cliente
    LEFT JOIN 
        integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = hvc.cod_seccion
    WHERE " . implode(" AND ", $whereConditionsNormal) . "
    GROUP BY 
        hvl.cod_venta, 
        hvc.fecha_venta, 
        c.cod_cliente, 
        c.nombre_comercial, 
        hvc.cod_seccion, 
        s.nombre
";
$sql_count_elim = "
    SELECT vlelim.cod_venta
    FROM 
        integral.dbo.ventas_linea_elim vlelim
    INNER JOIN 
        integral.dbo.ventas_cabecera_elim vcelim ON vcelim.cod_venta = vlelim.cod_venta
    LEFT JOIN (
        SELECT 
            cod_venta_origen, 
            linea_origen, 
            SUM(cantidad) AS cantidad_servida
        FROM 
            integral.dbo.entrega_lineas_venta
        WHERE 
            tipo_venta_origen = 1
        GROUP BY 
            cod_venta_origen, linea_origen
    ) elv ON vlelim.cod_venta = elv.cod_venta_origen AND vlelim.linea = elv.linea_origen
    LEFT JOIN 
        integral.dbo.clientes c ON vcelim.cod_cliente = c.cod_cliente
    LEFT JOIN 
        integral.dbo.secciones_cliente s ON s.cod_cliente = c.cod_cliente AND s.cod_seccion = vcelim.cod_seccion
    WHERE " . implode(" AND ", $whereConditionsElim) . "
    GROUP BY 
        vlelim.cod_venta, 
        vcelim.fecha_venta, 
        c.cod_cliente, 
        c.nombre_comercial, 
        vcelim.cod_seccion, 
        s.nombre
";
$sql_count = "
    SELECT COUNT(*) as total
    FROM (
        $sql_count_normal
        UNION ALL
        $sql_count_elim
    ) as TotalQuery
";
$result_count = odbc_exec($conn, $sql_count);
if (!$result_count || !odbc_fetch_row($result_count)) {
    error_log("Error en la consulta de conteo: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$totalRecords = odbc_result($result_count, "total");
$totalPages   = ceil($totalRecords / $resultsPerPage);
function buildPedidoUrlFaltas($pedido) {
    $params = array(
        'cod_cliente' => $pedido['cod_cliente'],
        'pedido' => $pedido['Pedido'],
    );

    if (isset($pedido['Cod_Seccion']) && $pedido['Cod_Seccion'] !== '' && $pedido['Cod_Seccion'] !== null) {
        $params['cod_seccion'] = $pedido['Cod_Seccion'];
    }

    if (isset($pedido['tabla']) && $pedido['tabla'] === 'vcelim') {
        $params['tabla'] = 'vcelim';
    }

    return 'pedido.php?' . http_build_query($params);
}


$pageTitle = "Pedidos Cerrados de Todos los Clientes";
include BASE_PATH . '/resources/views/layouts/header.php';
?>
require_once BASE_PATH . '/app/Modules/Pedidos/views/faltas_todos.php';
