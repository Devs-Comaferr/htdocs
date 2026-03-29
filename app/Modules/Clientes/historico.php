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
require_once BASE_PATH . '/app/Support/functions.php'; // Se incluyen todas las funciones centralizadas

// Verificar sesión


// Verificar que venga cod_cliente
if (empty($_GET['cod_cliente'])) {
    error_log("El parámetro 'cod_cliente' es obligatorio.");
    echo 'Error interno';
    return;
}
$cod_cliente = $_GET['cod_cliente'];
$cod_seccion = $_GET['cod_seccion'] ?? null;
$cod_comercial = $_SESSION['codigo'] ?? null;

// Variables para acumulados
$ventas_unificadas = [];
$num_ventas = 0;
$suma_total = 0;

// Flags descuento
$mostrar_dto1 = false;
$mostrar_dto2 = false;

// Filtros GET
$fecha_desde  = (isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== '') ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-1 year'));
$fecha_hasta  = (isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== '') ? $_GET['fecha_hasta'] : date('Y-m-d');
$cod_articulo = (isset($_GET['cod_articulo']) && $_GET['cod_articulo'] !== '') ? $_GET['cod_articulo'] : null;
$descripcion  = (isset($_GET['descripcion']) && $_GET['descripcion'] !== '') ? $_GET['descripcion'] : null;

// Filtro solo programa nuevo
$solo_programa_nuevo = (isset($_GET['solo_programa_nuevo']) && $_GET['solo_programa_nuevo'] == '1');

// (NUEVO) Filtro agrupar: true si llega agrupar=1
$agrupar = (isset($_GET['agrupar']) && $_GET['agrupar'] == '1');

// Columnas permitidas para la ordenación
$orden_permitido_detallado = [
    'cod_venta', 'fecha_venta', 'cod_articulo', 'descripcion',
    'cantidad', 'precio', 'dto1', 'dto2', 'importe', 'tipo_programa'
];
$orden_permitido_agrupado = [
    'cod_articulo', 'descripcion', 'cantidad', 'precio', 'importe'
];

// Elegir la lista en función de si está agrupado
$orden_permitido = $agrupar ? $orden_permitido_agrupado : $orden_permitido_detallado;

// Determinar la columna orden
$orden = (isset($_GET['orden']) && in_array($_GET['orden'], $orden_permitido))
         ? $_GET['orden']
         : ($agrupar ? 'cod_articulo' : 'fecha_venta');

// Si est activado el modo agrupado, forzamos el orden por "descripcion" de forma ascendente
if ($agrupar) {
    $orden = 'descripcion';
    $direccion = 'ASC';
} else {
    $direccion = (isset($_GET['direccion']) && in_array($_GET['direccion'], ['ASC', 'DESC']))
                 ? $_GET['direccion']
                 : 'DESC';
}
$direccion_invertida = ($direccion === 'ASC') ? 'DESC' : 'ASC';

// Incluir conexión a la base de datos.

// Consultar datos del cliente y su sección
$conn = db();
$sql_cli_sec = "
    SELECT
        c.nombre_comercial AS nombre_cliente,
        COALESCE(s.nombre, 'Sin Sección') AS nombre_seccion
    FROM [integral].[dbo].[clientes] c
    LEFT JOIN [integral].[dbo].[secciones_cliente] s
           ON c.cod_cliente = s.cod_cliente
";
if ($cod_seccion !== null) {
    $sql_cli_sec .= "
    WHERE c.cod_cliente = '" . addslashes($cod_cliente) . "'
      AND s.cod_seccion = '" . addslashes($cod_seccion) . "'";
} else {
    $sql_cli_sec .= "
    WHERE c.cod_cliente = '" . addslashes($cod_cliente) . "'";
}
$res_cli_sec = odbc_exec($conn, $sql_cli_sec);
if (!$res_cli_sec) {
    error_log("Error al obtener datos del cliente y sección: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$cli_sec = odbc_fetch_array($res_cli_sec);
if (!$cli_sec) {
    error_log("No se encontraron datos para el cliente o la sección.");
    echo 'Error interno';
    return;
}
$nombre_cliente = $cli_sec['nombre_cliente'] ?? 'Desconocido';
$nombre_seccion = $cli_sec['nombre_seccion'] ?? 'Sin Sección';

$pageTitle = "Histórico de " . $nombre_cliente . ($nombre_seccion != 'Sin Sección' ? " - " . $nombre_seccion : '');

/**
 * Se obtienen los filtros SQL usando las funciones definidas en funciones.php.
 */
$sql_filtros_nuevo = construir_filtros_nuevo();
$sql_filtros_antiguo = construir_filtros_antiguo();

/**
 * Filtro por vendedor usando la variable de sesión 'codigo'.
 * Recibe la columna completa (por ejemplo 'v.cod_vendedor' o 'm.cod_vendedor')
 * y devuelve un fragmento SQL de tipo " AND <col> = 'valor'" cuando existe.
 */
function construir_filtro_vendedor($col)
{
    if (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '') {
        $raw = $_SESSION['codigo'];
        if (is_numeric($raw) && (string)intval($raw) === (string)$raw) {
            $codigo = intval($raw);
            return " AND " . $col . " = " . $codigo;
        }
        $codigo = str_replace("'", "''", $raw);
        return " AND " . $col . " = '" . $codigo . "'";
    }
    return '';
}

// Consultas para modo DETALLADO
$sql_nuevo_detallado = "
    SELECT
        'Nuevo' AS tipo_programa,
        CAST(v.cod_venta AS VARCHAR(50)) AS cod_venta,
        v.fecha_venta,
        CAST(l.cod_articulo AS VARCHAR(50)) AS cod_articulo,
        l.descripcion,
        l.cantidad,
        l.precio,
        l.importe,
        COALESCE(l.dto1,0) AS dto1,
        COALESCE(l.dto2,0) AS dto2,
        COALESCE(l.observacion,'') AS detalle
    FROM [integral].[dbo].[hist_ventas_cabecera] v
    INNER JOIN [integral].[dbo].[hist_ventas_linea] l
           ON v.cod_venta = l.cod_venta
          AND v.tipo_venta = l.tipo_venta
    WHERE v.cod_cliente = '" . addslashes($cod_cliente) . "'
    AND v.tipo_venta = 2
" . construir_filtro_vendedor('v.cod_comisionista') . "
";
if ($cod_seccion !== null) {
    $sql_nuevo_detallado .= " AND v.cod_seccion = '" . addslashes($cod_seccion) . "'";
}

if ($cod_comercial !== null AND $cod_comercial === '30') {  //Bloqueo Agustn Castro
    $sql_nuevo_detallado .= " AND v.cod_comisionista = '" . addslashes($cod_comercial) . "'";
}

$sql_nuevo_detallado .= $sql_filtros_nuevo;
$sql_antiguo_detallado = "
    SELECT
        'Antiguo' AS tipo_programa,
        COALESCE(m.documento, CAST(m.cod_fac AS VARCHAR(50))) AS cod_venta,
        m.fecha AS fecha_venta,
        m.referencia AS cod_articulo,
        COALESCE(ad.descripcion,'Artículo eliminado') AS descripcion,
        m.cantidad,
        m.precio,
        m.importe,
        0 AS dto1,
        0 AS dto2,
        '' AS detalle
    FROM [integral].[dbo].[cmf_movimientos_ofipro] m
    LEFT JOIN [integral].[dbo].[articulo_descripcion] ad
           ON m.referencia = ad.cod_articulo
          AND ad.cod_idioma = 'ES'
    WHERE m.codigo = '" . addslashes($cod_cliente) . "'
";
$sql_antiguo_detallado .= $sql_filtros_antiguo;

// Consultas sin agrupar (para luego unificarlas)
$sql_nuevo_sin_agrup = "
    SELECT
        CAST(l.cod_articulo AS VARCHAR(50)) AS cod_articulo,
        l.descripcion,
        l.cantidad,
        l.precio,
        l.importe
    FROM [integral].[dbo].[hist_ventas_cabecera] v
    INNER JOIN [integral].[dbo].[hist_ventas_linea] l
           ON v.cod_venta = l.cod_venta
          AND v.tipo_venta = l.tipo_venta
        WHERE v.cod_cliente = '" . addslashes($cod_cliente) . "'
            AND v.tipo_venta = 2
" . construir_filtro_vendedor('v.cod_comisionista') . "
";
if ($cod_seccion !== null) {
    $sql_nuevo_sin_agrup .= " AND v.cod_seccion = '" . addslashes($cod_seccion) . "'";
}
$sql_nuevo_sin_agrup .= $sql_filtros_nuevo;

$sql_antiguo_sin_agrup = "
    SELECT
        m.referencia AS cod_articulo,
        COALESCE(ad.descripcion,'Artículo eliminado') AS descripcion,
        m.cantidad,
        m.precio,
        m.importe
    FROM [integral].[dbo].[cmf_movimientos_ofipro] m
    LEFT JOIN [integral].[dbo].[articulo_descripcion] ad
           ON m.referencia = ad.cod_articulo
          AND ad.cod_idioma = 'ES'
    WHERE m.codigo = '" . addslashes($cod_cliente) . "'
    -- cmf_movimientos_ofipro no dispone de cod_comisionista;
    -- el filtro por vendedor solo se aplica a hist_ventas_*.
";
$sql_antiguo_sin_agrup .= $sql_filtros_antiguo;

// Unir con UNION ALL y agrupar en la SELECT externa (modo AGRUPADO)
$sql_agrupado_unificado = "
SELECT
  sub.cod_articulo,
  MAX(sub.descripcion) AS descripcion,
  SUM(sub.cantidad)    AS cantidad,
  AVG(sub.precio)      AS precio,
  SUM(sub.importe)     AS importe
FROM (
   $sql_nuevo_sin_agrup
   UNION ALL
   $sql_antiguo_sin_agrup
) sub
GROUP BY sub.cod_articulo
";

// Decidir consulta final segn $agrupar y $solo_programa_nuevo
if ($agrupar) {
    if ($solo_programa_nuevo) {
        $sql_agrupado_unificado = "
        SELECT
          sub.cod_articulo,
          MAX(sub.descripcion) AS descripcion,
          SUM(sub.cantidad)    AS cantidad,
          AVG(sub.precio)      AS precio,
          SUM(sub.importe)     AS importe
        FROM (
          $sql_nuevo_sin_agrup
        ) sub
        GROUP BY sub.cod_articulo
        ";
    }
    $sql_unificado = $sql_agrupado_unificado;
} else {
    if (!$solo_programa_nuevo) {
        $sql_unificado = $sql_nuevo_detallado . " UNION ALL " . $sql_antiguo_detallado;
    } else {
        $sql_unificado = $sql_nuevo_detallado;
    }
}

$sql_unificado .= " ORDER BY " . $orden . " " . $direccion;

$result = odbc_exec($conn, $sql_unificado);
if (!$result) {
    error_log("Error al obtener ventas unificadas: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
while ($row = odbc_fetch_array($result)) {
    $ventas_unificadas[] = $row;
    $suma_total += (isset($row['importe']) ? floatval($row['importe']) : 0);
}
$num_ventas = count($ventas_unificadas);

if (!$agrupar) {
    foreach ($ventas_unificadas as $v) {
        if (isset($v['dto1']) && floatval($v['dto1']) > 0) {
            $mostrar_dto1 = true;
        }
        if (isset($v['dto2']) && floatval($v['dto2']) > 0) {
            $mostrar_dto2 = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle); ?></title>
    <!-- Bootstrap CSS (local via Composer assets) -->
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- noUiSlider CSS (local via Composer assets) -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.css" />
    <!-- Font Awesome (local via Composer assets) -->
<link href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
  <style>
      body {
          font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
          background-color: #f8f9fa;
          margin: 0;
          padding: 20px;
      }
      .container {
          max-width: 100%;
          margin: 0 auto;
          background-color: #fff;
          padding: 30px;
          border-radius: 8px;
          box-shadow: 0 0 20px rgba(0,0,0,0.05);
          margin-top: 60px;
      }
      /* Estilos para la tabla */
      table {
          width: 100%;
          border-collapse: collapse;
      }
      table th, table td {
          padding: 12px 15px;
          vertical-align: middle;
      }
      table thead th {
          background-color: #000 !important;
          color: #fff !important;
          font-size: 0.9em;
      }
      table thead th a {
          color: #fff !important;
          text-decoration: none;
      }
      table tbody tr {
          border-bottom: 1px solid #dee2e6;
          transition: background-color 0.2s ease-in-out;
      }
      table tbody tr:hover {
          background-color: #f1f1f1;
      }
      .text-end {
          text-align: right;
          white-space: nowrap;
      }
      .text-start {
          text-align: left;
      }
      .row-eliminated {
          font-style: italic;
          opacity: 0.7;
      }
  </style>
</head>
<body>

<!-- Incluir la cabecera -->
<?php include_once BASE_PATH . '/resources/views/layouts/header.php'; ?>

<div class="container">
    <!-- Formulario de filtros -->
    <form method="GET" id="filtrosForm" class="mb-3">
        <!-- Campos ocultos -->
        <input type="hidden" name="cod_cliente" value="<?= htmlspecialchars($cod_cliente); ?>">
        <?php if ($cod_seccion): ?>
            <input type="hidden" name="cod_seccion" value="<?= htmlspecialchars($cod_seccion); ?>">
        <?php endif; ?>
        <?php if ($solo_programa_nuevo): ?>
            <input type="hidden" name="solo_programa_nuevo" value="1">
        <?php endif; ?>
        <input type="hidden" name="agrupar" value="<?= $agrupar ? '1' : '0'; ?>">
        <input type="hidden" id="fecha_desde" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde); ?>">
        <input type="hidden" id="fecha_hasta" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta); ?>">
        
        <!-- Fila 1: Línea de tiempo (slider) -->
        <div class="row mb-3">
            <div class="col">
                <label for="date-slider" class="form-label">Rango de Fechas:</label>
                <div id="date-slider"></div>
            </div>
        </div>
        
        <!-- Fila 2: Filtros y botones -->
        <div class="row mb-3 align-items-end">
            <div class="col-12 col-md-2 mb-2 mb-md-0">
                <input type="text" class="form-control" id="cod_articulo" name="cod_articulo" maxlength="15" value="<?= htmlspecialchars($cod_articulo); ?>" placeholder="Código Artículo">
            </div>
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <input type="text" class="form-control" id="descripcion" name="descripcion" value="<?= htmlspecialchars($descripcion); ?>" placeholder="Descripción">
            </div>
            <div class="col-12 col-md-4 d-flex justify-content-end flex-wrap">
                <button type="submit" name="filtrar" value="1" class="btn btn-primary btn-sm me-2 mb-2">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <button type="button" class="btn btn-danger btn-sm me-2 mb-2" onclick="window.location.href='<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?cod_cliente=' . urlencode($cod_cliente) . ($cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : ''); ?>';">
                    <i class="fas fa-trash-alt"></i> Limpiar
                </button>
                <button type="submit" name="agrupar" value="<?= $agrupar ? '0' : '1'; ?>" class="btn btn-info btn-sm mb-2">
                    <i class="fas fa-layer-group"></i> <?= $agrupar ? 'Desagrupar Artculos' : 'Agrupar Artculos'; ?>
                </button>
            </div>
        </div>
    </form>

    <!-- Tabla de resultados -->
    <div class="table-responsive">
    <?php if ($num_ventas > 0): ?>
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <?php if (!$agrupar): ?>
                    <tr>
                        <th class="text-center">
                            <a href="?orden=cod_venta&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Código Venta</a>
                        </th>
                        <th class="text-center">
                            <a href="?orden=fecha_venta&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Fecha</a>
                        </th>
                        <th class="text-center">
                            <a href="?orden=cod_articulo&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Artículo</a>
                        </th>
                        <th class="text-start">
                            <a href="?orden=descripcion&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Descripción</a>
                        </th>
                        <th class="text-end">
                            <a href="?orden=cantidad&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Cantidad</a>
                        </th>
                        <th class="text-end">
                            <a href="?orden=precio&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Precio</a>
                        </th>
                        <?php if ($mostrar_dto1): ?>
                            <th class="text-end">
                                <a href="?orden=dto1&direccion=<?= $direccion_invertida;
                                   echo '&cod_cliente=' . urlencode($cod_cliente);
                                   echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                                   echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                                   echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                                   echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                                   echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                                   echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                                ?>">dto1 (%)</a>
                            </th>
                        <?php endif; ?>
                        <?php if ($mostrar_dto2): ?>
                            <th class="text-end">
                                <a href="?orden=dto2&direccion=<?= $direccion_invertida;
                                   echo '&cod_cliente=' . urlencode($cod_cliente);
                                   echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                                   echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                                   echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                                   echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                                   echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                                   echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                                ?>">dto2 (%)</a>
                            </th>
                        <?php endif; ?>
                        <th class="text-end">
                            <a href="?orden=importe&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Importe</a>
                        </th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th class="text-center">
                            <a href="?orden=cod_articulo&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                               echo '&agrupar=1';
                            ?>">Artículo</a>
                        </th>
                        <th class="text-start">
                            <a href="?orden=descripcion&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                               echo '&agrupar=1';
                            ?>">Descripción</a>
                        </th>
                        <th class="text-end">
                            <a href="?orden=cantidad&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                               echo '&agrupar=1';
                            ?>">Cantidad</a>
                        </th>
                        <th class="text-end">
                            <a href="?orden=precio&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Precio Medio</a>
                        </th>
                        <th class="text-end">
                            <a href="?orden=importe&direccion=<?= $direccion_invertida;
                               echo '&cod_cliente=' . urlencode($cod_cliente);
                               echo $cod_seccion ? '&cod_seccion=' . urlencode($cod_seccion) : '';
                               echo $solo_programa_nuevo ? '&solo_programa_nuevo=1' : '';
                               echo $fecha_desde ? '&fecha_desde=' . urlencode($fecha_desde) : '';
                               echo $fecha_hasta ? '&fecha_hasta=' . urlencode($fecha_hasta) : '';
                               echo $cod_articulo ? '&cod_articulo=' . urlencode($cod_articulo) : '';
                               echo $descripcion ? '&descripcion=' . urlencode($descripcion) : '';
                            ?>">Importe Total</a>
                        </th>
                    </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php foreach ($ventas_unificadas as $venta): 
                    // Usamos la clase table-danger de Bootstrap si la cantidad es menor que 0
                    $rowClass = (isset($venta['cantidad']) && floatval($venta['cantidad']) < 0) ? 'table-danger' : '';
                    if (isset($venta['descripcion']) && $venta['descripcion'] === 'Artículo eliminado') {
                        $rowClass .= ' row-eliminated';
                    }
                ?>
                    <?php if (!$agrupar): ?>
                        <tr class="<?= trim($rowClass); ?>">
                            <td class="text-center"><?= htmlspecialchars($venta['cod_venta']); ?></td>
                            <td class="text-center"><?= !empty($venta['fecha_venta']) ? date("d/m/Y", strtotime($venta['fecha_venta'])) : ''; ?></td>
                            <td class="text-center"><?= htmlspecialchars($venta['cod_articulo']); ?></td>
                            <td class="text-start">
                                <?= htmlspecialchars($venta['descripcion']); ?>
                                <?php if (!empty($venta['detalle'])): ?>
                                    <br>
                                    <span class="text-primary small"><?= htmlspecialchars($venta['detalle']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format($venta['cantidad'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?= number_format($venta['precio'], 2, ',', '.') . '&nbsp;'; ?></td>
                            <?php if ($mostrar_dto1): ?>
                                <td class="text-end"><?= (floatval($venta['dto1']) > 0) ? number_format($venta['dto1'], 2, ',', '.') . '%' : ''; ?></td>
                            <?php endif; ?>
                            <?php if ($mostrar_dto2): ?>
                                <td class="text-end"><?= (floatval($venta['dto2']) > 0) ? number_format($venta['dto2'], 2, ',', '.') . '%' : ''; ?></td>
                            <?php endif; ?>
                            <td class="text-end"><?= number_format($venta['importe'], 2, ',', '.') . '&nbsp;'; ?></td>
                        </tr>
                    <?php else: ?>
                        <tr class="<?= trim($rowClass); ?>">
                            <td class="text-center"><?= htmlspecialchars($venta['cod_articulo']); ?></td>
                            <td class="text-start"><?= htmlspecialchars($venta['descripcion']); ?></td>
                            <td class="text-end"><?= number_format($venta['cantidad'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?= number_format($venta['precio'], 2, ',', '.') . '&nbsp;'; ?></td>
                            <td class="text-end"><?= number_format($venta['importe'], 2, ',', '.') . '&nbsp;'; ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mt-3">
            <strong><?= $agrupar ? 'Artculos (agrupados)' : 'Ventas'; ?>:</strong> <?= $num_ventas; ?><br>
            <strong>Suma total:</strong> <?= number_format($suma_total, 2, ',', '.') . '&nbsp;'; ?>
        </div>
    <?php else: ?>
        <p>No se encontraron ventas para este cliente y sección.</p>
    <?php endif; ?>
    </div>

    <a href="cliente_detalles.php?cod_cliente=<?= urlencode($cod_cliente); ?>" class="btn btn-secondary mt-3">
       <i class="fas fa-arrow-left"></i> Volver al Cliente
    </a>

</div>

<!-- Scripts para el slider de fechas (local via Composer assets) -->
<script src="<?= BASE_URL ?>/assets/vendor/jquery/jquery.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/nouislider/nouislider.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/wnumb/wNumb.min.js"></script>
<script>
$(function(){
    var slider = document.getElementById('date-slider');
    var minTimestamp = Date.parse("2023-01-01") / 1000;
    var maxTimestamp = Date.now() / 1000;

    var today = new Date();
    var oneYearAgo = new Date();
    oneYearAgo.setFullYear(today.getFullYear() - 1);

    var initialStart = $("#fecha_desde").val() ? Date.parse($("#fecha_desde").val())/1000 : oneYearAgo.getTime()/1000;
    var initialEnd   = $("#fecha_hasta").val() ? Date.parse($("#fecha_hasta").val())/1000 : today.getTime()/1000;

    noUiSlider.create(slider, {
        start: [initialStart, initialEnd],
        connect: true,
        range: { 'min': minTimestamp, 'max': maxTimestamp },
        step: 86400,
        tooltips: [
            wNumb({ decimals: 0, edit: function(value){ return new Date(value*1000).toLocaleDateString(); } }),
            wNumb({ decimals: 0, edit: function(value){ return new Date(value*1000).toLocaleDateString(); } })
        ]
    });

    slider.noUiSlider.on('change', function(values, handle){
        $("#fecha_desde").val(new Date(parseFloat(values[0])*1000).toISOString().slice(0,10));
        $("#fecha_hasta").val(new Date(parseFloat(values[1])*1000).toISOString().slice(0,10));
        $("#filtrosForm").submit();
    });
});
</script>
<!-- Bootstrap JS -->
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Cerrar conexión
?>



