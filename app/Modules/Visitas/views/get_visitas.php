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
            background-color: var(--pedido-color, #6c757d);
        }
        .pedido-content {
            margin-left: 15px;
        }
        .pedido-info {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .pedido-info > div {
            margin-right: 15px;
            margin-bottom: 5px;
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
                    <span class="visita-cliente" style="font-weight:bold;">
                        <?php echo iconoDeOrigen((string)($visita['origenPrincipal'] ?? 'otros')); ?>
                        <?php echo htmlspecialchars(toUTF8((string)$visita['clientName'])); ?>
                    </span>
                    <span class="visita-fecha">&#128197; <?php echo htmlspecialchars(date('d/m/Y', strtotime($visita['fecha_visita']))); ?> (<?php echo obtenerDiaSemana($visita['fecha_visita']); ?>)</span>
                    <span class="visita-horas">&#9200; <?php echo htmlspecialchars(date('H:i', strtotime($visita['hora_inicio_visita']))); ?> - <?php echo htmlspecialchars(date('H:i', strtotime($visita['hora_fin_visita']))); ?></span>
                    <span class="visita-importe">&#128176; <?php echo number_format((float)($visita['importe_total'] ?? 0), 2, ',', '.') . ' '; ?></span>
                    <span class="visita-lineas">&#128221; <?php echo htmlspecialchars((string)($visita['numero_lineas_total'] ?? 0)); ?></span>
                </div>
                <?php if (!empty($visita['observaciones'])): ?>
                    <p class="visita-observaciones"><?php echo htmlspecialchars(toUTF8((string)$visita['observaciones'])); ?></p>
                <?php endif; ?>
            </div>
            <?php
            foreach ($visita['pedidos'] as $pedido) {
                    ?>
                    <div class="pedido-item" style="--pedido-color: <?php echo htmlspecialchars((string)$pedido['colorPedido']); ?>;">
                        <div class="pedido-content">
                            <div class="pedido-info">
                                <div>
                                    <?php echo iconoDeOrigen((string)($pedido['origen'] ?? 'otros')); ?>
                                    <strong><?php echo htmlspecialchars((string)$pedido['cod_pedido']); ?></strong>
                                </div>
                                <div>
                                    <strong>&#128197;</strong>
                                    <?php echo htmlspecialchars(date('d/m/Y', strtotime((string)$pedido['fecha_venta']))); ?>
                                </div>
                                <div>
                                    <strong>&#9200;</strong>
                                    <?php echo htmlspecialchars((string)$pedido['hora_venta']); ?>
                                </div>
                                <div>
                                    <strong>&#128176;</strong>
                                    <?php echo number_format((float)$pedido['importe'], 2, ',', '.') . ' '; ?>
                                </div>
                                <div>
                                    <strong>&#128221; L&iacute;neas:</strong>
                                    <?php echo htmlspecialchars((string)($pedido['numero_lineas'] ?? 0)); ?>
                                </div>
                            </div>
                            <?php if (!empty($pedido['observacion_interna'])): ?>
                                <div class="pedido-observaciones"><?php echo htmlspecialchars(toUTF8((string)$pedido['observacion_interna'])); ?></div>
                            <?php endif; ?>
                        </div>
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
