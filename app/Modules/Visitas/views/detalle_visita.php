<?php if ($detalle_visita_error !== ''): ?>
<?php echo $detalle_visita_error; ?>
<?php return; ?>
<?php endif; ?>
<h4>Detalles de la Visita #<?php echo $id_visita; ?></h4>
<p><strong>Cliente:</strong> <?php echo htmlspecialchars($row['nombre_comercial']); ?></p>
<p><strong>Fecha y Hora:</strong> <?php echo htmlspecialchars($row['fecha_visita']); ?> <?php echo htmlspecialchars($row['hora_inicio_visita']); ?></p>
<p><strong>Estado:</strong> <?php echo htmlspecialchars(normalizarEstadoVisita($row['estado_visita'])); ?></p>
