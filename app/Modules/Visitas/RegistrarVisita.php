<?php

require_once BASE_PATH . '/app/Support/db.php';

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
        appLogTechnicalError('registrar_visita_manual.prepare', odbc_errormsg($conn) ?: odbc_errormsg());
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
        appLogTechnicalError('registrar_visita_manual.execute', odbc_errormsg($conn) ?: odbc_errormsg());
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
