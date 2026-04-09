<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/public');
}

if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Modules/Planificador/services/planificador_service.php';
require_once BASE_PATH . '/app/Support/functions.php';

requierePermiso('perm_planificador');

$pageTitle = 'Reiniciar ciclo';
$zonas = obtenerZonasVisita();
$fechaInicioPorDefecto = date('Y-m-d');
$flashMensaje = trim((string)($_GET['mensaje'] ?? ''));
$flashEstado = trim((string)($_GET['estado'] ?? ''));

usort($zonas, static function ($a, $b) {
    $ordenA = (int)($a['orden'] ?? 0);
    $ordenB = (int)($b['orden'] ?? 0);

    if ($ordenA === $ordenB) {
        return strcmp((string)($a['nombre_zona'] ?? ''), (string)($b['nombre_zona'] ?? ''));
    }

    return $ordenA <=> $ordenB;
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            margin: 0;
            padding-top: 76px;
            background: linear-gradient(to bottom, #f8fafc, #eef2f7);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
        }

        .page-container {
            max-width: 980px;
            margin: 18px auto 36px;
            padding: 0 20px;
            box-sizing: border-box;
        }

        .hero-card,
        .zones-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
            padding: 22px;
            margin-bottom: 18px;
        }

        .hero-card h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .hero-card p {
            margin: 0;
            color: #64748b;
            line-height: 1.5;
        }

        .flash-message {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
        }

        .flash-message.ok {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .flash-message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .field-group {
            margin-bottom: 20px;
        }

        .field-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .field-group input[type="date"] {
            width: 100%;
            max-width: 240px;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .zones-list {
            display: grid;
            gap: 12px;
        }

        .zone-row {
            display: grid;
            grid-template-columns: 90px minmax(0, 1fr) 140px;
            gap: 12px;
            align-items: center;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #f8fafc;
        }

        .zone-order input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .zone-name {
            font-weight: 700;
        }

        .zone-meta {
            margin-top: 4px;
            font-size: 13px;
            color: #64748b;
        }

        .zone-current {
            text-align: right;
            font-size: 13px;
            color: #475569;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 22px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }

        .empty-state {
            color: #64748b;
            padding: 8px 0;
        }

        @media (max-width: 680px) {
            .zone-row {
                grid-template-columns: 1fr;
            }

            .zone-current {
                text-align: left;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

    <div class="page-container">
        <?php if ($flashMensaje !== ''): ?>
            <div class="flash-message <?= $flashEstado === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($flashMensaje, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="hero-card">
            <h1>Reiniciar ciclo de zonas</h1>
            <p>Define una fecha de inicio comun para todas las zonas del comercial activo y ajusta el orden real del ciclo. Asi evitamos tocar la tabla a mano cada vez que cambian las rutas tras vacaciones o reajustes.</p>
        </div>

        <div class="zones-card">
            <?php if (empty($zonas)): ?>
                <div class="empty-state">No hay zonas disponibles para este comercial.</div>
                <div class="actions">
                    <a href="planificador_menu.php" class="btn btn-secondary">Volver</a>
                </div>
            <?php else: ?>
                <form action="procesar_reiniciar_ciclos.php" method="post">
                    <?= csrfInput() ?>

                    <div class="field-group">
                        <label for="fecha_inicio_ciclo">Fecha de inicio del ciclo</label>
                        <input type="date" id="fecha_inicio_ciclo" name="fecha_inicio_ciclo" value="<?= htmlspecialchars($fechaInicioPorDefecto, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="zones-list">
                        <?php foreach ($zonas as $zona): ?>
                            <?php $codZona = (int)($zona['cod_zona'] ?? 0); ?>
                            <div class="zone-row">
                                <div class="zone-order">
                                    <label for="orden_zona_<?= $codZona ?>">Orden</label>
                                    <input
                                        type="number"
                                        id="orden_zona_<?= $codZona ?>"
                                        name="ordenes[<?= $codZona ?>]"
                                        min="1"
                                        max="<?= count($zonas) ?>"
                                        value="<?= htmlspecialchars((string)((int)($zona['orden'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >
                                </div>

                                <div>
                                    <div class="zone-name"><?= htmlspecialchars(toUTF8((string)($zona['nombre_zona'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="zone-meta">
                                        <?= (int)($zona['duracion_semanas'] ?? 0) ?> semana(s)
                                    </div>
                                </div>

                                <div class="zone-current">
                                    Orden actual: <?= (int)($zona['orden'] ?? 0) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="actions">
                        <a href="planificador_menu.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar nuevo ciclo</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
