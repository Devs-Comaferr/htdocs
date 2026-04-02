<?php

/**
 * Normaliza el origen al formato que se guarda en BD.
 */
if (!function_exists('normalizarOrigenPedidoDb')) {
    function normalizarOrigenPedidoDb(string $origen): string
    {
        $lc = strtolower(trim($origen));
        if ($lc === 'visita') {
            return 'Visita';
        }
        if ($lc === 'telÃ©fono' || $lc === 'telefono') {
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

if (!function_exists('recalcularTiempoPromedioVisita')) {
    function recalcularTiempoPromedioVisita($conn, $cod_cliente, $cod_seccion = null): float
    {
        $cod_cliente = (int)$cod_cliente;
        if ($cod_cliente <= 0) {
            throw new Exception('Cod cliente invÃ¡lido para recalcular promedio.');
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
            $cod_seccion = (int)$cod_seccion;

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
        $horaFinNorm = $normalizarHora($hora_fin_visita);

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
            throw new Exception('ID de visita invÃ¡lido al crear asignaciÃ³n.');
        }
        return $id_visita;
    }
}

/**
 * Inserta o actualiza la relacion visita-pedido.
 */
if (!function_exists('upsertRelacionVisitaPedido')) {
    function upsertRelacionVisitaPedido($conn, int $id_visita, int $cod_venta, string $origen): void
    {
        if ($id_visita <= 0 || $cod_venta <= 0) {
            throw new Exception('Datos invÃ¡lidos para relaciÃ³n visita-pedido.');
        }

        $origenDb = normalizarOrigenPedidoDb($origen);
        $sqlRel = "SELECT TOP 1 id_visita_pedido FROM [integral].[dbo].[cmf_visita_pedidos] WHERE cod_venta = ?";
        $stmtRel = odbc_prepare($conn, $sqlRel);
        if (!$stmtRel) {
            throw new Exception('Error al preparar consulta de relaciÃ³n visita-pedido: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtRel, [$cod_venta])) {
            throw new Exception('Error al consultar relaciÃ³n visita-pedido: ' . odbc_errormsg($conn));
        }
        $rel = odbc_fetch_array_utf8($stmtRel);

        if ($rel) {
            $sqlUpd = "UPDATE [integral].[dbo].[cmf_visita_pedidos] SET id_visita = ?, origen = ? WHERE cod_venta = ?";
            $stmtUpd = odbc_prepare($conn, $sqlUpd);
            if (!$stmtUpd) {
                throw new Exception('Error al preparar update visita-pedido: ' . odbc_errormsg($conn));
            }
            if (!odbc_execute($stmtUpd, [$id_visita, $origenDb, $cod_venta])) {
                throw new Exception('Error al actualizar relaciÃ³n visita-pedido: ' . odbc_errormsg($conn));
            }
            return;
        }

        $sqlIns = "INSERT INTO [integral].[dbo].[cmf_visita_pedidos] (id_visita, cod_venta, origen) VALUES (?, ?, ?)";
        $stmtIns = odbc_prepare($conn, $sqlIns);
        if (!$stmtIns) {
            throw new Exception('Error al preparar insercion visita-pedido: ' . odbc_errormsg($conn));
        }
        if (!odbc_execute($stmtIns, [$id_visita, $cod_venta, $origenDb])) {
            throw new Exception('Error al insertar relaciÃ³n visita-pedido: ' . odbc_errormsg($conn));
        }
    }
}

/**
 * Asegura que exista relacion visita-pedido y aplica origen.
 */
if (!function_exists('asegurarRelacionVisitaPedido')) {
    function asegurarRelacionVisitaPedido($conn, int $cod_venta, string $origen, array $opciones = []): array
    {
        if ($cod_venta <= 0) {
            throw new Exception('Cod venta invÃ¡lido.');
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
        $relacionCreada = false;

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
                $relacionCreada = true;
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
                $horaFin = array_key_exists('hora_fin_visita', $opciones)
                    ? (($opciones['hora_fin_visita'] === null || $opciones['hora_fin_visita'] === '') ? null : (string)$opciones['hora_fin_visita'])
                    : $horaInicio;
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
                $relacionCreada = true;
            }
        }

        return [
            'id_visita' => $idVisita,
            'cod_cliente' => $codCliente,
            'cod_seccion' => $codSeccion,
            'origen_anterior' => $origenAnterior,
            'origen_nuevo' => strtolower($origenDb),
            'relacion_creada' => $relacionCreada,
        ];
    }
}
