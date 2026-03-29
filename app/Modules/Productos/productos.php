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
requierePermiso('perm_productos');

// Definir el ttulo de la pgina para el header
$pageTitle = 'Productos';

// Incluir la conexión ODBC y las funciones generales (necesarias para el header)
require_once BASE_PATH . '/app/Support/functions.php';

$conn = db();

// Ahora incluir el header (que usa funciones como toUTF8)
require_once BASE_PATH . '/resources/views/layouts/header.php';

// Consultar la lista de marcas disponibles (para el select) inicialmente
$brandQuery = "SELECT cod_marca, descripcion FROM [integral].[dbo].[web_marcas] ORDER BY descripcion";
$brandStmt  = odbc_exec($conn, $brandQuery);
$brands     = [];
if ($brandStmt) {
    while ($row = odbc_fetch_array($brandStmt)) {
        // Si la descripción es null o vacía, reemplazar por "Sin marca rellenada"
        if (!isset($row['descripcion']) || trim($row['descripcion']) === '') {
            $row['descripcion'] = 'Sin marca rellenada';
        }
        $brands[] = $row;
    }
}

// Procesar la visualización de detalles o la búsqueda compuesta
$producto = null;
if (isset($_GET['cod_articulo']) && !empty($_GET['cod_articulo'])) {
    $cod_articulo = $_GET['cod_articulo'];
    $producto = obtenerProducto($conn, $cod_articulo);
}

// Función auxiliar para reconstruir los parámetros de búsqueda en la URL
function getSearchParams(): string {
    $params = [];
    if (isset($_GET['codigo']) && $_GET['codigo'] !== '') {
        $params[] = 'codigo=' . urlencode($_GET['codigo']);
    }
    if (isset($_GET['descripcion']) && $_GET['descripcion'] !== '') {
        $params[] = 'descripcion=' . urlencode($_GET['descripcion']);
    }
    if (isset($_GET['marca']) && $_GET['marca'] !== '') {
        $params[] = 'marca=' . urlencode($_GET['marca']);
    }
    return implode('&', $params);
}
$searchQuery = getSearchParams();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background-color: #f8f9fa;
            color: #343a40;
            padding-top: 70px; /* Para compensar el header fijo */
        }
        main {
            max-width: 1000px;
            margin: 0 auto;
        }
        form {
            background: #ffffff;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        form input[type="text"],
        form input[type="submit"],
        form input[type="button"],
        form select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        form input[type="submit"] {
            background-color: #0073aa;
            color: #fff;
            cursor: pointer;
            font-weight: bold;
            border: none;
        }
        form input[type="submit"]:hover {
            background-color: #005f8d;
        }
        form input[type="button"] {
            background-color: #6c757d;
            color: #fff;
            cursor: pointer;
            font-weight: bold;
            border: none;
        }
        form input[type="button"]:hover {
            background-color: #545b62;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: #ffffff;
            border: 1px solid #dee2e6;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #e9ecef;
        }
        tr.clickable {
            cursor: pointer;
        }
        tr.clickable:hover {
            background-color: #f1f1f1;
        }
        a {
            color: #0073aa;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .detalles {
            background: #ffffff;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .volver {
            margin-top: 15px;
        }
    </style>
    <script>
        // Función para actualizar el select de marcas según los valores ingresados en "codigo" o "descripcion"
        function updateBrandSelect() {
            var codigo = document.getElementById('codigo').value;
            var descripcion = document.getElementById('descripcion').value;
            
            var url = '<?= BASE_URL ?>/ajax/get_marcas.php?codigo=' + encodeURIComponent(codigo) + '&descripcion=' + encodeURIComponent(descripcion);
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    var select = document.getElementById('marca');
                    // Limpiar las opciones actuales y dejar la opción por defecto
                    select.innerHTML = '<option value="">-- Seleccione marca --</option>';
                    // Agregar la opción manual "Sin marca rellenada" solo si no existe ya en los datos
                    var alreadyExists = false;
                    data.forEach(function(item) {
                        if (item.cod_marca === null || item.cod_marca === "NULL") {
                            alreadyExists = true;
                        }
                    });
                    if (!alreadyExists) {
                        var optNull = document.createElement('option');
                        optNull.value = "NULL";
                        optNull.text = "Sin marca rellenada";
                        select.appendChild(optNull);
                    }
                    
                    // Rellenar con las marcas obtenidas
                    data.forEach(function(item) {
                        var opt = document.createElement('option');
                        opt.value = item.cod_marca;
                        opt.text = (item.descripcion && item.descripcion.trim() !== '') ? item.descripcion : 'Sin marca rellenada';
                        select.appendChild(opt);
                    });
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Al cargar la pgina, si ya hay valores en "codigo" o "descripcion", actualizar el select de marcas
        window.addEventListener('DOMContentLoaded', function() {
            var codigoField = document.getElementById('codigo');
            var descripcionField = document.getElementById('descripcion');
            if(codigoField.value.trim() !== '' || descripcionField.value.trim() !== ''){
                updateBrandSelect();
            }
            codigoField.addEventListener('blur', updateBrandSelect);
            descripcionField.addEventListener('blur', updateBrandSelect);
        });
    </script>
</head>
<body>
    <main>
        <!-- Formulario de búsqueda -->
        <section>
            <form method="get" action="productos.php">
                <label for="codigo">Código / Referencia Alternativa:</label>
                <input type="text" name="codigo" id="codigo" value="<?= isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : ''; ?>">
                
                <label for="descripcion">Descripción:</label>
                <input type="text" name="descripcion" id="descripcion" value="<?= isset($_GET['descripcion']) ? htmlspecialchars($_GET['descripcion']) : ''; ?>">
                
                <label for="marca">Marca:</label>
                <select name="marca" id="marca">
                    <option value="">-- Seleccione marca --</option>
                    <!-- Opción manual para Sin marca rellenada -->
                    <option value="NULL" <?= (isset($_GET['marca']) && $_GET['marca'] == "NULL") ? 'selected' : '' ?>>Sin marca rellenada</option>
                    <?php foreach ($brands as $brand): ?>
                        <?php
                        // Si la descripción es vacía, asignar "NULL" como valor
                        $optValue = (empty(trim($brand['descripcion']))) ? "NULL" : $brand['cod_marca'];
                        $optText = (empty(trim($brand['descripcion']))) ? "Sin marca rellenada" : $brand['descripcion'];
                        ?>
                        <option value="<?= htmlspecialchars($optValue) ?>" <?= (isset($_GET['marca']) && $_GET['marca'] == $optValue) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($optText) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" value="Buscar">
                <input type="button" value="Limpiar" onclick="window.location.href='productos.php'">
            </form>
        </section>
        
        <!-- Sección para mostrar resultados o detalles -->
        <section>
        <?php
            // Si se muestran detalles de un producto
            if (isset($producto)) {
                if ($producto) {
                    echo '<div class="detalles">';
                    echo "<h2>Detalles del Producto</h2>";
                    echo "<p><strong>Código:</strong> " . htmlspecialchars($producto['cod_articulo']) . "</p>";
                    echo "<p><strong>Marca:</strong> " . (isset($producto['marca']) && trim($producto['marca']) !== '' ? htmlspecialchars($producto['marca']) : 'Sin marca rellenada') . "</p>";
                    echo "<p><strong>Descripción:</strong> " . htmlspecialchars($producto['descripcion_articulo'] ?? $producto['descripcion']) . "</p>";
                    $backUrl = "productos.php";
                    if ($searchQuery) {
                        $backUrl .= "?" . $searchQuery;
                    }
                    echo '<p class="volver"><a href="' . $backUrl . '">Volver a la búsqueda</a></p>';
                    echo '</div>';
                } else {
                    echo "<p>No se encontr el producto solicitado.</p>";
                }
            }
            // Si se han enviado parámetros de búsqueda (y no se está mostrando un producto concreto)
            elseif ((isset($_GET['codigo']) && $_GET['codigo'] !== '') || (isset($_GET['descripcion']) && $_GET['descripcion'] !== '') || (isset($_GET['marca']) && $_GET['marca'] !== '')) {
                $codigo      = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
                $descripcion = isset($_GET['descripcion']) ? trim($_GET['descripcion']) : '';
                $marca       = isset($_GET['marca']) ? trim($_GET['marca']) : '';
                
                $resultados = buscarProductosCompuesta($conn, $codigo, $descripcion, $marca);
                
                echo "<h2>Resultados de la Búsqueda</h2>";
                if (!empty($resultados)) {
                    echo "<table>";
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th>Código</th>";
                    echo "<th>Descripción</th>";
                    echo "<th>Marca</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    foreach ($resultados as $prod) {
                        $url = "productos.php?cod_articulo=" . urlencode($prod['cod_articulo']);
                        if ($searchQuery) {
                            $url .= "&" . $searchQuery;
                        }
                        echo "<tr class='clickable' onclick=\"window.location.href='" . $url . "'\">";
                        echo "<td>" . htmlspecialchars($prod['cod_articulo']) . "</td>";
                        echo "<td>" . htmlspecialchars($prod['descripcion']) . "</td>";
                        echo "<td>" . (isset($prod['marca']) && trim($prod['marca']) !== '' ? htmlspecialchars($prod['marca']) : 'Sin marca rellenada') . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody>";
                    echo "</table>";
                } else {
                    echo "<p>No se encontraron productos que coincidan con la búsqueda.</p>";
                }
            }
        ?>
        </section>
    </main>
</body>
</html>
