<?php

if (!function_exists('planificadorRepoCompararNombreCliente')) {
    function planificadorRepoCompararNombreCliente($a, $b): int
    {
        return strcmp($a['nombre_cliente'], $b['nombre_cliente']);
    }
}

if (!function_exists('planificadorRepoCompararNombreClienteYSeccion')) {
    function planificadorRepoCompararNombreClienteYSeccion($a, $b): int
    {
        $resultado = strcmp($a['nombre_cliente'], $b['nombre_cliente']);
        if ($resultado === 0) {
            $resultado = strcmp($a['nombre_seccion'], $b['nombre_seccion']);
        }
        return $resultado;
    }
}

if (!function_exists('planificadorRepoObtenerNombreCliente')) {
    function planificadorRepoObtenerNombreCliente($cod_cliente): ?array
    {
        $conn = db();
        $cod_cliente = intval($cod_cliente);
        $query = "SELECT nombre_comercial FROM clientes WHERE cod_cliente = $cod_cliente";
        $resultado = odbc_exec($conn, $query);

        if (!$resultado) {
            error_log('Error al obtener el nombre del cliente: ' . odbc_errormsg($conn));
            return null;
        }

        $cliente = odbc_fetch_array($resultado);
        return $cliente ?: null;
    }
}

if (!function_exists('planificadorRepoObtenerZonaPorCodigoEditar')) {
    function planificadorRepoObtenerZonaPorCodigoEditar($cod_zona): ?array
    {
        $conn = db();
        $cod_zona = intval($cod_zona);
        $query = "SELECT nombre_zona FROM cmf_comerciales_zonas WHERE cod_zona = $cod_zona";
        $resultado = odbc_exec($conn, $query);

        if (!$resultado) {
            error_log('Error al obtener la zona: ' . odbc_errormsg($conn));
            return null;
        }

        $zona = odbc_fetch_array($resultado);
        return $zona ?: null;
    }
}

if (!function_exists('planificadorRepoObtenerAsignacion')) {
    function planificadorRepoObtenerAsignacion($cod_cliente, $cod_zona, $cod_seccion = null): ?array
    {
        $conn = db();
        $cod_cliente = intval($cod_cliente);
        $cod_zona = intval($cod_zona);
        $condicion_seccion = $cod_seccion === null ? "azc.cod_seccion IS NULL" : "azc.cod_seccion = " . intval($cod_seccion);

        $query = "
            SELECT azc.*,
                   c.nombre_comercial,
                   COALESCE(sc.nombre, 'Sin Sección') AS nombre_seccion,
                   z.nombre_zona
            FROM cmf_comerciales_clientes_zona azc
            JOIN clientes c ON azc.cod_cliente = c.cod_cliente
            LEFT JOIN secciones_cliente sc
                ON azc.cod_cliente = sc.cod_cliente
                AND azc.cod_seccion = sc.cod_seccion
            JOIN cmf_comerciales_zonas z ON azc.zona_principal = z.cod_zona
            WHERE azc.cod_cliente = $cod_cliente
              AND azc.zona_principal = $cod_zona
              AND $condicion_seccion
        ";

        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener la asignación: ' . odbc_errormsg($conn));
            return null;
        }

        $asignacion = odbc_fetch_array($resultado);
        return $asignacion ?: null;
    }
}

if (!function_exists('planificadorRepoActualizarAsignacion')) {
    function planificadorRepoActualizarAsignacion($cod_cliente, $cod_zona, $cod_seccion, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones): bool
    {
        $conn = db();

        $cod_cliente = intval($cod_cliente);
        $cod_zona = intval($cod_zona);
        $condicionCodSeccion = is_null($cod_seccion) ? 'IS NULL' : '= ' . intval($cod_seccion);
        $zona_secundaria = is_null($zona_secundaria) ? 'NULL' : intval($zona_secundaria);
        $tiempo_promedio_visita = is_null($tiempo_promedio_visita) ? 'NULL' : floatval($tiempo_promedio_visita);
        $preferencia_horaria = is_null($preferencia_horaria) ? 'NULL' : "'" . addslashes($preferencia_horaria) . "'";
        $frecuencia_visita = is_null($frecuencia_visita) ? 'NULL' : "'" . addslashes($frecuencia_visita) . "'";
        $observaciones = is_null($observaciones) ? 'NULL' : "'" . addslashes($observaciones) . "'";

        $query = "
            UPDATE cmf_comerciales_clientes_zona
            SET
                zona_secundaria = $zona_secundaria,
                tiempo_promedio_visita = $tiempo_promedio_visita,
                preferencia_horaria = $preferencia_horaria,
                frecuencia_visita = $frecuencia_visita,
                observaciones = $observaciones
            WHERE
                cod_cliente = $cod_cliente
                AND zona_principal = $cod_zona
                AND cod_seccion $condicionCodSeccion
        ";

        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al actualizar la asignación: ' . odbc_errormsg($conn));
            return false;
        }

        return true;
    }
}

if (!function_exists('planificadorRepoObtenerClientesDisponiblesParaAsignar')) {
    function planificadorRepoObtenerClientesDisponiblesParaAsignar($cod_zona, $rutas_asignadas, $cod_vendedor = null): array
    {
        $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : planificadorRepoObtenerCodVendedor();
        $conn = db();

        if (empty($rutas_asignadas)) {
            return [];
        }

        $cod_rutas = [];
        foreach ($rutas_asignadas as $ruta) {
            $cod_rutas[] = intval($ruta['cod_ruta']);
        }
        if (empty($cod_rutas)) {
            return [];
        }

        $cod_rutas_str = implode(',', $cod_rutas);

        $query_secciones_disponibles = "
            SELECT DISTINCT sc.cod_cliente
            FROM secciones_cliente sc
            WHERE sc.cod_cliente IN (
                SELECT c.cod_cliente
                FROM clientes c
                WHERE c.cod_ruta IN ($cod_rutas_str)
                  AND c.cod_vendedor = '$cod_vendedor'
            )
            AND NOT EXISTS (
                SELECT 1 FROM cmf_comerciales_clientes_zona azc
                WHERE azc.cod_cliente = sc.cod_cliente
                  AND azc.cod_seccion = sc.cod_seccion
            )
        ";

        $resultado_secciones = odbc_exec($conn, $query_secciones_disponibles);
        if (!$resultado_secciones) {
            error_log('Error al obtener secciones disponibles: ' . odbc_errormsg($conn));
            return [];
        }

        $clientes_con_secciones_disponibles = [];
        while ($fila = odbc_fetch_array($resultado_secciones)) {
            $clientes_con_secciones_disponibles[] = $fila['cod_cliente'];
        }

        $query_clientes_sin_secciones = "
            SELECT DISTINCT c.cod_cliente, c.nombre_comercial AS nombre_cliente
            FROM clientes c
            WHERE c.cod_ruta IN ($cod_rutas_str)
              AND c.cod_vendedor = '$cod_vendedor'
              AND NOT EXISTS (
                  SELECT 1 FROM secciones_cliente sc
                  WHERE sc.cod_cliente = c.cod_cliente
              )
              AND NOT EXISTS (
                  SELECT 1 FROM cmf_comerciales_clientes_zona azc
                  WHERE azc.cod_cliente = c.cod_cliente
              )
        ";

        $resultado_clientes_sin_secciones = odbc_exec($conn, $query_clientes_sin_secciones);
        if (!$resultado_clientes_sin_secciones) {
            error_log('Error al obtener clientes sin secciones: ' . odbc_errormsg($conn));
            return [];
        }

        $clientes_sin_secciones_disponibles = [];
        while ($fila = odbc_fetch_array($resultado_clientes_sin_secciones)) {
            $clientes_sin_secciones_disponibles[] = [
                'cod_cliente' => $fila['cod_cliente'],
                'nombre_cliente' => $fila['nombre_cliente'],
            ];
        }

        $clientes_con_secciones_disponibles_detalles = [];
        if (!empty($clientes_con_secciones_disponibles)) {
            $clientes_con_secciones_disponibles = array_unique($clientes_con_secciones_disponibles);
            $cod_clientes_str = implode(',', $clientes_con_secciones_disponibles);

            $query_clientes_con_secciones = "
                SELECT DISTINCT c.cod_cliente, c.nombre_comercial AS nombre_cliente
                FROM clientes c
                WHERE c.cod_cliente IN ($cod_clientes_str)
                  AND c.cod_vendedor = '$cod_vendedor'
            ";

            $resultado_clientes_con_secciones = odbc_exec($conn, $query_clientes_con_secciones);
            if (!$resultado_clientes_con_secciones) {
                error_log('Error al obtener detalles de clientes con secciones disponibles: ' . odbc_errormsg($conn));
                return [];
            }

            while ($fila = odbc_fetch_array($resultado_clientes_con_secciones)) {
                $clientes_con_secciones_disponibles_detalles[] = [
                    'cod_cliente' => $fila['cod_cliente'],
                    'nombre_cliente' => $fila['nombre_cliente'],
                ];
            }
        }

        $clientes_disponibles = array_merge($clientes_con_secciones_disponibles_detalles, $clientes_sin_secciones_disponibles);
        usort($clientes_disponibles, 'planificadorRepoCompararNombreCliente');

        return $clientes_disponibles;
    }
}

if (!function_exists('planificadorRepoAsignarClienteZona')) {
    function planificadorRepoAsignarClienteZona($cod_cliente, $cod_seccion, $zona_principal, $zona_secundaria, $tiempo_promedio_visita, $preferencia_horaria, $frecuencia_visita, $observaciones = ''): bool
    {
        $conn = db();

        $cod_cliente = intval($cod_cliente);
        $zona_principal = intval($zona_principal);
        $zona_secundaria = ($zona_secundaria !== 'NULL') ? intval($zona_secundaria) : 'NULL';
        $tiempo_promedio_visita = floatval($tiempo_promedio_visita);
        $preferencia_horaria = addslashes($preferencia_horaria);
        $frecuencia_visita = addslashes($frecuencia_visita);
        $observaciones = addslashes($observaciones);

        if ($cod_seccion !== 'NULL') {
            $cod_seccion = intval($cod_seccion);
        }

        $query_check = "SELECT COUNT(*) AS total FROM cmf_comerciales_clientes_zona
                       WHERE cod_cliente = '$cod_cliente'
                         AND (cod_seccion = " . ($cod_seccion === 'NULL' ? "NULL" : "'$cod_seccion'") . ")
                         AND zona_principal = '$zona_principal'";
        $resultado_check = odbc_exec($conn, $query_check);
        if (!$resultado_check) {
            error_log('Error al verificar asignación existente: ' . odbc_errormsg($conn));
            return false;
        }
        $fila_check = odbc_fetch_array($resultado_check);
        if ($fila_check['total'] > 0) {
            return false;
        }

        $query = "INSERT INTO cmf_comerciales_clientes_zona
                  (cod_cliente, cod_seccion, zona_principal, zona_secundaria, tiempo_promedio_visita, preferencia_horaria, frecuencia_visita, observaciones)
                  VALUES
                  ('$cod_cliente', " .
                  ($cod_seccion === 'NULL' ? "NULL" : "'$cod_seccion'") . ",
                  '$zona_principal',
                  " .
                  ($zona_secundaria === 'NULL' ? 'NULL' : "'$zona_secundaria'") . ",
                  '$tiempo_promedio_visita',
                  '$preferencia_horaria',
                  '$frecuencia_visita',
                  '$observaciones')";

        $result = odbc_exec($conn, $query);
        if (!$result) {
            error_log('Error al asignar el cliente a la zona: ' . odbc_errormsg($conn));
            return false;
        }

        return true;
    }
}

if (!function_exists('planificadorRepoSeccionDisponibleParaAsignacion')) {
    function planificadorRepoSeccionDisponibleParaAsignacion($cod_cliente, $cod_seccion): ?bool
    {
        if ($cod_seccion === 'NULL' || $cod_seccion === null || $cod_seccion === '') {
            return true;
        }

        $conn = db();
        $cod_cliente = intval($cod_cliente);
        $cod_seccion = intval($cod_seccion);

        $query = "SELECT COUNT(*) AS total FROM cmf_comerciales_clientes_zona
                  WHERE cod_cliente = '$cod_cliente' AND cod_seccion = '$cod_seccion'";
        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al verificar la disponibilidad de la seccion: ' . odbc_errormsg($conn));
            return null;
        }

        $fila = odbc_fetch_array($resultado);
        return ((int)($fila['total'] ?? 0)) === 0;
    }
}

if (!function_exists('planificadorRepoBorrarAsignacion')) {
    function planificadorRepoBorrarAsignacion($cod_cliente, $cod_zona, $cod_seccion): bool
    {
        $conn = db();
        $cod_cliente = intval($cod_cliente);
        $cod_zona = intval($cod_zona);
        $condicionSeccion = ($cod_seccion === 'NULL' || $cod_seccion === null || $cod_seccion === '')
            ? 'IS NULL'
            : '= ' . intval($cod_seccion);

        $query = "DELETE FROM cmf_comerciales_clientes_zona
                  WHERE cod_cliente = $cod_cliente
                  AND zona_principal = $cod_zona
                  AND cod_seccion $condicionSeccion";

        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al eliminar la asignacion: ' . odbc_errormsg($conn));
            return false;
        }

        return true;
    }
}

if (!function_exists('planificadorRepoObtenerClientesPorZona')) {
    function planificadorRepoObtenerClientesPorZona($cod_zona): array
    {
        $conn = db();
        $cod_zona = intval($cod_zona);

        $query_clientes_asignados = "
            SELECT
                c.cod_cliente,
                c.nombre_comercial AS nombre_cliente,
                c.poblacion AS poblacion_cliente,
                sc.cod_seccion,
                sc.nombre AS nombre_seccion,
                sc.poblacion AS poblacion_seccion,
                azc.frecuencia_visita,
                azc.observaciones,
                'primaria' AS tipo_asignacion
            FROM cmf_comerciales_clientes_zona azc
            JOIN clientes c ON azc.cod_cliente = c.cod_cliente
            LEFT JOIN secciones_cliente sc
                ON azc.cod_cliente = sc.cod_cliente
                AND azc.cod_seccion = sc.cod_seccion
            WHERE azc.zona_principal = '$cod_zona'

            UNION ALL

            SELECT
                c.cod_cliente,
                c.nombre_comercial AS nombre_cliente,
                c.poblacion AS poblacion_cliente,
                sc.cod_seccion,
                sc.nombre AS nombre_seccion,
                sc.poblacion AS poblacion_seccion,
                azc.frecuencia_visita,
                azc.observaciones,
                'secundaria' AS tipo_asignacion
            FROM cmf_comerciales_clientes_zona azc
            JOIN clientes c ON azc.cod_cliente = c.cod_cliente
            LEFT JOIN secciones_cliente sc
                ON azc.cod_cliente = sc.cod_cliente
                AND azc.cod_seccion = sc.cod_seccion
            WHERE azc.zona_secundaria = '$cod_zona'
        ";

        $resultado_asignados = odbc_exec($conn, $query_clientes_asignados);
        if (!$resultado_asignados) {
            error_log('Error al obtener los clientes asignados: ' . odbc_errormsg($conn));
            return [];
        }

        $clientes_asignados = [];
        while ($fila = odbc_fetch_array($resultado_asignados)) {
            if ($fila['frecuencia_visita'] == 'todos_ciclos') {
                $fila['frecuencia_visita'] = 'Todos';
            } elseif ($fila['frecuencia_visita'] == 'uno_no') {
                $fila['frecuencia_visita'] = 'Cada 2';
            }
            $clientes_asignados[] = $fila;
        }

        usort($clientes_asignados, 'planificadorRepoCompararNombreClienteYSeccion');
        return $clientes_asignados;
    }
}

if (!function_exists('planificadorRepoObtenerClientesPorZonaYRuta')) {
    function planificadorRepoObtenerClientesPorZonaYRuta($cod_zona, $cod_ruta, $cod_vendedor = null): array
    {
        $conn = db();
        $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : planificadorRepoObtenerCodVendedor();
        $cod_zona = intval($cod_zona);
        $cod_ruta = intval($cod_ruta);

        if ($cod_zona <= 0 || $cod_ruta <= 0 || $cod_vendedor <= 0) {
            return [];
        }

        $query = "
            SELECT
                c.cod_cliente,
                c.nombre_comercial AS nombre_cliente,
                c.poblacion AS poblacion_cliente,
                c.cod_ruta,
                NULL AS cod_seccion,
                '' AS nombre_seccion,
                '' AS poblacion_seccion,
                '' AS frecuencia_visita,
                '' AS observaciones,
                '' AS tipo_asignacion
            FROM clientes c
            LEFT JOIN secciones_cliente sc
                ON c.cod_cliente = sc.cod_cliente
            WHERE (c.cod_vendedor IS NULL OR c.cod_vendedor <> '$cod_vendedor')
              AND c.cod_ruta = '$cod_ruta'
              AND c.fecha_baja IS NULL
            GROUP BY
                c.cod_cliente,
                c.nombre_comercial,
                c.poblacion,
                c.cod_ruta
            ORDER BY c.nombre_comercial ASC
        ";

        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener clientes por zona y ruta: ' . odbc_errormsg($conn));
            return [];
        }

        $clientes = [];
        while ($fila = odbc_fetch_array($resultado)) {
            $clientes[] = $fila;
        }

        return $clientes;
    }
}
