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
// asignacion_clientes_zonas.php
require_once BASE_PATH . '/app/Modules/Planificacion/PlanificacionService.php';
require_once BASE_PATH . '/app/Support/functions.php';
$pageTitle = "Asignar Clientes a Zonas";
include BASE_PATH . '/resources/views/layouts/header.php';

// Verificar si el usuario ha iniciado sesiÃƒÂ³n

// Obtener todas las zonas asignadas al vendedor
$zonas = obtenerZonasVisitaService();
$zonas_alertas = array();
$clientes_desalineados = array();

// Verificar si se ha pasado 'cod_zona' en la URL
if (isset($_GET['cod_zona'])) {
    $cod_zona = intval($_GET['cod_zona']);
    
    // Obtener informaciÃƒÂ³n de la zona
    $zona_actual = obtenerZonaPorCodigo($cod_zona);
    
    if (!$zona_actual) {
        error_log('Zona no encontrada.');
        echo 'Error interno';
    }
        return;
    
    // Obtener rutas asignadas a la zona
    $rutas_asignadas = obtenerRutasPorZonaService($cod_zona);
    
    // Obtener clientes filtrados por rutas asignadas y no ya asignados a la zona
    $clientes_disponibles = obtenerClientesDisponiblesParaAsignar($cod_zona, $rutas_asignadas);
    
    // Obtener asignaciones actuales de clientes a la zona
    $asignaciones_actuales = obtenerClientesPorZona($cod_zona);

    // Clientes en la zona cuyo cod_vendedor no coincide con el vendedor en sesiÃƒÂ³n
    $codigoSesion = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
    if ($codigoSesion > 0 && !empty($asignaciones_actuales)) {
        $codigosClientes = array();
        foreach ($asignaciones_actuales as $asig) {
            if (isset($asig['cod_cliente']) && $asig['cod_cliente'] !== '') {
                $codigosClientes[] = intval($asig['cod_cliente']);
            }
        }
        $codigosClientes = array_values(array_unique($codigosClientes));

        if (!empty($codigosClientes)) {
            $inClientes = implode(',', $codigosClientes);
            $sql_desalineados = "
                SELECT DISTINCT c.cod_cliente
                FROM clientes c
                WHERE c.cod_cliente IN ($inClientes)
                  AND (c.cod_vendedor IS NULL OR c.cod_vendedor <> $codigoSesion)
            ";
            $res_desalineados = odbc_exec($conn, $sql_desalineados);
            if ($res_desalineados) {
                while ($fila_desalineada = odbc_fetch_array($res_desalineados)) {
                    $codClienteDesalineado = (string)($fila_desalineada['cod_cliente'] ?? '');
                    if ($codClienteDesalineado !== '') {
                        $clientes_desalineados[$codClienteDesalineado] = true;
                    }
                }
            }
        }
    }
    
} else {
    // No se ha pasado 'cod_zona', mostrar listado de zonas para seleccionar
    $cod_zona = null;
    $codigoSesion = isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;

    if ($codigoSesion > 0) {
        $sql_alertas = "
            SELECT
                z.cod_zona,
                COUNT(DISTINCT azc.cod_cliente) AS total_desalineados
            FROM cmf_zonas_visita z
            LEFT JOIN cmf_asignacion_zonas_clientes azc
                ON (azc.zona_principal = z.cod_zona OR azc.zona_secundaria = z.cod_zona)
            LEFT JOIN clientes c
                ON c.cod_cliente = azc.cod_cliente
            WHERE z.cod_vendedor = $codigoSesion
              AND (c.cod_vendedor IS NULL OR c.cod_vendedor <> $codigoSesion)
            GROUP BY z.cod_zona
        ";
        $res_alertas = odbc_exec($conn, $sql_alertas);
        if ($res_alertas) {
            while ($fila_alerta = odbc_fetch_array($res_alertas)) {
                $codZonaAlerta = (string)($fila_alerta['cod_zona'] ?? '');
                if ($codZonaAlerta !== '') {
                    $zonas_alertas[$codZonaAlerta] = (int)($fila_alerta['total_desalineados'] ?? 0);
                }
            }
        }
    }
    
    // Obtener clientes asignados para cada zona
    $asignaciones_por_zona = array();
    foreach ($zonas as $zona) {
        $asignaciones_por_zona[$zona['cod_zona']] = obtenerClientesPorZona($zona['cod_zona']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Clientes a Zonas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 3.3.7 -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
    <!-- Font Awesome para los ÃƒÂ­conos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
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

        /* Responsividad para dispositivos mÃƒÂ³viles */
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
        .assign-form select, .assign-form input, .assign-form textarea {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .assign-form input[type="submit"] {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 20px;
        }
        .assign-form input[type="submit"]:hover {
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
        /* Estilo para el campo de SecciÃƒÂ³n oculto */
        #seccion-container {
            display: none;
        }
        /* Estilos para mensajes de error */
        .error-message {
            color: red;
            margin-bottom: 15px;
            display: none;
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
                <a href="asignacion_clientes_zonas.php?cod_zona=<?php echo htmlspecialchars($zona['cod_zona']); ?>" class="btn-zona">
                    <?php if ($totalDesalineados > 0): ?>
                        <span class="zona-alerta-badge"><?php echo $totalDesalineados; ?></span>
                    <?php endif; ?>
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($zona['nombre_zona']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-data">No tienes zonas disponibles. <a href="zonas.php">Crear Zonas</a></div>
    <?php endif; ?>
<?php else: ?>
            <h2><?php echo htmlspecialchars($zona_actual['nombre_zona']); ?></h2>
            
            <?php if (!empty($clientes_disponibles)): ?>
            <div class="assign-form">
                <h3>Asignar Nuevo Cliente a la Zona</h3>
                
                <!-- Mensaje de error -->
                <div id="error-message" class="error-message">Por favor, completa todos los campos obligatorios.</div>
                
                <form id="assign-form" action="procesar_asignar_cliente_zona.php" method="post">
                    <input type="hidden" name="cod_zona" value="<?php echo $cod_zona; ?>">
                    
                    <label for="cod_cliente">Selecciona el Cliente:</label>
                    <select id="cod_cliente" name="cod_cliente" required>
                        <option value="">--Selecciona un Cliente--</option>
                        <?php if (!empty($clientes_disponibles)): ?>
                            <?php foreach ($clientes_disponibles as $cliente): ?>
                                <option value="<?php echo $cliente['cod_cliente']; ?>">
                                    <?php echo htmlspecialchars($cliente['nombre_cliente']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No hay clientes disponibles para asignar.</option>
                        <?php endif; ?>
                    </select>
                    
                    <!-- Campo de SecciÃƒÂ³n que se muestra dinÃƒÂ¡micamente -->
                    <div id="seccion-container">
                        <label for="cod_seccion">Selecciona la SecciÃƒÂ³n:</label>
                        <select id="cod_seccion" name="cod_seccion">
                            <option value="">--Selecciona una SecciÃƒÂ³n--</option>
                            <!-- Las opciones se llenarÃƒÂ¡n mediante AJAX -->
                        </select>
                    </div>
                    
                    <label for="zona_secundaria">Zona Secundaria (Opcional):</label>
                    <select id="zona_secundaria" name="zona_secundaria">
                        <option value="">--Selecciona una Zona Secundaria--</option>
                        <?php foreach ($zonas as $zona): ?>
                            <?php if ($zona['cod_zona'] != $cod_zona): ?>
                                <option value="<?php echo htmlspecialchars($zona['cod_zona']); ?>">
                                    <?php echo htmlspecialchars($zona['nombre_zona']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="tiempo_promedio_visita">Tiempo Promedio de Visita (horas):</label>
                    <input type="number" id="tiempo_promedio_visita" name="tiempo_promedio_visita" step="0.5">
                    
                    <label for="preferencia_horaria">Preferencia Horaria:</label>
                    <select id="preferencia_horaria" name="preferencia_horaria">
                        <option value="">--Selecciona una Preferencia--</option>
                        <option value="M">MaÃƒÂ±ana</option>
                        <option value="T">Tarde</option>
                    </select>
                    
                    <label for="frecuencia_visita">Frecuencia de Visita:</label>
                    <select id="frecuencia_visita" name="frecuencia_visita" required>
                        <option value="">--Selecciona una Frecuencia--</option>
                        <option value="Todos">Todos los meses</option>
                        <option value="Cada2">Cada 2 meses</option>
                        <option value="Cada3">Cada 3 meses</option>
                        <option value="Nunca">Nunca</option>
                    </select>
                    
                    <label for="observaciones">Observaciones (Opcional):</label>
                    <textarea id="observaciones" name="observaciones"></textarea>
                    
                    <input type="submit" value="Asignar Cliente a Zona">
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
        <?php
            $numSeccionesPorCliente = [];
            foreach ($asignaciones_actuales as $asigCount) {
                $codCliCount = (string)($asigCount['cod_cliente'] ?? '');
                if ($codCliCount === '') {
                    continue;
                }
                if (!isset($numSeccionesPorCliente[$codCliCount])) {
                    $numSeccionesPorCliente[$codCliCount] = [];
                }
                $nombreSecKey = trim((string)($asigCount['nombre_seccion'] ?? ''));
                if ($nombreSecKey !== '') {
                    $numSeccionesPorCliente[$codCliCount][$nombreSecKey] = true;
                }
            }
        ?>
        <?php foreach ($asignaciones_actuales as $asignacion): ?>
            <?php
                // Determinar la clase CSS basada en el tipo de asignaciÃƒÂ³n
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
                        echo htmlspecialchars($asignacion['nombre_cliente']) . " - " . htmlspecialchars((string)$municipioLineaPrincipal);
                    ?>
                    <?php
                    $codCliFila = (string)($asignacion['cod_cliente'] ?? '');
                    $tieneVariasSecciones = $codCliFila !== '' && isset($numSeccionesPorCliente[$codCliFila]) && count($numSeccionesPorCliente[$codCliFila]) > 1;
                    if ($tieneVariasSecciones && !empty($asignacion['nombre_seccion'])) {
                        echo '<br>&nbsp;&nbsp;&nbsp;&nbsp; &#128204; '. htmlspecialchars($asignacion['nombre_seccion']);
                    }
                    ?>
                    <?php if (!empty($asignacion['observaciones'])) { ?>
                        <br>
                        <span class="asignacion-secundaria">&#9997; <?php echo htmlspecialchars($asignacion['observaciones']); ?></span>
                    <?php } ?>

                </td>
                <td><center><?php echo ucfirst($asignacion['frecuencia_visita']); ?></center></td>
                <td>
                <?php if ($asignacion['tipo_asignacion'] === 'primaria') {?>
                    
                
    <!-- BotÃƒÂ³n de Editar -->
    <!-- BotÃƒÂ³n de Editar -->
<form action="editar_asignacion.php" method="get" style="display:inline;">
    <input type="hidden" name="cod_cliente" value="<?php echo htmlspecialchars($asignacion['cod_cliente']); ?>">
    <input type="hidden" name="cod_zona" value="<?php echo htmlspecialchars($cod_zona); ?>">
    <input type="hidden" name="cod_seccion" value="<?php echo isset($asignacion['cod_seccion']) ? htmlspecialchars($asignacion['cod_seccion']) : 'NULL'; ?>">
    <button type="submit" class="btn btn-sm btn-warning" title="Editar AsignaciÃƒÂ³n">
        <i class="fas fa-pencil"></i>
    </button>
</form>

    <!-- BotÃƒÂ³n de Eliminar -->
    <form action="borrar_asignacion.php" method="post" style="display:inline;" onsubmit="return confirm('EstÃƒÂ¡s seguro de que deseas eliminar esta asignaciÃƒÂ³n?');">
        <input type="hidden" name="cod_cliente" value="<?php echo htmlspecialchars($asignacion['cod_cliente']); ?>">
        <input type="hidden" name="cod_zona" value="<?php echo htmlspecialchars($cod_zona); ?>">
        <input type="hidden" name="cod_seccion" value="<?php echo htmlspecialchars($asignacion['cod_seccion']); ?>">
        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar AsignaciÃƒÂ³n">
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


            <script>
    // Script para alternar la visibilidad de las filas con frecuencia "Nunca"
    document.getElementById('mostrar-nunca').addEventListener('change', function() {
        const mostrar = this.checked;
        const filas = document.querySelectorAll('.frecuencia-nunca');

        filas.forEach(fila => {
            fila.style.display = mostrar ? '' : 'none';
        });
    });

    // InicializaciÃƒÂ³n: Ocultar filas si el checkbox estÃƒÂ¡ desmarcado
    document.addEventListener('DOMContentLoaded', function() {
        const mostrar = document.getElementById('mostrar-nunca').checked;
        const filas = document.querySelectorAll('.frecuencia-nunca');

        filas.forEach(fila => {
            fila.style.display = mostrar ? '' : 'none';
        });
    });
</script>

            
            <a href="asignacion_clientes_zonas.php" class="back-button">Volver a Zonas</a>
            
            <script>
                // FunciÃƒÂ³n para verificar y cargar las secciones del cliente seleccionado
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
                                seccionSelect.innerHTML = '<option value="">--Selecciona una SecciÃƒÂ³n--</option>';
                                
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
                                    seccionContainer.style.display = 'block';
                                    seccionSelect.required = true;
                                } else {
                                    // Cliente no tiene secciones disponibles, ocultar el campo de secciones
                                    seccionContainer.style.display = 'none';
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
                document.getElementById('cod_cliente').addEventListener('change', function() {
                    var cod_cliente = this.value;
                    
                    if (cod_cliente) {
                        verificarSecciones(cod_cliente);
                    } else {
                        // Si no hay cliente seleccionado, ocultar el campo de secciones
                        document.getElementById('seccion-container').style.display = 'none';
                        document.getElementById('cod_seccion').required = false;
                        document.getElementById('cod_seccion').value = '';
                    }
                });

                // ValidaciÃƒÂ³n del formulario antes de enviarlo
                document.getElementById('assign-form').addEventListener('submit', function(event) {
                    var errorMessage = document.getElementById('error-message');
                    var cod_cliente = document.getElementById('cod_cliente').value;
                    var seccionContainer = document.getElementById('seccion-container');
                    var cod_seccion = document.getElementById('cod_seccion').value;
                    var tiempo_promedio_visita = document.getElementById('tiempo_promedio_visita').value;
                    var preferencia_horaria = document.getElementById('preferencia_horaria').value;
                    var frecuencia_visita = document.getElementById('frecuencia_visita').value;
                    
                    // Resetear mensaje de error
                    errorMessage.style.display = 'none';
                    errorMessage.textContent = 'Por favor, completa todos los campos obligatorios.';
                    
                    // Verificar campos obligatorios
                    if (cod_cliente === '') {
                        errorMessage.style.display = 'block';
                        errorMessage.textContent = 'Debes seleccionar un cliente.';
                        event.preventDefault();
                        return;
                    }
                    
                    if (seccionContainer.style.display === 'block' && cod_seccion === '') {
                        errorMessage.style.display = 'block';
                        errorMessage.textContent = 'Debes seleccionar una secciÃƒÂ³n.';
                        event.preventDefault();
                        return;
                    }
                    
                    /* if (tiempo_promedio_visita === '' || tiempo_promedio_visita <= 0) {
                        errorMessage.style.display = 'block';
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
                    
                    // Si todas las validaciones pasan, el formulario se enviarÃƒÂ¡
                });
            </script>
<?php endif; ?>
    </div>
    
    <!-- jQuery + Bootstrap JS -->
    <script src="<?= BASE_URL ?>/assets/vendor/legacy/jquery-1.12.4.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/js/bootstrap.min.js"></script>
</body>
</html>
