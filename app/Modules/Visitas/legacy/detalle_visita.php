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
header('Content-Type: text/html; charset=utf-8');

// ConexiÃƒÂ³n a la base de datos
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

// Verificamos que se haya recibido el ID de la visita
if (!isset($_GET['id_visita'])) {
    echo "No se ha especificado la visita.";
    exit;
}

$id_visita = intval($_GET['id_visita']);

// Consulta para obtener los detalles de la visita
$sql = "SELECT * FROM cmf_visitas_comerciales WHERE id_visita = $id_visita";
$result = odbc_exec($conn, $sql);

if ($result && $row = odbc_fetch_array($result)) {
    echo "<h4>Detalles de la Visita #{$id_visita}</h4>";
    echo "<p><strong>Cliente:</strong> " . htmlspecialchars($row['nombre_comercial']) . "</p>";
    echo "<p><strong>Fecha y Hora:</strong> " . htmlspecialchars($row['fecha_visita']) . " " . htmlspecialchars($row['hora_inicio_visita']) . "</p>";
    echo "<p><strong>Estado:</strong> " . htmlspecialchars(normalizarEstadoVisita($row['estado_visita'])) . "</p>";
    // Agrega aqu otros campos que desees mostrar
} else {
    echo "No se encontraron detalles para la visita.";
}

?>
