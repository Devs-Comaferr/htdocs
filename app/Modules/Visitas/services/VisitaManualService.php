<?php

require_once __DIR__ . '/VisitasValidationService.php';
require_once __DIR__ . '/../../../Support/VisitasSupport.php';

if (!function_exists('visitaManualEscapeString')) {
    function visitaManualEscapeString(string $str): string
    {
        return str_replace("'", "''", $str);
    }
}

if (!function_exists('visitaManualInsertarVisitaBase')) {
    function visitaManualInsertarVisitaBase($conn, int $cod_cliente, $cod_seccion, int $cod_vendedor, string $fecha_visita, string $hora_inicio_visita, ?string $hora_fin_visita, ?string $observaciones): int
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
}

if (!function_exists('visitaManualRegistrar')) {
    function visitaManualRegistrar(array $data, bool $forzar = false): array
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
                FROM [integral].[dbo].[cmf_comerciales_visitas]
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
            FROM [integral].[dbo].[cmf_comerciales_visitas]
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

        $sql_insert = "INSERT INTO [integral].[dbo].[cmf_comerciales_visitas]
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
}

if (!function_exists('visitaManualProcesarRegistro')) {
    function visitaManualProcesarRegistro(array $data): array
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
                        throw new Exception('La visita estÃƒÆ’Ã‚Â¡ fuera del horario del cliente');
                    }

                    $sqlUpdate = "
                        UPDATE [integral].[dbo].[cmf_comerciales_visitas]
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
                        throw new Exception('La visita estÃƒÆ’Ã‚Â¡ fuera del horario del cliente');
                    }

                    visitaManualInsertarVisitaBase(
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
                        throw new Exception('La visita estÃƒÆ’Ã‚Â¡ fuera del horario del cliente');
                    }

                    asegurarRelacionVisitaPedido($conn, $cod_venta, 'Visita', [
                        'id_visita' => $previous_id_visita,
                        'cod_cliente' => $cod_cliente,
                        'cod_seccion' => $cod_seccion,
                    ]);

                    $sqlUpdate = "
                        UPDATE [integral].[dbo].[cmf_comerciales_visitas]
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
                        throw new Exception('La visita estÃƒÆ’Ã‚Â¡ fuera del horario del cliente');
                    }

                    $idVisita = visitaManualInsertarVisitaBase(
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
}
