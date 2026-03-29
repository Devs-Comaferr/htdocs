<?php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/db.php';

$conn = db();

if (!isset($_GET['fecha']) || empty($_GET['fecha'])) {
    appExitTextError('Fecha no especificada.', 400);
}

$fecha = addslashes((string)$_GET['fecha']);

$sql = "SELECT v.id_visita,
               v.cod_cliente,
               v.cod_seccion,
               v.estado_visita,
               v.fecha_visita,
               v.hora_inicio_visita,
               v.hora_fin_visita,
               v.observaciones,
               c.nombre_comercial,
               c.cod_cliente,
               sc.nombre AS nombre_seccion
        FROM [integral].[dbo].[cmf_visitas_comerciales] v
        LEFT JOIN [integral].[dbo].[clientes] c ON v.cod_cliente = c.cod_cliente
        LEFT JOIN [integral].[dbo].[secciones_cliente] sc ON v.cod_cliente = sc.cod_cliente AND v.cod_seccion = sc.cod_seccion
        WHERE CONVERT(varchar(10), v.fecha_visita, 120) = '$fecha'
        ORDER BY v.hora_inicio_visita ASC";
$result = odbc_exec($conn, $sql);
if (!$result) {
    appExitTextError('No se pudieron cargar las visitas.', 500, 'get_visitas', odbc_errormsg($conn) ?: odbc_errormsg());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Visitas del Dia <?php echo htmlspecialchars($fecha); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .visitas-container {
            margin-top: 20px;
        }
        .visita-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            color: #fff;
        }
        .visita-linea {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }
        .visita-linea span {
            margin-right: 20px;
            font-size: 16px;
        }
        .visita-observaciones {
            margin-top: 5px;
            font-style: italic;
            color: #fff;
        }
        .pedido-item {
            margin-left: 20px;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 8px solid;
            background-color: #fff;
            border-radius: 5px;
            color: #333;
        }
        .pedido-info {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }
        .pedido-info > div {
            margin-right: 15px;
            font-size: 14px;
        }
        .pedido-observaciones {
            font-style: italic;
            color: #007bff;
            margin-top: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Visitas programadas para el <?php echo htmlspecialchars(date('d/m/Y', strtotime($fecha))); ?></h2>
    <div class="visitas-container">
    <?php
    if (odbc_num_rows($result) == 0) {
        echo '<p>No hay visitas registradas para este dia.</p>';
    } else {
        while ($visita = odbc_fetch_array($result)) {
            $sql_pedido_principal = "SELECT TOP 1 vp.origen
                                     FROM [integral].[dbo].[cmf_visita_pedidos] vp
                                     WHERE vp.id_visita = '" . addslashes($visita['id_visita']) . "'
                                     ORDER BY vp.id_visita_pedido ASC";
            $result_pedido_principal = odbc_exec($conn, $sql_pedido_principal);
            $origenPrincipal = '';
            if ($result_pedido_principal && $pedidoPrincipal = odbc_fetch_array($result_pedido_principal)) {
                $origenPrincipal = $pedidoPrincipal['origen'];
            }

            $colorVisita = determinarColorVisita($visita['estado_visita'], $origenPrincipal);
            $clientName = !empty($visita['nombre_comercial']) ? $visita['nombre_comercial'] : 'Cliente ' . $visita['cod_cliente'];
            if (!empty($visita['nombre_seccion'])) {
                $clientName .= ' - ' . $visita['nombre_seccion'];
            }
            ?>
            <div class="visita-item" style="background-color: <?php echo $colorVisita; ?>;">
                <div class="visita-linea">
                    <span class="visita-cliente" style="font-weight:bold;"><?php echo htmlspecialchars($clientName); ?></span>
                    <span class="visita-fecha"><?php echo htmlspecialchars(date('d/m/Y', strtotime($visita['fecha_visita']))); ?> (<?php echo obtenerDiaSemana($visita['fecha_visita']); ?>)</span>
                    <span class="visita-horas"><?php echo htmlspecialchars(date('H:i', strtotime($visita['hora_inicio_visita']))); ?> - <?php echo htmlspecialchars(date('H:i', strtotime($visita['hora_fin_visita']))); ?></span>
                </div>
                <?php if (!empty($visita['observaciones'])): ?>
                    <p class="visita-observaciones"><?php echo htmlspecialchars($visita['observaciones']); ?></p>
                <?php endif; ?>
            </div>
            <?php
            $sql_pedidos = "SELECT
                                vp.cod_venta AS cod_pedido,
                                vp.origen,
                                hvc.fecha_venta,
                                hvc.hora_venta,
                                hvc.importe,
                                avc.observacion_interna
                            FROM [integral].[dbo].[cmf_visita_pedidos] vp
                            INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc
                                ON vp.cod_venta = hvc.cod_venta
                            LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] avc
                                ON hvc.cod_anexo = avc.cod_anexo
                            WHERE vp.id_visita = '" . addslashes($visita['id_visita']) . "'
                              AND hvc.tipo_venta = 1
                            ORDER BY vp.id_visita_pedido ASC";
            $result_pedidos = odbc_exec($conn, $sql_pedidos);
            if ($result_pedidos) {
                while ($pedido = odbc_fetch_array($result_pedidos)) {
                    $colorPedido = determinarColorPedido($pedido['origen']);
                    ?>
                    <div class="pedido-item" style="border-left-color: <?php echo $colorPedido; ?>;">
                        <div class="pedido-info">
                            <div><?php echo htmlspecialchars($pedido['cod_pedido']); ?></div>
                            <div><?php echo htmlspecialchars(date('d/m/Y', strtotime($pedido['fecha_venta']))); ?></div>
                            <div><?php echo htmlspecialchars($pedido['hora_venta']); ?></div>
                            <div><?php echo number_format($pedido['importe'], 2, ',', '.') . ' '; ?></div>
                        </div>
                        <?php if (!empty($pedido['observacion_interna'])): ?>
                            <div class="pedido-observaciones"><?php echo htmlspecialchars($pedido['observacion_interna']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            }
        }
    }
    ?>
    </div>
</div>
<?php
?>
</body>
</html>
