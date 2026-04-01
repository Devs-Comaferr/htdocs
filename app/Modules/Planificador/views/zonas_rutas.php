<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
// zonas_rutas.php
require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
requierePermiso('perm_planificador');
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';
require_once BASE_PATH . '/app/Support/functions.php';
$pageTitle = 'Gestionar Rutas de la Zona';
include BASE_PATH . '/resources/views/layouts/header.php';

$zonasRutasViewData = obtenerDatosZonasRutasView(
    isset($_GET['cod_zona']) ? intval($_GET['cod_zona']) : null,
    isset($_GET['cod_ruta']) ? intval($_GET['cod_ruta']) : 0
);
if (!empty($zonasRutasViewData['error'])) {
    echo $zonasRutasViewData['error'];
    return;
}
$cod_zona = $zonasRutasViewData['cod_zona'];
$cod_ruta_seleccionada = $zonasRutasViewData['cod_ruta_seleccionada'];
$zonas = $zonasRutasViewData['zonas'];
$zona_actual = $zonasRutasViewData['zona_actual'];
$rutas_asignadas = $zonasRutasViewData['rutas_asignadas'];
$clientes_ruta = $zonasRutasViewData['clientes_ruta'];
$ruta_actual = $zonasRutasViewData['ruta_actual'];
$todas_rutas_disponibles = $zonasRutasViewData['todas_rutas_disponibles'];
$zonas_disponibles = $zonasRutasViewData['zonas_disponibles'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($cod_zona) ? 'Gestionar Rutas de la Zona: ' . htmlspecialchars(toUTF8((string)$zona_actual['nombre_zona']), ENT_QUOTES, 'UTF-8') : 'Zonas Disponibles'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding-top: 20px;
            background-color: #f0f2f5;
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        .assign-form, .back-button, .zonas-list {
            max-width: 800px;
            margin: 0 auto 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .assign-form select, .zonas-list .zona-item {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .assign-form input[type="submit"], .zonas-list .zona-item a {
            width: 100%;
            padding: 15px;
            background-color: #17a2b8;
            color: #fff;
            border: none;
            font-size: 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .assign-form input[type="submit"]:hover, .zonas-list .zona-item a:hover {
            background-color: #117a8b;
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
            text-align: center;
            font-size: 16px;
        }
        th {
            background-color: #e9ecef;
            color: #333;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .route-row {
            cursor: pointer;
        }
        .route-row-active td {
            background-color: #e0f2fe;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #777;
        }
        .action-link {
            padding: 10px 20px;
            background-color: #dc3545;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .action-link:hover {
            background-color: #c82333;
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
        .zonas-list .zona-item {
            margin-bottom: 15px;
        }
        .zonas-list .zona-item a {
            background-color: #28a745;
        }
        .zonas-list .zona-item a:hover {
            background-color: #218838;
        }
        .flash-message {
            max-width: 800px;
            margin: 20px auto;
            padding: 12px 16px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }
        .flash-message.ok {
            background-color: #d4edda;
            color: #155724;
        }
        .flash-message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .route-actions {
            display: flex;
            justify-content: center;
        }
        .btn-delete-route {
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            background-color: #dc3545;
            color: #fff;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-delete-route:hover {
            background-color: #bb2d3b;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php $flashMensaje = trim((string)($_GET['mensaje'] ?? '')); ?>
        <?php $flashEstado = trim((string)($_GET['estado'] ?? '')); ?>
        <?php if ($flashMensaje !== ''): ?>
            <div class="flash-message <?= $flashEstado === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($flashMensaje, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if (isset($cod_zona)): ?>
            <h1><?php echo htmlspecialchars(toUTF8((string)$zona_actual['nombre_zona']), ENT_QUOTES, 'UTF-8'); ?></h1>

            <h2>Rutas Asignadas</h2>
            <table>
                <tr>
                    <th>C&oacute;digo de Ruta</th>
                    <th>Nombre de Ruta</th>
                    <th>Acci&oacute;n</th>
                </tr>
                <?php if (!empty($rutas_asignadas)): ?>
                    <?php foreach ($rutas_asignadas as $ruta): ?>
                        <?php $esRutaActiva = (int)($ruta['cod_ruta'] ?? 0) === (int)$cod_ruta_seleccionada; ?>
                        <tr class="route-row<?php echo $esRutaActiva ? ' route-row-active' : ''; ?>" onclick="window.location.href='zonas_rutas.php?cod_zona=<?php echo htmlspecialchars((string)$cod_zona, ENT_QUOTES, 'UTF-8'); ?>&cod_ruta=<?php echo htmlspecialchars((string)$ruta['cod_ruta'], ENT_QUOTES, 'UTF-8'); ?>'">
                            <td><?php echo htmlspecialchars((string)$ruta['cod_ruta'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(toUTF8((string)$ruta['nombre_ruta']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td onclick="event.stopPropagation();">
                                <div class="route-actions">
                                    <form action="eliminar_ruta_zona.php" method="post" onsubmit="return confirm('Seguro que deseas quitar esta ruta de la zona?');">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="cod_zona" value="<?php echo htmlspecialchars((string)$cod_zona, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="cod_ruta" value="<?php echo htmlspecialchars((string)$ruta['cod_ruta'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn-delete-route">Quitar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="no-data">No hay rutas asignadas a esta zona.</td>
                    </tr>
                <?php endif; ?>
            </table>

            <div class="assign-form">
                <h2>Asignar Nueva Ruta a la Zona</h2>
                <form action="procesar_asignar_ruta_zona.php" method="post">
                    <?= csrfInput() ?>
                    <input type="hidden" name="cod_zona" value="<?php echo $cod_zona; ?>">

                    <label for="cod_ruta">Selecciona la Ruta:</label>
                    <select id="cod_ruta" name="cod_ruta" required>
                        <option value="">--Selecciona una Ruta--</option>
                        <?php foreach ($todas_rutas_disponibles as $ruta): ?>
                            <option value="<?php echo htmlspecialchars((string)$ruta['cod_ruta'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(toUTF8((string)$ruta['nombre_ruta']), ENT_QUOTES, 'UTF-8') . ' - (' . htmlspecialchars((string)$ruta['cod_ruta'], ENT_QUOTES, 'UTF-8') . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="submit" value="Asignar Ruta">
                </form>
            </div>

            <?php if ($ruta_actual !== null): ?>
                <div class="assign-form">
                    <h2>Clientes de la Ruta <?php echo htmlspecialchars(toUTF8((string)$ruta_actual['nombre_ruta']), ENT_QUOTES, 'UTF-8'); ?> fuera del vendedor actual</h2>
                    <table>
                        <tr>
                            <th>Cliente</th>
                            <th>Secci&oacute;n</th>
                            <th>Frecuencia</th>
                        </tr>
                        <?php if (!empty($clientes_ruta)): ?>
                            <?php foreach ($clientes_ruta as $clienteRuta): ?>
                                <tr>
                                    <td>
                                        <?php
                                            $poblacionClienteRuta = trim((string)($clienteRuta['poblacion_cliente'] ?? ''));
                                            echo htmlspecialchars(toUTF8((string)$clienteRuta['nombre_cliente']), ENT_QUOTES, 'UTF-8');
                                            if ($poblacionClienteRuta !== '') {
                                                echo ' - ' . htmlspecialchars(toUTF8($poblacionClienteRuta), ENT_QUOTES, 'UTF-8');
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(toUTF8((string)($clienteRuta['nombre_seccion'] ?? 'Sin seccion')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($clienteRuta['frecuencia_visita'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="no-data">No hay clientes de esta ruta que pertenezcan a otro vendedor.</td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>

            <a href="zonas.php" class="back-button">Volver a Zonas</a>

        <?php else: ?>
            <h1>Zonas Disponibles</h1>

            <?php if (!empty($zonas_disponibles)): ?>
                <div class="zonas-list">
                    <?php foreach ($zonas_disponibles as $zona): ?>
                        <div class="zona-item">
                            <a href="zonas_rutas.php?cod_zona=<?php echo htmlspecialchars((string)$zona['cod_zona'], ENT_QUOTES, 'UTF-8'); ?>" class="action-link">
                                <?php echo htmlspecialchars(toUTF8((string)$zona['nombre_zona']), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">No tienes zonas disponibles.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- jQuery + Bootstrap JS -->
</body>
</html>


