<?php

function horaVisitaATotalMinutos(string $hora): int
{
    $partes = explode(':', substr($hora, 0, 5));
    return ((int)($partes[0] ?? 0) * 60) + (int)($partes[1] ?? 0);
}

function validarHorarioClienteVisita($conn, int $cod_cliente, $cod_seccion, int $inicioMinutos, int $finMinutos): bool
{
    $sql = "SELECT TOP 1 hora_inicio_manana, hora_fin_manana, hora_inicio_tarde, hora_fin_tarde
            FROM [integral].[dbo].[cmf_asignacion_zonas_clientes]
            WHERE cod_cliente = ?
              AND activo = 1";
    $params = [$cod_cliente];

    if ($cod_seccion !== null) {
        $sql .= " AND cod_seccion = ?";
        $params[] = $cod_seccion;
    } else {
        $sql .= " AND cod_seccion IS NULL";
    }

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt || !odbc_execute($stmt, $params)) {
        throw new Exception('Error al validar horario del cliente: ' . odbc_errormsg($conn));
    }

    $fila = odbc_fetch_array($stmt);
    if (!$fila) {
        return true;
    }

    $mananaInicio = !empty($fila['hora_inicio_manana']) ? horaVisitaATotalMinutos((string)$fila['hora_inicio_manana']) : null;
    $mananaFin = !empty($fila['hora_fin_manana']) ? horaVisitaATotalMinutos((string)$fila['hora_fin_manana']) : null;
    $tardeInicio = !empty($fila['hora_inicio_tarde']) ? horaVisitaATotalMinutos((string)$fila['hora_inicio_tarde']) : null;
    $tardeFin = !empty($fila['hora_fin_tarde']) ? horaVisitaATotalMinutos((string)$fila['hora_fin_tarde']) : null;

    $encajaManana = $mananaInicio !== null && $mananaFin !== null && $inicioMinutos >= $mananaInicio && $finMinutos <= $mananaFin;
    $encajaTarde = $tardeInicio !== null && $tardeFin !== null && $inicioMinutos >= $tardeInicio && $finMinutos <= $tardeFin;

    return $encajaManana || $encajaTarde;
}

function validarSolapeAgendaVisita($conn, int $cod_vendedor, string $fecha_visita, int $inicioMinutos, int $finMinutos, int $idVisitaExcluir = 0): void
{
    $sql = "SELECT hora_inicio_visita, hora_fin_visita
            FROM [integral].[dbo].[cmf_visitas_comerciales]
            WHERE cod_vendedor = ?
              AND fecha_visita = ?
              AND estado_visita IN ('Planificada','Pendiente')";
    $params = [$cod_vendedor, $fecha_visita];

    if ($idVisitaExcluir > 0) {
        $sql .= " AND id_visita <> ?";
        $params[] = $idVisitaExcluir;
    }

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt || !odbc_execute($stmt, $params)) {
        throw new Exception('Error al validar agenda del dÃƒÂ­a: ' . odbc_errormsg($conn));
    }

    while ($fila = odbc_fetch_array($stmt)) {
        $inicioExistente = horaVisitaATotalMinutos((string)($fila['hora_inicio_visita'] ?? '00:00'));
        $finExistente = horaVisitaATotalMinutos((string)($fila['hora_fin_visita'] ?? '00:00'));

        if ($inicioMinutos < $finExistente && $finMinutos > $inicioExistente) {
            throw new Exception('La visita se solapa con otra existente');
        }
    }
}

function is_valid_date($date)
{
    $parts = explode('-', $date);
    if (count($parts) != 3) {
        return false;
    }
    $year = intval($parts[0]);
    $month = intval($parts[1]);
    $day = intval($parts[2]);
    return checkdate($month, $day, $year);
}
