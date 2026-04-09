<?php
declare(strict_types=1);

if (!function_exists('importadorFestivosNormalizarTexto')) {
    function importadorFestivosNormalizarTexto(?string $texto): string
    {
        $texto = importadorFestivosTextoUtf8($texto);
        return mb_strtoupper(trim($texto), 'UTF-8');
    }
}

if (!function_exists('importadorFestivosTextoUtf8')) {
    function importadorFestivosTextoUtf8(?string $texto): string
    {
        $texto = (string)$texto;

        if ($texto === '') {
            return '';
        }

        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }

        $texto = preg_replace('/\s+/u', ' ', trim($texto));
        return is_string($texto) ? $texto : '';
    }
}

if (!function_exists('normalizarComparacion')) {
    function normalizarComparacion(?string $texto): string
    {
        $texto = importadorFestivosTextoUtf8($texto);
        $texto = mb_strtoupper($texto, 'UTF-8');

        $reemplazos = [
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
        ];

        return strtr($texto, $reemplazos);
    }
}

if (!function_exists('normalizarMunicipioINE')) {
    function normalizarMunicipioINE(?string $municipio): string
    {
        $municipioNormalizado = importadorFestivosTextoUtf8($municipio);
        if ($municipioNormalizado === '') {
            return '';
        }

        $municipioNormalizado = mb_strtoupper($municipioNormalizado, 'UTF-8');
        $articulos = ['EL', 'LA', 'LOS', 'LAS'];

        foreach ($articulos as $articulo) {
            $prefijo = $articulo . ' ';
            if (strpos($municipioNormalizado, $prefijo) === 0) {
                $resto = trim(substr($municipioNormalizado, strlen($prefijo)));
                return $resto !== '' ? $resto . ', ' . $articulo : $municipioNormalizado;
            }
        }

        return $municipioNormalizado;
    }
}

if (!function_exists('obtenerCodProvinciaDesdeNombre')) {
    function obtenerCodProvinciaDesdeNombre(?string $nombre): ?string
    {
        $mapa = [
            'ALMERIA' => '04',
            'CADIZ' => '11',
            'CORDOBA' => '14',
            'GRANADA' => '18',
            'HUELVA' => '21',
            'JAEN' => '23',
            'MALAGA' => '29',
            'SEVILLA' => '41',
        ];

        $nombreNormalizado = normalizarComparacion($nombre);
        return $mapa[$nombreNormalizado] ?? null;
    }
}

if (!function_exists('importadorFestivosConvertirFechaJson')) {
    function importadorFestivosConvertirFechaJson($valor): ?string
    {
        $texto = trim((string)$valor);
        if (!preg_match('/^\d{8}$/', $texto)) {
            return null;
        }

        $anio = substr($texto, 0, 4);
        $mes = substr($texto, 4, 2);
        $dia = substr($texto, 6, 2);
        $fecha = $anio . '-' . $mes . '-' . $dia;

        return validarFechaSQL($fecha) ? $fecha : null;
    }
}

if (!function_exists('importadorFestivosResolverAmbito')) {
    function importadorFestivosResolverAmbito(?string $type): ?string
    {
        $typeNormalizado = importadorFestivosNormalizarTexto($type);
        if ($typeNormalizado === 'LABORAL') {
            return 'NACIONAL';
        }
        if ($typeNormalizado === 'LOCAL') {
            return 'LOCAL';
        }

        return null;
    }
}

if (!function_exists('importadorFestivosClaveDuplicado')) {
    function importadorFestivosClaveDuplicado(string $fecha, string $ambito, ?string $provincia, ?string $poblacion): string
    {
        return implode('|', [
            $fecha,
            normalizarComparacion($ambito),
            normalizarComparacion($provincia),
            normalizarComparacion($poblacion),
        ]);
    }
}

if (!function_exists('importadorFestivosObtenerClavesExistentes')) {
    function importadorFestivosObtenerClavesExistentes($conn, string $fecha, string $ambito): array
    {
        $cacheKey = $fecha . '|' . normalizarComparacion($ambito);
        if (!isset($GLOBALS['importador_festivos_cache_existentes']) || !is_array($GLOBALS['importador_festivos_cache_existentes'])) {
            $GLOBALS['importador_festivos_cache_existentes'] = [];
        }

        if (isset($GLOBALS['importador_festivos_cache_existentes'][$cacheKey])) {
            return $GLOBALS['importador_festivos_cache_existentes'][$cacheKey];
        }

        $sql = "
            SELECT provincia, poblacion
            FROM cmf_comerciales_calendario_festivos
            WHERE fecha = ?
              AND LTRIM(RTRIM(ambito)) COLLATE Latin1_General_CI_AI = ? COLLATE Latin1_General_CI_AI
        ";
        $stmt = odbc_prepare($conn, $sql);
        if (!$stmt) {
            $GLOBALS['importador_festivos_cache_existentes'][$cacheKey] = [];
            return $GLOBALS['importador_festivos_cache_existentes'][$cacheKey];
        }

        $ambitoDb = mb_convert_encoding($ambito, 'Windows-1252', 'UTF-8');
        if (!odbc_execute($stmt, [$fecha, $ambitoDb])) {
            $GLOBALS['importador_festivos_cache_existentes'][$cacheKey] = [];
            return $GLOBALS['importador_festivos_cache_existentes'][$cacheKey];
        }

        $claves = [];
        while ($row = odbc_fetch_array($stmt)) {
            $provinciaBd = importadorFestivosTextoUtf8((string)($row['provincia'] ?? ''));
            $poblacionBd = importadorFestivosTextoUtf8((string)($row['poblacion'] ?? ''));
            $clave = importadorFestivosClaveDuplicado(
                $fecha,
                $ambito,
                $provinciaBd !== '' ? $provinciaBd : null,
                $poblacionBd !== '' ? $poblacionBd : null
            );
            $claves[$clave] = true;
        }

        $GLOBALS['importador_festivos_cache_existentes'][$cacheKey] = $claves;
        return $GLOBALS['importador_festivos_cache_existentes'][$cacheKey];
    }
}

if (!function_exists('importadorFestivosRegistrarClaveExistente')) {
    function importadorFestivosRegistrarClaveExistente(string $fecha, string $ambito, ?string $provincia, ?string $poblacion): void
    {
        $cacheKey = $fecha . '|' . normalizarComparacion($ambito);
        if (!isset($GLOBALS['importador_festivos_cache_existentes']) || !is_array($GLOBALS['importador_festivos_cache_existentes'])) {
            $GLOBALS['importador_festivos_cache_existentes'] = [];
        }

        if (!isset($GLOBALS['importador_festivos_cache_existentes'][$cacheKey]) || !is_array($GLOBALS['importador_festivos_cache_existentes'][$cacheKey])) {
            $GLOBALS['importador_festivos_cache_existentes'][$cacheKey] = [];
        }

        $clave = importadorFestivosClaveDuplicado($fecha, $ambito, $provincia, $poblacion);
        $GLOBALS['importador_festivos_cache_existentes'][$cacheKey][$clave] = true;
    }
}

if (!function_exists('importadorFestivosBuscarCodMunicipioIne')) {
    function importadorFestivosBuscarCodMunicipioIne($conn, ?string $municipio, ?string $provincia): ?string
    {
        $municipioNormalizado = normalizarMunicipioINE($municipio);
        $codProvincia = obtenerCodProvinciaDesdeNombre($provincia);

        if ($municipioNormalizado === '' || $codProvincia === null) {
            return null;
        }

        $sql = "
            SELECT cod_provincia, cod_municipio, poblacion
            FROM municipios_ine
            WHERE cod_provincia = ?
        ";
        $stmt = odbc_prepare($conn, $sql);
        if (!$stmt || !odbc_execute($stmt, [$codProvincia])) {
            return null;
        }

        $municipioBuscado = normalizarComparacion($municipioNormalizado);

        while ($row = odbc_fetch_array($stmt)) {
            $poblacionBd = importadorFestivosTextoUtf8((string)($row['poblacion'] ?? ''));
            if ($poblacionBd === '') {
                continue;
            }

            if (normalizarComparacion($poblacionBd) !== $municipioBuscado) {
                continue;
            }

            $codProvinciaBd = str_pad(trim((string)($row['cod_provincia'] ?? '')), 2, '0', STR_PAD_LEFT);
            $codMunicipioBd = str_pad(trim((string)($row['cod_municipio'] ?? '')), 3, '0', STR_PAD_LEFT);

            if ($codProvinciaBd === '' || $codMunicipioBd === '') {
                return null;
            }

            return $codProvinciaBd . $codMunicipioBd;
        }

        return null;
    }
}

if (!function_exists('importadorFestivosExisteRegistro')) {
    function importadorFestivosExisteRegistro($conn, string $fecha, string $ambito, ?string $provincia, ?string $poblacion): bool
    {
        $clave = importadorFestivosClaveDuplicado($fecha, $ambito, $provincia, $poblacion);
        $clavesExistentes = importadorFestivosObtenerClavesExistentes($conn, $fecha, $ambito);
        return isset($clavesExistentes[$clave]);
    }
}

if (!function_exists('importadorFestivosInsertarRegistro')) {
    function importadorFestivosInsertarRegistro(
        $conn,
        string $fecha,
        string $ambito,
        ?string $codMunicipioIne,
        ?string $provincia,
        ?string $poblacion,
        ?string $descripcion
    ): bool {
        $sql = "
            INSERT INTO cmf_comerciales_calendario_festivos
                (fecha, ambito, cod_municipio_ine, provincia, poblacion, descripcion, origen, repetir_anualmente)
            VALUES (?, ?, ?, ?, ?, ?, 'JSON_JUNTA_ANDALUCIA', 0)
        ";
        $stmt = odbc_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        $ambitoDb = mb_convert_encoding($ambito, 'Windows-1252', 'UTF-8');
        $provinciaDb = $provincia !== null ? mb_convert_encoding($provincia, 'Windows-1252', 'UTF-8') : null;
        $poblacionDb = $poblacion !== null ? mb_convert_encoding($poblacion, 'Windows-1252', 'UTF-8') : null;
        $descripcionDb = $descripcion !== null ? mb_convert_encoding($descripcion, 'Windows-1252', 'UTF-8') : null;

        return odbc_execute($stmt, [$fecha, $ambitoDb, $codMunicipioIne, $provinciaDb, $poblacionDb, $descripcionDb]);
    }
}

if (!function_exists('importarFestivosAndaluciaDesdeArray')) {
    function importarFestivosAndaluciaProcesarLote(array $registros, int $offset = 0, ?int $limite = null): array
    {
        $offset = max(0, $offset);
        $registrosLote = $limite === null ? array_slice($registros, $offset) : array_slice($registros, $offset, max(0, $limite));
        $resultado = [
            'total' => count($registros),
            'offset' => $offset,
            'procesados' => count($registrosLote),
            'insertados' => 0,
            'duplicados' => 0,
            'errores' => [],
        ];

        $conn = db();
        $vistosEnLote = [];

        foreach ($registrosLote as $indiceLocal => $registro) {
            $indice = $offset + $indiceLocal;
            if (!is_array($registro)) {
                $resultado['errores'][] = 'Registro ' . $indice . ': formato no valido.';
                continue;
            }

            $fecha = importadorFestivosConvertirFechaJson($registro['date'] ?? null);
            $ambito = importadorFestivosResolverAmbito($registro['type'] ?? null);
            $provincia = importadorFestivosTextoUtf8((string)($registro['province'] ?? ''));
            $poblacion = importadorFestivosTextoUtf8((string)($registro['municipality'] ?? ''));
            $descripcion = importadorFestivosTextoUtf8((string)($registro['description'] ?? ''));

            $provincia = $provincia !== '' ? $provincia : null;
            $poblacion = $poblacion !== '' ? $poblacion : null;
            $descripcion = $descripcion !== '' ? $descripcion : null;

            if ($fecha === null) {
                $resultado['errores'][] = 'Registro ' . $indice . ': fecha invalida.';
                continue;
            }

            if ($ambito === null) {
                $resultado['errores'][] = 'Registro ' . $indice . ': type no soportado.';
                continue;
            }

            $claveDuplicado = importadorFestivosClaveDuplicado($fecha, $ambito, $provincia, $poblacion);
            if (isset($vistosEnLote[$claveDuplicado])) {
                $resultado['duplicados']++;
                continue;
            }

            $vistosEnLote[$claveDuplicado] = true;

            $codMunicipioIne = null;
            if ($poblacion !== null) {
                $codMunicipioIne = importadorFestivosBuscarCodMunicipioIne($conn, $poblacion, $provincia);
            }

            if (importadorFestivosExisteRegistro($conn, $fecha, $ambito, $provincia, $poblacion)) {
                $resultado['duplicados']++;
                continue;
            }

            if (!importadorFestivosInsertarRegistro($conn, $fecha, $ambito, $codMunicipioIne, $provincia, $poblacion, $descripcion)) {
                $resultado['errores'][] = 'Registro ' . $indice . ': error al insertar.';
                continue;
            }

            importadorFestivosRegistrarClaveExistente($fecha, $ambito, $provincia, $poblacion);
            $resultado['insertados']++;
        }

        return $resultado;
    }
}

if (!function_exists('importarFestivosAndaluciaDesdeArray')) {
    function importarFestivosAndaluciaDesdeArray(array $registros): array
    {
        return importarFestivosAndaluciaProcesarLote($registros, 0, null);
    }
}

if (!function_exists('importadorFestivosCargarJsonFile')) {
    function importadorFestivosCargarJsonFile(string $rutaJson): array
    {
        if (!is_file($rutaJson)) {
            return [
                'ok' => false,
                'registros' => [],
                'errores' => ['No existe el archivo JSON: ' . $rutaJson],
            ];
        }

        $contenido = @file_get_contents($rutaJson);
        if ($contenido === false) {
            return [
                'ok' => false,
                'registros' => [],
                'errores' => ['No se pudo leer el archivo JSON: ' . $rutaJson],
            ];
        }

        $datos = json_decode($contenido, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_array($datos)) {
            return [
                'ok' => false,
                'registros' => [],
                'errores' => ['El archivo JSON no contiene un array valido.'],
            ];
        }

        return [
            'ok' => true,
            'registros' => $datos,
            'errores' => [],
        ];
    }
}

if (!function_exists('importadorFestivosCargarJsonUrl')) {
    function importadorFestivosCargarJsonUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return [
                'ok' => false,
                'registros' => [],
                'errores' => ['La URL de importacion no es valida.'],
            ];
        }

        $contenido = false;

        if (function_exists('curl_init')) {
            $opcionesCurl = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, $opcionesCurl);

            $contenido = curl_exec($ch);
            $codigoHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errorCurl = curl_error($ch);
            $errorSsl = $contenido === false && stripos($errorCurl, 'certificate') !== false;

            if ($errorSsl) {
                curl_setopt_array($ch, [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $contenido = curl_exec($ch);
                $codigoHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $errorCurl = curl_error($ch);
            }

            curl_close($ch);

            if ($contenido === false || $codigoHttp >= 400) {
                return [
                    'ok' => false,
                    'registros' => [],
                    'errores' => ['No se pudo descargar el JSON desde la API: ' . ($errorCurl !== '' ? $errorCurl : ('HTTP ' . $codigoHttp))],
                ];
            }
        } else {
            $contexto = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 60,
                    'header' => "Accept: application/json\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $contenido = @file_get_contents($url, false, $contexto);
            if ($contenido === false) {
                return [
                    'ok' => false,
                    'registros' => [],
                    'errores' => ['No se pudo descargar el JSON desde la API.'],
                ];
            }
        }

        $datos = json_decode((string)$contenido, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_array($datos)) {
            return [
                'ok' => false,
                'registros' => [],
                'errores' => ['La API no devolvio un array JSON valido.'],
            ];
        }

        return [
            'ok' => true,
            'registros' => $datos,
            'errores' => [],
        ];
    }
}

if (!function_exists('importadorFestivosSesionClaveApi')) {
    function importadorFestivosSesionClaveApi(): string
    {
        return 'importador_festivos_andalucia_api';
    }
}

if (!function_exists('importadorFestivosLimpiarCacheApiSesion')) {
    function importadorFestivosLimpiarCacheApiSesion(): void
    {
        unset($_SESSION[importadorFestivosSesionClaveApi()]);
    }
}

if (!function_exists('importadorFestivosObtenerRegistrosApiSesion')) {
    function importadorFestivosObtenerRegistrosApiSesion(string $url, bool $forzarRecarga = false): array
    {
        $clave = importadorFestivosSesionClaveApi();

        if ($forzarRecarga) {
            importadorFestivosLimpiarCacheApiSesion();
        }

        if (
            !$forzarRecarga &&
            isset($_SESSION[$clave]) &&
            is_array($_SESSION[$clave]) &&
            (string)($_SESSION[$clave]['url'] ?? '') === $url &&
            is_array($_SESSION[$clave]['registros'] ?? null)
        ) {
            return [
                'ok' => true,
                'registros' => $_SESSION[$clave]['registros'],
                'errores' => [],
            ];
        }

        $carga = importadorFestivosCargarJsonUrl($url);
        if (!$carga['ok']) {
            return $carga;
        }

        $_SESSION[$clave] = [
            'url' => $url,
            'registros' => $carga['registros'],
            'cargado_en' => date('Y-m-d H:i:s'),
        ];

        return $carga;
    }
}

if (!function_exists('importarFestivosAndaluciaDesdeJsonFile')) {
    function importarFestivosAndaluciaDesdeJsonFile(string $rutaJson): array
    {
        $carga = importadorFestivosCargarJsonFile($rutaJson);
        if (!$carga['ok']) {
            return [
                'total' => 0,
                'offset' => 0,
                'procesados' => 0,
                'insertados' => 0,
                'duplicados' => 0,
                'errores' => $carga['errores'],
            ];
        }

        return importarFestivosAndaluciaDesdeArray($carga['registros']);
    }
}

if (!function_exists('importarFestivosAndaluciaDesdeJsonFilePorLotes')) {
    function importarFestivosAndaluciaDesdeJsonFilePorLotes(string $rutaJson, int $offset = 0, int $limite = 100): array
    {
        $carga = importadorFestivosCargarJsonFile($rutaJson);
        if (!$carga['ok']) {
            return [
                'ok' => false,
                'total' => 0,
                'offset' => $offset,
                'procesados' => 0,
                'insertados' => 0,
                'duplicados' => 0,
                'errores' => $carga['errores'],
                'done' => true,
                'next_offset' => $offset,
            ];
        }

        $resultado = importarFestivosAndaluciaProcesarLote($carga['registros'], $offset, $limite);
        $siguienteOffset = $offset + (int)$resultado['procesados'];

        return [
            'ok' => true,
            'total' => (int)$resultado['total'],
            'offset' => (int)$resultado['offset'],
            'procesados' => (int)$resultado['procesados'],
            'insertados' => (int)$resultado['insertados'],
            'duplicados' => (int)$resultado['duplicados'],
            'errores' => $resultado['errores'],
            'done' => $siguienteOffset >= (int)$resultado['total'],
            'next_offset' => $siguienteOffset,
        ];
    }
}

if (!function_exists('importarFestivosAndaluciaDesdeApiPorLotes')) {
    function importarFestivosAndaluciaDesdeApiPorLotes(string $url, int $offset = 0, int $limite = 100): array
    {
        $offset = max(0, $offset);
        $limite = max(1, min(500, $limite));

        $carga = importadorFestivosObtenerRegistrosApiSesion($url, $offset === 0);
        if (!$carga['ok']) {
            importadorFestivosLimpiarCacheApiSesion();
            return [
                'ok' => false,
                'total' => 0,
                'offset' => $offset,
                'procesados' => 0,
                'insertados' => 0,
                'duplicados' => 0,
                'errores' => $carga['errores'],
                'done' => true,
                'next_offset' => $offset,
            ];
        }

        $resultado = importarFestivosAndaluciaProcesarLote($carga['registros'], $offset, $limite);
        $siguienteOffset = $offset + (int)$resultado['procesados'];
        $done = $siguienteOffset >= (int)$resultado['total'];

        if ($done) {
            importadorFestivosLimpiarCacheApiSesion();
        }

        return [
            'ok' => true,
            'total' => (int)$resultado['total'],
            'offset' => (int)$resultado['offset'],
            'procesados' => (int)$resultado['procesados'],
            'insertados' => (int)$resultado['insertados'],
            'duplicados' => (int)$resultado['duplicados'],
            'errores' => $resultado['errores'],
            'done' => $done,
            'next_offset' => $siguienteOffset,
        ];
    }
}
