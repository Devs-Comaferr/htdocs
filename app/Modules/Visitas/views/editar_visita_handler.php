<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Visita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $renderGlobalHeader = empty($isEmbedded); ?>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
    <style>
      body { padding-top: <?php echo empty($isEmbedded) ? '80px' : '16px'; ?>; }
      .boton-derecha { float: right; margin-left: 10px; }
      .container { max-width: <?php echo empty($isEmbedded) ? '960px' : '100%'; ?>; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var horaInicioInput = document.getElementById('hora_inicio_visita');
        var horaFinInput = document.getElementById('hora_fin_visita');
        var promedio = <?php echo $tiempo_promedio_minutes; ?>;

        if (!horaInicioInput || !horaFinInput) {
            return;
        }

        horaInicioInput.addEventListener('change', function () {
            var startTime = horaInicioInput.value;
            if (startTime) {
                var parts = startTime.split(':');
                var date = new Date();
                date.setHours(parseInt(parts[0], 10));
                date.setMinutes(parseInt(parts[1], 10) + promedio);
                var endHours = date.getHours();
                var endMinutes = date.getMinutes();
                if (endHours < 10) { endHours = '0' + endHours; }
                if (endMinutes < 10) { endMinutes = '0' + endMinutes; }
                horaFinInput.value = endHours + ':' + endMinutes;
            }
        });
    });
    </script>
</head>
<body>
<div class="container">
    <h2 class="text-center">Editar Visita</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form action="<?= BASE_URL ?>/editar_visita.php?id_visita=<?php echo $id_visita; ?><?php echo isset($_GET['origen']) ? '&amp;origen=' . $_GET['origen'] : ''; ?>" method="POST">
        <?= csrfInput() ?>
        <input type="hidden" name="id_visita" value="<?php echo $id_visita; ?>">

        <div class="mb-3">
            <label>Nombre Comercial:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($nombre_comercial); ?>" readonly>
        </div>

        <?php if (!is_null($cod_seccion)) { ?>
        <div class="mb-3">
            <label>SecciÃƒÂ³n:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($cod_seccion); ?>" readonly>
        </div>
        <?php } ?>

        <div class="mb-3">
            <label>Fecha de Visita:</label>
            <input type="date" class="form-control" name="fecha_visita" value="<?php echo htmlspecialchars($fecha_visita); ?>" required>
        </div>

        <div class="mb-3">
            <label>Hora de Inicio:</label>
            <input type="time" class="form-control" name="hora_inicio_visita" id="hora_inicio_visita" value="<?php echo htmlspecialchars($hora_inicio_visita); ?>" required>
        </div>

        <div class="mb-3">
            <label>Hora de Fin:</label>
            <input type="time" class="form-control" name="hora_fin_visita" id="hora_fin_visita" value="<?php echo htmlspecialchars($hora_fin_visita); ?>">
        </div>

        <div class="mb-3">
            <label>Observaciones:</label>
            <textarea class="form-control" name="observaciones"><?php echo htmlspecialchars($observaciones); ?></textarea>
        </div>

        <div class="mb-3">
            <label>Estado de Visita:</label>
            <select class="form-control" name="estado_visita">
                <option value="Pendiente" <?php if (normalizarEstadoVisita($estado_visita) == 'Pendiente') echo 'selected'; ?>>Pendiente</option>
                <option value="Planificada" <?php if (normalizarEstadoVisita($estado_visita) == 'Planificada') echo 'selected'; ?>>Planificada</option>
                <option value="Realizada" <?php if (normalizarEstadoVisita($estado_visita) == 'Realizada') echo 'selected'; ?>>Realizada</option>
                <option value="No atendida" <?php if (normalizarEstadoVisita($estado_visita) == 'No atendida') echo 'selected'; ?>>No atendida</option>
                <option value="Descartada" <?php if (normalizarEstadoVisita($estado_visita) == 'Descartada') echo 'selected'; ?>>Descartada</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        <?php
            if (isset($_GET['origen']) && $_GET['origen'] === 'visita_pedido') {
                $cancelUrl = 'visita_pedido.php';
            } else {
                $cancelUrl = 'mostrar_calendario.php';
            }
        ?>
        <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancelar</a>

        <a href="<?= BASE_URL ?>/eliminar_visita.php?id_visita=<?php echo $id_visita; ?><?php echo isset($_GET['origen']) ? '&amp;origen=' . $_GET['origen'] : ''; ?>" class="btn btn-danger boton-derecha">
            Eliminar Visita
        </a>

        <a href="cliente_detalles.php?cod_cliente=<?php echo urlencode($cod_cliente); ?><?php echo tieneValor($cod_seccion) ? '&cod_seccion=' . urlencode($cod_seccion) : ''; ?>" class="btn btn-warning boton-derecha">
            Ficha de Cliente
        </a>
        <div style="clear: both;"></div>
    </form>
</div>
</body>
</html>
<?php
?>
