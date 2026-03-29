<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';

$ajaxAccion = (string)($_GET['ajax'] ?? '');
if (!in_array($ajaxAccion, ['lineas_visita', 'lineas_pedido'], true)) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$conn = db();

if ($ajaxAccion === 'lineas_visita') {
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
