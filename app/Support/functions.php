<?php
/**
 * CORE FUNCTIONS (nueva ubicación SaaS)
 * Este archivo será la fuente principal en futuras fases.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* Funciones generales */

if (!function_exists('tieneValor')) {
    function tieneValor($value): bool {
        return $value !== null && $value !== '';
    }
}

/**
 * Aclara un color hexadecimal en un porcentaje dado.
 */
if (!function_exists('lighten_color')) {
    function lighten_color(string $hex, int $percent = 50): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2)
                 . str_repeat(substr($hex, 1, 1), 2)
                 . str_repeat(substr($hex, 2, 1), 2);
        }
        $num = hexdec($hex);
        $r = ($num >> 16) & 0xFF;
        $g = ($num >> 8) & 0xFF;
        $b = $num & 0xFF;
        $r = min(255, round($r + (255 - $r) * ($percent / 100)));
        $g = min(255, round($g + (255 - $g) * ($percent / 100)));
        $b = min(255, round($b + (255 - $b) * ($percent / 100)));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

/**
 * Compara el valor 'total' de dos arrays para ordenar grupos.
 */
if (!function_exists('cmpGrupo')) {
    function cmpGrupo(array $a, array $b): int {
        if ($a['total'] == $b['total']) {
            return 0;
        }
        return ($a['total'] < $b['total']) ? 1 : -1;
    }
}

/**
 * Compara el valor 'start' de dos arrays.
 */
if (!function_exists('cmpStart')) {
    function cmpStart(array $a, array $b): int {
        return strcmp($a['start'], $b['start']);
    }
}

/**
 * Convierte una cadena al encoding UTF-8.
 */
function toUTF8($string) {
    // Verifica si la cadena ya est en UTF-8
    if (mb_detect_encoding($string, 'UTF-8', true) === 'UTF-8') {
        return $string;
    }
    // Si no, la convierte (en este ejemplo desde Windows-1252 a UTF-8)
    return mb_convert_encoding($string, 'UTF-8', 'Windows-1252');
}

if (!function_exists('odbc_fetch_array_utf8')) {
    function odbc_fetch_array_utf8($rs): array|false {
        $row = odbc_fetch_array($rs);
        if (!$row) {
            return false;
        }

        foreach ($row as $k => $v) {
            if (is_string($v) && $v !== '') {
                if (!mb_check_encoding($v, 'UTF-8')) {
                    $row[$k] = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
                }
            }
        }

        return $row;
    }
}

/**
 * Devuelve una condición SQL según el código del vendedor.
 */
if (!function_exists('condicionVendedor')) {
    function condicionVendedor($codigo_vendedor): string {
        if (is_null($codigo_vendedor)) {
            return "1=1";
        } else {
            return "cli.cod_vendedor = " . intval($codigo_vendedor);
        }
    }
}

/**
 * Genera una subconsulta SQL para obtener el importe anual.
 */
if (!function_exists('subConsultaImporteAnual')) {
    function subConsultaImporteAnual($alias, $year): string {
        $subsql = "
        (
          SELECT COALESCE(SUM(sub.importe),0)
          FROM
          (
            SELECT l.importe, v.cod_cliente
            FROM hist_ventas_cabecera v
            JOIN hist_ventas_linea l
              ON v.cod_venta = l.cod_venta
             AND v.tipo_venta = l.tipo_venta
            WHERE YEAR(v.fecha_venta) = $year
              AND v.tipo_venta = 2
            UNION ALL
            SELECT mo.importe, mo.codigo AS cod_cliente
            FROM cmf_movimientos_ofipro mo
            WHERE YEAR(mo.fecha) = $year
          ) AS sub
          WHERE sub.cod_cliente = cli.cod_cliente
        ) AS $alias
        ";
        return $subsql;
    }
}

/**
 * Devuelve un icono de medalla según la posición.
 */
if (!function_exists('iconoMedalla')) {
    function iconoMedalla($pos): string {
        if ($pos == 1) {
            return '<i class="fas fa-medal" style="color:gold;"></i> ';
        } elseif ($pos == 2) {
            return '<i class="fas fa-medal" style="color:silver;"></i> ';
        } elseif ($pos == 3) {
            return '<i class="fas fa-medal" style="color:#cd7f32;"></i> ';
        }
        return '';
    }
}

/**
 * Compara dos clientes para fines de ranking.
 */
if (!function_exists('compararClientes')) {
    function compararClientes($a, $b): int {
        global $columnaRanking;
        $valA = floatval($a[$columnaRanking]);
        $valB = floatval($b[$columnaRanking]);
        if ($valB > $valA) {
            return 1;
        } elseif ($valB < $valA) {
            return -1;
        }
        return 0;
    }
}

/**
 * Calcula el ranking de clientes segn una columna.
 */
if (!function_exists('rankingPorAnio')) {
    function rankingPorAnio($arrayClientes, $columna): array {
        $copia = $arrayClientes;
        global $columnaRanking;
        $columnaRanking = $columna;
        usort($copia, 'compararClientes');
        $ranking = [];
        $i = 0;
        foreach ($copia as $fila) {
            $i++;
            $ranking[$fila['cod_cliente']] = $i;
        }
        return $ranking;
    }
}

/**
 * Devuelve el da de la semana en espaol para una fecha.
 */
if (!function_exists('obtenerDiaSemana')) {
    function obtenerDiaSemana(string $fecha): string {
        $dias = [
            'Sunday'    => 'Domingo',
            'Monday'    => 'Lunes',
            'Tuesday'   => 'Martes',
            'Wednesday' => 'Mircoles',
            'Thursday'  => 'Jueves',
            'Friday'    => 'Viernes',
            'Saturday'  => 'Sbado'
        ];
        $diaIngles = date('l', strtotime($fecha));
        return isset($dias[$diaIngles]) ? $dias[$diaIngles] : '';
    }
}
require_once __DIR__ . '/HorariosVisitasSupport.php';

require_once __DIR__ . '/VisitasSupport.php';

/* Funciones para manejo de fechas y slider */

/**
 * Valida que una fecha tenga el formato 'YYYY-MM-DD' y sea vlida.
 */
if (!function_exists('validarFechaSQL')) {
    function validarFechaSQL(string $fecha): bool {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            [$anio, $mes, $dia] = explode('-', $fecha);
            return checkdate((int)$mes, (int)$dia, (int)$anio);
        }
        return false;
    }
}

require_once __DIR__ . '/FechasHistoricoSupport.php';


/**
 * Realiza una búsqueda compuesta de productos usando código, descripción y marca.
 * Para la marca se utiliza la tabla web_marcas, relaciónándola mediante a.cod_marca_web = wm.cod_marca.
 *
 * @param resource $conn Conexión ODBC.
 * @param string $codigo Código de Artículo o parte del mismo, o referencia alternativa.
 * @param string $descripcion Descripción a buscar (se dividirá en palabras).
 * @param string $marca Código de la marca a buscar. Si es "NULL", se filtran los productos sin marca.
 * @return array Array de productos que coinciden con los criterios.
 */
function buscarProductosCompuesta($conn, string $codigo, string $descripcion, string $marca): array {
    $params = [];
    $conditions = [];
    
    // Condición para el código:
    // - En artículos: se compara el inicio del código mediante SUBSTRING
    // - En multicódigo: se busca el código completo (igualdad)
    if ($codigo !== '') {
        $codeLen = strlen($codigo);
        $conditions[] = "(SUBSTRING(a.cod_articulo, 1, $codeLen) = ? OR mca.codigo = ?)";
        $params[] = $codigo;
        $params[] = $codigo;
    }
    
    // Condición para la descripción: cada palabra se aplica con LIKE.
    // Si se ha seleccionado "Sin marca rellenada" (marca==='NULL'),
    // se permite que la descripción sea NULL para que no se descarte el Artículo.
    if ($descripcion !== '') {
        $palabras = preg_split('/\s+/', $descripcion, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($palabras as $palabra) {
            if ($marca === 'NULL') {
                $conditions[] = "(ad.descripcion LIKE ? OR ad.descripcion IS NULL)";
                $params[] = '%' . $palabra . '%';
            } else {
                $conditions[] = "ad.descripcion LIKE ?";
                $params[] = '%' . $palabra . '%';
            }
        }
    }
    
    // Condición para la marca:
    // Si el parámetro es "NULL", filtrar los productos sin marca (a.cod_marca_web IS NULL)
    if ($marca !== '') {
        if ($marca === 'NULL') {
            $conditions[] = "a.cod_marca_web IS NULL";
        } else {
            $conditions[] = "wm.cod_marca = ?";
            $params[] = $marca;
        }
    }
    
    $sql = "SELECT DISTINCT a.cod_articulo, wm.descripcion AS marca, ad.descripcion AS descripcion
            FROM [integral].[dbo].[articulos] a
            LEFT JOIN [integral].[dbo].[articulo_descripcion] ad 
                ON a.cod_articulo = ad.cod_articulo AND ad.cod_idioma = 'ES'
            LEFT JOIN [integral].[dbo].[multicodigo_articulo] mca 
                ON a.cod_articulo = mca.cod_articulo
            LEFT JOIN [integral].[dbo].[web_marcas] wm
                ON a.cod_marca_web = wm.cod_marca";
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    $exec = odbc_execute($stmt, $params);
    if (!$exec) {
        return [];
    }
    
    $resultados = [];
    while ($row = odbc_fetch_array_utf8($stmt)) {
        $resultados[] = $row;
    }
    
    return $resultados;
}



/**
 * Obtiene los detalles completos de un producto a partir de su código.
 * Se incluye la descripción del producto y la marca proveniente de web_marcas.
 *
 * @param resource $conn Conexión ODBC.
 * @param string $cod_articulo Código del producto.
 * @return array|null Array asociativo con los datos del producto o null si no se encuentra.
 */
function obtenerProducto($conn, string $cod_articulo): ?array {
    $sql = "SELECT a.*, ad.descripcion AS descripcion_articulo, wm.descripcion AS marca
            FROM [integral].[dbo].[articulos] a
            LEFT JOIN [integral].[dbo].[articulo_descripcion] ad 
                ON a.cod_articulo = ad.cod_articulo AND ad.cod_idioma = 'ES'
            LEFT JOIN [integral].[dbo].[web_marcas] wm 
                ON a.cod_marca_web = wm.cod_marca
            WHERE a.cod_articulo = ?";
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    $exec = odbc_execute($stmt, [$cod_articulo]);
    if (!$exec) {
        return null;
    }
    $producto = odbc_fetch_array_utf8($stmt);
    return $producto ? $producto : null;
}

if (!function_exists('obtenerConfiguracionApp')) {
    function obtenerConfiguracionApp($conn): array {
        $config = [
            'nombre_sistema' => 'COMAFERR',
            'color_primary' => '#2563eb',
            'logo_path' => '/imagenes/logo.png',
        ];

        if (!$conn) {
            return $config;
        }

        $sql = "SELECT clave, valor FROM cmf_configuracion_app";
        $res = @odbc_exec($conn, $sql);
        if (!$res) {
            return $config;
        }

        while ($row = odbc_fetch_array($res)) {
            $clave = isset($row['clave']) ? trim((string)$row['clave']) : '';
            if ($clave === '' || !array_key_exists($clave, $config)) {
                continue;
            }

            $valor = isset($row['valor']) ? trim((string)$row['valor']) : '';
            if ($valor !== '') {
                $config[$clave] = $valor;
            }
        }

        return $config;
    }
}
