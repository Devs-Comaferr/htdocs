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
            'Wednesday' => 'Miercoles',
            'Thursday'  => 'Jueves',
            'Friday'    => 'Viernes',
            'Saturday'  => 'Sabado'
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

if (!function_exists('normalizarComparacion')) {
    function normalizarComparacion(?string $texto): string
    {
        $texto = trim((string)$texto);
        if ($texto === '') {
            return '';
        }

        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }

        $texto = mb_strtoupper($texto, 'UTF-8');
        return strtr($texto, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
            'À' => 'A',
            'È' => 'E',
            'Ì' => 'I',
            'Ò' => 'O',
            'Ù' => 'U',
        ]);
    }
}

if (!function_exists('esDiaLaborable')) {
    function esDiaLaborable(string $fecha, ?array $cliente = null, ?int $cod_vendedor = null): bool
    {
        $fecha = trim($fecha);
        if (!validarFechaSQL($fecha)) {
            return false;
        }

        $conn = db();
        if (!$conn) {
            return true;
        }

        $cliente = is_array($cliente) ? $cliente : [];
        $provincia = isset($cliente['provincia']) ? trim((string)$cliente['provincia']) : '';
        $poblacion = isset($cliente['poblacion']) ? trim((string)$cliente['poblacion']) : '';
        $codMunicipioIne = isset($cliente['cod_municipio_ine']) ? trim((string)$cliente['cod_municipio_ine']) : '';
        $dia = (int)date('d', strtotime($fecha));
        $mes = (int)date('m', strtotime($fecha));

        $sqlFestivoNacional = "
            SELECT TOP 1 1 AS existe
            FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
            WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
              AND (
                    CONVERT(char(10), fecha, 23) = ?
                    OR (
                        repetir_anualmente = 1
                        AND DAY(fecha) = ?
                        AND MONTH(fecha) = ?
                    )
              )
        ";
        $stmtFestivoNacional = odbc_prepare($conn, $sqlFestivoNacional);
        if ($stmtFestivoNacional && odbc_execute($stmtFestivoNacional, ['NACIONAL', $fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoNacional)) {
            return false;
        }

        if ($provincia !== '') {
            $sqlFestivoAutonomico = "
                SELECT TOP 1 1 AS existe
                FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
                WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(provincia)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND (
                        CONVERT(char(10), fecha, 23) = ?
                        OR (
                            repetir_anualmente = 1
                            AND DAY(fecha) = ?
                            AND MONTH(fecha) = ?
                        )
                  )
            ";
            $stmtFestivoAutonomico = odbc_prepare($conn, $sqlFestivoAutonomico);
            if ($stmtFestivoAutonomico && odbc_execute($stmtFestivoAutonomico, ['AUTONOMICO', $provincia, $fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoAutonomico)) {
                return false;
            }
        }

        if ($codMunicipioIne !== '') {
            $sqlFestivoLocalIne = "
                SELECT TOP 1 1 AS existe
                FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
                WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(cod_municipio_ine)) = LTRIM(RTRIM(?))
                  AND (
                        CONVERT(char(10), fecha, 23) = ?
                        OR (
                            repetir_anualmente = 1
                            AND DAY(fecha) = ?
                            AND MONTH(fecha) = ?
                        )
                  )
            ";
            $stmtFestivoLocalIne = odbc_prepare($conn, $sqlFestivoLocalIne);
            if ($stmtFestivoLocalIne && odbc_execute($stmtFestivoLocalIne, ['LOCAL', $codMunicipioIne, $fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoLocalIne)) {
                return false;
            }
        } elseif ($provincia !== '' && $poblacion !== '') {
            $sqlFestivoLocalTexto = "
                SELECT TOP 1 1 AS existe
                FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
                WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(provincia)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(poblacion)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND (
                        CONVERT(char(10), fecha, 23) = ?
                        OR (
                            repetir_anualmente = 1
                            AND DAY(fecha) = ?
                            AND MONTH(fecha) = ?
                        )
                  )
            ";
            $stmtFestivoLocalTexto = odbc_prepare($conn, $sqlFestivoLocalTexto);
            if ($stmtFestivoLocalTexto && odbc_execute($stmtFestivoLocalTexto, ['LOCAL', $provincia, $poblacion, $fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoLocalTexto)) {
                return false;
            }
        }

        if ($cod_vendedor !== null) {
            $sqlAgenda = "
                SELECT TOP 1 1 AS existe
                FROM [integral].[dbo].[cmf_comerciales_calendario_agenda]
                WHERE cod_vendedor = ?
                  AND CONVERT(char(10), fecha, 23) = ?
                  AND hora_inicio IS NULL
                  AND hora_fin IS NULL
            ";
            $stmtAgenda = odbc_prepare($conn, $sqlAgenda);
            if ($stmtAgenda && odbc_execute($stmtAgenda, [$cod_vendedor, $fecha]) && odbc_fetch_row($stmtAgenda)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('obtenerDetalleDiaNoLaborable')) {
    function obtenerDetalleDiaNoLaborable(string $fecha, ?int $cod_vendedor = null, ?array $cliente = null): ?array
    {
        $fecha = trim($fecha);
        if (!validarFechaSQL($fecha)) {
            return null;
        }

        $conn = db();
        if (!$conn) {
            return null;
        }

        $cliente = is_array($cliente) ? $cliente : [];
        $provincia = isset($cliente['provincia']) ? trim((string)$cliente['provincia']) : '';
        $poblacion = isset($cliente['poblacion']) ? trim((string)$cliente['poblacion']) : '';
        $codMunicipioIne = isset($cliente['cod_municipio_ine']) ? trim((string)$cliente['cod_municipio_ine']) : '';
        $dia = (int)date('d', strtotime($fecha));
        $mes = (int)date('m', strtotime($fecha));

        if ($cod_vendedor !== null) {
            $sqlAgenda = "
                SELECT TOP 1 tipo_evento, descripcion
                FROM [integral].[dbo].[cmf_comerciales_calendario_agenda]
                WHERE cod_vendedor = ?
                  AND CONVERT(char(10), fecha, 23) = ?
                  AND hora_inicio IS NULL
                  AND hora_fin IS NULL
                ORDER BY id DESC
            ";
            $stmtAgenda = odbc_prepare($conn, $sqlAgenda);
            if ($stmtAgenda && odbc_execute($stmtAgenda, [$cod_vendedor, $fecha]) && odbc_fetch_row($stmtAgenda)) {
                $descripcion = trim((string)(odbc_result($stmtAgenda, 'descripcion') ?: ''));
                $tipoEvento = trim((string)(odbc_result($stmtAgenda, 'tipo_evento') ?: ''));
                return [
                    'origen' => 'agenda',
                    'label' => $tipoEvento !== '' ? $tipoEvento : 'AGENDA',
                    'descripcion' => $descripcion !== '' ? $descripcion : 'Bloqueo de agenda',
                ];
            }
        }

        $sqlFestivoNacional = "
            SELECT TOP 1 ambito, descripcion
            FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
            WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = 'NACIONAL' COLLATE Latin1_General_CI_AI
              AND (
                    CONVERT(char(10), fecha, 23) = ?
                    OR (
                        repetir_anualmente = 1
                        AND DAY(fecha) = ?
                        AND MONTH(fecha) = ?
                    )
              )
            ORDER BY id DESC
        ";
        $stmtFestivoNacional = odbc_prepare($conn, $sqlFestivoNacional);
        if ($stmtFestivoNacional && odbc_execute($stmtFestivoNacional, [$fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoNacional)) {
            $descripcion = trim((string)(odbc_result($stmtFestivoNacional, 'descripcion') ?: ''));
            $ambito = trim((string)(odbc_result($stmtFestivoNacional, 'ambito') ?: ''));
            return [
                'origen' => 'festivo_nacional',
                'label' => $ambito !== '' ? $ambito : 'NACIONAL',
                'descripcion' => $descripcion !== '' ? $descripcion : 'Festivo nacional',
            ];
        }

        if ($provincia !== '') {
            $sqlFestivoAutonomico = "
                SELECT TOP 1 ambito, descripcion
                FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
                WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = 'AUTONOMICO' COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(provincia)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND (
                        CONVERT(char(10), fecha, 23) = ?
                        OR (
                            repetir_anualmente = 1
                            AND DAY(fecha) = ?
                            AND MONTH(fecha) = ?
                        )
                  )
                ORDER BY id DESC
            ";
            $stmtFestivoAutonomico = odbc_prepare($conn, $sqlFestivoAutonomico);
            if ($stmtFestivoAutonomico && odbc_execute($stmtFestivoAutonomico, [$provincia, $fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoAutonomico)) {
                $descripcion = trim((string)(odbc_result($stmtFestivoAutonomico, 'descripcion') ?: ''));
                $ambito = trim((string)(odbc_result($stmtFestivoAutonomico, 'ambito') ?: ''));
                return [
                    'origen' => 'festivo_autonomico',
                    'label' => $ambito !== '' ? $ambito : 'AUTONOMICO',
                    'descripcion' => $descripcion !== '' ? $descripcion : 'Festivo autonomico',
                ];
            }
        }

        if ($codMunicipioIne !== '') {
            $sqlFestivoLocalIne = "
                SELECT TOP 1 ambito, descripcion
                FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
                WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = 'LOCAL' COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(cod_municipio_ine)) = LTRIM(RTRIM(?))
                  AND (
                        CONVERT(char(10), fecha, 23) = ?
                        OR (
                            repetir_anualmente = 1
                            AND DAY(fecha) = ?
                            AND MONTH(fecha) = ?
                        )
                  )
                ORDER BY id DESC
            ";
            $stmtFestivoLocalIne = odbc_prepare($conn, $sqlFestivoLocalIne);
            if ($stmtFestivoLocalIne && odbc_execute($stmtFestivoLocalIne, [$codMunicipioIne, $fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoLocalIne)) {
                $descripcion = trim((string)(odbc_result($stmtFestivoLocalIne, 'descripcion') ?: ''));
                $ambito = trim((string)(odbc_result($stmtFestivoLocalIne, 'ambito') ?: ''));
                return [
                    'origen' => 'festivo_local',
                    'label' => $ambito !== '' ? $ambito : 'LOCAL',
                    'descripcion' => $descripcion !== '' ? $descripcion : 'Festivo local',
                ];
            }
        } elseif ($provincia !== '' && $poblacion !== '') {
            $sqlFestivoLocalTexto = "
                SELECT TOP 1 ambito, descripcion
                FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
                WHERE LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = 'LOCAL' COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(provincia)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND LTRIM(RTRIM(poblacion)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
                  AND (
                        CONVERT(char(10), fecha, 23) = ?
                        OR (
                            repetir_anualmente = 1
                            AND DAY(fecha) = ?
                            AND MONTH(fecha) = ?
                        )
                  )
                ORDER BY id DESC
            ";
            $stmtFestivoLocalTexto = odbc_prepare($conn, $sqlFestivoLocalTexto);
            if ($stmtFestivoLocalTexto && odbc_execute($stmtFestivoLocalTexto, [$provincia, $poblacion, $fecha, $dia, $mes]) && odbc_fetch_row($stmtFestivoLocalTexto)) {
                $descripcion = trim((string)(odbc_result($stmtFestivoLocalTexto, 'descripcion') ?: ''));
                $ambito = trim((string)(odbc_result($stmtFestivoLocalTexto, 'ambito') ?: ''));
                return [
                    'origen' => 'festivo_local',
                    'label' => $ambito !== '' ? $ambito : 'LOCAL',
                    'descripcion' => $descripcion !== '' ? $descripcion : 'Festivo local',
                ];
            }
        }

        return null;
    }
}

if (!function_exists('normalizarFechaInicioSemanaCiclo')) {
    function normalizarFechaInicioSemanaCiclo(string $fecha): ?int
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }

        $fechaBase = substr($fecha, 0, 10);
        $lunesSemana = date('Y-m-d', strtotime($fechaBase . ' monday this week'));
        $timestamp = strtotime($lunesSemana . ' 00:00:00');

        return $timestamp === false ? null : $timestamp;
    }
}

if (!function_exists('calcularSemanasNaturalesEntreFechas')) {
    function calcularSemanasNaturalesEntreFechas(string $fechaInicio, string $fechaFin): int
    {
        $inicio = DateTimeImmutable::createFromFormat('Y-m-d', $fechaInicio);
        $fin = DateTimeImmutable::createFromFormat('Y-m-d', $fechaFin);

        if (!$inicio || !$fin) {
            return 0;
        }

        if ($fin < $inicio) {
            return 0;
        }

        $diferencia = $inicio->diff($fin);
        $dias = (int)($diferencia->days ?? 0);

        return intdiv($dias, 7);
    }
}

if (!function_exists('construirContextoZonaActivaDesdeCiclo')) {
    function construirContextoZonaActivaDesdeCiclo(array $zonasCicloVendedor, string $fecha): ?array
    {
        if (empty($zonasCicloVendedor) || !validarFechaSQL($fecha)) {
            return null;
        }

        usort($zonasCicloVendedor, static function (array $a, array $b): int {
            $ordenA = (int)($a['orden'] ?? 0);
            $ordenB = (int)($b['orden'] ?? 0);
            if ($ordenA === $ordenB) {
                return (int)($a['cod_zona'] ?? 0) <=> (int)($b['cod_zona'] ?? 0);
            }
            return $ordenA <=> $ordenB;
        });

        $fechaInicioCiclo = '';
        $zonasOrdenadas = [];
        $cicloTotalSemanas = 0;

        foreach ($zonasCicloVendedor as $zonaCiclo) {
            $duracion = max(0, (int)($zonaCiclo['duracion_semanas'] ?? 0));
            if ($duracion <= 0) {
                continue;
            }

            $fechaInicioTmp = trim((string)($zonaCiclo['fecha_inicio_ciclo'] ?? ''));
            if ($fechaInicioCiclo === '' && $fechaInicioTmp !== '') {
                $fechaInicioCiclo = $fechaInicioTmp;
            }

            $zonasOrdenadas[] = [
                'cod_zona' => (int)($zonaCiclo['cod_zona'] ?? 0),
                'duracion_semanas' => $duracion,
                'orden' => (int)($zonaCiclo['orden'] ?? 0),
                'nombre_zona' => trim((string)($zonaCiclo['nombre_zona'] ?? ($zonaCiclo['nombre'] ?? ''))),
            ];
            $cicloTotalSemanas += $duracion;
        }

        if ($fechaInicioCiclo === '' || empty($zonasOrdenadas) || $cicloTotalSemanas <= 0) {
            return null;
        }

        $inicioCicloTs = normalizarFechaInicioSemanaCiclo($fechaInicioCiclo);
        $fechaObjetivoTs = normalizarFechaInicioSemanaCiclo($fecha);
        $lunesInicio = $inicioCicloTs !== null ? date('Y-m-d', $inicioCicloTs) : '';
        $lunesObjetivo = $fechaObjetivoTs !== null ? date('Y-m-d', $fechaObjetivoTs) : '';
        if ($inicioCicloTs === null || $fechaObjetivoTs === null || $lunesInicio === '' || $lunesObjetivo === '') {
            return null;
        }

        $segundosSemana = 7 * 24 * 60 * 60;
        $diferenciaSemanas = calcularSemanasNaturalesEntreFechas($lunesInicio, $lunesObjetivo);
        $indiceCicloActual = (int)floor($diferenciaSemanas / $cicloTotalSemanas);
        $semanaCiclo = ($diferenciaSemanas % $cicloTotalSemanas) + 1;

        $zonaActual = null;
        $indiceZonaActual = 0;
        $semanaAcumulada = 0;
        foreach ($zonasOrdenadas as $indiceZona => $zonaCiclo) {
            $semanaAcumulada += (int)$zonaCiclo['duracion_semanas'];
            if ($semanaCiclo <= $semanaAcumulada) {
                $zonaActual = $zonaCiclo;
                $indiceZonaActual = $indiceZona;
                break;
            }
        }

        if ($zonaActual === null) {
            return null;
        }

        $cicloActualInicioTs = $inicioCicloTs + ($indiceCicloActual * $cicloTotalSemanas * $segundosSemana);
        $cicloActualFinTs = $cicloActualInicioTs + ($cicloTotalSemanas * $segundosSemana);
        $zonaSiguiente = $zonasOrdenadas[($indiceZonaActual + 1) % count($zonasOrdenadas)] ?? null;

        return [
            'inicio_ciclo_ts' => $inicioCicloTs,
            'ciclo_total_semanas' => $cicloTotalSemanas,
            'indice_ciclo_actual' => $indiceCicloActual,
            'numero_ciclo_actual' => $indiceCicloActual + 1,
            'semana_ciclo' => $semanaCiclo,
            'ciclo_actual_inicio_ts' => $cicloActualInicioTs,
            'ciclo_actual_fin_ts' => $cicloActualFinTs,
            'zona_actual' => (int)($zonaActual['cod_zona'] ?? 0),
            'zona_actual_detalle' => $zonaActual,
            'zona_siguiente' => (int)($zonaSiguiente['cod_zona'] ?? 0),
        ];
    }
}

if (!function_exists('obtenerZonaActivaPorFecha')) {
    function obtenerZonaActivaPorFecha($conn, int $codVendedor, string $fecha): ?array
    {
        if ($codVendedor <= 0 || !validarFechaSQL($fecha) || !$conn) {
            return null;
        }

        $sql = "
            SELECT cod_zona, nombre_zona, duracion_semanas, orden, fecha_inicio_ciclo
            FROM cmf_comerciales_zonas
            WHERE cod_vendedor = ?
            ORDER BY orden ASC, cod_zona ASC
        ";
        $stmt = odbc_prepare($conn, $sql);
        if (!$stmt || !odbc_execute($stmt, [$codVendedor])) {
            return null;
        }

        $zonas = [];
        while ($row = odbc_fetch_array($stmt)) {
            $zonas[] = [
                'cod_zona' => (int)($row['cod_zona'] ?? 0),
                'nombre_zona' => trim((string)($row['nombre_zona'] ?? '')),
                'duracion_semanas' => (int)($row['duracion_semanas'] ?? 0),
                'orden' => (int)($row['orden'] ?? 0),
                'fecha_inicio_ciclo' => trim((string)($row['fecha_inicio_ciclo'] ?? '')),
            ];
        }

        return construirContextoZonaActivaDesdeCiclo($zonas, $fecha);
    }
}

if (!function_exists('obtenerEventosCalendarioDia')) {
    if (!function_exists('descripcionFestivoIncluyeUbicacion')) {
        function descripcionFestivoIncluyeUbicacion(?string $descripcion, ?string $provincia, ?string $poblacion): bool
        {
            $descripcionNorm = normalizarComparacion($descripcion);
            $provinciaNorm = normalizarComparacion($provincia);
            $poblacionNorm = normalizarComparacion($poblacion);

            if ($descripcionNorm === '') {
                return false;
            }

            if ($poblacionNorm !== '' && strpos($descripcionNorm, $poblacionNorm) === false) {
                return false;
            }

            if ($provinciaNorm !== '' && strpos($descripcionNorm, $provinciaNorm) === false) {
                return false;
            }

            return $poblacionNorm !== '' || $provinciaNorm !== '';
        }
    }

    if (!function_exists('descripcionFestivoLocalEsGenerica')) {
        function descripcionFestivoLocalEsGenerica(?string $descripcion, ?string $provincia, ?string $poblacion): bool
        {
            $descripcionNorm = normalizarComparacion($descripcion);
            if ($descripcionNorm === '') {
                return false;
            }

            if (strpos($descripcionNorm, 'FIESTA LOCAL EN ') !== 0 && strpos($descripcionNorm, 'FESTIVO LOCAL EN ') !== 0) {
                return false;
            }

            return descripcionFestivoIncluyeUbicacion($descripcion, $provincia, $poblacion);
        }
    }

    if (!function_exists('formatearUbicacionFestivo')) {
        function formatearUbicacionFestivo(?string $provincia, ?string $poblacion): string
        {
            $provincia = trim((string)$provincia);
            $poblacion = trim((string)$poblacion);

            if ($poblacion !== '' && $provincia !== '') {
                return $poblacion . ' (' . $provincia . ')';
            }

            if ($poblacion !== '') {
                return $poblacion;
            }

            return $provincia;
        }
    }

    if (!function_exists('obtenerZonaActivaCalendarioFecha')) {
        function obtenerZonaActivaCalendarioFecha($conn, int $codVendedor, string $fecha): ?int
        {
            $contextoZona = obtenerZonaActivaPorFecha($conn, $codVendedor, $fecha);
            return $contextoZona !== null ? (int)($contextoZona['zona_actual'] ?? 0) : null;
        }
    }

    if (!function_exists('obtenerPoblacionesZonaActivaCalendario')) {
        function obtenerPoblacionesZonaActivaCalendario($conn, int $codVendedor, ?int $codZona): array
        {
            if ($codVendedor <= 0 || $codZona === null || $codZona <= 0) {
                return [];
            }

            $sql = "
                SELECT DISTINCT
                    LTRIM(RTRIM(ISNULL(c.provincia, ''))) AS provincia,
                    LTRIM(RTRIM(ISNULL(c.poblacion, ''))) AS poblacion
                FROM clientes c
                INNER JOIN cmf_comerciales_clientes_zona cz
                    ON cz.cod_cliente = c.cod_cliente
                WHERE c.cod_vendedor = ?
                  AND cz.zona_principal = ?
                  AND cz.activo = 1
            ";
            $stmt = odbc_prepare($conn, $sql);
            if (!$stmt || !odbc_execute($stmt, [$codVendedor, $codZona])) {
                return [];
            }

            $poblacionesZona = [];
            while ($row = odbc_fetch_array($stmt)) {
                $provincia = trim(toUTF8((string)($row['provincia'] ?? '')));
                $poblacion = trim(toUTF8((string)($row['poblacion'] ?? '')));
                if ($provincia === '' || $poblacion === '') {
                    continue;
                }

                $clave = normalizarComparacion($provincia) . '|' . normalizarComparacion($poblacion);
                $poblacionesZona[$clave] = true;
            }

            return $poblacionesZona;
        }
    }

    function obtenerEventosCalendarioDia(string $fecha, ?int $cod_vendedor = null): array
    {
        $fecha = trim($fecha);
        if (!validarFechaSQL($fecha)) {
            return [];
        }

        $conn = db();
        if (!$conn) {
            return [];
        }

        $eventos = [];
        $eventosVistos = [];
        $festivosLocalesAgrupados = [];
        $dia = (int)date('d', strtotime($fecha));
        $mes = (int)date('m', strtotime($fecha));
        $provinciasCliente = [];
        $poblacionesCliente = [];
        $poblacionesZonaActiva = [];

        if ($cod_vendedor !== null) {
            $codZonaActivaFecha = obtenerZonaActivaCalendarioFecha($conn, (int)$cod_vendedor, $fecha);
            $poblacionesZonaActiva = obtenerPoblacionesZonaActivaCalendario($conn, (int)$cod_vendedor, $codZonaActivaFecha);
        }

        if ($cod_vendedor !== null) {
            $sqlClientesComercial = "
                SELECT DISTINCT
                    LTRIM(RTRIM(ISNULL(provincia, ''))) AS provincia,
                    LTRIM(RTRIM(ISNULL(poblacion, ''))) AS poblacion
                FROM [integral].[dbo].[clientes]
                WHERE cod_vendedor = ?
            ";
            $stmtClientesComercial = odbc_prepare($conn, $sqlClientesComercial);
            if ($stmtClientesComercial && odbc_execute($stmtClientesComercial, [$cod_vendedor])) {
                while ($rowCliente = odbc_fetch_array($stmtClientesComercial)) {
                    $provinciaCliente = trim((string)($rowCliente['provincia'] ?? ''));
                    $poblacionCliente = trim((string)($rowCliente['poblacion'] ?? ''));

                    if ($provinciaCliente !== '') {
                        $claveProvincia = normalizarComparacion($provinciaCliente);
                        $provinciasCliente[$claveProvincia] = true;
                    }

                    if ($provinciaCliente !== '' && $poblacionCliente !== '') {
                        $clavePoblacion = normalizarComparacion($provinciaCliente) . '|' . normalizarComparacion($poblacionCliente);
                        $poblacionesCliente[$clavePoblacion] = true;
                    }
                }
            }
        }

        if ($cod_vendedor !== null) {
            $sqlAgenda = "
                SELECT id, fecha, hora_inicio, hora_fin, tipo_evento, descripcion
                FROM [integral].[dbo].[cmf_comerciales_calendario_agenda]
                WHERE cod_vendedor = ?
                  AND CONVERT(char(10), fecha, 23) = ?
                ORDER BY
                    CASE WHEN hora_inicio IS NULL THEN 0 ELSE 1 END,
                    hora_inicio,
                    id
            ";
            $stmtAgenda = odbc_prepare($conn, $sqlAgenda);
            if ($stmtAgenda && odbc_execute($stmtAgenda, [$cod_vendedor, $fecha])) {
                while ($row = odbc_fetch_array($stmtAgenda)) {
                    $horaInicio = trim((string)($row['hora_inicio'] ?? ''));
                    $horaFin = trim((string)($row['hora_fin'] ?? ''));
                    $tipoEvento = trim(toUTF8((string)($row['tipo_evento'] ?? '')));
                    $descripcion = trim(toUTF8((string)($row['descripcion'] ?? '')));
                    $claveEvento = implode('|', [
                        'agenda',
                        $fecha,
                        $horaInicio,
                        $horaFin,
                        normalizarComparacion($tipoEvento),
                        normalizarComparacion($descripcion),
                    ]);
                    if (isset($eventosVistos[$claveEvento])) {
                        continue;
                    }
                    $eventosVistos[$claveEvento] = true;

                    $eventos[] = [
                        'tipo_registro' => 'calendario',
                        'origen_calendario' => 'agenda',
                        'start' => $horaInicio !== '' ? $horaInicio : '00:00:00',
                        'titulo' => $tipoEvento !== '' ? $tipoEvento : 'AGENDA',
                        'descripcion' => $descripcion,
                        'hora_inicio' => $horaInicio,
                        'hora_fin' => $horaFin,
                        'ambito' => '',
                        'provincia' => '',
                        'poblacion' => '',
                    ];
                }
            }
        }

        $sqlFestivos = "
            SELECT id, fecha, ambito, provincia, poblacion, descripcion, repetir_anualmente
            FROM [integral].[dbo].[cmf_comerciales_calendario_festivos]
            WHERE CONVERT(char(10), fecha, 23) = ?
               OR (
                    repetir_anualmente = 1
                    AND DAY(fecha) = ?
                    AND MONTH(fecha) = ?
               )
            ORDER BY
                CASE LTRIM(RTRIM(ambito))
                    WHEN 'NACIONAL' THEN 1
                    WHEN 'AUTONOMICO' THEN 2
                    WHEN 'LOCAL' THEN 3
                    ELSE 4
                END,
                provincia,
                poblacion,
                id
        ";
        $stmtFestivos = odbc_prepare($conn, $sqlFestivos);
        if ($stmtFestivos && odbc_execute($stmtFestivos, [$fecha, $dia, $mes])) {
            while ($row = odbc_fetch_array($stmtFestivos)) {
                $ambito = trim(toUTF8((string)($row['ambito'] ?? '')));
                $provincia = trim(toUTF8((string)($row['provincia'] ?? '')));
                $poblacion = trim(toUTF8((string)($row['poblacion'] ?? '')));
                $descripcion = trim(toUTF8((string)($row['descripcion'] ?? '')));

                if ($cod_vendedor !== null) {
                    if (strcasecmp($ambito, 'AUTONOMICO') === 0) {
                        $claveProvincia = $provincia !== '' ? normalizarComparacion($provincia) : '';
                        if ($claveProvincia === '' || !isset($provinciasCliente[$claveProvincia])) {
                            continue;
                        }
                    }

                    if (strcasecmp($ambito, 'LOCAL') === 0) {
                        $clavePoblacion = ($provincia !== '' && $poblacion !== '')
                            ? normalizarComparacion($provincia) . '|' . normalizarComparacion($poblacion)
                            : '';
                        if ($clavePoblacion === '' || !isset($poblacionesCliente[$clavePoblacion])) {
                            continue;
                        }
                    }
                }

                $claveEvento = implode('|', [
                    'festivo',
                    $fecha,
                    normalizarComparacion($ambito),
                    normalizarComparacion($provincia),
                    normalizarComparacion($poblacion),
                    normalizarComparacion($descripcion),
                ]);
                if (isset($eventosVistos[$claveEvento])) {
                    continue;
                }
                $eventosVistos[$claveEvento] = true;

                if (strcasecmp($ambito, 'LOCAL') === 0) {
                    $provinciaLocal = $provincia !== '' ? $provincia : 'SIN PROVINCIA';
                    $poblacionLocal = $poblacion !== '' ? $poblacion : $provinciaLocal;
                    $claveProvinciaLocal = normalizarComparacion($provinciaLocal);
                    $clavePoblacionLocal = normalizarComparacion($poblacionLocal);

                    if (!isset($festivosLocalesAgrupados[$claveProvinciaLocal])) {
                        $festivosLocalesAgrupados[$claveProvinciaLocal] = [
                            'provincia' => $provinciaLocal,
                            'poblaciones' => [],
                        ];
                    }

                    $claveZona = normalizarComparacion($provinciaLocal) . '|' . normalizarComparacion($poblacionLocal);
                    $festivosLocalesAgrupados[$claveProvinciaLocal]['poblaciones'][$clavePoblacionLocal] = [
                        'texto' => $poblacionLocal,
                        'resaltado' => isset($poblacionesZonaActiva[$claveZona]),
                    ];
                    continue;
                }

                $eventos[] = [
                    'tipo_registro' => 'calendario',
                    'origen_calendario' => 'festivo',
                    'start' => '00:00:00',
                    'titulo' => $ambito !== '' ? ('FESTIVO ' . $ambito) : 'FESTIVO',
                    'descripcion' => $descripcion,
                    'hora_inicio' => '',
                    'hora_fin' => '',
                    'ambito' => $ambito,
                    'provincia' => $provincia,
                    'poblacion' => $poblacion,
                    'mostrar_ubicacion' => !descripcionFestivoIncluyeUbicacion($descripcion, $provincia, $poblacion),
                ];
            }
        }

        if (!empty($festivosLocalesAgrupados)) {
            uasort($festivosLocalesAgrupados, static function (array $a, array $b): int {
                return strcasecmp((string)$a['provincia'], (string)$b['provincia']);
            });

            $lineasLocales = [];
            foreach ($festivosLocalesAgrupados as $grupoProvincia) {
                $lineasLocales[] = [
                    'texto' => (string)$grupoProvincia['provincia'] . ':',
                    'resaltado' => false,
                    'cabecera' => true,
                ];

                $poblacionesLocales = array_values($grupoProvincia['poblaciones']);
                usort($poblacionesLocales, static function (array $a, array $b): int {
                    return strcasecmp((string)$a['texto'], (string)$b['texto']);
                });

                foreach ($poblacionesLocales as $poblacionLocal) {
                    $lineasLocales[] = [
                        'texto' => ' - ' . (string)$poblacionLocal['texto'],
                        'resaltado' => !empty($poblacionLocal['resaltado']),
                        'cabecera' => false,
                    ];
                }

                $lineasLocales[] = [
                    'texto' => '',
                    'resaltado' => false,
                    'cabecera' => false,
                ];
            }

            if (!empty($lineasLocales) && (string)(end($lineasLocales)['texto'] ?? '') === '') {
                array_pop($lineasLocales);
            }

            $eventos[] = [
                'tipo_registro' => 'calendario',
                'origen_calendario' => 'festivo',
                'start' => '00:00:00',
                'titulo' => 'FESTIVO LOCAL',
                'descripcion' => '',
                'lineas_descripcion' => $lineasLocales,
                'hora_inicio' => '',
                'hora_fin' => '',
                'ambito' => 'LOCAL',
                'provincia' => '',
                'poblacion' => '',
                'mostrar_ubicacion' => false,
            ];
        }

        return $eventos;
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

        $sql = "SELECT clave, valor FROM cmf_comerciales_app_config";
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
