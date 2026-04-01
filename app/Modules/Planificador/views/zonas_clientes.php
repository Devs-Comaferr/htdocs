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
require_once BASE_PATH . '/app/Support/functions.php';

$pageTitle = "Asignar Clientes a Zonas";
$ui_version = 'bs5';
$ui_requires_jquery = false;
include BASE_PATH . '/resources/views/layouts/header.php';

$zonasClientesViewData = obtenerDatosZonasClientesView(isset($_GET['cod_zona']) ? intval($_GET['cod_zona']) : null);
if (!empty($zonasClientesViewData['error'])) {
    echo $zonasClientesViewData['error'];
    return;
}
$zonas = $zonasClientesViewData['zonas'];
$zonas_alertas = $zonasClientesViewData['zonas_alertas'];
$clientes_desalineados = $zonasClientesViewData['clientes_desalineados'];
$asignaciones_por_zona = $zonasClientesViewData['asignaciones_por_zona'];
$zona_actual = $zonasClientesViewData['zona_actual'];
$rutas_asignadas = $zonasClientesViewData['rutas_asignadas'];
$clientes_disponibles = $zonasClientesViewData['clientes_disponibles'];
$asignaciones_actuales = $zonasClientesViewData['asignaciones_actuales'];
$cod_zona = $zonasClientesViewData['cod_zona'];
$numSeccionesPorCliente = $zonasClientesViewData['numSeccionesPorCliente'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Clientes a Zonas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Font Awesome para los iconos -->
    <style>
        /* Estilos existentes... */
        body {
            font-family: Arial, sans-serif;
            padding-top: 20px;
            background-color: #f0f2f5;
        }
        h1, h2, h3 {
            text-align: center;
            color: #333;
        }
        .zone-list, .client-list {
            margin-top: 20px;
        }
        .buttons-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-top: 60px;
            margin-bottom: 50px;
        }

        .btn-zona {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            width: 200px;
            height: 200px;
            padding: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #fff;
            background-color: #17a2b8;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-zona i {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .btn-zona:hover {
            background-color: #117a8b;
            transform: translateY(-5px);
        }

        .btn-zona:active {
            background-color: #0f6674;
            transform: translateY(0);
            box-shadow: none;
        }
        .btn-zona .zona-alerta-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            background-color: #dc3545;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            line-height: 22px;
            text-align: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.25);
        }

        /* Responsividad para dispositivos móviles */
        @media (max-width: 1024px) {
            .btn-zona {
                width: 150px;
                height: 150px;
                font-size: 16px;
            }
            .btn-zona i {
                font-size: 40px;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 480px) {
            .btn-zona {
                width: 120px;
                height: 120px;
                font-size: 14px;
            }
            .btn-zona i {
                font-size: 30px;
                margin-bottom: 8px;
            }
        }
        .zone-button {
            display: block;
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            font-size: 18px;
            text-align: center;
            text-decoration: none;
            color: #fff;
            background-color: #ffc107;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        .zone-button:hover {
            background-color: #e0a800;
            transform: scale(1.02);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        th, td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
            font-size: 16px;
        }
        th {
            background-color: #e9ecef;
            color: #333;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .action-link {
            padding: 10px 20px;
            background-color: #28a745;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .action-link:hover {
            background-color: #218838;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #777;
        }
        .assign-form {
            max-width: 600px;
            margin: 0 auto 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .assign-form .form-select, .assign-form .form-control {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .assign-form .submit-button {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 20px;
        }
        .assign-form .submit-button:hover {
            background-color: #218838;
        }
        .back-button {
            display: block;
            width: 100%;
            padding: 15px;
            font-size: 18px;
            background-color: #6c757d;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
            text-align: center;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
        /* Estilo para el campo de Sección oculto */
        .error-message {
            color: red;
            margin-bottom: 15px;
        }
        /* Estilo para asignaciones secundarias */
        .asignacion-secundaria {
            color: gray;
            font-style: italic;
        }
        /* Estilos para frecuencia nunca */
        .frecuencia-nunca {
            color: gray;
            text-decoration: line-through;
        }
        tr.cliente-desalineado td {
            background-color: #ffd1d1;
        }
        tr.cliente-desalineado:hover td {
            background-color: #ffb8b8;
        }
    </style>
</head>
<body>
    <div class="container">
        
        
    <?php if (!$cod_zona): ?>
    
    <?php if (!empty($zonas)): ?>
        <div class="buttons-container">
            <?php foreach ($zonas as $zona): ?>
                <?php
                    $codZona = (string)($zona['cod_zona'] ?? '');
                    $totalDesalineados = (int)($zonas_alertas[$codZona] ?? 0);
                ?>
                <a href="zonas_clientes.php?cod_zona=<?php echo htmlspecialchars($zona['cod_zona']); ?>" class="btn-zona">
                    <?php if ($totalDesalineados > 0): ?>
                        <span class="zona-alerta-badge"><?php echo $totalDesalineados; ?></span>
                    <?php endif; ?>
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars(toUTF8((string)$zona['nombre_zona']), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-data">No tienes zonas disponibles. <a href="zonas.php">Crear Zonas</a></div>
    <?php endif; ?>
<?php else: ?>
            <h2><?php echo htmlspecialchars(toUTF8((string)$zona_actual['nombre_zona']), ENT_QUOTES, 'UTF-8'); ?></h2>
            
            <?php if (!empty($clientes_disponibles)): ?>
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
                        <?php if (!empty($clientes_disponibles)): ?>
                            <?php foreach ($clientes_disponibles as $cliente): ?>
                                <option value="<?php echo $cliente['cod_cliente']; ?>">
                                    <?php echo htmlspecialchars(toUTF8((string)$cliente['nombre_cliente']), ENT_QUOTES, 'UTF-8'); ?>
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
                        <?php foreach ($zonas as $zona): ?>
                            <?php if ($zona['cod_zona'] != $cod_zona): ?>
                                <option value="<?php echo htmlspecialchars($zona['cod_zona']); ?>">
                                    <?php echo htmlspecialchars(toUTF8((string)$zona['nombre_zona']), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endif; ?>
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
    <?php if (!empty($asignaciones_actuales)): ?>
        <?php foreach ($asignaciones_actuales as $asignacion): ?>
            <?php
                // Determinar la clase CSS basada en el tipo de asignación
                $clase = '';
                if ($asignacion['tipo_asignacion'] === 'secundaria') {
                    $clase = 'asignacion-secundaria';
                }
                if ($asignacion['frecuencia_visita'] === 'Nunca') {
                    $clase = 'frecuencia-nunca';
                }
                if (isset($asignacion['cod_cliente']) && isset($clientes_desalineados[(string)$asignacion['cod_cliente']])) {
                    $clase = trim($clase . ' cliente-desalineado');
                }
            ?>
            <tr class="<?php echo $clase; ?>">
                <td>
                    <?php
                        $poblacionCliente = trim((string)($asignacion['poblacion_cliente'] ?? ''));
                        $poblacionSeccion = trim((string)($asignacion['poblacion_seccion'] ?? ''));
                        $municipioLineaPrincipal = !empty($asignacion['nombre_seccion'])
                            ? ($poblacionSeccion !== '' ? $poblacionSeccion : $poblacionCliente)
                            : $poblacionCliente;
                        echo htmlspecialchars(toUTF8((string)$asignacion['nombre_cliente']), ENT_QUOTES, 'UTF-8')
                            . " - " .
                            htmlspecialchars(toUTF8((string)$municipioLineaPrincipal), ENT_QUOTES, 'UTF-8');
                    ?>
                    <?php
                    $codCliFila = (string)($asignacion['cod_cliente'] ?? '');
                    $tieneVariasSecciones = $codCliFila !== '' && isset($numSeccionesPorCliente[$codCliFila]) && count($numSeccionesPorCliente[$codCliFila]) > 1;
                    if ($tieneVariasSecciones && !empty($asignacion['nombre_seccion'])) {
                        echo '<br>&nbsp;&nbsp;&nbsp;&nbsp; &#128204; ' . htmlspecialchars(toUTF8((string)$asignacion['nombre_seccion']), ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                    <?php if (!empty($asignacion['observaciones'])) { ?>
                        <br>
                        <span class="asignacion-secundaria">&#9997; <?php echo htmlspecialchars(toUTF8((string)$asignacion['observaciones']), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php } ?>

                </td>
                <td><center><?php echo ucfirst($asignacion['frecuencia_visita']); ?></center></td>
                <td>
                <?php if ($asignacion['tipo_asignacion'] === 'primaria') {?>
                    
                
    <!-- Botón de Editar -->
    <!-- Botón de Editar -->
    <button
        type="button"
        class="btn btn-sm btn-warning js-edit-asignacion"
        title="Editar Asignación"
        data-bs-toggle="modal"
        data-bs-target="#editarAsignacionModal"
        data-cod-cliente="<?php echo htmlspecialchars((string)$asignacion['cod_cliente'], ENT_QUOTES, 'UTF-8'); ?>"
        data-cod-zona="<?php echo htmlspecialchars((string)$cod_zona, ENT_QUOTES, 'UTF-8'); ?>"
        data-cod-seccion="<?php echo isset($asignacion['cod_seccion']) ? htmlspecialchars((string)$asignacion['cod_seccion'], ENT_QUOTES, 'UTF-8') : 'NULL'; ?>"
        data-nombre-cliente="<?php echo htmlspecialchars(toUTF8((string)$asignacion['nombre_cliente']), ENT_QUOTES, 'UTF-8'); ?>"
        data-nombre-seccion="<?php echo htmlspecialchars(toUTF8((string)($asignacion['nombre_seccion'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
        data-nombre-zona="<?php echo htmlspecialchars(toUTF8((string)$zona_actual['nombre_zona']), ENT_QUOTES, 'UTF-8'); ?>"
        data-zona-secundaria="<?php echo htmlspecialchars((string)($asignacion['zona_secundaria'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        data-tiempo-promedio-visita="<?php echo htmlspecialchars((string)($asignacion['tiempo_promedio_visita'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        data-preferencia-horaria="<?php echo htmlspecialchars((string)($asignacion['preferencia_horaria'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        data-frecuencia-visita="<?php echo htmlspecialchars((string)($asignacion['frecuencia_visita'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        data-observaciones="<?php echo htmlspecialchars(toUTF8((string)($asignacion['observaciones'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fas fa-pencil"></i>
    </button>

    <!-- Botón de Eliminar -->
    <form action="borrar_asignacion.php" method="post" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta asignación?');">
        <?= csrfInput() ?>
        <input type="hidden" name="cod_cliente" value="<?php echo htmlspecialchars($asignacion['cod_cliente']); ?>">
        <input type="hidden" name="cod_zona" value="<?php echo htmlspecialchars($cod_zona); ?>">
        <input type="hidden" name="cod_seccion" value="<?php echo htmlspecialchars($asignacion['cod_seccion']); ?>">
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
                                    <?php foreach ($zonas as $z): ?>
                                        <?php if ($z['cod_zona'] != $cod_zona): ?>
                                            <option value="<?php echo htmlspecialchars((string)$z['cod_zona'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars(toUTF8((string)$z['nombre_zona']), ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endif; ?>
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


            <script>
    // Script para alternar la visibilidad de las filas con frecuencia "Nunca"
    document.getElementById('mostrar-nunca').addEventListener('change', function() {
        const mostrar = this.checked;
        const filas = document.querySelectorAll('.frecuencia-nunca');

        filas.forEach(fila => {
            fila.style.display = mostrar ? '' : 'none';
        });
    });

    // Inicialización: Ocultar filas si el checkbox está desmarcado
    document.addEventListener('DOMContentLoaded', function() {
        const mostrar = document.getElementById('mostrar-nunca').checked;
        const filas = document.querySelectorAll('.frecuencia-nunca');

        filas.forEach(fila => {
            fila.style.display = mostrar ? '' : 'none';
        });
    });
</script>

            
            <a href="zonas_clientes.php" class="back-button">Volver a Zonas</a>
            
            <script>
                // Función para verificar y cargar las secciones del cliente seleccionado
                function verificarSecciones(cod_cliente) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'obtener_secciones.php?cod_cliente=' + cod_cliente, true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState == 4 && xhr.status == 200) {
                            try {
                                var secciones = JSON.parse(xhr.responseText);
                                var seccionContainer = document.getElementById('seccion-container');
                                var seccionSelect = document.getElementById('cod_seccion');
                                
                                // Limpiar opciones existentes
                                seccionSelect.innerHTML = '<option value="">--Selecciona una Sección--</option>';
                                
                                // Determinar si el cliente tiene secciones disponibles
                                if (secciones.length > 0) {
                                    // Cliente tiene secciones disponibles, mostrar el campo de secciones
                                    for (var i = 0; i < secciones.length; i++) {
                                        var seccion_cod = parseInt(secciones[i].cod_seccion);
                                        var seccion_nombre = secciones[i].nombre;
                                        
                                        var option = document.createElement('option');
                                        option.value = seccion_cod;
                                        option.text = seccion_nombre;
                                        seccionSelect.appendChild(option);
                                    }
                                    seccionContainer.classList.remove('d-none');
                                    seccionSelect.required = true;
                                } else {
                                    // Cliente no tiene secciones disponibles, ocultar el campo de secciones
                                    seccionContainer.classList.add('d-none');
                                    seccionSelect.required = false;
                                    seccionSelect.value = '';
                                }
                            } catch(e) {
                                console.error('Error al parsear JSON:', e);
                            }
                        }
                    };
                    xhr.send();
                }

                // Agregar evento de cambio al select de clientes
                var clienteSelect = document.getElementById('cod_cliente');
                if (clienteSelect) {
                    clienteSelect.addEventListener('change', function() {
                        var cod_cliente = this.value;
                        
                        if (cod_cliente) {
                            verificarSecciones(cod_cliente);
                        } else {
                            // Si no hay cliente seleccionado, ocultar el campo de secciones
                            document.getElementById('seccion-container').classList.add('d-none');
                            document.getElementById('cod_seccion').required = false;
                            document.getElementById('cod_seccion').value = '';
                        }
                    });
                }

                // Validación del formulario antes de enviarlo
                var assignForm = document.getElementById('assign-form');
                if (assignForm) {
                    assignForm.addEventListener('submit', function(event) {
                        var errorMessage = document.getElementById('error-message');
                        var cod_cliente = document.getElementById('cod_cliente').value;
                        var seccionContainer = document.getElementById('seccion-container');
                        var cod_seccion = document.getElementById('cod_seccion').value;
                        var tiempo_promedio_visita = document.getElementById('tiempo_promedio_visita').value;
                        var preferencia_horaria = document.getElementById('preferencia_horaria').value;
                        var frecuencia_visita = document.getElementById('frecuencia_visita').value;
                        
                        // Resetear mensaje de error
                        errorMessage.classList.add('d-none');
                        errorMessage.textContent = 'Por favor, completa todos los campos obligatorios.';
                        
                        // Verificar campos obligatorios
                        if (cod_cliente === '') {
                            errorMessage.classList.remove('d-none');
                            errorMessage.textContent = 'Debes seleccionar un cliente.';
                            event.preventDefault();
                            return;
                        }
                        
                        if (!seccionContainer.classList.contains('d-none') && cod_seccion === '') {
                            errorMessage.classList.remove('d-none');
                            errorMessage.textContent = 'Debes seleccionar una sección.';
                            event.preventDefault();
                            return;
                        }
                        
                        /* if (tiempo_promedio_visita === '' || tiempo_promedio_visita <= 0) {
                            errorMessage.classList.remove('d-none');
                            errorMessage.textContent = 'Debes ingresar un tiempo promedio de visita vlido.';
                            event.preventDefault();
                            return;
                        } */
                        
                        /* if (preferencia_horaria === '') {
                            errorMessage.style.display = 'block';
                            errorMessage.textContent = 'Debes seleccionar una preferencia horaria.';
                            event.preventDefault();
                            return;
                        } */
                        
                        if (frecuencia_visita === '') {
                            errorMessage.style.display = 'block';
                            errorMessage.textContent = 'Debes seleccionar una frecuencia de visita.';
                            event.preventDefault();
                            return;
                        }
                        
                        // Si todas las validaciones pasan, el formulario se enviará
                    });
                }

                document.querySelectorAll('.js-edit-asignacion').forEach(function(button) {
                    button.addEventListener('click', function() {
                        document.getElementById('modal-cod-cliente').value = this.dataset.codCliente || '';
                        document.getElementById('modal-cod-zona').value = this.dataset.codZona || '';
                        document.getElementById('modal-cod-seccion').value = this.dataset.codSeccion || 'NULL';
                        document.getElementById('modal-nombre-cliente').value = this.dataset.nombreCliente || '';
                        document.getElementById('modal-nombre-seccion').value = this.dataset.nombreSeccion || 'Sin Sección';
                        document.getElementById('modal-nombre-zona').value = this.dataset.nombreZona || '';
                        document.getElementById('modal-zona-secundaria').value = this.dataset.zonaSecundaria || '';
                        document.getElementById('modal-tiempo-promedio-visita').value = this.dataset.tiempoPromedioVisita || '';
                        document.getElementById('modal-preferencia-horaria').value = this.dataset.preferenciaHoraria || '';
                        document.getElementById('modal-frecuencia-visita').value = this.dataset.frecuenciaVisita || '';
                        document.getElementById('modal-observaciones').value = this.dataset.observaciones || '';
                    });
                });
            </script>
<?php endif; ?>
    </div>
    
    <!-- jQuery + Bootstrap JS -->
        </body>
</html>

