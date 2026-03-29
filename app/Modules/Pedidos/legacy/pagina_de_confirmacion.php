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
// Mensaje de éxito si se pasó correctamente
$mensaje = 'Tu visita y pedido han sido registrados correctamente.';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/css/bootstrap.min.css">
    <style>
        .confirmation-container {
            text-align: center;
            margin-top: 50px;
        }
        .confirmation-message {
            font-size: 1.5em;
            margin-bottom: 20px;
        }
        .back-button {
            font-size: 1.2em;
        }
    </style>
</head>
<body>

    <div class="container confirmation-container">
        <div class="confirmation-message alert alert-success">
            <?php echo $mensaje; ?>
        </div>
        
        <!-- Esto será visible durante los 2 segundos antes de la redirección -->
        <div>
            <p>Sers redirigido automticamente...</p>
        </div>
    </div>

    <!-- Redirección con JavaScript después de 2 segundos -->
    <script>
        setTimeout(function() {
            window.location.href = "index.php"; // Redirige a index.php
        }, 2000); // 2000 ms = 2 segundos
    </script>

    <script src="<?= BASE_URL ?>/assets/vendor/jquery/jquery.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/vendor/legacy/bootstrap-3.3.7/js/bootstrap.min.js"></script>
</body>
</html>
