<?php

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
    $visitasIndex = [];
    while ($visita = odbc_fetch_array($result)) {
        $clientName = !empty($visita['nombre_comercial']) ? $visita['nombre_comercial'] : 'Cliente ' . $visita['cod_cliente'];
        if (!empty($visita['nombre_seccion'])) {
            $clientName .= ' - ' . $visita['nombre_seccion'];
        }

        $visita['colorVisita'] = determinarColorVisita($visita['estado_visita'], '');
        $visita['clientName'] = $clientName;
        $visita['pedidos'] = [];
        $visitas[] = $visita;
        $visitasIndex[(string) $visita['id_visita']] = count($visitas) - 1;
    }

    if (count($visitas) === 0) {
        return ['fecha' => $fecha, 'visitas' => $visitas];
    }

    $ids = array_keys($visitasIndex);
    $idsSql = "'" . implode("','", array_map('addslashes', $ids)) . "'";

    $sqlOrigenes = "
        SELECT
            t.id_visita,
            t.origen
        FROM (
            SELECT
                vp.id_visita,
                vp.origen,
                ROW_NUMBER() OVER (PARTITION BY vp.id_visita ORDER BY vp.id_visita_pedido ASC) AS rn
            FROM [integral].[dbo].[cmf_visita_pedidos] vp
            WHERE vp.id_visita IN ($idsSql)
        ) t
        WHERE t.rn = 1
    ";
    $resultOrigenes = odbc_exec($conn, $sqlOrigenes);
    if ($resultOrigenes) {
        while ($row = odbc_fetch_array($resultOrigenes)) {
            $idVisita = (string) $row['id_visita'];
            if (!isset($visitasIndex[$idVisita])) {
                continue;
            }
            $idx = $visitasIndex[$idVisita];
            $origenPrincipal = (string) ($row['origen'] ?? '');
            $visitas[$idx]['origenPrincipal'] = $origenPrincipal;
            $visitas[$idx]['colorVisita'] = determinarColorVisita($visitas[$idx]['estado_visita'], $origenPrincipal);
        }
    }

    $sqlPedidos = "
        SELECT
            vp.id_visita,
            vp.cod_venta AS cod_pedido,
            vp.origen,
            hvc.fecha_venta,
            hvc.hora_venta,
            hvc.importe,
            avc.observacion_interna,
            ISNULL(hlc.numero_lineas, 0) AS numero_lineas
        FROM [integral].[dbo].[cmf_visita_pedidos] vp
        INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc
            ON vp.cod_venta = hvc.cod_venta
        LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] avc
            ON hvc.cod_anexo = avc.cod_anexo
        LEFT JOIN (
            SELECT cod_venta, COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[hist_ventas_linea]
            WHERE tipo_venta = 1
            GROUP BY cod_venta
        ) hlc
            ON vp.cod_venta = hlc.cod_venta
        WHERE vp.id_visita IN ($idsSql)
          AND hvc.tipo_venta = 1
        ORDER BY vp.id_visita ASC, vp.id_visita_pedido ASC
    ";
    $resultPedidos = odbc_exec($conn, $sqlPedidos);
    if ($resultPedidos) {
        while ($pedido = odbc_fetch_array($resultPedidos)) {
            $idVisita = (string) $pedido['id_visita'];
            if (!isset($visitasIndex[$idVisita])) {
                continue;
            }
            $pedido['colorPedido'] = determinarColorPedido($pedido['origen']);
            if (!isset($visitas[$visitasIndex[$idVisita]]['importe_total'])) {
                $visitas[$visitasIndex[$idVisita]]['importe_total'] = 0.0;
            }
            if (!isset($visitas[$visitasIndex[$idVisita]]['numero_lineas_total'])) {
                $visitas[$visitasIndex[$idVisita]]['numero_lineas_total'] = 0;
            }
            $visitas[$visitasIndex[$idVisita]]['importe_total'] += (float) ($pedido['importe'] ?? 0);
            $visitas[$visitasIndex[$idVisita]]['numero_lineas_total'] += (int) ($pedido['numero_lineas'] ?? 0);
            $visitas[$visitasIndex[$idVisita]]['pedidos'][] = $pedido;
        }
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

    $sqlPedidosAsociados = "
        SELECT COUNT(*) AS total
        FROM [integral].[dbo].[cmf_visita_pedidos]
        WHERE id_visita = ?
    ";
    $resultPedidosAsociados = visitasServicePrepareExecute($conn, $sqlPedidosAsociados, [$id_visita]);
    $tienePedidosAsociados = false;
    if ($resultPedidosAsociados && odbc_fetch_row($resultPedidosAsociados)) {
        $tienePedidosAsociados = ((int) odbc_result($resultPedidosAsociados, 'total')) > 0;
    }

    $tiempo_promedio = $assignment ? floatval($assignment['tiempo_promedio_visita']) : 0.0;
    $estadoActual = odbc_result($result, 'estado_visita');
    $bloquearCambioEstado = normalizarEstadoVisitaClave((string) $estadoActual) === 'realizada' && $tienePedidosAsociados;

    return [
        'id_visita' => $id_visita,
        'cod_cliente' => $cod_cliente,
        'nombre_comercial' => odbc_result($result, 'nombre_comercial'),
        'cod_seccion' => $cod_seccion,
        'fecha_visita' => odbc_result($result, 'fecha_visita'),
        'hora_inicio_visita' => (($value = odbc_result($result, 'hora_inicio_visita')) ? substr($value, 0, 5) : ''),
        'hora_fin_visita' => (($value = odbc_result($result, 'hora_fin_visita')) ? substr($value, 0, 5) : ''),
        'observaciones' => odbc_result($result, 'observaciones'),
        'estado_visita' => $estadoActual,
        'hora_inicio_manana' => ($assignment && !empty($assignment['hora_inicio_manana'])) ? substr($assignment['hora_inicio_manana'], 0, 5) : '',
        'hora_fin_manana' => ($assignment && !empty($assignment['hora_fin_manana'])) ? substr($assignment['hora_fin_manana'], 0, 5) : '',
        'hora_inicio_tarde' => ($assignment && !empty($assignment['hora_inicio_tarde'])) ? substr($assignment['hora_inicio_tarde'], 0, 5) : '',
        'hora_fin_tarde' => ($assignment && !empty($assignment['hora_fin_tarde'])) ? substr($assignment['hora_fin_tarde'], 0, 5) : '',
        'tiempo_promedio_minutes' => $tiempo_promedio * 60,
        'tiene_pedidos_asociados' => $tienePedidosAsociados,
        'bloquear_cambio_estado' => $bloquearCambioEstado,
    ];
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
