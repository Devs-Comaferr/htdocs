<?php

require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Support/functions.php';

function visitasServicePrepareExecute($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        appLogTechnicalError('visitas.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    if (!odbc_execute($stmt, $params)) {
        appLogTechnicalError('visitas.execute', odbc_errormsg($conn) ?: odbc_errormsg());
        return false;
    }

    return $stmt;
}

function escape_string_visita(string $str): string
{
    return str_replace("'", "''", $str);
}

function horaVisitaATotalMinutos(string $hora): int
{
    $partes = explode(':', substr($hora, 0, 5));
    return ((int)($partes[0] ?? 0) * 60) + (int)($partes[1] ?? 0);
}

function insertarVisitaBase($conn, int $cod_cliente, $cod_seccion, int $cod_vendedor, string $fecha_visita, string $hora_inicio_visita, ?string $hora_fin_visita, ?string $observaciones): int
{
    return crearVisitaRealizada(
        $conn,
        $cod_cliente,
        $cod_seccion,
        $cod_vendedor,
        $fecha_visita,
        $hora_inicio_visita,
        $hora_fin_visita ?? $hora_inicio_visita,
        $observaciones
    );
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
        throw new Exception('Error al validar agenda del dÃ­a: ' . odbc_errormsg($conn));
    }

    while ($fila = odbc_fetch_array($stmt)) {
        $inicioExistente = horaVisitaATotalMinutos((string)($fila['hora_inicio_visita'] ?? '00:00'));
        $finExistente = horaVisitaATotalMinutos((string)($fila['hora_fin_visita'] ?? '00:00'));

        if ($inicioMinutos < $finExistente && $finMinutos > $inicioExistente) {
            throw new Exception('La visita se solapa con otra existente');
        }
    }
}

function registrarVisitaManual(array $data, bool $forzar = false): array
{
    $conn = db();

    $conflictos = [
        'solape' => false,
        'cliente_duplicado' => false,
        'exceso_tiempo' => false,
        'no_cabe' => false,
    ];

    $fechaVisita = (string)$data['fecha_visita'];
    $horaInicio = (string)$data['hora_inicio_visita'];
    $horaFin = (string)$data['hora_fin_visita'];
    $codVendedor = (int)$data['cod_vendedor'];
    $codCliente = (int)$data['cod_cliente'];

    $inicioNuevo = strtotime($fechaVisita . ' ' . $horaInicio);
    $finEstimado = strtotime($fechaVisita . ' ' . $horaFin);
    if ($inicioNuevo !== false && $finEstimado !== false) {
        $duracionEstimada = max(0, $finEstimado - $inicioNuevo);
        $finNuevo = $inicioNuevo + $duracionEstimada;
        $duracionNuevaMinutos = (int)round($duracionEstimada / 60);
        $limiteManana = strtotime($fechaVisita . ' 14:00');
        $limiteTarde = strtotime($fechaVisita . ' 20:00');
        $inicioManana = strtotime($fechaVisita . ' 09:00');
        $inicioTarde = strtotime($fechaVisita . ' 17:00');

        $esManana = $inicioNuevo >= $inicioManana && $inicioNuevo < $limiteManana;
        $esTarde = $inicioNuevo >= $inicioTarde && $inicioNuevo < $limiteTarde;

        if (($esManana && $finNuevo > $limiteManana) || ($esTarde && $finNuevo > $limiteTarde)) {
            $conflictos['no_cabe'] = true;
        }

        $sqlSolape = "SELECT hora_inicio_visita, hora_fin_visita
            FROM [integral].[dbo].[cmf_visitas_comerciales]
            WHERE cod_vendedor = ?
              AND CONVERT(varchar(10), fecha_visita, 120) = ?
              AND LOWER(estado_visita) IN ('planificada','pendiente')";
        $stmtSolape = odbc_prepare($conn, $sqlSolape);
        if ($stmtSolape && odbc_execute($stmtSolape, [$codVendedor, $fechaVisita])) {
            $minutosDia = 0;
            while ($row = odbc_fetch_array($stmtSolape)) {
                $inicioExistente = strtotime($fechaVisita . ' ' . (string)$row['hora_inicio_visita']);
                $finExistente = strtotime($fechaVisita . ' ' . (string)$row['hora_fin_visita']);
                if ($inicioExistente === false || $finExistente === false) {
                    continue;
                }

                $minutosDia += (int)round(max(0, $finExistente - $inicioExistente) / 60);

                if ($inicioNuevo < $finExistente && $finNuevo > $inicioExistente) {
                    $conflictos['solape'] = true;
                }
            }

            $limiteMinutos = null;
            if ($esManana) {
                $limiteMinutos = 5 * 60;
            } elseif ($esTarde) {
                $limiteMinutos = 3 * 60;
            }

            if ($limiteMinutos !== null && ($minutosDia + $duracionNuevaMinutos) > $limiteMinutos) {
                $conflictos['exceso_tiempo'] = true;
            }
        }
    }

    $sqlDuplicado = "SELECT TOP 1 id_visita
        FROM [integral].[dbo].[cmf_visitas_comerciales]
        WHERE cod_cliente = ?
          AND CONVERT(varchar(10), fecha_visita, 120) = ?";
    $stmtDuplicado = odbc_prepare($conn, $sqlDuplicado);
    if ($stmtDuplicado && odbc_execute($stmtDuplicado, [$codCliente, $fechaVisita])) {
        if (odbc_fetch_array($stmtDuplicado)) {
            $conflictos['cliente_duplicado'] = true;
        }
    }

    if (($conflictos['solape'] || $conflictos['cliente_duplicado'] || $conflictos['exceso_tiempo'] || $conflictos['no_cabe']) && !$forzar) {
        return [
            'ok' => false,
            'conflictos' => $conflictos,
            'requiere_confirmacion' => true,
        ];
    }

    $sql_insert = "INSERT INTO [integral].[dbo].[cmf_visitas_comerciales]
        (cod_cliente, cod_seccion, cod_vendedor, fecha_visita, hora_inicio_visita, hora_fin_visita, estado_visita, cod_zona_visita, observaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = odbc_prepare($conn, $sql_insert);
    if (!$stmt) {
        appLogTechnicalError('visita_manual.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
        return [
            'ok' => false,
            'conflictos' => $conflictos,
            'requiere_confirmacion' => false,
        ];
    }

    $params = [
        $data['cod_cliente'],
        $data['cod_seccion'],
        $data['cod_vendedor'],
        $data['fecha_visita'],
        $data['hora_inicio_visita'],
        $data['hora_fin_visita'],
        $data['estado_visita'],
        $data['cod_zona_visita'],
        $data['observaciones'],
    ];

    if (!odbc_execute($stmt, $params)) {
        appLogTechnicalError('visita_manual.execute', odbc_errormsg($conn) ?: odbc_errormsg());
        return [
            'ok' => false,
            'conflictos' => $conflictos,
            'requiere_confirmacion' => false,
        ];
    }

    return [
        'ok' => true,
    ];
}

function procesarRegistroVisita(array $data): array
{
    $conn = db();

    $cod_venta = (int)($data['cod_venta'] ?? 0);
    $cod_cliente = (int)($data['cod_cliente'] ?? 0);
    $cod_seccion = $data['cod_seccion'];
    $cod_vendedor = (int)($data['cod_vendedor'] ?? 0);
    $fecha_visita = (string)($data['fecha_visita'] ?? '');
    $hora_inicio_visita = (string)($data['hora_inicio_visita'] ?? '');
    $hora_fin_visita = $data['hora_fin_visita'];
    $estado_visita = (string)($data['estado_visita'] ?? 'Realizada');
    $observaciones = $data['observaciones'];
    $ampliacion = (int)($data['ampliacion'] ?? 0);
    $previous_id_visita = (int)($data['previous_id_visita'] ?? 0);
    $esVisitaManual = ($cod_venta <= 0);

    if ($cod_cliente <= 0 || $cod_vendedor <= 0 || $fecha_visita === '' || $hora_inicio_visita === '') {
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error_formato_fecha'];
    }

    if ($observaciones !== null && strlen((string)$observaciones) > 500) {
        $observaciones = substr((string)$observaciones, 0, 500);
    }

    $horaFinValidacion = $hora_fin_visita ?? $hora_inicio_visita;
    $inicioMinutos = horaVisitaATotalMinutos($hora_inicio_visita);
    $finMinutos = horaVisitaATotalMinutos((string)$horaFinValidacion);

    if ($finMinutos <= $inicioMinutos) {
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error'];
    }

    try {
        odbc_autocommit($conn, false);

        if ($esVisitaManual) {
            if ($ampliacion === 1 && $previous_id_visita > 0) {
                validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos, $previous_id_visita);
                if (
                    !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                    && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
                ) {
                    throw new Exception('La visita estÃ¡ fuera del horario del cliente');
                }

                $sqlUpdate = "
                    UPDATE [integral].[dbo].[cmf_visitas_comerciales]
                    SET
                        estado_visita = 'Realizada',
                        fecha_visita = ?,
                        hora_inicio_visita = ?,
                        hora_fin_visita = ?,
                        observaciones = ?
                    WHERE
                        id_visita = ?
                        AND LOWER(estado_visita) IN ('pendiente', 'planificada')
                ";
                $stmtUpdate = odbc_prepare($conn, $sqlUpdate);
                if (!$stmtUpdate) {
                    throw new Exception('Error al preparar update de visita: ' . odbc_errormsg($conn));
                }
                if (!odbc_execute($stmtUpdate, [$fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $previous_id_visita])) {
                    throw new Exception('Error al actualizar visita: ' . odbc_errormsg($conn));
                }
            } else {
                validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos);
                if (
                    !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                    && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
                ) {
                    throw new Exception('La visita estÃ¡ fuera del horario del cliente');
                }

                insertarVisitaBase(
                    $conn,
                    $cod_cliente,
                    $cod_seccion,
                    $cod_vendedor,
                    $fecha_visita,
                    $hora_inicio_visita,
                    $hora_fin_visita,
                    $observaciones
                );
            }
        } else {
            if ($ampliacion === 1 && $previous_id_visita > 0) {
                validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos, $previous_id_visita);
                if (
                    !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                    && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
                ) {
                    throw new Exception('La visita estÃ¡ fuera del horario del cliente');
                }

                asegurarRelacionVisitaPedido($conn, $cod_venta, 'Visita', [
                    'id_visita' => $previous_id_visita,
                    'cod_cliente' => $cod_cliente,
                    'cod_seccion' => $cod_seccion,
                ]);

                $sqlUpdate = "
                    UPDATE [integral].[dbo].[cmf_visitas_comerciales]
                    SET
                        estado_visita = 'Realizada',
                        fecha_visita = ?,
                        hora_inicio_visita = ?,
                        hora_fin_visita = ?,
                        observaciones = ?
                    WHERE
                        id_visita = ?
                        AND LOWER(estado_visita) IN ('pendiente', 'planificada')
                ";
                $stmtUpdate = odbc_prepare($conn, $sqlUpdate);
                if (!$stmtUpdate) {
                    throw new Exception('Error al preparar update de visita: ' . odbc_errormsg($conn));
                }
                if (!odbc_execute($stmtUpdate, [$fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $previous_id_visita])) {
                    throw new Exception('Error al actualizar visita: ' . odbc_errormsg($conn));
                }
            } else {
                validarSolapeAgendaVisita($conn, $cod_vendedor, $fecha_visita, $inicioMinutos, $finMinutos);
                if (
                    !validarHorarioClienteVisita($conn, $cod_cliente, $cod_seccion, $inicioMinutos, $finMinutos)
                    && ($estado_visita === 'Planificada' || $estado_visita === 'Pendiente')
                ) {
                    throw new Exception('La visita estÃ¡ fuera del horario del cliente');
                }

                $idVisita = insertarVisitaBase(
                    $conn,
                    $cod_cliente,
                    $cod_seccion,
                    $cod_vendedor,
                    $fecha_visita,
                    $hora_inicio_visita,
                    $hora_fin_visita,
                    $observaciones
                );

                asegurarRelacionVisitaPedido($conn, $cod_venta, 'Visita', [
                    'id_visita' => $idVisita,
                    'cod_cliente' => $cod_cliente,
                    'cod_seccion' => $cod_seccion,
                ]);
            }
        }

        recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
        odbc_commit($conn);

        return ['ok' => true, 'redirect' => 'visita_pedido.php?msg=visita_ok'];
    } catch (Exception $e) {
        odbc_rollback($conn);
        appLogTechnicalError('registrar_visita', $e->getMessage());
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=' . urlencode($e->getMessage())];
    }
}

function procesarRegistroVisitaLegacy(array $data): array
{
    $conn = db();

    $nombre_comercial = (string)($data['nombre_comercial'] ?? '');
    $cod_cliente = (int)($data['cod_cliente'] ?? 0);
    $seccion_visita = (int)($data['seccion_visita'] ?? 0);
    $cod_vendedor = (int)($data['cod_vendedor'] ?? 0);
    $fecha_visita = (string)($data['fecha_visita'] ?? '');
    $hora_inicio_visita = (string)($data['hora_inicio_visita'] ?? '');
    $hora_fin_visita = (string)($data['hora_fin_visita'] ?? '');
    $observaciones = (string)($data['observaciones'] ?? '');
    $estado_visita = (string)($data['estado_visita'] ?? '');
    $tipo_visita = (string)($data['tipo_visita'] ?? '');

    if (empty($nombre_comercial) || $cod_cliente <= 0 || $seccion_visita <= 0 || $cod_vendedor <= 0 || empty($fecha_visita) || empty($hora_inicio_visita) || empty($hora_fin_visita)) {
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error'];
    }

    if (!preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $fecha_visita) ||
        !preg_match("/^\\d{2}:\\d{2}$/", $hora_inicio_visita) ||
        !preg_match("/^\\d{2}:\\d{2}$/", $hora_fin_visita)) {
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error_formato_fecha'];
    }

    $inicio = DateTime::createFromFormat('H:i', $hora_inicio_visita);
    $fin = DateTime::createFromFormat('H:i', $hora_fin_visita);

    if (!$inicio || !$fin) {
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error_formato_hora'];
    }

    $diff = $fin->getTimestamp() - $inicio->getTimestamp();
    $diff_min = $diff / 60;

    if ($diff_min < 15 || $diff_min > 300) {
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error_min_tiempo'];
    }

    $sql_insert_visita = "
        INSERT INTO cmf_visitas_comerciales 
        (cod_cliente, cod_seccion, cod_vendedor, fecha_visita, hora_inicio_visita, hora_fin_visita, observaciones, estado_visita, tipo_visita, event_id, cod_zona_visita)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)
    ";

    $stmt_insert = odbc_prepare($conn, $sql_insert_visita);
    if (!$stmt_insert) {
        return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error'];
    }

    $params_insert = array($cod_cliente, $seccion_visita, $cod_vendedor, $fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $estado_visita, $tipo_visita);
    $result_insert = odbc_execute($stmt_insert, $params_insert);

    if ($result_insert) {
        return ['ok' => true, 'redirect' => 'visita_pedido.php?msg=visita_ok'];
    }

    return ['ok' => false, 'redirect' => 'visita_pedido.php?msg=error'];
}

function procesarVisitaSimple(array $data, string $estado_visita): array
{
    $conn = db();
    $cod_vendedor = (int)($data['cod_vendedor'] ?? 0);
    $cod_cliente = (int)($data['cod_cliente'] ?? 0);
    $cod_seccion = (int)($data['cod_seccion'] ?? 0);
    $fecha_visita = (string)($data['fecha_visita'] ?? '');
    $hora_inicio_visita = (string)($data['hora_inicio_visita'] ?? '');
    $hora_fin_visita = (string)($data['hora_fin_visita'] ?? '');
    $observaciones = (string)($data['observaciones'] ?? '');

    $errors = array();
    if ($cod_cliente === 0 || $cod_seccion === 0 || empty($fecha_visita) || empty($hora_inicio_visita) || empty($hora_fin_visita)) {
        $errors[] = 'Todos los campos son obligatorios.';
    }
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $fecha_visita)) {
        $errors[] = 'Formato de fecha invalido.';
    }
    if (!preg_match('/^\\d{2}:\\d{2}$/', $hora_inicio_visita) || !preg_match('/^\\d{2}:\\d{2}$/', $hora_fin_visita)) {
        $errors[] = 'Formato de hora invalido.';
    }
    if (!empty($hora_inicio_visita) && !empty($hora_fin_visita)) {
        list($inicio_h, $inicio_m) = explode(':', $hora_inicio_visita);
        list($fin_h, $fin_m) = explode(':', $hora_fin_visita);
        $inicio_total = intval($inicio_h) * 60 + intval($inicio_m);
        $fin_total = intval($fin_h) * 60 + intval($fin_m);
        $diff = $fin_total - $inicio_total;
        if ($diff < 15 || $diff > 300) {
            $errors[] = 'La diferencia entre la hora de inicio y la hora de fin debe ser de al menos 15 minutos y no exceder las 5 horas.';
        }
    }

    if (!empty($errors)) {
        return ['ok' => false, 'errors' => $errors];
    }

    $sql = "INSERT INTO cmf_visitas_comerciales 
            (estado_visita, cod_vendedor, cod_cliente, cod_seccion, fecha_visita, hora_inicio_visita, hora_fin_visita, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = odbc_prepare($conn, $sql);
    if ($stmt && odbc_execute($stmt, array($estado_visita, $cod_vendedor, $cod_cliente, $cod_seccion, $fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones))) {
        odbc_free_result($stmt);
        return ['ok' => true];
    }

    if ($stmt) {
        odbc_free_result($stmt);
    }

    $errors[] = 'Ocurrio un error al registrar la visita.';
    return ['ok' => false, 'errors' => $errors];
}

function obtenerDetalleVisitaPorId(int $id_visita): ?array
{
    $conn = db();
    $sql = "SELECT * FROM cmf_visitas_comerciales WHERE id_visita = $id_visita";
    $result = odbc_exec($conn, $sql);
    if ($result && $row = odbc_fetch_array($result)) {
        return $row;
    }

    return null;
}

function obtenerVisitasDiaData(string $fecha): array
{
    $conn = db();
    $fecha = addslashes($fecha);

    $sql = "SELECT v.id_visita,
                   v.cod_cliente,
                   v.cod_seccion,
                   v.estado_visita,
                   v.fecha_visita,
                   v.hora_inicio_visita,
                   v.hora_fin_visita,
                   v.observaciones,
                   c.nombre_comercial,
                   c.cod_cliente,
                   sc.nombre AS nombre_seccion
            FROM [integral].[dbo].[cmf_visitas_comerciales] v
            LEFT JOIN [integral].[dbo].[clientes] c ON v.cod_cliente = c.cod_cliente
            LEFT JOIN [integral].[dbo].[secciones_cliente] sc ON v.cod_cliente = sc.cod_cliente AND v.cod_seccion = sc.cod_seccion
            WHERE CONVERT(varchar(10), v.fecha_visita, 120) = '$fecha'
            ORDER BY v.hora_inicio_visita ASC";
    $result = odbc_exec($conn, $sql);
    if (!$result) {
        appExitTextError('No se pudieron cargar las visitas.', 500, 'get_visitas', odbc_errormsg($conn) ?: odbc_errormsg());
    }

    $visitas = [];
    while ($visita = odbc_fetch_array($result)) {
        $sql_pedido_principal = "SELECT TOP 1 vp.origen
                                 FROM [integral].[dbo].[cmf_visita_pedidos] vp
                                 WHERE vp.id_visita = '" . addslashes($visita['id_visita']) . "'
                                 ORDER BY vp.id_visita_pedido ASC";
        $result_pedido_principal = odbc_exec($conn, $sql_pedido_principal);
        $origenPrincipal = '';
        if ($result_pedido_principal && $pedidoPrincipal = odbc_fetch_array($result_pedido_principal)) {
            $origenPrincipal = $pedidoPrincipal['origen'];
        }

        $colorVisita = determinarColorVisita($visita['estado_visita'], $origenPrincipal);
        $clientName = !empty($visita['nombre_comercial']) ? $visita['nombre_comercial'] : 'Cliente ' . $visita['cod_cliente'];
        if (!empty($visita['nombre_seccion'])) {
            $clientName .= ' - ' . $visita['nombre_seccion'];
        }

        $pedidos = [];
        $sql_pedidos = "SELECT
                            vp.cod_venta AS cod_pedido,
                            vp.origen,
                            hvc.fecha_venta,
                            hvc.hora_venta,
                            hvc.importe,
                            avc.observacion_interna
                        FROM [integral].[dbo].[cmf_visita_pedidos] vp
                        INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc
                            ON vp.cod_venta = hvc.cod_venta
                        LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] avc
                            ON hvc.cod_anexo = avc.cod_anexo
                        WHERE vp.id_visita = '" . addslashes($visita['id_visita']) . "'
                          AND hvc.tipo_venta = 1
                        ORDER BY vp.id_visita_pedido ASC";
        $result_pedidos = odbc_exec($conn, $sql_pedidos);
        if ($result_pedidos) {
            while ($pedido = odbc_fetch_array($result_pedidos)) {
                $pedido['colorPedido'] = determinarColorPedido($pedido['origen']);
                $pedidos[] = $pedido;
            }
        }

        $visita['colorVisita'] = $colorVisita;
        $visita['clientName'] = $clientName;
        $visita['pedidos'] = $pedidos;
        $visitas[] = $visita;
    }

    return ['fecha' => $fecha, 'visitas' => $visitas];
}

function obtenerDatosEditarVisita(int $id_visita): ?array
{
    $conn = db();

    $sql = "
        SELECT 
            cvc.id_visita,
            cvc.fecha_visita,
            cvc.hora_inicio_visita,
            cvc.hora_fin_visita,
            cvc.observaciones,
            cvc.estado_visita,
            cl.cod_cliente,
            cl.nombre_comercial,
            cvc.cod_seccion
        FROM [integral].[dbo].[cmf_visitas_comerciales] cvc
        JOIN [integral].[dbo].[clientes] cl ON cvc.cod_cliente = cl.cod_cliente
        WHERE cvc.id_visita = ?
    ";
    $result = visitasServicePrepareExecute($conn, $sql, [$id_visita]);
    if (!$result || !odbc_fetch_row($result)) {
        return null;
    }

    $cod_cliente = odbc_result($result, 'cod_cliente');
    $cod_seccion = odbc_result($result, 'cod_seccion');

    $sql_assignment = "SELECT * FROM [integral].[dbo].[cmf_asignacion_zonas_clientes] WHERE cod_cliente = ? AND activo = 1";
    $assignmentParams = [$cod_cliente];
    if (!is_null($cod_seccion)) {
        $sql_assignment .= " AND cod_seccion = ?";
        $assignmentParams[] = $cod_seccion;
    } else {
        $sql_assignment .= " AND cod_seccion IS NULL";
    }
    $result_assignment = visitasServicePrepareExecute($conn, $sql_assignment, $assignmentParams);
    $assignment = $result_assignment ? odbc_fetch_array($result_assignment) : false;

    $tiempo_promedio = $assignment ? floatval($assignment['tiempo_promedio_visita']) : 0.0;

    return [
        'id_visita' => $id_visita,
        'cod_cliente' => $cod_cliente,
        'nombre_comercial' => odbc_result($result, 'nombre_comercial'),
        'cod_seccion' => $cod_seccion,
        'fecha_visita' => odbc_result($result, 'fecha_visita'),
        'hora_inicio_visita' => (($value = odbc_result($result, 'hora_inicio_visita')) ? substr($value, 0, 5) : ''),
        'hora_fin_visita' => (($value = odbc_result($result, 'hora_fin_visita')) ? substr($value, 0, 5) : ''),
        'observaciones' => odbc_result($result, 'observaciones'),
        'estado_visita' => odbc_result($result, 'estado_visita'),
        'hora_inicio_manana' => ($assignment && !empty($assignment['hora_inicio_manana'])) ? substr($assignment['hora_inicio_manana'], 0, 5) : '',
        'hora_fin_manana' => ($assignment && !empty($assignment['hora_fin_manana'])) ? substr($assignment['hora_fin_manana'], 0, 5) : '',
        'hora_inicio_tarde' => ($assignment && !empty($assignment['hora_inicio_tarde'])) ? substr($assignment['hora_inicio_tarde'], 0, 5) : '',
        'hora_fin_tarde' => ($assignment && !empty($assignment['hora_fin_tarde'])) ? substr($assignment['hora_fin_tarde'], 0, 5) : '',
        'tiempo_promedio_minutes' => $tiempo_promedio * 60,
    ];
}

function procesarEdicionVisita(int $id_visita, array $input, int $codigo_vendedor_sesion, string $origen = ''): array
{
    $conn = db();

    $data = obtenerDatosEditarVisita($id_visita);
    if ($data === null) {
        return ['ok' => false, 'error' => 'Error interno'];
    }

    $fecha_visita = date('Y-m-d', strtotime(trim((string)$input['fecha_visita'])));
    $hora_inicio_visita = trim((string)$input['hora_inicio_visita']);
    $hora_fin_visita = trim((string)$input['hora_fin_visita']);
    $observaciones = trim((string)$input['observaciones']);
    $estado_visita = normalizarEstadoVisita(trim((string)$input['estado_visita']));

    $error = '';
    if (empty($fecha_visita) || empty($hora_inicio_visita) || empty($hora_fin_visita)) {
        $error = 'Por favor, complete la fecha y las horas de la visita.';
    } elseif (strtotime($hora_inicio_visita) >= strtotime($hora_fin_visita)) {
        $error = 'La hora de inicio debe ser anterior a la de fin.';
    } else {
        if (estadoVisitaRequiereFranja($estado_visita)) {
            $slot = '';
            if (!empty($data['hora_inicio_manana']) && !empty($data['hora_fin_manana'])) {
                $morning_start = strtotime($data['hora_inicio_manana']);
                $morning_end = strtotime($data['hora_fin_manana']);
                if (strtotime($hora_inicio_visita) < $morning_start) {
                    $error = "La hora de inicio de la visita no puede ser anterior a la apertura de la maÃ±ana ({$data['hora_inicio_manana']}).";
                } elseif (strtotime($hora_inicio_visita) >= $morning_start && strtotime($hora_inicio_visita) < $morning_end) {
                    $slot = 'morning';
                    if (strtotime($hora_fin_visita) > $morning_end) {
                        $error = "La hora de fin de la visita no puede ser posterior a la hora de cierre de la maÃ±ana ({$data['hora_fin_manana']}).";
                    }
                }
            }
            if (empty($slot) && empty($error) && !empty($data['hora_inicio_tarde']) && !empty($data['hora_fin_tarde'])) {
                $afternoon_start = strtotime($data['hora_inicio_tarde']);
                $afternoon_end = strtotime($data['hora_fin_tarde']);
                if (strtotime($hora_inicio_visita) < $afternoon_start) {
                    $error = "La hora de inicio de la visita no puede ser anterior a la apertura de la tarde ({$data['hora_inicio_tarde']}).";
                } elseif (strtotime($hora_inicio_visita) >= $afternoon_start && strtotime($hora_inicio_visita) < $afternoon_end) {
                    $slot = 'afternoon';
                    if (strtotime($hora_fin_visita) > $afternoon_end) {
                        $error = "La hora de fin de la visita no puede ser posterior a la hora de cierre de la tarde ({$data['hora_fin_tarde']}).";
                    }
                }
            }
            if (empty($slot) && empty($error)) {
                $horarios = '';
                if (!empty($data['hora_inicio_manana']) && !empty($data['hora_fin_manana'])) {
                    $horarios .= "MaÃ±ana: {$data['hora_inicio_manana']} a {$data['hora_fin_manana']}. ";
                }
                if (!empty($data['hora_inicio_tarde']) && !empty($data['hora_fin_tarde'])) {
                    $horarios .= "Tarde: {$data['hora_inicio_tarde']} a {$data['hora_fin_tarde']}.";
                }
                $error = 'La hora de inicio de la visita no se encuentra dentro de las franjas de disponibilidad del cliente. Horarios: ' . $horarios;
            }
        }

        if (empty($error)) {
            $estadoLower = normalizarEstadoVisitaClave($estado_visita);
            if ($estadoLower == 'descartada') {
                $skipOverlap = true;
            } elseif ($estadoLower == 'realizada') {
                $sql_order = "SELECT TOP 1 origen FROM [integral].[dbo].[cmf_visita_pedidos] 
                              WHERE id_visita = ? AND LOWER(origen) = 'visita'";
                $result_order = visitasServicePrepareExecute($conn, $sql_order, [$id_visita]);
                if ($result_order && odbc_fetch_row($result_order)) {
                    $skipOverlap = false;
                } else {
                    $skipOverlap = true;
                }
            } else {
                $skipOverlap = false;
            }

            if (!$skipOverlap) {
                $sql_overlap = "SELECT * FROM [integral].[dbo].[cmf_visitas_comerciales]
                                WHERE id_visita <> ?
                                  AND cod_vendedor = ?
                                  AND CONVERT(varchar(10), fecha_visita, 120) = ?
                                  AND LOWER(estado_visita) IN ('planificada','pendiente','realizada','no atendida')";
                $result_overlap = visitasServicePrepareExecute($conn, $sql_overlap, [$id_visita, $codigo_vendedor_sesion, $fecha_visita]);
                $overlap = false;
                $overlapDetails = '';
                if ($result_overlap) {
                    while ($row = odbc_fetch_array($result_overlap)) {
                        $existing_start = strtotime($row['hora_inicio_visita']);
                        $existing_end = strtotime($row['hora_fin_visita']);
                        $new_start = strtotime($hora_inicio_visita);
                        $new_end = strtotime($hora_fin_visita);
                        if ($new_start < $existing_end && $new_end > $existing_start) {
                            $overlap = true;
                            $sql_cliente_overlap = "SELECT nombre_comercial FROM [integral].[dbo].[clientes] WHERE cod_cliente = ?";
                            $result_cliente_overlap = visitasServicePrepareExecute($conn, $sql_cliente_overlap, [$row['cod_cliente']]);
                            $overlapCliente = '';
                            if ($result_cliente_overlap && odbc_fetch_row($result_cliente_overlap)) {
                                $overlapCliente = odbc_result($result_cliente_overlap, 'nombre_comercial');
                            }
                            $overlapSeccion = '';
                            if (!empty($row['cod_seccion'])) {
                                $overlap_seccion = intval($row['cod_seccion']);
                                $sql_overlap_seccion = "SELECT nombre FROM [integral].[dbo].[secciones_cliente] 
                                                        WHERE cod_cliente = ? AND cod_seccion = ?";
                                $result_overlap_seccion = visitasServicePrepareExecute($conn, $sql_overlap_seccion, [$row['cod_cliente'], $overlap_seccion]);
                                if ($result_overlap_seccion && odbc_fetch_row($result_overlap_seccion)) {
                                    $overlapSeccion = odbc_result($result_overlap_seccion, 'nombre');
                                }
                            }
                            $overlapDetails = ' ' . $overlapCliente;
                            if (!empty($overlapSeccion)) {
                                $overlapDetails .= ' - ' . $overlapSeccion;
                            }
                            $overlapDetails .= ' de ' . date('H:i', $existing_start) . ' a ' . date('H:i', $existing_end);
                            break;
                        }
                    }
                }
                if ($overlap) {
                    $error = 'Existe una visita programada que se solapa con la visita que intenta actualizar:' . $overlapDetails . '.';
                }
            }
        }
    }

    if (!empty($error)) {
        return ['ok' => false, 'error' => $error];
    }

    $sql_update = "
        UPDATE [integral].[dbo].[cmf_visitas_comerciales]
        SET 
            fecha_visita = ?,
            hora_inicio_visita = ?,
            hora_fin_visita = ?,
            observaciones = ?,
            estado_visita = ?
        WHERE id_visita = ?
    ";
    if (!visitasServicePrepareExecute($conn, $sql_update, [$fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $estado_visita, $id_visita])) {
        return ['ok' => false, 'error' => 'Error al actualizar la visita.'];
    }

    if ($origen === 'visita_pedido') {
        return ['ok' => true, 'redirect' => 'calendario.php?view=timeGridDay&msg=visita_actualizada'];
    }

    return ['ok' => true, 'redirect' => 'calendario.php?msg=visita_actualizada'];
}

function prepararVistaVisitaManual(array $input, int $codigo_vendedor): array
{
    $conn = db();

    $accion = $input['accion'] ?? null;
    $busqueda = trim((string)($input['buscar'] ?? ''));
    $cod_cliente = isset($input['cod_cliente']) ? intval($input['cod_cliente']) : 0;
    $cod_seccion = (isset($input['cod_seccion']) && $input['cod_seccion'] !== '') ? intval($input['cod_seccion']) : null;
    $fecha_visita = isset($input['fecha_visita']) ? trim((string)$input['fecha_visita']) : '';
    $hora_inicio_visita = isset($input['hora_inicio_visita']) ? trim((string)$input['hora_inicio_visita']) : '';
    $hora_fin_visita = isset($input['hora_fin_visita']) ? trim((string)$input['hora_fin_visita']) : '';
    $zona_seleccionada = isset($input['zona_seleccionada']) ? trim((string)$input['zona_seleccionada']) : '';
    $estado_visita = isset($input['estado_visita']) ? trim((string)$input['estado_visita']) : 'Planificada';
    $observaciones = isset($input['observaciones']) ? trim((string)$input['observaciones']) : '';

    $error = '';
    $resultadosBusqueda = [];
    $mostrarResultados = ($accion === 'buscar');
    $assignment = null;
    $nombreCliente = '';
    $nombreSeccion = '';
    $zonas = [];
    $tiempo_promedio_minutes = 0.0;
    $hora_inicio_manana = '';
    $hora_fin_manana = '';
    $hora_inicio_tarde = '';
    $hora_fin_tarde = '';
    $citas = [];

    if ($accion === 'buscar') {
        if ($busqueda !== '') {
            $sql_busqueda = "SELECT cl.cod_cliente, cl.nombre_comercial, sc.cod_seccion, sc.nombre AS nombre_seccion
                             FROM [integral].[dbo].[clientes] cl
                             LEFT JOIN [integral].[dbo].[secciones_cliente] sc
                               ON cl.cod_cliente = sc.cod_cliente
                             WHERE cl.nombre_comercial LIKE ?
                               AND cl.cod_vendedor = ?";
            $result_busqueda = visitasServicePrepareExecute($conn, $sql_busqueda, ['%' . $busqueda . '%', $codigo_vendedor]);
            if (!$result_busqueda) {
                $error = 'Error interno';
            } else {
                while ($cliente = odbc_fetch_array($result_busqueda)) {
                    $resultadosBusqueda[] = $cliente;
                }
            }
        } else {
            $error = 'Introduce un nombre para buscar.';
        }
    }

    if ($cod_cliente > 0) {
        $sql_assignment = "SELECT * FROM [integral].[dbo].[cmf_asignacion_zonas_clientes] WHERE cod_cliente = ? AND activo = 1";
        $assignmentParams = [$cod_cliente];
        if ($cod_seccion !== null) {
            $sql_assignment .= " AND cod_seccion = ?";
            $assignmentParams[] = $cod_seccion;
        } else {
            $sql_assignment .= " AND cod_seccion IS NULL";
        }
        $result_assignment = visitasServicePrepareExecute($conn, $sql_assignment, $assignmentParams);
        if (!$result_assignment) {
            $error = 'Error interno';
        } else {
            $assignment = odbc_fetch_array($result_assignment);
            if (!$assignment) {
                $error = 'Error interno';
            }
        }
    }

    if ($cod_cliente > 0 && $assignment) {
        $sql_cliente = "SELECT nombre_comercial FROM [integral].[dbo].[clientes] WHERE cod_cliente = ?";
        $result_cliente = visitasServicePrepareExecute($conn, $sql_cliente, [$cod_cliente]);
        $clienteData = $result_cliente ? odbc_fetch_array($result_cliente) : false;
        $nombreCliente = $clienteData ? (string)$clienteData['nombre_comercial'] : (string)$cod_cliente;

        if ($cod_seccion !== null) {
            $sql_seccion = "SELECT nombre FROM [integral].[dbo].[secciones_cliente] WHERE cod_cliente = ? AND cod_seccion = ?";
            $result_seccion = visitasServicePrepareExecute($conn, $sql_seccion, [$cod_cliente, $cod_seccion]);
            $seccionData = $result_seccion ? odbc_fetch_array($result_seccion) : false;
            $nombreSeccion = $seccionData ? (string)$seccionData['nombre'] : (string)$cod_seccion;
        }

        if (!empty($assignment['zona_principal'])) {
            $zonas[] = ['codigo' => $assignment['zona_principal'], 'nombre' => 'Zona Principal (' . $assignment['zona_principal'] . ')'];
        }
        if (!empty($assignment['zona_secundaria'])) {
            $zonas[] = ['codigo' => $assignment['zona_secundaria'], 'nombre' => 'Zona Secundaria (' . $assignment['zona_secundaria'] . ')'];
        }

        if ($zona_seleccionada === '' && !empty($zonas)) {
            $zona_seleccionada = (string)$zonas[0]['codigo'];
        }

        $tiempo_promedio_minutes = floatval($assignment['tiempo_promedio_visita']) * 60;
        $hora_inicio_manana = !empty($assignment['hora_inicio_manana']) ? substr((string)$assignment['hora_inicio_manana'], 0, 5) : '';
        $hora_fin_manana = !empty($assignment['hora_fin_manana']) ? substr((string)$assignment['hora_fin_manana'], 0, 5) : '';
        $hora_inicio_tarde = !empty($assignment['hora_inicio_tarde']) ? substr((string)$assignment['hora_inicio_tarde'], 0, 5) : '';
        $hora_fin_tarde = !empty($assignment['hora_fin_tarde']) ? substr((string)$assignment['hora_fin_tarde'], 0, 5) : '';

        $currentDate = date('Y-m-d');
        $sql_citas = "SELECT fecha_visita, hora_inicio_visita, hora_fin_visita, estado_visita
                      FROM [integral].[dbo].[cmf_visitas_comerciales]
                      WHERE cod_cliente = ? ";
        $citasParams = [$cod_cliente];
        if ($cod_seccion !== null) {
            $sql_citas .= "AND cod_seccion = ? ";
            $citasParams[] = $cod_seccion;
        } else {
            $sql_citas .= "AND cod_seccion IS NULL ";
        }
        $sql_citas .= "AND estado_visita IN ('Planificada','Pendiente')
                      AND fecha_visita >= ?
                      ORDER BY fecha_visita ASC";
        $citasParams[] = $currentDate;
        $result_citas = visitasServicePrepareExecute($conn, $sql_citas, $citasParams);
        if ($result_citas) {
            while ($cita = odbc_fetch_array($result_citas)) {
                $citas[] = $cita;
            }
        }
    }

    return compact(
        'accion',
        'busqueda',
        'cod_cliente',
        'cod_seccion',
        'fecha_visita',
        'hora_inicio_visita',
        'hora_fin_visita',
        'zona_seleccionada',
        'estado_visita',
        'observaciones',
        'error',
        'resultadosBusqueda',
        'mostrarResultados',
        'assignment',
        'nombreCliente',
        'nombreSeccion',
        'zonas',
        'tiempo_promedio_minutes',
        'hora_inicio_manana',
        'hora_fin_manana',
        'hora_inicio_tarde',
        'hora_fin_tarde',
        'citas'
    );
}

function obtenerSeccionesDisponiblesVisita(int $cod_cliente, bool $limitar = false, bool $usarNombreSeccion = false): array
{
    $conn = db();
    $prefix = $limitar ? 'TOP 20 ' : '';
    $query = "
        SELECT {$prefix}sc.cod_seccion, sc.nombre 
        FROM secciones_cliente sc
        WHERE sc.cod_cliente = '$cod_cliente' 
          AND sc.cod_seccion NOT IN (
              SELECT azc.cod_seccion 
              FROM cmf_asignacion_zonas_clientes azc 
              WHERE azc.cod_cliente = sc.cod_cliente
          )
        ORDER BY sc.cod_seccion ASC
    ";

    $resultado = odbc_exec($conn, $query);
    if (!$resultado) {
        throw new Exception(odbc_errormsg($conn));
    }

    $secciones = array();
    while ($fila = odbc_fetch_array($resultado)) {
        if ($usarNombreSeccion) {
            $secciones[] = array(
                'cod_seccion' => $fila['cod_seccion'],
                'nombre_seccion' => $fila['nombre'],
            );
        } else {
            $secciones[] = $fila;
        }
    }

    return $secciones;
}

function visitasJsonEncodeCustom($data)
{
    if (is_null($data)) {
        return 'null';
    }
    if ($data === false) {
        return 'false';
    }
    if ($data === true) {
        return 'true';
    }
    if (is_scalar($data)) {
        if (is_float($data)) {
            return floatval(str_replace(",", ".", strval($data)));
        }

        if (is_string($data)) {
            static $json_replaces = array("\\", "\"", "\n", "\t", "\r", "\b", "\f");
            static $json_escape = array('\\\\', '\\"', '\\n', '\\t', '\\r', '\\b', '\\f');
            return '"' . str_replace($json_replaces, $json_escape, $data) . '"';
        }

        return $data;
    }
    $isList = true;

    foreach ($data as $key => $value) {
        if (!is_numeric($key)) {
            $isList = false;
            break;
        }
    }

    $result = array();
    if ($isList) {
        foreach ($data as $value) {
            $result[] = visitasJsonEncodeCustom($value);
        }
        return '[' . join(',', $result) . ']';
    }

    foreach ($data as $key => $value) {
        $result[] = visitasJsonEncodeCustom(strval($key)) . ':' . visitasJsonEncodeCustom($value);
    }
    return '{' . join(',', $result) . '}';
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

function verificarVisitaPreviaService(int $cod_cliente, string $fecha_visita, $cod_seccion): array
{
    $conn = db();
    $fecha_limite = date('Y-m-d', strtotime($fecha_visita . ' -5 days'));

    $sql = "
    SELECT TOP 1 id_visita
    FROM [integral].[dbo].[cmf_visitas_comerciales]
    WHERE cod_cliente = ?
      AND fecha_visita BETWEEN ? AND ?
      AND (
            (? IS NULL AND cod_seccion IS NULL)
            OR (cod_seccion = ?)
          )
    ORDER BY fecha_visita DESC
    ";

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Error al preparar la consulta.'];
    }

    $params = array(
        $cod_cliente,
        $fecha_limite,
        $fecha_visita,
        ($cod_seccion === null ? null : $cod_seccion),
        ($cod_seccion === null ? null : $cod_seccion),
    );

    $exec = odbc_execute($stmt, $params);
    if (!$exec) {
        odbc_free_result($stmt);
        return ['ok' => false, 'error' => 'Error al ejecutar la consulta.'];
    }

    $id_visita = null;
    if ($row = odbc_fetch_array($stmt)) {
        $id_visita = intval($row['id_visita']);
    }

    odbc_free_result($stmt);

    return ['ok' => true, 'id_visita' => $id_visita];
}

function actualizarHorarioVisitaService(int $cod_cliente, $cod_seccion, string $hora_inicio_manana, string $hora_fin_manana, string $hora_inicio_tarde, string $hora_fin_tarde): bool
{
    $conn = db();
    $whereClause = "cod_cliente = ?";
    $params = [$hora_inicio_manana, $hora_fin_manana, $hora_inicio_tarde, $hora_fin_tarde, $cod_cliente];
    if ($cod_seccion !== null) {
        $whereClause .= " AND cod_seccion = ?";
        $params[] = $cod_seccion;
    } else {
        $whereClause .= " AND cod_seccion IS NULL";
    }

    $sql_update = "UPDATE [integral].[dbo].[cmf_asignacion_zonas_clientes]
                   SET hora_inicio_manana = ?,
                       hora_fin_manana = ?,
                       hora_inicio_tarde = ?,
                       hora_fin_tarde = ?
                   WHERE $whereClause";

    $stmt = odbc_prepare($conn, $sql_update);
    return $stmt ? odbc_execute($stmt, $params) : false;
}

function actualizarVisitaService(int $id_visita, string $fecha_visita, string $hora_inicio_visita, string $hora_fin_visita, string $observaciones, string $estado_visita): bool
{
    $conn = db();
    $sql = "
        UPDATE [integral].[dbo].[cmf_visitas_comerciales]
        SET fecha_visita = ?,
            hora_inicio_visita = ?,
            hora_fin_visita = ?,
            observaciones = ?,
            estado_visita = ?
        WHERE id_visita = ?
    ";

    $stmt = odbc_prepare($conn, $sql);
    return $stmt ? odbc_execute($stmt, [$fecha_visita, $hora_inicio_visita, $hora_fin_visita, $observaciones, $estado_visita, $id_visita]) : false;
}

function obtenerEventosVisitasService(): array
{
    $conn = db();
    if (!$conn) {
        throw new Exception(odbc_errormsg());
    }

    $sql = "
    SELECT
        v.id_visita,
        v.cod_cliente,
        v.cod_seccion,
        v.fecha_visita,
        v.hora_inicio_visita,
        v.hora_fin_visita,
        v.estado_visita,
        c.nombre_comercial,
        s.nombre AS nombre_seccion,
        vp.origen
    FROM cmf_visitas_comerciales v
    LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
    LEFT JOIN secciones_cliente s ON v.cod_seccion = s.cod_seccion AND v.cod_cliente = s.cod_cliente
    LEFT JOIN (
        SELECT vp1.id_visita, vp1.origen
        FROM cmf_visita_pedidos vp1
        INNER JOIN (
            SELECT id_visita, MIN(id_visita_pedido) AS min_id
            FROM cmf_visita_pedidos
            GROUP BY id_visita
        ) vp2 ON vp1.id_visita = vp2.id_visita AND vp1.id_visita_pedido = vp2.min_id
    ) vp ON v.id_visita = vp.id_visita
    ";

    $result = odbc_exec($conn, $sql);
    if (!$result) {
        throw new Exception(odbc_errormsg($conn));
    }

    $events = [];
    while ($row = odbc_fetch_array($result)) {
        if (strtolower((string)$row['estado_visita']) === 'descartada') {
            continue;
        }

        $nombreComercial = isset($row['nombre_comercial']) ? $row['nombre_comercial'] : ('Cliente ' . $row['cod_cliente']);
        $nombreComercial = toUTF8($nombreComercial);

        $title = $nombreComercial;
        if ($row['cod_seccion'] !== null && isset($row['nombre_seccion']) && $row['nombre_seccion'] !== null && $row['nombre_seccion'] !== '') {
            $title .= ' - ' . toUTF8($row['nombre_seccion']);
        }

        $origen = isset($row['origen']) ? $row['origen'] : '';
        $color = determinarColorVisita($row['estado_visita'], $origen);

        $events[] = [
            'id' => $row['id_visita'],
            'title' => $title,
            'start' => $row['fecha_visita'] . 'T' . $row['hora_inicio_visita'],
            'end' => $row['fecha_visita'] . 'T' . $row['hora_fin_visita'],
            'color' => $color,
        ];
    }

    return $events;
}

function obtenerVisitasExistentesService(int $codCliente, $codSeccion, string $fechaVisita): array
{
    $conn = db();
    $sql = "
        SELECT estado_visita
        FROM [integral].[dbo].[cmf_visitas_comerciales]
        WHERE cod_cliente = ?
    ";
    $params = [$codCliente];

    if ($codSeccion !== null) {
        $sql .= " AND cod_seccion = ?";
        $params[] = $codSeccion;
    } else {
        $sql .= " AND cod_seccion IS NULL";
    }

    $sql .= " AND fecha_visita = ?";
    $params[] = $fechaVisita;

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception(odbc_errormsg($conn) ?: odbc_errormsg());
    }

    if (!odbc_execute($stmt, $params)) {
        throw new Exception(odbc_errormsg($conn) ?: odbc_errormsg());
    }

    $estados = [];
    while ($row = odbc_fetch_array($stmt)) {
        $estado = isset($row['estado_visita']) ? trim((string)$row['estado_visita']) : '';
        if ($estado !== '') {
            $estados[] = $estado;
        }
    }

    return [
        'existe' => count($estados) > 0,
        'estados' => $estados,
    ];
}

function actualizarOrigenVisitaService(int $cod_pedido, string $nuevo_origen): array
{
    $conn = db();

    try {
        odbc_autocommit($conn, false);

        $sqlExiste = "SELECT TOP 1 id_visita_pedido FROM [integral].[dbo].[cmf_visita_pedidos] WHERE cod_venta = ?";
        $stmtExiste = odbc_prepare($conn, $sqlExiste);
        if (!$stmtExiste) {
            throw new Exception('Error al preparar validacion de relacion visita-pedido: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtExiste, [$cod_pedido])) {
            throw new Exception('Error al validar relacion visita-pedido: ' . odbc_errormsg($conn));
        }
        $existeRelacion = odbc_fetch_array($stmtExiste);
        if (!$existeRelacion) {
            throw new Exception('No se encontro relacion visita-pedido para ese codigo. Haz el cambio desde el planificador.');
        }

        $ctx = asegurarRelacionVisitaPedido($conn, $cod_pedido, $nuevo_origen);

        $origen_nuevo_lc = (string)$ctx['origen_nuevo'];
        $origen_anterior = (string)$ctx['origen_anterior'];
        $cod_cliente = (int)$ctx['cod_cliente'];
        $cod_seccion = $ctx['cod_seccion'];

        if ($cod_cliente > 0 && ($origen_nuevo_lc === 'visita' || $origen_anterior === 'visita')) {
            recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
        }

        odbc_commit($conn);
        return ['ok' => true];
    } catch (Exception $e) {
        odbc_rollback($conn);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function quitarPedidoVisitaService(int $cod_pedido): array
{
    $conn = db();

    try {
        odbc_autocommit($conn, false);

        $cod_cliente = null;
        $cod_seccion = null;

        $sqlContexto = "
            SELECT TOP 1 vc.cod_cliente, vc.cod_seccion
            FROM [integral].[dbo].[cmf_visita_pedidos] vp
            INNER JOIN [integral].[dbo].[cmf_visitas_comerciales] vc ON vc.id_visita = vp.id_visita
            WHERE vp.cod_venta = ?
            ORDER BY vp.id_visita_pedido ASC
        ";

        $stmtContexto = odbc_prepare($conn, $sqlContexto);
        if (!$stmtContexto) {
            throw new Exception('Error al preparar contexto: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtContexto, [$cod_pedido])) {
            throw new Exception('Error al obtener contexto: ' . odbc_errormsg($conn));
        }

        $ctx = odbc_fetch_array($stmtContexto);
        if ($ctx) {
            $cod_cliente = isset($ctx['cod_cliente']) ? (int)$ctx['cod_cliente'] : null;
            $cod_seccion = (array_key_exists('cod_seccion', $ctx) && $ctx['cod_seccion'] !== null && $ctx['cod_seccion'] !== '')
                ? (int)$ctx['cod_seccion']
                : null;
        }

        $sqlDelete = "
            DELETE FROM [integral].[dbo].[cmf_visita_pedidos]
            WHERE cod_venta = ?
        ";

        $stmtDelete = odbc_prepare($conn, $sqlDelete);
        if (!$stmtDelete) {
            throw new Exception('Error al preparar borrado: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtDelete, [$cod_pedido])) {
            throw new Exception('Error al borrar pedido de visita: ' . odbc_errormsg($conn));
        }

        if (!is_null($cod_cliente) && $cod_cliente > 0) {
            recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion);
        }

        odbc_commit($conn);
        return ['ok' => true];
    } catch (Exception $e) {
        odbc_rollback($conn);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function asociarVisitaService(int $cod_venta, string $origen, int $codigo_vendedor): array
{
    $conn = db();

    $sql_visita = "
    SELECT v.cod_visita
    FROM cmf_visitas_comerciales v
    JOIN hist_ventas_cabecera h ON v.cod_cliente = h.cod_cliente
    WHERE h.cod_venta = ? AND v.fecha_visita IS NOT NULL
    ORDER BY v.fecha_visita DESC
    LIMIT 1
    ";

    $stmt = odbc_prepare($conn, $sql_visita);
    if (!$stmt) {
        throw new RuntimeException('No se pudo consultar la visita.');
    }

    if (!odbc_execute($stmt, [$cod_venta])) {
        throw new RuntimeException('No se pudo consultar la visita.');
    }

    $visita = odbc_fetch_array($stmt);
    odbc_free_result($stmt);

    if ($visita) {
        $sql_asociar = "
        INSERT INTO cmf_visita_pedidos (cod_visita, cod_venta, origen)
        VALUES (?, ?, ?)
        ";

        $stmt = odbc_prepare($conn, $sql_asociar);
        if (!$stmt) {
            throw new RuntimeException('No se pudo asociar el pedido.');
        }

        if (!odbc_execute($stmt, [$visita['cod_visita'], $cod_venta, $origen])) {
            throw new RuntimeException('No se pudo asociar el pedido.');
        }

        return ['ok' => true, 'message' => 'Pedido asociado a visita existente: ' . $origen];
    }

    $fecha_visita = date('Y-m-d H:i:s');
    $sql_crear_visita = "
    INSERT INTO cmf_visitas_comerciales (cod_cliente, cod_vendedor, origen, fecha_visita)
    VALUES (
        (SELECT cod_cliente FROM hist_ventas_cabecera WHERE cod_venta = ? LIMIT 1),
        ?, ?, ?
    )";

    $stmt = odbc_prepare($conn, $sql_crear_visita);
    if (!$stmt) {
        throw new RuntimeException('No se pudo crear la visita.');
    }

    if (!odbc_execute($stmt, [$cod_venta, $codigo_vendedor, $origen, $fecha_visita])) {
        throw new RuntimeException('No se pudo crear la visita.');
    }

    $cod_visita = odbc_insert_id($conn);

    $sql_asociar = "
    INSERT INTO cmf_visita_pedidos (cod_visita, cod_venta, origen)
    VALUES (?, ?, ?)
    ";

    $stmt = odbc_prepare($conn, $sql_asociar);
    if (!$stmt) {
        throw new RuntimeException('No se pudo asociar el pedido.');
    }

    if (!odbc_execute($stmt, [$cod_visita, $cod_venta, $origen])) {
        throw new RuntimeException('No se pudo asociar el pedido.');
    }

    return ['ok' => true, 'message' => 'Nueva visita creada y asociada al pedido: ' . $origen];
}
