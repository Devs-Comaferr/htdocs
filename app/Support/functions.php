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

if (!function_exists('normalizarEstadoVisita')) {
    function normalizarEstadoVisita(?string $estado_visita): string {
        $estado = strtolower(trim((string)$estado_visita));

        switch ($estado) {
            case 'realizada':
                return 'Realizada';
            case 'no atendida':
                return 'No atendida';
            case 'pendiente':
                return 'Pendiente';
            case 'planificada':
                return 'Planificada';
            case 'descartada':
                return 'Descartada';
            default:
                return trim((string)$estado_visita);
        }
    }
}

/**
 * Determina el color de una visita segn su estado y origen.
 */

if (!function_exists('normalizarEstadoVisitaClave')) {
    function normalizarEstadoVisitaClave(?string $estado_visita): string {
        return strtolower(normalizarEstadoVisita($estado_visita));
    }
}

if (!function_exists('estadoVisitaEsRealizada')) {
    function estadoVisitaEsRealizada(?string $estado_visita): bool {
        return normalizarEstadoVisitaClave($estado_visita) === 'realizada';
    }
}

if (!function_exists('estadoVisitaRequiereFranja')) {
    function estadoVisitaRequiereFranja(?string $estado_visita): bool {
        return !estadoVisitaEsRealizada($estado_visita);
    }
}
if (!function_exists('determinarColorVisita')) {
    function determinarColorVisita(string $estado_visita, string $origen): string {
        $estado_visita = normalizarEstadoVisitaClave($estado_visita);
        $origen = strtolower($origen);
        $color = '#6c757d';
        if ($estado_visita == 'realizada') {
            switch ($origen) {
                case 'pedido web':
                    $color = '#af8641';
                    break;
                case 'teléfono':
                case 'telefono':
                    $color = '#13ba8a';
                    break;
                case 'visita':
                    $color = '#007723';
                    break;
                case 'whatsapp':
                    $color = '#25D366';
                    break;
                case 'email':
                    $color = '#0072C6';
                    break;
                default:
                    $color = '#6c757d';
                    break;
            }
        } elseif ($estado_visita == 'pendiente') {
            $color = '#ffc107';
        } elseif ($estado_visita == 'planificada') {
            $color = '#007bff';
        } elseif ($estado_visita == 'no atendida') {
            $color = '#e65414';
        } elseif ($estado_visita == 'descartada') {
            $color = '#6c757d';
        }
        return $color;
    }
}

/**
 * Determina el color de un pedido según su origen.
 */
if (!function_exists('determinarColorPedido')) {
    function determinarColorPedido(string $origen): string {
        $origen = strtolower($origen);
        $color = '#6c757d';
        switch ($origen) {
            case 'pedido web':
                $color = '#af8641';
                break;
            case 'teléfono':
            case 'telefono':
                $color = '#13ba8a';
                break;
            case 'visita':
                $color = '#007723';
                break;
            case 'whatsapp':
                $color = '#25D366';
                break;
            case 'email':
                $color = '#0072C6';
                break;
            default:
                $color = '#6c757d';
                break;
        }
        return $color;
    }
}

/**
 * Devuelve un icono representativo según el origen.
 */
if (!function_exists('iconoDeOrigen')) {
    function iconoDeOrigen(string $origen): string {
        $origen = strtolower($origen);
        switch ($origen) {
            case 'teléfono':
            case 'telefono':
                return '<i class="fa-solid fa-phone"></i>';
            case 'visita':
                return '<i class="fa-solid fa-calendar-check"></i>';
            case 'whatsapp':
                return '<i class="fa-brands fa-whatsapp"></i>';
            case 'email':
                return '<i class="fa-solid fa-envelope"></i>';
            default:
                return '<i class="fa-solid fa-info-circle"></i>';
        }
    }
}

/**
 * Recalcula y persiste tiempo_promedio_visita para cliente/seccion.
 * Se basa solo en visitas realizadas con origen = 'visita'.
 * Retorna el promedio en minutos.
 */
if (!function_exists('obtenerHorarioClienteValorFila')) {
    function obtenerHorarioClienteValorFila(array $fila, array $claves, $default = null) {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $fila) && $fila[$clave] !== null) {
                return $fila[$clave];
            }
        }

        return $default;
    }
}

if (!function_exists('normalizarHoraHorarioCliente')) {
    function normalizarHoraHorarioCliente($hora): ?string {
        if ($hora === null) {
            return null;
        }

        $hora = trim((string)$hora);
        if ($hora === '') {
            return null;
        }

        return strlen($hora) >= 5 ? substr($hora, 0, 5) : $hora;
    }
}

if (!function_exists('construirHorarioClienteDesdeFila')) {
    function construirHorarioClienteDesdeFila(array $fila, string $origen): array {
        $preferencia = obtenerHorarioClienteValorFila($fila, [
            'preferencia',
            'PREFERENCIA',
            'preferencia_horaria',
            'PREFERENCIA_HORARIA'
        ]);

        $descripcion = obtenerHorarioClienteValorFila($fila, ['descripcion', 'DESCRIPCION']);
        $idHorarioEspecial = obtenerHorarioClienteValorFila($fila, ['id_horario_especial', 'ID_HORARIO_ESPECIAL']);

        return [
            'origen' => $origen,
            'manana_inicio' => normalizarHoraHorarioCliente(obtenerHorarioClienteValorFila($fila, ['hora_inicio_manana', 'HORA_INICIO_MANANA'])),
            'manana_fin' => normalizarHoraHorarioCliente(obtenerHorarioClienteValorFila($fila, ['hora_fin_manana', 'HORA_FIN_MANANA'])),
            'tarde_inicio' => normalizarHoraHorarioCliente(obtenerHorarioClienteValorFila($fila, ['hora_inicio_tarde', 'HORA_INICIO_TARDE'])),
            'tarde_fin' => normalizarHoraHorarioCliente(obtenerHorarioClienteValorFila($fila, ['hora_fin_tarde', 'HORA_FIN_TARDE'])),
            'preferencia' => $preferencia !== null ? trim((string)$preferencia) : null,
            'id_horario_especial' => $origen === 'especial' && $idHorarioEspecial !== null && $idHorarioEspecial !== ''
                ? (int)$idHorarioEspecial
                : null,
            'descripcion' => $origen === 'especial' && $descripcion !== null ? trim((string)$descripcion) : null,
        ];
    }
}

if (!function_exists('obtenerHorarioCliente')) {
    /**
     * Devuelve el horario aplicable de cliente + seccion en una fecha concreta.
     * Prioridad:
     * 1) horario especial mas especifico (rango mas corto)
     * 2) horario base de asignacion
     * 3) horario por defecto
     */
    function obtenerHorarioCliente($cod_cliente, $cod_seccion, string $fecha): array {
        $conn = function_exists('db') ? db() : null;

        $cod_cliente = (int)$cod_cliente;
        $cod_seccion = ($cod_seccion === null || $cod_seccion === '') ? null : (int)$cod_seccion;
        $fecha = trim($fecha);

        if ($cod_cliente <= 0) {
            throw new InvalidArgumentException('cod_cliente invalido en obtenerHorarioCliente().');
        }

        if (!validarFechaSQL($fecha)) {
            throw new InvalidArgumentException('fecha invalida en obtenerHorarioCliente(). Formato esperado: YYYY-MM-DD.');
        }

        if (!$conn) {
            throw new RuntimeException('No hay conexion ODBC disponible en obtenerHorarioCliente().');
        }

        $horarioDefault = [
            'origen' => 'default',
            'manana_inicio' => '09:00',
            'manana_fin' => '14:00',
            'tarde_inicio' => '17:00',
            'tarde_fin' => '20:00',
            'preferencia' => null,
            'id_horario_especial' => null,
            'descripcion' => null,
        ];

        $whereSeccion = 'cod_seccion = ?';
        $paramsSeccion = [];
        if ($cod_seccion === null) {
            $whereSeccion = "(cod_seccion IS NULL OR cod_seccion = '')";
        } else {
            $paramsSeccion[] = $cod_seccion;
        }

        $sqlEspecial = "
            SELECT *
            FROM [integral].[dbo].[cmf_comerciales_clientes_horario_especial]
            WHERE cod_cliente = ?
              AND $whereSeccion
              AND activo = 1
              AND ? BETWEEN fecha_inicio AND fecha_fin
            ORDER BY DATEDIFF(day, fecha_inicio, fecha_fin) ASC,
                     fecha_inicio DESC,
                     id_horario_especial DESC
        ";
        $stmtEspecial = odbc_prepare($conn, $sqlEspecial);
        if (!$stmtEspecial) {
            throw new RuntimeException('Error al preparar horario especial: ' . (odbc_errormsg($conn) ?: odbc_errormsg()));
        }

        $paramsEspecial = array_merge([$cod_cliente], $paramsSeccion, [$fecha]);
        if (!odbc_execute($stmtEspecial, $paramsEspecial)) {
            throw new RuntimeException('Error al ejecutar horario especial: ' . (odbc_errormsg($conn) ?: odbc_errormsg()));
        }

        $horarioEspecial = odbc_fetch_array_utf8($stmtEspecial);
        if ($horarioEspecial) {
            return construirHorarioClienteDesdeFila($horarioEspecial, 'especial');
        }

        $sqlBase = "
            SELECT TOP 1 *
            FROM [integral].[dbo].[cmf_asignacion_zonas_clientes]
            WHERE cod_cliente = ?
              AND $whereSeccion
              AND activo = 1
        ";
        $stmtBase = odbc_prepare($conn, $sqlBase);
        if (!$stmtBase) {
            throw new RuntimeException('Error al preparar horario base: ' . (odbc_errormsg($conn) ?: odbc_errormsg()));
        }

        $paramsBase = array_merge([$cod_cliente], $paramsSeccion);
        if (!odbc_execute($stmtBase, $paramsBase)) {
            throw new RuntimeException('Error al ejecutar horario base: ' . (odbc_errormsg($conn) ?: odbc_errormsg()));
        }

        $horarioBase = odbc_fetch_array_utf8($stmtBase);
        if ($horarioBase) {
            return construirHorarioClienteDesdeFila($horarioBase, 'base');
        }

        return $horarioDefault;
    }
}

if (!function_exists('recalcularTiempoPromedioVisita')) {
    function recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion = null): float {
        $cod_cliente = (int)$cod_cliente;
        if ($cod_cliente <= 0) {
            throw new Exception('Cod cliente inválido para recalcular promedio.');
        }

        $sinSeccion = is_null($cod_seccion) || $cod_seccion === '';

        if ($sinSeccion) {
            $sqlPromedio = "
                SELECT AVG(DATEDIFF(minute, v.hora_inicio_visita, v.hora_fin_visita)) AS promedio
                FROM [integral].[dbo].[cmf_visitas_comerciales] v
                INNER JOIN [integral].[dbo].[cmf_visita_pedidos] p ON v.id_visita = p.id_visita
                WHERE v.cod_cliente = ?
                  AND (v.cod_seccion IS NULL OR v.cod_seccion = '')
                  AND LOWER(v.estado_visita) = 'realizada'
                  AND LOWER(p.origen) = 'visita'
            ";
            $stmtPromedio = odbc_prepare($conn, $sqlPromedio);
            if (!$stmtPromedio) {
                throw new Exception('Error al preparar promedio de visita: ' . odbc_errormsg($conn));
            }
            if (!odbc_execute($stmtPromedio, [$cod_cliente])) {
                throw new Exception('Error al ejecutar promedio de visita: ' . odbc_errormsg($conn));
            }

            $row = odbc_fetch_array_utf8($stmtPromedio);
            $promedioMin = $row && $row['promedio'] !== null ? (float)$row['promedio'] : 0.0;
            $promedioHoras = $promedioMin > 0 ? ($promedioMin / 60.0) : 0.0;

            $sqlUpdate = "
                UPDATE [integral].[dbo].[cmf_asignacion_zonas_clientes]
                SET tiempo_promedio_visita = ?
                WHERE cod_cliente = ?
                  AND (cod_seccion IS NULL OR cod_seccion = '')
            ";
            $stmtUpdate = odbc_prepare($conn, $sqlUpdate);
            if (!$stmtUpdate) {
                throw new Exception('Error al preparar actualizacion de promedio: ' . odbc_errormsg($conn));
            }
            if (!odbc_execute($stmtUpdate, [$promedioHoras, $cod_cliente])) {
                throw new Exception('Error al actualizar promedio: ' . odbc_errormsg($conn));
            }
        } else {
            $cod_seccion = (int)$cod_seccion; // 0 es valido.

            $sqlPromedio = "
                SELECT AVG(DATEDIFF(minute, v.hora_inicio_visita, v.hora_fin_visita)) AS promedio
                FROM [integral].[dbo].[cmf_visitas_comerciales] v
                INNER JOIN [integral].[dbo].[cmf_visita_pedidos] p ON v.id_visita = p.id_visita
                WHERE v.cod_cliente = ?
                  AND v.cod_seccion = ?
                  AND LOWER(v.estado_visita) = 'realizada'
                  AND LOWER(p.origen) = 'visita'
            ";
            $stmtPromedio = odbc_prepare($conn, $sqlPromedio);
            if (!$stmtPromedio) {
                throw new Exception('Error al preparar promedio de visita: ' . odbc_errormsg($conn));
            }
            if (!odbc_execute($stmtPromedio, [$cod_cliente, $cod_seccion])) {
                throw new Exception('Error al ejecutar promedio de visita: ' . odbc_errormsg($conn));
            }

            $row = odbc_fetch_array_utf8($stmtPromedio);
            $promedioMin = $row && $row['promedio'] !== null ? (float)$row['promedio'] : 0.0;
            $promedioHoras = $promedioMin > 0 ? ($promedioMin / 60.0) : 0.0;

            $sqlUpdate = "
                UPDATE [integral].[dbo].[cmf_asignacion_zonas_clientes]
                SET tiempo_promedio_visita = ?
                WHERE cod_cliente = ?
                  AND cod_seccion = ?
            ";
            $stmtUpdate = odbc_prepare($conn, $sqlUpdate);
            if (!$stmtUpdate) {
                throw new Exception('Error al preparar actualizacion de promedio: ' . odbc_errormsg($conn));
            }
            if (!odbc_execute($stmtUpdate, [$promedioHoras, $cod_cliente, $cod_seccion])) {
                throw new Exception('Error al actualizar promedio: ' . odbc_errormsg($conn));
            }
        }

        return $promedioMin;
    }
}

/**
 * Normaliza el origen al formato que se guarda en BD.
 */
if (!function_exists('normalizarOrigenPedidoDb')) {
    function normalizarOrigenPedidoDb(string $origen): string {
        $lc = strtolower(trim($origen));
        if ($lc === 'visita') {
            return 'Visita';
        }
        if ($lc === 'teléfono' || $lc === 'telefono') {
            return 'Telefono';
        }
        if ($lc === 'whatsapp') {
            return 'WhatsApp';
        }
        if ($lc === 'email') {
            return 'Email';
        }
        return $origen;
    }
}

/**
 * Crea una visita realizada y devuelve su id_visita.
 */
if (!function_exists('crearVisitaRealizada')) {
    function crearVisitaRealizada(
        $conn,
        int $cod_cliente,
        $cod_seccion,
        int $cod_vendedor,
        string $fecha_visita,
        string $hora_inicio_visita,
        ?string $hora_fin_visita = null,
        ?string $observaciones = null
    ): int {
        if ($cod_cliente <= 0 || $cod_vendedor <= 0 || $fecha_visita === '' || $hora_inicio_visita === '') {
            throw new Exception('Datos insuficientes para crear visita.');
        }

        $normalizarHora = static function (?string $h): ?string {
            if ($h === null || trim($h) === '') {
                return null;
            }
            $h = trim($h);
            if (strlen($h) >= 5) {
                return substr($h, 0, 5);
            }
            return $h;
        };
        $normalizarFecha = static function (string $f): string {
            $f = trim($f);
            if ($f === '') {
                return date('Y-m-d');
            }
            if (strlen($f) >= 10) {
                return substr($f, 0, 10);
            }
            return $f;
        };

        $fechaVisitaNorm = $normalizarFecha($fecha_visita);
        $horaInicioNorm = $normalizarHora($hora_inicio_visita) ?? '00:00';
        $horaFinNorm = $normalizarHora($hora_fin_visita ?: $hora_inicio_visita) ?? $horaInicioNorm;

        $sqlInsVisita = "
            INSERT INTO [integral].[dbo].[cmf_visitas_comerciales]
                (cod_cliente, cod_seccion, cod_vendedor, fecha_visita, hora_inicio_visita, hora_fin_visita, observaciones, estado_visita)
            OUTPUT INSERTED.id_visita
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmtInsVisita = odbc_prepare($conn, $sqlInsVisita);
        if (!$stmtInsVisita) {
            throw new Exception('Error al preparar insercion de visita: ' . odbc_errormsg($conn));
        }

        $okInsVisita = odbc_execute($stmtInsVisita, [
            $cod_cliente,
            $cod_seccion,
            $cod_vendedor,
            $fechaVisitaNorm,
            $horaInicioNorm,
            $horaFinNorm,
            $observaciones,
            'Realizada'
        ]);
        if (!$okInsVisita) {
            throw new Exception('Error al crear visita: ' . odbc_errormsg($conn));
        }

        $idRow = odbc_fetch_array_utf8($stmtInsVisita);
        $id_visita = $idRow ? (int)($idRow['id_visita'] ?? $idRow['ID_VISITA'] ?? 0) : 0;
        if ($id_visita <= 0) {
            throw new Exception('ID de visita inválido al crear asignación.');
        }
        return $id_visita;
    }
}

/**
 * Inserta o actualiza la relación visita-pedido.
 */
if (!function_exists('upsertRelacionVisitaPedido')) {
    function upsertRelacionVisitaPedido($conn, int $id_visita, int $cod_venta, string $origen): void {
        if ($id_visita <= 0 || $cod_venta <= 0) {
            throw new Exception('Datos inválidos para relación visita-pedido.');
        }

        $origenDb = normalizarOrigenPedidoDb($origen);
        $sqlRel = "SELECT TOP 1 id_visita_pedido FROM [integral].[dbo].[cmf_visita_pedidos] WHERE cod_venta = ?";
        $stmtRel = odbc_prepare($conn, $sqlRel);
        if (!$stmtRel) {
            throw new Exception('Error al preparar consulta de relación visita-pedido: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtRel, [$cod_venta])) {
            throw new Exception('Error al consultar relación visita-pedido: ' . odbc_errormsg($conn));
        }
        $rel = odbc_fetch_array_utf8($stmtRel);

        if ($rel) {
            $sqlUpd = "UPDATE [integral].[dbo].[cmf_visita_pedidos] SET id_visita = ?, origen = ? WHERE cod_venta = ?";
            $stmtUpd = odbc_prepare($conn, $sqlUpd);
            if (!$stmtUpd) {
                throw new Exception('Error al preparar update visita-pedido: ' . odbc_errormsg($conn));
            }
            if (!odbc_execute($stmtUpd, [$id_visita, $origenDb, $cod_venta])) {
                throw new Exception('Error al actualizar relación visita-pedido: ' . odbc_errormsg($conn));
            }
            return;
        }

        $sqlIns = "INSERT INTO [integral].[dbo].[cmf_visita_pedidos] (id_visita, cod_venta, origen) VALUES (?, ?, ?)";
        $stmtIns = odbc_prepare($conn, $sqlIns);
        if (!$stmtIns) {
            throw new Exception('Error al preparar insercion visita-pedido: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtIns, [$id_visita, $cod_venta, $origenDb])) {
            throw new Exception('Error al insertar relación visita-pedido: ' . odbc_errormsg($conn));
        }
    }
}

/**
 * Asegura que exista relación visita-pedido y aplica origen.
 */
if (!function_exists('asegurarRelacionVisitaPedido')) {
    function asegurarRelacionVisitaPedido($conn, int $cod_venta, string $origen, array $opciones = []): array {
        if ($cod_venta <= 0) {
            throw new Exception('Cod venta inválido.');
        }
        $origenDb = normalizarOrigenPedidoDb($origen);

        $sqlCtx = "
            SELECT TOP 1 vp.id_visita, vp.origen, vc.cod_cliente, vc.cod_seccion
            FROM [integral].[dbo].[cmf_visita_pedidos] vp
            INNER JOIN [integral].[dbo].[cmf_visitas_comerciales] vc ON vc.id_visita = vp.id_visita
            WHERE vp.cod_venta = ?
            ORDER BY vp.id_visita_pedido ASC
        ";
        $stmtCtx = odbc_prepare($conn, $sqlCtx);
        if (!$stmtCtx) {
            throw new Exception('Error al preparar contexto de visita-pedido: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtCtx, [$cod_venta])) {
            throw new Exception('Error al obtener contexto de visita-pedido: ' . odbc_errormsg($conn));
        }
        $ctx = odbc_fetch_array_utf8($stmtCtx);

        $origenAnterior = '';
        $codCliente = 0;
        $codSeccion = null;
        $idVisita = 0;
        $relaciónCreada = false;

        if ($ctx) {
            $idVisita = (int)($ctx['id_visita'] ?? $ctx['ID_VISITA'] ?? 0);
            $origenAnterior = strtolower(trim((string)($ctx['origen'] ?? $ctx['ORIGEN'] ?? '')));
            $codCliente = (int)($ctx['cod_cliente'] ?? $ctx['COD_CLIENTE'] ?? 0);
            $rawSeccion = $ctx['cod_seccion'] ?? $ctx['COD_SECCION'] ?? null;
            $codSeccion = ($rawSeccion === null || $rawSeccion === '') ? null : (int)$rawSeccion;
            upsertRelacionVisitaPedido($conn, $idVisita, $cod_venta, $origenDb);
        } else {
            $idVisitaOpcion = isset($opciones['id_visita']) ? (int)$opciones['id_visita'] : 0;
            if ($idVisitaOpcion > 0) {
                $idVisita = $idVisitaOpcion;
                $codCliente = (int)($opciones['cod_cliente'] ?? 0);
                $rawSeccion = $opciones['cod_seccion'] ?? null;
                $codSeccion = ($rawSeccion === null || $rawSeccion === '') ? null : (int)$rawSeccion;
                upsertRelacionVisitaPedido($conn, $idVisita, $cod_venta, $origenDb);
                $relaciónCreada = true;
            } else {
                $sqlCab = "
                    SELECT TOP 1 cod_cliente, cod_seccion, cod_comisionista, fecha_venta, hora_venta
                    FROM [integral].[dbo].[hist_ventas_cabecera]
                    WHERE cod_venta = ? AND tipo_venta = 1
                ";
                $stmtCab = odbc_prepare($conn, $sqlCab);
                if (!$stmtCab) {
                    throw new Exception('Error al preparar cabecera de pedido: ' . odbc_errormsg($conn));
                }
                if (!odbc_execute($stmtCab, [$cod_venta])) {
                    throw new Exception('Error al obtener cabecera de pedido: ' . odbc_errormsg($conn));
                }
                $cab = odbc_fetch_array_utf8($stmtCab);
                if (!$cab) {
                    throw new Exception('No se encontro cabecera para ese pedido.');
                }

                $codCliente = (int)($opciones['cod_cliente'] ?? ($cab['cod_cliente'] ?? $cab['COD_CLIENTE'] ?? 0));
                $rawSeccion = array_key_exists('cod_seccion', $opciones)
                    ? $opciones['cod_seccion']
                    : ($cab['cod_seccion'] ?? $cab['COD_SECCION'] ?? null);
                $codSeccion = ($rawSeccion === null || $rawSeccion === '') ? null : (int)$rawSeccion;
                $codVendedor = (int)($opciones['cod_vendedor'] ?? ($cab['cod_comisionista'] ?? $cab['COD_COMISIONISTA'] ?? 0));
                $fechaVisita = (string)($opciones['fecha_visita'] ?? ($cab['fecha_venta'] ?? $cab['FECHA_VENTA'] ?? date('Y-m-d')));
                $horaInicio = (string)($opciones['hora_inicio_visita'] ?? ($cab['hora_venta'] ?? $cab['HORA_VENTA'] ?? date('H:i:s')));
                $horaFin = (string)($opciones['hora_fin_visita'] ?? $horaInicio);
                $observaciones = array_key_exists('observaciones', $opciones)
                    ? (string)$opciones['observaciones']
                    : null;

                $idVisita = crearVisitaRealizada(
                    $conn,
                    $codCliente,
                    $codSeccion,
                    $codVendedor,
                    $fechaVisita,
                    $horaInicio,
                    $horaFin,
                    $observaciones
                );
                upsertRelacionVisitaPedido($conn, $idVisita, $cod_venta, $origenDb);
                $relaciónCreada = true;
            }
        }

        return [
            'id_visita' => $idVisita,
            'cod_cliente' => $codCliente,
            'cod_seccion' => $codSeccion,
            'origen_anterior' => $origenAnterior,
            'origen_nuevo' => strtolower($origenDb),
            'relación_creada' => $relaciónCreada
        ];
    }
}

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

/**
 * Función para formatear una fecha en "Mes Año" en español.
 * Si la extensión intl está disponible, se usa IntlDateFormatter; de lo contrario, se usa un mapeo manual.
 *
 * @param string $fecha Fecha en formato compatible (por ejemplo, "2025-02-01")
 * @return string Cadena formateada, por ejemplo: "Febrero 2025"
 */
if (!function_exists('formatearMesAno')) {
    if (!class_exists('IntlDateFormatter')) {
        function formatearMesAno(string $fecha): string {
            try {
                $date = new DateTime($fecha);
            } catch (Exception $e) {
                return $fecha;
            }
            $month = $date->format('F');
            $year = $date->format('Y');
            $meses = [
                'January'   => 'Enero',
                'February'  => 'Febrero',
                'March'     => 'Marzo',
                'April'     => 'Abril',
                'May'       => 'Mayo',
                'June'      => 'Junio',
                'July'      => 'Julio',
                'August'    => 'Agosto',
                'September' => 'Septiembre',
                'October'   => 'Octubre',
                'November'  => 'Noviembre',
                'December'  => 'Diciembre'
            ];
            $mes = $meses[$month] ?? $month;
            return $mes . ' ' . $year;
        }
    } else {
        function formatearMesAno(string $fecha): string {
            try {
                $date = new DateTime($fecha);
            } catch (Exception $e) {
                return $fecha;
            }
            $formatter = new IntlDateFormatter(
                'es_ES',
                IntlDateFormatter::LONG,
                IntlDateFormatter::NONE,
                'Europe/Madrid',
                IntlDateFormatter::GREGORIAN,
                "MMMM yyyy"
            );
            return ucfirst($formatter->format($date));
        }
    }
}

/* FUNCIONES EXPERIMENTALES
if (!function_exists('normalizarArrayUtf8')) {
    function normalizarArrayUtf8(array $fila): array {
        foreach ($fila as $clave => $valor) {
            if (is_string($valor)) {
                $fila[$clave] = toUTF8($valor);
            }
        }
        return $fila;
    }
}

if (!function_exists('getSliderDates')) {
    function getSliderDates(?string $fechaDesde = null, ?string $fechaHasta = null): array {
        if (!$fechaDesde && isset($_GET['fecha_desde']) && validarFechaSQL($_GET['fecha_desde'])) {
            $fechaDesde = $_GET['fecha_desde'];
        }
        if (!$fechaHasta && isset($_GET['fecha_hasta']) && validarFechaSQL($_GET['fecha_hasta'])) {
            $fechaHasta = $_GET['fecha_hasta'];
        }
        if (!$fechaDesde || !$fechaHasta) {
            $fechaHasta = date('Y-m-d');
            $fechaDesde = date('Y-m-d', strtotime('-30 days'));
        }
        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta
        ];
    }
}

if (!function_exists('sqlSliderDateFilter')) {
    function sqlSliderDateFilter(string $campo, ?string $fechaDesde = null, ?string $fechaHasta = null): string {
        if (!$fechaDesde && isset($_GET['fecha_desde']) && validarFechaSQL($_GET['fecha_desde'])) {
            $fechaDesde = $_GET['fecha_desde'];
        }
        if (!$fechaHasta && isset($_GET['fecha_hasta']) && validarFechaSQL($_GET['fecha_hasta'])) {
            $fechaHasta = $_GET['fecha_hasta'];
        }
        if (!$fechaDesde || !$fechaHasta) {
            $fechaHasta = date('Y-m-d');
            $fechaDesde = date('Y-m-d', strtotime('-30 days'));
        }
        return " AND $campo BETWEEN CONVERT(smalldatetime, '" . addslashes($fechaDesde) . "', 120) " .
               "AND CONVERT(smalldatetime, '" . addslashes($fechaHasta) . "', 120) ";
    }
}

if (!function_exists('renderDateSlider')) {
    function renderDateSlider(
        string $sliderId,
        string $startInputId,
        string $endInputId,
        string $displayStartId,
        string $displayEndId,
        string $defaultStart,
        string $defaultEnd,
        string $minDate,
        string $maxDate,
        bool $autoSubmit = true
    ): void {
        ?>
        <div id="<?php echo htmlspecialchars($sliderId); ?>"></div>
        <div id="date-range-values">
            <span id="<?php echo htmlspecialchars($displayStartId); ?>"></span> - <span id="<?php echo htmlspecialchars($displayEndId); ?>"></span>
        </div>
        <input type="hidden" name="<?php echo htmlspecialchars($startInputId); ?>" id="<?php echo htmlspecialchars($startInputId); ?>">
        <input type="hidden" name="<?php echo htmlspecialchars($endInputId); ?>" id="<?php echo htmlspecialchars($endInputId); ?>">
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function parseLocalDate(str) {
                var parts = str.split('-');
                return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), 12, 0, 0);
            }
            var fechaDesdePHP = <?php echo json_encode($defaultStart); ?>;
            var fechaHastaPHP = <?php echo json_encode($defaultEnd); ?>;
            var defaultStart, defaultEnd;
            if (fechaDesdePHP && fechaHastaPHP) {
                defaultStart = parseLocalDate(fechaDesdePHP);
                defaultEnd = parseLocalDate(fechaHastaPHP);
            } else {
                defaultEnd = new Date();
                defaultStart = new Date(defaultEnd.getTime() - (30 * 24 * 60 * 60 * 1000));
            }
            var minDate = parseLocalDate("<?php echo $minDate; ?>");
            var maxDate = parseLocalDate("<?php echo $maxDate; ?>");
            var slider = document.getElementById("<?php echo $sliderId; ?>");
            noUiSlider.create(slider, {
                start: [defaultStart.getTime(), defaultEnd.getTime()],
                connect: true,
                range: {
                    'min': minDate.getTime(),
                    'max': maxDate.getTime()
                },
                step: 24 * 60 * 60 * 1000
            });
            function formatDisplay(date) {
                var dd = String(date.getDate()).padStart(2, '0');
                var mm = String(date.getMonth() + 1).padStart(2, '0');
                var yyyy = date.getFullYear();
                return dd + '/' + mm + '/' + yyyy;
            }
            function formatDateISO(date) {
                var dd = String(date.getDate()).padStart(2, '0');
                var mm = String(date.getMonth() + 1).padStart(2, '0');
                var yyyy = date.getFullYear();
                return yyyy + '-' + mm + '-' + dd;
            }
            var displayStart = document.getElementById("<?php echo $displayStartId; ?>");
            var displayEnd = document.getElementById("<?php echo $displayEndId; ?>");
            var hiddenStart = document.getElementById("<?php echo $startInputId; ?>");
            var hiddenEnd = document.getElementById("<?php echo $endInputId; ?>");
            slider.noUiSlider.on('update', function(values, handle) {
                var rawValues = slider.noUiSlider.get(true);
                var startDateObj = new Date(rawValues[0]);
                var endDateObj = new Date(rawValues[1]);
                displayStart.innerHTML = formatDisplay(startDateObj);
                displayEnd.innerHTML = formatDisplay(endDateObj);
                hiddenStart.value = formatDateISO(startDateObj);
                hiddenEnd.value = formatDateISO(endDateObj);
            });
            <?php if($autoSubmit): ?>
            slider.noUiSlider.on('set', function() {
                document.querySelector('form').submit();
            });
            <?php endif; ?>
        });
        </script>
        <?php
    }
}
*/

/**
 * Construye la clusula SQL para el filtro en el modo "nuevo".
 */
if (!function_exists('construir_filtros_nuevo')) {
    function construir_filtros_nuevo(): string {
        global $fecha_desde, $fecha_hasta, $cod_articulo, $descripcion;
        $filtros = "";
        if ($fecha_desde) {
            $filtros .= " AND fecha_venta >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120)";
        }
        if ($fecha_hasta) {
            $filtros .= " AND fecha_venta <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120)";
        }
        if ($cod_articulo) {
            $filtros .= " AND cod_articulo LIKE '%" . addslashes($cod_articulo) . "%'";
        }
        if ($descripcion) {
            $filtros .= " AND descripcion LIKE '%" . addslashes($descripcion) . "%'";
        }
        return $filtros;
    }
}

/**
 * Construye la clusula SQL para el filtro en el modo "antiguo".
 */
if (!function_exists('construir_filtros_antiguo')) {
    function construir_filtros_antiguo(): string {
        global $fecha_desde, $fecha_hasta, $cod_articulo, $descripcion;
        $f = "";
        if ($fecha_desde) {
            $f .= " AND m.fecha >= CONVERT(smalldatetime, '" . addslashes($fecha_desde) . "', 120)";
        }
        if ($fecha_hasta) {
            $f .= " AND m.fecha <= CONVERT(smalldatetime, '" . addslashes($fecha_hasta) . "', 120)";
        }
        if ($cod_articulo) {
            $f .= " AND m.referencia LIKE '%" . addslashes($cod_articulo) . "%'";
        }
        if ($descripcion) {
            $f .= " AND ad.descripcion LIKE '%" . addslashes($descripcion) . "%'";
        }
        return $f;
    }
}

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
