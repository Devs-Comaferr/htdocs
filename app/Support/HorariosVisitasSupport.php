<?php

if (!function_exists('normalizarEstadoVisita')) {
    function normalizarEstadoVisita(?string $estado_visita): string
    {
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

if (!function_exists('normalizarEstadoVisitaClave')) {
    function normalizarEstadoVisitaClave(?string $estado_visita): string
    {
        return strtolower(normalizarEstadoVisita($estado_visita));
    }
}

if (!function_exists('estadoVisitaEsRealizada')) {
    function estadoVisitaEsRealizada(?string $estado_visita): bool
    {
        return normalizarEstadoVisitaClave($estado_visita) === 'realizada';
    }
}

if (!function_exists('estadoVisitaRequiereFranja')) {
    function estadoVisitaRequiereFranja(?string $estado_visita): bool
    {
        return !estadoVisitaEsRealizada($estado_visita);
    }
}

if (!function_exists('determinarColorVisita')) {
    function determinarColorVisita(string $estado_visita, string $origen): string
    {
        $estado_visita = normalizarEstadoVisitaClave($estado_visita);
        $origen = strtolower($origen);
        $color = '#6c757d';
        if ($estado_visita == 'realizada') {
            switch ($origen) {
                case 'pedido web':
                    $color = '#af8641';
                    break;
                case 'telÃ©fono':
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

if (!function_exists('determinarColorPedido')) {
    function determinarColorPedido(string $origen): string
    {
        $origen = strtolower($origen);
        $color = '#6c757d';
        switch ($origen) {
            case 'pedido web':
                $color = '#af8641';
                break;
            case 'telÃ©fono':
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

if (!function_exists('iconoDeOrigen')) {
    function iconoDeOrigen(string $origen): string
    {
        $origen = strtolower($origen);
        switch ($origen) {
            case 'telÃ©fono':
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

if (!function_exists('obtenerHorarioClienteValorFila')) {
    function obtenerHorarioClienteValorFila(array $fila, array $claves, $default = null)
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $fila) && $fila[$clave] !== null) {
                return $fila[$clave];
            }
        }

        return $default;
    }
}

if (!function_exists('normalizarHoraHorarioCliente')) {
    function normalizarHoraHorarioCliente($hora): ?string
    {
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
    function construirHorarioClienteDesdeFila(array $fila, string $origen): array
    {
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
    function obtenerHorarioCliente($cod_cliente, $cod_seccion, string $fecha): array
    {
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
            FROM [integral].[dbo].[cmf_comerciales_clientes_zona]
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
