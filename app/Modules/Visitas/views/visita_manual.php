<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Visita Manual</title>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/modules/visitas/visita_manual.css">
</head>
<body>
    <div class="container">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h1>Registrar Visita Manual</h1>

        <div class="mb-3">
            <span class="badge <?php echo htmlspecialchars($paso1BadgeClass, ENT_QUOTES, 'UTF-8'); ?>">Paso 1: Buscar cliente</span>
            <?php if ($mostrarPasosSeleccion): ?>
                <span class="badge bg-primary ms-1">Paso 2: Cliente seleccionado</span>
                <span class="badge bg-primary ms-1">Paso 3: Registrar visita</span>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/registrar_visita.php" class="form" id="flujoVisitaManual">
            <?= csrfInput() ?>
            <input type="hidden" name="origen" value="manual">
            <input type="hidden" name="cod_cliente" id="cod_cliente" value="<?php echo htmlspecialchars($codClienteHiddenValue, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="cod_seccion" id="cod_seccion" value="<?php echo htmlspecialchars($codSeccionHiddenValue, ENT_QUOTES, 'UTF-8'); ?>">

            <?php if ($cod_cliente == 0): ?>
                <div class="mb-3">
                    <label for="buscar">Nombre del Cliente:</label>
                    <input type="text" name="buscar" id="buscar" class="form-control" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Introduce el nombre del cliente">
                    <button type="submit" name="accion" value="buscar" formaction="<?= BASE_URL ?>/visita_manual.php" class="btn btn-primary w-100 btn-submit">Buscar</button>
                    <p class="text-center" style="margin-top:20px;">Solo se mostrarán clientes asignados a tu usuario.</p>
                </div>
            <?php endif; ?>

            <?php if ($mostrarResultados && $cod_cliente == 0): ?>
                <hr>
                <h3>Resultados de la búsqueda</h3>
                <?php if (empty($resultadosBusquedaRender)): ?>
                    <p class="text-center">No se encontraron clientes que cumplan con la búsqueda.</p>
                <?php else: ?>
                    <?php foreach ($resultadosBusquedaRender as $clienteRender): ?>
                        <button
                            type="submit"
                            name="accion"
                            value="seleccionar_cliente"
                            formaction="<?= BASE_URL ?>/visita_manual.php"
                            class="result-button"
                            onclick="document.getElementById('cod_cliente').value='<?php echo htmlspecialchars($clienteRender['cod_cliente'], ENT_QUOTES, 'UTF-8'); ?>'; document.getElementById('cod_seccion').value='<?php echo htmlspecialchars($clienteRender['cod_seccion'], ENT_QUOTES, 'UTF-8'); ?>';"
                        >
                            <?php echo htmlspecialchars($clienteRender['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($mostrarFormularioRegistro): ?>
                <hr>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="alert alert-primary mb-0 py-2 px-3">
                        <strong>Cliente seleccionado:</strong>
                        <?php echo htmlspecialchars($nombreCliente, ENT_QUOTES, 'UTF-8'); ?>
                        <span class="badge bg-light text-dark ms-2">#<?php echo htmlspecialchars((string)$cod_cliente); ?></span>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="window.location='<?= BASE_URL ?>/visita_manual.php'">Cambiar cliente</button>
                </div>

                <?php if (!empty($citasRender)): ?>
                    <?php foreach ($citasRender as $citaRender): ?>
                        <div class="alert <?php echo htmlspecialchars($citaRender['alert_class'], ENT_QUOTES, 'UTF-8'); ?>">
                            <strong><?php echo htmlspecialchars($citaRender['estado_label'], ENT_QUOTES, 'UTF-8'); ?>:</strong>
                            <?php echo htmlspecialchars($citaRender['fecha_label'], ENT_QUOTES, 'UTF-8'); ?>
                            de <?php echo htmlspecialchars($citaRender['hora_inicio_label'], ENT_QUOTES, 'UTF-8'); ?>
                            a <?php echo htmlspecialchars($citaRender['hora_fin_label'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($mostrarAvisoNunca): ?>
                    <div class="alert alert-danger">Atención: Este cliente no se visita habitualmente.</div>
                <?php endif; ?>

                <h3>Datos del Cliente y Disponibilidad</h3>
                <?php if ($nombreSeccion !== ''): ?>
                    <p><strong>Sección:</strong> <?php echo htmlspecialchars($nombreSeccion, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p><strong>Tiempo Promedio de Visita:</strong> <?php echo htmlspecialchars($tiempoPromedioLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Disponibilidad Mañana:</strong> <?php echo htmlspecialchars($disponibilidadMananaLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Disponibilidad Tarde:</strong> <?php echo htmlspecialchars($disponibilidadTardeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Preferencia Horaria:</strong> <?php echo htmlspecialchars($preferenciaHorariaLabel, ENT_QUOTES, 'UTF-8'); ?></p>

                <button id="horario_btnDefinirHorario" type="button" class="btn btn-info" style="margin-bottom:15px;">Definir Horario</button>

                <div id="horario_modalDefinirHorario">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h3>Definir Horario de Disponibilidad</h3>
                        <div id="formDefinirHorario">
                            <input type="hidden" id="horario_modal_cod_cliente" value="<?php echo htmlspecialchars((string)$cod_cliente); ?>">
                            <input type="hidden" id="horario_modal_cod_seccion" value="<?php echo $cod_seccion !== null ? htmlspecialchars((string)$cod_seccion) : ''; ?>">
                            <div class="mb-3">
                                <label for="horario_inicio_manana">Hora Inicio Mañana:</label>
                                <input type="time" class="form-control" id="horario_inicio_manana" value="<?php echo htmlspecialchars($hora_inicio_manana); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="horario_fin_manana">Hora Fin Mañana:</label>
                                <input type="time" class="form-control" id="horario_fin_manana" value="<?php echo htmlspecialchars($hora_fin_manana); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="horario_inicio_tarde">Hora Inicio Tarde:</label>
                                <input type="time" class="form-control" id="horario_inicio_tarde" value="<?php echo htmlspecialchars($hora_inicio_tarde); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="horario_fin_tarde">Hora Fin Tarde:</label>
                                <input type="time" class="form-control" id="horario_fin_tarde" value="<?php echo htmlspecialchars($hora_fin_tarde); ?>" required>
                            </div>
                            <button type="button" id="horario_guardarHorarioBtn" class="btn btn-success w-100 btn-submit">Guardar Horario</button>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="fecha_visita">Fecha de la Visita:</label>
                    <input type="date" class="form-control" name="fecha_visita" id="fecha_visita" value="<?php echo htmlspecialchars($fecha_visita); ?>">
                </div>
                <div id="warning_visitas_existentes" class="alert alert-warning d-none" role="alert"></div>
                <div class="mb-3">
                    <label for="hora_inicio_visita">Hora de Inicio:</label>
                    <input type="time" class="form-control" name="hora_inicio_visita" id="hora_inicio_visita" value="<?php echo htmlspecialchars($hora_inicio_visita); ?>">
                </div>
                <div class="mb-3">
                    <label for="hora_fin_visita">Hora de Fin:</label>
                    <input type="time" class="form-control" name="hora_fin_visita" id="hora_fin_visita" value="<?php echo htmlspecialchars($hora_fin_visita); ?>">
                </div>
                <div class="mb-3">
                    <label for="zona_seleccionada">Zona de Visita:</label>
                    <select name="zona_seleccionada" id="zona_seleccionada" class="form-select">
                        <?php foreach ($zonas as $zona): ?>
                            <option value="<?php echo htmlspecialchars((string)$zona['codigo']); ?>" <?php if ((string)$zona_seleccionada === (string)$zona['codigo']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars((string)$zona['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="estado_visita">Estado de la Visita:</label>
                    <select name="estado_visita" id="estado_visita" class="form-select">
                        <option value="Pendiente" <?php if ($estado_visita === 'Pendiente') echo 'selected'; ?>>Pendiente</option>
                        <option value="Planificada" <?php if ($estado_visita === 'Planificada') echo 'selected'; ?>>Planificada</option>
                        <option value="Realizada" <?php if ($estado_visita === 'Realizada') echo 'selected'; ?>>Realizada</option>
                        <option value="No atendida" <?php if ($estado_visita === 'No atendida') echo 'selected'; ?>>No atendida</option>
                        <option value="Descartada" <?php if ($estado_visita === 'Descartada') echo 'selected'; ?>>Descartada</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="observaciones">Observaciones (opcional):</label>
                    <textarea name="observaciones" id="observaciones" class="form-control" rows="4"><?php echo htmlspecialchars($observaciones); ?></textarea>
                </div>
                <div id="mensajes_validacion"></div>
                <button type="submit" name="accion" value="registrar" class="btn btn-primary w-100 btn-submit">Registrar Visita Manual</button>

                <div id="visitas_del_dia" style="margin-top:20px;">
                    <div class="alert alert-info">Seleccione una fecha para ver las visitas programadas.</div>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <script>
        window.visitaManualConfig = {
            csrfToken: <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            promedio: <?= json_encode($tiempo_promedio_minutes) ?>,
            horarioCliente: {
                mananaInicio: <?= json_encode($hora_inicio_manana) ?>,
                mananaFin: <?= json_encode($hora_fin_manana) ?>,
                tardeInicio: <?= json_encode($hora_inicio_tarde) ?>,
                tardeFin: <?= json_encode($hora_fin_tarde) ?>
            },
            emptyStateHtml: <?= json_encode("<div class='alert alert-info'>Seleccione una fecha para ver las visitas programadas.</div>", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            errorStateHtml: <?= json_encode("<div class='alert alert-danger'>Error al cargar las visitas.</div>", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            checkVisitasExistentesUrl: <?= json_encode(BASE_URL . '/ajax/check_visitas_existentes.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            getVisitasUrl: <?= json_encode(BASE_URL . '/get_visitas.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            definirHorarioUrl: <?= json_encode(BASE_URL . '/definir_horario.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        };
    </script>
    <script src="<?= BASE_URL ?>/assets/modules/visitas/visita_manual.js"></script>
</body>
</html>
