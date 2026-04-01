<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Visitas del Dia <?php echo htmlspecialchars($fecha); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
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
    if (count($visitas) == 0) {
        echo '<p>No hay visitas registradas para este dia.</p>';
    } else {
        foreach ($visitas as $visita) {
            ?>
            <div class="visita-item" style="background-color: <?php echo $visita['colorVisita']; ?>;">
                <div class="visita-linea">
                    <span class="visita-cliente" style="font-weight:bold;"><?php echo htmlspecialchars($visita['clientName']); ?></span>
                    <span class="visita-fecha"><?php echo htmlspecialchars(date('d/m/Y', strtotime($visita['fecha_visita']))); ?> (<?php echo obtenerDiaSemana($visita['fecha_visita']); ?>)</span>
                    <span class="visita-horas"><?php echo htmlspecialchars(date('H:i', strtotime($visita['hora_inicio_visita']))); ?> - <?php echo htmlspecialchars(date('H:i', strtotime($visita['hora_fin_visita']))); ?></span>
                </div>
                <?php if (!empty($visita['observaciones'])): ?>
                    <p class="visita-observaciones"><?php echo htmlspecialchars($visita['observaciones']); ?></p>
                <?php endif; ?>
            </div>
            <?php
            foreach ($visita['pedidos'] as $pedido) {
                    ?>
                    <div class="pedido-item" style="border-left-color: <?php echo $pedido['colorPedido']; ?>;">
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
    ?>
    </div>
</div>
</body>
</html>
