<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
ini_set('error_log', BASE_PATH . '/storage/logs/php_debug.log');

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
header('Content-Type: text/html; charset=utf-8');

// Si el usuario no ha iniciado sesión


// Definir el título de la página (usa el que se haya pasado, o uno por defecto)
if (!isset($pageTitle)) {
    $pageTitle = "Panel de " . ($_SESSION['nombre'] ?? 'Usuario');
}

// Conexión a la base de datos

// Incluir funciones comunes
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

// Consulta para contar los pedidos abiertos (históricos)
// Comentario saneado para evitar basura de encoding en esta sección.

$codigoSesion = null;
if (isset($_SESSION['codigo']) && $_SESSION['codigo'] !== '' && $_SESSION['codigo'] !== null) {
    $tmpCodigo = intval($_SESSION['codigo']);
    if ($tmpCodigo > 0) {
        $codigoSesion = $tmpCodigo;
    }
}

$whereCodComisionista = "";
if ($codigoSesion !== null) {
    $whereCodComisionista = " AND cod_comisionista = $codigoSesion";
}

$query_pedidos_abiertos = "
    SELECT COUNT(*) AS total
    FROM hist_ventas_cabecera
    WHERE tipo_venta = 1
      AND historico = 'N'
      AND CONVERT(date, fecha_venta) <= DATEADD(day, -7, GETDATE())
      $whereCodComisionista
      AND cod_venta NOT IN (
          SELECT cod_venta
          FROM cmf_solicitudes_pedido
          WHERE tipo_solicitud = 'Historico'
      )
";


$result_pedidos_abiertos = odbc_exec($conn, $query_pedidos_abiertos);
$count_pedidos_abiertos = 0;
if ($result_pedidos_abiertos && odbc_fetch_row($result_pedidos_abiertos)) {
    $count_pedidos_abiertos = odbc_result($result_pedidos_abiertos, "total");
}

// Contar pedidos cerrados con importe pendiente > 70 EUR (incluye eliminados), ultimos 15 dias.
$whereCodComisionistaElim = "";
if ($codigoSesion !== null) {
    $whereCodComisionistaElim = " AND vcelim.cod_comisionista = $codigoSesion";
}
$query_pedidos_cerrados_70 = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT
            hvl.cod_venta,
            SUM(
                CASE
                    WHEN elv.cod_venta_origen IS NULL THEN hvl.cantidad * hvl.precio
                    ELSE (hvl.cantidad - ISNULL(elv.cantidad_servida, 0)) * hvl.precio
                END
            ) AS importe_pendiente
        FROM hist_ventas_linea hvl
        INNER JOIN hist_ventas_cabecera hvc
            ON hvc.cod_venta = hvl.cod_venta
           AND hvc.tipo_venta = 1
        LEFT JOIN (
            SELECT
                cod_venta_origen,
                linea_origen,
                SUM(cantidad) AS cantidad_servida
            FROM entrega_lineas_venta
            WHERE tipo_venta_origen = 1
            GROUP BY cod_venta_origen, linea_origen
        ) elv
            ON hvl.cod_venta = elv.cod_venta_origen
           AND hvl.linea = elv.linea_origen
        WHERE hvl.tipo_venta = 1
          AND hvc.historico = 'S'
          AND (hvl.cantidad > ISNULL(elv.cantidad_servida, 0))
          AND CONVERT(date, hvc.fecha_venta) >= DATEADD(day, -15, CONVERT(date, GETDATE()))
          AND CONVERT(date, hvc.fecha_venta) <= CONVERT(date, GETDATE())
          $whereCodComisionista
        GROUP BY hvl.cod_venta
        HAVING SUM(
            CASE
                WHEN elv.cod_venta_origen IS NULL THEN hvl.cantidad * hvl.precio
                ELSE (hvl.cantidad - ISNULL(elv.cantidad_servida, 0)) * hvl.precio
            END
        ) > 70
        UNION
        SELECT
            vlelim.cod_venta,
            SUM(
                CASE
                    WHEN elv2.cod_venta_origen IS NULL THEN vlelim.cantidad * vlelim.precio
                    ELSE (vlelim.cantidad - ISNULL(elv2.cantidad_servida, 0)) * vlelim.precio
                END
            ) AS importe_pendiente
        FROM ventas_linea_elim vlelim
        INNER JOIN ventas_cabecera_elim vcelim
            ON vcelim.cod_venta = vlelim.cod_venta
           AND vcelim.tipo_venta = 1
        LEFT JOIN (
            SELECT
                cod_venta_origen,
                linea_origen,
                SUM(cantidad) AS cantidad_servida
            FROM entrega_lineas_venta
            WHERE tipo_venta_origen = 1
            GROUP BY cod_venta_origen, linea_origen
        ) elv2
            ON vlelim.cod_venta = elv2.cod_venta_origen
           AND vlelim.linea = elv2.linea_origen
        WHERE vlelim.tipo_venta = 1
          AND (vlelim.cantidad > ISNULL(elv2.cantidad_servida, 0))
          AND CONVERT(date, vcelim.fecha_venta) >= DATEADD(day, -15, CONVERT(date, GETDATE()))
          AND CONVERT(date, vcelim.fecha_venta) <= CONVERT(date, GETDATE())
          $whereCodComisionistaElim
        GROUP BY vlelim.cod_venta
        HAVING SUM(
            CASE
                WHEN elv2.cod_venta_origen IS NULL THEN vlelim.cantidad * vlelim.precio
                ELSE (vlelim.cantidad - ISNULL(elv2.cantidad_servida, 0)) * vlelim.precio
            END
        ) > 70
    ) t
";
$result_pedidos_cerrados_70 = odbc_exec($conn, $query_pedidos_cerrados_70);
$count_pedidos_cerrados_70 = 0;
if ($result_pedidos_cerrados_70 && odbc_fetch_row($result_pedidos_cerrados_70)) {
    $count_pedidos_cerrados_70 = (int)odbc_result($result_pedidos_cerrados_70, "total");
}

// Contar pedidos sin asignar a visita (pendientes de gestionar en planificador).
$whereCodComisionistaPlan = "";
if ($codigoSesion !== null) {
    $whereCodComisionistaPlan = " AND h.cod_comisionista = $codigoSesion";
}
$query_pedidos_sin_visita = "
    SELECT COUNT(*) AS total
    FROM hist_ventas_cabecera h
    LEFT JOIN cmf_visita_pedidos vp ON h.cod_venta = vp.cod_venta
    WHERE vp.cod_venta IS NULL
      AND h.tipo_venta = 1
      AND h.fecha_venta >= '2025-01-01'
      $whereCodComisionistaPlan
";
$result_pedidos_sin_visita = odbc_exec($conn, $query_pedidos_sin_visita);
$count_pedidos_sin_visita = 0;
if ($result_pedidos_sin_visita && odbc_fetch_row($result_pedidos_sin_visita)) {
    $count_pedidos_sin_visita = (int)odbc_result($result_pedidos_sin_visita, "total");
}



// Establecer la fecha de consulta (ejemplo: ?fecha=2025-02-22)
$fechaConsulta = $_GET['fecha'] ?? date('Y-m-d');

// Variables de control
$isJcasado = (
    (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') ||
    (isset($_SESSION['es_admin']) && (int)$_SESSION['es_admin'] === 1)
);
$layoutClass = $isJcasado ? 'layout-jcasado' : 'layout-normal';
$cod_vendedor_session = $codigoSesion;
$esAdminSession = (
    (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') ||
    (isset($_SESSION['es_admin']) && (int)$_SESSION['es_admin'] === 1)
);
$esPremiumSession = isset($_SESSION['tipo_plan']) && $_SESSION['tipo_plan'] === 'premium';
$puedeVerProductos = $esAdminSession || (isset($_SESSION['perm_productos']) && (int)$_SESSION['perm_productos'] === 1);
$puedeVerPlanificador = $esAdminSession || ($esPremiumSession && (isset($_SESSION['perm_planificador']) && (int)$_SESSION['perm_planificador'] === 1));
$puedeVerEstadisticas = $esAdminSession || (isset($_SESSION['perm_estadisticas']) && (int)$_SESSION['perm_estadisticas'] === 1);
$puedeEditarOrigenPedido = (
    (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') ||
    (isset($_SESSION['tipo_plan']) && $_SESSION['tipo_plan'] === 'premium')
);

/* ===========================================================================
   CONSULTA 1: PEDIDOS DE HOY
   =========================================================================== */
$whereCodPedidos = ($cod_vendedor_session === null) ? "1=1" : "hvc.cod_comisionista = $cod_vendedor_session";
$orderPedidos = ($cod_vendedor_session === null)
    ? "hvc.cod_comisionista ASC, cl.nombre_comercial ASC, hvc.cod_venta ASC"
    : "cl.nombre_comercial ASC, hvc.cod_venta ASC";

$sql_pedidos_hoy = "
  SELECT
    hvc.cod_comisionista,
    (SELECT nombre FROM vendedores WHERE cod_vendedor = hvc.cod_comisionista) AS nombre_comisionista,
    hvc.cod_empresa,
    hvc.cod_caja,
    hvc.cod_venta,
    cl.cod_cliente,
    cl.nombre_comercial,
    hvc.importe AS importe,
    hvc.historico,
    hvc.cod_vendedor,
    v.nombre AS nombre_vendedor,
    hvc.cod_pedido_web,
    hvc.hora_venta,
    hvc.cod_seccion,
    sc.nombre AS nombre_seccion,
    a.observacion_interna,
    hvc.preparar_pedido,
    elv.prepared,
    (SELECT COUNT(*)
     FROM hist_ventas_linea hvl
     WHERE hvl.cod_venta = hvc.cod_venta
       AND hvl.tipo_venta = 1
    ) AS num_lineas_pedido
  FROM hist_ventas_cabecera hvc
  JOIN clientes cl ON hvc.cod_cliente = cl.cod_cliente
  LEFT JOIN vendedores v ON hvc.cod_vendedor = v.cod_vendedor
  LEFT JOIN secciones_cliente sc 
         ON hvc.cod_cliente = sc.cod_cliente AND hvc.cod_seccion = sc.cod_seccion
  LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] a 
         ON hvc.cod_venta = a.cod_venta AND a.tipo_venta = 1
  LEFT JOIN (
      SELECT cod_venta_origen, 1 AS prepared
      FROM [integral].[dbo].[entrega_lineas_venta]
      WHERE tipo_venta_origen = 1
      GROUP BY cod_venta_origen
  ) elv ON hvc.cod_venta = elv.cod_venta_origen
  WHERE hvc.tipo_venta = 1
    AND $whereCodPedidos
    AND CONVERT(date, hvc.fecha_venta) = '$fechaConsulta'
  ORDER BY 
    CASE 
      WHEN hvc.cod_pedido_web IS NOT NULL AND LTRIM(RTRIM(hvc.cod_pedido_web)) <> '' THEN 0
      WHEN a.observacion_interna IS NOT NULL AND LOWER(LTRIM(RTRIM(a.observacion_interna))) LIKE '%urgente%' THEN 1
      ELSE 2
    END ASC,
    $orderPedidos
";
$result_pedidos = odbc_exec($conn, $sql_pedidos_hoy);
$pedidosHoy = [];
if ($result_pedidos) {
    while ($row = odbc_fetch_array($result_pedidos)) {
        $pedidosHoy[] = $row;
    }
}

/* ===========================================================================
   CONSULTA 2: ALBARANES DE HOY
   =========================================================================== */
$whereCodAlbaranes = ($cod_vendedor_session === null) ? "1=1" : "hvc.cod_comisionista = $cod_vendedor_session";
$orderAlbaranes = ($cod_vendedor_session === null)
    ? "hvc.cod_comisionista ASC, 
       CASE 
         WHEN hvc.cod_comisionista IS NULL OR hvc.cod_comisionista = 0 THEN NULL 
         ELSE cl.nombre_comercial 
       END ASC, 
       hvc.hora_venta ASC"
    : "cl.nombre_comercial ASC, hvc.hora_venta ASC";

$sql_albaranes_hoy = "
  SELECT
    CASE 
       WHEN hvc.cod_comisionista IS NULL OR hvc.cod_comisionista = 0 THEN hvc.cod_vendedor 
       ELSE hvc.cod_comisionista 
    END AS grupoVenta,
    CASE 
       WHEN hvc.cod_comisionista IS NULL OR hvc.cod_comisionista = 0 
         THEN (SELECT nombre FROM vendedores WHERE cod_vendedor = hvc.cod_vendedor)
       ELSE (SELECT nombre FROM vendedores WHERE cod_vendedor = hvc.cod_comisionista)
    END AS nombreGrupo,
    hvc.cod_empresa,
    hvc.cod_caja,
    hvc.cod_venta,
    cl.cod_cliente,
    cl.nombre_comercial,
    hvc.importe AS importe_total,
    hvc.hora_venta,
    hvc.cod_seccion,
    sc.nombre AS nombre_seccion,
    hvc.cod_vendedor,
    v.nombre AS nombre_vendedor,
    hvc.tipo_venta,
    a.observacion_interna,
    a.observacion_externa,
    (SELECT COUNT(*)
     FROM hist_ventas_linea hvl
     WHERE hvl.cod_venta = hvc.cod_venta
       AND hvl.tipo_venta = hvc.tipo_venta
    ) AS num_lineas_albaran
  FROM hist_ventas_cabecera hvc
  JOIN clientes cl ON hvc.cod_cliente = cl.cod_cliente
  LEFT JOIN vendedores v ON hvc.cod_vendedor = v.cod_vendedor
  LEFT JOIN secciones_cliente sc 
         ON hvc.cod_cliente = sc.cod_cliente AND hvc.cod_seccion = sc.cod_seccion
  LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] a
         ON hvc.cod_venta = a.cod_venta AND a.tipo_venta = hvc.tipo_venta
  WHERE hvc.tipo_venta IN (2, 4)
    AND $whereCodAlbaranes
    AND CONVERT(date, hvc.fecha_venta) = '$fechaConsulta'
  ORDER BY $orderAlbaranes
";
$result_albaranes = odbc_exec($conn, $sql_albaranes_hoy);
$albaranesHoy = [];
if ($result_albaranes) {
    while ($row = odbc_fetch_array($result_albaranes)) {
        $albaranesHoy[] = $row;
    }
}

/* ===========================================================================
   CONSULTA 3: VISITAS DE HOY (solo para $isJcasado)
   =========================================================================== */
$visitasHoy = [];
if ($isJcasado) {
    $sql_visitas_hoy = "
      SELECT
        cvc.id_visita,
        cvc.cod_cliente,
        cvc.cod_seccion,
        cvc.fecha_visita,
        cvc.hora_inicio_visita,
        cvc.hora_fin_visita,
        cvc.estado_visita,
        cvc.observaciones,
        cl.nombre_comercial,
        sc.nombre AS nombre_seccion,
        MAX(CASE WHEN LOWER(vp.origen) = 'visita' THEN 1 ELSE 0 END) as tiene_visita,
        (SELECT SUM(hvc.importe)
           FROM hist_ventas_cabecera hvc
           WHERE hvc.cod_venta IN (
                 SELECT cod_venta 
                 FROM [integral].[dbo].[cmf_visita_pedidos]
                 WHERE id_visita = cvc.id_visita AND tipo_venta=1
           )
        ) as total_importe_pedidos,
        (SELECT COUNT(*) 
           FROM [integral].[dbo].[hist_ventas_linea] hvl
           WHERE hvl.cod_venta IN (
                 SELECT cod_venta 
                 FROM [integral].[dbo].[cmf_visita_pedidos]
                 WHERE id_visita = cvc.id_visita
           )
           AND hvl.tipo_venta = 1
        ) as num_lineas_pedidos
      FROM [integral].[dbo].[cmf_visitas_comerciales] cvc
      JOIN [integral].[dbo].[clientes] cl ON cvc.cod_cliente = cl.cod_cliente
      LEFT JOIN [integral].[dbo].[secciones_cliente] sc 
             ON cvc.cod_cliente = sc.cod_cliente AND cvc.cod_seccion = sc.cod_seccion
      LEFT JOIN [integral].[dbo].[cmf_visita_pedidos] vp 
             ON cvc.id_visita = vp.id_visita
      WHERE cvc.cod_vendedor = $cod_vendedor_session
        AND CONVERT(date, cvc.fecha_visita) = '$fechaConsulta'
      GROUP BY cvc.id_visita, cvc.cod_cliente, cvc.cod_seccion, cvc.fecha_visita, cvc.hora_inicio_visita, cvc.hora_fin_visita,
               cvc.estado_visita, cvc.observaciones, cl.nombre_comercial, sc.nombre
      ORDER BY cvc.hora_inicio_visita ASC
    ";
    $result_visitas = odbc_exec($conn, $sql_visitas_hoy);
    if ($result_visitas) {
        while ($row = odbc_fetch_array($result_visitas)) {
            $visitasHoy[] = $row;
        }
    }
}

/* ===========================================================================
   CONSULTA 4: Comentario saneado
   =========================================================================== */
$diasNoLaborables = [];
if ($isJcasado) {
    $sql_no_laborables = "
      SELECT 
        id,
        cod_vendedor,
        fecha,
        hora_inicio,
        hora_fin,
        tipo_evento,
        descripcion,
        repetir_anualmente
      FROM [integral].[dbo].[cmf_dias_no_laborables]
      WHERE cod_vendedor = $cod_vendedor_session
        AND (
             (repetir_anualmente = 0 AND CONVERT(date, fecha) = '$fechaConsulta')
             OR (repetir_anualmente = 1 
                AND MONTH(fecha) = MONTH('$fechaConsulta') 
                AND DAY(fecha) = DAY('$fechaConsulta'))
        )
      ORDER BY hora_inicio ASC
    ";
    $result_no_laborables = odbc_exec($conn, $sql_no_laborables);
    if ($result_no_laborables) {
        while ($row = odbc_fetch_array($result_no_laborables)) {
            $diasNoLaborables[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <!-- 
       ESTILOS: Mantiene la sidebar al 20%, y si es layout-normal => 2 columnas de 40%;
                si es layout-jcasado => 3 columnas (c. 26.66%) para escritorio.
       Además, se mantiene el comportamiento actual para escritorio.
       Scroll normal en móvil.
  -->
  <style type="text/css">
    /* ==== Reseteo general ==== */
    * {
      box-sizing: border-box;
    }
    html, body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background: #ffffff;
    }
    .app-container {
      max-width: none;
      width: 100%;
      margin: 0 auto;
      padding-left: 12px;
      padding-right: 12px;
    }

    /* ==== Header (o .header, según header.php) ==== */
    header, .header {
      background-color: #fff; 
      /* Ajusta si quieres un header fijo en escritorio (ver media query) */
    }

    /* ==== Contenedor principal ==== */
    .main-content {
      display: flex;
      flex-direction: column;
      min-width: 0;
      overflow: hidden;
    }

    /* ==== Columnas ==== */
    .column {
      padding: 15px;
      background: #f4f4f4;
      border-right: 1px solid #ddd;
    }
    .column:last-child {
      border-right: none;
    }

    /* ========== BOTONES, GROUPS, ETC. ========== */
    .btn {
      display: flex; 
      flex-direction: column; 
      align-items: center; 
      justify-content: center; 
      height: 85px; 
      padding: 8px; 
      font-size: 16px; 
      font-weight: bold; 
      border: none; 
      border-radius: 8px; 
      cursor: pointer; 
      color: #fff; 
      background-color: #007bff; 
      margin-bottom: 8px; 
      text-decoration: none;
    }
    .btn:hover {
      opacity: 0.9;
    }
    .btn i {
      font-size: 40px;
      margin-bottom: 4px;
    }
    .btn-productos    { background-color: #0374ff; }    
    .btn-clientes     { background-color: #6c757d; }
    .btn-faltas       { background-color: #dc3545; }
    .btn-pedidos      { background-color: #ffc107; }
    .btn-planificador { background-color: #28a745; }
    .btn-estadisticas { background-color: #3fb8af; }
    .btn-nuevocliente { background-color: #2980b9; }
    .mobile-menu-toggle {
      display: none;
      width: 56px;
      height: 56px;
      margin: 0;
      border: none;
      border-radius: 50%;
      padding: 0;
      font-size: 20px;
      font-weight: bold;
      color: #fff;
      background: #495057;
      box-shadow: 0 8px 20px rgba(0,0,0,0.28);
    }
    .mobile-menu-toggle i {
      margin: 0;
    }
    .mobile-menu-backdrop {
      display: none;
    }
    .mobile-appbar {
      display: none;
    }

    .badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background-color: red;
    color: white;
    border-radius: 50%;
    padding: 8px 12px; /* Tamaño consistente */
    font-size: 14px;
    font-weight: bold;
    box-shadow: 0 0 5px rgba(0,0,0,0.3);
    }




    .group-header {
      background-color: #eee;
      padding: 8px;
      cursor: pointer;
      font-weight: bold;
      border: 1px solid #ccc;
      margin-bottom: 5px;
    }
    .group-content {
      padding-left: 10px;
      margin-bottom: 10px;
    }
    .content-column h3 {
      text-align: center;
      margin: 0 0 15px 0;
      padding: 10px 0;
    }

    .item-box {
      position: relative;
      padding: 10px;
      margin-bottom: 10px;
      background: #fff;
      cursor: pointer;
    }
    .item-box-link {
      display: block;
      color: inherit;
      text-decoration: none;
    }
    .item-box-link:hover,
    .item-box-link:focus,
    .item-box-link:visited {
      color: inherit;
      text-decoration: none;
    }
    .item-box:hover {
      background: #f1f1f1;
    }
    .item-box i {
      margin-right: 6px;
    }
    .item-title {
      font-size: 14px;
      font-weight: bold;
      margin-bottom: 4px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    /* Para visitas, a veces usas .item-title-visita */
    .item-title-visita {
      font-size: 12px;
      font-weight: bold;
      margin-bottom: 4px;
      display: flex;
      align-items: center;
    }
    .item-subtitle {
      font-size: 12px;
      color: #666;
    }
    .importe-box {
      font-size: 12px;
      text-align: left;
    }
    .origen-quick {
      margin-top: 6px;
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .origen-quick button {
      border: none;
      border-radius: 4px;
      padding: 3px 6px;
      color: #fff;
      font-size: 11px;
      cursor: pointer;
      line-height: 1.2;
    }
    .origen-quick button[disabled] {
      background: #8c8c8c !important;
      cursor: default;
      opacity: 0.9;
    }
    .no-data {
      text-align: center;
      font-style: italic;
      color: #999;
    }
    .total-general {
      font-weight: bold;
      margin-top: 12px;
      padding-bottom: 10px;
    }

    /* ======= MODALES ======= */
    #modal-pedido,
    #modal-albaran,
    #modal-visita {
      display: none;
      position: fixed;
      z-index: 9999;
      left: 0; 
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      overflow: auto;
    }
    #modal-pedido .modal-content,
    #modal-albaran .modal-content,
    #modal-visita .modal-content {
      background-color: #fff;
      margin: 5% auto;
      padding: 20px;
      width: 90%;
      max-width: 800px;
      border-radius: 8px;
      position: relative;
    }
    #modal-pedido .close-modal,
    #modal-albaran .close-modal,
    #modal-visita .close-modal {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 24px;
      cursor: pointer;
    }
    #modal-pedido .modal-header,
    #modal-albaran .modal-header,
    #modal-visita .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    .btn-client {
      display: inline-block;
      padding: 10px 15px;
      background-color: #f00f28;
      color: #fff;
      text-decoration: none;
      border-radius: 4px;
      font-size: 14px;
    }
    .btn-client i {
      margin-right: 5px;
    }

    .sidebar .buttons-container {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .sidebar .buttons-container > .btn {
      width: 100%;
      margin-bottom: 0;
    }

    /* ==== Tablas ==== */
    .table-container {
      width: 100%;
      overflow-x: auto;
      margin-top: 20px;
      border-radius: 8px;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 16px;
    }
    th, td {
      padding: 15px;
      text-align: left;
      border: 1px solid #ddd;
    }
    th {
      background-color: #007BFF;
      color: #fff;
      white-space: nowrap;
    }
    th a {
      color: #fff;
      text-decoration: none;
    }
    td a {
      color: #000;
      text-decoration: none;
    }
    td a:hover {
      color: #007BFF;
    }

    /* ==== Paginación ==== */
    .pagination {
      margin-top: 20px;
      text-align: center;
    }
    .pagination a, .pagination span {
      margin: 0 5px;
      padding: 8px 12px;
      border: 1px solid #ddd;
      color: #007BFF;
      text-decoration: none;
      border-radius: 4px;
    }
    .pagination a:hover {
      background-color: #f0f0f0;
    }
    .pagination .current {
      background-color: #007BFF;
      color: #fff;
      border-color: #007BFF;
    }

    .panel-toolbar {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 18px;
      flex-wrap: wrap;
      padding: 20px 0;
      margin-bottom: 8px;
      background: transparent;
      text-align: center;
    }

    .panel-toolbar h2 {
      font-size: 22px;
      font-weight: 600;
      margin: 0;
    }

    .panel-toolbar .date-wrapper {
      display: flex;
      align-items: center;
    }

    .panel-toolbar input[type="date"] {
      padding: 6px 10px;
      border-radius: 6px;
      border: 1px solid #d0d5dd;
      background: #f8f9fb;
      font-size: 14px;
    }
      .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
      overflow: hidden;
    }

    .dashboard-columns {
      display: flex;
      flex: 1;
      min-width: 0;
      overflow: hidden;
    }

    /* ============================= */
    /* ======== MEDIA QUERIES ===== */
    /* ============================= */

    /* === Móvil y tablet === */
@media (max-width: 1024px) {
  /* Permitimos scroll normal en <body> o lo dejas oculto, según tu preferencia */
  html, body {
    overflow-x: hidden;
    overflow-y: auto;
    height: auto;
  }
  /* El header sigue fijo */
  header, .header {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 60px; /* Ajusta según tu header */
    z-index: 9999;
  }
  .page-container {
    margin-top: 60px;
    display: block;
    padding: 0 10px;
    position: relative;
    height: auto;
    padding-bottom: 84px;
  }
  .column {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid #ddd;
    overflow: visible; /* O 'auto' si quieres scroll en cada columna */
  }
  .column:last-child {
    border-bottom: none;
  }
  .sidebar .mobile-menu-toggle {
    display: none !important;
  }
  .sidebar .buttons-container {
    display: none !important;
  }
  .sidebar .mobile-menu-backdrop {
    display: none !important;
  }
  .main-content {
    display: block;
    overflow: visible;
  }
  .dashboard-columns {
    display: block;
    overflow: visible;
  }
  .mobile-appbar {
    display: flex;
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10040;
    background: #ffffff;
    border-top: 1px solid #ddd;
    box-shadow: 0 -4px 14px rgba(0,0,0,0.12);
    overflow: visible;
    flex-wrap: nowrap;
    justify-content: space-around;
    align-items: center;
    gap: 0;
    padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
  }
  .mobile-appbar .app-btn {
    min-width: 46px;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    text-decoration: none;
    color: #fff;
    border-radius: 50%;
    padding: 0;
    text-align: center;
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .mobile-appbar .app-btn i {
    display: block;
    font-size: 20px;
    margin-bottom: 0;
  }
  .mobile-appbar .app-btn span {
    display: none;
  }
  .mobile-appbar .app-btn .badge {
    display: inline-block;
    position: absolute;
    z-index: 2;
    border-radius: 999px;
    background: #dc3545;
    color: #fff;
    font-weight: 700;
    line-height: 1.2;
    top: -2px;
    right: -2px;
    padding: 1px 5px;
    font-size: 10px;
  }
  .mobile-appbar .app-btn.app-productos    { background-color: #0374ff; }
  .mobile-appbar .app-btn.app-clientes     { background-color: #6c757d; }
  .mobile-appbar .app-btn.app-cerrados     { background-color: #dc3545; }
  .mobile-appbar .app-btn.app-abiertos     { background-color: #ffc107; color: #fff; }
  .mobile-appbar .app-btn.app-planificador { background-color: #28a745; }
  .mobile-appbar .app-btn.app-estadisticas { background-color: #3fb8af; }
  .mobile-appbar .app-btn.app-nuevo        { background-color: #2980b9; }
  .mobile-appbar .app-btn.app-abiertos i,
  .mobile-appbar .app-btn.app-abiertos span {
    color: #fff;
  }

}

    /* === TABLET HORIZONTAL (768-1024): panel fijo y scroll en contenido === */
    @media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
      .page-container {
        height: calc(100vh - 60px);
        height: calc(100dvh - 60px);
        box-sizing: border-box;
        overflow: hidden;
      }
      .main-content {
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #f7f8fa;
      }
      .panel-toolbar {
        position: sticky;
        top: 0;
        z-index: 40;
        margin-bottom: 0;
        padding: 12px 0;
        background: #f7f8fa;
        border-bottom: 1px solid #dcdfe5;
      }
      .dashboard-columns {
        display: flex;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
        padding-top: 12px;
      }
      .dashboard-columns.layout-normal .content-column {
        width: 50% !important;
      }
      .dashboard-columns.layout-jcasado .content-column {
        width: calc(100% / 3) !important;
      }
      .dashboard-columns .content-column {
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
      }
      .content-column h3 {
        margin: 0;
        background: #f7f8fa;
      }
      .content-column .column-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding-bottom: calc(150px + env(safe-area-inset-bottom));
      }
      .content-column .column-scroll::after {
        content: "";
        display: block;
        height: calc(70px + env(safe-area-inset-bottom));
      }
      .content-column .column-scroll .total-general {
        margin-bottom: calc(150px + env(safe-area-inset-bottom));
      }
      .content-column .group-header {
        position: sticky;
        top: 0;
        z-index: 20;
        background: #f1f3f5;
      }
    }

    /* === ESCRITORIO (> 1024px): sidebar fija, scroll solo en contenido derecho === */
    @media (min-width: 1025px) {
      header, .header {
        position: fixed;
        top: 0; left: 0; right: 0;
        height: 60px;
        z-index: 9999;
      }
      .page-container {
        position: relative;
        margin-top: 60px;
        width: 100%;
        height: calc(100vh - 60px);
        height: calc(100dvh - 60px);
        overflow: hidden;
      }
      .page-container.layout-normal .sidebar,
      .page-container.layout-jcasado .sidebar {
        position: fixed;
        top: 60px;
        left: 0;
        width: 14% !important;
        height: calc(100vh - 60px);
        height: calc(100dvh - 60px);
        overflow: hidden;
      }
      .page-container > .app-container {
        margin-left: 14%;
        width: 86%;
        max-width: none;
        height: 100%;
      }
      .main-content {
        height: 100%;
        overflow: hidden;
      }
      .dashboard-columns {
        height: 100%;
        overflow: hidden;
        flex: 1 1 auto;
      }
      .dashboard-columns.layout-normal .content-column {
        width: 50% !important;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
      }
      .dashboard-columns.layout-jcasado .content-column {
        width: calc(100% / 3) !important;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
      }
      .content-column h3 {
        margin: 0;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 8px;
        background: #f7f8fa;
        border-bottom: 1px solid #dcdfe5;
      }
      .content-column .column-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding: 12px 0 24px 0;
      }
      .content-column .column-scroll .total-general {
        margin-bottom: 16px;
      }
      .content-column .group-header {
        position: sticky;
        top: 0;
        z-index: 20;
        background: #f1f3f5;
      }
    }

    /* === FIX SCROLL COLUMNAS DASHBOARD === */
    .main-content,
    .dashboard-columns,
    .content-column {
      min-height: 0;
    }

    /* Tablet/movil: sin scroll interno en columnas */
    @media (max-width: 1024px) {
      .main-content,
      .dashboard-columns,
      .content-column,
      .column {
        overflow: visible;
      }

      .content-column h3,
      .content-column .group-header {
        position: static;
        top: auto;
        z-index: auto;
      }
      .content-column .column-scroll {
        overflow: visible;
        max-height: none;
        padding-bottom: 0;
      }
      .content-column .column-scroll::after {
        content: none;
      }
      .content-column .column-scroll .total-general {
        margin-bottom: 0;
      }
    }

    /* Escritorio: scroll interno solo en el contenedor correcto */
    @media (min-width: 1025px) {
      .main-content {
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
      }
      .dashboard-columns {
        display: flex;
        flex: 1 1 auto;
        min-height: 0;
        overflow: visible;
      }
      .dashboard-columns .content-column {
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: visible;
      }
      .content-column h3 {
        position: sticky;
        top: 0;
        z-index: 50;
        background: #f7f8fa;
      }

      .content-column .column-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        position: relative;
        isolation: isolate;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        scroll-padding-top: 8px;
      }

      .content-column .group-header {
        position: sticky;
        top: 0;
        z-index: 80;
        background-color: #f1f3f5;
        background-clip: padding-box;
      }

      .content-column .item-box {
        position: relative;
        z-index: 0;
      }
    }

    @media (min-width: 1025px) {
      .content-column .group-content {
        display: flow-root;
        margin-top: 0;
        padding-top: 0;
      }

      .content-column .group-header + .group-content > .item-box:first-child {
        margin-top: 0 !important;
      }

      .content-column .item-box {
        margin-top: 0;
      }

      .content-column h3 {
        position: relative;
        z-index: 100;
        background-color: #f7f8fa;
      }

      .content-column .column-scroll {
        padding-top: 0 !important;
        background-color: #f7f8fa;
      }

      .content-column .group-header {
        top: 0;
        z-index: 90;
        background-color: #f1f3f5;
      }
    }
  </style>
</head>
<body>

<!-- Incluir el header -->
<?php
$ui_version = 'bs5';
$ui_requires_jquery = false;
?>
<?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

<div class="page-container <?php echo $layoutClass; ?>">
  <!-- SIDEBAR -->
  <aside class="column sidebar">
    <button type="button" class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Abrir menu">
      <i class="fa fa-bars"></i>
    </button>
    <div class="mobile-menu-backdrop" onclick="toggleMobileMenu(false)"></div>
    <div class="buttons-container">
    <?php
    // Mostrar el botón correspondiente si procede
    if ($puedeVerProductos) {
    ?>
      <a href="productos.php" class="btn btn-productos">
        <i class="fa fa-cubes"></i> Productos
      </a>
    <?php } ?>

      <a href="clientes.php?cod_cliente=&nombre_comercial=&provincia=&poblacion=&order_by=ultima_fecha_venta&order_dir=desc"
         class="btn btn-clientes">
        <i class="fa fa-user"></i> Clientes
      </a>
      <a href="faltas_todos.php" class="btn btn-faltas" style="position: relative;">
        <?php if ($count_pedidos_cerrados_70 > 0) { ?>
          <span class="badge"><?php echo $count_pedidos_cerrados_70; ?></span>
        <?php } ?>
        <i class="fa fa-lock"></i> Pedidos Cerrados
      </a>
      <a href="pedidos_todos.php" class="btn btn-pedidos" style="position: relative;">
      <?php if ($count_pedidos_abiertos > 0) { ?>
        <span class="badge"><?php echo $count_pedidos_abiertos; ?></span>
      <?php } ?>
       <i class="fa fa-unlock-alt"></i> Pedidos Abiertos
      </a>

      <?php if ($puedeVerPlanificador) { ?>
        <a href="planificacion_rutas.php" class="btn btn-planificador" style="position: relative;">
          <?php if ($count_pedidos_sin_visita > 0) { ?>
            <span class="badge"><?php echo $count_pedidos_sin_visita; ?></span>
          <?php } ?>
          <i class="fa fa-calendar"></i> Planificador
        </a>
      <?php } ?>
      <?php if ($puedeVerEstadisticas) { ?>
                    <a href="<?= BASE_URL ?>/estadisticas.php" class="btn btn-estadisticas">
          <i class="fa fa-bar-chart"></i> Estad&iacute;sticas
        </a>
      <?php } ?>
      <a href="altaClientes/alta_cliente.php" class="btn btn-nuevocliente" >
        <i class="fa fa-user-plus"></i> A&ntilde;adir Cliente
      </a>
    </div>
  </aside>

  <div class="container app-container">
  <div class="main-content">
    <div class="panel-toolbar">
      <?php echo date_default_timezone_get(); ?>
      <h2>Panel Diario</h2>
      <div class="date-wrapper">
        <form method="GET" action="">
          <input
            type="date"
            id="fechaSelect"
            name="fecha"
            value="<?= htmlspecialchars((string)$fechaConsulta) ?>"
            onchange="this.form.submit()"
          >
        </form>
      </div>
    </div>

    <div class="dashboard-columns <?php echo $layoutClass; ?>">
  <!-- COLUMNA 2: PEDIDOS DE HOY -->
  <div class="column content-column">
    <h3>Pedidos de Hoy</h3>
    <div class="column-scroll">
    <?php
// echo "Zona horaria: " . ini_get("date.timezone") . "<br>";
// echo "Hora actual: " . date("Y-m-d H:i:s") . "<br>";
// Comentario de depuración saneado
?>
    <?php 
    if (empty($pedidosHoy)) {
        echo '<p class="no-data">No hay pedidos para hoy</p>';
    } else {
        // Agrupar por cod_comisionista
        $gruposPedidos = [];
        $totalGeneralPedidos = 0;

        foreach ($pedidosHoy as $pedido) {
            $grupo = $pedido['cod_comisionista'];
            if (!isset($gruposPedidos[$grupo])) {
                $gruposPedidos[$grupo] = [
                    'nombre_comisionista' => $pedido['nombre_comisionista'],
                    'total' => 0,
                    'registros' => []
                ];
            }
            $gruposPedidos[$grupo]['total'] += floatval($pedido['importe']);
            $totalGeneralPedidos += floatval($pedido['importe']);
            $gruposPedidos[$grupo]['registros'][] = $pedido;
        }

        // Ordenar por total desc (usort con cmpGrupo)
        $gruposOrdenados = [];
        foreach ($gruposPedidos as $cod => $grupo) {
            $grupo['cod'] = $cod;
            $gruposOrdenados[] = $grupo;
        }
        usort($gruposOrdenados, "cmpGrupo");

        // Mostrar grupos
        foreach ($gruposOrdenados as $grupo) {
            $codComisionista = $grupo['cod'];
            $nombreComisionista = $grupo['nombre_comisionista'];
            $totalGrupo = number_format($grupo['total'], 2, ',', '.');
            $display = ($cod_vendedor_session === null) ? "none" : "block";

            $numPedidos = count($grupo['registros']);
            echo '<div class="group-header" onclick="toggleGroup(\'grupo_' . $codComisionista . '\')">'
              . htmlspecialchars($nombreComisionista) . " - Total: " . $totalGrupo . " &euro;"
              . " (" . $numPedidos . " pedidos)"
              . '</div>';

            echo '<div class="group-content" id="grupo_' . $codComisionista . '" style="display: ' . $display . ';">';

            foreach ($grupo['registros'] as $ped) {
                $historico = trim($ped['historico']);
                $color = '#007bff';
                $icono = '<i class="fa fa-unlock"></i>';
                if ($historico === 'S') {
                    $color = '#28a745';
                    $icono = '<i class="fa fa-lock"></i>';
                }
                if (!empty($ped['cod_pedido_web'])) {
                    $color = '#af8641';
                    $icono .= ' <i class="fa fa-globe"></i>';
                }
                $colorBaseFondo = $color;
                // Origen del pedido
                $icono_origen = '';
                $colorOrigen = '';
                $origen = '';
                if (empty($ped['cod_pedido_web'])) {
                    $queryOrigen = "SELECT origen FROM cmf_visita_pedidos WHERE cod_venta = " . intval($ped['cod_venta']);
                    $result_icon = odbc_exec($conn, $queryOrigen);
                    if ($result_icon && odbc_fetch_row($result_icon)) {
                        $origen = strtolower(trim(odbc_result($result_icon, "origen")));
                        switch ($origen) {
                            case 'pedido web':
                                $colorOrigen = '#af8641';
                                $icono_origen = '<i class="fa fa-globe"></i> ';
                                break;
                            case 'telefono':
                            case 'telefono':
                                $colorOrigen = '#13ba8a';
                                $icono_origen = '<i class="fa fa-phone"></i> ';
                                break;
                            case 'visita':
                                $colorOrigen = '#007723';
                                $icono_origen = '<i class="fa fa-briefcase"></i> ';
                                break;
                            case 'whatsapp':
                                $colorOrigen = '#25D366';
                                $icono_origen = '<i class="fa-brands fa-whatsapp"></i> ';
                                break;
                            case 'email':
                                $colorOrigen = '#0072C6';
                                $icono_origen = '<i class="fa fa-envelope"></i> ';
                                break;
                            default:
                                $colorOrigen = '#6c757d';
                                $icono_origen = '<i class="fa fa-info-circle"></i> ';
                                break;
                        }
                    }
                }
                if (!empty($colorOrigen)) {
                    $color = $colorOrigen;
                }
                // Estado de preparado
                $bgColor = "";
                if (!empty($ped['prepared'])) {
                    $bgColor = "background-color: #d4edda;";
                } else if (!empty($ped['preparar_pedido']) && strtoupper($ped['preparar_pedido']) === 'S') {
                    $bgColor = "background-color: " . lighten_color($colorBaseFondo, 80) . ";";
                }

                // Datos
                $tipoVentaDocumento = isset($ped['tipo_venta']) ? (int)$ped['tipo_venta'] : 1;
                $codEmpresaDocumento = isset($ped['cod_empresa']) ? (int)$ped['cod_empresa'] : 0;
                $codCajaDocumento = isset($ped['cod_caja']) ? (int)$ped['cod_caja'] : 0;
                $codVenta = $ped['cod_venta'];
                $codCliente = $ped['cod_cliente'];
                $nomCom = toUTF8($ped['nombre_comercial']);
                $seccion = !empty($ped['nombre_seccion']) ? toUTF8($ped['nombre_seccion']) : '';
                $importe = number_format(floatval($ped['importe']), 2, ',', '.');
                $horaVenta = toUTF8($ped['hora_venta']);
                $nombre_vendedor = !empty($ped['nombre_vendedor']) ? toUTF8($ped['nombre_vendedor']) : '';
                $observacion_interna = $ped['observacion_interna'] ?? '';

                // Render
                echo '<div class="item-box js-documento-card" style="border-left: 6px solid ' . $color . '; ' . $bgColor
                     . ';" onclick="abrirDocumento('
                     . json_encode($tipoVentaDocumento) . ', '
                     . json_encode($codEmpresaDocumento) . ', '
                     . json_encode($codCajaDocumento) . ', '
                     . json_encode((int)$codVenta)
                     . ')"'
                     . ' data-cod-venta="' . htmlspecialchars((string)$codVenta) . '"'
                     . ' data-tipo-venta="' . htmlspecialchars((string)$tipoVentaDocumento) . '"'
                     . ' data-cod-empresa="' . htmlspecialchars((string)$codEmpresaDocumento) . '"'
                     . ' data-cod-caja="' . htmlspecialchars((string)$codCajaDocumento) . '">';
                echo '  <div class="item-title">'
                     . '    <span>' . $icono . $icono_origen . " Pedido #" . $codVenta . '</span>'
                     . '    <span><i class="fa fa-clock"></i> ' . htmlspecialchars($horaVenta) . '</span>'
                     . '  </div>';
                echo '  <div class="item-subtitle">' . htmlspecialchars($nomCom);
                if (!empty($seccion)) {
                    echo '    <span style="font-size:12px; color:#666;"><br><i class="fa-solid fa-location-dot"></i> '
                         . htmlspecialchars($seccion) . '</span>';
                }
                echo '  </div>';
                echo '  <div style="font-size:12px; text-align:left;"><br><i class="fa fa-shopping-cart"></i> '
                     . $importe . " &euro; (" . $ped['num_lineas_pedido'] . " l&iacute;neas)</div>";
                if (!empty($observacion_interna)) {
                    echo '  <div style="font-size:12px; color:#333; margin-top:5px;"><i class="fa fa-comment"></i> '
                         . htmlspecialchars($observacion_interna) . '</div>';
                }
                if (isset($ped['cod_vendedor']) && $ped['cod_vendedor'] != $cod_vendedor_session) {
                    echo '  <div style="font-size:12px; color:#333; margin-top:5px;"><i class="fa fa-user"></i> '
                         . htmlspecialchars($nombre_vendedor) . '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        // Total general (solo visible si no hay vendedor_session)
        if ($cod_vendedor_session === null) {
            $totalGeneralPedidos = number_format($totalGeneralPedidos, 2, ',', '.');
            echo '<div class="total-general">Total General Pedidos: '
                 . $totalGeneralPedidos . ' &euro;</div>';
        }
    }
    ?>
    </div>
  </div>
  
  <!-- COLUMNA 3: ALBARANES -->
  <div class="column content-column">
    <h3>Albaranes de Hoy</h3>
    <div class="column-scroll">
    <?php
    if (empty($albaranesHoy)) {
        echo '<p class="no-data">No hay albaranes para hoy</p>';
    } else {
        // Agrupar por grupoVenta
        $gruposAlbaranes = [];
        foreach ($albaranesHoy as $alb) {
            $grupo = $alb['grupoVenta'];
            if (!isset($gruposAlbaranes[$grupo])) {
                $gruposAlbaranes[$grupo] = ['total' => 0, 'registros' => []];
            }
            $gruposAlbaranes[$grupo]['total'] += floatval($alb['importe_total']);
            $gruposAlbaranes[$grupo]['registros'][] = $alb;
        }
        // Convertir a arreglo indexado y ordenar
        $gruposAlbOrdenados = [];
        foreach ($gruposAlbaranes as $cod => $grupo) {
            $grupo['cod'] = $cod;
            $gruposAlbOrdenados[] = $grupo;
        }
        usort($gruposAlbOrdenados, "cmpGrupo");

        foreach ($gruposAlbOrdenados as $grpA) {
            $codComisAlb = $grpA['cod'];
            $nombreComisionista = toUTF8($grpA['registros'][0]['nombreGrupo']);
            $totalAlb = number_format($grpA['total'], 2, ',', '.');
            $displayAlb = ($cod_vendedor_session === null) ? "none" : "block";

            echo '<div class="group-header" onclick="toggleGroup(\'grupoAlb_' . $codComisAlb . '\')">'
               . htmlspecialchars($nombreComisionista) . " - Total: " . $totalAlb . " &euro;"
               . '</div>';
            echo '<div class="group-content" id="grupoAlb_' . $codComisAlb . '" style="display:' . $displayAlb . ';">';

            // Mostrar cada albarán
            foreach ($grpA['registros'] as $alb) {
                $colorAlbaran = '#17a2b8';
                $obsExt = !empty($alb['observacion_externa']) ? toUTF8($alb['observacion_externa']) : '';
                $bgAlbaran = "";
                if (!empty($obsExt) && stripos($obsExt, "TK") !== false) {
                    $colorAlbaran = '#000000';
                    $bgAlbaran = "background-color: " . lighten_color($colorAlbaran, 80) . ";";
                } else {
                    if (!empty($obsExt)) {
                        $obsExtLower = strtolower($obsExt);
                        if (strpos($obsExtLower, 'redur') !== false) {
                            $colorAlbaran = '#37816e';
                        } else if (strpos($obsExtLower, 'egabrese') !== false
                               || strpos($obsExtLower, 'egabrense') !== false) {
                            $colorAlbaran = '#6acf19';
                        } else if (strpos($obsExtLower, 'abono') !== false) {
                            $colorAlbaran = '#990b0b';
                        }
                    }
                }
                $codAlb = $alb['cod_venta'];
                $codEmpresaAlb = isset($alb['cod_empresa']) ? (int)$alb['cod_empresa'] : 0;
                $codCajaAlb = isset($alb['cod_caja']) ? (int)$alb['cod_caja'] : 0;
                $codClienteAlb = $alb['cod_cliente'];
                $nomComAlb = toUTF8($alb['nombre_comercial']);
                $impAlb = number_format(floatval($alb['importe_total']), 2, ',', '.');
                $horaVentaAlb = toUTF8($alb['hora_venta']);
                $secAlb = !empty($alb['nombre_seccion']) ? toUTF8($alb['nombre_seccion']) : '';
                $obsIntAlb = !empty($alb['observacion_interna']) ? toUTF8($alb['observacion_interna']) : '';
                $nombreVendedorAlb = !empty($alb['nombre_vendedor']) ? toUTF8($alb['nombre_vendedor']) : '';

                // Prefijo
                if ($alb['tipo_venta'] == 2) {
                    $prefijo = "Albar&aacute;n";
                } elseif ($alb['tipo_venta'] == 4) {
                    $prefijo = "Ticket";
                } else {
                    $prefijo = "Venta";
                }
                // Identificador único
                $uniqueId = $codAlb . "_" . $alb['tipo_venta'];

                echo '<div class="item-box js-documento-card" style="border-left:6px solid ' . $colorAlbaran . ';' . $bgAlbaran
                     . ';" onclick="abrirDocumento('
                     . json_encode((int)$alb['tipo_venta']) . ', '
                     . json_encode($codEmpresaAlb) . ', '
                     . json_encode($codCajaAlb) . ', '
                     . json_encode((int)$codAlb)
                     . ')"'
                     . ' data-cod-venta="' . htmlspecialchars((string)$codAlb) . '"'
                     . ' data-tipo-venta="' . htmlspecialchars((string)$alb['tipo_venta']) . '"'
                     . ' data-cod-empresa="' . htmlspecialchars((string)$codEmpresaAlb) . '"'
                     . ' data-cod-caja="' . htmlspecialchars((string)$codCajaAlb) . '">';
                echo '  <div class="item-title">'
                     . '    <span><i class="fa fa-file"></i> ' . $prefijo . " #" . $codAlb . '</span>'
                     . '    <span><i class="fa fa-clock"></i> ' . htmlspecialchars($horaVentaAlb) . '</span>'
                     . '  </div>';
                echo '  <div class="item-subtitle">'
                     . htmlspecialchars($nomComAlb);
                if (!empty($secAlb)) {
                    echo '    <span style="font-size:12px; color:#666;"><br><i class="fa-solid fa-location-dot"></i> '
                         . htmlspecialchars($secAlb) . '</span>';
                }
                echo '  </div>';
                echo '  <div style="font-size:12px;text-align:left;"><br><i class="fa fa-shopping-cart"></i> '
                     . $impAlb . " &euro; (" . $alb['num_lineas_albaran'] . " l&iacute;neas)</div>";
                if (!empty($obsIntAlb)) {
                    echo '  <div style="font-size:12px;color:#333;margin-top:5px;"><i class="fa fa-comment"></i> Obs. Interna: '
                         . htmlspecialchars($obsIntAlb) . '</div>';
                }
                if (!empty($obsExt)) {
                    echo '  <div style="font-size:12px;color:#666;margin-top:3px;"><i class="fa fa-commenting-o"></i> Obs. Externa: '
                         . htmlspecialchars($obsExt) . '</div>';
                }
                if (isset($alb['cod_vendedor']) && $alb['cod_vendedor'] != $cod_vendedor_session) {
                    echo '  <div style="font-size:12px;color:#333;margin-top:5px;"><i class="fa fa-user"></i> '
                         . htmlspecialchars($nombreVendedorAlb) . '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        // Total general si no hay vendedor_session
        if ($cod_vendedor_session === null) {
            $totalGeneralAlbaranes = 0;
            foreach ($albaranesHoy as $alb2) {
                $totalGeneralAlbaranes += floatval($alb2['importe_total']);
            }
            $totalGeneralAlbaranes = number_format($totalGeneralAlbaranes, 2, ',', '.');
            echo '<div class="total-general">Total General Albaranes: '
                 . $totalGeneralAlbaranes . ' &euro;</div>';
        }
    }
    ?>
    </div>
  </div>

  <!-- COLUMNA 4: VISITAS (solo para jcasado) -->
  <?php if ($isJcasado) { 
      // Combinamos visitas y d&iacute;as no laborables
      $visitasTransform = [];
      foreach ($visitasHoy as $v) {
          $v['tipo_registro'] = 'visita';
          $v['start'] = $v['hora_inicio_visita'];
          $visitasTransform[] = $v;
      }
      $noLabTransform = [];
      foreach ($diasNoLaborables as $nl) {
          $nl['tipo_registro'] = 'no_laborable';
          $nl['start'] = $nl['hora_inicio'];
          $noLabTransform[] = $nl;
      }
      $merged = array_merge($visitasTransform, $noLabTransform);
      usort($merged, 'cmpStart');
      ?>
      <div class="column content-column">
        <h3>Visitas de Hoy</h3>
        <div class="column-scroll">
        <?php
        if (empty($merged)) {
            echo '<p class="no-data">No hay visitas ni d&iacute;as no laborables para hoy</p>';
        } else {
            foreach ($merged as $vis) {
                if ($vis['tipo_registro'] === 'visita') {
                    // VISITA
                    $estado = normalizarEstadoVisitaClave($vis['estado_visita']);
                    $colorVisita = '#007723';
                    $iconoVisita = '<i class="fa fa-briefcase"></i>';
                    if ($estado == 'pendiente') {
                        $colorVisita = '#ffc107';
                        $iconoVisita = '<i class="fa fa-clock-o"></i>';
                    } else if ($estado == 'planificada') {
                        $colorVisita = '#007bff';
                        $iconoVisita = '<i class="fa fa-calendar-check"></i>';
                    } else if ($estado == 'realizada') {
                        $colorVisita = '#007723';
                        $iconoVisita = '<i class="fa fa-briefcase"></i>';
                    } else if ($estado == 'no atendida') {
                        $colorVisita = '#e65414';
                        $iconoVisita = '<i class="fa fa-refresh"></i>';
                    } else if ($estado == 'descartada') {
                        $colorVisita = '#6c757d';
                        $iconoVisita = '<i class="fa fa-calendar"></i>';
                    }
                    // Si la visita marcada como realizada no tiene registro, la saltamos.
                    if ($estado == 'realizada' && intval($vis['tiene_visita']) !== 1) {
                        continue;
                    }
                    $secc = (!empty($vis['nombre_seccion'])) ? ' - ' . toUTF8($vis['nombre_seccion']) : '';
                    $nombreVisita = toUTF8($vis['nombre_comercial']);

                    echo '<a class="item-box item-box-link" style="border-left:6px solid ' . $colorVisita
                         . ';" href="' . BASE_URL . '/editar_visita.php?id_visita=' . rawurlencode((string)$vis['id_visita']) . '&origen=index">';
                    echo '  <div class="item-title-visita">'
                         . $iconoVisita . " " . htmlspecialchars($nombreVisita) . $secc
                         . '</div>';
                    echo '  <div class="item-subtitle"><i class="fa fa-clock"></i> '
                         . substr($vis['hora_inicio_visita'], 0, 5) . ' - '
                         . substr($vis['hora_fin_visita'], 0, 5) . '</div>';
                    if (!empty($vis['total_importe_pedidos'])
                        && (float)$vis['total_importe_pedidos'] > 0) {
                        $totalImporte = (float)$vis['total_importe_pedidos'];
                        $numLineas = intval($vis['num_lineas_pedidos']);
                        echo '<div style="font-size:12px;color:#333;margin-top:5px;">'
                             . '<i class="fa fa-shopping-cart"></i> '
                             . number_format($totalImporte, 2, ',', '.') . " &euro; (" 
                             . $numLineas . " l&iacute;neas)</div>";
                    }
                    if (!empty($vis['observaciones'])) {
                        echo '<div style="font-size:12px;color:#333;margin-top:5px;">'
                             . '<i class="fa fa-comment"></i> '
                             . htmlspecialchars($vis['observaciones']) . '</div>';
                    }
                    echo '</a>';

                } else {
                    // Detectar el tipo de evento para elegir el icono actual
                    $tipoEvento = $vis['tipo_evento'];
                    $tipoEventoLower = strtolower($tipoEvento);
                    $borderLeftNL = '6px solid black';
                    $colorTexto = '#666';
                    $iconoNL = '<i class="fa fa-ban"></i>';
                    if (strpos($tipoEventoLower, 'medico') !== false 
                        || strpos($tipoEventoLower, 'médico') !== false) {
                        $iconoNL = '<i class="fa fa-user-md"></i>';
                    } else if (strpos($tipoEventoLower, 'comaferr') !== false) {
                        $iconoNL = '<i class="fa fa-building"></i>';
                    } else if ($tipoEventoLower == 'festivo') {
                        $colorTexto = '#ff0000';
                        $iconoNL = '<i class="fa fa-calendar-times-o"></i>';
                    } else if ($tipoEventoLower == 'vacaciones') {
                        $colorTexto = '#ff0000';
                        $iconoNL = '<i class="fa fa-plane"></i>';
                    }
                    $todoElDia = false;
                    $hInicio = isset($vis['hora_inicio']) ? trim($vis['hora_inicio']) : '';
                    $hFin = isset($vis['hora_fin']) ? trim($vis['hora_fin']) : '';
                    if ($hInicio == '00:00:00' && $hFin == '00:00:00') {
                        $todoElDia = true;
                    }
                    echo '<div class="item-box" style="border-left:' . $borderLeftNL 
                         . ';color:' . $colorTexto . ';">';
                    echo '  <div class="item-title-visita">'
                         . $iconoNL . " " . htmlspecialchars($vis['tipo_evento']) 
                         . '</div>';
                    echo '  <div class="item-subtitle">';
                    echo htmlspecialchars($vis['descripcion']);
                    echo ' (' . ($todoElDia ? "Todo el d&iacute;a" 
                         : (substr($hInicio,0,5) . " - " . substr($hFin,0,5))) . ')';
                    if (!empty($vis['repetir_anualmente']) && $vis['repetir_anualmente'] == 1) {
                        echo " (Anual)";
                    }
                    echo '  </div>';
                    echo '</div>';
                }
            }
        }
        ?>
        </div>
      </div>
  <?php } // fin if($isJcasado) ?>
    </div> <!-- /.dashboard-columns -->
  </div> <!-- /.main-content -->
  </div> <!-- /.container.app-container -->
</div> <!-- /.page-container -->

<?php if (!defined('MOBILE_APPBAR_RENDERED')): ?>
<!-- APP BAR MOVIL -->
<div class="mobile-appbar">
  <?php
  if ($puedeVerProductos) {
      echo '<a href="productos.php" class="app-btn app-productos"><i class="fa fa-cubes"></i><span>Productos</span></a>';
  }
  ?>
  <a href="clientes.php?cod_cliente=&nombre_comercial=&provincia=&poblacion=&order_by=ultima_fecha_venta&order_dir=desc" class="app-btn app-clientes">
    <i class="fa fa-user"></i><span>Clientes</span>
  </a>
  <a href="faltas_todos.php" class="app-btn app-cerrados">
    <?php if ($count_pedidos_cerrados_70 > 0) { ?>
      <span class="badge"><?php echo $count_pedidos_cerrados_70; ?></span>
    <?php } ?>
    <i class="fa fa-lock"></i><span>Cerrados</span>
  </a>
  <a href="pedidos_todos.php" class="app-btn app-abiertos">
    <?php if ($count_pedidos_abiertos > 0) { ?>
      <span class="badge"><?php echo $count_pedidos_abiertos; ?></span>
    <?php } ?>
    <i class="fa fa-unlock-alt"></i><span>Abiertos</span>
  </a>
  <?php if ($puedeVerPlanificador) { ?>
    <a href="planificacion_rutas.php" class="app-btn app-planificador">
      <?php if ($count_pedidos_sin_visita > 0) { ?>
        <span class="badge"><?php echo $count_pedidos_sin_visita; ?></span>
      <?php } ?>
      <i class="fa fa-calendar"></i><span>Planificador</span>
    </a>
  <?php } ?>
  <?php if ($puedeVerEstadisticas) { ?>
    <a href="<?= BASE_URL ?>/estadisticas.php" class="app-btn app-estadisticas">
      <i class="fa fa-bar-chart"></i><span>Estad&iacute;sticas</span>
    </a>
  <?php } ?>
  <a href="altaClientes/alta_cliente.php" class="app-btn app-nuevo">
    <i class="fa fa-user-plus"></i><span>A&ntilde;adir</span>
  </a>
</div>
<?php endif; ?>

<!-- ===================== MODALES ===================== -->
<div id="modalContainer"></div>

<!-- MODAL: Pedido -->
<div class="modal" id="modal-pedido">
  <div class="modal-content">
    <span class="close-modal" onclick="cerrarModal('modal-pedido')">&times;</span>
    <div class="modal-header">
      <h3>Detalles del Pedido</h3>
      <div id="pedido-client-btn"></div>
    </div>
    <div id="pedido-detalle"></div>
  </div>
</div>

<!-- MODAL: Albar&aacute;n -->
<div class="modal" id="modal-albaran">
  <div class="modal-content">
    <span class="close-modal" onclick="cerrarModal('modal-albaran')">&times;</span>
    <div class="modal-header">
      <h3>Detalles del Albar&aacute;n</h3>
      <div id="albaran-client-btn"></div>
    </div>
    <div id="albaran-detalle"></div>
  </div>
</div>

<!-- MODAL: Visita -->
<div class="modal" id="modal-visita">
  <div class="modal-content">
    <span class="close-modal" onclick="cerrarModal('modal-visita')">&times;</span>
    <h3>Detalles de la Visita</h3>
    <div id="visita-detalle"></div>
  </div>
</div>

<!-- ===================== SCRIPTS ===================== -->
<script>
// ================ Pedidos ================
function abrirModalPedido(codVenta, codCliente) {
  var modal = document.getElementById('modal-pedido');
  modal.classList.add('show');
  modal.style.display = 'block';
  document.getElementById('pedido-client-btn').innerHTML =
    '<a href="faltas.php?cod_cliente=' + codCliente + '" class="btn-client"><i class="fa fa-book"></i>Faltas</a>';
  document.getElementById('pedido-detalle').innerHTML = 'Cargando...';

  $.get('<?= BASE_URL ?>/ajax/detalle_pedido.php', { cod_venta: codVenta }, function(resp){
    document.getElementById('pedido-detalle').innerHTML = resp;
  });
}

// ================ Albaranes ================
function abrirModalAlbaran(codVentaTipo, codCliente) {
  var modal = document.getElementById('modal-albaran');
  modal.classList.add('show');
  modal.style.display = 'block';
  document.getElementById('albaran-client-btn').innerHTML =
    '<a href="faltas.php?cod_cliente=' + codCliente + '" class="btn-client"><i class="fa fa-book"></i>Faltas</a>';
  document.getElementById('albaran-detalle').innerHTML = 'Cargando...';

  $.get('<?= BASE_URL ?>/ajax/detalle_albaran.php', { cod_venta_tipo: codVentaTipo }, function(resp){
    document.getElementById('albaran-detalle').innerHTML = resp;
  });
}

// ================ Visitas ================
function abrirModalVisita(idVisita) {
  // Redirige a la página de edición de visita
  window.location.href = "editar_visita.php?id_visita=" + idVisita + "&origen=index";
}

function actualizarOrigenDesdeIndex(codPedido, nuevoOrigen, event) {
  if (event) {
    event.stopPropagation();
  }
  $.ajax({
    url: '<?= BASE_URL ?>/ajax/actualizar_origen.php',
    type: 'POST',
    data: { cod_pedido: codPedido, origen: nuevoOrigen },
    success: function(response) {
      if (response.indexOf('OK') === 0) {
        location.reload();
      } else {
        alert('Error al actualizar el origen: ' + response);
      }
    },
    error: function() {
      alert('Error al actualizar el origen (AJAX).');
    }
  });
}
// ================ Cerrar modales ================
(function inicializarCierreModalesIndex() {
  var modals = document.getElementsByClassName('modal');
  for (var i = 0; i < modals.length; i++) {
    (function(modal) {
      modal.addEventListener('click', function(event) {
        if (event.target === modal) {
          modal.classList.remove('show');
          modal.style.display = 'none';
        }
      });
    })(modals[i]);
  }
})();

// ================ Toggle grupos ================
function toggleGroup(id) {
  var elem = document.getElementById(id);
  if (elem.style.display === "none" || elem.style.display === "") {
    elem.style.display = "block";
  } else {
    elem.style.display = "none";
  }
}

function toggleMobileMenu(forceOpen) {
  var sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;
  if (typeof forceOpen === 'boolean') {
    sidebar.classList.toggle('menu-open', forceOpen);
  } else {
    sidebar.classList.toggle('menu-open');
  }
}

if (window.matchMedia('(max-width: 1024px)').matches) {
  var sidebarLinks = document.querySelectorAll('.sidebar .buttons-container a');
  for (var i = 0; i < sidebarLinks.length; i++) {
    sidebarLinks[i].addEventListener('click', function() {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.remove('menu-open');
      }
    });
  }
}
</script>

<?php
// Cerrar la conexión ODBC
?>
<script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
<?php require_once BASE_PATH . '/app/Modules/Pedidos/Views/modal_documento.php'; ?>
</body>
</html>




