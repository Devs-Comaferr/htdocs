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

// Verificar si el usuario ha iniciado sesión


// Verificar parámetros GET
$ajaxAccion = (string)($_GET['ajax'] ?? '');
$esAjaxLineas = in_array($ajaxAccion, array('lineas_visita', 'lineas_pedido'), true);

if ($esAjaxLineas) {
    require_once BASE_PATH . '/app/Modules/Clientes/ajax/seccion_detalles.php';
    exit;
}

if (!$esAjaxLineas) {
    if (!isset($_GET['cod_cliente']) || $_GET['cod_cliente'] === '') {
        error_log("Falta cod_cliente");
        echo 'Error interno';
        return;
    }
    if (!isset($_GET['cod_seccion']) || $_GET['cod_seccion'] === '') {
        error_log("Falta cod_seccion");
        echo 'Error interno';
        return;
    }
}

$cod_cliente = (string)($_GET['cod_cliente'] ?? '');
$cod_seccion = (string)($_GET['cod_seccion'] ?? '');
$cod_comercial = $_SESSION['codigo'] ?? null;


$conn = db();
// Conexion auxiliar para consultas anidadas en el bucle de visitas.
$conn_aux = openOdbcConnection();
$conn_aux2 = openOdbcConnection();
$pageTitle = "Detalles de la Sección";

// Endpoint AJAX interno: lineas de visita/pedido para carga diferida en modales.
function cargarResumenPedido($connLocal, string $codPedido, string $origen = ''): array {
    $pedido = array(
        'cod_pedido' => $codPedido,
        'origen' => $origen,
        'fecha_venta' => null,
        'hora_venta' => null,
        'importe' => 0.0,
        'numero_lineas' => 0,
        'observacion_interna' => '',
        'pedido_eliminado' => 0,
        'eliminado_por_usuario' => '',
        'eliminado_por_equipo' => '',
        'eliminado_fecha' => null,
        'eliminado_hora' => null
    );

    // Prioridad: si existe en eliminadas, usar siempre esa cabecera.
    $sqlCabElim = "
        SELECT TOP 1 *
        FROM [integral].[dbo].[ventas_cabecera_elim] vce
        WHERE vce.cod_venta = '" . addslashes($codPedido) . "'
          AND vce.tipo_venta = 1
        ORDER BY vce.fecha_venta DESC, vce.hora_venta DESC
    ";
    $resCabElim = odbc_exec($connLocal, $sqlCabElim);
    $cabElim = $resCabElim ? odbc_fetch_array($resCabElim) : false;

    if ($cabElim) {
        $pedido['fecha_venta'] = $cabElim['fecha_venta'] ?? $cabElim['FECHA_VENTA'] ?? null;
        $pedido['hora_venta'] = $cabElim['hora_venta'] ?? $cabElim['HORA_VENTA'] ?? null;
        $pedido['importe'] = (float)($cabElim['importe'] ?? $cabElim['IMPORTE'] ?? 0);
        $pedido['pedido_eliminado'] = 1;

        $sqlLogElim = "
            SELECT TOP 1
                la.cod_usuario,
                la.cod_estacion,
                la.fecha,
                la.hora
            FROM [integral].[dbo].[log_acciones] la
            WHERE la.tipo = 'B'
              AND la.categoria = 'V'
              AND la.cod_n3 = '" . addslashes($codPedido) . "'
            ORDER BY la.fecha DESC, la.hora DESC
        ";
        $resLogElim = odbc_exec($connLocal, $sqlLogElim);
        $logElim = $resLogElim ? odbc_fetch_array($resLogElim) : false;
        if ($logElim) {
            $pedido['eliminado_por_usuario'] = (string)($logElim['cod_usuario'] ?? $logElim['COD_USUARIO'] ?? '');
            $pedido['eliminado_por_equipo'] = (string)($logElim['cod_estacion'] ?? $logElim['COD_ESTACION'] ?? '');
            $pedido['eliminado_fecha'] = $logElim['fecha'] ?? $logElim['FECHA'] ?? null;
            $pedido['eliminado_hora'] = $logElim['hora'] ?? $logElim['HORA'] ?? null;
        }
    } else {
        $sqlCabHist = "
            SELECT TOP 1
                hvc.fecha_venta,
                hvc.hora_venta,
                hvc.importe,
                avc.observacion_interna
            FROM [integral].[dbo].[hist_ventas_cabecera] hvc
            LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] avc
              ON hvc.cod_anexo = avc.cod_anexo
            WHERE hvc.cod_venta = '" . addslashes($codPedido) . "'
              AND hvc.tipo_venta = 1
            ORDER BY hvc.fecha_venta DESC, hvc.hora_venta DESC
        ";
        $resCabHist = odbc_exec($connLocal, $sqlCabHist);
        $cabHist = $resCabHist ? odbc_fetch_array($resCabHist) : false;
        if ($cabHist) {
            $pedido['fecha_venta'] = $cabHist['fecha_venta'] ?? $cabHist['FECHA_VENTA'] ?? null;
            $pedido['hora_venta'] = $cabHist['hora_venta'] ?? $cabHist['HORA_VENTA'] ?? null;
            $pedido['importe'] = (float)($cabHist['importe'] ?? $cabHist['IMPORTE'] ?? 0);
            $pedido['observacion_interna'] = (string)($cabHist['observacion_interna'] ?? $cabHist['OBSERVACION_INTERNA'] ?? '');
        }
    }

    $numeroLineas = 0;
    if ((int)$pedido['pedido_eliminado'] === 1) {
        $sqlNumLineasElim = "
            SELECT COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[ventas_linea_elim] vle
            WHERE vle.cod_venta = '" . addslashes($codPedido) . "'
              AND vle.tipo_venta = 1
        ";
        $resNumLineasElim = odbc_exec($connLocal, $sqlNumLineasElim);
        $numElim = $resNumLineasElim ? odbc_fetch_array($resNumLineasElim) : false;
        $numeroLineas = (int)($numElim['numero_lineas'] ?? $numElim['NUMERO_LINEAS'] ?? 0);
    } else {
        $sqlNumLineasHist = "
            SELECT COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[hist_ventas_linea] hl
            WHERE hl.cod_venta = '" . addslashes($codPedido) . "'
              AND hl.tipo_venta = 1
        ";
        $resNumLineasHist = odbc_exec($connLocal, $sqlNumLineasHist);
        $numHist = $resNumLineasHist ? odbc_fetch_array($resNumLineasHist) : false;
        $numeroLineas = (int)($numHist['numero_lineas'] ?? $numHist['NUMERO_LINEAS'] ?? 0);
    }

    $pedido['numero_lineas'] = $numeroLineas;
    return $pedido;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'lineas_visita') {
    $idVisitaAjax = (string)($_GET['id_visita'] ?? '');
    if ($idVisitaAjax === '') {
        echo '<p>ID de visita no valido.</p>';
        exit;
    }

    $sql_lineas_visita = "
        SELECT
            hl.cod_articulo,
            hl.descripcion,
            hl.precio AS precio,
            hl.cantidad,
            hl.dto1,
            hl.dto2,
            hl.importe,
            elv.cantidad AS cantidad_servida,
            hvc_dest.fecha_venta AS fecha_entrega
        FROM [integral].[dbo].[hist_ventas_linea] hl
        INNER JOIN [integral].[dbo].[cmf_visita_pedidos] vp
           ON hl.cod_venta = vp.cod_venta
        LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
           ON hl.cod_venta = elv.cod_venta_origen
          AND hl.linea = elv.linea_origen
        LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
           ON elv.cod_venta_destino = hvc_dest.cod_venta
          AND elv.tipo_venta_destino = hvc_dest.tipo_venta
        WHERE vp.id_visita = '" . addslashes($idVisitaAjax) . "'
          AND hl.tipo_venta = 1

        UNION ALL

        SELECT
            vle.cod_articulo,
            vle.descripcion,
            vle.precio AS precio,
            vle.cantidad,
            0 AS dto1,
            0 AS dto2,
            (vle.cantidad * vle.precio) AS importe,
            elv.cantidad AS cantidad_servida,
            hvc_dest.fecha_venta AS fecha_entrega
        FROM [integral].[dbo].[ventas_linea_elim] vle
        INNER JOIN [integral].[dbo].[cmf_visita_pedidos] vp
           ON vle.cod_venta = vp.cod_venta
        LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
           ON vle.cod_venta = elv.cod_venta_origen
          AND vle.linea = elv.linea_origen
        LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
           ON elv.cod_venta_destino = hvc_dest.cod_venta
          AND elv.tipo_venta_destino = hvc_dest.tipo_venta
        WHERE vp.id_visita = '" . addslashes($idVisitaAjax) . "'
          AND vle.tipo_venta = 1
    ";
    $result_lineas_visita = odbc_exec($conn, $sql_lineas_visita);
    if (!$result_lineas_visita) {
        echo '<p>Error al cargar lineas de la visita.</p>';
        exit;
    }

    $lineaIds = array();
    $hay = false;
    echo '<div class="modal-table-container"><table class="modal-table"><thead><tr>';
    echo '<th>Artículo</th><th>Descripción</th><th>Cantidad</th><th>Cantidad Servida</th><th>Precio (EUR)</th><th>Dto1 (%)</th><th>Dto2 (%)</th><th>Importe (EUR)</th><th>Fecha de Entrega</th>';
    echo '</tr></thead><tbody>';
    while ($linea = odbc_fetch_array($result_lineas_visita)) {
        $uniqueId = (string)($linea['cod_articulo'] ?? '') . '-' . (string)($linea['descripcion'] ?? '') . '-' . (string)($linea['cantidad'] ?? '');
        if (in_array($uniqueId, $lineaIds, true)) {
            continue;
        }
        $lineaIds[] = $uniqueId;
        $hay = true;
        $cant = (float)($linea['cantidad'] ?? 0);
        $cantServ = (float)($linea['cantidad_servida'] ?? 0);
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)($linea['cod_articulo'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($linea['descripcion'] ?? '')) . '</td>';
        echo '<td>' . number_format($cant, 2, ',', '.') . '</td>';
        echo '<td style="' . (($cantServ !== $cant) ? 'color:red;' : '') . '">' . number_format($cantServ, 2, ',', '.') . '</td>';
        echo '<td>' . number_format((float)($linea['precio'] ?? 0), 2, ',', '.') . ' &euro;</td>';
        echo '<td>' . (((float)($linea['dto1'] ?? 0) != 0) ? htmlspecialchars((string)($linea['dto1'] ?? '')) . ' %' : '-') . '</td>';
        echo '<td>' . (((float)($linea['dto2'] ?? 0) != 0) ? htmlspecialchars((string)($linea['dto2'] ?? '')) . ' %' : '-') . '</td>';
        echo '<td>' . number_format((float)($linea['importe'] ?? 0), 2, ',', '.') . ' &euro;</td>';
        echo '<td>' . (!empty($linea['fecha_entrega']) ? date('d/m/Y', strtotime((string)$linea['fecha_entrega'])) : '-') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    if (!$hay) {
        echo '<p>No hay lineas asociadas a esta visita.</p>';
    }
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'lineas_pedido') {
    $codPedidoAjax = (string)($_GET['cod_pedido'] ?? '');
    if ($codPedidoAjax === '') {
        echo '<p>Codigo de pedido no valido.</p>';
        exit;
    }

    $sqlCabElimPedido = "
        SELECT TOP 1 *
        FROM [integral].[dbo].[ventas_cabecera_elim] vce
        WHERE vce.cod_venta = '" . addslashes($codPedidoAjax) . "'
          AND vce.tipo_venta = 1
        ORDER BY vce.fecha_venta DESC, vce.hora_venta DESC
    ";
    $resCabElimPedido = odbc_exec($conn, $sqlCabElimPedido);
    $cabElimPedido = $resCabElimPedido ? odbc_fetch_array($resCabElimPedido) : false;
    $pedidoEliminadoAjax = $cabElimPedido ? true : false;
    $eliminadoUsuarioAjax = '';
    $eliminadoEquipoAjax = '';
    $eliminadoFechaAjax = null;
    $eliminadoHoraAjax = null;
    if ($pedidoEliminadoAjax) {
        $sqlLogElimAjax = "
            SELECT TOP 1
                la.cod_usuario,
                la.cod_estacion,
                la.fecha,
                la.hora
            FROM [integral].[dbo].[log_acciones] la
            WHERE la.tipo = 'B'
              AND la.categoria = 'V'
              AND la.cod_n3 = '" . addslashes($codPedidoAjax) . "'
            ORDER BY la.fecha DESC, la.hora DESC
        ";
        $resLogElimAjax = odbc_exec($conn, $sqlLogElimAjax);
        $logElimAjax = $resLogElimAjax ? odbc_fetch_array($resLogElimAjax) : false;
        if ($logElimAjax) {
            $eliminadoUsuarioAjax = (string)($logElimAjax['cod_usuario'] ?? $logElimAjax['COD_USUARIO'] ?? '');
            $eliminadoEquipoAjax = (string)($logElimAjax['cod_estacion'] ?? $logElimAjax['COD_ESTACION'] ?? '');
            $eliminadoFechaAjax = $logElimAjax['fecha'] ?? $logElimAjax['FECHA'] ?? null;
            $eliminadoHoraAjax = $logElimAjax['hora'] ?? $logElimAjax['HORA'] ?? null;
        }
    }

    $sql_lineas_pedido = "
        SELECT
            hl.cod_articulo,
            hl.descripcion,
            hl.precio AS precio,
            hl.cantidad,
            hl.dto1,
            hl.dto2,
            hl.importe,
            elv.cantidad AS cantidad_servida,
            hvc_dest.fecha_venta AS fecha_entrega
        FROM [integral].[dbo].[hist_ventas_linea] hl
        LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
           ON hl.cod_venta = elv.cod_venta_origen
          AND hl.linea = elv.linea_origen
        LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
           ON elv.cod_venta_destino = hvc_dest.cod_venta
          AND elv.tipo_venta_destino = hvc_dest.tipo_venta
        WHERE hl.cod_venta = '" . addslashes($codPedidoAjax) . "'
          AND hl.tipo_venta = 1
    ";
    $lineasPedidoRows = array();
    if (!$pedidoEliminadoAjax) {
        $result_lineas_pedido = odbc_exec($conn, $sql_lineas_pedido);
        if ($result_lineas_pedido) {
            while ($tmp = odbc_fetch_array($result_lineas_pedido)) {
                $lineasPedidoRows[] = $tmp;
            }
        }
    }

    if ($pedidoEliminadoAjax || count($lineasPedidoRows) === 0) {
        $sql_lineas_pedido_elim = "
            SELECT
                vle.cod_articulo,
                vle.descripcion,
                vle.precio AS precio,
                vle.cantidad,
                0 AS dto1,
                0 AS dto2,
                (vle.cantidad * vle.precio) AS importe,
                elv.cantidad AS cantidad_servida,
                hvc_dest.fecha_venta AS fecha_entrega
            FROM [integral].[dbo].[ventas_linea_elim] vle
            LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
               ON vle.cod_venta = elv.cod_venta_origen
              AND vle.linea = elv.linea_origen
            LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
               ON elv.cod_venta_destino = hvc_dest.cod_venta
              AND elv.tipo_venta_destino = hvc_dest.tipo_venta
            WHERE vle.cod_venta = '" . addslashes($codPedidoAjax) . "'
              AND vle.tipo_venta = 1
        ";
        $res_lineas_elim = odbc_exec($conn, $sql_lineas_pedido_elim);
        if ($res_lineas_elim) {
            while ($tmpElim = odbc_fetch_array($res_lineas_elim)) {
                $lineasPedidoRows[] = $tmpElim;
            }
        }
    }

    $lineaIds = array();
    $hay = false;
    echo '<div class="modal-table-container"><table class="modal-table"><thead><tr>';
    echo '<th>Artículo</th><th>Descripción</th><th>Cantidad</th><th>Cantidad Servida</th><th>Precio (EUR)</th><th>Dto1 (%)</th><th>Dto2 (%)</th><th>Importe (EUR)</th><th>Fecha de Entrega</th>';
    echo '</tr></thead><tbody>';
    foreach ($lineasPedidoRows as $linea) {
        $uniqueId = (string)($linea['cod_articulo'] ?? '') . '-' . (string)($linea['descripcion'] ?? '') . '-' . (string)($linea['cantidad'] ?? '');
        if (in_array($uniqueId, $lineaIds, true)) {
            continue;
        }
        $lineaIds[] = $uniqueId;
        $hay = true;
        $cant = (float)($linea['cantidad'] ?? 0);
        $cantServ = (float)($linea['cantidad_servida'] ?? 0);
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)($linea['cod_articulo'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($linea['descripcion'] ?? '')) . '</td>';
        echo '<td>' . number_format($cant, 2, ',', '.') . '</td>';
        echo '<td style="' . (($cantServ !== $cant) ? 'color:red;' : '') . '">' . number_format($cantServ, 2, ',', '.') . '</td>';
        echo '<td>' . number_format((float)($linea['precio'] ?? 0), 2, ',', '.') . ' &euro;</td>';
        echo '<td>' . (((float)($linea['dto1'] ?? 0) != 0) ? htmlspecialchars((string)($linea['dto1'] ?? '')) . ' %' : '-') . '</td>';
        echo '<td>' . (((float)($linea['dto2'] ?? 0) != 0) ? htmlspecialchars((string)($linea['dto2'] ?? '')) . ' %' : '-') . '</td>';
        echo '<td>' . number_format((float)($linea['importe'] ?? 0), 2, ',', '.') . ' &euro;</td>';
        echo '<td>' . (!empty($linea['fecha_entrega']) ? date('d/m/Y', strtotime((string)$linea['fecha_entrega'])) : '-') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    if (!$hay) {
        echo '<p>No hay lineas asociadas a este pedido.</p>';
    }
    if ($pedidoEliminadoAjax) {
        $usuarioTxt = ($eliminadoUsuarioAjax !== '') ? htmlspecialchars($eliminadoUsuarioAjax) : '-';
        $equipoTxt = ($eliminadoEquipoAjax !== '') ? htmlspecialchars($eliminadoEquipoAjax) : '-';
        $fechaTxt = '-';
        if (!empty($eliminadoFechaAjax)) {
            $fechaRaw = (string)$eliminadoFechaAjax;
            $fechaTxt = date('d/m/Y', strtotime($fechaRaw)) . ' (' . obtenerDiaSemana($fechaRaw) . ')';
        }
        $horaTxt = (!empty($eliminadoHoraAjax)) ? date('H:i', strtotime((string)$eliminadoHoraAjax)) : '-';
        echo '<p style="margin-top:10px; font-weight:bold; color:#a94442;">VENTA ELIMINADA POR ' . $usuarioTxt . ' | EQUIPO: ' . $equipoTxt . ' | FECHA: ' . $fechaTxt . ' | HORA: ' . $horaTxt . '</p>';
    }
    exit;
}

// 1. OBTENER EL NOMBRE DE LA SECCIÓN
$sql_seccion = "
    SELECT nombre
    FROM [integral].[dbo].[secciones_cliente]
    WHERE cod_cliente = '" . addslashes($cod_cliente) . "'
      AND cod_seccion = '" . addslashes($cod_seccion) . "'
";
$result_seccion = odbc_exec($conn, $sql_seccion);
if (!$result_seccion) {
    error_log("Error al consultar la sección: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$seccion_data = odbc_fetch_array($result_seccion);
$nombre_seccion = $seccion_data ? $seccion_data['nombre'] : "Sección desconocida";

// 2. CONSULTA DE DATOS BÁSICOS (CLIENTE + SECCIÓN)
$sql = "
SELECT 
    c.cod_cliente, 
    c.nombre_comercial, 
    s.direccion1 AS direccion, 
    s.telefono, 
    s.telefono_comentario, 
    s.telefono_movil, 
    s.telefono_movil_comentario, 
    s.e_mail, 
    s.poblacion, 
    s.provincia,
    s.nombre_contacto,
    s.cargo_contacto
FROM [integral].[dbo].[clientes] c
INNER JOIN [integral].[dbo].[secciones_cliente] s 
   ON c.cod_cliente = s.cod_cliente
WHERE c.cod_cliente = '" . addslashes($cod_cliente) . "'
  AND s.cod_seccion = '" . addslashes($cod_seccion) . "'
";
$result = odbc_exec($conn, $sql);
if (!$result) {
    error_log("Error al ejecutar la consulta de datos de la sección: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$data = odbc_fetch_array($result);

// Para el <title> en el HTML
$pageTitle = ($data ? $data['nombre_comercial'] ?? '' : 'Cliente desconocido') . " - " . $nombre_seccion;

// -----------------------------------------------------------------------------
// FUNCIONES (colores, día de la semana, iconos de origen, etc.)
// -----------------------------------------------------------------------------
// Funciones comunes cargadas desde funciones.php:
// obtenerDiaSemana, determinarColorVisita, determinarColorPedido, iconoDeOrigen

// -----------------------------------------------------------------------------
// CONSULTA A cmf_asignacion_zonas_clientes PARA MOSTRAR HORARIO Y DEMÁS
// -----------------------------------------------------------------------------
$sql_asignacion = "
SELECT * 
FROM [integral].[dbo].[cmf_asignacion_zonas_clientes]
WHERE cod_cliente = '" . addslashes($cod_cliente) . "'
  AND cod_seccion = '" . addslashes($cod_seccion) . "'
";
$res_asignacion = odbc_exec($conn, $sql_asignacion);
$asignacion     = odbc_fetch_array($res_asignacion);

// -----------------------------------------------------------------------------
// CONSULTA DE VISITAS
// -----------------------------------------------------------------------------
$sql_visitas = "
SELECT 
    v.id_visita,
    v.observaciones,
    v.estado_visita,
    v.fecha_visita,
    v.hora_inicio_visita,
    v.hora_fin_visita
FROM [integral].[dbo].[cmf_visitas_comerciales] v
WHERE v.cod_cliente = '" . addslashes($cod_cliente) . "'
  AND v.cod_seccion = '" . addslashes($cod_seccion) . "'
ORDER BY v.fecha_visita DESC
";
$result_visitas = odbc_exec($conn, $sql_visitas);
if (!$result_visitas) {
    error_log("Error al ejecutar la consulta de visitas: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}

$visitas = array();
while ($visita = odbc_fetch_array($result_visitas)) {
    // Normalizar claves por diferencias de mayusculas/minusculas del driver ODBC.
    $visita['id_visita'] = (string)($visita['id_visita'] ?? $visita['ID_VISITA'] ?? '');
    $visita['estado_visita'] = (string)($visita['estado_visita'] ?? $visita['ESTADO_VISITA'] ?? '');
    $visita['fecha_visita'] = $visita['fecha_visita'] ?? $visita['FECHA_VISITA'] ?? null;
    $visita['hora_inicio_visita'] = $visita['hora_inicio_visita'] ?? $visita['HORA_INICIO_VISITA'] ?? null;
    $visita['hora_fin_visita'] = $visita['hora_fin_visita'] ?? $visita['HORA_FIN_VISITA'] ?? null;
    $visita['observaciones'] = (string)($visita['observaciones'] ?? $visita['OBSERVACIONES'] ?? '');

    // Origen principal (para color de la visita)
    $sql_pedido_principal = "
        SELECT TOP 1 vp.cod_venta, vp.origen
        FROM [integral].[dbo].[cmf_visita_pedidos] vp
        WHERE vp.id_visita = '" . addslashes($visita['id_visita']) . "'
        ORDER BY vp.id_visita_pedido ASC
    ";
    $res_pedido_principal = odbc_exec($conn_aux, $sql_pedido_principal);
    $pedido_principal     = odbc_fetch_array($res_pedido_principal);
    $origen_principal     = $pedido_principal ? ($pedido_principal['origen'] ?? $pedido_principal['ORIGEN'] ?? 'otros') : 'otros';

    $visita['color'] = determinarColorVisita($visita['estado_visita'] ?? '', $origen_principal);

    // Pedidos asociados
    $sql_pedidos = "
        SELECT
            vp.cod_venta AS cod_pedido,
            vp.origen
        FROM [integral].[dbo].[cmf_visita_pedidos] vp
        WHERE vp.id_visita = '" . addslashes($visita['id_visita']) . "'
        ORDER BY vp.id_visita_pedido ASC
    ";
    $res_pedidos = odbc_exec($conn_aux, $sql_pedidos);
    if ($res_pedidos) {
        $pedidos        = array();
        $importe_total  = 0.0;
        $lineas_total   = 0;
        while ($pedido = odbc_fetch_array($res_pedidos)) {
            // Normalizar claves por diferencias de mayusculas/minusculas del driver ODBC.
            $pedido['cod_pedido'] = (string)($pedido['cod_pedido'] ?? $pedido['COD_PEDIDO'] ?? $pedido['cod_venta'] ?? $pedido['COD_VENTA'] ?? '');
            $pedido['origen'] = (string)($pedido['origen'] ?? $pedido['ORIGEN'] ?? '');
            if ($pedido['cod_pedido'] === '') {
                continue;
            }
            $pedido = cargarResumenPedido($conn_aux2, $pedido['cod_pedido'], $pedido['origen']);
            $importe_total += (float)($pedido['importe'] ?? 0);
            $lineas_total  += $pedido['numero_lineas'];
            $pedidos[] = $pedido;
        }
        // Fallback: si no llegaron pedidos por la consulta principal, derivarlos
        // de la misma relacion que ya devuelve lineas de la visita.
        if (count($pedidos) === 0) {
            $sql_pedidos_fallback = "
                SELECT DISTINCT
                    vp.cod_venta AS cod_pedido,
                    vp.origen
                FROM [integral].[dbo].[cmf_visita_pedidos] vp
                INNER JOIN [integral].[dbo].[hist_ventas_linea] hl
                  ON vp.cod_venta = hl.cod_venta
                 AND hl.tipo_venta = 1
                WHERE vp.id_visita = '" . addslashes($visita['id_visita']) . "'
            ";
            $res_pedidos_fallback = odbc_exec($conn_aux, $sql_pedidos_fallback);
            if ($res_pedidos_fallback) {
                while ($pedido_fb = odbc_fetch_array($res_pedidos_fallback)) {
                    $pedido_fb_cod = (string)($pedido_fb['cod_pedido'] ?? $pedido_fb['COD_PEDIDO'] ?? $pedido_fb['cod_venta'] ?? $pedido_fb['COD_VENTA'] ?? '');
                    if ($pedido_fb_cod === '') {
                        continue;
                    }
                    $pedido = cargarResumenPedido($conn_aux2, $pedido_fb_cod, (string)($pedido_fb['origen'] ?? $pedido_fb['ORIGEN'] ?? ''));
                    $importe_total += (float)$pedido['importe'];
                    $lineas_total += (int)$pedido['numero_lineas'];
                    $pedidos[] = $pedido;
                }
            }
        }
        $visita['pedidos']            = $pedidos;
        $visita['importe_total']      = $importe_total;
        $visita['numero_lineas_total'] = $lineas_total;
    } else {
        $visita['pedidos']            = array();
        $visita['importe_total']      = 0;
        $visita['numero_lineas_total'] = 0;
    }

    $visitas[] = $visita;
}

$visitasPorPagina = 10;
$totalVisitas = count($visitas);
$totalPaginasVisitas = max(1, (int)ceil($totalVisitas / $visitasPorPagina));
$paginaVisitas = isset($_GET['pag_visitas']) ? max(1, (int)$_GET['pag_visitas']) : 1;
if ($paginaVisitas > $totalPaginasVisitas) {
    $paginaVisitas = $totalPaginasVisitas;
}
$offsetVisitas = ($paginaVisitas - 1) * $visitasPorPagina;
$visitasPaginadas = array_slice($visitas, $offsetVisitas, $visitasPorPagina);

// Datos para graficos (filtro por cliente + seccion)
$sqlGraficoLineas = "
    SELECT
        YEAR(hvc.fecha_venta) AS anio,
        MONTH(hvc.fecha_venta) AS mes,
        SUM(CASE WHEN hvc.tipo_venta = 1 THEN hvc.importe ELSE 0 END) AS total_pedidos,
        SUM(CASE WHEN hvc.tipo_venta = 2 THEN hvc.importe ELSE 0 END) AS total_albaranes
    FROM [integral].[dbo].[hist_ventas_cabecera] hvc
    WHERE hvc.cod_cliente = '" . addslashes($cod_cliente) . "'
      AND hvc.cod_seccion = '" . addslashes($cod_seccion) . "'
      AND hvc.fecha_venta >= '2024-10-01'
      AND hvc.fecha_venta <= GETDATE()
    GROUP BY YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta)
    ORDER BY YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta)
";
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlGraficoLineas = str_replace(
        "AND hvc.fecha_venta <= GETDATE()",
        "AND hvc.fecha_venta <= GETDATE()\n      AND hvc.cod_comisionista = '" . addslashes((string)$cod_comercial) . "'",
        $sqlGraficoLineas
    );
}
$resultGraficoLineas = odbc_exec($conn, $sqlGraficoLineas);
$datosDict = array();
if ($resultGraficoLineas) {
    while ($rowG = odbc_fetch_array($resultGraficoLineas)) {
        $anio = (int)($rowG['anio'] ?? $rowG['ANIO'] ?? 0);
        $mes = (int)($rowG['mes'] ?? $rowG['MES'] ?? 0);
        $periodo = sprintf('%04d-%02d', $anio, $mes);
        $datosDict[$periodo] = array(
            'pedidos' => (float)($rowG['total_pedidos'] ?? $rowG['TOTAL_PEDIDOS'] ?? 0),
            'albaranes' => (float)($rowG['total_albaranes'] ?? $rowG['TOTAL_ALBARANES'] ?? 0)
        );
    }
}
$fechaInicio = new DateTime('2024-10-01');
$fechaFin = new DateTime();
$fechaFin->modify('last day of this month');
$intervalo = new DateInterval('P1M');
$periodo = new DatePeriod($fechaInicio, $intervalo, $fechaFin);
$datosMensuales = array();
foreach ($periodo as $dt) {
    $mesClave = $dt->format('Y-m');
    $pedidos = isset($datosDict[$mesClave]) ? (float)$datosDict[$mesClave]['pedidos'] : 0.0;
    $albaranes = isset($datosDict[$mesClave]) ? (float)$datosDict[$mesClave]['albaranes'] : 0.0;
    $datosMensuales[] = array('periodo' => $mesClave, 'pedidos' => $pedidos, 'albaranes' => $albaranes);
}
$datosMensualesJson = json_encode($datosMensuales);

$sqlDetalleBarras = "
    SELECT
        YEAR(hvc.fecha_venta) AS anio,
        MONTH(hvc.fecha_venta) AS mes,
        hvc.tipo_venta,
        hl.cod_articulo,
        COALESCE(ad.descripcion, hl.descripcion) AS descripcion,
        SUM(hl.cantidad) AS cantidad,
        SUM(hl.importe) AS importe
    FROM [integral].[dbo].[hist_ventas_linea] hl
    INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc
        ON hl.cod_venta = hvc.cod_venta
       AND hl.tipo_venta = hvc.tipo_venta
    LEFT JOIN [integral].[dbo].[articulo_descripcion] ad
        ON ad.cod_articulo = hl.cod_articulo
       AND ad.cod_idioma = 'ES'
    WHERE hvc.cod_cliente = '" . addslashes($cod_cliente) . "'
      AND hvc.cod_seccion = '" . addslashes($cod_seccion) . "'
      AND hvc.fecha_venta >= '2024-10-01'
      AND hvc.fecha_venta <= GETDATE()
      AND hvc.tipo_venta IN (1, 2)
    GROUP BY
        YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta), hvc.tipo_venta, hl.cod_articulo, COALESCE(ad.descripcion, hl.descripcion)
    ORDER BY
        YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta), hvc.tipo_venta, SUM(hl.importe) DESC
";
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlDetalleBarras = str_replace(
        "AND hvc.tipo_venta IN (1, 2)",
        "AND hvc.tipo_venta IN (1, 2)\n      AND hvc.cod_comisionista = '" . addslashes((string)$cod_comercial) . "'",
        $sqlDetalleBarras
    );
}
$resDetalleBarras = odbc_exec($conn, $sqlDetalleBarras);
$detalleBarras = array();
if ($resDetalleBarras) {
    while ($rowD = odbc_fetch_array($resDetalleBarras)) {
        $anio = (int)($rowD['anio'] ?? $rowD['ANIO'] ?? 0);
        $mes = (int)($rowD['mes'] ?? $rowD['MES'] ?? 0);
        $tipo = (int)($rowD['tipo_venta'] ?? $rowD['TIPO_VENTA'] ?? 0);
        $keyMes = sprintf('%04d-%02d', $anio, $mes);
        $codArticulo = (string)($rowD['cod_articulo'] ?? $rowD['COD_ARTICULO'] ?? '');
        $descripcion = toUTF8((string)($rowD['descripcion'] ?? $rowD['DESCRIPCION'] ?? ''));
        $keyArticulo = $codArticulo . '|' . $descripcion;

        if (!isset($detalleBarras[$keyMes])) {
            $detalleBarras[$keyMes] = array();
        }
        if (!isset($detalleBarras[$keyMes][$keyArticulo])) {
            $detalleBarras[$keyMes][$keyArticulo] = array(
                'cod_articulo' => $codArticulo,
                'descripcion' => $descripcion,
                'cantidad_pedido' => 0.0,
                'importe_pedido' => 0.0,
                'cantidad_albaran' => 0.0,
                'importe_albaran' => 0.0
            );
        }
        if ($tipo === 1) {
            $detalleBarras[$keyMes][$keyArticulo]['cantidad_pedido'] += (float)($rowD['cantidad'] ?? $rowD['CANTIDAD'] ?? 0);
            $detalleBarras[$keyMes][$keyArticulo]['importe_pedido'] += (float)($rowD['importe'] ?? $rowD['IMPORTE'] ?? 0);
        } elseif ($tipo === 2) {
            $detalleBarras[$keyMes][$keyArticulo]['cantidad_albaran'] += (float)($rowD['cantidad'] ?? $rowD['CANTIDAD'] ?? 0);
            $detalleBarras[$keyMes][$keyArticulo]['importe_albaran'] += (float)($rowD['importe'] ?? $rowD['IMPORTE'] ?? 0);
        }
    }
    foreach ($detalleBarras as $mesKey => $items) {
        $lista = array_values($items);
        usort($lista, function($a, $b) {
            $totalA = (float)$a['importe_pedido'] + (float)$a['importe_albaran'];
            $totalB = (float)$b['importe_pedido'] + (float)$b['importe_albaran'];
            return $totalB <=> $totalA;
        });
        $detalleBarras[$mesKey] = $lista;
    }
}
$detalleBarrasJson = json_encode($detalleBarras, JSON_UNESCAPED_UNICODE);

$sqlGraficoFamilia = "
    SELECT YEAR(hc.fecha_venta) AS anio, MONTH(hc.fecha_venta) AS mes, fam.cod_familia, fam.descripcion AS familia, SUM(hl.importe) AS total_familia
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc ON hl.cod_venta = hc.cod_venta
JOIN [integral].[dbo].[articulos] art ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[familias] fam ON art.cod_familia = fam.cod_familia
    WHERE hc.cod_cliente = '" . addslashes($cod_cliente) . "'
      AND hc.cod_seccion = '" . addslashes($cod_seccion) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlGraficoFamilia .= " AND hc.cod_comisionista = '" . addslashes((string)$cod_comercial) . "'";
}
$sqlGraficoFamilia .= " GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), fam.cod_familia, fam.descripcion ORDER BY anio, mes";
$resultFamilia = odbc_exec($conn, $sqlGraficoFamilia);
$datosFamiliaMensual = array();
if ($resultFamilia) {
    while ($rowF = odbc_fetch_array($resultFamilia)) {
        $datosFamiliaMensual[] = array(
            'anio' => (int)($rowF['anio'] ?? $rowF['ANIO'] ?? 0),
            'mes' => (int)($rowF['mes'] ?? $rowF['MES'] ?? 0),
            'cod_familia' => (string)($rowF['cod_familia'] ?? $rowF['COD_FAMILIA'] ?? ''),
            'familia' => toUTF8((string)($rowF['familia'] ?? $rowF['FAMILIA'] ?? '')),
            'importe' => (float)($rowF['total_familia'] ?? $rowF['TOTAL_FAMILIA'] ?? 0)
        );
    }
}
$datosFamiliaMensualJson = json_encode($datosFamiliaMensual);

$sqlGraficoMarca = "
    SELECT YEAR(hc.fecha_venta) AS anio, MONTH(hc.fecha_venta) AS mes, mar.descripcion AS marca, art.cod_familia, SUM(hl.importe) AS total_marca
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc ON hl.cod_venta = hc.cod_venta
JOIN [integral].[dbo].[articulos] art ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[web_marcas] mar ON art.cod_marca_web = mar.cod_marca
    WHERE hc.cod_cliente = '" . addslashes($cod_cliente) . "'
      AND hc.cod_seccion = '" . addslashes($cod_seccion) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlGraficoMarca .= " AND hc.cod_comisionista = '" . addslashes((string)$cod_comercial) . "'";
}
$sqlGraficoMarca .= " GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), mar.descripcion, art.cod_familia ORDER BY anio, mes";
$resultMarca = odbc_exec($conn, $sqlGraficoMarca);
$datosMarcaMensual = array();
if ($resultMarca) {
    while ($rowM = odbc_fetch_array($resultMarca)) {
        $datosMarcaMensual[] = array(
            'anio' => (int)($rowM['anio'] ?? $rowM['ANIO'] ?? 0),
            'mes' => (int)($rowM['mes'] ?? $rowM['MES'] ?? 0),
            'marca' => toUTF8((string)($rowM['marca'] ?? $rowM['MARCA'] ?? '')),
            'cod_familia' => (string)($rowM['cod_familia'] ?? $rowM['COD_FAMILIA'] ?? ''),
            'importe' => (float)($rowM['total_marca'] ?? $rowM['TOTAL_MARCA'] ?? 0)
        );
    }
}
$datosMarcaMensualJson = json_encode($datosMarcaMensual);

$sqlGraficoArticulos = "
    SELECT YEAR(hc.fecha_venta) AS anio, MONTH(hc.fecha_venta) AS mes, ad.descripcion AS articulo, art.cod_familia, SUM(hl.importe) AS total_articulo
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc ON hl.cod_venta = hc.cod_venta
JOIN [integral].[dbo].[articulos] art ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[articulo_descripcion] ad ON ad.cod_articulo = art.cod_articulo AND ad.cod_idioma = 'ES'
    WHERE hc.cod_cliente = '" . addslashes($cod_cliente) . "'
      AND hc.cod_seccion = '" . addslashes($cod_seccion) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlGraficoArticulos .= " AND hc.cod_comisionista = '" . addslashes((string)$cod_comercial) . "'";
}
$sqlGraficoArticulos .= " GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), ad.descripcion, art.cod_familia ORDER BY anio, mes";
$resultArt = odbc_exec($conn, $sqlGraficoArticulos);
$datosArticulosMensual = array();
if ($resultArt) {
    while ($rowA = odbc_fetch_array($resultArt)) {
        $datosArticulosMensual[] = array(
            'anio' => (int)($rowA['anio'] ?? $rowA['ANIO'] ?? 0),
            'mes' => (int)($rowA['mes'] ?? $rowA['MES'] ?? 0),
            'articulo' => toUTF8((string)($rowA['articulo'] ?? $rowA['ARTICULO'] ?? '')),
            'cod_familia' => (string)($rowA['cod_familia'] ?? $rowA['COD_FAMILIA'] ?? ''),
            'importe' => (float)($rowA['total_articulo'] ?? $rowA['TOTAL_ARTICULO'] ?? 0)
        );
    }
}
$datosArticulosMensualJson = json_encode($datosArticulosMensual);

include_once BASE_PATH . '/resources/views/layouts/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle ?? ''); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">

    <!-- Font Awesome 4.7 (para iconos) -->
    
    <!-- Estilos unificados con cliente_detalles.php -->
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f4f4f4;
        }
        td {
            background-color: #f9f9f9;
        }
        a {
            text-decoration: none;
            color: #007BFF;
        }
        a:hover {
            text-decoration: underline;
        }
        .back-button {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            margin-top: 20px;
            display: inline-block;
        }
        .back-button:hover {
            background-color: #0056b3;
        }
        .faltas-button, .historico-button {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        .faltas-button {
            background-color: #ff4d4d; 
            color: white;
        }
        .faltas-button:hover {
            background-color: #e63939; 
            transform: translateY(-2px);
        }
        .historico-button {
            background-color: #28a745;
            color: white;
        }
        .historico-button:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .button-container {
            text-align: center;
            margin-top: 30px;
        }
        .moroso {
            color: red;
            font-weight: bold;
            text-align: center;
            font-size: 18px;
        }
        .visitas-container {
            margin-top: 40px;
        }
        .visita-item {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            color: #fff;
            display: block;
            transition: background-color 0.3s ease, transform 0.2s;
            cursor: pointer;
        }
        .visita-item:hover {
            opacity: 0.9;
            transform: scale(1.01);
        }
        .visita-linea {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .visita-linea span {
            margin-right: 20px;
            font-size: 16px;
        }
        .visita-observaciones {
            display: block;
            margin-top: 5px;
            font-style: italic;
            color: #ffffff; 
        }
        @media screen and (max-width: 1024px) {
            .visita-linea {
                flex-direction: column;
                align-items: flex-start;
            }
            .visita-linea span {
                margin-right: 0;
                margin-bottom: 5px;
            }
        }
        .pedido-item {
            position: relative;
            background: #fff;
            padding: 15px 20px;
            margin-left: 40px; 
            margin-bottom: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .pedido-item:hover {
            transform: scale(1.02);
        }
        .pedido-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            width: 8px;
            height: 100%;
            border-radius: 5px 0 0 5px;
            background-color: #6c757d;
        }
        .pedido-info {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .pedido-info > div {
            margin-right: 20px;
            margin-bottom: 5px;
        }
        .pedido-observaciones {
            font-style: italic;
            color: #007bff;
        }
        .label {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            color: #fff;
            vertical-align: middle;
        }
        .label-warning {
            background: #f0ad4e;
        }
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 90%; 
            max-width: 1200px;
            border-radius: 10px;
            position: relative;
        }
        .close {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            z-index: 9999;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-table-container {
            width: 100%;
            overflow-x: auto; 
        }
        .modal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        .modal-table th, .modal-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .modal-table th {
            background-color: #f4f4f4;
        }
        .chart-col {
            margin-bottom: 20px;
        }
        .leyenda-diff {
            margin: 8px 0 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            font-size: 12px;
        }
        .leyenda-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .leyenda-color {
            width: 14px;
            height: 14px;
            border: 1px solid #bbb;
            border-radius: 3px;
            display: inline-block;
        }
        .diff-cantidad-pedido-mayor { background: #ffe5e5; }
        .diff-cantidad-albaran-mayor { background: #e7f7ea; }
        .diff-importe-pedido-mayor { background: #fff6d6; }
        .diff-importe-albaran-mayor { background: #e8f1ff; }
        .descripcion-con-observacion {
            position: relative;
        }
        .descripcion-con-observacion .observacion {
            display: block;
            color: #007bff;
            font-style: italic;
            margin-top: 5px;
        }

        /* Botones circulares para actualizar origen / quitar pedido */
        .pedido-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        .btn-circle {
            border-radius: 50%;
            width: 45px;
            height: 45px;
            font-size: 18px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
            border: none;
            outline: none;
        }
        .btn-visita   { background-color: #28a745; color: #fff; }
        .btn-telefono { background-color: #ffc107; color: #fff; }
        .btn-whatsapp { background-color: #25D366; color: #fff; }
        .btn-email    { background-color: #17a2b8; color: #fff; }
        .btn-eliminar { background-color: #dc3545; color: #fff; }
        .btn[disabled] { background-color: grey !important; color: #fff !important; cursor: not-allowed; }
        @media screen and (max-width: 1024px) {
            .pedido-actions {
                right: 70px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <?php if ($data): ?>
        
        <table>
            <tr>
                <th>C&oacute;digo Cliente</th>
                <td><?php echo htmlspecialchars($data['cod_cliente'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Nombre Comercial</th>
                <td><?php echo htmlspecialchars($data['nombre_comercial'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Direcci&oacute;n</th>
                <td><?php echo htmlspecialchars($data['direccion'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Poblaci&oacute;n</th>
                <td><?php echo htmlspecialchars($data['poblacion'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Provincia</th>
                <td><?php echo htmlspecialchars($data['provincia'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Nombre Contacto</th>
                <td><?php echo htmlspecialchars($data['nombre_contacto'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Cargo Contacto</th>
                <td><?php echo htmlspecialchars($data['cargo_contacto'] ?? ''); ?></td>
            </tr>
            <tr>
                <th>Tel&eacute;fonos</th>
                <td>
                    <?php 
                        echo htmlspecialchars($data['telefono'] ?? ''); 
                        if (!empty($data['telefono_comentario'])) {
                            echo " (" . htmlspecialchars($data['telefono_comentario'] ?? '') . ")";
                        }
                        if (!empty($data['telefono_movil'])) {
                            echo " / " . htmlspecialchars($data['telefono_movil'] ?? '');
                            if (!empty($data['telefono_movil_comentario'])) {
                                echo " (" . htmlspecialchars($data['telefono_movil_comentario'] ?? '') . ")";
                            }
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <th>Email</th>
                <td>
                    <a href="mailto:<?php echo htmlspecialchars($data['e_mail'] ?? ''); ?>">
                        <?php echo htmlspecialchars($data['e_mail'] ?? ''); ?>
                    </a>
                </td>
            </tr>
        </table>
    <?php else: ?>
        <p>No se encontraron datos para la secci&oacute;n especificada.</p>
    <?php endif; ?>

    <!-- RECUADRO DE ZONA / HORARIO / TIEMPO PROMEDIO / FRECUENCIA / OBSERVACIONES -->
    <?php if ($asignacion): 
        // Zona Principal
        $nombreZonaPrincipal = '';
        if (!empty($asignacion['zona_principal'])) {
            $sql_zp = "
                SELECT nombre_zona
                FROM [integral].[dbo].[cmf_zonas_visita]
                WHERE cod_zona = '" . addslashes($asignacion['zona_principal']) . "'
            ";
            $res_zp = odbc_exec($conn, $sql_zp);
            $zp_data = odbc_fetch_array($res_zp);
            $nombreZonaPrincipal = $zp_data ? $zp_data['nombre_zona'] : $asignacion['zona_principal'];
        }

        // Zona Secundaria
        $nombreZonaSecundaria = '';
        if (!empty($asignacion['zona_secundaria'])) {
            $sql_zs = "
                SELECT nombre_zona
                FROM [integral].[dbo].[cmf_zonas_visita]
                WHERE cod_zona = '" . addslashes($asignacion['zona_secundaria']) . "'
            ";
            $res_zs = odbc_exec($conn, $sql_zs);
            $zs_data = odbc_fetch_array($res_zs);
            $nombreZonaSecundaria = $zs_data ? $zs_data['nombre_zona'] : $asignacion['zona_secundaria'];
        }

        // Frecuencia
        $freq = strtolower($asignacion['frecuencia_visita'] ?? '');
        switch ($freq) {
            case 'todos':
                $frecuenciaTexto = "Todos los meses";
                break;
            case 'cada2':
                $frecuenciaTexto = "Cada 2 meses";
                break;
            case 'cada3':
                $frecuenciaTexto = "Cada 3 meses";
                break;
            case 'nunca':
                $frecuenciaTexto = "No se visita normalmente";
                break;
            default:
                $frecuenciaTexto = htmlspecialchars($asignacion['frecuencia_visita'] ?? '');
                break;
        }

        // Preferencia Horaria
        $pref = strtolower($asignacion['preferencia_horaria'] ?? '');
        $estiloManana = ($pref == 'm' || $pref == 'mañana') ? "background-color: #ffc107; padding:2px 4px;" : "";
        $estiloTarde  = ($pref == 't' || $pref == 'tarde')   ? "background-color: #007bff; color:#fff; padding:2px 4px;" : "";

        // Tiempo Promedio
        $tp = (float)($asignacion['tiempo_promedio_visita'] ?? 0);
        $horas   = floor($tp);
        $minutos = round(($tp - $horas) * 60);
        if ($horas == 0 && $minutos > 0) {
            $tiempoPromedioTexto = "{$minutos} minutos";
        } elseif ($horas > 0) {
            $tiempoPromedioTexto = "{$horas} horas {$minutos} minutos";
        } else {
            $tiempoPromedioTexto = "0 minutos";
        }

        $horaInicioManana = substr($asignacion['hora_inicio_manana'] ?? '', 0, 5);
        $horaFinManana    = substr($asignacion['hora_fin_manana'] ?? '', 0, 5);
        $horaInicioTarde  = substr($asignacion['hora_inicio_tarde'] ?? '', 0, 5);
        $horaFinTarde     = substr($asignacion['hora_fin_tarde'] ?? '', 0, 5);

        $observacionesAsign = trim($asignacion['observaciones'] ?? '');
        ?>
        <table style="width:100%; border:1px solid #ddd; background:#fdfdfd; margin-top:20px;">
            <tr>
                <th style="padding:8px;">Zona Principal</th>
                <td style="padding:8px;"><?php echo htmlspecialchars($nombreZonaPrincipal ?? ''); ?></td>
            </tr>
            <?php if (!empty($nombreZonaSecundaria)): ?>
            <tr>
                <th style="padding:8px;">Zona Secundaria</th>
                <td style="padding:8px;"><?php echo htmlspecialchars($nombreZonaSecundaria ?? ''); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th style="padding:8px;">Tiempo Promedio / Frecuencia</th>
                <td style="padding:8px;">
                    Tiempo Promedio:
                    <span id="promedio_valor">
                        <?php echo $tiempoPromedioTexto; ?>
                    </span>
                    <button type="button" class="btn btn-sm btn-info" 
                            onclick="calcularPromedioVisita('<?php echo addslashes($cod_cliente); ?>','<?php echo addslashes($cod_seccion); ?>')">
                        Calcular
                    </button>
                    &nbsp; | &nbsp; 
                    Frecuencia: <strong><?php echo $frecuenciaTexto; ?></strong>
                </td>
            </tr>
            <tr>
                <th style="padding:8px;">Horarios</th>
                <td style="padding:8px;">
                    <span style="<?php echo $estiloManana; ?>">
                        Ma&ntilde;ana: <?php echo $horaInicioManana; ?> - <?php echo $horaFinManana; ?>
                    </span>
                    &nbsp; | &nbsp;
                    <span style="<?php echo $estiloTarde; ?>">
                        Tarde: <?php echo $horaInicioTarde; ?> - <?php echo $horaFinTarde; ?>
                    </span>
                </td>
            </tr>
            <?php if (!empty($observacionesAsign)): ?>
            <tr>
                <th style="padding:8px;">Observaciones</th>
                <td style="padding:8px;"><?php echo htmlspecialchars($observacionesAsign ?? ''); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="2" align="center" style="padding:8px;">
                    <a href="registrar_visita_manual.php?cod_cliente=<?php echo urlencode($cod_cliente); ?>&cod_seccion=<?php echo urlencode($cod_seccion); ?>" 
                       class="btn btn-primary">
                       Registrar Visita Manual
                    </a>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <div style="margin-top: 40px;">
        <div style="display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-bottom:10px;">
            <label for="yearsWindow" style="font-weight:600;">A&ntilde;os:</label>
            <select id="yearsWindow" class="form-select form-select-sm" style="width:auto;">
                <option value="2" selected>Ultimos 2</option>
                <option value="3">Ultimos 3</option>
                <option value="4">Ultimos 4</option>
                <option value="all">Todos</option>
            </select>
        </div>
        <div style="position:relative;height:420px;">
            <canvas id="graficoLineas"></canvas>
        </div>
    </div>
    <div id="modal-detalle-barras" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modal-detalle-barras')">&times;</span>
            <h3 id="detalleBarrasTitulo">Detalle</h3>
            <div id="detalleBarrasContenido"></div>
        </div>
    </div>

    <div class="row" style="margin-top: 30px;">
        <div class="col-12 col-md-4 chart-col">
            <h4 style="text-align:center;"><br></h4>
            <canvas id="graficoFamilia" width="300" height="300"></canvas>
        </div>
        <div class="col-12 col-md-4 chart-col">
            <h4 style="text-align:center;"><br></h4>
            <canvas id="graficoMarca" width="300" height="300"></canvas>
        </div>
        <div class="col-12 col-md-4 chart-col">
            <h4 style="text-align:center;"><br></h4>
            <canvas id="graficoArticulos" width="300" height="300"></canvas>
        </div>
    </div>

    <!-- VISITAS DE LA SECCI&Oacute;N -->
    <?php if ((isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') || (isset($_SESSION['perm_planificador']) && (int)$_SESSION['perm_planificador'] === 1)): ?>
    <div class="visitas-container">
        <h2>Visitas de la Secci&oacute;n (<?php echo (int)$totalVisitas; ?>)</h2>
        <?php if ($totalVisitas > 0): ?>
            <?php foreach ($visitasPaginadas as $visita): ?>
                <div class="visita-item"
                     style="background-color: <?php echo $visita['color'] ?? ''; ?>;"
                     data-id-visita="<?php echo htmlspecialchars($visita['id_visita'] ?? ''); ?>">
                    <div class="visita-linea">
                        <span class="visita-fecha">
                            &#128197; <?php echo date("d/m/Y", strtotime($visita['fecha_visita'] ?? '')); ?>
                            (<?php echo obtenerDiaSemana($visita['fecha_visita'] ?? ''); ?>)
                        </span>
                        <span class="visita-horas">
                            &#9200; <?php echo date("H:i", strtotime($visita['hora_inicio_visita'] ?? '')); ?>
                             - 
                            <?php echo date("H:i", strtotime($visita['hora_fin_visita'] ?? '')); ?>
                        </span>
                        <span class="visita-importe">
                            &#128176; <?php echo number_format($visita['importe_total'] ?? 0, 2, ',', '.'); ?> &euro;
                        </span>
                        <span class="visita-lineas">
                            &#128221; <?php echo htmlspecialchars((string)($visita['numero_lineas_total'] ?? '')); ?>
                        </span>
                    </div>
                    <?php if (!empty($visita['observaciones'])): ?>
                        <br><span class="visita-observaciones">
                            &#9999; <?php echo htmlspecialchars($visita['observaciones'] ?? ''); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Modal de Visita -->
                <div id="modal-visita-<?php echo htmlspecialchars($visita['id_visita'] ?? ''); ?>" class="modal">
                    <div class="modal-content">
                        <span class="close" 
                              onclick="cerrarModal('modal-visita-<?php echo htmlspecialchars($visita['id_visita'] ?? ''); ?>')">
                              &times;
                        </span>
                        <h3>Detalles de la Visita <?php echo htmlspecialchars($visita['id_visita'] ?? ''); ?></h3>

                        <p><strong>&#128197; Fecha:</strong> 
                            <?php echo date("d/m/Y", strtotime($visita['fecha_visita'] ?? '')); ?>
                            (<?php echo obtenerDiaSemana($visita['fecha_visita'] ?? ''); ?>)
                        </p>
                        <p><strong>&#9200; Hora de Inicio:</strong> 
                            <?php echo date("H:i", strtotime($visita['hora_inicio_visita'] ?? '')); ?>
                        </p>
                        <p><strong>&#9200; Hora de Fin:</strong> 
                            <?php echo date("H:i", strtotime($visita['hora_fin_visita'] ?? '')); ?>
                        </p>
                        <p><strong>&#128176; Importe Total:</strong> 
                            <?php echo number_format($visita['importe_total'] ?? 0, 2, ',', '.'); ?> &euro;
                        </p>
                        <p><strong>&#128221; N&uacute;mero de L&iacute;neas:</strong> 
                            <?php echo htmlspecialchars((string)($visita['numero_lineas_total'] ?? '')); ?>
                        </p>
                        <p><strong>&#128221; Observaciones:</strong> 
                            <?php echo htmlspecialchars($visita['observaciones'] ?? ''); ?>
                        </p>

                        <h4>L&iacute;neas de Pedidos Asociados</h4>
                        <div id="lineas-visita-<?php echo htmlspecialchars((string)($visita['id_visita'] ?? '')); ?>" data-loaded="0">
                            <p>Pulsa para cargar las l&iacute;neas de esta visita.</p>
                        </div>
                    </div>
                </div>

                <!-- Pedidos Asociados SIEMPRE VISIBLES -->
                <?php
                    $pedidosVisita = (isset($visita['pedidos']) && is_array($visita['pedidos'])) ? $visita['pedidos'] : array();
                    if (count($pedidosVisita) === 0 && (float)($visita['importe_total'] ?? 0) > 0 && !empty($visita['id_visita'])) {
                        // Reconstruccion defensiva en render: si hay importe en la visita pero no pedidos cargados.
                        $sql_pedidos_render = "
                            SELECT vp.cod_venta AS cod_pedido, vp.origen
                            FROM [integral].[dbo].[cmf_visita_pedidos] vp
                            WHERE vp.id_visita = '" . addslashes((string)$visita['id_visita']) . "'
                            ORDER BY vp.id_visita_pedido ASC
                        ";
                        $res_pedidos_render = odbc_exec($conn, $sql_pedidos_render);
                        if ($res_pedidos_render) {
                            while ($p = odbc_fetch_array($res_pedidos_render)) {
                                $codPedidoRender = (string)($p['cod_pedido'] ?? $p['COD_PEDIDO'] ?? $p['cod_venta'] ?? $p['COD_VENTA'] ?? '');
                                if ($codPedidoRender === '') {
                                    continue;
                                }
                                $pedidoRender = cargarResumenPedido(
                                    $conn,
                                    $codPedidoRender,
                                    (string)($p['origen'] ?? $p['ORIGEN'] ?? '')
                                );
                                $pedidosVisita[] = $pedidoRender;
                            }
                        }
                    }
                ?>
                <?php if (count($pedidosVisita) > 0): ?>
                    <?php foreach ($pedidosVisita as $pedido): ?>
                        <?php
                            $pedidoCod = (string)($pedido['cod_pedido'] ?? '');
                            $pedidoOrigen = (string)($pedido['origen'] ?? '');
                            $pedidoFecha = (string)($pedido['fecha_venta'] ?? '');
                            $pedidoHora = (string)($pedido['hora_venta'] ?? '');
                            $pedidoImporte = (float)($pedido['importe'] ?? 0);
                            $pedidoLineas = (int)($pedido['numero_lineas'] ?? 0);
                            $pedidoObs = (string)($pedido['observacion_interna'] ?? '');
                            $pedidoEliminado = ((int)($pedido['pedido_eliminado'] ?? 0) === 1);
                            $pedidoFechaTxt = ($pedidoFecha !== '') ? date("d/m/Y", strtotime($pedidoFecha)) : '-';
                            $pedidoDiaTxt = ($pedidoFecha !== '') ? obtenerDiaSemana($pedidoFecha) : '-';
                            $pedidoHoraTxt = ($pedidoHora !== '') ? date("H:i", strtotime($pedidoHora)) : '-';
                        ?>
                        <div class="pedido-item"
                             style="border-left: 8px solid <?php echo determinarColorPedido($pedidoOrigen); ?>;"
                             data-cod-pedido="<?php echo htmlspecialchars($pedidoCod); ?>">
                            
                            <div class="pedido-info">
                                <div>
                                    <?php echo iconoDeOrigen($pedidoOrigen); ?>
                                    <strong><?php echo htmlspecialchars($pedidoCod); ?></strong>
                                    <?php if ($pedidoEliminado): ?>
                                        <span class="label label-warning">Eliminado</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong>&#128197;</strong> 
                                    <?php echo $pedidoFechaTxt; ?>
                                    (<?php echo $pedidoDiaTxt; ?>)
                                </div>
                                <div>
                                    <strong>&#9200;</strong> 
                                    <?php echo $pedidoHoraTxt; ?>
                                </div>
                                <div>
                                    <strong>&#128176;</strong> 
                                    <?php echo number_format($pedidoImporte, 2, ',', '.') . " &euro;"; ?>
                                </div>
                                <div>
                                    <strong>&#128221; L&iacute;neas:</strong> 
                                    <?php echo htmlspecialchars((string)$pedidoLineas); ?>
                                </div>
                            </div>

                            <?php if ($pedidoObs !== ''): ?>
                                <div class="pedido-observaciones">
                                    &#9999; <?php echo htmlspecialchars($pedidoObs); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Modal de Pedido (igual que en cliente_detalles.php) -->
                        <div id="modal-pedido-<?php echo htmlspecialchars($pedidoCod); ?>" class="modal">
                            <div class="modal-content">
                                <span class="close" 
                                      onclick="cerrarModal('modal-pedido-<?php echo htmlspecialchars($pedidoCod); ?>')">
                                      &times;
                                </span>
                                
                                <div class="pedido-actions">
                                    <!-- Bot&oacute;n para quitar el pedido de la visita -->
                                    <button class="btn btn-circle btn-eliminar" 
                                            title="Quitar pedido de la visita" 
                                            onclick="quitarPedido('<?php echo htmlspecialchars($pedidoCod); ?>', event)">
                                        <i class="fa fa-calendar-times"></i>
                                    </button>
                                    <?php 
                                    $origen_actual = strtolower($pedidoOrigen);
                                    $opciones = array(
                                        'visita'    => 'btn-visita',
                                        'telefono'  => 'btn-telefono',
                                        'whatsapp'  => 'btn-whatsapp',
                                        'email'     => 'btn-email'
                                    );
                                    foreach ($opciones as $opcion => $btn_class):
                                        $disabled = ($origen_actual == $opcion) ? 'disabled style="background-color: grey;"' : '';
                                    ?>
                                        <button class="btn btn-circle <?php echo $btn_class; ?>" 
                                                title="Cambiar origen a <?php echo ucfirst($opcion); ?>"
                                                onclick="actualizarOrigen('<?php echo htmlspecialchars($pedidoCod); ?>', '<?php echo $opcion; ?>', event)"
                                                <?php echo $disabled; ?>>
                                            <?php
                                            $iconos = array(
                                                'visita'   => 'fa-solid fa-calendar',
                                                'telefono' => 'fa-solid fa-phone',
                                                'whatsapp' => 'fa-brands fa-whatsapp',
                                                'email'    => 'fa-solid fa-envelope'
                                            );
                                            echo '<i class="'.$iconos[$opcion].'"></i>';
                                            ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <h3>Detalles del Pedido <?php echo htmlspecialchars($pedidoCod); ?></h3>
                                <?php if ($pedidoEliminado): ?>
                                    <p><span class="label label-warning">Pedido eliminado</span></p>
                                <?php endif; ?>
                                <p>
                                    <strong>&#128197; Fecha de Venta:</strong> 
                                    <?php echo $pedidoFechaTxt; ?>
                                    (<?php echo $pedidoDiaTxt; ?>)
                                </p>
                                <p>
                                    <strong>&#9200; Hora de Venta:</strong> 
                                    <?php echo $pedidoHoraTxt; ?>
                                </p>
                                <p>
                                    <strong>&#128176; Importe:</strong> 
                                    <?php echo number_format($pedidoImporte, 2, ',', '.') . " &euro;"; ?>
                                </p>
                                <p>
                                    <strong>&#128221; N&uacute;mero de L&iacute;neas:</strong> 
                                    <?php echo htmlspecialchars((string)$pedidoLineas); ?>
                                </p>
                                <?php if ($pedidoObs !== ''): ?>
                                    <p>
                                        <strong>&#9999; Observaciones Internas:</strong> 
                                    <?php echo htmlspecialchars($pedidoObs); ?>
                                    </p>
                                <?php endif; ?>

                                <h4>L&iacute;neas del Pedido</h4>
                                <div id="lineas-pedido-<?php echo htmlspecialchars($pedidoCod); ?>" data-loaded="0">
                                    <p>Pulsa para cargar las l&iacute;neas de este pedido.</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($totalPaginasVisitas > 1): ?>
                <?php
                $queryVisitas = $_GET;
                unset($queryVisitas['pag_visitas'], $queryVisitas['ajax'], $queryVisitas['id_visita'], $queryVisitas['cod_pedido']);
                $baseVisitas = basename((string)($_SERVER['PHP_SELF'] ?? 'seccion_detalles.php'));
                ?>
                <div style="margin: 15px 0; text-align: center;">
                    <?php if ($paginaVisitas > 1): ?>
                        <a class="back-button" style="margin-right:8px;" href="<?php echo htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, array('pag_visitas' => $paginaVisitas - 1)))); ?>">&larr; Anterior</a>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPaginasVisitas; $p++): ?>
                        <?php if ($p === $paginaVisitas): ?>
                            <span class="back-button" style="background:#6c757d;cursor:default;margin-right:8px;"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a class="back-button" style="margin-right:8px;" href="<?php echo htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, array('pag_visitas' => $p)))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($paginaVisitas < $totalPaginasVisitas): ?>
                        <a class="back-button" href="<?php echo htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, array('pag_visitas' => $paginaVisitas + 1)))); ?>">Siguiente &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>No hay visitas registradas para esta secci&oacute;n.</p>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="cliente_detalles.php?cod_cliente=<?php echo urlencode($cod_cliente); ?>" class="back-button">
            &larr; Volver al Cliente
        </a>
    </div>
</div>

<!-- jQuery -->
<script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.12.4.min.js"></script>
<!-- Bootstrap JS (opcional si necesitas modals de Bootstrap, etc.) -->
<script src="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/js/bootstrap.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js"></script>

<script>
if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
    Chart.register(ChartDataLabels);
}

var datosMensuales = <?php echo $datosMensualesJson; ?>;
var detalleBarrasMap = <?php echo $detalleBarrasJson; ?>;
var datosFamiliaMensual = <?php echo $datosFamiliaMensualJson; ?>;
var datosMarcaMensual = <?php echo $datosMarcaMensualJson; ?>;
var datosArticulosMensual = <?php echo $datosArticulosMensualJson; ?>;

var graficoComparativa = null;
var graficoFamilia = null;
var graficoMarca = null;
var graficoArticulos = null;

const colorFamilias = {
    'A': '#F39C12', 'B': '#8B5A2B', 'C': '#F1C40F', 'D': '#E74C3C',
    'E': '#3498DB', 'F': '#E84393', 'G': '#27AE60', 'H': '#95A5A6',
    'I': '#8E44AD', 'J': '#FFC0CB', 'K': '#D2B48C', 'L': '#00BCD4',
    '1': '#000000', '99': '#424242', '2': '#B6C002'
};

// Función para abrir un modal
function cargarContenidoAjax(url, containerId) {
    var container = document.getElementById(containerId);
    if (!container) {
        return;
    }
    if (container.getAttribute('data-loaded') === '1') {
        return;
    }
    container.innerHTML = '<p>Cargando lineas...</p>';

    var xhr = null;
    if (window.XMLHttpRequest) {
        xhr = new XMLHttpRequest();
    } else if (window.ActiveXObject) {
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xhr == null) {
        container.innerHTML = '<p>No se pudo iniciar la carga.</p>';
        return;
    }

    xhr.open("GET", url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                container.innerHTML = xhr.responseText;
                container.setAttribute('data-loaded', '1');
            } else {
                container.innerHTML = '<p>Error al cargar el detalle.</p>';
            }
        }
    };
    xhr.send(null);
}

function cargarDatosModal(id) {
    if (id.indexOf('modal-visita-') === 0) {
        var idVisita = id.substring('modal-visita-'.length);
        cargarContenidoAjax(
            '<?= BASE_URL ?>/ajax/seccion_detalles.php?ajax=lineas_visita&id_visita=' + encodeURIComponent(idVisita),
            'lineas-visita-' + idVisita
        );
    } else if (id.indexOf('modal-pedido-') === 0) {
        var codPedido = id.substring('modal-pedido-'.length);
        cargarContenidoAjax(
            '<?= BASE_URL ?>/ajax/seccion_detalles.php?ajax=lineas_pedido&cod_pedido=' + encodeURIComponent(codPedido),
            'lineas-pedido-' + codPedido
        );
    }
}

// Función para cerrar un modal
// Cerrar modal si se hace clic fuera de él
window.onclick = function(event) {
    var modals = document.getElementsByClassName('modal');
    for(var i=0; i<modals.length; i++) {
        if (event.target == modals[i]) {
            modals[i].style.display = "none";
        }
    }
}

// Asigna el click para abrir los modales de visita y pedido
function asignarEventos() {
    // Visitas
    var visitas = document.getElementsByClassName('visita-item');
    for (var i = 0; i < visitas.length; i++) {
        visitas[i].onclick = function() {
            var id_visita = this.getAttribute('data-id-visita');
            cargarDatosModal('modal-visita-' + id_visita);
            abrirModal('modal-visita-' + id_visita);
        };
    }
    // Pedidos
    var pedidos = document.getElementsByClassName('pedido-item');
    for (var j = 0; j < pedidos.length; j++) {
        pedidos[j].onclick = function(event) {
            // Evitar que el click abra el modal si se hace clic en .observacion
            if (event.target.classList.contains('observacion')) {
                return;
            }
            var cod_pedido = this.getAttribute('data-cod-pedido');
            cargarDatosModal('modal-pedido-' + cod_pedido);
            abrirModal('modal-pedido-' + cod_pedido);
        };
    }
}

function cargarPaginaVisitas(url, pushState) {
    var container = document.querySelector('.visitas-container');
    if (!container) {
        return;
    }

    var xhr = null;
    if (window.XMLHttpRequest) {
        xhr = new XMLHttpRequest();
    } else if (window.ActiveXObject) {
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xhr == null) {
        window.location.href = url;
        return;
    }

    container.style.opacity = '0.6';
    xhr.open("GET", url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) {
            return;
        }
        container.style.opacity = '';

        if (xhr.status !== 200) {
            window.location.href = url;
            return;
        }

        if (typeof DOMParser === 'undefined') {
            window.location.href = url;
            return;
        }

        var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
        var nuevoContainer = doc.querySelector('.visitas-container');
        if (!nuevoContainer) {
            window.location.href = url;
            return;
        }

        container.innerHTML = nuevoContainer.innerHTML;
        asignarEventos();

        if (pushState !== false && window.history && window.history.pushState) {
            window.history.pushState({ pagVisitas: true }, '', url);
        }
    };
    xhr.send(null);
}

function activarPaginacionVisitasAjax() {
    var container = document.querySelector('.visitas-container');
    if (!container) {
        return;
    }

    container.addEventListener('click', function(event) {
        var target = event.target;
        var link = null;
        if (target && typeof target.closest === 'function') {
            link = target.closest('a[href*="pag_visitas="]');
        }
        if (!link) {
            return;
        }

        event.preventDefault();
        cargarPaginaVisitas(link.href, true);
    });

    if (window.history && window.history.pushState) {
        window.addEventListener('popstate', function() {
            cargarPaginaVisitas(window.location.href, false);
        });
    }
}
function escapeHtml(text) {
    return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function mostrarDetalleBarra(periodoTxt, items) {
    var titulo = document.getElementById('detalleBarrasTitulo');
    var contenido = document.getElementById('detalleBarrasContenido');
    if (!titulo || !contenido) {
        return;
    }
    titulo.textContent = 'Detalle ' + periodoTxt + ' (Pedidos vs Albaranes)';

    if (!Array.isArray(items) || items.length === 0) {
                        contenido.innerHTML = '<p>No hay detalle por artículos para este periodo.</p>';
        abrirModal('modal-detalle-barras');
        return;
    }

    var totalCantPedido = 0;
    var totalCantAlbaran = 0;
    var totalImpPedido = 0;
    var totalImpAlbaran = 0;
    var filas = '';
    items.forEach(function(item) {
        var cantidadPedido = Number(item.cantidad_pedido || 0);
        var importePedido = Number(item.importe_pedido || 0);
        var cantidadAlbaran = Number(item.cantidad_albaran || 0);
        var importeAlbaran = Number(item.importe_albaran || 0);
        totalCantPedido += cantidadPedido;
        totalCantAlbaran += cantidadAlbaran;
        totalImpPedido += importePedido;
        totalImpAlbaran += importeAlbaran;

        var claseFila = '';
        var EPS = 0.0001;
        if (Math.abs(cantidadPedido - cantidadAlbaran) > EPS) {
            claseFila = (cantidadPedido > cantidadAlbaran)
                ? ' class="diff-cantidad-pedido-mayor"'
                : ' class="diff-cantidad-albaran-mayor"';
        } else if (Math.abs(importePedido - importeAlbaran) > EPS) {
            claseFila = (importePedido > importeAlbaran)
                ? ' class="diff-importe-pedido-mayor"'
                : ' class="diff-importe-albaran-mayor"';
        }

        filas += '<tr' + claseFila + '>' +
            '<td>' + escapeHtml(item.cod_articulo || '') + '</td>' +
            '<td>' + escapeHtml(item.descripcion || '') + '</td>' +
            '<td>' + cantidadPedido.toFixed(2) + '</td>' +
            '<td>' + importePedido.toFixed(2) + ' &euro;</td>' +
            '<td>' + cantidadAlbaran.toFixed(2) + '</td>' +
            '<td>' + importeAlbaran.toFixed(2) + ' &euro;</td>' +
            '</tr>';
    });

    contenido.innerHTML =
        '<p><strong>Total Pedidos:</strong> ' + totalImpPedido.toFixed(2) + ' &euro; | ' +
        '<strong>Total Albaranes:</strong> ' + totalImpAlbaran.toFixed(2) + ' &euro;</p>' +
        '<div class="leyenda-diff">' +
        '<span class="leyenda-item"><span class="leyenda-color" style="background:#ffe5e5;"></span>Cantidad: Pedido &gt; Albaran</span>' +
        '<span class="leyenda-item"><span class="leyenda-color" style="background:#e7f7ea;"></span>Cantidad: Albaran &gt; Pedido</span>' +
        '<span class="leyenda-item"><span class="leyenda-color" style="background:#fff6d6;"></span>Importe: Pedido &gt; Albaran (misma cantidad)</span>' +
        '<span class="leyenda-item"><span class="leyenda-color" style="background:#e8f1ff;"></span>Importe: Albaran &gt; Pedido (misma cantidad)</span>' +
        '</div>' +
        '<div class="modal-table-container"><table class="modal-table"><thead><tr>' +
        '<th>Artículo</th><th>Descripción</th><th>Cant. Pedido</th><th>Importe Pedido</th><th>Cant. Albarán</th><th>Importe Albarán</th>' +
        '</tr></thead><tbody>' + filas +
        '<tr><td colspan="2"><strong>Totales</strong></td>' +
        '<td><strong>' + totalCantPedido.toFixed(2) + '</strong></td>' +
        '<td><strong>' + totalImpPedido.toFixed(2) + ' &euro;</strong></td>' +
        '<td><strong>' + totalCantAlbaran.toFixed(2) + '</strong></td>' +
        '<td><strong>' + totalImpAlbaran.toFixed(2) + ' &euro;</strong></td>' +
        '</tr></tbody></table></div>';

    abrirModal('modal-detalle-barras');
}

function obtenerAnosSeleccionados() {
    var selector = document.getElementById('yearsWindow');
    var yearsWindow = selector ? selector.value : '2';
    var years = [];
    if (!Array.isArray(datosMensuales)) {
        return years;
    }
    datosMensuales.forEach(function(item) {
        var p = String(item.periodo || '');
        var parts = p.split('-');
        if (parts.length !== 2) return;
        var anio = parseInt(parts[0], 10);
        if (!isNaN(anio) && years.indexOf(anio) === -1) years.push(anio);
    });
    years.sort(function(a, b) { return a - b; });
    if (yearsWindow !== 'all') {
        var n = parseInt(yearsWindow, 10);
        if (!isNaN(n) && n > 0 && years.length > n) {
            years = years.slice(years.length - n);
        }
    }
    return years;
}

function dibujarGraficoLineas() {
    if (typeof Chart === 'undefined' || !Array.isArray(datosMensuales) || datosMensuales.length === 0) {
        return;
    }
    var canvas = document.getElementById('graficoLineas');
    if (!canvas) {
        return;
    }
    var etiquetas = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    var yearsMap = {};
    datosMensuales.forEach(function(item) {
        var p = String(item.periodo || '');
        var parts = p.split('-');
        if (parts.length !== 2) return;
        var anio = parseInt(parts[0], 10);
        var mes = parseInt(parts[1], 10);
        if (isNaN(anio) || isNaN(mes) || mes < 1 || mes > 12) return;
        if (!yearsMap[anio]) yearsMap[anio] = { pedidos: Array(12).fill(0), albaranes: Array(12).fill(0) };
        yearsMap[anio].pedidos[mes - 1] = Number(item.pedidos || 0);
        yearsMap[anio].albaranes[mes - 1] = Number(item.albaranes || 0);
    });

    var years = obtenerAnosSeleccionados();
    var paleta = [
        { pedidos: '#0B5FFF', albaranes: '#7FAAFF' },
        { pedidos: '#E85D04', albaranes: '#FFB380' },
        { pedidos: '#2B9348', albaranes: '#95D5B2' },
        { pedidos: '#6A4C93', albaranes: '#CDB4DB' },
        { pedidos: '#B02A37', albaranes: '#F1AEB5' }
    ];
    var datasets = [];
    years.forEach(function(anio, idx) {
        if (!yearsMap[anio]) return;
        var colores = paleta[idx % paleta.length];
        datasets.push({ label: anio + ' - Pedidos', data: yearsMap[anio].pedidos, backgroundColor: colores.pedidos });
        datasets.push({ label: anio + ' - Albaranes', data: yearsMap[anio].albaranes, backgroundColor: colores.albaranes });
    });

    if (graficoComparativa) {
        graficoComparativa.destroy();
    }
    graficoComparativa = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: { labels: etiquetas, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: true, text: 'Comparativa mensual por anio (Pedidos vs Albaranes)' },
                datalabels: { display: false }
            },
            onClick: function(evt) {
                var points = graficoComparativa.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                if (!points || points.length === 0) return;
                var p = points[0];
                var dataset = graficoComparativa.data.datasets[p.datasetIndex];
                var monthIndex = p.index;
                var monthNum = String(monthIndex + 1).padStart(2, '0');
                var monthName = graficoComparativa.data.labels[monthIndex];
                var parts = String(dataset.label || '').split(' - ');
                if (parts.length < 2) return;
                var anio = parts[0].trim();
                var keyMes = anio + '-' + monthNum;
                var items = Array.isArray(detalleBarrasMap[keyMes]) ? detalleBarrasMap[keyMes] : [];
                mostrarDetalleBarra(monthName + ' ' + anio, items);
            },
            scales: {
                x: { title: { display: true, text: 'Mes' } },
                y: { title: { display: true, text: 'Importe (EUR)' } }
            }
        }
    });
}

function dibujarGraficoFamilia() {
    if (typeof Chart === 'undefined' || !Array.isArray(datosFamiliaMensual)) return;
    var canvas = document.getElementById('graficoFamilia');
    if (!canvas) return;
    var years = obtenerAnosSeleccionados();
    var yearsSet = {};
    years.forEach(function(y) { yearsSet[y] = true; });
    var agrupado = {};
    datosFamiliaMensual.forEach(function(item) {
        var anio = parseInt(item.anio, 10);
        if (!yearsSet[anio]) return;
        var cod = String(item.cod_familia || '');
        if (!agrupado[cod]) agrupado[cod] = { familia: String(item.familia || cod), importe: 0, cod_familia: cod };
        agrupado[cod].importe += Number(item.importe || 0);
    });
    var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; });
    var etiquetas = lista.map(function(x) { return x.familia; });
    var valores = lista.map(function(x) { return x.importe; });
    var bgColors = lista.map(function(x) { return colorFamilias[x.cod_familia] || '#CCCCCC'; });
    if (graficoFamilia) graficoFamilia.destroy();
    graficoFamilia = new Chart(canvas.getContext('2d'), {
        type: 'pie',
        data: { labels: etiquetas, datasets: [{ data: valores, backgroundColor: bgColors }] },
        plugins: [ChartDataLabels],
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Ventas por Familia' },
                datalabels: {
                    formatter: function(value, context) {
                        var idx = context.dataIndex;
                        if (idx < 3) return context.chart.data.labels[idx] + '\n' + value.toFixed(2) + ' \u20AC';
                        return '';
                    },
                    color: '#FFFFFF',
                    align: 'center',
                    anchor: 'center',
                    font: { weight: 'bold', size: 12 }
                }
            }
        }
    });
}

function dibujarGraficoMarca() {
    if (typeof Chart === 'undefined' || !Array.isArray(datosMarcaMensual)) return;
    var canvas = document.getElementById('graficoMarca');
    if (!canvas) return;
    var years = obtenerAnosSeleccionados();
    var yearsSet = {};
    years.forEach(function(y) { yearsSet[y] = true; });
    var agrupado = {};
    datosMarcaMensual.forEach(function(item) {
        var anio = parseInt(item.anio, 10);
        if (!yearsSet[anio]) return;
        var marca = String(item.marca || 'SIN MARCA');
        if (!agrupado[marca]) agrupado[marca] = { marca: marca, importe: 0, familias: {} };
        var importe = Number(item.importe || 0);
        var cod = String(item.cod_familia || '');
        agrupado[marca].importe += importe;
        agrupado[marca].familias[cod] = (agrupado[marca].familias[cod] || 0) + importe;
    });
    var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; }).slice(0, 10);
    var etiquetas = [], valores = [], bgColors = [];
    lista.forEach(function(item) {
        etiquetas.push(item.marca);
        valores.push(item.importe);
        var codDominante = '', maxFam = -1;
        Object.keys(item.familias).forEach(function(cod) {
            if (item.familias[cod] > maxFam) { maxFam = item.familias[cod]; codDominante = cod; }
        });
        bgColors.push(colorFamilias[codDominante] || '#CCCCCC');
    });
    if (graficoMarca) graficoMarca.destroy();
    graficoMarca = new Chart(canvas.getContext('2d'), {
        type: 'pie',
        data: { labels: etiquetas, datasets: [{ data: valores, backgroundColor: bgColors }] },
        plugins: [ChartDataLabels],
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Marcas (Top 10)' },
                datalabels: {
                    formatter: function(value, context) {
                        var idx = context.dataIndex;
                        if (idx < 3) return context.chart.data.labels[idx] + '\n' + value.toFixed(2) + ' \u20AC';
                        return '';
                    },
                    color: '#FFFFFF',
                    align: 'center',
                    anchor: 'center',
                    font: { weight: 'bold', size: 12 }
                }
            }
        }
    });
}

function dibujarGraficoArticulos() {
    if (typeof Chart === 'undefined' || !Array.isArray(datosArticulosMensual)) return;
    var canvas = document.getElementById('graficoArticulos');
    if (!canvas) return;
    var years = obtenerAnosSeleccionados();
    var yearsSet = {};
    years.forEach(function(y) { yearsSet[y] = true; });
    var agrupado = {};
    datosArticulosMensual.forEach(function(item) {
        var anio = parseInt(item.anio, 10);
        if (!yearsSet[anio]) return;
        var articulo = String(item.articulo || 'SIN ARTICULO');
        if (!agrupado[articulo]) agrupado[articulo] = { articulo: articulo, importe: 0, familias: {} };
        var importe = Number(item.importe || 0);
        var cod = String(item.cod_familia || '');
        agrupado[articulo].importe += importe;
        agrupado[articulo].familias[cod] = (agrupado[articulo].familias[cod] || 0) + importe;
    });
    var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; }).slice(0, 10);
    var etiquetas = [], valores = [], bgColors = [];
    lista.forEach(function(item) {
        etiquetas.push(item.articulo);
        valores.push(item.importe);
        var codDominante = '', maxFam = -1;
        Object.keys(item.familias).forEach(function(cod) {
            if (item.familias[cod] > maxFam) { maxFam = item.familias[cod]; codDominante = cod; }
        });
        bgColors.push(colorFamilias[codDominante] || '#CCCCCC');
    });
    if (graficoArticulos) graficoArticulos.destroy();
    graficoArticulos = new Chart(canvas.getContext('2d'), {
        type: 'pie',
        data: { labels: etiquetas, datasets: [{ data: valores, backgroundColor: bgColors }] },
        plugins: [ChartDataLabels],
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                        title: { display: true, text: 'Artículos (Top 10)' },
                datalabels: {
                    formatter: function(value, context) {
                        var idx = context.dataIndex;
                        if (idx < 3) {
                            var label = context.chart.data.labels[idx];
                            if (label.length > 15) label = label.substring(0, 15) + '...';
                            return label + '\n' + value.toFixed(2) + ' \u20AC';
                        }
                        return '';
                    },
                    color: '#FFFFFF',
                    align: 'center',
                    anchor: 'center',
                    font: { weight: 'bold', size: 12 }
                }
            }
        }
    });
}

window.onload = function() {
    asignarEventos();
    activarPaginacionVisitasAjax();
    var selector = document.getElementById('yearsWindow');
    if (selector) {
        selector.addEventListener('change', function() {
            dibujarGraficoLineas();
            dibujarGraficoFamilia();
            dibujarGraficoMarca();
            dibujarGraficoArticulos();
        });
    }
    dibujarGraficoLineas();
    dibujarGraficoFamilia();
    dibujarGraficoMarca();
    dibujarGraficoArticulos();
}

// Función para recalcular el promedio (igual que en cliente_detalles.php)
function calcularPromedioVisita(cod_cliente, cod_seccion) {
    var xhr = null;
    if (window.XMLHttpRequest) {
        xhr = new XMLHttpRequest();
    } else if (window.ActiveXObject) {
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xhr == null) {
        return;
    }
    var url = '<?= BASE_URL ?>/ajax/calcular_promedio_visita.php?cod_cliente=' + encodeURIComponent(cod_cliente)
              + '&cod_seccion=' + encodeURIComponent(cod_seccion);
    xhr.open("GET", url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var resp = xhr.responseText;
            var elem = document.getElementById("promedio_valor");
            if (elem) {
                elem.innerHTML = resp;
            }
        }
    };
    xhr.send(null);
}

// Añadimos las funciones para quitarPedido y actualizarOrigen (AJAX)
function quitarPedido(cod_pedido, event) {
    event.stopPropagation();
    if (!confirm("¿Estás seguro de quitar este pedido de la visita?")) {
        return;
    }
    $.ajax({
        url: '<?= BASE_URL ?>/ajax/quitar_pedido.php',
        type: 'POST',
        data: { cod_pedido: cod_pedido },
        success: function(response) {
            if (response.indexOf("OK") === 0) {
                location.reload();
            } else {
                alert("Error al eliminar el pedido: " + response);
            }
        },
        error: function(xhr, status, error) {
            alert("Error al eliminar el pedido (AJAX).");
        }
    });
}

function actualizarOrigen(cod_pedido, nuevo_origen, event) {
    event.stopPropagation();
    $.ajax({
        url: '<?= BASE_URL ?>/ajax/actualizar_origen.php',
        type: 'POST',
        data: { cod_pedido: cod_pedido, origen: nuevo_origen },
        success: function(response) {
            if (response.indexOf("OK") === 0) {
                location.reload();
            } else {
                alert("Error al actualizar el origen: " + response);
            }
        },
        error: function(xhr, status, error) {
            alert("Error al actualizar el origen (AJAX).");
        }
    });
}
</script>

<script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
</body>
</html>



