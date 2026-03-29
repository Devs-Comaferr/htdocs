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
requierePermiso('perm_estadisticas');

$pageTitle = "Estadsticas";
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

        .stats-container {
            max-width: 1000px;
            margin: 70px auto 120px;
            padding: 0 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
        }

        .stats-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 60px 40px;
            text-decoration: none;
            color: #1f2937;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.06);
            transition: all 0.25s ease;
            position: relative;
            text-align: center;
        }

        .stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.10);
        }

        .stats-card h3 {
            margin-top: 10px;
            font-size: 20px;
            font-weight: 600;
        }

        .stats-card p {
            margin-top: 10px;
            font-size: 14px;
            color: #64748b;
        }

        .stats-icon {
            font-size: 44px;
            margin-bottom: 20px;
            color: #3b82f6;
        }

        .stats-icon.neutral {
            color: #475569;
        }

        .badge-new {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #3b82f6;
            color: #fff;
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>

    <div class="stats-container">
        <div class="stats-grid">
            <a href="estadisticas_ventas_clasicas.php" class="stats-card">
                <div class="stats-icon neutral">
                    <i class="fa fa-file-alt"></i>
                </div>
                <h3>Ventas (clsico)</h3>
                <p>Informe tradicional por artculos y clientes</p>
            </a>

            <a href="estadisticas_ventas_comerciales.php" class="stats-card primary">
                <div class="stats-icon">
                    <i class="fa fa-chart-line"></i>
                </div>
                <h3>Ventas comerciales</h3>
                <p>Anlisis por comercial, provincia y cliente</p>
                <span class="badge-new">Nuevo</span>
            </a>
        </div>
    </div>
</body>
</html>
