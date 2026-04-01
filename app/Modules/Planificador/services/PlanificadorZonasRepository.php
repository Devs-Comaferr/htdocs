<?php

if (!function_exists('planificadorRepoObtenerCodVendedor')) {
    function planificadorRepoObtenerCodVendedor(): int
    {
        return isset($_SESSION['codigo']) ? intval($_SESSION['codigo']) : 0;
    }
}

if (!function_exists('planificadorRepoCrearZonaVisita')) {
    function planificadorRepoCrearZonaVisita($nombre_zona, $descripcion, $duracion_semanas, $orden, $cod_vendedor = null): bool
    {
        $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : planificadorRepoObtenerCodVendedor();
        $conn = db();

        $nombre_zona = addslashes($nombre_zona);
        $descripcion = addslashes($descripcion);
        $duracion_semanas = intval($duracion_semanas);
        $orden = intval($orden);

        $query = "INSERT INTO cmf_zonas_visita (cod_vendedor, nombre_zona, descripcion, duracion_semanas, orden)
                  VALUES ('$cod_vendedor', '$nombre_zona', '$descripcion', '$duracion_semanas', '$orden')";

        $result = odbc_exec($conn, $query);
        if (!$result) {
            error_log('Error al crear la zona de visita: ' . odbc_errormsg($conn));
            return false;
        }
        return true;
    }
}

if (!function_exists('planificadorRepoObtenerZonasVisita')) {
    function planificadorRepoObtenerZonasVisita($cod_vendedor = null): array
    {
        $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : planificadorRepoObtenerCodVendedor();
        $conn = db();

        $query = "SELECT cod_zona, nombre_zona, descripcion, duracion_semanas, orden
                  FROM cmf_zonas_visita
                  WHERE cod_vendedor = '$cod_vendedor'
                  ORDER BY orden ASC";

        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener zonas de visita: ' . odbc_errormsg($conn));
            return [];
        }

        $zonas = [];
        while ($fila = odbc_fetch_array($resultado)) {
            $zonas[] = $fila;
        }

        return $zonas;
    }
}

if (!function_exists('planificadorRepoObtenerZonaPorCodigo')) {
    function planificadorRepoObtenerZonaPorCodigo($cod_zona, $cod_vendedor = null): ?array
    {
        $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : planificadorRepoObtenerCodVendedor();
        $conn = db();
        $cod_zona = intval($cod_zona);

        $query = "SELECT * FROM cmf_zonas_visita WHERE cod_zona = $cod_zona AND cod_vendedor = '$cod_vendedor'";
        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener la zona: ' . odbc_errormsg($conn));
            return null;
        }

        $zona = odbc_fetch_array($resultado);
        return $zona ?: null;
    }
}

if (!function_exists('planificadorRepoObtenerRutasPorZona')) {
    function planificadorRepoObtenerRutasPorZona($cod_zona): array
    {
        $conn = db();
        $cod_zona = intval($cod_zona);

        $query = "SELECT r.cod_ruta, COALESCE(r.descripcion, 'Sin DescripciÃ³n') AS nombre_ruta
                  FROM cmf_zonas_rutas czr
                  JOIN rutas r ON czr.cod_ruta = r.cod_ruta
                  WHERE czr.cod_zona = $cod_zona
                  ORDER BY r.descripcion ASC";

        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener rutas: ' . odbc_errormsg($conn));
            return [];
        }

        $rutas = [];
        while ($fila = odbc_fetch_array($resultado)) {
            $rutas[] = $fila;
        }

        return $rutas;
    }
}

if (!function_exists('planificadorRepoObtenerTodasRutas')) {
    function planificadorRepoObtenerTodasRutas(): array
    {
        $conn = db();

        $query = "SELECT DISTINCT r.cod_ruta, COALESCE(r.descripcion, 'Sin DescripciÃ³n') AS nombre_ruta
                  FROM rutas r
                  ORDER BY nombre_ruta ASC";
        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener todas las rutas: ' . odbc_errormsg($conn));
            return [];
        }

        $rutas = [];
        while ($fila = odbc_fetch_array($resultado)) {
            $rutas[] = $fila;
        }

        return $rutas;
    }
}

if (!function_exists('planificadorRepoAsignarRutaZona')) {
    function planificadorRepoAsignarRutaZona($cod_zona, $cod_ruta): bool
    {
        $conn = db();
        $cod_zona = intval($cod_zona);
        $cod_ruta = intval($cod_ruta);

        if ($cod_zona <= 0 || $cod_ruta <= 0) {
            return false;
        }

        $query_check = "SELECT COUNT(*) AS total
                        FROM cmf_zonas_rutas
                        WHERE cod_zona = '$cod_zona' AND cod_ruta = '$cod_ruta'";
        $resultado_check = odbc_exec($conn, $query_check);
        if (!$resultado_check) {
            return false;
        }

        $fila_check = odbc_fetch_array($resultado_check);
        if ($fila_check && intval($fila_check['total']) > 0) {
            return false;
        }

        $query = "INSERT INTO cmf_zonas_rutas (cod_zona, cod_ruta)
                  VALUES ('$cod_zona', '$cod_ruta')";
        $resultado = odbc_exec($conn, $query);
        return (bool)$resultado;
    }
}

if (!function_exists('planificadorRepoZonaTieneRutas')) {
    function planificadorRepoZonaTieneRutas($cod_zona): bool
    {
        $conn = db();
        $cod_zona = intval($cod_zona);
        if ($cod_zona <= 0) {
            return false;
        }

        $stmt = odbc_prepare($conn, "SELECT COUNT(*) AS total FROM cmf_zonas_rutas WHERE cod_zona = ?");
        if (!$stmt || !odbc_execute($stmt, [$cod_zona])) {
            return false;
        }

        $fila = odbc_fetch_array($stmt);
        return $fila && (int)($fila['total'] ?? $fila['TOTAL'] ?? 0) > 0;
    }
}

if (!function_exists('planificadorRepoZonaTieneClientesAsignados')) {
    function planificadorRepoZonaTieneClientesAsignados($cod_zona): bool
    {
        $conn = db();
        $cod_zona = intval($cod_zona);
        if ($cod_zona <= 0) {
            return false;
        }

        $stmt = odbc_prepare($conn, "SELECT COUNT(*) AS total FROM cmf_asignacion_zonas_clientes WHERE zona_principal = ? OR zona_secundaria = ?");
        if (!$stmt || !odbc_execute($stmt, [$cod_zona, $cod_zona])) {
            return false;
        }

        $fila = odbc_fetch_array($stmt);
        return $fila && (int)($fila['total'] ?? $fila['TOTAL'] ?? 0) > 0;
    }
}

if (!function_exists('planificadorRepoRutaZonaTieneClientesAsignados')) {
    function planificadorRepoRutaZonaTieneClientesAsignados($cod_zona, $cod_ruta): bool
    {
        $conn = db();
        $cod_zona = intval($cod_zona);
        $cod_ruta = intval($cod_ruta);
        if ($cod_zona <= 0 || $cod_ruta <= 0) {
            return false;
        }

        $sql = "
            SELECT COUNT(*) AS total
            FROM cmf_asignacion_zonas_clientes azc
            INNER JOIN clientes c
                ON c.cod_cliente = azc.cod_cliente
            WHERE (azc.zona_principal = ? OR azc.zona_secundaria = ?)
              AND c.cod_ruta = ?
        ";
        $stmt = odbc_prepare($conn, $sql);
        if (!$stmt || !odbc_execute($stmt, [$cod_zona, $cod_zona, $cod_ruta])) {
            return false;
        }

        $fila = odbc_fetch_array($stmt);
        return $fila && (int)($fila['total'] ?? $fila['TOTAL'] ?? 0) > 0;
    }
}

if (!function_exists('planificadorRepoEliminarRutaZona')) {
    function planificadorRepoEliminarRutaZona($cod_zona, $cod_ruta): bool
    {
        $conn = db();
        $cod_zona = intval($cod_zona);
        $cod_ruta = intval($cod_ruta);
        if ($cod_zona <= 0 || $cod_ruta <= 0) {
            return false;
        }

        $stmt = odbc_prepare($conn, "DELETE FROM cmf_zonas_rutas WHERE cod_zona = ? AND cod_ruta = ?");
        if (!$stmt) {
            return false;
        }

        return odbc_execute($stmt, [$cod_zona, $cod_ruta]);
    }
}

if (!function_exists('planificadorRepoEliminarRutaZonaSegura')) {
    function planificadorRepoEliminarRutaZonaSegura($cod_zona, $cod_ruta): array
    {
        $cod_zona = intval($cod_zona);
        $cod_ruta = intval($cod_ruta);

        if ($cod_zona <= 0 || $cod_ruta <= 0) {
            return ['ok' => false, 'message' => 'Ruta o zona no validas.'];
        }
        if (planificadorRepoRutaZonaTieneClientesAsignados($cod_zona, $cod_ruta)) {
            return ['ok' => false, 'message' => 'No se puede quitar la ruta porque tiene clientes asignados en esta zona.'];
        }
        if (!planificadorRepoEliminarRutaZona($cod_zona, $cod_ruta)) {
            return ['ok' => false, 'message' => 'No se pudo eliminar la ruta de la zona.'];
        }

        return ['ok' => true, 'message' => 'Ruta eliminada correctamente.'];
    }
}

if (!function_exists('planificadorRepoEliminarZonaSegura')) {
    function planificadorRepoEliminarZonaSegura($cod_zona, $cod_vendedor = null): array
    {
        $conn = db();
        $cod_zona = intval($cod_zona);
        $cod_vendedor = $cod_vendedor !== null ? intval($cod_vendedor) : planificadorRepoObtenerCodVendedor();

        if ($cod_zona <= 0 || $cod_vendedor <= 0) {
            return ['ok' => false, 'message' => 'Zona no válida.'];
        }
        if (planificadorRepoZonaTieneRutas($cod_zona)) {
            return ['ok' => false, 'message' => 'No se puede eliminar la zona porque tiene rutas asignadas.'];
        }
        if (planificadorRepoZonaTieneClientesAsignados($cod_zona)) {
            return ['ok' => false, 'message' => 'No se puede eliminar la zona porque tiene clientes asignados.'];
        }

        $stmt = odbc_prepare($conn, "DELETE FROM cmf_zonas_visita WHERE cod_zona = ? AND cod_vendedor = ?");
        if (!$stmt) {
            return ['ok' => false, 'message' => 'No se pudo preparar la eliminación de la zona.'];
        }
        $ok = odbc_execute($stmt, [$cod_zona, $cod_vendedor]);
        if (!$ok) {
            return ['ok' => false, 'message' => 'No se pudo eliminar la zona.'];
        }

        if (function_exists('odbc_num_rows')) {
            $filas = @odbc_num_rows($stmt);
            if (is_int($filas) && $filas === 0) {
                return ['ok' => false, 'message' => 'La zona no existe o no pertenece al vendedor actual.'];
            }
        }

        return ['ok' => true, 'message' => 'Zona eliminada correctamente.'];
    }
}

if (!function_exists('planificadorRepoObtenerSeccionesPorCliente')) {
    function planificadorRepoObtenerSeccionesPorCliente($cod_cliente): array
    {
        $conn = db();
        $cod_cliente = intval($cod_cliente);

        $query = "SELECT sc.cod_seccion, sc.nombre
                  FROM secciones_cliente sc
                  WHERE sc.cod_cliente = '$cod_cliente'
                    AND sc.cod_seccion NOT IN (
                        SELECT azc.cod_seccion
                        FROM cmf_asignacion_zonas_clientes azc
                        WHERE azc.cod_cliente = sc.cod_cliente
                    )
                  ORDER BY sc.cod_seccion ASC";

        $resultado = odbc_exec($conn, $query);
        if (!$resultado) {
            error_log('Error al obtener secciones: ' . odbc_errormsg($conn));
            return [];
        }

        $secciones = [];
        while ($fila = odbc_fetch_array($resultado)) {
            $secciones[] = $fila;
        }

        return $secciones;
    }
}
