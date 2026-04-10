<?php
if (!defined('BASE_PATH')) {
    header('Location: /public/');
    exit;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
// zonas_clientes.php
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';
require_once BASE_PATH . '/app/Modules/Planificador/services/PlanificadorZonasClientesViewBuilder.php';
require_once BASE_PATH . '/app/Support/functions.php';

$zonasClientesPageData = planificadorBuildZonasClientesViewData();
if (!empty($zonasClientesPageData['zonasClientesViewData']['error'])) {
    echo $zonasClientesPageData['zonasClientesViewData']['error'];
    return;
}

extract($zonasClientesPageData, EXTR_OVERWRITE);

$ui_version = 'bs5';
$ui_requires_jquery = false;
include BASE_PATH . '/resources/views/layouts/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Clientes a Zonas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/modules/planificador/zonas_clientes.css">
</head>
<body>
    <div class="container">
        <?php if ($flashMensaje !== ''): ?>
            <div class="flash-message <?= $flashEstado === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($flashMensaje, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
    <?php if (!$cod_zona): ?>
    
    <?php if (!empty($zonasBotones)): ?>
        <div class="buttons-container">
            <?php foreach ($zonasBotones as $zonaBoton): ?>
                <a href="zonas_clientes.php?cod_zona=<?php echo htmlspecialchars((string)$zonaBoton['cod_zona'], ENT_QUOTES, 'UTF-8'); ?>" class="btn-zona">
                    <?php if ((int)$zonaBoton['total_desalineados'] > 0): ?>
                        <span class="zona-alerta-badge"><?php echo (int)$zonaBoton['total_desalineados']; ?></span>
                    <?php endif; ?>
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars((string)$zonaBoton['nombre_zona'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-data">No tienes zonas disponibles. <a href="zonas.php">Crear Zonas</a></div>
    <?php endif; ?>
<?php else: ?>
            <h2><?php echo htmlspecialchars((string)$zonaActualNombre, ENT_QUOTES, 'UTF-8'); ?></h2>
            
            <?php if (!empty($clientesDisponiblesOptions)): ?>
            <div class="assign-form">
                <h3>Asignar Nuevo Cliente a la Zona</h3>
                
                <!-- Mensaje de error -->
                <div id="error-message" class="alert alert-danger error-message d-none" role="alert">Por favor, completa todos los campos obligatorios.</div>
                
                <form id="assign-form" action="procesar_asignar_cliente_zona.php" method="post">
                    <?= csrfInput() ?>
                    <input type="hidden" name="cod_zona" value="<?php echo $cod_zona; ?>">
                    
                    <label for="cod_cliente">Selecciona el Cliente:</label>
                    <select id="cod_cliente" name="cod_cliente" class="form-select" required>
                        <option value="">--Selecciona un Cliente--</option>
                        <?php if (!empty($clientesDisponiblesOptions)): ?>
                            <?php foreach ($clientesDisponiblesOptions as $clienteOption): ?>
                                <option value="<?php echo htmlspecialchars((string)$clienteOption['cod_cliente'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string)$clienteOption['nombre_cliente'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No hay clientes disponibles para asignar.</option>
                        <?php endif; ?>
                    </select>
                    
                    <!-- Campo de Sección que se muestra dinámicamente -->
                    <div id="seccion-container" class="d-none">
                        <label for="cod_seccion">Selecciona la Sección:</label>
                        <select id="cod_seccion" name="cod_seccion" class="form-select">
                            <option value="">--Selecciona una Sección--</option>
                            <!-- Las opciones se llenarán mediante AJAX -->
                        </select>
                    </div>
                    
                    <label for="zona_secundaria">Zona Secundaria (Opcional):</label>
                    <select id="zona_secundaria" name="zona_secundaria" class="form-select">
                        <option value="">--Selecciona una Zona Secundaria--</option>
                        <?php foreach ($zonasSecundariasOptions as $zonaSecundariaOption): ?>
                            <option value="<?php echo htmlspecialchars((string)$zonaSecundariaOption['cod_zona'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string)$zonaSecundariaOption['nombre_zona'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="tiempo_promedio_visita">Tiempo Promedio de Visita (horas):</label>
                    <input type="number" id="tiempo_promedio_visita" name="tiempo_promedio_visita" class="form-control" step="0.5">
                    
                    <label for="preferencia_horaria">Preferencia Horaria:</label>
                    <select id="preferencia_horaria" name="preferencia_horaria" class="form-select">
                        <option value="">--Selecciona una Preferencia--</option>
                        <option value="M">Mañana</option>
                        <option value="T">Tarde</option>
                    </select>
                    
                    <label for="frecuencia_visita">Frecuencia de Visita:</label>
                    <select id="frecuencia_visita" name="frecuencia_visita" class="form-select" required>
                        <option value="">--Selecciona una Frecuencia--</option>
                        <option value="Todos">Todos los meses</option>
                        <option value="Cada2">Cada 2 meses</option>
                        <option value="Cada3">Cada 3 meses</option>
                        <option value="Nunca">Nunca</option>
                    </select>
                    
                    <label for="observaciones">Observaciones (Opcional):</label>
                    <textarea id="observaciones" name="observaciones" class="form-control"></textarea>
                    
                    <input type="submit" value="Asignar Cliente a Zona" class="btn btn-success w-100 submit-button">
                </form>
            </div>
            <?php endif; ?>           

<!-- Checkbox para alternar la visibilidad -->
<div style="text-align: center; margin-bottom: 15px;">
    <label>
        <input type="checkbox" id="mostrar-nunca">
        Mostrar clientes con frecuencia "Nunca"
    </label>
</div>

<table>
    <tr>
        <th>Nombre del Cliente</th>
        <th>Frecuencia</th>
        <th colspan="2"><center>Acciones</center></th>
    </tr>
    <?php if (!empty($asignacionesPreparadas)): ?>
        <?php foreach ($asignacionesPreparadas as $asignacionRender): ?>
            <tr class="<?php echo htmlspecialchars((string)$asignacionRender['row_class'], ENT_QUOTES, 'UTF-8'); ?>">
                <td>
                    <?php echo htmlspecialchars((string)$asignacionRender['nombre_linea_principal'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($asignacionRender['mostrar_seccion'])): ?>
                        <?php echo '<br>&nbsp;&nbsp;&nbsp;&nbsp; &#128204; ' . htmlspecialchars((string)$asignacionRender['nombre_seccion'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                    <?php if (!empty($asignacionRender['observaciones'])) { ?>
                        <br>
                        <span class="asignacion-secundaria">&#9997; <?php echo htmlspecialchars((string)$asignacionRender['observaciones'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php } ?>

                </td>
                <td><center><?php echo htmlspecialchars((string)$asignacionRender['frecuencia_label'], ENT_QUOTES, 'UTF-8'); ?></center></td>
                <td>
                <?php if (!empty($asignacionRender['permitir_acciones'])) {?>
                    
                
    <!-- Botón de Editar -->
    <!-- Botón de Editar -->
    <button
        type="button"
        class="btn btn-sm btn-warning js-edit-asignacion"
        title="Editar Asignación"
        data-bs-toggle="modal"
        data-bs-target="#editarAsignacionModal"
        data-cod-cliente="<?php echo htmlspecialchars((string)$asignacionRender['cod_cliente'], ENT_QUOTES, 'UTF-8'); ?>"
        data-cod-zona="<?php echo htmlspecialchars((string)$cod_zona, ENT_QUOTES, 'UTF-8'); ?>"
        data-cod-seccion="<?php echo htmlspecialchars((string)$asignacionRender['cod_seccion_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-nombre-cliente="<?php echo htmlspecialchars((string)$asignacionRender['nombre_cliente_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-nombre-seccion="<?php echo htmlspecialchars((string)$asignacionRender['nombre_seccion_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-nombre-zona="<?php echo htmlspecialchars((string)$asignacionRender['nombre_zona_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-zona-secundaria="<?php echo htmlspecialchars((string)$asignacionRender['zona_secundaria_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-tiempo-promedio-visita="<?php echo htmlspecialchars((string)$asignacionRender['tiempo_promedio_visita_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-preferencia-horaria="<?php echo htmlspecialchars((string)$asignacionRender['preferencia_horaria_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-frecuencia-visita="<?php echo htmlspecialchars((string)$asignacionRender['frecuencia_visita_data'], ENT_QUOTES, 'UTF-8'); ?>"
        data-observaciones="<?php echo htmlspecialchars((string)$asignacionRender['observaciones_data'], ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fas fa-pencil"></i>
    </button>

    <!-- Botón de Eliminar -->
    <form action="borrar_asignacion.php" method="post" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta asignación?');">
        <?= csrfInput() ?>
        <input type="hidden" name="cod_cliente" value="<?php echo htmlspecialchars((string)$asignacionRender['cod_cliente'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="cod_zona" value="<?php echo htmlspecialchars((string)$cod_zona, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="cod_seccion" value="<?php echo htmlspecialchars((string)$asignacionRender['cod_seccion_hidden'], ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Asignación">
            <i class="fas fa-trash"></i>
        </button>
    </form>
    <?php } ?>
</td>

            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="4" class="no-data">No hay clientes asignados a esta zona.</td>
        </tr>
    <?php endif; ?>
</table>

            <div class="modal fade" id="editarAsignacionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="actualizar_asignacion.php" method="post">
                            <?= csrfInput() ?>
                            <div class="modal-header">
                                <h5 class="modal-title">Editar Asignación</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="cod_cliente" id="modal-cod-cliente">
                                <input type="hidden" name="cod_zona" id="modal-cod-zona">
                                <input type="hidden" name="cod_seccion" id="modal-cod-seccion">

                                <label>Nombre del Cliente:</label>
                                <input type="text" id="modal-nombre-cliente" class="form-control" disabled>

                                <label>Sección:</label>
                                <input type="text" id="modal-nombre-seccion" class="form-control" disabled>

                                <label>Zona Principal:</label>
                                <input type="text" id="modal-nombre-zona" class="form-control" disabled>

                                <label for="modal-zona-secundaria">Zona Secundaria (Opcional):</label>
                                <select name="zona_secundaria" id="modal-zona-secundaria" class="form-select">
                                    <option value="">--Selecciona una Zona--</option>
                                    <?php foreach ($zonasSecundariasOptions as $zonaSecundariaOption): ?>
                                            <option value="<?php echo htmlspecialchars((string)$zonaSecundariaOption['cod_zona'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars((string)$zonaSecundariaOption['nombre_zona'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="modal-tiempo-promedio-visita">Tiempo Promedio de Visita (horas):</label>
                                <input type="number" name="tiempo_promedio_visita" id="modal-tiempo-promedio-visita" class="form-control" step="0.5">

                                <label for="modal-preferencia-horaria">Preferencia Horaria:</label>
                                <select name="preferencia_horaria" id="modal-preferencia-horaria" class="form-select">
                                    <option value="">--Selecciona una Preferencia--</option>
                                    <option value="M">Mañana</option>
                                    <option value="T">Tarde</option>
                                </select>

                                <label for="modal-frecuencia-visita">Frecuencia de Visita:</label>
                                <select name="frecuencia_visita" id="modal-frecuencia-visita" class="form-select" required>
                                    <option value="">--Selecciona una Frecuencia--</option>
                                    <option value="Todos">Todos los meses</option>
                                    <option value="Cada2">Cada 2 meses</option>
                                    <option value="Cada3">Cada 3 meses</option>
                                    <option value="Nunca">Nunca</option>
                                </select>

                                <label for="modal-observaciones">Observaciones:</label>
                                <textarea name="observaciones" id="modal-observaciones" class="form-control"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Actualizar Asignación</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <a href="zonas_clientes.php" class="back-button">Volver a Zonas</a>
            <script>
                window.zonasClientesConfig = {
                    obtenerSeccionesUrl: <?= json_encode('obtener_secciones.php') ?>
                };
            </script>
            <script src="<?= BASE_URL ?>/assets/modules/planificador/zonas_clientes.js"></script>
<?php endif; ?>
    </div>
    
    <!-- jQuery + Bootstrap JS -->
        </body>
</html>

