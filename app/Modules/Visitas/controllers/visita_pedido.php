<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Support/pedidos_badges.php';
requierePermiso('perm_planificador');

$conn = db();

$pageTitle = "Relacionar Pedidos con Visitas";

$ui_version = 'bs5';
$ui_requires_jquery = false;

$codigo_vendedor = 0;
if (isset($_SESSION['codigo'])) {
  $codigo_vendedor = intval($_SESSION['codigo']);
}

$fecha_minima = '2025-01-01';

$sql_pedidos = "
SELECT 
    h.tipo_venta,
    h.cod_empresa,
    h.cod_caja,
    h.cod_venta,
    h.cod_cliente,
    h.cod_seccion,
    c.nombre_comercial,
    h.nombre_seccion,
    h.fecha_venta,
    h.hora_venta,
    h.importe,
    a.observacion_interna,
    h.cod_pedido_web,
    h.cod_vendedor,
    h.nombre_vendedor,
    h.cod_comisionista,
    cazc.tiempo_promedio_visita
FROM hist_ventas_cabecera h
JOIN clientes c ON h.cod_cliente = c.cod_cliente
LEFT JOIN anexo_ventas_cabecera a ON h.cod_anexo = a.cod_anexo
LEFT JOIN cmf_comerciales_visitas_pedidos vp ON h.cod_venta = vp.cod_venta
LEFT JOIN cmf_comerciales_clientes_zona cazc 
    ON h.cod_cliente = cazc.cod_cliente AND h.cod_seccion = cazc.cod_seccion
WHERE vp.cod_venta IS NULL
  AND h.cod_comisionista = $codigo_vendedor
  AND h.tipo_venta = 1
  AND h.fecha_venta >= '$fecha_minima'
ORDER BY 
    h.fecha_venta ASC,
    h.hora_venta ASC
";

$res_pedidos = odbc_exec($conn, $sql_pedidos);
if (!$res_pedidos) {
  error_log("Error al ejecutar la consulta de pedidos: " . odbc_errormsg($conn));
  echo 'Error interno';
  return;
}

$pedidos = array();
$cod_ventas = array();

while ($row = odbc_fetch_array($res_pedidos)) {
  if (empty($row['tiempo_promedio_visita'])) {
    $tiempo_promedio_min = 60;
  } else {
    $tiempo_promedio_min = floatval($row['tiempo_promedio_visita']) * 60;
  }

  $row['tiempo_promedio_min'] = $tiempo_promedio_min;
  $pedidos[] = $row;
  $cod_ventas[] = $row['cod_venta'];
}

odbc_free_result($res_pedidos);

$numero_lineas_map = array();

if (!empty($cod_ventas)) {
  $cod_ventas_esc = array();
  foreach ($cod_ventas as $cv) {
    $cod_ventas_esc[] = intval($cv);
  }

  $cod_ventas_str = implode(',', $cod_ventas_esc);

  $sql_lineas = "
    SELECT cod_venta, COUNT(*) AS numero_lineas
    FROM hist_ventas_linea
    WHERE tipo_venta = 1
      AND cod_venta IN ($cod_ventas_str)
    GROUP BY cod_venta
    ";

  $res_lineas = odbc_exec($conn, $sql_lineas);
  if (!$res_lineas) {
    error_log("Error al ejecutar la consulta de lÃ­neas: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
  }

  while ($row = odbc_fetch_array($res_lineas)) {
    $numero_lineas_map[$row['cod_venta']] = $row['numero_lineas'];
  }

  odbc_free_result($res_lineas);
}

function formatoFecha($fechaSql)
{
  return date('Y-m-d', strtotime($fechaSql));
}

function formatoHora($horaSql)
{
  return date('H:i', strtotime($horaSql));
}

require_once BASE_PATH . '/app/Modules/Visitas/views/visita_pedido.php';
