<?php
declare(strict_types=1);

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
require_once BASE_PATH . '/app/Modules/Planificador/services/PlanificadorMenuViewBuilder.php';
requierePermiso('perm_planificador');

$pageTitle = 'Planificacion de Rutas';
$planificadorMenuViewData = planificadorBuildMenuViewData();
extract($planificadorMenuViewData, EXTR_OVERWRITE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/modules/planificador/planificador_menu.css">
</head>
<body>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

    <div class="routes-container">
        <?php if ($flashMensaje !== ''): ?>
            <div class="flash-message <?= $flashEstado === 'ok' ? 'ok' : 'error' ?>">
                <?= htmlspecialchars($flashMensaje, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="container-fluid mb-4 px-0">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <div class="card shadow-sm h-100 border-0">
                                <div class="card-body">
                                    <div class="text-muted small mb-1">HOY</div>
                                    <h5 class="fw-bold mb-3">
                                        <?= htmlspecialchars($nombreZonaActiva ?? 'Sin zona', ENT_QUOTES, 'UTF-8') ?>
                                    </h5>

                                    <div>
                                        <div class="fw-semibold fs-5">
                                            <?= $visitasHoy ?> visitas
                                        </div>

                                        <div class="text-muted small">
                                            <?= $realizadasHoy ?> realizadas &middot;
                                            <span class="text-warning"><?= $pendientesHoy ?> pendientes</span>
                                        </div>

                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar" style="width: <?= $progresoHoy ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <div class="card shadow-sm h-100 border-0">
                                <div class="card-body">
                                    <div class="text-muted small mb-1">ZONA ACTUAL</div>
                                    <h5 class="fw-bold mb-2">
                                        <?= htmlspecialchars($rangoCicloActualLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </h5>

                                    <div>
                                        <div class="fw-semibold fs-5">
                                            <?= $visitasZonaActual ?> visitas
                                        </div>

                                        <div class="text-muted small">
                                            <?= $realizadasZonaActual ?> realizadas &middot;
                                            <span class="text-warning"><?= $pendientesZonaActual ?> pendientes</span>
                                        </div>

                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?= $progresoZonaActual ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body">
                            <div class="text-muted small mb-1">SIGUIENTE ACCI&Oacute;N</div>
                            <?php if (!empty($nombreClienteRecomendado)): ?>
                                <h5 class="fw-bold mb-2">
                                    <?= htmlspecialchars($nombreClienteRecomendado, ENT_QUOTES, 'UTF-8') ?>
                                </h5>

                                <div class="text-muted small">
                                    <?= htmlspecialchars($motivoClienteRecomendado ?: 'No hay datos disponibles', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php else: ?>
                                <h5 class="fw-bold mb-2">Dia sin urgencias</h5>

                                <div class="text-muted small">
                                    No hay clientes prioritarios ahora mismo.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="routes-grid container-cards">
            <?php foreach ($cards as $card): ?>
                <?php if (!empty($card['modal_target'])): ?>
                    <button
                        type="button"
                        class="route-card card card-button <?= htmlspecialchars($card['card_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['span_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['key'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?>"
                        data-bs-toggle="modal"
                        data-bs-target="<?= htmlspecialchars($card['modal_target'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <div class="route-icon icon-wrapper <?= htmlspecialchars($card['icon_wrapper'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="<?= htmlspecialchars($card['icon_class'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <h3><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (!$card['compact']): ?>
                            <p><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <span class="route-link cta"><?= htmlspecialchars($card['cta'], ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-arrow-right"></i></span>
                    </button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($card['href'], ENT_QUOTES, 'UTF-8') ?>"
                       class="route-card card <?= htmlspecialchars($card['card_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['span_class'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($card['key'], ENT_QUOTES, 'UTF-8') ?>"
                       aria-label="<?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="route-icon icon-wrapper <?= htmlspecialchars($card['icon_wrapper'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="<?= htmlspecialchars($card['icon_class'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <h3><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (!$card['compact']): ?>
                            <p><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($card['metric_value'] !== null && $card['metric_label'] !== null): ?>
                            <div class="card-metric-block">
                                <div class="metric-value <?= htmlspecialchars($card['metric_class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($card['metric_value'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="metric-label"><?= htmlspecialchars($card['metric_label'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($card['status_text']) && !empty($card['status_class'])): ?>
                            <div class="status-badge <?= htmlspecialchars($card['status_class'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($card['status_text'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <span class="route-link cta"><?= htmlspecialchars($card['cta'], ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal fade" id="reiniciarCiclosModal" tabindex="-1" aria-labelledby="reiniciarCiclosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="procesar_reiniciar_ciclos.php" id="reiniciarCiclosForm">
                    <?= csrfInput() ?>
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-bold" id="reiniciarCiclosModalLabel">Reiniciar ciclos</h5>
                            <div class="text-muted small">Fecha comun para todas las zonas y orden ajustado de forma tactil.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <p class="cycle-form-note">Arrastra las zonas para colocarlas en el orden correcto. Si en algun movil el arrastre no resulta comodo, tambien puedes subir o bajar cada zona con las flechas.</p>

                        <div class="cycle-date-group">
                            <label for="fecha_inicio_ciclo_modal">Fecha de inicio del ciclo</label>
                            <input type="date" id="fecha_inicio_ciclo_modal" name="fecha_inicio_ciclo" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <?php if (!empty($zonasCiclo)): ?>
                            <ul class="cycle-list" id="cycleList">
                                <?php foreach ($zonasCiclo as $zona): ?>
                                    <?php $codZona = (int)($zona['cod_zona'] ?? 0); ?>
                                    <li class="cycle-item" data-cod-zona="<?= htmlspecialchars((string)$codZona, ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="cycle-order-badge">1</div>
                                        <div>
                                            <div class="cycle-zone-name"><?= htmlspecialchars(toUTF8((string)($zona['nombre_zona'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="cycle-zone-meta">
                                                <?= (int)($zona['duracion_semanas'] ?? 0) ?> semana(s) · Orden actual <?= (int)($zona['orden'] ?? 0) ?>
                                            </div>
                                        </div>
                                        <div class="cycle-controls">
                                            <button type="button" class="cycle-move-button" data-direction="up" aria-label="Subir zona">
                                                <i class="fas fa-chevron-up"></i>
                                            </button>
                                            <button type="button" class="cycle-move-button" data-direction="down" aria-label="Bajar zona">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                            <span class="cycle-drag-handle" aria-hidden="true">
                                                <i class="fas fa-grip-vertical"></i>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div id="cycleHiddenInputs"></div>
                        <?php else: ?>
                            <div class="cycle-empty">No hay zonas disponibles para este comercial.</div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" <?= empty($zonasCiclo) ? 'disabled' : '' ?>>Guardar ciclo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.planificadorMenuConfig = {
            abrirModalReiniciar: <?= json_encode((bool)$abrirModalReiniciar) ?>
        };
    </script>
    <script src="<?= BASE_URL ?>/assets/modules/planificador/planificador_menu.js"></script>
</body>
</html>
