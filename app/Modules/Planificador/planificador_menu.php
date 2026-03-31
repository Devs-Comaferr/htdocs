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
requierePermiso('perm_planificador');

$pageTitle = 'Planificacion de Rutas';
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
        }

        .routes-container {
            max-width: 1100px;
            margin: 34px auto 70px;
            padding: 0 24px;
        }

        .routes-header {
            text-align: center;
            margin-bottom: 26px;
        }

        .routes-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
        }

        .routes-header p {
            margin: 8px auto 0;
            max-width: 760px;
            font-size: 14px;
            line-height: 1.45;
            color: #64748b;
        }

        .routes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 22px;
        }

        .route-card {
            min-height: 220px;
            padding: 34px 24px;
            border-radius: 18px;
            background: #ffffff;
            text-decoration: none;
            color: #1f2937;
            text-align: center;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.06);
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .route-card:hover {
            color: #1f2937;
            transform: translateY(-8px);
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.10);
        }

        .route-icon {
            display: block;
            font-size: 40px;
            line-height: 1;
            margin-bottom: 18px;
        }

        .route-card h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
        }

        .route-card p {
            margin: 10px 0 0;
            font-size: 14px;
            line-height: 1.45;
            color: #64748b;
        }

        .route-link {
            margin-top: 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .route-link i {
            margin-left: 4px;
            font-size: 11px;
        }

        .route-card.calendar .route-icon { color: #0374ff; }
        .route-card.orders .route-icon { color: #28a745; }
        .route-card.manual .route-icon { color: #d39e00; }
        .route-card.complete .route-icon { color: #dc3545; }
        .route-card.holiday .route-icon { color: #fd7e14; }
        .route-card.closed .route-icon { color: #495057; }
        .route-card.zones .route-icon { color: #6f42c1; }
        .route-card.assign .route-icon { color: #17a2b8; }

        @media (max-width: 1100px) {
            .routes-container {
                margin-top: 28px;
                padding: 0 18px;
            }

            .routes-header h1 {
                font-size: 25px;
            }

            .route-card {
                min-height: 190px;
                padding: 28px 20px;
            }

            .route-card h3 {
                font-size: 18px;
            }

            .route-card p {
                font-size: 13px;
            }

            .route-icon {
                font-size: 34px;
                margin-bottom: 15px;
            }
        }

        @media (max-width: 900px) {
            .routes-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .routes-container {
                margin-bottom: 28px;
            }

            .routes-header {
                margin-bottom: 18px;
            }

            .route-card {
                min-height: 150px;
                padding: 22px 18px;
            }

            .route-card h3 {
                font-size: 17px;
            }

            .route-icon {
                font-size: 30px;
                margin-bottom: 12px;
            }

            .route-link {
                margin-top: 10px;
                font-size: 10px;
            }
        }

        @media (max-width: 520px) {
            body {
                padding-top: 70px;
            }

            .routes-container {
                padding: 0 12px;
            }

            .routes-header h1 {
                font-size: 23px;
            }

            .routes-header p {
                font-size: 13px;
            }

            .routes-grid {
                grid-template-columns: 1fr;
            }

            .route-card {
                min-height: auto;
                padding: 24px 18px;
            }
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

    <div class="routes-container">
        <div class="routes-header">
            <h1>Planificacion de rutas</h1>
            <p>Accede a las herramientas clave del planificador desde un panel rapido, compacto y visual.</p>
        </div>

        <div class="routes-grid">
            <a href="mostrar_calendario.php" class="route-card calendar" aria-label="Calendario">
                <div class="route-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Calendario</h3>
                <p>Consulta la agenda diaria y revisa la planificacion operativa.</p>
                <span class="route-link">Abrir vista <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="pedidos_visitas.php" class="route-card orders" aria-label="Visita por pedidos">
                <div class="route-icon">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <h3>Visita por pedidos</h3>
                <p>Relaciona pedidos con visitas desde el flujo comercial.</p>
                <span class="route-link">Gestionar pedidos <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="registrar_visita_manual.php" class="route-card manual" aria-label="Registrar visita manual">
                <div class="route-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h3>Visita manual</h3>
                <p>Crea visitas puntuales fuera del flujo automatico.</p>
                <span class="route-link">Registrar visita <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="completar_dia.php" class="route-card complete" aria-label="Completar dia">
                <div class="route-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Completar dia</h3>
                <p>Cierra la jornada y valida el trabajo pendiente.</p>
                <span class="route-link">Finalizar jornada <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="festivo_local.php" class="route-card holiday" aria-label="Festivo local">
                <div class="route-icon">
                    <i class="fas fa-flag"></i>
                </div>
                <h3>Festivo local</h3>
                <p>Configura excepciones del calendario por zona.</p>
                <span class="route-link">Configurar festivo <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="registrar_dia_no_laborable.php" class="route-card closed" aria-label="No laborable">
                <div class="route-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <h3>No laborable</h3>
                <p>Bloquea dias no operativos dentro de la planificacion.</p>
                <span class="route-link">Bloquear dia <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="zonas.php" class="route-card zones" aria-label="Zonas">
                <div class="route-icon">
                    <i class="fas fa-route"></i>
                </div>
                <h3>Zonas</h3>
                <p>Organiza la estructura comercial por zonas.</p>
                <span class="route-link">Ver zonas <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="asignacion_clientes_zonas.php" class="route-card assign" aria-label="Asignar clientes a zonas">
                <div class="route-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3>Asignar clientes</h3>
                <p>Relaciona clientes y zonas para preparar rutas.</p>
                <span class="route-link">Asignar clientes <i class="fa-solid fa-arrow-right"></i></span>
            </a>
        </div>
    </div>
</body>
</html>
